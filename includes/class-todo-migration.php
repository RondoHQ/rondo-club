<?php
/**
 * Todo Status Migration
 *
 * Migrates todos from meta-based status (is_completed, awaiting_response)
 * to WordPress post status (rondo_open, rondo_awaiting, rondo_completed).
 */

namespace Rondo\Data;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TodoMigration {

	/**
	 * Constructor - register WP-CLI command
	 */
	public function __construct() {
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\WP_CLI::add_command( 'prm migrate-todos', [ $this, 'migrate_todos_command' ] );
		}
	}

	/**
	 * WP-CLI command to migrate todos
	 *
	 * ## EXAMPLES
	 *
	 *     wp prm migrate-todos
	 *     wp prm migrate-todos --dry-run
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function migrate_todos_command( $args, $assoc_args ) {
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( $dry_run ) {
			\WP_CLI::log( 'Dry run mode - no changes will be made.' );
		}

		$result = $this->migrate_todos( $dry_run );

		if ( $dry_run ) {
			\WP_CLI::success(
				sprintf(
					'Would migrate %d todos: %d to open, %d to awaiting, %d to completed.',
					$result['total'],
					$result['open'],
					$result['awaiting'],
					$result['completed']
				)
			);
		} else {
			\WP_CLI::success(
				sprintf(
					'Migrated %d todos: %d to open, %d to awaiting, %d to completed.',
					$result['total'],
					$result['open'],
					$result['awaiting'],
					$result['completed']
				)
			);
		}
	}

	/**
	 * Migrate all todos to new status system
	 *
	 * @param bool $dry_run If true, don't actually make changes.
	 * @return array Migration statistics.
	 */
	public function migrate_todos( $dry_run = false ) {
		global $wpdb;

		$stats = [
			'total'     => 0,
			'open'      => 0,
			'awaiting'  => 0,
			'completed' => 0,
			'skipped'   => 0,
		];

		// Get all todos with 'publish' status using direct DB query
		// This bypasses access control filters that would otherwise restrict results
		$todos = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				'rondo_todo',
				'publish'
			)
		);

		foreach ( $todos as $todo_id ) {
			++$stats['total'];

			$is_completed            = get_field( 'is_completed', $todo_id );
			$awaiting_response       = get_field( 'awaiting_response', $todo_id );
			$awaiting_response_since = get_field( 'awaiting_response_since', $todo_id );

			// Determine new status
			if ( $is_completed && $awaiting_response ) {
				$new_status = 'rondo_awaiting';
				++$stats['awaiting'];
			} elseif ( $is_completed ) {
				$new_status = 'rondo_completed';
				++$stats['completed'];
			} else {
				$new_status = 'rondo_open';
				++$stats['open'];
			}

			if ( ! $dry_run ) {
				// Update post status
				$wpdb->update(
					$wpdb->posts,
					[ 'post_status' => $new_status ],
					[ 'ID' => $todo_id ],
					[ '%s' ],
					[ '%d' ]
				);

				// Rename awaiting_response_since to awaiting_since if migrating to awaiting
				if ( $new_status === 'rondo_awaiting' && $awaiting_response_since ) {
					update_field( 'awaiting_since', $awaiting_response_since, $todo_id );
				}

				// Clean up old meta fields
				delete_field( 'is_completed', $todo_id );
				delete_field( 'awaiting_response', $todo_id );
				delete_field( 'awaiting_response_since', $todo_id );
			}
		}

		// Clear post cache
		if ( ! $dry_run ) {
			wp_cache_flush();
		}

		return $stats;
	}

	/**
	 * Check if migration is needed
	 *
	 * @return bool True if there are todos with 'publish' status.
	 */
	public static function needs_migration() {
		$count = wp_count_posts( 'rondo_todo' );
		return isset( $count->publish ) && $count->publish > 0;
	}
}
