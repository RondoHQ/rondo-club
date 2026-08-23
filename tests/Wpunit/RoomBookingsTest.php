<?php

namespace Tests\Wpunit;

use Rondo\Core\VolunteerStatus;
use Rondo\Core\PostTypes;
use Rondo\Fields\Fields;
use Rondo\REST\Narrowcasting;
use Rondo\REST\Rooms;
use Rondo\Rooms\BookingEligibility;
use Rondo\Rooms\BookingService;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** End-to-end coverage for room eligibility, privacy, conflicts, and presentation access. */
class RoomBookingsTest extends RondoTestCase {

	private BookingService $service;

	protected function set_up(): void {
		parent::set_up();
		$this->service = new BookingService();
	}

	public function test_commission_volunteer_can_book_and_availability_stays_private(): void {
		$holder_id    = $this->createRondoUser( [ 'display_name' => 'Commissielid' ] );
		$commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Grote Clubactie',
			]
		);
		$this->link_user_with_roles( $holder_id, [ $this->position( $commissie_id, 'commissie', 'Voorzitter' ) ] );
		$room_id = $this->create_room();
		$times   = $this->future_times();

		$booking = $this->service->create_booking(
			[
				'room_id'              => $room_id,
				'start_datetime'       => $times['start'],
				'end_datetime'         => $times['end'],
				'purpose'              => 'Overleg lotenverkoop',
				'booking_context_type' => 'commissie',
				'commissie_id'         => $commissie_id,
			],
			$holder_id
		);

		$this->assertIsArray( $booking );
		$this->assertSame( 'Grote Clubactie', $booking['context_label'] );
		$this->assertSame( 'Commissielid', $booking['holder_name'] );
		$availability = $this->service->availability(
			new \DateTimeImmutable( $times['start'] ),
			( new \DateTimeImmutable( $times['end'] ) )->modify( '+1 hour' )
		);
		$this->assertCount( 1, $availability );
		$this->assertArrayNotHasKey( 'holder_name', $availability[0] );
		$this->assertArrayNotHasKey( 'purpose', $availability[0] );
		$this->assertSame( 'created', $this->service->activity( $booking['id'] )[0]['action'] );
	}

	public function test_accommodation_role_can_operate_but_cannot_permanently_delete_bookings(): void {
		$manager_id   = self::factory()->user->create( [ 'role' => 'rondo_accommodatiebeheerder' ] );
		$capabilities = PostTypes::capability_map( BookingService::BOOKING_POST_TYPE );

		$this->assertTrue( user_can( $manager_id, 'accommodatiebeheer' ) );
		$this->assertTrue( user_can( $manager_id, $capabilities['edit_posts'] ) );
		$this->assertFalse( user_can( $manager_id, $capabilities['delete_posts'] ) );
	}

	public function test_player_position_does_not_qualify_and_manager_cannot_bypass_it(): void {
		$holder_id = $this->createRondoUser();
		$team_id   = $this->createOrganization( [ 'post_title' => 'AWC O12-1' ] );
		$this->link_user_with_roles( $holder_id, [ $this->position( $team_id, 'team', VolunteerStatus::get_player_roles()[0] ) ] );
		$this->assertSame( [], BookingEligibility::for_user( $holder_id ) );

		$times  = $this->future_times();
		$result = $this->service->create_booking(
			[
				'room_id'              => $this->create_room(),
				'start_datetime'       => $times['start'],
				'end_datetime'         => $times['end'],
				'purpose'              => 'Onbevoegd overleg',
				'holder_user_id'       => $holder_id,
				'booking_context_type' => 'age_group',
				'age_group_key'        => 'O12',
			],
			self::factory()->user->create( [ 'role' => 'administrator' ] ),
			true
		);

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_room_context_forbidden', $result->get_error_code() );
	}

	public function test_team_staff_gets_year_group_from_current_player_roster(): void {
		$holder_id = $this->createRondoUser();
		$team_id   = $this->createOrganization( [ 'post_title' => 'Naam zonder jaarlaag' ] );
		$this->link_user_with_roles( $holder_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$player_id = $this->createPerson( [ 'post_title' => 'Speler' ], [ 'leeftijdsgroep' => 'Onder 12' ] );
		Fields::update_for_post( $player_id, 'work_history', [ $this->position( $team_id, 'team', VolunteerStatus::get_player_roles()[0] ) ] );

		$contexts = BookingEligibility::for_user( $holder_id );
		$this->assertCount( 1, $contexts );
		$this->assertSame( 'O12', $contexts[0]['age_group_key'] );
		$this->assertSame( 'O12 jaarlaagoverleg', $contexts[0]['label'] );
	}

	public function test_overlapping_booking_is_rejected_but_management_block_is_allowed_without_holder(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$room_id  = $this->create_room();
		$times    = $this->future_times();
		$block    = [
			'room_id'        => $room_id,
			'start_datetime' => $times['start'],
			'end_datetime'   => $times['end'],
			'purpose'        => 'Onderhoud',
			'booking_type'   => 'management_block',
		];
		$created  = $this->service->create_booking( $block, $admin_id, true );
		$this->assertIsArray( $created );
		$this->assertSame( 0, $created['holder_user_id'] );

		$conflict = $this->service->create_booking( $block + [ 'purpose' => 'Tweede blokkade' ], $admin_id, true );
		$this->assertWPError( $conflict );
		$this->assertSame( 'rondo_room_conflict', $conflict->get_error_code() );
	}

	public function test_active_booking_extends_only_after_the_following_booking_is_cancelled(): void {
		$admin_id  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$room_id   = $this->create_room();
		$current   = current_datetime();
		$start     = $current->setTime( (int) $current->format( 'H' ), (int) floor( (int) $current->format( 'i' ) / 15 ) * 15 );
		$end       = $start->modify( '+30 minutes' );
		$active    = $this->service->create_booking(
			[
				'room_id'        => $room_id,
				'start_datetime' => $start->modify( '-15 minutes' )->format( DATE_RFC3339 ),
				'end_datetime'   => $end->format( DATE_RFC3339 ),
				'purpose'        => 'Actieve blokkade',
				'booking_type'   => 'management_block',
			],
			$admin_id,
			true
		);
		$following = $this->service->create_booking(
			[
				'room_id'        => $room_id,
				'start_datetime' => $end->format( DATE_RFC3339 ),
				'end_datetime'   => $end->modify( '+30 minutes' )->format( DATE_RFC3339 ),
				'purpose'        => 'Volgende blokkade',
				'booking_type'   => 'management_block',
			],
			$admin_id,
			true
		);

		$this->assertIsArray( $active );
		$this->assertIsArray( $following );
		$blocked = $this->service->extend_booking( $active['id'], $admin_id );
		$this->assertWPError( $blocked );
		$this->assertSame( 'rondo_room_conflict', $blocked->get_error_code() );
		$this->assertIsArray( $this->service->cancel_booking( $following['id'], $admin_id, 'Ruimte vrijgegeven', true ) );
		$extended = $this->service->extend_booking( $active['id'], $admin_id );
		$this->assertIsArray( $extended );
		$this->assertNotEmpty( $extended['extended_until'] );
	}

	public function test_controlled_display_only_allows_booking_holder_to_join(): void {
		$server       = $this->bootRestControllers( [ Narrowcasting::class ] );
		$device_token = 'room-presentation-device-token';
		$display_id   = self::factory()->post->create(
			[
				'post_type'   => 'rondo_display',
				'post_status' => 'publish',
				'post_title'  => 'Scherm bestuurskamer',
			]
		);
		Fields::update_many_for_post(
			$display_id,
			[
				'device_id'            => 'rondo-pi-room-001',
				'device_secret_hash'   => hash_hmac( 'sha256', $device_token, wp_salt( 'auth' ) ),
				'pairing_status'       => 'paired',
				'presentation_enabled' => true,
			]
		);
		$room_id      = $this->create_room( $display_id );
		$holder_id    = $this->createRondoUser();
		$commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Bestuur',
			]
		);
		$this->link_user_with_roles( $holder_id, [ $this->position( $commissie_id, 'commissie', 'Bestuurslid' ) ] );
		$current = current_datetime();
		$now     = $current->setTime( (int) $current->format( 'H' ), (int) floor( (int) $current->format( 'i' ) / 15 ) * 15 );
		$this->assertIsArray(
			$this->service->create_booking(
				[
					'room_id'              => $room_id,
					'start_datetime'       => $now->modify( '-15 minutes' )->format( DATE_RFC3339 ),
					'end_datetime'         => $now->modify( '+45 minutes' )->format( DATE_RFC3339 ),
					'purpose'              => 'Bestuursoverleg',
					'holder_user_id'       => $holder_id,
					'booking_context_type' => 'commissie',
					'commissie_id'         => $commissie_id,
				],
				self::factory()->user->create( [ 'role' => 'administrator' ] ),
				true
			)
		);

		wp_set_current_user( 0 );
		$session = $this->dispatch(
			$server,
			'POST',
			'/rondo/v1/narrowcasting/devices/me/presentation/session',
			[],
			[ 'X-Rondo-Device-Token' => $device_token ]
		);
		$this->assertSame( 200, $session->get_status() );
		$this->assertSame( 'Bestuurskamer', $session->get_data()['room_name'] );

		wp_set_current_user( $this->createRondoUser() );
		$denied = $this->dispatch( $server, 'POST', '/rondo/v1/narrowcasting/presentation/join', [ 'code' => $session->get_data()['code'] ] );
		$this->assertSame( 403, $denied->get_status() );

		wp_set_current_user( $holder_id );
		$joined = $this->dispatch( $server, 'POST', '/rondo/v1/narrowcasting/presentation/join', [ 'code' => $session->get_data()['code'] ] );
		$this->assertSame( 200, $joined->get_status() );
		$this->assertSame( 'Bestuurskamer', $joined->get_data()['room_name'] );
		$this->assertNotEmpty( $joined->get_data()['entitlement_ends_at'] );
	}

	public function test_rest_routes_keep_booking_details_private_and_manager_routes_capability_gated(): void {
		$server       = $this->bootRestControllers( [ Rooms::class ] );
		$holder_id    = $this->createRondoUser();
		$commissie_id = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => 'Toernooicommissie',
			]
		);
		$this->link_user_with_roles( $holder_id, [ $this->position( $commissie_id, 'commissie', 'Coördinator' ) ] );
		$times   = $this->future_times();
		$booking = $this->service->create_booking(
			[
				'room_id'              => $this->create_room(),
				'start_datetime'       => $times['start'],
				'end_datetime'         => $times['end'],
				'purpose'              => 'Toernooiplanning',
				'private_notes'        => 'Alleen voor de organisatie',
				'booking_context_type' => 'commissie',
				'commissie_id'         => $commissie_id,
			],
			$holder_id
		);
		$this->assertIsArray( $booking );

		wp_set_current_user( $this->createRondoUser() );
		$availability = $this->dispatch(
			$server,
			'GET',
			'/rondo/v1/rooms/availability',
			[
				'start' => $times['start'],
				'end'   => ( new \DateTimeImmutable( $times['end'] ) )->modify( '+1 hour' )->format( DATE_RFC3339 ),
			]
		);
		$this->assertSame( 200, $availability->get_status() );
		$this->assertArrayNotHasKey( 'holder_name', $availability->get_data()[0] );
		$this->assertSame( 403, $this->dispatch( $server, 'GET', '/rondo/v1/rooms/bookings/' . $booking['id'] )->get_status() );
		$this->assertSame(
			403,
			$this->dispatch(
			$server,
			'GET',
			'/rondo/v1/rooms/manage/bookings',
			[
				'start' => $times['start'],
				'end'   => $times['end'],
			]
			)->get_status()
			);

		wp_set_current_user( $holder_id );
		$own = $this->dispatch( $server, 'GET', '/rondo/v1/rooms/bookings/' . $booking['id'] );
		$this->assertSame( 200, $own->get_status() );
		$this->assertSame( 'Alleen voor de organisatie', $own->get_data()['private_notes'] );
	}

	private function create_room( int $display_id = 0 ): int {
		$result = $this->service->save_room(
			[
				'name'                    => 'Bestuurskamer',
				'display_id'              => $display_id,
				'presentation_controlled' => $display_id > 0,
				'opening_hours'           => array_map(
					static fn( int $day ): array => [
						'day'        => $day,
						'start_time' => '00:00',
						'end_time'   => '23:59',
					],
					range( 1, 7 )
				),
			],
		);
		$this->assertIsArray( $result );
		return $result['id'];
	}

	private function link_user_with_roles( int $user_id, array $roles ): int {
		$person_id = $this->createPerson( [ 'post_title' => get_userdata( $user_id )->display_name ] );
		Fields::update_for_post( $person_id, 'work_history', $roles );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		return $person_id;
	}

	private function position( int $entity_id, string $entity_type, string $job_title ): array {
		return [
			'team'        => $entity_id,
			'entity_type' => $entity_type,
			'job_title'   => $job_title,
			'start_date'  => '2020-01-01',
			'end_date'    => '',
			'is_current'  => true,
		];
	}

	private function future_times(): array {
		$start = current_datetime()->modify( '+1 day' )->setTime( 10, 0 );
		return [
			'start' => $start->format( DATE_RFC3339 ),
			'end'   => $start->modify( '+1 hour' )->format( DATE_RFC3339 ),
		];
	}

	private function dispatch( \WP_REST_Server $server, string $method, string $route, array $params = [], array $headers = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( $method === 'GET' ) {
			$request->set_query_params( $params );
		} else {
			$request->set_body_params( $params );
		}
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}
		return $server->dispatch( $request );
	}
}
