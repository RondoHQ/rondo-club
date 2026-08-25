<?php
/**
 * Membership pass REST endpoints.
 */

namespace Rondo\REST;

use Rondo\Core\AccessControl;
use Rondo\Passes\MembershipPassQr;
use Rondo\Core\SponsorStatus;
use Rondo\Passes\MembershipPassService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembershipPasses extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register membership pass routes.
	 */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/membership-passes/people/(?P<person_id>\d+)/qr-token',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'issue_person_qr_token' ],
				'permission_callback' => [ $this, 'check_person_access' ],
				'args'                => [
					'person_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
					],
					'season'    => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'ttl_days'  => [
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/membership-passes/verify',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'verify_qr_token' ],
				'permission_callback' => [ $this, 'check_admin_or_toegangscontrole_permission' ],
				'args'                => [
					'token' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return is_string( $param ) && strlen( trim( $param ) ) > 20;
						},
					],
				],
			]
		);
	}

	/**
	 * Issue a signed QR token for one person.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function issue_person_qr_token( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$season    = (string) $request->get_param( 'season' );
		$ttl_days  = (int) $request->get_param( 'ttl_days' );

		$service = new MembershipPassQr();
		$result  = $service->issue_for_person(
			$person_id,
			[
				'season'   => $season,
				'ttl_days' => $ttl_days,
			]
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$payload = $result['payload'];

		return rest_ensure_response(
			[
				'token'      => $result['token'],
				'expires_at' => isset( $payload['exp'] ) ? gmdate( DATE_ATOM, (int) $payload['exp'] ) : null,
				'payload'    => $payload,
				'person'     => $result['person'],
			]
		);
	}

	/**
	 * Verify scanned QR token and resolve person data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function verify_qr_token( $request ) {
		$token   = (string) $request->get_param( 'token' );
		$service = new MembershipPassQr();
		$result  = $service->verify_token( $token );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$payload   = $result['payload'];
		$person_id = isset( $payload['pid'] ) ? (int) $payload['pid'] : 0;
		if ( $person_id <= 0 ) {
			return new \WP_Error( 'membership_pass_missing_person', 'Token bevat geen geldig persoon-ID.', [ 'status' => 400 ] );
		}

		$access_control = new AccessControl();
		if ( ! $access_control->user_can_access_post( $person_id ) ) {
			return new \WP_Error( 'membership_pass_forbidden', 'Geen toegang tot dit lid.', [ 'status' => 403 ] );
		}

		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'membership_pass_person_not_found', 'Persoon niet gevonden.', [ 'status' => 404 ] );
		}

		$season          = isset( $payload['season'] ) ? (string) $payload['season'] : '';
		$status          = $service->get_person_status( $person_id, $season );
		$token_version   = isset( $payload['pass_version'] ) ? max( 1, (int) $payload['pass_version'] ) : 1;
		$current_version = MembershipPassService::get_pass_version( $person_id );
		$member_tier     = MembershipPassService::get_person_member_tier( $person_id );
		$valid           = $status['status'] === 'active' && $member_tier !== '' && $token_version === $current_version;
		$reason          = null;
		if ( $token_version !== $current_version ) {
			$reason = 'revoked';
		} elseif ( $status['status'] === 'former' ) {
			$reason = 'former';
		} elseif ( $status['status'] === 'expired' ) {
			$reason = 'expired';
		} elseif ( $member_tier === '' ) {
			$reason = 'no_pass_right';
		}

		return rest_ensure_response(
			[
				'valid'      => $valid,
				'reason'     => $reason,
				'token'      => [
					'issued_at'  => isset( $payload['iat'] ) ? gmdate( DATE_ATOM, (int) $payload['iat'] ) : null,
					'expires_at' => isset( $payload['exp'] ) ? gmdate( DATE_ATOM, (int) $payload['exp'] ) : null,
					'season'     => $payload['season'] ?? null,
				],
				'person'     => $this->format_verified_person_summary( $person ),
				'membership' => [
					'status'  => $status['status'],
					'lid_tot' => $status['lid_tot'],
				],
			]
		);
	}

	/**
	 * Build scanner-specific person summary for verified passes.
	 *
	 * @param \WP_Post $post Person post object.
	 * @return array
	 */
	private function format_verified_person_summary( $post ): array {
		$person = $this->format_person_summary( $post );

		$knvb_id      = (string) ( \Rondo\Fields\Fields::get_for_post( $post->ID, 'knvb_id' ) ?: get_post_meta( $post->ID, 'knvb-id', true ) ?: '' );
		$person_type  = (string) ( \Rondo\Fields\Fields::get_for_post( $post->ID, 'person_type' ) ?: get_post_meta( $post->ID, 'person_type', true ) ?: '' );
		$company_name = MembershipPassService::get_person_member_tier( $post->ID ) === 'sponsor'
			? MembershipPassService::get_sponsor_company_name( $post->ID )
			: (string) ( \Rondo\Fields\Fields::get_for_post( $post->ID, 'company_name' ) ?: get_post_meta( $post->ID, 'company_name', true ) ?: '' );

		$person['knvb_id']         = $knvb_id;
		$person['knvb-id']         = $knvb_id;
		$person['person_type']     = $this->sanitize_text( $person_type );
		$person['is_sponsor']      = SponsorStatus::is_sponsor( (int) $post->ID );
		$person['company_name']    = $this->sanitize_text( $company_name );
		$person['photo_thumbnail'] = $person['thumbnail'] ?? '';

		return $person;
	}
}
