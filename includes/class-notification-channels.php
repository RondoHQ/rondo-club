<?php
/**
 * Notification Channels System
 *
 * Handles multi-channel notification delivery (Email, Slack, etc.)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for notification channels
 */
abstract class PRM_Notification_Channel {

	/**
	 * Send notification to user via this channel
	 *
	 * @param int   $user_id     User ID
	 * @param array $digest_data Weekly digest data (today, tomorrow, rest_of_week)
	 * @return bool Success status
	 */
	abstract public function send( $user_id, $digest_data );

	/**
	 * Check if this channel is enabled for a user
	 *
	 * @param int $user_id User ID
	 * @return bool
	 */
	abstract public function is_enabled_for_user( $user_id );

	/**
	 * Get user-specific configuration for this channel
	 *
	 * @param int $user_id User ID
	 * @return array|false
	 */
	abstract public function get_user_config( $user_id );

	/**
	 * Get channel identifier
	 *
	 * @return string
	 */
	abstract public function get_channel_id();

	/**
	 * Get channel display name
	 *
	 * @return string
	 */
	abstract public function get_channel_name();

	/**
	 * Check if a person's name appears in a title and return the person if found
	 * Returns the first person whose name appears in the title, or null
	 *
	 * @param string $title The date title
	 * @param array  $people Array of person data
	 * @return array|null Person data if found, null otherwise
	 */
	protected function find_person_in_title( $title, $people ) {
		foreach ( $people as $person ) {
			$person_name = $person['name'];
			// Check if the person's name appears in the title (case-insensitive)
			if ( stripos( $title, $person_name ) !== false ) {
				return $person;
			}
		}
		return null;
	}
}

/**
 * Email notification channel
 */
class PRM_Email_Channel extends PRM_Notification_Channel {

	public function get_channel_id() {
		return 'email';
	}

	public function get_channel_name() {
		return __( 'Email', 'caelis' );
	}

	public function is_enabled_for_user( $user_id ) {
		$channels = get_user_meta( $user_id, 'caelis_notification_channels', true );
		if ( ! is_array( $channels ) ) {
			// Default to enabled for email if not set
			return true;
		}
		return in_array( 'email', $channels );
	}

	public function get_user_config( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user || ! $user->user_email ) {
			return false;
		}
		return array(
			'email' => $user->user_email,
		);
	}

	public function send( $user_id, $digest_data ) {
		$user = get_userdata( $user_id );

		if ( ! $user || ! $user->user_email ) {
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

		$site_name       = get_bloginfo( 'name' );
		$today_formatted = date_i18n( get_option( 'date_format' ) );

		// Update subject line to indicate collaborative activity
		$subject = $has_collab
			? sprintf( __( '[%1$s] Your digest (including team activity) - %2$s', 'caelis' ), $site_name, $today_formatted )
			: sprintf( __( '[%1$s] Your Reminders & Todos - %2$s', 'caelis' ), $site_name, $today_formatted );

		$message = $this->format_email_message( $user, $digest_data );

		// Set custom from name and email
		add_filter( 'wp_mail_from', array( $this, 'set_email_from_address' ) );
		add_filter( 'wp_mail_from_name', array( $this, 'set_email_from_name' ) );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$result = wp_mail( $user->user_email, $subject, $message, $headers );

		// Remove filters after sending
		remove_filter( 'wp_mail_from', array( $this, 'set_email_from_address' ) );
		remove_filter( 'wp_mail_from_name', array( $this, 'set_email_from_name' ) );

		return $result;
	}

	/**
	 * Format email message body as HTML
	 */
	private function format_email_message( $user, $digest_data ) {
		$site_url        = home_url();
		$today_formatted = date_i18n( get_option( 'date_format' ) );

		$todos = isset( $digest_data['todos'] ) ? $digest_data['todos'] : array(
			'today'        => array(),
			'tomorrow'     => array(),
			'rest_of_week' => array(),
		);

		$html  = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$html .= sprintf(
			'<p>Hello %s,</p><p>Here are your important dates and to-dos for this week:</p>',
			esc_html( $user->display_name )
		);

		// Today section
		if ( ! empty( $digest_data['today'] ) || ! empty( $todos['today'] ) ) {
			$html .= '<h3 style="margin-top: 20px; margin-bottom: 10px;">Today</h3>';

			// Dates
			foreach ( $digest_data['today'] as $date ) {
				$date_formatted = date_i18n(
					get_option( 'date_format' ),
					strtotime( $date['next_occurrence'] )
				);

				// Check if person name is in title
				$person_in_title = $this->find_person_in_title( $date['title'], $date['related_people'] );
				if ( $person_in_title ) {
					// Replace name in title with link
					$title_with_link = $this->replace_name_in_title_email( $date['title'], $person_in_title, $site_url );
					$html           .= sprintf(
						'<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
						$title_with_link,
						esc_html( $date_formatted )
					);
				} else {
					// Show title normally and add people links below
					$html        .= sprintf(
						'<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
						esc_html( $date['title'] ),
						esc_html( $date_formatted )
					);
					$people_links = $this->format_people_links( $date['related_people'], $site_url );
					if ( ! empty( $people_links ) ) {
						$html .= sprintf( '<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links );
					}
				}
			}

			// Todos
			foreach ( $todos['today'] as $todo ) {
				$overdue_text = ! empty( $todo['is_overdue'] ) ? ' <span style="color: #dc2626;">(overdue)</span>' : '';
				$person_link  = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $site_url . '/people/' . $todo['person_id'] ),
					esc_html( $todo['person_name'] )
				);
				$html        .= sprintf(
					'<p style="margin: 5px 0;">☐ %s%s<br><span style="font-size: 0.9em; color: #666; margin-left: 20px;">→ %s</span></p>',
					esc_html( $todo['content'] ),
					$overdue_text,
					$person_link
				);
			}
		}

		// Tomorrow section
		if ( ! empty( $digest_data['tomorrow'] ) || ! empty( $todos['tomorrow'] ) ) {
			$html .= '<h3 style="margin-top: 20px; margin-bottom: 10px;">Tomorrow</h3>';

			// Dates
			foreach ( $digest_data['tomorrow'] as $date ) {
				$date_formatted = date_i18n(
					get_option( 'date_format' ),
					strtotime( $date['next_occurrence'] )
				);

				// Check if person name is in title
				$person_in_title = $this->find_person_in_title( $date['title'], $date['related_people'] );
				if ( $person_in_title ) {
					// Replace name in title with link
					$title_with_link = $this->replace_name_in_title_email( $date['title'], $person_in_title, $site_url );
					$html           .= sprintf(
						'<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
						$title_with_link,
						esc_html( $date_formatted )
					);
				} else {
					// Show title normally and add people links below
					$html        .= sprintf(
						'<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
						esc_html( $date['title'] ),
						esc_html( $date_formatted )
					);
					$people_links = $this->format_people_links( $date['related_people'], $site_url );
					if ( ! empty( $people_links ) ) {
						$html .= sprintf( '<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links );
					}
				}
			}

			// Todos
			foreach ( $todos['tomorrow'] as $todo ) {
				$person_link = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $site_url . '/people/' . $todo['person_id'] ),
					esc_html( $todo['person_name'] )
				);
				$html       .= sprintf(
					'<p style="margin: 5px 0;">☐ %s<br><span style="font-size: 0.9em; color: #666; margin-left: 20px;">→ %s</span></p>',
					esc_html( $todo['content'] ),
					$person_link
				);
			}
		}

		// Rest of week section
		if ( ! empty( $digest_data['rest_of_week'] ) || ! empty( $todos['rest_of_week'] ) ) {
			$html .= '<h3 style="margin-top: 20px; margin-bottom: 10px;">This week</h3>';

			// Dates
			foreach ( $digest_data['rest_of_week'] as $date ) {
				$date_formatted = date_i18n(
					get_option( 'date_format' ),
					strtotime( $date['next_occurrence'] )
				);

				// Check if person name is in title
				$person_in_title = $this->find_person_in_title( $date['title'], $date['related_people'] );
				if ( $person_in_title ) {
					// Replace name in title with link
					$title_with_link = $this->replace_name_in_title_email( $date['title'], $person_in_title, $site_url );
					$html           .= sprintf(
						'<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
						$title_with_link,
						esc_html( $date_formatted )
					);
				} else {
					// Show title normally and add people links below
					$html        .= sprintf(
						'<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
						esc_html( $date['title'] ),
						esc_html( $date_formatted )
					);
					$people_links = $this->format_people_links( $date['related_people'], $site_url );
					if ( ! empty( $people_links ) ) {
						$html .= sprintf( '<p style="margin: 5px 0; margin-left: 20px;">%s</p>', $people_links );
					}
				}
			}

			// Todos
			foreach ( $todos['rest_of_week'] as $todo ) {
				$due_date_formatted = date_i18n( get_option( 'date_format' ), strtotime( $todo['due_date'] ) );
				$person_link        = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $site_url . '/people/' . $todo['person_id'] ),
					esc_html( $todo['person_name'] )
				);
				$html              .= sprintf(
					'<p style="margin: 5px 0;">☐ %s <span style="color: #666;">(%s)</span><br><span style="font-size: 0.9em; color: #666; margin-left: 20px;">→ %s</span></p>',
					esc_html( $todo['content'] ),
					esc_html( $due_date_formatted ),
					$person_link
				);
			}
		}

		// Mentions section
		if ( ! empty( $digest_data['mentions'] ) ) {
			$html .= '<h3 style="margin-top: 20px; margin-bottom: 10px; color: #2563eb;">You were mentioned</h3>';
			foreach ( $digest_data['mentions'] as $mention ) {
				$html .= sprintf(
					'<p style="margin: 5px 0; padding-left: 10px; border-left: 3px solid #2563eb;"><strong>%s</strong> mentioned you on <a href="%s">%s</a>:<br><em style="color: #666;">%s</em></p>',
					esc_html( $mention['author'] ),
					esc_url( $mention['post_url'] ),
					esc_html( $mention['post_title'] ),
					esc_html( $mention['preview'] )
				);
			}
		}

		// Workspace activity section
		if ( ! empty( $digest_data['workspace_activity'] ) ) {
			$html .= '<h3 style="margin-top: 20px; margin-bottom: 10px; color: #059669;">Workspace Activity</h3>';
			foreach ( $digest_data['workspace_activity'] as $activity ) {
				$html .= sprintf(
					'<p style="margin: 5px 0; padding-left: 10px; border-left: 3px solid #059669;"><strong>%s</strong> added a note on <a href="%s">%s</a>:<br><em style="color: #666;">%s</em></p>',
					esc_html( $activity['author'] ),
					esc_url( $activity['post_url'] ),
					esc_html( $activity['post_title'] ),
					esc_html( $activity['preview'] )
				);
			}
		}

		$html .= sprintf(
			'<p style="margin-top: 20px;"><a href="%s">Visit Caelis</a> to see more details.</p>',
			esc_url( $site_url )
		);

		$html .= '</body></html>';

		return $html;
	}

	/**
	 * Set email from address
	 */
	public function set_email_from_address( $from_email ) {
		$domain = parse_url( home_url(), PHP_URL_HOST );
		return 'notifications@' . $domain;
	}

	/**
	 * Set email from name
	 */
	public function set_email_from_name( $from_name ) {
		return 'Caelis';
	}

	/**
	 * Format people names as clickable HTML links
	 */
	private function format_people_links( $people, $site_url ) {
		$links = array();
		foreach ( $people as $person ) {
			$person_url  = esc_url( $site_url . '/people/' . $person['id'] );
			$person_name = esc_html( $person['name'] );
			$links[]     = sprintf( '<a href="%s">%s</a>', $person_url, $person_name );
		}
		return implode( ', ', $links );
	}

	/**
	 * Replace person name in title with a clickable link (for email)
	 */
	private function replace_name_in_title_email( $title, $person, $site_url ) {
		$person_name = esc_html( $person['name'] );
		$person_url  = esc_url( $site_url . '/people/' . $person['id'] );
		$person_link = sprintf( '<a href="%s">%s</a>', $person_url, $person_name );

		// Replace the name in the title (case-insensitive)
		return preg_replace( '/' . preg_quote( $person_name, '/' ) . '/i', $person_link, $title, 1 );
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
			return array(
				'bot_token'    => base64_decode( $bot_token ),
				'workspace_id' => get_user_meta( $user_id, 'caelis_slack_workspace_id', true ),
				'target'       => get_user_meta( $user_id, 'caelis_slack_target', true ), // Channel or user ID
			);
		}

		// Fallback to webhook for legacy support
		$webhook = get_user_meta( $user_id, 'caelis_slack_webhook', true );
		if ( ! empty( $webhook ) ) {
			return array(
				'webhook_url' => $webhook,
			);
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
			$targets = array();
			if ( ! empty( $target ) ) {
				// Single target passed as parameter (for backward compatibility)
				$targets = array( $target );
			} else {
				// Get configured targets from user meta
				$targets = get_user_meta( $user_id, 'caelis_slack_targets', true );
				if ( ! is_array( $targets ) || empty( $targets ) ) {
					// Fallback to user's Slack user ID (DM) if no targets configured
					$slack_user_id = get_user_meta( $user_id, 'caelis_slack_user_id', true );
					if ( ! empty( $slack_user_id ) ) {
						$targets = array( $slack_user_id );
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

		$payload = array(
			'channel' => $target,
			'text'    => __( 'Your Reminders & Todos for This Week', 'caelis' ),
			'blocks'  => $blocks,
		);

		$response = wp_remote_post(
			'https://slack.com/api/chat.postMessage',
			array(
				'body'    => json_encode( $payload ),
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $bot_token,
				),
				'timeout' => 10,
			)
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

		$payload = array(
			'text'     => __( 'Your Important Dates for This Week', 'caelis' ),
			'blocks'   => $blocks,
			'username' => 'Caelis',
		);

		if ( $logo_url ) {
			$payload['icon_url'] = $logo_url;
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'body'    => json_encode( $payload ),
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'timeout' => 10,
			)
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
		$blocks   = array();
		$site_url = home_url();

		$todos = isset( $digest_data['todos'] ) ? $digest_data['todos'] : array(
			'today'        => array(),
			'tomorrow'     => array(),
			'rest_of_week' => array(),
		);

		// Build text content for all sections
		$text_parts = array();

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
		$blocks[] = array(
			'type' => 'section',
			'text' => array(
				'type' => 'mrkdwn',
				'text' => implode( "\n", $text_parts ),
			),
		);

		return $blocks;
	}

	/**
	 * Format people names as clickable Slack markdown links
	 */
	private function format_slack_people_links( $people ) {
		$site_url = home_url();
		$links    = array();
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
