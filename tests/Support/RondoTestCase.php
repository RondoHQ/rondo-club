<?php

namespace Tests\Support;

use lucatume\WPBrowser\TestCase\WPTestCase;

/**
 * Base test case for Rondo Club theme tests.
 *
 * Provides helper methods for creating test fixtures and
 * ensures ACF is properly loaded.
 */
abstract class RondoTestCase extends WPTestCase {

	protected function set_up(): void {
		parent::set_up();

		// Ensure ACF is fully loaded with local JSON
		if ( function_exists( 'acf_get_local_json_files' ) ) {
			acf_get_local_json_files();
		}
	}

	/**
	 * Create a person post with optional ACF fields.
	 *
	 * @param array $args Post arguments (post_title, post_author, etc.)
	 * @param array $acf ACF field values keyed by field name
	 * @return int Post ID
	 */
	protected function createPerson( array $args = [], array $acf = [] ): int {
		$defaults = [
			'post_type'   => 'person',
			'post_status' => 'publish',
			'post_author' => get_current_user_id() ?: 1,
		];

		$post_id = self::factory()->post->create( array_merge( $defaults, $args ) );

		foreach ( $acf as $field => $value ) {
			update_field( $field, $value, $post_id );
		}

		return $post_id;
	}

	/**
	 * Create an organization (team) post.
	 *
	 * @param array $args Post arguments
	 * @param array $acf ACF field values
	 * @return int Post ID
	 */
	protected function createOrganization( array $args = [], array $acf = [] ): int {
		$defaults = [
			'post_type'   => 'team',
			'post_status' => 'publish',
			'post_author' => get_current_user_id() ?: 1,
		];

		$post_id = self::factory()->post->create( array_merge( $defaults, $args ) );

		foreach ( $acf as $field => $value ) {
			update_field( $field, $value, $post_id );
		}

		return $post_id;
	}

	/**
	 * Create a Rondo User with the custom role.
	 *
	 * @param array $args User arguments
	 * @return int User ID
	 */
	protected function createRondoUser( array $args = [] ): int {
		$defaults = [ 'role' => 'rondo_user' ];
		return self::factory()->user->create( array_merge( $defaults, $args ) );
	}

}
