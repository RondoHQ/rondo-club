<?php
/**
 * Scheduled reminder and survey emails for volunteer shifts.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShiftEmailScheduler {

	const CRON_HOOK = 'rondo_shift_email_sweeper';

	/** A cron run may arrive late, but must never send an old reminder days later. */
	const DELIVERY_WINDOW_SECONDS = DAY_IN_SECONDS;

	private const REMINDER_DAYS = [ 14, 7, 2 ];

	private const DEFAULT_REMINDER_SUBJECT = 'Herinnering: {dienst} op {datum}';

	private const DEFAULT_REMINDER_BODY = "Hoi {naam},\n\nDit is een herinnering voor je dienst {dienst} op {datum} van {tijd} tot {eindtijd}.\n\nJe draait deze dienst samen met {medevrijwilligers}.";

	private const DEFAULT_SURVEY_SUBJECT = 'Hoe ging je dienst {dienst}?';

	private const DEFAULT_SURVEY_BODY = "Hoi {naam},\n\nBedankt voor je inzet bij {dienst}. We horen graag hoe de dienst is verlopen. Wil je onze korte enquête invullen?";

	private const DEFAULT_EARLY_CANCELLATION_SUBJECT = 'Je dienst {dienst} op {datum} gaat niet door';

	private const DEFAULT_EARLY_CANCELLATION_BODY = "Hoi {naam},\n\nJe vrijwilligersdienst {dienst} op {datum} van {tijd} tot {eindtijd} gaat helaas niet door.\n\nDeze annulering is minimaal 48 uur voor aanvang doorgegeven. De dienst telt daarom niet mee voor je vrijwilligersplicht. Kies een nieuwe dienst via Rondo.";

	private const DEFAULT_LAST_MINUTE_CANCELLATION_SUBJECT = 'Je dienst {dienst} op {datum} gaat niet door';

	private const DEFAULT_LAST_MINUTE_CANCELLATION_BODY = "Hoi {naam},\n\nJe vrijwilligersdienst {dienst} op {datum} van {tijd} tot {eindtijd} gaat helaas niet door.\n\nDeze annulering is minder dan 48 uur voor aanvang doorgegeven. De dienst telt daarom wel mee voor je vrijwilligersplicht. Je hoeft hiervoor geen nieuwe dienst te kiezen.";

	public function __construct() {
		add_action( 'init', [ $this, 'register_cron' ] );
		add_action( self::CRON_HOOK, [ $this, 'run_sweep' ] );
	}

	public function register_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
	}

	public static function unregister_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Send all reminder/survey emails due within the current delivery window.
	 *
	 * @param \DateTimeImmutable|null $now Injectable clock for regression tests.
	 * @return int Number of emails sent.
	 */
	public function run_sweep( ?\DateTimeImmutable $now = null ): int {
		$now = $now ?: current_datetime();

		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				'post_status'      => 'publish',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded 22-day shift window.
				'posts_per_page'   => 300,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'fields'           => 'ids',
				'meta_query'       => [
					[
						'key'     => 'start_datetime',
						'value'   => [ $now->modify( '-7 days' )->format( 'Y-m-d H:i:s' ), $now->modify( '+15 days' )->format( 'Y-m-d H:i:s' ) ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
				],
			]
		);

		$sent = 0;
		foreach ( $query->posts as $shift_id ) {
			$sent += $this->process_shift( (int) $shift_id, $now );
		}

		return $sent;
	}

	/**
	 * Immediately notify all retained assignees of a cancelled shift.
	 *
	 * Repeated calls only retry recipients whose earlier wp_mail() call failed.
	 *
	 * @return array{sent: int, already_sent: int, failed: int, no_email: int, failed_person_ids: int[], no_email_person_ids: int[]}
	 */
	public function send_cancellation_notifications( int $shift_id ): array {
		$result = [
			'sent'                => 0,
			'already_sent'        => 0,
			'failed'              => 0,
			'no_email'            => 0,
			'failed_person_ids'   => [],
			'no_email_person_ids' => [],
		];

		if ( (string) get_post_meta( $shift_id, 'status', true ) !== 'geannuleerd' ) {
			return $result;
		}

		$cancellation = ShiftCancellationService::details( $shift_id );
		$start        = $this->parse_datetime( (string) get_post_meta( $shift_id, 'start_datetime', true ) );
		$end          = $this->parse_datetime( (string) get_post_meta( $shift_id, 'end_datetime', true ) );
		$type_id      = (int) get_post_meta( $shift_id, 'dienst_type_id', true );
		$assigned     = array_values( array_unique( array_filter( array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) ) ) ) );
		if ( ! $cancellation || ! $start || ! $end || $type_id <= 0 || empty( $assigned ) ) {
			return $result;
		}

		$is_last_minute   = $cancellation['variant'] === ShiftCancellationService::VARIANT_LAST_MINUTE;
		$subject_key      = $is_last_minute ? 'cancellation_last_minute_email_subject' : 'cancellation_early_email_subject';
		$body_key         = $is_last_minute ? 'cancellation_last_minute_email_body' : 'cancellation_early_email_body';
		$subject_template = (string) get_post_meta( $type_id, $subject_key, true );
		$body_template    = (string) get_post_meta( $type_id, $body_key, true );
		if ( $subject_template === '' ) {
			$subject_template = $is_last_minute ? self::DEFAULT_LAST_MINUTE_CANCELLATION_SUBJECT : self::DEFAULT_EARLY_CANCELLATION_SUBJECT;
		}
		if ( $body_template === '' ) {
			$body_template = $is_last_minute ? self::DEFAULT_LAST_MINUTE_CANCELLATION_BODY : self::DEFAULT_EARLY_CANCELLATION_BODY;
		}
		if ( $cancellation['reason'] !== '' ) {
			$body_template .= "\n\nReden: " . $cancellation['reason'];
		}

		foreach ( $assigned as $person_id ) {
			$sent_meta_key = '_shift_email_cancellation_sent_' . $person_id;
			if ( get_post_meta( $shift_id, $sent_meta_key, true ) ) {
				++$result['already_sent'];
				continue;
			}

			$email = $this->get_person_email( $person_id );
			if ( ! $email ) {
				++$result['no_email'];
				$result['no_email_person_ids'][] = $person_id;
				continue;
			}

			$vars    = $this->template_variables( $type_id, $person_id, $assigned, $start, $end );
			$subject = $this->substitute_variables( $subject_template, $vars );
			$body    = $this->substitute_variables( $body_template, $vars );
			$html    = EmailTemplate::render(
				[
					'eyebrow'      => 'Geannuleerde vrijwilligersdienst',
					'heading'      => $subject,
					'preheader'    => $subject,
					'body_html'    => EmailTemplate::format_plain_text( $body ),
					'cta_url'      => $is_last_minute ? '' : home_url( '/vrijwillig' ),
					'cta_label'    => $is_last_minute ? '' : 'Kies een nieuwe dienst',
					'accent_color' => '#b91c1c',
				]
			);

			update_post_meta( $shift_id, $sent_meta_key, current_time( 'mysql' ) );
			if ( ! wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
				delete_post_meta( $shift_id, $sent_meta_key );
				++$result['failed'];
				$result['failed_person_ids'][] = $person_id;
				continue;
			}

			++$result['sent'];
			do_action( 'rondo_shift_email_sent', $shift_id, $person_id, 'cancellation' );
		}

		return $result;
	}

	private function process_shift( int $shift_id, \DateTimeImmutable $now ): int {
		$status = (string) get_post_meta( $shift_id, 'status', true );
		if ( $status === 'geannuleerd' ) {
			return 0;
		}

		$start = $this->parse_datetime( (string) get_post_meta( $shift_id, 'start_datetime', true ) );
		$end   = $this->parse_datetime( (string) get_post_meta( $shift_id, 'end_datetime', true ) );
		if ( ! $start || ! $end ) {
			return 0;
		}

		$type_id = (int) get_post_meta( $shift_id, 'dienst_type_id', true );
		if ( $type_id <= 0 || get_post_type( $type_id ) !== 'dienst_type' ) {
			return 0;
		}

		$assigned = array_values( array_unique( array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) ) ) );
		if ( empty( $assigned ) ) {
			return 0;
		}

		$sent = 0;
		foreach ( self::REMINDER_DAYS as $days ) {
			$send_at = $start->modify( '-' . $days . ' days' );
			if ( ! $this->is_due( $now, $send_at ) ) {
				continue;
			}

			foreach ( $assigned as $person_id ) {
				$sent += (int) $this->send_to_person( $shift_id, $type_id, $person_id, $assigned, 'reminder_' . $days, $start, $end );
			}
		}

		$survey_url = esc_url_raw( (string) get_post_meta( $type_id, 'survey_url', true ) );
		if ( $survey_url !== '' && $this->is_due( $now, $end->modify( '+1 day' ) ) ) {
			foreach ( $assigned as $person_id ) {
				if ( get_post_meta( $shift_id, '_no_show_' . $person_id, true ) ) {
					continue;
				}
				$sent += (int) $this->send_to_person( $shift_id, $type_id, $person_id, $assigned, 'survey', $start, $end, $survey_url );
			}
		}

		return $sent;
	}

	private function send_to_person(
		int $shift_id,
		int $type_id,
		int $person_id,
		array $assigned,
		string $delivery,
		\DateTimeImmutable $start,
		\DateTimeImmutable $end,
		string $survey_url = ''
	): bool {
		$sent_meta_key = '_shift_email_' . $delivery . '_sent_' . $person_id;
		if ( get_post_meta( $shift_id, $sent_meta_key, true ) ) {
			return false;
		}

		$email = $this->get_person_email( $person_id );
		if ( ! $email ) {
			return false;
		}

		$is_survey = $delivery === 'survey';
		$subject   = (string) get_post_meta( $type_id, $is_survey ? 'survey_email_subject' : 'reminder_email_subject', true );
		$body      = (string) get_post_meta( $type_id, $is_survey ? 'survey_email_body' : 'reminder_email_body', true );
		$subject   = $subject !== '' ? $subject : ( $is_survey ? self::DEFAULT_SURVEY_SUBJECT : self::DEFAULT_REMINDER_SUBJECT );
		$body      = $body !== '' ? $body : ( $is_survey ? self::DEFAULT_SURVEY_BODY : self::DEFAULT_REMINDER_BODY );

		$vars    = $this->template_variables( $type_id, $person_id, $assigned, $start, $end );
		$subject = $this->substitute_variables( $subject, $vars );
		$body    = $this->substitute_variables( $body, $vars );

		$html = EmailTemplate::render(
			[
				'eyebrow'      => $is_survey ? 'Enquête vrijwilligersdienst' : 'Herinnering vrijwilligersdienst',
				'heading'      => $subject,
				'preheader'    => $subject,
				'body_html'    => EmailTemplate::format_plain_text( $body ),
				'cta_url'      => $survey_url,
				'cta_label'    => $is_survey ? 'Vul de enquête in' : '',
				'accent_color' => '#0f766e',
			]
		);

		update_post_meta( $shift_id, $sent_meta_key, current_time( 'mysql' ) );
		$result = wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
		if ( ! $result ) {
			delete_post_meta( $shift_id, $sent_meta_key );
			return false;
		}

		do_action( 'rondo_shift_email_sent', $shift_id, $person_id, $delivery );
		return true;
	}

	private function template_variables(
		int $type_id,
		int $person_id,
		array $assigned,
		\DateTimeImmutable $start,
		\DateTimeImmutable $end
	): array {
		$name = (string) get_field( 'first_name', $person_id );
		if ( $name === '' ) {
			$name = get_the_title( $person_id );
		}

		$fellow_names = [];
		foreach ( $assigned as $assigned_person_id ) {
			if ( (int) $assigned_person_id === $person_id || get_post_type( $assigned_person_id ) !== 'person' ) {
				continue;
			}
			$fellow_names[] = get_the_title( $assigned_person_id );
		}

		return [
			'naam'              => $name,
			'dienst'            => get_the_title( $type_id ),
			'datum'             => wp_date( 'l j F Y', $start->getTimestamp(), wp_timezone() ),
			'tijd'              => wp_date( 'H:i', $start->getTimestamp(), wp_timezone() ),
			'eindtijd'          => wp_date( 'H:i', $end->getTimestamp(), wp_timezone() ),
			'medevrijwilligers' => empty( $fellow_names ) ? 'nog niemand anders' : implode( ', ', $fellow_names ),
		];
	}

	private function substitute_variables( string $template, array $vars ): string {
		foreach ( $vars as $key => $value ) {
			$template = str_replace( '{' . $key . '}', (string) $value, $template );
		}
		return $template;
	}

	private function get_person_email( int $person_id ): ?string {
		foreach ( [ 'email_1', 'email_2' ] as $field_name ) {
			$email = (string) get_field( $field_name, $person_id );
			if ( is_email( $email ) ) {
				return $email;
			}
		}
		return null;
	}

	private function is_due( \DateTimeImmutable $now, \DateTimeImmutable $send_at ): bool {
		$elapsed = $now->getTimestamp() - $send_at->getTimestamp();
		return $elapsed >= 0 && $elapsed < self::DELIVERY_WINDOW_SECONDS;
	}

	private function parse_datetime( string $value ): ?\DateTimeImmutable {
		if ( $value === '' ) {
			return null;
		}
		try {
			return new \DateTimeImmutable( $value, wp_timezone() );
		} catch ( \Exception $exception ) {
			return null;
		}
	}
}
