<?php

namespace Tests\Wpunit;

use Rondo\Teams\TeamMatches;
use Rondo\Fields\Fields;
use Tests\Support\RondoTestCase;

class TeamMatchesTest extends RondoTestCase {

	private $http_filter;
	private array $programme = [];
	private array $results   = [];
	private bool $fail       = false;
	private int $team_id;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->team_id = $this->createOrganization(
			[ 'post_title' => 'Club 2' ],
			[
				'publicteamid' => 'T123',
				'activiteit'   => 'Veld - Zaterdag',
			]
			);
		set_transient( 'rondo_team_match_directory', $this->directory(), HOUR_IN_SECONDS );
		$this->http_filter = function ( $pre, $args, $url ) {
			if ( ! str_starts_with( $url, 'https://data.sportlink.com/' ) ) {
				return $pre;
			}
			if ( $this->fail ) {
				return new \WP_Error( 'timeout', 'Sensitive URL must not escape' );
			}
			parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $params );
			$this->assertSame( '123', $params['teamcode'] );
			$this->assertSame( '380', $params['aantaldagen'] );
			$this->assertLessThanOrEqual( 0, (int) $params['weekoffset'] );
			return [
				'response' => [ 'code' => 200 ],
				'headers'  => [],
				'body'     => wp_json_encode( str_contains( $url, '/uitslagen?' ) ? $this->results : $this->programme ),
			];
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}

	protected function tear_down(): void {
		remove_filter( 'pre_http_request', $this->http_filter, 10 );
		delete_transient( 'rondo_team_match_directory' );
		parent::tear_down();
	}

	private function directory(): array {
		return [
			[
				'teamcode'           => 123,
				'lokaleteamcode'     => -1,
				'teamnaam'           => 'Club 2',
				'spelsoort'          => 'Veld Algemeen/Zaterdag',
				'teamsoort'          => 'bond',
				'local_names'        => false,
				'kalespelsoort'      => 'VE',
				'competitiesoort'    => 'regulier',
				'leeftijdscategorie' => 'Senioren',
			],
			[
				'teamcode'       => 456,
				'lokaleteamcode' => -1,
				'teamnaam'       => 'Club 2',
				'spelsoort'      => 'Veld Algemeen/Zondag',
				'teamsoort'      => 'bond',
				'local_names'    => false,
			],
			[
				'teamcode'       => 123,
				'lokaleteamcode' => 51,
				'teamnaam'       => 'Club zaterdag 2',
				'spelsoort'      => 'Veld Algemeen',
				'teamsoort'      => 'lokaal',
				'local_names'    => true,
			],
		];
	}

	private function fixture( string $id, int $home = 123, ?string $date = null ): array {
		return [
			'wedstrijdcode'  => $id,
			'wedstrijddatum' => ( $date ?? substr( TeamMatches::season()['key'], 0, 4 ) . '-10-20' ) . 'T15:00:00+0200',
			'aanvangstijd'   => '15:00',
			'thuisteamid'    => $home,
			'uitteamid'      => 999,
			'thuisteam'      => 'Club 2',
			'uitteam'        => 'Tegenstander',
			'status'         => 'Te spelen',
			'accommodatie'   => 'Sportpark',
			'uitslag'        => '',
		];
	}

	private function expire_cache(): void {
		$cache                = get_post_meta( $this->team_id, '_rondo_team_matches_cache', true );
		$cache['retry_after'] = 0;
		update_post_meta( $this->team_id, '_rondo_team_matches_cache', $cache );
	}

	public function test_duration_uses_competition_metadata_and_includes_all_breaks(): void {
		$base = $this->directory()[0];
		foreach ( [
			8  => 54,
			9  => 54,
			10 => 64,
			11 => 79,
			12 => 79,
			13 => 75,
			14 => 85,
			15 => 85,
			16 => 95,
			17 => 95,
			19 => 105,
			20 => 105,
			21 => 105,
			23 => 105,
		] as $age => $minutes ) {
			$row = array_merge( $base, [ 'leeftijdscategorie' => 'Onder ' . $age ] );
			$this->assertSame( $minutes, TeamMatches::duration_minutes( [ 'teamcode' => 123 ], [ $row ] ), 'O' . $age );
		}
		foreach ( [ [ 13, '2e divisie', 85 ], [ 13, 'Divisie 3', 75 ], [ 15, 'Divisie 3', 95 ], [ 15, 'Divisie 4', 85 ], [ 17, 'Divisie 1', 105 ], [ 17, 'Divisie 4', 95 ] ] as [ $age, $division, $minutes ] ) {
			$row = array_merge(
				$base,
				[
					'leeftijdscategorie' => 'Onder ' . $age,
					'klasse'             => $division,
				]
				);
			$this->assertSame( $minutes, TeamMatches::duration_minutes( [ 'teamcode' => 123 ], [ $row ] ) );
		}
		$row['geslacht'] = 'vrouw';
		$row['klasse']   = 'Divisie 1';
		$this->assertSame( 95, TeamMatches::duration_minutes( [ 'teamcode' => 123 ], [ $row ] ) );
		$this->assertSame( 105, TeamMatches::duration_minutes( [ 'teamcode' => 123 ], [ $base ] ) );
		foreach ( [ [ 'leeftijdscategorie' => 'Onder 7' ], [ 'competitienaam' => 'Vrouwen 30+ Toernooivorm 7x7' ], [ 'kalespelsoort' => 'ZA' ], [ 'teamsoort' => 'lokaal' ], [ 'competitienaam' => 'Onder 13 9x9' ] ] as $unsupported ) {
			$this->assertNull( TeamMatches::duration_minutes( [ 'teamcode' => 123 ], [ array_merge( $base, $unsupported ) ] ) );
		}
		$this->assertNull( TeamMatches::duration_minutes( [ 'teamcode' => -1 ], [ $base ] ) );
		$this->assertNull( TeamMatches::duration_minutes( [ 'teamcode' => 123 ], [ $base, $row ] ) );
	}

	public function test_existing_subscription_gains_end_time_and_one_revision(): void {
		$directory                          = $this->directory();
		$directory[0]['leeftijdscategorie'] = 'Onder 17';
		set_transient( 'rondo_team_match_directory', $directory, HOUR_IN_SECONDS );
		$this->programme = [ $this->fixture( '100' ) ];
		$service         = new TeamMatches();
		$service->get_feed( $this->team_id );
		$legacy = get_post_meta( $this->team_id, '_rondo_team_matches_cache', true );
		unset( $legacy['duration_version'], $legacy['matches'][0]['duration_minutes'] );
		$legacy['matches'][0]['sequence']    = 4;
		$legacy['matches'][0]['modified_at'] = '2026-07-01T00:00:00+00:00';
		update_post_meta( $this->team_id, '_rondo_team_matches_cache', $legacy );
		$before = TeamMatches::calendar( $this->team_id, $legacy );
		$feed   = $service->get_feed( $this->team_id );
		$this->assertSame( 95, $feed['matches'][0]['duration_minutes'] );
		$this->assertSame( 5, $feed['matches'][0]['sequence'] );
		$this->assertNotSame( $legacy['matches'][0]['modified_at'], $feed['matches'][0]['modified_at'] );
		$calendar = TeamMatches::calendar( $this->team_id, $feed );
		preg_match( '/UID:[^\r]+/', $before, $old_uid );
		$this->assertStringContainsString( $old_uid[0], $calendar );
		$this->assertStringContainsString( 'DTSTART:' . substr( TeamMatches::season()['key'], 0, 4 ) . '1020T130000Z', $calendar );
		$this->assertStringContainsString( 'DTEND:' . substr( TeamMatches::season()['key'], 0, 4 ) . '1020T143500Z', $calendar );
		$this->expire_cache();
		$again = $service->get_feed( $this->team_id );
		$this->assertSame( 5, $again['matches'][0]['sequence'] );
		$this->assertSame( $feed['matches'][0]['modified_at'], $again['matches'][0]['modified_at'] );
		$this->expire_cache();
		$this->fail = true;
		$this->assertSame( $calendar, TeamMatches::calendar( $this->team_id, $service->get_feed( $this->team_id ) ) );
	}

	public function test_unknown_kickoff_stays_all_day_and_end_time_uses_elapsed_minutes(): void {
		$this->programme                        = [ $this->fixture( '100' ) ];
		$feed                                   = ( new TeamMatches() )->get_feed( $this->team_id );
		$feed['matches'][0]['starts_at']        = '2026-10-25T02:30:00+02:00';
		$feed['matches'][0]['duration_minutes'] = 95;
		$calendar                               = TeamMatches::calendar( $this->team_id, $feed );
		$this->assertStringContainsString( 'DTSTART:20261025T003000Z', $calendar );
		$this->assertStringContainsString( 'DTEND:20261025T020500Z', $calendar );
		$feed['matches'][0]['time_known'] = false;
		$this->assertStringNotContainsString( 'DTEND:', TeamMatches::calendar( $this->team_id, $feed ) );
		$this->assertStringContainsString( 'DTSTART;VALUE=DATE:20261025', TeamMatches::calendar( $this->team_id, $feed ) );
	}

	public function test_resolves_identical_names_by_day_and_includes_linked_local_team(): void {
		$team = ( new TeamMatches() )->resolve_team( $this->team_id, $this->directory() );
		$this->assertSame( 123, $team['teamcode'] );
		$this->assertSame( [ 123, 51 ], $team['ids'] );
		Fields::update_for_post( $this->team_id, 'activiteit', '' );
		$this->assertNull( ( new TeamMatches() )->resolve_team( $this->team_id, $this->directory() ) );
	}

	public function test_local_team_mapping_ignores_wordpress_smart_apostrophes(): void {
		$id        = $this->createOrganization(
			[ 'post_title' => "Mini's" ],
			[
				'publicteamid' => 'CT123',
				'activiteit'   => 'Veld - Zaterdag',
			]
			);
		$directory = [
			[
				'teamcode'       => -1,
				'lokaleteamcode' => 101,
				'teamnaam'       => "Mini's",
				'spelsoort'      => 'Veld Zaterdag',
				'teamsoort'      => 'lokaal',
				'local_names'    => true,
			],
		];
		$team      = ( new TeamMatches() )->resolve_team( $id, $directory );
		$this->assertSame( 101, $team['lokaleteamcode'] );
		$this->assertSame( [ 101 ], $team['ids'] );
	}

	public function test_full_season_merges_results_and_filters_unrelated_local_fixtures(): void {
		$year            = (int) substr( TeamMatches::season()['key'], 0, 4 );
		$this->programme = [ $this->fixture( '100' ), $this->fixture( '101', 51 ), $this->fixture( 'other', 700 ), $this->fixture( 'old', 123, ( $year - 1 ) . '-05-01' ) ];
		$this->results   = [
			array_merge(
			$this->fixture( '100' ),
			[
				'uitslag' => '3 - 1',
				'status'  => 'Uitgespeeld',
			]
			),
		];
		$feed            = ( new TeamMatches() )->get_feed( $this->team_id );
		$this->assertCount( 2, $feed['matches'] );
		$this->assertSame( '3 - 1', $feed['matches'][0]['result'] );
		$this->assertArrayNotHasKey( 'identity', $feed );
	}

	public function test_rescheduled_match_keeps_uid_and_increments_revision_without_logo_churn(): void {
		$this->programme = [ $this->fixture( '100' ) ];
		$service         = new TeamMatches();
		$first           = $service->get_feed( $this->team_id );
		$this->expire_cache();
		$this->programme[0]['wedstrijddatum'] = str_replace( '15:00', '17:00', $this->programme[0]['wedstrijddatum'] );
		$second                               = $service->get_feed( $this->team_id );
		$this->assertSame( $first['matches'][0]['id'], $second['matches'][0]['id'] );
		$this->assertSame( 1, $second['matches'][0]['sequence'] );
		$this->assertStringContainsString( 'SEQUENCE:1', TeamMatches::calendar( $this->team_id, $second ) );
		$this->expire_cache();
		$this->programme[0]['thuisteamlogo'] = 'https://example.test/logo.png?sig=new';
		$third                               = $service->get_feed( $this->team_id );
		$this->assertSame( 1, $third['matches'][0]['sequence'] );
	}

	public function test_outage_preserves_events_and_disappeared_future_match_becomes_cancelled(): void {
		$future = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS );
		// On June 30, use today's date so the fixture belongs to the current season.
		if ( $future >= TeamMatches::season()['end'] ) {
			$future = wp_date( 'Y-m-d' );
		}
		$this->programme = [ $this->fixture( '100', 123, $future ) ];
		$service         = new TeamMatches();
		$service->get_feed( $this->team_id );
		$this->expire_cache();
		$this->fail = true;
		$stale      = $service->get_feed( $this->team_id );
		$this->assertTrue( $stale['stale'] );
		$this->assertCount( 1, $stale['matches'] );
		$this->assertFalse( $stale['matches'][0]['cancelled'] );
		$this->fail      = false;
		$this->programme = [];
		$this->expire_cache();
		$cancelled = $service->get_feed( $this->team_id );
		$this->assertTrue( $cancelled['matches'][0]['cancelled'] );
		$this->assertStringContainsString( 'STATUS:CANCELLED', TeamMatches::calendar( $this->team_id, $cancelled ) );
	}

	public function test_calendar_escapes_and_folds_unicode_and_handles_unknown_times(): void {
		$this->programme = [
			array_merge(
			$this->fixture( '100' ),
			[
				'aanvangstijd' => '',
				'accommodatie' => str_repeat( 'één, veld; ', 20 ),
			]
			),
		];
		$feed            = ( new TeamMatches() )->get_feed( $this->team_id );
		$calendar        = TeamMatches::calendar( $this->team_id, $feed );
		$this->assertStringContainsString( 'DTSTART;VALUE=DATE:', $calendar );
		$this->assertStringContainsString( '\\, veld\\;', $calendar );
		foreach ( explode( "\r\n", $calendar ) as $line ) {
			$this->assertLessThanOrEqual( 75, strlen( $line ) );
			$this->assertTrue( mb_check_encoding( $line, 'UTF-8' ) );
		}
	}

	public function test_signed_calendar_access_is_team_scoped_and_overview_requires_login(): void {
		$server          = $this->bootRestControllers( [ \Rondo\REST\TeamMatches::class ] );
		$this->programme = [ $this->fixture( '100' ) ];
		$overview        = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/teams/' . $this->team_id . '/matches' ) );
		$this->assertSame( 200, $overview->get_status() );
		$this->assertArrayHasKey( 'calendar_url', $overview->get_data() );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/teams/' . $this->team_id . '/matches' ) )->get_status() );
		$calendar = new \WP_REST_Request( 'GET', '/rondo/v1/teams/' . $this->team_id . '/matches.ics' );
		$calendar->set_param( 'token', TeamMatches::token( $this->createOrganization( [ 'post_title' => 'Ander team' ] ) ) );
		$this->assertSame( 401, $server->dispatch( $calendar )->get_status() );
		$calendar->set_param( 'token', TeamMatches::token( $this->team_id ) );
		$this->assertSame( 200, $server->dispatch( $calendar )->get_status() );
		wp_update_post(
			[
				'ID'          => $this->team_id,
				'post_status' => 'trash',
			]
			);
		$this->assertSame( 401, $server->dispatch( $calendar )->get_status() );
	}

	public function test_initial_source_failure_returns_503_instead_of_empty_calendar(): void {
		$this->fail = true;
		$result     = ( new TeamMatches() )->get_feed( $this->team_id );
		$this->assertWPError( $result );
		$this->assertSame( 503, $result->get_error_data()['status'] );
	}

	public function test_placeholder_cache_survives_outage_and_tracks_newly_scheduled_matches(): void {
		$directory    = $this->directory();
		$directory[0] = array_merge(
			$directory[0],
			[
				'competitiesoort'    => 'regulier',
				'kalespelsoort'      => 'VE',
				'leeftijdscategorie' => 'Onder 12',
				'competitienaam'     => 'Onder 12 (1e fase)',
				'klasse'             => '1e klasse',
				'speeldag'           => 'Zaterdag',
			]
			);
		set_transient( 'rondo_team_match_directory', $directory, HOUR_IN_SECONDS );
		$service = new TeamMatches();
		$first   = $service->get_feed( $this->team_id );
		$this->assertArrayHasKey( 'matchdays', $first );
		if ( ! $first['matchdays'] ) {
			$this->assertSame( [], $first['matchdays'] );
			return; // Source season has no remaining dates; pure calendar tests use fixed dates.
		}
		$date = array_key_first( $first['matchdays'] );
		$this->expire_cache();
		$this->programme = [ $this->fixture( '100', 123, $date ) ];
		$second          = $service->get_feed( $this->team_id );
		$this->assertCount( 1, $second['matches'] );
		$this->assertTrue( $second['matchdays'][ $date ]['cancelled'] );
		$this->assertSame( 1, $second['matchdays'][ $date ]['sequence'] );
		$this->expire_cache();
		$this->fail = true;
		$stale      = $service->get_feed( $this->team_id );
		$this->assertTrue( $stale['stale'] );
		$this->assertSame( $second['matchdays'], $stale['matchdays'] );
	}
}
