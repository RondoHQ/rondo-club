<?php
/**
 * Slack notification channel
 *
 * Handles Slack-based notification delivery via OAuth or webhooks.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slack notification channel
 */
class PRM_Slack_Channel extends PRM_Notification_Channel {

	public function get_channel_id() {
		return 'slack';
	}

	public function get_channel_name() {
		return __( 'Slack', 'caelis' );
	}

	public function is_enabled_for_user( $user_id ) {
		$channels = get_user_meta( $user_id, 'caelis_notification_channels', true );
		if ( ! is_array( $channels ) ) {
			return false;
		}
		if ( ! in_array( 'slack', $channels ) ) {
			return false;
		}
		// Check if bot token is configured (OAuth) or webhook (legacy)
		$bot_token = get_user_meta( $user_id, 'caelis_slack_bot_token', true );
		$webhook   = get_user_meta( $user_id, 'caelis_slack_webhook', true );
		return ! empty( $bot_token ) || ! empty( $webhook );
	}

	public function get_user_config( $user_id ) {
		// Prefer OAuth bot token over webhook
		$bot_token = get_user_meta( $user_id, 'caelis_slack_bot_token', true );
		if ( ! empty( $bot_token ) ) {
			return [
				'bot_token'    => base64_decode( $bot_token ),
				'workspace_id' => get_user_meta( $user_id, 'caelis_slack_workspace_id', true ),
				'target'       => get_user_meta( $user_id, 'caelis_slack_target', true ), // Channel or user ID
			];
		}

		// Fallback to webhook for legacy support
		$webhook = get_user_meta( $user_id, 'caelis_slack_webhook', true );
		if ( ! empty( $webhook ) ) {
			return [
				'webhook_url' => $webhook,
			];
		}

		return false;
	}

	public function send( $user_id, $digest_data, $target = null ) {
		$config = $this->get_user_config( $user_id );
		if ( ! $config ) {
			return false;
		}

		// Check what content we have
		$has_dates = ! empty( $digest_data['today'] ) ||
					! empty( $digest_data['tomorrow'] ) ||
					! empty( $digest_data['rest_of_week'] );

		$has_todos = isset( $digest_data['todos'] ) && (
					! empty( $digest_data['todos']['today'] ) ||
					! empty( $digest_data['todos']['tomorrow'] ) ||
					! empty( $digest_data['todos']['rest_of_week'] ) );

		$has_mentions           = ! empty( $digest_data['mentions'] );
		$has_workspace_activity = ! empty( $digest_data['workspace_activity'] );
		$has_collab             = $has_mentions || $has_workspace_activity;

		// Don't send if there's no content at all
		if ( ! $has_dates && ! $has_todos && ! $has_collab ) {
			return false;
		}

		$blocks = $this->format_slack_blocks( $digest_data );

		// Use OAuth bot token if available
		if ( isset( $config['bot_token'] ) && ! empty( $config['bot_token'] ) ) {
			// Get targets - use parameter if provided, otherwise use configured targets
			$targets = [];
			if ( ! empty( $target ) ) {
				// Single target passed as parameter (for backward compatibility)
				$targets = [ $target ];
			} else {
				// Get configured targets from user meta
				$targets = get_user_meta( $user_id, 'caelis_slack_targets', true );
				if ( ! is_array( $targets ) || empty( $targets ) ) {
					// Fallback to user's Slack user ID (DM) if no targets configured
					$slack_user_id = get_user_meta( $user_id, 'caelis_slack_user_id', true );
					if ( ! empty( $slack_user_id ) ) {
						$targets = [ $slack_user_id ];
					}
				}
			}

			// If still no targets, can't send
			if ( empty( $targets ) ) {
				error_log( 'PRM Slack: No targets specified for user ' . $user_id );
				return false;
			}

			// Send to all targets
			$success = true;
			foreach ( $targets as $target_id ) {
				$result = $this->send_via_web_api( $config['bot_token'], $blocks, $target_id );
				if ( ! $result ) {
					$success = false;
				}
			}
			return $success;
		}

		// Fallback to webhook for legacy support
		if ( isset( $config['webhook_url'] ) && ! empty( $config['webhook_url'] ) ) {
			return $this->send_via_webhook( $config['webhook_url'], $blocks );
		}

		return false;
	}

	/**
	 * Send message via Slack Web API (OAuth)
	 */
	private function send_via_web_api( $bot_token, $blocks, $target ) {

		$payload = [
			'channel' => $target,
			'text'    => __( 'Your Reminders & Todos for This Week', 'caelis' ),
			'blocks'  => $blocks,
		];

		$response = wp_remote_post(
			'https://slack.com/api/chat.postMessage',
			[
				'body'    => json_encode( $payload ),
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $bot_token,
				],
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'PRM Slack Web API error: ' . $response->get_error_message() );
			return false;
		}

		$body        = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code >= 200 && $status_code < 300 && ! empty( $body['ok'] ) ) {
			return true;
		}

		error_log( 'PRM Slack Web API error: ' . ( isset( $body['error'] ) ? $body['error'] : 'Unknown error' ) );
		return false;
	}

	/**
	 * Send message via webhook (legacy support)
	 */
	private function send_via_webhook( $webhook_url, $blocks ) {
		$logo_url = $this->get_logo_url();

		$payload = [
			'text'     => __( 'Your Important Dates for This Week', 'caelis' ),
			'blocks'   => $blocks,
			'username' => 'Caelis',
		];

		if ( $logo_url ) {
			$payload['icon_url'] = $logo_url;
		}

		$response = wp_remote_post(
			$webhook_url,
			[
				'body'    => json_encode( $payload ),
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'PRM Slack notification error: ' . $response->get_error_message() );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		return $status_code >= 200 && $status_code < 300;
	}

	/**
	 * Format Slack message blocks
	 * Made public so it can be called from REST API for slash commands
	 */
	public function format_slack_blocks( $digest_data ) {
		$blocks   = [];
		$site_url = home_url();

		$todos = isset( $digest_data['todos'] ) ? $digest_data['todos'] : [
			'today'        => [],
			'tomorrow'     => [],
			'rest_of_week' => [],
		];

		// Build text content for all sections
		$text_parts = [];

		// Today section
		$text_parts[] = sprintf( '*<%s|%s> %s*', home_url(), 'Caelis', __( 'Today', 'caelis' ) );
		$text_parts[] = ''; // Empty line

		$has_today_items = false;

		if ( ! empty( $digest_data['today'] ) ) {
			$has_today_items = true;
			foreach ( $digest_data['today'] as $date ) {
				$date_formatted = date_i18n(
					get_option( 'date_format' ),
					strtotime( $date['next_occurrence'] )
				);

				// Check if person name is in title
				$person_in_title = $this->find_person_in_title( $date['title'], $date['related_people'] );
				if ( $person_in_title ) {
					// Replace name in title with link
					$title_with_link = $this->replace_name_in_title_slack( $date['title'], $person_in_title, $site_url );
					$text_parts[]    = sprintf( '• %s - %s', $title_with_link, $date_formatted );
				} else {
					// Show title normally
					$text_parts[] = sprintf( '• %s - %s', $date['title'], $date_formatted );
				}
			}
		}

		if ( ! empty( $todos['today'] ) ) {
			$has_today_items = true;
			foreach ( $todos['today'] as $todo ) {
				$person_link  = sprintf( '<%s|%s>', $site_url . '/people/' . $todo['person_id'], $todo['person_name'] );
				$overdue_text = ! empty( $todo['is_overdue'] ) ? ' _(overdue)_' : '';
				$text_parts[] = sprintf( '☐ %s%s → %s', $todo['content'], $overdue_text, $person_link );
			}
		}

		if ( ! $has_today_items ) {
			$text_parts[] = '• ' . __( 'No reminders or todos', 'caelis' );
		}

		$text_parts[] = ''; // Empty line

		// Tomorrow section
		$text_parts[] = '*' . __( 'Tomorrow', 'caelis' ) . '*';
		$text_parts[] = ''; // Empty line

		$has_tomorrow_items = false;

		if ( ! empty( $digest_data['tomorrow'] ) ) {
			$has_tomorrow_items = true;
			foreach ( $digest_data['tomorrow'] as $date ) {
				$date_formatted = date_i18n(
					get_option( 'date_format' ),
					strtotime( $date['next_occurrence'] )
				);

				$person_in_title = $this->find_person_in_title( $date['title'], $date['related_people'] );
				if ( $person_in_title ) {
					$title_with_link = $this->replace_name_in_title_slack( $date['title'], $person_in_title, $site_url );
					$text_parts[]    = sprintf( '• %s - %s', $title_with_link, $date_formatted );
				} else {
					$text_parts[] = sprintf( '• %s - %s', $date['title'], $date_formatted );
				}
			}
		}

		if ( ! empty( $todos['tomorrow'] ) ) {
			$has_tomorrow_items = true;
			foreach ( $todos['tomorrow'] as $todo ) {
				$person_link  = sprintf( '<%s|%s>', $site_url . '/people/' . $todo['person_id'], $todo['person_name'] );
				$text_parts[] = sprintf( '☐ %s → %s', $todo['content'], $person_link );
			}
		}

		if ( ! $has_tomorrow_items ) {
			$text_parts[] = '• ' . __( 'No reminders or todos', 'caelis' );
		}

		$text_parts[] = ''; // Empty line

		// Rest of week section
		$text_parts[] = '*' . __( 'Rest of the week', 'caelis' ) . '*';
		$text_parts[] = ''; // Empty line

		$has_week_items = false;

		if ( ! empty( $digest_data['rest_of_week'] ) ) {
			$has_week_items = true;
			foreach ( $digest_data['rest_of_week'] as $date ) {
				$date_formatted = date_i18n(
					get_option( 'date_format' ),
					strtotime( $date['next_occurrence'] )
				);

				$person_in_title = $this->find_person_in_title( $date['title'], $date['related_people'] );
				if ( $person_in_title ) {
					$title_with_link = $this->replace_name_in_title_slack( $date['title'], $person_in_title, $site_url );
					$text_parts[]    = sprintf( '• %s - %s', $title_with_link, $date_formatted );
				} else {
					$text_parts[] = sprintf( '• %s - %s', $date['title'], $date_formatted );
				}
			}
		}

		if ( ! empty( $todos['rest_of_week'] ) ) {
			$has_week_items = true;
			foreach ( $todos['rest_of_week'] as $todo ) {
				$person_link        = sprintf( '<%s|%s>', $site_url . '/people/' . $todo['person_id'], $todo['person_name'] );
				$due_date_formatted = date_i18n( 'M j', strtotime( $todo['due_date'] ) );
				$text_parts[]       = sprintf( '☐ %s (%s) → %s', $todo['content'], $due_date_formatted, $person_link );
			}
		}

		if ( ! $has_week_items ) {
			$text_parts[] = '• ' . __( 'No reminders or todos', 'caelis' );
		}

		// Mentions section
		if ( ! empty( $digest_data['mentions'] ) ) {
			$text_parts[] = ''; // Empty line
			$text_parts[] = '*' . __( 'You were mentioned', 'caelis' ) . '* 💬';
			$text_parts[] = ''; // Empty line

			foreach ( $digest_data['mentions'] as $mention ) {
				$post_link    = sprintf( '<%s|%s>', $mention['post_url'], $mention['post_title'] );
				$text_parts[] = sprintf( '• *%s* mentioned you on %s:', $mention['author'], $post_link );
				$text_parts[] = sprintf( '  _%s_', $mention['preview'] );
			}
		}

		// Workspace activity section
		if ( ! empty( $digest_data['workspace_activity'] ) ) {
			$text_parts[] = ''; // Empty line
			$text_parts[] = '*' . __( 'Workspace Activity', 'caelis' ) . '* 📋';
			$text_parts[] = ''; // Empty line

			foreach ( $digest_data['workspace_activity'] as $activity ) {
				$post_link    = sprintf( '<%s|%s>', $activity['post_url'], $activity['post_title'] );
				$text_parts[] = sprintf( '• *%s* added a note on %s:', $activity['author'], $post_link );
				$text_parts[] = sprintf( '  _%s_', $activity['preview'] );
			}
		}

		// Combine all text parts into a single block
		$blocks[] = [
			'type' => 'section',
			'text' => [
				'type' => 'mrkdwn',
				'text' => implode( "\n", $text_parts ),
			],
		];

		return $blocks;
	}

	/**
	 * Format people names as clickable Slack markdown links
	 */
	private function format_slack_people_links( $people ) {
		$site_url = home_url();
		$links    = [];
		foreach ( $people as $person ) {
			$person_url = $site_url . '/people/' . $person['id'];
			$links[]    = sprintf( '<%s|%s>', $person_url, $person['name'] );
		}
		return implode( ', ', $links );
	}

	/**
	 * Replace person name in title with a clickable link (for Slack)
	 */
	private function replace_name_in_title_slack( $title, $person, $site_url ) {
		$person_name = $person['name'];
		$person_url  = $site_url . '/people/' . $person['id'];
		$person_link = sprintf( '<%s|%s>', $person_url, $person_name );

		// Replace the name in the title (case-insensitive)
		return preg_replace( '/' . preg_quote( $person_name, '/' ) . '/i', $person_link, $title, 1 );
	}

	/**
	 * Get logo URL for Slack messages
	 *
	 * Note: Slack prefers PNG/JPG images. SVG may not display correctly.
	 * The image must be publicly accessible.
	 */
	private function get_logo_url() {
		// Try to use favicon or logo from theme
		$theme_url = get_template_directory_uri();

		// Try PNG first (better Slack support)
		$logo_png    = $theme_url . '/logo.png';
		$logo_jpg    = $theme_url . '/logo.jpg';
		$favicon_svg = $theme_url . '/favicon.svg';

		// Return PNG if it exists, otherwise try JPG, then SVG
		// In production, ensure logo.png exists for best Slack compatibility
		return $favicon_svg; // For now, use SVG. Replace with PNG/JPG path when available
	}
}
