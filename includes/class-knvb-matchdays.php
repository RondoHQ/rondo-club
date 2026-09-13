<?php
/**
 * Provisional matchdays from the KNVB district Oost calendar, season 2026/27.
 */

namespace Rondo\Teams;

use DateTimeImmutable;
use Rondo\Narrowcasting\SportlinkMatchday;

class KnvbMatchdays {

	/** Load the reviewed source snapshot; never extrapolate into another season. */
	public static function data(): array {
		static $data = null;
		if ( $data === null ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bundled, reviewed calendar data.
			$data = json_decode( file_get_contents( __DIR__ . '/data/knvb-oost-2026-2027.json' ), true );
		}
		return $data;
	}

	/** Classify only supported regular field competitions, never by the team number. */
	public static function profile( array $row, int $pool_size = 0 ): ?array {
		if ( ( $row['teamsoort'] ?? '' ) !== 'bond' || ( $row['competitiesoort'] ?? '' ) !== 'regulier' || ( $row['kalespelsoort'] ?? '' ) !== 'VE' ) {
			return null;
		}
		$day         = mb_strtolower( (string) ( $row['speeldag'] ?? '' ) );
		$competition = mb_strtolower( (string) ( $row['competitienaam'] ?? '' ) );
		$class       = mb_strtolower( (string) ( $row['klasse'] ?? '' ) );
		$age_text    = (string) ( $row['leeftijdscategorie'] ?? '' ) . ' ' . $competition;
		preg_match( '/(?:onder\s*|\bo)(\d{1,2})\b/i', $age_text, $age_match );
		$age = (int) ( $age_match[1] ?? 0 );
		preg_match( '/(?:divisie\s*(\d+)|(\d+)e?\s+divisie)/i', $class, $division_match );
		$division = (int) ( ( $division_match[1] ?? '' ) ?: ( $division_match[2] ?? 0 ) );
		preg_match( '/\b(\d+)e?\s+klasse/', $class, $class_match );
		$level = (int) ( $class_match[1] ?? 0 );
		$girls = ( $row['geslacht'] ?? '' ) === 'vrouw';
		$top   = str_contains( $class, 'hoofdklasse' );
		$key   = null;
		if ( $day === 'vrijdag' && str_contains( $competition, '7x7' ) && str_contains( $competition, 'toernooi' ) ) {
			$key = 'friday';
		} elseif ( ! in_array( $day, [ 'zaterdag', 'zondag' ], true ) || str_contains( $competition, 'landelijk' ) || str_contains( $competition, '7x7' ) ) {
			return null;
		} elseif ( $age >= 7 && $age <= 12 ) {
			$key = 'pupil';
		} elseif ( $girls && $age >= 13 && $age <= 20 ) {
			$key = in_array( $age, [ 17, 20 ], true ) && ( $division || $top ) ? 'girls_top' : 'girls';
		} elseif ( $age >= 13 && $age <= 19 ) {
			// The national youth calendar covers O19 divisions 1-4, O14-O17 1-3, O13 1-2.
			$national_max = $age === 19 ? 4 : ( $age === 13 ? 2 : 3 );
			if ( $division && $division <= $national_max ) {
				return null;
			}
			$key = $division ? 'junior_division' : ( $top || $level ? 'junior' : null );
		} elseif ( $age === 23 ) {
			$key = $division >= 3 ? 'u23_a' : ( ! $division && $level ? 'u23_b' : null );
		} elseif ( $age === 0 && str_contains( mb_strtolower( (string) ( $row['leeftijdscategorie'] ?? '' ) ), 'senior' ) && ! $division ) {
			$is_a = str_contains( $competition, 'a-cat' ) || ( $girls && $level > 0 && $level <= 2 ) || ( str_contains( $competition, 'reserve' ) && ( $top || ( $level > 0 && $level <= 2 ) ) );
			$is_b = ( str_contains( $competition, 'reserve' ) || $girls ) && $level >= 3;
			$key  = $is_a ? ( in_array( $pool_size, [ 12, 14 ], true ) ? 'senior_a' . $pool_size : 'senior_a' ) : ( $is_b ? 'senior_b' : null );
		}
		return $key ? [
			'key'      => $key,
			'day'      => $day,
			'division' => $division > 0,
		] : null;
	}

	/** Resolve the linked union team's regular competition, including a verified A-pool size. */
	public static function resolve( array $team, array $directory ) {
		$profiles = [];
		foreach ( $directory as $row ) {
			if ( ! empty( $row['local_names'] ) || (int) ( $row['teamcode'] ?? 0 ) !== $team['teamcode'] ) {
				continue;
			}
			$profile = self::profile( $row );
			if ( ! $profile && ( $row['teamsoort'] ?? '' ) === 'bond' && ( $row['competitiesoort'] ?? '' ) === 'regulier' ) {
				return null;
			}
			if ( $profile && $profile['key'] === 'senior_a' ) {
				$poule = (int) ( $row['poulecode'] ?? 0 );
				if ( ! $poule ) {
					return null;
				}
				$key  = 'rondo_knvb_pool_size_' . $poule;
				$size = get_transient( $key );
				if ( $size === false ) {
					$standings = ( new SportlinkMatchday( false ) )->request( 'poulestand', [ 'poulecode' => $poule ] );
					if ( is_wp_error( $standings ) ) {
						return $standings;
					}
					$size = count( $standings );
					set_transient( $key, $size, DAY_IN_SECONDS );
				}
				$profile = self::profile( $row, (int) $size );
				if ( $profile['key'] === 'senior_a' ) {
					return null;
				}
			}
			if ( $profile ) {
				$profiles[ wp_json_encode( $profile ) ] = $profile;
			}
		}
		return count( $profiles ) === 1 ? reset( $profiles ) : null;
	}

	/** Translate source abbreviations while preserving the kind of reserved day. */
	private static function label( string $cell, bool $division ): string {
		if ( $cell === 'Div. Inh. / HK vrij' ) {
			return $division ? 'Inhaal' : '';
		}
		if ( $cell === 'Div. Inh. / Hfdkl. F3' ) {
			return $division ? 'Inhaal' : 'Speeldag - Fase 3';
		}
		if ( str_starts_with( $cell, 'Div Fase 2' ) ) {
			return 'Speeldag - Fase ' . ( $division || str_ends_with( $cell, 'F2' ) ? '2' : '3' );
		}
		if ( str_contains( $cell, 'Fase' ) ) {
			return 'Speeldag - ' . $cell;
		}
		return [
			'WD'                   => 'Speeldag',
			'WD NJ'                => 'Speeldag najaar',
			'WD VJ'                => 'Speeldag voorjaar',
			'Beker poule'          => 'Beker poule',
			'Beker KO'             => 'Beker knock-out',
			'Beker'                => 'Beker',
			'Inhaal'               => 'Inhaal',
			'Inh. / Bek.'          => 'Inhaal / beker',
			'Vrij / Inhaal'        => 'Vrij / inhaal',
			'Vrij / NC'            => 'Vrij / nacompetitie',
			'NC'                   => 'Nacompetitie',
			'Midweekse Bekerronde' => 'Midweekse bekerronde',
			'Final League'         => 'Final League',
		][ $cell ] ?? '';
	}

	/** Build dated, all-day candidates. Ranges remain ranges when no exact day is known. */
	public static function candidates( ?array $profile, string $season ): array {
		$data = self::data();
		if ( ! $profile || $season !== $data['season'] ) {
			return [];
		}
		$events = [];
		$rows   = $data['rows'];
		$column = array_search( $profile['key'], $data['columns'], true );
		if ( $profile['key'] === 'friday' ) {
			$rows   = array_map(
				static fn( $date ) => [
					'start' => $date,
					'end'   => $date,
					'mode'  => 'fixed',
					'cells' => [ 'Toernooi 7x7' ],
				],
				$data['fridays']
				);
			$column = 0;
		}
		if ( $column === false ) {
			return [];
		}
		foreach ( $rows as $row ) {
			$cell  = (string) $row['cells'][ $column ];
			$label = $profile['key'] === 'friday' ? $cell : self::label( $cell, $profile['division'] );
			if ( $label === '' ) {
				continue;
			}
			$start = $row['start'];
			$end   = $row['end'];
			if ( $row['mode'] === 'weekend' ) {
				$start = $profile['day'] === 'zondag' ? $end : $start;
				$end   = $start;
			}
			$events[ $start ] = [
				'date'        => $start,
				'end_date'    => ( new DateTimeImmutable( $end ) )->modify( '+1 day' )->format( 'Y-m-d' ),
				'label'       => $label,
				'source_text' => $cell,
			];
		}
		return $events;
	}

	/** Persist revisions and cancellation tombstones so subscriptions remove replaced placeholders. */
	public static function reconcile( array $candidates, array $matches, array $previous, string $today ): array {
		$events = [];
		foreach ( $candidates as $date => $event ) {
			$old = $previous[ $date ] ?? null;
			if ( $date < $today && ! $old ) {
				continue;
			}
			$event['cancelled'] = false;
			foreach ( $matches as $match ) {
				if ( ! $match['cancelled'] && $match['date'] >= $date && $match['date'] < $event['end_date'] ) {
					$event['cancelled'] = true;
					break;
				}
			}
			// An already scheduled match does not need a brand-new cancellation event.
			if ( $event['cancelled'] && ! $old ) {
				continue;
			}
			$events[ $date ] = $event;
		}
		foreach ( $previous as $date => $old ) {
			if ( ! isset( $events[ $date ] ) ) {
				$old['cancelled'] = true;
				$events[ $date ]  = $old;
			}
		}
		foreach ( $events as $date => &$event ) {
			$old                  = $previous[ $date ] ?? [];
			$fields               = array_flip( [ 'date', 'end_date', 'label', 'source_text', 'cancelled' ] );
			$changed              = array_intersect_key( $event, $fields ) !== array_intersect_key( $old, $fields );
			$event['sequence']    = (int) ( $old['sequence'] ?? 0 ) + ( $old && $changed ? 1 : 0 );
			$event['modified_at'] = $changed ? gmdate( DATE_RFC3339 ) : $old['modified_at'];
		}
		unset( $event );
		return $events;
	}
}
