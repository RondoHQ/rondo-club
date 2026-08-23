<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\Passes\MembershipPassService;
use Rondo\REST\People;
use Rondo\REST\UserSettings;
use Rondo\Sponsors\Relations;
use Tests\Support\RondoTestCase;

/** Contract tests for membership passes on the personal household surface. */
class HouseholdMembershipPassTest extends RondoTestCase {

	private const TYPE_CHILD = 3;

	protected function set_up(): void {
		parent::set_up();
		AccessControl::flush_visible_person_ids_cache();
		Relations::flush_cache();
	}

	public function test_household_exposes_only_visible_eligible_passes_with_correct_labels(): void {
		$parent               = $this->createPerson(
			[ 'post_title' => 'Ouder Bondslid' ],
			[
				'first_name' => 'Ouder',
				'type-lid'   => 'Bondslid',
			]
		);
		$club_member          = $this->minorPerson(
			'Kind Verenigingslid',
			[ 'type-lid' => 'Verenigingslid' ]
		);
		$businessclub_sponsor = $this->minorPerson(
			'Kind Businessclub',
			[ 'person_type' => 'contact' ]
		);
		$dual_role_sponsor    = $this->minorPerson(
			'Kind Sponsor en Bondslid',
			[
				'person_type' => 'member',
				'type-lid'    => 'Bondslid',
			]
		);
		$ineligible           = $this->minorPerson(
			'Kind zonder pas',
			[ 'person_type' => 'contact' ]
		);
		$stranger             = $this->createPerson(
			[ 'post_title' => 'Onbekend Bondslid' ],
			[ 'type-lid' => 'Bondslid' ]
		);

		foreach ( [ $club_member, $businessclub_sponsor, $dual_role_sponsor, $ineligible ] as $child_id ) {
			$this->addChild( $parent, $child_id );
		}

		$this->createSponsor(
			'Businessclubbedrijf',
			'businessclub',
			$businessclub_sponsor
		);
		$this->createSponsor(
			'Sponsorbedrijf',
			'awc_sponsor',
			$dual_role_sponsor
		);

		$user_id = $this->createRondoUser( [ 'user_login' => 'household_membership_passes' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $parent );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$response = ( new People() )->get_household();
		$people   = [];
		foreach ( $response->get_data() as $person ) {
			$people[ (int) $person['id'] ] = $person;
		}

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 5, $people );
		$this->assertArrayNotHasKey( $stranger, $people );
		$this->assertPass( $people[ $parent ]['membership_pass'], $parent, 'bondslid', 'Ledenpas' );
		$this->assertPass( $people[ $club_member ]['membership_pass'], $club_member, 'verenigingslid', 'Ledenpas' );
		$this->assertPass( $people[ $businessclub_sponsor ]['membership_pass'], $businessclub_sponsor, 'businessclub', 'Businessclubpas' );
		$this->assertPass( $people[ $dual_role_sponsor ]['membership_pass'], $dual_role_sponsor, 'awc_sponsor', 'Sponsorpas' );
		$this->assertNull( $people[ $ineligible ]['membership_pass'] );
		$this->assertNull( $people[ $businessclub_sponsor ]['sponsor_organization'] );
	}

	public function test_sponsor_account_receives_its_organization_and_sponsor_landing_flag(): void {
		$person_id  = $this->createPerson(
			[ 'post_title' => 'Sponsor Contact' ],
			[
				'person_type' => 'contact',
				'first_name'  => 'Sponsor',
				'last_name'   => 'Contact',
			]
		);
		$sponsor_id = $this->createSponsor( 'Voorbeeld & Partner BV', 'awc_sponsor', $person_id );
		$user_id    = $this->createRondoUser( [ 'user_login' => 'sponsor_personal_landing' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$response = ( new People() )->get_household();
		$person   = $response->get_data()[0];
		$user     = ( new UserSettings() )->get_current_user_data( $user_id );

		$this->assertSame(
			[
				'id'            => $sponsor_id,
				'name'          => 'Voorbeeld & Partner BV',
				'logo_url'      => null,
				'can_edit_logo' => true,
			],
			$person['sponsor_organization']
		);
		$this->assertTrue( $user['is_sponsor'] );
		$this->assertFalse( $user['is_parent'] );
		$this->assertFalse( $user['is_kader'] );
	}

	public function test_household_requires_one_choice_for_multiple_current_roles(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Lid met twee rollen' ],
			[ 'type-lid' => 'Bondslid' ]
		);
		$team_one  = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC 1',
			]
		);
		$team_two  = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC 2',
			]
		);
		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'team'       => $team_one,
					'job_title'  => 'Trainer',
					'is_current' => true,
				],
				[
					'team'       => $team_two,
					'job_title'  => 'Leider',
					'is_current' => true,
				],
			]
		);

		$pass = MembershipPassService::get_person_pass_summary( $person_id );

		$this->assertTrue( $pass['requires_role'] );
		$this->assertSame( [ 'AWC 1 — Trainer', 'AWC 2 — Leider' ], array_column( $pass['role_options'], 'label' ) );
		$this->assertCount( 2, array_unique( array_column( $pass['role_options'], 'key' ) ) );
	}

	public function test_businessclub_member_with_current_work_can_choose_businessclub_or_awc_pass(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Businessclublid en vrijwilliger' ],
			[ 'type-lid' => 'Bondslid' ]
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
		$this->createSponsor( 'Businessclubbedrijf', 'businessclub', $person_id );

		$pass = MembershipPassService::get_person_pass_summary( $person_id );

		$this->assertSame( 'businessclub', $pass['type'] );
		$this->assertSame( 'Lidpassen', $pass['label'] );
		$this->assertTrue( $pass['requires_role'] );
		$this->assertSame( [ 'Businessclubpas', 'AWC-pas — AWC 1 — Trainer' ], array_column( $pass['role_options'], 'label' ) );
		$this->assertSame( MembershipPassService::SPONSOR_PASS_SELECTION, $pass['role_options'][0]['key'] );

		$resolve_selection = ( new \ReflectionClass( MembershipPassService::class ) )->getMethod( 'resolve_selected_pass' );
		$resolve_selection->setAccessible( true );
		$work_options = ( new \Rondo\Passes\MembershipPassApple() )->get_work_options_for_person( $person_id );

		$this->assertSame(
			[
				'member_tier' => 'sponsor',
				'work'        => '',
			],
			$resolve_selection->invoke( null, $person_id, MembershipPassService::SPONSOR_PASS_SELECTION, $work_options )
		);
		$this->assertSame(
			[
				'member_tier' => 'bondslid',
				'work'        => $work_options[0]['key'],
			],
			$resolve_selection->invoke( null, $person_id, $work_options[0]['key'], $work_options )
		);
	}

	public function test_wallet_action_token_is_bound_to_the_current_user_session(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Lid met wallettoken' ],
			[ 'type-lid' => 'Bondslid' ]
		);
		$user_one  = $this->createRondoUser( [ 'user_login' => 'wallet_token_one' ] );
		$user_two  = $this->createRondoUser( [ 'user_login' => 'wallet_token_two' ] );

		wp_set_current_user( $user_one );
		$pass  = MembershipPassService::get_person_pass_summary( $person_id );
		$query = wp_parse_url( $pass['wallets']['google']['url'], PHP_URL_QUERY );
		parse_str( $query, $args );

		$verify = ( new \ReflectionClass( MembershipPassService::class ) )->getMethod( 'verify_wallet_token' );
		$verify->setAccessible( true );
		$this->assertTrue( $verify->invoke( null, $person_id, 'google', $args['_wallet_token'] ) );

		wp_set_current_user( $user_two );
		$this->assertFalse( $verify->invoke( null, $person_id, 'google', $args['_wallet_token'] ) );
	}

	public function test_wallet_admin_post_action_bypasses_the_backend_redirect(): void {
		global $pagenow;

		$original_pagenow = $pagenow;
		$user_id          = $this->createRondoUser( [ 'user_login' => 'wallet_admin_post_member' ] );
		wp_set_current_user( $user_id );

		try {
			$pagenow = 'admin-post.php';
			$this->assertFalse( rondo_should_block_wp_admin() );

			$pagenow = 'edit.php';
			$this->assertTrue( rondo_should_block_wp_admin() );
		} finally {
			$pagenow = $original_pagenow;
		}
	}

	public function test_legacy_public_pass_tokens_and_urls_are_removed(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Oud publiek token' ] );
		update_post_meta( $person_id, MembershipPassService::LEGACY_TOKEN_META_KEY, str_repeat( 'a', 64 ) );
		update_post_meta( $person_id, MembershipPassService::LEGACY_URL_META_KEY, 'https://example.org/lidpas/oud' );
		update_option( MembershipPassService::LEGACY_BACKFILL_OPTION, true );
		delete_option( MembershipPassService::LEGACY_CLEANUP_OPTION );

		( new MembershipPassService() )->maybe_remove_legacy_public_pass_data();

		$this->assertSame( '', get_post_meta( $person_id, MembershipPassService::LEGACY_TOKEN_META_KEY, true ) );
		$this->assertSame( '', get_post_meta( $person_id, MembershipPassService::LEGACY_URL_META_KEY, true ) );
		$this->assertFalse( get_option( MembershipPassService::LEGACY_BACKFILL_OPTION, false ) );
		$this->assertTrue( (bool) get_option( MembershipPassService::LEGACY_CLEANUP_OPTION, false ) );
	}

	public function test_sponsor_parent_receives_parent_landing_flag(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Sponsor Ouder' ],
			[
				'person_type' => 'member',
				'first_name'  => 'Sponsor',
				'last_name'   => 'Ouder',
			]
		);
		$child_id  = $this->minorPerson( 'Kind van Sponsor', [ 'type-lid' => 'Bondslid' ] );
		$this->addChild( $person_id, $child_id );
		$this->createSponsor( 'Ouderbedrijf BV', 'awc_sponsor', $person_id );

		$user_id = $this->createRondoUser( [ 'user_login' => 'sponsor_parent_landing' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$user = ( new UserSettings() )->get_current_user_data( $user_id );

		$this->assertTrue( $user['is_sponsor'] );
		$this->assertTrue( $user['is_parent'] );
		$this->assertFalse( $user['is_kader'] );
	}

	private function minorPerson( string $title, array $fields ): int {
		$fields['birthdate'] = gmdate( 'Ymd', strtotime( '-10 years' ) );
		return $this->createPerson( [ 'post_title' => $title ], $fields );
	}

	private function addChild( int $parent_id, int $child_id ): void {
		$relationships   = Fields::get_for_post( $parent_id, 'relationships' ) ?: [];
		$relationships[] = [
			'related_person'    => $child_id,
			'relationship_type' => self::TYPE_CHILD,
		];
		Fields::update_for_post( $parent_id, 'relationships', $relationships );
		AccessControl::flush_visible_person_ids_cache();
	}

	private function createSponsor( string $title, string $role, int $person_id ): int {
		$sponsor_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_sponsor',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
		Fields::update_for_post( $sponsor_id, 'sponsor_role', $role );
		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'       => $person_id,
					'receives_pass'   => true,
					'is_primary_pass' => true,
				],
			]
		);
		return $sponsor_id;
	}

	private function assertPass( array $pass, int $person_id, string $type, string $label ): void {
		$this->assertSame( [ 'type', 'label', 'wallets', 'role_options', 'requires_role' ], array_keys( $pass ) );
		$this->assertSame( $type, $pass['type'] );
		$this->assertSame( $label, $pass['label'] );
		$this->assertSame( [], $pass['role_options'] );
		$this->assertFalse( $pass['requires_role'] );

		foreach ( [ 'apple', 'google' ] as $wallet ) {
			$this->assertIsBool( $pass['wallets'][ $wallet ]['available'] );
			$this->assertStringContainsString( '/wp-admin/admin-post.php', $pass['wallets'][ $wallet ]['url'] );
			$query = wp_parse_url( $pass['wallets'][ $wallet ]['url'], PHP_URL_QUERY );
			parse_str( $query, $args );
			$this->assertSame( 'rondo_membership_pass_wallet', $args['action'] );
			$this->assertSame( (string) $person_id, $args['person_id'] );
			$this->assertSame( $wallet, $args['wallet'] );
			$this->assertArrayNotHasKey( '_wpnonce', $args );
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $args['_wallet_token'] );

			$verify = ( new \ReflectionClass( MembershipPassService::class ) )->getMethod( 'verify_wallet_token' );
			$verify->setAccessible( true );
			$this->assertTrue( $verify->invoke( null, $person_id, $wallet, $args['_wallet_token'] ) );
		}
	}
}
