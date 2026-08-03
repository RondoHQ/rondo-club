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
	const WINDOW_DAYS = 93; // Covers every possible three-calendar-month window.

	public function __construct() {
		add_action( 'init', [ $this, 'register_cron' ] );
		add_action( self::CRON_HOOK, [ $this, 'expand_default_window' ] );
		add_action( 'rondo_fields_saved_post', [ $this, 'expand_on_template_save' ], 20 );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rest_after_insert_dienst_shift', [ $this, 'detach_on_manual_edit' ], 10, 3 );
		add_action( 'before_delete_post', [ $this, 'cleanup_on_template_delete' ], 10, 2 );
	}

	/**
	 * Frontend triggert deze als de gebruiker op "Uitrollen" klikt zodat we
	 * niet hoeven te wachten op de nachtelijke cron. De handmatige actie
	 * vereist een expliciete einddatum; de cron houdt zijn vaste venster.
	 */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/shift-templates/expand',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_expand_all' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ) || current_user_can( 'vrijwilligers' );
				},
				'args'                => [
					'until' => [
						'description'       => 'Laatste datum die bij de handmatige uitrol wordt meegenomen (Y-m-d).',
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/shift-templates/(?P<id>\d+)/rerun',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_rerun_template' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ) || current_user_can( 'vrijwilligers' );
				},
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Expose the sjabloon link so the shift editor can show provenance and
		// whether a manual edit already detached the shift from its template.
		register_rest_field(
			'dienst_shift',
			'template_link',
			[
				'get_callback' => static function ( array $object ): ?array {
					$shift_id    = (int) ( $object['id'] ?? 0 );
					$template_id = (int) get_post_meta( $shift_id, 'template_id', true );
					if ( $template_id <= 0 ) {
						return null;
					}
					return [
						'id'         => $template_id,
						'title'      => get_the_title( $template_id ),
						'customized' => (int) get_post_meta( $shift_id, '_shift_customized', true ) === 1,
					];
				},
				'schema'       => [
					'description' => 'Sjabloonkoppeling van de inschrijftaak (null als los ingepland).',
					'type'        => [ 'object', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
			]
		);
	}

	/**
	 * A manual edit through the admin form detaches a rolled-out shift from its
	 * sjabloon. The shift keeps its `template_id` for provenance but is flagged
	 * `_shift_customized`, so re-rollout can never delete or overwrite it.
	 *
	 * Only fires for REST writes (the admin shift editor). The expander itself
	 * uses `wp_insert_post()` directly and member signup/cancellation write meta
	 * straight through `rondo/v1`, so neither trips this hook.
	 *
	 * @param \WP_Post         $post     The saved shift.
	 * @param \WP_REST_Request $request  The REST request (unused).
	 * @param bool             $creating True when the post was just created.
	 */
	public function detach_on_manual_edit( $post, $request, $creating ): void {
		if ( $creating || ! $post instanceof \WP_Post || $post->post_type !== 'dienst_shift' ) {
			return;
		}
		if ( (int) get_post_meta( $post->ID, 'template_id', true ) <= 0 ) {
			return; // Standalone shift — nothing to detach from.
		}
		update_post_meta( $post->ID, '_shift_customized', 1 );
	}

	/**
	 * Re-roll a single template: delete its future, template-managed shifts and
	 * regenerate them from the template's current settings. Customized shifts,
	 * shifts with signups and cancelled shifts are preserved.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_rerun_template( \WP_REST_Request $request ) {
		$template_id = (int) $request->get_param( 'id' );
		if ( get_post_type( $template_id ) !== 'shift_template' ) {
			return new \WP_Error( 'rondo_invalid_template', 'Sjabloon niet gevonden.', [ 'status' => 404 ] );
		}

		$from   = gmdate( 'Y-m-d' );
		$to     = gmdate( 'Y-m-d', strtotime( '+' . self::WINDOW_DAYS . ' days' ) );
		$result = self::rerun_template( $template_id, $from, $to );

		return rest_ensure_response(
			array_merge(
				$result,
				[
					'from' => $from,
					'to'   => $to,
				]
			)
		);
	}

	/**
	 * Permanently deleting a sjabloon takes its rolled-out future shifts with
	 * it, so the calendar does not stay full of taken nobody manages anymore.
	 * Same preservation rules as re-rollout: customized shifts, shifts with
	 * signups and cancelled shifts stay, as does everything in the past. What
	 * stays is detached from the (now gone) template so nothing dangles.
	 *
	 * The frontend deletes with `force=true`, so this fires immediately; a
	 * wp-admin trash only triggers it once the trash is emptied.
	 *
	 * @param int           $post_id Post being deleted.
	 * @param \WP_Post|null $post    The post object (WP ≥ 5.5 passes it along).
	 */
	public function cleanup_on_template_delete( $post_id, $post = null ): void {
		$post = $post instanceof \WP_Post ? $post : get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'shift_template' ) {
			return;
		}
		self::cleanup_template_shifts( (int) $post_id );
	}

	/**
	 * Delete the deletable future shifts of a template and detach the rest.
	 *
	 * @param int $template_id Template post ID (may already be mid-deletion).
	 * @return array{deleted:int, detached:int}
	 */
	public static function cleanup_template_shifts( int $template_id ): array {
		$shift_ids = get_posts(
			[
				'post_type'        => 'dienst_shift',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish', 'draft' ],
				'meta_query'       => [
					[
						'key'   => 'template_id',
						'value' => $template_id,
					],
				],
			]
		);

		$totals = [
			'deleted'  => 0,
			'detached' => 0,
		];
		foreach ( $shift_ids as $shift_id ) {
			++$totals[ self::delete_or_detach( (int) $shift_id ) ];
		}

		return $totals;
	}

	/**
	 * Sweep shifts whose sjabloon no longer exists. Templates deleted before
	 * cascade-cleanup existed (or removed straight from the database) left
	 * their rolled-out shifts orphaned with a dangling `template_id`; this
	 * cleans those up with the same rules as a live template delete. Runs
	 * nightly before expansion so production heals itself.
	 *
	 * @return array{deleted:int, detached:int}
	 */
	public static function cleanup_orphaned_shifts(): array {
		$shift_ids = get_posts(
			[
				'post_type'        => 'dienst_shift',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish', 'draft' ],
				'meta_query'       => [
					[
						'key'     => 'template_id',
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$totals          = [
			'deleted'  => 0,
			'detached' => 0,
		];
		$template_exists = [];

		foreach ( $shift_ids as $shift_id ) {
			$shift_id    = (int) $shift_id;
			$template_id = (int) get_post_meta( $shift_id, 'template_id', true );
			if ( $template_id <= 0 ) {
				continue;
			}
			if ( ! isset( $template_exists[ $template_id ] ) ) {
				$template_exists[ $template_id ] = get_post_type( $template_id ) === 'shift_template';
			}
			if ( $template_exists[ $template_id ] ) {
				continue;
			}
			++$totals[ self::delete_or_detach( $shift_id ) ];
		}

		return $totals;
	}

	/**
	 * Apply the template-gone rules to one shift: future, untouched shifts are
	 * deleted; anything protected or in the past stays but loses its dangling
	 * sjabloonverwijzing and continues as a standalone shift.
	 *
	 * @return string 'deleted' or 'detached'.
	 */
	private static function delete_or_detach( int $shift_id ): string {
		$today = gmdate( 'Y-m-d' ) . ' 00:00:00';
		$start = (string) get_post_meta( $shift_id, 'start_datetime', true );

		if ( $start >= $today && self::preserve_reason( $shift_id ) === null && wp_delete_post( $shift_id, true ) ) {
			return 'deleted';
		}

		delete_post_meta( $shift_id, 'template_id' );
		delete_post_meta( $shift_id, '_shift_customized' );
		return 'detached';
	}

	/**
	 * Why a template-managed shift must survive a re-rollout or template
	 * delete: manual customization, an explicit cancellation kept for history,
	 * or members already signed up.
	 *
	 * @return string|null 'customized' | 'cancelled' | 'signups', or null when
	 *                     the shift is safe to delete.
	 */
	private static function preserve_reason( int $shift_id ): ?string {
		if ( (int) get_post_meta( $shift_id, '_shift_customized', true ) === 1 ) {
			return 'customized';
		}
		if ( (string) get_post_meta( $shift_id, 'status', true ) === 'geannuleerd' ) {
			return 'cancelled';
		}
		if ( ! empty( ShiftAssignments::person_ids( $shift_id ) ) ) {
			return 'signups';
		}
		return null;
	}

	/**
	 * Delete every future template-managed shift for one template, then expand
	 * the template afresh over the same window. Preserves customized shifts,
	 * shifts with signups and cancelled shifts (kept for history).
	 *
	 * @param int    $template_id Template post ID.
	 * @param string $from        Y-m-d start date.
	 * @param string $to          Y-m-d end date.
	 * @return array{deleted:int, created:int, kept:int, kept_signups:int}
	 */
	public static function rerun_template( int $template_id, string $from, string $to ): array {
		$shift_ids = get_posts(
			[
				'post_type'        => 'dienst_shift',
				'posts_per_page'   => -1,
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
						'key'     => 'start_datetime',
						'value'   => [ $from . ' 00:00:00', $to . ' 23:59:59' ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
				],
			]
		);

		$deleted      = 0;
		$kept         = 0;
		$kept_signups = 0;

		foreach ( $shift_ids as $shift_id ) {
			$shift_id = (int) $shift_id;
			$reason   = self::preserve_reason( $shift_id );

			if ( $reason === 'signups' ) {
				++$kept_signups;
				continue;
			}
			if ( $reason !== null ) {
				++$kept;
				continue;
			}

			if ( wp_delete_post( $shift_id, true ) ) {
				++$deleted;
			}
		}

		$created = self::expand_template( $template_id, $from, $to );

		return [
			'deleted'      => $deleted,
			'created'      => $created,
			'kept'         => $kept,
			'kept_signups' => $kept_signups,
		];
	}

	/**
	 * Expand all templates through the explicitly selected date.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_expand_all( \WP_REST_Request $request ) {
		$from  = current_datetime()->format( 'Y-m-d' );
		$until = (string) $request->get_param( 'until' );

		if ( ! self::is_valid_date( $until ) ) {
			return new \WP_Error(
				'rondo_invalid_expansion_date',
				'Kies een geldige einddatum.',
				[ 'status' => 400 ]
			);
		}

		if ( $until < $from ) {
			return new \WP_Error(
				'rondo_expansion_date_in_past',
				'De einddatum mag niet vóór vandaag liggen.',
				[ 'status' => 400 ]
			);
		}

		$created = self::expand_range( $from, $until );
		return rest_ensure_response(
			[
				'created' => (int) $created,
				'from'    => $from,
				'until'   => $until,
			]
		);
	}

	/**
	 * Validate a strict ISO calendar date.
	 */
	private static function is_valid_date( string $date ): bool {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, new \DateTimeZone( 'UTC' ) );
		return $parsed !== false && $parsed->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Expand the saved template immediately so the user sees concrete shifts
	 * for the next three months without waiting for the daily cron. Idempotent —
	 * relies on `find_existing_shift()` to skip already-rolled-out dates.
	 *
	 * @param int|string $post_id native field save_post payload (post ID or "options").
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
	 * Expand the standard window: from today to the end of the season.
	 *
	 * A rolling three-month window meant coordinators could not plan or even see
	 * the second half of the season, and volunteers could not see what was
	 * coming. Expanding to season end makes the whole club year real; the
	 * expansion is idempotent, so after the first run each night creates almost
	 * nothing.
	 */
	public function expand_default_window(): int {
		$orphans = self::cleanup_orphaned_shifts();
		if ( $orphans['deleted'] > 0 || $orphans['detached'] > 0 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log(
				sprintf(
					'[Rondo Volunteer] Orphaned shift sweep: %d deleted, %d detached.',
					$orphans['deleted'],
					$orphans['detached']
				)
			);
		}

		$from = gmdate( 'Y-m-d' );
		return self::expand_range( $from, self::default_window_end( $from ) );
	}

	/**
	 * End of the expansion window: the season's last day, never closer than
	 * WINDOW_DAYS.
	 *
	 * The floor matters in June: without it the last weeks of a season would
	 * expand almost nothing, leaving the club with an empty calendar exactly
	 * while next season's templates are being set up.
	 *
	 * @param string|null $today Y-m-d, defaults to today.
	 */
	public static function default_window_end( ?string $today = null ): string {
		$today       = $today ?: gmdate( 'Y-m-d' );
		$season      = \Rondo\Fees\SeasonKey::current( $today );
		$season_end  = substr( $season, 5, 4 ) . '-06-30';
		$minimum_end = gmdate( 'Y-m-d', strtotime( $today . ' +' . self::WINDOW_DAYS . ' days' ) );

		return max( $season_end, $minimum_end );
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
		$iva_waived     = (bool) get_post_meta( $template_id, 'iva_waived', true );

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
				// Store the canonical `Y-m-d H:i:s` form native field also writes, so an admin
				// edit through the shift editor can't produce a phantom mismatch that
				// re-spawns a duplicate on the next expansion.
				$start_datetime = gmdate( 'Y-m-d', $cursor ) . ' ' . self::normalize_time( $start_time ) . ':00';
				$end_datetime   = gmdate( 'Y-m-d', $cursor ) . ' ' . self::normalize_time( $end_time ) . ':00';

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
						update_post_meta( $post_id, 'end_datetime', $end_datetime );
						update_post_meta( $post_id, 'capacity', $capacity > 0 ? $capacity : 1 );
						update_post_meta( $post_id, 'status', 'open' );
						update_post_meta( $post_id, 'iva_waived', $iva_waived ? 1 : 0 );
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
	 *
	 * Historic rows were stored without seconds (`Y-m-d H:i`); current rows carry
	 * the canonical `Y-m-d H:i:s`. Match both so neither legacy data nor an
	 * native field-normalized edit slips past the de-dup and spawns a duplicate.
	 */
	private static function find_existing_shift( int $template_id, string $start_datetime ): int {
		$variants = array_values( array_unique( [ $start_datetime, preg_replace( '/:00$/', '', $start_datetime ) ] ) );

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
						'key'     => 'start_datetime',
						'value'   => $variants,
						'compare' => 'IN',
					],
				],
			]
		);

		return empty( $query->posts ) ? 0 : (int) $query->posts[0];
	}

	/**
	 * Normalize a time input to HH:MM (handles "9:00" → "09:00", "9" → "09:00", etc.).
	 *
	 * Seconds are dropped, not kept: templates saved through wp-admin store
	 * `start_time` as `HH:MM:SS`, and the callers append their own `:00`. Passing
	 * those through produced `2027-03-06 14:00:00:00`, which `strtotime()` rejects
	 * — so every shift from such a template got a "01-01-1970 00:00" title.
	 */
	private static function normalize_time( string $time ): string {
		$time = trim( $time );
		if ( $time === '' ) {
			return '00:00';
		}
		if ( preg_match( '/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $m ) ) {
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
