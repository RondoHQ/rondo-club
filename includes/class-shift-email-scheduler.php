<?php
/**
 * Scheduled reminder and survey emails for volunteer shifts.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Notifications\EmailTemplate;
use Rondo\Users\GuardianAccountService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShiftEmailScheduler {

	const CRON_HOOK                         = 'rondo_shift_email_sweeper';
	const SIGNUP_CONFIRMATION_CRON_HOOK     = 'rondo_send_shift_signup_confirmation';
	const SIGNUP_CONFIRMATION_DELAY_SECONDS = 10 * MINUTE_IN_SECONDS;

	private const SIGNUP_CONFIRMATION_RETRY_SECONDS = 15 * MINUTE_IN_SECONDS;
	private const SIGNUP_CONFIRMATION_QUEUE_PREFIX  = '_shift_confirmation_queued_at_';
	private const CALENDAR_TIMEZONE                 = 'Europe/Amsterdam';
	private const DUTCH_WEEKDAYS                    = [
		1 => 'maandag',
		2 => 'dinsdag',
		3 => 'woensdag',
		4 => 'donderdag',
		5 => 'vrijdag',
		6 => 'zaterdag',
		7 => 'zondag',
	];
	private const DUTCH_MONTHS                      = [
		1  => 'januari',
		2  => 'februari',
		3  => 'maart',
		4  => 'april',
		5  => 'mei',
		6  => 'juni',
		7  => 'juli',
		8  => 'augustus',
		9  => 'september',
		10 => 'oktober',
		11 => 'november',
		12 => 'december',
	];

	/** A cron run may arrive late, but must never send an old reminder days later. */
	const DELIVERY_WINDOW_SECONDS = DAY_IN_SECONDS;

	private const REMINDER_DAYS = [ 14, 7, 2 ];

	private const DEFAULT_REMINDER_SUBJECT = 'Herinnering: {dienst} op {datum}';

	private const DEFAULT_REMINDER_BODY = "Hoi {naam},\n\nDit is een herinnering voor je inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd}.\n\nJe voert deze inschrijftaak uit samen met {medevrijwilligers}.";

	private const DEFAULT_SURVEY_SUBJECT = 'Hoe ging je inschrijftaak {dienst}?';

	private const DEFAULT_SURVEY_BODY = "Hoi {naam},\n\nBedankt voor je inzet bij {dienst}. We horen graag hoe de inschrijftaak is verlopen. Wil je onze korte enquête invullen?";

	private const DEFAULT_EARLY_CANCELLATION_SUBJECT = 'Je inschrijftaak {dienst} op {datum} gaat niet door';

	private const DEFAULT_EARLY_CANCELLATION_BODY = "Hoi {naam},\n\nJe inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd} gaat helaas niet door.\n\nDeze annulering is minimaal 48 uur voor aanvang doorgegeven. De inschrijftaak telt daarom niet mee voor je vrijwilligersplicht. Kies een nieuwe inschrijftaak via Rondo.";

	private const DEFAULT_LAST_MINUTE_CANCELLATION_SUBJECT = 'Je inschrijftaak {dienst} op {datum} gaat niet door';

	private const DEFAULT_LAST_MINUTE_CANCELLATION_BODY = "Hoi {naam},\n\nJe inschrijftaak {dienst} op {datum} van {tijd} tot {eindtijd} gaat helaas niet door.\n\nDeze annulering is minder dan 48 uur voor aanvang doorgegeven. De inschrijftaak telt daarom wel mee voor je vrijwilligersplicht. Je hoeft hiervoor geen nieuwe inschrijftaak te kiezen.";

	public function __construct() {
		add_action( 'init', [ $this, 'register_cron' ] );
		add_action( self::CRON_HOOK, [ $this, 'run_sweep' ] );
		add_action( self::SIGNUP_CONFIRMATION_CRON_HOOK, [ $this, 'send_signup_confirmation' ] );
	}

	/**
	 * Queue a shift for the member's next combined signup confirmation.
	 */
	public static function queue_signup_confirmation( int $person_id, int $shift_id ): void {
		if ( $person_id <= 0 || get_post_type( $shift_id ) !== 'dienst_shift' ) {
			return;
		}

		update_post_meta( $shift_id, self::signup_confirmation_queue_key( $person_id ), time() );
		self::schedule_signup_confirmation( $person_id, self::SIGNUP_CONFIRMATION_DELAY_SECONDS );
	}

	/**
	 * Remove a cancelled signup from a pending confirmation.
	 */
	public static function discard_signup_confirmation( int $person_id, int $shift_id ): void {
		delete_post_meta( $shift_id, self::signup_confirmation_queue_key( $person_id ) );
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
	 * Send one email for every shift the member planned during the delay window.
	 *
	 * @return int Number of confirmed shifts.
	 */
	public function send_signup_confirmation( int $person_id ): int {
		$shifts = $this->pending_signup_confirmations( $person_id );
		if ( empty( $shifts ) ) {
			return 0;
		}

		$email = $this->get_person_email( $person_id );
		if ( ! $email ) {
			$this->clear_signup_confirmation_queue( $person_id, $shifts );
			return 0;
		}

		$name = GuardianAccountService::greeting_name_for_person( $person_id );

		$count   = count( $shifts );
		$subject = $count === 1
			? sprintf( 'Bevestiging: %s op %s', $shifts[0]['title'], $this->format_dutch_date( $shifts[0]['start'], false ) )
			: sprintf( 'Bevestiging van je %d inschrijftaken', $count );

		$list_items = [];
		foreach ( $shifts as $shift ) {
			$list_items[] = sprintf(
				'<li style="margin:0 0 12px;"><strong>%1$s</strong><br>%2$s, %3$s–%4$s</li>',
				esc_html( $shift['title'] ),
				esc_html( $this->format_dutch_date( $shift['start'] ) ),
				esc_html( wp_date( 'H:i', $shift['start']->getTimestamp(), wp_timezone() ) ),
				esc_html( wp_date( 'H:i', $shift['end']->getTimestamp(), wp_timezone() ) )
			);
		}

		$attachment = $this->create_signup_calendar_attachment( $person_id, $shifts );
		$body_html  = sprintf(
			'<p style="margin:0 0 16px;">Hoi %s,</p><p style="margin:0 0 16px;">Je aanmelding is bevestigd voor:</p><ul style="margin:0 0 16px;padding-left:22px;">%s</ul><p style="margin:0;">%s</p>',
			esc_html( $name ),
			implode( '', $list_items ),
			$attachment
				? 'In de bijlage staat een kalenderbestand waarmee je deze inschrijftaken direct aan je agenda kunt toevoegen.'
				: 'Je vindt je planning ook terug op de vrijwilligerspagina.'
		);
		$html       = EmailTemplate::render(
			[
				'eyebrow'      => 'Aanmelding bevestigd',
				'heading'      => $subject,
				'preheader'    => $subject,
				'body_html'    => $body_html,
				'cta_url'      => home_url( '/vrijwillig' ),
				'cta_label'    => 'Bekijk je inschrijftaken',
				'accent_color' => '#0f766e',
			]
		);

		$attachments = $attachment ? [ $attachment ] : [];
		$sent        = wp_mail(
			$email,
			$subject,
			$html,
			[
				'Content-Type: text/html; charset=UTF-8',
				'X-Rondo-Email-Tag: shift-signup-confirmation',
			],
			$attachments
		);

		if ( $attachment ) {
			wp_delete_file( $attachment );
		}

		if ( ! $sent ) {
			self::schedule_signup_confirmation( $person_id, self::SIGNUP_CONFIRMATION_RETRY_SECONDS );
			return 0;
		}

		foreach ( $shifts as $shift ) {
			delete_post_meta( $shift['id'], self::signup_confirmation_queue_key( $person_id ) );
			update_post_meta( $shift['id'], '_shift_email_confirmation_sent_' . $person_id, current_time( 'mysql' ) );
			do_action( 'rondo_shift_email_sent', $shift['id'], $person_id, 'confirmation' );
		}

		return $count;
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
		$assigned     = $this->valid_assigned_person_ids( $shift_id );
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
					'eyebrow'      => 'Geannuleerde inschrijftaak',
					'heading'      => $subject,
					'preheader'    => $subject,
					'body_html'    => EmailTemplate::format_plain_text( $body ),
					'cta_url'      => $is_last_minute ? '' : home_url( '/vrijwillig' ),
					'cta_label'    => $is_last_minute ? '' : 'Kies een nieuwe inschrijftaak',
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

		$assigned = $this->valid_assigned_person_ids( $shift_id );
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
				'eyebrow'      => $is_survey ? 'Enquête inschrijftaak' : 'Herinnering inschrijftaak',
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
		$name = GuardianAccountService::greeting_name_for_person( $person_id );

		$fellow_names = [];
		foreach ( $assigned as $assigned_person_id ) {
			if ( (int) $assigned_person_id === $person_id || get_post_type( $assigned_person_id ) !== 'person' ) {
				continue;
			}
			$fellow_names[] = GuardianAccountService::display_name_for_person( (int) $assigned_person_id );
		}

		return [
			'naam'              => $name,
			'dienst'            => get_the_title( $type_id ),
			'datum'             => $this->format_dutch_date( $start ),
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
			$email = (string) \Rondo\Fields\Fields::try_get_for_post( $person_id, $field_name );
			if ( is_email( $email ) ) {
				return $email;
			}
		}
		return null;
	}

	/** @return int[] Existing person post IDs assigned to a shift. */
	private function valid_assigned_person_ids( int $shift_id ): array {
		$person_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) )
				)
			)
		);

		return array_values(
			array_filter(
				$person_ids,
				static fn ( int $person_id ): bool => get_post_type( $person_id ) === 'person'
			)
		);
	}

	private static function signup_confirmation_queue_key( int $person_id ): string {
		return self::SIGNUP_CONFIRMATION_QUEUE_PREFIX . $person_id;
	}

	private static function schedule_signup_confirmation( int $person_id, int $delay ): void {
		$args = [ $person_id ];
		if ( wp_next_scheduled( self::SIGNUP_CONFIRMATION_CRON_HOOK, $args ) === false ) {
			wp_schedule_single_event( time() + $delay, self::SIGNUP_CONFIRMATION_CRON_HOOK, $args );
		}
	}

	/**
	 * @return array<int, array{id: int, title: string, start: \DateTimeImmutable, end: \DateTimeImmutable}>
	 */
	private function pending_signup_confirmations( int $person_id ): array {
		$queue_key = self::signup_confirmation_queue_key( $person_id );
		$query     = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				'post_status'      => 'publish',
				'posts_per_page'   => 100,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'fields'           => 'ids',
				'meta_query'       => [
					[
						'key'     => $queue_key,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		$shifts = [];
		foreach ( $query->posts as $shift_id ) {
			$shift_id = (int) $shift_id;
			$status   = (string) get_post_meta( $shift_id, 'status', true );
			$assigned = array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) );
			$start    = $this->parse_datetime( (string) get_post_meta( $shift_id, 'start_datetime', true ) );
			$end      = $this->parse_datetime( (string) get_post_meta( $shift_id, 'end_datetime', true ) );
			$type_id  = (int) get_post_meta( $shift_id, 'dienst_type_id', true );

			if ( ! in_array( $status, [ 'open', 'vol' ], true ) || ! in_array( $person_id, $assigned, true ) || ! $start || ! $end || get_post_type( $type_id ) !== 'dienst_type' ) {
				delete_post_meta( $shift_id, $queue_key );
				continue;
			}

			$shifts[] = [
				'id'    => $shift_id,
				'title' => get_the_title( $type_id ),
				'start' => $start,
				'end'   => $end,
			];
		}

		usort(
			$shifts,
			static fn( array $left, array $right ): int => $left['start']->getTimestamp() <=> $right['start']->getTimestamp()
		);

		return $shifts;
	}

	private function clear_signup_confirmation_queue( int $person_id, array $shifts ): void {
		foreach ( $shifts as $shift ) {
			delete_post_meta( $shift['id'], self::signup_confirmation_queue_key( $person_id ) );
		}
	}

	/**
	 * @param array<int, array{id: int, title: string, start: \DateTimeImmutable, end: \DateTimeImmutable}> $shifts Shift data.
	 */
	private function create_signup_calendar_attachment( int $person_id, array $shifts ): ?string {
		$calendar = $this->build_signup_calendar( $person_id, $shifts );
		$dir      = trailingslashit( get_temp_dir() ) . 'rondo-shift-confirmations';
		if ( ! wp_mkdir_p( $dir ) ) {
			return null;
		}

		$path = trailingslashit( $dir ) . 'inschrijftaken-' . $person_id . '-' . wp_generate_uuid4() . '.ics';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- temporary calendar attachment.
		if ( file_put_contents( $path, $calendar ) === false ) {
			return null;
		}

		return $path;
	}

	/**
	 * @param array<int, array{id: int, title: string, start: \DateTimeImmutable, end: \DateTimeImmutable}> $shifts Shift data.
	 */
	private function build_signup_calendar( int $person_id, array $shifts ): string {
		$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$host  = $host !== '' ? $host : 'rondo.club';
		$lines = [
			'BEGIN:VCALENDAR',
			'VERSION:2.0',
			'PRODID:-//Rondo Club//Vrijwilligerstaken//NL',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:' . $this->escape_ical_text( get_bloginfo( 'name' ) . ' inschrijftaken' ),
			'X-WR-TIMEZONE:' . self::CALENDAR_TIMEZONE,
			'BEGIN:VTIMEZONE',
			'TZID:' . self::CALENDAR_TIMEZONE,
			'X-LIC-LOCATION:' . self::CALENDAR_TIMEZONE,
			'BEGIN:DAYLIGHT',
			'TZOFFSETFROM:+0100',
			'TZOFFSETTO:+0200',
			'TZNAME:CEST',
			'DTSTART:19700329T020000',
			'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
			'END:DAYLIGHT',
			'BEGIN:STANDARD',
			'TZOFFSETFROM:+0200',
			'TZOFFSETTO:+0100',
			'TZNAME:CET',
			'DTSTART:19701025T030000',
			'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
			'END:STANDARD',
			'END:VTIMEZONE',
		];
		$url   = home_url( '/vrijwillig' );

		foreach ( $shifts as $shift ) {
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:rondo-shift-' . $shift['id'] . '-' . $person_id . '@' . $host;
			$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
			$lines[] = 'DTSTART;TZID=' . self::CALENDAR_TIMEZONE . ':' . $shift['start']->format( 'Ymd\THis' );
			$lines[] = 'DTEND;TZID=' . self::CALENDAR_TIMEZONE . ':' . $shift['end']->format( 'Ymd\THis' );
			$lines[] = 'SUMMARY:' . $this->escape_ical_text( $shift['title'] );
			$lines[] = 'DESCRIPTION:' . $this->escape_ical_text( 'Bekijk je inschrijftaken in Rondo: ' . $url );
			$lines[] = 'URL:' . $url;
			$lines[] = 'STATUS:CONFIRMED';
			$lines[] = 'TRANSP:OPAQUE';
			$lines[] = 'END:VEVENT';
		}

		$lines[] = 'END:VCALENDAR';
		return implode( "\r\n", array_map( [ $this, 'fold_ical_line' ], $lines ) ) . "\r\n";
	}

	private function escape_ical_text( string $value ): string {
		return str_replace(
			[ '\\', "\r\n", "\r", "\n", ',', ';' ],
			[ '\\\\', '\\n', '\\n', '\\n', '\\,', '\\;' ],
			$value
		);
	}

	private function format_dutch_date( \DateTimeImmutable $date, bool $include_weekday = true ): string {
		$parts = [];
		if ( $include_weekday ) {
			$parts[] = self::DUTCH_WEEKDAYS[ (int) $date->format( 'N' ) ];
		}
		$parts[] = $date->format( 'j' );
		$parts[] = self::DUTCH_MONTHS[ (int) $date->format( 'n' ) ];
		$parts[] = $date->format( 'Y' );
		return implode( ' ', $parts );
	}

	private function fold_ical_line( string $line ): string {
		if ( strlen( $line ) <= 75 ) {
			return $line;
		}

		$characters = preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY );
		if ( $characters === false ) {
			$characters = str_split( $line );
		}

		$segments = [];
		$current  = '';
		foreach ( $characters as $character ) {
			$limit = empty( $segments ) ? 75 : 74;
			if ( $current !== '' && strlen( $current . $character ) > $limit ) {
				$segments[] = $current;
				$current    = $character;
				continue;
			}
			$current .= $character;
		}
		if ( $current !== '' ) {
			$segments[] = $current;
		}

		return implode( "\r\n ", $segments );
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
