<?php
/**
 * Sends a branded notification when user feedback is declined.
 *
 * @package Rondo\Feedback
 */

namespace Rondo\Feedback;

use Rondo\Notifications\EmailTemplate;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DeclineEmailSender {

	public const META_SENT_AT = '_feedback_decline_email_sent_at';

	/**
	 * Notify the feedback author once that their feedback has been declined.
	 *
	 * @return array{status: string, recipient?: string}
	 */
	public function send( int $feedback_id ): array {
		$feedback = get_post( $feedback_id );
		if ( ! $feedback || $feedback->post_type !== 'rondo_feedback' ) {
			return [ 'status' => 'invalid_feedback' ];
		}

		if ( get_post_meta( $feedback_id, self::META_SENT_AT, true ) !== '' ) {
			return [ 'status' => 'already_sent' ];
		}

		$decline_reason = trim( (string) get_post_meta( $feedback_id, StatusService::META_DECLINE_REASON, true ) );
		if ( $decline_reason === '' ) {
			return [ 'status' => 'missing_decline_reason' ];
		}

		$email = UserProvisioning::contact_email( (int) $feedback->post_author );
		if ( ! $email ) {
			return [ 'status' => 'no_email' ];
		}

		$author       = get_userdata( (int) $feedback->post_author );
		$display_name = $author ? trim( (string) ( $author->first_name ?: $author->display_name ) ) : '';
		$title        = EmailTemplate::decode_title( get_the_title( $feedback_id ) );
		$subject      = $title !== '' ? sprintf( 'Je feedback is afgewezen: %s', $title ) : 'Je feedback is afgewezen';
		$feedback_url = home_url( '/feedback/' . $feedback_id );
		$greeting     = $display_name !== '' ? sprintf( 'Hoi %s,', $display_name ) : 'Hoi,';
		$title_html   = $title !== ''
			? sprintf(
				'<div style="margin:20px 0;padding:16px 18px;border:1px solid #dbe4e1;border-radius:16px;background:#f8fafc;"><strong style="color:#0f172a;font-size:16px;">%s</strong></div>',
				esc_html( $title )
			)
			: '';
		$reason_html  = sprintf(
			'<div style="margin:20px 0;padding:18px;border-radius:16px;background:#fef6e7;border:1px solid #f0d9a8;"><p style="margin:0 0 8px;color:#92400e;font-size:13px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">Waarom we dit niet doen</p>%s</div>',
			EmailTemplate::format_plain_text( $decline_reason )
		);
		$body_html    = sprintf(
			'<p style="margin:0 0 16px;color:#0f172a;font-size:16px;line-height:1.7;">%1$s</p><p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;">Bedankt voor het delen van je feedback. We gaan er dit keer niets mee doen:</p>%2$s%3$s<p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;">Ben je het er niet mee eens of is er iets veranderd? Laat het gerust weten — je kunt altijd nieuwe feedback insturen.</p>',
			esc_html( $greeting ),
			$title_html,
			$reason_html
		);
		$html         = EmailTemplate::render(
			[
				'preheader'   => $subject,
				'eyebrow'     => 'Feedback',
				'heading'     => 'Je feedback is afgewezen',
				'body_html'   => $body_html,
				'cta_url'     => $feedback_url,
				'cta_label'   => 'Bekijk je feedback',
				'footer_html' => '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">Deze e-mail is automatisch verstuurd nadat je feedback als afgewezen is gemarkeerd.</p>',
			]
		);

		$sent = wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
		if ( ! $sent ) {
			return [
				'status'    => 'send_failed',
				'recipient' => $email,
			];
		}

		update_post_meta( $feedback_id, self::META_SENT_AT, current_time( 'mysql', true ) );
		do_action( 'rondo_feedback_decline_email_sent', $feedback_id, $email );

		return [
			'status'    => 'sent',
			'recipient' => $email,
		];
	}
}
