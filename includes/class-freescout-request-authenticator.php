<?php
/**
 * Authentication for signed FreeScout integration requests.
 *
 * @package Rondo\Integrations\FreeScout
 */

namespace Rondo\Integrations\FreeScout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Validate HMAC signatures before integration payloads are parsed. */
final class RequestAuthenticator {

	private const MAX_BODY_BYTES        = 65536;
	private const CLOCK_SKEW_SECONDS    = 300;
	private const NONCE_TTL_SECONDS     = 600;
	private const RATE_WINDOW_SECONDS   = 60;
	private const RATE_LIMIT_PER_SOURCE = 120;

	/**
	 * Authenticate a request and return its decoded body.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function authenticate( \WP_REST_Request $request ) {
		if ( strtoupper( $request->get_method() ) !== 'POST' ) {
			return $this->error( 'rondo_freescout_method_not_allowed', 'Alleen POST is toegestaan.', 405 );
		}

		$content_type = $request->get_content_type();
		if ( ! is_array( $content_type ) || strtolower( (string) ( $content_type['value'] ?? '' ) ) !== 'application/json' ) {
			return $this->error( 'rondo_freescout_content_type_invalid', 'Content-Type application/json is verplicht.', 415 );
		}

		$raw_body = (string) $request->get_body();
		if ( $raw_body === '' || strlen( $raw_body ) > self::MAX_BODY_BYTES ) {
			return $this->error( 'rondo_freescout_body_invalid', 'De request body ontbreekt of is te groot.', 413 );
		}

		if ( wp_get_environment_type() === 'production' && ! is_ssl() ) {
			return $this->error( 'rondo_freescout_https_required', 'HTTPS is verplicht.', 403 );
		}

		$timestamp = trim( (string) $request->get_header( 'x-rondo-timestamp' ) );
		$nonce     = trim( (string) $request->get_header( 'x-rondo-nonce' ) );
		$signature = trim( (string) $request->get_header( 'x-rondo-signature' ) );

		if ( ! preg_match( '/^[0-9]{10}$/', $timestamp ) || abs( time() - (int) $timestamp ) > self::CLOCK_SKEW_SECONDS ) {
			$this->audit( 'signature_denied', 'timestamp' );
			return $this->error( 'rondo_freescout_timestamp_invalid', 'De request timestamp is ongeldig.', 401 );
		}
		if ( ! preg_match( '/^[A-Za-z0-9_-]{32,128}$/', $nonce ) ) {
			$this->audit( 'signature_denied', 'nonce_format' );
			return $this->error( 'rondo_freescout_nonce_invalid', 'De request nonce is ongeldig.', 401 );
		}
		if ( ! preg_match( '/^v1=([a-f0-9]{64})$/i', $signature, $signature_match ) ) {
			$this->audit( 'signature_denied', 'signature_format' );
			return $this->error( 'rondo_freescout_signature_invalid', 'De request signature is ongeldig.', 401 );
		}

		$keys = $this->signing_keys();
		if ( is_wp_error( $keys ) ) {
			return $keys;
		}

		$signed = $timestamp . "\n" . $nonce . "\n" . $raw_body;
		$valid  = false;
		foreach ( $keys as $key ) {
			$expected = hash_hmac( 'sha256', $signed, $key );
			$valid    = hash_equals( $expected, strtolower( $signature_match[1] ) ) || $valid;
		}
		if ( ! $valid ) {
			$this->audit( 'signature_denied', 'mismatch' );
			return $this->error( 'rondo_freescout_signature_invalid', 'De request signature is ongeldig.', 401 );
		}

		$nonce_key = 'rondo_fs_nonce_' . hash( 'sha256', $nonce );
		if ( get_transient( $nonce_key ) !== false ) {
			$this->audit( 'signature_denied', 'replay' );
			return $this->error( 'rondo_freescout_replay', 'Deze request is al verwerkt.', 409 );
		}
		set_transient( $nonce_key, 1, self::NONCE_TTL_SECONDS );

		$decoded = json_decode( $raw_body, true, 32 );
		if ( ! is_array( $decoded ) || json_last_error() !== JSON_ERROR_NONE ) {
			return $this->error( 'rondo_freescout_json_invalid', 'De JSON request body is ongeldig.', 400 );
		}

		$rate_limit = $this->apply_rate_limits( $decoded );
		if ( is_wp_error( $rate_limit ) ) {
			return $rate_limit;
		}

		return $decoded;
	}

	/** @return string[]|\WP_Error */
	private function signing_keys() {
		$keys = [];
		foreach ( [ 'RONDO_FREESCOUT_SIGNING_KEY', 'RONDO_FREESCOUT_SIGNING_KEY_PREVIOUS' ] as $name ) {
			$value = defined( $name ) ? constant( $name ) : getenv( $name );
			if ( is_string( $value ) && trim( $value ) !== '' ) {
				$keys[] = trim( $value );
			}
		}

		/**
		 * Filter signing keys for test and managed-host integration.
		 *
		 * Production keys must remain server-side and must never be returned by an API.
		 *
		 * @param string[] $keys Current and previous signing keys.
		 */
		$keys = (array) apply_filters( 'rondo_freescout_signing_keys', $keys );
		$keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ), static fn( string $key ): bool => strlen( $key ) >= 32 ) ) );

		if ( $keys === [] ) {
			return $this->error( 'rondo_freescout_signing_key_missing', 'De FreeScout signing key is niet geconfigureerd.', 503 );
		}

		return $keys;
	}

	/** @return true|\WP_Error */
	private function apply_rate_limits( array $body ) {
		$sources   = [];
		$remote_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		if ( filter_var( $remote_ip, FILTER_VALIDATE_IP ) ) {
			$sources[] = 'ip:' . $remote_ip;
		}
		$agent_id = absint( $body['agent']['freescoutUserId'] ?? $body['freescoutUserId'] ?? 0 );
		if ( $agent_id > 0 ) {
			$sources[] = 'agent:' . $agent_id;
		}
		$instance = $this->normalize_url( (string) ( $body['instance'] ?? '' ) );
		if ( $instance !== '' ) {
			$sources[] = 'instance:' . $instance;
		}

		$limit = (int) apply_filters( 'rondo_freescout_rate_limit', self::RATE_LIMIT_PER_SOURCE );
		foreach ( $sources as $source ) {
			$key   = 'rondo_fs_rate_' . hash( 'sha256', $source . ':' . (string) floor( time() / self::RATE_WINDOW_SECONDS ) );
			$count = (int) get_transient( $key );
			if ( $count >= max( 1, $limit ) ) {
				$this->audit( 'request_denied', 'rate_limit' );
				return $this->error( 'rondo_freescout_rate_limited', 'Te veel integratieverzoeken.', 429 );
			}
			set_transient( $key, $count + 1, self::RATE_WINDOW_SECONDS + 5 );
		}

		return true;
	}

	private function normalize_url( string $url ): string {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		return untrailingslashit( esc_url_raw( $url ) );
	}

	private function audit( string $event, string $reason ): void {
		do_action(
			'rondo_freescout_integration_audit',
			[
				'event'       => $event,
				'outcome'     => 'denied',
				'reason'      => $reason,
				'occurred_at' => gmdate( DATE_ATOM ),
			]
		);
	}

	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, [ 'status' => $status ] );
	}
}
