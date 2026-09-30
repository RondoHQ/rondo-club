<?php
/** Businessclub invoice allocation, using native WordPress metadata. */
namespace Rondo\Twelve;

use Rondo\Fields\Fields;
use Rondo\Finance\InvoiceNumbering;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BusinessclubInvoicing {
	public const META_MONTH = '_twelve_businessclub_month';
	public const CLAIM      = '_twelve_businessclub_invoice_id';
	public const BILLED     = '_twelve_businessclub_billed_invoice_id';
	public const REPORTS    = '_twelve_report_ids';

	public function __construct() {
		add_action( 'transition_post_status', [ self::class, 'record_sent' ], 10, 3 );
	}

	/** A successfully sent invoice permanently consumes its original reports. */
	public static function record_sent( $new_status, $old_status, $post ): void {
		if ( $new_status !== 'rondo_sent' || $post->post_type !== 'rondo_invoice' ) {
			return;
		}
		foreach ( (array) get_post_meta( $post->ID, self::REPORTS, true ) as $id ) {
			update_post_meta( (int) $id, self::BILLED, $post->ID );
		}
	}

	public function overview(): array {
		$rows      = [];
		$total     = 0.0;
		$available = 0.0;
		foreach ( ( new ReportRepository() )->query( '1970-01-01', '9999-12-31' ) as $report ) {
			$days = ReportAggregator::businessclub_days( [ $report ] );
			if ( ! $days || (float) $days[0]['bedrag'] === 0.0 ) {
				continue;
			}
			$day     = $days[0];
			$claim   = (int) get_post_meta( $report['id'], self::CLAIM, true );
			$billed  = (int) get_post_meta( $report['id'], self::BILLED, true );
			$invoice = $claim ? get_post( $claim ) : null;
			$status  = $billed || ( $invoice && in_array( $invoice->post_status, [ 'rondo_sent', 'rondo_paid', 'rondo_overdue' ], true ) ) ? 'billed' : ( $invoice && $invoice->post_status === 'rondo_draft' ? 'draft' : 'available' );
			if ( $status !== 'billed' ) {
				$total = round( $total + $day['bedrag'], 2 );
			}
			if ( $status === 'available' ) {
				$available = round( $available + $day['bedrag'], 2 );
			}
			$rows[] = array_merge(
				$day,
				[
					'report_id'  => $report['id'],
					'status'     => $status,
					'invoice_id' => $billed ?: $claim,
				]
				);
		}
		$history = [];
		foreach ( get_posts(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => 'any',
				'numberposts'      => -1,
				'meta_key'         => self::REPORTS,
				'suppress_filters' => true,
			]
			) as $post ) {
			$history[] = [
				'id'        => $post->ID,
				'number'    => Fields::get_for_post( $post->ID, 'invoice_number' ),
				'status'    => $post->post_status,
				'amount'    => (float) Fields::get_for_post( $post->ID, 'total_amount' ),
				'recipient' => (string) get_post_meta( $post->ID, '_customer_name', true ),
				'email'     => (string) get_post_meta( $post->ID, '_customer_email', true ),
				'sent_date' => Fields::get_for_post( $post->ID, 'sent_date' ),
			];
		}
		return [
			'total'     => $total,
			'available' => $available,
			'rows'      => $rows,
			'invoices'  => $history,
		];
	}

	/** Freeze only the available source reports, protected by an atomic option lock. */
	public function create_draft_invoice( string $month = '', array $recipient = [] ) {
		if ( $month !== '' && ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			return new \WP_Error( 'twelve_bad_month', 'Ongeldige maand.' );
		}
		if ( ! add_option( 'rondo_twelve_invoice_lock', wp_generate_uuid4(), '', false ) ) {
			return new \WP_Error( 'twelve_invoice_busy', 'Er wordt al een factuur aangemaakt. Probeer opnieuw.', [ 'status' => 409 ] );
		}
		try {
			$overview = $this->overview();
			$days     = array_values( array_filter( $overview['rows'], static fn( $day ) => $day['status'] === 'available' && ( $month === '' || str_starts_with( $day['datum'], $month ) ) ) );
			if ( ! $days ) {
				$code = $month !== '' && $this->find_invoice_for_month( $month ) ? 'twelve_duplicate_invoice' : 'twelve_nothing_to_invoice';
				return new \WP_Error( $code, 'Geen ongefactureerde businessclub-omzet beschikbaar.', [ 'status' => 409 ] );
			}
			$items = [];
			$vat   = 0.0;
			$total = 0.0;
			foreach ( $days as $day ) {
				$items[] = [
					'discipline_case' => null,
					'description'     => 'Businessclub omzet ' . $day['datum'] . ' (kantine)',
					'amount'          => $day['netto'],
				];
				$vat     = round( $vat + $day['btw'], 2 );
				$total   = round( $total + $day['netto'] + $day['btw'], 2 );
			}
			if ( $total <= 0 ) {
				return new \WP_Error( 'twelve_nothing_to_invoice', 'Het netto factuurbedrag moet positief zijn.', [ 'status' => 409 ] );
			}
			$items[] = [
				'discipline_case' => null,
				'description'     => 'Btw volgens Twelve dagrapportages',
				'amount'          => $vat,
			];
			$number  = InvoiceNumbering::generate_next( 'manual' );
			$id      = wp_insert_post(
				[
					'post_type'   => 'rondo_invoice',
					'post_title'  => $number,
					'post_status' => 'rondo_draft',
					'post_author' => get_current_user_id(),
				],
				true
				);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			foreach ( [
				'invoice_number' => $number,
				'status'         => 'draft',
				'invoice_type'   => 'manual',
				'total_amount'   => $total,
				'line_items'     => $items,
			] as $key => $value ) {
				Fields::update_for_post( $id, $key, $value );
			}
			update_post_meta( $id, '_invoice_kind', 'normal' );
			update_post_meta( $id, '_twelve_total_amount', $total );
			update_post_meta( $id, self::META_MONTH, $month );
			update_post_meta( $id, self::REPORTS, array_column( $days, 'report_id' ) );
			foreach ( [ 'name', 'address', 'email' ] as $field ) {
				update_post_meta( $id, '_customer_' . $field, $recipient[ $field ] ?? '' );
			}
			foreach ( $days as $day ) {
				update_post_meta( $day['report_id'], self::CLAIM, $id );
			}
			$stored   = get_post_meta( $id, self::REPORTS, true );
			$complete = $stored === array_column( $days, 'report_id' )
				&& abs( (float) Fields::get_for_post( $id, 'total_amount' ) - $total ) < 0.005;
			foreach ( $days as $day ) {
				$complete = $complete && (int) get_post_meta( $day['report_id'], self::CLAIM, true ) === $id;
			}
			if ( ! $complete ) {
				wp_delete_post( $id, true );
				return new \WP_Error( 'twelve_invoice_storage', 'Concept opslaan mislukt; de omzet blijft beschikbaar.', [ 'status' => 500 ] );
			}
			return $id;
		} finally {
			delete_option( 'rondo_twelve_invoice_lock' );
		}
	}

	public function find_invoice_for_month( string $month ): ?int {
		$ids = get_posts(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => self::META_MONTH,
				'meta_value'       => $month,
			]
			);
		return $ids ? (int) $ids[0] : null;
	}
}
