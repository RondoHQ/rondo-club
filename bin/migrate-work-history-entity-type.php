#!/usr/bin/env php
<?php
/**
 * Migration script to backfill entity_type for existing work history entries.
 *
 * Run via WP-CLI:
 *   wp eval-file bin/migrate-work-history-entity-type.php
 *
 * Or with dry-run first:
 *   wp eval-file bin/migrate-work-history-entity-type.php --dry-run
 */

// Check if running in WP-CLI context
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    echo "This script must be run via WP-CLI: wp eval-file bin/migrate-work-history-entity-type.php\n";
    exit( 1 );
}

// Check for dry-run mode via environment variable
$dry_run = getenv( 'DRY_RUN' ) === '1';

if ( $dry_run ) {
    WP_CLI::log( "DRY RUN MODE - No changes will be made\n" );
}

// Get all people posts
$people = get_posts( [
    'post_type'      => 'person',
    'posts_per_page' => -1,
    'post_status'    => 'any',
] );

WP_CLI::log( sprintf( "Found %d people to process\n", count( $people ) ) );

$updated_count = 0;
$entries_updated = 0;

foreach ( $people as $person ) {
    $work_history = get_field( 'work_history', $person->ID );

    if ( empty( $work_history ) || ! is_array( $work_history ) ) {
        continue;
    }

    $needs_update = false;
    $updated_history = [];

    foreach ( $work_history as $entry ) {
        // Skip entries without a team/entity ID
        if ( empty( $entry['team'] ) ) {
            $updated_history[] = $entry;
            continue;
        }

        // Skip entries that already have entity_type set
        if ( ! empty( $entry['entity_type'] ) ) {
            $updated_history[] = $entry;
            continue;
        }

        // Determine the post type of the referenced entity
        $entity_id = $entry['team'];
        $post_type = get_post_type( $entity_id );

        if ( $post_type === 'team' || $post_type === 'commissie' ) {
            $entry['entity_type'] = $post_type;
            $needs_update = true;
            $entries_updated++;

            $entity_title = get_the_title( $entity_id );
            WP_CLI::log( sprintf(
                "  Person #%d (%s): Setting entity_type='%s' for entity #%d (%s)",
                $person->ID,
                $person->post_title,
                $post_type,
                $entity_id,
                $entity_title
            ) );
        } else {
            WP_CLI::warning( sprintf(
                "  Person #%d (%s): Unknown post type '%s' for entity #%d - skipping",
                $person->ID,
                $person->post_title,
                $post_type ?: 'null',
                $entity_id
            ) );
        }

        $updated_history[] = $entry;
    }

    if ( $needs_update ) {
        if ( ! $dry_run ) {
            update_field( 'work_history', $updated_history, $person->ID );
        }
        $updated_count++;
    }
}

WP_CLI::success( sprintf(
    "%s: Updated %d entries across %d people",
    $dry_run ? 'DRY RUN COMPLETE' : 'MIGRATION COMPLETE',
    $entries_updated,
    $updated_count
) );
