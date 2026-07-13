<?php

namespace Tests\Wpunit;

use ReflectionClass;
use Rondo\Passes\MembershipPassApple;
use Rondo\Passes\MembershipPassGoogle;
use Rondo\Passes\PublicMembershipPassPage;
use Rondo\REST\MembershipPasses;
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

	public function test_sponsor_wallet_passes_use_businessclub_title_and_company_fields(): void {
		$apple             = new MembershipPassApple();
		$google            = new MembershipPassGoogle();
		$apple_reflection  = new ReflectionClass( MembershipPassApple::class );
		$google_reflection = new ReflectionClass( MembershipPassGoogle::class );

		$apple_title    = $apple_reflection->getMethod( 'get_card_title' );
		$apple_fields   = $apple_reflection->getMethod( 'get_card_fields' );
		$google_title   = $google_reflection->getMethod( 'get_card_title' );
		$google_modules = $google_reflection->getMethod( 'get_text_module_definitions' );

		$apple_title->setAccessible( true );
		$apple_fields->setAccessible( true );
		$google_title->setAccessible( true );
		$google_modules->setAccessible( true );

		$this->assertSame( 'Businessclub AWC', $apple_title->invoke( $apple, 'AWC', 'sponsor' ) );
		$this->assertSame( 'Businessclub AWC', $google_title->invoke( $google, 'AWC', 'sponsor' ) );

		$apple_content  = $apple_fields->invoke( $apple, 'sponsor', 'Team 1', 'Trainer', 'Sponsor BV', '', '2026-2027' );
		$google_content = $google_modules->invoke( $google, 'sponsor', 'Team 1', 'Trainer', 'Sponsor BV', '', '2026-2027' );

		$this->assertSame( [ 'BEDRIJF', 'SEIZOEN' ], array_column( $apple_content['secondary'], 'label' ) );
		$this->assertSame( 'Sponsor BV', $apple_content['secondary'][0]['value'] );
		$this->assertSame( [], $apple_content['auxiliary'] );
		$this->assertSame( [ 'BEDRIJF', 'SEIZOEN' ], array_column( $google_content, 'header' ) );
		$this->assertSame( 'Sponsor BV', $google_content[0]['body'] );
	}

	public function test_scanner_summary_identifies_sponsor_and_company(): void {
		$sponsor_id = $this->createPerson(
			[ 'post_title' => 'Sponsor BV' ],
			[
				'company_name' => 'Sponsor BV',
				'person_type'  => 'sponsor',
			]
		);

		$method = ( new ReflectionClass( MembershipPasses::class ) )->getMethod( 'format_verified_person_summary' );
		$method->setAccessible( true );

		$summary = $method->invoke( new MembershipPasses(), get_post( $sponsor_id ) );

		$this->assertSame( 'sponsor', $summary['person_type'] );
		$this->assertSame( 'Sponsor BV', $summary['company_name'] );
	}
}
