<?php

namespace Tests\Wpunit;

use Rondo\Config\ClubConfig;
use Tests\Support\RondoTestCase;

/**
 * Tests for the PHP-served web app manifest.
 *
 * The manifest used to be a static build artifact with a hardcoded name, so
 * every deployment advertised itself as "Rondo Club" regardless of the site
 * title. These tests pin the behaviour that replaced it.
 */
class PwaManifestTest extends RondoTestCase {

	protected function tear_down(): void {
		delete_option( ClubConfig::OPTION_CLUB_NAME );
		update_option( 'blogname', 'Test Blog' );
		parent::tear_down();
	}

	/**
	 * Render the manifest without letting its exit() kill the test run.
	 *
	 * @return array Decoded manifest.
	 */
	private function render_manifest(): array {
		// rondo_render_manifest() sends headers and exits, so assert on the
		// builder it delegates to instead of the exit path.
		return json_decode( rondo_build_manifest(), true );
	}

	public function test_name_follows_the_site_title(): void {
		update_option( 'blogname', 'AWC Rondo' );

		$manifest = $this->render_manifest();

		$this->assertSame( 'AWC Rondo', $manifest['name'] );
		$this->assertSame( 'AWC Rondo', $manifest['short_name'] );
	}

	public function test_name_falls_back_when_site_title_is_empty(): void {
		update_option( 'blogname', '' );

		$manifest = $this->render_manifest();

		$this->assertSame( 'Rondo', $manifest['name'] );
	}

	public function test_description_uses_the_configured_club_name(): void {
		ClubConfig::update_club_name( 'AWC' );

		$manifest = $this->render_manifest();

		$this->assertSame(
			'Ledenadministratie en vrijwilligersbeheer voor AWC',
			$manifest['description']
		);
	}

	public function test_description_omits_club_name_when_unset(): void {
		delete_option( ClubConfig::OPTION_CLUB_NAME );

		$manifest = $this->render_manifest();

		$this->assertSame( 'Ledenadministratie en vrijwilligersbeheer', $manifest['description'] );
	}

	/**
	 * start_url used to point at /dashboard, which is not a route — it only
	 * resolved via the router's catch-all redirect.
	 */
	public function test_start_url_and_scope_are_the_app_root(): void {
		$manifest = $this->render_manifest();

		$this->assertSame( '/', $manifest['start_url'] );
		$this->assertSame( '/', $manifest['scope'] );
		$this->assertSame( '/', $manifest['id'] );
	}

	public function test_declares_the_icon_sizes_chrome_requires(): void {
		$manifest = $this->render_manifest();

		$sizes = wp_list_pluck( $manifest['icons'], 'sizes' );
		$this->assertContains( '192x192', $sizes );
		$this->assertContains( '512x512', $sizes );

		$purposes = wp_list_pluck( $manifest['icons'], 'purpose' );
		$this->assertContains( 'maskable', $purposes, 'Android adaptive icons need a maskable variant' );
	}

	public function test_icon_urls_are_absolute(): void {
		$manifest = $this->render_manifest();

		foreach ( $manifest['icons'] as $icon ) {
			$this->assertStringStartsWith(
				'http',
				$icon['src'],
				'Relative icon paths break when the manifest moves off the build directory'
			);
		}
	}

	/**
	 * iOS mattes any alpha channel onto black, which is how the previous icon
	 * set ended up as a logo on a black tile. The generator strips it; this
	 * catches a hand-edited or re-exported icon that reintroduces it.
	 */
	public function test_icon_files_exist_and_are_opaque(): void {
		$manifest  = $this->render_manifest();
		$theme_dir = get_template_directory();

		foreach ( $manifest['icons'] as $icon ) {
			$path = $theme_dir . '/public/icons/' . basename( $icon['src'] );
			$this->assertFileExists( $path, $icon['src'] . ' is declared but missing' );

			// PNG colour type lives in the IHDR chunk at byte 25:
			// 4 = greyscale+alpha, 6 = RGBA. Both carry transparency.
			$header = file_get_contents( $path, false, null, 0, 26 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$this->assertNotFalse( $header, $icon['src'] . ' is not readable' );

			$colour_type = ord( $header[25] );
			$this->assertNotContains(
				$colour_type,
				[ 4, 6 ],
				basename( $icon['src'] ) . ' has an alpha channel; iOS would composite it on black'
			);
		}
	}

	public function test_display_is_standalone(): void {
		$manifest = $this->render_manifest();

		$this->assertSame( 'standalone', $manifest['display'] );
	}
}
