<?php
/**
 * Sponsor-company relationship helpers.
 */

namespace Rondo\Sponsors;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Keep sponsor contacts single-sourced on sponsor-company posts. */
final class Relations {
	/** @var array<int,array<int,array<string,mixed>>>|null */
	private static ?array $person_index = null;

	/** Return every sponsor relationship for one person. */
	public static function for_person( int $person_id, bool $active_only = false ): array {
		self::build_index();
		$rows = self::$person_index[ $person_id ] ?? [];
		if ( ! $active_only ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => $row['sponsor_status'] === 'publish'
			)
		);
	}

	/** Whether a person has at least one active sponsor-company relationship. */
	public static function is_sponsor_contact( int $person_id ): bool {
		return self::for_person( $person_id, true ) !== [];
	}

	/**
	 * Return person IDs with an active sponsor-company relationship.
	 *
	 * Legacy person flags remain part of the result during the migration window,
	 * so filters keep working before and while the migration is applied.
	 */
	public static function active_person_ids( string $sponsor_role = '', bool $include_legacy = true ): array {
		self::build_index();
		$ids = [];

		foreach ( self::$person_index as $person_id => $relationships ) {
			foreach ( $relationships as $relationship ) {
				if ( $relationship['sponsor_status'] !== 'publish' ) {
					continue;
				}
				if ( $sponsor_role !== '' && $relationship['sponsor_role'] !== $sponsor_role ) {
					continue;
				}
				$ids[] = (int) $person_id;
				break;
			}
		}

		if ( $include_legacy ) {
			$meta_query = [
				[
					'key'   => 'is_sponsor',
					'value' => '1',
				],
			];
			if ( $sponsor_role !== '' ) {
				$meta_query[] = [
					'key'   => 'sponsor_pass_variant',
					'value' => $sponsor_role,
				];
			}

			$ids = array_merge(
				$ids,
				get_posts(
					[
						'post_type'        => 'person',
						'post_status'      => 'publish',
						'posts_per_page'   => -1,
						'fields'           => 'ids',
						'meta_query'       => $meta_query,
						'suppress_filters' => true,
					]
				)
			);
		}

		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		sort( $ids );
		return $ids;
	}

	/** Resolve a person's pass variant from active, pass-enabled relationships. */
	public static function pass_variant_for_person( int $person_id ): string {
		$relationship = self::pass_relationship_for_person( $person_id );
		return $relationship ? (string) $relationship['sponsor_role'] : '';
	}

	/** Resolve the single relationship that owns a person's sponsor pass. */
	public static function pass_relationship_for_person( int $person_id ): ?array {
		$eligible = array_values(
			array_filter(
				self::for_person( $person_id, true ),
				static fn( array $row ): bool => ! empty( $row['receives_pass'] )
			)
		);

		if ( count( $eligible ) === 1 ) {
			return $eligible[0];
		}

		$primary = array_values(
			array_filter(
				$eligible,
				static fn( array $row ): bool => ! empty( $row['is_primary_pass'] )
			)
		);

		return count( $primary ) === 1 ? $primary[0] : null;
	}

	/**
	 * Persist validated contacts and keep a person's primary pass unique globally.
	 *
	 * @return true|\WP_Error
	 */
	public static function set_contacts( int $sponsor_id, array $contacts ) {
		$normalized = self::normalize_contacts( $contacts );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		foreach ( $normalized as $row ) {
			if ( ! empty( $row['is_primary_pass'] ) ) {
				self::clear_other_primary_pass_relations( (int) $row['person_id'], $sponsor_id );
			}
		}

		$result = Fields::update_for_post( $sponsor_id, 'contacts', $normalized );
		self::flush_cache();
		return $result;
	}

	/** Validate and normalize one sponsor company's contact rows. */
	public static function normalize_contacts( array $contacts ) {
		$normalized    = [];
		$seen          = [];
		$primary_count = 0;

		foreach ( $contacts as $index => $row ) {
			if ( ! is_array( $row ) ) {
				return new \WP_Error( 'rondo_sponsor_contact_invalid', sprintf( 'Contactregel %d is ongeldig.', $index + 1 ), [ 'status' => 400 ] );
			}

			$person_id = absint( $row['person_id'] ?? 0 );
			if ( ! $person_id || get_post_type( $person_id ) !== 'person' ) {
				return new \WP_Error( 'rondo_sponsor_person_not_found', sprintf( 'Persoon in contactregel %d bestaat niet.', $index + 1 ), [ 'status' => 400 ] );
			}
			if ( isset( $seen[ $person_id ] ) ) {
				return new \WP_Error( 'rondo_sponsor_contact_duplicate', 'Een persoon kan maar één keer aan dezelfde sponsor zijn gekoppeld.', [ 'status' => 400 ] );
			}
			$seen[ $person_id ] = true;

			$is_primary = ! empty( $row['is_primary'] );
			if ( $is_primary ) {
				++$primary_count;
			}
			if ( $primary_count > 1 ) {
				return new \WP_Error( 'rondo_sponsor_primary_contact_duplicate', 'Een sponsor kan maar één primair contact hebben.', [ 'status' => 400 ] );
			}

			$receives_pass   = ! empty( $row['receives_pass'] );
			$is_primary_pass = $receives_pass && ! empty( $row['is_primary_pass'] );
			$normalized[]    = [
				'person_id'         => $person_id,
				'contact_role'      => sanitize_text_field( (string) ( $row['contact_role'] ?? 'Contactpersoon' ) ) ?: 'Contactpersoon',
				'is_primary'        => $is_primary,
				'receives_pass'     => $receives_pass,
				'is_primary_pass'   => $is_primary_pass,
				'sponsit_person_id' => sanitize_text_field( (string) ( $row['sponsit_person_id'] ?? '' ) ),
			];
		}

		return $normalized;
	}

	/** Drop request-local relationship data after a write. */
	public static function flush_cache(): void {
		self::$person_index = null;
	}

	/** Build a small reverse index; sponsor counts are intentionally modest. */
	private static function build_index(): void {
		if ( self::$person_index !== null ) {
			return;
		}

		self::$person_index = [];
		$sponsor_ids        = get_posts(
			[
				'post_type'        => 'rondo_sponsor',
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
		);

		foreach ( $sponsor_ids as $sponsor_id ) {
			$post     = get_post( $sponsor_id );
			$fields   = Fields::all_for_post( (int) $sponsor_id );
			$contacts = is_array( $fields['contacts'] ?? null ) ? $fields['contacts'] : [];
			foreach ( $contacts as $contact ) {
				$person_id = absint( $contact['person_id'] ?? 0 );
				if ( ! $person_id ) {
					continue;
				}
				self::$person_index[ $person_id ][] = [
					'sponsor_id'        => (int) $sponsor_id,
					'sponsor_name'      => get_the_title( $sponsor_id ),
					'sponsor_status'    => $post ? $post->post_status : 'draft',
					'sponsor_role'      => (string) ( $fields['sponsor_role'] ?? '' ),
					'logo_url'          => get_the_post_thumbnail_url( $sponsor_id, 'medium' ) ?: null,
					'contact_role'      => (string) ( $contact['contact_role'] ?? 'Contactpersoon' ),
					'is_primary'        => ! empty( $contact['is_primary'] ),
					'receives_pass'     => ! empty( $contact['receives_pass'] ),
					'is_primary_pass'   => ! empty( $contact['is_primary_pass'] ),
					'sponsit_person_id' => (string) ( $contact['sponsit_person_id'] ?? '' ),
				];
			}
		}
	}

	/** Clear a person's primary pass flag on every other sponsor company. */
	private static function clear_other_primary_pass_relations( int $person_id, int $except_sponsor_id ): void {
		$sponsor_ids = get_posts(
			[
				'post_type'        => 'rondo_sponsor',
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'exclude'          => [ $except_sponsor_id ],
				'suppress_filters' => true,
			]
		);

		foreach ( $sponsor_ids as $sponsor_id ) {
			$contacts = Fields::get_for_post( (int) $sponsor_id, 'contacts' );
			if ( ! is_array( $contacts ) ) {
				continue;
			}
			$changed = false;
			foreach ( $contacts as &$contact ) {
				if ( (int) ( $contact['person_id'] ?? 0 ) === $person_id && ! empty( $contact['is_primary_pass'] ) ) {
					$contact['is_primary_pass'] = false;
					$changed                    = true;
				}
			}
			unset( $contact );
			if ( $changed ) {
				Fields::update_for_post( (int) $sponsor_id, 'contacts', $contacts );
			}
		}
	}
}
