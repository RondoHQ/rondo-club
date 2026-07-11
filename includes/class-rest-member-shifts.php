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
use Rondo\Volunteer\IvaStatus;
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
	const AVAILABLE_WINDOW_DAYS = 84;

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
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
			'/shifts/available',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_available_shifts' ],
				'permission_callback' => 'is_user_logged_in',
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
		$person_id = $this->current_person_id();
		if ( $person_id <= 0 ) {
			return new \WP_Error( 'no_person', 'Geen gekoppelde persoon gevonden voor dit account.', [ 'status' => 404 ] );
		}

		$season      = (string) ( $request->get_param( 'season' ) ?: SeasonKey::current() );
		$eligibility = new VolunteerEligibilityService();
		$calculator  = new VolunteerObligationCalculator();

		$units       = $eligibility->get_eligible_units_for_person( $person_id, $season );
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
			]
		);
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

		$out = [];
		foreach ( $query->posts as $shift ) {
			$summary = $this->format_shift_summary( $shift );
			if ( $summary === null ) {
				continue;
			}

			$dienst_type_id = (int) $summary['dienst_type_id'];
			$requires_vog   = $dienst_type_id > 0 && (bool) get_post_meta( $dienst_type_id, 'vog_required', true );
			$requires_iva   = $dienst_type_id > 0 && (bool) get_post_meta( $dienst_type_id, 'iva_required', true );

			// Per-dienst override: een specifieke shift mag de IVA-eis uitschakelen
			// (bv. zaterdag voor 15:00 — geen alcohol → geen IVA nodig).
			if ( $requires_iva && (bool) get_post_meta( $shift->ID, 'iva_waived', true ) ) {
				$requires_iva = false;
			}

			$shift_blocks = [];
			if ( $requires_vog && in_array( 'vog', $blocks, true ) ) {
				$shift_blocks[] = 'vog';
			}
			if ( $requires_iva && in_array( 'iva', $blocks, true ) ) {
				$shift_blocks[] = 'iva';
			}

			// Hard-block hidden shifts: VOG/IVA-vereiste shifts pas zichtbaar als
			// de persoon eraan voldoet (bestuursbesluit 2026-05-26).
			if ( ! empty( $shift_blocks ) ) {
				continue;
			}

			// Pool-only shifts: only show to pool members.
			$required_pool = (int) get_post_meta( $dienst_type_id, 'required_pool', true );
			if ( $required_pool > 0 && ! $this->person_is_pool_member( $person_id, $required_pool ) ) {
				continue;
			}

			$summary['is_signed_up']      = in_array( $person_id, $summary['assigned_person_ids'], true );
			$summary['can_signup']        = ! $summary['is_signed_up'] && $summary['spots_remaining'] !== 0;
			$summary['fellow_volunteers'] = $this->format_fellow_volunteers( $summary['assigned_person_ids'], $person_id );
			unset( $summary['assigned_person_ids'] );

			$out[] = $summary;
		}

		return rest_ensure_response(
			[
				'person_id'     => $person_id,
				'eligible'      => true,
				'iva_status'    => IvaStatus::status( $person_id ),
				'block_reasons' => $blocks,
				'shifts'        => $out,
			]
		);
	}

	public function signup( \WP_REST_Request $request ) {
		$person_id = $this->current_person_id();
		$shift_id  = (int) $request->get_param( 'id' );
		$force     = filter_var( $request->get_param( 'force_overlap' ), FILTER_VALIDATE_BOOLEAN );

		if ( $person_id <= 0 ) {
			return new \WP_Error( 'no_person', 'Geen gekoppelde persoon.', [ 'status' => 404 ] );
		}

		$shift = get_post( $shift_id );
		if ( ! $shift || $shift->post_type !== 'dienst_shift' ) {
			return new \WP_Error( 'invalid_shift', 'Shift bestaat niet.', [ 'status' => 404 ] );
		}

		$status = (string) get_post_meta( $shift_id, 'status', true );
		if ( $status !== 'open' && $status !== 'vol' ) {
			return new \WP_Error( 'shift_closed', 'Deze shift staat niet meer open.', [ 'status' => 409 ] );
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
			return new \WP_Error( 'shift_full', 'Deze shift is vol.', [ 'status' => 409 ] );
		}

		$dienst_type_id = (int) get_post_meta( $shift_id, 'dienst_type_id', true );
		$blocks         = $this->signup_blocks( $person_id );
		if ( $dienst_type_id > 0 ) {
			if ( get_post_meta( $dienst_type_id, 'vog_required', true ) && in_array( 'vog', $blocks, true ) ) {
				return new \WP_Error( 'vog_required', 'Voor deze dienst is een geldige VOG vereist.', [ 'status' => 403 ] );
			}
			$iva_waived = (bool) get_post_meta( $shift_id, 'iva_waived', true );
			if ( ! $iva_waived && get_post_meta( $dienst_type_id, 'iva_required', true ) && in_array( 'iva', $blocks, true ) ) {
				return new \WP_Error( 'iva_required', 'Voor deze dienst is een geldig IVA-certificaat vereist.', [ 'status' => 403 ] );
			}
		}

		// Overlap check (warning, not block, unless force=false explicitly opted out).
		if ( ! $force ) {
			$overlap = $this->find_overlapping_shift( $person_id, $shift_id );
			if ( $overlap !== null ) {
				return new \WP_Error(
					'overlap_warning',
					sprintf( 'Deze shift overlapt met een bestaande aanmelding (%s).', $overlap['title'] ),
					[
						'status'        => 409,
						'overlap_shift' => $overlap,
						'can_force'     => true,
					]
				);
			}
		}

		$assigned[] = $person_id;
		$assigned   = array_values( array_unique( $assigned ) );
		update_post_meta( $shift_id, 'assigned_persons', $assigned );

		// Auto-flip to "vol" if capacity reached.
		if ( $capacity > 0 && count( $assigned ) >= $capacity ) {
			update_post_meta( $shift_id, 'status', 'vol' );
		}

		return rest_ensure_response(
			[
				'shift_id'  => $shift_id,
				'signed_up' => true,
			]
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
			return new \WP_Error( 'invalid_shift', 'Shift bestaat niet.', [ 'status' => 404 ] );
		}

		$status = (string) get_post_meta( $shift_id, 'status', true );
		if ( $status === 'voltooid' ) {
			return new \WP_Error( 'shift_completed', 'Deze shift is al voltooid en kan niet meer worden afgemeld.', [ 'status' => 409 ] );
		}

		$assigned = array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) );
		$filtered = array_values( array_diff( $assigned, [ $person_id ] ) );

		update_post_meta( $shift_id, 'assigned_persons', $filtered );
		if ( $status === 'vol' ) {
			update_post_meta( $shift_id, 'status', 'open' );
		}

		return rest_ensure_response(
			[
				'shift_id'  => $shift_id,
				'cancelled' => true,
			]
		);
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
	 * Return display names for the other volunteers on a shift.
	 *
	 * Person IDs stay internal: members only need names to know who they will
	 * work with, not identifiers that can be used to enumerate person records.
	 *
	 * @param int[] $assigned_person_ids Assigned person post IDs.
	 * @return string[]
	 */
	private function format_fellow_volunteers( array $assigned_person_ids, int $current_person_id ): array {
		$names = [];
		foreach ( $assigned_person_ids as $assigned_person_id ) {
			$assigned_person_id = (int) $assigned_person_id;
			if ( $assigned_person_id <= 0 || $assigned_person_id === $current_person_id ) {
				continue;
			}

			$person = get_post( $assigned_person_id );
			if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
				continue;
			}

			$name = $this->sanitize_text( $person->post_title );
			if ( $name !== '' ) {
				$names[] = $name;
			}
		}

		return array_values( array_unique( $names ) );
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
		];
	}

	/**
	 * Query every shift the person is assigned to OR completed in.
	 */
	private function query_shifts_for_person( int $person_id ): array {
		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- per-person scope, intentional cap.
				'posts_per_page'   => 200,
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
				$summary['fellow_volunteers'] = $this->format_fellow_volunteers( $summary['assigned_person_ids'], $person_id );
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
		$start = (string) get_post_meta( $candidate_shift_id, 'start_datetime', true );
		$end   = (string) get_post_meta( $candidate_shift_id, 'end_datetime', true );
		if ( $start === '' || $end === '' ) {
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
			if ( $shift['start_datetime'] < $end && $shift['end_datetime'] > $start ) {
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
