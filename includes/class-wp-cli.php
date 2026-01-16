<?php
/**
 * WP-CLI Commands for Caelis
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Only load if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Reminders WP-CLI Commands
	 */
	class PRM_Reminders_CLI_Command {

		/**
		 * Trigger daily reminders manually
		 *
		 * ## OPTIONS
		 *
		 * [--user=<user_id>]
		 * : User ID to send reminders to (if not specified, processes all users)
		 *
		 * [--force]
		 * : Force send regardless of preferred notification time
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm reminders trigger
		 *     wp prm reminders trigger --user=1
		 *     wp prm reminders trigger --user=1 --force
		 *
		 * @when after_wp_load
		 */
		public function trigger( $args, $assoc_args ) {
			WP_CLI::log( 'Processing daily reminders...' );

			$reminders = new PRM_Reminders();

			// Check if specific user ID is provided
			$specific_user_id = isset( $assoc_args['user'] ) ? (int) $assoc_args['user'] : null;

			if ( $specific_user_id ) {
				$user = get_userdata( $specific_user_id );
				if ( ! $user ) {
					WP_CLI::error( sprintf( 'User with ID %d not found.', $specific_user_id ) );
					return;
				}

				// Verify user has dates they can access
				$digest_data = $reminders->get_weekly_digest( $specific_user_id );
				$has_dates   = ! empty( $digest_data['today'] ) ||
							! empty( $digest_data['tomorrow'] ) ||
							! empty( $digest_data['rest_of_week'] );

				if ( ! $has_dates ) {
					WP_CLI::warning( sprintf( 'User %s (ID: %d) has no upcoming dates.', $user->display_name, $specific_user_id ) );
					return;
				}

				WP_CLI::log( sprintf( 'Processing reminders for user: %s (ID: %d)', $user->display_name, $specific_user_id ) );
				$users_to_notify = [ $specific_user_id ];
			} else {
				// Get all users who should receive reminders
				$users_to_notify = $this->get_all_users_to_notify();

				if ( empty( $users_to_notify ) ) {
					WP_CLI::warning( 'No users found to notify.' );
					WP_CLI::log( '' );
					WP_CLI::log( 'This could mean:' );
					WP_CLI::log( '1. No important dates exist in the system' );
					WP_CLI::log( '2. Important dates exist but have no related people' );
					WP_CLI::log( '3. People exist but are not linked to any dates' );
					WP_CLI::log( '' );
					WP_CLI::log( 'Run with --debug flag for more details: wp prm reminders trigger --debug' );
					WP_CLI::log( 'Or specify a user: wp prm reminders trigger --user=1' );
					return;
				}

				WP_CLI::log( sprintf( 'Found %d user(s) to notify.', count( $users_to_notify ) ) );
			}

			$current_time = new DateTime( 'now', wp_timezone() );
			$current_hour = (int) $current_time->format( 'H' );

			$users_processed    = 0;
			$notifications_sent = 0;
			$users_skipped      = 0;

			foreach ( $users_to_notify as $user_id ) {
				$user = get_userdata( $user_id );
				if ( ! $user ) {
					continue;
				}

				// Check if it's the right time for this user (unless --force flag is set or specific user)
				if ( ! isset( $assoc_args['force'] ) && ! $specific_user_id ) {
					$preferred_time = get_user_meta( $user_id, 'caelis_notification_time', true );
					if ( empty( $preferred_time ) ) {
						$preferred_time = '09:00';
					}

					list($preferred_hour, $preferred_minute) = explode( ':', $preferred_time );
					$preferred_hour                          = (int) $preferred_hour;

					if ( $current_hour !== $preferred_hour ) {
						WP_CLI::log(
							sprintf(
								'Skipping user %s (ID: %d) - preferred time is %s, current hour is %d',
								$user->display_name,
								$user_id,
								$preferred_time,
								$current_hour
							)
						);
						++$users_skipped;
						continue;
					}
				}

				WP_CLI::log( sprintf( 'Processing reminders for user: %s (ID: %d)', $user->display_name, $user_id ) );

				// Get weekly digest for this user
				$digest_data = $reminders->get_weekly_digest( $user_id );

				// Check if there are any dates to notify about
				$has_dates = ! empty( $digest_data['today'] ) ||
							! empty( $digest_data['tomorrow'] ) ||
							! empty( $digest_data['rest_of_week'] );

				if ( ! $has_dates ) {
					WP_CLI::log( sprintf( '  No upcoming dates for user %s', $user->display_name ) );
					++$users_processed;
					continue;
				}

				// Count dates
				$date_count = count( $digest_data['today'] ) +
								count( $digest_data['tomorrow'] ) +
								count( $digest_data['rest_of_week'] );

				WP_CLI::log(
					sprintf(
						'  Found %d date(s): %d today, %d tomorrow, %d rest of week',
						$date_count,
						count( $digest_data['today'] ),
						count( $digest_data['tomorrow'] ),
						count( $digest_data['rest_of_week'] )
					)
				);

				// Send via all enabled channels
				$email_channel = new PRM_Email_Channel();
				$slack_channel = new PRM_Slack_Channel();

				$user_notifications_sent = 0;

				if ( $email_channel->is_enabled_for_user( $user_id ) ) {
					if ( $email_channel->send( $user_id, $digest_data ) ) {
						WP_CLI::log( sprintf( '  ✓ Email sent to %s', $user->user_email ) );
						++$user_notifications_sent;
						++$notifications_sent;
					} else {
						WP_CLI::warning( sprintf( '  ✗ Failed to send email to %s', $user->user_email ) );
					}
				}

				if ( $slack_channel->is_enabled_for_user( $user_id ) ) {
					if ( $slack_channel->send( $user_id, $digest_data ) ) {
						WP_CLI::log( sprintf( '  ✓ Slack notification sent' ) );
						++$user_notifications_sent;
						++$notifications_sent;
					} else {
						WP_CLI::warning( sprintf( '  ✗ Failed to send Slack notification' ) );
					}
				}

				if ( 0 === $user_notifications_sent ) {
					WP_CLI::log( sprintf( '  ⚠ No notification channels enabled for user %s', $user->display_name ) );
				}

				++$users_processed;
			}

			WP_CLI::success(
				sprintf(
					'Completed: Processed %d user(s), sent %d notification(s), skipped %d user(s)',
					$users_processed,
					$notifications_sent,
					$users_skipped
				)
			);
		}

		/**
		 * Get all users who should receive reminders
		 *
		 * @return array User IDs
		 */
		private function get_all_users_to_notify() {
			// Use direct database query to bypass access control filters
			// WP-CLI runs without a logged-in user, so get_posts() would return nothing
			global $wpdb;

			$date_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = %s 
                 AND post_status = 'publish'",
					'important_date'
				)
			);

			WP_CLI::log( sprintf( 'Found %d important date(s) in system.', count( $date_ids ) ) );

			if ( empty( $date_ids ) ) {
				return [];
			}

			// Get full post objects
			$dates = array_map( 'get_post', $date_ids );

			$user_ids = [];

			foreach ( $dates as $date_post ) {
				// Get related people using ACF (handles repeater fields correctly)
				$related_people = get_field( 'related_people', $date_post->ID );

				if ( empty( $related_people ) ) {
					WP_CLI::debug( sprintf( 'Date "%s" (ID: %d) has no related people.', $date_post->post_title, $date_post->ID ) );
					continue;
				}

				// Ensure it's an array
				if ( ! is_array( $related_people ) ) {
					$related_people = [ $related_people ];
				}

				WP_CLI::debug( sprintf( 'Date "%s" (ID: %d) has %d related people.', $date_post->post_title, $date_post->ID, count( $related_people ) ) );

				// Get user IDs from people post authors
				foreach ( $related_people as $person ) {
					$person_id = is_object( $person ) ? $person->ID : ( is_array( $person ) ? $person['ID'] : $person );

					if ( ! $person_id ) {
						WP_CLI::debug( '  Skipping invalid person ID.' );
						continue;
					}

					$person_post = get_post( $person_id );
					if ( ! $person_post ) {
						WP_CLI::debug( sprintf( '  Person ID %d not found.', $person_id ) );
						continue;
					}

					$author_id = (int) $person_post->post_author;
					if ( $author_id > 0 ) {
						$user_ids[] = $author_id;
						WP_CLI::debug( sprintf( '  Added user ID %d (author of person "%s")', $author_id, $person_post->post_title ) );
					}
				}
			}

			$unique_user_ids = array_unique( $user_ids );
			WP_CLI::log( sprintf( 'Found %d unique user(s) to notify.', count( $unique_user_ids ) ) );

			return $unique_user_ids;
		}
	}

	/**
	 * Migration WP-CLI Commands
	 */
	class PRM_Migration_CLI_Command {

		/**
		 * Migrate addresses from contact_info to dedicated addresses field
		 *
		 * This command moves all address-type entries from the contact_info repeater
		 * to the new structured addresses field.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm migrate addresses
		 *     wp prm migrate addresses --dry-run
		 *
		 * @when after_wp_load
		 */
		public function addresses( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			}

			WP_CLI::log( 'Migrating addresses from contact_info to addresses field...' );

			// Get all people
			global $wpdb;
			$person_ids = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts} 
                 WHERE post_type = 'person' 
                 AND post_status = 'publish'"
			);

			if ( empty( $person_ids ) ) {
				WP_CLI::warning( 'No people found in the system.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d person(s) to check.', count( $person_ids ) ) );

			$migrated_count        = 0;
			$addresses_migrated    = 0;
			$people_with_addresses = 0;

			foreach ( $person_ids as $post_id ) {
				$contact_info       = get_field( 'contact_info', $post_id ) ?: [];
				$existing_addresses = get_field( 'addresses', $post_id ) ?: [];

				// Find address entries in contact_info
				$address_entries = array_filter(
					$contact_info,
					function ( $item ) {
						return isset( $item['contact_type'] ) && 'address' === $item['contact_type'];
					}
				);

				if ( empty( $address_entries ) ) {
					continue;
				}

				++$people_with_addresses;
				$person_title = get_the_title( $post_id );
				WP_CLI::log(
					sprintf(
						'Processing: %s (ID: %d) - Found %d address(es)',
						$person_title,
						$post_id,
						count( $address_entries )
					)
				);

				// Build new addresses array from address entries
				$new_addresses        = $existing_addresses;
				$updated_contact_info = [];

				foreach ( $contact_info as $item ) {
					if ( isset( $item['contact_type'] ) && 'address' === $item['contact_type'] ) {
						// Migrate to addresses field
						$new_addresses[] = [
							'address_label' => $item['contact_label'] ?? '',
							'street'        => $item['contact_value'] ?? '', // Put full address in street
							'postal_code'   => '',
							'city'          => '',
							'state'         => '',
							'country'       => '',
						];
						++$addresses_migrated;
						WP_CLI::log( sprintf( '  → Moving: "%s" to addresses field', $item['contact_value'] ?? '' ) );
					} else {
						// Keep non-address entries
						$updated_contact_info[] = $item;
					}
				}

				if ( ! $dry_run ) {
					// Save updated addresses
					update_field( 'addresses', $new_addresses, $post_id );

					// Save updated contact_info (without addresses)
					update_field( 'contact_info', $updated_contact_info, $post_id );

					++$migrated_count;
				}
			}

			if ( $dry_run ) {
				WP_CLI::success(
					sprintf(
						'DRY RUN: Would migrate %d address(es) from %d person(s)',
						$addresses_migrated,
						$people_with_addresses
					)
				);
			} else {
				WP_CLI::success(
					sprintf(
						'Migration complete: Migrated %d address(es) from %d person(s)',
						$addresses_migrated,
						$migrated_count
					)
				);
			}
		}
	}

	/**
	 * VCard WP-CLI Commands
	 */
	class PRM_VCard_CLI_Command {

		/**
		 * Get the vCard for a person (as CardDAV would serve it)
		 *
		 * ## OPTIONS
		 *
		 * <person_id>
		 * : The ID of the person to export
		 *
		 * [--output=<file>]
		 * : Optional file path to save the vCard (otherwise outputs to stdout)
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm vcard get 123
		 *     wp prm vcard get 123 --output=/tmp/contact.vcf
		 *
		 * @when after_wp_load
		 */
		public function get( $args, $assoc_args ) {
			$person_id = (int) $args[0];

			if ( ! $person_id ) {
				WP_CLI::error( 'Please provide a valid person ID.' );
				return;
			}

			$person = get_post( $person_id );

			if ( ! $person ) {
				WP_CLI::error( sprintf( 'Person with ID %d not found.', $person_id ) );
				return;
			}

			if ( 'person' !== $person->post_type ) {
				WP_CLI::error( sprintf( 'Post ID %d is not a person (it is a %s).', $person_id, $person->post_type ) );
				return;
			}

			// Generate vCard using the same method CardDAV uses
			$vcard = \Caelis\Export\VCard::generate( $person );

			if ( empty( $vcard ) ) {
				WP_CLI::error( 'Failed to generate vCard.' );
				return;
			}

			// Output to file or stdout
			if ( isset( $assoc_args['output'] ) ) {
				$file_path = $assoc_args['output'];
				$result    = file_put_contents( $file_path, $vcard );

				if ( false === $result ) {
					WP_CLI::error( sprintf( 'Failed to write to file: %s', $file_path ) );
					return;
				}

				WP_CLI::success( sprintf( 'vCard saved to: %s (%d bytes)', $file_path, $result ) );
			} else {
				// Output to stdout
				WP_CLI::log( $vcard );
			}
		}

		/**
		 * Parse a vCard file and show what would be imported
		 *
		 * ## OPTIONS
		 *
		 * <file>
		 * : Path to the vCard file to parse
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm vcard parse /tmp/contact.vcf
		 *
		 * @when after_wp_load
		 */
		public function parse( $args, $assoc_args ) {
			$file_path = $args[0];

			if ( ! file_exists( $file_path ) ) {
				WP_CLI::error( sprintf( 'File not found: %s', $file_path ) );
				return;
			}

			$vcard_data = file_get_contents( $file_path );

			if ( empty( $vcard_data ) ) {
				WP_CLI::error( 'File is empty or could not be read.' );
				return;
			}

			// Parse the vCard
			$parsed = \Caelis\Export\VCard::parse( $vcard_data );

			if ( empty( $parsed ) ) {
				WP_CLI::error( 'Failed to parse vCard.' );
				return;
			}

			WP_CLI::log( 'Parsed vCard data:' );
			WP_CLI::log( '' );

			if ( ! empty( $parsed['first_name'] ) ) {
				WP_CLI::log( sprintf( 'First Name: %s', $parsed['first_name'] ) );
			}
			if ( ! empty( $parsed['last_name'] ) ) {
				WP_CLI::log( sprintf( 'Last Name: %s', $parsed['last_name'] ) );
			}
			if ( ! empty( $parsed['full_name'] ) ) {
				WP_CLI::log( sprintf( 'Full Name: %s', $parsed['full_name'] ) );
			}
			if ( ! empty( $parsed['nickname'] ) ) {
				WP_CLI::log( sprintf( 'Nickname: %s', $parsed['nickname'] ) );
			}

			if ( ! empty( $parsed['contact_info'] ) ) {
				WP_CLI::log( '' );
				WP_CLI::log( 'Contact Info:' );
				foreach ( $parsed['contact_info'] as $contact ) {
					$type    = $contact['contact_type'] ?? 'unknown';
					$value   = $contact['contact_value'] ?? '';
					$label   = $contact['contact_label'] ?? '';
					$display = $label ? "{$type} ({$label})" : $type;
					WP_CLI::log( sprintf( '  %s: %s', $display, $value ) );
				}
			}

			if ( ! empty( $parsed['addresses'] ) ) {
				WP_CLI::log( '' );
				WP_CLI::log( 'Addresses:' );
				foreach ( $parsed['addresses'] as $addr ) {
					$parts = array_filter(
						[
							$addr['street'] ?? '',
							$addr['city'] ?? '',
							$addr['state'] ?? '',
							$addr['postal_code'] ?? '',
							$addr['country'] ?? '',
						]
					);
					$label = $addr['address_label'] ?? 'Address';
					WP_CLI::log( sprintf( '  %s: %s', $label, implode( ', ', $parts ) ) );
				}
			}

			WP_CLI::log( '' );
			WP_CLI::success( 'vCard parsed successfully.' );
		}
	}

	/**
	 * Visibility WP-CLI Commands
	 */
	class PRM_Visibility_CLI_Command {

		/**
		 * Set default visibility for posts that don't have it set
		 *
		 * ## OPTIONS
		 *
		 * [--post-type=<type>]
		 * : Post type to update (person, company, important_date, or all). Default: all
		 *
		 * [--dry-run]
		 * : Preview changes without making them
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm visibility set-defaults
		 *     wp prm visibility set-defaults --dry-run
		 *     wp prm visibility set-defaults --post-type=person
		 *     wp prm visibility set-defaults --post-type=company
		 *     wp prm visibility set-defaults --post-type=important_date
		 *
		 * @when after_wp_load
		 */
		public function set_defaults( $args, $assoc_args ) {
			$dry_run   = isset( $assoc_args['dry-run'] );
			$post_type = $assoc_args['post-type'] ?? 'all';

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			}

			// Determine which post types to process.
			$post_types = [];
			if ( 'all' === $post_type ) {
				$post_types = [ 'person', 'company', 'important_date' ];
			} elseif ( in_array( $post_type, [ 'person', 'company', 'important_date' ] ) ) {
				$post_types = [ $post_type ];
			} else {
				WP_CLI::error( 'Invalid post type. Use: person, company, important_date, or all' );
				return;
			}

			WP_CLI::log( sprintf( 'Setting default visibility to "private" for post types: %s', implode( ', ', $post_types ) ) );
			WP_CLI::log( '' );

			$total_updated = 0;
			$total_skipped = 0;

			foreach ( $post_types as $type ) {
				$result         = $this->process_post_type( $type, $dry_run );
				$total_updated += $result['updated'];
				$total_skipped += $result['skipped'];
			}

			WP_CLI::log( '' );
			if ( $dry_run ) {
				WP_CLI::success(
					sprintf(
						'DRY RUN: Would update %d post(s), skipped %d post(s) (already have visibility set)',
						$total_updated,
						$total_skipped
					)
				);
			} else {
				WP_CLI::success(
					sprintf(
						'Complete: Updated %d post(s), skipped %d post(s) (already have visibility set)',
						$total_updated,
						$total_skipped
					)
				);
			}
		}

		/**
		 * Process a single post type
		 *
		 * @param string $post_type Post type to process
		 * @param bool $dry_run Whether to actually make changes
		 * @return array Results with 'updated' and 'skipped' counts
		 */
		private function process_post_type( $post_type, $dry_run ) {
			global $wpdb;

			// Get all posts of this type
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = %s
                 AND post_status = 'publish'",
					$post_type
				)
			);

			if ( empty( $post_ids ) ) {
				WP_CLI::log( sprintf( 'No %s posts found.', $post_type ) );
				return [
					'updated' => 0,
					'skipped' => 0,
				];
			}

			WP_CLI::log( sprintf( 'Processing %d %s post(s)...', count( $post_ids ), $post_type ) );

			$updated = 0;
			$skipped = 0;

			foreach ( $post_ids as $post_id ) {
				$visibility = get_field( '_visibility', $post_id );

				// Skip if visibility is already set
				if ( ! empty( $visibility ) ) {
					++$skipped;
					continue;
				}

				$post_title = get_the_title( $post_id );

				if ( $dry_run ) {
					WP_CLI::log( sprintf( '  Would set visibility to "private" for: %s (ID: %d)', $post_title, $post_id ) );
				} else {
					update_field( '_visibility', 'private', $post_id );
					WP_CLI::log( sprintf( '  Set visibility to "private" for: %s (ID: %d)', $post_title, $post_id ) );
				}

				++$updated;
			}

			WP_CLI::log( sprintf( '  %s: %d updated, %d skipped', ucfirst( $post_type ), $updated, $skipped ) );

			return [
				'updated' => $updated,
				'skipped' => $skipped,
			];
		}
	}

	/**
	 * Multi-User Migration WP-CLI Commands
	 */
	class PRM_MultiUser_CLI_Command {

		/**
		 * Migrate existing Caelis installation to multi-user system
		 *
		 * This command sets default visibility on all existing contacts,
		 * companies, and important dates, enabling the multi-user features
		 * while preserving the existing single-user behavior (all private).
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm multiuser migrate
		 *     wp prm multiuser migrate --dry-run
		 *
		 * @when after_wp_load
		 */
		public function migrate( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			WP_CLI::log( '' );
			WP_CLI::log( '╔════════════════════════════════════════════════════════════╗' );
			WP_CLI::log( '║         Caelis Multi-User Migration                        ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );
			WP_CLI::log( 'This migration will:' );
			WP_CLI::log( '  1. Set visibility to "private" on all contacts without visibility' );
			WP_CLI::log( '  2. Set visibility to "private" on all companies without visibility' );
			WP_CLI::log( '  3. Set visibility to "private" on all important dates without visibility' );
			WP_CLI::log( '' );
			WP_CLI::log( 'This preserves single-user behavior: all your data remains private' );
			WP_CLI::log( 'and only visible to you. You can then selectively share items.' );
			WP_CLI::log( '' );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
				WP_CLI::log( '' );
			}

			// Use the existing visibility command logic
			$visibility_command = new PRM_Visibility_CLI_Command();

			// Process all post types
			WP_CLI::log( 'Starting migration...' );
			WP_CLI::log( '' );

			// We need to call the internal method directly, so we'll replicate the logic
			$post_types = [ 'person', 'company', 'important_date' ];
			$results    = [];

			foreach ( $post_types as $post_type ) {
				$result                = $this->migrate_post_type( $post_type, $dry_run );
				$results[ $post_type ] = $result;
			}

			// Summary
			WP_CLI::log( '' );
			WP_CLI::log( '────────────────────────────────────────────────────────────────' );
			WP_CLI::log( 'Migration Summary:' );
			WP_CLI::log( '────────────────────────────────────────────────────────────────' );

			$total_updated = 0;
			$total_skipped = 0;

			foreach ( $results as $type => $result ) {
				$label  = 'person' === $type ? 'People' : ( 'company' === $type ? 'Companies' : 'Important Dates' );
				$action = $dry_run ? 'Would update' : 'Updated';
				WP_CLI::log(
					sprintf(
						'  %s: %s %d, skipped %d (already had visibility)',
						$label,
						$action,
						$result['updated'],
						$result['skipped']
					)
				);
				$total_updated += $result['updated'];
				$total_skipped += $result['skipped'];
			}

			WP_CLI::log( '────────────────────────────────────────────────────────────────' );
			WP_CLI::log(
				sprintf(
					'  Total: %s %d, skipped %d',
					$dry_run ? 'Would update' : 'Updated',
					$total_updated,
					$total_skipped
				)
			);
			WP_CLI::log( '' );

			if ( $dry_run ) {
				WP_CLI::success( 'Dry run complete. Run without --dry-run to apply changes.' );
			} else {
				if ( $total_updated > 0 ) {
					WP_CLI::success( 'Migration complete! Your Caelis installation is now multi-user ready.' );
				} else {
					WP_CLI::success( 'Migration complete. All posts already had visibility set.' );
				}
				WP_CLI::log( '' );
				WP_CLI::log( 'Next steps:' );
				WP_CLI::log( '  1. Run "wp prm multiuser validate" to verify the migration' );
				WP_CLI::log( '  2. Create workspaces to share contacts with other users' );
				WP_CLI::log( '' );
			}
		}

		/**
		 * Validate multi-user migration status
		 *
		 * Checks that all contacts, companies, and important dates have
		 * visibility set. Reports counts and provides guidance if migration
		 * is incomplete.
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm multiuser validate
		 *
		 * @when after_wp_load
		 */
		public function validate( $args, $assoc_args ) {
			WP_CLI::log( '' );
			WP_CLI::log( 'Validating multi-user migration status...' );
			WP_CLI::log( '' );

			$post_types    = [ 'person', 'company', 'important_date' ];
			$all_valid     = true;
			$total_with    = 0;
			$total_without = 0;

			global $wpdb;

			foreach ( $post_types as $post_type ) {
				// Get all posts of this type
				$post_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = %s
                     AND post_status = 'publish'",
						$post_type
					)
				);

				$total              = count( $post_ids );
				$with_visibility    = 0;
				$without_visibility = 0;

				foreach ( $post_ids as $post_id ) {
					$visibility = get_field( '_visibility', $post_id );
					if ( ! empty( $visibility ) ) {
						++$with_visibility;
					} else {
						++$without_visibility;
					}
				}

				$label = 'person' === $post_type ? 'People' : ( 'company' === $post_type ? 'Companies' : 'Important Dates' );

				if ( $without_visibility > 0 ) {
					WP_CLI::log(
						sprintf(
							'  [!] %s: %d/%d have visibility set (%d missing)',
							$label,
							$with_visibility,
							$total,
							$without_visibility
						)
					);
					$all_valid = false;
				} else {
					WP_CLI::log(
						sprintf(
							'  [OK] %s: %d/%d have visibility set',
							$label,
							$with_visibility,
							$total
						)
					);
				}

				$total_with    += $with_visibility;
				$total_without += $without_visibility;
			}

			WP_CLI::log( '' );

			if ( $all_valid ) {
				WP_CLI::success(
					sprintf(
						'Validation passed! All %d posts have visibility set.',
						$total_with
					)
				);
				WP_CLI::log( '' );
				WP_CLI::log( 'Your Caelis installation is properly configured for multi-user mode.' );
			} else {
				WP_CLI::warning(
					sprintf(
						'Validation failed: %d post(s) are missing visibility.',
						$total_without
					)
				);
				WP_CLI::log( '' );
				WP_CLI::log( 'To complete the migration, run:' );
				WP_CLI::log( '  wp prm multiuser migrate' );
				WP_CLI::log( '' );
			}
		}

		/**
		 * Migrate a single post type
		 *
		 * @param string $post_type Post type to process
		 * @param bool $dry_run Whether to actually make changes
		 * @return array Results with 'updated' and 'skipped' counts
		 */
		private function migrate_post_type( $post_type, $dry_run ) {
			global $wpdb;

			// Get all posts of this type
			$post_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = %s
                 AND post_status = 'publish'",
					$post_type
				)
			);

			$label = 'person' === $post_type ? 'people' : ( 'company' === $post_type ? 'companies' : 'important dates' );

			if ( empty( $post_ids ) ) {
				WP_CLI::log( sprintf( 'No %s found.', $label ) );
				return [
					'updated' => 0,
					'skipped' => 0,
				];
			}

			WP_CLI::log( sprintf( 'Processing %d %s...', count( $post_ids ), $label ) );

			$updated = 0;
			$skipped = 0;

			foreach ( $post_ids as $post_id ) {
				$visibility = get_field( '_visibility', $post_id );

				// Skip if visibility is already set
				if ( ! empty( $visibility ) ) {
					++$skipped;
					continue;
				}

				if ( ! $dry_run ) {
					update_field( '_visibility', 'private', $post_id );
				}

				++$updated;
			}

			$action = $dry_run ? 'Would update' : 'Updated';
			WP_CLI::log( sprintf( '  %s: %d, skipped: %d', $action, $updated, $skipped ) );

			return [
				'updated' => $updated,
				'skipped' => $skipped,
			];
		}
	}

	/**
	 * CardDAV WP-CLI Commands
	 */
	class PRM_CardDAV_CLI_Command {

		/**
		 * Reset CardDAV sync token to force a full resync
		 *
		 * This clears the sync token for a user, causing the next sync
		 * request from their CardDAV client to receive all contacts as "added".
		 *
		 * ## OPTIONS
		 *
		 * [--user=<user_id>]
		 * : User ID to reset sync for. If not specified, resets for all users.
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm carddav reset-sync
		 *     wp prm carddav reset-sync --user=1
		 *
		 * @when after_wp_load
		 */
		public function reset_sync( $args, $assoc_args ) {
			$user_id = isset( $assoc_args['user'] ) ? (int) $assoc_args['user'] : null;

			if ( $user_id ) {
				$users = [ get_user_by( 'ID', $user_id ) ];
				if ( ! $users[0] ) {
					WP_CLI::error( sprintf( 'User with ID %d not found.', $user_id ) );
					return;
				}
			} else {
				// Get all users
				$users = get_users( [ 'fields' => 'all' ] );
			}

			$changes        = get_option( 'prm_carddav_changes', [] );
			$tokens         = get_option( 'prm_carddav_sync_tokens', [] );
			$now            = time();
			$total_contacts = 0;

			global $wpdb;

			foreach ( $users as $user ) {
				$uid = $user->ID;

				// Get all persons for this user (bypass access control filters)
				$persons = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
                     WHERE post_type = 'person'
                     AND post_status = 'publish'
                     AND post_author = %d",
						$uid
					)
				);

				if ( empty( $persons ) ) {
					WP_CLI::log( sprintf( 'User %s (ID: %d) has no contacts to sync.', $user->display_name, $uid ) );
					continue;
				}

				// Add all contacts to change log as "added"
				if ( ! isset( $changes[ $uid ] ) ) {
					$changes[ $uid ] = [];
				}

				foreach ( $persons as $person_id ) {
					$uri                           = get_post_meta( $person_id, '_carddav_uri', true ) ?: $person_id . '.vcf';
					$changes[ $uid ][ $person_id ] = [
						'type'      => 'added',
						'timestamp' => $now,
						'uri'       => $uri,
					];
				}

				// Update sync token
				$tokens[ $uid ] = $now;

				$count           = count( $persons );
				$total_contacts += $count;
				WP_CLI::log( sprintf( 'Queued %d contact(s) for resync for user %s (ID: %d)', $count, $user->display_name, $uid ) );
			}

			update_option( 'prm_carddav_changes', $changes );
			update_option( 'prm_carddav_sync_tokens', $tokens );

			WP_CLI::success( sprintf( 'Queued %d contact(s) for resync. Next sync will pull all contacts.', $total_contacts ) );
			WP_CLI::log( '' );
			WP_CLI::log( 'To trigger the resync, open your CardDAV client (iPhone Contacts, etc.) and pull down to refresh.' );
		}
	}

	/**
	 * Important Dates WP-CLI Commands
	 */
	class PRM_Dates_CLI_Command {

		/**
		 * Regenerate all Important Date titles using current naming convention.
		 *
		 * Uses full names instead of first names only.
		 * Skips dates with custom labels.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without saving.
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm dates regenerate-titles
		 *     wp prm dates regenerate-titles --dry-run
		 *
		 * @when after_wp_load
		 */
		public function regenerate_titles( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'Dry run mode - no changes will be saved.' );
			}

			// Query all important_date posts (bypass access control)
			global $wpdb;
			$date_ids = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'important_date'
                 AND post_status IN ('publish', 'draft', 'pending')"
			);

			if ( empty( $date_ids ) ) {
				WP_CLI::success( 'No important dates found.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d important date(s) to process.', count( $date_ids ) ) );

			$updated = 0;
			$skipped = 0;

			foreach ( $date_ids as $post_id ) {
				$date_post = get_post( $post_id );
				if ( ! $date_post ) {
					continue;
				}

				// Check if has custom_label - skip if custom
				$custom_label = get_field( 'custom_label', $post_id );
				if ( ! empty( $custom_label ) ) {
					WP_CLI::log( sprintf( '[SKIP] #%d: Has custom label "%s"', $post_id, $custom_label ) );
					++$skipped;
					continue;
				}

				$old_title = $date_post->post_title;

				// Generate new title using same logic as PRM_Auto_Title
				$new_title = $this->generate_date_title( $post_id );

				if ( $old_title === $new_title ) {
					WP_CLI::log( sprintf( '[SAME] #%d: "%s"', $post_id, $old_title ) );
					++$skipped;
					continue;
				}

				if ( $dry_run ) {
					WP_CLI::log( sprintf( '[WOULD UPDATE] #%d: "%s" -> "%s"', $post_id, $old_title, $new_title ) );
				} else {
					wp_update_post(
						[
							'ID'         => $post_id,
							'post_title' => $new_title,
							'post_name'  => sanitize_title( $new_title . '-' . $post_id ),
						]
					);
					WP_CLI::log( sprintf( '[UPDATED] #%d: "%s" -> "%s"', $post_id, $old_title, $new_title ) );
				}
				++$updated;
			}

			if ( $dry_run ) {
				WP_CLI::success( sprintf( 'Would update %d title(s). Skipped %d.', $updated, $skipped ) );
			} else {
				WP_CLI::success( sprintf( 'Updated %d title(s). Skipped %d.', $updated, $skipped ) );
			}
		}

		/**
		 * Generate date title from fields (mirrors PRM_Auto_Title logic)
		 *
		 * @param int $post_id Post ID
		 * @return string Generated title
		 */
		private function generate_date_title( $post_id ) {
			// Get date type from taxonomy
			$date_types = wp_get_post_terms( $post_id, 'date_type', [ 'fields' => 'names' ] );
			$type_label = ! empty( $date_types ) ? $date_types[0] : __( 'Date', 'caelis' );

			// Get related people
			$people = get_field( 'related_people', $post_id ) ?: [];

			if ( empty( $people ) ) {
				// translators: %s is the date type label (e.g., "Birthday", "Anniversary").
				return sprintf( __( 'Unnamed %s', 'caelis' ), $type_label );
			}

			// Get full names of related people.
			$names = [];
			foreach ( $people as $person ) {
				$person_id = is_object( $person ) ? $person->ID : $person;
				$full_name = html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' );
				if ( $full_name && __( 'Unnamed Person', 'caelis' ) !== $full_name ) {
					$names[] = $full_name;
				}
			}

			if ( empty( $names ) ) {
				// translators: %s is the date type label (e.g., "Birthday", "Anniversary").
				return sprintf( __( 'Unnamed %s', 'caelis' ), $type_label );
			}

			$count = count( $names );

			// Get date type slug to check for wedding.
			$date_type_slugs = wp_get_post_terms( $post_id, 'date_type', [ 'fields' => 'slugs' ] );
			$type_slug       = ! empty( $date_type_slugs ) ? $date_type_slugs[0] : '';

			// Special handling for wedding type.
			if ( 'wedding' === $type_slug ) {
				if ( $count >= 2 ) {
					// translators: %1$s and %2$s are the names of the people getting married.
					return sprintf( __( 'Wedding of %1$s & %2$s', 'caelis' ), $names[0], $names[1] );
				} elseif ( 1 === $count ) {
					// translators: %s is the name of the person getting married.
					return sprintf( __( 'Wedding of %s', 'caelis' ), $names[0] );
				}
			}

			if ( 1 === $count ) {
				// translators: %1$s is person name, %2$s is date type (e.g., "John's Birthday").
				return sprintf( __( "%1\$s's %2\$s", 'caelis' ), $names[0], $type_label );
			} elseif ( 2 === $count ) {
				// translators: %1$s and %2$s are person names, %3$s is date type (e.g., "John & Jane's Anniversary").
				return sprintf( __( "%1\$s & %2\$s's %3\$s", 'caelis' ), $names[0], $names[1], $type_label );
			} else {
				$first_two = implode( ', ', array_slice( $names, 0, 2 ) );
				$remaining = $count - 2;
				// translators: %1$s is first two names, %2$d is remaining count, %3$s is date type.
				return sprintf( __( '%1$s +%2$d %3$s', 'caelis' ), $first_two, $remaining, $type_label );
			}
		}
	}

	/**
	 * Todos WP-CLI Commands
	 */
	class PRM_Todos_CLI_Command {

		/**
		 * Migrate todos from comment-based storage to CPT-based storage
		 *
		 * This command migrates all prm_todo comments to the new prm_todo
		 * custom post type, preserving all metadata and relationships.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm todos migrate
		 *     wp prm todos migrate --dry-run
		 *
		 * @when after_wp_load
		 */
		public function migrate( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			}

			WP_CLI::log( '' );
			WP_CLI::log( '╔════════════════════════════════════════════════════════════╗' );
			WP_CLI::log( '║         Caelis Todo Migration                              ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );
			WP_CLI::log( 'This migration will:' );
			WP_CLI::log( '  1. Find all comment-based todos (prm_todo comment type)' );
			WP_CLI::log( '  2. Create corresponding prm_todo CPT posts' );
			WP_CLI::log( '  3. Copy all metadata (is_completed, due_date)' );
			WP_CLI::log( '  4. Delete the original comments after successful migration' );
			WP_CLI::log( '' );

			// Query all prm_todo comments
			$todos = get_comments(
				[
					'type'   => 'prm_todo',
					'status' => 'approve',
					'number' => 0, // All todos
				]
			);

			if ( empty( $todos ) ) {
				WP_CLI::success( 'No comment-based todos found. Nothing to migrate.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d todo(s) to migrate.', count( $todos ) ) );
			WP_CLI::log( '' );

			$migrated = 0;
			$skipped  = 0;
			$failed   = 0;

			foreach ( $todos as $todo ) {
				$person_id = (int) $todo->comment_post_ID;
				$person    = get_post( $person_id );

				if ( ! $person || 'person' !== $person->post_type ) {
					WP_CLI::warning(
						sprintf(
							'Skipping todo ID %d: linked to non-person post ID %d',
							$todo->comment_ID,
							$person_id
						)
					);
					++$skipped;
					continue;
				}

				$todo_content = $todo->comment_content;
				$is_completed = get_comment_meta( $todo->comment_ID, 'is_completed', true );
				$due_date     = get_comment_meta( $todo->comment_ID, 'due_date', true );

				WP_CLI::log(
					sprintf(
						'Migrating todo ID %d: "%s" (person: %s)',
						$todo->comment_ID,
						wp_trim_words( $todo_content, 10 ),
						$person->post_title
					)
				);

				if ( $dry_run ) {
					WP_CLI::log(
						sprintf(
							'  Would create: author=%d, completed=%s, due=%s',
							$todo->user_id,
							$is_completed ? 'yes' : 'no',
							$due_date ?: 'none'
						)
					);
					++$migrated;
					continue;
				}

				// Create the new prm_todo CPT post
				$post_data = [
					'post_type'   => 'prm_todo',
					'post_title'  => $todo_content,
					'post_author' => $todo->user_id,
					'post_date'   => $todo->comment_date,
					'post_status' => 'publish',
				];

				$new_post_id = wp_insert_post( $post_data, true );

				if ( is_wp_error( $new_post_id ) ) {
					WP_CLI::warning(
						sprintf(
							'  Failed to create post: %s',
							$new_post_id->get_error_message()
						)
					);
					++$failed;
					continue;
				}

				// Set ACF fields
				update_field( 'related_person', $person_id, $new_post_id );
				update_field( 'is_completed', ! empty( $is_completed ), $new_post_id );

				if ( ! empty( $due_date ) ) {
					update_field( 'due_date', $due_date, $new_post_id );
				}

				// Set visibility to private (todos were always private)
				update_field( '_visibility', 'private', $new_post_id );

				WP_CLI::log( sprintf( '  Created prm_todo post ID %d', $new_post_id ) );

				// Delete the original comment
				$deleted = wp_delete_comment( $todo->comment_ID, true );

				if ( $deleted ) {
					WP_CLI::log( sprintf( '  Deleted original comment ID %d', $todo->comment_ID ) );
				} else {
					WP_CLI::warning( sprintf( '  Failed to delete original comment ID %d', $todo->comment_ID ) );
				}

				++$migrated;
			}

			WP_CLI::log( '' );
			WP_CLI::log( '────────────────────────────────────────────────────────────────' );
			WP_CLI::log( 'Migration Summary:' );
			WP_CLI::log( '────────────────────────────────────────────────────────────────' );

			if ( $dry_run ) {
				WP_CLI::log( sprintf( '  Would migrate: %d', $migrated ) );
				WP_CLI::log( sprintf( '  Would skip: %d', $skipped ) );
				WP_CLI::success( 'Dry run complete. Run without --dry-run to apply changes.' );
			} else {
				WP_CLI::log( sprintf( '  Migrated: %d', $migrated ) );
				WP_CLI::log( sprintf( '  Skipped: %d', $skipped ) );
				WP_CLI::log( sprintf( '  Failed: %d', $failed ) );

				if ( $failed > 0 ) {
					WP_CLI::warning( sprintf( 'Migration completed with %d failure(s).', $failed ) );
				} else {
					WP_CLI::success( 'Migration complete! All todos migrated to CPT.' );
				}
			}
		}

		/**
		 * Migrate todos from related_person to related_persons
		 *
		 * This command migrates existing todos from single-person (related_person)
		 * to multi-person (related_persons) field format.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm todos migrate-persons
		 *     wp prm todos migrate-persons --dry-run
		 *
		 * @when after_wp_load
		 */
		public function migrate_persons( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			}

			WP_CLI::log( '' );
			WP_CLI::log( '╔════════════════════════════════════════════════════════════╗' );
			WP_CLI::log( '║         Caelis Todo Persons Migration                      ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );
			WP_CLI::log( 'This migration will:' );
			WP_CLI::log( '  1. Find all todos with old related_person field' );
			WP_CLI::log( '  2. Convert single person to related_persons array' );
			WP_CLI::log( '  3. Remove old related_person meta' );
			WP_CLI::log( '' );

			// Query all prm_todo posts (bypass access control)
			global $wpdb;
			$todo_ids = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'prm_todo'
                 AND post_status IN ('prm_open', 'prm_awaiting', 'prm_completed', 'publish')"
			);

			if ( empty( $todo_ids ) ) {
				WP_CLI::success( 'No todos found. Nothing to migrate.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d todo(s) to check.', count( $todo_ids ) ) );
			WP_CLI::log( '' );

			$migrated                 = 0;
			$skipped_already_migrated = 0;
			$skipped_no_person        = 0;

			foreach ( $todo_ids as $todo_id ) {
				// Check if already has new format (related_persons)
				$new_field = get_field( 'related_persons', $todo_id );
				if ( $new_field && is_array( $new_field ) && count( $new_field ) > 0 ) {
					++$skipped_already_migrated;
					continue;
				}

				// Get old single value (check raw meta since field name changed)
				$old_value = get_post_meta( $todo_id, 'related_person', true );
				if ( ! $old_value ) {
					++$skipped_no_person;
					continue;
				}

				$old_person_id = (int) $old_value;
				$person_title  = get_the_title( $old_person_id ) ?: 'Unknown';
				$todo_title    = get_the_title( $todo_id );

				WP_CLI::log(
					sprintf(
						'Todo #%d: "%s" → person %d (%s) → [%d]',
						$todo_id,
						wp_trim_words( $todo_title, 8 ),
						$old_person_id,
						$person_title,
						$old_person_id
					)
				);

				if ( ! $dry_run ) {
					// Save new array format
					update_field( 'related_persons', [ $old_person_id ], $todo_id );
					// Remove old meta
					delete_post_meta( $todo_id, 'related_person' );
				}

				++$migrated;
			}

			WP_CLI::log( '' );
			WP_CLI::log( '────────────────────────────────────────────────────────────────' );
			WP_CLI::log( 'Migration Summary:' );
			WP_CLI::log( '────────────────────────────────────────────────────────────────' );

			$action = $dry_run ? 'Would migrate' : 'Migrated';
			WP_CLI::log( sprintf( '  %s: %d', $action, $migrated ) );
			WP_CLI::log( sprintf( '  Skipped (already migrated): %d', $skipped_already_migrated ) );
			WP_CLI::log( sprintf( '  Skipped (no person set): %d', $skipped_no_person ) );

			if ( $dry_run ) {
				WP_CLI::success( 'Dry run complete. Run without --dry-run to apply changes.' );
			} else {
				WP_CLI::success( sprintf( 'Migration complete! %d todo(s) migrated to multi-person format.', $migrated ) );
			}
		}
	}

	/**
	 * Calendar Sync WP-CLI Commands
	 */
	class PRM_Calendar_CLI_Command {

		/**
		 * Sync calendar events from connected calendars
		 *
		 * ## OPTIONS
		 *
		 * [--user=<user_id>]
		 * : User ID to sync calendars for (syncs specific user only)
		 *
		 * [--all]
		 * : Sync all users immediately (ignores rate limiting)
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm calendar sync
		 *     wp prm calendar sync --user=1
		 *     wp prm calendar sync --all
		 *
		 * @when after_wp_load
		 */
		public function sync( $args, $assoc_args ) {
			$specific_user_id = isset( $assoc_args['user'] ) ? (int) $assoc_args['user'] : null;
			$sync_all         = isset( $assoc_args['all'] );

			if ( $specific_user_id && $sync_all ) {
				WP_CLI::error( 'Cannot use both --user and --all options together.' );
				return;
			}

			if ( $sync_all ) {
				WP_CLI::log( 'Syncing all users (ignoring rate limiting)...' );
				WP_CLI::log( '' );

				$results = PRM_Calendar_Sync::force_sync_all();

				if ( empty( $results ) ) {
					WP_CLI::warning( 'No users with calendar connections found.' );
					return;
				}

				$total_connections = 0;
				$total_events      = 0;
				$total_errors      = 0;

				foreach ( $results as $user_result ) {
					$user      = get_userdata( $user_result['user_id'] );
					$user_name = $user ? $user->display_name : 'Unknown';

					WP_CLI::log( sprintf( 'User: %s (ID: %d)', $user_name, $user_result['user_id'] ) );

					foreach ( $user_result['connections'] as $conn ) {
						++$total_connections;

						if ( 'success' === $conn['status'] ) {
							WP_CLI::log(
								sprintf(
									'  [OK] Connection %s: %d events (%d created, %d updated)',
									$conn['id'],
									$conn['total'],
									$conn['created'],
									$conn['updated']
								)
							);
							$total_events += $conn['total'];
						} else {
							WP_CLI::log(
								sprintf(
									'  [ERROR] Connection %s: %s',
									$conn['id'],
									$conn['error']
								)
							);
							++$total_errors;
						}
					}
					WP_CLI::log( '' );
				}

				WP_CLI::success(
					sprintf(
						'Sync complete: %d user(s), %d connection(s), %d event(s) processed, %d error(s)',
						count( $results ),
						$total_connections,
						$total_events,
						$total_errors
					)
				);
				return;
			}

			if ( $specific_user_id ) {
				$user = get_userdata( $specific_user_id );
				if ( ! $user ) {
					WP_CLI::error( sprintf( 'User with ID %d not found.', $specific_user_id ) );
					return;
				}

				WP_CLI::log( sprintf( 'Syncing calendars for user: %s (ID: %d)', $user->display_name, $specific_user_id ) );
				WP_CLI::log( '' );

				$connections = PRM_Calendar_Connections::get_user_connections( $specific_user_id );

				if ( empty( $connections ) ) {
					WP_CLI::warning( 'No calendar connections found for this user.' );
					return;
				}

				$synced = 0;
				$errors = 0;

				foreach ( $connections as $connection ) {
					if ( empty( $connection['sync_enabled'] ) ) {
						WP_CLI::log( sprintf( '  [SKIP] Connection %s: sync disabled', $connection['id'] ) );
						continue;
					}

					$provider              = $connection['provider'] ?? '';
					$connection['user_id'] = $specific_user_id;

					try {
						if ( 'caldav' === $provider ) {
							$result = PRM_CalDAV_Provider::sync( $specific_user_id, $connection );
						} elseif ( 'google' === $provider ) {
							$result = PRM_Google_Calendar_Provider::sync( $specific_user_id, $connection );
						} else {
							WP_CLI::log( sprintf( '  [SKIP] Connection %s: unknown provider "%s"', $connection['id'], $provider ) );
							continue;
						}

						PRM_Calendar_Connections::update_connection(
							$specific_user_id,
							$connection['id'],
							[
								'last_sync'  => current_time( 'c' ),
								'last_error' => null,
							]
						);

						WP_CLI::log(
							sprintf(
								'  [OK] Connection %s: %d events (%d created, %d updated)',
								$connection['id'],
								$result['total'],
								$result['created'],
								$result['updated']
							)
						);
						++$synced;

					} catch ( Exception $e ) {
						PRM_Calendar_Connections::update_connection(
							$specific_user_id,
							$connection['id'],
							[
								'last_error' => $e->getMessage(),
							]
						);

						WP_CLI::log( sprintf( '  [ERROR] Connection %s: %s', $connection['id'], $e->getMessage() ) );
						++$errors;
					}
				}

				WP_CLI::log( '' );
				WP_CLI::success( sprintf( 'Sync complete: %d synced, %d errors', $synced, $errors ) );
				return;
			}

			// Default: run the normal background sync (one user, rate limited)
			WP_CLI::log( 'Running background sync (rate-limited, one user)...' );
			WP_CLI::log( '' );

			$calendar_sync = new PRM_Calendar_Sync();
			$calendar_sync->run_background_sync();

			WP_CLI::success( 'Background sync completed. Check error log for details.' );
		}

		/**
		 * Show sync status and schedule information
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm calendar status
		 *
		 * @when after_wp_load
		 */
		public function status( $args, $assoc_args ) {
			WP_CLI::log( '' );
			WP_CLI::log( '╔════════════════════════════════════════════════════════════╗' );
			WP_CLI::log( '║         Calendar Sync Status                               ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );

			$status = PRM_Calendar_Sync::get_sync_status();

			// Cron schedule status
			if ( $status['is_scheduled'] ) {
				WP_CLI::log( sprintf( 'Cron Status: Scheduled' ) );
				WP_CLI::log( sprintf( 'Next Run: %s', $status['next_scheduled'] ) );
			} else {
				WP_CLI::log( sprintf( 'Cron Status: NOT SCHEDULED' ) );
				WP_CLI::log( '' );
				WP_CLI::warning( 'Calendar sync cron is not scheduled. Try deactivating and reactivating the theme.' );
			}

			WP_CLI::log( sprintf( 'Schedule: %s (every 15 minutes)', $status['cron_schedule'] ) );
			WP_CLI::log( '' );

			// User stats
			WP_CLI::log( sprintf( 'Users with Sync-Enabled Connections: %d', $status['total_users_with_connections'] ) );
			WP_CLI::log( sprintf( 'Current User Index (round-robin): %d', $status['current_user_index'] ) );

			if ( $status['total_users_with_connections'] > 0 ) {
				WP_CLI::log( sprintf( 'Estimated Full Cycle: %d minutes', $status['estimated_full_cycle_minutes'] ) );
			}

			WP_CLI::log( '' );

			// List users with connections
			global $wpdb;
			$user_ids = $wpdb->get_col(
				"SELECT DISTINCT user_id
                 FROM {$wpdb->usermeta}
                 WHERE meta_key = '_prm_calendar_connections'"
			);

			if ( ! empty( $user_ids ) ) {
				WP_CLI::log( 'Users with Calendar Connections:' );
				WP_CLI::log( '' );

				foreach ( $user_ids as $user_id ) {
					$user        = get_userdata( $user_id );
					$user_name   = $user ? $user->display_name : 'Unknown';
					$connections = PRM_Calendar_Connections::get_user_connections( (int) $user_id );

					$enabled_count = 0;
					foreach ( $connections as $conn ) {
						if ( ! empty( $conn['sync_enabled'] ) ) {
							++$enabled_count;
						}
					}

					WP_CLI::log(
						sprintf(
							'  User %s (ID: %d): %d connection(s), %d sync-enabled',
							$user_name,
							$user_id,
							count( $connections ),
							$enabled_count
						)
					);

					foreach ( $connections as $conn ) {
						$status_icon = ! empty( $conn['sync_enabled'] ) ? '[ON]' : '[OFF]';
						$last_sync   = $conn['last_sync'] ?? 'Never';
						$last_error  = $conn['last_error'] ?? null;

						WP_CLI::log(
							sprintf(
								'    %s %s (%s) - Last sync: %s%s',
								$status_icon,
								$conn['name'] ?? $conn['id'],
								$conn['provider'] ?? 'unknown',
								$last_sync,
								$last_error ? ' [ERROR: ' . $last_error . ']' : ''
							)
						);
					}
				}
			}

			WP_CLI::log( '' );
		}

		/**
		 * Run auto-logging of past meetings
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm calendar auto-log
		 *
		 * @when after_wp_load
		 */
		public function auto_log( $args, $assoc_args ) {
			WP_CLI::log( 'Running auto-logging for past meetings...' );
			WP_CLI::log( '' );

			$calendar_sync = new PRM_Calendar_Sync();
			$calendar_sync->auto_log_past_meetings();

			WP_CLI::success( 'Auto-logging complete. Check error log for details.' );
		}

		/**
		 * Re-match calendar events against contacts
		 *
		 * Invalidates the email lookup cache and re-matches all calendar events
		 * against the user's contacts. Useful after adding new email addresses
		 * to contacts or after bulk imports.
		 *
		 * ## OPTIONS
		 *
		 * [--user-id=<user_id>]
		 * : User ID to re-match events for (required)
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm calendar rematch --user-id=1
		 *
		 * @when after_wp_load
		 */
		public function rematch( $args, $assoc_args ) {
			$user_id = isset( $assoc_args['user-id'] ) ? (int) $assoc_args['user-id'] : 0;

			if ( ! $user_id ) {
				WP_CLI::error( 'No user ID provided. Use --user-id=ID' );
				return;
			}

			$user = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				WP_CLI::error( "User {$user_id} not found." );
				return;
			}

			WP_CLI::log( "Invalidating email cache for user {$user_id}..." );
			PRM_Calendar_Matcher::invalidate_cache( $user_id );

			WP_CLI::log( "Re-matching calendar events for user {$user_id}..." );
			$count = PRM_Calendar_Matcher::rematch_events_for_user( $user_id );

			WP_CLI::success( "Re-matched {$count} calendar events for user {$user->display_name}." );
		}
	}

	/**
	 * Register WP-CLI commands
	 */
	WP_CLI::add_command( 'prm reminders', 'PRM_Reminders_CLI_Command' );
	WP_CLI::add_command( 'prm migrate', 'PRM_Migration_CLI_Command' );
	WP_CLI::add_command( 'prm vcard', 'PRM_VCard_CLI_Command' );
	WP_CLI::add_command( 'prm visibility', 'PRM_Visibility_CLI_Command' );
	WP_CLI::add_command( 'prm carddav', 'PRM_CardDAV_CLI_Command' );
	WP_CLI::add_command( 'prm multiuser', 'PRM_MultiUser_CLI_Command' );
	WP_CLI::add_command( 'prm dates', 'PRM_Dates_CLI_Command' );
	WP_CLI::add_command( 'prm todos', 'PRM_Todos_CLI_Command' );
	WP_CLI::add_command( 'prm calendar', 'PRM_Calendar_CLI_Command' );
}
