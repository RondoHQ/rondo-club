<?php
/**
 * Access-event selection, scanning and anonymous statistics.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Access\AdmissionService;
use Rondo\Narrowcasting\SportlinkMatchday;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** REST controller for match-bound admission registration. */
class AccessEvents extends Base {

	private SportlinkMatchday $matchday;
	private AdmissionService $admissions;
	private MembershipPasses $membership_passes;

	public function __construct( ?SportlinkMatchday $matchday = null, ?AdmissionService $admissions = null, ?MembershipPasses $membership_passes = null ) {
		$this->matchday          = $matchday ?? new SportlinkMatchday( false );
		$this->admissions        = $admissions ?? new AdmissionService( false );
		$this->membership_passes = $membership_passes ?? new MembershipPasses( false );
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/** Register scanner-only routes. */
	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/access-events/matches',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_matches' ],
				'permission_callback' => [ $this, 'check_admin_or_toegangscontrole_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/access-events/select',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'select_event' ],
				'permission_callback' => [ $this, 'check_admin_or_toegangscontrole_permission' ],
				'args'                => [
					'source_id' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $value ): bool => is_string( $value ) && trim( $value ) !== '' && strlen( $value ) <= 200,
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/access-events/(?P<id>\d+)/scan',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'scan_event' ],
				'permission_callback' => [ $this, 'check_admin_or_toegangscontrole_permission' ],
				'args'                => [
					'id'    => [
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ) && (int) $value > 0,
					],
					'token' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => static fn( $value ): bool => is_string( $value ) && strlen( trim( $value ) ) > 20,
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/access-events/(?P<id>\d+)/stats',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_stats' ],
				'permission_callback' => [ $this, 'check_admin_or_toegangscontrole_permission' ],
				'args'                => [
					'id' => [
						'validate_callback' => static fn( $value ): bool => is_numeric( $value ) && (int) $value > 0,
					],
				],
			]
		);
	}

	/** Return normalized home matches and their active time windows. */
	public function get_matches() {
		return rest_ensure_response( $this->matchday->get_access_candidates() );
	}

	/** Snapshot the selected Sportlink match as an access event. */
	public function select_event( $request ) {
		$source_id = (string) $request->get_param( 'source_id' );
		$feed      = $this->matchday->get_access_candidates();
		$match     = null;

		foreach ( $feed['matches'] ?? [] as $candidate ) {
			if ( ! empty( $candidate['is_selectable'] ) && isset( $candidate['id'] ) && hash_equals( $source_id, (string) $candidate['id'] ) ) {
				$match = $candidate;
				break;
			}
		}

		if ( $match === null ) {
			return new \WP_Error( 'rondo_access_match_not_found', __( 'Deze thuiswedstrijd staat niet in het actuele Sportlink-programma.', 'rondo' ), [ 'status' => 404 ] );
		}

		$event = $this->admissions->select_match( $match );
		if ( is_wp_error( $event ) ) {
			return $event;
		}

		return rest_ensure_response(
			[
				'event' => $event,
				'stats' => $this->admissions->get_stats( (int) $event['id'] ),
			]
		);
	}

	/** Verify a pass and count an accepted attendee once for this event. */
	public function scan_event( $request ) {
		$event_id = (int) $request->get_param( 'id' );
		if ( get_post_type( $event_id ) !== 'rondo_access_event' ) {
			return new \WP_Error( 'rondo_access_event_not_found', __( 'Toegangsevenement niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		$verify_request = new \WP_REST_Request( 'POST', '/rondo/v1/membership-passes/verify' );
		$verify_request->set_param( 'token', (string) $request->get_param( 'token' ) );
		$verified = $this->membership_passes->verify_qr_token( $verify_request );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$data = $verified->get_data();
		if ( empty( $data['valid'] ) ) {
			$data['admission'] = [
				'counted'    => false,
				'duplicate'  => false,
				'pass_type'  => $data['pass_type'] ?? '',
				'scanned_at' => null,
			];
			$data['stats']     = $this->admissions->get_stats( $event_id );
			return rest_ensure_response( $data );
		}

		$person_id = (int) ( $data['person']['id'] ?? 0 );
		$pass_type = sanitize_key( (string) ( $data['pass_type'] ?? '' ) );
		$admission = $this->admissions->record_admission( $event_id, $person_id, $pass_type );
		if ( is_wp_error( $admission ) ) {
			return $admission;
		}

		$data['admission'] = $admission;
		$data['stats']     = $this->admissions->get_stats( $event_id );

		return rest_ensure_response( $data );
	}

	/** Return anonymous event totals. */
	public function get_stats( $request ) {
		$event_id = (int) $request->get_param( 'id' );
		if ( get_post_type( $event_id ) !== 'rondo_access_event' ) {
			return new \WP_Error( 'rondo_access_event_not_found', __( 'Toegangsevenement niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->admissions->get_stats( $event_id ) );
	}
}
