<?php
/**
 * Magic Login account activation bridge.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes the Magic Login form the single entry point for login and activation.
 */
class MagicLoginActivation {

	/** Email queued for post-response processing. */
	private ?string $queued_email = null;

	/** Whether this request was handled, including a throttled request. */
	private bool $handled_request = false;

	/** Prevent this bridge from intercepting links it creates itself. */
	private static bool $dispatching = false;

	public function __construct() {
		add_filter( 'magic_login_pre_send_login_link', [ $this, 'intercept_send' ], PHP_INT_MAX, 2 );
		add_filter( 'magic_login_process_login_request_result', [ $this, 'normalize_result' ], PHP_INT_MAX, 2 );
		add_action( 'shutdown', [ $this, 'dispatch_queued_request' ], PHP_INT_MAX );
	}

	/**
	 * Stop the plugin's synchronous email and queue Rondo's unified flow.
	 *
	 * Running last preserves CAPTCHA, honeypot, and plugin rate-limit failures.
	 *
	 * @param mixed         $result Earlier short-circuit result.
	 * @param \WP_User|false $user   Resolved user, or false for an unknown address.
	 * @return mixed
	 */
	public function intercept_send( $result, $user ) {
		unset( $user );

		if ( self::$dispatching || $result !== null ) {
			return $result;
		}

		$email = $this->submitted_email();
		if ( $email === null ) {
			return $result;
		}

		$this->handled_request = true;
		$ip                    = ActivationService::client_ip();
		if ( ! ActivationService::is_rate_limited( $email, $ip ) ) {
			ActivationService::record_attempt( $email, $ip );
			$this->queued_email = $email;
		}

		// Any non-null, non-error result tells Magic Login that sending succeeded.
		return true;
	}

	/**
	 * Replace both known- and unknown-account outcomes with one neutral response.
	 *
	 * @param array $response Magic Login response.
	 * @param array $args     Magic Login message overrides.
	 * @return array
	 */
	public function normalize_result( $response, $args ) {
		unset( $args );

		if ( ! $this->handled_request || ! is_array( $response ) ) {
			return $response;
		}

		$response['errors']                 = new \WP_Error();
		$response['info']                   = '<p class="message magic_login_block_login_success">'
			. esc_html__( 'Als er een account bestaat voor deze gegevens, ontvang je een e-mail met de juiste inlog- of activatielink. Geen e-mail ontvangen? Kijk dan ook in je spammap.', 'rondo' )
			. '</p>';
		$response['show_form']              = false;
		$response['show_registration_form'] = false;
		$response['code_login']             = false;
		$response['phone_login']            = false;
		$response['is_processed']           = true;

		return $response;
	}

	/**
	 * Finish the browser response, then perform the lookup and mail work.
	 *
	 * @param bool $finish_response False only in unit tests.
	 */
	public function dispatch_queued_request( bool $finish_response = true ): void {
		if ( $this->queued_email === null ) {
			return;
		}

		$email              = $this->queued_email;
		$this->queued_email = null;

		if ( $finish_response ) {
			self::finish_response();
		}

		self::$dispatching = true;
		try {
			ActivationService::send_for_magic_login_request( $email );
		} finally {
			self::$dispatching = false;
		}
	}

	/** Return the submitted email, or null when this is not an email login request. */
	private function submitted_email(): ?string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Magic Login validates its own form nonce before this filter runs.
		$email = isset( $_POST['log'] ) ? sanitize_email( wp_unslash( $_POST['log'] ) ) : '';

		return is_email( $email ) ? strtolower( $email ) : null;
	}

	/** Flush the completed response before doing the slower lookup and mail work. */
	private static function finish_response(): void {
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
			return;
		}

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}
		flush();
	}
}
