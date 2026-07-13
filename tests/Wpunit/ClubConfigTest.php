<?php

namespace Tests\Wpunit;

use Rondo\Config\ClubConfig;
use Tests\Support\RondoTestCase;

/**
 * Tests for club-wide configuration values.
 */
class ClubConfigTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		delete_option( ClubConfig::OPTION_VOLUNTEER_SIGNUP_INFO );
	}

	protected function tear_down(): void {
		delete_option( ClubConfig::OPTION_VOLUNTEER_SIGNUP_INFO );
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
}
