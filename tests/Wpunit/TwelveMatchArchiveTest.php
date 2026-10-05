<?php
namespace Tests\Wpunit;

use Rondo\Twelve\MatchArchive;
use Tests\Support\RondoTestCase;

class TwelveMatchArchiveTest extends RondoTestCase {
	public function test_archive_is_idempotent_retains_past_fixtures_and_moves_rescheduled_ids(): void {
		$service = new MatchArchive();
		$service->register();
		update_option(
			'rondo_kantine_match_config',
			[
				'venue'          => 'De Wijchert',
				'first_team_ids' => [ '641' ],
				'u23_team_ids'   => [ '568' ],
			]
			);
		update_option( 'rondo_narrowcasting_sportlink_club_code', 'TESTCLUB' );
		$today = new \DateTimeImmutable( 'now', new \DateTimeZone( 'Europe/Amsterdam' ) );
		$date  = $today->modify( '+1 day' )->format( 'Y-m-d' );
		$row   = [
			'wedstrijdcode'            => 123,
			'wedstrijddatum'           => $date . 'T15:00:00+02:00',
			'thuisteam'                => 'AWC',
			'uitteam'                  => 'Visitors',
			'thuisteamid'              => 641,
			'thuisteamclubrelatiecode' => 'TESTCLUB',
			'accommodatie'             => 'Sportpark De Wijchert',
		];
		$rows  = [ $row ];
		$fail  = false;
		$mock  = static function ( $response, $args, $url ) use ( &$rows, &$fail ) {
			if ( strpos( $url, 'data.sportlink.com' ) === false ) {
				return $response;
			}
			if ( $fail ) {
				return new \WP_Error( 'timeout' );
			}
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( strpos( $url, '/programma' ) !== false ? $rows : [] ),
			];
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		try {
			$this->assertIsArray( $service->refresh() );
			$first = MatchArchive::load();
			$this->assertSame( 1, MatchArchive::summary( $first[ $date ] )['count'] );
			$this->assertTrue( MatchArchive::summary( $first[ $date ] )['first_home'] );
			$this->assertFalse( $first[ $date ]['complete'] );
			$this->assertIsArray( $service->refresh() );
			$this->assertSame( $first[ $date ]['post_id'], MatchArchive::load()[ $date ]['post_id'] );
			$new_date                  = $today->modify( '+2 days' )->format( 'Y-m-d' );
			$rows[0]['wedstrijddatum'] = $new_date . 'T15:00:00+02:00';
			$service->refresh();
			$this->assertSame( 0, MatchArchive::summary( MatchArchive::load()[ $date ] )['count'] );
			$this->assertSame( 1, MatchArchive::summary( MatchArchive::load()[ $new_date ] )['count'] );
			$fail = true;
			$this->assertWPError( $service->refresh() );
			$this->assertSame( 1, MatchArchive::summary( MatchArchive::load()[ $new_date ] )['count'] );
			$this->assertFalse( (bool) get_option( 'rondo_kantine_archive_lock' ) );
		} finally {
			remove_filter( 'pre_http_request', $mock, 10 );
		}
	}
}
