<?php

namespace Tests\Wpunit;

use Rondo\Users\GuardianAccountService;
use Rondo\Users\UserProvisioning;
use Rondo\Volunteer\VolunteerEligibilityService;
use Tests\Support\RondoTestCase;

/**
 * Tests the temporary parent identity and the later account migration.
 */
class GuardianAccountServiceTest extends RondoTestCase {

	private function person( string $name, ?string $age_group = null ): int {
		$person_id        = $this->createPerson( [ 'post_title' => $name ] );
		[ $first, $last ] = array_pad( explode( ' ', $name, 2 ), 2, '' );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'first_name', $first );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'last_name', $last );
		if ( $age_group !== null ) {
			update_post_meta( $person_id, 'leeftijdsgroep', $age_group );
		}
		return $person_id;
	}

	private function linked_user( int $person_id ): int {
		$user_id = self::factory()->user->create(
			[
				'user_login'   => 'guardian-' . wp_generate_password( 6, false ),
				'user_email'   => 'guardian-' . wp_generate_password( 6, false ) . '@example.com',
				'display_name' => get_the_title( $person_id ),
			]
		);
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		update_post_meta( $person_id, UserProvisioning::META_USER_ID, $user_id );
		return $user_id;
	}

	public function test_claim_sends_the_requested_notification_and_stores_the_guardian(): void {
		$child_id = $this->person( 'Rens van Haren', 'Onder 12' );
		$user_id  = $this->linked_user( $child_id );
		$mail     = null;
		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $attributes ) use ( &$mail ) {
				$mail = $attributes;
				return true;
			},
			10,
			2
		);

		$result = GuardianAccountService::claim( $user_id, $child_id, 'Bas van Haren' );

		$this->assertIsArray( $result );
		$this->assertSame( 'Bas van Haren', $result['pending_guardian']['name'] );
		$this->assertSame( 'Bas van Haren', get_userdata( $user_id )->display_name );
		$this->assertSame( [ GuardianAccountService::ADMIN_EMAIL ], $mail['to'] );
		$this->assertSame(
			'De ouder/verzorger Bas van Haren van Rens van Haren heeft zich via het account van het kind aangemeld voor Rondo.',
			$mail['message']
		);
	}

	public function test_pending_guardian_sees_the_youths_family_obligation(): void {
		$child_id = $this->person( 'Jeugdlid', 'Onder 12' );
		$user_id  = $this->linked_user( $child_id );
		add_filter( 'pre_wp_mail', '__return_true' );
		GuardianAccountService::claim( $user_id, $child_id, 'Test Ouder' );

		$unit = ( new VolunteerEligibilityService() )->get_gezin_unit_for_youth( $child_id );

		$this->assertNotNull( $unit );
		$this->assertSame( 'gezin', $unit['kind'] );
		$this->assertSame( 2, $unit['required_count'] );
		$this->assertContains( $child_id, $unit['trigger_person_ids'] );
	}

	public function test_relink_moves_account_owned_shifts_and_vog_iva_data(): void {
		$child_id  = $this->person( 'Rens van Haren', 'Onder 12' );
		$parent_id = $this->person( 'Bas van Haren' );
		$user_id   = $this->linked_user( $child_id );
		add_filter( 'pre_wp_mail', '__return_true' );
		GuardianAccountService::claim( $user_id, $child_id, 'Bas van Haren' );

		\Rondo\Fields\Fields::update_for_post( $child_id, 'datum_vog', '20260720' );
		update_post_meta( $child_id, 'vog_justis_submitted_date', '2026-07-20' );
		\Rondo\Fields\Fields::update_for_post( $child_id, 'datum_iva', '20260719' );
		\Rondo\Fields\Fields::update_for_post( $child_id, 'iva_approved', 1 );
		update_post_meta( $child_id, 'iva-approved', 1 );

		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Kantinedienst',
			]
		);
		update_post_meta( $shift_id, 'assigned_persons', [ $child_id ] );
		update_post_meta( $shift_id, '_shift_signup_at_' . $child_id, 123456789 );
		GuardianAccountService::mark_shift_signup( $shift_id, $child_id, $user_id );

		$result = GuardianAccountService::relink( $user_id, $parent_id );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['migrated_shifts'] );
		$this->assertSame( $parent_id, (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
		$this->assertSame( $user_id, (int) get_post_meta( $parent_id, UserProvisioning::META_USER_ID, true ) );
		$this->assertSame( '', get_post_meta( $child_id, UserProvisioning::META_USER_ID, true ) );
		$this->assertSame( [ $parent_id ], array_map( 'intval', get_post_meta( $shift_id, 'assigned_persons', true ) ) );
		$this->assertSame( 123456789, (int) get_post_meta( $shift_id, '_shift_signup_at_' . $parent_id, true ) );
		$this->assertSame( '', get_post_meta( $shift_id, '_shift_signup_at_' . $child_id, true ) );
		$this->assertSame( $user_id, (int) get_post_meta( $shift_id, GuardianAccountService::SHIFT_USER_META_PREFIX . $parent_id, true ) );
		$this->assertSame( '20260720', get_post_meta( $parent_id, 'datum-vog', true ) );
		$this->assertSame( '', get_post_meta( $child_id, 'datum-vog', true ) );
		$this->assertSame( '2026-07-20', get_post_meta( $parent_id, 'vog_justis_submitted_date', true ) );
		$this->assertSame( '20260719', get_post_meta( $parent_id, 'datum-iva', true ) );
		$this->assertSame( '1', get_post_meta( $parent_id, 'iva-approved', true ) );
		$this->assertNull( GuardianAccountService::pending_for_user( $user_id ) );
	}

	public function test_relink_refuses_to_overwrite_different_vog_or_iva_data(): void {
		$child_id  = $this->person( 'Kind', 'Onder 12' );
		$parent_id = $this->person( 'Ouder' );
		$user_id   = $this->linked_user( $child_id );
		\Rondo\Fields\Fields::update_for_post( $child_id, 'datum_vog', '20260720' );
		\Rondo\Fields\Fields::update_for_post( $parent_id, 'datum_vog', '20250101' );

		$result = GuardianAccountService::relink( $user_id, $parent_id );

		$this->assertWPError( $result );
		$this->assertSame( 'person_data_conflict', $result->get_error_code() );
		$this->assertSame( $child_id, (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
	}
}
