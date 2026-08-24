<?php
/**
 * Member self-service profile and profile-change audit REST API.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Users\ActivationService;
use Rondo\Users\MemberProfileService;
use Rondo\Users\ProfileChangeLog;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MemberProfile extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/** Register member profile and audit routes. */
	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/user/profile-email/pending',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_pending_email' ],
					'permission_callback' => 'is_user_logged_in',
					'args'                => [
						'person_id' => [
							'type'    => 'integer',
							'minimum' => 1,
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'cancel_pending_email' ],
					'permission_callback' => 'is_user_logged_in',
					'args'                => [
						'person_id' => [
							'type'    => 'integer',
							'minimum' => 1,
						],
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/user/profile-email/request',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'request_email_change' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'slot'      => [
						'required' => true,
						'type'     => 'string',
					],
					'email'     => [
						'required' => true,
						'type'     => 'string',
					],
					'person_id' => [
						'type'    => 'integer',
						'minimum' => 1,
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/user/profile-email/secondary',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_secondary_email' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'person_id' => [
						'type'    => 'integer',
						'minimum' => 1,
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/user/profile-phones',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_phones' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/user/household-address',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_household_address' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/profile-change-log',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_change_log' ],
				'permission_callback' => [ $this, 'can_view_change_log' ],
				'args'                => [
					'page'     => [
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					],
					'per_page' => [
						'type'    => 'integer',
						'default' => 50,
						'minimum' => 1,
						'maximum' => 100,
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/profile-change-log/sync-status',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_sync_status' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'person_id' => [
						'required' => true,
						'type'     => 'integer',
						'minimum'  => 1,
					],
					'fields'    => [
						'required' => true,
						'type'     => 'array',
						'minItems' => 1,
					],
					'status'    => [
						'required' => true,
						'type'     => 'string',
						'enum'     => [ 'synced', 'failed' ],
					],
					'error'     => [
						'type'    => 'string',
						'default' => '',
					],
				],
			]
		);
	}

	public function get_pending_email( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( [ 'pending' => MemberProfileService::pending_email_change( get_current_user_id(), (int) $request['person_id'] ?: null ) ] );
	}

	public function cancel_pending_email( \WP_REST_Request $request ): \WP_REST_Response {
		MemberProfileService::cancel_email_change( get_current_user_id(), (int) $request['person_id'] ?: null );
		return rest_ensure_response( [ 'success' => true ] );
	}

	public function request_email_change( \WP_REST_Request $request ) {
		return MemberProfileService::request_email_change(
			get_current_user_id(),
			(string) $request['slot'],
			(string) $request['email'],
			ActivationService::client_ip(),
			(int) $request['person_id'] ?: null
		);
	}

	public function remove_secondary_email( \WP_REST_Request $request ) {
		return MemberProfileService::remove_secondary_email( get_current_user_id(), (int) $request['person_id'] ?: null );
	}

	public function update_phones( \WP_REST_Request $request ) {
		$values = (array) $request->get_json_params();
		return MemberProfileService::update_phones( get_current_user_id(), $values, (int) ( $values['person_id'] ?? 0 ) ?: null );
	}

	public function update_household_address( \WP_REST_Request $request ) {
		return MemberProfileService::update_household_address( get_current_user_id(), (array) $request->get_json_params() );
	}

	public function can_view_change_log(): bool {
		return current_user_can( 'ledenadministratie' ) || current_user_can( 'manage_options' );
	}

	public function get_change_log( \WP_REST_Request $request ): \WP_REST_Response {
		return rest_ensure_response( ProfileChangeLog::recent( (int) $request['page'], (int) $request['per_page'] ) );
	}

	public function update_sync_status( \WP_REST_Request $request ): \WP_REST_Response {
		$fields = array_values( array_filter( array_map( 'sanitize_key', (array) $request['fields'] ) ) );
		return rest_ensure_response(
			[
				'updated' => ProfileChangeLog::update_sync_status(
					(int) $request['person_id'],
					$fields,
					(string) $request['status'],
					(string) $request['error']
				),
			]
		);
	}
}
