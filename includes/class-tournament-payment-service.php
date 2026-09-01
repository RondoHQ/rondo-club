<?php
/**
 * Tournament invoice and payment-link orchestration.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Fields\Fields;
use Rondo\Finance\FinanceServices;
use Rondo\Finance\InvoiceNumbering;
use Rondo\Finance\MolliePayment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentPaymentService {

	private MolliePayment $mollie_payment;

	/** @var callable */
	private $account_resolver;

	/**
	 * @param MolliePayment|null $mollie_payment Payment-link service.
	 * @param callable|null      $account_resolver Returns a payment-account snapshot.
	 */
	public function __construct( ?MolliePayment $mollie_payment = null, ?callable $account_resolver = null ) {
		$this->mollie_payment   = $mollie_payment ?? new MolliePayment();
		$this->account_resolver = $account_resolver ?? static function () {
			$mollie = FinanceServices::mollie();
			if ( $mollie->get_active_payment_provider() !== 'mollie' ) {
				return new \WP_Error( 'rondo_tournament_mollie_required', __( 'Activeer Mollie voordat je toernooibetalingen gebruikt.', 'rondo' ), [ 'status' => 400 ] );
			}
			return $mollie->get_payment_account_snapshot_for_invoice_type( 'tournament' );
		};
	}

	/** Validate that publishing can create tournament payment links. */
	public function validate_configuration() {
		$account = ( $this->account_resolver )();
		if ( is_wp_error( $account ) ) {
			return new \WP_Error(
				'rondo_tournament_payment_account_required',
				__( 'Kies eerst onder Instellingen → Koppelingen → Betaalproviders een standaard Mollie-rekening voor toernooien.', 'rondo' ),
				[ 'status' => 409 ]
			);
		}
		if ( ! is_array( $account ) || empty( $account['id'] ) ) {
			return new \WP_Error( 'rondo_tournament_payment_account_required', __( 'De standaard Mollie-rekening voor toernooien is niet bruikbaar.', 'rondo' ), [ 'status' => 409 ] );
		}
		return $account;
	}

	/** Create or reuse the one invoice and payment link for a submitted entry. */
	public function ensure_payment( int $entry_id, int $actor_user_id ) {
		$entry = get_post( $entry_id );
		if ( ! $entry || $entry->post_type !== TournamentService::ENTRY_POST_TYPE || $entry->post_status === 'trash' ) {
			return new \WP_Error( 'rondo_tournament_entry_not_found', __( 'Inschrijfopdracht niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		$fields = Fields::all_for_post( $entry_id );
		if ( (string) ( $fields['registration_status'] ?? 'open' ) !== 'submitted' ) {
			return new \WP_Error( 'rondo_tournament_entry_not_submitted', __( 'Bevestig de inschrijving voordat je de betaling start.', 'rondo' ), [ 'status' => 409 ] );
		}

		$total_amount = (float) ( $fields['total_amount'] ?? 0 );
		if ( $total_amount <= 0 ) {
			Fields::update_for_post( $entry_id, 'payment_state', 'not_applicable' );
			delete_post_meta( $entry_id, '_tournament_payment_error' );
			TournamentPaymentRetryScheduler::clear( $entry_id );
			return $this->payment_summary( $entry_id, $fields );
		}

		$summary = $this->payment_summary( $entry_id, $fields );
		if ( in_array( $summary['payment_state'], [ 'open', 'paid' ], true ) ) {
			if ( $summary['payment_state'] === 'open' ) {
				TournamentPaymentEmail::send_initial( $entry_id );
			}
			return $summary;
		}

		$lock = $this->acquire_lock( $entry_id );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}

		try {
			Fields::update_for_post( $entry_id, 'payment_state', 'creating' );
			delete_post_meta( $entry_id, '_tournament_payment_error' );

			$invoice_id = $this->find_invoice_id( $entry_id, $fields );
			if ( $invoice_id <= 0 ) {
				$account = ( $this->account_resolver )();
				if ( is_wp_error( $account ) ) {
					$this->record_error( $entry_id, $account );
					return $account;
				}

				$invoice_id = $this->create_invoice( $entry_id, $fields, $actor_user_id, $account );
				if ( is_wp_error( $invoice_id ) ) {
					$this->record_error( $entry_id, $invoice_id );
					return $invoice_id;
				}
			} else {
				$account_result = $this->ensure_invoice_account( $invoice_id );
				if ( is_wp_error( $account_result ) ) {
					$this->record_error( $entry_id, $account_result );
					return $account_result;
				}
			}

			Fields::update_for_post( $entry_id, 'invoice_id', $invoice_id );
			$link = $this->mollie_payment->create_payment_link( $invoice_id );
			if ( is_wp_error( $link ) ) {
				$this->record_error( $entry_id, $link );
				return $link;
			}

			if ( get_post_status( $invoice_id ) !== 'rondo_paid' ) {
				wp_update_post(
					[
						'ID'          => $invoice_id,
						'post_status' => 'rondo_sent',
					]
				);
				Fields::update_many_for_post(
					$invoice_id,
					[
						'sent_date' => current_time( 'Ymd' ),
						'status'    => 'sent',
					]
				);
				if ( $actor_user_id > 0 && ! get_post_meta( $invoice_id, '_invoice_sent_by_user_id', true ) ) {
					update_post_meta( $invoice_id, '_invoice_sent_by_user_id', $actor_user_id );
				}
			}

			Fields::update_for_post( $entry_id, 'payment_state', 'open' );
			delete_post_meta( $entry_id, '_tournament_payment_error' );
			TournamentPaymentRetryScheduler::clear( $entry_id );
			$summary = $this->payment_summary( $entry_id );
			TournamentActivityLog::record( $entry_id, 'payment_created', $actor_user_id, [ 'invoice_id' => $invoice_id ] );
			TournamentPaymentEmail::send_initial( $entry_id );
			return $summary;
		} finally {
			delete_post_meta( $entry_id, '_tournament_payment_lock' );
		}
	}

	/** Return the permission-safe payment state derived from the linked invoice. */
	public function payment_summary( int $entry_id, ?array $entry_fields = null ): array {
		$fields      = $entry_fields ?? Fields::all_for_post( $entry_id );
		$submitted   = (string) ( $fields['registration_status'] ?? 'open' ) === 'submitted';
		$total       = (float) ( $fields['total_amount'] ?? 0 );
		$invoice_id  = $this->find_invoice_id( $entry_id, $fields );
		$error       = (string) get_post_meta( $entry_id, '_tournament_payment_error', true );
		$state       = $submitted && $total > 0 ? 'error' : 'not_applicable';
		$payment_url = null;
		$paid_at     = null;

		if ( $invoice_id > 0 ) {
			$invoice_status = (string) Fields::get_for_post( $invoice_id, 'status' );
			$post_status    = (string) get_post_status( $invoice_id );
			if ( $invoice_status === 'paid' || $post_status === 'rondo_paid' ) {
				$state   = 'paid';
				$paid_at = (string) get_post_meta( $invoice_id, '_mollie_paid_at', true ) ?: null;
			} elseif ( $invoice_status === 'cancelled' || $post_status === 'rondo_cancelled' ) {
				$state = 'expired';
			} else {
				$link = (string) Fields::get_for_post( $invoice_id, 'payment_link' );
				if ( $link !== '' ) {
					$state       = 'open';
					$payment_url = $link;
				} elseif ( (string) ( $fields['payment_state'] ?? '' ) === 'creating' ) {
					$state = 'creating';
				}
			}
		}

		return [
			'invoice_id'    => $invoice_id ?: null,
			'payment_state' => $state,
			'payment_url'   => $payment_url,
			'paid_at'       => $paid_at,
			'payment_error' => $state === 'error' ? ( $error ?: __( 'De betaallink is nog niet aangemaakt.', 'rondo' ) ) : null,
		];
	}

	/** Cancel an unpaid linked invoice before its tournament entry is trashed. */
	public function cancel_unpaid_payment( int $entry_id ) {
		TournamentPaymentRetryScheduler::clear( $entry_id );
		$invoice_id = $this->find_invoice_id( $entry_id );
		if ( $invoice_id <= 0 ) {
			return true;
		}
		if ( get_post_status( $invoice_id ) === 'rondo_paid' || Fields::get_for_post( $invoice_id, 'status' ) === 'paid' ) {
			return new \WP_Error( 'rondo_tournament_payment_already_paid', __( 'Een betaalde inschrijving kan niet worden heropend.', 'rondo' ), [ 'status' => 409 ] );
		}

		$this->mollie_payment->archive_payment_links( $invoice_id );
		wp_update_post(
			[
				'ID'          => $invoice_id,
				'post_status' => 'rondo_cancelled',
			]
		);
		Fields::update_many_for_post(
			$invoice_id,
			[
				'payment_link' => null,
				'status'       => 'cancelled',
			]
		);
		update_post_meta( $invoice_id, '_cancelled_at', current_time( 'mysql' ) );
		if ( get_current_user_id() > 0 ) {
			update_post_meta( $invoice_id, '_cancelled_by', get_current_user_id() );
		}
		delete_post_meta( $invoice_id, '_mollie_payment_link_id' );
		delete_post_meta( $invoice_id, '_rabobank_payment_request_id' );
		Fields::update_for_post( $entry_id, 'payment_state', 'expired' );
		return true;
	}

	private function create_invoice( int $entry_id, array $fields, int $actor_user_id, array $account ) {
		$tournament_id = (int) ( $fields['tournament_id'] ?? 0 );
		$tournament    = get_post( $tournament_id );
		$number        = InvoiceNumbering::generate_next( 'tournament' );
		$invoice_id    = wp_insert_post(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_draft',
				'post_title'  => $number,
				'post_author' => $actor_user_id,
			],
			true
		);
		if ( is_wp_error( $invoice_id ) ) {
			return $invoice_id;
		}

		$person_id = (int) ( $fields['contact_person_id'] ?? 0 );
		if ( get_post_type( $person_id ) !== 'person' ) {
			$person_id = (int) get_user_meta( $actor_user_id, 'rondo_linked_person_id', true );
			if ( get_post_type( $person_id ) !== 'person' ) {
				$person_id = 0;
			}
		}
		$team_count = (int) ( $fields['registered_team_count'] ?? 0 );
		$price      = (float) ( $fields['price_per_team'] ?? 0 );
		$team_name  = (string) ( $fields['team_name_snapshot'] ?? '' );
		$line_items = [];
		for ( $index = 1; $index <= $team_count; $index++ ) {
			$line_items[] = [
				'discipline_case' => null,
				'description'     => sprintf( 'Inschrijving %s · team %d', $team_name, $index ),
				'amount'          => $price,
			];
		}

		$payment_deadline = (string) Fields::get_for_post( $tournament_id, 'payment_deadline' );
		if ( $payment_deadline === '' ) {
			$payment_deadline = (string) Fields::get_for_post( $tournament_id, 'internal_deadline' );
		}
		Fields::update_many_for_post(
			(int) $invoice_id,
			[
				'due_date'       => $payment_deadline !== '' ? str_replace( '-', '', substr( $payment_deadline, 0, 10 ) ) : null,
				'invoice_number' => $number,
				'invoice_type'   => 'tournament',
				'line_items'     => $line_items,
				'person'         => $person_id ?: null,
				'status'         => 'draft',
				'total_amount'   => (float) ( $fields['total_amount'] ?? 0 ),
			]
		);

		$description = sprintf(
			'%s · %s · %d %s · %d spelers',
			$tournament ? $tournament->post_title : __( 'Toernooi', 'rondo' ),
			$team_name,
			$team_count,
			_n( 'team', 'teams', $team_count, 'rondo' ),
			(int) ( $fields['player_count'] ?? 0 )
		);
		update_post_meta( (int) $invoice_id, '_tournament_entry_id', $entry_id );
		update_post_meta( (int) $invoice_id, '_mollie_description', $description );
		update_post_meta( (int) $invoice_id, '_mollie_redirect_url', home_url( '/mijn-toernooien/' . $entry_id ) );
		update_post_meta( (int) $invoice_id, '_invoice_kind', 'normal' );
		update_post_meta( (int) $invoice_id, '_customer_name', (string) ( $fields['contact_name'] ?? '' ) );
		update_post_meta( (int) $invoice_id, '_customer_email', (string) ( $fields['contact_email'] ?? '' ) );
		$this->store_account_snapshot( (int) $invoice_id, $account );
		Fields::update_for_post( $entry_id, 'invoice_id', (int) $invoice_id );

		return (int) $invoice_id;
	}

	private function ensure_invoice_account( int $invoice_id ) {
		if ( (string) get_post_meta( $invoice_id, '_payment_account_id', true ) !== '' ) {
			return true;
		}
		$account = ( $this->account_resolver )();
		if ( is_wp_error( $account ) ) {
			return $account;
		}
		$this->store_account_snapshot( $invoice_id, $account );
		return true;
	}

	private function store_account_snapshot( int $invoice_id, array $account ): void {
		update_post_meta( $invoice_id, '_payment_account_id', (string) ( $account['id'] ?? '' ) );
		update_post_meta( $invoice_id, '_payment_account_internal_name', (string) ( $account['internal_name'] ?? '' ) );
		update_post_meta( $invoice_id, '_payment_account_account_holder', (string) ( $account['account_holder'] ?? '' ) );
		update_post_meta( $invoice_id, '_payment_account_iban', (string) ( $account['iban'] ?? '' ) );
		update_post_meta( $invoice_id, '_payment_account_linked_provider', (string) ( $account['linked_provider'] ?? 'mollie' ) );
	}

	private function find_invoice_id( int $entry_id, ?array $fields = null ): int {
		$fields     = $fields ?? Fields::all_for_post( $entry_id );
		$invoice_id = (int) ( $fields['invoice_id'] ?? 0 );
		if ( get_post_type( $invoice_id ) === 'rondo_invoice' && get_post_status( $invoice_id ) !== 'trash' ) {
			return $invoice_id;
		}

		$ids = get_posts(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'   => '_tournament_entry_id',
						'value' => $entry_id,
					],
				],
			]
		);
		foreach ( $ids as $id ) {
			if ( get_post_status( (int) $id ) !== 'rondo_cancelled' ) {
				return (int) $id;
			}
		}
		return 0;
	}

	private function record_error( int $entry_id, \WP_Error $error ): void {
		Fields::update_for_post( $entry_id, 'payment_state', 'error' );
		update_post_meta( $entry_id, '_tournament_payment_error', sanitize_text_field( $error->get_error_message() ) );
		TournamentActivityLog::record( $entry_id, 'payment_failed', get_current_user_id(), [ 'error_code' => $error->get_error_code() ] );
		TournamentPaymentRetryScheduler::schedule( $entry_id );
	}

	private function acquire_lock( int $entry_id ) {
		$now = time();
		if ( add_post_meta( $entry_id, '_tournament_payment_lock', $now, true ) ) {
			return true;
		}

		$existing = (int) get_post_meta( $entry_id, '_tournament_payment_lock', true );
		if ( $existing > 0 && $existing < $now - 60 ) {
			delete_post_meta( $entry_id, '_tournament_payment_lock', $existing );
			if ( add_post_meta( $entry_id, '_tournament_payment_lock', $now, true ) ) {
				return true;
			}
		}

		return new \WP_Error( 'rondo_tournament_payment_in_progress', __( 'De betaallink wordt al aangemaakt. Probeer het zo opnieuw.', 'rondo' ), [ 'status' => 409 ] );
	}
}
