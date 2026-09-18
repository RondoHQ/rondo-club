<?php
/** Sunday recruitment digest for the weekend thirteen/fourteen days later. */

namespace Rondo\Volunteer;

use Rondo\Config\FinanceConfig;
use Rondo\Core\PostTitle;
use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\Notifications\EmailTemplate;
use Rondo\People\CommunicationPolicy;

final class WeekendVolunteerMail {
	const HOOK       = 'rondo_weekend_volunteer_mail';
	const STATE      = 'rondo_weekend_volunteer_mail_state';
	const BATCH_SIZE = 25;

	public function __construct() {
		// WordPress supplies an empty string for actions without arguments.
		// Keep the optional test clock separate from hook arguments.
		add_action( 'init', [ $this, 'register_cron' ], 10, 0 );
		add_action( self::HOOK, [ $this, 'run' ], 10, 0 );
	}

	/** Calendar arithmetic, rather than 604800 seconds, preserves 19:00 across DST. */
	public static function next_sunday( \DateTimeImmutable $now ): \DateTimeImmutable {
		$now  = $now->setTimezone( new \DateTimeZone( 'Europe/Amsterdam' ) );
		$next = $now->modify( 'sunday this week' )->setTime( 19, 0 );
		return $next > $now ? $next : $next->modify( '+1 week' );
	}

	/** Inclusive start, exclusive end in the club's local timezone. */
	public static function weekend( \DateTimeImmutable $sunday ): array {
		$sunday = $sunday->setTimezone( new \DateTimeZone( 'Europe/Amsterdam' ) )->setTime( 0, 0 );
		return [ $sunday->modify( '+13 days' ), $sunday->modify( '+15 days' ) ];
	}

	private static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', new \DateTimeZone( 'Europe/Amsterdam' ) );
	}

	/** Recover missing cron events during the Sunday evening delivery window. */
	public function register_cron( ?\DateTimeImmutable $now = null ): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$now     = ( $now ?? self::now() )->setTimezone( new \DateTimeZone( 'Europe/Amsterdam' ) );
		$state   = get_option( self::STATE, [] );
		$pending = ( $state['date'] ?? '' ) !== $now->format( 'Y-m-d' ) || ! empty( $state['pending'] );
		$next    = self::in_window( $now ) && $pending ? $now->modify( '+1 minute' ) : self::next_sunday( $now );
		wp_schedule_single_event( $next->getTimestamp(), self::HOOK );
	}

	public static function unregister_cron(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	private static function in_window( \DateTimeImmutable $now ): bool {
		return $now->format( 'N' ) === '7' && $now->format( 'H:i' ) >= '19:00';
	}

	/** Primary addresses of responsible adults/players with an unplanned duty. */
	public function recipients( string $season ): array {
		$result = [];
		foreach ( ( new PeopleShiftProgress() )->for_season( $season ) as $person_id => $progress ) {
			if ( ! in_array( $progress['status'], [ 'not_started', 'insufficient' ], true )
				|| ! ( new VolunteerEligibilityService() )->may_volunteer( (int) $person_id ) ) {
				continue;
			}
			$email = CommunicationPolicy::primary_email( (int) $person_id );
			if ( $email ) {
				$result[ $email ][] = (int) $person_id;
			}
		}
		ksort( $result );
		return $result;
	}

	/** Read current capacity and requirements; do not depend on the queue snapshot. */
	public function available_shifts( array $person_ids, \DateTimeImmutable $sunday ): array {
		[ $from, $until ] = self::weekend( $sunday );
		$posts            = get_posts(
			[
				'post_type'        => 'dienst_shift',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'suppress_filters' => true,
				'meta_key'         => 'start_datetime',
				'orderby'          => 'meta_value',
				'order'            => 'ASC',
				'meta_query'       => [
					[
						'key'     => 'start_datetime',
						'value'   => [ $from->format( 'Y-m-d H:i:s' ), $until->modify( '-1 second' )->format( 'Y-m-d H:i:s' ) ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
				],
			]
		);
		$shifts           = [];
		foreach ( $posts as $post ) {
			$status = (string) Fields::get_for_post( $post->ID, 'status' );
			if ( ! in_array( $status, [ '', 'open' ], true ) || ! ShiftSignupWindow::is_open( $post->ID, $sunday ) ) {
				continue;
			}
			$type = (int) Fields::get_for_post( $post->ID, 'dienst_type_id' );
			if ( get_post_type( $type ) !== 'dienst_type' || get_post_status( $type ) !== 'publish' ) {
				continue;
			}
			$assigned  = ShiftAssignments::person_ids( $post->ID );
			$capacity  = (int) Fields::get_for_post( $post->ID, 'capacity' );
			$remaining = $capacity > 0 ? max( 0, $capacity - count( $assigned ) ) : -1;
			if ( $remaining === 0 ) {
				continue;
			}
			$eligible = array_filter( $person_ids, static fn( $id ) => ! in_array( $id, $assigned, true ) && ShiftSignupEligibility::block_reason( $post->ID, $id ) === null );
			if ( ! $eligible ) {
				continue;
			}
			try {
				$start_value = (string) Fields::get_for_post( $post->ID, 'start_datetime' );
				$end_value   = (string) Fields::get_for_post( $post->ID, 'end_datetime' );
				if ( $start_value === '' || $end_value === '' ) {
					continue;
				}
				$start = ( new \DateTimeImmutable( $start_value, $from->getTimezone() ) )->setTimezone( $from->getTimezone() );
				$end   = ( new \DateTimeImmutable( $end_value, $from->getTimezone() ) )->setTimezone( $from->getTimezone() );
			} catch ( \Exception $exception ) {
				continue;
			}
			if ( $end <= $start || $start < $from || $start >= $until ) {
				continue;
			}
			$iva = (bool) Fields::get_for_post( $type, 'iva_required' ) && ! (bool) Fields::get_for_post( $post->ID, 'iva_waived' );
			$key = $type . '|' . $start->format( 'c' ) . '|' . $end->format( 'c' ) . '|' . (int) $iva;
			if ( isset( $shifts[ $key ] ) ) {
				$old                         = $shifts[ $key ]['remaining'];
				$shifts[ $key ]['remaining'] = $old < 0 || $remaining < 0 ? -1 : $old + $remaining;
			} else {
				$shifts[ $key ] = [
					'name'      => PostTitle::plain( $type ),
					'start'     => $start,
					'end'       => $end,
					'remaining' => $remaining,
					'iva'       => $iva,
				];
			}
		}
		return array_values( $shifts );
	}

	/** Dutch dates remain Dutch regardless of the administrator's language. */
	private static function date_label( \DateTimeImmutable $date ): string {
		$months = [
			1 => 'januari',
			'februari',
			'maart',
			'april',
			'mei',
			'juni',
			'juli',
			'augustus',
			'september',
			'oktober',
			'november',
			'december',
		];
		return $date->format( 'j' ) . ' ' . $months[ (int) $date->format( 'n' ) ];
	}

	/** Render the same branded template as the reviewed preview. No sending here. */
	public function message( array $person_ids, array $shifts, \DateTimeImmutable $sunday ): array {
		[ $saturday, $monday ] = self::weekend( $sunday );
		$dates                 = self::date_label( $saturday ) . ' en ' . self::date_label( $monday->modify( '-1 day' ) );
		$club                  = ( new FinanceConfig() )->get_display_name();
		$names                 = array_values( array_unique( array_filter( array_map( static fn( $id ) => trim( (string) Fields::get_for_post( $id, 'first_name' ) ), $person_ids ) ) ) );
		$greeting              = $names ? 'Hoi ' . implode( ' en ', $names ) . ',' : 'Hoi,';
		$body                  = '<p style="margin:0 0 16px;">' . esc_html( $greeting ) . '</p><p style="margin:0 0 16px;">Je hebt dit seizoen nog niet al je diensten ingepland. Met jouw hulp houden we ' . esc_html( $club ) . ' draaiende.</p><p style="margin:0 0 8px;">Voor <strong>' . esc_html( $dates ) . '</strong> zoeken we nog vrijwilligers. Zit er een moment tussen dat jou uitkomt?</p>';
		$day                   = '';
		foreach ( $shifts as $shift ) {
			$date = $shift['start']->format( 'Y-m-d' );
			if ( $day !== $date ) {
				$body .= $day !== '' ? '</table>' : '';
				$label = ( $shift['start']->format( 'N' ) === '6' ? 'Zaterdag ' : 'Zondag ' ) . self::date_label( $shift['start'] );
				$body .= '<h2 style="margin:26px 0 10px;font-size:18px;line-height:1.4;">' . esc_html( $label ) . '</h2><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="border-collapse:collapse;">';
				$day   = $date;
			}
			$spots = $shift['remaining'] < 0 ? 'Plek beschikbaar' : $shift['remaining'] . ( $shift['remaining'] === 1 ? ' plek vrij' : ' plekken vrij' );
			$body .= '<tr><td style="padding:12px 0;border-top:1px solid #dbe4e1;vertical-align:top;"><strong style="font-size:16px;">' . esc_html( $shift['name'] ) . '</strong><br><span style="color:#475569;font-size:14px;">' . esc_html( $shift['start']->format( 'H:i' ) . '–' . $shift['end']->format( 'H:i' ) . ( $shift['iva'] ? ' · IVA nodig' : '' ) ) . '</span></td><td width="84" style="padding:12px 0 12px 12px;border-top:1px solid #dbe4e1;text-align:right;vertical-align:top;font-size:14px;">' . esc_html( $spots ) . '</td></tr>';
		}
		$body .= $day !== '' ? '</table>' : '';
		$body .= '<p style="margin:24px 0 0;">Kies en bevestig je dienst in Rondo. Daar zie je welke plekken op dat moment nog beschikbaar zijn.</p>';
		return [
			'subject' => $club . ' zoekt hulp: openstaande diensten van ' . $dates,
			'html'    => EmailTemplate::render(
				[
					'brand_name'  => $club,
					'heading'     => 'Welke dienst past in jouw agenda?',
					'preheader'   => 'Dit zijn de openstaande diensten van ' . $dates . '.',
					'body_html'   => $body,
					'cta_url'     => home_url( '/vrijwillig' ),
					'cta_label'   => 'Kies je dienst',
					'footer_html' => '<p style="margin:0;color:#475569;font-size:13px;line-height:1.6;">Je ontvangt dit overzicht omdat je nog diensten moet inplannen. Zodra je alle diensten hebt ingepland, stopt deze wekelijkse mail.</p>',
				]
			),
		];
	}

	/**
	 * Maximum 25 recipients per minute, with one durable attempt per address/week.
	 * A failed or uncertain send is recorded, never automatically retried: wp_mail
	 * cannot prove that a provider did not accept a request before a timeout.
	 */
	public function run( ?\DateTimeImmutable $now = null ): int {
		$now = ( $now ?? self::now() )->setTimezone( new \DateTimeZone( 'Europe/Amsterdam' ) );
		if ( ! self::in_window( $now ) ) {
			$this->register_cron( $now );
			return 0;
		}
		$date = $now->format( 'Y-m-d' );
		$lock = 'rondo_weekend_mail_lock_' . $date;
		// An invariant value makes concurrent add_option calls a no-op on duplicate.
		if ( ! add_option( $lock, 'locked', '', false ) ) {
			return 0;
		}
		$sent = 0;
		try {
			$state = get_option( self::STATE, [] );
			if ( ( $state['date'] ?? '' ) === $date && ( empty( $state['pending'] ) || $now->getTimestamp() < ( $state['next_batch'] ?? 0 ) ) ) {
				return 0;
			}
			VolunteerEligibilityService::invalidate_cache();
			VolunteerObligationCalculator::invalidate_cache();
			$season     = SeasonKey::current( $now->format( 'Y-m-d' ) );
			$recipients = $this->recipients( $season );
			if ( ( $state['date'] ?? '' ) !== $date ) {
				// Last Sunday's callbacks can no longer enter the delivery window.
				if ( ! empty( $state['date'] ) ) {
					delete_option( 'rondo_weekend_mail_lock_' . $state['date'] );
				}
				$state = [
					'date'       => $date,
					'pending'    => array_keys( $recipients ),
					'results'    => [],
					'next_batch' => 0,
				];
			}
			$state['next_batch'] = $now->getTimestamp() + MINUTE_IN_SECONDS;
			$this->save_state( $state );
			$generation = $this->generation();
			foreach ( array_slice( $state['pending'], 0, self::BATCH_SIZE ) as $email ) {
				if ( $generation !== $this->generation() ) {
					$recipients = $this->recipients( $season );
					$generation = $this->generation();
				}
				$people = $recipients[ $email ] ?? [];
				// Contact data, death status and publication may change during a batch.
				$people = array_values( array_filter( $people, static fn( $id ) => get_post_status( $id ) === 'publish' && CommunicationPolicy::primary_email( $id ) === $email ) );
				$shifts = $people ? $this->available_shifts( $people, $now ) : [];
				array_shift( $state['pending'] );
				$key                      = hash( 'sha256', $email );
				$state['results'][ $key ] = [
					'email'  => $email,
					'status' => $shifts ? 'reserved' : 'skipped',
				];
				// Commit removal and reservation BEFORE calling the provider.
				$this->save_state( $state );
				if ( ! $shifts ) {
					continue;
				}
				try {
					$message                            = $this->message( $people, $shifts, $now );
					$accepted                           = wp_mail( $email, $message['subject'], $message['html'], [ 'Content-Type: text/html; charset=UTF-8', 'X-Rondo-Email-Tag: volunteer-weekend' ] );
					$state['results'][ $key ]['status'] = $accepted ? 'accepted' : 'failed';
					$sent                              += (int) $accepted;
				} catch ( \Throwable $exception ) {
					$state['results'][ $key ]['status'] = 'uncertain';
				}
				$this->save_state( $state );
			}
		} finally {
			delete_option( $lock );
			$this->register_cron( $now );
		}
		return $sent;
	}

	private function generation(): string {
		return (string) get_option( VolunteerEligibilityService::CACHE_GENERATION_OPTION, '1' ) . '|' . (string) get_option( VolunteerObligationCalculator::CACHE_GENERATION_OPTION, '1' );
	}

	/** Do not send if durable reservation cannot be verified. */
	private function save_state( array $state ): void {
		update_option( self::STATE, $state, false );
		if ( get_option( self::STATE ) !== $state ) {
			throw new \RuntimeException( 'Weekend mail state could not be saved.' );
		}
	}
}
