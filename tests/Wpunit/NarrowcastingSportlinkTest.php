<?php

namespace Tests\Wpunit;

use DateTimeImmutable;
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
					'thuisteamlogo'            => 'https://example.test/awc.png',
					'thuisteam'                => 'AWC JO13-1',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteamlogo'              => 'https://example.test/bezoekers.png',
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
					'thuisteamlogo'            => 'https://example.test/awc.png',
					'thuisteam'                => 'AWC 1',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteamlogo'              => 'https://example.test/bezoekers.png',
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
		$this->assertSame( 'https://example.test/awc.png', $feed['matches'][0]['home_logo_url'] );
		$this->assertSame( 'https://example.test/bezoekers.png', $feed['matches'][0]['away_logo_url'] );
		$this->assertSame( 'Veld 2', $feed['matches'][0]['pitch'] );
		$this->assertSame( '3', $feed['matches'][0]['dressing_rooms']['home'] );
		$this->assertSame( 'S1', $feed['matches'][0]['dressing_rooms']['referee'] );
		$this->assertSame( '3 - 1', $feed['results'][0]['result'] );
		$this->assertSame( 'https://example.test/awc.png', $feed['results'][0]['home_logo_url'] );
		$this->assertSame( 'https://example.test/bezoekers.png', $feed['results'][0]['away_logo_url'] );
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

	public function test_upcoming_saturday_feed_selects_that_matchday(): void {
		$service = new SportlinkMatchday( false );
		$service->update_settings(
			[
				'client_id'          => self::CLIENT_ID,
				'club_relation_code' => self::CLUB_CODE,
			]
		);

		$today    = new DateTimeImmutable( 'today', wp_timezone() );
		$saturday = (int) $today->format( 'N' ) === 6 ? $today : $today->modify( 'next saturday' );
		$other    = $saturday->modify( '+1 day' );
		$this->mock_sportlink(
			[
				[
					'wedstrijdcode'            => 'saturday-match',
					'wedstrijddatum'           => $saturday->format( 'Y-m-d' ) . 'T12:30:00+0200',
					'thuisteam'                => 'AWC JO13-1',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteam'                  => 'Bezoekers JO13-1',
					'uitteamclubrelatiecode'   => 'OTHER1',
				],
				[
					'wedstrijdcode'            => 'other-match',
					'wedstrijddatum'           => $other->format( 'Y-m-d' ) . 'T12:30:00+0200',
					'thuisteam'                => 'AWC JO14-1',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteam'                  => 'Bezoekers JO14-1',
					'uitteamclubrelatiecode'   => 'OTHER2',
				],
			],
			[],
			[]
		);
		$service->refresh( true );

		$feed = $service->get_upcoming_saturday_feed( false );

		$this->assertSame( $saturday->format( 'Y-m-d' ), $feed['target_date'] );
		$this->assertCount( 1, $feed['matches'] );
		$this->assertSame( 'saturday-match', $feed['matches'][0]['id'] );
	}

	public function test_access_candidates_include_only_home_matches_and_mark_the_active_window(): void {
		$service = new SportlinkMatchday( false );
		$service->update_settings(
			[
				'client_id'          => self::CLIENT_ID,
				'club_relation_code' => self::CLUB_CODE,
			]
		);

		$starts_at = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( '+30 minutes' );
		$future    = $starts_at->modify( '+2 days' );
		$this->mock_sportlink(
			[
				[
					'wedstrijdcode'            => 'home-access-match',
					'wedstrijddatum'           => $starts_at->format( DATE_RFC3339 ),
					'thuisteam'                => 'AWC 1',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteam'                  => 'Bezoekers 1',
					'uitteamclubrelatiecode'   => 'OTHER1',
				],
				[
					'wedstrijdcode'            => 'future-access-match',
					'wedstrijddatum'           => $future->format( DATE_RFC3339 ),
					'thuisteam'                => 'AWC 2',
					'thuisteamclubrelatiecode' => self::CLUB_CODE,
					'uitteam'                  => 'Bezoekers 2',
					'uitteamclubrelatiecode'   => 'OTHER3',
				],
				[
					'wedstrijdcode'            => 'away-access-match',
					'wedstrijddatum'           => $starts_at->format( DATE_RFC3339 ),
					'thuisteam'                => 'Andere club 1',
					'thuisteamclubrelatiecode' => 'OTHER2',
					'uitteam'                  => 'AWC 2',
					'uitteamclubrelatiecode'   => self::CLUB_CODE,
				],
			],
			[],
			[]
		);
		$service->refresh( true );

		$feed = $service->get_access_candidates( false );

		$this->assertCount( 2, $feed['matches'] );
		$this->assertSame( 'home-access-match', $feed['matches'][0]['id'] );
		$this->assertTrue( $feed['matches'][0]['is_active'] );
		$this->assertTrue( $feed['matches'][0]['is_selectable'] );
		$this->assertFalse( $feed['matches'][1]['is_selectable'] );
		$this->assertSame( wp_date( 'Y-m-d' ), $feed['local_date'] );
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

			$query  = wp_parse_url( $url, PHP_URL_QUERY );
			$params = [];
			wp_parse_str( is_string( $query ) ? $query : '', $params );
			$fields = array_filter( explode( ',', (string) ( $params['velden'] ?? '' ) ) );
			if ( $fields ) {
				$allowed = array_fill_keys( $fields, true );
				$body    = array_map( static fn( array $row ): array => array_intersect_key( $row, $allowed ), $body );
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
