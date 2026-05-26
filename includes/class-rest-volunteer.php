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
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerExemptionResolver;
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
