<?php

namespace Tests\Wpunit;

use Rondo\Access\AdmissionService;
use Rondo\Passes\MembershipPassQr;
use Rondo\REST\AccessEvents;
use Tests\Support\RondoTestCase;

/** Tests for anonymous, match-bound membership-pass admissions. */
class AccessEventTest extends RondoTestCase {

	public function test_valid_pass_is_counted_once_without_attendee_data(): void {
		$scanner_user = self::factory()->user->create( [ 'role' => 'rondo_toegangscontrole' ] );
		wp_set_current_user( $scanner_user );
		$person_id = $this->createPerson(
			[ 'post_title' => 'Privacy Testlid' ],
			[
				'type-lid' => 'Bondslid',
				'knvb-id'  => 'TEST123',
				'email_1'  => 'privacy@example.test',
			]
		);

		$service = new AdmissionService( false );
		$event   = $service->select_match( $this->home_match() );
		$this->assertNotWPError( $event );
		$same_event = $service->select_match( $this->home_match() );
		$this->assertNotWPError( $same_event );
		$this->assertSame( $event['id'], $same_event['id'] );

		$issued     = ( new MembershipPassQr() )->issue_for_person( $person_id );
		$controller = new AccessEvents();
		$request    = new \WP_REST_Request( 'POST', '/rondo/v1/access-events/' . $event['id'] . '/scan' );
		$request->set_param( 'id', $event['id'] );
		$request->set_param( 'token', $issued['token'] );

		$first = $controller->scan_event( $request )->get_data();
		$this->assertTrue( $first['valid'] );
		$this->assertTrue( $first['admission']['counted'] );
		$this->assertFalse( $first['admission']['duplicate'] );
		$this->assertSame( 'bondslid', $first['pass_type'] );
		$this->assertSame( 1, $first['stats']['total'] );
		$this->assertSame( 1, $first['stats']['counts']['bondslid'] );

		$second = $controller->scan_event( $request )->get_data();
		$this->assertTrue( $second['valid'] );
		$this->assertFalse( $second['admission']['counted'] );
		$this->assertTrue( $second['admission']['duplicate'] );
		$this->assertSame( 1, $second['stats']['total'] );

		$admission_id = (int) $first['admission']['id'];
		$meta_keys    = array_keys( get_post_meta( $admission_id ) );
		$this->assertNotContains( 'person_id', $meta_keys );
		$this->assertNotContains( 'name', $meta_keys );
		$this->assertNotContains( 'email', $meta_keys );
		$this->assertNotContains( 'knvb_id', $meta_keys );
		$this->assertStringNotContainsString( 'Privacy Testlid', get_the_title( $admission_id ) );
		$this->assertStringNotContainsString( 'privacy@example.test', wp_json_encode( get_post_meta( $admission_id ) ) );
		$this->assertStringNotContainsString( 'TEST123', wp_json_encode( get_post_meta( $admission_id ) ) );
	}

	public function test_access_event_routes_require_scanner_permission(): void {
		$server  = $this->bootRestControllers( [ AccessEvents::class ] );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/access-events/matches' );

		$rondo_user = self::factory()->user->create( [ 'role' => 'rondo_user' ] );
		wp_set_current_user( $rondo_user );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );

		$scanner_user = self::factory()->user->create( [ 'role' => 'rondo_toegangscontrole' ] );
		wp_set_current_user( $scanner_user );
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
	}

	public function test_archive_is_read_only_paginated_and_permission_protected(): void {
		$server           = $this->bootRestControllers( [ AccessEvents::class ] );
		$service          = new AdmissionService( false );
		$old              = $this->home_match();
		$old['id']        = 'archived-match';
		$old['starts_at'] = '2020-01-01T14:00:00+01:00';
		$event            = $service->select_match( $old );
		$service->record_admission( $event['id'], 12345, 'bondslid' );
		for ( $i = 0; $i < 25; ++$i ) {
			$match       = $this->home_match();
			$match['id'] = 'archive-page-' . $i;
			$service->select_match( $match );
		}
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/access-events' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_user' ] ) );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_toegangscontrole' ] ) );
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 25, $response->get_data()['events'] );
		$this->assertSame( 2, $response->get_data()['total_pages'] );
		$request->set_param( 'page', 2 );
		$data = $server->dispatch( $request )->get_data();
		$this->assertCount( 1, $data['events'] );
		$this->assertSame( $event['id'], $data['events'][0]['id'] );
		$this->assertArrayNotHasKey( 'person_id', $data['events'][0] );
		$stats = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/access-events/' . $event['id'] . '/stats' ) );
		$this->assertSame( 1, $stats->get_data()['total'] );
		$this->assertSame( 26, (int) wp_count_posts( 'rondo_access_event' )->publish );
		$request->set_param( 'page', 0 );
		$this->assertSame( 400, $server->dispatch( $request )->get_status() );
	}

	/** @return array<string,mixed> */
	private function home_match(): array {
		return [
			'id'        => 'privacy-match-1',
			'starts_at' => wp_date( DATE_RFC3339, time() + HOUR_IN_SECONDS ),
			'date'      => wp_date( 'Y-m-d' ),
			'time'      => wp_date( 'H:i', time() + HOUR_IN_SECONDS ),
			'home_team' => 'AWC 1',
			'away_team' => 'Bezoekers 1',
			'club_side' => 'home',
			'pitch'     => 'Veld 1',
			'location'  => 'Sportpark De Wijchert',
			'status'    => 'Te spelen',
			'cancelled' => false,
		];
	}
}
