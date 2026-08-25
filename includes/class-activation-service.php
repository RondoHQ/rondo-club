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

use Rondo\Core\SponsorStatus;
use Rondo\Fields\Fields;
use Rondo\Notifications\EmailTemplate;
use Rondo\Pages\PublicPageChrome;
use Rondo\People\CommunicationPolicy;
use Rondo\People\ParentRelationshipService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActivationService {

	const ACTIVATION_FROM_EMAIL = 'ledenadministratie@svawc.nl';

	/** Transient prefix for activation tokens. */
	const TOKEN_TRANSIENT_PREFIX = 'rondo_activation_';

	/** How long an activation link stays valid. */
	const TOKEN_TTL_SECONDS = 2 * HOUR_IN_SECONDS;

	/** Diagnostic context retained after a token expires or is consumed. */
	const TOKEN_CONTEXT_PREFIX = 'rondo_activation_context_';

	/** Keep token diagnostics for seven days without storing the raw token. */
	const TOKEN_CONTEXT_TTL_SECONDS = 7 * DAY_IN_SECONDS;

	/** Prevent repeated refreshes of one failed link from flooding the audit log. */
	const TOKEN_LOGGED_PREFIX = 'rondo_activation_logged_';

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
	 * Former members are excluded unless they still have a current parent role or an
	 * active sponsor role. Those roles need an account even though the former member's
	 * own profile stays read-only. Includes people who already have an account, so the
	 * token page can say "you already have one" rather than hiding them.
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
				[ self::class, 'is_person_activatable' ]
			)
		);
	}

	/** Whether a person may receive a Rondo account through any activation route. */
	public static function is_person_activatable( int $person_id ): bool {
		if ( get_post_type( $person_id ) !== 'person' || ! CommunicationPolicy::may_contact( $person_id ) ) {
			return false;
		}

		return Fields::get_for_post( $person_id, 'former_member' ) !== true
			|| ( new ParentRelationshipService() )->has_current_child( $person_id )
			|| SponsorStatus::is_sponsor( $person_id );
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
		$token      = bin2hex( random_bytes( 32 ) );
		$token_hash = hash( 'sha256', $token );
		$email      = strtolower( $email );

		set_transient(
			self::TOKEN_TRANSIENT_PREFIX . $token_hash,
			$email,
			self::TOKEN_TTL_SECONDS
		);
		set_transient(
			self::TOKEN_CONTEXT_PREFIX . $token_hash,
			[
				'email'      => $email,
				'person_ids' => self::persons_for_email( $email ),
				'consumed'   => false,
			],
			self::TOKEN_CONTEXT_TTL_SECONDS
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
		$token_hash = hash( 'sha256', $token );
		delete_transient( self::TOKEN_TRANSIENT_PREFIX . $token_hash );

		$context = self::context_for_token( $token );
		if ( $context !== null ) {
			$context['consumed'] = true;
			set_transient( self::TOKEN_CONTEXT_PREFIX . $token_hash, $context, self::TOKEN_CONTEXT_TTL_SECONDS );
		}
	}

	/**
	 * Record a real expired or reused activation link without logging guessed URLs.
	 *
	 * @return int|\WP_Error|null Log post ID, insertion error, or null without context.
	 */
	public static function record_invalid_token_failure( string $token ) {
		$context = self::context_for_token( $token );
		if ( $context === null ) {
			return null;
		}

		$consumed = ! empty( $context['consumed'] );
		return self::record_token_failure(
			$token,
			$consumed ? 'activation_token_used' : 'activation_token_expired',
			$consumed ? 'De activatielink was al gebruikt.' : 'De activatielink was verlopen.',
			0
		);
	}

	/**
	 * Record a failed activation against context proven by the emailed token.
	 *
	 * @return int|\WP_Error|null Log post ID, insertion error, or null without context.
	 */
	public static function record_token_failure( string $token, string $code, string $message, int $person_id = 0 ) {
		$context = self::context_for_token( $token );
		if ( $context === null ) {
			return null;
		}

		$token_hash = hash( 'sha256', $token );
		$dedupe_key = self::TOKEN_LOGGED_PREFIX . hash( 'sha256', $token_hash . '|' . sanitize_key( $code ) );
		if ( get_transient( $dedupe_key ) ) {
			return null;
		}

		$person_ids = array_map( 'intval', (array) ( $context['person_ids'] ?? [] ) );
		if ( $person_id > 0 && in_array( $person_id, $person_ids, true ) ) {
			$person_ids = [ $person_id ];
		}

		$result = ActivationLog::record_failure(
			$code,
			$message,
			$person_ids,
			(string) ( $context['email'] ?? '' )
		);
		if ( ! is_wp_error( $result ) ) {
			set_transient( $dedupe_key, 1, HOUR_IN_SECONDS );
		}

		return $result;
	}

	/** Return diagnostic context for a genuine activation token. */
	private static function context_for_token( string $token ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}

		$context = get_transient( self::TOKEN_CONTEXT_PREFIX . hash( 'sha256', $token ) );
		return is_array( $context ) && is_email( $context['email'] ?? '' ) ? $context : null;
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

		$sent = (bool) wp_mail( $email, $subject, $html, self::email_headers( $branding['name'] ) );
		if ( ! $sent ) {
			ActivationLog::record_failure(
				'activation_email_failed',
				'De e-mail met de activatielink kon niet worden verzonden.',
				self::persons_for_email( $email ),
				$email,
				'activation_email'
			);
		}

		return $sent;
	}

	/**
	 * Send Magic Login links for the existing accounts behind an address.
	 *
	 * A shared household address receives one message with a named button per account.
	 * Token creation and authentication remain owned by the Magic Login plugin.
	 *
	 * @param string $email      Address on file.
	 * @param int[]  $person_ids People matched to the address.
	 * @return bool Whether wp_mail() accepted the message.
	 */
	public static function send_magic_login_email( string $email, array $person_ids ): bool {
		$branding    = PublicPageChrome::branding();
		$login_links = self::magic_login_links( $person_ids );

		if ( empty( $login_links ) ) {
			return false;
		}

		$multiple = count( $login_links ) > 1;
		$subject  = sprintf( 'Log in bij %s', $branding['name'] );
		$body     = $multiple
			? '<p>Voor dit e-mailadres bestaan meerdere accounts. Kies hieronder met welk account je wilt inloggen.</p>'
			: '<p>Voor dit e-mailadres bestaat al een account. Met de knop hieronder log je veilig in zonder wachtwoord.</p>';

		foreach ( $login_links as $login_link ) {
			$label = $multiple ? 'Inloggen als ' . $login_link['name'] : 'Direct inloggen';
			$body .= EmailTemplate::render_cta_button( $login_link['url'], $label, $branding['accent_color'] );
		}

		$body .= '<p style="margin:24px 0 0;">Heb je dit niet zelf aangevraagd? Dan hoef je niets te doen.</p>';

		$html = EmailTemplate::render(
			[
				'brand_name'    => $branding['name'],
				'preheader'     => $subject,
				'eyebrow'       => 'Account',
				'heading'       => $subject,
				'body_html'     => $body,
				'accent_color'  => $branding['accent_color'],
				'support_email' => self::ACTIVATION_FROM_EMAIL,
			]
		);

		return (bool) wp_mail( $email, $subject, $html, self::email_headers( $branding['name'] ) );
	}

	/**
	 * Send one household email containing existing logins and an activation choice.
	 *
	 * @param string $email               Address on file.
	 * @param int[]  $existing_person_ids People who already have accounts.
	 * @param string $activation_token    Identity-picker token.
	 * @return bool Whether wp_mail() accepted the message.
	 */
	private static function send_household_access_email( string $email, array $existing_person_ids, string $activation_token ): bool {
		$branding       = PublicPageChrome::branding();
		$login_links    = self::magic_login_links( $existing_person_ids );
		$activation_url = self::activation_url( $activation_token );
		if ( empty( $login_links ) ) {
			return self::send_activation_email( $email, $activation_token );
		}

		$subject = sprintf( 'Log in of activeer je account bij %s', $branding['name'] );
		$body    = '<p>Voor dit e-mailadres bestaan al één of meer accounts. Kies hieronder met welk account je wilt inloggen.</p>';
		foreach ( $login_links as $login_link ) {
			$body .= EmailTemplate::render_cta_button(
				$login_link['url'],
				'Inloggen als ' . $login_link['name'],
				$branding['accent_color']
			);
		}
		$body .= '<p style="margin:24px 0 12px;">Wil je een account activeren voor iemand anders op dit e-mailadres? Kies dan eerst de juiste persoon.</p>';
		$body .= EmailTemplate::render_cta_button( $activation_url, 'Account activeren', $branding['accent_color'] );
		$body .= '<p style="margin:24px 0 0;">Heb je dit niet zelf aangevraagd? Dan hoef je niets te doen.</p>';

		$html = EmailTemplate::render(
			[
				'brand_name'    => $branding['name'],
				'preheader'     => $subject,
				'eyebrow'       => 'Account',
				'heading'       => $subject,
				'body_html'     => $body,
				'accent_color'  => $branding['accent_color'],
				'support_email' => self::ACTIVATION_FROM_EMAIL,
			]
		);

		$sent = (bool) wp_mail( $email, $subject, $html, self::email_headers( $branding['name'] ) );
		if ( ! $sent ) {
			ActivationLog::record_failure(
				'household_access_email_failed',
				'De e-mail met de inlog- en activatielinks kon niet worden verzonden.',
				self::persons_for_email( $email ),
				$email,
				'household_access_email'
			);
		}

		return $sent;
	}

	/**
	 * Build named Magic Login links for valid person-account pairs.
	 *
	 * @param int[] $person_ids Person IDs.
	 * @return array<int,array{name:string,url:string}>
	 */
	private static function magic_login_links( array $person_ids ): array {
		$login_links = [];

		foreach ( $person_ids as $person_id ) {
			$user_id = (int) get_post_meta( (int) $person_id, UserProvisioning::META_USER_ID, true );
			$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$login_url = self::magic_login_url_for_user( $user );
			if ( $login_url === '' ) {
				continue;
			}

			$login_links[] = [
				'name' => get_the_title( (int) $person_id ),
				'url'  => $login_url,
			];
		}

		return $login_links;
	}

	/**
	 * Send the appropriate email after a Magic Login form submission.
	 *
	 * A single adult identity can be provisioned without an extra choice: mailbox
	 * possession is still the credential, and the newly created account is only
	 * reachable through the link sent to that mailbox. Youth and household addresses
	 * keep using the activation picker because Rondo must not guess which member or
	 * guardian needs the account.
	 *
	 * Unknown addresses and former members without a current parent role deliberately
	 * do nothing. The HTTP response has already been made generic by
	 * MagicLoginActivation before this method runs.
	 *
	 * @param string $email Submitted email address.
	 */
	public static function send_for_magic_login_request( string $email ): void {
		$persons = self::persons_for_email( $email );
		if ( empty( $persons ) ) {
			return;
		}

		$available = array_values(
			array_filter(
				$persons,
				static fn( int $person_id ): bool => ! self::has_account( $person_id )
			)
		);

		if ( empty( $available ) ) {
			self::send_magic_login_email( $email, $persons );
			return;
		}

		$can_provision_directly = count( $persons ) === 1
			&& count( $available ) === 1
			&& ! GuardianAccountService::is_youth_person( $available[0] );

		if ( ! $can_provision_directly ) {
			$token               = self::create_token( $email );
			$existing_person_ids = array_values( array_diff( $persons, $available ) );
			if ( empty( $existing_person_ids ) ) {
				self::send_activation_email( $email, $token );
			} else {
				self::send_household_access_email( $email, $existing_person_ids, $token );
			}
			return;
		}

		$result = ( new UserProvisioning() )->provision( $available[0], false );
		if ( is_wp_error( $result ) ) {
			ActivationLog::record_failure(
				$result->get_error_code(),
				$result->get_error_message(),
				[ $available[0] ],
				$email,
				'magic_login_activation'
			);
			error_log(
				sprintf(
					'[Rondo] Magic Login activation failed for person %d: %s',
					$available[0],
					$result->get_error_message()
				)
			);
			return;
		}

		if ( ! self::send_magic_login_email( $email, $persons ) ) {
			ActivationLog::record_failure(
				'activation_login_email_failed',
				'Het account is aangemaakt, maar de e-mail met de inloglink kon niet worden verzonden.',
				[ $available[0] ],
				$email,
				'magic_login_activation'
			);
		}
	}

	/**
	 * Shared sender headers for activation and Magic Login mail.
	 *
	 * @param string $brand_name Club display name.
	 * @return string[]
	 */
	private static function email_headers( string $brand_name ): array {
		return [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', sanitize_text_field( $brand_name ), self::ACTIVATION_FROM_EMAIL ),
		];
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

		return self::activate_person( $token, $person_id, false );
	}

	/**
	 * Activate a parent through the address stored on a youth person.
	 *
	 * An existing parent record is used immediately. If none matches and a
	 * Sportlink parent slot is available, a parent person and relationship are
	 * created before provisioning the account. The former temporary child-linked
	 * identity remains the fallback when the parent cannot be linked safely.
	 *
	 * @return string|\WP_Error Set-password URL or an error.
	 */
	public static function activate_guardian( string $token, int $child_id, string $guardian_name ) {
		$guardian_name = trim( sanitize_text_field( $guardian_name ) );
		if ( mb_strlen( $guardian_name ) < 2 || mb_strlen( $guardian_name ) > 120 ) {
			return new \WP_Error( 'invalid_guardian_name', 'Vul je volledige naam in.' );
		}
		if ( ! GuardianAccountService::is_youth_person( $child_id ) ) {
			return new \WP_Error( 'invalid_guardian_child', 'Deze persoon is geen jeugdlid.' );
		}

		$email = self::email_for_token( $token );
		if ( $email === null ) {
			return new \WP_Error( 'invalid_token', 'Deze activatielink is verlopen of ongeldig.' );
		}
		$household_ids = self::persons_for_email( $email );
		if ( ! in_array( $child_id, $household_ids, true ) ) {
			return new \WP_Error( 'person_mismatch', 'Deze persoon hoort niet bij dit e-mailadres.' );
		}
		$youth_household_ids = array_values(
			array_filter(
				$household_ids,
				static fn( int $person_id ): bool => GuardianAccountService::is_youth_person( $person_id )
			)
		);

		$parent = ( new ParentRelationshipService() )->prepare_for_activation( $child_id, $email, $guardian_name, $youth_household_ids );
		if ( ! is_wp_error( $parent ) ) {
			return self::activate_person( $token, (int) $parent['parent_id'], true );
		}

		$fallback_errors = [
			'rondo_parent_child_without_knvb_id',
			'rondo_parent_type_missing',
			'rondo_parent_slots_full',
			'rondo_parent_activation_ambiguous',
			'rondo_parent_email_exists',
		];
		if ( ! in_array( $parent->get_error_code(), $fallback_errors, true ) ) {
			return $parent;
		}

		$url = self::activate( $token, $child_id );
		if ( is_wp_error( $url ) ) {
			return $url;
		}

		$user_id = (int) get_post_meta( $child_id, UserProvisioning::META_USER_ID, true );
		$claim   = GuardianAccountService::claim( $user_id, $child_id, $guardian_name );
		if ( is_wp_error( $claim ) ) {
			return $claim;
		}

		return $url;
	}

	/**
	 * Provision a prevalidated person or open its existing account.
	 *
	 * @param bool $allow_existing Whether an existing account may be opened with Magic Login.
	 * @return string|\WP_Error
	 */
	private static function activate_person( string $token, int $person_id, bool $allow_existing ) {
		if ( self::has_account( $person_id ) ) {
			if ( ! $allow_existing ) {
				return new \WP_Error( 'already_active', 'Voor deze persoon bestaat al een account.' );
			}
			return self::existing_account_url( $token, $person_id );
		}

		$result = ( new UserProvisioning() )->provision( $person_id, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ( $result['status'] ?? '' ) === 'already_exists' && $allow_existing ) {
			return self::existing_account_url( $token, $person_id );
		}
		if ( empty( $result['reset_key'] ) ) {
			return new \WP_Error( 'no_reset_key', 'Account aangemaakt, maar er kon geen wachtwoordlink gemaakt worden.' );
		}

		self::consume_token( $token );
		$user = get_userdata( (int) $result['user_id'] );
		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error( 'invalid_user', 'Het aangemaakte account kon niet worden geopend.' );
		}

		return UserProvisioning::set_password_url( $user, (string) $result['reset_key'] );
	}

	/** Return a one-time login URL for an already provisioned parent account. */
	private static function existing_account_url( string $token, int $person_id ) {
		$user_id = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error( 'invalid_user', 'Het bestaande ouderaccount kon niet worden geopend.' );
		}

		$login_url = self::magic_login_url_for_user( $user );
		if ( $login_url === '' ) {
			return new \WP_Error( 'magic_login_unavailable', 'Voor deze ouder/verzorger bestaat al een account. Vraag een nieuwe inloglink aan.' );
		}

		self::consume_token( $token );
		return $login_url;
	}

	/** Create a Magic Login URL while respecting the plugin's failsafe hooks. */
	private static function magic_login_url_for_user( \WP_User $user ): string {
		if ( apply_filters( 'magic_login_pre_send_login_link', null, $user ) !== null ) {
			return '';
		}

		$login_url = (string) apply_filters( 'rondo_activation_magic_login_url', '', $user );
		if ( $login_url === '' && function_exists( '\\MagicLogin\\Utils\\create_login_link' ) ) {
			$login_url = (string) \MagicLogin\Utils\create_login_link( $user, 'email', home_url( '/' ) );
		}
		if ( $login_url !== '' ) {
			do_action( 'magic_login_send_login_link', $user );
		}
		return $login_url;
	}
}
