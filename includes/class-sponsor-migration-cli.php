<?php
/**
 * WP-CLI adapter for the sponsor-company migration.
 */

namespace Rondo\Sponsors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dry-run by default; explicit --apply for writes. */
final class MigrationCli {
	/**
	 * Audit or migrate legacy sponsor persons.
	 *
	 * ## OPTIONS
	 *
	 * [--apply]
	 * : Create/update sponsor companies. Without this flag, nothing is written.
	 *
	 * [--overrides=<file>]
	 * : JSON file with company_names, sponsor_roles and optional skip/company-only person IDs.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$overrides = [];
		if ( ! empty( $assoc_args['overrides'] ) ) {
			$contents  = file_get_contents( (string) $assoc_args['overrides'] );
			$overrides = json_decode( (string) $contents, true );
			if ( ! is_array( $overrides ) ) {
				\WP_CLI::error( 'Het overridebestand bevat geen geldige JSON.' );
			}
		}

		$migration = new Migration();
		if ( isset( $assoc_args['apply'] ) ) {
			$result = $migration->apply( $overrides );
			\WP_CLI::line( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
			if ( ! empty( $result['errors'] ) ) {
				\WP_CLI::error( 'De migratie is met fouten afgerond.' );
			}
			\WP_CLI::success( 'Sponsoren zijn gemigreerd; reviewgroepen zijn overgeslagen.' );
			return;
		}

		\WP_CLI::line( wp_json_encode( $migration->plan( $overrides ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		\WP_CLI::success( 'Dry-run voltooid; er zijn geen gegevens gewijzigd.' );
	}
}
