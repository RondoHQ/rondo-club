<?php
/**
 * Sends the member notification after a valid IVA certificate is approved.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Collaboration\CommentTypes;
use Rondo\Config\ClubConfig;
use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IvaApprovalEmailSender {

	/**
	 * Send the approval email to the first valid address on the person record.
	 *
	 * @return array{status: string, recipient?: string}
	 */
	public function send( int $person_id ): array {
		$email = $this->get_person_email( $person_id );
		if ( ! $email ) {
			return [ 'status' => 'no_email' ];
		}

		$template_values = $this->get_template_values( $person_id );
		$subject         = sanitize_text_field( strtr( ClubConfig::get_iva_approval_email_subject(), $template_values ) );
		$body            = strtr( ClubConfig::get_iva_approval_email_body(), $template_values );
		$html            = EmailTemplate::render(
			[
				'preheader' => $subject,
				'eyebrow'   => 'Vrijwilligers',
				'heading'   => $subject,
				'body_html' => EmailTemplate::format_plain_text( $body ),
				'cta_url'   => home_url( '/vrijwillig' ),
				'cta_label' => 'Bekijk inschrijftaken',
			]
		);

		$sent = wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
		if ( ! $sent ) {
			return [
				'status'    => 'send_failed',
				'recipient' => $email,
			];
		}

		if ( class_exists( CommentTypes::class ) ) {
			$comment_types = new CommentTypes();
			$comment_types->create_email_log(
				$person_id,
				[
					'template_type' => 'iva_approved',
					'recipient'     => $email,
					'subject'       => $subject,
					'content'       => $html,
				]
			);
		}

		do_action( 'rondo_iva_approval_email_sent', $person_id, $email );

		return [
			'status'    => 'sent',
			'recipient' => $email,
		];
	}

	private function get_person_email( int $person_id ): ?string {
		foreach ( [ 'email_1', 'email_2' ] as $field_name ) {
			$email = (string) \Rondo\Fields\Fields::get_for_post( $person_id, $field_name );
			if ( is_email( $email ) ) {
				return $email;
			}
		}

		return null;
	}

	/**
	 * Get placeholder replacements for the configured email templates.
	 *
	 * @return array<string, string>
	 */
	private function get_template_values( int $person_id ): array {
		$full_name  = trim( (string) get_the_title( $person_id ) );
		$first_name = trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) );
		$club_name  = trim( ClubConfig::get_club_name() );

		if ( $first_name === '' ) {
			$first_name = $full_name !== '' ? $full_name : 'daar';
		}

		if ( $club_name === '' ) {
			$club_name = (string) get_bloginfo( 'name' );
		}

		return [
			'{first_name}' => $first_name,
			'{full_name}'  => $full_name,
			'{club_name}'  => $club_name,
		];
	}
}
