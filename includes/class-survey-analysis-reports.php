<?php
/** Immutable conclusions with aggregate evidence and protected source references. */
namespace Rondo\Surveys;

final class AnalysisReports {
	public static function create( int $id, array $input ) {
		return Surveys::locked(
			'survey:' . $id,
			static function () use ( $id, $input ) {
				if ( ! AnalysisSchema::text( $input['title'] ?? null ) || trim( $input['title'] ) === '' || ! AnalysisSchema::text( $input['text'] ?? null, 10000 ) || trim( $input['text'] ) === '' || ! is_array( $input['selection'] ?? [] ) ) {
					return Schema::error( 'Geef de conclusie een titel en toelichting.' );
				}
				$data = Analysis::select( $id, [ 'version' => $input['version'] ?? 0 ] + ( $input['selection'] ?? [] ) );
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				if ( ( $input['profile_revision'] ?? null ) !== $data['configuration']['revision'] || ( $input['source_revision'] ?? null ) !== $data['source_revision'] ) {
					return Schema::error( 'De bron of het profiel is gewijzigd. Vernieuw en beoordeel de cijfers opnieuw.', 409 );
				}
				$previous = $input['previous'] ?? 0;
				if ( ! is_int( $previous ) || $previous < 0 ) {
					return Schema::error( 'Ongeldige rapportverwijzing.' );
				}
				if ( $previous ) {
					$old = Surveys::read( $previous, Analysis::REPORT );
					if ( is_wp_error( $old ) || (int) get_post_field( 'post_parent', $previous ) !== $id || $old['version'] !== $data['version'] ) {
						return Schema::error( 'Het vorige rapport hoort niet bij deze enquêteversie.' );
					}
				}
				$references = $input['references'] ?? [];
				if ( ! is_array( $references ) || count( $references ) > 100 ) {
					return Schema::error( 'Gebruik maximaal 100 bronverwijzingen.' );
				}
				$clean        = [];
				$selected_ids = array_column( $data['rows'], 'id' );
				foreach ( $references as $ref ) {
					if ( ! is_array( $ref ) || ! in_array( $ref['type'] ?? '', [ 'question', 'theme', 'response' ], true ) ) {
						return Schema::error( 'Ongeldige bronverwijzing.' );
					}
					$key   = $ref['id'] ?? null;
					$valid = $ref['type'] === 'response' ? is_int( $key ) && in_array( $key, $selected_ids, true )
					: ( is_string( $key ) && ( $ref['type'] === 'question' ? isset( $data['questions'][ $key ] ) : in_array( $key, array_column( $data['configuration']['profile']['themes'], 'id' ), true ) ) );
					if ( ! $valid ) {
						return Schema::error( 'Een bron hoort niet bij deze selectie.' );
					}
					$clean[] = [
						'type' => $ref['type'],
						'id'   => $key,
					];
				}
				return Surveys::write(
				Analysis::REPORT,
				[
					'title'               => sanitize_text_field( $input['title'] ),
					'text'                => sanitize_textarea_field( $input['text'] ),
					'author'              => get_current_user_id(),
					'created_at'          => current_datetime()->format( DATE_ATOM ),
					'version'             => $data['version'],
					'previous'            => $previous,
					'references'          => $clean,
					'profile_revision'    => $data['configuration']['revision'],
					'profile'             => $data['configuration']['profile'],
					'selection'           => $data['selection'],
					'calculation_version' => Analysis::CALCULATION,
					'source_revision'     => $data['source_revision'],
					'source_count'        => $data['source_count'],
					'latest_response_id'  => $data['latest_response_id'],
					'aggregates'          => Analysis::aggregate( $data ),
					'response'            => $data['response'],
				],
				0,
				$id
				);
			}
			);
	}

	public static function listing( array $data ): array {
		$out = [];
		foreach ( Surveys::children( $data['survey_id'], Analysis::REPORT ) as $post ) {
			$report = Surveys::read( $post->ID, Analysis::REPORT );
			if ( is_wp_error( $report ) || $report['version'] !== $data['version'] ) {
				continue;
			}
			$author                   = get_userdata( $report['author'] );
			$report['author_name']    = $author ? $author->display_name : 'Verwijderde gebruiker';
			$report['new_responses']  = $data['latest_response_id'] > $report['latest_response_id'];
			$report['source_changed'] = $data['source_revision'] !== $report['source_revision'];
			foreach ( $report['references'] as &$ref ) {
				$ref['available'] = $ref['type'] === 'response'
					? ! is_wp_error( Surveys::read( (int) $ref['id'], Surveys::RESPONSE ) ) && (int) get_post_field( 'post_parent', $ref['id'] ) === $data['survey_id']
					: ( $ref['type'] === 'question' ? isset( $data['questions'][ $ref['id'] ] ) : in_array( $ref['id'], array_column( $data['configuration']['profile']['themes'], 'id' ), true ) );
			}
			unset( $ref );
			$out[] = $report;
		}
		return array_reverse( $out );
	}
}
