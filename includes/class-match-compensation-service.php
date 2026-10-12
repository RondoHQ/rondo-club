<?php
/** Private match registrations and immutable monthly settlement snapshots. */
namespace Rondo\Matches;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Finance\SepaCreditTransfer;
use Rondo\Teams\TeamMatches;

final class CompensationService {
	public const CONFIG = 'rondo_match_compensation';
	public const REG    = 'rondo_match_reg';
	public const BATCH  = 'rondo_match_batch';

	public function __construct() {
		add_action( 'init', [ self::class, 'register_types' ] );
	}
	public static function register_types(): void {
		foreach ( [ self::REG, self::BATCH ] as $type ) {
			register_post_type(
				$type,
				[
					'public'          => false,
					'show_ui'         => false,
					'show_in_rest'    => false,
					'rewrite'         => false,
					'query_var'       => false,
					'supports'        => [],
					'capability_type' => 'post',
					'map_meta_cap'    => true,
				]
				);
		}
	}
	public static function config(): array {
		return array_merge(
			[
				'debtor_number'                 => '',
				'company_number'                => '',
				'basis_code'                    => 'U2150',
				'win_code'                      => 'L3090',
				'draw_code'                     => 'L3091',
				'payroll_import_confirmed'      => false,
				'payroll_corrections_confirmed' => false,
			],
			get_option(
			self::CONFIG,
			[
				'teams'            => [],
				'bank_code'        => '',
				'retention_policy' => '',
			]
			)
			);
	}
	public static function finance( bool $write = false ): bool {
		return is_user_logged_in() && ( current_user_can( 'manage_options' ) || ( $write ? UserRoles::can_manage_finances() : UserRoles::can_view_finances() ) );
	}
	public static function team_access( int $id ): bool {
		return get_post_type( $id ) === 'team' && get_post_status( $id ) === 'publish' && ( new AccessControl() )->user_can_access_post( $id );
	}
	public static function registrar( int $id ): bool {
		return self::team_access( $id ) && ( current_user_can( 'manage_options' ) || ( current_user_can( 'wedstrijdregistratie' ) && in_array( $id, array_map( 'intval', (array) get_user_meta( get_current_user_id(), '_rondo_match_teams', true ) ), true ) ) );
	}
	public static function rule( int $id ): ?array {
		foreach ( self::config()['teams'] as $team ) {
			if ( $team['team_id'] === $id ) {
				return $team;
			}
		}
		return null;
	}
	public static function settings(): array {
		$config          = self::config();
		$config['teams'] = array_values( array_filter( $config['teams'], static fn( $team ) => self::finance() || self::registrar( $team['team_id'] ) ) );
		foreach ( $config['teams'] as &$team ) {
			$team['name']         = get_the_title( $team['team_id'] );
			$team['can_register'] = self::registrar( $team['team_id'] );
		}
		unset( $team );
		$config['can_manage']    = self::finance( true );
		$config['can_finance']   = self::finance();
		$config['can_configure'] = current_user_can( 'manage_options' );
		if ( ! self::finance() ) {
			unset( $config['bank_code'], $config['retention_policy'] );
		}
		if ( current_user_can( 'manage_options' ) ) {
			$config['available_teams'] = array_map(
				static fn( $post ) => [
					'id'   => $post->ID,
					'name' => $post->post_title,
				],
				get_posts(
			[
				'post_type'        => 'team',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			]
			)
				);
			$config['available_users'] = array_map(
				static fn( $user ) => [
					'id'    => $user->ID,
					'name'  => $user->display_name,
					'teams' => array_values( array_filter( array_map( 'intval', (array) get_user_meta( $user->ID, '_rondo_match_teams', true ) ), static fn( $id ) => $id > 0 ) ),
				],
				get_users()
				);
		}
		return $config;
	}
	public static function configure( array $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::error( 'Alleen een beheerder kan teams en registratoren instellen.', 403 );
		}
		if ( ! is_array( $input['teams'] ?? null ) || ! is_array( $input['registrators'] ?? [] ) || ! is_string( $input['bank_code'] ?? '' ) || ! is_string( $input['retention_policy'] ?? '' ) ) {
			return self::error( 'Ongeldige instellingen.' );
		}
		$teams = [];
		foreach ( $input['teams'] ?? [] as $team ) {
			if ( ! is_array( $team ) ) {
				return self::error( 'Ongeldig team.' ); }
			$id = (int) ( $team['team_id'] ?? 0 );
			if ( ! self::team_access( $id ) || ! in_array( $team['scheme'] ?? '', [ 'awc1', 'jo23' ], true ) || isset( $teams[ $id ] ) ) {
				return self::error( 'Kies unieke, bestaande teams en geldige regelingen.' );
			}
			$old = self::rule( $id );
			if ( $old && $old['scheme'] !== $team['scheme'] && self::posts( self::REG, $id ) ) {
				return self::error( 'De regeling van een team met registraties kan niet worden gewijzigd.', 409 );
			}
			$teams[ $id ] = [
				'team_id' => $id,
				'scheme'  => $team['scheme'],
			];
		}
		foreach ( self::config()['teams'] as $old ) {
			if ( ! isset( $teams[ $old['team_id'] ] ) && self::posts( self::REG, $old['team_id'] ) ) {
				return self::error( 'Een team met historie kan niet worden verwijderd.', 409 );
			}
		}
		$assignments = [];
		foreach ( $input['registrators'] ?? [] as $assignment ) {
			if ( ! is_array( $assignment ) || ! is_array( $assignment['teams'] ?? null ) ) {
				return self::error( 'Ongeldige registratortoewijzing.' ); }
			$user = get_user_by( 'id', (int) ( $assignment['user_id'] ?? 0 ) );
			$ids  = array_unique( array_map( 'intval', (array) ( $assignment['teams'] ?? [] ) ) );
			if ( ! $user || array_diff( $ids, array_keys( $teams ) ) ) {
				return self::error( 'Ongeldige registratortoewijzing.' );
			}
			foreach ( $ids as $team_id ) {
				if ( ! ( new AccessControl() )->user_can_access_post( $team_id, $user->ID ) ) {
					return self::error( 'Geef de registrator eerst toegang tot het toegewezen team via het bestaande rechtenbeheer.' ); }
			}
			$assignments[ $user->ID ] = $ids;
		}
		foreach ( get_users( [ 'meta_key' => '_rondo_match_teams' ] ) as $user ) {
			$assignments[ $user->ID ] ??= [];
		}
		$config = [
			'teams'            => array_values( $teams ),
			'bank_code'        => sanitize_text_field( (string) ( $input['bank_code'] ?? '' ) ),
			'retention_policy' => sanitize_textarea_field( (string) ( $input['retention_policy'] ?? '' ) ),
		];

		foreach ( [ 'debtor_number', 'company_number', 'basis_code', 'win_code', 'draw_code' ] as $key ) {
			$value = $input[ $key ] ?? self::config()[ $key ];
			if ( ! is_string( $value ) || ( $value !== '' && ! preg_match( in_array( $key, [ 'debtor_number', 'company_number' ], true ) ? '/^[1-9][0-9]{0,13}$/D' : '/^[UL][0-9]{4}$/D', $value ) ) ) {
				return self::error( 'Ongeldige administratie- of looncomponentcode.' );
			}
			$config[ $key ] = $value;
		}
		if ( $config['bank_code'] !== '' && ! preg_match( '/^U[0-9]{4}$/D', $config['bank_code'] ) ) {
			return self::error( 'Ongeldige bankvergoedingcode.' );
		}
		foreach ( [ 'payroll_import_confirmed', 'payroll_corrections_confirmed' ] as $key ) {
			$config[ $key ] = ( $input[ $key ] ?? self::config()[ $key ] ) === true;
		}
		update_option( self::CONFIG, $config, false );
		foreach ( $assignments as $id => $ids ) {
			update_user_meta( $id, '_rondo_match_teams', array_values( $ids ) );
			$user = get_user_by( 'id', $id );
			if ( $ids ) {
				$user->add_cap( 'wedstrijdregistratie' );
			} else {
				$user->remove_cap( 'wedstrijdregistratie' );
			}
		}
		return self::settings();
	}

	/** Internal query; records are never exposed by the generic posts API. */
	public static function posts( string $type, int $team ): array {
		return get_posts(
			[
				'post_type'        => $type,
				'post_status'      => [ 'publish', 'draft' ],
				'numberposts'      => -1,
				'meta_key'         => 'team_id',
				'meta_value'       => $team,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			]
			);
	}
	public static function record( int $id ): array {
		// Only a fully committed snapshot is visible after an interrupted multi-field write.
		$record = get_post_meta( $id, '_match_committed', true );
		return is_array( $record ) ? $record : [];
	}
	private static function save( int $id, array $fields, string $request_id ) {
		if ( get_post_type( $id ) === self::BATCH && isset( $fields['rows'] ) ) {
			foreach ( $fields['rows'] as &$row ) {
				$row += [
					'payment_route'         => '',
					'nmbrs_employee_number' => '',
				];
			}
			unset( $row );
		}
		$previous = self::record( $id );
		update_post_meta( $id, '_match_pending', wp_slash( $fields ) );
		$result = Fields::update_many_for_post( $id, Formatter::for_storage( get_post_type( $id ), $fields ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$wire     = Formatter::for_wire( get_post_type( $id ), Fields::all_for_post( $id ) );
		$expected = Formatter::for_wire( get_post_type( $id ), Formatter::for_storage( get_post_type( $id ), $fields ) );
		$actual   = array_intersect_key( $wire, $fields );
		if ( self::canonical( $actual ) !== self::canonical( $expected ) ) {
			return self::error( 'De registratie is niet volledig opgeslagen. Probeer dezelfde aanvraag opnieuw.', 500 );
		}
		$wire['id'] = $id;
		// Per-version history is immutable and does not contain unmasked bank data in comments.
		if ( $previous ) {
			update_post_meta( $id, '_match_version_' . (int) $previous['version'], wp_slash( $previous ) );
		}
		$wire['last_request_id'] = $request_id;
		if ( ! update_post_meta( $id, '_match_committed', wp_slash( $wire ) ) && self::record( $id ) !== $wire ) {
			return self::error( 'Opslaan mislukt. Probeer dezelfde aanvraag opnieuw.', 500 );
		}
		delete_post_meta( $id, '_match_pending' );
		update_post_meta(
			$id,
			'_match_actor_' . (int) $wire['version'],
			[
				'actor' => get_current_user_id(),
				'at'    => current_datetime()->format( DATE_ATOM ),
			]
			);
		return $wire;
	}

	/** Normalize associative key order while retaining scalar types and row order. */
	private static function canonical( array $value ): array {
		foreach ( $value as &$item ) {
			if ( is_array( $item ) ) {
				$item = self::canonical( $item ); }
		}
		unset( $item );
		ksort( $value );
		return $value;
	}

	/** A durable request receipt is written before work; same input safely resumes. */
	public static function operation( array $input, string $scope, callable $work ) {
		$request_id = $input['request_id'] ?? '';
		if ( ! is_string( $request_id ) || ! preg_match( '/^[a-zA-Z0-9-]{16,80}$/D', $request_id ) ) {
			return self::error( 'Ongeldige aanvraagcode.' );
		}
		return CompensationLock::run(
			static function () use ( $input, $scope, $work, $request_id ) {
				$key     = '_rondo_match_op_' . hash( 'sha256', get_current_user_id() . ':' . $request_id );
				$hash    = hash( 'sha256', $scope . ':' . wp_json_encode( $input ) );
				$receipt = get_option( $key );
				if ( $receipt && $receipt['hash'] !== $hash ) {
					return self::error( 'Deze aanvraagcode is al gebruikt voor andere gegevens.', 409 );
				}
				if ( $receipt && isset( $receipt['result'] ) ) {
					return $receipt['result'];
				}
				if ( ! $receipt ) {
					if ( ! add_option( $key, [ 'hash' => $hash ], '', false ) ) {
						return self::error( 'De aanvraag kon niet worden vastgelegd. Probeer opnieuw.', 500 ); }
				}
				$result = $work( $request_id );
				if ( ! is_wp_error( $result ) ) {
					update_option(
					$key,
					[
						'hash'   => $hash,
						'result' => $result,
					],
					false
					);
				}
				return $result;
			}
			);
	}

	/** List only a registrar's own team; financial readers never see absence reasons. */
	public static function registrations( int $team ) {
		if ( ! self::rule( $team ) || ! self::registrar( $team ) ) {
			return self::error( 'Geen registratierechten voor dit team.', 403 );
		}
		return array_values( array_filter( array_map( static fn( $post ) => self::record( $post->ID ), self::posts( self::REG, $team ) ) ) );
	}

	public static function registration( int $id, array $input, bool $dry_run = false ) {
		$old  = $id ? self::record( $id ) : [];
		$team = (int) ( $old['team_id'] ?? $input['team_id'] ?? 0 );
		if ( ! self::rule( $team ) || ! self::registrar( $team ) || ( $id && get_post_type( $id ) !== self::REG ) ) {
			return self::error( 'Geen registratierechten voor dit team.', 403 );
		}
		$work = static function ( $request_id ) use ( $input, $id, $team, $dry_run ) {
				$old = $id ? self::record( $id ) : [];
			if ( ( $old['last_request_id'] ?? '' ) === $request_id ) {
				return $old;
			}
			if ( $id && ( ! $old || (int) ( $input['expected_version'] ?? -1 ) !== (int) $old['version'] ) ) {
				return self::error( 'De registratie is gewijzigd. Vernieuw eerst.', 409 );
			}
			if ( $id && self::locked( $id ) ) {
				return self::error( 'Deze registratie zit in een afgesloten maand. Open eerst een correctieronde.', 409 );
			}
				$allowed = [ 'source_match_id', 'played_on', 'opponent_name', 'home', 'category', 'home_score', 'away_score', 'selection', 'reason', 'phase' ];
				$patch   = $input['fields'] ?? [];
			if ( ! is_array( $patch ) || array_diff( array_keys( $patch ), $allowed ) ) {
				return self::error( 'Onbekende registratievelden.' );
			}
			try {
				Formatter::for_storage( self::REG, $patch );
			} catch ( \InvalidArgumentException $error ) {
				return self::error( $error->getMessage() ); }
				$data                    = array_merge( array_diff_key( $old, array_flip( [ 'id', 'last_request_id' ] ) ), $patch );
				$data['team_id']         = $team;
				$data['season']          = '2026-2027';
				$data['version']         = (int) ( $old['version'] ?? 0 ) + 1;
				$data['phase']         ??= 'draft';
				$data['reason']          = sanitize_text_field( (string) ( $data['reason'] ?? '' ) );
				$data['source_match_id'] = sanitize_text_field( (string) ( $data['source_match_id'] ?? '' ) );
				$data['opponent_name']   = sanitize_text_field( (string) ( $data['opponent_name'] ?? '' ) );
			if ( $data['source_match_id'] === '' || $data['opponent_name'] === '' || ! in_array( $data['phase'], [ 'draft', 'completed' ], true ) || ! is_bool( $data['home'] ?? null ) || ! in_array( $data['category'] ?? '', CompensationCalculator::CATEGORIES, true ) ) {
				return self::error( 'Vul wedstrijd, tegenstander, thuis/uit en wedstrijdsoort in.' );
			}
				$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) ( $data['played_on'] ?? '' ), wp_timezone() );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $data['played_on'] || $data['played_on'] < '2026-07-01' || $data['played_on'] >= '2027-07-01' ) {
				return self::error( 'Kies een speeldatum in seizoen 2026-2027.' );
			}
			if ( ! $id ) {
				foreach ( self::posts( self::REG, $team ) as $post ) {
					$committed = self::record( $post->ID );
					if ( $committed && $committed['last_request_id'] === $request_id ) {
						return $committed; }
				}
			}
			foreach ( self::posts( self::BATCH, $team ) as $post ) {
				$batch = self::record( $post->ID );
				if ( $batch && $batch['phase'] === 'closed' && $batch['month'] === substr( $data['played_on'], 0, 7 ) ) {
					return self::error( 'Deze maand is afgesloten. Open eerst een correctieronde.', 409 );
				}
			}
			foreach ( [ 'home_score', 'away_score' ] as $score ) {
				$data[ $score ] ??= null;
				if ( $data[ $score ] !== null && ( ! is_int( $data[ $score ] ) || $data[ $score ] < 0 || $data[ $score ] > 99 ) ) {
					return self::error( 'Gebruik gehele doelpunten tussen 0 en 99.' );
				}
			}
				$selection = $data['selection'] ?? [];
			if ( ! is_array( $selection ) || count( $selection ) > 100 ) {
				return self::error( 'Ongeldige selectie.' );
			}
				$seen = [];
			foreach ( $selection as &$player ) {
				$person = (int) ( $player['person_id'] ?? 0 );
				if ( ! AccessControl::can_view_person( $person ) || get_post_type( $person ) !== 'person' || get_post_status( $person ) !== 'publish' || isset( $seen[ $person ] ) || ! in_array( $player['participation'] ?? '', array_merge( [ '' ], CompensationCalculator::STATUSES ), true ) ) {
					return self::error( 'Controleer personen en statussen; dubbele of ontoegankelijke personen zijn niet toegestaan.' );
				}
				$seen[ $person ] = true;
				$player          = [
					'person_id'     => $person,
					'player_name'   => html_entity_decode( get_the_title( $person ), ENT_QUOTES, 'UTF-8' ),
					'participation' => $player['participation'],
					'guest'         => ! empty( $player['guest'] ),
				];
			}
				unset( $player );
				$data['selection'] = array_values( $selection );
			if ( $data['phase'] === 'completed' && ( ! $selection || $data['home_score'] === null || $data['away_score'] === null || in_array( '', array_column( $selection, 'participation' ), true ) || $data['played_on'] > current_time( 'Y-m-d' ) ) ) {
				return self::error( 'Alleen gespeelde wedstrijden met uitslag en volledige selectie kunnen worden afgerond.' );
			}
			if ( $data['phase'] === 'completed' && count( array_filter( $selection, static fn( $row ) => $row['participation'] === 'basis' ) ) !== 11 && $data['reason'] === '' ) {
				return self::error( 'Geef een toelichting bij een afwijkend aantal basisspelers.' );
			}
			if ( $old && $old['phase'] === 'completed' && $data['reason'] === '' ) {
				return self::error( 'Geef een reden voor de correctie.' );
			}
			if ( $old && ( ( $old['source_match_id'] !== $data['source_match_id'] && ! str_starts_with( $old['source_match_id'], 'manual:' ) ) || substr( $old['played_on'], 0, 7 ) !== substr( $data['played_on'], 0, 7 ) ) ) {
				return self::error( 'Bronidentiteit of maand wijzigen vereist afzonderlijke beoordeling.', 409 );
			}

				$source_hash = self::verify_source( $team, $data );
			if ( is_wp_error( $source_hash ) ) {
				return $source_hash; }
				$data['source_fingerprint'] = $source_hash;
			foreach ( self::posts( self::REG, $team ) as $other ) {
				$record = self::record( $other->ID );
				if ( $other->ID !== $id && $record && $record['source_match_id'] === $data['source_match_id'] ) {
					return $record['last_request_id'] === $request_id ? $record : self::error( 'Deze wedstrijd is al geregistreerd.', 409 );
				}
			}
				$slug     = 'reg-' . hash( 'sha256', $team . ':' . $data['season'] . ':' . $data['source_match_id'] );
				$existing = get_page_by_path( $slug, OBJECT, self::REG );
			if ( ! $id && $existing && self::record( $existing->ID ) ) {
				$record = self::record( $existing->ID );
				return $record['last_request_id'] === $request_id ? $record : self::error( 'Deze wedstrijd is al geregistreerd.', 409 );
			}
			if ( $dry_run ) {
				return $data; }
			if ( ! $id ) {
				$id = $existing ? $existing->ID : wp_insert_post(
				[
					'post_type'   => self::REG,
					'post_status' => 'draft',
					'post_name'   => $slug,
					'post_title'  => $data['played_on'] . ' ' . $data['opponent_name'],
				],
				true
				);
				if ( is_wp_error( $id ) ) {
					return $id;
				}
			}
				return self::save( $id, $data, $request_id );
		};
		return $dry_run ? $work( $input['request_id'] ?? '' ) : self::operation( $input, 'registration:' . $id, $work );
	}

	/** Current source evidence; injectable for tests without external requests. */
	public static function source_feed( int $team ) {
		return apply_filters( 'rondo_match_compensation_feed', null, $team ) ?? ( new TeamMatches() )->get_feed( $team );
	}
	public static function source_hash( array $match ): string {
		return hash( 'sha256', wp_json_encode( array_intersect_key( $match, array_flip( [ 'id', 'date', 'home', 'home_team', 'away_team', 'competition', 'result', 'cancelled', 'status' ] ) ) ) );
	}
	public static function category( string $source ): string {
		return [
			'regulier'       => 'competitie',
			'competitie'     => 'competitie',
			'nacompetitie'   => 'nacompetitie',
			'beker'          => 'beker',
			'oefen'          => 'oefen',
			'oefenwedstrijd' => 'oefen',
		][ mb_strtolower( trim( $source ) ) ] ?? '';
	}
	private static function verify_source( int $team, array $data ) {
		if ( str_starts_with( $data['source_match_id'], 'manual:' ) ) {
			return $data['reason'] === '' ? self::error( 'Geef een reden voor een handmatige wedstrijd.' ) : '';
		}
		$feed = self::source_feed( $team );
		if ( is_wp_error( $feed ) || ! empty( $feed['stale'] ) ) {
			return self::error( 'De actuele bron kon niet worden gecontroleerd. Probeer later opnieuw.', 409 );
		}
		$match = null;
		foreach ( $feed['matches'] ?? [] as $candidate ) {
			if ( (string) $candidate['id'] === $data['source_match_id'] ) {
				$match = $candidate;
			}
		}
		if ( ! $match || ! empty( $match['cancelled'] ) ) {
			return self::error( 'De bronwedstrijd ontbreekt of is afgelast; historie blijft bewaard.', 409 );
		}
		$category = self::category( (string) ( $match['competition'] ?? '' ) );
		if ( $category !== '' && $category !== $data['category'] ) {
			return self::error( 'De wedstrijdsoort wijkt af van Sportlink.' );
		}
		$differs = $category === '' || $match['date'] !== $data['played_on'] || (bool) $match['home'] !== $data['home'];
		if ( preg_match( '/^(\d+)\s*-\s*(\d+)$/D', trim( (string) ( $match['result'] ?? '' ) ), $score ) ) {
			$differs = $differs || (int) $score[1] !== $data['home_score'] || (int) $score[2] !== $data['away_score'];
		} elseif ( $data['phase'] === 'completed' ) {
			$differs = true;
		}
		if ( $differs && $data['reason'] === '' ) {
			return self::error( 'Licht een onbekende wedstrijdsoort, uitslag of bronafwijking toe.' );
		}
		return self::source_hash( $match );
	}
	private static function source_errors( int $team, string $month, array $matches ): array {
		$feed = self::source_feed( $team );
		if ( is_wp_error( $feed ) || ! empty( $feed['stale'] ) || empty( $feed['matched'] ) ) {
			return [ 'Het actuele Sportlink-programma kon niet volledig worden gecontroleerd.' ];
		}
		$all        = array_filter( array_map( static fn( $post ) => self::record( $post->ID ), self::posts( self::REG, $team ) ) );
		$by_source  = array_column( $all, null, 'source_match_id' );
		$errors     = [];
		$source_ids = [];
		foreach ( $feed['matches'] as $match ) {
			$source_ids[] = (string) $match['id'];
			$registered   = $by_source[ (string) $match['id'] ] ?? null;
			$category     = self::category( (string) ( $match['competition'] ?? '' ) );
			if ( substr( $match['date'], 0, 7 ) === $month && empty( $match['cancelled'] ) && ! in_array( $category, [ 'beker', 'oefen' ], true ) && ! $registered ) {
				$errors[] = $match['date'] . ': wedstrijd ' . $match['home_team'] . ' - ' . $match['away_team'] . ' ontbreekt in de registratie.';
			}
			if ( $registered && substr( $registered['played_on'], 0, 7 ) === $month && $registered['source_fingerprint'] !== self::source_hash( $match ) ) {
				$errors[] = $registered['played_on'] . ': Sportlink is gewijzigd; laat de registrator de wedstrijd opnieuw controleren.';
			}
		}
		foreach ( $matches as $record ) {
			if ( ! str_starts_with( $record['source_match_id'], 'manual:' ) && ! in_array( $record['source_match_id'], $source_ids, true ) ) {
				$errors[] = $record['played_on'] . ': bronwedstrijd ontbreekt; historie blijft bewaard.';
			}
		}
		return $errors;
	}

	public static function locked( int $registration ): bool {
		$record = self::record( $registration );
		foreach ( self::posts( self::BATCH, (int) $record['team_id'] ) as $post ) {
			$batch = self::record( $post->ID );
			if ( $batch && $batch['phase'] === 'closed' && isset( get_post_meta( $post->ID, '_match_sources', true )[ $registration ] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function preview( int $team, string $month ) {
		$rule = self::rule( $team );
		if ( ! self::finance() || ! $rule ) {
			return self::error( 'Geen financiële toegang tot dit team.', 403 );
		}
		if ( ! preg_match( '/^202[67]-(0[1-9]|1[0-2])$/D', $month ) || $month < '2026-07' || $month > '2027-06' ) {
			return self::error( 'Ongeldige maand.' );
		}
		$matches = [];
		$sources = [];
		$errors  = [];
		foreach ( self::posts( self::REG, $team ) as $post ) {
			$record = self::record( $post->ID );
			if ( ! $record ) {
				$errors[] = 'Een registratie is onvolledig opgeslagen.';
				continue;
			}
			if ( substr( $record['played_on'], 0, 7 ) !== $month || ! in_array( $record['category'], [ 'competitie', 'nacompetitie' ], true ) ) {
				continue;
			}
			if ( $record['phase'] !== 'completed' || get_post_meta( $post->ID, '_match_pending', true ) ) {
				$errors[] = $record['played_on'] . ': registratie niet afgerond.';
				continue;
			}
			$matches[]            = $record;
			$sources[ $post->ID ] = (int) $record['version'];
		}
		$errors      = array_merge( $errors, self::source_errors( $team, $month, $matches ) );
		$agreements  = $rule['scheme'] === 'awc1' ? CompensationAgreements::roster( $team ) : [];
		$calculation = $rule['scheme'] === 'awc1' ? CompensationPayroll::calculate( $matches, $agreements, $month ) : [
			'rows'   => CompensationCalculator::calculate( $matches, $rule['scheme'] ),
			'errors' => [],
		];
		$rows        = $calculation['rows'];
		$errors      = array_merge( $errors, $calculation['errors'] );
		foreach ( $rows as &$row ) {
			if ( ! AccessControl::can_view_person( (int) $row['person_id'] ) ) {
				return self::error( 'Een betrokken persoon is niet toegankelijk.', 403 );
			}
			if ( get_post_meta( $row['person_id'], '_match_agreement_pending', true ) ) {
				$errors[] = 'Een afspraakwijziging is niet volledig opgeslagen; herstel deze eerst.';
			}
			$row['nmbrs_name']        ??= '';
			$row['iban']                = (string) Fields::get_for_post( $row['person_id'], 'iban' );
			$row['bank_account_holder'] = (string) Fields::get_for_post( $row['person_id'], 'bank_account_holder' );
		}
		unset( $row );
		if ( ! $matches && ! $rows ) {
			$errors[] = 'Geen afgeronde meetellende wedstrijden of geldige loonafspraken.';
		}
		if ( self::config()['retention_policy'] === '' ) {
			$errors[] = 'Leg het vastgestelde bewaarbeleid vast bij Instellingen.';
		}
		$batches  = array_values( array_filter( array_map( static fn( $post ) => self::record( $post->ID ), self::posts( self::BATCH, $team ) ), static fn( $batch ) => $batch && $batch['month'] === $month ) );
		$baseline = [];
		$previous = $batches ? end( $batches ) : null;
		if ( $previous && $previous['phase'] === 'superseded' ) {
			$baseline = self::baseline( self::record( $previous['id'] ) );
		}
		$allocation_baseline = $previous && $previous['phase'] === 'superseded' ? self::baseline_allocations( $previous ) : [];
		foreach ( $allocation_baseline as $participation => $allocation ) {
			$now = $calculation['allocations'][ $participation ] ?? null;
			if ( $now && $now['key'] !== $allocation['key'] ) {
				$errors[] = 'Een verwerkte deelname is aan een andere betaalroute of een ander personeelsnummer gekoppeld; leg eerst het externe herstel vast.';
			}
		}
		$settlement = $baseline ? self::delta_rows( $rows, $baseline ) : $rows;
		foreach ( $settlement as $row ) {
			$payroll = ( $row['payment_route'] ?? '' ) === 'payroll';
			if ( $payroll && ( $row['nmbrs_name'] === '' || empty( $row['nmbrs_employee_number'] ) ) ) {
				$errors[] = $row['player_name'] . ': loonafspraak is onvolledig.';
			}
			if ( ( $rule['scheme'] === 'jo23' || ( $row['payment_route'] ?? '' ) === 'sepa_guest' ) && $row['amount_cents'] > 0 && ( ! SepaCreditTransfer::valid_iban( $row['iban'] ) || $row['bank_account_holder'] === '' ) ) {
				$errors[] = $row['player_name'] . ': bankgegevens ontbreken of zijn ongeldig.';
			}
		}
		if ( $rule['scheme'] === 'awc1' && $baseline ) {
			foreach ( $baseline as $old ) {
				$old_route = $old['payment_route'] ?? '';
				$same      = array_values( array_filter( $rows, static fn( $row ) => CompensationPayroll::key( $row ) === CompensationPayroll::key( $old ) ) );
				$other     = array_filter( $rows, static fn( $row ) => $row['person_id'] === $old['person_id'] && CompensationPayroll::key( $row ) !== CompensationPayroll::key( $old ) );
				if ( $other && ( ! $same || $same[0]['days'] < $old['days'] || $old_route === '' ) ) {
					$errors[] = 'Betaalroute of personeelsnummer gewijzigd na externe verwerking; leg eerst het herstel van de oorspronkelijke verwerking vast.';
				}
			}
		}
		if ( $rule['scheme'] === 'awc1' && array_filter( $rows, static fn( $row ) => $row['payment_route'] === 'payroll' ) ) {
			foreach ( [ 'debtor_number', 'company_number', 'bank_code', 'basis_code', 'win_code', 'draw_code' ] as $key ) {
				if ( empty( self::config()[ $key ] ) ) {
					$errors[] = 'Nmbrs-instelling ontbreekt: ' . $key;
				}
			}
		}
		$data                = [
			'allocations'         => $calculation['allocations'] ?? [],
			'allocation_baseline' => $allocation_baseline,
			'agreement_snapshot'  => wp_json_encode( $agreements ),
			'payroll_config'      => wp_json_encode( self::payroll_config() ),
			'team_id'             => $team,
			'month'               => $month,
			'scheme'              => $rule['scheme'],
			'rows'                => $rows,
			'settlement_rows'     => $settlement,
			'is_correction'       => (bool) $baseline,
			'sources'             => $sources,
			'errors'              => $errors,
			'bank_code'           => self::config()['bank_code'],
			'batches'             => array_map( static fn( $batch ) => self::batch_summary( $batch ), $batches ),
		];
		$data['fingerprint'] = hash( 'sha256', wp_json_encode( [ $rows, $settlement, $sources, $rule, $data['bank_code'], $agreements, self::payroll_config(), self::config()['retention_policy'], $baseline, $allocation_baseline, $calculation['allocations'] ?? [], $previous['id'] ?? null ] ) );
		return $data;
	}
	public static function payroll_config(): array {
		return array_intersect_key( self::config(), array_flip( [ 'debtor_number', 'company_number', 'basis_code', 'bank_code', 'win_code', 'draw_code' ] ) );
	}
	public static function route_summary( array $batch ): array {
		if ( ( $batch['schema_version'] ?? '' ) !== '2' || $batch['scheme'] !== 'awc1' ) {
			return [];
		}
		$result = [];
		foreach ( [ 'payroll', 'sepa_guest' ] as $route ) {
			$result[ $route ] = [
				'applicable' => (bool) array_filter( $batch['rows'], static fn( $row ) => $row['payment_route'] === $route ),
				'exported'   => (bool) get_post_meta( $batch['id'], '_match_export_' . $route, true ),
				'processing' => get_post_meta( $batch['id'], '_match_processing_' . $route, true ) ?: null,
			];
		}
		return $result;
	}
	private static function baseline_allocations( array $batch ): array {
		$result    = [];
		$reversals = get_post_meta( $batch['id'], '_match_route_reversals', true ) ?: [];
		foreach ( self::route_summary( $batch ) as $route => $status ) {
			$source = get_post_meta( $batch['id'], $status['processing'] ? '_match_full_allocations' : '_match_baseline_allocations', true ) ?: [];
			foreach ( $source as $key => $allocation ) {
				if ( $allocation['payment_route'] === $route && ! isset( $reversals[ $allocation['person_id'] ] ) ) {
					$result[ $key ] = $allocation; }
			}
		}
		return $result;
	}
	public static function baseline( array $batch ): array {
		$routes = self::route_summary( $batch );
		$rows   = [];
		if ( ! $routes ) {
			$rows = get_post_meta( $batch['id'], get_post_meta( $batch['id'], '_match_processing', true ) ? '_match_full_rows' : '_match_baseline_rows', true ) ?: [];
		} else {
			foreach ( $routes as $route => $status ) {
				$source = get_post_meta( $batch['id'], $status['processing'] ? '_match_full_rows' : '_match_baseline_rows', true ) ?: [];
				foreach ( $source as $row ) {
					if ( $row['payment_route'] === $route ) {
						$rows[] = $row; }
				}
			}
		}
		$reversals = get_post_meta( $batch['id'], '_match_route_reversals', true ) ?: [];
		return array_values( array_filter( $rows, static fn( $row ) => ! isset( $reversals[ $row['person_id'] ] ) ) );
	}
	/** External reversal is an explicit attestation, never inferred from a changed agreement. */
	private static function reverse_person( array $batch, array $input, string $request_id ) {
		$person = (int) ( $input['person_id'] ?? 0 );
		if ( $batch['phase'] !== 'superseded' || ( $input['confirmed'] ?? false ) !== true || ! is_string( $input['reference'] ?? null ) || trim( $input['reference'] ) === '' || ! AccessControl::can_view_person( $person ) ) {
			return self::error( 'Open eerst een correctieronde en bevestig met een bewijsreferentie dat alle eerdere verwerking voor deze speler en maand extern is teruggedraaid.', 409 );
		}
		foreach ( self::posts( self::BATCH, (int) $batch['team_id'] ) as $post ) {
			$other = self::record( $post->ID );
			if ( $other && $other['month'] === $batch['month'] && (int) $other['version'] > (int) $batch['version'] ) {
				return self::error( 'Er is al een volgende maandversie; corrigeer de nieuwste versie.', 409 );
			}
		}
		if ( ! array_filter( self::baseline( $batch ), static fn( $row ) => (int) $row['person_id'] === $person ) ) {
			return self::error( 'Deze speler heeft geen eerdere verwerking in deze correctie.' );
		}
		$date = is_string( $input['date'] ?? null ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $input['date'] ) : false;
		if ( ! $date || $date->format( 'Y-m-d' ) !== $input['date'] || $input['date'] > current_time( 'Y-m-d' ) ) {
			return self::error( 'Gebruik een geldige hersteldatum tot en met vandaag.' );
		}
		$reversals            = get_post_meta( $batch['id'], '_match_route_reversals', true ) ?: [];
		$reversals[ $person ] = [
			'date'       => $input['date'],
			'reference'  => sanitize_text_field( $input['reference'] ),
			'actor'      => get_current_user_id(),
			'request_id' => $request_id,
		];
		update_post_meta( $batch['id'], '_match_route_reversals', wp_slash( $reversals ) );
		return get_post_meta( $batch['id'], '_match_route_reversals', true ) === $reversals ? $reversals[ $person ] : self::error( 'Vastleggen van het herstel is mislukt.', 500 );
	}
	public static function batch_summary( array $batch ): array {
		return [
			'routes'     => self::route_summary( $batch ),
			'id'         => $batch['id'],
			'phase'      => $batch['phase'],
			'closed_at'  => $batch['closed_at'],
			'version'    => $batch['version'],
			'exported'   => (bool) get_post_meta( $batch['id'], '_match_export', true ),
			'processing' => get_post_meta( $batch['id'], '_match_processing', true ) ?: null,
		];
	}

	public static function close( array $input ) {
		if ( ! self::finance( true ) ) {
			return self::error( 'Geen financieel beheerrecht.', 403 );
		}
		return self::operation(
			$input,
			'close',
			static function ( $request_id ) use ( $input ) {
				$team    = (int) ( $input['team_id'] ?? 0 );
				$month   = (string) ( $input['month'] ?? '' );
				$preview = self::preview( $team, $month );
				if ( is_wp_error( $preview ) ) {
					return $preview;
				}
				foreach ( $preview['batches'] as $batch ) {
					$record = self::record( $batch['id'] );
					if ( $record['last_request_id'] === $request_id ) {
						return $record;
					}
					if ( $record['phase'] === 'closed' ) {
						return self::error( 'Deze maand is al afgesloten.', 409 );
					}
				}
				if ( $preview['errors'] || ( $input['fingerprint'] ?? '' ) !== $preview['fingerprint'] || ( $input['all_matches_confirmed'] ?? false ) !== true ) {
					return self::error( 'Controleer de actuele maand, alle gespeelde wedstrijden en de blokkerende meldingen.', 409 );
				}
				if ( self::config()['retention_policy'] === '' ) {
					return self::error( 'Leg vóór ingebruikname het clubbeleid voor bewaren vast bij de instellingen.', 409 );
				}
				$version  = count( $preview['batches'] ) + 1;
				$slug     = 'batch-' . hash( 'sha256', $team . ':' . $month . ':' . $request_id );
				$existing = get_page_by_path( $slug, OBJECT, self::BATCH );
				$id       = $existing ? $existing->ID : wp_insert_post(
				[
					'post_type'   => self::BATCH,
					'post_status' => 'draft',
					'post_name'   => $slug,
					'post_title'  => $month . ' ' . get_the_title( $team ),
				],
				true
				);
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				$baseline = [];
				$previous = $preview['batches'] ? end( $preview['batches'] ) : null;
				if ( $previous ) {
					$baseline = self::baseline( self::record( $previous['id'] ) );
				}
				update_post_meta( $id, '_match_full_allocations', wp_slash( $preview['allocations'] ) );
				update_post_meta( $id, '_match_baseline_allocations', wp_slash( $preview['allocation_baseline'] ) );
				if ( get_post_meta( $id, '_match_full_allocations', true ) !== $preview['allocations'] || get_post_meta( $id, '_match_baseline_allocations', true ) !== $preview['allocation_baseline'] ) {
					return self::error( 'De verdeling van deelnames kon niet worden opgeslagen.', 500 );
				}
				update_post_meta( $id, '_match_full_rows', wp_slash( $preview['rows'] ) );
				update_post_meta( $id, '_match_baseline_rows', wp_slash( $baseline ) );
				if ( $baseline ) {
					$preview['rows'] = self::delta_rows( $preview['rows'], $baseline ); }
				update_post_meta( $id, '_match_sources', $preview['sources'] );
				if ( get_post_meta( $id, '_match_sources', true ) !== $preview['sources'] || get_post_meta( $id, '_match_baseline_rows', true ) !== $baseline || ! is_array( get_post_meta( $id, '_match_full_rows', true ) ) ) {
					return self::error( 'De maandmomentopname kon niet volledig worden opgeslagen.', 500 ); }
				return self::save(
				$id,
				[
					'team_id'            => $team,
					'season'             => '2026-2027',
					'month'              => $month,
					'scheme'             => $preview['scheme'],
					'phase'              => 'closed',
					'version'            => $version,
					'closed_at'          => current_datetime()->format( DATE_ATOM ),
					'bank_code'          => $preview['bank_code'],
					'rows'               => $preview['rows'],
					'schema_version'     => '2',
					'agreement_snapshot' => $preview['agreement_snapshot'],
					'payroll_config'     => $preview['payroll_config'],
				],
				$request_id
				);
			}
			);
	}

	public static function batch_action( int $id, string $action, array $input ) {
		$batch = self::record( $id );
		if ( ! self::finance( true ) || get_post_type( $id ) !== self::BATCH || ! $batch || ! self::rule( (int) $batch['team_id'] ) ) {
			return self::error( 'Geen toegang tot deze maand.', 403 );
		}
		return self::operation(
			$input,
			$action . ':' . $id,
			static function ( $request_id ) use ( $id, $action, $input ) {
				$batch  = self::record( $id );
				$modern = ( $batch['schema_version'] ?? '' ) === '2' && $batch['scheme'] === 'awc1';
				$route  = $modern ? ( $input['route'] ?? '' ) : '';
				if ( $modern && ! in_array( $action, [ 'corrections', 'route-corrections' ], true ) && ! in_array( $route, [ 'payroll', 'sepa_guest' ], true ) ) {
					return self::error( 'Kies loonadministratie of directe uitbetaling.' );
				}
				$export_key     = '_match_export' . ( $modern ? '_' . $route : '' );
				$processing_key = '_match_processing' . ( $modern ? '_' . $route : '' );
				if ( $action === 'route-corrections' ) {
					return self::reverse_person( $batch, $input, $request_id );
				}
				if ( $action === 'corrections' ) {
					if ( $batch['last_request_id'] === $request_id ) {
						return $batch; }
					if ( $modern ) {
						foreach ( self::route_summary( $batch ) as $status ) {
							if ( $status['applicable'] && ! $status['processing'] && ( $input['previous_file_unused'] ?? false ) !== true ) {
								return self::error( 'Bevestig dat alle onverwerkte bestanden niet zijn gebruikt en niet meer worden gebruikt.', 409 );
							}
						}
					}
					if ( $batch['phase'] !== 'closed' || empty( $input['reason'] ) || ( ! $modern && ! get_post_meta( $id, $processing_key, true ) && ( $input['previous_file_unused'] ?? false ) !== true ) ) {
						return self::error( 'Geef een reden; bevestig bij een onverwerkte maand dat het oude bestand niet wordt gebruikt.', 409 );
					}
					update_post_meta( $id, '_match_correction_reason', sanitize_text_field( $input['reason'] ) );
					unset( $batch['id'], $batch['last_request_id'] );
					$batch['phase'] = 'superseded';
					return self::save( $id, $batch, $request_id );
				}
				if ( $action === 'exports' && get_post_meta( $id, $export_key, true ) ) {
					return get_post_meta( $id, $export_key, true );
				}
				if ( $batch['phase'] !== 'closed' ) {
					return self::error( 'Deze maand is vervangen.', 409 );
				}
				if ( $modern ) {
					$batch['rows'] = array_values( array_filter( $batch['rows'], static fn( $row ) => $row['payment_route'] === $route ) );
					if ( ! $batch['rows'] ) {
						return self::error( 'Deze uitvoerroute is niet van toepassing.' );
					}
				}
				if ( $action === 'processing' ) {
					if ( empty( $input['reference'] ) || ! in_array( $input['kind'] ?? '', [ 'nmbrs', 'paid', 'manual_correction' ], true ) || empty( $input['date'] ) ) {
						return self::error( 'Vul datum, soort verwerking en referentie in.' );
					}
					$existing_event = get_post_meta( $id, $processing_key, true );
					if ( $existing_event ) {
						return ( $existing_event['request_id'] ?? '' ) === $request_id ? $existing_event : self::error( 'Verwerking is al vastgelegd.', 409 ); }
					$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $input['date'], wp_timezone() );
					if ( ! $date || $date->format( 'Y-m-d' ) !== $input['date'] || $input['date'] > current_time( 'Y-m-d' ) ) {
						return self::error( 'Gebruik een geldige verwerkingsdatum tot en met vandaag.' ); }
					$negative      = count( array_filter( $batch['rows'], static fn( $row ) => $row['amount_cents'] < 0 ) ) > 0;
					$expected_kind = ( $modern ? $route === 'payroll' : $batch['scheme'] === 'awc1' ) ? 'nmbrs' : ( $negative ? 'manual_correction' : 'paid' );
					if ( $input['kind'] !== $expected_kind ) {
						return self::error( 'Gebruik de verwerkingssoort die bij deze maand hoort: ' . $expected_kind ); }

					$event = [
						'kind'       => $input['kind'],
						'date'       => sanitize_text_field( $input['date'] ),
						'reference'  => sanitize_text_field( $input['reference'] ),
						'actor'      => get_current_user_id(),
						'request_id' => $request_id,
					];
					update_post_meta( $id, $processing_key, wp_slash( $event ) );
					return get_post_meta( $id, $processing_key, true ) === $event ? $event : self::error( 'De verwerking kon niet worden vastgelegd. Probeer opnieuw.', 500 );
				}
				$existing = get_post_meta( $id, $export_key, true );
				if ( $existing ) {
					return $existing;
				}
				if ( ( $input['confirmed'] ?? false ) !== true || get_post_meta( $id, $processing_key, true ) ) {
					return self::error( 'Bevestig dat deze maand nog niet verwerkt of klaargezet is.', 409 );
				}
				if ( $modern && $route === 'payroll' ) {
					if ( ! self::config()['payroll_import_confirmed'] || ( get_post_meta( $id, '_match_baseline_rows', true ) && ! self::config()['payroll_corrections_confirmed'] ) ) {
						return self::error( 'Bevestig eerst de proefimport en zo nodig de correctiewerkwijze bij Instellingen.' );
					}
					$export = CompensationPayroll::workbook( $batch, json_decode( $batch['payroll_config'], true ) );
					if ( is_wp_error( $export ) ) {
						return $export;
					}
				} elseif ( ! $modern && $batch['scheme'] === 'awc1' ) {
					$rows = [ [ 'Naam', 'Naam in Nmbrs', 'Dagen', 'L3090 winst', 'L3091 gelijkspel', 'U2150 basis', $batch['bank_code'] ?: 'Bankvergoeding (code nog niet ingesteld)' ] ];
					foreach ( $batch['rows'] as $row ) {
						$rows[] = [ $row['player_name'], $row['nmbrs_name'], $row['days'], $row['wins'], $row['draws'], $row['basis'], $row['bank'] ];
					}
					$csv = fopen( 'php://temp', 'w+' );
					foreach ( $rows as $row ) {
						$row = array_map( static fn( $value ) => is_string( $value ) && preg_match( '/^[=+@\-\t\r]/', $value ) ? "'" . $value : $value, $row );
						fputcsv( $csv, $row, ';', '"', '' );
					}
					rewind( $csv );
					$export = [
						'content'  => stream_get_contents( $csv ),
						'filename' => 'Nmbrs-' . $batch['month'] . '-' . $id . '.csv',
						'mime'     => 'text/csv',
					];
					fclose( $csv );
				} else {
					if ( array_filter( $batch['rows'], static fn( $row ) => $row['amount_cents'] < 0 ) ) {
						return self::error( 'Deze correctie bevat te veel betaalde premies. Handel deze maandcorrectie handmatig af.' ); }
					$transactions = [];
					$export_id    = 'RM' . str_replace( '-', '', wp_generate_uuid4() );
					$payment      = [];
					foreach ( $batch['rows'] as $row ) {
						if ( (int) $row['amount_cents'] <= 0 ) {
							continue;
						}
						$payment = SepaCreditTransfer::validate_payment(
						[
							'creditor_iban'  => $row['iban'],
							'creditor_name'  => $row['bank_account_holder'],
							'debtor_iban'    => (string) ( $input['debtor_iban'] ?? '' ),
							'debtor_name'    => (string) ( $input['debtor_name'] ?? '' ),
							'execution_date' => (string) ( $input['execution_date'] ?? '' ),
						]
						);
						if ( is_wp_error( $payment ) ) {
							return $payment;
						}
						$transactions[] = [
							'amount_cents'  => (int) $row['amount_cents'],
							'creditor_iban' => $payment['creditor_iban'],
							'creditor_name' => $payment['creditor_name'],
							'end_to_end_id' => 'RM' . substr( hash( 'sha256', $export_id . ':' . $row['person_id'] ), 0, 32 ),
							'description'   => 'Vergoeding ' . get_the_title( $batch['team_id'] ) . ' ' . CompensationPayroll::month_name( $batch['month'] ) . ' ' . substr( $batch['month'], 0, 4 ),
						];
					}
					if ( ! $transactions ) {
						return self::error( 'Deze maand heeft geen uit te betalen premies.' );
					}
					$xml    = SepaCreditTransfer::xml(
					[
						'export_id'    => $export_id,
						'created_at'   => current_datetime()->format( DATE_ATOM ),
						'payment'      => $payment,
						'transactions' => $transactions,
					]
					);
					$export = [
						'content'  => $xml,
						'filename' => 'Premies-' . $batch['month'] . '-' . $export_id . '.xml',
						'mime'     => 'application/xml',
					];
				}
				$export['sha256']     = hash( 'sha256', $export['content'] );
				$export['route']      = $route;
				$export['created_at'] = current_datetime()->format( DATE_ATOM );
				$export['created_by'] = get_current_user_id();
				if ( ! update_post_meta( $id, $export_key, wp_slash( $export ) ) ) {
					return self::error( 'Het betaalbestand kon niet worden opgeslagen.', 500 );
				}
				return $export;
			}
			);
	}
	/** Delta against the last externally processed full snapshot, never a future month. */
	public static function delta_rows( array $current, array $baseline ): array {
		$new = [];
		$old = [];
		foreach ( $current as $row ) {
			$new[ CompensationPayroll::key( $row ) ] = $row; }
		foreach ( $baseline as $row ) {
			$old[ CompensationPayroll::key( $row ) ] = $row; }
		$rows = [];
		foreach ( array_unique( array_merge( array_keys( $new ), array_keys( $old ) ) ) as $id ) {
			$row     = $new[ $id ] ?? $old[ $id ];
			$changed = false;
			foreach ( [ 'days', 'basis', 'bank', 'wins', 'draws', 'amount_cents' ] as $key ) {
				$row[ $key ] = (int) ( $new[ $id ][ $key ] ?? 0 ) - (int) ( $old[ $id ][ $key ] ?? 0 );
				$changed     = $changed || $row[ $key ] !== 0;
			}
			if ( $changed ) {
				$rows[] = $row; }
		}
		return $rows;
	}

	/** Validate a prepared source-to-person mapping without writes or implicit person creation. */
	public static function import( array $input, bool $commit = false ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return self::error( 'Alleen een beheerder mag importeren.', 403 ); }
		$entries = $input['registrations'] ?? null;
		if ( ! is_array( $entries ) || ! $entries || count( $entries ) > 100 ) {
			return self::error( 'Een import bevat 1 tot 100 gekoppelde wedstrijden.' ); }
		$hash = hash( 'sha256', wp_json_encode( $entries ) );
		if ( $commit && ( ( $input['confirmed'] ?? false ) !== true || ( $input['preview_hash'] ?? '' ) !== $hash || empty( $input['backup_reference'] ) ) ) {
			return self::error( 'Bevestig de voorvertoning en vul de referentie van de bronkopie en backup in.', 409 ); }
		$report   = [];
		$prepared = [];
		$errors   = [];
		$seen     = [];
		foreach ( $entries as $index => $entry ) {
			if ( ! is_array( $entry ) ) {
				return self::error( 'Ongeldige wedstrijdregel.' ); }
			$key = (string) ( $entry['team_id'] ?? '' ) . ':' . (string) ( $entry['fields']['source_match_id'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				return self::error( 'Dezelfde wedstrijd komt meerdere keren voor in het importbestand.' ); }
			$seen[ $key ]        = true;
			$entry['request_id'] = 'import-' . substr( hash( 'sha256', wp_json_encode( $entry ) ), 0, 64 );
			$result              = self::registration( 0, $entry, true );
			if ( is_wp_error( $result ) ) {
				$errors[] = 'Wedstrijd ' . ( $index + 1 ) . ': ' . $result->get_error_message();
				continue; }
			$prepared[] = $entry;
			$report[]   = [
				'team'        => get_the_title( (int) $entry['team_id'] ),
				'date'        => $result['played_on'],
				'opponent'    => $result['opponent_name'],
				'players'     => count( $result['selection'] ),
				'existing_id' => $result['id'] ?? null,
			];
		}
		if ( $commit && $errors ) {
			return self::error( implode( ' ', $errors ), 409 ); }
		if ( $commit ) {
			$ids = [];
			foreach ( $prepared as $entry ) {
				$result = self::registration( 0, $entry );
				if ( is_wp_error( $result ) ) {
					return self::error( 'Import onderbroken: ' . $result->get_error_message() . ' Reeds opgeslagen wedstrijden blijven bewaard; hetzelfde bestand kan veilig opnieuw worden aangeboden.', 409 ); }
				$ids[] = $result['id'];
				update_post_meta(
					$result['id'],
					'_match_import',
					[
						'hash'             => $hash,
						'backup_reference' => sanitize_text_field( $input['backup_reference'] ),
						'actor'            => get_current_user_id(),
					]
					);
			}
			return [
				'imported_ids' => $ids,
				'preview_hash' => $hash,
			];
		}
		return [
			'matches'      => $report,
			'errors'       => $errors,
			'preview_hash' => $hash,
		];
	}

	public static function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'rondo_match_compensation', $message, [ 'status' => $status ] );
	}
}
