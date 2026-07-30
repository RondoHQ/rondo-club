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
	 * Incorrect-usage notices to treat as background noise for this test.
	 *
	 * @var string[]
	 */
	private array $ignoredIncorrectUsage = [];

	/**
	 * Ignore one known `_doing_it_wrong()` notice without going deaf to the rest.
	 *
	 * `setExpectedIncorrectUsage()` is the wrong tool for a notice that fires
	 * only for *some* requests in a test — it fails when the notice does not
	 * appear, so the test ends up coupled to which fields happen to be in the
	 * payload. This drops the named notice and leaves every other one failing
	 * the test as it should.
	 */
	protected function ignoreIncorrectUsage( string $function_name ): void {
		$this->ignoredIncorrectUsage[] = $function_name;
	}

	public function assert_post_conditions() {
		if ( $this->ignoredIncorrectUsage ) {
			// wp-browser proxies this property through __get/__set, so it has to be
			// read out, modified and written back — unsetting a key in place is
			// silently discarded.
			$caught = $this->caught_doing_it_wrong;
			foreach ( $this->ignoredIncorrectUsage as $function_name ) {
				unset( $caught[ $function_name ] );
			}
			$this->caught_doing_it_wrong = $caught;
		}

		parent::assert_post_conditions();
	}

	/**
	 * Register the given REST controllers and rebuild the REST server.
	 *
	 * The theme only instantiates its REST controllers on real REST requests, so
	 * in tests their routes do not exist and every dispatch answers 404 — which
	 * looks exactly like a permission test passing for the wrong reason. Booting
	 * the controllers *before* the server is built matters: `rest_get_server()`
	 * fires `rest_api_init` once, and a controller constructed after that has
	 * already missed it.
	 *
	 * @param class-string[] $controllers Controller classes to instantiate.
	 */
	protected function bootRestControllers( array $controllers ): \WP_REST_Server {
		foreach ( $controllers as $controller ) {
			new $controller();
		}

		global $wp_rest_server;
		$wp_rest_server = null;

		return rest_get_server();
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
