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
		return in_array( 'email', $channels, true );
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

		if ( ! $this->has_content( $digest_data ) ) {
			return false;
		}

		$has_collab = ! empty( $digest_data['mentions'] ) || ! empty( $digest_data['workspace_activity'] );

		$site_name       = get_bloginfo( 'name' );
		$today_formatted = date_i18n( get_option( 'date_format' ) );

		if ( $has_collab ) {
			// translators: %1$s is the site name, %2$s is the formatted date.
			$subject = sprintf( __( '[%1$s] Your digest (including team activity) - %2$s', 'rondo' ), $site_name, $today_formatted );
		} else {
			// translators: %1$s is the site name, %2$s is the formatted date.
			$subject = sprintf( __( '[%1$s] Your Reminders & Todos - %2$s', 'rondo' ), $site_name, $today_formatted );
		}

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
		$site_url    = home_url();
		$date_format = get_option( 'date_format' );

		$todos = $digest_data['todos'] ?? [
			'today'        => [],
			'tomorrow'     => [],
			'rest_of_week' => [],
		];

		$html = sprintf(
			'<p style="margin:0 0 16px;color:#0f172a;font-size:16px;line-height:1.7;">Hallo %s,</p><p style="margin:0 0 16px;color:#0f172a;font-size:16px;line-height:1.7;">Hier is je overzicht van verjaardagen, taken en teamactiviteit.</p>',
			esc_html( $user->display_name )
		);

		// Today section
		$html .= $this->render_digest_section(
			'Today',
			$digest_data['today'] ?? [],
			$todos['today'],
			$site_url,
			$date_format,
			true
		);

		// Tomorrow section
		$html .= $this->render_digest_section(
			'Tomorrow',
			$digest_data['tomorrow'] ?? [],
			$todos['tomorrow'],
			$site_url,
			$date_format,
			false
		);

		// Rest of week section
		$html .= $this->render_digest_section(
			'This week',
			$digest_data['rest_of_week'] ?? [],
			$todos['rest_of_week'],
			$site_url,
			$date_format,
			false
		);

		// Mentions section
		if ( ! empty( $digest_data['mentions'] ) ) {
			$html .= '<h3 style="margin:24px 0 10px;color:#2563eb;font-size:18px;line-height:1.3;">Je bent genoemd</h3>';
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
			$html .= '<h3 style="margin:24px 0 10px;color:#059669;font-size:18px;line-height:1.3;">Teamactiviteit</h3>';
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

		return EmailTemplate::render(
			[
				'brand_name' => get_bloginfo( 'name' ),
				'preheader'  => 'Je Rondo weekoverzicht staat klaar',
				'eyebrow'    => 'Digest',
				'heading'    => 'Je weekoverzicht',
				'body_html'  => $html,
				'cta_url'    => $site_url,
				'cta_label'  => 'Open Rondo',
			]
		);
	}

	/**
	 * Check if digest data has any content worth sending
	 */
	private function has_content( $digest_data ) {
		$has_dates = ! empty( $digest_data['today'] ) ||
					! empty( $digest_data['tomorrow'] ) ||
					! empty( $digest_data['rest_of_week'] );

		$has_todos = isset( $digest_data['todos'] ) && (
					! empty( $digest_data['todos']['today'] ) ||
					! empty( $digest_data['todos']['tomorrow'] ) ||
					! empty( $digest_data['todos']['rest_of_week'] ) );

		$has_collab = ! empty( $digest_data['mentions'] ) || ! empty( $digest_data['workspace_activity'] );

		return $has_dates || $has_todos || $has_collab;
	}

	/**
	 * Render a digest section (Today/Tomorrow/This week)
	 */
	private function render_digest_section( $section_title, $dates, $todos, $site_url, $date_format, $check_overdue ) {
		if ( empty( $dates ) && empty( $todos ) ) {
			return '';
		}

		$html  = sprintf( '<h3 style="margin:24px 0 10px;color:#0f172a;font-size:18px;line-height:1.3;">%s</h3>', esc_html( $section_title ) );
		$html .= $this->render_date_items( $dates, $site_url, $date_format );
		$html .= $this->render_todo_items( $todos, $site_url, $date_format, $check_overdue );

		return $html;
	}

	/**
	 * Render date items
	 */
	private function render_date_items( $dates, $site_url, $date_format ) {
		$html = '';

		foreach ( $dates as $date ) {
			$next_occurrence_ts = strtotime( $date['next_occurrence'] );
			$date_value_ts      = ! empty( $date['date_value'] ) ? strtotime( $date['date_value'] ) : false;
			$display_ts         = $next_occurrence_ts;
			$age_suffix         = '';

			if ( $date_value_ts && ! empty( $date['is_recurring'] ) && empty( $date['year_unknown'] ) ) {
				$birth_year = (int) gmdate( 'Y', $date_value_ts );
				$occ_year   = (int) gmdate( 'Y', $next_occurrence_ts );
				if ( $birth_year > 0 && $birth_year < $occ_year ) {
					$display_ts = $date_value_ts;
					$age_suffix = sprintf( ' (wordt %d)', $occ_year - $birth_year );
				}
			}

			$date_formatted = date_i18n( $date_format, $display_ts ) . $age_suffix;

			$person_in_title = $this->find_person_in_title( $date['title'], $date['related_people'] );
			if ( $person_in_title ) {
				$title_with_link      = $this->replace_name_in_title_email( $date['title'], $person_in_title, $site_url );
				$safe_title_with_link = wp_kses(
					$title_with_link,
					[
						'a' => [
							'href'   => true,
							'title'  => true,
							'target' => true,
							'rel'    => true,
						],
					]
				);
				$html                .= sprintf(
					'<p style="margin: 5px 0;">• <strong>%s</strong> - %s</p>',
					$safe_title_with_link,
					esc_html( $date_formatted )
				);
			} else {
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

		return $html;
	}

	/**
	 * Render todo items
	 */
	private function render_todo_items( $todos, $site_url, $date_format, $check_overdue ) {
		$html = '';

		foreach ( $todos as $todo ) {
			$person_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $site_url . '/people/' . $todo['person_id'] ),
				esc_html( $todo['person_name'] )
			);

			if ( $check_overdue ) {
				$overdue_text = ! empty( $todo['is_overdue'] ) ? ' <span style="color: #dc2626;">(overdue)</span>' : '';
				$html        .= sprintf(
					'<p style="margin: 5px 0;">☐ %s%s<br><span style="font-size: 0.9em; color: #666; margin-left: 20px;">→ %s</span></p>',
					esc_html( $todo['content'] ),
					$overdue_text,
					$person_link
				);
			} else {
				$due_date_formatted = date_i18n( $date_format, strtotime( $todo['due_date'] ) );
				$html              .= sprintf(
					'<p style="margin: 5px 0;">☐ %s <span style="color: #666;">(%s)</span><br><span style="font-size: 0.9em; color: #666; margin-left: 20px;">→ %s</span></p>',
					esc_html( $todo['content'] ),
					esc_html( $due_date_formatted ),
					$person_link
				);
			}
		}

		return $html;
	}

	/**
	 * Set email from address
	 *
	 * Extracts the root domain (e.g. svawc.nl from rondo.svawc.nl) so the
	 * From address matches the verified sending domain in Lettermint.
	 */
	public function set_email_from_address( $from_email ) {
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$parts  = explode( '.', $host );
		$domain = count( $parts ) >= 2
			? implode( '.', array_slice( $parts, -2 ) )
			: $host;
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
