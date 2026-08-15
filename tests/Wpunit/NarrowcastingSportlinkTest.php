<?php

namespace Tests\Wpunit;

use Rondo\Narrowcasting\SportlinkMatchday;
use Tests\Support\RondoTestCase;

/**
 * Contract tests for the server-only Sportlink matchday adapter.
 */
class NarrowcastingSportlinkTest extends RondoTestCase {

	private const CLIENT_ID = 'ServerOnlyClient123';
	private const CLUB_CODE = 'BBKX38Z';

	/** @var callable|null */
	private $http_filter;

	protected function tear_down(): void {
		if ( $this->http_filter ) {
			remove_filter( 'pre_http_request', $this->http_filter, 10 );
		}

		delete_option( 'rondo_narrowcasting_sportlink_client_id' );
		delete_option( 'rondo_narrowcasting_sportlink_club_code' );
		delete_option( 'rondo_narrowcasting_matchday_cache' );
		delete_transient( 'rondo_narrowcasting_matchday_refresh_lock' );
		delete_transient( 'rondo_narrowcasting_manual_refresh_lock' );
		parent::tear_down();
	}

	public function test_refresh_normalizes_matchday_data_without_exposing_the_client_id(): void {
		$service = new SportlinkMatchday( false );
		$saved   = $service->update_settings(
			[
				'client_id'          => self::CLIENT_ID,
				'club_relation_code' => self::CLUB_CODE,
			]
		);

		$this->assertTrue( $saved['client_id_configured'] );
		$this->assertSame( '••••••••', $saved['client_id_masked'] );
		$this->assertStringNotContainsString( self::CLIENT_ID, wp_json_encode( $saved ) );

		$today     = wp_date( 'Y-m-d', null, wp_timezone() );
		$yesterday = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS, wp_timezone() );
		$this->mock_sportlink(
			[
				[
					'wedstrijdcode'            => 'match-100',
					'wedstrijddatum'           => $today . 'T12:30:00+0000',
					'thuisteam'                => 'AWC JO13-1',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteam'                  => 'Bezoekers JO13-1',
					'uitteamclubrelatiecode'   => 'OTHER1',
					'veld'                     => 'Veld 2',
					'kleedkamerthuisteam'      => '3',
					'kleedkameruitteam'        => '4',
					'kleedkamerscheidsrechter' => 'S1',
					'status'                   => 'Te spelen',
				],
			],
			[],
			[
				[
					'wedstrijdcode'            => 'result-90',
					'wedstrijddatum'           => $yesterday . 'T15:00:00+0000',
					'thuisteam'                => 'AWC 1',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteam'                  => 'Bezoekers 1',
					'uitteamclubrelatiecode'   => 'OTHER2',
					'uitslag'                  => '3 - 1',
					'status'                   => 'Gespeeld',
				],
			]
		);

		$feed = $service->refresh( true );

		$this->assertFalse( is_wp_error( $feed ) );
		$this->assertCount( 1, $feed['matches'] );
		$this->assertSame( 'home', $feed['matches'][0]['club_side'] );
		$this->assertSame( 'Veld 2', $feed['matches'][0]['pitch'] );
		$this->assertSame( '3', $feed['matches'][0]['dressing_rooms']['home'] );
		$this->assertSame( 'S1', $feed['matches'][0]['dressing_rooms']['referee'] );
		$this->assertSame( '3 - 1', $feed['results'][0]['result'] );
		$this->assertFalse( $feed['source']['stale'] );
		$this->assertStringNotContainsString( self::CLIENT_ID, wp_json_encode( $feed ) );
		$this->assertStringNotContainsString( self::CLIENT_ID, wp_json_encode( get_option( 'rondo_narrowcasting_matchday_cache' ) ) );
	}

	public function test_failed_refresh_preserves_last_known_good_payload(): void {
		$service = new SportlinkMatchday( false );
		$service->update_settings(
			[
				'client_id'          => self::CLIENT_ID,
				'club_relation_code' => self::CLUB_CODE,
			]
		);

		$today = wp_date( 'Y-m-d', null, wp_timezone() );
		$this->mock_sportlink(
			[
				[
					'wedstrijdcode'            => 'retained-match',
					'wedstrijddatum'           => $today . 'T10:00:00+0000',
					'thuisteam'                => 'AWC JO10-1',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteam'                  => 'Bezoekers JO10-1',
					'uitteamclubrelatiecode'   => 'OTHER3',
				],
			],
			[],
			[]
		);
		$first = $service->refresh( true );
		$this->assertCount( 1, $first['matches'] );

		remove_filter( 'pre_http_request', $this->http_filter, 10 );
		$this->http_filter = static function () {
			return [
				'headers'  => [],
				'body'     => '{}',
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$second = $service->refresh( true );

		$this->assertFalse( is_wp_error( $second ) );
		$this->assertCount( 1, $second['matches'] );
		$this->assertSame( 'retained-match', $second['matches'][0]['id'] );
		$this->assertStringContainsString( 'geen geldige JSON-feed', $second['source']['last_error'] );
		$this->assertStringNotContainsString( self::CLIENT_ID, $second['source']['last_error'] );
	}

	/** Install a deterministic pre_http_request response for each Club.Data endpoint. */
	private function mock_sportlink( array $matches, array $cancellations, array $results ): void {
		$this->http_filter = static function ( $preempt, $arguments, $url ) use ( $matches, $cancellations, $results ) {
			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			$body = [];
			if ( str_ends_with( $path, '/programma' ) ) {
				$body = $matches;
			} elseif ( str_ends_with( $path, '/afgelastingen' ) ) {
				$body = $cancellations;
			} elseif ( str_ends_with( $path, '/uitslagen' ) ) {
				$body = $results;
			}

			return [
				'headers'  => [],
				'body'     => wp_json_encode( $body ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );
	}
}
