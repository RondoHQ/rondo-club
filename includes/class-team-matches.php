<?php
/**
 * Season programme and subscription calendars for teams.
 */

namespace Rondo\Teams;

use DateTimeImmutable;
use Rondo\Fields\Fields;
use Rondo\Narrowcasting\SportlinkMatchday;

class TeamMatches {

	private const CACHE_KEY = '_rondo_team_matches_cache';
	private const TTL       = 15 * MINUTE_IN_SECONDS;

	/** Resolve the current season in the WordPress timezone. */
	public static function season(): array {
		$today = new DateTimeImmutable( 'today', wp_timezone() );
		$year  = (int) $today->format( 'Y' ) - ( (int) $today->format( 'n' ) < 7 ? 1 : 0 );
		return [
			'key'   => $year . '-' . ( $year + 1 ),
			'start' => $year . '-07-01',
			'end'   => ( $year + 1 ) . '-07-01',
		];
	}

	/** Match names exactly, ignoring presentation-only whitespace and case. */
	private static function name_key( string $value ): string {
		return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) ) );
	}

	/** Read the club directory without ever exposing the server credential. */
	private function directory() {
		$key    = 'rondo_team_match_directory';
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$source = new SportlinkMatchday( false );
		$rows   = [];
		foreach ( [ 'NEE', 'JA' ] as $local_names ) {
			$result = $source->request( 'teams', [ 'gebruiklokaleteamgegevens' => $local_names ] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			foreach ( $result as $row ) {
				$row['local_names'] = $local_names === 'JA';
				$rows[]             = $row;
			}
		}
		set_transient( $key, $rows, HOUR_IN_SECONDS );
		return $rows;
	}

	/** Resolve canonical union names or club-local names; never guess between teams. */
	public function resolve_team( int $team_id, array $directory ): ?array {
		$name       = self::name_key( get_the_title( $team_id ) );
		$public_id  = (string) Fields::get_for_post( $team_id, 'publicteamid' );
		$is_local   = str_starts_with( $public_id, 'CT' );
		$activity   = mb_strtolower( (string) Fields::get_for_post( $team_id, 'activiteit' ) );
		$candidates = [];
		foreach ( $directory as $row ) {
			if ( (bool) $row['local_names'] !== $is_local || self::name_key( (string) ( $row['teamnaam'] ?? '' ) ) !== $name ) {
				continue;
			}
			if ( ! $is_local && ( $row['teamsoort'] ?? '' ) !== 'bond' ) {
				continue;
			}
			$day_mismatch = false;
			foreach ( [ 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag' ] as $day ) {
				if ( str_contains( $activity, $day ) && ! str_contains( mb_strtolower( (string) ( $row['spelsoort'] ?? '' ) ), $day ) && ( $row['teamsoort'] ?? '' ) === 'bond' ) {
					$day_mismatch = true;
				}
			}
			if ( $day_mismatch ) {
				continue;
			}
			$union              = (int) ( $row['teamcode'] ?? -1 );
			$local              = (int) ( $row['lokaleteamcode'] ?? -1 );
			$key                = $union > 0 ? 'team:' . $union : 'local:' . $local;
			$candidates[ $key ] = [
				'teamcode'       => $union,
				'lokaleteamcode' => $local,
			];
		}
		if ( count( $candidates ) !== 1 ) {
			return null;
		}
		$match        = reset( $candidates );
		$match['ids'] = [];
		foreach ( $directory as $row ) {
			if ( $match['teamcode'] > 0 && (int) ( $row['teamcode'] ?? -1 ) === $match['teamcode'] ) {
				$match['ids'][] = $match['teamcode'];
				if ( (int) ( $row['lokaleteamcode'] ?? -1 ) > 0 ) {
					$match['ids'][] = (int) $row['lokaleteamcode'];
				}
			}
		}
		if ( $match['lokaleteamcode'] > 0 ) {
			$match['ids'][] = $match['lokaleteamcode'];
		}
		$match['ids'] = array_values( array_unique( $match['ids'] ) );
		return $match;
	}

	/** Fetch a whole season, retaining history and last-known-good data on outages. */
	public function get_feed( int $team_id ) {
		$season   = self::season();
		$identity = (string) Fields::get_for_post( $team_id, 'publicteamid' ) . '|' . (string) Fields::get_for_post( $team_id, 'activiteit' );
		$cache    = get_post_meta( $team_id, self::CACHE_KEY, true );
		if ( ! is_array( $cache ) || ( $cache['season'] ?? '' ) !== $season['key'] || ( $cache['identity'] ?? '' ) !== $identity ) {
			$cache = [];
		}
		if ( ( $cache['retry_after'] ?? 0 ) > time() ) {
			return $this->response( $cache );
		}
		$lock = 'rondo_team_matches_lock_' . $team_id;
		if ( get_transient( $lock ) ) {
			return $cache ? $this->response( $cache ) : new \WP_Error( 'rondo_matches_loading', 'Het wedstrijdprogramma wordt opgehaald. Probeer het zo opnieuw.', [ 'status' => 503 ] );
		}
		set_transient( $lock, 1, MINUTE_IN_SECONDS );
		try {
			$directory = $this->directory();
			if ( is_wp_error( $directory ) ) {
				return $this->failed_refresh( $team_id, $cache );
			}
			$team = $this->resolve_team( $team_id, $directory );
			if ( ! $team ) {
				if ( $cache ) {
					return $this->failed_refresh( $team_id, $cache );
				}
				return [
					'season'     => $season['key'],
					'matched'    => false,
					'matches'    => [],
					'stale'      => false,
					'updated_at' => null,
				];
			}
			$today  = new DateTimeImmutable( 'today', wp_timezone() );
			$monday = $today->modify( 'monday this week' );
			$start  = new DateTimeImmutable( $season['start'], wp_timezone() );
			$offset = (int) floor( (int) $monday->diff( $start )->format( '%r%a' ) / 7 );
			$params = [
				'teamcode'                  => $team['teamcode'],
				'aantaldagen'               => 380,
				'aantalregels'              => 500,
				'weekoffset'                => $offset,
				'thuis'                     => 'JA',
				'uit'                       => 'JA',
				'eigenwedstrijden'          => 'JA',
				'gebruiklokaleteamgegevens' => 'JA',
			];
			if ( $team['teamcode'] <= 0 ) {
				$params['lokaleteamcode'] = $team['lokaleteamcode'];
			}
			$source = new SportlinkMatchday( false );
			$items  = [];
			foreach ( [ 'programma', 'uitslagen' ] as $endpoint ) {
				$rows = $source->request( $endpoint, $params );
				if ( is_wp_error( $rows ) || count( $rows ) >= 500 ) {
					return $this->failed_refresh( $team_id, $cache );
				}
				foreach ( $rows as $row ) {
					// Sportlink also returns unrelated local fixtures for a team-filtered request.
					if ( ! in_array( (int) ( $row['thuisteamid'] ?? -1 ), $team['ids'], true ) && ! in_array( (int) ( $row['uitteamid'] ?? -1 ), $team['ids'], true ) ) {
						continue;
					}
					if ( empty( $row['wedstrijdcode'] ) ) {
						continue;
					}
					$item = $source->normalize_fixture( $row, true );
					if ( ! $item || $item['date'] < $season['start'] || $item['date'] >= $season['end'] ) {
						continue;
					}
					$item['cancelled']    = $item['cancelled'] || preg_match( '/vervallen|geannuleerd|uitgesteld/i', $item['status'] ) === 1;
					$item['home']         = in_array( (int) ( $row['thuisteamid'] ?? -1 ), $team['ids'], true );
					$item['time_known']   = preg_match( '/^\d{1,2}:\d{2}$/', trim( (string) ( $row['aanvangstijd'] ?? '' ) ) ) === 1;
					$item['competition']  = sanitize_text_field( (string) ( $row['competitiesoort'] ?? '' ) );
					$item['location']     = implode( ', ', array_filter( [ $item['location'], sanitize_text_field( (string) ( $row['plaats'] ?? '' ) ) ] ) );
					$items[ $item['id'] ] = $item;
				}
			}
			$previous = array_column( $cache['matches'] ?? [], null, 'id' );
			foreach ( $previous as $id => $old ) {
				if ( ! isset( $items[ $id ] ) ) {
					// Keep disappeared upcoming events as cancellation tombstones for subscribers.
					if ( $old['date'] >= $today->format( 'Y-m-d' ) ) {
						$old['cancelled'] = true;
						$old['status']    = 'Vervallen';
					}
					$items[ $id ] = $old;
				}
			}
			foreach ( $items as $id => &$item ) {
				$old = $previous[ $id ] ?? [];
				// Expiring logo URLs do not change a calendar event's revision.
				$keys                = [ 'starts_at', 'home_team', 'away_team', 'location', 'pitch', 'status', 'cancelled', 'result', 'time_known', 'competition' ];
				$changed             = array_intersect_key( $item, array_flip( $keys ) ) !== array_intersect_key( $old, array_flip( $keys ) );
				$item['sequence']    = (int) ( $old['sequence'] ?? 0 ) + ( $old && $changed ? 1 : 0 );
				$item['modified_at'] = $changed ? gmdate( DATE_RFC3339 ) : $old['modified_at'];
			}
			unset( $item );
			usort( $items, static fn( $a, $b ) => strcmp( $a['starts_at'], $b['starts_at'] ) );
			$cache = [
				'season'      => $season['key'],
				'identity'    => $identity,
				'matched'     => true,
				'matches'     => array_values( $items ),
				'stale'       => false,
				'updated_at'  => gmdate( DATE_RFC3339 ),
				'retry_after' => time() + self::TTL,
			];
			update_post_meta( $team_id, self::CACHE_KEY, $cache );
			return $this->response( $cache );
		} finally {
			delete_transient( $lock );
		}
	}

	/** A failed source request must never replace a subscribed calendar with an empty one. */
	private function failed_refresh( int $team_id, array $cache ) {
		if ( ! $cache ) {
			return new \WP_Error( 'rondo_matches_unavailable', 'Sportlink is tijdelijk niet beschikbaar. Probeer het later opnieuw.', [ 'status' => 503 ] );
		}
		$cache['stale']       = true;
		$cache['retry_after'] = time() + MINUTE_IN_SECONDS;
		update_post_meta( $team_id, self::CACHE_KEY, $cache );
		return $this->response( $cache );
	}

	/** Expose only match data and freshness, never internal identity/cache keys. */
	private function response( array $cache ): array {
		return array_intersect_key( $cache, array_flip( [ 'season', 'matched', 'matches', 'stale', 'updated_at' ] ) );
	}

	/** A stable, team-scoped capability link usable without WordPress cookies. */
	public static function token( int $team_id ): string {
		return hash_hmac( 'sha256', 'team-matches|' . $team_id . '|' . (string) Fields::get_for_post( $team_id, 'publicteamid' ), wp_salt( 'auth' ) );
	}

	/** Escape RFC 5545 TEXT values. */
	private static function escape( string $value ): string {
		return str_replace( [ '\\', "\r\n", "\r", "\n", ';', ',' ], [ '\\\\', '\\n', '\\n', '\\n', '\\;', '\\,' ], $value );
	}

	/** Generate a subscription with stable UIDs, revisions and explicit cancellations. */
	public static function calendar( int $team_id, array $feed ): string {
		$lines = [ 'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Rondo Club//Teamwedstrijden//NL', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH', 'X-WR-CALNAME:' . self::escape( html_entity_decode( get_the_title( $team_id ), ENT_QUOTES, 'UTF-8' ) . ' wedstrijden' ), 'X-WR-TIMEZONE:' . wp_timezone_string(), 'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H' ];
		foreach ( $feed['matches'] as $item ) {
			$start   = new DateTimeImmutable( $item['starts_at'] );
			$stamp   = gmdate( 'Ymd\THis\Z', strtotime( $item['modified_at'] ) );
			$summary = $item['home_team'] . ' - ' . $item['away_team'];
			if ( $item['result'] !== '' ) {
				$summary .= ' (' . $item['result'] . ')';
			}
			$lines[] = 'BEGIN:VEVENT';
			$lines[] = 'UID:rondo-match-' . $item['id'] . '-' . $team_id . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
			$lines[] = 'DTSTAMP:' . $stamp;
			$lines[] = 'LAST-MODIFIED:' . $stamp;
			$lines[] = 'SEQUENCE:' . $item['sequence'];
			$lines[] = $item['time_known'] ? 'DTSTART:' . gmdate( 'Ymd\THis\Z', $start->getTimestamp() ) : 'DTSTART;VALUE=DATE:' . $start->format( 'Ymd' );
			$lines[] = 'SUMMARY:' . self::escape( $summary );
			$lines[] = 'LOCATION:' . self::escape( $item['location'] );
			$lines[] = 'DESCRIPTION:' . self::escape( implode( "\n", array_filter( [ $item['competition'], $item['status'], $item['pitch'] ? 'Veld: ' . $item['pitch'] : '', ! $item['time_known'] ? 'Aanvangstijd nog niet bekend.' : '' ] ) ) );
			$lines[] = 'STATUS:' . ( $item['cancelled'] ? 'CANCELLED' : 'CONFIRMED' );
			$lines[] = 'END:VEVENT';
		}
		$lines[] = 'END:VCALENDAR';
		$folded  = [];
		foreach ( $lines as $line ) {
			while ( strlen( $line ) > 75 ) {
				$part     = mb_strcut( $line, 0, 75, 'UTF-8' );
				$folded[] = $part;
				$line     = ' ' . substr( $line, strlen( $part ) );
			}
			$folded[] = $line;
		}
		return implode( "\r\n", $folded ) . "\r\n";
	}
}
