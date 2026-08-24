<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Data\InverseRelationships;
use Rondo\Fields\Fields;
use Rondo\REST\MemberProfile;
use Rondo\Users\MemberProfileService;
use Rondo\Users\ProfileChangeLog;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/** Member self-service profile contract tests. */
class MemberProfileServiceTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		AccessControl::flush_visible_person_ids_cache();
	}

	public function test_primary_email_requires_verification_and_updates_matching_child_and_account(): void {
		[ $user_id, $parent_id ] = $this->linked_member( 'old@example.com' );
		$matching_child          = $this->add_minor_child( $parent_id, 'Kind Zelfde Mail', 'old@example.com' );
		$distinct_child          = $this->add_minor_child( $parent_id, 'Kind Eigen Mail', 'kind@example.com' );
		$token                   = '';
		add_filter(
			'pre_wp_mail',
			static function ( $return, $atts ) use ( &$token ) {
				if ( preg_match( '#email-wijzigen/([a-f0-9]{64})#', (string) $atts['message'], $matches ) ) {
					$token = $matches[1];
				}
				return true;
			},
			10,
			2
		);

		$request = MemberProfileService::request_email_change( $user_id, 'primary', 'new@example.com', '192.0.2.1' );

		$this->assertIsArray( $request );
		$this->assertSame( 'old@example.com', Fields::get_for_post( $parent_id, 'email_1' ) );
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $token );

		$result = MemberProfileService::verify_email_token( $token );

		$this->assertIsArray( $result );
		$this->assertSame( 'new@example.com', Fields::get_for_post( $parent_id, 'email_1' ) );
		$this->assertSame( 'new@example.com', Fields::get_for_post( $matching_child, 'email_1' ) );
		$this->assertSame( 'kind@example.com', Fields::get_for_post( $distinct_child, 'email_1' ) );
		$this->assertSame( 'new@example.com', UserProvisioning::contact_email( $user_id ) );
		$this->assertSame( 'new@example.com', get_userdata( $user_id )->user_email );
		$this->assertWPError( MemberProfileService::verify_email_token( $token ) );

		$log = get_posts(
			[
				'post_type'   => ProfileChangeLog::POST_TYPE,
				'post_status' => 'private',
				'numberposts' => 1,
			]
			);
		$this->assertCount( 1, $log );
		$this->assertSame( '1', get_post_meta( $log[0]->ID, '_rondo_profile_change_verified', true ) );
	}

	public function test_promoting_secondary_email_swaps_both_slots(): void {
		[ $user_id, $person_id ] = $this->linked_member( 'primary@example.com' );
		Fields::update_for_post( $person_id, 'email_2', 'second@example.com' );
		$token = '';
		add_filter(
			'pre_wp_mail',
			static function ( $return, $atts ) use ( &$token ) {
				preg_match( '#email-wijzigen/([a-f0-9]{64})#', (string) $atts['message'], $matches );
				$token = $matches[1] ?? '';
				return true;
			},
			10,
			2
		);

		MemberProfileService::request_email_change( $user_id, 'primary', 'second@example.com', '192.0.2.2' );
		$result = MemberProfileService::verify_email_token( $token );

		$this->assertIsArray( $result );
		$this->assertSame( 'second@example.com', Fields::get_for_post( $person_id, 'email_1' ) );
		$this->assertSame( 'primary@example.com', Fields::get_for_post( $person_id, 'email_2' ) );
	}

	public function test_secondary_email_can_be_removed_without_verification(): void {
		[ $user_id, $person_id ] = $this->linked_member( 'primary@example.com' );
		Fields::update_for_post( $person_id, 'email_2', 'second@example.com' );

		$result = MemberProfileService::remove_secondary_email( $user_id );

		$this->assertIsArray( $result );
		$this->assertSame( '', Fields::get_for_post( $person_id, 'email_2' ) );
	}

	public function test_phone_two_is_logged_as_rondo_only(): void {
		[ $user_id, $person_id ] = $this->linked_member( 'member@example.com' );
		Fields::update_for_post( $person_id, 'knvb_id', 'KNVB123' );
		wp_update_post(
			[
				'ID'                => $person_id,
				'post_modified'     => '2020-01-01 00:00:00',
				'post_modified_gmt' => '2020-01-01 00:00:00',
			]
		);

		$result = MemberProfileService::update_phones( $user_id, [ 'telephone_2' => '+31241234567' ] );

		$this->assertIsArray( $result );
		$this->assertSame( '', Fields::get_for_post( $person_id, 'telephone_1' ) );
		$this->assertSame( '+31241234567', Fields::get_for_post( $person_id, 'telephone_2' ) );
		$this->assertSame( 'local_only', get_post_meta( $result['log_id'], '_rondo_profile_change_sync_status', true ) );
		$this->assertGreaterThan( '2020-01-01 00:00:00', get_post_field( 'post_modified_gmt', $person_id ) );
	}

	public function test_parent_can_update_minor_child_phones_without_changing_own_phones(): void {
		[ $user_id, $parent_id ] = $this->linked_member( 'parent-phone@example.com' );
		$child_id                = $this->add_minor_child( $parent_id, 'Kind Telefoon', 'child-phone@example.com' );
		Fields::update_for_post( $parent_id, 'mobile_1', '+31611111111' );

		$result = MemberProfileService::update_phones(
			$user_id,
			[ 'mobile_1' => '+31622222222' ],
			$child_id
		);

		$this->assertIsArray( $result );
		$this->assertSame( [ $child_id ], $result['affected'] );
		$this->assertSame( '+31622222222', Fields::get_for_post( $child_id, 'mobile_1' ) );
		$this->assertSame( '+31611111111', Fields::get_for_post( $parent_id, 'mobile_1' ) );
	}

	public function test_parent_can_verify_minor_child_email_without_changing_account_or_sibling(): void {
		[ $user_id, $parent_id ] = $this->linked_member( 'parent-email@example.com' );
		$child_id                = $this->add_minor_child( $parent_id, 'Kind Mail', 'old-child@example.com' );
		$sibling_id              = $this->add_minor_child( $parent_id, 'Ander Kind', 'old-child@example.com' );
		$token                   = '';
		add_filter(
			'pre_wp_mail',
			static function ( $return, $atts ) use ( &$token ) {
				preg_match( '#email-wijzigen/([a-f0-9]{64})#', (string) $atts['message'], $matches );
				$token = $matches[1] ?? '';
				return true;
			},
			10,
			2
		);

		$request = MemberProfileService::request_email_change( $user_id, 'primary', 'new-child@example.com', '192.0.2.20', $child_id );

		$this->assertIsArray( $request );
		$this->assertSame( $child_id, $request['person_id'] );
		$this->assertSame( $child_id, MemberProfileService::pending_email_change( $user_id, $child_id )['person_id'] );
		$this->assertNull( MemberProfileService::pending_email_change( $user_id, $parent_id ) );

		$result = MemberProfileService::verify_email_token( $token );

		$this->assertIsArray( $result );
		$this->assertSame( 'new-child@example.com', Fields::get_for_post( $child_id, 'email_1' ) );
		$this->assertSame( 'old-child@example.com', Fields::get_for_post( $sibling_id, 'email_1' ) );
		$this->assertSame( 'parent-email@example.com', Fields::get_for_post( $parent_id, 'email_1' ) );
		$this->assertSame( 'parent-email@example.com', UserProvisioning::contact_email( $user_id ) );
		$this->assertSame( 'parent-email@example.com', get_userdata( $user_id )->user_email );
	}

	public function test_parent_cannot_update_person_outside_minor_household_scope(): void {
		[ $user_id ] = $this->linked_member( 'scoped-parent@example.com' );
		$other_id    = $this->createPerson(
			[ 'post_title' => 'Niet Mijn Kind' ],
			[
				'first_name' => 'Niet',
				'birthdate'  => gmdate( 'Y-m-d', strtotime( '-10 years' ) ),
			]
		);

		$result = MemberProfileService::update_phones( $user_id, [ 'mobile_1' => '+31633333333' ], $other_id );

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_profile_target_forbidden', $result->get_error_code() );
		$this->assertSame( '', Fields::get_for_post( $other_id, 'mobile_1' ) );
	}

	public function test_household_address_updates_parent_and_child_and_preserves_other_rows(): void {
		[ $user_id, $parent_id ] = $this->linked_member( 'parent@example.com' );
		$child_id                = $this->add_minor_child( $parent_id, 'Adres Kind', 'parent@example.com' );
		Fields::update_for_post( $child_id, 'knvb_id', 'ADDRESS123' );
		Fields::update_for_post(
			$child_id,
			'addresses',
			[
				[
					'address_label' => 'Home',
					'street_name'   => 'Oud',
					'house_number'  => '1',
					'postal_code'   => '6601 AA',
					'city'          => 'Wijchen',
					'country_code'  => 'NL',
				],
				[
					'address_label' => 'Factuur',
					'street_name'   => 'Postbus',
					'house_number'  => '9',
					'postal_code'   => '6600 AA',
					'city'          => 'Wijchen',
				],
			]
		);

		$result = MemberProfileService::update_household_address(
			$user_id,
			[
				'street_name'  => 'Nieuweweg',
				'house_number' => '12',
				'postal_code'  => '6602bb',
				'city'         => 'Wijchen',
			]
		);

		$this->assertIsArray( $result );
		$this->assertEqualsCanonicalizing( [ $parent_id, $child_id ], $result['affected'] );
		$child_addresses = Fields::get_for_post( $child_id, 'addresses' );
		$this->assertSame( 'Nieuweweg', $child_addresses[0]['street_name'] );
		$this->assertSame( '6602 BB', $child_addresses[0]['postal_code'] );
		$this->assertSame( 'Factuur', $child_addresses[1]['address_label'] );
		$pending = get_post_meta( $result['log_id'], '_rondo_profile_change_sync_pending', true );
		$this->assertNotContains( $child_id . ':country_code', $pending );
		$this->assertContains( $child_id . ':street_name', $pending );
	}

	public function test_former_member_cannot_use_self_service_edits(): void {
		[ $user_id, $person_id ] = $this->linked_member( 'former@example.com' );
		Fields::update_for_post( $person_id, 'former_member', true );

		$result = MemberProfileService::update_phones( $user_id, [ 'mobile_1' => '+31612345678' ] );

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_former_member_readonly', $result->get_error_code() );
	}

	public function test_self_service_routes_require_login_and_log_requires_membership_administration(): void {
		$server = $this->bootRestControllers( [ MemberProfile::class ] );
		wp_set_current_user( 0 );

		$anonymous = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/user/profile-email/pending' ) );
		$this->assertSame( 401, $anonymous->get_status() );

		[ $user_id ] = $this->linked_member( 'route@example.com' );
		$forbidden   = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/profile-change-log' ) );
		$this->assertSame( 403, $forbidden->get_status() );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$allowed = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/profile-change-log' ) );
		$this->assertSame( 200, $allowed->get_status() );
	}

	public function test_phone_route_accepts_only_a_minor_child_in_the_personal_household(): void {
		$server                  = $this->bootRestControllers( [ MemberProfile::class ] );
		[ $user_id, $parent_id ] = $this->linked_member( 'route-parent@example.com' );
		$child_id                = $this->add_minor_child( $parent_id, 'Route Kind', 'route-child@example.com' );
		$other_id                = $this->createPerson( [ 'post_title' => 'Route Ander' ], [ 'birthdate' => gmdate( 'Y-m-d', strtotime( '-10 years' ) ) ] );
		$child_request           = new \WP_REST_Request( 'PATCH', '/rondo/v1/user/profile-phones' );
		$child_request->set_header( 'content-type', 'application/json' );
		$child_request->set_body(
			(string) wp_json_encode(
			[
				'person_id' => $child_id,
				'mobile_1'  => '+31644444444',
			]
			)
			);

		$child_response = $server->dispatch( $child_request );

		$this->assertSame( 200, $child_response->get_status() );
		$this->assertSame( '+31644444444', Fields::get_for_post( $child_id, 'mobile_1' ) );

		$other_request = new \WP_REST_Request( 'PATCH', '/rondo/v1/user/profile-phones' );
		$other_request->set_header( 'content-type', 'application/json' );
		$other_request->set_body(
			(string) wp_json_encode(
			[
				'person_id' => $other_id,
				'mobile_1'  => '+31655555555',
			]
			)
			);
		$other_response = $server->dispatch( $other_request );

		$this->assertSame( 403, $other_response->get_status() );
		$this->assertSame( '', Fields::get_for_post( $other_id, 'mobile_1' ) );
	}

	private function linked_member( string $email ): array {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Test Lid' ],
			[
				'first_name' => 'Test',
				'last_name'  => 'Lid',
				'email_1'    => $email,
			]
		);
		$user_id   = $this->createRondoUser(
			[
				'user_login' => 'member-' . wp_generate_password( 8, false ),
				'user_email' => $email,
			]
			);
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		update_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, $email );
		wp_set_current_user( $user_id );
		return [ $user_id, $person_id ];
	}

	private function add_minor_child( int $parent_id, string $name, string $email ): int {
		$child_id        = $this->createPerson(
			[ 'post_title' => $name ],
			[
				'first_name' => $name,
				'birthdate'  => gmdate( 'Y-m-d', strtotime( '-10 years' ) ),
				'email_1'    => $email,
			]
		);
		$relationships   = Fields::get_for_post( $parent_id, 'relationships' ) ?: [];
		$relationships[] = [
			'related_person'    => $child_id,
			'relationship_type' => InverseRelationships::TYPE_CHILD,
		];
		Fields::update_for_post( $parent_id, 'relationships', $relationships );
		AccessControl::flush_visible_person_ids_cache();
		return $child_id;
	}
}
