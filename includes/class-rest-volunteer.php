<?php
/**
 * Volunteer REST API Endpoints
 *
 * Owns every /rondo/v1/volunteer-* endpoint. Today it covers eligibility
 * derivation and exemption resolution; later phases will hang shift signup,
 * IVA approval, and the obligation counter off this same class.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Fees\SeasonKey;
use Rondo\Volunteer\IvaStatus;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerExemptionResolver;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Rondo\Volunteer\VolunteerSeeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Volunteer extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/volunteer-eligibility',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_eligibility' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'season'       => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'with_persons' => [
						'required' => false,
						'default'  => false,
					],
					'person_id'    => [
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/managed-commissies',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_managed_commissies' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/volunteer-exemption/(?P<person_id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_exemption' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'season'    => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// IVA approval — restricted to bestuurslid kantine (rondo_iva_approve cap).
		register_rest_route(
			'rondo/v1',
			'/iva/(?P<person_id>\d+)/approve',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'approve_iva' ],
				'permission_callback' => [ $this, 'check_iva_approve_permission' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'approve'   => [
						'required' => false,
						'default'  => true,
					],
				],
			]
		);

		// Obligations — counter view per unit, plus aggregate dashboard stats.
		register_rest_route(
			'rondo/v1',
			'/volunteer-obligations',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_obligations' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'season' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// No-show endpoint (admin / coordinator only).
		register_rest_route(
			'rondo/v1',
			'/shifts/(?P<id>\d+)/no-show',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'mark_no_show' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'id'        => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'revert'    => [
						'required' => false,
						'default'  => false,
					],
				],
			]
		);

		// IVA admin overview — every person who has uploaded a cert, has a datum-iva,
		// or has been approved. Keeps the result set small enough that we don't need
		// pagination (typical clubs have ~30–100 IVA-relevant people, not the full
		// member roster). Available to anyone with the vrijwilligers cap.
		register_rest_route(
			'rondo/v1',
			'/iva/people',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_iva_people' ],
				'permission_callback' => [ $this, 'check_iva_view_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/iva/(?P<person_id>\d+)/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_iva_status' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	public function check_iva_approve_permission() {
		return current_user_can( 'rondo_iva_approve' ) || current_user_can( 'manage_options' );
	}

	public function check_iva_view_permission() {
		return current_user_can( 'vrijwilligers' )
			|| current_user_can( 'rondo_iva_approve' )
			|| current_user_can( 'manage_options' );
	}

	/**
	 * GET /rondo/v1/iva/people
	 *
	 * Returns every person with any IVA-relevant data: a datum-iva, an uploaded
	 * certificaat, or an iva-approved flag. The set is naturally small (kantine
	 * bar-vrijwilligers) so we return it all in one shot, no pagination.
	 */
	public function get_iva_people( \WP_REST_Request $request ) {
		$ids = $this->collect_iva_person_ids();

		if ( empty( $ids ) ) {
			return rest_ensure_response( [ 'people' => [] ] );
		}

		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'post__in'         => $ids,
				'posts_per_page'   => count( $ids ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		$people = [];
		foreach ( $query->posts as $post ) {
			$cert_field = get_field( 'iva-certificaat', $post->ID );
			$cert_url   = '';
			if ( is_array( $cert_field ) ) {
				$cert_url = $cert_field['url'] ?? '';
			} elseif ( is_string( $cert_field ) ) {
				$cert_url = $cert_field;
			}

			$people[] = [
				'id'                => $post->ID,
				'name'              => $this->sanitize_text( $post->post_title ),
				'thumbnail'         => $this->sanitize_url( get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ),
				'datum_iva'         => (string) get_field( 'datum-iva', $post->ID ),
				'iva_certificaat'   => $cert_url ? $this->sanitize_url( $cert_url ) : '',
				'iva_approved'      => (bool) get_post_meta( $post->ID, 'iva-approved', true ),
				'status'            => IvaStatus::status( $post->ID ),
				'expires_at'        => IvaStatus::expires_at( $post->ID ),
			];
		}

		return rest_ensure_response( [ 'people' => $people ] );
	}

	/**
	 * Find the post IDs of every person with any IVA-relevant meta set.
	 *
	 * Direct WPDB query — meta-OR via WP_Query is awkward and we want the
	 * smallest possible read for an admin page that's loaded rarely.
	 */
	private function collect_iva_person_ids(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			"SELECT DISTINCT pm.post_id
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = 'person'
			   AND p.post_status = 'publish'
			   AND pm.meta_key IN ('datum-iva', 'iva-certificaat', 'iva-approved')
			   AND pm.meta_value <> ''
			   AND pm.meta_value <> '0'"
		);

		return array_values( array_unique( array_map( 'intval', (array) $ids ) ) );
	}

	public function approve_iva( \WP_REST_Request $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$approve   = filter_var( $request->get_param( 'approve' ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( $approve === null ) {
			$approve = true;
		}

		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Invalid person ID.', [ 'status' => 404 ] );
		}

		update_post_meta( $person_id, 'iva-approved', $approve ? 1 : 0 );
		update_field( 'iva-approved', $approve ? 1 : 0, $person_id );

		return rest_ensure_response(
			[
				'person_id' => $person_id,
				'approved'  => $approve,
				'status'    => IvaStatus::status( $person_id ),
				'expires_at' => IvaStatus::expires_at( $person_id ),
			]
		);
	}

	/**
	 * GET /rondo/v1/volunteer-obligations
	 *
	 * Returns every eligible unit for the season, augmented with
	 * `completed_count`, `pending_count`, `no_show_count`, and a `status` bucket.
	 * Used by the Vrijwilligers dashboard and (later) the member-facing surface.
	 */
	public function get_obligations( \WP_REST_Request $request ) {
		$season = $request->get_param( 'season' ) ?: SeasonKey::current();

		$eligibility = new VolunteerEligibilityService();
		$calculator  = new VolunteerObligationCalculator();

		$units      = $eligibility->get_eligible_units( $season );
		$decorated  = $calculator->decorate_units( $units, $season );
		$aggregate  = $calculator->aggregate( $decorated );

		return rest_ensure_response(
			[
				'season'    => $season,
				'units'     => $decorated,
				'aggregate' => $aggregate,
			]
		);
	}

	/**
	 * POST /rondo/v1/shifts/{id}/no-show
	 *
	 * Marks a person as a no-show on a given shift (or reverts the mark when
	 * `revert=true`). Triggers the `rondo_volunteer_no_show_marked` action so the
	 * boete pipeline can fire — see ShiftScheduler.
	 */
	public function mark_no_show( \WP_REST_Request $request ) {
		$shift_id  = (int) $request->get_param( 'id' );
		$person_id = (int) $request->get_param( 'person_id' );
		$revert    = filter_var( $request->get_param( 'revert' ), FILTER_VALIDATE_BOOLEAN );

		if ( $revert ) {
			$ok = VolunteerObligationCalculator::unmark_no_show( $shift_id, $person_id );
			return rest_ensure_response(
				[
					'shift_id'  => $shift_id,
					'person_id' => $person_id,
					'reverted'  => (bool) $ok,
				]
			);
		}

		$result = VolunteerObligationCalculator::mark_no_show( $shift_id, $person_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a person is marked no-show on a shift.
		 * Consumed by ShiftScheduler → VolunteerFineGenerator.
		 */
		do_action( 'rondo_volunteer_no_show_marked', $shift_id, $person_id, get_current_user_id() );

		return rest_ensure_response(
			[
				'shift_id'  => $shift_id,
				'person_id' => $person_id,
				'marked'    => true,
			]
		);
	}

	public function get_iva_status( \WP_REST_Request $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Invalid person ID.', [ 'status' => 404 ] );
		}

		return rest_ensure_response(
			[
				'person_id'             => $person_id,
				'status'                => IvaStatus::status( $person_id ),
				'expires_at'            => IvaStatus::expires_at( $person_id ),
				'needs_renewal_reminder' => IvaStatus::needs_renewal_reminder( $person_id ),
				'validity_years'        => IvaStatus::VALIDITY_YEARS,
			]
		);
	}

	/**
	 * GET /rondo/v1/volunteer-eligibility
	 *
	 * Returns the derived eligible units for the requested season.
	 * If `person_id` is given, returns just that person's unit (or 404 if none).
	 *
	 * Response shape:
	 *   {
	 *     season: "2026-2027",
	 *     units: [ { unit_id, kind, person_ids, trigger_person_ids, required_count, ... }, ... ],
	 *     total_units: 312,
	 *   }
	 */
	public function get_eligibility( \WP_REST_Request $request ) {
		$season       = $request->get_param( 'season' ) ?: SeasonKey::current();
		$with_persons = filter_var( $request->get_param( 'with_persons' ), FILTER_VALIDATE_BOOLEAN );
		$person_id    = (int) $request->get_param( 'person_id' );

		$service = new VolunteerEligibilityService();

		if ( $person_id > 0 ) {
			$unit = $service->get_eligible_unit_for_person( $person_id, $season );
			if ( $unit === null ) {
				return rest_ensure_response(
					[
						'season' => $season,
						'unit'   => null,
					]
				);
			}
			return rest_ensure_response(
				[
					'season' => $season,
					'unit'   => $with_persons ? $this->expand_unit( $unit, $season ) : $unit,
				]
			);
		}

		$units = $service->get_eligible_units( $season );

		if ( $with_persons ) {
			$units = array_map(
				fn( $unit ) => $this->expand_unit( $unit, $season ),
				$units
			);
		}

		return rest_ensure_response(
			[
				'season'      => $season,
				'units'       => $units,
				'total_units' => count( $units ),
			]
		);
	}

	/**
	 * GET /rondo/v1/volunteer-exemption/{person_id}
	 *
	 * Returns the exemption reason for a single person, or null.
	 */
	public function get_exemption( \WP_REST_Request $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$season    = $request->get_param( 'season' ) ?: SeasonKey::current();

		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Invalid person ID.', [ 'status' => 404 ] );
		}

		$reason = VolunteerExemptionResolver::resolve( $person_id, $season );

		return rest_ensure_response(
			[
				'person_id'    => $person_id,
				'season'       => $season,
				'is_exempt'    => $reason !== null,
				'reason'       => $reason,
				'reason_label' => $reason ? VolunteerExemptionResolver::reason_label( $reason ) : null,
			]
		);
	}

	/**
	 * GET /rondo/v1/managed-commissies
	 *
	 * Public list of commissie post IDs that are managed inside Rondo (pool
	 * commissies seeded by VolunteerSeeder + any others tagged with the seed key).
	 * Consumed by rondo-sync to skip these during the untracked-commissie cleanup,
	 * so a Sportlink sync never deletes a Rondo-managed pool.
	 *
	 * No auth: read-only and the IDs themselves are not sensitive.
	 */
	public function get_managed_commissies( \WP_REST_Request $request ) {
		$ids   = VolunteerSeeder::get_managed_commissie_ids();
		$pools = VolunteerSeeder::get_pool_commissies();

		$detail = [];
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_type !== 'commissie' ) {
				continue;
			}
			$slug = array_search( $id, $pools, true ) ?: '';
			$detail[] = [
				'id'        => (int) $id,
				'slug'      => is_string( $slug ) ? $slug : '',
				'title'     => $this->sanitize_text( $post->post_title ),
			];
		}

		return rest_ensure_response(
			[
				'ids'         => $ids,
				'pools'       => $pools,
				'commissies'  => $detail,
			]
		);
	}

	/**
	 * Expand a unit with person summaries (name/thumbnail/exemption reason).
	 * Used when the client requests `with_persons=true`.
	 */
	private function expand_unit( array $unit, string $season ): array {
		$persons = [];
		foreach ( $unit['person_ids'] as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}
			$reason = VolunteerExemptionResolver::resolve( (int) $pid, $season );
			$persons[] = [
				'id'           => (int) $pid,
				'name'         => $this->sanitize_text( $post->post_title ),
				'thumbnail'    => $this->sanitize_url( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ),
				'is_trigger'   => in_array( (int) $pid, $unit['trigger_person_ids'], true ),
				'is_exempt'    => $reason !== null,
				'reason'       => $reason,
				'reason_label' => $reason ? VolunteerExemptionResolver::reason_label( $reason ) : null,
			];
		}
		$unit['persons'] = $persons;
		return $unit;
	}
}
