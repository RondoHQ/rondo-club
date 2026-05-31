<?php
/**
 * ShiftTemplateExpander
 *
 * Turns `shift_template` records into concrete `dienst_shift` posts for an
 * upcoming window. Rolling expansion: by default we keep WINDOW_DAYS days of
 * shifts visible at any time. Idempotent — each generated shift carries a
 * deterministic meta tuple (`template_id`, `start_datetime`) that we de-dup on.
 *
 * Runs:
 *   - Daily via WP-Cron (`rondo_expand_shift_templates`).
 *   - On-demand via REST endpoint or WP-CLI command (TBD).
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShiftTemplateExpander {

	const CRON_HOOK   = 'rondo_expand_shift_templates';
	const WINDOW_DAYS = 84; // 12 weeks rolling

	public function __construct() {
		add_action( 'init', [ $this, 'register_cron' ] );
		add_action( self::CRON_HOOK, [ $this, 'expand_default_window' ] );
		add_action( 'acf/save_post', [ $this, 'expand_on_template_save' ], 20 );
	}

	/**
	 * Expand the saved template immediately so the user sees concrete shifts
	 * for the next 12 weeks without waiting for the daily cron. Idempotent —
	 * relies on `find_existing_shift()` to skip already-rolled-out dates.
	 *
	 * @param int|string $post_id ACF save_post payload (post ID or "options").
	 */
	public function expand_on_template_save( $post_id ) {
		if ( ! is_numeric( $post_id ) ) {
			return;
		}
		if ( get_post_type( (int) $post_id ) !== 'shift_template' ) {
			return;
		}
		self::expand_template( (int) $post_id, gmdate( 'Y-m-d' ), gmdate( 'Y-m-d', strtotime( '+' . self::WINDOW_DAYS . ' days' ) ) );
	}

	public function register_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	public static function unregister_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Expand the standard window: starting today, WINDOW_DAYS ahead.
	 */
	public function expand_default_window(): int {
		$from = gmdate( 'Y-m-d' );
		$to   = gmdate( 'Y-m-d', strtotime( '+' . self::WINDOW_DAYS . ' days' ) );
		return self::expand_range( $from, $to );
	}

	/**
	 * Expand every active shift_template into concrete dienst_shifts between
	 * $from and $to (inclusive). Returns the number of NEW shifts created.
	 *
	 * @param string $from Y-m-d start date.
	 * @param string $to   Y-m-d end date.
	 */
	public static function expand_range( string $from, string $to ): int {
		$templates = get_posts(
			[
				'post_type'        => 'shift_template',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
			]
		);

		if ( empty( $templates ) ) {
			return 0;
		}

		$created = 0;
		foreach ( $templates as $template_id ) {
			$created += self::expand_template( (int) $template_id, $from, $to );
		}

		if ( $created > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
				'[Rondo Volunteer] Expanded %d shift(s) between %s and %s.',
				$created,
				$from,
				$to
			)
				);
		}

		return $created;
	}

	/**
	 * Expand a single template into the requested window. Idempotent.
	 *
	 * @return int Number of NEW shifts created (skips already-existing).
	 */
	public static function expand_template( int $template_id, string $from, string $to ): int {
		$dienst_type_id = (int) get_post_meta( $template_id, 'dienst_type_id', true );
		$day_of_week    = (int) get_post_meta( $template_id, 'day_of_week', true );
		$start_time     = (string) get_post_meta( $template_id, 'start_time', true );
		$end_time       = (string) get_post_meta( $template_id, 'end_time', true );
		$capacity       = (int) get_post_meta( $template_id, 'capacity', true );
		$active_from    = (string) get_post_meta( $template_id, 'active_from', true );
		$active_until   = (string) get_post_meta( $template_id, 'active_until', true );
		$notes          = (string) get_post_meta( $template_id, 'notes', true );

		// Default capacity falls back to the dienst_type setting.
		if ( $capacity <= 0 && $dienst_type_id > 0 ) {
			$capacity = (int) get_post_meta( $dienst_type_id, 'default_capacity', true );
		}

		if ( $dienst_type_id <= 0 || $day_of_week < 1 || $day_of_week > 7 || $start_time === '' || $end_time === '' ) {
			return 0; // Incomplete template — skip silently.
		}

		// Clamp the requested window to the template's active range.
		$window_start = max( $from, $active_from ?: $from );
		$window_end   = $to;
		if ( $active_until !== '' ) {
			$window_end = min( $window_end, $active_until );
		}

		if ( $window_start > $window_end ) {
			return 0;
		}

		$created = 0;
		$cursor  = strtotime( $window_start );
		$end_ts  = strtotime( $window_end );

		while ( $cursor !== false && $cursor <= $end_ts ) {
			// PHP date('N') returns 1=Monday..7=Sunday — matches our convention.
			if ( (int) gmdate( 'N', $cursor ) === $day_of_week ) {
				$start_datetime = gmdate( 'Y-m-d', $cursor ) . ' ' . self::normalize_time( $start_time );
				$end_datetime   = gmdate( 'Y-m-d', $cursor ) . ' ' . self::normalize_time( $end_time );

				if ( self::find_existing_shift( $template_id, $start_datetime ) === 0 ) {
					$title   = self::shift_title( $dienst_type_id, $start_datetime );
					$post_id = wp_insert_post(
						[
							'post_type'   => 'dienst_shift',
							'post_status' => 'publish',
							'post_title'  => $title,
						],
						true
					);

					if ( ! is_wp_error( $post_id ) && $post_id !== 0 ) {
						update_post_meta( $post_id, 'dienst_type_id', $dienst_type_id );
						update_post_meta( $post_id, 'template_id', $template_id );
						update_post_meta( $post_id, 'start_datetime', $start_datetime );
						update_post_meta( $post_id, 'end_datetime', $end_datetime . ':00' );
						update_post_meta( $post_id, 'capacity', $capacity > 0 ? $capacity : 1 );
						update_post_meta( $post_id, 'status', 'open' );
						update_post_meta( $post_id, 'assigned_persons', [] );
						if ( $notes !== '' ) {
							update_post_meta( $post_id, 'notes', $notes );
						}
						++$created;
					}
				}
			}
			$cursor = strtotime( '+1 day', $cursor );
		}

		return $created;
	}

	/**
	 * Idempotency check — does a shift already exist for this template and start time?
	 */
	private static function find_existing_shift( int $template_id, string $start_datetime ): int {
		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish', 'draft' ],
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'   => 'template_id',
						'value' => $template_id,
					],
					[
						'key'   => 'start_datetime',
						'value' => $start_datetime,
					],
				],
			]
		);

		return empty( $query->posts ) ? 0 : (int) $query->posts[0];
	}

	/**
	 * Normalize HH:MM input to HH:MM (handles "9:00" → "09:00", "9" → "09:00", etc.).
	 */
	private static function normalize_time( string $time ): string {
		$time = trim( $time );
		if ( $time === '' ) {
			return '00:00';
		}
		if ( preg_match( '/^(\d{1,2}):(\d{2})$/', $time, $m ) ) {
			return str_pad( $m[1], 2, '0', STR_PAD_LEFT ) . ':' . $m[2];
		}
		if ( preg_match( '/^(\d{1,2})$/', $time, $m ) ) {
			return str_pad( $m[1], 2, '0', STR_PAD_LEFT ) . ':00';
		}
		return $time;
	}

	private static function shift_title( int $dienst_type_id, string $start_datetime ): string {
		$type = $dienst_type_id > 0 ? get_the_title( $dienst_type_id ) : 'Dienst';
		$date = $start_datetime !== '' ? gmdate( 'd-m-Y H:i', strtotime( $start_datetime ) ) : '';
		return trim( $type . ' — ' . $date );
	}
}
