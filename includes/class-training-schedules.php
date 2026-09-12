<?php
/**
 * Named weekly training schedules and shared planning settings.
 *
 * @package Rondo\Training
 */

namespace Rondo\Training;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schedules {
	public const SETTINGS = 'rondo_training_settings';
	public const ACTIVE   = 'rondo_training_active';

	public static function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'rondo_training_invalid', $message, [ 'status' => $status ] );
	}

	public static function settings(): array {
		return array_merge(
			[
				'revision'   => 0,
				'pitches'    => [],
				'age_groups' => [],
				'teams'      => [],
			],
			get_option( self::SETTINGS, [] )
			);
	}

	/** Private CPTs are exposed exclusively through the feature-gated controller. */
	public static function posts(): array {
		return get_posts(
			[
				'post_type'      => 'rondo_training',
				'post_status'    => 'private',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
			);
	}

	public static function exists( int $id ): bool {
		return get_post_type( $id ) === 'rondo_training' && get_post_status( $id ) === 'private';
	}

	public static function schedule( int $id ) {
		if ( ! self::exists( $id ) ) {
			return self::error( 'Trainingsschema niet gevonden.', 404 );
		}
		$fields = Fields::all_for_post( $id );
		$blocks = $fields['blocks'] ?: [];
		foreach ( $blocks as &$block ) {
			// Native time storage includes seconds; the API consistently returns HH:mm.
			$block['start']      = substr( $block['start'], 0, 5 );
			$block['team_ids']   = array_map( 'intval', $block['team_ids'] ?: [] );
			$block['team_names'] = array_map( static fn( $team_id ) => html_entity_decode( get_the_title( $team_id ), ENT_QUOTES, 'UTF-8' ), $block['team_ids'] );
		}
		unset( $block );
		return [
			'id'       => $id,
			'name'     => html_entity_decode( get_post( $id )->post_title, ENT_QUOTES, 'UTF-8' ),
			'season'   => $fields['season'] ?: '',
			'revision' => (int) $fields['revision'],
			'blocks'   => $blocks,
		];
	}

	public static function feed(): array {
		return [
			'active_id' => (int) get_option( self::ACTIVE, 0 ),
			'timezone'  => wp_timezone_string(),
			'pitches'   => self::settings()['pitches'],
			'schedules' => array_map( static fn( $post ) => self::schedule( $post->ID ), self::posts() ),
		];
	}

	public static function team_directory(): array {
		return array_map(
			static fn( $post ) => [
				'id'   => $post->ID,
				'name' => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			],
			get_posts(
				[
					'post_type'      => 'team',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'orderby'        => 'title',
					'order'          => 'ASC',
				]
				)
		);
	}

	private static function valid_text( $value, int $max = 100 ): bool {
		return is_string( $value ) && trim( sanitize_text_field( $value ) ) !== '' && mb_strlen( $value ) <= $max;
	}

	private static function valid_id( $value ): bool {
		return is_string( $value ) && preg_match( '/^[a-zA-Z0-9_-]{1,64}$/D', $value ) === 1;
	}

	private static function duration_valid( $value ): bool {
		return is_int( $value ) && $value >= 15 && $value <= 360 && $value % 15 === 0;
	}

	private static function size_valid( $value ): bool {
		return in_array( $value, [ 1, 2, 4 ], true );
	}

	/** Validate the whole settings document before changing any persisted setting. */
	public static function save_settings( array $data ) {
		$current = self::settings();
		if ( ( $data['revision'] ?? null ) !== $current['revision'] ) {
			return self::error( 'De instellingen zijn intussen gewijzigd. Vernieuw de pagina.', 409 );
		}
		if ( array_diff( array_keys( $data ), [ 'revision', 'pitches', 'age_groups', 'teams' ] ) ) {
			return self::error( 'Onbekende trainingsinstelling.' );
		}
		foreach ( [ 'pitches', 'age_groups', 'teams' ] as $key ) {
			if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) || ! ( array_values( $data[ $key ] ) === $data[ $key ] ) || count( $data[ $key ] ) > 500 ) {
				return self::error( 'Ongeldige lijst: ' . $key );
			}
		}
		$seen = [];
		foreach ( $data['pitches'] as &$pitch ) {
			if ( ! is_array( $pitch ) || array_diff( array_keys( $pitch ), [ 'id', 'name' ] ) || ! self::valid_id( $pitch['id'] ?? null ) || ! self::valid_text( $pitch['name'] ?? null ) || isset( $seen[ $pitch['id'] ] ) ) {
				return self::error( 'Geef elk veld een unieke identifier en een naam.' );
			}
			$seen[ $pitch['id'] ] = true;
			$pitch['name']        = sanitize_text_field( $pitch['name'] );
		}
		unset( $pitch );
		$pitch_ids = array_column( $data['pitches'], 'id' );
		$seen      = [];
		foreach ( $data['age_groups'] as &$group ) {
			if ( ! is_array( $group ) || array_diff( array_keys( $group ), [ 'id', 'name', 'duration', 'size' ] ) || ! self::valid_id( $group['id'] ?? null ) || ! self::valid_text( $group['name'] ?? null ) || isset( $seen[ $group['id'] ] ) || ! self::duration_valid( $group['duration'] ?? null ) || ! self::size_valid( $group['size'] ?? null ) ) {
				return self::error( 'Controleer de leeftijdslagen: unieke naam/identifier, duur van 15–360 minuten in kwartieren en een kwart, half of heel veld.' );
			}
			$seen[ $group['id'] ] = true;
			$group['name']        = sanitize_text_field( $group['name'] );
		}
		unset( $group );
		$group_ids = array_column( $data['age_groups'], 'id' );
		$seen      = [];
		foreach ( $data['teams'] as $team ) {
			if ( ! is_array( $team ) || array_diff( array_keys( $team ), [ 'team_id', 'age_group_id', 'duration', 'size' ] ) || ! is_int( $team['team_id'] ?? null ) || isset( $seen[ $team['team_id'] ] ) || get_post_type( $team['team_id'] ) !== 'team' || get_post_status( $team['team_id'] ) !== 'publish' || ! in_array( $team['age_group_id'] ?? null, array_merge( [ '' ], $group_ids ), true ) || ( isset( $team['duration'] ) && ! self::duration_valid( $team['duration'] ) ) || ( isset( $team['size'] ) && ! self::size_valid( $team['size'] ) ) ) {
				return self::error( 'Controleer de leeftijdslaag en afwijkende waarden per team.' );
			}
			$seen[ $team['team_id'] ] = true;
		}
		foreach ( self::posts() as $post ) {
			foreach ( Fields::get_for_post( $post->ID, 'blocks' ) ?: [] as $block ) {
				if ( ! in_array( $block['pitch_id'], $pitch_ids, true ) ) {
					return self::error( 'Dit veld wordt nog gebruikt in schema “' . get_the_title( $post ) . '”. Verplaats of verwijder eerst die blokken.', 409 );
				}
			}
		}
		++$data['revision'];
		update_option( self::SETTINGS, $data, false );
		return $data;
	}

	/** Times are recurring local wall-clock times, not dated appointments. */
	public static function minutes( string $time ): int {
		return (int) substr( $time, 0, 2 ) * 60 + (int) substr( $time, 3, 2 );
	}

	public static function validate_blocks( $blocks ) {
		if ( ! is_array( $blocks ) || ! ( array_values( $blocks ) === $blocks ) || count( $blocks ) > 1000 ) {
			return self::error( 'Een schema mag maximaal 1000 trainingsblokken bevatten.' );
		}
		$pitch_ids = array_column( self::settings()['pitches'], 'id' );
		$seen      = [];
		$validated = [];
		foreach ( $blocks as $index => $block ) {
			$prefix = 'Blok ' . ( $index + 1 ) . ': ';
			if ( ! is_array( $block ) || array_diff( array_keys( $block ), [ 'block_id', 'label', 'team_ids', 'pitch_id', 'day', 'start', 'duration', 'size', 'offset' ] ) ) {
				return self::error( $prefix . 'onbekende velden.' );
			}
			if ( ! self::valid_id( $block['block_id'] ?? null ) || isset( $seen[ $block['block_id'] ] ) || ! is_string( $block['label'] ?? null ) || mb_strlen( $block['label'] ) > 100 ) {
				return self::error( $prefix . 'ongeldige identifier of omschrijving.' );
			}
			$seen[ $block['block_id'] ] = true;
			if ( ! is_array( $block['team_ids'] ?? null ) || ! ( array_values( $block['team_ids'] ) === $block['team_ids'] ) || count( $block['team_ids'] ) > 100 || count( array_unique( $block['team_ids'], SORT_REGULAR ) ) !== count( $block['team_ids'] ) ) {
				return self::error( $prefix . 'ongeldige teams.' );
			}
			foreach ( $block['team_ids'] as $team_id ) {
				if ( ! is_int( $team_id ) || get_post_type( $team_id ) !== 'team' || get_post_status( $team_id ) !== 'publish' ) {
					return self::error( $prefix . 'team bestaat niet of is niet actief.' );
				}
			}
			$block['label'] = sanitize_text_field( $block['label'] );
			if ( $block['label'] === '' && ! $block['team_ids'] ) {
				return self::error( $prefix . 'kies een team of vul een omschrijving in.' );
			}
			if ( ! in_array( $block['pitch_id'] ?? null, $pitch_ids, true ) || ! is_int( $block['day'] ?? null ) || $block['day'] < 1 || $block['day'] > 7 ) {
				return self::error( $prefix . 'kies een veld en een weekdag.' );
			}
			if ( ! is_string( $block['start'] ?? null ) || preg_match( '/^(?:[01]\d|2[0-3]):(?:00|15|30|45)$/D', $block['start'] ) !== 1 || ! self::duration_valid( $block['duration'] ?? null ) || self::minutes( $block['start'] ) + $block['duration'] > 1440 ) {
				return self::error( $prefix . 'kies kwartieren en een duur van 15–360 minuten, binnen dezelfde dag.' );
			}
			if ( ! self::size_valid( $block['size'] ?? null ) || ! is_int( $block['offset'] ?? null ) || $block['offset'] < 0 || $block['offset'] + $block['size'] > 4 || $block['offset'] % $block['size'] !== 0 ) {
				return self::error( $prefix . 'kies een kwart (A–D), half (AB/CD) of heel veld.' );
			}
			foreach ( $validated as $other ) {
				if ( $other['day'] !== $block['day'] || self::minutes( $other['start'] ) >= self::minutes( $block['start'] ) + $block['duration'] || self::minutes( $block['start'] ) >= self::minutes( $other['start'] ) + $other['duration'] ) {
					continue;
				}
				if ( array_intersect( $other['team_ids'], $block['team_ids'] ) || ( $other['pitch_id'] === $block['pitch_id'] && $other['offset'] < $block['offset'] + $block['size'] && $block['offset'] < $other['offset'] + $other['size'] ) ) {
					return new \WP_Error(
						'rondo_training_conflict',
						$prefix . 'velddeel of team is op dit tijdstip al ingepland.',
						[
							'status'    => 409,
							'block_ids' => [ $other['block_id'], $block['block_id'] ],
						]
						);
				}
			}
			$validated[] = $block;
		}
		return $validated;
	}

	/** Write one entire version so an invalid move never partially changes a schedule. */
	public static function save( int $id, array $data ) {
		if ( array_diff( array_keys( $data ), [ 'name', 'season', 'revision', 'blocks' ] ) || ! self::valid_text( $data['name'] ?? null ) || ! self::valid_text( $data['season'] ?? null, 30 ) ) {
			return self::error( 'Vul een schemanaam en seizoen in; stuur alleen de gedocumenteerde velden.' );
		}
		if ( $id && ! self::exists( $id ) ) {
			return self::error( 'Trainingsschema niet gevonden.', 404 );
		}
		$revision = $id ? (int) Fields::get_for_post( $id, 'revision' ) : 0;
		if ( ( $data['revision'] ?? null ) !== $revision ) {
			return self::error( 'Dit schema is intussen gewijzigd. Vernieuw de pagina voordat je opnieuw opslaat.', 409 );
		}
		$blocks = self::validate_blocks( $data['blocks'] ?? null );
		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}
		$post   = [ 'post_title' => sanitize_text_field( $data['name'] ) ];
		$result = $id ? wp_update_post( wp_slash( array_merge( $post, [ 'ID' => $id ] ) ), true ) : wp_insert_post(
			wp_slash(
			array_merge(
			$post,
			[
				'post_type'   => 'rondo_training',
				'post_status' => 'private',
				'post_author' => get_current_user_id(),
			]
			)
			),
			true
			);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$saved = Fields::update_many_for_post(
			$result,
			[
				'season'   => sanitize_text_field( $data['season'] ),
				'revision' => $revision + 1,
				'blocks'   => $blocks,
			]
			);
		return is_wp_error( $saved ) ? $saved : self::schedule( $result );
	}

	/** Serialize settings, activation and repeater writes using the native Options API. */
	public static function locked( callable $callback ) {
		$key   = 'rondo_training_write_lock';
		$token = wp_generate_uuid4();
		wp_cache_get( $key, 'options', true );
		$old = get_option( $key, [] );
		if ( is_array( $old ) && ! empty( $old['time'] ) && $old['time'] < time() - 300 ) {
			delete_option( $key );
		}
		if ( ! add_option(
			$key,
			[
				'token' => $token,
				'time'  => time(),
			],
			'',
			false
			) ) {
			return self::error( 'Een ander trainingsschema wordt nu opgeslagen. Probeer het opnieuw.', 409 );
		}
		try {
			return $callback();
		} finally {
			wp_cache_get( $key, 'options', true );
			if ( ( get_option( $key, [] )['token'] ?? '' ) === $token ) {
				delete_option( $key );
			}
		}
	}
}
