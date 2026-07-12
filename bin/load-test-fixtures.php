<?php
/**
 * Seed and clean synthetic volunteer load-test fixtures.
 *
 * Usage (demo only):
 *   RONDO_LOAD_TEST_PASSWORD='...' wp eval-file \
 *     wp-content/themes/rondo-club/bin/load-test-fixtures.php seed 100
 *   wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php reset
 *   wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php verify 20
 *   wp eval-file wp-content/themes/rondo-club/bin/load-test-fixtures.php cleanup
 *
 * @package Rondo
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

const RONDO_LOAD_TEST_META_KEY = '_rondo_load_test_fixture';
const RONDO_LOAD_TEST_OPTION   = 'rondo_load_test_fixture_state';

/**
 * Abort unless this is the dedicated demo site.
 */
function rondo_load_test_require_demo(): void {
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	if ( $host !== 'demo.rondo.club' || ! get_option( 'rondo_is_demo_site', false ) ) {
		WP_CLI::error( 'Load-test fixtures may only run on demo.rondo.club in demo mode.' );
	}
}

/**
 * Find fixture posts for a post type.
 *
 * @param string $post_type Post type.
 * @return int[]
 */
function rondo_load_test_post_ids( string $post_type ): array {
	return array_map(
		'intval',
		get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => RONDO_LOAD_TEST_META_KEY,
				'meta_value'     => '1',
			]
		)
	);
}

/**
 * Find fixture users.
 *
 * @return int[]
 */
function rondo_load_test_user_ids(): array {
	return array_map(
		'intval',
		get_users(
			[
				'fields'     => 'ID',
				'meta_key'   => RONDO_LOAD_TEST_META_KEY,
				'meta_value' => '1',
			]
		)
	);
}

/**
 * Delete all generated fixtures.
 */
function rondo_load_test_cleanup(): void {
	if ( ! function_exists( 'wp_delete_user' ) ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
	}

	foreach ( rondo_load_test_user_ids() as $user_id ) {
		wp_delete_user( $user_id );
	}

	foreach ( [ 'dienst_shift', 'dienst_type', 'person' ] as $post_type ) {
		foreach ( rondo_load_test_post_ids( $post_type ) as $post_id ) {
			wp_delete_post( $post_id, true );
		}
	}

	delete_option( RONDO_LOAD_TEST_OPTION );
	WP_CLI::success( 'Synthetic load-test fixtures removed.' );
}

/**
 * Reset signups while keeping reusable users and shifts.
 */
function rondo_load_test_reset(): void {
	$person_ids = rondo_load_test_post_ids( 'person' );
	$shift_ids  = rondo_load_test_post_ids( 'dienst_shift' );

	foreach ( $shift_ids as $shift_id ) {
		update_post_meta( $shift_id, 'assigned_persons', [] );
		update_post_meta( $shift_id, 'status', 'open' );
		foreach ( $person_ids as $person_id ) {
			delete_post_meta( $shift_id, '_shift_signup_at_' . $person_id );
		}
	}

	WP_CLI::success( sprintf( 'Reset %d synthetic shifts.', count( $shift_ids ) ) );
}

/**
 * Create or update a fixture person.
 *
 * @param int $index Fixture index.
 * @return int Person ID.
 */
function rondo_load_test_upsert_person( int $index ): int {
	$existing = get_posts(
		[
			'post_type'      => 'person',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_key'       => '_rondo_load_test_index',
			'meta_value'     => (string) $index,
		]
	);

	$post_data = [
		'post_type'   => 'person',
		'post_status' => 'publish',
		'post_title'  => sprintf( 'Loadtest Vrijwilliger %03d', $index ),
	];
	if ( ! empty( $existing ) ) {
		$post_data['ID'] = (int) $existing[0];
	}

	$person_id = wp_insert_post( $post_data, true );
	if ( is_wp_error( $person_id ) ) {
		WP_CLI::error( $person_id );
	}

	update_post_meta( $person_id, RONDO_LOAD_TEST_META_KEY, '1' );
	update_post_meta( $person_id, '_rondo_load_test_index', $index );
	update_post_meta( $person_id, 'first_name', 'Loadtest' );
	update_post_meta( $person_id, 'last_name', sprintf( 'Vrijwilliger %03d', $index ) );
	update_post_meta( $person_id, 'former_member', '0' );
	update_post_meta( $person_id, 'leeftijdsgroep', 'Senioren' );

	return (int) $person_id;
}

/**
 * Create or update a fixture user.
 *
 * @param int    $index     Fixture index.
 * @param int    $person_id Linked person ID.
 * @param string $password  Shared demo-only password.
 * @return int User ID.
 */
function rondo_load_test_upsert_user( int $index, int $person_id, string $password ): int {
	$login = sprintf( 'loadtest_volunteer_%03d', $index );
	$user  = get_user_by( 'login', $login );

	if ( $user && get_user_meta( $user->ID, RONDO_LOAD_TEST_META_KEY, true ) !== '1' ) {
		WP_CLI::error( sprintf( 'Refusing to overwrite non-fixture user %s.', $login ) );
	}

	$userdata = [
		'user_login'   => $login,
		'user_pass'    => $password,
		'user_email'   => sprintf( '%s@example.invalid', $login ),
		'display_name' => sprintf( 'Loadtest Vrijwilliger %03d', $index ),
		'first_name'   => 'Loadtest',
		'last_name'    => sprintf( 'Vrijwilliger %03d', $index ),
		'role'         => 'rondo_user',
	];
	if ( $user ) {
		$userdata['ID'] = $user->ID;
	}

	$user_id = wp_insert_user( $userdata );
	if ( is_wp_error( $user_id ) ) {
		WP_CLI::error( $user_id );
	}

	update_user_meta( $user_id, RONDO_LOAD_TEST_META_KEY, '1' );
	update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );

	return (int) $user_id;
}

/**
 * Create the fixture dienst type and shifts.
 *
 * @return array{dienst_type_id:int, contention_shift_id:int, shift_ids:int[]}
 */
function rondo_load_test_seed_shifts(): array {
	$type_ids = rondo_load_test_post_ids( 'dienst_type' );
	$type_id  = $type_ids[0] ?? 0;
	if ( $type_id <= 0 ) {
		$type_id = wp_insert_post(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Loadtest Algemene Dienst',
			],
			true
		);
		if ( is_wp_error( $type_id ) ) {
			WP_CLI::error( $type_id );
		}
	}

	update_post_meta( $type_id, RONDO_LOAD_TEST_META_KEY, '1' );
	update_post_meta( $type_id, 'default_capacity', 10 );
	update_post_meta( $type_id, 'color', '#0891b2' );
	update_post_meta( $type_id, 'vog_required', false );
	update_post_meta( $type_id, 'iva_required', false );

	$existing_shifts = rondo_load_test_post_ids( 'dienst_shift' );
	if ( ! empty( $existing_shifts ) ) {
		$contention = 0;
		foreach ( $existing_shifts as $shift_id ) {
			if ( get_post_meta( $shift_id, '_rondo_load_test_kind', true ) === 'contention' ) {
				$contention = $shift_id;
				break;
			}
		}
		return [
			'dienst_type_id'      => (int) $type_id,
			'contention_shift_id' => (int) $contention,
			'shift_ids'           => $existing_shifts,
		];
	}

	$shift_ids  = [];
	$contention = 0;
	$base_time  = strtotime( '+28 days 18:00:00' );
	for ( $i = 0; $i < 21; $i++ ) {
		$start = $base_time + ( $i * DAY_IN_SECONDS );
		$end   = $start + ( 3 * HOUR_IN_SECONDS );
		$kind  = $i === 0 ? 'contention' : 'browse';
		$title = $i === 0 ? 'Loadtest Gelijktijdige Inschrijving' : sprintf( 'Loadtest Dienst %02d', $i );

		$shift_id = wp_insert_post(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => $title,
			],
			true
		);
		if ( is_wp_error( $shift_id ) ) {
			WP_CLI::error( $shift_id );
		}

		update_post_meta( $shift_id, RONDO_LOAD_TEST_META_KEY, '1' );
		update_post_meta( $shift_id, '_rondo_load_test_kind', $kind );
		update_post_meta( $shift_id, 'dienst_type_id', (int) $type_id );
		update_post_meta( $shift_id, 'start_datetime', gmdate( 'Y-m-d H:i:s', $start ) );
		update_post_meta( $shift_id, 'end_datetime', gmdate( 'Y-m-d H:i:s', $end ) );
		update_post_meta( $shift_id, 'capacity', $i === 0 ? 200 : 10 );
		update_post_meta( $shift_id, 'status', 'open' );
		update_post_meta( $shift_id, 'assigned_persons', [] );
		$shift_ids[] = (int) $shift_id;
		if ( $i === 0 ) {
			$contention = (int) $shift_id;
		}
	}

	return [
		'dienst_type_id'      => (int) $type_id,
		'contention_shift_id' => $contention,
		'shift_ids'           => $shift_ids,
	];
}

/**
 * Seed synthetic users, people and shifts.
 *
 * @param int $count Number of volunteer accounts.
 */
function rondo_load_test_seed( int $count ): void {
	$password = (string) getenv( 'RONDO_LOAD_TEST_PASSWORD' );
	if ( $password === '' || strlen( $password ) < 16 ) {
		WP_CLI::error( 'Set RONDO_LOAD_TEST_PASSWORD to at least 16 characters.' );
	}
	if ( $count < 1 || $count > 200 ) {
		WP_CLI::error( 'Fixture count must be between 1 and 200.' );
	}
	if ( ! get_role( 'rondo_user' ) ) {
		WP_CLI::error( 'The rondo_user role is missing.' );
	}

	for ( $index = 1; $index <= $count; $index++ ) {
		$person_id = rondo_load_test_upsert_person( $index );
		rondo_load_test_upsert_user( $index, $person_id, $password );
	}

	$shift_state = rondo_load_test_seed_shifts();
	rondo_load_test_reset();
	$state = [
		'users'               => $count,
		'people'              => $count,
		'shifts'              => count( $shift_state['shift_ids'] ),
		'contention_shift_id' => $shift_state['contention_shift_id'],
	];
	update_option( RONDO_LOAD_TEST_OPTION, $state, false );
	WP_CLI::line( wp_json_encode( $state ) );
	WP_CLI::success( sprintf( 'Seeded %d synthetic volunteers.', $count ) );
}

/**
 * Verify contention-shift data integrity.
 *
 * @param int $expected Expected unique assignments.
 */
function rondo_load_test_verify( int $expected ): void {
	$state    = get_option( RONDO_LOAD_TEST_OPTION, [] );
	$shift_id = (int) ( $state['contention_shift_id'] ?? 0 );
	if ( $shift_id <= 0 ) {
		WP_CLI::error( 'No contention shift found.' );
	}

	$assigned = array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) );
	$unique   = array_values( array_unique( $assigned ) );
	WP_CLI::line(
		wp_json_encode(
			[
				'shift_id'       => $shift_id,
				'assigned_count' => count( $assigned ),
				'unique_count'   => count( $unique ),
				'expected_count' => $expected,
			]
		)
	);

	if ( count( $assigned ) !== $expected || count( $unique ) !== $expected ) {
		WP_CLI::error( 'Concurrent signup integrity check failed.' );
	}
	WP_CLI::success( 'Concurrent signup integrity check passed.' );
}

rondo_load_test_require_demo();
$command = isset( $args[0] ) ? sanitize_key( $args[0] ) : 'status';

switch ( $command ) {
	case 'seed':
		rondo_load_test_seed( isset( $args[1] ) ? absint( $args[1] ) : 100 );
		break;
	case 'reset':
		rondo_load_test_reset();
		break;
	case 'verify':
		rondo_load_test_verify( isset( $args[1] ) ? absint( $args[1] ) : 0 );
		break;
	case 'cleanup':
		rondo_load_test_cleanup();
		break;
	case 'status':
		WP_CLI::line( wp_json_encode( get_option( RONDO_LOAD_TEST_OPTION, [] ) ) );
		break;
	default:
		WP_CLI::error( 'Unknown command. Use seed, reset, verify, cleanup, or status.' );
}
