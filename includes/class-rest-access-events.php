<?php
/**
 * Access-event selection, scanning and anonymous statistics.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Access\AdmissionService;
use Rondo\Narrowcasting\SportlinkMatchday;
use Rondo\Passes\GuestPassService;
use Rondo\Passes\MembershipPassQr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** REST controller for match-bound admission registration. */
class AccessEvents extends Base {

	private SportlinkMatchday $matchday;
	private AdmissionService $admissions;
	private MembershipPasses $membership_passes;
	private GuestPassService $guest_passes;

	public function __construct( ?SportlinkMatchday $matchday = null, ?AdmissionService $admissions = null, ?MembershipPasses $membership_passes = null, ?GuestPassService $guest_passes = null ) {
		$this->matchday          = $matchday ?? new SportlinkMatchday( false );
		$this->admissions        = $admissions ?? new AdmissionService( false );
		$this->membership_passes = $membership_passes ?? new MembershipPasses( false );
		$this->guest_passes      = $guest_passes ?? new GuestPassService();
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

		$token        = (string) $request->get_param( 'token' );
		$guest_result = ( new MembershipPassQr() )->verify_guest_token( $token );
		if ( ! is_wp_error( $guest_result ) ) {
			return $this->scan_guest_pass( $event_id, $guest_result['payload'] );
		}
		if ( $guest_result->get_error_code() !== 'membership_pass_invalid_audience' ) {
			return $guest_result;
		}

		$verify_request = new \WP_REST_Request( 'POST', '/rondo/v1/membership-passes/verify' );
		$verify_request->set_param( 'token', $token );
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

	/** Validate and count one guest slot for the configured team's selected match. */
	private function scan_guest_pass( int $event_id, array $payload ) {
		$guest_pass_id = isset( $payload['gpid'] ) ? (int) $payload['gpid'] : 0;
		$token_version = isset( $payload['pass_version'] ) ? max( 1, (int) $payload['pass_version'] ) : 1;
		$guest         = $this->guest_passes->validate_for_event( $guest_pass_id, $token_version, $event_id );
		if ( is_wp_error( $guest ) ) {
			$current = $this->guest_passes->get_pass_data( $guest_pass_id );
			return rest_ensure_response(
				[
					'valid'     => false,
					'reason'    => $this->guest_error_reason( $guest->get_error_code() ),
					'pass_type' => 'guest',
					'guest'     => $current === null ? null : $this->format_guest( $current ),
					'admission' => [
						'counted'    => false,
						'duplicate'  => false,
						'pass_type'  => 'guest',
						'scanned_at' => null,
					],
					'stats'     => $this->admissions->get_stats( $event_id ),
				]
			);
		}

		$admission = $this->admissions->record_guest_admission(
			$event_id,
			$guest['id'],
			$guest['host_person_id'],
			$guest['slot'],
			$guest['guest_name']
		);
		if ( is_wp_error( $admission ) ) {
			return $admission;
		}

		return rest_ensure_response(
			[
				'valid'     => true,
				'reason'    => null,
				'pass_type' => 'guest',
				'guest'     => $this->format_guest( $guest ),
				'admission' => $admission,
				'stats'     => $this->admissions->get_stats( $event_id ),
			]
		);
	}

	private function format_guest( array $guest ): array {
		return [
			'name'           => $guest['guest_name'],
			'host_name'      => $guest['host_name'],
			'host_person_id' => $guest['host_person_id'],
			'slot'           => $guest['slot'],
		];
	}

	private function guest_error_reason( string $error_code ): string {
		return match ( $error_code ) {
			'rondo_guest_pass_revoked'         => 'revoked',
			'rondo_guest_pass_unclaimed'       => 'unclaimed',
			'rondo_guest_pass_host_ineligible' => 'host_ineligible',
			'rondo_guest_pass_wrong_match'     => 'wrong_match',
			default                            => 'invalid',
		};
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
