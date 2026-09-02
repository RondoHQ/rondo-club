<?php
/**
 * Exact customer-email matching shared by FreeScout integration callers.
 *
 * @package Rondo\Integrations\FreeScout
 */

namespace Rondo\Integrations\FreeScout;

use Rondo\Core\AccessControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Match only exact canonical Rondo email fields. */
final class PersonMatcher {

	/**
	 * Match customer emails within either the effective user's scope or integration scope.
	 *
	 * @return array{status:string,person_id:?int,candidate_count:int}
	 */
	public function match( array $emails, string $scope = 'integration', int $user_id = 0 ): array {
		$emails = $this->normalize_emails( $emails );
		if ( $emails === [] ) {
			return $this->result( 'no_match', null, 0 );
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

		if ( $scope === 'sidebar' ) {
			$visible = array_values(
				array_filter(
					$candidates,
					static fn( int $person_id ): bool => AccessControl::can_view_person( $person_id, $user_id )
				)
			);
			if ( $visible === [] && $candidates !== [] ) {
				return $this->result( 'inaccessible', null, count( $candidates ) );
			}
			$candidates = $visible;
		}

		if ( count( $candidates ) === 1 ) {
			return $this->result( 'exact', $candidates[0], 1 );
		}
		if ( count( $candidates ) > 1 ) {
			return $this->result( 'ambiguous', null, count( $candidates ) );
		}

		return $this->result( 'no_match', null, 0 );
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

	/** @return array{status:string,person_id:?int,candidate_count:int} */
	private function result( string $status, ?int $person_id, int $candidate_count ): array {
		return compact( 'status', 'person_id', 'candidate_count' );
	}
}
