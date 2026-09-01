<?php
/**
 * Automatic recovery for tournament payment-link creation.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentPaymentRetryScheduler {

	public const CRON_HOOK = 'rondo_tournament_payment_retry';

	private const ATTEMPTS_META = '_tournament_payment_retry_attempts';
	private const DELAYS        = [ 5 * MINUTE_IN_SECONDS, 30 * MINUTE_IN_SECONDS, 2 * HOUR_IN_SECONDS, 12 * HOUR_IN_SECONDS, DAY_IN_SECONDS ];

	private TournamentPaymentService $payments;

	public function __construct( ?TournamentPaymentService $payments = null ) {
		$this->payments = $payments ?? new TournamentPaymentService();
		add_action( self::CRON_HOOK, [ $this, 'retry' ] );
	}

	/** Schedule one deduplicated retry with increasing backoff. */
	public static function schedule( int $entry_id ): void {
		$args = [ $entry_id ];
		if ( wp_next_scheduled( self::CRON_HOOK, $args ) ) {
			return;
		}

		$attempts = absint( get_post_meta( $entry_id, self::ATTEMPTS_META, true ) );
		$delay    = self::DELAYS[ min( $attempts, count( self::DELAYS ) - 1 ) ];
		update_post_meta( $entry_id, self::ATTEMPTS_META, $attempts + 1 );
		wp_schedule_single_event( time() + $delay, self::CRON_HOOK, $args );
	}

	/** Remove any pending retry after success, reopening or deletion. */
	public static function clear( int $entry_id ): void {
		wp_clear_scheduled_hook( self::CRON_HOOK, [ $entry_id ] );
		delete_post_meta( $entry_id, self::ATTEMPTS_META );
	}

	/** Retry one submitted entry. Public for focused tests and WP-Cron. */
	public function retry( int $entry_id ): void {
		$fields = Fields::all_for_post( $entry_id );
		if ( get_post_type( $entry_id ) !== TournamentService::ENTRY_POST_TYPE || get_post_status( $entry_id ) === 'trash' || (string) ( $fields['registration_status'] ?? '' ) !== 'submitted' ) {
			self::clear( $entry_id );
			return;
		}

		$summary = $this->payments->payment_summary( $entry_id, $fields );
		if ( ! in_array( $summary['payment_state'], [ 'error', 'expired' ], true ) ) {
			self::clear( $entry_id );
			return;
		}

		$actor_user_id = (int) ( $fields['submitted_by_user_id'] ?? 0 );
		$result        = $this->payments->ensure_payment( $entry_id, $actor_user_id );
		if ( ! is_wp_error( $result ) ) {
			self::clear( $entry_id );
		}
	}
}
