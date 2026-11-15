<?php
/** Explicit cross-survey measurements; questionnaire revisions are never pooled. */
namespace Rondo\Surveys;

final class RoundComparison {
	const TYPE = 'rondo_survey_round';

	/** Small immutable questionnaire catalog without answers or invitations. */
	public static function catalog(): array {
		$out = [];
		foreach ( get_posts(
			[
				'post_type'        => Surveys::TYPE,
				'post_status'      => 'private',
				'numberposts'      => -1,
				'suppress_filters' => true,
			]
			) as $post ) {
			$survey = Surveys::read( $post->ID );
			if ( is_wp_error( $survey ) ) {
				continue; }
			$versions = [];
			foreach ( Surveys::children( $post->ID, Surveys::VERSION ) as $version ) {
				$publication = Surveys::read( $version->ID, Surveys::VERSION );
				if ( is_wp_error( $publication ) ) {
					continue; }
				$versions[] = [
					'id'        => $version->ID,
					'date'      => $version->post_date_gmt,
					'questions' => array_values( Analysis::questions( $publication['document'] ) ),
				];
			}
			if ( $versions ) {
				$out[] = [
					'id'       => $post->ID,
					'title'    => $survey['title'],
					'versions' => $versions,
				]; }
		}
		return $out;
	}

	public static function save( int $survey, array $input ) {
		return Surveys::locked(
			'survey:' . $survey,
			static function () use ( $survey, $input ) {
				$data = Analysis::select( $survey, [ 'version' => $input['version'] ?? 0 ] );
				if ( is_wp_error( $data ) ) {
					return $data; }
				$id  = (int) ( $input['id'] ?? 0 );
				$old = $id ? Followup::record( $survey, $data['version'], $id, self::TYPE ) : null;
				if ( is_wp_error( $old ) ) {
					return $old; }
				if ( $old && ( $input['revision'] ?? null ) !== $old['revision'] ) {
					return Schema::error( 'De meetdefinitie is gewijzigd. Vernieuw de vergelijkingen.', 409 ); }
				foreach ( [
					'title'      => 200,
					'definition' => 2000,
				] as $key => $max ) {
					if ( ! AnalysisSchema::text( $input[ $key ] ?? null, $max ) || trim( $input[ $key ] ) === '' ) {
						return Schema::error( 'Geef de vergelijking een naam en gedeelde meetdefinitie.' ); }
				}
				$rounds = $input['rounds'] ?? null;
				if ( ! is_array( $rounds ) || array_values( $rounds ) !== $rounds || count( $rounds ) < 2 || count( $rounds ) > 10 ) {
					return Schema::error( 'Koppel twee tot tien afzonderlijke enquêterondes.' ); }
				$seen   = [];
				$clean  = [];
				$anchor = false;
				foreach ( $rounds as $round ) {
					if ( ! is_array( $round ) || ! is_int( $round['survey_id'] ?? null ) || ! is_int( $round['version'] ?? null ) || isset( $seen[ $round['survey_id'] ] ) ) {
						return Schema::error( 'Elke ronde moet een andere enquête zijn; publicatieversies worden niet samengevoegd.' ); }
					$seen[ $round['survey_id'] ] = true;
					$selection                   = Analysis::select( $round['survey_id'], [ 'version' => $round['version'] ] );
					if ( is_wp_error( $selection ) ) {
						return $selection; }
					$question = $round['question'] ?? '';
					if ( ! is_string( $question ) ) {
						return Schema::error( 'Koppel een bestaande vraag.' ); }
					$profile = AnalysisSchema::profile(
					[
						'title'   => $input['title'],
						'metrics' => [
							[
								'id'       => 'measure',
								'label'    => $input['title'],
								'question' => $question,
								'options'  => $round['options'] ?? [],
								'exclude'  => $round['exclude'] ?? [],
							],
						],
					]
					);
					if ( is_wp_error( $profile ) ) {
						return $profile; }
					$metric = Analysis::metric( $selection, $profile['metrics'][0] );
					if ( $metric['error'] ) {
						return Schema::error( 'Koppel per ronde een keuzevraag en geldige antwoordopties.' ); }
					if ( ! Followup::date( $round['start'] ?? null ) || ! Followup::date( $round['end'] ?? null ) || $round['end'] < $round['start'] || ! AnalysisSchema::text( $round['audience'] ?? null, 500 ) || trim( $round['audience'] ) === '' || ! in_array( $round['compatibility'] ?? '', [ 'comparable', 'limited', 'incompatible' ], true ) || ! AnalysisSchema::text( $round['note'] ?? '', 1000 ) ) {
						return Schema::error( 'Geef per ronde een geldige periode, doelgroep en vergelijkbaarheidsbeoordeling.' ); }
					if ( $round['compatibility'] !== 'comparable' && trim( $round['note'] ?? '' ) === '' ) {
						return Schema::error( 'Licht beperkte of ontbrekende vergelijkbaarheid toe.' ); }
					$anchor  = $anchor || ( $round['survey_id'] === $survey && $round['version'] === $data['version'] );
					$clean[] = [
						'survey_id'     => $round['survey_id'],
						'version'       => $round['version'],
						'question'      => $question,
						'options'       => $profile['metrics'][0]['options'],
						'exclude'       => $profile['metrics'][0]['exclude'],
						'start'         => $round['start'],
						'end'           => $round['end'],
						'audience'      => sanitize_text_field( $round['audience'] ),
						'compatibility' => $round['compatibility'],
						'note'          => sanitize_textarea_field( $round['note'] ?? '' ),
					];
				}
				if ( ! $anchor ) {
					return Schema::error( 'Neem de huidige enquêteversie op in de vergelijking.' ); }
				$row   = [
					'title'      => sanitize_text_field( $input['title'] ),
					'definition' => sanitize_textarea_field( $input['definition'] ),
					'version'    => $data['version'],
					'rounds'     => $clean,
					'revision'   => wp_generate_uuid4(),
					'author'     => get_current_user_id(),
					'updated_at' => current_datetime()->format( DATE_ATOM ),
				];
				$saved = Surveys::write( self::TYPE, $row, $id, $survey );
				return is_wp_error( $saved ) ? $saved : [ 'id' => $saved ];
			}
			);
	}

	private static function labels( array $q, ?array $ids = null ): array {
		$labels = [];
		foreach ( $q['options'] as $option ) {
			if ( $ids === null || in_array( $option['id'], $ids, true ) ) {
				$labels[] = mb_strtolower( trim( $option['label'] ) ); }
		}
		// Scale ordering matters. For ordinary choice questions only membership matters.
		if ( $q['type'] !== 'scale' || $ids !== null ) {
			sort( $labels ); }
		return $labels;
	}

	public static function calculate( array $row ): array {
		$results  = [];
		$baseline = null;
		foreach ( $row['rounds'] as $index => $round ) {
			$data = Analysis::select( $round['survey_id'], [ 'version' => $round['version'] ] );
			if ( is_wp_error( $data ) ) {
				$results[] = $round + [
					'available' => false,
					'error'     => 'Bronenquête of vragenlijstversie is niet meer beschikbaar.',
					'delta'     => null,
				];
				continue;
			}
			$q      = $data['questions'][ $round['question'] ] ?? null;
			$metric = Analysis::metric(
				$data,
				[
					'id'       => 'measure',
					'label'    => $row['title'],
					'question' => $round['question'],
					'options'  => $round['options'],
					'exclude'  => $round['exclude'],
				]
				);
			if ( ! $q || $metric['error'] ) {
				$results[] = $round + [
					'available' => false,
					'error'     => 'De vraag- of optiekoppeling is niet meer geldig.',
					'delta'     => null,
				];
				continue;
			}
			$reasons = [];
			$level   = $round['compatibility'];
			if ( $round['note'] !== '' ) {
				$reasons[] = $round['note']; }
			if ( $index === 0 ) {
				$baseline = [
					'round'    => $round,
					'question' => $q,
					'metric'   => $metric,
				]; }
			if ( $index > 0 && $baseline ) {
				$bq = $baseline['question'];
				$br = $baseline['round'];
				if ( $q['type'] !== $bq['type'] || count( $q['options'] ) !== count( $bq['options'] ) ) {
					$level     = 'incompatible';
					$reasons[] = 'Vraagtype of omvang van de antwoordschaal verschilt.';
				} elseif ( self::labels( $q ) !== self::labels( $bq ) || self::labels( $q, $round['options'] ) !== self::labels( $bq, $br['options'] ) || self::labels( $q, $round['exclude'] ) !== self::labels( $bq, $br['exclude'] ) ) {
					if ( $level === 'comparable' ) {
						$level = 'limited';
					} $reasons[] = 'Antwoordopties, schaalvolgorde of tel-/uitsluitgroepen verschillen.';
				}
				if ( mb_strtolower( $round['audience'] ) !== mb_strtolower( $br['audience'] ) ) {
					if ( $level === 'comparable' ) {
						$level = 'limited';
					} $reasons[] = 'De beschreven doelgroepen verschillen.';
				}
				if ( $q['label'] !== $bq['label'] ) {
					$reasons[] = 'Vraagtekst gewijzigd; beoordeel de expliciete mapping.';
					if ( $level === 'comparable' ) {
						$level = 'limited'; }
				}
				if ( $br['compatibility'] !== 'comparable' ) {
					$level     = $br['compatibility'] === 'incompatible' ? 'incompatible' : ( $level === 'comparable' ? 'limited' : $level );
					$reasons[] = 'De basisronde is niet volledig vergelijkbaar verklaard.'; }
			} elseif ( $index > 0 ) {
				$level     = 'incompatible';
				$reasons[] = 'De basisronde is niet beschikbaar.'; }
			$delta = null;
			if ( $index > 0 && $baseline && $level === 'comparable' && $metric['denominator'] && $baseline['metric']['denominator'] ) {
				$delta = round( 100 * ( $metric['count'] / $metric['denominator'] - $baseline['metric']['count'] / $baseline['metric']['denominator'] ), 1 );
			}
			$survey    = Surveys::read( $round['survey_id'] );
			$results[] = $round + [
				'available'      => true,
				'survey_title'   => $survey['title'],
				'question_label' => $q['label'],
				'options_labels' => self::labels( $q, $round['options'] ),
				'exclude_labels' => self::labels( $q, $round['exclude'] ),
				'total'          => count( $data['rows'] ),
				'count'          => $metric['count'],
				'denominator'    => $metric['denominator'],
				'rate'           => $metric['rate'],
				'small'          => $metric['denominator'] < 10,
				'assessment'     => $level,
				'reasons'        => $reasons,
				'delta'          => $delta,
			];
		}
		$row['results'] = $results;
		return $row;
	}

	public static function listing( int $survey, int $version ) {
		$data = Analysis::select( $survey, [ 'version' => $version ] );
		if ( is_wp_error( $data ) ) {
			return $data; }
		$out = [];
		foreach ( Surveys::children( $survey, self::TYPE ) as $post ) {
			$row = Surveys::read( $post->ID, self::TYPE );
			if ( ! is_wp_error( $row ) && $row['version'] === $version ) {
				$out[] = self::calculate( $row ); }
		}
		return $out;
	}
}
