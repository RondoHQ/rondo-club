#!/usr/bin/env php
<?php
/**
 * WP-CLI command to delete duplicate important_date posts.
 *
 * Duplicates are identified by matching title AND date_value.
 * Keeps the newest (highest ID) and deletes the oldest.
 *
 * Usage:
 *   wp eval-file bin/cleanup-duplicate-dates.php
 *   wp eval-file bin/cleanup-duplicate-dates.php --dry-run
 *
 * @package Stadion
 */

// Check if running in WP-CLI context.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "This script must be run via WP-CLI: wp eval-file bin/cleanup-duplicate-dates.php\n";
	exit( 1 );
}

/**
 * Find and delete duplicate important_date posts.
 *
 * @param bool $dry_run If true, only report duplicates without deleting.
 * @return array Summary of duplicates found and deleted.
 */
function cleanup_duplicate_dates( $dry_run = false ) {
	global $wpdb;

	WP_CLI::log( $dry_run ? 'DRY RUN - No posts will be deleted' : 'Finding and deleting duplicate dates...' );
	WP_CLI::log( '' );

	// Get all important_date posts.
	$dates = get_posts(
		[
			'post_type'        => 'important_date',
			'posts_per_page'   => -1,
			'post_status'      => 'any',
			'suppress_filters' => true,
			'orderby'          => 'ID',
			'order'            => 'DESC', // Newest first.
		]
	);

	WP_CLI::log( sprintf( 'Found %d total important_date posts', count( $dates ) ) );

	// Group by title + date_value.
	$groups = [];
	foreach ( $dates as $date ) {
		$date_value = get_field( 'date_value', $date->ID ) ?: '';
		$key        = $date->post_title . '|' . $date_value;

		if ( ! isset( $groups[ $key ] ) ) {
			$groups[ $key ] = [];
		}
		$groups[ $key ][] = $date;
	}

	// Find groups with duplicates.
	$duplicates_found   = 0;
	$duplicates_deleted = 0;
	$duplicate_groups   = [];

	foreach ( $groups as $key => $group ) {
		if ( count( $group ) > 1 ) {
			$duplicate_groups[ $key ] = $group;
			// First item is newest (kept), rest are duplicates to delete.
			$duplicates_found += count( $group ) - 1;
		}
	}

	if ( empty( $duplicate_groups ) ) {
		WP_CLI::success( 'No duplicate dates found!' );
		return [
			'total_posts'        => count( $dates ),
			'duplicate_groups'   => 0,
			'duplicates_found'   => 0,
			'duplicates_deleted' => 0,
		];
	}

	WP_CLI::log( '' );
	WP_CLI::log( sprintf( 'Found %d duplicate groups with %d total duplicates to delete:', count( $duplicate_groups ), $duplicates_found ) );
	WP_CLI::log( '' );

	foreach ( $duplicate_groups as $key => $group ) {
		list( $title, $date_value ) = explode( '|', $key, 2 );
		$keep    = $group[0]; // Newest (highest ID).
		$delete  = array_slice( $group, 1 ); // Rest are duplicates.

		WP_CLI::log( sprintf( '  "%s" (%s):', $title, $date_value ?: 'no date' ) );
		WP_CLI::log( sprintf( '    Keeping: ID %d', $keep->ID ) );

		foreach ( $delete as $dup ) {
			if ( $dry_run ) {
				WP_CLI::log( sprintf( '    Would delete: ID %d', $dup->ID ) );
			} else {
				$result = wp_delete_post( $dup->ID, true ); // Force delete, bypass trash.
				if ( $result ) {
					WP_CLI::log( sprintf( '    Deleted: ID %d', $dup->ID ) );
					$duplicates_deleted++;
				} else {
					WP_CLI::warning( sprintf( '    Failed to delete: ID %d', $dup->ID ) );
				}
			}
		}
	}

	WP_CLI::log( '' );

	if ( $dry_run ) {
		WP_CLI::success( sprintf( 'DRY RUN complete. Would delete %d duplicates.', $duplicates_found ) );
	} else {
		WP_CLI::success( sprintf( 'Deleted %d duplicate dates.', $duplicates_deleted ) );
	}

	return [
		'total_posts'        => count( $dates ),
		'duplicate_groups'   => count( $duplicate_groups ),
		'duplicates_found'   => $duplicates_found,
		'duplicates_deleted' => $duplicates_deleted,
	];
}

// Check for --dry-run flag via WP-CLI args.
$dry_run = false;
if ( class_exists( 'WP_CLI' ) ) {
	// Get positional args passed after --
	$args = WP_CLI::get_runner()->arguments;
	$dry_run = in_array( '--dry-run', $args, true ) || in_array( 'dry-run', $args, true );
}

// Run the cleanup.
cleanup_duplicate_dates( $dry_run );
