<?php
/**
 * Club Configuration Service
 *
 * Handles club-wide configuration settings storage and retrieval using the WordPress Options API.
 *
 * @package Rondo\Config
 */

namespace Rondo\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Club Configuration static utility class
 */
class ClubConfig {

	/**
	 * Option key for club name
	 */
	const OPTION_CLUB_NAME = 'rondo_club_name';

	/**
	 * Option key for the member-facing volunteer signup information block.
	 */
	const OPTION_VOLUNTEER_SIGNUP_INFO = 'rondo_volunteer_signup_info';

	/**
	 * Option key for the month-day on which the second half of the season opens
	 * for volunteer signup.
	 */
	const OPTION_VOLUNTEER_SECOND_HALF_OPENS = 'rondo_volunteer_second_half_opens';

	/**
	 * Option key for the IVA approval email subject template.
	 */
	const OPTION_IVA_APPROVAL_EMAIL_SUBJECT = 'rondo_iva_approval_email_subject';

	/**
	 * Option key for the IVA approval email body template.
	 */
	const OPTION_IVA_APPROVAL_EMAIL_BODY = 'rondo_iva_approval_email_body';

	/**
	 * Option key for FreeScout URL
	 */
	const OPTION_FREESCOUT_URL = 'rondo_freescout_url';

	/**
	 * Option key for FreeScout API key
	 */
	const OPTION_FREESCOUT_API_KEY = 'rondo_freescout_api_key';

	/**
	 * Option key for Lettermint project API token.
	 */
	const OPTION_LETTERMINT_API_TOKEN = \Rondo\Notifications\LettermintConfig::OPTION_API_TOKEN;

	/**
	 * Option key for Lettermint team API token.
	 */
	const OPTION_LETTERMINT_TEAM_API_TOKEN = \Rondo\Notifications\LettermintConfig::OPTION_TEAM_API_TOKEN;

	/**
	 * Option key for Lettermint project ID.
	 */
	const OPTION_LETTERMINT_PROJECT_ID = 'rondo_lettermint_project_id';

	/**
	 * Option key for Lettermint route ID.
	 */
	const OPTION_LETTERMINT_ROUTE_ID = \Rondo\Notifications\LettermintConfig::OPTION_ROUTE_ID;

	/**
	 * Option key for Lettermint default from email.
	 */
	const OPTION_LETTERMINT_FROM_EMAIL = \Rondo\Notifications\LettermintConfig::OPTION_FROM_EMAIL;

	/**
	 * Option key for Lettermint default from name.
	 */
	const OPTION_LETTERMINT_FROM_NAME = \Rondo\Notifications\LettermintConfig::OPTION_FROM_NAME;

	/**
	 * Option key for Lettermint webhook secret.
	 */
	const OPTION_LETTERMINT_WEBHOOK_SECRET = \Rondo\Notifications\LettermintConfig::OPTION_WEBHOOK_SECRET;

	/**
	 * Option key for the last created Lettermint webhook ID.
	 */
	const OPTION_LETTERMINT_WEBHOOK_ID = 'rondo_lettermint_webhook_id';

	/**
	 * Option key for Lettermint email verification subject template.
	 */
	const OPTION_LETTERMINT_VERIFICATION_EMAIL_SUBJECT = 'rondo_lettermint_verification_email_subject';

	/**
	 * Option key for Lettermint email verification body template.
	 */
	const OPTION_LETTERMINT_VERIFICATION_EMAIL_BODY = 'rondo_lettermint_verification_email_body';

	/**
	 * Option key for Lettermint verification email from address.
	 */
	const OPTION_LETTERMINT_VERIFICATION_FROM_EMAIL = 'rondo_lettermint_verification_from_email';

	/**
	 * Option key for Lettermint verification email from name.
	 */
	const OPTION_LETTERMINT_VERIFICATION_FROM_NAME = 'rondo_lettermint_verification_from_name';

	/**
	 * Default configuration values
	 *
	 * @var array<string, string>
	 */
	const DEFAULTS = [
		'club_name'                             => '',
		'volunteer_signup_info'                 => '',
		'volunteer_second_half_opens'           => '11-01',
		'iva_approval_email_subject'            => 'Je IVA-certificaat is goedgekeurd',
		'iva_approval_email_body'               => "Hoi {first_name},\n\nJe IVA-certificaat is goedgekeurd. Je kunt je nu ook inschrijven voor inschrijftaken waarvoor een geldig IVA-certificaat nodig is.",
		'freescout_url'                         => '',
		'freescout_api_key'                     => '',
		'lettermint_api_token'                  => '',
		'lettermint_team_api_token'             => '',
		'lettermint_project_id'                 => '',
		'lettermint_route_id'                   => '',
		'lettermint_from_email'                 => '',
		'lettermint_from_name'                  => '',
		'lettermint_webhook_secret'             => '',
		'lettermint_webhook_id'                 => '',
		'lettermint_verification_email_subject' => '[Rondo Club] Controle e-mailadres',
		'lettermint_verification_email_body'    => "Beste {name},\n\nWe krijgen een foutmelding op e-mails naar {email}.\nWil je controleren of dit e-mailadres nog klopt en op deze mail reageren?\n\nAlvast bedankt,\n{sender_name}",
		'lettermint_verification_from_email'    => '',
		'lettermint_verification_from_name'     => '',
	];

	/**
	 * Get club name
	 *
	 * @return string The club name (empty string if not configured)
	 */
	public static function get_club_name(): string {
		return get_option( self::OPTION_CLUB_NAME, self::DEFAULTS['club_name'] );
	}

	/**
	 * Month-day (m-d) on which the second half of the season opens for signup.
	 *
	 * Stored as a month-day rather than a full date so it holds every season
	 * without an annual edit. Validation lives in ShiftSignupWindow, which is
	 * also the only place that resolves it against a season.
	 */
	public static function get_volunteer_second_half_opens(): string {
		return (string) get_option(
			self::OPTION_VOLUNTEER_SECOND_HALF_OPENS,
			self::DEFAULTS['volunteer_second_half_opens']
		);
	}

	/**
	 * Set the month-day on which the second half opens. Invalid input is ignored.
	 */
	public static function update_volunteer_second_half_opens( string $month_day ): bool {
		$clean = \Rondo\Volunteer\ShiftSignupWindow::sanitize_month_day( $month_day );

		if ( $clean === null ) {
			return false;
		}

		return update_option( self::OPTION_VOLUNTEER_SECOND_HALF_OPENS, $clean );
	}

	/**
	 * Get the member-facing volunteer signup information block.
	 *
	 * @return string Sanitized HTML, or an empty string when not configured.
	 */
	public static function get_volunteer_signup_info(): string {
		return wp_kses_post(
			(string) get_option(
				self::OPTION_VOLUNTEER_SIGNUP_INFO,
				self::DEFAULTS['volunteer_signup_info']
			)
		);
	}

	/**
	 * Get the IVA approval email subject template.
	 *
	 * @return string
	 */
	public static function get_iva_approval_email_subject(): string {
		$subject = sanitize_text_field(
			(string) get_option(
				self::OPTION_IVA_APPROVAL_EMAIL_SUBJECT,
				self::DEFAULTS['iva_approval_email_subject']
			)
		);

		return $subject !== '' ? $subject : self::DEFAULTS['iva_approval_email_subject'];
	}

	/**
	 * Get the IVA approval email body template.
	 *
	 * @return string
	 */
	public static function get_iva_approval_email_body(): string {
		$body = sanitize_textarea_field(
			(string) get_option(
				self::OPTION_IVA_APPROVAL_EMAIL_BODY,
				self::DEFAULTS['iva_approval_email_body']
			)
		);

		return trim( $body ) !== '' ? $body : self::DEFAULTS['iva_approval_email_body'];
	}

	/**
	 * Get FreeScout URL
	 *
	 * @return string The FreeScout URL (empty string if not configured)
	 */
	public static function get_freescout_url(): string {
		return get_option( self::OPTION_FREESCOUT_URL, self::DEFAULTS['freescout_url'] );
	}

	/**
	 * Get FreeScout API key.
	 *
	 * @return string The FreeScout API key (empty string if not configured)
	 */
	public static function get_freescout_api_key(): string {
		return get_option( self::OPTION_FREESCOUT_API_KEY, self::DEFAULTS['freescout_api_key'] );
	}

	/**
	 * Check whether a FreeScout API key is configured.
	 *
	 * @return bool True when a key exists, false otherwise.
	 */
	public static function has_freescout_api_key(): bool {
		return trim( self::get_freescout_api_key() ) !== '';
	}

	/**
	 * Get Lettermint route ID.
	 *
	 * @return string
	 */
	public static function get_lettermint_route_id(): string {
		return get_option( self::OPTION_LETTERMINT_ROUTE_ID, self::DEFAULTS['lettermint_route_id'] );
	}

	/**
	 * Get Lettermint default from email.
	 *
	 * @return string
	 */
	public static function get_lettermint_from_email(): string {
		return sanitize_email( (string) get_option( self::OPTION_LETTERMINT_FROM_EMAIL, self::DEFAULTS['lettermint_from_email'] ) );
	}

	/**
	 * Get Lettermint default from name.
	 *
	 * @return string
	 */
	public static function get_lettermint_from_name(): string {
		return sanitize_text_field( (string) get_option( self::OPTION_LETTERMINT_FROM_NAME, self::DEFAULTS['lettermint_from_name'] ) );
	}

	/**
	 * Get Lettermint project ID.
	 *
	 * @return string
	 */
	public static function get_lettermint_project_id(): string {
		return get_option( self::OPTION_LETTERMINT_PROJECT_ID, self::DEFAULTS['lettermint_project_id'] );
	}

	/**
	 * Get Lettermint webhook ID.
	 *
	 * @return string
	 */
	public static function get_lettermint_webhook_id(): string {
		return get_option( self::OPTION_LETTERMINT_WEBHOOK_ID, self::DEFAULTS['lettermint_webhook_id'] );
	}

	/**
	 * Get Lettermint verification email subject template.
	 *
	 * @return string
	 */
	public static function get_lettermint_verification_email_subject(): string {
		return sanitize_text_field(
			(string) get_option(
				self::OPTION_LETTERMINT_VERIFICATION_EMAIL_SUBJECT,
				self::DEFAULTS['lettermint_verification_email_subject']
			)
		);
	}

	/**
	 * Get Lettermint verification email body template.
	 *
	 * @return string
	 */
	public static function get_lettermint_verification_email_body(): string {
		return (string) get_option(
			self::OPTION_LETTERMINT_VERIFICATION_EMAIL_BODY,
			self::DEFAULTS['lettermint_verification_email_body']
		);
	}

	/**
	 * Get Lettermint verification email from address.
	 *
	 * @return string
	 */
	public static function get_lettermint_verification_from_email(): string {
		return sanitize_email(
			(string) get_option(
				self::OPTION_LETTERMINT_VERIFICATION_FROM_EMAIL,
				self::DEFAULTS['lettermint_verification_from_email']
			)
		);
	}

	/**
	 * Get Lettermint verification email from name.
	 *
	 * @return string
	 */
	public static function get_lettermint_verification_from_name(): string {
		return sanitize_text_field(
			(string) get_option(
				self::OPTION_LETTERMINT_VERIFICATION_FROM_NAME,
				self::DEFAULTS['lettermint_verification_from_name']
			)
		);
	}

	/**
	 * Check whether a Lettermint project API token is configured.
	 *
	 * @return bool
	 */
	public static function has_lettermint_api_token(): bool {
		return trim( (string) get_option( self::OPTION_LETTERMINT_API_TOKEN, self::DEFAULTS['lettermint_api_token'] ) ) !== '';
	}

	/**
	 * Check whether a Lettermint team API token is configured.
	 *
	 * @return bool
	 */
	public static function has_lettermint_team_api_token(): bool {
		return trim( (string) get_option( self::OPTION_LETTERMINT_TEAM_API_TOKEN, self::DEFAULTS['lettermint_team_api_token'] ) ) !== '';
	}

	/**
	 * Check whether a Lettermint webhook secret is configured.
	 *
	 * @return bool
	 */
	public static function has_lettermint_webhook_secret(): bool {
		return trim( (string) get_option( self::OPTION_LETTERMINT_WEBHOOK_SECRET, self::DEFAULTS['lettermint_webhook_secret'] ) ) !== '';
	}

	/**
	 * Get all configuration settings
	 *
	 * @return array<string, string|bool> Array of all configuration settings
	 */
	public static function get_all_settings(): array {
		$webhook_path = 'rondo/v1/lettermint/webhook';

		return [
			'club_name'                             => self::get_club_name(),
			'volunteer_signup_info'                 => self::get_volunteer_signup_info(),
			'volunteer_second_half_opens'           => self::get_volunteer_second_half_opens(),
			'iva_approval_email_subject'            => self::get_iva_approval_email_subject(),
			'iva_approval_email_body'               => self::get_iva_approval_email_body(),
			'freescout_url'                         => self::get_freescout_url(),
			'freescout_has_api_key'                 => self::has_freescout_api_key(),
			'lettermint_project_id'                 => self::get_lettermint_project_id(),
			'lettermint_route_id'                   => self::get_lettermint_route_id(),
			'lettermint_from_email'                 => self::get_lettermint_from_email(),
			'lettermint_from_name'                  => self::get_lettermint_from_name(),
			'lettermint_webhook_id'                 => self::get_lettermint_webhook_id(),
			'lettermint_verification_email_subject' => self::get_lettermint_verification_email_subject(),
			'lettermint_verification_email_body'    => self::get_lettermint_verification_email_body(),
			'lettermint_verification_from_email'    => self::get_lettermint_verification_from_email(),
			'lettermint_verification_from_name'     => self::get_lettermint_verification_from_name(),
			'lettermint_has_api_token'              => self::has_lettermint_api_token(),
			'lettermint_has_team_api_token'         => self::has_lettermint_team_api_token(),
			'lettermint_has_webhook_secret'         => self::has_lettermint_webhook_secret(),
			'lettermint_webhook_url'                => rest_url( $webhook_path ),
		];
	}

	/**
	 * Update club name
	 *
	 * @param string $name The club name to set.
	 * @return bool True on success, false on failure
	 */
	public static function update_club_name( string $name ): bool {
		$sanitized = sanitize_text_field( $name );
		return update_option( self::OPTION_CLUB_NAME, $sanitized );
	}

	/**
	 * Update the member-facing volunteer signup information block.
	 *
	 * @param string $html Information block HTML.
	 * @return bool True on success, false on failure.
	 */
	public static function update_volunteer_signup_info( string $html ): bool {
		return update_option( self::OPTION_VOLUNTEER_SIGNUP_INFO, wp_kses_post( $html ) );
	}

	/**
	 * Update the IVA approval email subject template.
	 *
	 * @param string $subject Subject template.
	 * @return bool True on success, false on failure.
	 */
	public static function update_iva_approval_email_subject( string $subject ): bool {
		return update_option( self::OPTION_IVA_APPROVAL_EMAIL_SUBJECT, sanitize_text_field( $subject ) );
	}

	/**
	 * Update the IVA approval email body template.
	 *
	 * @param string $body Body template.
	 * @return bool True on success, false on failure.
	 */
	public static function update_iva_approval_email_body( string $body ): bool {
		return update_option( self::OPTION_IVA_APPROVAL_EMAIL_BODY, sanitize_textarea_field( $body ) );
	}

	/**
	 * Update FreeScout URL
	 *
	 * @param string $url The FreeScout URL to set.
	 * @return bool True on success, false on failure
	 */
	public static function update_freescout_url( string $url ): bool {
		$sanitized = esc_url_raw( $url );
		return update_option( self::OPTION_FREESCOUT_URL, $sanitized );
	}

	/**
	 * Update FreeScout API key.
	 *
	 * @param string $api_key The FreeScout API key to set.
	 * @return bool True on success, false on failure
	 */
	public static function update_freescout_api_key( string $api_key ): bool {
		$sanitized = sanitize_text_field( $api_key );
		return update_option( self::OPTION_FREESCOUT_API_KEY, $sanitized );
	}

	/**
	 * Update Lettermint project API token.
	 *
	 * @param string $api_token Token.
	 * @return bool
	 */
	public static function update_lettermint_api_token( string $api_token ): bool {
		$sanitized = sanitize_text_field( $api_token );
		return update_option( self::OPTION_LETTERMINT_API_TOKEN, $sanitized );
	}

	/**
	 * Update Lettermint team API token.
	 *
	 * @param string $api_token Token.
	 * @return bool
	 */
	public static function update_lettermint_team_api_token( string $api_token ): bool {
		$sanitized = sanitize_text_field( $api_token );
		return update_option( self::OPTION_LETTERMINT_TEAM_API_TOKEN, $sanitized );
	}

	/**
	 * Update Lettermint project ID.
	 *
	 * @param string $project_id Project ID.
	 * @return bool
	 */
	public static function update_lettermint_project_id( string $project_id ): bool {
		$sanitized = sanitize_text_field( $project_id );
		return update_option( self::OPTION_LETTERMINT_PROJECT_ID, $sanitized );
	}

	/**
	 * Update Lettermint route ID.
	 *
	 * @param string $route_id Route ID.
	 * @return bool
	 */
	public static function update_lettermint_route_id( string $route_id ): bool {
		$sanitized = sanitize_text_field( $route_id );
		return update_option( self::OPTION_LETTERMINT_ROUTE_ID, $sanitized );
	}

	/**
	 * Update Lettermint default from email.
	 *
	 * @param string $email Email.
	 * @return bool
	 */
	public static function update_lettermint_from_email( string $email ): bool {
		$sanitized = sanitize_email( $email );
		return update_option( self::OPTION_LETTERMINT_FROM_EMAIL, $sanitized );
	}

	/**
	 * Update Lettermint default from name.
	 *
	 * @param string $name Name.
	 * @return bool
	 */
	public static function update_lettermint_from_name( string $name ): bool {
		$sanitized = sanitize_text_field( $name );
		return update_option( self::OPTION_LETTERMINT_FROM_NAME, $sanitized );
	}

	/**
	 * Update Lettermint webhook secret.
	 *
	 * @param string $secret Secret.
	 * @return bool
	 */
	public static function update_lettermint_webhook_secret( string $secret ): bool {
		$sanitized = sanitize_text_field( $secret );
		return update_option( self::OPTION_LETTERMINT_WEBHOOK_SECRET, $sanitized );
	}

	/**
	 * Update Lettermint webhook ID.
	 *
	 * @param string $webhook_id Webhook ID.
	 * @return bool
	 */
	public static function update_lettermint_webhook_id( string $webhook_id ): bool {
		$sanitized = sanitize_text_field( $webhook_id );
		return update_option( self::OPTION_LETTERMINT_WEBHOOK_ID, $sanitized );
	}

	/**
	 * Update Lettermint verification email subject template.
	 *
	 * @param string $subject Subject template.
	 * @return bool
	 */
	public static function update_lettermint_verification_email_subject( string $subject ): bool {
		$sanitized = sanitize_text_field( $subject );
		return update_option( self::OPTION_LETTERMINT_VERIFICATION_EMAIL_SUBJECT, $sanitized );
	}

	/**
	 * Update Lettermint verification email body template.
	 *
	 * @param string $body Body template.
	 * @return bool
	 */
	public static function update_lettermint_verification_email_body( string $body ): bool {
		$sanitized = wp_kses_post( $body );
		return update_option( self::OPTION_LETTERMINT_VERIFICATION_EMAIL_BODY, $sanitized );
	}

	/**
	 * Update Lettermint verification email from address.
	 *
	 * @param string $email From address.
	 * @return bool
	 */
	public static function update_lettermint_verification_from_email( string $email ): bool {
		$sanitized = sanitize_email( $email );
		return update_option( self::OPTION_LETTERMINT_VERIFICATION_FROM_EMAIL, $sanitized );
	}

	/**
	 * Update Lettermint verification email from name.
	 *
	 * @param string $name From name.
	 * @return bool
	 */
	public static function update_lettermint_verification_from_name( string $name ): bool {
		$sanitized = sanitize_text_field( $name );
		return update_option( self::OPTION_LETTERMINT_VERIFICATION_FROM_NAME, $sanitized );
	}
}
