<?php
/**
 * REST API for rooms, bookings, management, and iCalendar downloads.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use DateInterval;
use DateTimeImmutable;
use Rondo\Rooms\BookingEligibility;
use Rondo\Rooms\BookingService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Rooms extends Base {

	private BookingService $service;

	public function __construct() {
		parent::__construct();
		$this->service = new BookingService();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $this, 'serve_calendar' ], 10, 4 );
	}

	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/rooms',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_rooms' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/availability',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_availability' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/booking-contexts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_booking_contexts' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/bookings/mine',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_my_bookings' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/bookings',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_booking' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/bookings/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_booking' ],
					'permission_callback' => [ $this, 'check_booking_read' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_booking' ],
					'permission_callback' => [ $this, 'check_booking_write' ],
				],
				'args' => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/bookings/(?P<id>\d+)/cancel',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel_booking' ],
				'permission_callback' => [ $this, 'check_booking_write' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/bookings/(?P<id>\d+)/extend',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'extend_booking' ],
				'permission_callback' => [ $this, 'check_booking_write' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/bookings/(?P<id>\d+)/calendar',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_calendar' ],
				'permission_callback' => [ $this, 'check_booking_read' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/rooms/manage/bookings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_managed_bookings' ],
					'permission_callback' => [ $this, 'check_manager_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_managed_booking' ],
					'permission_callback' => [ $this, 'check_manager_permission' ],
				],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/manage/booking-contexts',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_managed_booking_contexts' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/manage/bookings/(?P<id>\d+)/presentation',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'set_presentation_override' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/bookings/(?P<id>\d+)/activity',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_activity' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/manage/config',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_management_config' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/manage/rooms',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_room' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/rooms/manage/rooms/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_room' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [ 'id' => [ 'sanitize_callback' => 'absint' ] ],
			]
		);
	}

	public function check_manager_permission(): bool {
		return current_user_can( 'accommodatiebeheer' ) || current_user_can( 'manage_options' );
	}

	public function check_booking_read( $request ): bool {
		return is_user_logged_in() && $this->service->user_can_read_booking( absint( $request->get_param( 'id' ) ), get_current_user_id() );
	}

	public function check_booking_write( $request ): bool {
		return $this->check_manager_permission()
			|| ( is_user_logged_in() && $this->service->user_is_holder( absint( $request->get_param( 'id' ) ), get_current_user_id() ) );
	}

	public function get_rooms( $request ) {
		$include_archived = current_user_can( 'manage_options' ) && rest_sanitize_boolean( $request->get_param( 'include_archived' ) );
		return rest_ensure_response( $this->service->rooms( $include_archived ) );
	}

	public function get_availability( $request ) {
		$range = $this->parse_range( $request, 31 );
		return is_wp_error( $range ) ? $range : rest_ensure_response( $this->service->availability( $range['start'], $range['end'] ) );
	}

	public function get_booking_contexts() {
		return rest_ensure_response( BookingEligibility::for_user( get_current_user_id() ) );
	}

	public function get_managed_booking_contexts( $request ) {
		$user_id = absint( $request->get_param( 'holder_user_id' ) );
		if ( ! get_userdata( $user_id ) ) {
			return new \WP_Error( 'rondo_room_holder_invalid', __( 'Kies een geldige reserveringshouder.', 'rondo' ), [ 'status' => 400 ] );
		}
		return rest_ensure_response( BookingEligibility::for_user( $user_id ) );
	}

	public function get_my_bookings() {
		$now      = current_datetime();
		$bookings = $this->service->bookings_between( $now->sub( new DateInterval( 'P10Y' ) ), $now->add( new DateInterval( 'P10Y' ) ), get_current_user_id() );
		return rest_ensure_response( array_reverse( $bookings ) );
	}

	public function create_booking( $request ) {
		$result = $this->service->create_booking( $request->get_json_params() ?: [], get_current_user_id(), false );
		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result, 201 );
	}

	public function create_managed_booking( $request ) {
		$result = $this->service->create_booking( $request->get_json_params() ?: [], get_current_user_id(), true );
		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result, 201 );
	}

	public function get_booking( $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$manager = $this->check_manager_permission();
		$full    = $manager || $this->service->user_is_holder( $id, get_current_user_id() );
		return rest_ensure_response( $this->service->format_booking( $id, $full ) );
	}

	public function update_booking( $request ) {
		$result = $this->service->update_booking(
			absint( $request->get_param( 'id' ) ),
			$request->get_json_params() ?: [],
			get_current_user_id(),
			$this->check_manager_permission()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function cancel_booking( $request ) {
		$result = $this->service->cancel_booking(
			absint( $request->get_param( 'id' ) ),
			get_current_user_id(),
			(string) $request->get_param( 'reason' ),
			$this->check_manager_permission()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function extend_booking( $request ) {
		$result = $this->service->extend_booking( absint( $request->get_param( 'id' ) ), get_current_user_id() );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function get_managed_bookings( $request ) {
		$range = $this->parse_range( $request, 31 );
		return is_wp_error( $range ) ? $range : rest_ensure_response( $this->service->bookings_between( $range['start'], $range['end'] ) );
	}

	public function get_activity( $request ) {
		$id = absint( $request->get_param( 'id' ) );
		if ( get_post_type( $id ) !== BookingService::BOOKING_POST_TYPE ) {
			return new \WP_Error( 'rondo_room_booking_not_found', __( 'Reservering niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		return rest_ensure_response( $this->service->activity( $id ) );
	}

	public function set_presentation_override( $request ) {
		$result = $this->service->set_presentation_override(
			absint( $request->get_param( 'id' ) ),
			sanitize_key( (string) $request->get_param( 'action' ) ),
			get_current_user_id()
		);
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function get_management_config() {
		$display_ids = get_posts(
			[
				'post_type'        => 'rondo_display',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
		);
		return rest_ensure_response(
			[
				'displays' => array_map(
					static fn( int $id ): array => [
						'id'   => $id,
						'name' => get_the_title( $id ),
					],
					array_map( 'intval', $display_ids )
				),
			]
		);
	}

	public function create_room( $request ) {
		$result = $this->service->save_room( $request->get_json_params() ?: [] );
		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result, 201 );
	}

	public function update_room( $request ) {
		$result = $this->service->save_room( $request->get_json_params() ?: [], absint( $request->get_param( 'id' ) ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
	}

	public function get_calendar( $request ) {
		$booking = $this->service->format_booking( absint( $request->get_param( 'id' ) ), true );
		$uid     = sprintf( 'room-booking-%d@%s', $booking['id'], wp_parse_url( home_url(), PHP_URL_HOST ) );
		$ics     = implode(
			"\r\n",
			[
				'BEGIN:VCALENDAR',
				'VERSION:2.0',
				'PRODID:-//Rondo Club//Ruimtereservering//NL',
				'CALSCALE:GREGORIAN',
				'METHOD:PUBLISH',
				'BEGIN:VEVENT',
				'UID:' . $this->escape_ics( $uid ),
				'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ),
				'DTSTART:' . $this->ics_datetime( $booking['start_datetime'] ),
				'DTEND:' . $this->ics_datetime( $booking['effective_end_datetime'] ),
				'SUMMARY:' . $this->escape_ics( $booking['purpose'] . ' · ' . $booking['context_label'] ),
				'LOCATION:' . $this->escape_ics( $booking['room_name'] ),
				'END:VEVENT',
				'END:VCALENDAR',
				'',
			]
		);
		return rest_ensure_response(
			[
				'__ics'    => $ics,
				'filename' => 'rondo-reservering-' . $booking['id'] . '.ics',
			]
			);
	}

	public function serve_calendar( $served, $result, $request, $server ) {
		unset( $server );
		if ( ! preg_match( '#^/rondo/v1/rooms/bookings/\d+/calendar$#', $request->get_route() ) || ! $result instanceof \WP_REST_Response || $result->get_status() >= 400 ) {
			return $served;
		}
		$data = $result->get_data();
		if ( ! is_array( $data ) || ! isset( $data['__ics'] ) ) {
			return $served;
		}
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $data['filename'] ?? 'reservering.ics' ) . '"' );
		header( 'Cache-Control: private, no-store' );
		echo $data['__ics']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- validated iCalendar serialization.
		return true;
	}

	private function parse_range( $request, int $maximum_days ) {
		$start = $this->service->parse_api_datetime( $request->get_param( 'start' ) );
		$end   = $this->service->parse_api_datetime( $request->get_param( 'end' ) );
		if ( is_wp_error( $start ) || is_wp_error( $end ) ) {
			return is_wp_error( $start ) ? $start : $end;
		}
		if ( $end <= $start || ( $end->getTimestamp() - $start->getTimestamp() ) > $maximum_days * DAY_IN_SECONDS ) {
			return new \WP_Error( 'rondo_room_range_invalid', __( 'Kies een geldige, kortere periode.', 'rondo' ), [ 'status' => 400 ] );
		}
		return [
			'start' => $start,
			'end'   => $end,
		];
	}

	private function ics_datetime( string $value ): string {
		return ( new DateTimeImmutable( $value ) )->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' );
	}

	private function escape_ics( string $value ): string {
		return str_replace( [ '\\', ';', ',', "\r", "\n" ], [ '\\\\', '\\;', '\\,', '', '\\n' ], $value );
	}
}
