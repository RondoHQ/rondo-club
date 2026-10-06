<?php
/**
 * Invoice overdue status based on the applicable payment schedule.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Fields\Fields;

/** Keeps open invoice statuses consistent with unpaid installment due dates. */
class InvoiceOverdueStatus {

	/**
	 * Refresh one open invoice without changing payments or sending emails.
	 *
	 * @param int $invoice_id Invoice post ID.
	 */
	public static function refresh( int $invoice_id ): void {
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice'
			|| ! in_array( $invoice->post_status, [ 'rondo_sent', 'rondo_overdue' ], true )
			|| get_post_meta( $invoice_id, '_invoice_kind', true ) === 'credit' ) {
			return;
		}

		$status = substr( $invoice->post_status, strlen( 'rondo_' ) );
		$plan   = get_post_meta( $invoice_id, '_installment_plan', true );
		$today  = current_time( 'Y-m-d' );

		if ( in_array( $plan, [ 'quarterly_3', 'monthly_8' ], true ) ) {
			$due_date = self::next_unpaid_due_date( $invoice_id );
			if ( $due_date === null ) {
				return;
			}
			$status = $due_date < $today ? 'overdue' : 'sent';
		} else {
			$due_date = (string) get_post_meta( $invoice_id, 'due_date', true );
			if ( $due_date && $due_date < current_time( 'Ymd' ) ) {
				$status = 'overdue';
			}
		}

		if ( $invoice->post_status !== 'rondo_' . $status ) {
			$result = wp_update_post(
				[
					'ID'          => $invoice_id,
					'post_status' => 'rondo_' . $status,
				],
				true
				);
			if ( is_wp_error( $result ) ) {
				return;
			}
		}
		if ( Fields::get_for_post( $invoice_id, 'status' ) !== $status ) {
			Fields::update_for_post( $invoice_id, 'status', $status );
		}
	}

	/**
	 * Get the next unpaid installment deadline for an open installment invoice.
	 *
	 * Incomplete schedules return null rather than falling back to the original
	 * invoice deadline or allowing a later installment to hide an unpaid debt.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return string|null Due date in YYYY-MM-DD, or null without a valid schedule.
	 */
	public static function next_unpaid_due_date( int $invoice_id ): ?string {
		$plan = get_post_meta( $invoice_id, '_installment_plan', true );
		if ( ! in_array( get_post_status( $invoice_id ), [ 'rondo_sent', 'rondo_overdue' ], true )
			|| ! in_array( $plan, [ 'quarterly_3', 'monthly_8' ], true ) ) {
			return null;
		}
		$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		if ( $count < 1 ) {
			return null;
		}

		$next_due_date = null;
		for ( $n = 1; $n <= $count; $n++ ) {
			if ( get_post_meta( $invoice_id, '_installment_' . $n . '_status', true ) === 'betaald' ) {
				continue;
			}
			$due_date = (string) get_post_meta( $invoice_id, '_installment_' . $n . '_due_date', true );
			$date     = \DateTimeImmutable::createFromFormat( '!Y-m-d', $due_date );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $due_date ) {
				return null;
			}
			if ( $next_due_date === null || $due_date < $next_due_date ) {
				$next_due_date = $due_date;
			}
		}
		return $next_due_date;
	}
}
