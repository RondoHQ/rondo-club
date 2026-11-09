<?php

namespace Tests\Wpunit;

use Rondo\Access\AdmissionService;
use Rondo\Config\ClubConfig;
use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\Passes\GuestPassService;
use Rondo\Passes\MembershipPassQr;
use Rondo\Passes\MembershipPassService;
use Rondo\REST\AccessEvents;
use Rondo\REST\GuestPasses;
use Rondo\Sponsors\Relations;
use Tests\Support\RondoTestCase;

/** Tests reusable guest slots for the configured team and their match quota. */
class GuestPassTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		delete_option( ClubConfig::OPTION_GUEST_PASS_TEAM_ID );
		Relations::flush_cache();
	}

	protected function tear_down(): void {
		Relations::flush_cache();
		delete_option( ClubConfig::OPTION_GUEST_PASS_TEAM_ID );
		parent::tear_down();
	}

	public function test_businessclub_contact_gets_one_company_slot_without_a_team_membership(): void {
		[ $user_id, $person_id, $sponsor_id ] = $this->create_businessclub_host();
		wp_set_current_user( $user_id );
		$server = $this->bootRestControllers( [ GuestPasses::class ] );
		$data   = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/guest-passes/me' ) )->get_data();
		$this->assertFalse( $data['eligible'] );
		$this->assertSame( [], $data['slots'] );
		$this->assertCount( 1, $data['businessclub'] );
		$this->assertSame( $sponsor_id, $data['businessclub'][0]['sponsor_id'] );
		$this->assertSame( 'Voorbeeld & Co', $data['businessclub'][0]['name'] );
		$this->assertSame( 1, $data['businessclub'][0]['limit'] );
		$this->assertCount( 1, $data['businessclub'][0]['slots'] );
		$this->assertSame( 'businessclub', MembershipPassService::get_sponsor_pass_variant( $person_id ) );

		$request = new \WP_REST_Request( 'POST', '/rondo/v1/guest-passes/slots/1' );
		$request->set_param( 'sponsor_id', $sponsor_id );
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$slot = $response->get_data();
		$this->assertSame( $sponsor_id, Fields::get_for_post( $slot['id'], 'sponsor_id' ) );
		$this->assertSame( (string) $sponsor_id, get_post_meta( $slot['id'], 'sponsor_id', true ) );
		$this->assertSame( 'field_guest_pass_sponsor_id', get_post_meta( $slot['id'], '_sponsor_id', true ) );
		$wire = \Rondo\Fields\Formatter::for_wire( 'rondo_guest_pass', [ 'sponsor_id' => $sponsor_id ] );
		$this->assertSame( $sponsor_id, $wire['sponsor_id'] );
		$this->assertSame( [ 'rondo_sponsor' ], \Rondo\Fields\Registry::resolve( 'rondo_guest_pass', 'sponsor_id' )['post_type'] );

		$service = new GuestPassService();
		$this->assertSame( $slot['id'], $service->ensure_slot( $person_id, 1, $sponsor_id )['id'] );
		$this->assertWPError( $service->ensure_slot( $person_id, 2, $sponsor_id ) );
		$this->assertWPError( $service->replace_slot( $person_id, 2, $sponsor_id ) );
		$this->assertWPError( $service->ensure_slot( $person_id, 1 ) );
		wp_set_current_user( 0 );
		Relations::flush_cache();
		$slot = $service->claim( $this->share_token( $slot['share_url'] ), 'BC Gast' );
		$this->assertSame( 'active', $slot['status'] );
		$this->assertSame( 'Voorbeeld & Co', $service->get_pass_data( $slot['id'] )['sponsor_name'] );
		$this->assertNotWPError( ( new MembershipPassQr() )->issue_for_guest( $slot['id'] ) );
		wp_set_current_user( $user_id );

		$event = ( new AdmissionService( false ) )->select_match( $this->home_match( 'bc-match', 'AWC O23-1' ) );
		$first = $this->scan( new AccessEvents(), $event['id'], $this->issue_guest_token( $slot['id'] ) );
		$this->assertTrue( $first['valid'] );
		$this->assertTrue( $first['admission']['counted'] );
		$this->assertSame( 1, $first['stats']['counts']['guest'] );
	}

	public function test_businessclub_guest_pass_ends_at_second_contact_and_preserves_personal_passes(): void {
		[ $user_id, $person_id, $sponsor_id ] = $this->create_businessclub_host();
		wp_set_current_user( $user_id );
		$service = new GuestPassService();
		$slot    = $service->ensure_slot( $person_id, 1, $sponsor_id );
		$token   = $this->share_token( $slot['share_url'] );
		$service->claim( $token, 'Oorspronkelijke Gast' );
		$qr         = $this->issue_guest_token( $slot['id'] );
		$contacts   = Fields::get_for_post( $sponsor_id, 'contacts' );
		$contacts[] = [
			'person_id'     => $this->createPerson(),
			'receives_pass' => true,
		];
		$this->assertNotWPError( Relations::set_contacts( $sponsor_id, $contacts ) );

		$this->assertSame( [], $service->get_businessclub_sponsors( $person_id ) );
		$this->assertWPError( $service->claim( $token, 'Andere Gast' ) );
		$this->assertWPError( $service->ensure_slot( $person_id, 1, $sponsor_id ) );
		$this->assertWPError( $service->replace_slot( $person_id, 1, $sponsor_id ) );
		$this->assertFalse( $service->is_eligible_pass( $service->get_pass_data( $slot['id'] ) ) );
		$this->assertWPError( ( new MembershipPassQr() )->issue_for_guest( $slot['id'] ) );
		$event = ( new AdmissionService( false ) )->select_match( $this->home_match( 'bc-ended', 'AWC 1' ) );
		$scan  = $this->scan( new AccessEvents(), $event['id'], $qr );
		$this->assertFalse( $scan['valid'] );
		$this->assertSame( 'host_ineligible', $scan['reason'] );
		$this->assertSame( 0, $scan['stats']['counts']['guest'] );

		for ( $count = 2; $count <= 5; ++$count ) {
			foreach ( $contacts as $contact ) {
				$this->assertSame( 'businessclub', MembershipPassService::get_sponsor_pass_variant( $contact['person_id'] ) );
				$this->assertSame( [], $service->get_businessclub_sponsors( $contact['person_id'] ) );
			}
			$contacts[] = [
				'person_id'     => $this->createPerson(),
				'receives_pass' => true,
			];
			Relations::set_contacts( $sponsor_id, $contacts );
		}
	}

	public function test_company_must_be_active_businessclub_with_exactly_one_pass_enabled_contact(): void {
		[ , $person_id, $sponsor_id ] = $this->create_businessclub_host();
		$service                      = new GuestPassService();
		$this->assertTrue( $service->is_eligible_host( $person_id, $sponsor_id ) );

		Fields::update_for_post( $sponsor_id, 'sponsor_role', 'awc_sponsor' );
		Relations::flush_cache();
		$this->assertFalse( $service->is_eligible_host( $person_id, $sponsor_id ) );
		Fields::update_for_post( $sponsor_id, 'sponsor_role', 'businessclub' );
		wp_update_post(
			[
				'ID'          => $sponsor_id,
				'post_status' => 'draft',
			]
			);
		Relations::flush_cache();
		$this->assertFalse( $service->is_eligible_host( $person_id, $sponsor_id ) );
		wp_update_post(
			[
				'ID'          => $sponsor_id,
				'post_status' => 'publish',
			]
			);
		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'receives_pass' => false,
				],
			]
			);
		$this->assertFalse( $service->is_eligible_host( $person_id, $sponsor_id ) );
		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'receives_pass' => true,
				],
				[
					'person_id'     => $this->createPerson(),
					'receives_pass' => false,
				],
			]
			);
		$this->assertFalse( $service->is_eligible_host( $person_id, $sponsor_id ) );
		Relations::set_contacts( $sponsor_id, [] );
		$this->assertFalse( $service->is_eligible_host( $person_id, $sponsor_id ) );
	}

	public function test_company_guest_slot_cannot_be_accessed_by_another_account_or_invalid_source(): void {
		[ $user_id, $person_id, $sponsor_id ] = $this->create_businessclub_host();
		[ , , $other_company ]                = $this->create_businessclub_host();
		wp_set_current_user( $user_id );
		$server = $this->bootRestControllers( [ GuestPasses::class ] );
		foreach ( [ '', '/replace' ] as $suffix ) {
			foreach ( [ $other_company, $person_id, 9999999, 0, -1, 1.5 ] as $source ) {
				$request = new \WP_REST_Request( 'POST', '/rondo/v1/guest-passes/slots/1' . $suffix );
				$request->set_param( 'sponsor_id', $source );
				$this->assertContains( $server->dispatch( $request )->get_status(), [ 400, 403 ] );
			}
			$request = new \WP_REST_Request( 'POST', '/rondo/v1/guest-passes/slots/2' . $suffix );
			$request->set_param( 'sponsor_id', $sponsor_id );
			$this->assertSame( 400, $server->dispatch( $request )->get_status() );
		}
		wp_set_current_user( 0 );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/guest-passes/slots/1' );
		$request->set_param( 'sponsor_id', $sponsor_id );
		$this->assertSame( 401, $server->dispatch( $request )->get_status() );
		$this->assertSame( 401, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/guest-passes/me' ) )->get_status() );
	}

	public function test_team_and_company_slots_remain_independent_and_replacement_does_not_reset_quota(): void {
		[ $user_id, $person_id ] = $this->create_awc_one_player();
		[ , , $sponsor_id ]      = $this->create_businessclub_host( $person_id );
		wp_set_current_user( $user_id );
		$service = new GuestPassService();
		$team    = $service->ensure_slot( $person_id, 1 );
		$company = $service->ensure_slot( $person_id, 1, $sponsor_id );
		$this->assertNotSame( $team['id'], $company['id'] );
		$this->assertSame( 0, (int) Fields::get_for_post( $team['id'], 'sponsor_id' ) );
		Fields::delete_for_post( $team['id'], 'sponsor_id' );
		$this->assertSame( $team['id'], $service->ensure_slot( $person_id, 1 )['id'] );
		$this->assertCount( 2, $service->get_slots( $person_id ) );
		$this->assertCount( 1, $service->get_slots( $person_id, $sponsor_id ) );
		$service->claim( $this->share_token( $team['share_url'] ), 'Teamgast' );
		$service->claim( $this->share_token( $company['share_url'] ), 'Bedrijfsgast' );
		$qr    = $this->issue_guest_token( $company['id'] );
		$event = ( new AdmissionService( false ) )->select_match( $this->home_match( 'bc-and-team', 'AWC 1' ) );
		$this->scan( new AccessEvents(), $event['id'], $this->issue_guest_token( $team['id'] ) );
		$scan = $this->scan( new AccessEvents(), $event['id'], $qr );
		$this->assertTrue( $scan['admission']['counted'] );
		$this->assertSame( 2, $scan['stats']['counts']['guest'] );

		$replacement = $service->replace_slot( $person_id, 1, $sponsor_id );
		$this->assertSame( $company['id'], $replacement['id'] );
		$this->assertNotSame( $company['share_url'], $replacement['share_url'] );
		$this->assertSame( 'revoked', $this->scan( new AccessEvents(), $event['id'], $qr )['reason'] );
		$service->claim( $this->share_token( $replacement['share_url'] ), 'Nieuwe Gast' );
		$scan = $this->scan( new AccessEvents(), $event['id'], $this->issue_guest_token( $replacement['id'] ) );
		$this->assertTrue( $scan['admission']['duplicate'] );
		$this->assertSame( 2, $scan['stats']['counts']['guest'] );
	}

	public function test_two_companies_with_the_same_contact_have_separate_guest_quotas(): void {
		[ $user_id, $person_id, $first_company ] = $this->create_businessclub_host();
		[ , , $second_company ]                  = $this->create_businessclub_host( $person_id );
		wp_set_current_user( $user_id );
		$service = new GuestPassService();
		$this->assertCount( 2, $service->get_businessclub_sponsors( $person_id ) );
		$first  = $service->ensure_slot( $person_id, 1, $first_company );
		$second = $service->ensure_slot( $person_id, 1, $second_company );
		$this->assertNotSame( $first['id'], $second['id'] );
		$service->claim( $this->share_token( $first['share_url'] ), 'Eerste Bedrijfsgast' );
		$service->claim( $this->share_token( $second['share_url'] ), 'Tweede Bedrijfsgast' );
		$event = ( new AdmissionService( false ) )->select_match( $this->home_match( 'two-companies', 'AWC 1' ) );
		$this->scan( new AccessEvents(), $event['id'], $this->issue_guest_token( $first['id'] ) );
		$scan = $this->scan( new AccessEvents(), $event['id'], $this->issue_guest_token( $second['id'] ) );
		$this->assertTrue( $scan['admission']['counted'] );
		$this->assertSame( 2, $scan['stats']['counts']['guest'] );
	}

	public function test_replacing_company_contact_invalidates_old_pass_without_resetting_match_quota(): void {
		[ $user_id, $person_id, $sponsor_id ] = $this->create_businessclub_host();
		wp_set_current_user( $user_id );
		$service = new GuestPassService();
		$slot    = $service->ensure_slot( $person_id, 1, $sponsor_id );
		$service->claim( $this->share_token( $slot['share_url'] ), 'Eerste Gast' );
		$qr    = $this->issue_guest_token( $slot['id'] );
		$event = ( new AdmissionService( false ) )->select_match( $this->home_match( 'bc-contact-changed', 'AWC 1' ) );
		$this->scan( new AccessEvents(), $event['id'], $qr );

		$new_person = $this->createPerson();
		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $new_person,
					'receives_pass' => true,
				],
			]
			);
		$this->assertSame( 'host_ineligible', $this->scan( new AccessEvents(), $event['id'], $qr )['reason'] );
		$new_slot = $service->ensure_slot( $new_person, 1, $sponsor_id );
		$this->assertNotSame( $slot['id'], $new_slot['id'] );
		$service->claim( $this->share_token( $new_slot['share_url'] ), 'Tweede Gast' );
		$scan = $this->scan( new AccessEvents(), $event['id'], $this->issue_guest_token( $new_slot['id'] ) );
		$this->assertTrue( $scan['admission']['duplicate'] );
		$this->assertSame( 1, $scan['stats']['counts']['guest'] );
	}

	private function create_businessclub_host( int $person_id = 0 ): array {
		$user_id    = $this->createRondoUser();
		$person_id  = $person_id ?: $this->createPerson( [ 'post_title' => 'BC Contact' ], [ 'person_type' => 'contact' ] );
		$sponsor_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_sponsor',
				'post_status' => 'publish',
				'post_title'  => 'Voorbeeld &amp; Co',
			]
			);
		Fields::update_for_post( $sponsor_id, 'sponsor_role', 'businessclub' );
		$this->assertNotWPError(
			Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'receives_pass' => true,
				],
			]
			)
			);
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		return [ $user_id, $person_id, $sponsor_id ];
	}

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

	public function test_current_staff_member_can_use_guest_passes(): void {
		[ $user_id, $person_id ] = $this->create_awc_one_staff_member();
		wp_set_current_user( $user_id );

		$service = new GuestPassService();
		$this->assertTrue( $service->is_eligible_host( $person_id ) );

		$slot = $service->ensure_slot( $person_id, 1 );
		$this->assertNotWPError( $slot );
		$this->assertSame( 1, $slot['slot'] );
	}

	public function test_eligibility_uses_the_configured_team_id_and_is_disabled_without_it(): void {
		[ , $person_id, $team_id ] = $this->create_awc_one_player();
		$service                   = new GuestPassService();
		$this->assertTrue( $service->is_eligible_host( $person_id ) );

		delete_option( ClubConfig::OPTION_GUEST_PASS_TEAM_ID );
		$this->assertFalse( $service->is_eligible_host( $person_id ) );

		$other_team_id = $this->createOrganization( [ 'post_title' => 'AWC 1' ] );
		ClubConfig::update_guest_pass_team_id( $other_team_id );
		$this->assertFalse( $service->is_eligible_host( $person_id ) );

		ClubConfig::update_guest_pass_team_id( $team_id );
		$this->assertTrue( $service->is_eligible_host( $person_id ) );
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

	/** @return array{0:int,1:int,2:int} */
	private function create_awc_one_player(): array {
		return $this->create_awc_one_team_member( 'Speler', 'Speler' );
	}

	/** @return array{0:int,1:int,2:int} */
	private function create_awc_one_staff_member(): array {
		return $this->create_awc_one_team_member( VolunteerStatus::get_staff_roles()[0], 'Staflid' );
	}

	/** @return array{0:int,1:int,2:int} */
	private function create_awc_one_team_member( string $job_title, string $last_name ): array {
		$user_id = $this->createRondoUser();
		$team_id = $this->createOrganization( [ 'post_title' => 'AWC 1' ] );
		$person  = $this->createPerson(
			[ 'post_title' => 'AWC 1 ' . $last_name ],
			[
				'type_lid'     => 'Bondslid',
				'first_name'   => 'AWC',
				'last_name'    => $last_name,
				'work_history' => [
					[
						'team'        => $team_id,
						'entity_type' => 'team',
						'job_title'   => $job_title,
						'is_current'  => true,
					],
				],
			]
		);
		ClubConfig::update_guest_pass_team_id( $team_id );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person );
		return [ $user_id, $person, $team_id ];
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
