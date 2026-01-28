<?php
/**
 * Fix entity_type for person 3948
 * Run via: wp eval-file bin/fix-person-3948.php
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
    echo "Run via WP-CLI: wp eval-file bin/fix-person-3948.php\n";
    exit( 1 );
}

$person_id = 3948;
$work_history = get_field( 'work_history', $person_id );

if ( empty( $work_history ) ) {
    WP_CLI::error( "No work history found for person $person_id" );
}

WP_CLI::log( "Found " . count( $work_history ) . " work history entries" );

$updated = [];
$changes = 0;

foreach ( $work_history as $entry ) {
    if ( ! empty( $entry['team'] ) && empty( $entry['entity_type'] ) ) {
        $post_type = get_post_type( $entry['team'] );
        WP_CLI::log( "  Team {$entry['team']} ({$entry['job_title']}): setting entity_type to '$post_type'" );
        $entry['entity_type'] = $post_type;
        $changes++;
    }
    $updated[] = $entry;
}

if ( $changes > 0 ) {
    update_field( 'work_history', $updated, $person_id );
    WP_CLI::success( "Updated $changes entries for person $person_id" );

    // Verify
    $verify = get_field( 'work_history', $person_id );
    WP_CLI::log( "\nVerification:" );
    foreach ( $verify as $entry ) {
        $type = $entry['entity_type'] ?? 'NOT SET';
        WP_CLI::log( "  Team {$entry['team']}: entity_type = $type" );
    }
} else {
    WP_CLI::log( "No changes needed" );
}
