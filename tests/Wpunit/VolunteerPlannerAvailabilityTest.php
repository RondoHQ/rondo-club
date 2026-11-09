<?php
namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Fees\SeasonKey;
use Rondo\Training\Schedules;
use Rondo\Volunteer\PlannerAvailability;
use Tests\Support\RondoTestCase;

class VolunteerPlannerAvailabilityTest extends RondoTestCase {
	private int $person;
	private int $team;
	private array $feed;
	private \DateTimeImmutable $monday;

	protected function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		$this->monday = current_datetime()->modify( 'next monday' )->setTime( 0, 0 );
		$season       = SeasonKey::current();
		$year         = (int) substr( $season, 0, 4 );
		$this->team   = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'Team',
			]
			);
		$this->person = $this->createPerson(
			[],
			[
				'leeftijdsgroep' => 'Senioren',
				'work_history'   => [
					[
						'team'       => $this->team,
						'job_title'  => 'Teamspeler',
						'is_current' => true,
					],
				],
			]
			);
		$training     = self::factory()->post->create(
			[
				'post_type'   => 'rondo_training',
				'post_status' => 'private',
				'post_title'  => 'Training',
			]
			);
		Fields::update_for_post( $training, 'season', $year . '/' . substr( (string) ( $year + 1 ), -2 ) );
		Fields::update_for_post(
			$training,
			'blocks',
			[
				[
					'block_id' => 'b1',
					'team_ids' => [ $this->team ],
					'day'      => 1,
					'start'    => '18:00',
					'duration' => 90,
				],
			]
			);
		update_option( Schedules::ACTIVE, $training );
		$this->feed = [
			'season'           => $season,
			'identity'         => '|',
			'retry_after'      => time() + HOUR_IN_SECONDS,
			'duration_version' => 1,
			'matched'          => true,
			'stale'            => false,
			'matchdays'        => [],
			'matches'          => [],
		];
		$this->store();
	}
	private function store(): void {
		update_post_meta( $this->team, '_rondo_team_matches_cache', $this->feed );}
	private function span( int $days, string $from, string $to ): array {
		$date = $this->monday->modify( '+' . $days . ' days' )->format( 'Y-m-d' );
		return [ new \DateTimeImmutable( $date . ' ' . $from, wp_timezone() ), new \DateTimeImmutable( $date . ' ' . $to, wp_timezone() ) ];
	}
	public function test_minors_can_finish_at_2200_but_not_later_or_after_midnight(): void {
		$person = $this->createPerson();
		$start  = new \DateTimeImmutable( '2032-11-21 20:00:00', wp_timezone() );
		foreach ( [ '20171121', '2016-11-21', '20151121' ] as $birthdate ) {
			Fields::update_for_post( $person, 'birthdate', $birthdate );
			$this->assertNull( ( new PlannerAvailability() )->reason( $person, [ $start, $start->setTime( 22, 0 ) ], [] ) );
			foreach ( [ '2032-11-21 22:00:01', '2032-11-21 23:59:00', '2032-11-22 00:00:00', '2032-11-22 01:00:00' ] as $end ) {
				$this->assertStringContainsString( 'Jonger dan 18', ( new PlannerAvailability() )->reason( $person, [ $start, new \DateTimeImmutable( $end, wp_timezone() ) ], [] ), $birthdate . ' / ' . $end );
			}
		}
	}

	public function test_age_is_taken_on_the_local_shift_date_including_the_eighteenth_birthday(): void {
		$person = $this->createPerson( [], [ 'birthdate' => '2014-11-21' ] );
		foreach ( [
			'2032-11-20' => true,
			'2032-11-21' => false,
			'2032-11-22' => false,
		] as $date => $blocked ) {
			$start  = new \DateTimeImmutable( $date . ' 20:00:00', wp_timezone() );
			$reason = ( new PlannerAvailability() )->reason( $person, [ $start, $start->setTime( 23, 59 ) ], [] );
			$this->assertSame( $blocked, $reason !== null, $date );
		}
		// Midnight in Amsterdam is still the previous date in UTC.
		$start = new \DateTimeImmutable( '2032-11-20T23:00:00+00:00' );
		$end   = new \DateTimeImmutable( '2032-11-21T22:00:00+00:00' );
		$this->assertNull( ( new PlannerAvailability() )->reason( $person, [ $start, $end ], [] ) );
	}

	public function test_cutoff_uses_amsterdam_time_in_summer_and_winter(): void {
		$person = $this->createPerson( [], [ 'birthdate' => '20160101' ] );
		foreach ( [
			'2032-07-21' => 20,
			'2032-11-21' => 21,
		] as $date => $utc_cutoff ) {
			$start = new \DateTimeImmutable( $date . 'T18:00:00+00:00' );
			$end   = $start->setTime( $utc_cutoff, 0 );
			$this->assertNull( ( new PlannerAvailability() )->reason( $person, [ $start, $end ], [] ) );
			$this->assertStringContainsString( 'na 22:00', ( new PlannerAvailability() )->reason( $person, [ $start, $end->modify( '+1 minute' ) ], [] ) );
		}
	}

	public function test_missing_invalid_and_future_birthdates_are_not_invented_ages(): void {
		$person = $this->createPerson();
		$start  = new \DateTimeImmutable( '2032-11-21 20:00:00', wp_timezone() );
		foreach ( [ '', 'not-a-date', '20150230', '2033-11-21' ] as $birthdate ) {
			Fields::update_for_post( $person, 'birthdate', $birthdate );
			$this->assertNull( ( new PlannerAvailability() )->reason( $person, [ $start, $start->setTime( 23, 59 ) ], [] ), $birthdate );
		}
	}
	public function test_training_uses_active_schedule_and_half_hour_margin(): void {
		$this->assertStringContainsString( 'training', ( new PlannerAvailability() )->reason( $this->person, $this->span( 0, '17:45', '18:15' ), [] ) );
		$this->assertNull( ( new PlannerAvailability() )->reason( $this->person, $this->span( 0, '16:00', '17:30' ), [] ) );
		$this->assertNull( ( new PlannerAvailability() )->reason( $this->person, $this->span( 0, '20:00', '21:00' ), [] ) );
	}
	public function test_match_duration_and_two_hour_margins_are_respected(): void {
		$start                 = $this->monday->modify( '+3 days' )->setTime( 15, 0 );
		$this->feed['matches'] = [
			[
				'starts_at'        => $start->format( DATE_ATOM ),
				'time_known'       => true,
				'duration_minutes' => 105,
				'cancelled'        => false,
			],
		];
		$this->store();
		$this->assertStringContainsString( 'wedstrijd', ( new PlannerAvailability() )->reason( $this->person, $this->span( 3, '13:00', '14:00' ), [] ) );
		$this->assertStringContainsString( 'wedstrijd', ( new PlannerAvailability() )->reason( $this->person, $this->span( 3, '18:00', '19:00' ), [] ) );
		$this->assertNull( ( new PlannerAvailability() )->reason( $this->person, $this->span( 3, '12:00', '13:00' ), [] ) );
		$this->assertNull( ( new PlannerAvailability() )->reason( $this->person, $this->span( 3, '18:45', '20:00' ), [] ) );
	}
	public function test_unknown_time_provisional_day_and_stale_feed_are_not_free_time(): void {
		$day                   = $this->monday->modify( '+3 days' );
		$this->feed['matches'] = [
			[
				'starts_at'        => $day->format( DATE_ATOM ),
				'time_known'       => false,
				'duration_minutes' => null,
				'cancelled'        => false,
			],
		];
		$this->store();
		$this->assertStringContainsString( 'wedstrijd', ( new PlannerAvailability() )->reason( $this->person, $this->span( 3, '07:00', '08:00' ), [] ) );
		$this->feed['matches']   = [];
		$this->feed['matchdays'] = [
			[
				'date'      => $day->format( 'Y-m-d' ),
				'end_date'  => $day->modify( '+1 day' )->format( 'Y-m-d' ),
				'cancelled' => false,
			],
		];
		$this->store();
		$this->assertStringContainsString( 'Voorlopige', ( new PlannerAvailability() )->reason( $this->person, $this->span( 3, '07:00', '08:00' ), [] ) );
		$this->feed['stale'] = true;
		$this->store();
		$this->assertStringContainsString( 'ontbreekt', ( new PlannerAvailability() )->reason( $this->person, $this->span( 1, '07:00', '08:00' ), [] ) );
	}
	public function test_existing_proposal_or_shift_and_missing_team_training_data_block_assignment(): void {
		$span = $this->span( 1, '12:00', '14:00' );
		$this->assertStringContainsString( 'Overlapt', ( new PlannerAvailability() )->reason( $this->person, $span, [ $this->person => [ $span ] ] ) );
		update_option( Schedules::ACTIVE, 0 );
		$this->assertStringContainsString( 'trainingsschema', ( new PlannerAvailability() )->reason( $this->person, $span, [] ) );
		Fields::update_for_post( $this->person, 'work_history', [] );
		$this->assertStringContainsString( 'teamindeling', ( new PlannerAvailability() )->reason( $this->person, $span, [] ) );
	}
}
