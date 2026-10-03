<?php
/** Linked credit drafts, with server-side amounts and replay protection. */
namespace Rondo\Finance;

use Rondo\Fields\Fields;

class CreditNotes {

	public static function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'credit_invalid', $message, [ 'status' => $status ] );
	}

	public static function linked( int $invoice_id ): array {
		$posts = get_posts(
			[
				'post_type'      => 'rondo_invoice',
				'post_status'    => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue', 'rondo_cancelled' ],
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'   => '_credit_source_invoice_id',
						'value' => $invoice_id,
					],
				],
			]
			);
		return array_map( [ PersonFinanceHistory::class, 'summary' ], $posts );
	}

	public static function cents( $value ): ?int {
		if ( ! is_scalar( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || abs( (float) $value ) > 1000000 ) {
			return null;
		}
		return (int) round( (float) $value * 100 );
	}

	public static function preview( array $input, int $exclude_id = 0 ) {
		if ( ( $input['mode'] ?? '' ) === 'injury' ) {
			return self::injury_preview( $input, $exclude_id );
		}
		$id   = absint( $input['source_invoice_id'] ?? 0 );
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'rondo_invoice' || ! in_array( $post->post_status, [ 'rondo_sent', 'rondo_paid', 'rondo_overdue' ], true ) || get_post_meta( $id, '_invoice_kind', true ) === 'credit' ) {
			return self::error( 'Kies een verstuurde, betaalde of achterstallige oorspronkelijke factuur.' );
		}
		$person_id = (int) Fields::get_for_post( $id, 'person' );
		if ( $person_id && ! \Rondo\Core\AccessControl::can_view_person( $person_id, get_current_user_id() ) ) {
			return self::error( 'Factuur niet toegankelijk.', 404 );
		}
		$reason   = sanitize_text_field( $input['reason'] ?? '' );
		$amount   = self::cents( $input['amount'] ?? null );
		$total    = self::cents( Fields::get_for_post( $id, 'total_amount' ) );
		$reserved = 0;
		foreach ( self::linked( $id ) as $credit ) {
			if ( $credit['status'] !== 'cancelled' && $credit['id'] !== $exclude_id ) {
				$reserved += abs( self::cents( $credit['total_amount'] ) );
			}
		}
		if ( $reason === '' || $amount === null || $amount <= 0 || $total === null || $amount > $total - $reserved ) {
			return self::error( 'Vul een reden en een positief bedrag in binnen het nog te crediteren bedrag.' );
		}
		return [
			'person_id'             => $person_id,
			'source_invoice_id'     => $id,
			'source_invoice_number' => Fields::get_for_post( $id, 'invoice_number' ),
			'season'                => PersonFinanceHistory::invoice_season( $id ),
			'reason'                => $reason,
			'amount'                => $amount / 100,
			'available'             => max( 0, $total - $reserved ) / 100,
			'line_items'            => [
				[
					'description' => 'Creditering ' . Fields::get_for_post( $id, 'invoice_number' ) . ': ' . $reason,
					'amount'      => -$amount / 100,
				],
			],
		];
	}

	/** Validate dates and conditions, then calculate solely from stored payment data. */
	private static function injury_preview( array $input, int $exclude_id = 0 ) {
		$person_id = absint( $input['person_id'] ?? 0 );
		$season    = is_string( $input['season'] ?? null ) ? $input['season'] : '';
		$history   = PersonFinanceHistory::get( $person_id, $season );
		if ( is_wp_error( $history ) ) {
			return $history;
		}
		$start = $input['injury_start'] ?? '';
		$end   = $input['injury_end'] ?? '';
		foreach ( [ $start, $end ] as $date ) {
			$parsed = is_string( $date ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $date ) : false;
			if ( ! $parsed || $parsed->format( 'Y-m-d' ) !== $date ) {
				return self::error( 'Vul een geldige begin- en einddatum van de blessureperiode in.' );
			}
		}
		$season_start = substr( $season, 0, 4 ) . '-07-01';
		$season_end   = substr( $season, 5, 4 ) . '-06-30';
		if ( $start < $season_start || $end > $season_end || $start > $end || $season_end >= wp_date( 'Y-m-d' ) ) {
			return self::error( 'De blessure moet in het gekozen seizoen zijn ontstaan. Beoordeel de regeling na afloop van dat seizoen.' );
		}
		$injury_days = ( new \DateTimeImmutable( $start ) )->diff( new \DateTimeImmutable( $end ) )->days + 1;
		$season_days = ( new \DateTimeImmutable( $season_start ) )->diff( new \DateTimeImmutable( $season_end ) )->days + 1;
		if ( $injury_days * 2 <= $season_days || ( $input['conditions_confirmed'] ?? false ) !== true ) {
			return self::error( 'De uitval moet meer dan een half seizoen duren en de trainer of coördinator moet de voorwaarden hebben bevestigd.' );
		}
		$total = self::cents( $history['contribution_total'] );
		$paid  = self::cents( $history['contribution_paid'] );
		if ( ! $total || $total <= 0 || $paid === null || $paid < $total ) {
			return self::error( 'De volledige seizoenscontributie moet aantoonbaar betaald zijn.' );
		}
		$percentage = $input['percentage'] ?? null;
		$costs      = self::cents( $input['costs'] ?? null );
		if ( ! in_array( $percentage, [ 25, 50, 75, 100 ], true ) || $costs === null || $costs < 5000 ) {
			return self::error( 'Kies 25, 50, 75 of 100 procent en vul minimaal €50 aan gemaakte kosten in.' );
		}
		$source_id     = absint( $input['source_invoice_id'] ?? 0 );
		$basis         = $total;
		$source_number = 'Nikki ' . $season;
		if ( $history['source'] === 'rondo' ) {
			$source = null;
			foreach ( $history['invoices'] as $invoice ) {
				if ( $invoice['id'] === $source_id && $invoice['invoice_type'] === 'membership' && $invoice['invoice_kind'] !== 'credit' && $invoice['status'] === 'paid' ) {
					$source = $invoice;
				}
			}
			if ( ! $source ) {
				return self::error( 'Selecteer een betaalde contributiefactuur van dit lid in dit seizoen.' );
			}
			$basis         = self::cents( $source['total_amount'] );
			$source_number = $source['invoice_number'];
		} elseif ( $source_id !== 0 || $history['source'] !== 'nikki' ) {
			return self::error( 'Geen geldige betaalde contributiebron beschikbaar.' );
		}
		$reserved = 0;
		foreach ( $history['invoices'] as $credit ) {
			if ( $credit['id'] === $exclude_id || $credit['status'] === 'cancelled' || $credit['invoice_kind'] !== 'credit' ) {
				continue;
			}
			$calculation = get_post_meta( $credit['id'], '_credit_calculation', true );
			if ( is_array( $calculation ) && ( $calculation['mode'] ?? '' ) === 'injury' ) {
				return self::error( 'Voor deze contributiebron bestaat al een blessurecreditnota. Open het bestaande concept of de bestaande creditnota.', 409 );
			}
			if ( $credit['source_invoice_id'] === $source_id ) {
				$reserved += abs( self::cents( $credit['total_amount'] ) );
			}
		}
		$gross  = (int) round( $basis * $percentage / 100 );
		$amount = max( 0, $gross - $costs );
		if ( $amount <= 0 || $amount > $basis - $reserved ) {
			return self::error( 'Deze berekening geeft geen positief beschikbaar creditbedrag. Controleer kosten en eerdere creditnota’s.' );
		}
		$reason = 'Restitutie contributie wegens langdurige blessure, seizoen ' . $season;
		return [
			'mode'                  => 'injury',
			'person_id'             => $person_id,
			'season'                => $season,
			'source_invoice_id'     => $source_id,
			'source_invoice_number' => $source_number,
			'reason'                => $reason,
			'amount'                => $amount / 100,
			'available'             => ( $basis - $reserved ) / 100,
			'paid_contribution'     => $basis / 100,
			'percentage'            => $percentage,
			'costs'                 => $costs / 100,
			'injury_start'          => $start,
			'injury_end'            => $end,
			'injury_days'           => $injury_days,
			'conditions_confirmed'  => true,
			'policy_url'            => 'https://www.svawc.nl/leden/blessures/',
			'line_items'            => [
				[
					'description' => 'Restitutie contributie ' . $season . ' (' . $percentage . '%) · ' . $source_number,
					'amount'      => -$gross / 100,
				],
				[
					'description' => 'Inhouding gemaakte kosten',
					'amount'      => $costs / 100,
				],
			],
		];
	}

	/** Recheck stored inputs just before sending, without reserving a draft twice. */
	public static function validate_draft( int $id ) {
		$input = get_post_meta( $id, '_credit_input', true );
		if ( ! is_array( $input ) ) {
			return true;
		}
		$result = self::preview( $input, $id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( self::cents( Fields::get_for_post( $id, 'total_amount' ) ) !== -self::cents( $result['amount'] ) || self::cents( array_sum( array_column( Fields::get_for_post( $id, 'line_items' ) ?: [], 'amount' ) ) ) !== -self::cents( $result['amount'] ) || get_post_meta( $id, '_invoice_kind', true ) !== 'credit' || (int) Fields::get_for_post( $id, 'person' ) !== $result['person_id'] ) {
			return self::error( 'De financiële gegevens zijn gewijzigd. Verwijder dit concept en bereken de creditnota opnieuw.', 409 );
		}
		return true;
	}

	public static function create( array $input ) {
		$key = $input['request_id'] ?? '';
		if ( ! is_string( $key ) || ! preg_match( '/^[a-zA-Z0-9-]{16,80}$/', $key ) ) {
			return self::error( 'Ongeldige aanvraagcode. Vernieuw de pagina.' );
		}
		// Kernel locks release on process exit; an expired lease can never admit two writers.
		$path = get_temp_dir() . 'rondo-credit-' . md5( ABSPATH ) . '.lock';
		$lock = fopen( $path, 'c' );
		if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( $lock ) {
				fclose( $lock );
			}
			return self::error( 'Er wordt al een creditnota verwerkt. Probeer het opnieuw.', 409 );
		}
		try {
			$fingerprint = hash( 'sha256', wp_json_encode( $input ) . ':' . get_current_user_id() );
			$existing    = get_posts(
				[
					'post_type'      => 'rondo_invoice',
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'meta_key'       => '_credit_request_id',
					'meta_value'     => $key,
				]
				);
			if ( $existing ) {
				if ( get_post_meta( $existing[0]->ID, '_credit_request_fingerprint', true ) !== $fingerprint ) {
					return self::error( 'Deze aanvraagcode is al voor andere gegevens gebruikt.', 409 );
				}
				return [ 'id' => $existing[0]->ID ];
			}
			$calculation = self::preview( $input );
			if ( is_wp_error( $calculation ) ) {
				return $calculation;
			}

			if ( isset( $input['expected_amount'] ) && self::cents( $input['expected_amount'] ) !== self::cents( $calculation['amount'] ) ) {
				return self::error( 'Het bedrag is gewijzigd sinds de controle. Bereken de creditnota opnieuw.', 409 );
			}
			$request = new \WP_REST_Request( 'POST' );
			$request->set_param( 'person_id', $calculation['person_id'] );
			$request->set_param( 'invoice_type', 'manual' );
			$request->set_param( 'invoice_kind', 'credit' );
			$request->set_param( 'line_items', $calculation['line_items'] );
			$request->set_param(
				'custom_fields',
				[
					[
						'label' => $calculation['source_invoice_id'] ? 'Oorspronkelijke factuur' : 'Contributiebron',
						'text'  => $calculation['source_invoice_number'],
					],
				]
				);
			foreach ( [ 'customer_name', 'customer_attention', 'customer_email', 'customer_cc_email', 'customer_address' ] as $field ) {
				$request->set_param( $field, $calculation['source_invoice_id'] ? get_post_meta( $calculation['source_invoice_id'], '_' . $field, true ) : '' );
			}
			$result = ( new \Rondo\REST\Invoices() )->create_invoice( $request );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$id = (int) $result->get_data()['id'];
			update_post_meta( $id, '_credit_source_invoice_id', $calculation['source_invoice_id'] );
			update_post_meta( $id, '_credit_reason', $calculation['reason'] );
			update_post_meta( $id, '_credit_calculation', $calculation );
			update_post_meta( $id, '_credit_input', $input );
			update_post_meta( $id, '_credit_created_by', get_current_user_id() );
			update_post_meta( $id, '_invoice_season', $calculation['season'] );
			update_post_meta( $id, '_credit_request_fingerprint', $fingerprint );
			update_post_meta( $id, '_credit_request_id', $key );
			return [ 'id' => $id ];
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}
}
