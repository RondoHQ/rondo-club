<?php
/**
 * Volunteer shift cancellation policy and audit trail.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShiftCancellationService {

	public const CREDIT_WINDOW_SECONDS = 48 * HOUR_IN_SECONDS;
	public const VARIANT_EARLY         = 'early';
	public const VARIANT_LAST_MINUTE   = 'last_minute';

	public const META_CANCELLED_AT   = '_shift_cancelled_at';
	public const META_CANCELLED_BY   = '_shift_cancelled_by_user';
	public const META_VARIANT        = '_shift_cancellation_variant';
	public const META_CREDIT         = '_shift_cancellation_credit';
	public const META_REASON         = '_shift_cancellation_reason';
	public const META_SECONDS_NOTICE = '_shift_cancellation_seconds_notice';

	public function __construct() {
		add_filter( 'pre_delete_post', [ $this, 'prevent_delete_with_assignees' ], 10, 3 );
		add_filter( 'rest_pre_insert_dienst_shift', [ $this, 'prevent_direct_rest_cancellation' ], 10, 2 );
	}

	/**
	 * Persist one cancellation. The caller must serialize writes for this shift.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function cancel( int $shift_id, string $reason = '', ?int $user_id = null, ?\DateTimeImmutable $now = null ) {
		$shift = get_post( $shift_id );
		if ( ! $shift || $shift->post_type !== 'dienst_shift' ) {
			return new \WP_Error( 'invalid_shift', 'Inschrijftaak bestaat niet.', [ 'status' => 404 ] );
		}

		$status = (string) get_post_meta( $shift_id, 'status', true );
		if ( $status === 'geannuleerd' ) {
			$details = self::details( $shift_id );
			if ( ! $details ) {
				return new \WP_Error( 'legacy_shift_cancelled', 'Deze inschrijftaak was al geannuleerd zonder annuleringsgegevens.', [ 'status' => 409 ] );
			}
			return array_merge( $details, [ 'already_cancelled' => true ] );
		}
		if ( $status === 'voltooid' ) {
			return new \WP_Error( 'shift_completed', 'Een voltooide inschrijftaak kan niet meer worden geannuleerd.', [ 'status' => 409 ] );
		}

		$start = self::parse_datetime( (string) get_post_meta( $shift_id, 'start_datetime', true ) );
		$now   = $now ?: current_datetime();
		if ( ! $start ) {
			return new \WP_Error( 'invalid_shift_start', 'De inschrijftaak heeft geen geldige starttijd.', [ 'status' => 400 ] );
		}

		$seconds_notice = $start->getTimestamp() - $now->getTimestamp();
		if ( $seconds_notice <= 0 ) {
			return new \WP_Error( 'shift_already_started', 'Een inschrijftaak die al is begonnen kan niet meer worden geannuleerd.', [ 'status' => 409 ] );
		}

		$credit  = $seconds_notice < self::CREDIT_WINDOW_SECONDS;
		$variant = $credit ? self::VARIANT_LAST_MINUTE : self::VARIANT_EARLY;

		update_post_meta( $shift_id, 'status', 'geannuleerd' );
		update_post_meta( $shift_id, self::META_CANCELLED_AT, $now->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift_id, self::META_CANCELLED_BY, $user_id ?: get_current_user_id() );
		update_post_meta( $shift_id, self::META_VARIANT, $variant );
		update_post_meta( $shift_id, self::META_CREDIT, $credit ? '1' : '0' );
		update_post_meta( $shift_id, self::META_SECONDS_NOTICE, $seconds_notice );
		if ( $reason !== '' ) {
			update_post_meta( $shift_id, self::META_REASON, sanitize_textarea_field( $reason ) );
		} else {
			delete_post_meta( $shift_id, self::META_REASON );
		}

		VolunteerObligationCalculator::invalidate_cache();

		do_action( 'rondo_shift_cancelled', $shift_id, $variant, $credit, $user_id ?: get_current_user_id() );

		return array_merge( self::details( $shift_id ), [ 'already_cancelled' => false ] );
	}

	/**
	 * Public, read-only cancellation data for REST responses.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function details( int $shift_id ): ?array {
		$variant = (string) get_post_meta( $shift_id, self::META_VARIANT, true );
		if ( ! in_array( $variant, [ self::VARIANT_EARLY, self::VARIANT_LAST_MINUTE ], true ) ) {
			return null;
		}

		return [
			'variant'        => $variant,
			'credit_awarded' => (bool) get_post_meta( $shift_id, self::META_CREDIT, true ),
			'reason'         => (string) get_post_meta( $shift_id, self::META_REASON, true ),
			'cancelled_at'   => (string) get_post_meta( $shift_id, self::META_CANCELLED_AT, true ),
			'seconds_notice' => (int) get_post_meta( $shift_id, self::META_SECONDS_NOTICE, true ),
		];
	}

	/**
	 * Never hard-delete a shift that still carries assignments or earned credit.
	 *
	 * @param mixed    $delete       Existing short-circuit value.
	 * @param \WP_Post $post         Post being deleted.
	 * @param bool     $force_delete Whether trash is bypassed.
	 * @return mixed
	 */
	public function prevent_delete_with_assignees( $delete, $post, $force_delete ) {
		if ( $delete !== null || ! $post instanceof \WP_Post || $post->post_type !== 'dienst_shift' ) {
			return $delete;
		}

		$assigned = array_filter( array_map( 'intval', (array) get_post_meta( $post->ID, 'assigned_persons', true ) ) );
		if ( ! empty( $assigned ) || self::details( $post->ID ) !== null ) {
			return false;
		}

		return $delete;
	}

	/**
	 * Force standard REST clients through the policy endpoint for cancellations.
	 *
	 * @param \WP_Post|\WP_Error $prepared_post Prepared post object.
	 * @param \WP_REST_Request   $request       REST request.
	 * @return \WP_Post|\WP_Error
	 */
	public function prevent_direct_rest_cancellation( $prepared_post, \WP_REST_Request $request ) {
		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}

		$post_id = isset( $prepared_post->ID ) ? (int) $prepared_post->ID : (int) $request->get_param( 'id' );
		if ( $post_id > 0 && (string) get_post_meta( $post_id, 'status', true ) === 'geannuleerd' ) {
			return new \WP_Error( 'cancelled_shift_readonly', 'Een geannuleerde inschrijftaak kan niet meer worden bewerkt.', [ 'status' => 409 ] );
		}

		$fields           = \Rondo\Fields\RestFields::request_payload( $request, 'dienst_shift' );
		$requested_status = (string) ( $fields['status'] ?? '' );
		if ( $requested_status !== 'geannuleerd' ) {
			return $prepared_post;
		}

		return new \WP_Error(
			'use_shift_cancellation',
			'Annuleer deze inschrijftaak via de annuleringsactie, zodat vrijwilligers bericht krijgen en de punten correct worden verwerkt.',
			[ 'status' => 409 ]
		);
	}

	private static function parse_datetime( string $value ): ?\DateTimeImmutable {
		if ( $value === '' ) {
			return null;
		}
		try {
			return new \DateTimeImmutable( $value, wp_timezone() );
		} catch ( \Exception $exception ) {
			return null;
		}
	}
}
