<?php
/**
 * Read-only production evidence commands for the ACF removal cutover.
 *
 * @package Rondo\Fields
 */

namespace Rondo\Fields;

use Rondo\CustomFields\Manager;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Export field definitions and audit persisted field identifiers without writes. */
final class AcfPreflightCli {
	/** Definition properties required for the cutover evidence. */
	private const CONFIG_KEYS = [
		'instructions',
		'required',
		'unique',
		'choices',
		'default_value',
		'placeholder',
		'min',
		'max',
		'step',
		'prepend',
		'append',
		'allow_null',
		'multiple',
		'ui',
		'layout',
		'maxlength',
		'ui_on_text',
		'ui_off_text',
		'return_format',
		'display_format',
		'post_type',
		'filters',
		'editable_in_ui',
	];

	/** User-meta stores known to persist field-derived identifiers. */
	private const USER_META_KEYS = [
		'rondo_people_list_preferences',
		'rondo_people_list_column_order',
		'rondo_people_list_column_widths',
	];

	/**
	 * Export production dynamic definitions and value-population counts.
	 *
	 * This command is read-only and deliberately excludes field values.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rondo acf-preflight export-dynamic > dynamic-fields.json
	 *
	 * @when after_wp_load
	 */
	public function export_dynamic(): void {
		$this->write_json( $this->build_dynamic_export() );
	}

	/**
	 * Audit persisted settings for legacy field identifiers.
	 *
	 * This command is read-only and deliberately excludes stored values.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rondo acf-preflight audit-persisted > persisted-field-references.json
	 *
	 * @when after_wp_load
	 */
	public function audit_persisted(): void {
		$this->write_json( $this->build_persisted_audit() );
	}

	/** Build the dynamic-definition export document. */
	public function build_dynamic_export(): array {
		global $wpdb;

		$this->assert_acf_available();

		$manager = new Manager();
		$export  = [
			'schema_version' => 1,
			'exported_at'    => gmdate( DATE_RFC3339 ),
			'site_url'       => home_url(),
			'source'         => 'acf-production-preflight',
			'contexts'       => [],
		];

		foreach ( Manager::SUPPORTED_POST_TYPES as $post_type ) {
			$definitions = [];
			foreach ( $manager->get_fields( $post_type, true ) as $field ) {
				$storage_name   = (string) ( $field['rondo_storage_key'] ?? $field['name'] ?? '' );
				$canonical_name = (string) ( $field['rondo_canonical_name'] ?? $this->canonical_name( $storage_name ) );
				$counts         = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT pm.post_id) AS stored_posts,
							COUNT(DISTINCT CASE
								WHEN pm.meta_value NOT IN ('', '0', 'a:0:{}') THEN pm.post_id
							END) AS populated_posts
						 FROM {$wpdb->postmeta} pm
						 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						 WHERE p.post_type = %s AND pm.meta_key = %s",
						$post_type,
						$storage_name
					),
					ARRAY_A
				);

				$definitions[] = [
					'id'              => (string) ( $field['key'] ?? '' ),
					'key'             => (string) ( $field['key'] ?? '' ),
					'storage_name'    => $storage_name,
					'canonical_name'  => $canonical_name,
					'label'           => (string) ( $field['label'] ?? '' ),
					'type'            => (string) ( $field['type'] ?? '' ),
					'active'          => ! isset( $field['active'] ) || (bool) $field['active'],
					'order'           => (int) ( $field['menu_order'] ?? 0 ),
					'stored_posts'    => (int) ( $counts['stored_posts'] ?? 0 ),
					'populated_posts' => (int) ( $counts['populated_posts'] ?? 0 ),
					'config'          => $this->definition_config( $field ),
				];
			}
			$export['contexts'][ $post_type ] = $definitions;
		}

		return $export;
	}

	/** Build the persisted-reference audit document. */
	public function build_persisted_audit(): array {
		global $wpdb;

		$this->assert_acf_available();

		$legacy_map = $this->legacy_identifier_map();
		$stores     = [];
		foreach ( self::USER_META_KEYS as $meta_key ) {
			$rows        = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
					$meta_key
				),
				ARRAY_A
			);
			$legacy_hits = [];
			$hit_records = 0;
			foreach ( $rows as $row ) {
				$row_hits = [];
				$this->collect_legacy_identifiers( maybe_unserialize( $row['meta_value'] ), $legacy_map, $row_hits );
				if ( ! empty( $row_hits ) ) {
					++$hit_records;
					$legacy_hits += $row_hits;
				}
			}
			ksort( $legacy_hits );
			$stores[] = [
				'store'                           => 'user_meta.' . $meta_key,
				'owner'                           => 'rondo-club',
				'populated_records'               => count( $rows ),
				'records_with_legacy_identifiers' => $hit_records,
				'legacy_identifiers'              => $legacy_hits,
				'strategy'                        => 'Versioned on-read migration plus dry-run/apply WP-CLI migration.',
			];
		}

		$option_hits = [];
		$last_id     = 0;
		do {
			$option_rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_id, option_name, option_value FROM {$wpdb->options}
					 WHERE option_id > %d
					 AND option_name NOT LIKE '\\_transient\\_%%'
					 AND option_name NOT LIKE '\\_site\\_transient\\_%%'
					 ORDER BY option_id ASC LIMIT 500",
					$last_id
				),
				ARRAY_A
			);
			foreach ( $option_rows as $row ) {
				$last_id = (int) $row['option_id'];
				$hits    = [];
				$this->collect_legacy_identifiers( maybe_unserialize( $row['option_value'] ), $legacy_map, $hits );
				if ( ! empty( $hits ) ) {
					ksort( $hits );
					$option_hits[ $row['option_name'] ] = $hits;
				}
			}
		} while ( count( $option_rows ) === 500 );

		ksort( $option_hits );
		$stores[] = [
			'store'              => 'wp_options (including cron arguments)',
			'owner'              => 'WordPress/Rondo integrations',
			'populated_records'  => count( $option_hits ),
			'legacy_identifiers' => $option_hits,
			'strategy'           => 'Review every populated hit; migrate only known field-bearing schemas.',
		];
		$stores[] = [
			'store'              => 'browser.localStorage',
			'owner'              => 'React app',
			'populated_records'  => null,
			'legacy_identifiers' => [ 'stadion_column_widths', 'rondo-col-* values' ],
			'strategy'           => 'One-time in-browser key/value migration; population is not server-observable.',
		];
		$stores[] = [
			'store'              => 'URL query/bookmarks',
			'owner'              => 'React app and external bookmarks',
			'populated_records'  => null,
			'legacy_identifiers' => array_keys( $this->legacy_sort_map() ),
			'strategy'           => 'Generate canonical aliases and accept legacy aliases for a bounded period.',
		];

		return [
			'schema_version' => 1,
			'generated_at'   => gmdate( DATE_RFC3339 ),
			'site_url'       => home_url(),
			'source'         => 'acf-production-preflight',
			'stores'         => $stores,
		];
	}

	/** Fail clearly if the production ACF provider is unavailable. */
	private function assert_acf_available(): void {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			throw new RuntimeException( 'ACF must be active to run the production preflight.' );
		}
	}

	/** Print a JSON document through WP-CLI. */
	private function write_json( array $document ): void {
		try {
			$json = wp_json_encode( $document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR );
		} catch ( \JsonException $exception ) {
			\WP_CLI::error( 'Could not encode the preflight report: ' . $exception->getMessage() );
			return;
		}
		\WP_CLI::line( $json );
	}

	/** Return the future canonical form of a storage name. */
	private function canonical_name( string $storage_name ): string {
		return sanitize_key( str_replace( '-', '_', $storage_name ) );
	}

	/** Return the auditable configuration subset of an ACF field. */
	private function definition_config( array $field ): array {
		$config = [];
		foreach ( self::CONFIG_KEYS as $key ) {
			if ( array_key_exists( $key, $field ) ) {
				$config[ $key ] = $field[ $key ];
			}
		}
		return $config;
	}

	/** Build storage-to-canonical mappings from every active production field group. */
	private function field_identifier_map(): array {
		$map = [];
		foreach ( acf_get_field_groups() as $group ) {
			$fields = acf_get_fields( $group );
			if ( is_array( $fields ) ) {
				$this->collect_field_identifiers( $fields, $map );
			}
		}
		ksort( $map );
		return $map;
	}

	/** Recursively collect field names, including repeater sub-fields. */
	private function collect_field_identifiers( array $fields, array &$map ): void {
		foreach ( $fields as $field ) {
			$storage = (string) ( $field['rondo_storage_key'] ?? $field['name'] ?? '' );
			if ( $storage !== '' ) {
				$map[ $storage ] = (string) ( $field['rondo_canonical_name'] ?? $this->canonical_name( $storage ) );
			}
			if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
				$this->collect_field_identifiers( $field['sub_fields'], $map );
			}
		}
	}

	/** Return known legacy names and legacy sort aliases. */
	private function legacy_identifier_map(): array {
		$field_map = $this->field_identifier_map();
		$legacy    = [];
		foreach ( $field_map as $storage => $canonical ) {
			if ( $storage !== $canonical ) {
				$legacy[ $storage ] = $canonical;
			}
		}
		return $legacy + $this->legacy_sort_map( $field_map );
	}

	/** Return old list-sort identifiers and their canonical replacements. */
	private function legacy_sort_map( ?array $field_map = null ): array {
		$field_map = $field_map ?? $this->field_identifier_map();
		$sort_map  = [];
		foreach ( $field_map as $storage => $canonical ) {
			$sort_map[ 'custom_' . $storage ] = 'field_' . $canonical;
		}
		ksort( $sort_map );
		return $sort_map;
	}

	/** Recursively collect exact legacy identifiers from keys and values. */
	private function collect_legacy_identifiers( $value, array $legacy_map, array &$hits ): void {
		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( (array) $value as $key => $child ) {
				$this->collect_legacy_identifiers( $key, $legacy_map, $hits );
				$this->collect_legacy_identifiers( $child, $legacy_map, $hits );
			}
			return;
		}
		if ( is_string( $value ) && isset( $legacy_map[ $value ] ) ) {
			$hits[ $value ] = $legacy_map[ $value ];
		}
	}
}
