<?php

namespace Tests\Wpunit;

use Rondo\Users\ActivationService;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;

/** Account names for contacts that only have a company name. */
class UserProvisioningNameTest extends RondoTestCase {

	private function sponsor( string $company ): int {
		return $this->createPerson(
			[],
			[
				'company_name' => $company,
				'is_sponsor'   => true,
				'email_1'      => 'sponsor@example.com',
			]
		);
	}

	public function test_company_only_sponsor_can_activate_and_set_a_password(): void {
		$person_id = $this->sponsor( 'van Dal Assurantien' );
		$token     = ActivationService::create_token( 'sponsor@example.com' );
		$url       = ActivationService::activate( $token, $person_id );

		$this->assertIsString( $url );
		$user_id = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
		$user    = get_userdata( $user_id );
		$this->assertSame( 'van-dal-assurantien', $user->user_login );
		$this->assertSame( 'van Dal Assurantien', $user->display_name );
		$this->assertSame( '', $user->first_name );
		$this->assertSame( '', $user->last_name );
		$this->assertSame( $person_id, (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
		$this->assertNull( ActivationService::email_for_token( $token ) );
		parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$this->assertInstanceOf( \WP_User::class, check_password_reset_key( $query['key'], $query['login'] ) );
	}

	public function test_duplicate_long_company_names_get_unique_valid_logins(): void {
		$company = str_repeat( 'Assurantien', 7 );
		$first   = ( new UserProvisioning() )->provision( $this->sponsor( $company ), false );
		$second  = ( new UserProvisioning() )->provision( $this->sponsor( $company ), false );

		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		$first_user  = get_userdata( $first['user_id'] );
		$second_user = get_userdata( $second['user_id'] );
		$this->assertNotSame( $first_user->user_login, $second_user->user_login );
		$this->assertLessThanOrEqual( 60, strlen( $first_user->user_login ) );
		$this->assertLessThanOrEqual( 60, strlen( $second_user->user_login ) );
		$this->assertNotEmpty( $first_user->user_nicename );
		$this->assertNotEmpty( $second_user->user_nicename );
		$this->assertSame( $company, $second_user->display_name );
	}

	public function test_punctuation_only_company_name_gets_a_safe_fallback(): void {
		$person_id = $this->sponsor( '...' );
		$result    = ( new UserProvisioning() )->provision( $person_id, false );

		$this->assertIsArray( $result );
		$user = get_userdata( $result['user_id'] );
		$this->assertSame( 'gebruiker-' . $person_id, $user->user_login );
		$this->assertNotEmpty( $user->user_nicename );
	}

	public function test_personal_name_takes_precedence_over_company_name(): void {
		$person_id = $this->createPerson(
			[],
			[
				'first_name'   => 'Anne',
				'last_name'    => 'Jansen',
				'company_name' => 'Voorbeeld BV',
				'email_1'      => 'anne@example.com',
			]
		);
		$result    = ( new UserProvisioning() )->provision( $person_id, false );

		$this->assertIsArray( $result );
		$user = get_userdata( $result['user_id'] );
		$this->assertSame( 'anne.jansen', $user->user_login );
		$this->assertSame( 'Anne Jansen', $user->display_name );
	}
}
