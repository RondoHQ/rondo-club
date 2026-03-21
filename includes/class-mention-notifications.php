<?php
/**
 * Handles notifications when users are @mentioned
 */

namespace Rondo\Collaboration;

use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MentionNotifications {

	public function __construct() {
		add_action( 'rondo_user_mentioned', [ $this, 'handle_mentions' ], 10, 3 );
	}

	/**
	 * Handle mention notifications
	 *
	 * @param int $comment_id Comment ID
	 * @param int[] $mentioned_user_ids Array of mentioned user IDs
	 * @param int $author_id Author who wrote the note
	 */
	public function handle_mentions( $comment_id, $mentioned_user_ids, $author_id ) {
		$comment = get_comment( $comment_id );
		if ( ! $comment ) {
			return;
		}

		$post = get_post( $comment->comment_post_ID );
		if ( ! $post ) {
			return;
		}

		$author      = get_userdata( $author_id );
		$author_name = $author ? $author->display_name : 'Someone';

		foreach ( $mentioned_user_ids as $user_id ) {
			// Don't notify yourself
			if ( $user_id === $author_id ) {
				continue;
			}

			// Check user preference
			$pref = get_user_meta( $user_id, 'rondo_mention_notifications', true );
			if ( $pref === 'never' ) {
				continue;
			}

			// Default to digest
			if ( empty( $pref ) ) {
				$pref = 'digest';
			}

			if ( $pref === 'immediate' ) {
				$this->send_immediate_notification( $user_id, $author_name, $post, $comment );
			} else {
				$this->queue_for_digest( $user_id, $comment_id );
			}
		}
	}

	/**
	 * Send immediate notification via email
	 */
	private function send_immediate_notification( $user_id, $author_name, $post, $comment ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->user_email ) {
			return;
		}

		$post_title = $post->post_title;
		$post_url   = home_url( '/people/' . $post->ID );

		$subject   = sprintf( '%s mentioned you in a note about %s', $author_name, $post_title );
		$content   = wp_strip_all_tags( $comment->comment_content );
		$preview   = strlen( $content ) > 200 ? substr( $content, 0, 200 ) . '...' : $content;
		$site_name = get_bloginfo( 'name' );

		$message = EmailTemplate::render(
			[
				'brand_name' => $site_name,
				'preheader'  => $subject,
				'eyebrow'    => 'Melding',
				'heading'    => 'Je bent genoemd',
				'body_html'  => sprintf(
					'<p style="margin:0 0 16px;color:#0f172a;font-size:16px;line-height:1.7;"><strong>%s</strong> noemde je in een notitie over <strong>%s</strong>.</p><blockquote style="margin:0;padding:16px 18px;border-left:4px solid #0f766e;background:#f8fafc;border-radius:0 16px 16px 0;color:#334155;">%s</blockquote>',
					esc_html( $author_name ),
					esc_html( $post_title ),
					esc_html( $preview )
				),
				'cta_url'    => $post_url,
				'cta_label'  => 'Bekijk notitie',
			]
		);

		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$parts  = explode( '.', $host );
		$domain = count( $parts ) >= 2
			? implode( '.', array_slice( $parts, -2 ) )
			: $host;

		wp_mail(
			$user->user_email,
			$subject,
			$message,
			[
				'Content-Type: text/html; charset=UTF-8',
				'From: ' . $site_name . ' <notifications@' . $domain . '>',
			]
		);
	}

	/**
	 * Queue mention for inclusion in user's next digest
	 */
	private function queue_for_digest( $user_id, $comment_id ) {
		$queued = get_user_meta( $user_id, '_queued_mention_notifications', true );
		if ( ! is_array( $queued ) ) {
			$queued = [];
		}

		// Add to queue if not already present
		if ( ! in_array( $comment_id, $queued, true ) ) {
			$queued[] = $comment_id;
			update_user_meta( $user_id, '_queued_mention_notifications', $queued );
		}
	}

	/**
	 * Get and clear queued mentions for a user (called by RONDO_Reminders)
	 *
	 * @param int $user_id User ID
	 * @return array Array of mention data for digest
	 */
	public static function get_queued_mentions( $user_id ) {
		$queued = get_user_meta( $user_id, '_queued_mention_notifications', true );
		if ( ! is_array( $queued ) || empty( $queued ) ) {
			return [];
		}

		$mentions = [];
		foreach ( $queued as $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( ! $comment ) {
				continue;
			}

			$post = get_post( $comment->comment_post_ID );
			if ( ! $post ) {
				continue;
			}

			$author = get_userdata( $comment->user_id );

			$mentions[] = [
				'author'     => $author ? $author->display_name : 'Someone',
				'post_title' => $post->post_title,
				'post_url'   => home_url( '/people/' . $post->ID ),
				'preview'    => wp_trim_words( wp_strip_all_tags( $comment->comment_content ), 20 ),
			];
		}

		// Clear the queue
		delete_user_meta( $user_id, '_queued_mention_notifications' );

		return $mentions;
	}
}
