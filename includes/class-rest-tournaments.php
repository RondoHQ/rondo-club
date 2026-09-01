<?php
/**
 * REST API for tournament planning and assigned team registrations.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Tournaments\TournamentAccess;
use Rondo\Tournaments\TournamentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tournaments extends Base {

	private TournamentService $service;

	public function __construct() {
		parent::__construct();
		$this->service = new TournamentService();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/tournaments',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_tournaments' ],
					'permission_callback' => [ $this, 'check_manager_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_tournament' ],
					'permission_callback' => [ $this, 'check_manager_permission' ],
				],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/assignment-options',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_assignment_options' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_tournament' ],
					'permission_callback' => [ $this, 'check_manager_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_tournament' ],
					'permission_callback' => [ $this, 'check_manager_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_tournament' ],
					'permission_callback' => [ $this, 'check_manager_permission' ],
				],
				'args' => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/(?P<id>\d+)/publish',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'publish_tournament' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/(?P<id>\d+)/deadline',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'extend_deadline' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/(?P<id>\d+)/entries',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_tournament_entries' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournament-entries/mine',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_my_entries' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournament-entries/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_entry' ],
				'permission_callback' => [ $this, 'check_entry_read' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournament-entries/(?P<id>\d+)/draft',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'save_draft' ],
				'permission_callback' => [ $this, 'check_entry_write' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournament-entries/(?P<id>\d+)/submit',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'submit_entry' ],
				'permission_callback' => [ $this, 'check_entry_write' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournament-entries/(?P<id>\d+)/retry-payment-link',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'retry_payment_link' ],
				'permission_callback' => [ $this, 'check_entry_read' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
	}

	public function check_manager_permission(): bool {
		return $this->check_user_approved() && TournamentAccess::can_manage();
	}

	public function check_entry_read( $request ): bool {
		return $this->check_user_approved() && TournamentAccess::can_read_entry( absint( $request->get_param( 'id' ) ) );
	}

	public function check_entry_write( $request ): bool {
		return $this->check_user_approved() && TournamentAccess::is_assigned( absint( $request->get_param( 'id' ) ) );
	}

	public function get_tournaments() {
		return rest_ensure_response( $this->service->tournaments() );
	}

	public function create_tournament( $request ) {
		$result = $this->service->save_tournament( $request->get_json_params() ?: [], get_current_user_id() );
		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result, 201 );
	}

	public function get_tournament( $request ) {
		$result = $this->service->format_tournament( absint( $request->get_param( 'id' ) ), true );
		return empty( $result )
			? new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] )
			: rest_ensure_response( $result );
	}

	public function update_tournament( $request ) {
		$result = $this->service->save_tournament( $request->get_json_params() ?: [], get_current_user_id(), absint( $request->get_param( 'id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function delete_tournament( $request ) {
		$result = $this->service->delete_tournament( absint( $request->get_param( 'id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function get_assignment_options() {
		return rest_ensure_response( $this->service->assignment_options() );
	}

	public function publish_tournament( $request ) {
		$payload = $request->get_json_params() ?: [];
		$result  = $this->service->publish(
			absint( $request->get_param( 'id' ) ),
			is_array( $payload['assignments'] ?? null ) ? $payload['assignments'] : [],
			get_current_user_id()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function extend_deadline( $request ) {
		$payload = $request->get_json_params() ?: [];
		$result  = $this->service->extend_deadline( absint( $request->get_param( 'id' ) ), $payload['internal_deadline'] ?? '' );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function get_tournament_entries( $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( get_post_type( $id ) !== TournamentService::TOURNAMENT_POST_TYPE ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		return rest_ensure_response( $this->service->entries_for_tournament( $id ) );
	}

	public function get_my_entries() {
		return rest_ensure_response( $this->service->entries_for_user( get_current_user_id() ) );
	}

	public function get_entry( $request ) {
		$result = $this->service->format_entry( absint( $request->get_param( 'id' ) ) );
		return empty( $result )
			? new \WP_Error( 'rondo_tournament_entry_not_found', __( 'Inschrijfopdracht niet gevonden.', 'rondo' ), [ 'status' => 404 ] )
			: rest_ensure_response( $result );
	}

	public function save_draft( $request ) {
		$result = $this->service->save_draft( absint( $request->get_param( 'id' ) ), $request->get_json_params() ?: [], get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function submit_entry( $request ) {
		$result = $this->service->submit_entry( absint( $request->get_param( 'id' ) ), $request->get_json_params() ?: [], get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function retry_payment_link( $request ) {
		$result = $this->service->retry_payment( absint( $request->get_param( 'id' ) ), get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}
}
