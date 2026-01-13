<?php
/**
 * Handles notifications when users are @mentioned
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_Mention_Notifications {

    public function __construct() {
        add_action('prm_user_mentioned', [$this, 'handle_mentions'], 10, 3);
    }

    /**
     * Handle mention notifications
     *
     * @param int $comment_id Comment ID
     * @param int[] $mentioned_user_ids Array of mentioned user IDs
     * @param int $author_id Author who wrote the note
     */
    public function handle_mentions($comment_id, $mentioned_user_ids, $author_id) {
        $comment = get_comment($comment_id);
        if (!$comment) return;

        $post = get_post($comment->comment_post_ID);
        if (!$post) return;

        $author = get_userdata($author_id);
        $author_name = $author ? $author->display_name : 'Someone';

        foreach ($mentioned_user_ids as $user_id) {
            // Don't notify yourself
            if ($user_id === $author_id) continue;

            // Check user preference
            $pref = get_user_meta($user_id, 'caelis_mention_notifications', true);
            if ($pref === 'never') continue;

            // Default to digest
            if (empty($pref)) $pref = 'digest';

            if ($pref === 'immediate') {
                $this->send_immediate_notification($user_id, $author_name, $post, $comment);
            } else {
                $this->queue_for_digest($user_id, $comment_id);
            }
        }
    }

    /**
     * Send immediate notification via email
     */
    private function send_immediate_notification($user_id, $author_name, $post, $comment) {
        $user = get_userdata($user_id);
        if (!$user || !$user->user_email) return;

        $post_title = $post->post_title;
        $post_url = home_url('/people/' . $post->ID);

        $subject = sprintf('%s mentioned you in a note about %s', $author_name, $post_title);
        $content = wp_strip_all_tags($comment->comment_content);
        $preview = strlen($content) > 200 ? substr($content, 0, 200) . '...' : $content;

        $message = sprintf(
            "<p>%s mentioned you in a note:</p>\n<blockquote>%s</blockquote>\n<p><a href=\"%s\">View %s</a></p>",
            esc_html($author_name),
            esc_html($preview),
            esc_url($post_url),
            esc_html($post_title)
        );

        wp_mail(
            $user->user_email,
            $subject,
            $message,
            ['Content-Type: text/html; charset=UTF-8']
        );
    }

    /**
     * Queue mention for inclusion in user's next digest
     */
    private function queue_for_digest($user_id, $comment_id) {
        $queued = get_user_meta($user_id, '_queued_mention_notifications', true);
        if (!is_array($queued)) $queued = [];

        // Add to queue if not already present
        if (!in_array($comment_id, $queued)) {
            $queued[] = $comment_id;
            update_user_meta($user_id, '_queued_mention_notifications', $queued);
        }
    }

    /**
     * Get and clear queued mentions for a user (called by PRM_Reminders)
     *
     * @param int $user_id User ID
     * @return array Array of mention data for digest
     */
    public static function get_queued_mentions($user_id) {
        $queued = get_user_meta($user_id, '_queued_mention_notifications', true);
        if (!is_array($queued) || empty($queued)) return [];

        $mentions = [];
        foreach ($queued as $comment_id) {
            $comment = get_comment($comment_id);
            if (!$comment) continue;

            $post = get_post($comment->comment_post_ID);
            if (!$post) continue;

            $author = get_userdata($comment->user_id);

            $mentions[] = [
                'author' => $author ? $author->display_name : 'Someone',
                'post_title' => $post->post_title,
                'post_url' => home_url('/people/' . $post->ID),
                'preview' => wp_trim_words(wp_strip_all_tags($comment->comment_content), 20),
            ];
        }

        // Clear the queue
        delete_user_meta($user_id, '_queued_mention_notifications');

        return $mentions;
    }
}
