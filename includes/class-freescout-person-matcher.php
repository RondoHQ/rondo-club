<?php
/**
 * Exact person-identifier matching shared by FreeScout integration callers.
 *
 * @package Rondo\Integrations\FreeScout
 */

namespace Rondo\Integrations\FreeScout;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Match only exact canonical Rondo identifiers. */
final class PersonMatcher {

	/**
	 * Match customer emails within either the effective user's scope or integration scope.
	 *
	 * @return array{status:string,person_id:?int,candidate_count:int,candidate_ids:int[]}
	 */
	public function match( array $emails, string $scope = 'integration', int $user_id = 0, string $from_name = '' ): array {
		$emails = $this->normalize_emails( $emails );
		if ( $emails === [] ) {
			return $this->result( 'no_match', null, [] );
		}

		$meta_query = [ 'relation' => 'OR' ];
		foreach ( $emails as $email ) {
			$meta_query[] = [
				'key'     => 'email_1',
				'value'   => $email,
				'compare' => '=',
			];
			$meta_query[] = [
				'key'     => 'email_2',
				'value'   => $email,
				'compare' => '=',
			];
		}

		$query      = new \WP_Query(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => 25,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => $meta_query,
			]
		);
		$candidates = array_values( array_unique( array_map( 'intval', $query->posts ) ) );

		return $this->result_for_candidates( $candidates, $scope, $user_id, $from_name );
	}

	/**
	 * Match one validated Sportlink relation code against the canonical KNVB ID.
	 *
	 * @return array{status:string,person_id:?int,candidate_count:int,candidate_ids:int[]}
	 */
	public function match_knvb_id( string $knvb_id, string $scope = 'integration', int $user_id = 0 ): array {
		$knvb_id = strtoupper( trim( sanitize_text_field( $knvb_id ) ) );
		if ( ! preg_match( '/^[A-Z0-9]{4,20}$/D', $knvb_id ) ) {
			return $this->result( 'no_match', null, [] );
		}

		$query      = new \WP_Query(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => 25,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => 'knvb-id',
						'value'   => $knvb_id,
						'compare' => '=',
					],
				],
			]
		);
		$candidates = array_values( array_unique( array_map( 'intval', $query->posts ) ) );

		return $this->result_for_candidates( $candidates, $scope, $user_id );
	}

	/**
	 * Apply the same row-level visibility and uniqueness rules to every identifier.
	 *
	 * @param int[] $candidates Candidate person IDs.
	 * @return array{status:string,person_id:?int,candidate_count:int,candidate_ids:int[]}
	 */
	private function result_for_candidates( array $candidates, string $scope, int $user_id, string $from_name = '' ): array {

		if ( $scope === 'sidebar' ) {
			$visible = array_values(
				array_filter(
					$candidates,
					static fn( int $person_id ): bool => AccessControl::can_view_person( $person_id, $user_id )
				)
			);
			if ( $visible === [] && $candidates !== [] ) {
				return $this->result( 'inaccessible', null, [] );
			}
			$candidates = $visible;
		}

		if ( count( $candidates ) === 1 ) {
			return $this->result( 'exact', $candidates[0], $candidates );
		}
		if ( count( $candidates ) > 1 ) {
			$name_matches = $this->name_matches( $candidates, $from_name );
			if ( count( $name_matches ) === 1 ) {
				return $this->result( 'exact', $name_matches[0], $name_matches );
			}

			$active = array_values( array_filter( $candidates, [ $this, 'is_active' ] ) );
			if ( count( $active ) === 1 ) {
				return $this->result( 'exact', $active[0], $active );
			}

			return $this->result( 'ambiguous', null, $candidates );
		}

		return $this->result( 'no_match', null, [] );
	}

	/** @return string[] */
	public function normalize_emails( array $emails ): array {
		$normalized = [];
		foreach ( $emails as $email ) {
			if ( ! is_string( $email ) ) {
				continue;
			}
			$value = strtolower( trim( sanitize_email( $email ) ) );
			if ( ! is_email( $value ) || str_ends_with( $value, '@members.rondo.invalid' ) ) {
				continue;
			}
			$normalized[] = $value;
		}

		return array_values( array_unique( $normalized ) );
	}

	/** @param int[] $candidate_ids
	 * @return int[]
	 */
	private function name_matches( array $candidate_ids, string $from_name ): array {
		$from_name = $this->normalize_name( $from_name );
		if ( $from_name === '' ) {
			return [];
		}

		return array_values(
			array_filter(
				$candidate_ids,
				fn( int $person_id ): bool => $this->normalize_name( get_the_title( $person_id ) ) === $from_name
			)
		);
	}

	private function normalize_name( string $name ): string {
		$name = mb_strtolower( remove_accents( sanitize_text_field( $name ) ) );
		$name = preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $name );

		return trim( preg_replace( '/\s+/u', ' ', (string) $name ) );
	}

	private function is_active( int $person_id ): bool {
		if ( (bool) Fields::get_for_post( $person_id, 'former_member' ) ) {
			return false;
		}

		$lid_tot = Formatter::for_wire(
			'person',
			[ 'lid_tot' => Fields::get_for_post( $person_id, 'lid_tot' ) ]
		)['lid_tot'] ?? '';

		return $lid_tot === '' || $lid_tot >= wp_date( 'Y-m-d' );
	}

	/** @param int[] $candidate_ids
	 * @return array{status:string,person_id:?int,candidate_count:int,candidate_ids:int[]}
	 */
	private function result( string $status, ?int $person_id, array $candidate_ids ): array {
		$candidate_ids   = array_values( array_unique( array_map( 'intval', $candidate_ids ) ) );
		$candidate_count = count( $candidate_ids );

		return compact( 'status', 'person_id', 'candidate_count', 'candidate_ids' );
	}
}
