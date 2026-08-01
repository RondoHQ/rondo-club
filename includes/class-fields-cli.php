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
	 * Export the exact native dynamic-definition store for backup/restore.
	 *
	 * @subcommand backup-dynamic
	 */
	public function backup_dynamic(): void {
		$manager = new Manager();
		\WP_CLI::line( wp_json_encode( $manager->export_store(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Validate or import a native dynamic-definition backup.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : JSON backup created by backup-dynamic.
	 *
	 * [--apply]
	 * : Persist the import. Omit for a dry-run validation.
	 *
	 * [--replace]
	 * : Replace all definitions instead of merging by immutable field ID.
	 *
	 * @param string[]            $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @subcommand import-dynamic
	 */
	public function import_dynamic( array $args, array $assoc_args ): void {
		$path = $args[0] ?? '';
		if ( $path === '' || ! is_readable( $path ) ) {
			\WP_CLI::error( 'Provide a readable dynamic-field backup file.' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Explicit CLI input path.
		$document = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $document ) ) {
			\WP_CLI::error( 'The backup is not valid JSON.' );
		}
		$apply   = isset( $assoc_args['apply'] );
		$replace = isset( $assoc_args['replace'] );
		$result  = ( new Manager() )->import_store( $document, $replace, $apply );
		if ( is_wp_error( $result ) ) {
			\WP_CLI::error( $result->get_error_message() );
		}
		\WP_CLI::success( $apply ? 'Dynamic field definitions imported.' : 'Dry-run passed; backup is valid.' );
	}

	/**
	 * Export production dynamic definitions and populated-value counts.
	 *
	 * This command is read-only. Redirect its JSON output to a protected file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rondo fields export-dynamic > dynamic-fields.json
	 *
	 * @subcommand export-dynamic
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
				$canonical_name = (string) $field['canonical_name'];
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
	 * @subcommand audit-persisted
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

	/**
	 * Dry-run or apply migration of known persisted user field identifiers.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Persist changes. Without this flag the command is read-only.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rondo fields migrate-persisted
	 *     wp rondo fields migrate-persisted --apply
	 *
	 * @when after_wp_load
	 * @param string[]            $args Positional arguments.
	 * @param array<string,mixed> $assoc_args Named arguments.
	 * @subcommand migrate-persisted
	 */
	public function migrate_persisted( array $args, array $assoc_args ): void {
		global $wpdb;

		$apply      = isset( $assoc_args['apply'] );
		$legacy_map = $this->legacy_identifier_map();
		$changes    = [];
		$users      = [];
		foreach (
			[
				'rondo_people_list_preferences',
				'rondo_people_list_column_order',
				'rondo_people_list_column_widths',
			] as $meta_key
		) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
					$meta_key
				),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$before = maybe_unserialize( $row['meta_value'] );
				$after  = $this->migrate_identifiers( $before, $legacy_map );
				if ( $after === $before ) {
					continue;
				}
				$user_id              = (int) $row['user_id'];
				$users[ $user_id ]    = true;
				$changes[ $meta_key ] = ( $changes[ $meta_key ] ?? 0 ) + 1;
				if ( $apply ) {
					update_user_meta( $user_id, $meta_key, $after );
					update_user_meta( $user_id, 'rondo_people_list_pref_version', 3 );
				}
			}
		}

		\WP_CLI::line(
			wp_json_encode(
				[
					'mode'            => $apply ? 'apply' : 'dry-run',
					'changed_users'   => count( $users ),
					'changed_records' => $changes,
				],
				JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
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
		$manager = new Manager();
		foreach ( $manager->get_fields( 'person', true ) as $field ) {
			if ( $field['storage_key'] !== $field['canonical_name'] ) {
				$map[ $field['storage_key'] ] = $field['canonical_name'];
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
		$manager = new Manager();
		foreach ( $manager->get_fields( 'person', true ) as $field ) {
			$map[ 'custom_' . $field['storage_key'] ] = 'field_' . $field['canonical_name'];
		}
		return $map;
	}

	/**
	 * Recursively migrate exact identifier values and associative keys.
	 *
	 * @param mixed                $value Value to migrate.
	 * @param array<string,string> $legacy_map Known identifier map.
	 * @return mixed
	 */
	private function migrate_identifiers( $value, array $legacy_map ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			$migrated = [];
			foreach ( (array) $value as $key => $child ) {
				$migrated_key              = is_string( $key ) ? ( $legacy_map[ $key ] ?? $key ) : $key;
				$migrated[ $migrated_key ] = $this->migrate_identifiers( $child, $legacy_map );
			}
			return $migrated;
		}
		if ( is_string( $value ) && isset( $legacy_map[ $value ] ) ) {
			return $legacy_map[ $value ];
		}
		return $value;
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
