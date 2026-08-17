<?php
/**
 * REST API for the Rondo narrowcasting player pilot.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Rondo\Config\ClubConfig;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Narrowcasting\Content;
use Rondo\Narrowcasting\SportlinkMatchday;
use Rondo\Pages\PublicPageChrome;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pair players, serve their configuration, and track basic health.
 */
class Narrowcasting extends Base {

	private const POST_TYPE                    = 'rondo_display';
	private const REGISTRATION_TTL             = 900;
	private const CLAIM_REPLAY_TTL             = 300;
	private const ONLINE_TTL                   = 180;
	private const DURABLE_HEARTBEAT_INTERVAL   = 300;
	private const COMMAND_TTL                  = 600;
	private const REGISTRATION_RATE_PER_MINUTE = 10;

	private SportlinkMatchday $matchday;
	private Content $content;

	/** Commands the player service is allowed to execute. */
	private const ALLOWED_COMMANDS = [
		'reload',
		'restart_browser',
		'reboot',
		'wake_tv',
		'sleep_tv',
		'cec_detect',
	];

	public function __construct() {
		parent::__construct();
		$this->matchday = new SportlinkMatchday( false );
		$this->content  = new Content();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/** Register pilot routes. */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/narrowcasting/devices/register',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'register_device' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/devices/claim',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'claim_device_token' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/devices/me/config',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_device_config' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/devices/me/playlist',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_device_playlist' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/devices/me/heartbeat',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'record_heartbeat' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/devices/me/commands',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_device_command' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/devices/me/commands/ack',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'acknowledge_device_command' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/displays',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_displays' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/preview',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_preview_config' ],
				'permission_callback' => [ $this, 'check_content_permission' ],
			]
		);

		$this->register_content_routes();

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_matchday_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_matchday_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/refresh',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'refresh_matchday_feed' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/feeds/matchday',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_matchday_feed' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/displays/claim',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'approve_display' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/displays/(?P<id>\d+)/commands',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'queue_display_command' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/displays/(?P<id>\d+)/revoke',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'revoke_display' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/** Register authenticated content and public player playlist routes. */
	private function register_content_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/narrowcasting/items',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_content_items' ],
					'permission_callback' => [ $this, 'check_content_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_content_item' ],
					'permission_callback' => [ $this, 'check_content_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/items/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_content_item' ],
					'permission_callback' => [ $this, 'check_content_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_content_item' ],
					'permission_callback' => [ $this, 'check_content_permission' ],
				],
				'args' => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/playlists',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_playlists' ],
					'permission_callback' => [ $this, 'check_playlist_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_playlist' ],
					'permission_callback' => [ $this, 'check_playlist_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/playlists/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_playlist' ],
					'permission_callback' => [ $this, 'check_playlist_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_playlist' ],
					'permission_callback' => [ $this, 'check_playlist_permission' ],
				],
				'args' => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/playlists/(?P<id>\d+)/default',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_default_playlist' ],
				'permission_callback' => [ $this, 'check_playlist_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/preview/playlist',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_preview_playlist' ],
				'permission_callback' => [ $this, 'check_content_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/content/sponsors',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_sponsor_choices' ],
				'permission_callback' => [ $this, 'check_content_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/content/displays',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_display_choices' ],
				'permission_callback' => [ $this, 'check_playlist_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/displays/(?P<id>\d+)/playlist',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'assign_display_playlist' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
	}

	/**
	 * Start or resume a short-lived pairing registration.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function register_device( $request ) {
		$rate_error = $this->consume_registration_rate_limit();
		if ( is_wp_error( $rate_error ) ) {
			return $rate_error;
		}

		$device_id = $this->sanitize_device_id( $request->get_param( 'device_id' ) );
		if ( is_wp_error( $device_id ) ) {
			return $device_id;
		}

		$existing_display = $this->find_display_by_device_id( $device_id );
		if ( $existing_display && Fields::get_for_post( $existing_display, 'pairing_status' ) !== 'revoked' ) {
			return new \WP_Error(
				'rondo_player_already_registered',
				__( 'Deze player is al aan een scherm gekoppeld.', 'rondo' ),
				[ 'status' => 409 ]
			);
		}

		$device_key   = $this->device_registration_key( $device_id );
		$registration = get_transient( $device_key );
		if ( is_array( $registration ) && ! empty( $registration['code'] ) ) {
			return rest_ensure_response( $this->registration_response( $registration ) );
		}

		$code       = $this->generate_activation_code();
		$expires_at = time() + self::REGISTRATION_TTL;
		$payload    = [
			'code'       => $code,
			'device_id'  => $device_id,
			'expires_at' => $expires_at,
		];

		set_transient( $device_key, $payload, self::REGISTRATION_TTL );
		set_transient( $this->code_registration_key( $code ), $payload, self::REGISTRATION_TTL );

		return rest_ensure_response( $this->registration_response( $payload ) );
	}

	/**
	 * Approve a code shown by a physical player and create its display record.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function approve_display( $request ) {
		$code = $this->normalize_activation_code( $request->get_param( 'code' ) );
		if ( $code === '' ) {
			return new \WP_Error( 'rondo_player_code_required', __( 'Vul de activatiecode in.', 'rondo' ), [ 'status' => 400 ] );
		}

		$registration = get_transient( $this->code_registration_key( $code ) );
		if ( ! is_array( $registration ) || empty( $registration['device_id'] ) ) {
			return new \WP_Error( 'rondo_player_code_expired', __( 'Deze activatiecode is ongeldig of verlopen.', 'rondo' ), [ 'status' => 404 ] );
		}

		if ( ! empty( $registration['display_id'] ) ) {
			return new \WP_Error( 'rondo_player_code_used', __( 'Deze activatiecode is al goedgekeurd.', 'rondo' ), [ 'status' => 409 ] );
		}

		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'rondo_display_title_required', __( 'Geef het scherm een naam.', 'rondo' ), [ 'status' => 400 ] );
		}

		$wake_time  = $this->sanitize_time( $request->get_param( 'wake_time' ), '08:00' );
		$sleep_time = $this->sanitize_time( $request->get_param( 'sleep_time' ), '23:00' );
		$timezone   = $this->sanitize_timezone( $request->get_param( 'timezone' ) );
		if ( is_wp_error( $wake_time ) ) {
			return $wake_time;
		}
		if ( is_wp_error( $sleep_time ) ) {
			return $sleep_time;
		}
		if ( is_wp_error( $timezone ) ) {
			return $timezone;
		}

		$existing_display = $this->find_display_by_device_id( $registration['device_id'] );
		if ( $existing_display && Fields::get_for_post( $existing_display, 'pairing_status' ) !== 'revoked' ) {
			return new \WP_Error( 'rondo_player_already_registered', __( 'Deze player is al gekoppeld.', 'rondo' ), [ 'status' => 409 ] );
		}

		$display_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_author' => get_current_user_id(),
			],
			true
		);
		if ( is_wp_error( $display_id ) ) {
			return $display_id;
		}

		$stored = Fields::update_many_for_post(
			$display_id,
			[
				'device_id'        => $registration['device_id'],
				'location'         => sanitize_text_field( (string) $request->get_param( 'location' ) ),
				'display_timezone' => $timezone,
				'wake_time'        => $wake_time,
				'sleep_time'       => $sleep_time,
				'cec_enabled'      => true,
				'pairing_status'   => 'approved',
				'pilot_message'    => __( 'Rondo Player is verbonden', 'rondo' ),
			]
		);
		if ( is_wp_error( $stored ) ) {
			wp_delete_post( $display_id, true );
			return $stored;
		}

		$registration['display_id'] = $display_id;
		$ttl                        = max( 1, (int) $registration['expires_at'] - time() );
		set_transient( $this->code_registration_key( $code ), $registration, $ttl );
		set_transient( $this->device_registration_key( $registration['device_id'] ), $registration, $ttl );

		return rest_ensure_response( $this->format_display( $display_id ) );
	}

	/**
	 * Exchange an administrator-approved registration for a device token.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function claim_device_token( $request ) {
		$device_id = $this->sanitize_device_id( $request->get_param( 'device_id' ) );
		if ( is_wp_error( $device_id ) ) {
			return $device_id;
		}

		$code = $this->normalize_activation_code( $request->get_param( 'code' ) );
		if ( $code === '' ) {
			return new \WP_Error( 'rondo_player_code_required', __( 'Activatiecode ontbreekt.', 'rondo' ), [ 'status' => 400 ] );
		}

		$replay_key = $this->claim_replay_key( $device_id, $code );
		$replay     = get_transient( $replay_key );
		if ( is_array( $replay ) && ! empty( $replay['token'] ) ) {
			return rest_ensure_response( $replay );
		}

		$registration = get_transient( $this->code_registration_key( $code ) );
		if ( ! is_array( $registration ) || empty( $registration['display_id'] ) ) {
			return new \WP_Error( 'rondo_player_not_approved', __( 'De player wacht nog op goedkeuring.', 'rondo' ), [ 'status' => 409 ] );
		}

		if ( ! hash_equals( (string) $registration['device_id'], $device_id ) ) {
			return new \WP_Error( 'rondo_player_device_mismatch', __( 'De activatiecode hoort bij een andere player.', 'rondo' ), [ 'status' => 403 ] );
		}

		$display_id = absint( $registration['display_id'] );
		if ( ! $this->is_display( $display_id ) ) {
			return new \WP_Error( 'rondo_display_not_found', __( 'Het gekoppelde scherm bestaat niet.', 'rondo' ), [ 'status' => 404 ] );
		}

		$token  = $this->generate_device_token();
		$stored = Fields::update_many_for_post(
			$display_id,
			[
				'device_secret_hash' => $this->hash_device_token( $token ),
				'pairing_status'     => 'paired',
				'paired_at'          => current_datetime()->format( 'Y-m-d H:i:s' ),
			]
		);
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		$response = [
			'token'   => $token,
			'display' => $this->device_config( $display_id ),
		];
		set_transient( $replay_key, $response, self::CLAIM_REPLAY_TTL );

		return rest_ensure_response( $response );
	}

	/** Return the current device configuration. */
	public function get_device_config( $request ) {
		$display_id = $this->authenticate_device( $request );
		if ( is_wp_error( $display_id ) ) {
			return $display_id;
		}

		return rest_ensure_response( $this->device_config( $display_id ) );
	}

	/** Return the resolved, player-safe playlist for one paired display. */
	public function get_device_playlist( $request ) {
		$display_id = $this->authenticate_device( $request );
		if ( is_wp_error( $display_id ) ) {
			return $display_id;
		}
		return rest_ensure_response( $this->content->resolve_manifest( $display_id ) );
	}

	/** List content in the current user's allowed scope. */
	public function get_content_items() {
		return rest_ensure_response( $this->content->list_items( $this->is_sponsor_only_user() ) );
	}

	/** Create a validated content item. */
	public function create_content_item( $request ) {
		$result = $this->content->create_item( $request->get_json_params() ?: [], $this->is_sponsor_only_user() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Update a validated content item. */
	public function update_content_item( $request ) {
		$result = $this->content->update_item( absint( $request->get_param( 'id' ) ), $request->get_json_params() ?: [], $this->is_sponsor_only_user() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Trash a content item. */
	public function delete_content_item( $request ) {
		$result = $this->content->delete_item( absint( $request->get_param( 'id' ) ), $this->is_sponsor_only_user() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'deleted' => true ] );
	}

	public function get_playlists() {
		return rest_ensure_response( $this->content->list_playlists() );
	}

	public function create_playlist( $request ) {
		$result = $this->content->create_playlist( $request->get_json_params() ?: [] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function update_playlist( $request ) {
		$result = $this->content->update_playlist( absint( $request->get_param( 'id' ) ), $request->get_json_params() ?: [] );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function delete_playlist( $request ) {
		$result = $this->content->delete_playlist( absint( $request->get_param( 'id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'deleted' => true ] );
	}

	public function set_default_playlist( $request ) {
		$result = $this->content->set_default_playlist( absint( $request->get_param( 'id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Preview scheduling at the current or a supplied point in time. */
	public function get_preview_playlist( $request ) {
		$at = null;
		if ( $request->get_param( 'at' ) ) {
			try {
				$at = new DateTimeImmutable( sanitize_text_field( (string) $request->get_param( 'at' ) ) );
			} catch ( \Exception $error ) {
				return new \WP_Error( 'rondo_signage_preview_time_invalid', __( 'De previewtijd is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
			}
		}
		return rest_ensure_response( $this->content->resolve_manifest( absint( $request->get_param( 'display_id' ) ), absint( $request->get_param( 'playlist_id' ) ), $at, true ) );
	}

	public function get_sponsor_choices() {
		return rest_ensure_response( $this->content->sponsor_choices() );
	}

	public function get_display_choices() {
		return rest_ensure_response( $this->content->display_choices() );
	}

	public function assign_display_playlist( $request ) {
		$result = $this->content->assign_display_playlist( absint( $request->get_param( 'id' ) ), absint( $request->get_param( 'playlist_id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Store bounded health information from a paired player. */
	public function record_heartbeat( $request ) {
		$display_id = $this->authenticate_device( $request );
		if ( is_wp_error( $display_id ) ) {
			return $display_id;
		}

		$state = sanitize_key( (string) $request->get_param( 'state' ) );
		if ( ! in_array( $state, [ 'starting', 'playing', 'sleeping', 'degraded', 'error' ], true ) ) {
			$state = 'playing';
		}

		$fields = [
			'player_version'      => substr( sanitize_text_field( (string) $request->get_param( 'version' ) ), 0, 40 ),
			'last_playback_state' => $state,
			'last_error'          => substr( sanitize_text_field( (string) $request->get_param( 'error' ) ), 0, 300 ),
		];

		$last_seen = Fields::get_for_post( $display_id, 'last_seen_at' );
		$last_ts   = $last_seen ? $this->field_timestamp( (string) $last_seen ) : 0;
		if ( $last_ts === 0 || ( time() - $last_ts ) >= self::DURABLE_HEARTBEAT_INTERVAL ) {
			$fields['last_seen_at'] = current_datetime()->format( 'Y-m-d H:i:s' );
		}

		$stored = Fields::update_many_for_post( $display_id, $fields );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		set_transient( $this->online_key( $display_id ), time(), self::ONLINE_TTL );

		return rest_ensure_response(
			[
				'ok'          => true,
				'server_time' => gmdate( DATE_RFC3339 ),
			]
		);
	}

	/** Return a pending predefined command, if any. */
	public function get_device_command( $request ) {
		$display_id = $this->authenticate_device( $request );
		if ( is_wp_error( $display_id ) ) {
			return $display_id;
		}

		$command = $this->pending_command( $display_id );
		return rest_ensure_response( [ 'command' => $command ] );
	}

	/** Clear a command after the player reports its result. */
	public function acknowledge_device_command( $request ) {
		$display_id = $this->authenticate_device( $request );
		if ( is_wp_error( $display_id ) ) {
			return $display_id;
		}

		$command_id = sanitize_text_field( (string) $request->get_param( 'command_id' ) );
		$current_id = (string) Fields::get_for_post( $display_id, 'pending_command_id' );
		if ( $command_id === '' || ! hash_equals( $current_id, $command_id ) ) {
			return new \WP_Error( 'rondo_player_command_mismatch', __( 'Dit commando is niet meer actief.', 'rondo' ), [ 'status' => 409 ] );
		}

		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$error  = $status === 'failed'
			? substr( sanitize_text_field( (string) $request->get_param( 'error' ) ), 0, 300 )
			: '';

		$stored = Fields::update_many_for_post(
			$display_id,
			[
				'pending_command'           => '',
				'pending_command_id'        => '',
				'pending_command_issued_at' => '',
				'last_error'                => $error,
			]
		);
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		return rest_ensure_response( [ 'ok' => true ] );
	}

	/** List displays for the administrator screen. */
	public function get_displays() {
		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);

		return rest_ensure_response(
			array_map(
				fn( $post ) => $this->format_display( (int) $post->ID ),
				$posts
			)
		);
	}

	/** Return a credential-free sample configuration for an administrator preview. */
	public function get_preview_config() {
		return rest_ensure_response(
			$this->configuration_envelope(
				[
					'id'            => 0,
					'name'          => __( 'Voorbeeldscherm', 'rondo' ),
					'location'      => __( 'Browserpreview', 'rondo' ),
					'timezone'      => wp_timezone_string() ?: 'Europe/Amsterdam',
					'wake_time'     => '08:00',
					'sleep_time'    => '23:00',
					'cec_enabled'   => false,
					'pilot_message' => __( 'Rondo Club TV is klaar voor de pilot', 'rondo' ),
					'preview'       => true,
				]
			)
		);
	}

	/** Return masked Sportlink settings and feed health to administrators. */
	public function get_matchday_settings() {
		return rest_ensure_response( $this->matchday->settings_summary() );
	}

	/** Store the server-only Sportlink credential and club relation code. */
	public function update_matchday_settings( $request ) {
		$result = $this->matchday->update_settings(
			[
				'client_id'          => $request->get_param( 'client_id' ),
				'club_relation_code' => $request->get_param( 'club_relation_code' ),
			]
		);

		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Force a rate-limited refresh for administrator diagnostics. */
	public function refresh_matchday_feed() {
		$result = $this->matchday->manual_refresh();
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	/** Return normalized public match data to a paired player or administrator preview. */
	public function get_matchday_feed( $request ) {
		$can_preview = $this->check_content_permission();
		if ( ! $can_preview ) {
			$display_id = $this->authenticate_device( $request );
			if ( is_wp_error( $display_id ) ) {
				return $display_id;
			}
		}

		$is_preview = $can_preview && rest_sanitize_boolean( $request->get_param( 'preview' ) );
		$feed       = $is_preview ? $this->matchday->get_upcoming_saturday_feed() : $this->matchday->get_feed();

		return rest_ensure_response( $feed );
	}

	/** Queue one safe command for a paired display. */
	public function queue_display_command( $request ) {
		$display_id = absint( $request->get_param( 'id' ) );
		if ( ! $this->is_display( $display_id ) ) {
			return new \WP_Error( 'rondo_display_not_found', __( 'Scherm niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		if ( Fields::get_for_post( $display_id, 'pairing_status' ) !== 'paired' ) {
			return new \WP_Error( 'rondo_player_not_paired', __( 'Dit scherm is nog niet gekoppeld.', 'rondo' ), [ 'status' => 409 ] );
		}

		$command = sanitize_key( (string) $request->get_param( 'command' ) );
		if ( ! in_array( $command, self::ALLOWED_COMMANDS, true ) ) {
			return new \WP_Error( 'rondo_player_command_invalid', __( 'Dit playercommando is niet toegestaan.', 'rondo' ), [ 'status' => 400 ] );
		}

		$command_id = wp_generate_uuid4();
		$stored     = Fields::update_many_for_post(
			$display_id,
			[
				'pending_command'           => $command,
				'pending_command_id'        => $command_id,
				'pending_command_issued_at' => current_datetime()->format( 'Y-m-d H:i:s' ),
			]
		);
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		return rest_ensure_response( [ 'command' => $this->pending_command( $display_id ) ] );
	}

	/** Revoke one player's credential without deleting its operational history. */
	public function revoke_display( $request ) {
		$display_id = absint( $request->get_param( 'id' ) );
		if ( ! $this->is_display( $display_id ) ) {
			return new \WP_Error( 'rondo_display_not_found', __( 'Scherm niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		$stored = Fields::update_many_for_post(
			$display_id,
			[
				'device_secret_hash'        => '',
				'pairing_status'            => 'revoked',
				'pending_command'           => '',
				'pending_command_id'        => '',
				'pending_command_issued_at' => '',
			]
		);
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}
		delete_transient( $this->online_key( $display_id ) );

		return rest_ensure_response( $this->format_display( $display_id ) );
	}

	/** Build the safe configuration shared with the player and display browser. */
	private function device_config( int $display_id ): array {
		$fields = $this->wire_fields(
			$display_id,
			[
				'location',
				'display_timezone',
				'wake_time',
				'sleep_time',
				'cec_enabled',
				'pilot_message',
				'assigned_playlist_id',
			]
		);

		return $this->configuration_envelope(
			[
				'id'            => $display_id,
				'name'          => get_the_title( $display_id ),
				'location'      => $fields['location'],
				'timezone'      => $fields['display_timezone'],
				'wake_time'     => $fields['wake_time'],
				'sleep_time'    => $fields['sleep_time'],
				'cec_enabled'   => $fields['cec_enabled'],
				'pilot_message' => $fields['pilot_message'],
				'playlist_id'   => $fields['assigned_playlist_id'] ?: null,
				'preview'       => false,
			]
		);
	}

	/** Add shared club and polling metadata to a display configuration. */
	private function configuration_envelope( array $configuration ): array {
		$branding         = PublicPageChrome::branding();
		$accent_color     = sanitize_hex_color( $branding['accent_color'] ) ?: PublicPageChrome::DEFAULT_ACCENT;
		$background_color = sanitize_hex_color( $branding['accent_background_color'] ) ?: PublicPageChrome::DEFAULT_BACKGROUND;

		return array_merge(
			$configuration,
			[
				'club_name'                  => ClubConfig::get_club_name() ?: get_bloginfo( 'name' ),
				'branding'                   => [
					'logo_url'         => esc_url_raw( $branding['logo_url'] ),
					'accent_color'     => $accent_color,
					'background_color' => $background_color,
				],
				'display_url'                => home_url( '/display' ),
				'heartbeat_interval_seconds' => 60,
				'command_interval_seconds'   => 15,
				'content_interval_seconds'   => 60,
				'playlist_url'               => home_url( '/wp-json/rondo/v1/narrowcasting/devices/me/playlist' ),
				'server_time'                => gmdate( DATE_RFC3339 ),
			]
		);
	}

	/** Build an administrator-safe display representation. */
	private function format_display( int $display_id ): array {
		$fields = $this->wire_fields(
			$display_id,
			[
				'device_id',
				'location',
				'display_timezone',
				'wake_time',
				'sleep_time',
				'cec_enabled',
				'pairing_status',
				'paired_at',
				'last_seen_at',
				'player_version',
				'last_playback_state',
				'last_error',
				'pilot_message',
				'assigned_playlist_id',
			]
		);

		return array_merge(
			[
				'id'      => $display_id,
				'name'    => get_the_title( $display_id ),
				'online'  => get_transient( $this->online_key( $display_id ) ) !== false,
				'command' => $this->pending_command( $display_id ),
			],
			$fields
		);
	}

	/** Allow dedicated content managers and sponsor managers into Club TV. */
	public function check_content_permission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'narrowcasting' ) || current_user_can( 'sponsorbeheer' );
	}

	/** Playlist structure and overrides are outside the sponsor-only role. */
	public function check_playlist_permission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'narrowcasting' );
	}

	private function is_sponsor_only_user(): bool {
		return current_user_can( 'sponsorbeheer' ) && ! current_user_can( 'narrowcasting' ) && ! current_user_can( 'manage_options' );
	}

	/** Return a pending command unless it has expired. */
	private function pending_command( int $display_id ): ?array {
		$command = (string) Fields::get_for_post( $display_id, 'pending_command' );
		$id      = (string) Fields::get_for_post( $display_id, 'pending_command_id' );
		$issued  = (string) Fields::get_for_post( $display_id, 'pending_command_issued_at' );

		if ( $command === '' || $id === '' || ! in_array( $command, self::ALLOWED_COMMANDS, true ) ) {
			return null;
		}

		$issued_timestamp = $this->field_timestamp( $issued );
		if ( $issued_timestamp === 0 || ( time() - $issued_timestamp ) > self::COMMAND_TTL ) {
			Fields::update_many_for_post(
				$display_id,
				[
					'pending_command'           => '',
					'pending_command_id'        => '',
					'pending_command_issued_at' => '',
				]
			);
			return null;
		}

		return [
			'id'        => $id,
			'name'      => $command,
			'issued_at' => Formatter::for_wire( self::POST_TYPE, [ 'pending_command_issued_at' => $issued ] )['pending_command_issued_at'],
		];
	}

	/** Resolve a paired display from its request header. */
	private function authenticate_device( $request ) {
		$token = trim( (string) $request->get_header( 'x-rondo-device-token' ) );
		if ( $token === '' ) {
			$authorization = trim( (string) $request->get_header( 'authorization' ) );
			if ( preg_match( '/^Bearer\s+(.+)$/i', $authorization, $matches ) ) {
				$token = trim( $matches[1] );
			}
		}

		if ( $token === '' || strlen( $token ) > 200 ) {
			return new \WP_Error( 'rondo_player_unauthorized', __( 'Geldige playerauthenticatie ontbreekt.', 'rondo' ), [ 'status' => 401 ] );
		}

		$hash       = $this->hash_device_token( $token );
		$displays   = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => 'device_secret_hash',
				'meta_value'       => $hash,
				'suppress_filters' => true,
			]
		);
		$display_id = (int) ( $displays[0] ?? 0 );

		if ( ! $display_id || Fields::get_for_post( $display_id, 'pairing_status' ) !== 'paired' ) {
			return new \WP_Error( 'rondo_player_unauthorized', __( 'De playercredential is ongeldig of ingetrokken.', 'rondo' ), [ 'status' => 401 ] );
		}

		return $display_id;
	}

	/** Format a selected field subset for REST. */
	private function wire_fields( int $display_id, array $names ): array {
		$values = [];
		foreach ( $names as $name ) {
			$values[ $name ] = Fields::get_for_post( $display_id, $name );
		}
		return Formatter::for_wire( self::POST_TYPE, $values );
	}

	/** Find the active or most recently created display for a hardware ID. */
	private function find_display_by_device_id( string $device_id ): int {
		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'meta_key'         => 'device_id',
				'meta_value'       => $device_id,
				'suppress_filters' => true,
			]
		);
		return (int) ( $posts[0] ?? 0 );
	}

	private function is_display( int $display_id ): bool {
		return $display_id > 0 && get_post_type( $display_id ) === self::POST_TYPE;
	}

	/** @return string|\WP_Error */
	private function sanitize_device_id( $value ) {
		$device_id = trim( sanitize_text_field( (string) $value ) );
		if ( ! preg_match( '/^[A-Za-z0-9._:-]{6,128}$/', $device_id ) ) {
			return new \WP_Error( 'rondo_player_device_id_invalid', __( 'Device ID is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
		}
		return $device_id;
	}

	/** @return string|\WP_Error */
	private function sanitize_time( $value, string $default ) {
		$time = trim( (string) $value );
		if ( $time === '' ) {
			return $default;
		}
		if ( ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return new \WP_Error( 'rondo_display_time_invalid', __( 'Gebruik HH:mm voor de schermtijd.', 'rondo' ), [ 'status' => 400 ] );
		}
		return $time;
	}

	/** @return string|\WP_Error */
	private function sanitize_timezone( $value ) {
		$timezone = trim( sanitize_text_field( (string) $value ) );
		if ( $timezone === '' ) {
			$timezone = wp_timezone_string() ?: 'Europe/Amsterdam';
		}
		try {
			new DateTimeZone( $timezone );
		} catch ( \Exception $error ) {
			return new \WP_Error( 'rondo_display_timezone_invalid', __( 'De tijdzone is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
		}
		return $timezone;
	}

	private function consume_registration_rate_limit() {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$remote_address = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
		$key            = 'rondo_nc_rate_' . substr( hash( 'sha256', $remote_address ), 0, 32 );
		$count          = (int) get_transient( $key );
		if ( $count >= self::REGISTRATION_RATE_PER_MINUTE ) {
			return new \WP_Error( 'rondo_player_rate_limited', __( 'Te veel activatiepogingen. Probeer het over een minuut opnieuw.', 'rondo' ), [ 'status' => 429 ] );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private function registration_response( array $registration ): array {
		return [
			'code'                   => $registration['code'],
			'expires_at'             => gmdate( DATE_RFC3339, (int) $registration['expires_at'] ),
			'approved'               => ! empty( $registration['display_id'] ),
			'claim_interval_seconds' => 5,
		];
	}

	private function generate_activation_code(): string {
		$alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
		do {
			$code = '';
			for ( $index = 0; $index < 8; $index++ ) {
				$code .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
			}
		} while ( get_transient( $this->code_registration_key( $code ) ) !== false );

		return substr( $code, 0, 4 ) . '-' . substr( $code, 4 );
	}

	private function normalize_activation_code( $value ): string {
		$code = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $value ) );
		return strlen( $code ) === 8 ? substr( $code, 0, 4 ) . '-' . substr( $code, 4 ) : '';
	}

	private function generate_device_token(): string {
		return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	}

	private function hash_device_token( string $token ): string {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
	}

	private function device_registration_key( string $device_id ): string {
		return 'rondo_nc_device_' . substr( hash( 'sha256', $device_id ), 0, 32 );
	}

	private function code_registration_key( string $code ): string {
		return 'rondo_nc_code_' . substr( hash( 'sha256', $this->normalize_activation_code( $code ) ), 0, 32 );
	}

	private function claim_replay_key( string $device_id, string $code ): string {
		return 'rondo_nc_claim_' . substr( hash( 'sha256', $device_id . '|' . $code ), 0, 32 );
	}

	private function online_key( int $display_id ): string {
		return 'rondo_nc_online_' . $display_id;
	}

	/** Parse a native datetime field using the WordPress site timezone. */
	private function field_timestamp( string $value ): int {
		if ( $value === '' ) {
			return 0;
		}
		try {
			return ( new \DateTimeImmutable( $value, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $error ) {
			return 0;
		}
	}
}
