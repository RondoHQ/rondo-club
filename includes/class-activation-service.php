<?php
/**
 * ActivationService
 *
 * The logic behind the public /activeren page, kept separate from its rendering so it
 * can be tested without emitting HTML.
 *
 * The security model in one line: **activation never grants access directly — it only
 * ever mails a link to the address already on file.** Someone who guesses an address
 * learns nothing (the page answers identically either way) and receives nothing (the
 * mail goes to the member, not the guesser). That is what makes email-only activation
 * acceptable for parents, who have no KNVB-ID and no birthdate on record.
 *
 * A household mailbox can activate any person on that mailbox. That is intended: it is
 * the family's own inbox.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Notifications\EmailTemplate;
use Rondo\Pages\PublicPageChrome;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActivationService {

	const ACTIVATION_FROM_EMAIL = 'ledenadministratie@svawc.nl';

	/** Transient prefix for activation tokens. */
	const TOKEN_TRANSIENT_PREFIX = 'rondo_activation_';

	/** How long an activation link stays valid. */
	const TOKEN_TTL_SECONDS = 2 * HOUR_IN_SECONDS;

	/** Transient prefix for the per-IP rate limiter. */
	const RATE_IP_PREFIX = 'rondo_act_ip_';

	/** Transient prefix for the per-address rate limiter. */
	const RATE_EMAIL_PREFIX = 'rondo_act_em_';

	/** Requests allowed per IP per window. */
	const RATE_IP_MAX = 10;

	/** Requests allowed per email address per window. */
	const RATE_EMAIL_MAX = 3;

	/** Rate-limit window. */
	const RATE_WINDOW_SECONDS = HOUR_IN_SECONDS;

	/**
	 * Every active person reachable at this address.
	 *
	 * Former members are excluded — they are read-only end to end and have nothing to
	 * log in to. Includes people who already have an account, so the token page can say
	 * "you already have one" rather than silently hiding them.
	 *
	 * @param string $email Address to match against email_1 and email_2.
	 * @return int[] Person post IDs.
	 */
	public static function persons_for_email( string $email ): array {
		if ( ! is_email( $email ) ) {
			return [];
		}

		$matches = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => 'email_1',
						'value'   => $email,
						'compare' => '=',
					],
					[
						'key'     => 'email_2',
						'value'   => $email,
						'compare' => '=',
					],
				],
			]
		);

		return array_values(
			array_filter(
				array_map( 'intval', $matches ),
				fn( $person_id ) => get_post_meta( $person_id, 'former_member', true ) !== '1'
			)
		);
	}

	/**
	 * Does this person already have an account?
	 *
	 * @param int $person_id Person post ID.
	 * @return bool
	 */
	public static function has_account( int $person_id ): bool {
		return (bool) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
	}

	/**
	 * Mint an activation token for an address and store it.
	 *
	 * Only the SHA-256 of the token is stored, so a database read cannot be replayed as
	 * a valid link.
	 *
	 * @param string $email Address the token belongs to.
	 * @return string The raw 64-character hex token, to be emailed and never persisted.
	 */
	public static function create_token( string $email ): string {
		$token = bin2hex( random_bytes( 32 ) );

		set_transient(
			self::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ),
			strtolower( $email ),
			self::TOKEN_TTL_SECONDS
		);

		return $token;
	}

	/**
	 * The address behind a token, or null when unknown or expired.
	 *
	 * @param string $token Raw token from the URL.
	 * @return string|null
	 */
	public static function email_for_token( string $token ): ?string {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}

		$email = get_transient( self::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ) );

		return is_string( $email ) && is_email( $email ) ? $email : null;
	}

	/**
	 * Burn a token so an activation link cannot be replayed.
	 *
	 * @param string $token Raw token.
	 */
	public static function consume_token( string $token ): void {
		delete_transient( self::TOKEN_TRANSIENT_PREFIX . hash( 'sha256', $token ) );
	}

	/**
	 * Has this requester exhausted their allowance?
	 *
	 * Checked before any lookup, so a flood costs one transient read. Counters are only
	 * incremented on a real attempt (see record_attempt).
	 *
	 * @param string $email Address being requested.
	 * @param string $ip    Requester IP.
	 * @return bool True when the request must be refused.
	 */
	public static function is_rate_limited( string $email, string $ip ): bool {
		$ip_hits    = (int) get_transient( self::RATE_IP_PREFIX . md5( $ip ) );
		$email_hits = (int) get_transient( self::RATE_EMAIL_PREFIX . md5( strtolower( $email ) ) );

		return $ip_hits >= self::RATE_IP_MAX || $email_hits >= self::RATE_EMAIL_MAX;
	}

	/**
	 * Count one activation request against both limiters.
	 *
	 * @param string $email Address being requested.
	 * @param string $ip    Requester IP.
	 */
	public static function record_attempt( string $email, string $ip ): void {
		$ip_key    = self::RATE_IP_PREFIX . md5( $ip );
		$email_key = self::RATE_EMAIL_PREFIX . md5( strtolower( $email ) );

		set_transient( $ip_key, ( (int) get_transient( $ip_key ) ) + 1, self::RATE_WINDOW_SECONDS );
		set_transient( $email_key, ( (int) get_transient( $email_key ) ) + 1, self::RATE_WINDOW_SECONDS );
	}

	/**
	 * The requester's IP, as far as we can tell.
	 *
	 * @return string
	 */
	public static function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return $ip !== '' ? $ip : '0.0.0.0';
	}

	/**
	 * The public URL of an activation link.
	 *
	 * @param string $token Raw token.
	 * @return string
	 */
	public static function activation_url( string $token ): string {
		return home_url( '/activeren/' . $token );
	}

	/**
	 * Mail the activation link.
	 *
	 * Called only when at least one active person matches; the caller's response to the
	 * member is identical either way.
	 *
	 * @param string $email Address on file.
	 * @param string $token Raw token.
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public static function send_activation_email( string $email, string $token ): bool {
		$branding = PublicPageChrome::branding();
		$url      = self::activation_url( $token );
		$subject  = sprintf( 'Activeer je account bij %s', $branding['name'] );

		$body = '<p>Hallo,</p>'
			. '<p>Er is een account aangevraagd voor dit e-mailadres. Klik op de knop hieronder om je account te activeren en een wachtwoord in te stellen.</p>'
			. '<p>Deze link is twee uur geldig. Heb je dit niet zelf aangevraagd, dan hoef je niets te doen — er gebeurt niets zonder dat je op de link klikt.</p>';

		$html = EmailTemplate::render(
			[
				'brand_name' => $branding['name'],
				'preheader'  => $subject,
				'eyebrow'    => 'Account',
				'heading'    => $subject,
				'body_html'  => $body,
				'cta_url'    => $url,
				'cta_label'  => 'Account activeren',
			]
		);

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', sanitize_text_field( $branding['name'] ), self::ACTIVATION_FROM_EMAIL ),
		];

		return (bool) wp_mail( $email, $subject, $html, $headers );
	}

	/**
	 * Activate one person against a token, returning the set-password URL.
	 *
	 * Re-validates everything at this point rather than trusting the earlier page: the
	 * token must still be live, and the person must still match its address, still be
	 * active, and still lack an account.
	 *
	 * @param string $token     Raw token from the URL.
	 * @param int    $person_id Person the visitor claims to be.
	 * @return string|\WP_Error Set-password URL, or an error.
	 */
	public static function activate( string $token, int $person_id ) {
		$email = self::email_for_token( $token );

		if ( $email === null ) {
			return new \WP_Error( 'invalid_token', 'Deze activatielink is verlopen of ongeldig.' );
		}

		if ( ! in_array( $person_id, self::persons_for_email( $email ), true ) ) {
			return new \WP_Error( 'person_mismatch', 'Deze persoon hoort niet bij dit e-mailadres.' );
		}

		if ( self::has_account( $person_id ) ) {
			return new \WP_Error( 'already_active', 'Voor deze persoon bestaat al een account.' );
		}

		$result = ( new UserProvisioning() )->provision( $person_id, false );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( empty( $result['reset_key'] ) ) {
			return new \WP_Error( 'no_reset_key', 'Account aangemaakt, maar er kon geen wachtwoordlink gemaakt worden.' );
		}

		// One link, one account. Burn it.
		self::consume_token( $token );

		$user = get_userdata( (int) $result['user_id'] );

		return UserProvisioning::set_password_url( $user, (string) $result['reset_key'] );
	}
}
