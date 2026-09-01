<?php
/**
 * REST API for tournament planning and assigned team registrations.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Tournaments\TournamentAccess;
use Rondo\Tournaments\TournamentChangeNotificationService;
use Rondo\Tournaments\TournamentExport;
use Rondo\Tournaments\TournamentProgramService;
use Rondo\Tournaments\TournamentService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Tournaments extends Base {

	private TournamentService $service;
	private TournamentChangeNotificationService $change_notifications;
	private TournamentProgramService $programs;
	private TournamentExport $export;

	public function __construct() {
		parent::__construct();
		$this->change_notifications = new TournamentChangeNotificationService();
		$this->service              = new TournamentService( null, $this->change_notifications );
		$this->programs             = new TournamentProgramService();
		$this->export               = new TournamentExport( $this->service );
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
			'/tournaments/(?P<id>\d+)/change-notification',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_change_notification' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
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
			'/tournament-entries/(?P<id>\d+)/payment-reminder',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_payment_reminder' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournament-entries/(?P<id>\d+)/reopen',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reopen_entry' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournament-entries/(?P<id>\d+)/planner-note',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_planner_note' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/(?P<id>\d+)/external-status',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_external_status' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/(?P<id>\d+)/status',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_lifecycle_status' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/(?P<id>\d+)/program',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_program' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/tournaments/(?P<id>\d+)/export\.(?P<format>csv|pdf)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'download_export' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [
					'id'     => [ 'sanitize_callback' => 'absint' ],
					'format' => [ 'sanitize_callback' => 'sanitize_key' ],
				],
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

	public function send_change_notification( $request ) {
		$payload = $request->get_json_params() ?: [];
		$result  = $this->change_notifications->send(
			absint( $request->get_param( 'id' ) ),
			absint( $payload['activity_id'] ?? 0 ),
			$payload,
			get_current_user_id()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function extend_deadline( $request ) {
		$payload = $request->get_json_params() ?: [];
		$result  = $this->service->extend_deadline( absint( $request->get_param( 'id' ) ), $payload['internal_deadline'] ?? '', get_current_user_id() );
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

	public function send_payment_reminder( $request ) {
		$result = $this->service->send_payment_reminder( absint( $request->get_param( 'id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function reopen_entry( $request ) {
		$result = $this->service->reopen_entry( absint( $request->get_param( 'id' ) ), get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function update_planner_note( $request ) {
		$payload = $request->get_json_params() ?: [];
		$result  = $this->service->update_planner_note( absint( $request->get_param( 'id' ) ), (string) ( $payload['planner_note'] ?? '' ), get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function update_external_status( $request ) {
		$payload = $request->get_json_params() ?: [];
		$result  = $this->service->update_external_status( absint( $request->get_param( 'id' ) ), (string) ( $payload['external_status'] ?? '' ), get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function update_lifecycle_status( $request ) {
		$payload = $request->get_json_params() ?: [];
		$result  = $this->service->update_lifecycle_status( absint( $request->get_param( 'id' ) ), (string) ( $payload['lifecycle_status'] ?? '' ), get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function handle_program( $request ) {
		$tournament_id = absint( $request->get_param( 'id' ) );
		$payload       = $request->get_json_params() ?: $request->get_params();
		$files         = $request->get_file_params();
		$attachment_id = null;
		if ( ! empty( $files['program_file'] ) ) {
			$attachment_id = $this->programs->upload( $tournament_id, $files['program_file'] );
			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}
		}
		$saved = $this->programs->save( $tournament_id, $payload, get_current_user_id(), $attachment_id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$preview = $this->programs->recipients( $tournament_id );
		$sent    = null;
		if ( sanitize_key( (string) ( $payload['action'] ?? 'preview' ) ) === 'send' ) {
			$sent = $this->programs->send( $tournament_id, $payload, get_current_user_id() );
			if ( is_wp_error( $sent ) ) {
				return $sent;
			}
		}
		return rest_ensure_response(
			[
				'tournament' => $this->service->format_tournament( $tournament_id, true ),
				'program'    => $this->programs->state( $tournament_id ),
				'preview'    => $preview,
				'sent'       => $sent,
			]
		);
	}

	public function download_export( $request ) {
		$tournament_id = absint( $request->get_param( 'id' ) );
		$format        = sanitize_key( (string) $request->get_param( 'format' ) );
		$content       = $format === 'pdf' ? $this->export->pdf( $tournament_id ) : $this->export->csv( $tournament_id );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		$name = sanitize_file_name( get_the_title( $tournament_id ) ?: 'toernooi' );
		nocache_headers();
		header( 'Content-Type: ' . ( $format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8' ) );
		header( 'Content-Disposition: attachment; filename="' . $name . '.' . $format . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary download response.
		echo $content;
		exit;
	}
}
