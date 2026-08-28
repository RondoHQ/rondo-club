<?php
/**
 * Server-side Sportlink Club.Data adapter for Club TV.
 *
 * @package Rondo\Narrowcasting
 */

namespace Rondo\Narrowcasting;

use DateTimeImmutable;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetch, normalize and retain last-known-good matchday data.
 */
class SportlinkMatchday {

	private const API_BASE              = 'https://data.sportlink.com/';
	private const OPTION_CLIENT_ID      = 'rondo_narrowcasting_sportlink_client_id';
	private const OPTION_CLUB_CODE      = 'rondo_narrowcasting_sportlink_club_code';
	private const OPTION_CACHE          = 'rondo_narrowcasting_matchday_cache';
	private const CRON_HOOK             = 'rondo_narrowcasting_refresh_matchday';
	private const CRON_SCHEDULE         = 'rondo_every_five_minutes';
	private const REFRESH_LOCK          = 'rondo_narrowcasting_matchday_refresh_lock';
	private const MANUAL_REFRESH_LOCK   = 'rondo_narrowcasting_manual_refresh_lock';
	private const PROGRAM_TTL           = 5 * MINUTE_IN_SECONDS;
	private const RESULTS_TTL           = 15 * MINUTE_IN_SECONDS;
	private const STALE_MAX_AGE         = DAY_IN_SECONDS;
	private const HTTP_TIMEOUT_SECONDS  = 12;
	private const MANUAL_REFRESH_WINDOW = 30;
	private const ACCESS_WINDOW_BEFORE  = 2 * HOUR_IN_SECONDS;
	private const ACCESS_WINDOW_AFTER   = 4 * HOUR_IN_SECONDS;

	/** Register the periodic refresh when the service is booted outside a REST controller test. */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}

		// phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- The interval is declared through the class constant below.
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedule' ] );
		add_action( 'init', [ $this, 'schedule_refresh' ] );
		add_action( self::CRON_HOOK, [ $this, 'run_scheduled_refresh' ] );
	}

	/** Add the five-minute Club.Data refresh cadence. */
	public function add_cron_schedule( array $schedules ): array {
		$schedules[ self::CRON_SCHEDULE ] = [
			'interval' => self::PROGRAM_TTL,
			'display'  => __( 'Elke vijf minuten', 'rondo' ),
		];

		return $schedules;
	}

	/** Ensure the refresh event exists. */
	public function schedule_refresh(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + self::PROGRAM_TTL, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	/** Refresh expired feeds from cron. */
	public function run_scheduled_refresh(): void {
		$this->refresh( false );
	}

	/** Return administrator-safe settings and cache health. */
	public function settings_summary(): array {
		$feed = $this->get_feed( false );

		return [
			'client_id_configured' => $this->client_id() !== '',
			'client_id_masked'     => $this->client_id() !== '' ? '••••••••' : '',
			'club_relation_code'   => $this->club_code(),
			'status'               => $feed['source'],
			'counts'               => [
				'matches'       => count( $feed['matches'] ),
				'cancellations' => count( $feed['cancellations'] ),
				'results'       => count( $feed['results'] ),
			],
		];
	}

	/**
	 * Save credentials without ever returning the stored client ID.
	 *
	 * An empty client_id preserves the existing value so the masked form can be
	 * submitted without re-entering the credential.
	 *
	 * @return array|\WP_Error
	 */
	public function update_settings( array $settings ) {
		$client_id = isset( $settings['client_id'] ) ? trim( sanitize_text_field( (string) $settings['client_id'] ) ) : '';
		$club_code = isset( $settings['club_relation_code'] )
			? strtoupper( trim( sanitize_text_field( (string) $settings['club_relation_code'] ) ) )
			: $this->club_code();

		if ( $client_id !== '' && ! preg_match( '/^[A-Za-z0-9_-]{4,100}$/', $client_id ) ) {
			return new \WP_Error( 'rondo_sportlink_client_id_invalid', __( 'De Sportlink client-ID heeft een ongeldig formaat.', 'rondo' ), [ 'status' => 400 ] );
		}

		if ( $club_code === '' || ! preg_match( '/^[A-Z0-9_-]{3,30}$/', $club_code ) ) {
			return new \WP_Error( 'rondo_sportlink_club_code_invalid', __( 'Vul een geldige clubrelatiecode in.', 'rondo' ), [ 'status' => 400 ] );
		}

		$changed = false;
		if ( $client_id !== '' && ! hash_equals( $this->client_id(), $client_id ) ) {
			update_option( self::OPTION_CLIENT_ID, $client_id, false );
			$changed = true;
		}

		if ( $club_code !== $this->club_code() ) {
			update_option( self::OPTION_CLUB_CODE, $club_code, false );
			$changed = true;
		}

		if ( $changed ) {
			delete_option( self::OPTION_CACHE );
		}

		return $this->settings_summary();
	}

	/**
	 * Rate-limited administrator refresh.
	 *
	 * @return array|\WP_Error
	 */
	public function manual_refresh() {
		if ( get_transient( self::MANUAL_REFRESH_LOCK ) !== false ) {
			return new \WP_Error( 'rondo_sportlink_refresh_rate_limited', __( 'De Sportlink-feed is zojuist al ververst. Wacht even en probeer opnieuw.', 'rondo' ), [ 'status' => 429 ] );
		}

		set_transient( self::MANUAL_REFRESH_LOCK, 1, self::MANUAL_REFRESH_WINDOW );
		return $this->refresh( true );
	}

	/**
	 * Return a credential-free feed, optionally refreshing expired data first.
	 */
	public function get_feed( bool $refresh_if_stale = true, ?string $target_date = null ): array {
		$cache = $this->cache();
		if ( $refresh_if_stale && $this->client_id() !== '' && $this->cache_needs_refresh( $cache ) ) {
			$this->refresh( false );
			$cache = $this->cache();
		}

		return $this->public_feed( $cache, $target_date );
	}

	/** Return the matchday feed for the nearest Saturday, including today when today is Saturday. */
	public function get_upcoming_saturday_feed( bool $refresh_if_stale = true ): array {
		$today  = new DateTimeImmutable( 'today', wp_timezone() );
		$target = (int) $today->format( 'N' ) === 6 ? $today : $today->modify( 'next saturday' );

		return $this->get_feed( $refresh_if_stale, $target->format( 'Y-m-d' ) );
	}

	/**
	 * Return upcoming home matches for the access scanner.
	 *
	 * The normalized programme cache is shared with Club TV, so credentials and
	 * outbound Sportlink requests stay server-side and are never duplicated in
	 * the browser.
	 */
	public function get_access_candidates( bool $refresh_if_stale = true ): array {
		$cache = $this->cache();
		if ( $refresh_if_stale && $this->client_id() !== '' && $this->cache_needs_refresh( $cache ) ) {
			$this->refresh( false );
			$cache = $this->cache();
		}

		$matches       = $cache['feeds']['matches']['items'] ?? [];
		$cancellations = $cache['feeds']['cancellations']['items'] ?? [];
		$cancelled_ids = array_fill_keys( array_column( $cancellations, 'id' ), true );
		$now           = new DateTimeImmutable( 'now', wp_timezone() );
		$today         = new DateTimeImmutable( 'today', wp_timezone() );
		$latest        = $now->modify( '+8 days' );
		$candidates    = [];

		foreach ( $matches as $match ) {
			if ( ( $match['club_side'] ?? null ) !== 'home' ) {
				continue;
			}

			try {
				$starts_at = new DateTimeImmutable( (string) ( $match['starts_at'] ?? '' ) );
				$starts_at = $starts_at->setTimezone( wp_timezone() );
			} catch ( Exception $exception ) {
				continue;
			}

			if ( $starts_at < $today || $starts_at > $latest ) {
				continue;
			}

			$is_cancelled           = ! empty( $match['cancelled'] ) || isset( $cancelled_ids[ $match['id'] ?? '' ] );
			$window_starts_at       = $starts_at->modify( '-' . self::ACCESS_WINDOW_BEFORE . ' seconds' );
			$window_ends_at         = $starts_at->modify( '+' . self::ACCESS_WINDOW_AFTER . ' seconds' );
			$match['cancelled']     = $is_cancelled;
			$match['is_active']     = ! $is_cancelled && $now >= $window_starts_at && $now <= $window_ends_at;
			$match['is_selectable'] = ! $is_cancelled && ( $match['is_active'] || $starts_at->format( 'Y-m-d' ) === $today->format( 'Y-m-d' ) );
			$match['window_from']   = $window_starts_at->format( DATE_RFC3339 );
			$match['window_until']  = $window_ends_at->format( DATE_RFC3339 );
			$candidates[]           = $match;
		}

		$feed = $this->public_feed( $cache );

		return [
			'configured' => $feed['configured'],
			'local_date' => $today->format( 'Y-m-d' ),
			'matches'    => array_values( $candidates ),
			'source'     => $feed['source'],
		];
	}

	/**
	 * Refresh each expired feed and retain good cached data when Sportlink fails.
	 *
	 * @return array|\WP_Error
	 */
	public function refresh( bool $force = false ) {
		if ( $this->client_id() === '' ) {
			return new \WP_Error( 'rondo_sportlink_not_configured', __( 'Configureer eerst de Sportlink client-ID.', 'rondo' ), [ 'status' => 409 ] );
		}

		if ( get_transient( self::REFRESH_LOCK ) !== false ) {
			return $this->get_feed( false );
		}
		set_transient( self::REFRESH_LOCK, 1, self::HTTP_TIMEOUT_SECONDS + 5 );

		$cache                    = $this->cache();
		$cache['last_attempt_at'] = gmdate( DATE_RFC3339 );
		$errors                   = [];
		$attempted                = false;
		$succeeded                = false;

		$specifications = $this->feed_specifications();
		foreach ( $specifications as $name => $specification ) {
			$existing = $cache['feeds'][ $name ] ?? [];
			if ( ! $force && ! $this->feed_is_expired( $existing ) ) {
				continue;
			}

			$attempted = true;
			$response  = $this->request( $specification['endpoint'], $specification['params'] );
			if ( is_wp_error( $response ) ) {
				$errors[] = sprintf( '%s: %s', $specification['label'], $response->get_error_message() );
				continue;
			}

			$items                   = $this->normalize( $name, $response );
			$now                     = time();
			$cache['feeds'][ $name ] = [
				'items'       => $items,
				'fetched_at'  => gmdate( DATE_RFC3339, $now ),
				'fresh_until' => gmdate( DATE_RFC3339, $now + $specification['ttl'] ),
			];
			$succeeded               = true;
		}

		if ( $succeeded ) {
			$cache['last_success_at'] = gmdate( DATE_RFC3339 );
		}
		$cache['last_error'] = $errors ? implode( ' ', $errors ) : '';
		update_option( self::OPTION_CACHE, $cache, false );
		delete_transient( self::REFRESH_LOCK );

		if ( $attempted && ! $succeeded && ! $this->cache_has_data( $cache ) ) {
			return new \WP_Error( 'rondo_sportlink_unavailable', __( 'Sportlink kon niet worden bereikt en er is nog geen eerder opgeslagen feed.', 'rondo' ), [ 'status' => 502 ] );
		}

		return $this->public_feed( $cache );
	}

	/** Describe the three Club.Data feeds and their independent freshness windows. */
	private function feed_specifications(): array {
		$common = [
			'gebruiklokaleteamgegevens' => 'NEE',
			'thuis'                     => 'JA',
			'uit'                       => 'JA',
		];

		return [
			'matches'       => [
				'label'    => __( 'Programma', 'rondo' ),
				'endpoint' => 'programma',
				'ttl'      => self::PROGRAM_TTL,
				'params'   => array_merge(
					$common,
					[
						'aantaldagen'      => 7,
						'aantalregels'     => 100,
						'weekoffset'       => 0,
						'eigenwedstrijden' => 'JA',
						'sorteervolgorde'  => 'datum',
						'velden'           => 'wedstrijdcode,wedstrijdnummer,thuisteamlogo,thuisteam,thuisteamclubrelatiecode,wedstrijddatum,aanvangstijd,uitteamlogo,uitteam,uitteamclubrelatiecode,veld,kleedkamerthuisteam,kleedkameruitteam,kleedkamerscheidsrechter,status,competitiesoort,accommodatie,locatie,plaats',
					]
				),
			],
			'cancellations' => [
				'label'    => __( 'Afgelastingen', 'rondo' ),
				'endpoint' => 'afgelastingen',
				'ttl'      => self::PROGRAM_TTL,
				'params'   => [
					'weekoffset'                => 0,
					'gebruiklokaleteamgegevens' => 'NEE',
					'velden'                    => 'wedstrijdcode,wedstrijdnummer,thuisteam,thuisteamclubrelatiecode,status,datum,wedstrijddatum,aanvangstijd,uitteam,uitteamclubrelatiecode,veld',
				],
			],
			'results'       => [
				'label'    => __( 'Uitslagen', 'rondo' ),
				'endpoint' => 'uitslagen',
				'ttl'      => self::RESULTS_TTL,
				'params'   => array_merge(
					$common,
					[
						'aantaldagen'     => 14,
						'weekoffset'      => -1,
						'sorteervolgorde' => 'datum-omgekeerd',
						'velden'          => 'wedstrijdcode,wedstrijdnummer,thuisteamlogo,thuisteam,thuisteamclubrelatiecode,uitslag,wedstrijddatum,uitteamlogo,uitteam,uitteamclubrelatiecode,status',
					]
				),
			],
		];
	}

	/**
	 * Make one server-side Club.Data request without leaking its URL in errors.
	 *
	 * @return array|\WP_Error
	 */
	private function request( string $endpoint, array $params ) {
		$params['client_id'] = $this->client_id();
		$url                 = add_query_arg( $params, self::API_BASE . $endpoint );
		$response            = wp_safe_remote_get(
			$url,
			[
				'timeout'     => self::HTTP_TIMEOUT_SECONDS,
				'redirection' => 2,
				'headers'     => [ 'Accept' => 'application/json' ],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'rondo_sportlink_request_failed', __( 'Sportlink reageerde niet op tijd.', 'rondo' ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			return new \WP_Error(
				'rondo_sportlink_http_error',
				/* translators: %d is the HTTP response status from Sportlink. */
				sprintf( __( 'Sportlink gaf HTTP-status %d terug.', 'rondo' ), $status )
			);
		}

		$body = trim( wp_remote_retrieve_body( $response ) );
		if ( $body === '' || $body[0] !== '[' ) {
			return new \WP_Error( 'rondo_sportlink_invalid_json', __( 'Sportlink gaf geen geldige JSON-feed terug.', 'rondo' ) );
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return new \WP_Error( 'rondo_sportlink_invalid_json', __( 'Sportlink gaf geen geldige JSON-feed terug.', 'rondo' ) );
		}

		return $decoded;
	}

	/** Normalize one response into the small player-owned contract. */
	private function normalize( string $name, array $rows ): array {
		$items = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$item = $this->normalize_fixture( $row, $name === 'results' );
			if ( $item === null ) {
				continue;
			}

			if ( $name === 'cancellations' ) {
				$item['cancelled'] = true;
			}
			$items[] = $item;
		}

		usort(
			$items,
			static function ( array $left, array $right ) use ( $name ): int {
				$comparison = strcmp( $left['starts_at'], $right['starts_at'] );
				return $name === 'results' ? -$comparison : $comparison;
			}
		);

		return $name === 'results' ? array_slice( $items, 0, 12 ) : $items;
	}

	/** Convert one Sportlink row without member or credential data. */
	private function normalize_fixture( array $row, bool $include_result ): ?array {
		$starts_at = $this->fixture_datetime( $row );
		if ( ! $starts_at ) {
			return null;
		}

		$home_name = $this->clean_text( $row['thuisteam'] ?? '' );
		$away_name = $this->clean_text( $row['uitteam'] ?? '' );
		if ( $home_name === '' || $away_name === '' ) {
			return null;
		}

		$home_code = strtoupper( $this->clean_text( $row['thuisteamclubrelatiecode'] ?? '' ) );
		$away_code = strtoupper( $this->clean_text( $row['uitteamclubrelatiecode'] ?? '' ) );
		$club_side = null;
		if ( $this->club_code() !== '' && hash_equals( $this->club_code(), $home_code ) ) {
			$club_side = 'home';
		} elseif ( $this->club_code() !== '' && hash_equals( $this->club_code(), $away_code ) ) {
			$club_side = 'away';
		}

		$status           = $this->clean_text( $row['status'] ?? '' );
		$competition_type = $this->clean_text( $row['competitiesoort'] ?? '' );
		$cancelled        = stripos( $status, 'afgelast' ) !== false || stripos( $competition_type, 'afgelast' ) !== false;
		$source_id        = $this->clean_text( $row['wedstrijdcode'] ?? $row['wedstrijdnummer'] ?? '' );
		$stable_id        = $source_id !== '' ? $source_id : substr( hash( 'sha256', $starts_at->format( DATE_RFC3339 ) . '|' . $home_name . '|' . $away_name ), 0, 24 );

		$item = [
			'id'             => $stable_id,
			'starts_at'      => $starts_at->format( DATE_RFC3339 ),
			'date'           => $starts_at->format( 'Y-m-d' ),
			'time'           => $starts_at->format( 'H:i' ),
			'home_team'      => $home_name,
			'home_logo_url'  => $this->club_logo_url( $row['thuisteamlogo'] ?? '', $home_code ),
			'away_team'      => $away_name,
			'away_logo_url'  => $this->club_logo_url( $row['uitteamlogo'] ?? '', $away_code ),
			'club_side'      => $club_side,
			'pitch'          => $this->clean_text( $row['veld'] ?? '' ),
			'dressing_rooms' => [
				'home'    => $this->clean_text( $row['kleedkamerthuisteam'] ?? '' ),
				'away'    => $this->clean_text( $row['kleedkameruitteam'] ?? '' ),
				'referee' => $this->clean_text( $row['kleedkamerscheidsrechter'] ?? '' ),
			],
			'location'       => $this->clean_text( $row['accommodatie'] ?? $row['locatie'] ?? $row['plaats'] ?? '' ),
			'status'         => $status,
			'cancelled'      => $cancelled,
		];

		if ( $include_result ) {
			$item['result'] = $this->clean_text( $row['uitslag'] ?? '' );
		}

		return $item;
	}

	/** Parse Sportlink's ISO offset or split date/time representation. */
	private function fixture_datetime( array $row ): ?DateTimeImmutable {
		$value = trim( (string) ( $row['wedstrijddatum'] ?? '' ) );
		if ( $value === '' ) {
			$date  = trim( (string) ( $row['datum'] ?? '' ) );
			$time  = trim( (string) ( $row['aanvangstijd'] ?? '00:00' ) );
			$value = trim( $date . ' ' . $time );
		}

		if ( $value === '' ) {
			return null;
		}

		try {
			return ( new DateTimeImmutable( $value, wp_timezone() ) )->setTimezone( wp_timezone() );
		} catch ( Exception $exception ) {
			return null;
		}
	}

	/** Strip markup and bound data originating outside WordPress. */
	private function clean_text( $value ): string {
		return substr( sanitize_text_field( is_scalar( $value ) ? (string) $value : '' ), 0, 200 );
	}

	/** Keep external logo URLs limited to web-safe image sources. */
	private function clean_url( $value ): string {
		$url = is_scalar( $value ) ? trim( (string) $value ) : '';
		return esc_url_raw( substr( $url, 0, 2048 ), [ 'http', 'https' ] );
	}

	/** Use Sportlink's direct logo first, then its documented voetbal.nl club-code fallback. */
	private function club_logo_url( $value, string $club_code ): string {
		$direct_url = $this->clean_url( $value );
		if ( $direct_url !== '' ) {
			return $direct_url;
		}

		if ( ! preg_match( '/^[A-Z0-9_-]{3,30}$/', $club_code ) ) {
			return '';
		}

		return 'https://logoapi.voetbal.nl/logo.php?clubcode=' . rawurlencode( $club_code );
	}

	/** Select one matchday, merge cancellations, and expose freshness metadata. */
	private function public_feed( array $cache, ?string $target_date = null ): array {
		if ( ! $target_date || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $target_date ) ) {
			$target_date = wp_date( 'Y-m-d', null, wp_timezone() );
		}

		$matches       = $cache['feeds']['matches']['items'] ?? [];
		$cancellations = $cache['feeds']['cancellations']['items'] ?? [];
		$results       = $cache['feeds']['results']['items'] ?? [];
		$matches       = array_values( array_filter( $matches, static fn( array $match ): bool => ( $match['date'] ?? '' ) === $target_date ) );
		$cancellations = array_values( array_filter( $cancellations, static fn( array $match ): bool => ( $match['date'] ?? '' ) === $target_date ) );
		$cancelled_ids = array_fill_keys( array_column( $cancellations, 'id' ), true );

		foreach ( $matches as &$match ) {
			if ( isset( $cancelled_ids[ $match['id'] ] ) ) {
				$match['cancelled'] = true;
				$match['status']    = __( 'Afgelast', 'rondo' );
			}
		}
		unset( $match );

		$fresh_until = [];
		$fetched_at  = [];
		foreach ( $cache['feeds'] ?? [] as $feed ) {
			if ( ! empty( $feed['fresh_until'] ) ) {
				$fresh_until[] = strtotime( $feed['fresh_until'] );
			}
			if ( ! empty( $feed['fetched_at'] ) ) {
				$fetched_at[] = strtotime( $feed['fetched_at'] );
			}
		}

		$oldest_fresh_until = $fresh_until ? min( $fresh_until ) : 0;
		$latest_fetch       = $fetched_at ? max( $fetched_at ) : 0;
		$age                = $latest_fetch ? max( 0, time() - $latest_fetch ) : null;

		return [
			'configured'    => $this->client_id() !== '' && $this->club_code() !== '',
			'generated_at'  => gmdate( DATE_RFC3339 ),
			'target_date'   => $target_date,
			'matches'       => array_values( $matches ),
			'cancellations' => array_values( $cancellations ),
			'results'       => array_values( $results ),
			'source'        => [
				'provider'        => 'Sportlink Club.Data',
				'last_attempt_at' => $cache['last_attempt_at'] ?? null,
				'last_success_at' => $cache['last_success_at'] ?? null,
				'fetched_at'      => $latest_fetch ? gmdate( DATE_RFC3339, $latest_fetch ) : null,
				'fresh_until'     => $oldest_fresh_until ? gmdate( DATE_RFC3339, $oldest_fresh_until ) : null,
				'stale'           => $oldest_fresh_until === 0 || $oldest_fresh_until < time(),
				'expired'         => $age === null || $age > self::STALE_MAX_AGE,
				'age_seconds'     => $age,
				'last_error'      => $cache['last_error'] ?? '',
			],
		];
	}

	/** Return the persisted cache in a stable shape. */
	private function cache(): array {
		$cache = get_option( self::OPTION_CACHE, [] );
		return is_array( $cache ) ? $cache : [];
	}

	/** Whether any required feed needs a server refresh. */
	private function cache_needs_refresh( array $cache ): bool {
		foreach ( array_keys( $this->feed_specifications() ) as $name ) {
			if ( $this->feed_is_expired( $cache['feeds'][ $name ] ?? [] ) ) {
				return true;
			}
		}

		return false;
	}

	/** Whether one cached feed is absent or expired. */
	private function feed_is_expired( array $feed ): bool {
		return empty( $feed['fresh_until'] ) || strtotime( (string) $feed['fresh_until'] ) <= time();
	}

	/** Whether a cache contains at least one last-known-good response. */
	private function cache_has_data( array $cache ): bool {
		foreach ( $cache['feeds'] ?? [] as $feed ) {
			if ( array_key_exists( 'items', $feed ) ) {
				return true;
			}
		}

		return false;
	}

	/** Stored server-only credential. */
	private function client_id(): string {
		return trim( (string) get_option( self::OPTION_CLIENT_ID, '' ) );
	}

	/** Club relation code used to identify home and away fixtures. */
	private function club_code(): string {
		return strtoupper( trim( (string) get_option( self::OPTION_CLUB_CODE, '' ) ) );
	}
}
