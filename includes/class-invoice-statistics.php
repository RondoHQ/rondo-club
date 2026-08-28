<?php
/**
 * Aggregates recent invoice payment statistics for the finance dashboard.
 */

namespace Rondo\Finance;

use Rondo\Fees\SeasonKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds cash-receipt and invoice lead-time statistics from native invoice meta.
 */
class InvoiceStatistics {

	/**
	 * Build rolling totals, lead time, and chart series for received payments.
	 *
	 * @param \DateTimeImmutable|null $now Reference time; defaults to the WordPress site time.
	 * @param string|null             $invoice_type Optional invoice type filter.
	 * @return array<string,mixed>
	 */
	public function calculate( ?\DateTimeImmutable $now = null, ?string $invoice_type = null ): array {
		$now                       = $now ?: current_datetime();
		$week_start                = $now->modify( '-7 days' );
		$month_start               = $now->modify( '-30 days' );
		$day_start                 = $now->setTime( 0, 0 )->modify( '-29 days' );
		$year_start                = $now->modify( 'first day of this month' )->setTime( 0, 0 )->modify( '-11 months' );
		$payments                  = [];
		$open_days                 = [];
		$season                    = SeasonKey::current( $now->format( 'Y-m-d' ) );
		$plan_people               = [
			'quarterly_3' => [],
			'monthly_8'   => [],
		];
		$membership_payment_status = [
			'paid'   => 0,
			'unpaid' => 0,
		];
		$invoice_ids               = get_posts(
			[
				'post_type'              => 'rondo_invoice',
				'post_status'            => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue', 'rondo_cancelled' ],
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			]
		);

		if ( ! empty( $invoice_ids ) ) {
			update_meta_cache( 'post', $invoice_ids );
		}

		foreach ( $invoice_ids as $invoice_id ) {
			$invoice_id = (int) $invoice_id;
			$this->collect_membership_payment_status( $invoice_id, $season, $membership_payment_status );
			if ( ! $this->matches_invoice_type( $invoice_id, $invoice_type ) ) {
				continue;
			}

			$payments = array_merge( $payments, $this->get_invoice_payments( $invoice_id ) );
			$this->collect_installment_plan_person( $invoice_id, $season, $plan_people );

			if ( get_post_status( $invoice_id ) !== 'rondo_paid' ) {
				continue;
			}

			$paid_at = $this->get_fully_paid_at( $invoice_id );
			if ( ! $paid_at || $paid_at < $month_start || $paid_at > $now ) {
				continue;
			}

			$sent_at = $this->parse_date( (string) get_post_meta( $invoice_id, 'sent_date', true ) );
			if ( ! $sent_at || $sent_at > $paid_at ) {
				continue;
			}

			$open_days[] = (int) $sent_at->diff( $paid_at )->format( '%a' );
		}

		$week_payments   = $this->filter_payments( $payments, $week_start, $now );
		$month_payments  = $this->filter_payments( $payments, $month_start, $now );
		$all_plan_people = array_unique(
			array_merge( array_keys( $plan_people['quarterly_3'] ), array_keys( $plan_people['monthly_8'] ) )
		);

		return [
			'generated_at'              => $now->format( DATE_ATOM ),
			'week'                      => $this->summarize_period( $week_payments, $week_start, $now, 7 ),
			'month'                     => $this->summarize_period( $month_payments, $month_start, $now, 30 ),
			'invoice_type'              => $invoice_type,
			'daily_income'              => $this->build_daily_income( $payments, $day_start, $now ),
			'monthly_income'            => $this->build_monthly_income( $payments, $year_start, $now ),
			'average_days_open'         => empty( $open_days ) ? null : round( array_sum( $open_days ) / count( $open_days ), 1 ),
			'paid_invoice_count'        => count( $open_days ),
			'membership_payment_status' => [
				'season' => $season,
				'paid'   => $membership_payment_status['paid'],
				'unpaid' => $membership_payment_status['unpaid'],
				'total'  => $membership_payment_status['paid'] + $membership_payment_status['unpaid'],
			],
			'installment_plans'         => [
				'season'       => $season,
				'total_people' => count( $all_plan_people ),
				'quarterly_3'  => count( $plan_people['quarterly_3'] ),
				'monthly_8'    => count( $plan_people['monthly_8'] ),
			],
		];
	}

	/**
	 * Count current-season membership invoices that are paid or still open.
	 *
	 * Draft, cancelled, and credit invoices are outside the payable population.
	 *
	 * @param int                     $invoice_id Invoice post ID.
	 * @param string                  $season     Current season key.
	 * @param array<string,int>       $counts     Paid and unpaid counters.
	 * @return void
	 */
	private function collect_membership_payment_status( int $invoice_id, string $season, array &$counts ): void {
		$status = get_post_status( $invoice_id );
		if ( ! in_array( $status, [ 'rondo_sent', 'rondo_paid', 'rondo_overdue' ], true ) ) {
			return;
		}

		if ( get_post_meta( $invoice_id, '_invoice_kind', true ) === 'credit' ) {
			return;
		}

		if ( (string) get_post_meta( $invoice_id, '_invoice_season', true ) !== $season ) {
			return;
		}

		if ( \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_type' ) !== 'membership' ) {
			return;
		}

		++$counts[ $status === 'rondo_paid' ? 'paid' : 'unpaid' ];
	}

	/**
	 * Check whether an invoice belongs in the selected statistics.
	 *
	 * Credit invoices are excluded because they are not income.
	 *
	 * @param int         $invoice_id   Invoice post ID.
	 * @param string|null $invoice_type Optional invoice type filter.
	 * @return bool
	 */
	private function matches_invoice_type( int $invoice_id, ?string $invoice_type ): bool {
		if ( get_post_meta( $invoice_id, '_invoice_kind', true ) === 'credit' ) {
			return false;
		}

		return $invoice_type === null
			|| \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_type' ) === $invoice_type;
	}

	/**
	 * Build one income bucket for each of the last 30 calendar days.
	 *
	 * @param array<int,array{amount:float,paid_at:\DateTimeImmutable}> $payments Payments.
	 * @param \DateTimeImmutable                                      $start    First day.
	 * @param \DateTimeImmutable                                      $end      Last day.
	 * @return array<int,array{date:string,amount:float,payment_count:int}>
	 */
	private function build_daily_income( array $payments, \DateTimeImmutable $start, \DateTimeImmutable $end ): array {
		$buckets = [];
		for ( $date = $start; $date <= $end; $date = $date->modify( '+1 day' ) ) {
			$key             = $date->format( 'Y-m-d' );
			$buckets[ $key ] = [
				'date'          => $key,
				'amount'        => 0.0,
				'payment_count' => 0,
			];
		}

		foreach ( $payments as $payment ) {
			$key = $payment['paid_at']->format( 'Y-m-d' );
			if ( ! isset( $buckets[ $key ] ) ) {
				continue;
			}
			$buckets[ $key ]['amount'] += $payment['amount'];
			++$buckets[ $key ]['payment_count'];
		}

		foreach ( $buckets as &$bucket ) {
			$bucket['amount'] = round( $bucket['amount'], 2 );
		}
		unset( $bucket );

		return array_values( $buckets );
	}

	/**
	 * Build one income bucket for each of the last 12 calendar months.
	 *
	 * @param array<int,array{amount:float,paid_at:\DateTimeImmutable}> $payments Payments.
	 * @param \DateTimeImmutable                                      $start    First month.
	 * @param \DateTimeImmutable                                      $end      Last month.
	 * @return array<int,array{month:string,amount:float,payment_count:int}>
	 */
	private function build_monthly_income( array $payments, \DateTimeImmutable $start, \DateTimeImmutable $end ): array {
		$buckets = [];
		for ( $month = $start; $month <= $end; $month = $month->modify( '+1 month' ) ) {
			$key             = $month->format( 'Y-m' );
			$buckets[ $key ] = [
				'month'         => $key,
				'amount'        => 0.0,
				'payment_count' => 0,
			];
		}

		foreach ( $payments as $payment ) {
			$key = $payment['paid_at']->format( 'Y-m' );
			if ( ! isset( $buckets[ $key ] ) ) {
				continue;
			}
			$buckets[ $key ]['amount'] += $payment['amount'];
			++$buckets[ $key ]['payment_count'];
		}

		foreach ( $buckets as &$bucket ) {
			$bucket['amount'] = round( $bucket['amount'], 2 );
		}
		unset( $bucket );

		return array_values( $buckets );
	}

	/**
	 * Add the linked person for a current-season installment invoice.
	 *
	 * @param int                              $invoice_id Invoice post ID.
	 * @param string                           $season     Current season key.
	 * @param array<string,array<int,bool>>     $people     Unique people per plan.
	 * @return void
	 */
	private function collect_installment_plan_person( int $invoice_id, string $season, array &$people ): void {
		$status = get_post_status( $invoice_id );
		if ( ! in_array( $status, [ 'rondo_sent', 'rondo_paid', 'rondo_overdue' ], true ) ) {
			return;
		}

		if ( (string) get_post_meta( $invoice_id, '_invoice_season', true ) !== $season ) {
			return;
		}

		if ( \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_type' ) !== 'membership' ) {
			return;
		}

		$plan = (string) get_post_meta( $invoice_id, '_installment_plan', true );
		if ( ! isset( $people[ $plan ] ) ) {
			return;
		}

		$person_id = (int) \Rondo\Fields\Fields::get_for_post( $invoice_id, 'person' );
		if ( $person_id > 0 ) {
			$people[ $plan ][ $person_id ] = true;
		}
	}

	/**
	 * Return dated payments recorded for one invoice.
	 *
	 * Installments are individual receipts. A later manual paid mark contributes
	 * only the remaining principal, preventing earlier installments from being
	 * counted twice.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return array<int,array{amount:float,paid_at:\DateTimeImmutable}>
	 */
	private function get_invoice_payments( int $invoice_id ): array {
		$payments                   = [];
		$paid_installment_principal = 0.0;
		$has_paid_installment       = false;
		$count                      = (int) get_post_meta( $invoice_id, '_installment_count', true );

		for ( $number = 1; $number <= $count; $number++ ) {
			if ( get_post_meta( $invoice_id, '_installment_' . $number . '_status', true ) !== 'betaald' ) {
				continue;
			}

			$has_paid_installment        = true;
			$principal                   = (float) get_post_meta( $invoice_id, '_installment_' . $number . '_amount', true );
			$admin_fee                   = (float) get_post_meta( $invoice_id, '_installment_' . $number . '_admin_fee', true );
			$paid_installment_principal += $principal;
			$paid_at                     = $this->get_installment_paid_at( $invoice_id, $number );

			if ( $paid_at ) {
				$payments[] = [
					'amount'  => $principal + $admin_fee,
					'paid_at' => $paid_at,
				];
			}
		}

		$manual_paid_at = $this->parse_date( (string) get_post_meta( $invoice_id, '_manually_marked_paid_at', true ) );
		if ( $manual_paid_at ) {
			$total     = (float) \Rondo\Fields\Fields::get_for_post( $invoice_id, 'total_amount' );
			$remaining = max( 0.0, $total - $paid_installment_principal );
			if ( $remaining > 0 ) {
				$payments[] = [
					'amount'  => $remaining,
					'paid_at' => $manual_paid_at,
				];
			}
		} elseif ( ! $has_paid_installment ) {
			$mollie_paid_at = $this->parse_date( (string) get_post_meta( $invoice_id, '_mollie_paid_at', true ) );
			if ( $mollie_paid_at ) {
				$payments[] = [
					'amount'  => (float) \Rondo\Fields\Fields::get_for_post( $invoice_id, 'total_amount' ),
					'paid_at' => $mollie_paid_at,
				];
			}
		}

		return $payments;
	}

	/**
	 * Resolve when an invoice became fully paid.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return \DateTimeImmutable|null
	 */
	public function get_fully_paid_at( int $invoice_id ): ?\DateTimeImmutable {
		$manual_paid_at = $this->parse_date( (string) get_post_meta( $invoice_id, '_manually_marked_paid_at', true ) );
		if ( $manual_paid_at ) {
			return $manual_paid_at;
		}

		$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		if ( $count > 0 ) {
			$paid_dates = [];
			for ( $number = 1; $number <= $count; $number++ ) {
				if ( get_post_meta( $invoice_id, '_installment_' . $number . '_status', true ) !== 'betaald' ) {
					return null;
				}

				$paid_at = $this->get_installment_paid_at( $invoice_id, $number );
				if ( ! $paid_at ) {
					return null;
				}

				$paid_dates[] = $paid_at;
			}

			return max( $paid_dates );
		}

		return $this->parse_date( (string) get_post_meta( $invoice_id, '_mollie_paid_at', true ) );
	}

	/**
	 * Resolve an installment payment timestamp.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @param int $number     Installment number.
	 * @return \DateTimeImmutable|null
	 */
	private function get_installment_paid_at( int $invoice_id, int $number ): ?\DateTimeImmutable {
		$mollie_paid_at = (string) get_post_meta( $invoice_id, '_installment_' . $number . '_mollie_paid_at', true );
		$paid_at        = $mollie_paid_at ?: (string) get_post_meta( $invoice_id, '_installment_' . $number . '_paid_at', true );

		return $this->parse_date( $paid_at );
	}

	/**
	 * Parse stored ISO, MySQL, compact, or dashed dates in the site timezone.
	 *
	 * @param string $value Stored date value.
	 * @return \DateTimeImmutable|null
	 */
	private function parse_date( string $value ): ?\DateTimeImmutable {
		$value = trim( $value );
		if ( $value === '' ) {
			return null;
		}

		$timezone = wp_timezone();
		try {
			if ( preg_match( '/^\d{8}$/', $value ) ) {
				$date = \DateTimeImmutable::createFromFormat( '!Ymd', $value, $timezone );
			} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
				$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $timezone );
			} elseif ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
				$date = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value, $timezone );
			} else {
				$date = new \DateTimeImmutable( $value );
			}
		} catch ( \Exception $exception ) {
			return null;
		}

		return $date ? $date->setTimezone( $timezone ) : null;
	}

	/**
	 * Keep payments in a period.
	 *
	 * @param array<int,array{amount:float,paid_at:\DateTimeImmutable}> $payments Payments.
	 * @param \DateTimeImmutable                                      $start    Inclusive start.
	 * @param \DateTimeImmutable                                      $end      Inclusive end.
	 * @return array<int,array{amount:float,paid_at:\DateTimeImmutable}>
	 */
	private function filter_payments( array $payments, \DateTimeImmutable $start, \DateTimeImmutable $end ): array {
		return array_values(
			array_filter(
				$payments,
				static fn( array $payment ): bool => $payment['paid_at'] >= $start && $payment['paid_at'] <= $end
			)
		);
	}

	/**
	 * Format one period for the REST response.
	 *
	 * @param array<int,array{amount:float,paid_at:\DateTimeImmutable}> $payments Payments.
	 * @param \DateTimeImmutable                                      $start    Start.
	 * @param \DateTimeImmutable                                      $end      End.
	 * @param int                                                     $days     Period length.
	 * @return array<string,int|float|string>
	 */
	private function summarize_period( array $payments, \DateTimeImmutable $start, \DateTimeImmutable $end, int $days ): array {
		return [
			'days'            => $days,
			'starts_at'       => $start->format( DATE_ATOM ),
			'ends_at'         => $end->format( DATE_ATOM ),
			'received_amount' => round( array_sum( array_column( $payments, 'amount' ) ), 2 ),
			'payment_count'   => count( $payments ),
		];
	}
}
