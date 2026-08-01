<?php
/**
 * Prevent deleting people while they still participate in active relationships.
 */

namespace Rondo\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PersonDeletionGuard {

	public function __construct() {
		add_filter( 'pre_trash_post', [ $this, 'prevent_trash' ], 10, 3 );
		add_filter( 'pre_delete_post', [ $this, 'prevent_delete' ], 10, 3 );
		add_filter( 'rest_pre_dispatch', [ $this, 'prevent_rest_delete' ], 10, 3 );
	}

	/**
	 * Block moving a referenced person to the trash.
	 *
	 * @param mixed         $trash           Existing short-circuit value.
	 * @param \WP_Post      $post            Post being trashed.
	 * @param string|null   $previous_status Status before trashing (WordPress 6.3+).
	 * @return mixed
	 */
	public function prevent_trash( $trash, $post, $previous_status = null ) {
		unset( $previous_status );

		if ( $trash !== null || ! $this->is_blocked_person( $post ) ) {
			return $trash;
		}

		return false;
	}

	/**
	 * Block permanently deleting a referenced person.
	 *
	 * @param mixed    $delete       Existing short-circuit value.
	 * @param \WP_Post $post         Post being deleted.
	 * @param bool     $force_delete Whether the deletion is permanent.
	 * @return mixed
	 */
	public function prevent_delete( $delete, $post, $force_delete ) {
		unset( $force_delete );

		if ( $delete !== null || ! $this->is_blocked_person( $post ) ) {
			return $delete;
		}

		return false;
	}

	/**
	 * Return a descriptive REST error before WordPress reaches its generic delete error.
	 *
	 * @param mixed            $result  Existing pre-dispatch result.
	 * @param \WP_REST_Server  $server  REST server.
	 * @param \WP_REST_Request $request Current request.
	 * @return mixed
	 */
	public function prevent_rest_delete( $result, $server, $request ) {
		unset( $server );

		if ( $result !== null || $request->get_method() !== 'DELETE' ) {
			return $result;
		}

		if ( ! preg_match( '#^/wp/v2/people/(\d+)$#', $request->get_route(), $matches ) ) {
			return $result;
		}

		$person_id = (int) $matches[1];
		if ( ! current_user_can( 'delete_post', $person_id ) ) {
			return $result;
		}

		$relationships = $this->get_active_relationships( $person_id );
		if ( empty( $relationships ) ) {
			return $result;
		}

		$names = wp_list_pluck( $relationships, 'name' );

		return new \WP_Error(
			'rondo_person_has_relationships',
			sprintf(
				/* translators: %s is a comma-separated list of related people. */
				__( 'Deze persoon kan niet worden verwijderd zolang er nog een relatie bestaat met %s. Verwijder of corrigeer eerst de relatie op het persoonsprofiel.', 'rondo' ),
				implode( ', ', $names )
			),
			[
				'status'         => 409,
				'related_people' => $relationships,
			]
		);
	}

	/**
	 * Find relationships from this person to people that are not in the trash.
	 *
	 * InverseRelationships mirrors normal relationship updates, so checking the
	 * person's own repeater covers both the original and inverse side without a
	 * club-wide query during deletion.
	 *
	 * @param int $person_id Person post ID.
	 * @return array<int, array{id: int, name: string, relationship_type: string}>
	 */
	public function get_active_relationships( int $person_id ): array {
		$rows = \Rondo\Fields\Fields::get_for_post( $person_id, 'relationships' );
		if ( ! is_array( $rows ) ) {
			return [];
		}

		$relationships = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$related_id = $this->extract_id( $row['related_person'] ?? null, 'ID' );
			if ( ! $related_id || isset( $relationships[ $related_id ] ) ) {
				continue;
			}

			$related = get_post( $related_id );
			if ( ! $related || $related->post_type !== 'person' || $related->post_status === 'trash' ) {
				continue;
			}

			$type_id = $this->extract_id( $row['relationship_type'] ?? null, 'term_id' );
			$type    = $type_id ? get_term( $type_id, 'relationship_type' ) : null;

			/* translators: %d is a WordPress person post ID. */
			$fallback_name = sprintf( __( 'Persoon %d', 'rondo' ), $related_id );

			$relationships[ $related_id ] = [
				'id'                => $related_id,
				'name'              => $related->post_title ?: $fallback_name,
				'relationship_type' => $type instanceof \WP_Term ? $type->name : '',
			];
		}

		return array_values( $relationships );
	}

	private function is_blocked_person( $post ): bool {
		return $post instanceof \WP_Post
			&& $post->post_type === 'person'
			&& ! empty( $this->get_active_relationships( (int) $post->ID ) );
	}

	/**
	 * Extract an ID from the native field scalar, object, or array return formats.
	 *
	 * @param mixed  $value Value returned by native field.
	 * @param string $key   Object/array key containing the ID.
	 * @return int|null
	 */
	private function extract_id( $value, string $key ): ?int {
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}

		if ( is_object( $value ) && isset( $value->{$key} ) ) {
			return (int) $value->{$key};
		}

		if ( is_array( $value ) && isset( $value[ $key ] ) ) {
			return (int) $value[ $key ];
		}

		return null;
	}
}
