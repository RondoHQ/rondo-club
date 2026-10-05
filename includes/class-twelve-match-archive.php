<?php
/** Durable daily fixture snapshots. Results alone never prove a complete programme. */
namespace Rondo\Twelve;

use Rondo\Narrowcasting\SportlinkMatchday;

class MatchArchive {
	public const POST_TYPE = 'rondo_kantine_day';
	public const CRON      = 'rondo_kantine_match_archive';

	public function __construct() {
		add_action( 'init', [ $this, 'register' ] );
		add_action( self::CRON, [ $this, 'refresh' ] );
	}

	public function register(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'        => 'Kantine wedstrijddagen',
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'supports'     => [ 'title' ],
			]
			);
		if ( get_option( 'rondo_kantine_match_config' ) && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 60, 'hourly', self::CRON );
		}
	}

	public static function load(): array {
		$posts  = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'suppress_filters' => true,
			]
			);
		$result = [];
		foreach ( $posts as $post ) {
			$data = get_post_meta( $post->ID, '_kantine_day', true );
			if ( is_array( $data ) && isset( $data['date'] ) ) {
				$data['post_id']         = $post->ID;
				$result[ $data['date'] ] = $data;
			}
		}
		return $result;
	}

	/** No arbitrary team-name matching: Saturday's AWC 1 is a different team. */
	public static function normalize( array $row, SportlinkMatchday $source, array $config ): ?array {
		$item = $source->normalize_fixture( $row, true );
		if ( ! $item || empty( $row['wedstrijdcode'] ?? $row['wedstrijdnummer'] ?? null ) ) {
			return null;
		}
		$item              = array_intersect_key( $item, array_flip( [ 'id', 'date', 'time', 'home_team', 'away_team', 'club_side', 'location', 'status', 'cancelled', 'result' ] ) );
		$kind              = sanitize_text_field( $row['competitiesoort'] ?? '' );
		$team_id           = (string) ( $row['thuisteamid'] ?? '' );
		$item['special']   = in_array( $team_id, $config['first_team_ids'] ?? [], true ) ? 'first' : ( in_array( $team_id, $config['u23_team_ids'] ?? [], true ) ? 'u23' : null );
		$item['youth']     = (bool) preg_match( '/\b(?:J|M)?O\d|\b023/i', $item['home_team'] );
		$item['cancelled'] = $item['cancelled'] || (bool) preg_match( '/afgelast|geannuleerd|uitgesteld|vervallen/i', $item['status'] . ' ' . $kind );
		$activity          = preg_match( '/training|onderling/i', $kind ) || preg_match( '/blokkade|recreanten|mini.s|training/i', $item['home_team'] . ' ' . $item['away_team'] );
		$item['activity']  = (bool) $activity;
		$item['reason']    = $item['cancelled'] ? 'Afgelast of vervallen' : ( $item['club_side'] !== 'home' ? ( $item['club_side'] === null ? 'Club onbekend' : 'Uitwedstrijd' ) : ( $item['location'] === '' ? 'Locatie onbekend' : ( stripos( $item['location'], $config['venue'] ) === false ? 'Andere locatie' : ( $activity ? 'Activiteit' : null ) ) ) );
		return $item;
	}

	/** Preserve history; only fully fetched future programmes replace absence. */
	public function refresh( int $weekoffset = -1, int $days = 21 ) {
		$config = get_option( 'rondo_kantine_match_config', [] );
		if ( empty( $config['venue'] ) ) {
			return new \WP_Error( 'kantine_archive_not_configured', 'Wedstrijdlocatie ontbreekt.' );
		}
		$lock = (int) get_option( 'rondo_kantine_archive_lock', 0 );
		if ( $lock && $lock < time() - 15 * MINUTE_IN_SECONDS ) {
			delete_option( 'rondo_kantine_archive_lock' );
		}
		if ( ! add_option( 'rondo_kantine_archive_lock', time(), '', false ) ) {
			return new \WP_Error( 'kantine_archive_busy', 'Wedstrijdarchief wordt bijgewerkt.' );
		}
		try {
			$source          = new SportlinkMatchday( false );
			$rows            = [];
			$invalid_records = 0;
			foreach ( [ 'programma', 'uitslagen', 'afgelastingen' ] as $endpoint ) {
				$response = $source->request(
					$endpoint,
					[
						'weekoffset'                => $weekoffset,
						'aantaldagen'               => $days,
						'aantalregels'              => 500,
						'thuis'                     => 'JA',
						'uit'                       => 'NEE',
						'eigenwedstrijden'          => 'JA',
						'gebruiklokaleteamgegevens' => 'JA',
					]
					);
				if ( is_wp_error( $response ) || count( $response ) >= 500 ) {
					return new \WP_Error( 'kantine_archive_incomplete', 'Het wedstrijdprogramma kon niet volledig worden opgehaald.' );
				}
				foreach ( $response as $row ) {
					if ( ! is_array( $row ) ) {
						++$invalid_records;
						continue;
					}
					$item = self::normalize( $row, $source, $config );
					if ( ! $item ) {
						++$invalid_records;
						continue;
					}
					if ( $endpoint === 'afgelastingen' ) {
						$item['cancelled'] = true;
						$item['reason']    = 'Afgelast of vervallen';
					}
					$rows[ $item['id'] ] = isset( $rows[ $item['id'] ] ) && $endpoint === 'afgelastingen' ? array_merge(
						$rows[ $item['id'] ],
						[
							'cancelled' => true,
							'reason'    => 'Afgelast of vervallen',
						]
						) : $item;
				}
			}
			$now     = new \DateTimeImmutable( 'now', new \DateTimeZone( 'Europe/Amsterdam' ) );
			$from    = $now->setTime( 0, 0 )->modify( $weekoffset . ' weeks' );
			$to      = $from->modify( '+' . $days . ' days' );
			$rows    = array_filter( $rows, static fn( $row ) => $row['date'] >= $from->format( 'Y-m-d' ) && $row['date'] < $to->format( 'Y-m-d' ) );
			$archive = self::load();
			$changed = [];
			// A rescheduled stable source ID must not remain on its former date.
			foreach ( $archive as $date => &$day ) {
				foreach ( $day['fixtures'] as $id => $old ) {
					if ( isset( $rows[ $id ] ) && $rows[ $id ]['date'] !== $date ) {
						unset( $day['fixtures'][ $id ] );
						$changed[ $date ] = true;
					}
				}
			}
			unset( $day );
			for ( $time = $from; $time < $to; $time = $time->modify( '+1 day' ) ) {
				$date = $time->format( 'Y-m-d' );
				$day  = $archive[ $date ] ?? [
					'date'            => $date,
					'fixtures'        => [],
					'captured_before' => false,
					'captured_during' => false,
					'complete'        => false,
				];
				if ( ! $invalid_records && $date > $now->format( 'Y-m-d' ) ) {
					foreach ( $day['fixtures'] as $id => &$old ) {
						if ( ! isset( $rows[ $id ] ) ) {
							$old['reason'] = 'Niet meer in programma';
						}
					}
					unset( $old );
				}
				foreach ( $rows as $id => $item ) {
					if ( $item['date'] === $date ) {
						$day['fixtures'][ $id ] = $item;
					}
				}
				$day['captured_before'] = $day['captured_before'] || ( ! $invalid_records && $time > $now && $time < $now->modify( '+2 days' ) );
				$day['captured_during'] = ( $day['captured_during'] ?? false ) || ( ! $invalid_records && $date === $now->format( 'Y-m-d' ) );
				// Backfills cannot reconstruct youth fixtures that have disappeared.
				// A final refresh after the day plus a pre-day snapshot is required.
				$day['complete']   = ! $invalid_records && ( $day['complete'] || ( $day['captured_before'] && $day['captured_during'] && $time->modify( '+1 day' ) < $now ) );
				$day['updated_at'] = $now->format( DATE_RFC3339 );
				$archive[ $date ]  = $day;
				$changed[ $date ]  = true;
			}
			foreach ( array_keys( $changed ) as $date ) {
				$day = $archive[ $date ];
				$id  = $day['post_id'] ?? 0;
				if ( ! $id ) {
					$id = wp_insert_post(
						[
							'post_type'   => self::POST_TYPE,
							'post_status' => 'publish',
							'post_title'  => 'Kantine ' . $date,
						],
						true
						);
					if ( is_wp_error( $id ) ) {
						return $id;
					}
				}
				unset( $day['post_id'] );
				update_post_meta( $id, '_kantine_day', wp_slash( $day ) );
				if ( get_post_meta( $id, '_kantine_day', true ) !== $day ) {
					return new \WP_Error( 'kantine_archive_storage', 'Wedstrijdarchief opslaan mislukt.' );
				}
			}
			return [
				'days'            => count( $changed ),
				'fixtures'        => count( $rows ),
				'invalid_records' => $invalid_records,
			];
		} finally {
			delete_option( 'rondo_kantine_archive_lock' );
		}
	}

	public static function summary( array $day ): array {
		$fixtures = array_values( $day['fixtures'] ?? [] );
		usort( $fixtures, static fn( $a, $b ) => strcmp( $a['time'], $b['time'] ) ?: strcmp( $a['home_team'], $b['home_team'] ) );
		$counted  = array_values( array_filter( $fixtures, static fn( $fixture ) => $fixture['reason'] === null ) );
		$first    = array_values( array_filter( $counted, static fn( $fixture ) => $fixture['special'] === 'first' ) );
		$u23      = array_values( array_filter( $counted, static fn( $fixture ) => $fixture['special'] === 'u23' ) );
		$complete = ( $day['complete'] ?? false ) && ! array_filter( $fixtures, static fn( $fixture ) => in_array( $fixture['reason'], [ 'Locatie onbekend', 'Club onbekend' ], true ) );
		return [
			'count'      => count( $counted ),
			'complete'   => $complete,
			'updated_at' => $day['updated_at'] ?? null,
			'fixtures'   => $fixtures,
			'first_home' => $first ? true : ( $complete ? false : null ),
			'u23_home'   => $u23 ? true : ( $complete ? false : null ),
			'first_time' => $first[0]['time'] ?? null,
			'u23_time'   => $u23[0]['time'] ?? null,
		];
	}
}
