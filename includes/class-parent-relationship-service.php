<?php
/**
 * Parent/guardian relationship creation and Sportlink status storage.
 */

namespace Rondo\People;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ParentRelationshipService {

	private const STATUS_META_KEY = '_rondo_parent_sync_statuses';

	/**
	 * Add an existing or newly created parent to a child.
	 *
	 * @param int   $child_id Child person post ID.
	 * @param array $payload  Validated request payload.
	 * @return array|\WP_Error
	 */
	public function add_parent( int $child_id, array $payload ) {
		$child = get_post( $child_id );
		if ( ! $child || $child->post_type !== 'person' ) {
			return new \WP_Error( 'rondo_parent_child_not_found', __( 'Het kind is niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		$knvb_id = trim( (string) Fields::get_for_post( $child_id, 'knvb_id' ) );
		if ( $knvb_id === '' ) {
			return new \WP_Error( 'rondo_parent_child_without_knvb_id', __( 'Dit kind is niet aan Sportlink gekoppeld.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( (bool) Fields::get_for_post( $child_id, 'former_member' ) ) {
			return new \WP_Error( 'rondo_parent_former_member', __( 'Bij een oud-lid kunnen geen oudergegevens naar Sportlink worden geschreven.', 'rondo' ), [ 'status' => 409 ] );
		}

		$parent_term = get_term_by( 'slug', 'parent', 'relationship_type' );
		if ( ! $parent_term || is_wp_error( $parent_term ) ) {
			return new \WP_Error( 'rondo_parent_type_missing', __( 'Het relatietype ouder/verzorger ontbreekt.', 'rondo' ), [ 'status' => 500 ] );
		}

		$relationships = Fields::get_for_post( $child_id, 'relationships' ) ?: [];
		$parent_rows   = $this->parent_rows( $relationships, (int) $parent_term->term_id );
		if ( count( $parent_rows ) >= 2 ) {
			return new \WP_Error( 'rondo_parent_slots_full', __( 'Dit kind heeft al twee ouders/verzorgers; beide Sportlink-velden zijn daarmee gereserveerd.', 'rondo' ), [ 'status' => 409 ] );
		}

		$mode              = sanitize_key( (string) ( $payload['mode'] ?? '' ) );
		$created_parent_id = 0;
		if ( $mode === 'existing' ) {
			$parent_id  = absint( $payload['parent_id'] ?? 0 );
			$validation = $this->validate_existing_parent( $child_id, $parent_id, $parent_rows );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
		} elseif ( $mode === 'new' ) {
			$created = $this->create_parent( $payload );
			if ( is_wp_error( $created ) ) {
				return $created;
			}
			$parent_id         = $created;
			$created_parent_id = $created;
		} else {
			return new \WP_Error( 'rondo_parent_invalid_mode', __( 'Kies een bestaande of een nieuwe ouder/verzorger.', 'rondo' ), [ 'status' => 400 ] );
		}

		$parent_email = $this->person_email( $parent_id );
		foreach ( $parent_rows as $row ) {
			$linked_id = $this->relationship_person_id( $row );
			if ( $linked_id && $this->person_email( $linked_id ) === $parent_email ) {
				if ( $created_parent_id ) {
					wp_delete_post( $created_parent_id, true );
				}
				return new \WP_Error( 'rondo_parent_email_already_linked', __( 'Een ouder/verzorger met dit e-mailadres is al gekoppeld.', 'rondo' ), [ 'status' => 409 ] );
			}
		}

		$relationships[] = [
			'related_person'     => $parent_id,
			'relationship_type'  => (int) $parent_term->term_id,
			'relationship_label' => __( 'Ouder/verzorger', 'rondo' ),
		];
		$saved           = Fields::update_many_for_post( $child_id, [ 'relationships' => $relationships ] );
		if ( is_wp_error( $saved ) || $saved !== true ) {
			if ( $created_parent_id ) {
				wp_delete_post( $created_parent_id, true );
			}
			return is_wp_error( $saved )
				? $saved
				: new \WP_Error( 'rondo_parent_relationship_failed', __( 'De ouderrelatie kon niet worden opgeslagen.', 'rondo' ), [ 'status' => 500 ] );
		}

		$this->set_sync_status( $child_id, $parent_id, 'pending', null, '' );
		// Native field writes do not update post_modified. Touch the child so the
		// independent rondo-sync cursor can discover this relationship promptly.
		wp_update_post( [ 'ID' => $child_id ] );

		return [
			'child_id'  => $child_id,
			'parent_id' => $parent_id,
			'created'   => (bool) $created_parent_id,
			'status'    => 'pending',
		];
	}

	/**
	 * Store a callback from rondo-sync without touching post_modified.
	 */
	public function set_sync_status( int $child_id, int $parent_id, string $state, ?int $slot, string $message ): bool {
		$allowed = [ 'pending', 'synced', 'error' ];
		if ( ! in_array( $state, $allowed, true ) ) {
			return false;
		}
		$statuses    = get_post_meta( $child_id, self::STATUS_META_KEY, true );
		$statuses    = is_array( $statuses ) ? $statuses : [];
		$next_status = [
			'parent_id'  => $parent_id,
			'state'      => $state,
			'slot'       => in_array( $slot, [ 1, 2 ], true ) ? $slot : null,
			'message'    => sanitize_text_field( $message ),
			'updated_at' => gmdate( 'c' ),
		];
		$current     = $statuses[ $parent_id ] ?? [];
		if ( ( $current['state'] ?? '' ) === $next_status['state']
			&& ( $current['slot'] ?? null ) === $next_status['slot']
			&& ( $current['message'] ?? '' ) === $next_status['message'] ) {
			return true;
		}
		$statuses[ $parent_id ] = $next_status;
		return (bool) update_post_meta( $child_id, self::STATUS_META_KEY, $statuses );
	}

	/** Return statuses as a stable list for REST consumers. */
	public function get_sync_statuses( int $child_id ): array {
		$statuses = get_post_meta( $child_id, self::STATUS_META_KEY, true );
		if ( ! is_array( $statuses ) ) {
			return [];
		}
		return array_values( $statuses );
	}

	private function validate_existing_parent( int $child_id, int $parent_id, array $parent_rows ) {
		$parent = get_post( $parent_id );
		if ( ! $parent || $parent->post_type !== 'person' ) {
			return new \WP_Error( 'rondo_parent_not_found', __( 'De gekozen ouder/verzorger is niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $parent_id === $child_id ) {
			return new \WP_Error( 'rondo_parent_self_relation', __( 'Een persoon kan niet diens eigen ouder/verzorger zijn.', 'rondo' ), [ 'status' => 400 ] );
		}
		foreach ( $parent_rows as $row ) {
			if ( $this->relationship_person_id( $row ) === $parent_id ) {
				return new \WP_Error( 'rondo_parent_already_linked', __( 'Deze ouder/verzorger is al gekoppeld.', 'rondo' ), [ 'status' => 409 ] );
			}
		}
		if ( $this->person_name( $parent_id ) === '' || $this->person_email( $parent_id ) === '' ) {
			return new \WP_Error( 'rondo_parent_contact_required', __( 'De gekozen persoon moet een naam en e-mailadres hebben.', 'rondo' ), [ 'status' => 400 ] );
		}
		return true;
	}

	private function create_parent( array $payload ) {
		$name  = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( (string) ( $payload['name'] ?? '' ) ) ) );
		$email = sanitize_email( (string) ( $payload['email'] ?? '' ) );
		$phone = sanitize_text_field( (string) ( $payload['phone'] ?? '' ) );
		if ( $name === '' ) {
			return new \WP_Error( 'rondo_parent_name_required', __( 'Vul de naam van de ouder/verzorger in.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'rondo_parent_email_required', __( 'Vul een geldig e-mailadres in.', 'rondo' ), [ 'status' => 400 ] );
		}

		$existing_id = $this->find_person_by_email( $email );
		if ( $existing_id ) {
			return new \WP_Error(
				'rondo_parent_email_exists',
				__( 'Er bestaat al een persoon met dit e-mailadres. Kies die persoon via “Bestaande persoon”.', 'rondo' ),
				[
					'status'             => 409,
					'existing_person_id' => $existing_id,
				]
			);
		}

		$parent_id = wp_insert_post(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
				'post_title'  => $name,
				'post_author' => get_current_user_id(),
			],
			true
		);
		if ( is_wp_error( $parent_id ) ) {
			return $parent_id;
		}

		$fields = [
			'first_name'  => $name,
			'last_name'   => '',
			'email_1'     => strtolower( $email ),
			'person_type' => 'member',
		];
		if ( $phone !== '' ) {
			$fields['telephone_1'] = $phone;
		}
		$saved = Fields::update_many_for_post( (int) $parent_id, $fields );
		if ( is_wp_error( $saved ) || $saved !== true ) {
			wp_delete_post( (int) $parent_id, true );
			return is_wp_error( $saved )
				? $saved
				: new \WP_Error( 'rondo_parent_create_failed', __( 'De ouder/verzorger kon niet worden opgeslagen.', 'rondo' ), [ 'status' => 500 ] );
		}
		return (int) $parent_id;
	}

	private function parent_rows( array $relationships, int $parent_term_id ): array {
		return array_values(
			array_filter(
				$relationships,
				static function ( $row ) use ( $parent_term_id ) {
					$type = $row['relationship_type'] ?? 0;
					if ( is_object( $type ) && isset( $type->term_id ) ) {
						$type = $type->term_id;
					} elseif ( is_array( $type ) && isset( $type['term_id'] ) ) {
						$type = $type['term_id'];
					}
					return (int) $type === $parent_term_id;
				}
			)
		);
	}

	private function relationship_person_id( array $row ): int {
		$value = $row['related_person'] ?? $row['related_person_id'] ?? 0;
		if ( is_object( $value ) && isset( $value->ID ) ) {
			$value = $value->ID;
		}
		return absint( $value );
	}

	private function person_name( int $person_id ): string {
		return trim(
			preg_replace(
				'/\s+/',
				' ',
				implode(
					' ',
					array_filter(
						[
							Fields::get_for_post( $person_id, 'first_name' ),
							Fields::get_for_post( $person_id, 'infix' ),
							Fields::get_for_post( $person_id, 'last_name' ),
						]
					)
				)
			)
		);
	}

	private function person_email( int $person_id ): string {
		$email = Fields::get_for_post( $person_id, 'email_1' ) ?: Fields::get_for_post( $person_id, 'email_2' );
		return strtolower( trim( (string) $email ) );
	}

	private function find_person_by_email( string $email ): int {
		$person_ids = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		$needle     = strtolower( trim( $email ) );
		foreach ( $person_ids as $person_id ) {
			if ( $this->person_email( (int) $person_id ) === $needle ) {
				return (int) $person_id;
			}
		}
		return 0;
	}
}
