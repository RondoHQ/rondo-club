<?php
/**
 * Cookie-authenticated account switching endpoints.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Users\AccountSwitcher as Switcher;

/** WordPress REST cookie authentication enforces a session-bound nonce on all routes. */
class AccountSwitcher extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/user/account-switcher',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
			);
		register_rest_route(
			'rondo/v1',
			'/user/account-switcher/verify',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'verify' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'email' => [
						'type'     => 'string',
						'format'   => 'email',
						'required' => true,
					],
				],
			]
			);
		foreach ( [ 'switch', 'activate' ] as $action ) {
			register_rest_route(
				'rondo/v1',
				'/user/account-switcher/' . $action,
				[
					'methods'             => 'POST',
					'callback'            => [ $this, $action === 'switch' ? 'switch_account' : 'activate' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
					'args'                => [
						'person_id' => [
							'type'     => 'integer',
							'minimum'  => 1,
							'required' => true,
						],
					],
				]
				);
		}
	}

	public function get_status() {
		$response = rest_ensure_response( Switcher::status() );
		$response->header( 'Cache-Control', 'no-store, private' );
		return $response;
	}

	public function verify( \WP_REST_Request $request ) {
		return Switcher::request_verification( $request['email'] );
	}

	public function switch_account( \WP_REST_Request $request ) {
		return Switcher::switch_account( (int) $request['person_id'] );
	}

	public function activate( \WP_REST_Request $request ) {
		return Switcher::activation_url( (int) $request['person_id'] );
	}
}
