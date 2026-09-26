<?php
/**
 * Board-only authenticator enrollment and administrator-only configuration.
 */

namespace Rondo\REST;

use Rondo\Security\AppAccess as AppAccessService;
use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AppAccess extends Base {
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'prevent_caching' ], 10, 3 );
	}

	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/app-access/laposta',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'status' ],
					'permission_callback' => [ $this, 'can_access' ],
				],
				[
					'methods'             => 'PUT',
					'callback'            => [ $this, 'save' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => 'DELETE',
					'callback'            => [ $this, 'remove' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/app-access/laposta/reveal',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'reveal' ],
				'permission_callback' => [ $this, 'can_access' ],
			]
		);
	}

	public function can_access(): bool {
		return AppAccessService::can_access();
	}

	/** Apply to errors as well as successful responses. POST reveal also avoids old GET caches. */
	public function prevent_caching( $response, $server, $request ) {
		if ( str_starts_with( $request->get_route(), '/rondo/v1/app-access/' ) ) {
			$response = rest_ensure_response( $response );
			$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
			$response->header( 'Pragma', 'no-cache' );
		}
		return $response;
	}

	public function status() {
		return rest_ensure_response( AppAccessService::status() );
	}

	public function save( $request ) {
		// Accept secrets in the JSON body only, never URL query parameters.
		$body = $request->get_json_params();
		$uri  = AppAccessService::validate_uri( $body['uri'] ?? null );
		if ( is_wp_error( $uri ) ) {
			return $uri;
		}
		if ( ! CredentialEncryption::update_secret_option( AppAccessService::OPTION, $uri ) ) {
			return new \WP_Error( 'rondo_authenticator_save_failed', 'Opslaan is niet gelukt. Probeer het opnieuw.', [ 'status' => 500 ] );
		}
		return $this->status();
	}

	public function remove() {
		delete_option( AppAccessService::OPTION );
		if ( get_option( AppAccessService::OPTION, '' ) !== '' ) {
			return new \WP_Error( 'rondo_authenticator_remove_failed', 'Verwijderen is niet gelukt. Probeer het opnieuw.', [ 'status' => 500 ] );
		}
		return $this->status();
	}

	public function reveal() {
		$uri = AppAccessService::uri();
		if ( $uri === null || is_wp_error( AppAccessService::validate_uri( $uri ) ) ) {
			return new \WP_Error( 'rondo_authenticator_unavailable', 'De QR-code is niet beschikbaar. Vraag een beheerder om de Laposta-toegang in te stellen.', [ 'status' => 404 ] );
		}
		return rest_ensure_response( [ 'uri' => $uri ] );
	}
}
