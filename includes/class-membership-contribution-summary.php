<?php
/**
 * Client-safe contribution summaries for the personal household page.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\Fields\RestFields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Read the current-season membership invoice status for visible household members. */
final class MembershipContributionSummary {

	/**
	 * Return one contribution summary per person ID.
	 *
	 * Draft and cancelled invoices remain private to the finance workflow. The
	 * caller is responsible for passing only person IDs visible to the user.
	 *
	 * @param int[] $person_ids Visible person IDs.
	 * @return array<int,array<string,mixed>>
	 */
	public static function for_people( array $person_ids ): array {
		$person_ids = array_values( array_unique( array_filter( array_map( 'absint', $person_ids ) ) ) );
		if ( empty( $person_ids ) ) {
			return [];
		}

		$season   = SeasonKey::current( wp_date( 'Y-m-d' ) );
		$invoices = get_posts(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => [ 'rondo_sent', 'rondo_paid', 'rondo_overdue' ],
				'posts_per_page'   => -1,
				'orderby'          => [
					'date' => 'DESC',
					'ID'   => 'DESC',
				],
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'     => 'person',
						'value'   => $person_ids,
						'compare' => 'IN',
						'type'    => 'NUMERIC',
					],
					[
						'key'   => '_invoice_season',
						'value' => $season,
					],
					[
						'key'   => 'invoice_type',
						'value' => 'membership',
					],
				],
			]
		);

		$summaries = [];
		foreach ( $invoices as $invoice ) {
			$person_id = (int) Fields::get_for_post( $invoice->ID, 'person' );
			if ( $person_id <= 0 || isset( $summaries[ $person_id ] ) ) {
				continue;
			}

			$summaries[ $person_id ] = self::format_invoice( $invoice, $season );
		}

		return $summaries;
	}

	/** Format one current-season invoice without exposing internal finance data. */
	private static function format_invoice( \WP_Post $invoice, string $season ): array {
		$fields = RestFields::for_post_fields(
			'rondo_invoice',
			$invoice->ID,
			[ 'invoice_number', 'total_amount', 'due_date', 'payment_link' ]
		);
		$status = match ( $invoice->post_status ) {
			'rondo_paid'    => 'paid',
			'rondo_overdue' => 'overdue',
			default          => 'sent',
		};
		$plan  = (string) get_post_meta( $invoice->ID, '_installment_plan', true );
		$count = max( 0, (int) get_post_meta( $invoice->ID, '_installment_count', true ) );

		$paid_installments = 0;
		$next_installment  = null;
		for ( $number = 1; $number <= $count; $number++ ) {
			$installment_status = (string) get_post_meta( $invoice->ID, '_installment_' . $number . '_status', true );
			if ( $installment_status === 'betaald' ) {
				++$paid_installments;
				continue;
			}

			if ( $next_installment === null ) {
				$amount           = (float) get_post_meta( $invoice->ID, '_installment_' . $number . '_amount', true );
				$admin_fee        = (float) get_post_meta( $invoice->ID, '_installment_' . $number . '_admin_fee', true );
				$next_installment = [
					'number'      => $number,
					'amount'      => round( $amount + $admin_fee, 2 ),
					'due_date'    => self::format_date( (string) get_post_meta( $invoice->ID, '_installment_' . $number . '_due_date', true ) ),
					'sent'        => $installment_status === 'sent',
					'payment_url' => self::safe_url( (string) get_post_meta( $invoice->ID, '_installment_' . $number . '_payment_link', true ) ),
				];
			}
		}

		$payment_url = null;
		if ( $status !== 'paid' ) {
			$payment_url = $plan === ''
				? self::safe_url( (string) ( $fields['payment_link'] ?? '' ) )
				: ( $next_installment['payment_url'] ?? null );
		}

		return [
			'invoice_number'    => (string) ( $fields['invoice_number'] ?? '' ),
			'season'            => $season,
			'total_amount'      => (float) ( $fields['total_amount'] ?? 0 ),
			'status'            => $status,
			'due_date'          => $fields['due_date'] ?? null,
			'payment_url'       => $payment_url,
			'installment_plan'  => $plan !== '' ? $plan : null,
			'installment_count' => $count,
			'paid_installments' => $paid_installments,
			'next_installment'  => $next_installment,
		];
	}

	/** Normalize compact or canonical stored dates to YYYY-MM-DD. */
	private static function format_date( string $date ): ?string {
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date ) ) {
			return $date;
		}
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $date, $matches ) ) {
			return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
		}
		return null;
	}

	/** Return only valid HTTP(S) destinations. */
	private static function safe_url( string $url ): ?string {
		$url = esc_url_raw( $url, [ 'http', 'https' ] );
		return $url !== '' ? $url : null;
	}
}
