<?php
/** Shared survey selection, aggregation and private analysis persistence. */
namespace Rondo\Surveys;

use Rondo\Fields\Fields;

final class Analysis {
	const PROFILE     = 'rondo_survey_profile';
	const REPORT      = 'rondo_survey_report';
	const CALCULATION = 1;

	public static function json_field( int $id, string $field ): array {
		$value = json_decode( (string) Fields::get_for_post( $id, $field ), true );
		return is_array( $value ) ? $value : [];
	}

	public static function configuration( int $id, int $version ): array {
		$all = self::json_field( $id, 'analysis_profiles' );
		return $all[ $version ] ?? [
			'revision' => 'none',
			'profile'  => AnalysisSchema::blank(),
		];
	}

	public static function questions( array $document ): array {
		$out = [];
		foreach ( $document['sections'] as $section ) {
			foreach ( $section['questions'] as $q ) {
				$out[ $q['id'] ] = $q + [ 'section' => $section['title'] ];
			}
		}
		return $out;
	}

	/** The same selection feeds results, text pages, CSV and immutable reports. */
	public static function select( int $id, array $params = [] ) {
		$survey = Surveys::read( $id );
		if ( is_wp_error( $survey ) ) {
			return $survey;
		}
		$version     = (int) ( $params['version'] ?? $survey['current_version'] );
		$publication = Surveys::read( $version, Surveys::VERSION );
		if ( is_wp_error( $publication ) || (int) get_post_field( 'post_parent', $version ) !== $id ) {
			return Schema::error( 'Selecteer een gepubliceerde vragenlijstversie.' );
		}
		$questions = self::questions( $publication['document'] );
		$config    = self::configuration( $id, $version );
		$filters   = $params['filters'] ?? [];
		if ( ! empty( $params['view'] ) ) {
			$views = array_column( $config['profile']['views'], null, 'id' );
			if ( ! is_string( $params['view'] ) || ! isset( $views[ $params['view'] ] ) ) {
				return Schema::error( 'Deze opgeslagen selectie bestaat niet meer. Wis de selectie.' );
			}
			if ( ! empty( $views[ $params['view'] ]['unmapped'] ) ) {
				return Schema::error( 'Koppel deze opgeslagen selectie opnieuw bij Analyse instellen.' ); }
			$filters = $views[ $params['view'] ]['filters'];
		}
		if ( is_string( $filters ) ) {
			$filters = json_decode( $filters, true );
		}
		if ( ! empty( $params['question'] ) && isset( $params['value'] ) && $params['value'] !== '' ) {
			$filters = [ $params['question'] => [ $params['value'] ] ];
		}
		$filters = AnalysisSchema::filters( $filters, $questions );
		if ( is_wp_error( $filters ) ) {
			return $filters;
		}
		$selection = [ 'filters' => $filters ];
		foreach ( [ 'search', 'text_question', 'theme' ] as $key ) {
			if ( ! AnalysisSchema::text( $params[ $key ] ?? '' ) ) {
				return Schema::error( 'Ongeldige tekstselectie.' );
			}
			$selection[ $key ] = $params[ $key ] ?? '';
		}
		if ( $selection['text_question'] !== '' && ( $questions[ $selection['text_question'] ]['type'] ?? '' ) !== 'text' ) {
			return Schema::error( 'Selecteer een bestaande open vraag.' );
		}
		if ( $selection['theme'] !== '' && ! in_array( $selection['theme'], array_column( $config['profile']['themes'], 'id' ), true ) ) {
			return Schema::error( 'Selecteer een bestaand thema.' );
		}
		$rows    = [];
		$source  = [];
		$all_ids = [];
		$latest  = 0;
		$posts   = Surveys::children( $id, Surveys::RESPONSE );
		update_meta_cache( 'post', array_column( $posts, 'ID' ) );
		foreach ( $posts as $post ) {
			$row = json_decode( $post->post_content, true );
			if ( ! is_array( $row ) || (int) ( $row['version'] ?? 0 ) !== $version ) {
				continue;
			}
			$row['id']             = $post->ID;
			$row['labels']         = self::json_field( $post->ID, 'analysis_labels' );
			$row['label_revision'] = hash( 'sha256', wp_json_encode( $row['labels'] ) );
			$source[]              = $post->ID . ':' . hash( 'sha256', $post->post_content ) . ':' . $row['label_revision'];
			$all_ids[]             = $post->ID;
			$latest                = max( $latest, $post->ID );
			if ( ! $survey['linked'] ) {
				unset( $row['respondent'] );
			}
			if ( self::matches( $row, $selection, $questions ) ) {
				$rows[] = $row;
			}
		}
		$response = self::response( $id, $version, $all_ids );
		return [
			'survey_id'          => $id,
			'version'            => $version,
			'linked'             => $survey['linked'],
			'document'           => $publication['document'],
			'questions'          => $questions,
			'rows'               => $rows,
			'configuration'      => $config,
			'selection'          => $selection,
			'source_revision'    => hash( 'sha256', implode( '|', $source ) . wp_json_encode( $response ) ),
			'source_count'       => count( $all_ids ),
			'latest_response_id' => $latest,
			'response'           => $response,
		];
	}

	public static function matches( array $row, array $selection, array $questions ): bool {
		foreach ( $selection['filters'] as $id => $options ) {
			if ( ! array_intersect( (array) ( $row['answers'][ $id ] ?? [] ), $options ) ) {
				return false;
			}
		}
		if ( $selection['search'] === '' && $selection['theme'] === '' && $selection['text_question'] === '' ) {
			return true;
		}
		foreach ( $questions as $q ) {
			if ( self::text_matches( $row, $q, $selection ) ) {
				return true;
			}
		}
		return false;
	}

	private static function text_matches( array $row, array $q, array $selection ): bool {
		return $q['type'] === 'text' && isset( $row['answers'][ $q['id'] ] )
			&& ( $selection['text_question'] === '' || $selection['text_question'] === $q['id'] )
			&& ( $selection['search'] === '' || mb_stripos( $row['answers'][ $q['id'] ], $selection['search'] ) !== false )
			&& ( $selection['theme'] === '' || in_array( $selection['theme'], $row['labels'][ $q['id'] ] ?? [], true ) );
	}

	/** Invitation claims are used only internally; no invitation or identity ID leaves this method. */
	private static function response( int $id, int $version, array $response_ids ): array {
		$sent      = 0;
		$completed = 0;
		$invited   = [];
		$existing  = array_fill_keys( $response_ids, true );
		foreach ( Surveys::children( $id, Surveys::INVITE ) as $post ) {
			$invite = json_decode( $post->post_content, true );
			if ( ! is_array( $invite ) || (int) ( $invite['version'] ?? 0 ) !== $version ) {
				continue;
			}
			$reply = (int) get_option( 'rondo_survey_submit_' . hash( 'sha256', $id . ':invite:' . $post->ID ), 0 );
			if ( isset( $existing[ $reply ] ) ) {
				$invited[ $reply ] = true;
			}
			if ( ! empty( $invite['sent_at'] ) ) {
				++$sent;
				$completed += (int) isset( $existing[ $reply ] );
			}
		}
		return [
			'sent'                => $sent,
			'completed'           => $completed,
			'rate'                => self::rate( $completed, $sent ),
			'invited_submissions' => count( $invited ),
			'public_submissions'  => count( $existing ) - count( $invited ),
		];
	}

	public static function rate( int $count, int $denominator ): ?float {
		return $denominator > 0 ? round( 100 * $count / $denominator, 1 ) : null;
	}

	public static function summary( array $questions, array $rows, bool $texts = false ): array {
		$summary = [];
		foreach ( $questions as $q ) {
			$counts   = array_fill_keys( array_column( $q['options'], 'id' ), 0 );
			$answered = 0;
			$values   = [];
			foreach ( $rows as $row ) {
				$value = $row['answers'][ $q['id'] ] ?? null;
				if ( $value === null || $value === '' || $value === [] ) {
					continue;
				}
				++$answered;
				if ( $q['type'] === 'text' && $texts ) {
					$values[] = $value;
				} else {
					foreach ( array_unique( (array) $value ) as $choice ) {
						if ( isset( $counts[ $choice ] ) ) {
							++$counts[ $choice ];
						}
					}
				}
			}
			$summary[] = $q + [
				'answered' => $answered,
				'counts'   => $counts,
				'texts'    => $values,
			];
		}
		return $summary;
	}

	/** Aggregates contain no answer text or respondent fields. */
	public static function aggregate( array $data ): array {
		$profile   = $data['configuration']['profile'];
		$questions = $data['questions'];
		$metrics   = [];
		foreach ( $profile['metrics'] as $metric ) {
			$q           = $questions[ $metric['question'] ] ?? null;
			$error       = ! $q || $q['type'] === 'text' || ! $metric['options'] || array_diff( array_merge( $metric['options'], $metric['exclude'] ), array_column( $q['options'], 'id' ) );
			$count       = 0;
			$denominator = 0;
			if ( ! $error ) {
				foreach ( $data['rows'] as $row ) {
					$value = (array) ( $row['answers'][ $q['id'] ] ?? [] );
					if ( ! $value || array_intersect( $value, $metric['exclude'] ) ) {
						continue;
					}
					++$denominator;
					$count += (int) (bool) array_intersect( $value, $metric['options'] );
				}
			}
			$metrics[] = $metric + [
				'count'       => $count,
				'denominator' => $denominator,
				'rate'        => $error ? null : self::rate( $count, $denominator ),
				'error'       => $error ? 'Koppel de vraag en antwoordopties in Analyse instellen.' : null,
			];
		}
		$comparisons = [];
		foreach ( $profile['comparisons'] as $comparison ) {
			$outcome = $questions[ $comparison['outcome'] ] ?? null;
			$group   = $questions[ $comparison['group'] ] ?? null;
			$error   = ! $outcome || ! $group || $outcome['type'] === 'text' || $group['type'] === 'text';
			$groups  = [];
			if ( ! $error ) {
				foreach ( $group['options'] as $option ) {
					$rows     = array_values( array_filter( $data['rows'], static fn( $row ) => in_array( $option['id'], (array) ( $row['answers'][ $group['id'] ] ?? [] ), true ) ) );
					$summary  = self::summary( [ $outcome ], $rows )[0];
					$groups[] = [
						'id'       => $option['id'],
						'label'    => $option['label'],
						'total'    => count( $rows ),
						'answered' => $summary['answered'],
						'counts'   => $summary['counts'],
						'small'    => $summary['answered'] < 10,
					];
				}
			}
			$comparisons[] = $comparison + [
				'groups'  => $groups,
				'options' => $outcome['options'] ?? [],
				'error'   => $error ? 'Koppel beide vragen in Analyse instellen.' : null,
			];
		}
		$themes = [];
		foreach ( $profile['themes'] as $theme ) {
			$count = 0;
			foreach ( $data['rows'] as $row ) {
				$hit = false;
				foreach ( $row['labels'] as $question => $labels ) {
					if ( isset( $row['answers'][ $question ] ) && in_array( $theme['id'], $labels, true ) ) {
						$hit = true;
					}
				}
				$count += (int) $hit;
			}
			$themes[] = [
				'id'    => $theme['id'],
				'label' => $theme['label'],
				'count' => $count,
			];
		}
		return [
			'total'       => count( $data['rows'] ),
			'summary'     => self::summary( $questions, $data['rows'] ),
			'metrics'     => $metrics,
			'comparisons' => $comparisons,
			'themes'      => $themes,
		];
	}

	public static function text_page( array $data, int $page ): array {
		$items = [];
		$total = 0;
		foreach ( $data['rows'] as $row ) {
			foreach ( $data['questions'] as $q ) {
				if ( ! self::text_matches( $row, $q, $data['selection'] ) ) {
					continue;
				}
				++$total;
				if ( $total <= ( $page - 1 ) * 25 || count( $items ) >= 25 ) {
					continue;
				}
				$suggestions = [];
				foreach ( $data['configuration']['profile']['themes'] as $theme ) {
					if ( in_array( $theme['id'], $row['labels'][ $q['id'] ] ?? [], true ) ) {
						continue;
					}
					$hits = array_values( array_filter( $theme['keywords'], static fn( $word ) => mb_stripos( $row['answers'][ $q['id'] ], $word ) !== false ) );
					if ( $hits ) {
						$suggestions[] = [
							'theme' => $theme['id'],
							'words' => $hits,
						];
					}
				}
				$items[] = [
					'response_id' => $row['id'],
					'question'    => $q['id'],
					'label'       => $q['label'],
					'text'        => $row['answers'][ $q['id'] ],
					'themes'      => $row['labels'][ $q['id'] ] ?? [],
					'revision'    => $row['label_revision'],
					'suggestions' => $suggestions,
				];
			}
		}
		return [
			'items' => $items,
			'total' => $total,
			'page'  => $page,
			'pages' => (int) ceil( $total / 25 ),
		];
	}

	public static function save_configuration( int $id, array $input ) {
		return Surveys::locked(
			'survey:' . $id,
			static function () use ( $id, $input ) {
				$data = self::select( $id, [ 'version' => $input['version'] ?? 0 ] );
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				if ( ( $input['revision'] ?? null ) !== $data['configuration']['revision'] ) {
					return Schema::error( 'De analyse is gewijzigd. Vernieuw voordat je opnieuw opslaat.', 409 );
				}
				$profile = AnalysisSchema::profile( $input['profile'] ?? null );
				if ( is_wp_error( $profile ) ) {
					return $profile;
				}
				$source = $input['profile']['source_profile'] ?? null;
				if ( $source !== null ) {
					$templates = self::templates();
					$found     = false;
					foreach ( $templates as $template ) {
						if ( is_array( $source ) && (string) ( $source['id'] ?? '' ) === (string) $template['id'] && ( $source['version'] ?? null ) === $template['version'] ) {
							$found = true;
						}
					}
					if ( ! $found ) {
						return Schema::error( 'Het bronprofiel bestaat niet.' );
					}
					$profile['source_profile'] = [
						'id'      => $source['id'],
						'version' => $source['version'],
					];
				}
				$config                  = [
					'revision' => wp_generate_uuid4(),
					'profile'  => $profile,
				];
				$all                     = self::json_field( $id, 'analysis_profiles' );
				$all[ $data['version'] ] = $config;
				$saved                   = Fields::update_for_post( $id, 'analysis_profiles', wp_json_encode( $all ) );
				return $saved ? $config : Schema::error( 'Opslaan is mislukt.', 500 );
			}
			);
	}

	public static function templates(): array {
		$out = AnalysisSchema::templates();
		foreach ( Surveys::children( 0, self::PROFILE ) as $post ) {
			$data = Surveys::read( $post->ID, self::PROFILE );
			if ( ! is_wp_error( $data ) ) {
				$out[] = [
					'id'      => $post->ID,
					'version' => 1,
					'profile' => $data['profile'],
				];
			}
		}
		return $out;
	}

	public static function save_template( array $input ) {
		$profile = AnalysisSchema::profile( $input['profile'] ?? null );
		if ( is_wp_error( $profile ) ) {
			return $profile;
		}
		// Each saved template is an immutable version; replacing it creates a new ID.
		return Surveys::write(
			self::PROFILE,
			[
				'title'   => $profile['title'],
				'profile' => $profile,
				'version' => 1,
			]
			);
	}

	public static function label( int $id, array $input ) {
		return Surveys::locked(
			'survey:' . $id,
			static function () use ( $id, $input ) {
				$reply = (int) ( $input['response_id'] ?? 0 );
				$row   = Surveys::read( $reply, Surveys::RESPONSE );
				if ( is_wp_error( Surveys::read( $id ) ) || is_wp_error( $row ) || (int) get_post_field( 'post_parent', $reply ) !== $id ) {
					return Schema::error( 'Inzending niet gevonden.', 404 );
				}
				$publication = Surveys::read( (int) $row['version'], Surveys::VERSION );
				if ( is_wp_error( $publication ) || (int) get_post_field( 'post_parent', $row['version'] ) !== $id ) {
					return Schema::error( 'Vragenlijstversie niet gevonden.', 404 );
				}
				$q         = $input['question'] ?? '';
				$questions = self::questions( $publication['document'] );
				$config    = self::configuration( $id, $row['version'] );
				$themes    = $input['themes'] ?? null;
				if ( ! is_string( $q ) || ( $questions[ $q ]['type'] ?? '' ) !== 'text' || ! isset( $row['answers'][ $q ] ) || ! AnalysisSchema::ids( $themes, 40 ) || array_diff( $themes, array_column( $config['profile']['themes'], 'id' ) ) ) {
					return Schema::error( 'Selecteer een open antwoord en bestaande thema’s.' );
				}
				$labels = self::json_field( $reply, 'analysis_labels' );
				if ( ( $input['revision'] ?? null ) !== hash( 'sha256', wp_json_encode( $labels ) ) || ( $input['profile_revision'] ?? null ) !== $config['revision'] ) {
					return Schema::error( 'De labels of het profiel zijn gewijzigd. Vernieuw de analyse.', 409 );
				}
				$labels[ $q ] = array_values( array_unique( $themes ) );
				return Fields::update_for_post( $reply, 'analysis_labels', wp_json_encode( $labels ) ) ? [ 'saved' => true ] : Schema::error( 'Opslaan is mislukt.', 500 );
			}
			);
	}
}
