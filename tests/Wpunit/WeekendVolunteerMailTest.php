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

	private function shift( string $date = '2026-10-03 11:00:00', array $assigned = [], string $status = 'open', int $capacity = 2, ?int $type = null ): int {
		$id = self::factory()->post->create(
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

	public function test_schedule_preserves_local_evening_through_dst_and_year_boundary(): void {
		foreach ( [
			[ '2026-10-18 19:00:00', '2026-10-25T19:00:00+01:00' ],
			[ '2026-03-22 19:00:00', '2026-03-29T19:00:00+02:00' ],
			[ '2026-12-27 19:00:00', '2027-01-03T19:00:00+01:00' ],
			[ '2026-09-20 18:59:00', '2026-09-20T19:00:00+02:00' ],
		] as [$input, $expected] ) {
			$this->assertSame( $expected, WeekendVolunteerMail::next_sunday( new \DateTimeImmutable( $input, new \DateTimeZone( 'Europe/Amsterdam' ) ) )->format( 'c' ) );
		}
		[$start, $end] = WeekendVolunteerMail::weekend( $this->sunday );
		$this->assertSame( '2026-10-03 00:00:00', $start->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-10-05 00:00:00', $end->format( 'Y-m-d H:i:s' ) );
	}

	public function test_weekend_only_current_capacity_grouping_and_certificate_pool_rules(): void {
		$person = $this->player();
		$this->shift();
		$this->shift();
		$this->shift( '2026-10-04 09:00:00' );
		foreach ( [ '2026-10-02 23:00:00', '2026-10-05 00:00:00', '2026-09-26 11:00:00' ] as $outside ) {
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
		$this->player( 'shared@example.org' );
		$this->player( 'SHARED@example.org' );
		$planned = $this->player( 'planned@example.org' );
		$this->shift( '2026-10-10 11:00:00', [ $planned ] );
		$this->shift( '2026-10-11 11:00:00', [ $planned ] );
		$exempt = $this->player( 'exempt@example.org' );
		Fields::update_for_post( $exempt, 'vrijgesteld_handmatig', true );
		Fields::update_for_post( $exempt, 'vrijstelling_seizoen', '2026-2027' );
		$former = $this->player( 'former@example.org' );
		Fields::update_for_post( $former, 'former_member', true );
		$dead = $this->player( 'dead@example.org' );
		Fields::update_for_post( $dead, 'datum_overlijden', '2026-09-01' );
		$this->shift();
		$this->assertSame( 1, $this->mailer->run( $this->sunday ) );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( [ 'shared@example.org' ], $this->mail[0]['to'] );
		$this->assertStringContainsString( '3 oktober en 4 oktober', $this->mail[0]['subject'] );
		$this->assertStringContainsString( 'Hoi Anne,', $this->mail[0]['message'] );
		$this->assertStringNotContainsString( '[voornaam]', $this->mail[0]['message'] );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+2 minutes' ) ) );
	}

	public function test_batch_limit_rechecks_planning_email_and_capacity(): void {
		$people = [];
		for ( $n = 0; $n < 28; ++$n ) {
			$people[] = $this->player( sprintf( 'person%02d@example.org', $n ) );
		}
		$open = $this->shift();
		$this->assertSame( 25, $this->mailer->run( $this->sunday ) );
		$this->assertSame( 0, $this->mailer->run( $this->sunday->modify( '+30 seconds' ) ) );
		$this->shift( '2026-10-10 11:00:00', [ $people[25] ] );
		$this->shift( '2026-10-11 11:00:00', [ $people[25] ] );
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
		$this->assertEqualsCanonicalizing( [ 'parent@example.org', 'partner@example.org' ], array_keys( $this->mailer->recipients( '2026-2027' ) ) );
		$this->shift( '2026-10-10 11:00:00', [ $partner ] );
		$this->shift( '2026-10-11 11:00:00', [ $partner ] );
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

	public function test_concurrent_callback_cannot_send_and_next_week_is_independent(): void {
		$this->player();
		$this->shift();
		add_option( 'rondo_weekend_mail_lock_2026-09-20', 'locked', '', false );
		$this->assertSame( 0, $this->mailer->run( $this->sunday ) );
		delete_option( 'rondo_weekend_mail_lock_2026-09-20' );
		$this->assertSame( 1, $this->mailer->run( $this->sunday ) );
		$this->shift( '2026-10-10 11:00:00' );
		$this->assertSame( 1, $this->mailer->run( $this->sunday->modify( '+1 week' ) ) );
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
}
