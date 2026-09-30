<?php
/**
 * Monthly businessclub invoicing from Twelve reports.
 *
 * Aggregates the "Businessclub" category rows of one calendar month and
 * creates a draft `rondo_invoice` (type "manual") with one line per day
 * plus a VAT specification line. Sending the invoice stays a manual step
 * in Rondo: the recipient is completed there before sending.
 */

namespace Rondo\Twelve;

use Rondo\Finance\InvoiceNumbering;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BusinessclubInvoicing {

	/** Meta key linking an invoice to its businessclub month. */
	public const META_MONTH = '_twelve_businessclub_month';

	/**
	 * Create a draft invoice for one month's businessclub turnover.
	 *
	 * @param string $month Month in "YYYY-MM" format.
	 * @return int|\WP_Error Invoice post id.
	 */
	public function create_draft_invoice( string $month ) {
		if ( ! (bool) preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ) {
			return new \WP_Error( 'twelve_bad_month', 'Maand moet in formaat JJJJ-MM zijn, bijvoorbeeld 2026-09.' );
		}

		$existing = $this->find_invoice_for_month( $month );
		if ( $existing !== null ) {
			return new \WP_Error(
				'twelve_duplicate_invoice',
				sprintf( 'Er bestaat al een businessclub-factuur voor %s (factuur %d).', $month, $existing ),
				[ 'post_id' => $existing ]
			);
		}

		$repository = new ReportRepository();
		$reports    = $repository->query( $month . '-01', $this->last_day( $month ) );
		$days       = ReportAggregator::businessclub_days( $reports );
		$days       = array_values(
			array_filter(
				$days,
				static fn( array $day ): bool => $day['bedrag'] > 0
			)
		);

		if ( empty( $days ) ) {
			return new \WP_Error(
				'twelve_nothing_to_invoice',
				sprintf( 'Geen businessclub-omzet gevonden voor %s; geen factuur aangemaakt.', $month )
			);
		}

		$month_label = $this->month_label( $month );
		$line_items  = [];
		$total_netto = 0.0;
		$total_btw   = 0.0;

		foreach ( $days as $day ) {
			$date_label   = (string) \DateTime::createFromFormat( 'Y-m-d', $day['datum'] )->format( 'd-m-Y' );
			$line_items[] = [
				'discipline_case' => null,
				'description'     => sprintf( 'Businessclub omzet %s (kantine)', $date_label ),
				'amount'          => $day['netto'],
			];
			$total_netto  = round( $total_netto + $day['netto'], 2 );
			$total_btw    = round( $total_btw + $day['btw'], 2 );
		}

		$line_items[] = [
			'discipline_case' => null,
			'description'     => sprintf( 'BTW 9%% (laag tarief) over businessclub omzet %s', $month_label ),
			'amount'          => $total_btw,
		];

		$total_amount   = round( $total_netto + $total_btw, 2 );
		$invoice_number = InvoiceNumbering::generate_next( 'manual' );

		$post_id = wp_insert_post(
			[
				'post_type'   => 'rondo_invoice',
				'post_title'  => $invoice_number,
				'post_status' => 'rondo_draft',
				'post_author' => 0,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		\Rondo\Fields\Fields::update_for_post( $post_id, 'invoice_number', $invoice_number );
		\Rondo\Fields\Fields::update_for_post( $post_id, 'status', 'draft' );
		\Rondo\Fields\Fields::update_for_post( $post_id, 'invoice_type', 'manual' );
		\Rondo\Fields\Fields::update_for_post( $post_id, 'total_amount', $total_amount );
		\Rondo\Fields\Fields::update_for_post( $post_id, 'line_items', $line_items );
		update_post_meta( $post_id, self::META_MONTH, $month );

		return $post_id;
	}

	/**
	 * Find an existing businessclub invoice for a month.
	 */
	public function find_invoice_for_month( string $month ): ?int {
		$posts = get_posts(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'   => self::META_MONTH,
						'value' => $month,
					],
				],
			]
		);

		return empty( $posts ) ? null : (int) $posts[0];
	}

	/**
	 * Last calendar day of a YYYY-MM month.
	 */
	private function last_day( string $month ): string {
		return (string) ( new \DateTime( $month . '-01' ) )->format( 'Y-m-t' );
	}

	/**
	 * Dutch month label, e.g. "september 2026".
	 */
	private function month_label( string $month ): string {
		$names          = [
			'01' => 'januari',
			'02' => 'februari',
			'03' => 'maart',
			'04' => 'april',
			'05' => 'mei',
			'06' => 'juni',
			'07' => 'juli',
			'08' => 'augustus',
			'09' => 'september',
			'10' => 'oktober',
			'11' => 'november',
			'12' => 'december',
		];
		[ $year, $num ] = explode( '-', $month );
		return ( $names[ $num ] ?? $month ) . ' ' . $year;
	}
}
