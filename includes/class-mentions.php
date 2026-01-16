<?php
/**
 * Handles @mention parsing, storage, and rendering
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Mentions {

	/**
	 * Parse user IDs from mention markup
	 * Markup format: @[Display Name](user_id)
	 *
	 * @param string $content Content with mention markup
	 * @return int[] Array of mentioned user IDs
	 */
	public static function parse_mention_ids( $content ) {
		$pattern = '/@\[[^\]]+\]\((\d+)\)/';
		preg_match_all( $pattern, $content, $matches );
		return array_map( 'intval', $matches[1] ?? [] );
	}

	/**
	 * Convert mention markup to linked HTML for display
	 *
	 * @param string $content Content with mention markup
	 * @return string Content with mentions as styled spans
	 */
	public static function render_mentions( $content ) {
		$pattern = '/@\[([^\]]+)\]\((\d+)\)/';
		return preg_replace_callback(
			$pattern,
			function ( $matches ) {
				$name    = esc_html( $matches[1] );
				$user_id = intval( $matches[2] );
				$user    = get_userdata( $user_id );
				if ( ! $user ) {
					return '@' . $name; // User deleted, show plain text
				}
				return sprintf(
					'<span class="mention" data-user-id="%d">@%s</span>',
					$user_id,
					$name
				);
			},
			$content
		);
	}

	/**
	 * Store mentioned user IDs as comment meta
	 *
	 * @param int $comment_id Comment ID
	 * @param string $content Comment content
	 * @return int[] Mentioned user IDs
	 */
	public static function save_mentions( $comment_id, $content ) {
		$mentioned_ids = self::parse_mention_ids( $content );
		if ( ! empty( $mentioned_ids ) ) {
			update_comment_meta( $comment_id, '_mentioned_users', $mentioned_ids );
		} else {
			delete_comment_meta( $comment_id, '_mentioned_users' );
		}
		return $mentioned_ids;
	}

	/**
	 * Get mentioned user IDs from comment
	 *
	 * @param int $comment_id Comment ID
	 * @return int[] Mentioned user IDs
	 */
	public static function get_mentions( $comment_id ) {
		$mentions = get_comment_meta( $comment_id, '_mentioned_users', true );
		return is_array( $mentions ) ? $mentions : [];
	}
}
