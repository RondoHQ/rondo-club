<?php
/**
 * VOG Email Service
 *
 * Handles VOG (Verklaring Omtrent Gedrag) email sending and settings management.
 *
 * @package Stadion\VOG
 */

namespace Rondo\VOG;

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
	const OPTION_FROM_EMAIL = 'stadion_vog_from_email';

	/**
	 * Option key for from name
	 */
	const OPTION_FROM_NAME = 'stadion_vog_from_name';

	/**
	 * Option key for new volunteer template
	 */
	const OPTION_TEMPLATE_NEW = 'stadion_vog_template_new';

	/**
	 * Option key for renewal template
	 */
	const OPTION_TEMPLATE_RENEWAL = 'stadion_vog_template_renewal';

	/**
	 * Option key for exempt commissies (commissies that don't require VOG)
	 */
	const OPTION_EXEMPT_COMMISSIES = 'stadion_vog_exempt_commissies';

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
	 * Get all VOG settings
	 *
	 * @return array Settings array with from_email, from_name, template_new, template_renewal, exempt_commissies
	 */
	public function get_all_settings(): array {
		return [
			'from_email'         => $this->get_from_email(),
			'from_name'          => $this->get_from_name(),
			'template_new'       => $this->get_template_new(),
			'template_renewal'   => $this->get_template_renewal(),
			'exempt_commissies'  => $this->get_exempt_commissies(),
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
		$sanitized = sanitize_textarea_field( $template );
		return update_option( self::OPTION_TEMPLATE_NEW, $sanitized );
	}

	/**
	 * Update the renewal template
	 *
	 * @param string $template Template content
	 * @return bool True on success
	 */
	public function update_template_renewal( string $template ): bool {
		$sanitized = sanitize_textarea_field( $template );
		return update_option( self::OPTION_TEMPLATE_RENEWAL, $sanitized );
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
				__( 'Invalid template type. Must be "new" or "renewal".', 'stadion' )
			);
		}

		// Get person post
		$person = get_post( $person_id );
		if ( ! $person || 'person' !== $person->post_type ) {
			return new \WP_Error(
				'invalid_person',
				__( 'Invalid person ID.', 'stadion' )
			);
		}

		// Get person's email from contact_info ACF field
		$recipient_email = $this->get_person_email( $person_id );
		if ( ! $recipient_email ) {
			return new \WP_Error(
				'no_email',
				__( 'No email address found for this person.', 'stadion' )
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
				$vars['previous_vog_date'] = __( 'onbekend', 'stadion' );
			}
		}

		// Get template
		$template = 'new' === $template_type ? $this->get_template_new() : $this->get_template_renewal();

		// Substitute variables
		$message = $this->substitute_variables( $template, $vars );

		// Build subject
		$subject = 'new' === $template_type
			? __( 'VOG aanvraag', 'stadion' )
			: __( 'VOG vernieuwing', 'stadion' );

		// Store from email for filter
		$this->current_from_email = $this->get_from_email();

		// Add filters for custom from address
		add_filter( 'wp_mail_from', [ $this, 'filter_mail_from' ] );
		add_filter( 'wp_mail_from_name', [ $this, 'filter_mail_from_name' ] );

		// Set content type to HTML
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Convert newlines to <br> for HTML email
		$html_message = nl2br( esc_html( $message ) );

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
				__( 'Failed to send email.', 'stadion' )
			);
		}

		// Record email sent date in post meta
		update_post_meta( $person_id, 'vog_email_sent_date', current_time( 'Y-m-d H:i:s' ) );

		// Log email to timeline
		$comment_types = new \Stadion\Collaboration\CommentTypes();
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
	 * Get email address from person's contact_info ACF field
	 *
	 * @param int $person_id Person post ID
	 * @return string|null Email address or null if not found
	 */
	private function get_person_email( int $person_id ): ?string {
		$contact_info = get_field( 'contact_info', $person_id );

		if ( empty( $contact_info ) || ! is_array( $contact_info ) ) {
			return null;
		}

		// Find first email type contact
		foreach ( $contact_info as $contact ) {
			if ( isset( $contact['contact_type'] ) && 'email' === $contact['contact_type'] ) {
				$email = $contact['contact_value'] ?? '';
				if ( is_email( $email ) ) {
					return $email;
				}
			}
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
		return <<<EOT
Beste {first_name},

Als vrijwilliger bij onze vereniging vragen wij je om een Verklaring Omtrent Gedrag (VOG) aan te vragen.

Dit is een wettelijke vereiste voor iedereen die werkt met minderjarigen.

Je kunt de VOG gratis aanvragen via de KNVB. Meer informatie ontvang je van onze VOG-coordinator.

Met sportieve groet,
De vereniging
EOT;
	}

	/**
	 * Get default template for renewal requests
	 *
	 * @return string Default Dutch template
	 */
	private function get_default_template_renewal(): string {
		return <<<EOT
Beste {first_name},

Je huidige VOG-verklaring (van {previous_vog_date}) is ouder dan 3 jaar en moet vernieuwd worden.

Wij verzoeken je vriendelijk om een nieuwe VOG aan te vragen.

Met sportieve groet,
De vereniging
EOT;
	}
}
