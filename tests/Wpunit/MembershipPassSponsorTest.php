<?php

namespace Tests\Wpunit;

use ReflectionClass;
use Rondo\Config\FinanceConfig;
use Rondo\Passes\MembershipPassApple;
use Rondo\Passes\MembershipPassGoogle;
use Rondo\Passes\MembershipPassQr;
use Rondo\Passes\MembershipPassService;
use Rondo\REST\MembershipPasses;
use Tests\Support\RondoTestCase;

class MembershipPassSponsorTest extends RondoTestCase {

	public function test_expired_member_uses_wire_date_in_status_payload(): void {
		$person_id = $this->createPerson();
		update_post_meta( $person_id, 'lid-tot', '20260630' );

		$status = ( new MembershipPassQr() )->get_person_status( $person_id, '2025-2026' );

		$this->assertSame( 'expired', $status['status'] );
		$this->assertSame( '2026-06-30', $status['lid_tot'] );
	}

	public function test_sponsor_is_eligible_for_a_membership_pass_summary(): void {
		$sponsor_id = $this->createPerson(
			[ 'post_title' => 'Sponsor BV' ],
			[
				'company_name'         => 'Sponsor BV',
				'person_type'          => 'contact',
				'is_sponsor'           => 1,
				'sponsor_pass_variant' => 'businessclub',
			]
		);

		$this->assertSame( 'sponsor', MembershipPassService::get_person_member_tier( $sponsor_id ) );
		$this->assertSame( 'businessclub', MembershipPassService::get_person_pass_summary( $sponsor_id )['type'] );
	}

	public function test_member_sponsor_uses_sponsor_pass_precedence(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Lid en sponsor' ],
			[
				'first_name'           => 'Dubbelrol',
				'person_type'          => 'member',
				'is_sponsor'           => 1,
				'sponsor_pass_variant' => 'awc_sponsor',
				'type-lid'             => 'Bondslid',
			]
		);

		$this->assertSame( 'sponsor', MembershipPassService::get_person_member_tier( $person_id ) );
	}

	public function test_sponsor_wallet_passes_use_a_white_background(): void {
		$apple_method  = ( new ReflectionClass( MembershipPassApple::class ) )->getMethod( 'get_background_color' );
		$google_method = ( new ReflectionClass( MembershipPassGoogle::class ) )->getMethod( 'get_hex_background_color' );

		$apple_method->setAccessible( true );
		$google_method->setAccessible( true );

		$this->assertSame( 'rgb(255,255,255)', $apple_method->invoke( new MembershipPassApple(), 'sponsor' ) );
		$this->assertSame( '#ffffff', $google_method->invoke( new MembershipPassGoogle(), 'sponsor' ) );
	}

	public function test_apple_membership_pass_description_uses_the_club_name(): void {
		$description = ( new ReflectionClass( MembershipPassApple::class ) )->getMethod( 'get_pass_description' );
		$description->setAccessible( true );

		$this->assertSame( 'AWC lidmaatschapspas', $description->invoke( new MembershipPassApple(), 'AWC', false ) );
	}

	public function test_sponsor_wallet_passes_use_businessclub_logo(): void {
		$apple_method  = ( new ReflectionClass( MembershipPassApple::class ) )->getMethod( 'get_logo_image_path' );
		$google_method = ( new ReflectionClass( MembershipPassGoogle::class ) )->getMethod( 'get_logo_image_url' );

		$apple_method->setAccessible( true );
		$google_method->setAccessible( true );

		$apple_path = $apple_method->invoke( new MembershipPassApple(), 'sponsor', 'businessclub' );
		$google_url = $google_method->invoke( new MembershipPassGoogle(), 'sponsor', 'businessclub' );

		$this->assertFileExists( $apple_path );
		$this->assertSame( 'businessclub-awc-logo.png', basename( $apple_path ) );
		$this->assertStringEndsWith( '/public/icons/businessclub-awc-logo.png', $google_url );
	}

	public function test_sponsor_wallet_passes_use_configured_businessclub_logo(): void {
		$logo_path     = get_template_directory() . '/public/icons/businessclub-awc-logo.png';
		$attachment_id = wp_insert_attachment(
			[
				'post_title'     => 'Aangepast Businessclub-logo',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
				'guid'           => 'https://example.org/uploads/aangepast-businessclub-logo.png',
			],
			$logo_path
		);
		update_attached_file( $attachment_id, $logo_path );
		update_option( FinanceConfig::OPTION_BUSINESSCLUB_LOGO_ID, $attachment_id );

		$apple_method  = ( new ReflectionClass( MembershipPassApple::class ) )->getMethod( 'get_logo_image_path' );
		$google_method = ( new ReflectionClass( MembershipPassGoogle::class ) )->getMethod( 'get_logo_image_url' );
		$apple_method->setAccessible( true );
		$google_method->setAccessible( true );

		$this->assertSame( $logo_path, $apple_method->invoke( new MembershipPassApple(), 'sponsor', 'businessclub' ) );
		$this->assertSame( wp_get_attachment_url( $attachment_id ), $google_method->invoke( new MembershipPassGoogle(), 'sponsor', 'businessclub' ) );
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

		$this->assertSame( 'Businessclub AWC', $apple_title->invoke( $apple, 'AWC', 'sponsor', 'businessclub' ) );
		$this->assertSame( 'Businessclub AWC', $google_title->invoke( $google, 'AWC', 'sponsor', 'businessclub' ) );

		$apple_content  = $apple_fields->invoke( $apple, 'sponsor', 'Team 1', 'Trainer', 'Sponsor BV', '', '2026-2027' );
		$google_content = $google_modules->invoke( $google, 'sponsor', 'Team 1', 'Trainer', 'Sponsor BV', '', '2026-2027' );

		$this->assertSame( [ 'BEDRIJF', 'SEIZOEN' ], array_column( $apple_content['secondary'], 'label' ) );
		$this->assertSame( 'Sponsor BV', $apple_content['secondary'][0]['value'] );
		$this->assertSame( [], $apple_content['auxiliary'] );
		$this->assertSame( [ 'BEDRIJF', 'SEIZOEN' ], array_column( $google_content, 'header' ) );
		$this->assertSame( 'Sponsor BV', $google_content[0]['body'] );
	}

	public function test_awc_sponsor_variant_uses_awc_title_and_standard_logo(): void {
		$apple             = new MembershipPassApple();
		$google            = new MembershipPassGoogle();
		$apple_reflection  = new ReflectionClass( MembershipPassApple::class );
		$google_reflection = new ReflectionClass( MembershipPassGoogle::class );

		$apple_title  = $apple_reflection->getMethod( 'get_card_title' );
		$apple_logo   = $apple_reflection->getMethod( 'get_logo_image_path' );
		$google_title = $google_reflection->getMethod( 'get_card_title' );
		$google_logo  = $google_reflection->getMethod( 'get_logo_image_url' );

		$apple_title->setAccessible( true );
		$apple_logo->setAccessible( true );
		$google_title->setAccessible( true );
		$google_logo->setAccessible( true );

		$this->assertSame( 'AWC Sponsor', $apple_title->invoke( $apple, 'AWC', 'sponsor', 'awc_sponsor' ) );
		$this->assertSame( 'AWC Sponsor', $google_title->invoke( $google, 'AWC', 'sponsor', 'awc_sponsor' ) );
		$this->assertNotSame( 'businessclub-awc-logo.png', basename( $apple_logo->invoke( $apple, 'sponsor', 'awc_sponsor' ) ) );
		$this->assertStringNotContainsString( 'businessclub-awc-logo.png', $google_logo->invoke( $google, 'sponsor', 'awc_sponsor' ) );
	}

	public function test_sponsor_without_pass_variant_is_not_pass_eligible(): void {
		$sponsor_id = $this->createPerson(
			[ 'post_title' => 'Sponsor zonder variant' ],
			[
				'company_name' => 'Sponsor BV',
				'person_type'  => 'contact',
				'is_sponsor'   => 1,
			]
		);

		$this->assertSame( '', MembershipPassService::get_sponsor_pass_variant( $sponsor_id ) );
		$this->assertSame( '', MembershipPassService::get_person_member_tier( $sponsor_id ) );
		$this->assertNull( MembershipPassService::get_person_pass_summary( $sponsor_id ) );
	}

	public function test_scanner_summary_identifies_sponsor_and_company(): void {
		$sponsor_id = $this->createPerson(
			[ 'post_title' => 'Sponsor BV' ],
			[
				'company_name' => 'Sponsor BV',
				'person_type'  => 'member',
				'is_sponsor'   => 1,
			]
		);

		$method = ( new ReflectionClass( MembershipPasses::class ) )->getMethod( 'format_verified_person_summary' );
		$method->setAccessible( true );

		$summary = $method->invoke( new MembershipPasses(), get_post( $sponsor_id ) );

		$this->assertSame( 'member', $summary['person_type'] );
		$this->assertTrue( $summary['is_sponsor'] );
		$this->assertSame( 'Sponsor BV', $summary['company_name'] );
	}
}
