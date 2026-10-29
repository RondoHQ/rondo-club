<?php
/**
 * Session-scoped mailbox proof and switching between identities on that mailbox.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Fields\Fields;

/** Never treat an editable contact address or an old verification as login proof. */
class AccountSwitcher {

	private const PROOF           = 'rondo_switch_email';
	private const HINT            = 'rondo_login_email';
	private const LINK_PREFIX     = 'rondo_switch_link_';
	private string $login_email   = '';
	private array $issued_session = [];

	public function __construct() {
		add_action( 'wp_authenticate', [ $this, 'remember_identifier' ], 1 );
		add_filter( 'attach_session_information', [ $this, 'attach_identifier' ], 10, 2 );
		add_action( 'set_logged_in_cookie', [ $this, 'capture_session' ], 10, 6 );
		add_action( 'magic_login_logged_in', [ $this, 'confirm_magic_login' ], 20, 2 );
	}

	/** Remember the submitted address before LoginResolver rewrites it. */
	public function remember_identifier( $identifier ): void {
		$this->login_email = self::normalize( (string) $identifier );
	}

	/** The identifier is a UI hint, never proof of mailbox possession. */
	public function attach_identifier( array $session, int $user_id ): array {
		if ( in_array( $this->login_email, self::addresses( $user_id ), true ) ) {
			$session[ self::HINT ] = $this->login_email;
		}
		return $session;
	}

	/** Capture the new session; request cookies still refer to the old identity. */
	public function capture_session( $cookie, $expire, $expiration, $user_id, $scheme, $token ): void {
		unset( $cookie, $expire, $expiration, $scheme );
		$this->issued_session = [
			'user_id' => (int) $user_id,
			'token'   => $token,
		];
	}

	/** Bind a plugin-generated login token to the exact mailbox receiving the email. */
	public static function bind_login_link( string $url, int $user_id, string $email ): void {
		$email = self::normalize( $email );
		if ( ! in_array( $email, self::addresses( $user_id ), true ) ) {
			return;
		}
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		if ( empty( $query['token'] ) || ! is_string( $query['token'] ) || (int) ( $query['user_id'] ?? 0 ) !== $user_id ) {
			return;
		}
		$hash = hash_hmac( 'sha256', $query['token'], wp_salt() );
		set_transient(
			self::LINK_PREFIX . $hash,
			[
				'user_id' => $user_id,
				'email'   => $email,
			],
			DAY_IN_SECONDS
			);
	}

	/** Called only after Magic Login has validated and consumed its token. */
	public function confirm_magic_login( \WP_User $user, $token = null ): void {
		$hash = is_array( $token ) ? ( $token['token'] ?? '' ) : '';
		if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
			return;
		}
		$key   = self::LINK_PREFIX . $hash;
		$proof = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $proof ) || (int) $proof['user_id'] !== $user->ID || ( $this->issued_session['user_id'] ?? 0 ) !== $user->ID ) {
			return;
		}
		if ( ! in_array( $proof['email'], self::addresses( $user->ID ), true ) ) {
			return;
		}
		$manager = \WP_Session_Tokens::get_instance( $user->ID );
		$session = $manager->get( $this->issued_session['token'] );
		if ( $session ) {
			$session[ self::PROOF ] = $proof['email'];
			$manager->update( $this->issued_session['token'], $session );
		}
	}

	/** Canonical addresses of a valid bidirectionally linked, active account. */
	private static function addresses( int $user_id ): array {
		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		if ( ! self::valid_link( $person_id, $user_id ) ) {
			return [];
		}
		return array_values(
			array_unique(
			array_filter(
			[
				self::normalize( (string) Fields::get_for_post( $person_id, 'email_1' ) ),
				self::normalize( (string) Fields::get_for_post( $person_id, 'email_2' ) ),
			]
			)
			)
			);
	}

	private static function valid_link( int $person_id, int $user_id ): bool {
		return $person_id > 0 && $user_id > 0
			&& get_post_status( $person_id ) === 'publish'
			&& ActivationService::is_person_activatable( $person_id )
			&& (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true ) === $user_id
			&& (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) === $person_id
			&& user_can( $user_id, 'read' );
	}

	private static function normalize( string $email ): string {
		$email = strtolower( trim( $email ) );
		return is_email( $email ) ? $email : '';
	}

	/** Only cookie-authenticated sessions can use mailbox proof. */
	private static function session(): array {
		$token = wp_get_session_token();
		return $token !== '' && get_current_user_id() > 0
			? ( \WP_Session_Tokens::get_instance( get_current_user_id() )->get( $token ) ?: [] )
			: [];
	}

	/** Returns proof only while the current account still belongs to that address. */
	private static function verified_email(): string {
		$email = (string) ( self::session()[ self::PROOF ] ?? '' );
		return $email !== '' && in_array( $email, self::addresses( get_current_user_id() ), true ) ? $email : '';
	}

	/** Cheap sidebar availability check; no other account names leave this method. */
	public static function available(): bool {
		foreach ( self::addresses( get_current_user_id() ) as $email ) {
			if ( count( ActivationService::persons_for_email( $email ) ) > 1 ) {
				return true;
			}
		}
		return false;
	}

	/** Names are returned only after this browser has proved mailbox possession. */
	public static function status(): array {
		$verified = self::verified_email();
		$emails   = array_values( array_filter( self::addresses( get_current_user_id() ), static fn( $email ) => count( ActivationService::persons_for_email( $email ) ) > 1 ) );
		$hint     = self::session()[ self::HINT ] ?? '';
		$email    = $verified ?: ( in_array( $hint, $emails, true ) ? $hint : ( $emails[0] ?? '' ) );
		$accounts = [];
		if ( $verified !== '' ) {
			foreach ( ActivationService::persons_for_email( $verified ) as $person_id ) {
				$user_id = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
				if ( $user_id && ! self::valid_link( $person_id, $user_id ) ) {
					continue;
				}
				$accounts[] = [
					'person_id' => $person_id,
					'name'      => get_the_title( $person_id ),
					'current'   => $user_id === get_current_user_id(),
					'active'    => $user_id > 0,
				];
			}
		}
		return [
			'available' => count( $emails ) > 0,
			'verified'  => $verified !== '',
			'email'     => $email,
			'emails'    => $emails,
			'accounts'  => $accounts,
		];
	}

	/** Send the existing named login buttons; the user explicitly requests this mail. */
	public static function request_verification( string $email ) {
		$email = self::normalize( $email );
		if ( ! self::session() || ! in_array( $email, self::addresses( get_current_user_id() ), true ) ) {
			return self::denied();
		}
		$ip = ActivationService::client_ip();
		if ( ActivationService::is_rate_limited( $email, $ip ) ) {
			return new \WP_Error( 'rondo_switch_rate_limit', 'Er zijn al meerdere e-mails aangevraagd. Probeer het later opnieuw.', [ 'status' => 429 ] );
		}
		ActivationService::record_attempt( $email, $ip );
		$sent = ActivationService::send_magic_login_email( $email, [ (int) get_user_meta( get_current_user_id(), 'rondo_linked_person_id', true ) ], home_url( '/wissel-account' ) );
		return $sent ? [ 'sent' => true ] : new \WP_Error( 'rondo_switch_mail_failed', 'De bevestigingsmail kon niet worden verstuurd. Probeer het opnieuw.', [ 'status' => 502 ] );
	}

	/** Recheck current source and target immediately before changing authentication. */
	public static function switch_account( int $person_id ) {
		$email = self::verified_email();
		if ( $email === '' || ! in_array( $person_id, ActivationService::persons_for_email( $email ), true ) ) {
			return self::denied();
		}
		$user_id = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
		if ( ! self::valid_link( $person_id, $user_id ) ) {
			return self::denied();
		}
		$user = get_userdata( $user_id );
		if ( apply_filters( 'magic_login_pre_send_login_link', null, $user ) !== null ) {
			return self::denied();
		}
		$session            = self::session();
		$expiration         = (int) $session['expiration'];
		$manager            = \WP_Session_Tokens::get_instance( $user_id );
		$token              = $manager->create( $expiration );
		$new                = $manager->get( $token );
		$new[ self::PROOF ] = $email;
		$manager->update( $token, $new );
		wp_destroy_current_session();
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		// Preserve the original session lifetime across any number of switches.
		$lifetime = static fn() => max( 1, $expiration - time() );
		add_filter( 'auth_cookie_expiration', $lifetime );
		wp_set_auth_cookie( $user_id, false, is_ssl(), $token );
		remove_filter( 'auth_cookie_expiration', $lifetime );
		do_action( 'wp_login', $user->user_login, $user );
		return [ 'redirect_url' => home_url( '/' ) ];
	}

	/** Reuse activation's identity picker after the mailbox has been confirmed. */
	public static function activation_url( int $person_id ) {
		$email = self::verified_email();
		if ( $email === '' || ! in_array( $person_id, ActivationService::persons_for_email( $email ), true ) || ActivationService::has_account( $person_id ) ) {
			return self::denied();
		}
		return [ 'redirect_url' => ActivationService::activation_url( ActivationService::create_token( $email ) ) ];
	}

	private static function denied(): \WP_Error {
		return new \WP_Error( 'rondo_switch_forbidden', 'Dit account is niet beschikbaar. Bevestig je e-mailadres opnieuw of vernieuw deze pagina.', [ 'status' => 403 ] );
	}
}
