<?php
/**
 * First-party OpenID Connect client registry.
 *
 * @package Rondo\Identity
 */

namespace Rondo\Identity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Store confidential first-party OIDC clients in one non-autoloaded option. */
final class OidcClientRegistry {

	public const OPTION_CLIENTS = 'rondo_oidc_clients';
	public const SCOPES         = [ 'openid', 'email', 'profile' ];

	/** Return every client without secret hashes. */
	public static function all(): array {
		return array_values(
			array_map(
				[ self::class, 'public_client' ],
				self::stored_clients()
			)
		);
	}

	/** Return one enabled client including its hash for internal validation. */
	public static function find( string $client_id ): ?array {
		$clients = self::stored_clients();
		$client  = $clients[ $client_id ] ?? null;

		return is_array( $client ) ? $client : null;
	}

	/** Register one confidential client and return its raw secret exactly once. */
	public static function create( array $input ) {
		$validated = self::validate_input( $input );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$clients   = self::stored_clients();
		$client_id = self::random_value( 24 );
		$secret    = self::random_value( 32 );
		$now       = gmdate( DATE_ATOM );
		$client    = [
			'client_id'              => $client_id,
			'client_secret_hash'     => password_hash( $secret, PASSWORD_DEFAULT ),
			'label'                  => $validated['label'],
			'redirect_uris'          => $validated['redirect_uris'],
			'allowed_scopes'         => self::SCOPES,
			'freescout_base_url'     => $validated['freescout_base_url'],
			'enabled'                => true,
			'created_at'             => $now,
			'secret_created_at'      => $now,
			'secret_last_rotated_at' => null,
		];

		$clients[ $client_id ] = $client;
		self::store_clients( $clients );

		return array_merge( self::public_client( $client ), [ 'client_secret' => $secret ] );
	}

	/** Update the non-secret configuration for an existing client. */
	public static function update( string $client_id, array $input ) {
		$clients = self::stored_clients();
		if ( ! isset( $clients[ $client_id ] ) ) {
			return new \WP_Error( 'rondo_oidc_client_not_found', 'OpenID Connect-client niet gevonden.', [ 'status' => 404 ] );
		}

		$current   = $clients[ $client_id ];
		$validated = self::validate_input(
			[
				'label'              => $input['label'] ?? $current['label'],
				'redirect_uris'      => $input['redirect_uris'] ?? $current['redirect_uris'],
				'freescout_base_url' => $input['freescout_base_url'] ?? $current['freescout_base_url'],
			]
		);
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$current['label']              = $validated['label'];
		$current['redirect_uris']      = $validated['redirect_uris'];
		$current['freescout_base_url'] = $validated['freescout_base_url'];
		if ( array_key_exists( 'enabled', $input ) ) {
			$current['enabled'] = rest_sanitize_boolean( $input['enabled'] );
		}

		$clients[ $client_id ] = $current;
		self::store_clients( $clients );

		return self::public_client( $current );
	}

	/** Replace a secret immediately and return the new raw value exactly once. */
	public static function rotate_secret( string $client_id ) {
		$clients = self::stored_clients();
		if ( ! isset( $clients[ $client_id ] ) ) {
			return new \WP_Error( 'rondo_oidc_client_not_found', 'OpenID Connect-client niet gevonden.', [ 'status' => 404 ] );
		}

		$secret                                      = self::random_value( 32 );
		$clients[ $client_id ]['client_secret_hash'] = password_hash( $secret, PASSWORD_DEFAULT );
		$clients[ $client_id ]['secret_last_rotated_at'] = gmdate( DATE_ATOM );
		self::store_clients( $clients );

		return array_merge( self::public_client( $clients[ $client_id ] ), [ 'client_secret' => $secret ] );
	}

	/** Constant-work secret validation against the stored password hash. */
	public static function verify_secret( array $client, string $secret ): bool {
		$hash = (string) ( $client['client_secret_hash'] ?? '' );

		return $hash !== '' && $secret !== '' && password_verify( $secret, $hash );
	}

	/** Whether a redirect URI is one of the exact registered values. */
	public static function redirect_allowed( array $client, string $redirect_uri ): bool {
		return in_array( $redirect_uri, (array) ( $client['redirect_uris'] ?? [] ), true );
	}

	/** Parse, normalize and validate one requested scope string. */
	public static function scopes( string $scope, array $allowed_scopes = self::SCOPES ) {
		$requested = array_values( array_unique( array_filter( preg_split( '/\s+/', trim( $scope ) ) ?: [] ) ) );
		if ( ! in_array( 'openid', $requested, true ) || ! in_array( 'email', $requested, true ) ) {
			return new \WP_Error( 'invalid_scope', 'De scopes openid en email zijn verplicht.' );
		}
		foreach ( $requested as $item ) {
			if ( ! in_array( $item, $allowed_scopes, true ) ) {
				return new \WP_Error( 'invalid_scope', 'De aanvraag bevat een niet-ondersteunde scope.' );
			}
		}

		return $requested;
	}

	/** Remove secret material from an API response. */
	private static function public_client( array $client ): array {
		unset( $client['client_secret_hash'] );
		$client['has_client_secret'] = true;

		return $client;
	}

	/** Validate administrator-supplied client configuration. */
	private static function validate_input( array $input ) {
		$label = trim( sanitize_text_field( (string) ( $input['label'] ?? '' ) ) );
		if ( $label === '' || mb_strlen( $label ) > 120 ) {
			return new \WP_Error( 'rondo_oidc_invalid_label', 'Vul een geldige clientnaam in.', [ 'status' => 400 ] );
		}

		$redirects = array_values( array_unique( array_filter( array_map( 'strval', (array) ( $input['redirect_uris'] ?? [] ) ) ) ) );
		if ( empty( $redirects ) || count( $redirects ) > 5 ) {
			return new \WP_Error( 'rondo_oidc_invalid_redirects', 'Configureer één tot vijf redirect-URL’s.', [ 'status' => 400 ] );
		}
		foreach ( $redirects as $index => $redirect ) {
			$normalized = self::validate_url( $redirect, true );
			if ( is_wp_error( $normalized ) ) {
				return $normalized;
			}
			$redirects[ $index ] = $normalized;
		}

		$base_url = self::validate_url( (string) ( $input['freescout_base_url'] ?? '' ), true );
		if ( is_wp_error( $base_url ) ) {
			return $base_url;
		}

		return [
			'label'              => $label,
			'redirect_uris'      => $redirects,
			'freescout_base_url' => untrailingslashit( $base_url ),
		];
	}

	/** Validate a configured HTTPS URL, allowing local HTTP only outside production. */
	private static function validate_url( string $url, bool $allow_path ): string|\WP_Error {
		$url   = trim( $url );
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return new \WP_Error( 'rondo_oidc_invalid_url', 'Configureer een volledige URL.', [ 'status' => 400 ] );
		}
		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) || isset( $parts['query'] ) ) {
			return new \WP_Error( 'rondo_oidc_invalid_url', 'De URL mag geen inloggegevens, query of fragment bevatten.', [ 'status' => 400 ] );
		}

		$host       = strtolower( (string) $parts['host'] );
		$is_local   = $host === 'localhost' || str_ends_with( $host, '.localhost' ) || $host === '127.0.0.1' || $host === '::1';
		$production = function_exists( 'wp_get_environment_type' ) && wp_get_environment_type() === 'production';
		if ( strtolower( (string) $parts['scheme'] ) !== 'https' && ( $production || ! $is_local ) ) {
			return new \WP_Error( 'rondo_oidc_https_required', 'OpenID Connect-URL’s moeten HTTPS gebruiken.', [ 'status' => 400 ] );
		}
		if ( ! $allow_path && ! empty( $parts['path'] ) && $parts['path'] !== '/' ) {
			return new \WP_Error( 'rondo_oidc_url_path', 'Deze URL mag geen pad bevatten.', [ 'status' => 400 ] );
		}

		return esc_url_raw( $url );
	}

	/** Return normalized stored clients keyed by client ID. */
	private static function stored_clients(): array {
		$clients = get_option( self::OPTION_CLIENTS, [] );

		return is_array( $clients ) ? $clients : [];
	}

	/** Persist clients without autoloading secret hashes on every request. */
	private static function store_clients( array $clients ): void {
		update_option( self::OPTION_CLIENTS, $clients, false );
	}

	private static function random_value( int $bytes ): string {
		return rtrim( strtr( base64_encode( random_bytes( $bytes ) ), '+/', '-_' ), '=' );
	}
}
