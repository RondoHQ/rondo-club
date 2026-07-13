<?php

namespace Tests\Wpunit;

use ReflectionClass;
use Rondo\Passes\MembershipPassApple;
use Rondo\Passes\MembershipPassGoogle;
use Rondo\Passes\PublicMembershipPassPage;
use Tests\Support\RondoTestCase;

class MembershipPassSponsorTest extends RondoTestCase {

	public function test_sponsor_is_eligible_for_a_membership_pass_url(): void {
		$sponsor_id = $this->createPerson(
			[ 'post_title' => 'Sponsor BV' ],
			[
				'company_name' => 'Sponsor BV',
				'person_type'  => 'sponsor',
			]
		);

		$this->assertSame( 'sponsor', PublicMembershipPassPage::get_person_member_tier( $sponsor_id ) );
		$this->assertStringContainsString( '/lidpas/', PublicMembershipPassPage::ensure_person_pass_url( $sponsor_id ) );
	}

	public function test_sponsor_wallet_passes_use_a_white_background(): void {
		$apple_method  = ( new ReflectionClass( MembershipPassApple::class ) )->getMethod( 'get_background_color' );
		$google_method = ( new ReflectionClass( MembershipPassGoogle::class ) )->getMethod( 'get_hex_background_color' );

		$apple_method->setAccessible( true );
		$google_method->setAccessible( true );

		$this->assertSame( 'rgb(255,255,255)', $apple_method->invoke( new MembershipPassApple(), 'sponsor' ) );
		$this->assertSame( '#ffffff', $google_method->invoke( new MembershipPassGoogle(), 'sponsor' ) );
	}
}
