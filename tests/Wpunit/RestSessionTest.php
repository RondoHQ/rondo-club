<?php

namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;

/** Existing windows must recover after the browser's shared login cookie changes. */
class RestSessionTest extends RondoTestCase {
	public function test_new_login_invalidates_old_tab_nonce_and_ajax_recovers_it(): void {
		$user_id    = $this->createRondoUser();
		$cookie_key = LOGGED_IN_COOKIE;
		$old_cookie = $_COOKIE[ $cookie_key ] ?? null;
		$old_header = $_SERVER['HTTP_X_WP_NONCE'] ?? null;
		$old_auth   = $GLOBALS['wp_rest_auth_cookie'] ?? null;
		$manager    = \WP_Session_Tokens::get_instance( $user_id );
		wp_set_current_user( $user_id );
		$old_token              = $manager->create( time() + HOUR_IN_SECONDS );
		$_COOKIE[ $cookie_key ] = wp_generate_auth_cookie( $user_id, time() + HOUR_IN_SECONDS, 'logged_in', $old_token );
		$old_nonce              = wp_create_nonce( 'wp_rest' );
		$new_token              = $manager->create( time() + HOUR_IN_SECONDS );
		$_COOKIE[ $cookie_key ] = wp_generate_auth_cookie( $user_id, time() + HOUR_IN_SECONDS, 'logged_in', $new_token );

		$die_handler = static function () {
			throw new \RuntimeException( 'ajax_complete' );
		};
		$die_filter  = static function () use ( $die_handler ) {
			return $die_handler;
		};
		add_filter( 'wp_die_handler', $die_filter );
		add_filter( 'wp_die_ajax_handler', $die_filter );
		add_filter( 'wp_doing_ajax', '__return_true' );
		ob_start();
		try {
			$this->assertFalse( wp_verify_nonce( $old_nonce, 'wp_rest' ), 'Re-login must reproduce the stale nonce in the original window.' );
			$_SERVER['HTTP_X_WP_NONCE']     = $old_nonce;
			$GLOBALS['wp_rest_auth_cookie'] = true;
			$error                          = rest_cookie_check_errors( null );
			$this->assertSame( 'rest_cookie_invalid_nonce', $error->get_error_code() );
			$this->assertSame( 403, $error->get_error_data()['status'] );
			$this->assertNotFalse( has_action( 'wp_ajax_rondo_rest_session', 'rondo_refresh_rest_session' ) );
			$this->assertFalse( has_action( 'wp_ajax_nopriv_rondo_rest_session' ), 'Logged-out visitors must not receive a session nonce.' );
			try {
				rondo_refresh_rest_session();
			} catch ( \RuntimeException $exception ) {
				$this->assertSame( 'ajax_complete', $exception->getMessage() );
			}
			$result = json_decode( ob_get_contents(), true );
			$this->assertTrue( $result['success'] );
			$this->assertSame( $user_id, $result['data']['userId'] );
			$this->assertNotFalse( wp_verify_nonce( $result['data']['nonce'], 'wp_rest' ) );
		} finally {
			ob_end_clean();
			remove_filter( 'wp_die_handler', $die_filter );
			remove_filter( 'wp_die_ajax_handler', $die_filter );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'rest_send_nocache_headers', '__return_true', 20 );
			$GLOBALS['wp_rest_auth_cookie'] = $old_auth;
			if ( $old_header === null ) {
				unset( $_SERVER['HTTP_X_WP_NONCE'] );
			} else {
				$_SERVER['HTTP_X_WP_NONCE'] = $old_header;
			}
			if ( $old_cookie === null ) {
				unset( $_COOKIE[ $cookie_key ] );
			} else {
				$_COOKIE[ $cookie_key ] = $old_cookie;
			}
			wp_set_current_user( 0 );
		}
	}
}
