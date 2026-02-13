<?php
/**
 * Email notification channel
 *
 * Handles email-based notification delivery for daily digests.
 */

namespace Rondo\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email notification channel
 */
class EmailChannel extends Channel {

	public function get_channel_id() {
		return 'email';
	}

	public function get_channel_name() {
		return __( 'Email', 'rondo' );
	}

	public function is_enabled_for_user( $user_id ) {
		$channels = get_user_meta( $user_id, 'rondo_notification_channels', true );
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
		return [
			'email' => $user->user_email,
		];
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
			? sprintf( __( '[%1$s] Your digest (including team activity) - %2$s', 'rondo' ), $site_name, $today_formatted )
			: sprintf( __( '[%1$s] Your Reminders & Todos - %2$s', 'rondo' ), $site_name, $today_formatted );

		$message = $this->format_email_message( $user, $digest_data );

		// Set custom from name and email
		add_filter( 'wp_mail_from', [ $this, 'set_email_from_address' ] );
		add_filter( 'wp_mail_from_name', [ $this, 'set_email_from_name' ] );

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$result = wp_mail( $user->user_email, $subject, $message, $headers );

		// Remove filters after sending
		remove_filter( 'wp_mail_from', [ $this, 'set_email_from_address' ] );
		remove_filter( 'wp_mail_from_name', [ $this, 'set_email_from_name' ] );

		return $result;
	}

	/**
	 * Format email message body as HTML
	 */
	private function format_email_message( $user, $digest_data ) {
		$site_url        = home_url();
		$today_formatted = date_i18n( get_option( 'date_format' ) );

		$todos = isset( $digest_data['todos'] ) ? $digest_data['todos'] : [
			'today'        => [],
			'tomorrow'     => [],
			'rest_of_week' => [],
		];

		$html  = '<html><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
		$html .= sprintf(
			'<p>Hello %s,</p><p>Here are your birthdays and to-dos for this week:</p>',
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
			'<p style="margin-top: 20px;"><a href="%s">Visit Rondo</a> to see more details.</p>',
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
		return 'Rondo';
	}

	/**
	 * Format people names as clickable HTML links
	 */
	private function format_people_links( $people, $site_url ) {
		$links = [];
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
}
