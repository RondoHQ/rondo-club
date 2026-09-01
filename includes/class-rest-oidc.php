<?php
/**
 * Administrator API for first-party OpenID Connect clients and signing keys.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Identity\OidcAuthorizationService;
use Rondo\Identity\OidcClientRegistry;
use Rondo\Identity\OidcKeyStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Expose narrow OIDC administration without returning stored secret material. */
final class Oidc extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/** Register administrator-only client and key management routes. */
	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/oidc/clients',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_clients' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_client' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/oidc/clients/(?P<client_id>[A-Za-z0-9_-]{32})',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_client' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/oidc/clients/(?P<client_id>[A-Za-z0-9_-]{32})/rotate-secret',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rotate_client_secret' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/oidc/signing-keys/rotate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rotate_signing_key' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
	}

	public function list_clients(): \WP_REST_Response {
		return rest_ensure_response(
			[
				'clients'     => OidcClientRegistry::all(),
				'signing_key' => OidcKeyStore::status(),
				'metadata'    => OidcAuthorizationService::metadata(),
			]
		);
	}

	public function create_client( \WP_REST_Request $request ) {
		$result = OidcClientRegistry::create( $this->body( $request ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $result, 201 );
	}

	public function update_client( \WP_REST_Request $request ) {
		return OidcClientRegistry::update( (string) $request['client_id'], $this->body( $request ) );
	}

	public function rotate_client_secret( \WP_REST_Request $request ) {
		return OidcClientRegistry::rotate_secret( (string) $request['client_id'] );
	}

	public function rotate_signing_key(): array|\WP_Error {
		return OidcKeyStore::rotate();
	}

	private function body( \WP_REST_Request $request ): array {
		$body = $request->get_json_params();

		return is_array( $body ) ? $body : $request->get_body_params();
	}
}
