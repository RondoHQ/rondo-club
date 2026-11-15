<?php
/** Bounded analysis documents. Question mappings are explicit, never inferred from prose. */
namespace Rondo\Surveys;

final class AnalysisSchema {
	public static function blank(): array {
		return [
			'title'          => 'Algemene analyse',
			'metrics'        => [],
			'comparisons'    => [],
			'views'          => [],
			'themes'         => [],
			'context'        => '',
			'source_profile' => null,
		];
	}

	public static function templates(): array {
		$volunteers            = self::blank();
		$volunteers['title']   = 'Vrijwilligersbeleid';
		$volunteers['context'] = 'volunteers';
		foreach ( [
			'satisfaction'  => 'Tevreden',
			'clarity'       => 'Duidelijke verwachtingen',
			'participation' => 'Deelname',
		] as $id => $label ) {
			$volunteers['metrics'][] = [
				'id'       => $id,
				'label'    => $label,
				'question' => '',
				'options'  => [],
				'exclude'  => [],
			];
		}
		foreach ( [
			'role'          => 'Vergelijking naar rol',
			'participation' => 'Vergelijking naar deelname',
		] as $id => $label ) {
			$volunteers['comparisons'][] = [
				'id'      => $id,
				'label'   => $label,
				'outcome' => '',
				'group'   => '',
			];
		}
		return [
			[
				'id'      => 'blank',
				'version' => 1,
				'profile' => self::blank(),
			],
			[
				'id'      => 'volunteers',
				'version' => 1,
				'profile' => $volunteers,
			],
		];
	}

	public static function text( $value, int $max = 200 ): bool {
		return is_string( $value ) && mb_strlen( $value ) <= $max;
	}

	public static function ids( $values, int $max = 100 ): bool {
		return is_array( $values ) && array_values( $values ) === $values && count( $values ) <= $max
			&& count( array_filter( $values, static fn( $v ) => is_string( $v ) && preg_match( '/^[a-zA-Z0-9_-]{1,80}$/D', $v ) ) ) === count( $values );
	}

	/** Validate a selection against the selected immutable questionnaire version. */
	public static function filters( $filters, array $questions, bool $resolve = true ) {
		if ( ! is_array( $filters ) || count( $filters ) > 100 ) {
			return Schema::error( 'Ongeldige antwoordfilters.' );
		}
		$out = [];
		foreach ( $filters as $key => $options ) {
			if ( ! self::ids( [ $key ] ) || ! self::ids( $options ) || ! $options ) {
				return Schema::error( 'Selecteer minstens één antwoordoptie per filter.' );
			}
			$q = $questions[ $key ] ?? null;
			if ( $resolve && ( ! $q || $q['type'] === 'text' || array_diff( $options, array_column( $q['options'], 'id' ) ) ) ) {
				return Schema::error( 'Een filter verwijst naar een ontbrekende vraag of optie. Herstel de selectie.' );
			}
			$out[ $key ] = array_values( array_unique( $options ) );
		}
		return $out;
	}

	public static function profile( $input ) {
		if ( ! is_array( $input ) || strlen( (string) wp_json_encode( $input ) ) > 100000 || ! self::text( $input['title'] ?? null ) || trim( $input['title'] ) === '' || ! in_array( $input['context'] ?? '', [ '', 'volunteers' ], true ) ) {
			return Schema::error( 'Geef het analyseprofiel een titel en geldige context.' );
		}
		$out            = self::blank();
		$out['title']   = sanitize_text_field( $input['title'] );
		$out['context'] = $input['context'] ?? '';
		foreach ( [ 'metrics', 'comparisons', 'views', 'themes' ] as $kind ) {
			$rows = $input[ $kind ] ?? [];
			if ( ! is_array( $rows ) || array_values( $rows ) !== $rows || count( $rows ) > 40 ) {
				return Schema::error( 'Gebruik maximaal 40 items per profielonderdeel.' );
			}
			$seen = [];
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || ! self::ids( [ $row['id'] ?? null ] ) || isset( $seen[ $row['id'] ] ) || ! self::text( $row['label'] ?? null ) || trim( $row['label'] ) === '' ) {
					return Schema::error( 'Elk profielitem heeft een unieke sleutel en naam nodig.' );
				}
				$seen[ $row['id'] ] = true;
				$clean              = [
					'id'    => $row['id'],
					'label' => sanitize_text_field( $row['label'] ),
				];
				if ( $kind === 'metrics' || $kind === 'comparisons' ) {
					foreach ( $kind === 'metrics' ? [ 'question' ] : [ 'outcome', 'group' ] as $key ) {
						$value = $row[ $key ] ?? '';
						if ( $value !== '' && ! self::ids( [ $value ] ) ) {
							return Schema::error( 'Ongeldige vraagkoppeling.' );
						}
						$clean[ $key ] = $value;
					}
				}
				if ( $kind === 'metrics' ) {
					foreach ( [ 'options', 'exclude' ] as $key ) {
						if ( ! self::ids( $row[ $key ] ?? [] ) ) {
							return Schema::error( 'Ongeldige antwoordgroep.' );
						}
						$clean[ $key ] = array_values( array_unique( $row[ $key ] ?? [] ) );
					}
					if ( array_intersect( $clean['options'], $clean['exclude'] ) ) {
						return Schema::error( 'Een optie kan niet tegelijk meetellen en uitgesloten zijn.' );
					}
				}
				if ( $kind === 'views' ) {
					if ( isset( $row['unmapped'] ) && ! is_bool( $row['unmapped'] ) ) {
						return Schema::error( 'Ongeldige filterkoppeling.' ); }
					$clean['unmapped'] = $row['unmapped'] ?? false;
					$clean['filters']  = self::filters( $row['filters'] ?? [], [], false );
					if ( is_wp_error( $clean['filters'] ) ) {
						return $clean['filters'];
					}
				}
				if ( $kind === 'themes' ) {
					$words = $row['keywords'] ?? [];
					if ( ! is_array( $words ) || array_values( $words ) !== $words || count( $words ) > 30 || count( array_filter( $words, static fn( $v ) => self::text( $v, 80 ) && trim( $v ) !== '' ) ) !== count( $words ) ) {
						return Schema::error( 'Gebruik maximaal 30 trefwoorden per thema.' );
					}
					$clean['keywords'] = array_values( array_unique( array_map( 'sanitize_text_field', $words ) ) );
				}
				$out[ $kind ][] = $clean;
			}
		}
		return $out;
	}
}
