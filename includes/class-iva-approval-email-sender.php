<?php
/**
 * Sends the member notification after a valid IVA certificate is approved.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Collaboration\CommentTypes;
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

		$first_name = trim( (string) get_field( 'first_name', $person_id ) );
		$greeting   = $first_name !== '' ? sprintf( 'Hoi %s,', $first_name ) : 'Hoi,';
		$subject    = 'Je IVA-certificaat is goedgekeurd';
		$body       = $greeting
			. "\n\nJe IVA-certificaat is goedgekeurd. Je kunt je nu ook inschrijven voor inschrijftaken waarvoor een geldig IVA-certificaat nodig is.";
		$html       = EmailTemplate::render(
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
			$email = (string) get_field( $field_name, $person_id );
			if ( is_email( $email ) ) {
				return $email;
			}
		}

		return null;
	}
}
