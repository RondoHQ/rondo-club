<?php

namespace Tests\Wpunit;

use Rondo\Access\AdmissionService;
use Rondo\Fields\Fields;
use Rondo\Passes\GuestPassService;
use Rondo\Passes\MembershipPassQr;
use Rondo\REST\AccessEvents;
use Rondo\REST\GuestPasses;
use Tests\Support\RondoTestCase;

/** Tests reusable AWC 1 guest slots and their match quota. */
class GuestPassTest extends RondoTestCase {

	public function test_player_can_claim_two_slots_and_each_counts_once_per_match(): void {
		[ $user_id, $person_id ] = $this->create_awc_one_player();
		wp_set_current_user( $user_id );

		$guest_passes = new GuestPassService();
		$slot_one     = $guest_passes->ensure_slot( $person_id, 1 );
		$slot_two     = $guest_passes->ensure_slot( $person_id, 2 );
		$this->assertNotWPError( $slot_one );
		$this->assertNotWPError( $slot_two );
		$this->assertWPError( $guest_passes->ensure_slot( $person_id, 3 ) );

		$slot_one = $guest_passes->claim( $this->share_token( $slot_one['share_url'] ), 'Anna Voorbeeld' );
		$slot_two = $guest_passes->claim( $this->share_token( $slot_two['share_url'] ), 'Bram Voorbeeld' );
		$this->assertSame( 'active', $slot_one['status'] );
		$this->assertSame( 'Anna Voorbeeld', $slot_one['guest_name'] );

		$event = ( new AdmissionService( false ) )->select_match( $this->home_match( 'guest-match-1', 'AWC 1' ) );
		$this->assertNotWPError( $event );
		$controller = new AccessEvents();

		$first = $this->scan( $controller, $event['id'], $this->issue_guest_token( $slot_one['id'] ) );
		$this->assertTrue( $first['valid'] );
		$this->assertTrue( $first['admission']['counted'] );
		$this->assertSame( 'guest', $first['pass_type'] );
		$this->assertSame( 'Anna Voorbeeld', $first['guest']['name'] );
		$this->assertSame( $person_id, $first['guest']['host_person_id'] );

		$duplicate = $this->scan( $controller, $event['id'], $this->issue_guest_token( $slot_one['id'] ) );
		$this->assertTrue( $duplicate['admission']['duplicate'] );
		$this->assertSame( 1, $duplicate['stats']['counts']['guest'] );

		$second = $this->scan( $controller, $event['id'], $this->issue_guest_token( $slot_two['id'] ) );
		$this->assertTrue( $second['admission']['counted'] );
		$this->assertSame( 2, $second['stats']['counts']['guest'] );

		$old_token   = $this->issue_guest_token( $slot_one['id'] );
		$replacement = $guest_passes->replace_slot( $person_id, 1 );
		$this->assertSame( 'unclaimed', $replacement['status'] );
		$revoked = $this->scan( $controller, $event['id'], $old_token );
		$this->assertFalse( $revoked['valid'] );
		$this->assertSame( 'revoked', $revoked['reason'] );

		$replacement = $guest_passes->claim( $this->share_token( $replacement['share_url'] ), 'Cato Voorbeeld' );
		$still_used  = $this->scan( $controller, $event['id'], $this->issue_guest_token( $replacement['id'] ) );
		$this->assertTrue( $still_used['admission']['duplicate'] );
		$this->assertSame( 2, $still_used['stats']['counts']['guest'] );
	}

	public function test_guest_pass_only_works_for_awc_one_home_match(): void {
		[ $user_id, $person_id ] = $this->create_awc_one_player();
		wp_set_current_user( $user_id );
		$service = new GuestPassService();
		$slot    = $service->ensure_slot( $person_id, 1 );
		$slot    = $service->claim( $this->share_token( $slot['share_url'] ), 'Dina Voorbeeld' );
		$token   = $this->issue_guest_token( $slot['id'] );

		$event = ( new AdmissionService( false ) )->select_match( $this->home_match( 'other-home-match', 'AWC O23-1' ) );
		$this->assertNotWPError( $event );
		$result = $this->scan( new AccessEvents(), $event['id'], $token );
		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'wrong_match', $result['reason'] );
		$this->assertSame( 0, $result['stats']['counts']['guest'] );
	}

	public function test_non_player_does_not_receive_guest_slots(): void {
		$user_id   = $this->createRondoUser();
		$person_id = $this->createPerson(
			[ 'post_title' => 'Gewoon Lid' ],
			[
				'type_lid'   => 'Bondslid',
				'first_name' => 'Gewoon',
				'last_name'  => 'Lid',
			]
		);
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$server   = $this->bootRestControllers( [ GuestPasses::class ] );
		$response = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/guest-passes/me' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['eligible'] );
		$this->assertSame( [], $response->get_data()['slots'] );
	}

	public function test_guest_identity_is_removed_after_thirty_days_but_count_remains(): void {
		[ $user_id, $person_id ] = $this->create_awc_one_player();
		wp_set_current_user( $user_id );
		$service = new GuestPassService();
		$slot    = $service->ensure_slot( $person_id, 1 );
		$slot    = $service->claim( $this->share_token( $slot['share_url'] ), 'Eva Voorbeeld' );

		$admissions = new AdmissionService( false );
		$event      = $admissions->select_match( $this->home_match( 'cleanup-match', 'AWC 1' ) );
		$record     = $admissions->record_guest_admission( $event['id'], $slot['id'], $person_id, 1, 'Eva Voorbeeld' );
		$this->assertNotWPError( $record );
		$this->assertSame( 'Eva Voorbeeld', Fields::get_for_post( $record['id'], 'guest_name' ) );

		$old = gmdate( 'Y-m-d H:i:s', time() - ( 31 * DAY_IN_SECONDS ) );
		wp_update_post(
			[
				'ID'            => $record['id'],
				'post_date'     => get_date_from_gmt( $old ),
				'post_date_gmt' => $old,
			]
		);
		$admissions->cleanup_fingerprints();

		$this->assertSame( '', Fields::get_for_post( $record['id'], 'guest_name' ) );
		$this->assertSame( 0, (int) Fields::get_for_post( $record['id'], 'host_person_id' ) );
		$this->assertSame( 'guest', Fields::get_for_post( $record['id'], 'pass_type' ) );
		$this->assertSame( 1, $admissions->get_stats( $event['id'] )['counts']['guest'] );
	}

	/** @return array{0:int,1:int} */
	private function create_awc_one_player(): array {
		$user_id = $this->createRondoUser();
		$team_id = $this->createOrganization( [ 'post_title' => 'AWC 1' ] );
		$person  = $this->createPerson(
			[ 'post_title' => 'AWC 1 Speler' ],
			[
				'type_lid'     => 'Bondslid',
				'first_name'   => 'AWC',
				'last_name'    => 'Speler',
				'work_history' => [
					[
						'team'        => $team_id,
						'entity_type' => 'team',
						'job_title'   => 'Speler',
						'is_current'  => true,
					],
				],
			]
		);
		update_user_meta( $user_id, 'rondo_linked_person_id', $person );
		return [ $user_id, $person ];
	}

	private function issue_guest_token( int $pass_id ): string {
		$result = ( new MembershipPassQr() )->issue_for_guest( $pass_id );
		$this->assertNotWPError( $result );
		return $result['token'];
	}

	private function scan( AccessEvents $controller, int $event_id, string $token ): array {
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/access-events/' . $event_id . '/scan' );
		$request->set_param( 'id', $event_id );
		$request->set_param( 'token', $token );
		$response = $controller->scan_event( $request );
		$this->assertNotWPError( $response );
		return $response->get_data();
	}

	private function share_token( string $url ): string {
		return (string) basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
	}

	private function home_match( string $id, string $home_team ): array {
		return [
			'id'        => $id,
			'starts_at' => wp_date( DATE_RFC3339, time() + HOUR_IN_SECONDS ),
			'date'      => wp_date( 'Y-m-d' ),
			'time'      => wp_date( 'H:i', time() + HOUR_IN_SECONDS ),
			'home_team' => $home_team,
			'away_team' => 'Bezoekers 1',
			'club_side' => 'home',
			'pitch'     => 'Veld 1',
			'location'  => 'Sportpark De Wijchert',
			'status'    => 'Te spelen',
			'cancelled' => false,
		];
	}
}
