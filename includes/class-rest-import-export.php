<?php
/**
 * Import/Export REST API Endpoints
 *
 * Handles CardDAV URL endpoint for the current user.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ImportExport extends Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Get CardDAV URLs for the current user.
		register_rest_route(
			'rondo/v1',
			'/carddav/urls',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_carddav_urls' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);
	}

	/**
	 * Get CardDAV URLs for the current user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_carddav_urls( $request ) {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			return new \WP_Error(
				'not_logged_in',
				__( 'You must be logged in.', 'rondo' ),
				[ 'status' => 401 ]
			);
		}

		$base_url = home_url( '/carddav/' );

		return rest_ensure_response(
			[
				'server'      => $base_url,
				'principal'   => $base_url . 'principals/' . $user->user_login . '/',
				'addressbook' => $base_url . 'addressbooks/' . $user->user_login . '/contacts/',
				'username'    => $user->user_login,
			]
		);
	}
}
