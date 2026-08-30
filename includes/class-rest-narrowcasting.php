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
use Rondo\Rooms\BookingService;

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
	private const PRESENTATION_CODE_TTL        = 600;
	private const PRESENTATION_SESSION_TTL     = 7200;
	private const PRESENTATION_JOIN_RATE       = 10;
	private const REGISTRATION_RATE_PER_MINUTE = 10;
	private const OPTION_STABLE_VERSION        = 'rondo_player_stable_version';
	private const OPTION_BETA_VERSION          = 'rondo_player_beta_version';
	private const DEFAULT_STABLE_VERSION       = '0.3.0';

	private SportlinkMatchday $matchday;
	private Content $content;

	/** Commands the player service is allowed to execute. */
	private const ALLOWED_COMMANDS = [
		'reload',
		'restart_browser',
		'reboot',
		'shutdown',
		'wake_tv',
		'sleep_tv',
		'cec_detect',
	];

	public function __construct() {
		parent::__construct();
		$this->matchday = new SportlinkMatchday( false );
		$this->content  = new Content();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rondo_room_presentation_stop', [ $this, 'stop_display_presentation' ] );
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

		$this->register_presentation_routes();

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/displays',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_displays' ],
				'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/displays/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_display' ],
				'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
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
					'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_matchday_settings' ],
					'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/refresh',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'refresh_matchday_feed' ],
				'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
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
				'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/displays/(?P<id>\d+)/commands',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'queue_display_command' ],
				'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
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
				'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
				'args'                => [
					'id' => [
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	/** Register the temporary browser-presentation signaling routes. */
	private function register_presentation_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/narrowcasting/devices/me/presentation/session',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_presentation_session' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/presentation/join',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'join_presentation_session' ],
				'permission_callback' => [ $this, 'check_presentation_user_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/narrowcasting/presentation/sessions/(?P<session_id>[a-f0-9-]{36})/signal',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_presentation_signal' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_presentation_signal' ],
					'permission_callback' => '__return_true',
				],
				'args' => [
					'session_id' => [
						'sanitize_callback' => 'sanitize_text_field',
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
				'permission_callback' => [ $this, 'check_narrowcasting_admin_permission' ],
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
		$channel    = $this->sanitize_update_channel( $request->get_param( 'update_channel' ) ?: 'stable' );
		if ( is_wp_error( $wake_time ) ) {
			return $wake_time;
		}
		if ( is_wp_error( $sleep_time ) ) {
			return $sleep_time;
		}
		if ( is_wp_error( $timezone ) ) {
			return $timezone;
		}
		if ( is_wp_error( $channel ) ) {
			return $channel;
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
				'update_channel'   => $channel,
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

		return $this->no_store_response( $this->device_config( $display_id ) );
	}

	/** Return the resolved, player-safe playlist for one paired display. */
	public function get_device_playlist( $request ) {
		$display_id = $this->authenticate_device( $request );
		if ( is_wp_error( $display_id ) ) {
			return $display_id;
		}
		return $this->no_store_response( $this->content->resolve_manifest( $display_id ) );
	}

	/** Create one short-lived presentation code for a paired display. */
	public function create_presentation_session( $request ) {
		$display_id = $this->authenticate_device( $request );
		if ( is_wp_error( $display_id ) ) {
			return $display_id;
		}
		if ( ! Fields::get_for_post( $display_id, 'presentation_enabled' ) ) {
			return new \WP_Error( 'rondo_presentation_disabled', __( 'Browserpresentaties zijn niet ingeschakeld voor dit scherm.', 'rondo' ), [ 'status' => 403 ] );
		}

		$entitlement = null;
		if ( \rondo_rooms_enabled() ) {
			$entitlement = ( new BookingService() )->presentation_entitlement_for_display( $display_id );
			if ( is_array( $entitlement ) && ! $entitlement['allowed'] ) {
				return new \WP_Error( 'rondo_presentation_outside_booking', __( 'Dit scherm is nu niet beschikbaar voor een presentatie.', 'rondo' ), [ 'status' => 403 ] );
			}
		}

		$previous_session_id = get_transient( $this->presentation_display_key( $display_id ) );
		if ( is_string( $previous_session_id ) && $previous_session_id !== '' ) {
			$this->delete_presentation_session( $previous_session_id );
		}

		$code = $this->generate_presentation_code();
		if ( is_wp_error( $code ) ) {
			return $code;
		}

		$session_id      = wp_generate_uuid4();
		$receiver_token  = $this->generate_device_token();
		$created_at      = time();
		$entitlement_end = is_array( $entitlement ) && ! empty( $entitlement['ends_at'] )
			? ( new DateTimeImmutable( $entitlement['ends_at'] ) )->getTimestamp()
			: $created_at + self::PRESENTATION_SESSION_TTL;
		$session_end     = min( $created_at + self::PRESENTATION_SESSION_TTL, $entitlement_end );
		$code_end        = min( $created_at + self::PRESENTATION_CODE_TTL, $session_end );
		$session         = [
			'id'                  => $session_id,
			'display_id'          => $display_id,
			'code'                => $code,
			'code_expires_at'     => $code_end,
			'expires_at'          => $session_end,
			'receiver_token_hash' => $this->hash_device_token( $receiver_token ),
			'sender_token_hash'   => '',
			'user_id'             => 0,
			'booking_id'          => (int) ( $entitlement['booking_id'] ?? 0 ),
		];

		$session_ttl = max( 1, $session_end - $created_at );
		$code_ttl    = max( 1, $code_end - $created_at );
		set_transient( $this->presentation_session_key( $session_id ), $session, $session_ttl );
		set_transient( $this->presentation_code_key( $code ), $session_id, $code_ttl );
		set_transient( $this->presentation_display_key( $display_id ), $session_id, $session_ttl );

		return $this->no_store_response(
			[
				'session_id'            => $session_id,
				'code'                  => $code,
				'token'                 => $receiver_token,
				'code_expires_at'       => gmdate( DATE_RFC3339, $session['code_expires_at'] ),
				'entitlement_ends_at'   => gmdate( DATE_RFC3339, $session['expires_at'] ),
				'booking_id'            => (int) $session['booking_id'],
				'room_name'             => (string) ( $entitlement['room_name'] ?? '' ),
				'booking_starts_at'     => (string) ( $entitlement['starts_at'] ?? '' ),
				'booking_ends_at'       => (string) ( $entitlement['ends_at'] ?? '' ),
				'poll_interval_seconds' => 1,
			]
		);
	}

	/** Exchange a visible presentation code for an authenticated sender token. */
	public function join_presentation_session( $request ) {
		$rate_error = $this->consume_presentation_join_rate_limit();
		if ( is_wp_error( $rate_error ) ) {
			return $rate_error;
		}

		$code = $this->normalize_presentation_code( $request->get_param( 'code' ) );
		if ( $code === '' ) {
			return new \WP_Error( 'rondo_presentation_code_invalid', __( 'Voer de zescijferige code van het scherm in.', 'rondo' ), [ 'status' => 400 ] );
		}

		$session_id = get_transient( $this->presentation_code_key( $code ) );
		$session    = is_string( $session_id ) ? get_transient( $this->presentation_session_key( $session_id ) ) : false;
		if ( ! is_array( $session ) || (int) $session['code_expires_at'] < time() ) {
			return new \WP_Error( 'rondo_presentation_code_expired', __( 'Deze schermcode is ongeldig of verlopen.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( ! empty( $session['sender_token_hash'] ) ) {
			return new \WP_Error( 'rondo_presentation_in_use', __( 'Dit scherm wordt al door iemand gebruikt.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( ! $this->is_display( (int) $session['display_id'] ) || ! Fields::get_for_post( (int) $session['display_id'], 'presentation_enabled' ) ) {
			return new \WP_Error( 'rondo_presentation_unavailable', __( 'Dit scherm is niet beschikbaar voor browserpresentaties.', 'rondo' ), [ 'status' => 409 ] );
		}

		$entitlement = null;
		if ( ! \rondo_rooms_enabled() && (int) ( $session['booking_id'] ?? 0 ) > 0 ) {
			$this->delete_presentation_session( $session['id'] );
			return new \WP_Error( 'rondo_presentation_expired', __( 'Deze presentatiesessie is verlopen.', 'rondo' ), [ 'status' => 410 ] );
		}
		if ( (int) ( $session['booking_id'] ?? 0 ) > 0 && ! \Rondo\Config\FeatureToggles::can_access( 'rooms' ) ) {
			return new \WP_Error( 'rondo_presentation_not_authorized', __( 'Je hebt nu geen toegang tot dit scherm.', 'rondo' ), [ 'status' => 403 ] );
		}
		$booking_service = \rondo_rooms_enabled() ? new BookingService() : null;
		if ( $booking_service && ( (int) ( $session['booking_id'] ?? 0 ) > 0 || $booking_service->display_is_reservation_controlled( (int) $session['display_id'] ) ) ) {
			$entitlement = $booking_service->presentation_entitlement_for_display( (int) $session['display_id'], get_current_user_id() );
			if ( ! is_array( $entitlement ) || ! $entitlement['allowed'] || (int) ( $entitlement['booking_id'] ?? 0 ) !== (int) ( $session['booking_id'] ?? 0 ) ) {
				return new \WP_Error( 'rondo_presentation_not_authorized', __( 'Je hebt nu geen toegang tot dit scherm.', 'rondo' ), [ 'status' => 403 ] );
			}
			$session['expires_at'] = min(
				time() + self::PRESENTATION_SESSION_TTL,
				( new DateTimeImmutable( $entitlement['ends_at'] ) )->getTimestamp()
			);
		}

		$sender_token                 = $this->generate_device_token();
		$session['sender_token_hash'] = $this->hash_device_token( $sender_token );
		$session['user_id']           = get_current_user_id();
		if ( empty( $session['booking_id'] ) ) {
			$session['expires_at'] = time() + self::PRESENTATION_SESSION_TTL;
		}
		set_transient( $this->presentation_session_key( $session['id'] ), $session, max( 1, (int) $session['expires_at'] - time() ) );
		delete_transient( $this->presentation_code_key( $code ) );

		return $this->no_store_response(
			[
				'session_id'            => $session['id'],
				'token'                 => $sender_token,
				'display_name'          => get_the_title( (int) $session['display_id'] ),
				'entitlement_ends_at'   => gmdate( DATE_RFC3339, (int) $session['expires_at'] ),
				'booking_id'            => (int) ( $session['booking_id'] ?? 0 ),
				'room_name'             => (string) ( $entitlement['room_name'] ?? '' ),
				'poll_interval_seconds' => 1,
			]
		);
	}

	/** Return the other participant's latest complete signaling snapshot. */
	public function get_presentation_signal( $request ) {
		$identity = $this->authenticate_presentation_session( $request );
		if ( is_wp_error( $identity ) ) {
			return $identity;
		}

		$other_role = $identity['role'] === 'sender' ? 'receiver' : 'sender';
		$signal     = get_transient( $this->presentation_signal_key( $identity['session']['id'], $other_role ) );
		$this->refresh_presentation_session( $identity['session'] );

		return $this->no_store_response(
			[
				'signal'              => is_array( $signal ) ? $signal : null,
				'entitlement_ends_at' => gmdate( DATE_RFC3339, (int) $identity['session']['expires_at'] ),
			]
		);
	}

	/** Store one participant's complete signaling snapshot. */
	public function update_presentation_signal( $request ) {
		$identity = $this->authenticate_presentation_session( $request );
		if ( is_wp_error( $identity ) ) {
			return $identity;
		}

		$signal = $this->sanitize_presentation_signal( $request->get_json_params(), $identity['role'] );
		if ( is_wp_error( $signal ) ) {
			return $signal;
		}

		set_transient(
			$this->presentation_signal_key( $identity['session']['id'], $identity['role'] ),
			$signal,
			self::PRESENTATION_SESSION_TTL
		);
		$this->refresh_presentation_session( $identity['session'] );

		return $this->no_store_response( [ 'stored' => true ] );
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
		return $this->no_store_response( $this->content->resolve_manifest( absint( $request->get_param( 'display_id' ) ), absint( $request->get_param( 'playlist_id' ) ), $at, true ) );
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
		return $this->no_store_response( [ 'command' => $command ] );
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

	/** Update the administrator-managed name, location and schedule of a display. */
	public function update_display( $request ) {
		$display_id = absint( $request->get_param( 'id' ) );
		if ( ! $this->is_display( $display_id ) ) {
			return new \WP_Error( 'rondo_display_not_found', __( 'Scherm niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		$title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'rondo_display_title_required', __( 'Geef het scherm een naam.', 'rondo' ), [ 'status' => 400 ] );
		}

		$current              = $this->wire_fields( $display_id, [ 'wake_time', 'sleep_time', 'display_timezone', 'update_channel', 'presentation_enabled' ] );
		$wake_time            = $this->sanitize_time( $request->get_param( 'wake_time' ), (string) $current['wake_time'] );
		$sleep_time           = $this->sanitize_time( $request->get_param( 'sleep_time' ), (string) $current['sleep_time'] );
		$timezone             = $this->sanitize_timezone( $request->get_param( 'timezone' ) ?: $current['display_timezone'] );
		$channel              = $this->sanitize_update_channel( $request->get_param( 'update_channel' ) ?: $current['update_channel'] );
		$presentation_enabled = $request->has_param( 'presentation_enabled' )
			? rest_sanitize_boolean( $request->get_param( 'presentation_enabled' ) )
			: (bool) $current['presentation_enabled'];
		if ( is_wp_error( $wake_time ) ) {
			return $wake_time;
		}
		if ( is_wp_error( $sleep_time ) ) {
			return $sleep_time;
		}
		if ( is_wp_error( $timezone ) ) {
			return $timezone;
		}
		if ( is_wp_error( $channel ) ) {
			return $channel;
		}

		$updated = wp_update_post(
			[
				'ID'         => $display_id,
				'post_title' => $title,
			],
			true
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$stored = Fields::update_many_for_post(
			$display_id,
			[
				'location'             => sanitize_text_field( (string) $request->get_param( 'location' ) ),
				'display_timezone'     => $timezone,
				'wake_time'            => $wake_time,
				'sleep_time'           => $sleep_time,
				'update_channel'       => $channel,
				'presentation_enabled' => $presentation_enabled,
			]
		);
		return is_wp_error( $stored ) ? $stored : rest_ensure_response( $this->format_display( $display_id ) );
	}

	/** Return a credential-free sample configuration for an administrator preview. */
	public function get_preview_config() {
		return $this->no_store_response(
			$this->configuration_envelope(
				[
					'id'                   => 0,
					'name'                 => __( 'Voorbeeldscherm', 'rondo' ),
					'location'             => __( 'Browserpreview', 'rondo' ),
					'timezone'             => wp_timezone_string() ?: 'Europe/Amsterdam',
					'wake_time'            => '08:00',
					'sleep_time'           => '23:00',
					'cec_enabled'          => false,
					'presentation_enabled' => false,
					'pilot_message'        => __( 'Rondo Club TV is klaar voor de pilot', 'rondo' ),
					'preview'              => true,
				]
			)
		);
	}

	/** Return masked Sportlink settings, feed health and approved player releases. */
	public function get_matchday_settings() {
		return rest_ensure_response( $this->settings_summary() );
	}

	/** Store Sportlink configuration and approved player release versions. */
	public function update_matchday_settings( $request ) {
		if ( $request->has_param( 'client_id' ) || $request->has_param( 'club_relation_code' ) ) {
			$result = $this->matchday->update_settings(
				[
					'client_id'          => $request->get_param( 'client_id' ),
					'club_relation_code' => $request->get_param( 'club_relation_code' ),
				]
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		foreach ( [
			'stable_version' => self::OPTION_STABLE_VERSION,
			'beta_version'   => self::OPTION_BETA_VERSION,
		] as $parameter => $option ) {
			if ( ! $request->has_param( $parameter ) ) {
				continue;
			}
			$version = trim( sanitize_text_field( (string) $request->get_param( $parameter ) ) );
			if ( $version !== '' && ! preg_match( '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/', $version ) ) {
				return new \WP_Error( 'rondo_player_version_invalid', __( 'Gebruik een player-versie zoals 0.3.0.', 'rondo' ), [ 'status' => 400 ] );
			}
			update_option( $option, $version, false );
		}

		return rest_ensure_response( $this->settings_summary() );
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

		$is_preview       = $can_preview && rest_sanitize_boolean( $request->get_param( 'preview' ) );
		$feed             = $is_preview ? $this->matchday->get_upcoming_saturday_feed() : $this->matchday->get_feed();
		$feed['sponsors'] = array_values(
			array_filter(
				$this->content->sponsor_choices(),
				static fn( array $sponsor ): bool => empty( $sponsor['legacy'] )
					&& ! empty( $sponsor['logo_url'] )
					&& empty( $sponsor['club_tv_opt_out'] )
					&& (int) ( $sponsor['club_tv_priority'] ?? 0 ) > 0
			)
		);

		return $this->no_store_response( $feed );
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
				'update_channel',
				'presentation_enabled',
			]
		);

		$configuration                      = $this->configuration_envelope(
			[
				'id'                   => $display_id,
				'name'                 => get_the_title( $display_id ),
				'location'             => $fields['location'],
				'timezone'             => $fields['display_timezone'],
				'wake_time'            => $fields['wake_time'],
				'sleep_time'           => $fields['sleep_time'],
				'cec_enabled'          => $fields['cec_enabled'],
				'pilot_message'        => $fields['pilot_message'],
				'presentation_enabled' => (bool) $fields['presentation_enabled'],
				'playlist_id'          => $fields['assigned_playlist_id'] ?: null,
				'preview'              => false,
			]
		);
		$configuration['update']            = $this->player_update_config( (string) $fields['update_channel'] );
		$room_service                       = \rondo_rooms_enabled() ? new BookingService() : null;
		$room_id                            = $room_service ? $room_service->room_id_for_display( $display_id ) : 0;
		$entitlement                        = $room_service ? $room_service->presentation_entitlement_for_display( $display_id ) : null;
		$configuration['room_presentation'] = [
			'controlled' => $room_service && $room_id > 0 && $room_service->display_is_reservation_controlled( $display_id ),
			'room_id'    => $room_id ?: null,
			'room_name'  => (string) ( $entitlement['room_name'] ?? '' ),
			'active'     => is_array( $entitlement ) && ! empty( $entitlement['allowed'] ),
			'booking_id' => (int) ( $entitlement['booking_id'] ?? 0 ) ?: null,
			'starts_at'  => (string) ( $entitlement['starts_at'] ?? '' ) ?: null,
			'ends_at'    => (string) ( $entitlement['ends_at'] ?? '' ) ?: null,
		];
		return $configuration;
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
				'content_interval_seconds'   => 10,
				'playlist_url'               => home_url( '/wp-json/rondo/v1/narrowcasting/devices/me/playlist' ),
				'server_time'                => gmdate( DATE_RFC3339 ),
			]
		);
	}

	/** Prevent device-specific and time-sensitive responses from entering HTTP caches. */
	private function no_store_response( $data ): \WP_REST_Response {
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'Expires', 'Wed, 11 Jan 1984 05:00:00 GMT' );
		return $response;
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
				'update_channel',
				'presentation_enabled',
			]
		);

		$display                          = array_merge(
			[
				'id'      => $display_id,
				'name'    => get_the_title( $display_id ),
				'online'  => get_transient( $this->online_key( $display_id ) ) !== false,
				'command' => $this->pending_command( $display_id ),
			],
			$fields
		);
		$display['update_target_version'] = $this->player_update_config( (string) $fields['update_channel'] )['target_version'];
		return $display;
	}

	/** Combine the existing feed settings with player release controls. */
	private function settings_summary(): array {
		return array_merge(
			$this->matchday->settings_summary(),
			[
				'player_updates' => [
					'stable_version' => $this->player_version_option( self::OPTION_STABLE_VERSION, self::DEFAULT_STABLE_VERSION ),
					'beta_version'   => $this->player_version_option( self::OPTION_BETA_VERSION, '' ),
				],
			]
		);
	}

	/** Resolve the signed release target sent to one player. */
	private function player_update_config( string $channel ): array {
		$channel = in_array( $channel, [ 'stable', 'beta', 'off' ], true ) ? $channel : 'stable';
		$target  = '';
		if ( $channel === 'stable' ) {
			$target = $this->player_version_option( self::OPTION_STABLE_VERSION, self::DEFAULT_STABLE_VERSION );
		} elseif ( $channel === 'beta' ) {
			$target = $this->player_version_option( self::OPTION_BETA_VERSION, '' );
		}
		return [
			'channel'        => $channel,
			'target_version' => $target,
		];
	}

	private function player_version_option( string $option, string $default ): string {
		$value = (string) get_option( $option, $default );
		return preg_match( '/^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/', $value ) ? $value : $default;
	}

	/** Validate a player update channel. */
	private function sanitize_update_channel( $value ) {
		$channel = sanitize_key( (string) $value );
		if ( ! in_array( $channel, [ 'stable', 'beta', 'off' ], true ) ) {
			return new \WP_Error( 'rondo_player_update_channel_invalid', __( 'Kies stabiele updates, beta-updates of updates uit.', 'rondo' ), [ 'status' => 400 ] );
		}
		return $channel;
	}

	/** Allow dedicated content managers and sponsor managers into Club TV. */
	public function check_content_permission(): bool {
		return \Rondo\Config\FeatureToggles::can_access( 'narrowcasting' )
			&& ( current_user_can( 'manage_options' ) || current_user_can( 'narrowcasting' ) || current_user_can( 'sponsorbeheer' ) );
	}

	/** Playlist structure and overrides are outside the sponsor-only role. */
	public function check_playlist_permission(): bool {
		return \Rondo\Config\FeatureToggles::can_access( 'narrowcasting' )
			&& ( current_user_can( 'manage_options' ) || current_user_can( 'narrowcasting' ) );
	}

	/** Club TV administrator access, including the feature state. */
	public function check_narrowcasting_admin_permission(): bool {
		return \Rondo\Config\FeatureToggles::can_access( 'narrowcasting' ) && $this->check_admin_permission();
	}

	/** Browser presentation access, including the feature state. */
	public function check_presentation_user_permission(): bool {
		return \Rondo\Config\FeatureToggles::can_access( 'narrowcasting' ) && $this->check_user_approved();
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

	/** @return string|\WP_Error */
	private function generate_presentation_code() {
		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );
			if ( get_transient( $this->presentation_code_key( $code ) ) === false ) {
				return $code;
			}
		}

		return new \WP_Error( 'rondo_presentation_code_unavailable', __( 'Er kon geen vrije schermcode worden gemaakt. Probeer het opnieuw.', 'rondo' ), [ 'status' => 503 ] );
	}

	private function normalize_presentation_code( $value ): string {
		$code = preg_replace( '/\D/', '', (string) $value );
		return strlen( $code ) === 6 ? $code : '';
	}

	/** Limit code guessing per signed-in user. */
	private function consume_presentation_join_rate_limit() {
		$key   = 'rondo_present_join_' . get_current_user_id();
		$count = (int) get_transient( $key );
		if ( $count >= self::PRESENTATION_JOIN_RATE ) {
			return new \WP_Error( 'rondo_presentation_rate_limited', __( 'Te veel schermcodes geprobeerd. Probeer het over een minuut opnieuw.', 'rondo' ), [ 'status' => 429 ] );
		}
		set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
		return true;
	}

	/** @return array|\WP_Error */
	private function authenticate_presentation_session( $request ) {
		$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
		$token      = trim( (string) $request->get_header( 'x-rondo-presentation-token' ) );
		if ( ! wp_is_uuid( $session_id ) || $token === '' || strlen( $token ) > 200 ) {
			return new \WP_Error( 'rondo_presentation_unauthorized', __( 'Geldige presentatieauthenticatie ontbreekt.', 'rondo' ), [ 'status' => 401 ] );
		}

		$session = get_transient( $this->presentation_session_key( $session_id ) );
		if ( ! is_array( $session ) ) {
			return new \WP_Error( 'rondo_presentation_expired', __( 'Deze presentatiesessie is verlopen.', 'rondo' ), [ 'status' => 410 ] );
		}

		if ( (int) ( $session['booking_id'] ?? 0 ) > 0 ) {
			if ( ! \rondo_rooms_enabled() ) {
				$this->delete_presentation_session( $session_id );
				return new \WP_Error( 'rondo_presentation_expired', __( 'Deze presentatiesessie is verlopen.', 'rondo' ), [ 'status' => 410 ] );
			}
			$entitlement = ( new BookingService() )->presentation_entitlement_for_display(
				(int) $session['display_id'],
				(int) ( $session['user_id'] ?? 0 )
			);
			if ( ! is_array( $entitlement ) || ! $entitlement['allowed'] || (int) ( $entitlement['booking_id'] ?? 0 ) !== (int) $session['booking_id'] ) {
				$this->delete_presentation_session( $session_id );
				return new \WP_Error( 'rondo_presentation_expired', __( 'Deze presentatiesessie is verlopen.', 'rondo' ), [ 'status' => 410 ] );
			}
			$session['expires_at'] = min(
				time() + self::PRESENTATION_SESSION_TTL,
				( new DateTimeImmutable( $entitlement['ends_at'] ) )->getTimestamp()
			);
		}
		if ( (int) $session['expires_at'] < time() ) {
			$this->delete_presentation_session( $session_id );
			return new \WP_Error( 'rondo_presentation_expired', __( 'Deze presentatiesessie is verlopen.', 'rondo' ), [ 'status' => 410 ] );
		}

		$token_hash = $this->hash_device_token( $token );
		$role       = '';
		if ( hash_equals( (string) $session['receiver_token_hash'], $token_hash ) ) {
			$role = 'receiver';
		} elseif ( $session['sender_token_hash'] !== '' && hash_equals( (string) $session['sender_token_hash'], $token_hash ) ) {
			$role = 'sender';
		}
		if ( $role === '' ) {
			return new \WP_Error( 'rondo_presentation_unauthorized', __( 'De presentatiecredential is ongeldig.', 'rondo' ), [ 'status' => 401 ] );
		}
		if ( $role === 'sender' && ! \Rondo\Config\FeatureToggles::can_access( 'narrowcasting', (int) ( $session['user_id'] ?? 0 ) ) ) {
			return new \WP_Error( 'rondo_presentation_unauthorized', __( 'Je hebt geen toegang meer tot deze presentatie.', 'rondo' ), [ 'status' => 403 ] );
		}

		return [
			'role'    => $role,
			'session' => $session,
		];
	}

	/** @return array|\WP_Error */
	private function sanitize_presentation_signal( $payload, string $role ) {
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'rondo_presentation_signal_invalid', __( 'Het presentatiesignaal is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
		}

		$clean       = [
			'description' => null,
			'candidates'  => [],
			'hangup'      => rest_sanitize_boolean( $payload['hangup'] ?? false ),
		];
		$description = $payload['description'] ?? null;
		if ( $description !== null ) {
			$expected_type = $role === 'sender' ? 'offer' : 'answer';
			if ( ! is_array( $description ) || ( $description['type'] ?? '' ) !== $expected_type ) {
				return new \WP_Error( 'rondo_presentation_description_invalid', __( 'De WebRTC-beschrijving is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
			}
			$sdp = wp_check_invalid_utf8( (string) ( $description['sdp'] ?? '' ) );
			if ( $sdp === '' || strlen( $sdp ) > 65535 ) {
				return new \WP_Error( 'rondo_presentation_sdp_invalid', __( 'De WebRTC-beschrijving is te groot of leeg.', 'rondo' ), [ 'status' => 400 ] );
			}
			$clean['description'] = [
				'type' => $expected_type,
				'sdp'  => $sdp,
			];
		}

		$candidates = $payload['candidates'] ?? [];
		if ( ! is_array( $candidates ) || count( $candidates ) > 128 ) {
			return new \WP_Error( 'rondo_presentation_candidates_invalid', __( 'De WebRTC-kandidaten zijn ongeldig.', 'rondo' ), [ 'status' => 400 ] );
		}
		foreach ( $candidates as $candidate ) {
			if ( ! is_array( $candidate ) ) {
				return new \WP_Error( 'rondo_presentation_candidate_invalid', __( 'Een WebRTC-kandidaat is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
			}
			$value = sanitize_text_field( (string) ( $candidate['candidate'] ?? '' ) );
			if ( $value === '' || strlen( $value ) > 2048 ) {
				return new \WP_Error( 'rondo_presentation_candidate_invalid', __( 'Een WebRTC-kandidaat is te groot of leeg.', 'rondo' ), [ 'status' => 400 ] );
			}
			$clean['candidates'][] = [
				'candidate'        => $value,
				'sdpMid'           => substr( sanitize_text_field( (string) ( $candidate['sdpMid'] ?? '' ) ), 0, 64 ),
				'sdpMLineIndex'    => absint( $candidate['sdpMLineIndex'] ?? 0 ),
				'usernameFragment' => substr( sanitize_text_field( (string) ( $candidate['usernameFragment'] ?? '' ) ), 0, 256 ),
			];
		}

		return $clean;
	}

	private function refresh_presentation_session( array $session ): void {
		if ( empty( $session['booking_id'] ) ) {
			$session['expires_at'] = time() + self::PRESENTATION_SESSION_TTL;
		}
		$ttl = max( 1, min( self::PRESENTATION_SESSION_TTL, (int) $session['expires_at'] - time() ) );
		set_transient( $this->presentation_session_key( $session['id'] ), $session, $ttl );
		set_transient( $this->presentation_display_key( (int) $session['display_id'] ), $session['id'], $ttl );
	}

	/** Stop any current WebRTC session when a booking is cancelled or blocked. */
	public function stop_display_presentation( int $display_id ): void {
		$session_id = get_transient( $this->presentation_display_key( $display_id ) );
		if ( is_string( $session_id ) && $session_id !== '' ) {
			$this->delete_presentation_session( $session_id );
		}
	}

	private function delete_presentation_session( string $session_id ): void {
		$session = get_transient( $this->presentation_session_key( $session_id ) );
		if ( is_array( $session ) ) {
			delete_transient( $this->presentation_code_key( (string) $session['code'] ) );
			delete_transient( $this->presentation_display_key( (int) $session['display_id'] ) );
		}
		delete_transient( $this->presentation_signal_key( $session_id, 'sender' ) );
		delete_transient( $this->presentation_signal_key( $session_id, 'receiver' ) );
		delete_transient( $this->presentation_session_key( $session_id ) );
	}

	private function presentation_session_key( string $session_id ): string {
		return 'rondo_present_session_' . $session_id;
	}

	private function presentation_code_key( string $code ): string {
		return 'rondo_present_code_' . substr( hash( 'sha256', $code ), 0, 32 );
	}

	private function presentation_display_key( int $display_id ): string {
		return 'rondo_present_display_' . $display_id;
	}

	private function presentation_signal_key( string $session_id, string $role ): string {
		return 'rondo_present_signal_' . $session_id . '_' . $role;
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
