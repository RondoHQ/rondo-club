<?php
/**
 * Fee snapshot generator.
 *
 * Runs on WordPress via `wp eval-file`. Emits a deterministic JSON array of
 * {person_id, category, base_fee, family_discount, final_fee} rows for every
 * active member, for use as a before/after regression baseline during the
 * v33.0 Fee Service Decomposition milestone.
 *
 * Deliberately read-only: no writes, no emails, no side effects. Safe to run
 * against production. Output is sorted by person_id so diffs are stable.
 *
 * Usage (from repo root):
 *   source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
 *       "cd $DEPLOY_REMOTE_WP_PATH && wp eval-file -" \
 *       < bin/fee-snapshot.php > snapshot.json
 *
 * Or via the bin/fee-snapshot.sh wrapper.
 *
 * @package Rondo\Fees\Tools
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Must run under WordPress (wp eval-file).\n" );
	exit( 1 );
}

if ( ! class_exists( '\Rondo\Fees\FeeServices' ) ) {
	fwrite( STDERR, "Rondo\\Fees\\FeeServices not loaded.\n" );
	exit( 1 );
}

$person_context = \Rondo\Fees\FeeServices::person_context();
$fee_calculator = \Rondo\Fees\FeeServices::fee_calculator();
$season         = \Rondo\Fees\SeasonKey::current();

// Under wp eval-file there is no logged-in user, so the AccessControl
// pre_get_posts hook would otherwise clamp this query down to post__in [0].
// We explicitly opt out since this is a read-only admin snapshot.
$query = new \WP_Query(
	[
		'post_type'        => 'person',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'post_status'      => 'publish',
		'no_found_rows'    => true,
		'orderby'          => 'ID',
		'order'            => 'ASC',
		'suppress_filters' => true,
	]
);

$rows = [];

foreach ( $query->posts as $person_id ) {
	$person_id = (int) $person_id;

	// Skip excluded members (matches calculate_fee semantics).
	if ( get_post_meta( $person_id, '_exclude_from_contributie', true ) ) {
		continue;
	}

	// Former members: include only those who qualify for the current season.
	$is_former = (bool) get_field( 'former_member', $person_id );
	if ( $is_former && ! $person_context->is_former_member_in_season( $person_id, $season ) ) {
		continue;
	}

	// Use calculate_full_fee so family discount + pro-rata are included,
	// matching the numbers real invoices and forecasts use.
	$registration_date = get_field( 'lid-sinds', $person_id );
	if ( ! empty( $registration_date ) && ! is_string( $registration_date ) ) {
		$registration_date = (string) $registration_date;
	}

	$full = $fee_calculator->calculate_full_fee( $person_id, $registration_date ?: null, $season );

	if ( $full === null ) {
		// Unresolvable fee: record the fact so we notice if it changes.
		$rows[] = [
			'person_id'       => $person_id,
			'category'        => null,
			'base_fee'        => null,
			'family_discount' => null,
			'final_fee'       => null,
			'resolvable'      => false,
		];
		continue;
	}

	$rows[] = [
		'person_id'       => $person_id,
		'category'        => $full['category'] ?? null,
		'base_fee'        => isset( $full['base_fee'] ) ? (float) $full['base_fee'] : null,
		'family_discount' => isset( $full['family_discount_amount'] ) ? (float) $full['family_discount_amount'] : 0.0,
		'final_fee'       => isset( $full['final_fee'] ) ? (float) $full['final_fee'] : null,
		'resolvable'      => true,
	];
}

// Deterministic sort: already sorted by person_id via WP_Query, but re-sort
// to be defensive against any WP_Query ordering surprises.
usort(
	$rows,
	static function ( $a, $b ) {
		return $a['person_id'] <=> $b['person_id'];
	}
);

$envelope = [
	'schema_version' => 1,
	'generated_at'   => gmdate( 'c' ),
	'season'         => $season,
	'site_url'       => home_url(),
	'row_count'      => count( $rows ),
	'rows'           => $rows,
];

fwrite( STDOUT, wp_json_encode( $envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
