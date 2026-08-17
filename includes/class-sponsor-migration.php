<?php
/**
 * Idempotent migration from legacy sponsor-person fields to sponsor companies.
 */

namespace Rondo\Sponsors;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Build, audit and apply the sponsor-company migration. */
final class Migration {
	/** Build a reviewable migration plan without writing data. */
	public function plan( array $overrides = [] ): array {
		$person_ids = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'meta_key'         => 'is_sponsor',
				'meta_value'       => '1',
				'suppress_filters' => true,
			]
		);

		$groups = [];
		foreach ( $person_ids as $person_id ) {
			$person_id = (int) $person_id;
			if ( in_array( $person_id, array_map( 'intval', $overrides['skip_person_ids'] ?? [] ), true ) ) {
				continue;
			}

			$fields             = Fields::all_for_post( $person_id );
			$sponsit_contact_id = trim( (string) ( $fields['sponsit_contact_id'] ?? '' ) );
			$company_name       = trim( (string) ( $fields['company_name'] ?? '' ) );
			$company_name       = $this->override_company_name( $company_name, $person_id, $sponsit_contact_id, $overrides );
			$key                = $sponsit_contact_id !== ''
				? 'sponsit:' . $sponsit_contact_id
				: ( $company_name !== '' ? 'company:' . $this->normalize_company_name( $company_name ) : 'review:' . $person_id );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = [
					'key'                => $key,
					'company_names'      => [],
					'person_ids'         => [],
					'roles'              => [],
					'sponsit_contact_id' => $sponsit_contact_id,
					'people'             => [],
				];
			}

			$role = $this->override_sponsor_role(
				(string) ( $fields['sponsor_pass_variant'] ?? '' ),
				$company_name,
				$person_id,
				$sponsit_contact_id,
				$overrides
			);
			if ( $company_name !== '' ) {
				$groups[ $key ]['company_names'][] = $company_name;
			}
			if ( in_array( $role, [ 'businessclub', 'awc_sponsor' ], true ) ) {
				$groups[ $key ]['roles'][] = $role;
			}
			$groups[ $key ]['person_ids'][] = $person_id;
			$groups[ $key ]['people'][]     = [
				'person_id'         => $person_id,
				'name'              => get_the_title( $person_id ),
				'sponsit_person_id' => trim( (string) ( $fields['sponsit_person_id'] ?? '' ) ),
				'company_only'      => in_array( $person_id, array_map( 'intval', $overrides['company_only_person_ids'] ?? [] ), true ),
				'fields'            => $fields,
			];
		}

		$rows = [];
		foreach ( $groups as $group ) {
			$rows[] = $this->finish_group( $group );
		}
		usort( $rows, static fn( array $a, array $b ): int => strcasecmp( $a['company_name'], $b['company_name'] ) );

		return [
			'summary' => [
				'legacy_people' => count( $person_ids ),
				'groups'        => count( $rows ),
				'ready'         => count( array_filter( $rows, static fn( array $row ): bool => $row['decision'] === 'ready' ) ),
				'review'        => count( array_filter( $rows, static fn( array $row ): bool => $row['decision'] === 'review' ) ),
			],
			'groups'  => $rows,
		];
	}

	/** Apply only unambiguous groups; review rows are deliberately left untouched. */
	public function apply( array $overrides = [] ): array {
		$plan    = $this->plan( $overrides );
		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = [];

		foreach ( $plan['groups'] as $group ) {
			if ( $group['decision'] !== 'ready' ) {
				++$skipped;
				continue;
			}

			$sponsor_id = $this->find_existing_sponsor( $group );
			$is_new     = ! $sponsor_id;
			$post_id    = wp_insert_post(
				[
					'ID'          => $sponsor_id,
					'post_type'   => 'rondo_sponsor',
					'post_status' => 'publish',
					'post_title'  => $group['company_name'],
				],
				true
			);
			if ( is_wp_error( $post_id ) ) {
				$errors[] = [
					'key'     => $group['key'],
					'message' => $post_id->get_error_message(),
				];
				continue;
			}

			$company_fields = array_merge(
				$group['address'],
				[
					'sponsor_role'       => $group['sponsor_role'],
					'sponsit_contact_id' => $group['sponsit_contact_id'],
					'legacy_person_ids'  => $group['person_ids'],
				]
			);
			foreach ( $company_fields as $field_name => $value ) {
				Fields::update_for_post( (int) $post_id, $field_name, $value );
			}

			$contact_result = Relations::set_contacts( (int) $post_id, $group['contacts'] );
			if ( is_wp_error( $contact_result ) ) {
				$errors[] = [
					'key'     => $group['key'],
					'message' => $contact_result->get_error_message(),
				];
				continue;
			}

			if ( $is_new && $group['logo_attachment_id'] ) {
				set_post_thumbnail( (int) $post_id, $group['logo_attachment_id'] );
			}
			$is_new ? ++$created : ++$updated;
		}

		return compact( 'created', 'updated', 'skipped', 'errors' );
	}

	/** Turn an accumulated group into one explicit migration decision. */
	private function finish_group( array $group ): array {
		$names   = array_values( array_unique( array_filter( $group['company_names'] ) ) );
		$roles   = array_values( array_unique( array_filter( $group['roles'] ) ) );
		$reasons = [];
		if ( empty( $names ) ) {
			$reasons[] = 'bedrijfsnaam ontbreekt';
		}
		if ( count( $roles ) !== 1 ) {
			$reasons[] = count( $roles ) === 0 ? 'sponsorrol ontbreekt' : 'tegenstrijdige sponsorrollen';
		}
		if ( count( $names ) > 1 ) {
			$reasons[] = 'meerdere bedrijfsnamen binnen dezelfde bron-ID';
		}

		$contacts = [];
		foreach ( $group['people'] as $index => $person ) {
			$fields       = $person['fields'];
			$has_identity = trim( (string) ( $fields['first_name'] ?? '' ) ) !== ''
				|| trim( (string) ( $fields['last_name'] ?? '' ) ) !== ''
				|| trim( (string) ( $fields['email_1'] ?? '' ) ) !== '';
			if ( ! $has_identity && empty( $person['company_only'] ) ) {
				$reasons[] = sprintf( 'persoon %d lijkt een bedrijfsrecord', $person['person_id'] );
				continue;
			}
			if ( ! $has_identity ) {
				continue;
			}
			$contacts[] = [
				'person_id'         => $person['person_id'],
				'contact_role'      => 'Contactpersoon',
				'is_primary'        => $index === 0,
				'receives_pass'     => true,
				'is_primary_pass'   => true,
				'sponsit_person_id' => $person['sponsit_person_id'],
			];
		}

		$address = [];
		foreach ( $group['people'] as $person ) {
			$address = $this->company_address( $person['fields']['addresses'] ?? [] );
			if ( $address ) {
				break;
			}
		}
		$logo_id = 0;
		foreach ( $group['person_ids'] as $person_id ) {
			$logo_id = get_post_thumbnail_id( $person_id );
			if ( $logo_id ) {
				break;
			}
		}

		return [
			'key'                => $group['key'],
			'company_name'       => $names[0] ?? '',
			'sponsor_role'       => $roles[0] ?? '',
			'sponsit_contact_id' => $group['sponsit_contact_id'],
			'person_ids'         => array_values( array_unique( array_map( 'intval', $group['person_ids'] ) ) ),
			'contacts'           => $contacts,
			'address'            => $address,
			'logo_attachment_id' => (int) $logo_id,
			'decision'           => empty( $reasons ) ? 'ready' : 'review',
			'review_reasons'     => array_values( array_unique( $reasons ) ),
		];
	}

	/** Prefer explicitly business-labelled addresses and never copy a home row by accident. */
	private function company_address( $addresses ): array {
		if ( ! is_array( $addresses ) || empty( $addresses ) ) {
			return [];
		}

		$ranked = [];
		foreach ( $addresses as $address ) {
			$label    = strtolower( trim( (string) ( $address['address_label'] ?? '' ) ) );
			$priority = preg_match( '/hoofd|werk|work|bedrijf|post/', $label ) ? 0 : ( preg_match( '/home|thuis|priv/', $label ) ? 2 : 1 );
			$ranked[] = [
				'priority' => $priority,
				'address'  => $address,
			];
		}
		usort( $ranked, static fn( array $a, array $b ): int => $a['priority'] <=> $b['priority'] );
		if ( $ranked[0]['priority'] === 2 ) {
			return [];
		}

		$address = $ranked[0]['address'];
		return [
			'address_street_name'           => (string) ( $address['street_name'] ?? '' ),
			'address_house_number'          => (string) ( $address['house_number'] ?? '' ),
			'address_house_number_addition' => (string) ( $address['house_number_addition'] ?? '' ),
			'address_postal_code'           => (string) ( $address['postal_code'] ?? '' ),
			'address_city'                  => (string) ( $address['city'] ?? '' ),
			'address_country'               => (string) ( $address['country'] ?? '' ),
			'address_country_code'          => (string) ( $address['country_code'] ?? 'NL' ),
		];
	}

	/** Find an already migrated company by stable source ID or legacy-person overlap. */
	private function find_existing_sponsor( array $group ): int {
		if ( $group['sponsit_contact_id'] !== '' ) {
			$matches = get_posts(
				[
					'post_type'        => 'rondo_sponsor',
					'post_status'      => [ 'publish', 'draft' ],
					'posts_per_page'   => 1,
					'fields'           => 'ids',
					'meta_key'         => 'sponsit_contact_id',
					'meta_value'       => $group['sponsit_contact_id'],
					'suppress_filters' => true,
				]
			);
			if ( ! empty( $matches ) ) {
				return (int) $matches[0];
			}
		}

		foreach ( get_posts(
			[
				'post_type'        => 'rondo_sponsor',
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
			) as $sponsor_id ) {
			$legacy_ids = array_map( 'intval', (array) Fields::get_for_post( (int) $sponsor_id, 'legacy_person_ids' ) );
			if ( array_intersect( $legacy_ids, $group['person_ids'] ) ) {
				return (int) $sponsor_id;
			}
		}
		return 0;
	}

	/** Apply explicit human-reviewed company-name mappings. */
	private function override_company_name( string $name, int $person_id, string $sponsit_contact_id, array $overrides ): string {
		$names = is_array( $overrides['company_names'] ?? null ) ? $overrides['company_names'] : [];
		foreach ( [ (string) $person_id, $sponsit_contact_id, $name ] as $key ) {
			if ( $key !== '' && isset( $names[ $key ] ) ) {
				return trim( (string) $names[ $key ] );
			}
		}
		return $name;
	}

	/** Apply an explicit sponsor-role decision to ambiguous legacy groups. */
	private function override_sponsor_role( string $role, string $company_name, int $person_id, string $sponsit_contact_id, array $overrides ): string {
		$roles = is_array( $overrides['sponsor_roles'] ?? null ) ? $overrides['sponsor_roles'] : [];
		foreach ( [ (string) $person_id, $sponsit_contact_id, $company_name ] as $key ) {
			if ( $key !== '' && isset( $roles[ $key ] ) && in_array( $roles[ $key ], [ 'businessclub', 'awc_sponsor' ], true ) ) {
				return $roles[ $key ];
			}
		}
		return $role;
	}

	/** Normalize punctuation and spacing only; do not guess legal-name changes. */
	private function normalize_company_name( string $name ): string {
		$name = strtolower( remove_accents( $name ) );
		$name = preg_replace( '/[^a-z0-9]+/', ' ', $name );
		return trim( preg_replace( '/\s+/', ' ', (string) $name ) );
	}
}
