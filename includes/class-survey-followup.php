<?php
/** Private survey actions, reviewable suggestions and their audit history. */
namespace Rondo\Surveys;

use Rondo\Core\UserRoles;

final class Followup {
	const ACTION   = 'rondo_survey_action';
	const RULE     = 'rondo_survey_rule';
	const STATUSES = [ 'open', 'in_progress', 'done', 'no_action', 'proposed', 'dismissed' ];

	public static function eligible( int $id ): bool {
		return UserRoles::is_user_approved( $id ) && UserRoles::can_access_section( 'enquetes', $id ) && UserRoles::can_access_section( 'enquete_resultaten', $id );
	}

	public static function owners(): array {
		$owners = [];
		foreach ( get_users(
			[
				'fields'  => [ 'ID', 'display_name' ],
				'orderby' => 'display_name',
			]
			) as $user ) {
			if ( self::eligible( (int) $user->ID ) ) {
				$owners[] = [
					'id'   => (int) $user->ID,
					'name' => $user->display_name,
				];
			}
		}
		return $owners;
	}

	public static function date( $value ): bool {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $value ) ) {
			return false;
		}
		[ $year, $month, $day ] = array_map( 'intval', explode( '-', $value ) );
		return checkdate( $month, $day, $year );
	}

	/** Resolve references every time; never copy answers or respondent identities. */
	public static function source( int $survey, int $version, $ref ) {
		if ( ! is_array( $ref ) || ! in_array( $ref['type'] ?? '', [ 'response', 'theme', 'report' ], true ) ) {
			return Schema::error( 'Selecteer een antwoord, thema of conclusie als bron.' );
		}
		$id = $ref['id'] ?? null;
		if ( $ref['type'] === 'theme' ) {
			$themes = array_column( Analysis::configuration( $survey, $version )['profile']['themes'], null, 'id' );
			if ( ! is_string( $id ) || ! isset( $themes[ $id ] ) ) {
				return Schema::error( 'Het bronthema is niet meer beschikbaar.', 404 );
			}
			$label = $themes[ $id ]['label'];
		} else {
			if ( ! is_int( $id ) || $id <= 0 ) {
				return Schema::error( 'Ongeldige bronverwijzing.' );
			}
			$row = Surveys::read( $id, $ref['type'] === 'response' ? Surveys::RESPONSE : Analysis::REPORT );
			if ( is_wp_error( $row ) || (int) get_post_field( 'post_parent', $id ) !== $survey || (int) ( $row['version'] ?? 0 ) !== $version ) {
				return Schema::error( 'De bron hoort niet bij deze enquêteversie of is verwijderd.', 404 );
			}
			$label = $ref['type'] === 'report' ? $row['title'] : 'Inzending ' . $id;
		}
		return [
			'type'      => $ref['type'],
			'id'        => $id,
			'label'     => $label,
			'available' => true,
		];
	}

	public static function record( int $survey, int $version, int $id, string $type ) {
		$row = Surveys::read( $id, $type );
		if ( is_wp_error( $row ) || (int) get_post_field( 'post_parent', $id ) !== $survey || (int) ( $row['version'] ?? 0 ) !== $version ) {
			return Schema::error( 'Record niet gevonden bij deze enquêteversie.', 404 );
		}
		return $row;
	}

	private static function decorate( int $survey, array $row ): array {
		$source                = self::source( $survey, $row['version'], $row['source'] );
		$row['source']         = is_wp_error( $source ) ? $row['source'] + [
			'available' => false,
			'label'     => 'Bron niet meer beschikbaar',
		] : $source;
		$user                  = $row['owner'] ? get_userdata( $row['owner'] ) : false;
		$row['owner_name']     = $user ? $user->display_name : 'Niet toegewezen';
		$row['owner_eligible'] = $row['owner'] > 0 && self::eligible( $row['owner'] );
		$row['overdue']        = $row['due'] !== '' && $row['due'] < current_time( 'Y-m-d' ) && in_array( $row['status'], [ 'open', 'in_progress' ], true );
		return $row;
	}

	public static function listing( int $survey, array $params ) {
		$data = Analysis::select( $survey, [ 'version' => $params['version'] ?? 0 ] );
		if ( is_wp_error( $data ) ) {
			return $data; }
		$rows = [];
		foreach ( Surveys::children( $survey, self::ACTION ) as $post ) {
			$row = Surveys::read( $post->ID, self::ACTION );
			if ( is_wp_error( $row ) || $row['version'] !== $data['version'] ) {
				continue; }
			$row = self::decorate( $survey, $row );
			if ( ( ! empty( $params['status'] ) && $row['status'] !== $params['status'] ) || ( ! empty( $params['owner'] ) && $row['owner'] !== (int) $params['owner'] ) || ( ! empty( $params['overdue'] ) && ! $row['overdue'] ) ) {
				continue; }
			unset( $row['history'] );
			$rows[] = $row;
		}
		$page = max( 1, (int) ( $params['page'] ?? 1 ) );
		return [
			'rows'  => array_slice( array_reverse( $rows ), ( $page - 1 ) * 25, 25 ),
			'total' => count( $rows ),
			'page'  => $page,
			'pages' => (int) ceil( count( $rows ) / 25 ),
		];
	}

	/** Keep the authoritative audit trail in the same atomic post-content update. */
	private static function persist( int $survey, array $row, int $id = 0 ) {
		$row['revision']   = wp_generate_uuid4();
		$row['updated_at'] = current_datetime()->format( DATE_ATOM );
		return Surveys::write( self::ACTION, $row, $id, $survey );
	}

	public static function save( int $survey, array $input ) {
		return Surveys::locked(
			'survey:' . $survey,
			static function () use ( $survey, $input ) {
				$data = Analysis::select( $survey, [ 'version' => $input['version'] ?? 0 ] );
				if ( is_wp_error( $data ) ) {
					return $data; }
				$id  = (int) ( $input['id'] ?? 0 );
				$old = $id ? self::record( $survey, $data['version'], $id, self::ACTION ) : null;
				if ( is_wp_error( $old ) ) {
					return $old; }
				if ( $old && ( $input['revision'] ?? null ) !== $old['revision'] ) {
					return Schema::error( 'Deze actie is gewijzigd. Vernieuw voordat je opnieuw opslaat.', 409 ); }
				$status = $input['status'] ?? 'open';
				if ( ! in_array( $status, self::STATUSES, true ) || ( ! $old && in_array( $status, [ 'proposed', 'dismissed' ], true ) ) || ( $old && $old['status'] !== $status && ( $status === 'proposed' || ( $status === 'dismissed' && $old['status'] !== 'proposed' ) ) ) ) {
					return Schema::error( 'Ongeldige actiestatus.' ); }
				$source = self::source( $survey, $data['version'], $old['source'] ?? ( $input['source'] ?? null ) );
				// Existing actions can be closed after deletion of their source, but suggestions cannot be accepted.
				if ( is_wp_error( $source ) && ( ! $old || ( $old['status'] === 'proposed' && ! in_array( $status, [ 'proposed', 'dismissed' ], true ) ) ) ) {
					return $source; }
				$owner            = $input['owner'] ?? 0;
				$due              = $input['due'] ?? '';
				$needs_assignment = ! in_array( $status, [ 'proposed', 'dismissed' ], true );
				if ( ! is_int( $owner ) || ( $needs_assignment && ( $owner <= 0 || ! self::eligible( $owner ) || ! self::date( $due ) ) ) || ( ! $needs_assignment && ( $owner !== 0 || $due !== '' ) ) ) {
					return Schema::error( 'Kies een bevoegde interne eigenaar en geldige deadline voor een actie.' ); }
				$fields = [];
				foreach ( [
					'title'  => 200,
					'notes'  => 5000,
					'reason' => 2000,
				] as $key => $max ) {
					$value = $input[ $key ] ?? '';
					if ( ! AnalysisSchema::text( $value, $max ) || ( $key === 'title' && trim( $value ) === '' ) ) {
						return Schema::error( 'Vul een omschrijving in en houd notities binnen de toegestane lengte.' ); }
					$fields[ $key ] = sanitize_textarea_field( $value );
				}
				if ( in_array( $status, [ 'no_action', 'dismissed' ], true ) && trim( $fields['reason'] ) === '' ) {
					return Schema::error( 'Geef een reden voor Geen actie of het verwerpen van een voorstel.' ); }
				$fields += [
					'owner'  => $owner,
					'due'    => $due,
					'status' => $status,
				];
				$changes = [];
				foreach ( $fields as $key => $value ) {
					if ( ! $old || $old[ $key ] !== $value ) {
						$changes[ $key ] = [
							'from' => $old[ $key ] ?? null,
							'to'   => $value,
						]; }
				}
				if ( $old && ! $changes ) {
					return self::decorate( $survey, $old ); }
				$history   = $old['history'] ?? [];
				$history[] = [
					'author'  => get_current_user_id(),
					'at'      => current_datetime()->format( DATE_ATOM ),
					'changes' => $changes,
				];
				$row       = $fields + [
					'version' => $data['version'],
					'source'  => $old['source'] ?? [
						'type' => $source['type'],
						'id'   => $source['id'],
					],
					'origin'  => $old['origin'] ?? null,
					'history' => $history,
				];
				$saved     = self::persist( $survey, $row, $id );
				return is_wp_error( $saved ) ? $saved : self::decorate( $survey, Surveys::read( $saved, self::ACTION ) );
			}
			);
	}

	public static function detail( int $survey, int $version, int $id ) {
		$data = Analysis::select( $survey, [ 'version' => $version ] );
		if ( is_wp_error( $data ) ) {
			return $data; }
		$row = self::record( $survey, $version, $id, self::ACTION );
		if ( is_wp_error( $row ) ) {
			return $row; }
		foreach ( $row['history'] as &$event ) {
			$user                 = get_userdata( $event['author'] );
			$event['author_name'] = $user ? $user->display_name : 'Verwijderde gebruiker';
		}
		unset( $event );
		return self::decorate( $survey, $row );
	}

	public static function rules( int $survey, int $version ) {
		$data = Analysis::select( $survey, [ 'version' => $version ] );
		if ( is_wp_error( $data ) ) {
			return $data; }
		$rows = [];
		foreach ( Surveys::children( $survey, self::RULE ) as $post ) {
			$row = Surveys::read( $post->ID, self::RULE );
			if ( ! is_wp_error( $row ) && $row['version'] === $version ) {
				$rows[] = $row; }
		}
		return $rows;
	}

	private static function predicate( array $input, array $data ) {
		if ( ( $input['kind'] ?? '' ) === 'answer' ) {
			$q = $input['question'] ?? '';
			if ( ! is_string( $q ) ) {
				return Schema::error( 'Selecteer een vraag.' ); }
			$filters = AnalysisSchema::filters( [ $q => $input['options'] ?? [] ], $data['questions'] );
			return is_wp_error( $filters ) ? $filters : [
				'kind'     => 'answer',
				'question' => $q,
				'options'  => $filters[ $q ],
			];
		}
		if ( ( $input['kind'] ?? '' ) === 'theme' && is_string( $input['theme'] ?? null ) && in_array( $input['theme'], array_column( $data['configuration']['profile']['themes'], 'id' ), true ) ) {
			return [
				'kind'  => 'theme',
				'theme' => $input['theme'],
			];
		}
		return Schema::error( 'Kies expliciete antwoordopties of een bevestigd thema.' );
	}

	public static function save_rule( int $survey, array $input ) {
		return Surveys::locked(
			'survey:' . $survey,
			static function () use ( $survey, $input ) {
				$data = Analysis::select( $survey, [ 'version' => $input['version'] ?? 0 ] );
				if ( is_wp_error( $data ) ) {
					return $data; }
				$id  = (int) ( $input['id'] ?? 0 );
				$old = $id ? self::record( $survey, $data['version'], $id, self::RULE ) : null;
				if ( is_wp_error( $old ) ) {
					return $old; }
				if ( $old && ( $input['revision'] ?? null ) !== $old['revision'] ) {
					return Schema::error( 'De regel is gewijzigd. Vernieuw de regels.', 409 ); }
				if ( ! AnalysisSchema::text( $input['title'] ?? null ) || trim( $input['title'] ) === '' || ! is_bool( $input['active'] ?? null ) ) {
					return Schema::error( 'Geef de regel een omschrijving en geldige aan/uit-keuze.' ); }
				$predicate = self::predicate( $input, $data );
				if ( is_wp_error( $predicate ) ) {
					return $predicate; }
				$changed = ! $old || $old['predicate'] !== $predicate || $old['title'] !== sanitize_text_field( $input['title'] );
				$row     = [
					'title'        => sanitize_text_field( $input['title'] ),
					'active'       => $input['active'],
					'version'      => $data['version'],
					'predicate'    => $predicate,
					'rule_version' => $old ? $old['rule_version'] + ( $changed ? 1 : 0 ) : 1,
					'revision'     => wp_generate_uuid4(),
					'author'       => get_current_user_id(),
					'updated_at'   => current_datetime()->format( DATE_ATOM ),
				];
				$saved   = Surveys::write( self::RULE, $row, $id, $survey );
				return is_wp_error( $saved ) ? $saved : Surveys::read( $saved, self::RULE );
			}
			);
	}

	/** Explicitly requested batches only. No cron, mail or generic task assignment. */
	public static function run_rule( int $survey, array $input ) {
		return Surveys::locked(
			'survey:' . $survey,
			static function () use ( $survey, $input ) {
				$data = Analysis::select( $survey, [ 'version' => $input['version'] ?? 0 ] );
				if ( is_wp_error( $data ) ) {
					return $data; }
				$rule = self::record( $survey, $data['version'], (int) ( $input['id'] ?? 0 ), self::RULE );
				if ( is_wp_error( $rule ) ) {
					return $rule; }
				if ( ! $rule['active'] || ( $input['revision'] ?? null ) !== $rule['revision'] ) {
					return Schema::error( 'De regel is gewijzigd of uitgeschakeld. Vernieuw de regels.', 409 ); }
				$predicate = self::predicate( $rule['predicate'], $data );
				if ( is_wp_error( $predicate ) ) {
					return $predicate; }
				$known = [];
				foreach ( Surveys::children( $survey, self::ACTION ) as $post ) {
					$action = Surveys::read( $post->ID, self::ACTION );
					if ( ! is_wp_error( $action ) && ! empty( $action['origin'] ) ) {
						$known[ $action['origin'] ] = true; }
				}
				$after   = max( 0, (int) ( $input['after'] ?? 0 ) );
				$rows    = array_values( array_filter( $data['rows'], static fn( $r ) => $r['id'] > $after ) );
				$batch   = array_slice( $rows, 0, 100 );
				$created = 0;
				foreach ( $batch as $row ) {
					$origin  = $rule['id'] . ':' . $rule['rule_version'] . ':' . $row['id'];
					$matches = $predicate['kind'] === 'answer' ? (bool) array_intersect( (array) ( $row['answers'][ $predicate['question'] ] ?? [] ), $predicate['options'] ) : (bool) array_filter( $row['labels'], static fn( $themes ) => in_array( $predicate['theme'], $themes, true ) );
					if ( ! $matches || isset( $known[ $origin ] ) ) {
						continue; }
					$saved = self::persist(
					$survey,
					[
						'title'   => $rule['title'],
						'notes'   => '',
						'reason'  => '',
						'owner'   => 0,
						'due'     => '',
						'status'  => 'proposed',
						'version' => $data['version'],
						'source'  => [
							'type' => 'response',
							'id'   => $row['id'],
						],
						'origin'  => $origin,
						'history' => [
							[
								'author'  => get_current_user_id(),
								'at'      => current_datetime()->format( DATE_ATOM ),
								'changes' => [
									'status' => [
										'from' => null,
										'to'   => 'proposed',
									],
								],
								'rule'    => [
									'id'        => $rule['id'],
									'version'   => $rule['rule_version'],
									'predicate' => $predicate,
								],
							],
						],
					]
					);
					if ( is_wp_error( $saved ) ) {
						return $saved; }
					++$created;
				}
				return [
					'created'   => $created,
					'processed' => count( $batch ),
					'next'      => count( $rows ) > 100 ? end( $batch )['id'] : null,
				];
			}
			);
	}
}
