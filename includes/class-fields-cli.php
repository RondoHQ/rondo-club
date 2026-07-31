<?php
/**
 * Read-only field migration audit commands.
 *
 * @package Rondo\Fields
 */

namespace Rondo\Fields;

use Rondo\CustomFields\Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** WP-CLI commands supporting the field-contract migration. */
final class FieldsCli {

	/**
	 * Export production dynamic definitions and populated-value counts.
	 *
	 * This command is read-only. Redirect its JSON output to a protected file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rondo fields export-dynamic > dynamic-fields.json
	 *
	 * @when after_wp_load
	 */
	public function export_dynamic(): void {
		global $wpdb;

		$manager = new Manager();
		$export  = [
			'schema_version' => 1,
			'exported_at'    => gmdate( DATE_RFC3339 ),
			'site_url'       => home_url(),
			'contexts'       => [],
		];

		foreach ( Manager::SUPPORTED_POST_TYPES as $post_type ) {
			$definitions = [];
			foreach ( $manager->get_fields( $post_type, true ) as $field ) {
				$storage_name   = (string) $field['name'];
				$canonical_name = sanitize_key( str_replace( '-', '_', $storage_name ) );
				$populated      = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(DISTINCT pm.post_id)
						 FROM {$wpdb->postmeta} pm
						 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
						 WHERE p.post_type = %s AND pm.meta_key = %s
						 AND pm.meta_value NOT IN ('', '0', 'a:0:{}')",
						$post_type,
						$storage_name
					)
				);

				$definitions[] = [
					'id'              => (string) $field['key'],
					'key'             => (string) $field['key'],
					'storage_name'    => $storage_name,
					'canonical_name'  => $canonical_name,
					'label'           => (string) $field['label'],
					'type'            => (string) $field['type'],
					'active'          => ! isset( $field['active'] ) || (bool) $field['active'],
					'order'           => (int) ( $field['menu_order'] ?? 0 ),
					'populated_posts' => $populated,
					'config'          => $this->definition_config( $field ),
				];
			}
			$export['contexts'][ $post_type ] = $definitions;
		}

		\WP_CLI::line( wp_json_encode( $export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Audit persisted settings for legacy field identifiers.
	 *
	 * This command is read-only and prints JSON suitable for the Phase A report.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rondo fields audit-persisted > persisted-field-references.json
	 *
	 * @when after_wp_load
	 */
	public function audit_persisted(): void {
		global $wpdb;

		$legacy_map = $this->legacy_identifier_map();
		$stores     = [];
		foreach (
			[
				'rondo_people_list_preferences',
				'rondo_people_list_column_order',
				'rondo_people_list_column_widths',
			] as $meta_key
		) {
			$rows        = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
					$meta_key
				),
				ARRAY_A
			);
			$legacy_hits = [];
			foreach ( $rows as $row ) {
				$this->collect_legacy_identifiers( maybe_unserialize( $row['meta_value'] ), $legacy_map, $legacy_hits );
			}
			$stores[] = [
				'store'              => 'user_meta.' . $meta_key,
				'owner'              => 'rondo-club',
				'populated_records'  => count( $rows ),
				'legacy_identifiers' => $legacy_hits,
				'strategy'           => 'Versioned on-read migration plus dry-run/apply WP-CLI migration.',
			];
		}

		$option_rows = $wpdb->get_results(
			"SELECT option_name, option_value FROM {$wpdb->options}
			 WHERE option_name NOT LIKE '\\_transient\\_%'
			 AND option_name NOT LIKE '\\_site\\_transient\\_%'",
			ARRAY_A
		);
		$option_hits = [];
		foreach ( $option_rows as $row ) {
			$hits = [];
			$this->collect_legacy_identifiers( maybe_unserialize( $row['option_value'] ), $legacy_map, $hits );
			if ( ! empty( $hits ) ) {
				$option_hits[ $row['option_name'] ] = $hits;
			}
		}
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

		\WP_CLI::line(
			wp_json_encode(
				[
					'schema_version' => 1,
					'generated_at'   => gmdate( DATE_RFC3339 ),
					'stores'         => $stores,
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			)
		);
	}

	/** @return array<string,mixed> */
	private function definition_config( array $field ): array {
		$keys   = [
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
		$config = [];
		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $field ) ) {
				$config[ $key ] = $field[ $key ];
			}
		}
		return $config;
	}

	/** @return array<string,string> */
	private function legacy_identifier_map(): array {
		$map = [];
		foreach ( Registry::fields_for( 'person' ) as $definition ) {
			if ( $definition['storage_name'] !== null && $definition['storage_name'] !== $definition['canonical_name'] ) {
				$map[ $definition['storage_name'] ] = $definition['canonical_name'];
			}
		}
		return $map + $this->legacy_sort_map();
	}

	/** @return array<string,string> */
	private function legacy_sort_map(): array {
		$map = [];
		foreach ( Registry::fields_for( 'person' ) as $definition ) {
			if ( $definition['storage_name'] !== null ) {
				$map[ 'custom_' . $definition['storage_name'] ] = 'field_' . $definition['canonical_name'];
			}
		}
		return $map;
	}

	/**
	 * @param mixed                $value Value to scan.
	 * @param array<string,string> $legacy_map Map.
	 * @param array<string,string> $hits Collected hits.
	 */
	private function collect_legacy_identifiers( $value, array $legacy_map, array &$hits ): void {
		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( (array) $value as $key => $child ) {
				$this->collect_legacy_identifiers( $key, $legacy_map, $hits );
				$this->collect_legacy_identifiers( $child, $legacy_map, $hits );
			}
			return;
		}
		if ( ! is_string( $value ) ) {
			return;
		}
		if ( isset( $legacy_map[ $value ] ) ) {
			$hits[ $value ] = $legacy_map[ $value ];
		}
	}
}
