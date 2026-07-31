<?php
/**
 * Sends a branded notification when a feedback item is created.
 *
 * @package Rondo\Feedback
 */

namespace Rondo\Feedback;

use Rondo\Notifications\EmailTemplate;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NewFeedbackEmailSender {

	public const META_SENT_AT = '_new_feedback_email_sent_at';

	/**
	 * Notify the configured WordPress administration address once.
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

		$recipient = sanitize_email( (string) get_option( 'admin_email' ) );
		if ( ! is_email( $recipient ) ) {
			return [ 'status' => 'no_recipient' ];
		}

		$title        = EmailTemplate::decode_title( get_the_title( $feedback_id ) );
		$title        = $title !== '' ? $title : sprintf( 'Feedback #%d', $feedback_id );
		$subject      = sprintf( 'Nieuwe feedback #%1$d: %2$s', $feedback_id, $title );
		$feedback_url = home_url( '/feedback/' . $feedback_id );
		$author       = get_userdata( (int) $feedback->post_author );
		$author_name  = $author ? trim( (string) $author->display_name ) : '';
		$author_name  = $author_name !== '' ? $author_name : 'Onbekende gebruiker';
		$author_email = UserProvisioning::contact_email( (int) $feedback->post_author );
		$type         = (string) ( \Rondo\Fields\Fields::get_for_post( $feedback_id, 'feedback_type' ) ?: '' );
		$project      = (string) ( get_post_meta( $feedback_id, '_feedback_project', true ) ?: 'rondo-club' );
		$priority     = (string) ( \Rondo\Fields\Fields::get_for_post( $feedback_id, 'priority' ) ?: 'medium' );
		$content      = trim( wp_strip_all_tags( (string) $feedback->post_content ) );

		$type_labels     = [
			'bug'             => 'Bug',
			'feature_request' => 'Functieverzoek',
		];
		$priority_labels = [
			'low'      => 'Laag',
			'medium'   => 'Gemiddeld',
			'high'     => 'Hoog',
			'critical' => 'Kritiek',
		];

		$author_display = $author_email
			? sprintf( '%s (%s)', $author_name, $author_email )
			: $author_name;
		$details_html   = sprintf(
			'<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="margin:20px 0;border-collapse:collapse;"><tr><td style="padding:8px 12px;color:#64748b;font-size:14px;border-bottom:1px solid #e2e8f0;">Ingediend door</td><td style="padding:8px 12px;color:#0f172a;font-size:14px;font-weight:600;border-bottom:1px solid #e2e8f0;">%1$s</td></tr><tr><td style="padding:8px 12px;color:#64748b;font-size:14px;border-bottom:1px solid #e2e8f0;">Type</td><td style="padding:8px 12px;color:#0f172a;font-size:14px;font-weight:600;border-bottom:1px solid #e2e8f0;">%2$s</td></tr><tr><td style="padding:8px 12px;color:#64748b;font-size:14px;border-bottom:1px solid #e2e8f0;">Project</td><td style="padding:8px 12px;color:#0f172a;font-size:14px;font-weight:600;border-bottom:1px solid #e2e8f0;">%3$s</td></tr><tr><td style="padding:8px 12px;color:#64748b;font-size:14px;">Prioriteit</td><td style="padding:8px 12px;color:#0f172a;font-size:14px;font-weight:600;">%4$s</td></tr></table>',
			esc_html( $author_display ),
			esc_html( $type_labels[ $type ] ?? $type ),
			esc_html( $project ),
			esc_html( $priority_labels[ $priority ] ?? $priority )
		);
		$content_html   = $content !== ''
			? sprintf(
				'<div style="margin:20px 0;padding:18px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;"><p style="margin:0 0 8px;color:#64748b;font-size:13px;font-weight:700;letter-spacing:0.04em;text-transform:uppercase;">Beschrijving</p>%s</div>',
				EmailTemplate::format_plain_text( $content )
			)
			: '';
		$body_html      = sprintf(
			'<p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;">Er is een nieuw feedbackitem ingediend:</p><div style="margin:20px 0;padding:16px 18px;border:1px solid #dbe4e1;border-radius:16px;background:#f8fafc;"><strong style="color:#0f172a;font-size:16px;">%1$s</strong></div>%2$s%3$s',
			esc_html( $title ),
			$details_html,
			$content_html
		);
		$html           = EmailTemplate::render(
			[
				'preheader'   => $subject,
				'eyebrow'     => 'Feedback',
				'heading'     => 'Nieuw feedbackitem',
				'body_html'   => $body_html,
				'cta_url'     => $feedback_url,
				'cta_label'   => 'Bekijk feedback',
				'footer_html' => '<p style="margin:0;color:#64748b;font-size:13px;line-height:1.6;">Deze e-mail is automatisch verstuurd naar het ingestelde WordPress-administratieadres.</p>',
			]
		);

		$sent = wp_mail( $recipient, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
		if ( ! $sent ) {
			return [
				'status'    => 'send_failed',
				'recipient' => $recipient,
			];
		}

		update_post_meta( $feedback_id, self::META_SENT_AT, current_time( 'mysql', true ) );
		do_action( 'rondo_new_feedback_email_sent', $feedback_id, $recipient );

		return [
			'status'    => 'sent',
			'recipient' => $recipient,
		];
	}
}
