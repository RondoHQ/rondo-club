<?php
/**
 * Member Shifts REST endpoints — the `/vrijwillig` surface from #4.
 *
 * Powers the self-service signup flow for logged-in members. Eligibility,
 * VOG/IVA hard-blocks and the overlap warning are all enforced server-side
 * so a frontend without these checks would still be safe.
 *
 * Endpoints:
 *   GET  /rondo/v1/my-shifts              — current user's assigned + completed shifts plus their counter
 *   GET  /rondo/v1/people/{id}/shifts      — person's active future shifts plus their two most recent past shifts
 *   GET  /rondo/v1/shifts/recent-signups   — 50 shifts with the most recent current signups
 *   GET  /rondo/v1/shifts/available       — open shifts the current user can sign up for
 *   POST /rondo/v1/shifts/{id}/signup     — add current user to a shift
 *   POST /rondo/v1/shifts/{id}/cancel     — remove current user from a shift (afmelden mag altijd)
 *
 * All endpoints resolve the calling user to their linked `person` via
 * `rondo_linked_person_id` user meta — matches the v30.0 provisioning convention.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Fees\SeasonKey;
use Rondo\Users\GuardianAccountService;
use Rondo\Volunteer\IvaStatus;
use Rondo\Volunteer\ShiftCancellationService;
use Rondo\Volunteer\ShiftEmailScheduler;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerExemptionResolver;
use Rondo\Volunteer\VolunteerObligationCalculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MemberShifts extends Base {

	/**
	 * Window (days ahead from "now") of available shifts to consider.
	 * Mirrors ShiftTemplateExpander::WINDOW_DAYS so nothing falls out of view.
	 */
	const AVAILABLE_WINDOW_DAYS = 93;

	/** Longest calendar range accepted by the API. */
	const CALENDAR_MAX_DAYS = 190;

	/** Members may cancel until this many days before a shift starts. */
	const CANCEL_DEADLINE_DAYS = 21;

	/** Grace period after signup for correcting an accidental click. */
	const CANCEL_GRACE_SECONDS = 30 * MINUTE_IN_SECONDS;

	/** Maximum time to wait for another write on the same shift. */
	const SHIFT_LOCK_TIMEOUT_SECONDS = 10;

	/** Locks older than this are treated as abandoned after a fatal request. */
	const SHIFT_LOCK_STALE_SECONDS = 15;

	/**
	 * Refresh one value from the persistent object cache.
	 *
	 * SiteGround's Memcached drop-in keeps a per-request runtime copy and only
	 * unsets it when a shared-cache delete succeeds. A waiter can therefore keep
	 * an old value after the lock owner has already deleted the shared key. The
	 * force flag bypasses that runtime copy without flushing unrelated caches.
	 *
	 * @param int|string $key   Cache key.
	 * @param string     $group Cache group.
	 */
	private function refresh_cache_value( $key, string $group ): void {
		wp_cache_get( $key, $group, true );
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_prepare_dienst_shift', [ $this, 'add_assignee_display_names' ], 10, 2 );
	}

	/**
	 * Add temporary guardian names to the manager shift editor response.
	 */
	public function add_assignee_display_names( $response, $post ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data     = $response->get_data();
		$assigned = array_map( 'intval', (array) get_post_meta( $post->ID, 'assigned_persons', true ) );
		$names    = [];
		foreach ( $assigned as $person_id ) {
			$names[ $person_id ] = GuardianAccountService::display_name_for_person( $person_id );
		}
		$data['assigned_person_names'] = $names;
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Serialize writes to a shift's assigned-persons array.
	 *
	 * `assigned_persons` is a serialized WordPress meta value. Concurrent
	 * read-modify-write requests would otherwise overwrite each other. An
	 * autoload-disabled option gives us an atomic `add_option()` lock without a
	 * custom table and works independently of the configured object-cache backend.
	 *
	 * @param int      $shift_id Shift post ID.
	 * @param callable $callback Mutation to execute while holding the lock.
	 * @return mixed Callback result or WP_Error when the lock stays busy.
	 */
	private function with_shift_write_lock( int $shift_id, callable $callback ) {
		$lock_key = 'rondo_shift_write_lock_' . $shift_id;
		$deadline = microtime( true ) + self::SHIFT_LOCK_TIMEOUT_SECONDS;
		$token    = wp_generate_uuid4();

		do {
			// A request that already observed this option can retain that value in
			// its runtime cache after another PHP process releases the lock. Force
			// this request to consult the shared cache/database on every attempt.
			$this->refresh_cache_value( $lock_key, 'options' );

			$lock_value = [
				'token'      => $token,
				'created_at' => microtime( true ),
			];
			if ( add_option( $lock_key, $lock_value, '', false ) ) {
				try {
					// The request may have primed this shift's post-meta cache while
					// loading the available-shifts list before it waited for the lock.
					// Discard that snapshot so the mutation starts with the latest row.
					$this->refresh_cache_value( $shift_id, 'post_meta' );
					return $callback();
				} finally {
					$this->refresh_cache_value( $lock_key, 'options' );
					$current = get_option( $lock_key, [] );
					if ( is_array( $current ) && ( $current['token'] ?? '' ) === $token ) {
						delete_option( $lock_key );
					}
				}
			}

			$this->refresh_cache_value( $lock_key, 'options' );
			$current = get_option( $lock_key, [] );
			if (
				is_array( $current )
				&& isset( $current['created_at'], $current['token'] )
				&& (float) $current['created_at'] < microtime( true ) - self::SHIFT_LOCK_STALE_SECONDS
			) {
				$this->refresh_cache_value( $lock_key, 'options' );
				$latest = get_option( $lock_key, [] );
				if ( is_array( $latest ) && ( $latest['token'] ?? '' ) === $current['token'] ) {
					delete_option( $lock_key );
				}
			}

			usleep( 25000 );
		} while ( microtime( true ) < $deadline );

		return new \WP_Error(
			'shift_busy',
			'Deze inschrijftaak wordt op dit moment bijgewerkt. Probeer het opnieuw.',
			[ 'status' => 503 ]
		);
	}

	public function register_routes() {
		register_rest_field(
			'dienst_shift',
			'cancellation',
			[
				'get_callback' => static function ( array $object ) {
					return ShiftCancellationService::details( (int) ( $object['id'] ?? 0 ) );
				},
				'schema'       => [
					'description' => 'Auditgegevens van een geannuleerde inschrijftaak.',
					'type'        => [ 'object', 'null' ],
					'context'     => [ 'view', 'edit' ],
					'readonly'    => true,
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/my-shifts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_my_shifts' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'season' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/shifts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_person_shifts' ],
				'permission_callback' => [ $this, 'check_person_access' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/shifts/available',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_available_shifts' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/shifts/recent-signups',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_recent_signups' ],
				'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/shifts/calendar',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_shift_calendar' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'from'           => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'to'             => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'dienst_type_id' => [
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
					'view'           => [
						'required'          => false,
						'default'           => 'signup',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/shifts/(?P<id>\d+)/signup',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'signup' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'id'            => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'force_overlap' => [
						'required' => false,
						'default'  => false,
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/shifts/(?P<id>\d+)/cancel',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/shifts/(?P<id>\d+)/cancellation',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel_shift' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ) || current_user_can( 'vrijwilligers' );
				},
				'args'                => [
					'id'     => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'reason' => [
						'required'          => false,
						'default'           => '',
						'sanitize_callback' => 'sanitize_textarea_field',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/shifts/(?P<id>\d+)/assignees/(?P<person_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_assignee' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' ) || current_user_can( 'vrijwilligers' );
				},
				'args'                => [
					'id'        => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/**
	 * Resolve the caller's linked person, or WP_Error.
	 */
	private function current_person_id(): int {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return 0;
		}
		return (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
	}

	public function get_my_shifts( \WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$person_id = $this->current_person_id();
		if ( $person_id <= 0 ) {
			return new \WP_Error( 'no_person', 'Geen gekoppelde persoon gevonden voor dit account.', [ 'status' => 404 ] );
		}

		$season      = (string) ( $request->get_param( 'season' ) ?: SeasonKey::current() );
		$eligibility = new VolunteerEligibilityService();
		$calculator  = new VolunteerObligationCalculator();

		$pending_guardian = GuardianAccountService::pending_for_user( $user_id );
		$units            = $eligibility->get_eligible_units_for_person( $person_id, $season );
		if ( $pending_guardian && $pending_guardian['child_id'] === $person_id ) {
			$gezin_unit = $eligibility->get_gezin_unit_for_youth( $person_id, $season );
			$units      = $gezin_unit ? [ $gezin_unit ] : [];
		}
		$obligations = $units ? $calculator->decorate_units( $units, $season ) : [];

		$shifts = $this->query_shifts_for_person( $person_id );

		return rest_ensure_response(
			[
				'person_id'      => $person_id,
				'season'         => $season,
				'obligations'    => $obligations,
				'exemption'      => $this->resolve_exemption_block( $person_id, $season ),
				'iva_status'     => IvaStatus::status( $person_id ),
				'iva_expires_at' => IvaStatus::expires_at( $person_id ),
				'shifts'         => $shifts,
				'identity'       => [
					'linked_person_name' => get_the_title( $person_id ),
					'is_youth'           => GuardianAccountService::is_youth_person( $person_id ),
					'pending_guardian'   => $pending_guardian,
				],
			]
		);
	}

	/**
	 * Return the active future shifts and two most recent past shifts for a person.
	 *
	 * This read-only profile response deliberately omits fellow-volunteer contact
	 * details and internal assignee IDs. Access follows the same person visibility
	 * rules as the rest of the person detail screen.
	 */
	public function get_person_shifts( \WP_REST_Request $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$person    = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Persoon bestaat niet.', [ 'status' => 404 ] );
		}

		$now      = current_datetime()->getTimestamp();
		$upcoming = [];
		$recent   = [];

		foreach ( $this->query_shifts_for_person( $person_id, false, -1 ) as $shift ) {
			$start = $this->shift_start_timestamp( (int) $shift['id'] );
			if ( $start === null ) {
				continue;
			}

			if ( $start >= $now ) {
				if ( in_array( $shift['status'], [ 'open', 'vol' ], true ) ) {
					$upcoming[] = $shift;
				}
				continue;
			}

			$recent[] = $shift;
		}

		usort(
			$upcoming,
			fn( array $a, array $b ): int => strcmp( $a['start_datetime'], $b['start_datetime'] )
		);
		usort(
			$recent,
			fn( array $a, array $b ): int => strcmp( $b['start_datetime'], $a['start_datetime'] )
		);

		return rest_ensure_response(
			[
				'person_id' => $person_id,
				'upcoming'  => $upcoming,
				'recent'    => array_slice( $recent, 0, 2 ),
			]
		);
	}

	/**
	 * Return at most 50 shifts ordered by their latest current signup.
	 *
	 * A signup remains current only while the person is still present in the
	 * shift's `assigned_persons` value. Manager-added assignees without a signup
	 * timestamp are deliberately omitted: this overview tracks self-service
	 * registrations, not every assignment edit.
	 */
	public function get_recent_signups() {
		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- dynamic per-person signup keys must be inspected before the top 50 can be selected.
				'posts_per_page'   => -1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
				'meta_query'       => [
					[
						'key'         => '_shift_signup_at_',
						'compare_key' => 'LIKE',
						'compare'     => 'EXISTS',
					],
				],
			]
		);

		$shifts         = [];
		$seen_shift_ids = [];
		foreach ( $query->posts as $shift ) {
			if ( isset( $seen_shift_ids[ $shift->ID ] ) ) {
				continue;
			}
			$seen_shift_ids[ $shift->ID ] = true;

			$assigned = array_map( 'intval', (array) get_post_meta( $shift->ID, 'assigned_persons', true ) );
			$signups  = [];
			foreach ( $assigned as $person_id ) {
				$timestamp = (int) get_post_meta( $shift->ID, '_shift_signup_at_' . $person_id, true );
				if ( $timestamp <= 0 ) {
					continue;
				}

				$name = $this->sanitize_text( GuardianAccountService::display_name_for_person( $person_id ) );
				if ( $name === '' ) {
					continue;
				}

				$signups[] = [
					'person_id'    => $person_id,
					'name'         => $name,
					'signed_up_at' => wp_date( 'c', $timestamp ),
					'timestamp'    => $timestamp,
				];
			}

			if ( empty( $signups ) ) {
				continue;
			}

			usort( $signups, fn( array $a, array $b ): int => $b['timestamp'] <=> $a['timestamp'] );
			$summary = $this->format_shift_summary( $shift );
			if ( $summary === null ) {
				continue;
			}

			$latest_timestamp = $signups[0]['timestamp'];
			unset( $summary['assigned_person_ids'] );
			foreach ( $signups as &$signup ) {
				unset( $signup['timestamp'] );
			}
			unset( $signup );

			$summary['latest_signup_at'] = $signups[0]['signed_up_at'];
			$summary['latest_timestamp'] = $latest_timestamp;
			$summary['signups']          = $signups;
			$shifts[]                    = $summary;
		}

		usort( $shifts, fn( array $a, array $b ): int => $b['latest_timestamp'] <=> $a['latest_timestamp'] );
		$shifts = array_slice( $shifts, 0, 50 );
		foreach ( $shifts as &$shift ) {
			unset( $shift['latest_timestamp'] );
		}
		unset( $shift );

		return rest_ensure_response( [ 'shifts' => $shifts ] );
	}

	public function get_available_shifts( \WP_REST_Request $request ) {
		$person_id = $this->current_person_id();
		if ( $person_id <= 0 ) {
			return new \WP_Error( 'no_person', 'Geen gekoppelde persoon gevonden voor dit account.', [ 'status' => 404 ] );
		}

		// Owing an obligation is not a precondition for helping out. Anyone still on the
		// books may claim a shift; only oud-leden are turned away.
		if ( ! ( new VolunteerEligibilityService() )->may_volunteer( $person_id ) ) {
			return rest_ensure_response(
				[
					'person_id'    => $person_id,
					'eligible'     => false,
					'shifts'       => [],
					'block_reason' => 'Je bent geen actief lid meer.',
				]
			);
		}

		$blocks  = $this->signup_blocks( $person_id );
		$now     = gmdate( 'Y-m-d H:i:s' );
		$horizon = gmdate( 'Y-m-d H:i:s', strtotime( '+' . self::AVAILABLE_WINDOW_DAYS . ' days' ) );

		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded by AVAILABLE_WINDOW_DAYS horizon, intentional cap.
				'posts_per_page'   => 200,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'     => 'start_datetime',
						'value'   => [ $now, $horizon ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
					[
						'key'     => 'status',
						'value'   => 'open',
						'compare' => '=',
					],
				],
				'orderby'          => 'meta_value',
				'meta_key'         => 'start_datetime',
				'order'            => 'ASC',
			]
		);

		$out              = [];
		$effective_blocks = [];
		foreach ( $query->posts as $shift ) {
			$summary = $this->format_shift_summary( $shift );
			if ( $summary === null ) {
				continue;
			}

			$block_reason = $this->member_shift_block_reason( $shift->ID, $person_id, $blocks );
			if ( $block_reason !== null ) {
				if ( in_array( $block_reason, [ 'vog', 'iva' ], true ) ) {
					$effective_blocks[ $block_reason ] = true;
				}
				continue;
			}

			$fellow_volunteers = $this->format_fellow_volunteer_contacts( $summary['assigned_person_ids'], $person_id );

			$summary['is_signed_up']                = in_array( $person_id, $summary['assigned_person_ids'], true );
			$summary['can_signup']                  = ! $summary['is_signed_up'] && $summary['spots_remaining'] !== 0;
			$summary['fellow_volunteers']           = array_column( $fellow_volunteers, 'name' );
			$summary['can_cancel']                  = $summary['is_signed_up'] && $this->can_member_cancel( $shift->ID, $person_id );
			$cancel_deadline                        = $this->cancel_deadline_timestamp( $shift->ID );
			$summary['signup_is_final_after_grace'] = $cancel_deadline !== null && time() > $cancel_deadline;
			unset( $summary['assigned_person_ids'] );

			$out[] = $summary;
		}

		return rest_ensure_response(
			[
				'person_id'     => $person_id,
				'eligible'      => true,
				'iva_status'    => IvaStatus::status( $person_id ),
				'block_reasons' => array_keys( $effective_blocks ),
				'shifts'        => $out,
			]
		);
	}

	/**
	 * Return shift coverage for the manager and signup calendars.
	 */
	public function get_shift_calendar( \WP_REST_Request $request ) {
		$view = (string) ( $request->get_param( 'view' ) ?: 'signup' );
		if ( ! in_array( $view, [ 'manage', 'signup' ], true ) ) {
			return new \WP_Error( 'invalid_calendar_view', 'Onbekende kalenderweergave.', [ 'status' => 400 ] );
		}

		if ( $view === 'manage' && ! current_user_can( 'manage_options' ) && ! current_user_can( 'vrijwilligers' ) ) {
			return new \WP_Error( 'rest_forbidden', 'Je hebt geen toegang tot de inschrijftakenkalender.', [ 'status' => 403 ] );
		}

		$range = $this->calendar_range( $request );
		if ( is_wp_error( $range ) ) {
			return $range;
		}
		[ $from, $to ] = $range;

		$person_id = 0;
		$blocks    = [];
		if ( $view === 'signup' ) {
			$person_id = $this->current_person_id();
			if ( $person_id <= 0 ) {
				return new \WP_Error( 'no_person', 'Geen gekoppelde persoon gevonden voor dit account.', [ 'status' => 404 ] );
			}
			if ( ! ( new VolunteerEligibilityService() )->may_volunteer( $person_id ) ) {
				return rest_ensure_response( $this->empty_calendar_response( $view, $from, $to ) );
			}
			$blocks = $this->signup_blocks( $person_id );
		}

		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded by the validated calendar range.
				'posts_per_page'   => 1000,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'     => 'start_datetime',
						'value'   => [ $from->format( 'Y-m-d 00:00:00' ), $to->format( 'Y-m-d 23:59:59' ) ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
					[
						'key'     => 'status',
						'value'   => [ 'open', 'vol' ],
						'compare' => 'IN',
					],
				],
				'orderby'          => 'meta_value',
				'meta_key'         => 'start_datetime',
				'order'            => 'ASC',
			]
		);

		$selected_type    = (int) $request->get_param( 'dienst_type_id' );
		$types            = [];
		$days             = [];
		$effective_blocks = [];
		foreach ( $query->posts as $shift ) {
			$summary = $this->format_shift_summary( $shift );
			if ( $summary === null ) {
				continue;
			}

			$type_id = (int) $summary['dienst_type_id'];
			if ( $view === 'signup' ) {
				$block_reason = $this->member_shift_block_reason( $shift->ID, $person_id, $blocks );
				if ( $block_reason !== null ) {
					if ( ( $selected_type <= 0 || $type_id === $selected_type ) && in_array( $block_reason, [ 'vog', 'iva' ], true ) ) {
						$effective_blocks[ $block_reason ] = true;
					}
					continue;
				}
			}

			if ( $type_id > 0 ) {
				$types[ $type_id ] = [
					'id'    => $type_id,
					'name'  => $this->sanitize_text( $summary['dienst_type_name'] ),
					'color' => sanitize_hex_color( $summary['dienst_type_color'] ) ?: '#6b7280',
				];
			}
			if ( $selected_type > 0 && $type_id !== $selected_type ) {
				continue;
			}

			$assigned_ids         = $summary['assigned_person_ids'];
			$is_filled            = $summary['capacity'] > 0 && $summary['assigned_count'] >= $summary['capacity'];
			$summary['is_filled'] = $is_filled;
			if ( $view === 'signup' ) {
				$fellow_volunteers                      = $this->format_fellow_volunteer_contacts( $assigned_ids, $person_id );
				$summary['is_signed_up']                = in_array( $person_id, $assigned_ids, true );
				$summary['can_signup']                  = $summary['status'] === 'open' && ! $summary['is_signed_up'] && $summary['spots_remaining'] !== 0;
				$summary['fellow_volunteers']           = array_column( $fellow_volunteers, 'name' );
				$summary['can_cancel']                  = $summary['is_signed_up'] && $this->can_member_cancel( $shift->ID, $person_id );
				$cancel_deadline                        = $this->cancel_deadline_timestamp( $shift->ID );
				$summary['signup_is_final_after_grace'] = $cancel_deadline !== null && time() > $cancel_deadline;
			}
			unset( $summary['assigned_person_ids'] );

			$date = substr( $summary['start_datetime'], 0, 10 );
			if ( ! isset( $days[ $date ] ) ) {
				$days[ $date ] = [
					'date'            => $date,
					'state'           => 'full',
					'shift_count'     => 0,
					'capacity'        => 0,
					'assigned_count'  => 0,
					'spots_remaining' => 0,
					'shifts'          => [],
				];
			}
			++$days[ $date ]['shift_count'];
			$required_capacity                 = max( 1, (int) $summary['capacity'] );
			$days[ $date ]['capacity']        += $required_capacity;
			$days[ $date ]['assigned_count']  += min( $required_capacity, (int) $summary['assigned_count'] );
			$days[ $date ]['spots_remaining'] += max( 0, $required_capacity - (int) $summary['assigned_count'] );
			$days[ $date ]['shifts'][]         = $summary;
			if ( ! $is_filled ) {
				$days[ $date ]['state'] = 'open';
			}
		}

		usort( $types, static fn( array $a, array $b ): int => strnatcasecmp( $a['name'], $b['name'] ) );

		return rest_ensure_response(
			[
				'view'          => $view,
				'from'          => $from->format( 'Y-m-d' ),
				'to'            => $to->format( 'Y-m-d' ),
				'dienst_types'  => array_values( $types ),
				'block_reasons' => array_keys( $effective_blocks ),
				'days'          => array_values( $days ),
			]
		);
	}

	/**
	 * Validate the requested calendar range in the WordPress timezone.
	 *
	 * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}|\WP_Error
	 */
	private function calendar_range( \WP_REST_Request $request ) {
		$timezone = wp_timezone();
		$today    = current_datetime()->setTime( 0, 0, 0 );
		$from     = $this->parse_calendar_date( (string) $request->get_param( 'from' ), $timezone ) ?: $today;
		$to       = $this->parse_calendar_date( (string) $request->get_param( 'to' ), $timezone ) ?: $from->modify( 'first day of this month' )->modify( '+6 months -1 day' );

		if ( $request->get_param( 'from' ) && ! $this->parse_calendar_date( (string) $request->get_param( 'from' ), $timezone ) ) {
			return new \WP_Error( 'invalid_calendar_from', 'De begindatum is ongeldig.', [ 'status' => 400 ] );
		}
		if ( $request->get_param( 'to' ) && ! $this->parse_calendar_date( (string) $request->get_param( 'to' ), $timezone ) ) {
			return new \WP_Error( 'invalid_calendar_to', 'De einddatum is ongeldig.', [ 'status' => 400 ] );
		}
		if ( $to < $from ) {
			return new \WP_Error( 'invalid_calendar_range', 'De einddatum moet na de begindatum liggen.', [ 'status' => 400 ] );
		}
		if ( (int) $from->diff( $to )->format( '%a' ) > self::CALENDAR_MAX_DAYS ) {
			return new \WP_Error(
				'calendar_range_too_large',
				sprintf( 'De kalenderperiode mag maximaal %d dagen zijn.', self::CALENDAR_MAX_DAYS ),
				[ 'status' => 400 ]
			);
		}

		return [ $from, $to ];
	}

	private function parse_calendar_date( string $value, \DateTimeZone $timezone ): ?\DateTimeImmutable {
		if ( $value === '' ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, $timezone );
		return $date && $date->format( 'Y-m-d' ) === $value ? $date : null;
	}

	private function empty_calendar_response( string $view, \DateTimeImmutable $from, \DateTimeImmutable $to ): array {
		return [
			'view'          => $view,
			'from'          => $from->format( 'Y-m-d' ),
			'to'            => $to->format( 'Y-m-d' ),
			'dienst_types'  => [],
			'block_reasons' => [],
			'days'          => [],
		];
	}

	/**
	 * Return the reason a shift is hidden from a member, or null when visible.
	 *
	 * Certificate reasons are also used to decide whether the member-facing
	 * warning banners are relevant to the shifts in the requested result set.
	 */
	private function member_shift_block_reason( int $shift_id, int $person_id, array $blocks ): ?string {
		$dienst_type_id = (int) get_post_meta( $shift_id, 'dienst_type_id', true );
		if ( $dienst_type_id <= 0 ) {
			return null;
		}

		if ( (bool) get_post_meta( $dienst_type_id, 'vog_required', true ) && in_array( 'vog', $blocks, true ) ) {
			return 'vog';
		}

		$requires_iva = (bool) get_post_meta( $dienst_type_id, 'iva_required', true );
		if ( $requires_iva && ! (bool) get_post_meta( $shift_id, 'iva_waived', true ) && in_array( 'iva', $blocks, true ) ) {
			return 'iva';
		}

		$required_pool = (int) get_post_meta( $dienst_type_id, 'required_pool', true );
		if ( $required_pool > 0 && ! $this->person_is_pool_member( $person_id, $required_pool ) ) {
			return 'pool';
		}

		return null;
	}

	public function signup( \WP_REST_Request $request ) {
		$person_id = $this->current_person_id();
		$shift_id  = (int) $request->get_param( 'id' );
		$force     = filter_var( $request->get_param( 'force_overlap' ), FILTER_VALIDATE_BOOLEAN );

		if ( $person_id <= 0 ) {
			return new \WP_Error( 'no_person', 'Geen gekoppelde persoon.', [ 'status' => 404 ] );
		}
		if ( ! ( new VolunteerEligibilityService() )->may_volunteer( $person_id ) ) {
			return new \WP_Error( 'not_eligible', 'Je bent geen actief lid meer.', [ 'status' => 403 ] );
		}

		$shift = get_post( $shift_id );
		if ( ! $shift || $shift->post_type !== 'dienst_shift' ) {
			return new \WP_Error( 'invalid_shift', 'Inschrijftaak bestaat niet.', [ 'status' => 404 ] );
		}

		$dienst_type_id = (int) get_post_meta( $shift_id, 'dienst_type_id', true );
		$blocks         = $this->signup_blocks( $person_id );
		if ( $dienst_type_id > 0 ) {
			if ( get_post_meta( $dienst_type_id, 'vog_required', true ) && in_array( 'vog', $blocks, true ) ) {
				return new \WP_Error( 'vog_required', 'Voor deze inschrijftaak is een geldige VOG vereist.', [ 'status' => 403 ] );
			}
			$iva_waived = (bool) get_post_meta( $shift_id, 'iva_waived', true );
			if ( ! $iva_waived && get_post_meta( $dienst_type_id, 'iva_required', true ) && in_array( 'iva', $blocks, true ) ) {
				return new \WP_Error( 'iva_required', 'Voor deze inschrijftaak is een geldig IVA-certificaat vereist.', [ 'status' => 403 ] );
			}
			$required_pool = (int) get_post_meta( $dienst_type_id, 'required_pool', true );
			if ( $required_pool > 0 && ! $this->person_is_pool_member( $person_id, $required_pool ) ) {
				return new \WP_Error( 'pool_membership_required', 'Deze inschrijftaak is alleen beschikbaar voor leden van de bijbehorende vrijwilligerspool.', [ 'status' => 403 ] );
			}
		}

		// Overlap check (warning, not block, unless force=false explicitly opted out).
		if ( ! $force ) {
			$overlap = $this->find_overlapping_shift( $person_id, $shift_id );
			if ( $overlap !== null ) {
				return new \WP_Error(
					'overlap_warning',
					sprintf( 'Deze inschrijftaak overlapt met een bestaande aanmelding (%s).', $overlap['title'] ),
					[
						'status'        => 409,
						'shift_id'      => $shift_id,
						'overlap_shift' => $overlap,
						'can_force'     => true,
					]
				);
			}
		}

		return $this->with_shift_write_lock(
			$shift_id,
			function () use ( $person_id, $shift_id ) {
				$status = (string) get_post_meta( $shift_id, 'status', true );
				if ( $status !== 'open' && $status !== 'vol' ) {
					return new \WP_Error( 'shift_closed', 'Deze inschrijftaak staat niet meer open.', [ 'status' => 409 ] );
				}

				$assigned = array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) );
				if ( in_array( $person_id, $assigned, true ) ) {
					return rest_ensure_response(
						[
							'shift_id'          => $shift_id,
							'already_signed_up' => true,
						]
					);
				}

				$capacity = (int) get_post_meta( $shift_id, 'capacity', true );
				if ( $capacity > 0 && count( $assigned ) >= $capacity ) {
					return new \WP_Error( 'shift_full', 'Deze inschrijftaak is vol.', [ 'status' => 409 ] );
				}

				$assigned[] = $person_id;
				$assigned   = array_values( array_unique( $assigned ) );
				update_post_meta( $shift_id, 'assigned_persons', $assigned );
				update_post_meta( $shift_id, '_shift_signup_at_' . $person_id, time() );
				GuardianAccountService::mark_shift_signup( $shift_id, $person_id, get_current_user_id() );
				ShiftEmailScheduler::queue_signup_confirmation( $person_id, $shift_id );

				if ( $capacity > 0 && count( $assigned ) >= $capacity ) {
					update_post_meta( $shift_id, 'status', 'vol' );
				}
				VolunteerObligationCalculator::invalidate_cache();

				return rest_ensure_response(
					[
						'shift_id'  => $shift_id,
						'signed_up' => true,
					]
				);
			}
		);
	}

	public function cancel( \WP_REST_Request $request ) {
		$person_id = $this->current_person_id();
		$shift_id  = (int) $request->get_param( 'id' );

		if ( $person_id <= 0 ) {
			return new \WP_Error( 'no_person', 'Geen gekoppelde persoon.', [ 'status' => 404 ] );
		}

		$shift = get_post( $shift_id );
		if ( ! $shift || $shift->post_type !== 'dienst_shift' ) {
			return new \WP_Error( 'invalid_shift', 'Inschrijftaak bestaat niet.', [ 'status' => 404 ] );
		}

		return $this->with_shift_write_lock(
			$shift_id,
			function () use ( $person_id, $shift_id ) {
				$status = (string) get_post_meta( $shift_id, 'status', true );
				if ( in_array( $status, [ 'geannuleerd', 'voltooid' ], true ) ) {
					return new \WP_Error( 'shift_closed', 'Een geannuleerde of voltooide inschrijftaak kan niet meer worden afgemeld.', [ 'status' => 409 ] );
				}

				if ( ! $this->can_member_cancel( $shift_id, $person_id ) ) {
					return new \WP_Error(
						'shift_cancel_deadline_passed',
						'Afmelden kan tot 3 weken voor de inschrijftaak, of binnen 30 minuten na je aanmelding. Neem contact op met de vrijwilligerscoördinator.',
						[ 'status' => 409 ]
					);
				}

				$assigned = array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) );
				$filtered = array_values( array_diff( $assigned, [ $person_id ] ) );

				update_post_meta( $shift_id, 'assigned_persons', $filtered );
				delete_post_meta( $shift_id, '_shift_signup_at_' . $person_id );
				GuardianAccountService::unmark_shift_signup( $shift_id, $person_id );
				ShiftEmailScheduler::discard_signup_confirmation( $person_id, $shift_id );
				if ( $status === 'vol' ) {
					update_post_meta( $shift_id, 'status', 'open' );
				}
				VolunteerObligationCalculator::invalidate_cache();

				return rest_ensure_response(
					[
						'shift_id'  => $shift_id,
						'cancelled' => true,
					]
				);
			}
		);
	}

	/**
	 * Cancel an entire shift and notify every retained assignee.
	 */
	public function cancel_shift( \WP_REST_Request $request ) {
		$shift_id = (int) $request->get_param( 'id' );
		$reason   = (string) $request->get_param( 'reason' );
		$shift    = get_post( $shift_id );
		if ( ! $shift || $shift->post_type !== 'dienst_shift' ) {
			return new \WP_Error( 'invalid_shift', 'Inschrijftaak bestaat niet.', [ 'status' => 404 ] );
		}

		$result = $this->with_shift_write_lock(
			$shift_id,
			function () use ( $shift_id, $reason ) {
				$service = new ShiftCancellationService();
				return $service->cancel( $shift_id, $reason, get_current_user_id() );
			}
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$mailer                  = new ShiftEmailScheduler();
		$result['notifications'] = $mailer->send_cancellation_notifications( $shift_id );

		return rest_ensure_response( $result );
	}

	/**
	 * Let a volunteer manager remove an assignee after the member deadline.
	 */
	public function remove_assignee( \WP_REST_Request $request ) {
		$shift_id  = (int) $request->get_param( 'id' );
		$person_id = (int) $request->get_param( 'person_id' );
		$shift     = get_post( $shift_id );
		if ( ! $shift || $shift->post_type !== 'dienst_shift' ) {
			return new \WP_Error( 'invalid_shift', 'Inschrijftaak bestaat niet.', [ 'status' => 404 ] );
		}

		return $this->with_shift_write_lock(
			$shift_id,
			function () use ( $person_id, $shift_id ) {
				$status = (string) get_post_meta( $shift_id, 'status', true );
				if ( in_array( $status, [ 'geannuleerd', 'voltooid' ], true ) ) {
					return new \WP_Error( 'shift_closed', 'Aanmeldingen van een geannuleerde of voltooide inschrijftaak kunnen niet meer worden gewijzigd.', [ 'status' => 409 ] );
				}

				$assigned = array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) );
				if ( ! in_array( $person_id, $assigned, true ) ) {
					return new \WP_Error( 'assignee_not_found', 'Deze persoon is niet voor de inschrijftaak aangemeld.', [ 'status' => 404 ] );
				}

				update_post_meta( $shift_id, 'assigned_persons', array_values( array_diff( $assigned, [ $person_id ] ) ) );
				delete_post_meta( $shift_id, '_shift_signup_at_' . $person_id );
				GuardianAccountService::unmark_shift_signup( $shift_id, $person_id );
				ShiftEmailScheduler::discard_signup_confirmation( $person_id, $shift_id );
				if ( (string) get_post_meta( $shift_id, 'status', true ) === 'vol' ) {
					update_post_meta( $shift_id, 'status', 'open' );
				}
				VolunteerObligationCalculator::invalidate_cache();

				return rest_ensure_response(
					[
						'shift_id'  => $shift_id,
						'person_id' => $person_id,
						'removed'   => true,
					]
				);
			}
		);
	}

	/**
	 * Determine whether a member may still cancel their own signup.
	 */
	private function can_member_cancel( int $shift_id, int $person_id ): bool {
		$start_at = $this->shift_start_timestamp( $shift_id );
		if ( $start_at === null ) {
			return false;
		}

		$now = time();
		if ( $now >= $start_at ) {
			return false;
		}

		$deadline_at = $this->cancel_deadline_timestamp( $shift_id );
		if ( $now <= $deadline_at ) {
			return true;
		}

		$signup_at = (int) get_post_meta( $shift_id, '_shift_signup_at_' . $person_id, true );
		return $signup_at > 0 && $now <= $signup_at + self::CANCEL_GRACE_SECONDS;
	}

	private function cancel_deadline_timestamp( int $shift_id ): ?int {
		$start_at = $this->shift_start_timestamp( $shift_id );
		return $start_at === null ? null : $start_at - ( self::CANCEL_DEADLINE_DAYS * DAY_IN_SECONDS );
	}

	private function shift_start_timestamp( int $shift_id ): ?int {
		$start = (string) get_post_meta( $shift_id, 'start_datetime', true );
		if ( $start === '' ) {
			return null;
		}

		try {
			return ( new \DateTimeImmutable( $start, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $exception ) {
			return null;
		}
	}

	/**
	 * Compute the missing-cert reasons that gate signup for this person.
	 * Returns a subset of ['vog', 'iva'].
	 */
	private function signup_blocks( int $person_id ): array {
		$blocks = [];

		$datum_vog = (string) get_field( 'datum-vog', $person_id );
		// VOG validity = 3 years (existing convention from class-rest-vog.php).
		if ( $datum_vog === '' || strtotime( $datum_vog . ' +3 years' ) < time() ) {
			$blocks[] = 'vog';
		}

		if ( ! IvaStatus::is_valid( $person_id ) ) {
			$blocks[] = 'iva';
		}

		return $blocks;
	}

	private function resolve_exemption_block( int $person_id, string $season ): ?array {
		$reason = VolunteerExemptionResolver::resolve( $person_id, $season );
		if ( $reason === null ) {
			return null;
		}
		return [
			'reason'       => $reason,
			'reason_label' => VolunteerExemptionResolver::reason_label( $reason ),
		];
	}

	/**
	 * Return contact details for the other volunteers on a shift.
	 *
	 * Person IDs stay internal: members only need names to know who they will
	 * work with, not identifiers that can be used to enumerate person records.
	 *
	 * @param int[] $assigned_person_ids Assigned person post IDs.
	 * @return array<int, array{name: string, whatsapp_url: string|null}>
	 */
	private function format_fellow_volunteer_contacts( array $assigned_person_ids, int $current_person_id ): array {
		$contacts = [];
		foreach ( $assigned_person_ids as $assigned_person_id ) {
			$assigned_person_id = (int) $assigned_person_id;
			if ( $assigned_person_id <= 0 || $assigned_person_id === $current_person_id ) {
				continue;
			}

			$person = get_post( $assigned_person_id );
			if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
				continue;
			}

			$name = $this->sanitize_text( GuardianAccountService::display_name_for_person( $assigned_person_id ) );
			if ( $name !== '' ) {
				$contacts[] = [
					'name'         => $name,
					'whatsapp_url' => $this->get_whatsapp_url( $assigned_person_id ),
				];
			}
		}

		return $contacts;
	}

	/**
	 * Build a wa.me link from a person's primary mobile number.
	 */
	private function get_whatsapp_url( int $person_id ): ?string {
		$mobile = (string) get_field( 'mobile_1', $person_id );
		if ( $mobile === '' ) {
			return null;
		}

		$mobile = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', trim( $mobile ) );
		$digits = preg_replace( '/\D/', '', $mobile );
		if ( str_starts_with( $digits, '00' ) ) {
			$digits = substr( $digits, 2 );
		} elseif ( str_starts_with( $digits, '0' ) ) {
			$digits = '31' . substr( $digits, 1 );
		}

		if ( ! preg_match( '/^\d{8,15}$/', $digits ) ) {
			return null;
		}

		return esc_url_raw( 'https://wa.me/' . $digits );
	}

	/**
	 * Format a shift post for both `get_my_shifts` and `get_available_shifts`.
	 */
	private function format_shift_summary( \WP_Post $shift ): ?array {
		$dienst_type_id = (int) get_post_meta( $shift->ID, 'dienst_type_id', true );
		$start          = (string) get_post_meta( $shift->ID, 'start_datetime', true );
		$end            = (string) get_post_meta( $shift->ID, 'end_datetime', true );
		$capacity       = (int) get_post_meta( $shift->ID, 'capacity', true );
		$assigned       = array_map( 'intval', (array) get_post_meta( $shift->ID, 'assigned_persons', true ) );
		$status         = (string) get_post_meta( $shift->ID, 'status', true );

		if ( $start === '' || $end === '' ) {
			return null;
		}

		return [
			'id'                  => $shift->ID,
			'title'               => $this->sanitize_text( $shift->post_title ),
			'dienst_type_id'      => $dienst_type_id,
			'dienst_type_name'    => $dienst_type_id > 0 ? get_the_title( $dienst_type_id ) : '',
			'dienst_type_color'   => $dienst_type_id > 0 ? (string) get_post_meta( $dienst_type_id, 'color', true ) : '',
			'start_datetime'      => $start,
			'end_datetime'        => $end,
			'capacity'            => $capacity,
			'assigned_count'      => count( $assigned ),
			'assigned_person_ids' => $assigned,
			'spots_remaining'     => $capacity > 0 ? max( 0, $capacity - count( $assigned ) ) : -1,
			'status'              => $status ?: 'open',
			'cancellation'        => ShiftCancellationService::details( $shift->ID ),
		];
	}

	/**
	 * Query every shift the person is assigned to OR completed in.
	 */
	private function query_shifts_for_person( int $person_id, bool $include_member_details = true, int $posts_per_page = 200 ): array {
		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- per-person scope; profile overview intentionally requests every future shift.
				'posts_per_page'   => $posts_per_page,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
				'meta_query'       => [
					[
						'key'     => 'assigned_persons',
						'value'   => sprintf( ':%d;', $person_id ), // serialized-array fragment
						'compare' => 'LIKE',
					],
				],
				'orderby'          => 'meta_value',
				'meta_key'         => 'start_datetime',
				'order'            => 'DESC',
			]
		);

		$out = [];
		foreach ( $query->posts as $shift ) {
			$assigned = array_map( 'intval', (array) get_post_meta( $shift->ID, 'assigned_persons', true ) );
			if ( ! in_array( $person_id, $assigned, true ) ) {
				continue; // LIKE match was a false positive on serialized data.
			}
			$summary = $this->format_shift_summary( $shift );
			if ( $summary !== null ) {
				if ( $include_member_details ) {
					$fellow_volunteers = $this->format_fellow_volunteer_contacts( $summary['assigned_person_ids'], $person_id );

					$summary['fellow_volunteers']         = array_column( $fellow_volunteers, 'name' );
					$summary['fellow_volunteer_contacts'] = $fellow_volunteers;
					$summary['can_cancel']                = in_array( $summary['status'], [ 'open', 'vol' ], true ) && $this->can_member_cancel( $shift->ID, $person_id );
				}
				unset( $summary['assigned_person_ids'] );
				$summary['no_show'] = (bool) get_post_meta( $shift->ID, '_no_show_' . $person_id, true );
				$out[]              = $summary;
			}
		}

		return $out;
	}

	/**
	 * Detect a same-day overlap with another assignment for this person.
	 */
	private function find_overlapping_shift( int $person_id, int $candidate_shift_id ): ?array {
		$start = strtotime( (string) get_post_meta( $candidate_shift_id, 'start_datetime', true ) );
		$end   = strtotime( (string) get_post_meta( $candidate_shift_id, 'end_datetime', true ) );
		if ( $start === false || $end === false ) {
			return null;
		}

		$shifts = $this->query_shifts_for_person( $person_id );
		foreach ( $shifts as $shift ) {
			if ( (int) $shift['id'] === $candidate_shift_id ) {
				continue;
			}
			if ( in_array( $shift['status'], [ 'geannuleerd', 'voltooid' ], true ) ) {
				continue;
			}
			$shift_start = strtotime( $shift['start_datetime'] );
			$shift_end   = strtotime( $shift['end_datetime'] );
			if ( $shift_start === false || $shift_end === false ) {
				continue;
			}
			if ( $shift_start < $end && $shift_end > $start ) {
				return $shift;
			}
		}

		return null;
	}

	private function person_is_pool_member( int $person_id, int $commissie_id ): bool {
		$work_history = get_field( 'work_history', $person_id );
		if ( ! is_array( $work_history ) ) {
			return false;
		}

		$today = gmdate( 'Y-m-d', strtotime( '+1 day' ) );
		foreach ( $work_history as $position ) {
			$team_id = (int) ( $position['team'] ?? 0 );
			if ( $team_id !== $commissie_id ) {
				continue;
			}
			$is_current = ! empty( $position['is_current'] );
			$end_date   = (string) ( $position['end_date'] ?? '' );
			if ( $is_current || $end_date === '' || $end_date >= $today ) {
				return true;
			}
		}
		return false;
	}
}
