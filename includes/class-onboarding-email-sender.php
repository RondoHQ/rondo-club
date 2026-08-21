<?php
/**
 * Onboarding Email Sender
 *
 * Sends onboarding emails to new members and new volunteers from the
 * Onboarding screen. Stores a timestamp on the person so they are only
 * onboarded once per type.
 *
 * @package Rondo\Notifications
 */

namespace Rondo\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service class that builds, sends, and logs onboarding emails.
 *
 * No hooks registered in constructor — instantiated on-demand by REST callbacks.
 */
class OnboardingEmailSender {

	const TYPE_LID          = 'lid';
	const TYPE_VRIJWILLIGER = 'vrijwilliger';

	const OPTION_LID_SUBJECT          = 'rondo_onboarding_lid_subject';
	const OPTION_LID_BODY             = 'rondo_onboarding_lid_body';
	const OPTION_VRIJWILLIGER_SUBJECT = 'rondo_onboarding_vrijwilliger_subject';
	const OPTION_VRIJWILLIGER_BODY    = 'rondo_onboarding_vrijwilliger_body';

	const META_LID_SENT          = 'onboarding-email-lid-sent';
	const META_VRIJWILLIGER_SENT = 'onboarding-email-vrijwilliger-sent';

	/**
	 * Send an onboarding email to one person.
	 *
	 * @param int    $person_id Person post ID.
	 * @param string $type      Either 'lid' or 'vrijwilliger'.
	 * @return array {
	 *     @type string $status     One of: 'sent', 'already_sent', 'no_email',
	 *                              'not_found', 'send_failed', 'invalid_type'.
	 *     @type int    $person_id  Echo of the person ID.
	 *     @type string $message    Human-readable explanation.
	 *     @type string $recipient  Email used (only on 'sent').
	 * }
	 */
	public function send( int $person_id, string $type ): array {
		if ( ! in_array( $type, [ self::TYPE_LID, self::TYPE_VRIJWILLIGER ], true ) ) {
			return [
				'person_id' => $person_id,
				'status'    => 'invalid_type',
				'message'   => 'Ongeldig type onboarding e-mail.',
			];
		}

		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return [
				'person_id' => $person_id,
				'status'    => 'not_found',
				'message'   => 'Persoon niet gevonden of niet gepubliceerd.',
			];
		}

		$meta_key = self::sent_meta_key( $type );
		$existing = (string) get_post_meta( $person_id, $meta_key, true );
		if ( $existing !== '' ) {
			return [
				'person_id' => $person_id,
				'status'    => 'already_sent',
				'message'   => 'Onboarding e-mail was al verstuurd.',
				'sent_at'   => $existing,
			];
		}

		$email = $this->get_person_email( $person_id );
		if ( ! $email ) {
			return [
				'person_id' => $person_id,
				'status'    => 'no_email',
				'message'   => 'Deze persoon heeft geen e-mailadres.',
			];
		}

		$first_name = (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) ?: '' );
		$infix      = (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'infix' ) ?: '' );
		$last_name  = (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'last_name' ) ?: '' );
		$full_name  = trim( implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) ) );

		$club_naam = $this->get_club_name();

		$settings = $this->get_settings( $type );
		$subject  = $settings['subject'];
		$body     = $settings['body'];

		$vars = [
			'first_name' => $first_name,
			'infix'      => $infix,
			'last_name'  => $last_name,
			'full_name'  => $full_name,
			'email'      => $email,
			'club_naam'  => $club_naam,
		];

		$subject = $this->substitute_variables( $subject, $vars );
		$body    = $this->substitute_variables( $body, $vars );

		$html_body = EmailTemplate::render(
			[
				'brand_name' => $club_naam,
				'preheader'  => $subject,
				'eyebrow'    => $type === self::TYPE_VRIJWILLIGER ? 'Vrijwilliger' : 'Lid',
				'heading'    => $subject,
				'body_html'  => $this->is_html( $body ) ? wp_kses_post( $body ) : EmailTemplate::format_plain_text( $body ),
			]
		);

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		$result  = wp_mail( $email, $subject, $html_body, $headers );

		if ( ! $result ) {
			return [
				'person_id' => $person_id,
				'status'    => 'send_failed',
				'message'   => 'E-mail kon niet verstuurd worden.',
			];
		}

		// Stamp the timestamp through the native field API.
		$timestamp = current_time( 'Y-m-d H:i:s' );
		\Rondo\Fields\Fields::update_for_post( $person_id, $meta_key, $timestamp );

		// Log to the person's timeline.
		if ( class_exists( '\Rondo\Collaboration\CommentTypes' ) ) {
			$comment_types = new \Rondo\Collaboration\CommentTypes();
			$comment_types->create_email_log(
				$person_id,
				[
					'template_type' => $type === self::TYPE_VRIJWILLIGER ? 'onboarding_vrijwilliger' : 'onboarding_lid',
					'recipient'     => $email,
					'subject'       => $subject,
					'content'       => $html_body,
				]
			);
		}

		return [
			'person_id' => $person_id,
			'status'    => 'sent',
			'message'   => 'Onboarding e-mail verstuurd.',
			'recipient' => $email,
			'sent_at'   => $timestamp,
		];
	}

	/**
	 * Get template settings for a given type.
	 *
	 * @param string $type Either 'lid' or 'vrijwilliger'.
	 * @return array { subject, body }
	 */
	public function get_settings( string $type ): array {
		$subject_key = $type === self::TYPE_VRIJWILLIGER ? self::OPTION_VRIJWILLIGER_SUBJECT : self::OPTION_LID_SUBJECT;
		$body_key    = $type === self::TYPE_VRIJWILLIGER ? self::OPTION_VRIJWILLIGER_BODY : self::OPTION_LID_BODY;

		$subject = (string) get_option( $subject_key, '' );
		$body    = (string) get_option( $body_key, '' );

		if ( $subject === '' ) {
			$subject = $this->get_default_subject( $type );
		}
		if ( $body === '' ) {
			$body = $this->get_default_body( $type );
		}

		return [
			'subject' => $subject,
			'body'    => $body,
		];
	}

	/**
	 * Persist template settings for a given type.
	 *
	 * @param string $type     Either 'lid' or 'vrijwilliger'.
	 * @param array  $settings Accepts: subject, body.
	 * @return array Updated settings.
	 */
	public function update_settings( string $type, array $settings ): array {
		$subject_key = $type === self::TYPE_VRIJWILLIGER ? self::OPTION_VRIJWILLIGER_SUBJECT : self::OPTION_LID_SUBJECT;
		$body_key    = $type === self::TYPE_VRIJWILLIGER ? self::OPTION_VRIJWILLIGER_BODY : self::OPTION_LID_BODY;

		if ( isset( $settings['subject'] ) ) {
			update_option( $subject_key, sanitize_text_field( $settings['subject'] ) );
		}
		if ( isset( $settings['body'] ) ) {
			update_option( $body_key, wp_kses_post( $settings['body'] ) );
		}

		return $this->get_settings( $type );
	}

	/**
	 * Default subject per type.
	 *
	 * @param string $type Either 'lid' or 'vrijwilliger'.
	 * @return string
	 */
	public function get_default_subject( string $type ): string {
		if ( $type === self::TYPE_VRIJWILLIGER ) {
			return 'Welkom als vrijwilliger bij {club_naam}';
		}
		return 'Welkom als lid van {club_naam}';
	}

	/**
	 * Default body per type.
	 *
	 * @param string $type Either 'lid' or 'vrijwilliger'.
	 * @return string
	 */
	public function get_default_body( string $type ): string {
		if ( $type === self::TYPE_VRIJWILLIGER ) {
			return <<<'EOT'
Beste {first_name},

Wat fijn dat je vrijwilliger bent geworden bij {club_naam}. Vrijwilligers maken onze club mogelijk en we zijn blij met jouw inzet.

**Vrijwilligersbeleid in het kort**

Sinds dit seizoen vragen we van elke vrijwilliger 2 inschrijftaken per jaar (terreinmeester, kantine, schoonmaak of terreinonderhoud). Bij meerdere kinderen telt de plicht per kind met een oplopende korting. Trainers, leiders, commissieleden en betaalde vrijwilligers zijn automatisch vrijgesteld.

**Wat je nu kunt doen**

1. Bekijk het rooster en meld je aan: ga in Rondo naar **Vrijwilligers → Inschrijftaken**. Je ziet alleen inschrijftaken waarvoor je in aanmerking komt.
2. **VOG verklaring** is verplicht voor alle vrijwilligers. Heb je er nog geen of is hij verlopen? Onze VOG-coördinator stuurt je een aanvraag via Justis.
3. **IVA-certificaat of diploma Sociale Hygiëne** is alleen verplicht voor inschrijftaken achter de bar. Een IVA is gratis via NOC*NSF. Beide bewijssoorten blijven na goedkeuring geldig. Upload je bewijsstuk in Rondo via **Vrijwilligers → Mijn profiel** — het bestuurslid kantine keurt het daar goed.
4. Plan je eerste inschrijftaak binnen 4 weken na ontvangst van deze mail — sociale druk doet de rest.

Als je een inschrijftaak niet kunt doen: tijdig afmelden mag altijd zonder gevolg. Wel komen opdagen telt voor de plicht.

Heb je vragen? Neem gerust contact op.

Met sportieve groet,
{club_naam}
EOT;
		}
		return <<<'EOT'
Beste {first_name},

Welkom bij {club_naam}! We zijn blij dat je lid bent geworden.

Op onze website en in de app vind je informatie over trainingen, wedstrijden en activiteiten. Heb je vragen? Neem gerust contact met ons op.

Met sportieve groet,
{club_naam}
EOT;
	}

	/**
	 * Return the post meta key that stores the sent timestamp for a type.
	 *
	 * @param string $type Either 'lid' or 'vrijwilliger'.
	 * @return string
	 */
	public static function sent_meta_key( string $type ): string {
		return $type === self::TYPE_VRIJWILLIGER ? self::META_VRIJWILLIGER_SENT : self::META_LID_SENT;
	}

	/**
	 * Get email address from person's fixed email fields.
	 *
	 * @param int $person_id Person post ID.
	 * @return string|null Email address or null if not found.
	 */
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
	 * Return the configured club name or fall back to site name.
	 */
	private function get_club_name(): string {
		if ( class_exists( '\Rondo\Config\ClubConfig' ) && method_exists( '\Rondo\Config\ClubConfig', 'get_club_name' ) ) {
			$name = (string) \Rondo\Config\ClubConfig::get_club_name();
			if ( $name !== '' ) {
				return $name;
			}
		}
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Detect whether a template body already contains HTML markup.
	 */
	private function is_html( string $text ): bool {
		return (bool) preg_match( '/<[a-z][\s\S]*>/i', $text );
	}

	/**
	 * Substitute {variable} placeholders in a template string.
	 *
	 * @param string $template Template with {variable} placeholders.
	 * @param array  $vars     key => value pairs.
	 */
	private function substitute_variables( string $template, array $vars ): string {
		foreach ( $vars as $key => $value ) {
			$template = str_replace( '{' . $key . '}', (string) $value, $template );
		}
		return $template;
	}
}
