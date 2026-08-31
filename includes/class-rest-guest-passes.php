<?php
/**
 * Authenticated guest-pass slot management.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Passes\GuestPassService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** REST controller for a player's own two guest slots. */
class GuestPasses extends Base {

	private GuestPassService $service;

	public function __construct( ?GuestPassService $service = null, bool $register_routes = true ) {
		$this->service = $service ?? new GuestPassService();
		if ( $register_routes ) {
			add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		}
	}

	/** Register player-owned guest-pass routes. */
	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/guest-passes/me',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_mine' ],
				'permission_callback' => [ $this, 'is_logged_in' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/guest-passes/slots/(?P<slot>[12])',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_slot' ],
				'permission_callback' => [ $this, 'is_logged_in' ],
				'args'                => $this->slot_args(),
			]
		);

		register_rest_route(
			'rondo/v1',
			'/guest-passes/slots/(?P<slot>[12])/replace',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'replace_slot' ],
				'permission_callback' => [ $this, 'is_logged_in' ],
				'args'                => $this->slot_args(),
			]
		);
	}

	/** Return eligibility and both stable slots. */
	public function get_mine() {
		$host_person_id = $this->service->get_current_host_person_id();
		$eligible       = $this->service->is_eligible_player( $host_person_id );
		$team_id        = $this->service->get_eligible_team_id();
		return rest_ensure_response(
			[
				'eligible'       => $eligible,
				'team_id'        => $team_id,
				'team'           => $this->service->get_eligible_team_name(),
				'limit'          => GuestPassService::SLOT_LIMIT,
				'host_person_id' => $eligible ? $host_person_id : null,
				'slots'          => $eligible ? $this->service->get_slots( $host_person_id ) : [],
			]
		);
	}

	/** Create one slot lazily. */
	public function create_slot( $request ) {
		$result = $this->service->ensure_slot(
			$this->service->get_current_host_person_id(),
			(int) $request->get_param( 'slot' )
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Revoke and clear one slot while preserving the quota identity. */
	public function replace_slot( $request ) {
		$result = $this->service->replace_slot(
			$this->service->get_current_host_person_id(),
			(int) $request->get_param( 'slot' )
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Authenticated account boundary. */
	public function is_logged_in(): bool {
		return is_user_logged_in();
	}

	private function slot_args(): array {
		return [
			'slot' => [
				'validate_callback' => static fn( $value ): bool => is_numeric( $value ) && (int) $value >= 1 && (int) $value <= GuestPassService::SLOT_LIMIT,
			],
		];
	}
}
