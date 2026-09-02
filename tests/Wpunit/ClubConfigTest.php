<?php

namespace Tests\Wpunit;

use Rondo\Config\ClubConfig;
use Rondo\Data\CredentialEncryption;
use Tests\Support\RondoTestCase;

/**
 * Tests for club-wide configuration values.
 */
class ClubConfigTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		delete_option( ClubConfig::OPTION_VOLUNTEER_SIGNUP_INFO );
		delete_option( ClubConfig::OPTION_IVA_APPROVAL_EMAIL_SUBJECT );
		delete_option( ClubConfig::OPTION_IVA_APPROVAL_EMAIL_BODY );
		delete_option( ClubConfig::OPTION_GUEST_PASS_TEAM_ID );
		delete_option( ClubConfig::OPTION_LETTERMINT_API_TOKEN );
	}

	protected function tear_down(): void {
		delete_option( ClubConfig::OPTION_VOLUNTEER_SIGNUP_INFO );
		delete_option( ClubConfig::OPTION_IVA_APPROVAL_EMAIL_SUBJECT );
		delete_option( ClubConfig::OPTION_IVA_APPROVAL_EMAIL_BODY );
		delete_option( ClubConfig::OPTION_GUEST_PASS_TEAM_ID );
		delete_option( ClubConfig::OPTION_LETTERMINT_API_TOKEN );
		parent::tear_down();
	}

	public function test_volunteer_signup_info_defaults_to_empty(): void {
		$this->assertSame( '', ClubConfig::get_volunteer_signup_info() );
	}

	public function test_volunteer_signup_info_keeps_safe_links_and_strips_unsafe_markup(): void {
		ClubConfig::update_volunteer_signup_info(
			'<p>Lees de <a href="https://example.com/taken" onclick="alert(1)">uitleg</a>.</p><script>alert(1)</script>'
		);

		$stored = ClubConfig::get_volunteer_signup_info();

		$this->assertStringContainsString( '<a href="https://example.com/taken">uitleg</a>', $stored );
		$this->assertStringNotContainsString( 'onclick', $stored );
		$this->assertStringNotContainsString( '<script', $stored );
		$this->assertSame( $stored, ClubConfig::get_all_settings()['volunteer_signup_info'] );
	}

	public function test_iva_approval_email_uses_defaults_and_can_be_updated(): void {
		$this->assertSame( 'Je bewijs voor verantwoord alcohol schenken is goedgekeurd', ClubConfig::get_iva_approval_email_subject() );
		$this->assertStringContainsString( '{first_name}', ClubConfig::get_iva_approval_email_body() );

		ClubConfig::update_iva_approval_email_subject( 'IVA voor {full_name} <script>' );
		ClubConfig::update_iva_approval_email_body( "Beste {first_name},\n\nWelkom bij {club_name}.<script>alert(1)</script>" );

		$this->assertSame( 'IVA voor {full_name}', ClubConfig::get_iva_approval_email_subject() );
		$this->assertSame(
			"Beste {first_name},\n\nWelkom bij {club_name}.",
			ClubConfig::get_iva_approval_email_body()
		);
		$this->assertSame(
			ClubConfig::get_iva_approval_email_body(),
			ClubConfig::get_all_settings()['iva_approval_email_body']
		);
	}

	public function test_guest_pass_team_defaults_to_disabled_and_accepts_only_teams(): void {
		$this->assertSame( 0, ClubConfig::get_guest_pass_team_id() );
		$this->assertSame( '', ClubConfig::get_guest_pass_team_name() );

		$team_id = $this->createOrganization( [ 'post_title' => 'AWC Zaterdag 1' ] );
		$this->assertTrue( ClubConfig::update_guest_pass_team_id( $team_id ) );
		$this->assertSame( $team_id, ClubConfig::get_guest_pass_team_id() );
		$this->assertSame( 'AWC Zaterdag 1', ClubConfig::get_guest_pass_team_name() );
		$this->assertSame( $team_id, ClubConfig::get_all_settings()['guest_pass_team_id'] );

		$person_id = $this->createPerson( [ 'post_title' => 'Geen team' ] );
		$this->assertFalse( ClubConfig::update_guest_pass_team_id( $person_id ) );
		$this->assertSame( $team_id, ClubConfig::get_guest_pass_team_id() );

		$this->assertTrue( ClubConfig::update_guest_pass_team_id( 0 ) );
		$this->assertSame( 0, ClubConfig::get_guest_pass_team_id() );
	}

	public function test_lettermint_token_is_encrypted_at_rest(): void {
		$this->assertTrue( ClubConfig::update_lettermint_api_token( 'lm_test_secret' ) );
		$stored = (string) get_option( ClubConfig::OPTION_LETTERMINT_API_TOKEN, '' );

		$this->assertTrue( CredentialEncryption::is_encrypted( $stored ) );
		$this->assertStringNotContainsString( 'lm_test_secret', $stored );
		$this->assertTrue( ClubConfig::has_lettermint_api_token() );
		$this->assertSame( 'lm_test_secret', \Rondo\Notifications\LettermintConfig::get_api_token() );
	}
}
