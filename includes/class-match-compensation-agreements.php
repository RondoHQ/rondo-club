<?php
/** Dated, finance-only agreements on existing people. */
namespace Rondo\Matches;

use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Core\AccessControl;
use Rondo\Finance\PersonBankAccount;

final class CompensationAgreements {
	public const FIELD           = 'match_compensation_agreements';
	private static bool $writing = false;

	public function __construct() {
		add_filter( 'rondo_fields_validate_value', [ $this, 'guard' ], 10, 4 );
	}
	public function guard( $value, $id, $definition, $old ) {
		if ( ( $definition['context'] ?? '' ) === 'person' && $definition['canonical_name'] === self::FIELD && ! self::$writing ) {
			return CompensationService::error( 'Beheer afspraken op de pagina Wedstrijdvergoedingen.', 403 );
		}
		return $value;
	}
	public static function read( int $id ): array {
		$pending = get_post_meta( $id, '_match_agreement_pending', true );
		if ( is_array( $pending ) ) {
			return $pending['old']; }
		return Formatter::for_wire( 'person', [ self::FIELD => Fields::get_for_post( $id, self::FIELD ) ] )[ self::FIELD ];
	}
	public static function people(): array {
		return get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'meta_query'       => [
					'relation' => 'OR',
					[
						'key'     => self::FIELD,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					],
					[
						'key'     => '_match_agreement_pending',
						'compare' => 'EXISTS',
					],
				],
				'suppress_filters' => false,
				'orderby'          => 'ID',
				'order'            => 'ASC',
			]
			);
	}
	public static function roster( int $team ): array {
		$people = [];
		foreach ( self::people() as $post ) {
			if ( ! AccessControl::can_view_person( $post->ID ) ) {
				continue;
			}
			$agreements = array_values( array_filter( self::read( $post->ID ), static fn( $row ) => (int) $row['team_id'] === $team ) );
			if ( $agreements ) {
				$people[] = [
					'person_id'   => $post->ID,
					'player_name' => $post->post_title,
					'agreements'  => $agreements,
				];
			}
		}
		return $people;
	}
	public static function overlap( array $a, array $b ): bool {
		return $a['valid_from'] <= ( $b['valid_until'] ?: '9999-12-31' ) && $b['valid_from'] <= ( $a['valid_until'] ?: '9999-12-31' );
	}
	public static function update( int $id, array $input ) {
		if ( ! CompensationService::finance( true ) || ! PersonBankAccount::can_write( $id ) ) {
			return CompensationService::error( 'Geen toegang tot deze afspraken.', 403 );
		}
		return CompensationService::operation(
			$input,
			'agreements:' . $id,
			static function ( $request_id ) use ( $id, $input ) {
				if ( get_post_meta( $id, '_match_agreement_request', true ) === $request_id ) {
					return self::response( $id ); }
				$old = self::read( $id );
				if ( ( $input['version'] ?? '' ) !== hash( 'sha256', wp_json_encode( $old ) ) ) {
					return CompensationService::error( 'Afspraken zijn gewijzigd. Laad de persoon opnieuw.', 409 );
				}
				$rows = $input['agreements'] ?? null;
				if ( ! is_array( $rows ) || ! array_is_list( $rows ) || count( $rows ) > 100 ) {
					return CompensationService::error( 'Gebruik een lijst met maximaal 100 afspraken.' );
				}
				foreach ( $rows as $i => &$row ) {
					if ( ! is_array( $row ) || array_diff( array_keys( $row ), [ 'team_id', 'valid_from', 'valid_until', 'payment_route', 'nmbrs_employee_number', 'nmbrs_name' ] ) ) {
						return CompensationService::error( 'Ongeldige afspraakvelden.' );
					}
					$team = $row['team_id'] ?? 0;
					if ( ! is_int( $team ) || ! CompensationService::team_access( $team ) || ( CompensationService::rule( $team )['scheme'] ?? '' ) !== 'awc1' || ! in_array( $row['payment_route'] ?? '', [ 'payroll', 'sepa_guest' ], true ) ) {
						return CompensationService::error( 'Kies een geldig AWC 1-team en betaalroute.' );
					}
					foreach ( [ 'valid_from', 'valid_until' ] as $key ) {
						$value = $row[ $key ] ?? null;
						if ( $key === 'valid_until' && ( $value === null || $value === '' ) ) {
							$row[ $key ] = null;
							continue;
						}
						$date = is_string( $value ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $value ) : false;
						if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
							return CompensationService::error( 'Vul een geldige ingangsdatum en optionele einddatum in.' );
						}
					}
					if ( $row['valid_until'] && $row['valid_until'] < $row['valid_from'] ) {
						return CompensationService::error( 'De einddatum ligt vóór de ingangsdatum.' );
					}
					foreach ( [ 'nmbrs_name', 'nmbrs_employee_number' ] as $key ) {
						if ( ! is_string( $row[ $key ] ?? '' ) ) {
							return CompensationService::error( 'Gebruik tekst voor naam en personeelsnummer.' );
						}
						$row[ $key ] = trim( $row[ $key ] ?? '' );
					}
					if ( $row['payment_route'] === 'payroll' ) {
						if ( ! preg_match( '/^[A-Za-z0-9-]{1,30}$/D', $row['nmbrs_employee_number'] ) || $row['nmbrs_name'] === '' || mb_strlen( $row['nmbrs_name'] ) > 100 || preg_match( '/[\x00-\x1f<>]/', $row['nmbrs_name'] ) ) {
							return CompensationService::error( 'Vul een geldig personeelsnummer en bevestigde Nmbrs-naam in.' );
						}
					} else {
						$row['nmbrs_name']            = '';
						$row['nmbrs_employee_number'] = '';
					}
				}
				unset( $row );
				foreach ( $rows as $i => $row ) {
					foreach ( array_slice( $rows, $i + 1 ) as $other ) {
						if ( $row['team_id'] === $other['team_id'] && self::overlap( $row, $other ) ) {
							return CompensationService::error( 'Afspraken voor hetzelfde team mogen niet overlappen.' );
						}
					}
					if ( $row['payment_route'] !== 'payroll' ) {
						continue;
					}
					foreach ( self::people() as $person ) {
						if ( $person->ID === $id ) {
							continue;
						}
						foreach ( self::read( $person->ID ) as $other ) {
							if ( $other['payment_route'] === 'payroll' && $row['nmbrs_employee_number'] === $other['nmbrs_employee_number'] && self::overlap( $row, $other ) ) {
								return CompensationService::error( 'Dit personeelsnummer is in deze periode al gekoppeld.', 409 );
							}
						}
					}
				}
				// Native storage uses compact dates; the endpoint remains canonical.
				$stored = $rows;
				foreach ( $stored as &$row ) {
					$row['valid_from']  = str_replace( '-', '', $row['valid_from'] );
					$row['valid_until'] = $row['valid_until'] ? str_replace( '-', '', $row['valid_until'] ) : null;
				}
				unset( $row );
				$pending = [
					'old'        => $old,
					'request_id' => $request_id,
				];
				update_post_meta( $id, '_match_agreement_pending', wp_slash( $pending ) );
				if ( get_post_meta( $id, '_match_agreement_pending', true ) !== $pending ) {
					return CompensationService::error( 'De wijziging kon niet worden voorbereid.', 500 );
				}
				self::$writing = true;
				try {
					$result = Fields::update_many_for_post( $id, [ self::FIELD => $stored ] );
				} finally {
					self::$writing = false;
				}
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( Formatter::for_wire( 'person', [ self::FIELD => Fields::get_for_post( $id, self::FIELD ) ] )[ self::FIELD ] !== $rows ) {
					return CompensationService::error( 'Afspraken zijn niet volledig opgeslagen. Laad opnieuw.', 500 );
				}
				delete_post_meta( $id, '_match_agreement_pending' );
				if ( metadata_exists( 'post', $id, '_match_agreement_pending' ) ) {
					return CompensationService::error( 'De wijziging kon niet worden afgerond.', 500 ); }
				update_post_meta( $id, '_match_agreement_request', $request_id );
				add_post_meta(
				$id,
				'_match_agreement_audit',
				[
					'actor'       => get_current_user_id(),
					'at'          => current_datetime()->format( DATE_ATOM ),
					'before_hash' => hash( 'sha256', wp_json_encode( $old ) ),
					'after_hash'  => hash( 'sha256', wp_json_encode( $rows ) ),
				]
				);
				return self::response( $id );
			}
			);
	}
	public static function response( int $id ) {
		if ( ! CompensationService::finance( true ) || ! PersonBankAccount::can_read( $id ) ) {
			return CompensationService::error( 'Geen toegang tot deze afspraken.', 403 );
		}
		$closed = [];
		foreach ( CompensationService::config()['teams'] as $team ) {
			foreach ( CompensationService::posts( CompensationService::BATCH, $team['team_id'] ) as $post ) {
				$batch = CompensationService::record( $post->ID );
				if ( ( $batch['phase'] ?? '' ) === 'closed' ) {
					$closed[ $team['team_id'] ][] = $batch['month']; }
			}
		}
		$rows = self::read( $id );
		return [
			'closed_months' => $closed,
			'person_id'     => $id,
			'player_name'   => get_the_title( $id ),
			'agreements'    => $rows,
			'version'       => hash( 'sha256', wp_json_encode( $rows ) ),
			'can_edit'      => PersonBankAccount::can_write( $id ),
		];
	}
}
