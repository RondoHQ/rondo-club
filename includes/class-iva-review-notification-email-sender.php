<?php
/**
 * Notifies IVA approvers when a member uploads a certificate.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Collaboration\CommentTypes;
use Rondo\Core\UserRoles;
use Rondo\Notifications\EmailTemplate;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IvaReviewNotificationEmailSender {

	/**
	 * When the last review request for a person went out.
	 */
	public const META_NOTIFIED_AT = '_iva_review_notified_at';

	/**
	 * How long to suppress a repeat review request for the same person.
	 *
	 * Each upload creates its own attachment, so a resubmit cannot be
	 * recognised by certificate identity — only by how quickly it follows the
	 * previous one. Ten minutes is far longer than any double submit and far
	 * shorter than a genuine second attempt after an approver asked for a
	 * better scan.
	 */
	public const THROTTLE_SECONDS = 600;

	/**
	 * Send one review request per unique, deliverable approver address.
	 *
	 * @return array{status: string, sent: int, failed: int}
	 */
	public function send( int $person_id ): array {
		if ( $this->is_throttled( $person_id ) ) {
			return [
				'status' => 'throttled',
				'sent'   => 0,
				'failed' => 0,
			];
		}

		$recipients = $this->get_recipient_emails();
		if ( empty( $recipients ) ) {
			return [
				'status' => 'no_recipients',
				'sent'   => 0,
				'failed' => 0,
			];
		}

		$person_name = trim( (string) get_the_title( $person_id ) );
		if ( $person_name === '' ) {
			$person_name = sprintf( 'Persoon %d', $person_id );
		}

		$subject    = sprintf( 'Nieuw IVA-certificaat van %s', $person_name );
		$review_url = add_query_arg( 'review', $person_id, home_url( '/vrijwilligers/iva' ) );
		$body_html  = sprintf(
			'<p style="margin:0 0 16px;color:#0f172a;font-size:16px;line-height:1.7;"><strong>%1$s</strong> heeft een nieuw IVA-certificaat geüpload. Het certificaat wacht op beoordeling.</p><p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;">Open de review om het certificaat te bekijken en direct goed te keuren.</p>',
			esc_html( $person_name )
		);
		$html       = EmailTemplate::render(
			[
				'preheader' => $subject,
				'eyebrow'   => 'Vrijwilligers',
				'heading'   => 'IVA-certificaat wacht op goedkeuring',
				'body_html' => $body_html,
				'cta_url'   => $review_url,
				'cta_label' => 'Bekijk en keur goed',
			]
		);

		// Stamp before sending: ten recipients take a few seconds to work
		// through, and the resubmits this guards against arrive inside that
		// window. Stamping afterwards would let them all pass the check first.
		update_post_meta( $person_id, self::META_NOTIFIED_AT, time() );

		$sent   = 0;
		$failed = 0;
		foreach ( $recipients as $email ) {
			if ( wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
				++$sent;
				$this->log_email( $person_id, $email, $subject, $html );
			} else {
				++$failed;
			}
		}

		// Nothing went out, so do not let the stamp suppress a real retry.
		if ( $sent === 0 ) {
			delete_post_meta( $person_id, self::META_NOTIFIED_AT );
		}

		do_action( 'rondo_iva_review_notification_sent', $person_id, $sent, $failed );

		return [
			'status' => $failed === 0 ? 'sent' : ( $sent > 0 ? 'partially_sent' : 'send_failed' ),
			'sent'   => $sent,
			'failed' => $failed,
		];
	}

	/**
	 * Whether a review request for this person went out too recently.
	 */
	private function is_throttled( int $person_id ): bool {
		$last = (int) get_post_meta( $person_id, self::META_NOTIFIED_AT, true );
		if ( $last <= 0 ) {
			return false;
		}

		return ( time() - $last ) < self::THROTTLE_SECONDS;
	}

	/**
	 * Resolve users who can approve IVA certificates to unique real addresses.
	 *
	 * @return string[]
	 */
	private function get_recipient_emails(): array {
		$user_ids = get_users( [ 'fields' => 'ids' ] );
		$emails   = [];

		foreach ( $user_ids as $user_id ) {
			$user_id = (int) $user_id;
			if ( ! user_can( $user_id, UserRoles::IVA_APPROVE_CAPABILITY ) && ! user_can( $user_id, 'manage_options' ) ) {
				continue;
			}

			$email = UserProvisioning::contact_email( $user_id );
			if ( $email ) {
				$emails[] = strtolower( $email );
			}
		}

		return array_values( array_unique( $emails ) );
	}

	private function log_email( int $person_id, string $email, string $subject, string $html ): void {
		if ( ! class_exists( CommentTypes::class ) ) {
			return;
		}

		( new CommentTypes() )->create_email_log(
			$person_id,
			[
				'template_type' => 'iva_review_requested',
				'recipient'     => $email,
				'subject'       => $subject,
				'content'       => $html,
			]
		);
	}
}
