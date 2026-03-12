<?php
/**
 * VOG Email Service
 *
 * Handles VOG (Verklaring Omtrent Gedrag) email sending and settings management.
 *
 * @package Rondo\VOG
 */

namespace Rondo\VOG;

use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * VOG Email service class
 */
class VOGEmail {

	/**
	 * Option key for from email address
	 */
	const OPTION_FROM_EMAIL = 'rondo_vog_from_email';

	/**
	 * Option key for from name
	 */
	const OPTION_FROM_NAME = 'rondo_vog_from_name';

	/**
	 * Option key for new volunteer template
	 */
	const OPTION_TEMPLATE_NEW = 'rondo_vog_template_new';

	/**
	 * Option key for renewal template
	 */
	const OPTION_TEMPLATE_RENEWAL = 'rondo_vog_template_renewal';

	/**
	 * Option key for exempt commissies (commissies that don't require VOG)
	 */
	const OPTION_EXEMPT_COMMISSIES = 'rondo_vog_exempt_commissies';

	/**
	 * Option key for teams exempt from discipline case charging
	 */
	const OPTION_EXEMPT_DISCIPLINE_TEAMS = 'rondo_vog_exempt_discipline_teams';

	/**
	 * Option key for new volunteer reminder template
	 */
	const OPTION_REMINDER_TEMPLATE_NEW = 'rondo_vog_reminder_template_new';

	/**
	 * Option key for renewal reminder template
	 */
	const OPTION_REMINDER_TEMPLATE_RENEWAL = 'rondo_vog_reminder_template_renewal';

	/**
	 * Custom from email for current send operation
	 *
	 * @var string|null
	 */
	private $current_from_email = null;

	/**
	 * Get the from email address for VOG emails
	 *
	 * @return string Email address to send from
	 */
	public function get_from_email(): string {
		$email = get_option( self::OPTION_FROM_EMAIL, '' );
		if ( empty( $email ) ) {
			return get_option( 'admin_email' );
		}
		return $email;
	}

	/**
	 * Get the from name for VOG emails
	 *
	 * @return string Name to display as sender
	 */
	public function get_from_name(): string {
		$name = get_option( self::OPTION_FROM_NAME, '' );
		if ( empty( $name ) ) {
			return get_bloginfo( 'name' );
		}
		return $name;
	}

	/**
	 * Get the template for new volunteers
	 *
	 * @return string Template content
	 */
	public function get_template_new(): string {
		$template = get_option( self::OPTION_TEMPLATE_NEW, '' );
		if ( empty( $template ) ) {
			return $this->get_default_template_new();
		}
		return $template;
	}

	/**
	 * Get the template for renewal requests
	 *
	 * @return string Template content
	 */
	public function get_template_renewal(): string {
		$template = get_option( self::OPTION_TEMPLATE_RENEWAL, '' );
		if ( empty( $template ) ) {
			return $this->get_default_template_renewal();
		}
		return $template;
	}

	/**
	 * Get exempt commissies (commissies that don't require VOG)
	 *
	 * @return array Array of commissie IDs that are exempt from VOG requirements
	 */
	public function get_exempt_commissies(): array {
		$exempt = get_option( self::OPTION_EXEMPT_COMMISSIES, [] );
		if ( ! is_array( $exempt ) ) {
			return [];
		}
		return array_map( 'intval', $exempt );
	}

	/**
	 * Update exempt commissies
	 *
	 * @param array $ids Array of commissie IDs
	 * @return bool True on success
	 */
	public function update_exempt_commissies( array $ids ): bool {
		$sanitized = array_map( 'intval', array_filter( $ids, 'is_numeric' ) );
		return update_option( self::OPTION_EXEMPT_COMMISSIES, $sanitized );
	}

	/**
	 * Get teams exempt from discipline case charging
	 *
	 * @return array Array of team IDs
	 */
	public function get_exempt_discipline_teams(): array {
		$teams = get_option( self::OPTION_EXEMPT_DISCIPLINE_TEAMS, [] );
		if ( ! is_array( $teams ) ) {
			return [];
		}
		return array_map( 'intval', $teams );
	}

	/**
	 * Update teams exempt from discipline case charging
	 *
	 * @param array $ids Array of team IDs.
	 * @return bool True on success.
	 */
	public function update_exempt_discipline_teams( array $ids ): bool {
		$sanitized = array_map( 'intval', array_filter( $ids, 'is_numeric' ) );
		return update_option( self::OPTION_EXEMPT_DISCIPLINE_TEAMS, $sanitized );
	}

	/**
	 * Get the reminder template for new volunteers
	 *
	 * @return string Reminder template content
	 */
	public function get_reminder_template_new(): string {
		$template = get_option( self::OPTION_REMINDER_TEMPLATE_NEW, '' );
		if ( empty( $template ) ) {
			return $this->get_default_reminder_template_new();
		}
		return $template;
	}

	/**
	 * Get the reminder template for renewal requests
	 *
	 * @return string Reminder template content
	 */
	public function get_reminder_template_renewal(): string {
		$template = get_option( self::OPTION_REMINDER_TEMPLATE_RENEWAL, '' );
		if ( empty( $template ) ) {
			return $this->get_default_reminder_template_renewal();
		}
		return $template;
	}

	/**
	 * Get all VOG settings
	 *
	 * @return array Settings array with from_email, from_name, template_new, template_renewal, exempt_commissies
	 */
	public function get_all_settings(): array {
		return [
			'from_email'              => $this->get_from_email(),
			'from_name'               => $this->get_from_name(),
			'template_new'            => $this->get_template_new(),
			'template_renewal'        => $this->get_template_renewal(),
			'reminder_template_new'   => $this->get_reminder_template_new(),
			'reminder_template_renewal' => $this->get_reminder_template_renewal(),
			'exempt_commissies'       => $this->get_exempt_commissies(),
			'exempt_discipline_teams' => $this->get_exempt_discipline_teams(),
		];
	}

	/**
	 * Update the from email address
	 *
	 * @param string $email Email address
	 * @return bool True on success
	 */
	public function update_from_email( string $email ): bool {
		$sanitized = sanitize_email( $email );
		if ( empty( $sanitized ) && ! empty( $email ) ) {
			return false; // Invalid email provided
		}
		return update_option( self::OPTION_FROM_EMAIL, $sanitized );
	}

	/**
	 * Update the from name
	 *
	 * @param string $name Sender name
	 * @return bool True on success
	 */
	public function update_from_name( string $name ): bool {
		$sanitized = sanitize_text_field( $name );
		return update_option( self::OPTION_FROM_NAME, $sanitized );
	}

	/**
	 * Update the new volunteer template
	 *
	 * @param string $template Template content
	 * @return bool True on success
	 */
	public function update_template_new( string $template ): bool {
		$sanitized = wp_kses_post( $template );
		return update_option( self::OPTION_TEMPLATE_NEW, $sanitized );
	}

	/**
	 * Update the renewal template
	 *
	 * @param string $template Template content
	 * @return bool True on success
	 */
	public function update_template_renewal( string $template ): bool {
		$sanitized = wp_kses_post( $template );
		return update_option( self::OPTION_TEMPLATE_RENEWAL, $sanitized );
	}

	/**
	 * Update the new volunteer reminder template
	 *
	 * @param string $template Template content
	 * @return bool True on success
	 */
	public function update_reminder_template_new( string $template ): bool {
		$sanitized = wp_kses_post( $template );
		return update_option( self::OPTION_REMINDER_TEMPLATE_NEW, $sanitized );
	}

	/**
	 * Update the renewal reminder template
	 *
	 * @param string $template Template content
	 * @return bool True on success
	 */
	public function update_reminder_template_renewal( string $template ): bool {
		$sanitized = wp_kses_post( $template );
		return update_option( self::OPTION_REMINDER_TEMPLATE_RENEWAL, $sanitized );
	}

	/**
	 * Send a VOG email to a person
	 *
	 * @param int    $person_id     The person post ID
	 * @param string $template_type Template type: 'new' or 'renewal'
	 * @return true|\WP_Error True on success, WP_Error on failure
	 */
	public function send( int $person_id, string $template_type ) {
		// Validate template type
		if ( ! in_array( $template_type, [ 'new', 'renewal' ], true ) ) {
			return new \WP_Error(
				'invalid_template_type',
				__( 'Invalid template type. Must be "new" or "renewal".', 'rondo' )
			);
		}

		// Get person post
		$person = get_post( $person_id );
		if ( ! $person || 'person' !== $person->post_type ) {
			return new \WP_Error(
				'invalid_person',
				__( 'Invalid person ID.', 'rondo' )
			);
		}

		// Get person's email from fixed email field
		$recipient_email = $this->get_person_email( $person_id );
		if ( ! $recipient_email ) {
			return new \WP_Error(
				'no_email',
				__( 'No email address found for this person.', 'rondo' )
			);
		}

		// Get person's first name
		$first_name = get_field( 'first_name', $person_id );
		if ( empty( $first_name ) ) {
			$first_name = $person->post_title;
		}

		// Build substitution variables
		$vars = [
			'first_name' => $first_name,
		];

		// For renewal, get previous VOG date
		if ( 'renewal' === $template_type ) {
			$previous_vog_date = get_field( 'datum-vog', $person_id );
			if ( $previous_vog_date ) {
				// Format date for display (ACF returns Y-m-d format)
				$vars['previous_vog_date'] = date_i18n( get_option( 'date_format' ), strtotime( $previous_vog_date ) );
			} else {
				$vars['previous_vog_date'] = __( 'onbekend', 'rondo' );
			}
		}

		// Get template
		$template = 'new' === $template_type ? $this->get_template_new() : $this->get_template_renewal();

		// Substitute variables
		$message = $this->substitute_variables( $template, $vars );

		// Build subject
		$subject = 'new' === $template_type
			? __( 'VOG aanvraag', 'rondo' )
			: __( 'VOG vernieuwing', 'rondo' );

		// Store from email for filter
		$this->current_from_email = $this->get_from_email();

		// Add filters for custom from address
		add_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
		add_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

		// Set content type to HTML
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$html_message = EmailTemplate::render(
			[
				'brand_name'    => $this->get_from_name(),
				'preheader'     => $subject,
				'eyebrow'       => 'VOG',
				'heading'       => $subject,
				'body_html'     => self::prepare_body_html( $message ),
				'support_email' => $this->get_from_email(),
			]
		);

		// Send email
		$result = wp_mail( $recipient_email, $subject, $html_message, $headers );

		// Remove filters after sending
		remove_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
		remove_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

		// Clear stored email
		$this->current_from_email = null;

		if ( ! $result ) {
			return new \WP_Error(
				'send_failed',
				__( 'Failed to send email.', 'rondo' )
			);
		}

		// Record email sent date in post meta
		update_post_meta( $person_id, 'vog_email_sent_date', current_time( 'Y-m-d H:i:s' ) );

		// Log email to timeline
		$comment_types = new \Rondo\Collaboration\CommentTypes();
		$comment_types->create_email_log(
			$person_id,
			[
				'template_type' => $template_type,
				'recipient'     => $recipient_email,
				'subject'       => $subject,
				'content'       => $html_message,
			]
		);

		return true;
	}

	/**
	 * Filter callback for wp_mail_from
	 *
	 * @param string $from_email Original from email
	 * @return string Modified from email
	 */
	public function filter_mail_from( $from_email ): string {
		if ( $this->current_from_email ) {
			return $this->current_from_email;
		}
		return $from_email;
	}

	/**
	 * Filter callback for wp_mail_from_name
	 *
	 * @param string $from_name Original from name
	 * @return string Modified from name
	 */
	public function filter_mail_from_name( $from_name ): string {
		return $this->get_from_name();
	}

	/**
	 * Prepare template content as HTML for the email body.
	 *
	 * If the content already contains HTML markup (from the rich text editor), it is
	 * used directly with inline styles added for email clients. Plain-text legacy
	 * templates are converted via EmailTemplate::format_plain_text().
	 *
	 * @param string $content Template content (HTML or plain text).
	 * @return string HTML ready for the email body.
	 */
	private static function prepare_body_html( string $content ): string {
		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}

		// Detect HTML: if it contains tags like <p>, <ul>, <ol>, <br>, <strong>, <em>, <a>
		if ( preg_match( '/<\s*(?:p|ul|ol|li|br|strong|em|a|h[1-6])\b/i', $content ) ) {
			// Already HTML — add inline styles for email clients.
			$styled = preg_replace(
				'/<p(?:\s[^>]*)?>/',
				'<p style="margin:0 0 16px;color:#0f172a;font-size:16px;line-height:1.7;">',
				$content
			);
			$styled = preg_replace(
				'/<ul(?:\s[^>]*)?>/',
				'<ul style="margin:0 0 16px;padding-left:1.5em;color:#0f172a;font-size:16px;line-height:1.7;">',
				$styled
			);
			$styled = preg_replace(
				'/<ol(?:\s[^>]*)?>/',
				'<ol style="margin:0 0 16px;padding-left:1.5em;color:#0f172a;font-size:16px;line-height:1.7;">',
				$styled
			);
			$styled = preg_replace(
				'/<li(?:\s[^>]*)?>/',
				'<li style="margin:0 0 4px;">',
				$styled
			);
			$styled = preg_replace(
				'/<a\s/',
				'<a style="color:#0f766e;text-decoration:underline;" ',
				$styled
			);
			return $styled;
		}

		// Plain text — fall back to the paragraph converter.
		return EmailTemplate::format_plain_text( $content );
	}

	/**
	 * Get email address from person's fixed email fields.
	 *
	 * @param int $person_id Person post ID.
	 * @return string|null Email address or null if not found.
	 */
	private function get_person_email( int $person_id ): ?string {
		$email = get_field( 'email_1', $person_id );
		if ( is_email( $email ) ) {
			return $email;
		}

		$email = get_field( 'email_2', $person_id );
		if ( is_email( $email ) ) {
			return $email;
		}

		return null;
	}

	/**
	 * Substitute variables in template
	 *
	 * @param string $template Template with {variable} placeholders
	 * @param array  $vars     Array of variable => value pairs
	 * @return string Template with substituted values
	 */
	private function substitute_variables( string $template, array $vars ): string {
		foreach ( $vars as $key => $value ) {
			$template = str_replace( '{' . $key . '}', $value, $template );
		}
		return $template;
	}

	/**
	 * Get default template for new volunteers
	 *
	 * @return string Default Dutch template
	 */
	private function get_default_template_new(): string {
		return '<p>Beste {first_name},</p><p>Als vrijwilliger bij onze vereniging vragen wij je om een Verklaring Omtrent Gedrag (VOG) aan te vragen.</p><p>Dit is een wettelijke vereiste voor iedereen die werkt met minderjarigen.</p><p>Je kunt de VOG gratis aanvragen via de KNVB. Meer informatie ontvang je van onze VOG-coordinator.</p><p>Met sportieve groet,<br>De vereniging</p>';
	}

	/**
	 * Get default template for renewal requests
	 *
	 * @return string Default Dutch template
	 */
	private function get_default_template_renewal(): string {
		return '<p>Beste {first_name},</p><p>Je huidige VOG-verklaring (van {previous_vog_date}) is ouder dan 3 jaar en moet vernieuwd worden.</p><p>Wij verzoeken je vriendelijk om een nieuwe VOG aan te vragen.</p><p>Met sportieve groet,<br>De vereniging</p>';
	}

	/**
	 * Get default reminder template for new volunteers
	 *
	 * @return string Default Dutch template
	 */
	private function get_default_reminder_template_new(): string {
		return '<p>Beste {first_name},</p><p>Op {email_sent_date} hebben wij je gevraagd om een VOG aan te vragen. Je hebt deze op {justis_date} bij Justis ingediend.</p><p>We willen je erop wijzen dat je de VOG nog moet uploaden in ons systeem zodra je deze hebt ontvangen.</p><p>Mocht je vragen hebben, neem dan gerust contact met ons op.</p><p>Met sportieve groet,<br>De vereniging</p>';
	}

	/**
	 * Get default reminder template for renewal requests
	 *
	 * @return string Default Dutch template
	 */
	private function get_default_reminder_template_renewal(): string {
		return '<p>Beste {first_name},</p><p>Op {email_sent_date} hebben wij je gevraagd om je VOG (van {previous_vog_date}) te vernieuwen. Je hebt deze op {justis_date} bij Justis ingediend.</p><p>We willen je erop wijzen dat je de nieuwe VOG nog moet uploaden in ons systeem zodra je deze hebt ontvangen.</p><p>Mocht je vragen hebben, neem dan gerust contact met ons op.</p><p>Met sportieve groet,<br>De vereniging</p>';
	}

	/**
	 * Send a VOG reminder email to a person
	 *
	 * Sent automatically 7 days after Justis submission date.
	 *
	 * @param int    $person_id     The person post ID
	 * @param string $template_type Template type: 'reminder_new' or 'reminder_renewal'
	 * @return true|\WP_Error True on success, WP_Error on failure
	 */
	public function send_reminder( int $person_id, string $template_type ) {
		// Validate template type
		if ( ! in_array( $template_type, [ 'reminder_new', 'reminder_renewal' ], true ) ) {
			return new \WP_Error(
				'invalid_template_type',
				__( 'Invalid template type. Must be "reminder_new" or "reminder_renewal".', 'rondo' )
			);
		}

		// Get person post
		$person = get_post( $person_id );
		if ( ! $person || 'person' !== $person->post_type ) {
			return new \WP_Error(
				'invalid_person',
				__( 'Invalid person ID.', 'rondo' )
			);
		}

		// Get person's email from fixed email field
		$recipient_email = $this->get_person_email( $person_id );
		if ( ! $recipient_email ) {
			return new \WP_Error(
				'no_email',
				__( 'No email address found for this person.', 'rondo' )
			);
		}

		// Get person's first name
		$first_name = get_field( 'first_name', $person_id );
		if ( empty( $first_name ) ) {
			$first_name = $person->post_title;
		}

		// Get email sent date
		$email_sent_date = get_post_meta( $person_id, 'vog_email_sent_date', true );
		if ( $email_sent_date ) {
			$email_sent_date = date_i18n( get_option( 'date_format' ), strtotime( $email_sent_date ) );
		} else {
			$email_sent_date = __( 'onbekend', 'rondo' );
		}

		// Get Justis submitted date
		$justis_date = get_post_meta( $person_id, 'vog_justis_submitted_date', true );
		if ( $justis_date ) {
			$justis_date = date_i18n( get_option( 'date_format' ), strtotime( $justis_date ) );
		} else {
			$justis_date = __( 'onbekend', 'rondo' );
		}

		// Build substitution variables
		$vars = [
			'first_name'      => $first_name,
			'email_sent_date' => $email_sent_date,
			'justis_date'     => $justis_date,
		];

		// For renewal, get previous VOG date
		if ( 'reminder_renewal' === $template_type ) {
			$previous_vog_date = get_field( 'datum-vog', $person_id );
			if ( $previous_vog_date ) {
				// Format date for display (ACF returns Y-m-d format)
				$vars['previous_vog_date'] = date_i18n( get_option( 'date_format' ), strtotime( $previous_vog_date ) );
			} else {
				$vars['previous_vog_date'] = __( 'onbekend', 'rondo' );
			}
		}

		// Get template
		$template = 'reminder_new' === $template_type ? $this->get_reminder_template_new() : $this->get_reminder_template_renewal();

		// Substitute variables
		$message = $this->substitute_variables( $template, $vars );

		// Build subject
		$subject = 'reminder_new' === $template_type
			? __( 'VOG herinnering', 'rondo' )
			: __( 'VOG herinnering - vernieuwing', 'rondo' );

		// Store from email for filter
		$this->current_from_email = $this->get_from_email();

		// Add filters for custom from address
		add_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
		add_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

		// Set content type to HTML
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$html_message = EmailTemplate::render(
			[
				'brand_name'    => $this->get_from_name(),
				'preheader'     => $subject,
				'eyebrow'       => 'VOG',
				'heading'       => $subject,
				'body_html'     => self::prepare_body_html( $message ),
				'support_email' => $this->get_from_email(),
			]
		);

		// Send email
		$result = wp_mail( $recipient_email, $subject, $html_message, $headers );

		// Remove filters after sending
		remove_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
		remove_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

		// Clear stored email
		$this->current_from_email = null;

		if ( ! $result ) {
			return new \WP_Error(
				'send_failed',
				__( 'Failed to send email.', 'rondo' )
			);
		}

		// Record reminder sent date in post meta
		update_post_meta( $person_id, 'vog_reminder_sent_date', current_time( 'Y-m-d H:i:s' ) );

		// Log email to timeline
		$comment_types = new \Rondo\Collaboration\CommentTypes();
		$comment_types->create_email_log(
			$person_id,
			[
				'template_type' => $template_type,
				'recipient'     => $recipient_email,
				'subject'       => $subject,
				'content'       => $html_message,
			]
		);

		return true;
	}

}
