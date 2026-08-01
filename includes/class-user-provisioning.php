<?php
/**
 * User Provisioning Service
 *
 * Creates WordPress user accounts from person records, sends branded welcome
 * emails with password-set links, manages bidirectional person↔user linking,
 * and provides configurable email template settings.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * User provisioning service class.
 *
 * No hooks registered in constructor — pure service class, instantiated
 * on-demand by REST API callbacks. PSR-4 autoloaded.
 */
class UserProvisioning {

	/**
	 * Option key for welcome email subject.
	 */
	const OPTION_EMAIL_SUBJECT = 'rondo_welcome_email_subject';

	/**
	 * Option key for welcome email body.
	 */
	const OPTION_EMAIL_BODY = 'rondo_welcome_email_body';

	/**
	 * Option key for from email address.
	 */
	const OPTION_FROM_EMAIL = 'rondo_welcome_from_email';

	/**
	 * Option key for from name.
	 */
	const OPTION_FROM_NAME = 'rondo_welcome_from_name';

	/**
	 * Post meta key: WordPress user ID linked to this person.
	 */
	const META_USER_ID = '_rondo_wp_user_id';

	/**
	 * Post meta key: Timestamp of when the welcome email was sent.
	 */
	const META_EMAIL_SENT = '_welcome_email_sent_at';

	/**
	 * User meta key: KNVB ID stored on the WordPress user.
	 */
	const META_KNVB_ID = '_rondo_knvb_id';

	/**
	 * User meta key: the member's real email address.
	 *
	 * Always set, for every provisioned user. When the address is free, it also becomes
	 * the WordPress `user_email`; when it is already claimed by another member of the
	 * same household, `user_email` gets a synthetic placeholder instead and this meta is
	 * the only record of where mail should actually go. Read it through contact_email().
	 */
	const META_CONTACT_EMAIL = 'rondo_contact_email';

	/**
	 * Domain for synthetic, undeliverable `user_email` values.
	 *
	 * `.invalid` is reserved by RFC 2606 and can never resolve, so a stray notification
	 * addressed here fails locally rather than reaching a stranger.
	 */
	const SYNTHETIC_EMAIL_DOMAIN = 'members.rondo.invalid';

	/**
	 * Custom from email for current send operation.
	 *
	 * @var string|null
	 */
	private $current_from_email = null;

	/**
	 * Custom from name for current send operation.
	 *
	 * @var string|null
	 */
	private $current_from_name = null;

	/**
	 * Provision a WordPress user account for a person.
	 *
	 * Creates a user, assigns roles, links person↔user bidirectionally,
	 * stores KNVB ID, and sends a branded welcome email.
	 *
	 * @param int  $person_id     The person post ID.
	 * @param bool $send_welcome  Send the welcome email. False for self-service activation,
	 *                            where the member already clicked a link from their inbox
	 *                            and is sent straight on to set a password.
	 * @return array|\WP_Error Result array with status, user_id, message or WP_Error.
	 */
	public function provision( int $person_id, bool $send_welcome = true ): array|\WP_Error {
		// Validate the person post.
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return new \WP_Error(
				'invalid_person',
				'Persoon niet gevonden of niet gepubliceerd.',
				[ 'status' => 404 ]
			);
		}

		// Idempotency check (PROV-05): return early if already provisioned.
		$existing_user_id = (int) get_post_meta( $person_id, self::META_USER_ID, true );
		if ( $existing_user_id ) {
			$existing_user = get_userdata( $existing_user_id );
			if ( $existing_user ) {
				return [
					'status'  => 'already_exists',
					'user_id' => $existing_user_id,
					'message' => 'Gebruiker bestaat al.',
				];
			}
			// Meta exists but user was deleted — clean up stale meta and proceed.
			delete_post_meta( $person_id, self::META_USER_ID );
		}

		// The reverse link may survive without the forward one (stale data, or a user
		// created before provisioning existed). Adopt it rather than duplicating — and
		// do so before any further validation, since the account already exists.
		$orphan_link = get_users(
			[
				'meta_key'   => 'rondo_linked_person_id',
				'meta_value' => $person_id,
				'number'     => 1,
				'fields'     => 'ID',
			]
		);
		if ( ! empty( $orphan_link ) ) {
			$existing_id = (int) $orphan_link[0];
			update_post_meta( $person_id, self::META_USER_ID, $existing_id );
			return [
				'status'  => 'already_exists',
				'user_id' => $existing_id,
				'message' => 'Gebruiker bestaat al.',
			];
		}

		// Get person's email address.
		$email = $this->get_person_email( $person_id );
		if ( ! $email ) {
			return new \WP_Error(
				'no_email',
				'Deze persoon heeft geen e-mailadres.',
				[ 'status' => 422 ]
			);
		}

		// Households share one mailbox. WordPress insists on a unique user_email, so the
		// first claimant keeps the real address and later members get an undeliverable
		// placeholder. The real address always lives in META_CONTACT_EMAIL.
		$wp_email = email_exists( $email ) ? $this->synthetic_email( $person_id ) : $email;

		// Get person name fields.
		$first_name = (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) ?: '' );
		$last_name  = (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'last_name' ) ?: '' );

		// Generate a unique username.
		$username = $this->generate_username( $first_name, $last_name );

		// Create the WordPress user.
		$user_id = wp_create_user( $username, wp_generate_password( 24, true, true ), $wp_email );
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// The address we actually send to — never the synthetic placeholder.
		update_user_meta( $user_id, self::META_CONTACT_EMAIL, $email );

		$user = new \WP_User( $user_id );

		// Set base Rondo role.
		$user->set_role( \Rondo\Core\UserRoles::ROLE_NAME );

		// Assign roles from work history Functies (Phase 204 integration).
		$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' );
		if ( is_array( $work_history ) ) {
			$assigned_roles = [];
			foreach ( $work_history as $job ) {
				$job_title = $job['job_title'] ?? '';
				if ( empty( $job_title ) ) {
					continue;
				}
				$roles = \Rondo\Config\FunctieCapabilityMap::get_roles_for_functie( $job_title );
				foreach ( $roles as $role_slug ) {
					if ( ! in_array( $role_slug, $assigned_roles, true ) ) {
						$user->add_role( $role_slug );
						$assigned_roles[] = $role_slug;
					}
				}
			}
		}

		// Bidirectional link (PROV-03).
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		update_post_meta( $person_id, self::META_USER_ID, $user_id );

		// Store KNVB ID on user (PROV-04).
		$knvb_id = \Rondo\Fields\Fields::get_for_post( $person_id, 'knvb_id' );
		if ( $knvb_id ) {
			update_user_meta( $user_id, self::META_KNVB_ID, $knvb_id );
		}

		// Set display name so user list shows a real name.
		wp_update_user(
			[
				'ID'           => $user_id,
				'display_name' => trim( $first_name . ' ' . $last_name ),
				'first_name'   => $first_name,
				'last_name'    => $last_name,
			]
		);

		// Generate password reset key with scoped 7-day expiry.
		add_filter( 'password_reset_expiration', [ $this, 'filter_reset_expiration' ] );
		$reset_key = get_password_reset_key( $user );
		remove_filter( 'password_reset_expiration', [ $this, 'filter_reset_expiration' ] );

		if ( is_wp_error( $reset_key ) ) {
			// Rollback: delete user and remove person meta.
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( $user_id );
			delete_post_meta( $person_id, self::META_USER_ID );
			return $reset_key;
		}

		if ( ! $send_welcome ) {
			return [
				'status'    => 'created',
				'user_id'   => $user_id,
				'reset_key' => $reset_key,
				'message'   => 'Account aangemaakt.',
			];
		}

		// Send welcome email (non-blocking: user is created even if email fails).
		$email_result = $this->send_welcome_email( $person_id, $user_id, $reset_key );
		if ( is_wp_error( $email_result ) ) {
			// Log the failure but do not rollback.
			error_log(
				sprintf(
					'[Rondo] UserProvisioning: welcome email failed for user %d (person %d): %s',
					$user_id,
					$person_id,
					$email_result->get_error_message()
				)
			);
		}

		return [
			'status'  => 'created',
			'user_id' => $user_id,
			'message' => 'Account aangemaakt en welkomstmail verstuurd.',
		];
	}

	/**
	 * The wp-login.php URL where a user sets their first password.
	 *
	 * @param \WP_User $user      The user.
	 * @param string   $reset_key Key from get_password_reset_key().
	 * @return string
	 */
	public static function set_password_url( \WP_User $user, string $reset_key ): string {
		return add_query_arg(
			[
				'action' => 'rp',
				'key'    => $reset_key,
				'login'  => rawurlencode( $user->user_login ),
			],
			network_site_url( 'wp-login.php', 'login' )
		);
	}

	/**
	 * Send the branded welcome email with a password-set link.
	 *
	 * @param int    $person_id The person post ID.
	 * @param int    $user_id   The WordPress user ID.
	 * @param string $reset_key The password reset key.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private function send_welcome_email( int $person_id, int $user_id, string $reset_key ): true|\WP_Error {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'user_not_found', 'Gebruiker niet gevonden.' );
		}

		// Never wp_mail() to user_email — for a household member that is a .invalid
		// placeholder and the mail would go nowhere.
		$to = self::contact_email( $user_id );
		if ( ! $to ) {
			return new \WP_Error( 'no_contact_email', 'Geen bezorgbaar e-mailadres voor deze gebruiker.' );
		}

		// Build password-set URL.
		$set_password_url = add_query_arg(
			[
				'action' => 'rp',
				'key'    => $reset_key,
				'login'  => rawurlencode( $user->user_login ),
			],
			network_site_url( 'wp-login.php', 'login' )
		);

		// Get person name for substitution.
		$first_name = (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) ?: '' );

		// Determine club name.
		$club_naam = '';
		if ( class_exists( '\Rondo\Config\ClubConfig' ) && method_exists( '\Rondo\Config\ClubConfig', 'get_club_name' ) ) {
			$club_naam = \Rondo\Config\ClubConfig::get_club_name();
		}
		if ( empty( $club_naam ) ) {
			$club_naam = get_bloginfo( 'name' );
		}

		// Get subject and body from options (with defaults).
		$subject = get_option( self::OPTION_EMAIL_SUBJECT, '' );
		if ( empty( $subject ) ) {
			$subject = $this->get_default_subject();
		}

		$body = get_option( self::OPTION_EMAIL_BODY, '' );
		if ( empty( $body ) ) {
			$body = $this->get_default_body();
		}

		// Perform {variable} substitution.
		$vars = [
			'first_name'       => $first_name,
			'email'            => $to,
			'login'            => $user->user_login,
			'set_password_url' => $set_password_url,
			'club_naam'        => $club_naam,
		];

		$subject = $this->substitute_variables( $subject, $vars );
		$body    = $this->substitute_variables( $body, $vars );

		$html_body = EmailTemplate::render(
			[
				'brand_name' => $club_naam,
				'preheader'  => $subject,
				'eyebrow'    => 'Account',
				'heading'    => $subject,
				'body_html'  => $this->is_html( $body ) ? wp_kses_post( $body ) : EmailTemplate::format_plain_text( $body ),
				'cta_url'    => $set_password_url,
				'cta_label'  => 'Wachtwoord instellen',
			]
		);

		// Store from address/name for filter callbacks.
		$this->current_from_email = get_option( self::OPTION_FROM_EMAIL, '' );
		$this->current_from_name  = get_option( self::OPTION_FROM_NAME, '' );

		// Add filters for custom from address.
		add_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
		add_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$result = wp_mail( $to, $subject, $html_body, $headers );

		// Remove filters after sending.
		remove_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
		remove_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

		// Clear stored values.
		$this->current_from_email = null;
		$this->current_from_name  = null;

		if ( ! $result ) {
			return new \WP_Error( 'send_failed', 'Welkomstmail kon niet verstuurd worden.' );
		}

		// Record when the welcome email was sent.
		update_post_meta( $person_id, self::META_EMAIL_SENT, current_time( 'Y-m-d H:i:s' ) );

		// Log to timeline.
		$comment_types = new \Rondo\Collaboration\CommentTypes();
		$comment_types->create_email_log(
			$person_id,
			[
				'template_type' => 'welcome',
				'recipient'     => $user->user_email,
				'subject'       => $subject,
				'content'       => $html_body,
			]
		);

		return true;
	}

	/**
	 * Generate a unique WordPress username from first and last name.
	 *
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @return string Unique username.
	 */
	private function generate_username( string $first, string $last ): string {
		$base = sanitize_user( strtolower( $first . '.' . $last ), true );

		if ( empty( $base ) ) {
			return 'gebruiker-' . uniqid();
		}

		$login = $base;
		$i     = 1;
		while ( username_exists( $login ) ) {
			$login = $base . $i;
			++$i;
		}

		return $login;
	}

	/**
	 * Get the current provisioning email template settings.
	 *
	 * @return array Settings array with subject, body, from_email, from_name.
	 */
	public function get_settings(): array {
		$subject    = get_option( self::OPTION_EMAIL_SUBJECT, '' );
		$body       = get_option( self::OPTION_EMAIL_BODY, '' );
		$from_email = get_option( self::OPTION_FROM_EMAIL, '' );
		$from_name  = get_option( self::OPTION_FROM_NAME, '' );

		return [
			'subject'    => ! empty( $subject ) ? $subject : $this->get_default_subject(),
			'body'       => ! empty( $body ) ? $body : $this->get_default_body(),
			'from_email' => ! empty( $from_email ) ? $from_email : get_option( 'admin_email' ),
			'from_name'  => ! empty( $from_name ) ? $from_name : get_bloginfo( 'name' ),
		];
	}

	/**
	 * Persist updated email template settings.
	 *
	 * @param array $settings Associative array of settings to update.
	 *                        Accepted keys: subject, body, from_email, from_name.
	 * @return array The updated full settings array.
	 */
	public function update_settings( array $settings ): array {
		if ( isset( $settings['subject'] ) ) {
			update_option( self::OPTION_EMAIL_SUBJECT, sanitize_text_field( $settings['subject'] ) );
		}

		if ( isset( $settings['body'] ) ) {
			update_option( self::OPTION_EMAIL_BODY, wp_kses_post( $settings['body'] ) );
		}

		if ( isset( $settings['from_email'] ) ) {
			$sanitized_email = sanitize_email( $settings['from_email'] );
			if ( ! empty( $sanitized_email ) || empty( $settings['from_email'] ) ) {
				update_option( self::OPTION_FROM_EMAIL, $sanitized_email );
			}
		}

		if ( isset( $settings['from_name'] ) ) {
			update_option( self::OPTION_FROM_NAME, sanitize_text_field( $settings['from_name'] ) );
		}

		return $this->get_settings();
	}

	/**
	 * Get the default email subject template.
	 *
	 * @return string Default Dutch subject.
	 */
	public function get_default_subject(): string {
		return 'Welkom bij {club_naam}';
	}

	/**
	 * Get the default email body template.
	 *
	 * @return string Default Dutch body template.
	 */
	public function get_default_body(): string {
		return <<<'EOT'
Beste {first_name},

Je hebt een account gekregen voor {club_naam}.

Gebruik de volgende link om je wachtwoord in te stellen en in te loggen:
{set_password_url}

Je gebruikersnaam is: {login}

Deze link is 7 dagen geldig.

Met sportieve groet,
{club_naam}
EOT;
	}

	/**
	 * Filter callback for password_reset_expiration.
	 *
	 * Sets a 7-day expiry for the welcome email password-set link.
	 *
	 * @return int Expiry in seconds.
	 */
	public function filter_reset_expiration(): int {
		return 7 * DAY_IN_SECONDS;
	}

	/**
	 * Filter callback for wp_mail_from.
	 *
	 * @param string $from_email Original from email.
	 * @return string Modified from email.
	 */
	public function filter_mail_from( $from_email ): string {
		if ( ! empty( $this->current_from_email ) ) {
			return $this->current_from_email;
		}
		return $from_email;
	}

	/**
	 * Filter callback for wp_mail_from_name.
	 *
	 * @param string $from_name Original from name.
	 * @return string Modified from name.
	 */
	public function filter_mail_from_name( $from_name ): string {
		if ( ! empty( $this->current_from_name ) ) {
			return $this->current_from_name;
		}
		return $from_name;
	}

	/**
	 * Get email address from person's fixed email fields.
	 *
	 * @param int $person_id Person post ID.
	 * @return string|null Email address or null if not found.
	 */
	/**
	 * Where mail for this user must actually go.
	 *
	 * Falls back to `user_email` for accounts provisioned before META_CONTACT_EMAIL
	 * existed — those all hold a real address, since a synthetic one is only ever
	 * written alongside the meta.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string|null The real address, or null when the user is gone.
	 */
	public static function contact_email( int $user_id ): ?string {
		$contact = (string) get_user_meta( $user_id, self::META_CONTACT_EMAIL, true );
		if ( is_email( $contact ) ) {
			return $contact;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || self::is_synthetic_email( $user->user_email ) ) {
			return null;
		}

		return $user->user_email;
	}

	/**
	 * Is this an undeliverable placeholder rather than a real address?
	 *
	 * @param string $email Address to test.
	 * @return bool
	 */
	public static function is_synthetic_email( string $email ): bool {
		return str_ends_with( strtolower( $email ), '@' . self::SYNTHETIC_EMAIL_DOMAIN );
	}

	/**
	 * A unique, undeliverable `user_email` for a member whose real address is taken.
	 *
	 * @param int $person_id Person post ID — unique by construction, so the address is too.
	 * @return string
	 */
	private function synthetic_email( int $person_id ): string {
		return sprintf( 'person-%d@%s', $person_id, self::SYNTHETIC_EMAIL_DOMAIN );
	}

	private function get_person_email( int $person_id ): ?string {
		$email = \Rondo\Fields\Fields::get_for_post( $person_id, 'email_1' );
		if ( is_email( $email ) ) {
			return $email;
		}

		$email = \Rondo\Fields\Fields::get_for_post( $person_id, 'email_2' );
		if ( is_email( $email ) ) {
			return $email;
		}

		return null;
	}

	/**
	 * Substitute {variable} placeholders in a template string.
	 *
	 * @param string $template Template with {variable} placeholders.
	 * @param array  $vars     Array of variable => value pairs.
	 * @return string Template with substituted values.
	 */
	private function is_html( string $text ): bool {
		return (bool) preg_match( '/<[a-z][\s\S]*>/i', $text );
	}

	private function substitute_variables( string $template, array $vars ): string {
		foreach ( $vars as $key => $value ) {
			$template = str_replace( '{' . $key . '}', $value, $template );
		}
		return $template;
	}
}
