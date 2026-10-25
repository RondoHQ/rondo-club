<?php
namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Fees\SeasonKey;
use Rondo\Volunteer\WeekendVolunteerMail;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Tests\Support\RondoTestCase;

class WeekendVolunteerMailTest extends RondoTestCase {
	private WeekendVolunteerMail $mailer;
	private \DateTimeImmutable $sunday;
	private array $mail  = [];
	private bool $accept = true;
	private int $type;

	protected function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		$this->sunday = new \DateTimeImmutable( '2026-09-20 19:00:00', new \DateTimeZone( 'Europe/Amsterdam' ) );
		$this->mailer = new WeekendVolunteerMail();
		$this->type   = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Keuken',
			]
			);
		add_filter(
			'pre_wp_mail',
			function ( $pre, $atts ) {
				$this->mail[] = $atts;
				return $this->accept;
			},
			1,
			2
			);
	}

	private function player( string $email = 'person@example.org' ): int {
		return $this->createPerson(
			[],
			[
				'first_name'     => 'Anne',
				'leeftijdsgroep' => 'Senioren',
				'email_1'        => $email,
			]
			);
	}

	private function shift( ?string $date = null, array $assigned = [], string $status = 'open', int $capacity = 2, ?int $type = null ): int {
		$date = $date ?? $this->sunday->modify( '+13 days' )->setTime( 11, 0 )->format( 'Y-m-d H:i:s' );
		$id   = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
			]
			);
		foreach ( [
			'start_datetime'   => $date,
			'end_datetime'     => ( new \DateTimeImmutable( $date ) )->modify( '+3 hours' )->format( 'Y-m-d H:i:s' ),
			'dienst_type_id'   => $type ?? $this->type,
			'status'           => $status,
			'capacity'         => $capacity,
			'assigned_persons' => $assigned,
		] as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	/** Planning uses the real clock, so keep these fixtures in a future autumn season. */
	private function use_future_round(): string {
		$year         = (int) current_datetime()->format( 'Y' ) + 1;
		$this->sunday = WeekendVolunteerMail::next_sunday( new \DateTimeImmutable( $year . '-09-01', wp_timezone() ) );
		return SeasonKey::current( $this->sunday->format( 'Y-m-d' ) );
	}

	private function plan_required_shifts( int $person_id ): void {
		foreach ( [ 20, 21 ] as $days ) {
			$start = $this->sunday->modify( '+' . $days . ' days' )->setTime( 11, 0 );
			$this->assertGreaterThan( time(), $start->getTimestamp() );
			$this->shift( $start->format( 'Y-m-d H:i:s' ), [ $person_id ] );
		}
	}

	public function test_wordpress_init_schedules_mail_without_a_date_argument(): void {
		WeekendVolunteerMail::unregister_cron();

		// Replay only this service's init registration; core init already ran.
		$original_init                = $GLOBALS['wp_filter']['init'];
		$GLOBALS['wp_filter']['init'] = new \WP_Hook();
		$mailer                       = new WeekendVolunteerMail();
		try {
			do_action( 'init' );
		} finally {
			$GLOBALS['wp_filter']['init'] = $original_init;
			remove_action( WeekendVolunteerMail::HOOK, [ $mailer, 'run' ] );
		}

		$this->assertIsInt( wp_next_scheduled( WeekendVolunteerMail::HOOK ) );
	}

	public function test_wordpress_cron_callback_accepts_no_arguments(): void {
		WeekendVolunteerMail::unregister_cron();

		do_action( 'rondo_weekend_volunteer_mail' );

		$this->assertIsInt( wp_next_scheduled( WeekendVolunteerMail::HOOK ) );
		$this->assertEmpty( $this->mail );
	}

	public function test_schedule_preserves_local_evening_through_dst_and_year_boundary(): void {
		foreach ( [
			[ '2026-10-18 19:00:00', '2026-10-25T19:00:00+01:00' ],
			[ '2027-03-21 19:00:00', '2027-03-28T19:00:00+02:00' ],
			[ '2026-12-27 19:00:00', '2027-01-03T19:00:00+01:00' ],
			[ '2026-09-20 18:59:00', '2026-09-20T19:00:00+02:00' ],
		] as [$input, $expected] ) {
			$this->assertSame( $expected, WeekendVolunteerMail::next_sunday( new \DateTimeImmutable( $input, new \DateTimeZone( 'Europe/Amsterdam' ) ) )->format( 'c' ) );
		}
		[$start, $end] = WeekendVolunteerMail::weekend( $this->sunday );
		$this->assertSame( '2026-09-21 00:00:00', $start->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-10-26 00:00:00', $end->format( 'Y-m-d H:i:s' ) );
		$this->assertStringContainsString( '21 september t/m 25 oktober', $this->mailer->message( [], [], $this->sunday )['subject'] );
	}

	public function test_all_days_of_five_full_weeks_across_dst_and_year_boundaries(): void {
		$person = $this->player();
		foreach ( [ '2026-10-18', '2026-12-20' ] as $date ) {
			$sunday   = new \DateTimeImmutable( $date . ' 19:00:00', wp_timezone() );
			$expected = [];
			$ids      = [];
			for ( $day = 1; $day <= 35; ++$day ) {
				$value      = $sunday->modify( '+' . $day . ' days' )->format( 'Y-m-d' );
				$expected[] = $value;
				$ids[]      = $this->shift( $value . ' 11:00:00' );
			}
			$ids[] = $this->shift( $sunday->modify( '+36 days' )->format( 'Y-m-d' ) . ' 11:00:00' );
			$rows  = $this->mailer->available_shifts( [ $person ], $sunday );
			$this->assertSame( $expected, array_map( static fn( $row ) => $row['start']->format( 'Y-m-d' ), $rows ) );
			$message = $this->mailer->message( [ $person ], $rows, $sunday );
			foreach ( [ 'Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag' ] as $day ) {
				$this->assertSame( 5, substr_count( $message['html'], '>' . $day . ' ' ) );
			}
			foreach ( $ids as $id ) {
				wp_delete_post( $id, true ); }
		}
	}

	public function test_current_capacity_grouping_and_certificate_pool_rules(): void {
		$person = $this->player();
		$this->shift();
		$this->shift();
		$this->shift( '2026-10-04 09:00:00' );
		foreach ( [ '2026-09-20 11:00:00', '2026-09-19 23:59:00', '2026-10-26 00:00:00', '2026-11-02 11:00:00' ] as $outside ) {
			$this->shift( $outside );
		}
		$this->shift( '2026-10-03 08:00:00', [ $person ], 'open', 1 );
		$this->shift( '2026-10-03 09:00:00', [], 'geannuleerd' );
		$this->shift( '2026-10-03 10:00:00', [], 'voltooid' );
		$bar = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Bar',
			]
			);
		update_post_meta( $bar, 'iva_required', true );
		$this->shift( '2026-10-03 14:00:00', [], 'open', 2, $bar );
		$rows = $this->mailer->available_shifts( [ $person ], $this->sunday );
		$this->assertCount( 2, $rows );
		$this->assertSame( [ 4, 2 ], array_column( $rows, 'remaining' ) );
		Fields::update_for_post( $person, 'datum_iva', '2026-09-01' );
		Fields::update_for_post( $person, 'iva_approved', true );
		$this->assertCount( 3, $this->mailer->available_shifts( [ $person ], $this->sunday ) );
		update_post_meta( $bar, 'vog_required', true );
		$this->assertCount( 2, $this->mailer->available_shifts( [ $person ], $this->sunday ) );
		Fields::update_for_post( $person, 'datum_vog', '2026-09-01' );
		$this->assertCount( 3, $this->mailer->available_shifts( [ $person ], $this->sunday ) );
		$pool = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
			]
			);
		update_post_meta( $bar, 'required_pool', $pool );
		$this->assertCount( 2, $this->mailer->available_shifts( [ $person ], $this->sunday ) );
		Fields::update_for_post(
			$person,
			'work_history',
			[
				[
					'team'       => $pool,
					'start_date' => '2026-09-01',
					'is_current' => true,
				],
			]
			);
		$this->assertCount( 3, $this->mailer->available_shifts( [ $person ], $this->sunday ) );
	}

	public function test_only_open_duties_receive_mail_and_shared_addresses_are_deduplicated(): void {
		$season = $this->use_future_round();
		$this->player( 'shared@example.org' );
		$this->player( 'SHARED@example.org' );
		$planned = $this->player( 'planned@example.org' );
		$this->plan_required_shifts( $planned );
		$exempt = $this->player( 'exempt@example.org' );
		Fields::update_for_post( $exempt, 'vrijgesteld_handmatig', true );
		Fields::update_for_post( $exempt, 'vrijstelling_seizoen', $season );
		$former = $this->player( 'former@example.org' );
		Fields::update_for_post( $former, 'former_member', true );
		$dead = $this->player( 'dead@example.org' );
		Fields::update_for_post( $dead, 'datum_overlijden', '2026-09-01' );
		$this->shift();
		$this->assertSame( 1, $this->mailer->run( $this->sunday ) );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( [ 'shared@example.org' ], $this->mail[0]['to'] );
		$this->assertStringContainsString( 'Hoi Anne,', $this->mail[0]['message'] );
		$this->assertStringNotContainsString( '[voornaam]', $this->mail[0]['message'] );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+2 minutes' ) ) );
	}

	public function test_batch_limit_rechecks_planning_email_and_capacity(): void {
		$this->use_future_round();
		$people = [];
		for ( $n = 0; $n < 28; ++$n ) {
			$people[] = $this->player( sprintf( 'person%02d@example.org', $n ) );
		}
		$open = $this->shift();
		$this->assertSame( 25, $this->mailer->run( $this->sunday ) );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+30 seconds' ) ) );
		$this->plan_required_shifts( $people[25] );
		Fields::update_for_post( $people[26], 'email_1', 'changed@example.org' );
		$this->assertSame( 1, $this->mailer->run( $this->sunday->modify( '+1 minute' ) ) );
		$this->assertSame( [ 'person27@example.org' ], $this->mail[25]['to'] );
		$this->assertCount( 26, $this->mail );
		$this->assertEmpty( get_option( WeekendVolunteerMail::STATE )['pending'] );
	}

	public function test_filled_shift_between_batches_suppresses_remaining_mail(): void {
		for ( $n = 0; $n < 26; ++$n ) {
			$this->player( sprintf( 'person%02d@example.org', $n ) );
		}
		$open = $this->shift();
		$this->assertSame( 25, $this->mailer->run( $this->sunday ) );
		update_post_meta( $open, 'assigned_persons', [ 9991, 9992 ] );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+1 minute' ) ) );
		$this->assertCount( 25, $this->mail );
	}

	public function test_family_duty_targets_parents_and_partner_signup_satisfies_both(): void {
		$season  = $this->use_future_round();
		$parent  = $this->createPerson( [], [ 'email_1' => 'parent@example.org' ] );
		$partner = $this->createPerson( [], [ 'email_1' => 'partner@example.org' ] );
		$child   = $this->createPerson(
			[],
			[
				'leeftijdsgroep' => 'Onder 12',
				'email_1'        => 'child@example.org',
			]
			);
		Fields::update_for_post(
			$child,
			'relationships',
			[
				[
					'related_person'    => $parent,
					'relationship_type' => 2,
				],
				[
					'related_person'    => $partner,
					'relationship_type' => 2,
				],
			]
			);
		foreach ( [ $parent, $partner ] as $adult ) {
			Fields::update_for_post(
				$adult,
				'relationships',
				[
					[
						'related_person'    => $child,
						'relationship_type' => 3,
					],
				]
				);
		}
		VolunteerEligibilityService::invalidate_cache();
		VolunteerObligationCalculator::invalidate_cache();
		$this->assertEqualsCanonicalizing( [ 'parent@example.org', 'partner@example.org' ], array_keys( $this->mailer->recipients( $season ) ) );
		$this->plan_required_shifts( $partner );
		$this->shift();
		$this->assertSame( 0, $this->mailer->run( $this->sunday ) );
	}

	public function test_no_empty_or_late_mail_and_no_retry_after_failure(): void {
		$this->player();
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '-1 minute' ) ) );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+1 day' ) ) );
		$this->assertSame( 0, $this->mailer->run( $this->sunday ) );
		$this->assertEmpty( $this->mail );
		delete_option( WeekendVolunteerMail::STATE );
		$this->shift();
		$this->accept = false;
		$this->assertSame( 0, $this->mailer->run( $this->sunday ) );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 'failed', array_values( get_option( WeekendVolunteerMail::STATE )['results'] )[0]['status'] );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+1 minute' ) ) );
		$this->assertCount( 1, $this->mail );
	}

	public function test_concurrent_callback_cannot_send_and_next_round_is_independent(): void {
		$this->player();
		$this->shift();
		add_option( 'rondo_weekend_mail_lock_2026-09-20', 'locked', '', false );
		$this->assertSame( 0, $this->mailer->run( $this->sunday ) );
		delete_option( 'rondo_weekend_mail_lock_2026-09-20' );
		$this->assertSame( 1, $this->mailer->run( $this->sunday ) );
		$this->assertSame( 1, $this->mailer->run( $this->sunday->modify( '+1 week' ) ) );
		$this->shift( '2026-10-17 11:00:00' );
		$this->assertSame( 1, $this->mailer->run( $this->sunday->modify( '+2 weeks' ) ) );
	}
	public function test_cron_registration_and_batch_continuation(): void {
		WeekendVolunteerMail::unregister_cron();
		$this->mailer->register_cron( $this->sunday->modify( '-2 days' ) );
		$this->assertSame( $this->sunday->getTimestamp(), wp_next_scheduled( WeekendVolunteerMail::HOOK ) );
		WeekendVolunteerMail::unregister_cron();
		for ( $n = 0; $n < 26; ++$n ) {
			$this->player( sprintf( 'cron%02d@example.org', $n ) );
		}
		$this->shift();
		$this->mailer->run( $this->sunday );
		$this->assertSame( $this->sunday->getTimestamp() + 60, wp_next_scheduled( WeekendVolunteerMail::HOOK ) );
		WeekendVolunteerMail::unregister_cron();
		$this->mailer->run( $this->sunday->modify( '+1 minute' ) );
		$this->assertSame( $this->sunday->modify( '+1 week' )->getTimestamp(), wp_next_scheduled( WeekendVolunteerMail::HOOK ) );
		WeekendVolunteerMail::unregister_cron();
		$this->assertFalse( wp_next_scheduled( WeekendVolunteerMail::HOOK ) );
	}

	public function test_change_during_batch_and_ambiguous_provider_result_are_not_resent(): void {
		$this->player( 'a@example.org' );
		$second = $this->player( 'b@example.org' );
		$this->shift();
		add_filter(
			'pre_wp_mail',
			function () use ( $second ) {
				Fields::update_for_post( $second, 'vrijgesteld_handmatig', true );
				Fields::update_for_post( $second, 'vrijstelling_seizoen', '2026-2027' );
				VolunteerEligibilityService::invalidate_cache();
				VolunteerObligationCalculator::invalidate_cache();
				throw new \RuntimeException( 'Provider timeout after possible acceptance' );
			},
			0
			);
		$this->assertSame( 0, $this->mailer->run( $this->sunday ) );
		$results = array_values( get_option( WeekendVolunteerMail::STATE )['results'] );
		$this->assertSame( [ 'uncertain', 'skipped' ], array_column( $results, 'status' ) );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+1 minute' ) ) );
	}

	public function test_closed_signup_window_iva_waiver_and_unlimited_capacity(): void {
		$person = $this->player();
		update_post_meta( $this->type, 'iva_required', true );
		$shift = $this->shift( '2026-10-03 11:00:00', [], 'open', 0 );
		$this->assertEmpty( $this->mailer->available_shifts( [ $person ], $this->sunday ) );
		update_post_meta( $shift, 'iva_waived', true );
		$rows = $this->mailer->available_shifts( [ $person ], $this->sunday );
		$this->assertCount( 1, $rows );
		$this->assertSame( -1, $rows[0]['remaining'] );
		$this->assertFalse( $rows[0]['iva'] );
		$this->shift( '2026-07-04 11:00:00' );
		$this->assertEmpty( $this->mailer->available_shifts( [ $person ], new \DateTimeImmutable( '2026-06-21 19:00:00', new \DateTimeZone( 'Europe/Amsterdam' ) ) ) );
	}
	public function test_every_sunday_is_a_delivery_round_and_old_fortnightly_cron_is_replaced(): void {
		WeekendVolunteerMail::unregister_cron();
		wp_schedule_single_event( $this->sunday->modify( '+2 weeks' )->getTimestamp(), WeekendVolunteerMail::HOOK );
		$this->mailer->register_cron( $this->sunday->modify( '+1 day' ) );
		$this->assertSame( $this->sunday->modify( '+1 week' )->getTimestamp(), wp_next_scheduled( WeekendVolunteerMail::HOOK ) );
		$this->player();
		$this->shift( '2026-10-10 11:00:00' );
		$this->assertSame( 1, $this->mailer->run( $this->sunday->modify( '+1 week' ) ) );
		$this->assertSame( '2026-09-27T19:00:00+02:00', WeekendVolunteerMail::next_sunday( $this->sunday )->format( 'c' ) );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+8 days' ) ) );
	}

	public function test_general_overview_is_once_per_round_and_counts_towards_batch_limit(): void {
		update_option( WeekendVolunteerMail::OVERVIEW_EMAIL, 'clubhouse@example.org' );
		for ( $n = 0; $n < 26; ++$n ) {
			$this->player( sprintf( 'member%02d@example.org', $n ) );
		}
		$this->shift();
		$this->assertSame( 25, $this->mailer->run( $this->sunday ) );
		$this->assertCount( 25, $this->mail );
		$this->assertSame( [ 'clubhouse@example.org' ], $this->mail[0]['to'] );
		$this->assertStringContainsString( 'algemene overzicht', $this->mail[0]['message'] );
		$this->assertStringNotContainsString( 'Je hebt dit seizoen', $this->mail[0]['message'] );
		update_option( WeekendVolunteerMail::OVERVIEW_EMAIL, 'changed@example.org' );
		$this->assertSame( 2, $this->mailer->run( $this->sunday->modify( '+1 minute' ) ) );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+2 minutes' ) ) );
		$this->assertCount( 27, $this->mail );
		$this->assertSame( 'accepted', get_option( WeekendVolunteerMail::STATE )['overview']['status'] );
		$this->assertSame( 'clubhouse@example.org', get_option( WeekendVolunteerMail::STATE )['overview']['email'] );
		$this->assertEmpty( get_option( WeekendVolunteerMail::STATE )['pending'] );
	}

	public function test_general_overview_includes_iva_shifts_without_eligible_members_and_handles_empty_rounds(): void {
		update_option( WeekendVolunteerMail::OVERVIEW_EMAIL, 'clubhouse@example.org' );
		update_post_meta( $this->type, 'iva_required', true );
		$this->shift();
		$this->assertSame( 1, $this->mailer->run( $this->sunday ) );
		$this->assertStringContainsString( 'Keuken', $this->mail[0]['message'] );
		$this->assertStringContainsString( 'IVA nodig', $this->mail[0]['message'] );
		$this->assertSame( 1, $this->mailer->run( $this->sunday->modify( '+2 weeks' ) ) );
		$this->assertStringContainsString( 'geen openstaande diensten', $this->mail[1]['message'] );
		$this->assertCount( 2, $this->mail );
	}

	public function test_failed_or_uncertain_overview_is_not_retried(): void {
		update_option( WeekendVolunteerMail::OVERVIEW_EMAIL, 'clubhouse@example.org' );
		$this->accept = false;
		$this->assertSame( 0, $this->mailer->run( $this->sunday ) );
		$this->assertSame( 'failed', get_option( WeekendVolunteerMail::STATE )['overview']['status'] );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+1 minute' ) ) );
		$this->assertCount( 1, $this->mail );
		$attempts = 0;
		add_filter(
			'pre_wp_mail',
			static function () use ( &$attempts ) {
				++$attempts;
				throw new \RuntimeException( 'Provider timeout' );
			},
			0
			);
		$next = $this->sunday->modify( '+2 weeks' );
		$this->assertSame( 0, $this->mailer->run( $next ) );
		$this->assertSame( 'uncertain', get_option( WeekendVolunteerMail::STATE )['overview']['status'] );
		$this->assertSame( 0, $this->mailer->run( $next->modify( '+1 minute' ) ) );
		$this->assertSame( 1, $attempts );
	}

	public function test_status_has_next_local_round_and_aggregate_results_without_recipients(): void {
		update_option( WeekendVolunteerMail::OVERVIEW_EMAIL, 'clubhouse@example.org' );
		update_option(
			WeekendVolunteerMail::STATE,
			[
				'date'     => '2026-10-18',
				'pending'  => [ 'private-pending@example.org' ],
				'results'  => [
					[
						'email'  => 'private-member@example.org',
						'status' => 'accepted',
					],
					[
						'email'  => 'private-unknown@example.org',
						'status' => 'reserved',
					],
				],
				'overview' => [
					'status' => 'accepted',
					'email'  => 'previous-private@example.org',
				],
			]
			);
		$data = $this->mailer->status( new \DateTimeImmutable( '2026-10-25 20:00:00', new \DateTimeZone( 'Europe/Amsterdam' ) ) );
		$this->assertSame( '2026-11-01T19:00:00+01:00', $data['next_at'] );
		$this->assertSame( 1, $data['last_round']['accepted'] );
		$this->assertSame( 1, $data['last_round']['pending'] );
		$this->assertSame( 1, $data['last_round']['uncertain'] );
		$this->assertSame( 'accepted', $data['last_round']['overview_status'] );
		$this->assertStringNotContainsString( 'private', wp_json_encode( $data ) );
	}

	public function test_member_eligibility_is_rechecked_after_sending_the_overview(): void {
		$season = $this->use_future_round();
		$person = $this->player();
		$this->shift();
		update_option( WeekendVolunteerMail::OVERVIEW_EMAIL, 'clubhouse@example.org' );
		add_filter(
			'pre_wp_mail',
			static function ( $pre, $atts ) use ( $person, $season ) {
				if ( in_array( 'X-Rondo-Email-Tag: volunteer-weekend-overview', $atts['headers'], true ) ) {
					Fields::update_for_post( $person, 'vrijgesteld_handmatig', true );
					Fields::update_for_post( $person, 'vrijstelling_seizoen', $season );
					VolunteerEligibilityService::invalidate_cache();
					VolunteerObligationCalculator::invalidate_cache();
				}
				return $pre;
			},
			0,
			2
		);
		$this->assertSame( 1, $this->mailer->run( $this->sunday ) );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 'skipped', array_values( get_option( WeekendVolunteerMail::STATE )['results'] )[0]['status'] );
	}
}
