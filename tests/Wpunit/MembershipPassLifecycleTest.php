<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\Passes\MembershipPassQr;
use Rondo\Passes\MembershipPassService;
use Rondo\REST\MembershipPasses;
use Rondo\Sponsors\Relations;
use Tests\Support\RondoTestCase;

/** Contract tests for permanent, online-verifiable membership passes. */
class MembershipPassLifecycleTest extends RondoTestCase {

	public function test_default_qr_token_has_no_expiry_and_includes_pass_version(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Actief lid' ],
			[ 'type-lid' => 'Bondslid' ]
		);

		$issued = ( new MembershipPassQr() )->issue_for_person( $person_id );

		$this->assertNotWPError( $issued );
		$this->assertArrayNotHasKey( 'exp', $issued['payload'] );
		$this->assertSame( MembershipPassService::get_pass_version( $person_id ), $issued['payload']['pass_version'] );
		$this->assertSame( 'bondslid', $issued['payload']['pass_type'] );
		$this->assertNotWPError( ( new MembershipPassQr() )->verify_token( $issued['token'] ) );
	}

	public function test_former_and_expired_people_cannot_receive_a_pass(): void {
		$former_id  = $this->createPerson(
			[ 'post_title' => 'Oud lid' ],
			[
				'type-lid'      => 'Bondslid',
				'former_member' => true,
			]
		);
		$expired_id = $this->createPerson(
			[ 'post_title' => 'Verlopen lid' ],
			[
				'type-lid' => 'Verenigingslid',
				'lid-tot'  => '20200101',
			]
		);

		$this->assertNull( MembershipPassService::get_person_pass_summary( $former_id ) );
		$this->assertNull( MembershipPassService::get_person_pass_summary( $expired_id ) );
		$this->assertWPError( ( new MembershipPassQr() )->issue_for_person( $former_id ) );
		$this->assertWPError( ( new MembershipPassQr() )->issue_for_person( $expired_id ) );
	}

	public function test_inactive_members_can_only_use_their_active_sponsor_pass(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		foreach ( [ 'awc_sponsor', 'businessclub' ] as $variant ) {
			foreach ( [ [ 'former_member' => true ], [ 'lid-tot' => '20200101' ] ] as $inactive_fields ) {
				$person_id  = $this->createPerson( [], array_merge( [ 'type-lid' => 'Bondslid' ], $inactive_fields ) );
				$sponsor_id = self::factory()->post->create(
					[
						'post_type'   => 'rondo_sponsor',
						'post_status' => 'publish',
					]
					);
				Fields::update_for_post( $sponsor_id, 'sponsor_role', $variant );
				Relations::set_contacts(
					$sponsor_id,
					[
						[
							'person_id'     => $person_id,
							'receives_pass' => true,
						],
					]
					);
				$this->assertSame( $variant, MembershipPassService::get_person_pass_summary( $person_id )['type'] );
				$this->assertSame( 'sponsor', MembershipPassService::resolve_person_pass_selection( $person_id )['member_tier'] );
				$this->assertSame( '', MembershipPassService::get_person_standard_member_tier( $person_id ) );
				$this->assertFalse( MembershipPassService::person_has_pass_type( $person_id, 'bondslid' ) );
				$qr = new MembershipPassQr();
				$this->assertWPError( $qr->issue_for_person( $person_id, [ 'member_tier' => 'bondslid' ] ) );
				$issued = $qr->issue_for_person( $person_id, [ 'member_tier' => 'sponsor' ] );
				$this->assertNotWPError( $issued );
				$request = new \WP_REST_Request( 'POST', '/rondo/v1/membership-passes/verify' );
				$request->set_param( 'token', $issued['token'] );
				$result = ( new MembershipPasses() )->verify_qr_token( $request )->get_data();
				$this->assertTrue( $result['valid'] );
				$this->assertNull( $result['reason'] );
				Relations::set_contacts(
					$sponsor_id,
					[
						[
							'person_id'     => $person_id,
							'receives_pass' => false,
						],
					]
					);
				$this->assertFalse( ( new MembershipPasses() )->verify_qr_token( $request )->get_data()['valid'] );
				$this->assertNull( MembershipPassService::get_person_pass_summary( $person_id ) );
			}
		}
	}

	public function test_old_pass_stays_revoked_after_membership_is_reactivated(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Herintredend lid' ],
			[ 'type-lid' => 'Bondslid' ]
		);
		$user_id   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );

		new MembershipPassService();
		$qr      = new MembershipPassQr();
		$issued  = $qr->issue_for_person( $person_id );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/membership-passes/verify' );
		$request->set_param( 'token', $issued['token'] );

		$before = ( new MembershipPasses() )->verify_qr_token( $request );
		$this->assertTrue( $before->get_data()['valid'] );

		Fields::update_for_post( $person_id, 'former_member', true );
		Fields::update_for_post( $person_id, 'former_member', false );

		$after = ( new MembershipPasses() )->verify_qr_token( $request );
		$this->assertFalse( $after->get_data()['valid'] );
		$this->assertSame( 'revoked', $after->get_data()['reason'] );

		$new_pass = $qr->issue_for_person( $person_id );
		$request->set_param( 'token', $new_pass['token'] );
		$this->assertTrue( ( new MembershipPasses() )->verify_qr_token( $request )->get_data()['valid'] );
	}

	public function test_scanner_requires_toegangscontrole_or_admin(): void {
		$server  = $this->bootRestControllers( [ MembershipPasses::class ] );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/membership-passes/verify' );
		$request->set_param( 'token', str_repeat( 'x', 30 ) );
		$rondo_user = self::factory()->user->create( [ 'role' => 'rondo_user' ] );
		wp_set_current_user( $rondo_user );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );

		$scanner_user = self::factory()->user->create( [ 'role' => 'rondo_toegangscontrole' ] );
		wp_set_current_user( $scanner_user );
		$this->assertNotSame( 403, $server->dispatch( $request )->get_status() );
	}

	public function test_only_sponsor_pass_entitlement_changes_revoke_a_pass(): void {
		$person_id  = $this->createPerson( [ 'post_title' => 'Sponsorcontact' ], [ 'person_type' => 'contact' ] );
		$sponsor_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_sponsor',
				'post_status' => 'publish',
				'post_title'  => 'Sponsor BV',
			]
		);
		new MembershipPassService();
		Fields::update_for_post( $sponsor_id, 'sponsor_role', 'businessclub' );
		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'contact_role'  => 'Directeur',
					'receives_pass' => true,
				],
			]
		);
		$version = MembershipPassService::get_pass_version( $person_id );

		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'contact_role'  => 'Eigenaar',
					'receives_pass' => true,
				],
			]
		);
		$this->assertSame( $version, MembershipPassService::get_pass_version( $person_id ) );

		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'contact_role'  => 'Eigenaar',
					'receives_pass' => false,
				],
			]
		);
		$this->assertGreaterThan( $version, MembershipPassService::get_pass_version( $person_id ) );
	}

	public function test_dual_role_person_gets_the_exact_selected_pass_type(): void {
		$person_id  = $this->createPerson( [ 'post_title' => 'Lid en sponsorcontact' ], [ 'type-lid' => 'Bondslid' ] );
		$sponsor_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_sponsor',
				'post_status' => 'publish',
				'post_title'  => 'Businessclub BV',
			]
		);
		new MembershipPassService();
		Fields::update_for_post( $sponsor_id, 'sponsor_role', 'businessclub' );
		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'contact_role'  => 'Directeur',
					'receives_pass' => true,
				],
			]
		);

		$qr           = new MembershipPassQr();
		$sponsor_pass = $qr->issue_for_person( $person_id, [ 'member_tier' => 'sponsor' ] );
		$member_pass  = $qr->issue_for_person( $person_id, [ 'member_tier' => 'bondslid' ] );

		$this->assertNotWPError( $sponsor_pass );
		$this->assertNotWPError( $member_pass );
		$this->assertSame( 'businessclub', $sponsor_pass['payload']['pass_type'] );
		$this->assertSame( 'bondslid', $member_pass['payload']['pass_type'] );
	}

	public function test_qr_endpoint_requires_and_honours_the_exact_pass_choice(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Lid met digitale passen' ],
			[
				'first_name' => 'Digitaal',
				'last_name'  => 'Lid',
				'type-lid'   => 'Bondslid',
			]
		);
		$team_id   = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC 1',
			]
		);
		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'team'       => $team_id,
					'job_title'  => 'Trainer',
					'is_current' => true,
				],
			]
		);

		$sponsor_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_sponsor',
				'post_status' => 'publish',
				'post_title'  => 'Businessclub Digitaal',
			]
		);
		new MembershipPassService();
		Fields::update_for_post( $sponsor_id, 'sponsor_role', 'businessclub' );
		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'     => $person_id,
					'contact_role'  => 'Directeur',
					'receives_pass' => true,
				],
			]
		);

		$user_id = $this->createRondoUser( [ 'user_login' => 'digital_pass_member' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$summary  = MembershipPassService::get_person_pass_summary( $person_id );
		$role_key = $summary['role_options'][1]['key'];
		$server   = $this->bootRestControllers( [ MembershipPasses::class ] );

		$missing_choice = new \WP_REST_Request( 'GET', '/rondo/v1/membership-passes/people/' . $person_id . '/qr-token' );
		$this->assertSame( 400, $server->dispatch( $missing_choice )->get_status() );

		$sponsor_request = new \WP_REST_Request( 'GET', '/rondo/v1/membership-passes/people/' . $person_id . '/qr-token' );
		$sponsor_request->set_param( 'role', MembershipPassService::SPONSOR_PASS_SELECTION );
		$sponsor_data = $server->dispatch( $sponsor_request )->get_data();
		$this->assertSame( 'businessclub', $sponsor_data['payload']['pass_type'] );
		$this->assertSame( 'Businessclub Digitaal', $sponsor_data['person']['company_name'] );
		$this->assertSame( 'Businessclubpas', $sponsor_data['pass']['role_label'] );
		$this->assertSame( '#ffffff', $sponsor_data['pass']['background_color'] );
		$this->assertStringEndsWith( '/public/icons/businessclub-awc-logo.png', $sponsor_data['pass']['logo_url'] );

		$member_request = new \WP_REST_Request( 'GET', '/rondo/v1/membership-passes/people/' . $person_id . '/qr-token' );
		$member_request->set_param( 'role', $role_key );
		$member_data = $server->dispatch( $member_request )->get_data();
		$this->assertSame( 'bondslid', $member_data['payload']['pass_type'] );
		$this->assertSame( 'AWC 1 — Trainer', $member_data['pass']['role_label'] );
		$this->assertMatchesRegularExpression( '/^#[a-f0-9]{6}$/', $member_data['pass']['background_color'] );
		$this->assertStringEndsWith( '/public/icons/apple-touch-icon-180x180.png', $member_data['pass']['logo_url'] );

		$invalid_request = new \WP_REST_Request( 'GET', '/rondo/v1/membership-passes/people/' . $person_id . '/qr-token' );
		$invalid_request->set_param( 'role', 'not-a-real-choice' );
		$this->assertSame( 400, $server->dispatch( $invalid_request )->get_status() );

		$unrelated_id      = $this->createPerson( [ 'post_title' => 'Onverwant lid' ], [ 'type-lid' => 'Bondslid' ] );
		$unrelated_request = new \WP_REST_Request( 'GET', '/rondo/v1/membership-passes/people/' . $unrelated_id . '/qr-token' );
		$this->assertSame( 403, $server->dispatch( $unrelated_request )->get_status() );
	}
}
