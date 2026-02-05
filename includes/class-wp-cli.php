<?php
/**
 * WP-CLI Commands for Stadion
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Import namespaced classes for WP-CLI commands
use Stadion\Collaboration\Reminders;
use Stadion\Notifications\EmailChannel;
use Stadion\Calendar\Connections;
use Stadion\Calendar\Matcher;
use Stadion\Calendar\Sync;
use Stadion\Calendar\GoogleProvider;
use Stadion\Calendar\CalDAVProvider;
use Stadion\Export\VCard;
use Stadion\Contacts\GoogleContactsSync;
use Stadion\Contacts\GoogleContactsConnection;
use Stadion\Import\GoogleContactsAPI;
use Stadion\Collaboration\CommentTypes;

// Only load if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {

	/**
	 * Reminders WP-CLI Commands
	 */
	class STADION_Reminders_CLI_Command {

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

			$reminders = new Reminders();

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
					$preferred_time = get_user_meta( $user_id, 'stadion_notification_time', true );
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
				$email_channel = new EmailChannel();

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
	class STADION_Migration_CLI_Command {

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

		/**
		 * Migrate birthdates from important_dates to person post_meta
		 *
		 * Finds all birthday important_dates and copies their date_value
		 * to the _birthdate meta key on related persons.
		 *
		 * This command is idempotent - safe to re-run, it overwrites existing values.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them
		 *
		 * ## EXAMPLES
		 *
		 *     wp stadion migrate-birthdates
		 *     wp stadion migrate-birthdates --dry-run
		 *
		 * @when after_wp_load
		 */
		public function migrate_birthdates( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			}

			WP_CLI::log( '' );
			WP_CLI::log( '╔════════════════════════════════════════════════════════════╗' );
			WP_CLI::log( '║         Stadion Birthdate Migration                        ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );
			WP_CLI::log( 'This migration will:' );
			WP_CLI::log( '  1. Find all birthday important_dates with known years' );
			WP_CLI::log( '  2. Copy date_value to _birthdate meta on related persons' );
			WP_CLI::log( '  3. Clear _birthdate on persons with year_unknown birthdays' );
			WP_CLI::log( '' );

			// Query all birthday important_dates
			$birthdays = new \WP_Query(
				[
					'post_type'        => 'important_date',
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'tax_query'        => [
						[
							'taxonomy' => 'date_type',
							'field'    => 'slug',
							'terms'    => 'birthday',
						],
					],
					'suppress_filters' => true, // Bypass access control for migration
				]
			);

			if ( ! $birthdays->have_posts() ) {
				WP_CLI::success( 'No birthday dates found. Nothing to migrate.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d birthday date(s) to process.', $birthdays->post_count ) );
			WP_CLI::log( '' );

			$migrated = 0;
			$cleared  = 0;
			$skipped  = 0;

			while ( $birthdays->have_posts() ) {
				$birthdays->the_post();
				$birthday_id = get_the_ID();

				$date_value     = get_field( 'date_value', $birthday_id );
				$year_unknown   = get_field( 'year_unknown', $birthday_id );
				$related_people = get_field( 'related_people', $birthday_id );

				if ( empty( $related_people ) || ! is_array( $related_people ) ) {
					WP_CLI::log( sprintf( 'Skipping birthday ID %d: no related people', $birthday_id ) );
					++$skipped;
					continue;
				}

				$person_names = array_map(
					function ( $id ) {
						return get_the_title( $id );
					},
					$related_people
				);

				if ( $year_unknown || empty( $date_value ) ) {
					WP_CLI::log(
						sprintf(
							'Birthday ID %d (year unknown): clearing _birthdate for %s',
							$birthday_id,
							implode( ', ', $person_names )
						)
					);

					if ( ! $dry_run ) {
						foreach ( $related_people as $person_id ) {
							delete_post_meta( $person_id, '_birthdate' );
						}
					}
					++$cleared;
				} else {
					WP_CLI::log(
						sprintf(
							'Birthday ID %d (%s): setting _birthdate for %s',
							$birthday_id,
							$date_value,
							implode( ', ', $person_names )
						)
					);

					if ( ! $dry_run ) {
						foreach ( $related_people as $person_id ) {
							update_post_meta( $person_id, '_birthdate', $date_value );
						}
					}
					++$migrated;
				}
			}

			wp_reset_postdata();

			WP_CLI::log( '' );
			WP_CLI::log( '────────────────────────────────────────────────────────────────' );
			WP_CLI::log( 'Migration Summary:' );
			WP_CLI::log( '────────────────────────────────────────────────────────────────' );

			if ( $dry_run ) {
				WP_CLI::log( sprintf( '  Would set birthdates: %d', $migrated ) );
				WP_CLI::log( sprintf( '  Would clear birthdates: %d', $cleared ) );
				WP_CLI::log( sprintf( '  Would skip: %d', $skipped ) );
				WP_CLI::success( 'Dry run complete. Run without --dry-run to apply changes.' );
			} else {
				WP_CLI::log( sprintf( '  Set birthdates: %d', $migrated ) );
				WP_CLI::log( sprintf( '  Cleared birthdates: %d', $cleared ) );
				WP_CLI::log( sprintf( '  Skipped: %d', $skipped ) );
				WP_CLI::success( 'Migration complete!' );
			}
		}
	}

	/**
	 * VCard WP-CLI Commands
	 */
	class STADION_VCard_CLI_Command {

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
			$vcard = VCard::generate( $person );

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
			$parsed = VCard::parse( $vcard_data );

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
	 * CardDAV WP-CLI Commands
	 */
	class STADION_CardDAV_CLI_Command {

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

			$changes        = get_option( 'stadion_carddav_changes', [] );
			$tokens         = get_option( 'stadion_carddav_sync_tokens', [] );
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

			update_option( 'stadion_carddav_changes', $changes );
			update_option( 'stadion_carddav_sync_tokens', $tokens );

			WP_CLI::success( sprintf( 'Queued %d contact(s) for resync. Next sync will pull all contacts.', $total_contacts ) );
			WP_CLI::log( '' );
			WP_CLI::log( 'To trigger the resync, open your CardDAV client (iPhone Contacts, etc.) and pull down to refresh.' );
		}
	}

	/**
	 * Important Dates WP-CLI Commands
	 */
	class STADION_Dates_CLI_Command {

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

				// Generate new title using same logic as STADION_Auto_Title
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
		 * Generate date title from fields (mirrors STADION_Auto_Title logic)
		 *
		 * @param int $post_id Post ID
		 * @return string Generated title
		 */
		private function generate_date_title( $post_id ) {
			// Get date type from taxonomy
			$date_types = wp_get_post_terms( $post_id, 'date_type', [ 'fields' => 'names' ] );
			$type_label = ! empty( $date_types ) ? $date_types[0] : __( 'Date', 'stadion' );

			// Get related people
			$people = get_field( 'related_people', $post_id ) ?: [];

			if ( empty( $people ) ) {
				// translators: %s is the date type label (e.g., "Birthday", "Anniversary").
				return sprintf( __( 'Unnamed %s', 'stadion' ), $type_label );
			}

			// Get full names of related people.
			$names = [];
			foreach ( $people as $person ) {
				$person_id = is_object( $person ) ? $person->ID : $person;
				$full_name = html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' );
				if ( $full_name && __( 'Unnamed Person', 'stadion' ) !== $full_name ) {
					$names[] = $full_name;
				}
			}

			if ( empty( $names ) ) {
				// translators: %s is the date type label (e.g., "Birthday", "Anniversary").
				return sprintf( __( 'Unnamed %s', 'stadion' ), $type_label );
			}

			$count = count( $names );

			// Get date type slug to check for wedding.
			$date_type_slugs = wp_get_post_terms( $post_id, 'date_type', [ 'fields' => 'slugs' ] );
			$type_slug       = ! empty( $date_type_slugs ) ? $date_type_slugs[0] : '';

			// Special handling for wedding type.
			if ( 'wedding' === $type_slug ) {
				if ( $count >= 2 ) {
					// translators: %1$s and %2$s are the names of the people getting married.
					return sprintf( __( 'Wedding of %1$s & %2$s', 'stadion' ), $names[0], $names[1] );
				} elseif ( 1 === $count ) {
					// translators: %s is the name of the person getting married.
					return sprintf( __( 'Wedding of %s', 'stadion' ), $names[0] );
				}
			}

			if ( 1 === $count ) {
				// translators: %1$s is person name, %2$s is date type (e.g., "John's Birthday").
				return sprintf( __( "%1\$s's %2\$s", 'stadion' ), $names[0], $type_label );
			} elseif ( 2 === $count ) {
				// translators: %1$s and %2$s are person names, %3$s is date type (e.g., "John & Jane's Anniversary").
				return sprintf( __( "%1\$s & %2\$s's %3\$s", 'stadion' ), $names[0], $names[1], $type_label );
			} else {
				$first_two = implode( ', ', array_slice( $names, 0, 2 ) );
				$remaining = $count - 2;
				// translators: %1$s is first two names, %2$d is remaining count, %3$s is date type.
				return sprintf( __( '%1$s +%2$d %3$s', 'stadion' ), $first_two, $remaining, $type_label );
			}
		}
	}

	/**
	 * Todos WP-CLI Commands
	 */
	class STADION_Todos_CLI_Command {

		/**
		 * Migrate todos from comment-based storage to CPT-based storage
		 *
		 * This command migrates all stadion_todo comments to the new stadion_todo
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
			WP_CLI::log( '║         Stadion Todo Migration                              ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );
			WP_CLI::log( 'This migration will:' );
			WP_CLI::log( '  1. Find all comment-based todos (stadion_todo comment type)' );
			WP_CLI::log( '  2. Create corresponding stadion_todo CPT posts' );
			WP_CLI::log( '  3. Copy all metadata (is_completed, due_date)' );
			WP_CLI::log( '  4. Delete the original comments after successful migration' );
			WP_CLI::log( '' );

			// Query all stadion_todo comments
			$todos = get_comments(
				[
					'type'   => 'stadion_todo',
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

				// Create the new stadion_todo CPT post
				$post_data = [
					'post_type'   => 'stadion_todo',
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

				WP_CLI::log( sprintf( '  Created stadion_todo post ID %d', $new_post_id ) );

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
			WP_CLI::log( '║         Stadion Todo Persons Migration                      ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );
			WP_CLI::log( 'This migration will:' );
			WP_CLI::log( '  1. Find all todos with old related_person field' );
			WP_CLI::log( '  2. Convert single person to related_persons array' );
			WP_CLI::log( '  3. Remove old related_person meta' );
			WP_CLI::log( '' );

			// Query all stadion_todo posts (bypass access control)
			global $wpdb;
			$todo_ids = $wpdb->get_col(
				"SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'stadion_todo'
                 AND post_status IN ('stadion_open', 'stadion_awaiting', 'stadion_completed', 'publish')"
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
	class STADION_Calendar_CLI_Command {

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

				$results = Sync::force_sync_all();

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

				$connections = Connections::get_user_connections( $specific_user_id );

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
							$result = CalDAVProvider::sync( $specific_user_id, $connection );
						} elseif ( 'google' === $provider ) {
							$result = GoogleProvider::sync( $specific_user_id, $connection );
						} else {
							WP_CLI::log( sprintf( '  [SKIP] Connection %s: unknown provider "%s"', $connection['id'], $provider ) );
							continue;
						}

						Connections::update_connection(
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
						Connections::update_connection(
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

			$calendar_sync = new Sync();
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

			$status = Sync::get_sync_status();

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
                 WHERE meta_key = '_stadion_calendar_connections'"
			);

			if ( ! empty( $user_ids ) ) {
				WP_CLI::log( 'Users with Calendar Connections:' );
				WP_CLI::log( '' );

				foreach ( $user_ids as $user_id ) {
					$user        = get_userdata( $user_id );
					$user_name   = $user ? $user->display_name : 'Unknown';
					$connections = Connections::get_user_connections( (int) $user_id );

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

			$calendar_sync = new Sync();
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
			Matcher::invalidate_cache( $user_id );

			WP_CLI::log( "Re-matching calendar events for user {$user_id}..." );
			$count = Matcher::rematch_events_for_user( $user_id );

			WP_CLI::success( "Re-matched {$count} calendar events for user {$user->display_name}." );
		}
	}

	/**
	 * Google Contacts WP-CLI Commands
	 */
	class STADION_Google_Contacts_CLI_Command {

		/**
		 * Sync Google Contacts for a user
		 *
		 * Triggers delta sync (changes since last sync) by default.
		 * Use --full flag to trigger a complete resync of all contacts.
		 *
		 * ## OPTIONS
		 *
		 * --user-id=<user_id>
		 * : The WordPress user ID to sync contacts for (required)
		 *
		 * [--full]
		 * : Trigger a full resync (import all contacts instead of just changes)
		 *
		 * ## EXAMPLES
		 *
		 *     wp stadion google-contacts sync --user-id=1
		 *     wp stadion google-contacts sync --user-id=1 --full
		 *
		 * @when after_wp_load
		 */
		public function sync( $args, $assoc_args ) {
			$user_id = isset( $assoc_args['user-id'] ) ? (int) $assoc_args['user-id'] : 0;

			if ( ! $user_id ) {
				WP_CLI::error( 'No user ID provided. Use --user-id=ID' );
				return;
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				WP_CLI::error( sprintf( 'User with ID %d not found.', $user_id ) );
				return;
			}

			// Check if user is connected to Google Contacts
			if ( ! GoogleContactsConnection::is_connected( $user_id ) ) {
				WP_CLI::error( sprintf( 'User %s (ID: %d) is not connected to Google Contacts.', $user->display_name, $user_id ) );
				return;
			}

			$is_full = isset( $assoc_args['full'] );

			WP_CLI::log(
				sprintf(
					'Starting %s sync for user: %s (ID: %d)',
					$is_full ? 'full' : 'delta',
					$user->display_name,
					$user_id
				)
			);

			try {
				if ( $is_full ) {
					// Full import using GoogleContactsAPI
					$importer = new GoogleContactsAPI( $user_id );
					$stats    = $importer->import_all();

					WP_CLI::log( '' );
					WP_CLI::log( 'Full Import Results:' );
					WP_CLI::log( sprintf( '  Contacts imported: %d', $stats['contacts_imported'] ?? 0 ) );
					WP_CLI::log( sprintf( '  Contacts updated: %d', $stats['contacts_updated'] ?? 0 ) );
					WP_CLI::log( sprintf( '  Contacts skipped (no email): %d', $stats['contacts_no_email'] ?? 0 ) );
					WP_CLI::log( sprintf( '  Teams created: %d', $stats['teams_created'] ?? 0 ) );
					WP_CLI::log( sprintf( '  Dates created: %d', $stats['dates_created'] ?? 0 ) );
					WP_CLI::log( sprintf( '  Photos imported: %d', $stats['photos_imported'] ?? 0 ) );

					if ( ! empty( $stats['errors'] ) ) {
						WP_CLI::log( '' );
						WP_CLI::warning( sprintf( 'Errors encountered: %d', count( $stats['errors'] ) ) );
						foreach ( $stats['errors'] as $error ) {
							WP_CLI::log( sprintf( '  - %s', $error ) );
						}
					}
				} else {
					// Delta sync using GoogleContactsSync
					$sync_service = new GoogleContactsSync();
					$results      = $sync_service->sync_user_manual( $user_id );

					WP_CLI::log( '' );
					WP_CLI::log( 'Delta Sync Results:' );

					if ( isset( $results['pull'] ) ) {
						$pull = $results['pull'];
						WP_CLI::log(
							sprintf(
								'  Pulled: %d imported, %d updated, %d unlinked',
								$pull['contacts_imported'] ?? 0,
								$pull['contacts_updated'] ?? 0,
								$pull['contacts_unlinked'] ?? 0
							)
						);
					}

					if ( isset( $results['push'] ) ) {
						$push = $results['push'];
						WP_CLI::log(
							sprintf(
								'  Pushed: %d exported, %d failed, %d skipped',
								$push['pushed'] ?? 0,
								$push['failed'] ?? 0,
								$push['skipped'] ?? 0
							)
						);
					}
				}

				WP_CLI::success( 'Sync completed successfully.' );

			} catch ( \Exception $e ) {
				WP_CLI::error( sprintf( 'Sync failed: %s', $e->getMessage() ) );
			}
		}

		/**
		 * Show Google Contacts sync status for a user
		 *
		 * Displays connection details, sync history, and configuration.
		 *
		 * ## OPTIONS
		 *
		 * --user-id=<user_id>
		 * : The WordPress user ID to check status for (required)
		 *
		 * ## EXAMPLES
		 *
		 *     wp stadion google-contacts status --user-id=1
		 *
		 * @when after_wp_load
		 */
		public function status( $args, $assoc_args ) {
			$user_id = isset( $assoc_args['user-id'] ) ? (int) $assoc_args['user-id'] : 0;

			if ( ! $user_id ) {
				WP_CLI::error( 'No user ID provided. Use --user-id=ID' );
				return;
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				WP_CLI::error( sprintf( 'User with ID %d not found.', $user_id ) );
				return;
			}

			$connection = GoogleContactsConnection::get_connection( $user_id );

			WP_CLI::log( '' );
			WP_CLI::log( '╔════════════════════════════════════════════════════════════╗' );
			WP_CLI::log( '║         Google Contacts Status                             ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );

			WP_CLI::log( sprintf( 'User: %s (ID: %d)', $user->display_name, $user_id ) );
			WP_CLI::log( '' );

			if ( ! $connection ) {
				WP_CLI::warning( 'Not connected to Google Contacts.' );
				return;
			}

			// Connection details
			WP_CLI::log( 'Connection Details:' );
			WP_CLI::log( sprintf( '  Connected Email: %s', $connection['email'] ?? 'Unknown' ) );
			WP_CLI::log( sprintf( '  Access Mode: %s', $connection['access_mode'] ?? 'Unknown' ) );
			WP_CLI::log( sprintf( '  Connected At: %s', $connection['connected_at'] ?? 'Unknown' ) );
			WP_CLI::log( sprintf( '  Contact Count: %d', $connection['contact_count'] ?? 0 ) );
			WP_CLI::log( '' );

			// Sync status
			WP_CLI::log( 'Sync Status:' );
			WP_CLI::log( sprintf( '  Last Sync: %s', $connection['last_sync'] ?? 'Never' ) );
			WP_CLI::log( sprintf( '  Sync Frequency: %d minutes', $connection['sync_frequency'] ?? GoogleContactsConnection::get_default_frequency() ) );

			if ( ! empty( $connection['last_error'] ) ) {
				WP_CLI::log( sprintf( '  Last Error: %s', $connection['last_error'] ) );
			} else {
				WP_CLI::log( '  Last Error: None' );
			}

			WP_CLI::log( '' );

			// Sync history
			$history = $connection['sync_history'] ?? [];
			if ( ! empty( $history ) ) {
				WP_CLI::log( 'Recent Sync History (last 10):' );
				WP_CLI::log( '' );

				$table_data = [];
				foreach ( $history as $entry ) {
					$table_data[] = [
						'Timestamp'   => $entry['timestamp'] ?? 'Unknown',
						'Pulled'      => $entry['pulled'] ?? 0,
						'Pushed'      => $entry['pushed'] ?? 0,
						'Errors'      => $entry['errors'] ?? 0,
						'Duration'    => ( $entry['duration_ms'] ?? 0 ) . 'ms',
					];
				}

				WP_CLI\Utils\format_items( 'table', $table_data, [ 'Timestamp', 'Pulled', 'Pushed', 'Errors', 'Duration' ] );
			} else {
				WP_CLI::log( 'No sync history available.' );
			}

			WP_CLI::log( '' );
		}

		/**
		 * List unresolved sync conflicts for a user
		 *
		 * Shows contacts where data conflicted between Google and Stadion during sync.
		 * Stadion wins all conflicts but they are logged for review.
		 *
		 * ## OPTIONS
		 *
		 * --user-id=<user_id>
		 * : The WordPress user ID to check conflicts for (required)
		 *
		 * ## EXAMPLES
		 *
		 *     wp stadion google-contacts conflicts --user-id=1
		 *
		 * @when after_wp_load
		 */
		public function conflicts( $args, $assoc_args ) {
			$user_id = isset( $assoc_args['user-id'] ) ? (int) $assoc_args['user-id'] : 0;

			if ( ! $user_id ) {
				WP_CLI::error( 'No user ID provided. Use --user-id=ID' );
				return;
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				WP_CLI::error( sprintf( 'User with ID %d not found.', $user_id ) );
				return;
			}

			WP_CLI::log( '' );
			WP_CLI::log( sprintf( 'Checking sync conflicts for user: %s (ID: %d)', $user->display_name, $user_id ) );
			WP_CLI::log( '' );

			// Get all person posts owned by this user
			global $wpdb;
			$person_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'person'
					AND post_status = 'publish'
					AND post_author = %d",
					$user_id
				)
			);

			if ( empty( $person_ids ) ) {
				WP_CLI::warning( 'No contacts found for this user.' );
				return;
			}

			// Query comments with sync_conflict activity type for these persons
			$id_placeholders = implode( ',', array_fill( 0, count( $person_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$conflicts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT c.comment_ID, c.comment_post_ID, c.comment_content, c.comment_date
					FROM {$wpdb->comments} c
					INNER JOIN {$wpdb->commentmeta} cm ON c.comment_ID = cm.comment_id
					WHERE c.comment_type = %s
					AND cm.meta_key = 'activity_type'
					AND cm.meta_value = 'sync_conflict'
					AND c.comment_post_ID IN ({$id_placeholders})
					ORDER BY c.comment_date DESC",
					array_merge( [ CommentTypes::TYPE_ACTIVITY ], $person_ids )
				)
			);

			if ( empty( $conflicts ) ) {
				WP_CLI::warning( 'No sync conflicts found.' );
				WP_CLI::log( '' );
				WP_CLI::log( 'This is good! It means Stadion and Google Contacts data have not conflicted.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d conflict(s):', count( $conflicts ) ) );
			WP_CLI::log( '' );

			$table_data = [];
			foreach ( $conflicts as $conflict ) {
				$person_name = get_the_title( $conflict->comment_post_ID );
				$table_data[] = [
					'Person'  => $person_name,
					'Date'    => $conflict->comment_date,
					'Details' => wp_trim_words( $conflict->comment_content, 20 ),
				];
			}

			WP_CLI\Utils\format_items( 'table', $table_data, [ 'Person', 'Date', 'Details' ] );

			WP_CLI::log( '' );
			WP_CLI::log( 'Note: Stadion is the source of truth. Conflicts are auto-resolved in favor of Stadion data.' );
			WP_CLI::log( '' );
		}

		/**
		 * Unlink all contacts from Google to reset sync state
		 *
		 * Removes Google metadata from all contacts owned by the user but preserves
		 * all Stadion data. Useful to reset sync state for a fresh start.
		 *
		 * ## OPTIONS
		 *
		 * --user-id=<user_id>
		 * : The WordPress user ID to unlink contacts for (required)
		 *
		 * [--yes]
		 * : Skip confirmation prompt
		 *
		 * ## EXAMPLES
		 *
		 *     wp stadion google-contacts unlink-all --user-id=1
		 *     wp stadion google-contacts unlink-all --user-id=1 --yes
		 *
		 * @when after_wp_load
		 */
		public function unlink_all( $args, $assoc_args ) {
			$user_id = isset( $assoc_args['user-id'] ) ? (int) $assoc_args['user-id'] : 0;

			if ( ! $user_id ) {
				WP_CLI::error( 'No user ID provided. Use --user-id=ID' );
				return;
			}

			$user = get_userdata( $user_id );
			if ( ! $user ) {
				WP_CLI::error( sprintf( 'User with ID %d not found.', $user_id ) );
				return;
			}

			// Get all person posts with Google contact ID that are owned by user
			global $wpdb;
			$linked_contacts = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT p.ID, pm.meta_value as google_id
					FROM {$wpdb->posts} p
					INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
					WHERE p.post_type = 'person'
					AND p.post_status = 'publish'
					AND p.post_author = %d
					AND pm.meta_key = '_google_contact_id'",
					$user_id
				)
			);

			$count = count( $linked_contacts );

			if ( 0 === $count ) {
				WP_CLI::warning( 'No linked Google Contacts found for this user.' );
				return;
			}

			WP_CLI::log( '' );
			WP_CLI::log( sprintf( 'Found %d contact(s) linked to Google for user: %s (ID: %d)', $count, $user->display_name, $user_id ) );
			WP_CLI::log( '' );

			// Confirm unless --yes flag provided
			if ( ! isset( $assoc_args['yes'] ) ) {
				WP_CLI::confirm( 'This will remove all Google sync metadata from these contacts. Stadion data will be preserved. Continue?' );
			}

			WP_CLI::log( 'Unlinking contacts...' );

			$unlinked = 0;
			foreach ( $linked_contacts as $contact ) {
				delete_post_meta( $contact->ID, '_google_contact_id' );
				delete_post_meta( $contact->ID, '_google_etag' );
				delete_post_meta( $contact->ID, '_google_last_import' );
				delete_post_meta( $contact->ID, '_google_last_export' );
				delete_post_meta( $contact->ID, '_google_synced_fields' );
				++$unlinked;
			}

			// Clear sync token from connection to force full resync on next sync
			GoogleContactsConnection::update_connection( $user_id, [ 'sync_token' => null ] );

			WP_CLI::log( '' );
			WP_CLI::success( sprintf( 'Unlinked %d contact(s). Sync token cleared.', $unlinked ) );
			WP_CLI::log( '' );
			WP_CLI::log( 'To re-sync, run: wp stadion google-contacts sync --user-id=' . $user_id . ' --full' );
		}
	}

	/**
	 * Calendar Event WP-CLI Commands
	 */
	class STADION_Event_CLI_Command {

		/**
		 * Clean up HTML entities in calendar event titles
		 *
		 * Decodes HTML entities like &amp; to & in all calendar event titles.
		 * This fixes titles synced from Google Calendar that have encoded ampersands.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm event cleanup-titles
		 *     wp prm event cleanup-titles --dry-run
		 *
		 * @when after_wp_load
		 */
		public function cleanup_titles( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			}

			WP_CLI::log( 'Cleaning up HTML entities in calendar event titles...' );
			WP_CLI::log( '' );

			// Get all calendar events
			global $wpdb;
			$events = $wpdb->get_results(
				"SELECT ID, post_title FROM {$wpdb->posts}
				 WHERE post_type = 'calendar_event'
				 AND post_status IN ('publish', 'future')"
			);

			if ( empty( $events ) ) {
				WP_CLI::warning( 'No calendar events found.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d calendar event(s) to check.', count( $events ) ) );
			WP_CLI::log( '' );

			$updated = 0;
			$skipped = 0;

			foreach ( $events as $event ) {
				$original_title = $event->post_title;
				$decoded_title  = html_entity_decode( $original_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

				// Skip if no change needed
				if ( $original_title === $decoded_title ) {
					++$skipped;
					continue;
				}

				if ( $dry_run ) {
					WP_CLI::log( sprintf( '[WOULD UPDATE] #%d: "%s" -> "%s"', $event->ID, $original_title, $decoded_title ) );
				} else {
					wp_update_post( [
						'ID'         => $event->ID,
						'post_title' => $decoded_title,
					] );
					WP_CLI::log( sprintf( '[UPDATED] #%d: "%s" -> "%s"', $event->ID, $original_title, $decoded_title ) );
				}

				++$updated;
			}

			WP_CLI::log( '' );

			if ( $dry_run ) {
				WP_CLI::success( sprintf( 'Would update %d event(s). Skipped %d (no changes needed).', $updated, $skipped ) );
			} else {
				WP_CLI::success( sprintf( 'Updated %d event(s). Skipped %d (no changes needed).', $updated, $skipped ) );
			}
		}
	}

	/**
	 * People WP-CLI Commands
	 */
	class STADION_People_CLI_Command {

		/**
		 * Find and merge duplicate people (same email, different records)
		 *
		 * This is useful when parents were created as separate records but the person
		 * already existed as a member. The command will:
		 * - Find people with "Ouder/verzorger" in their name
		 * - Check if their email matches another person
		 * - Merge relationships from the duplicate to the original
		 * - Delete the duplicate
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them.
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm people merge_duplicates
		 *     wp prm people merge_duplicates --dry-run
		 *
		 * @when after_wp_load
		 */
		public function merge_duplicates( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'Dry run mode - no changes will be saved.' );
			}

			// Get all people using direct DB query to bypass access control hooks
			global $wpdb;
			$person_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
					'person',
					'publish'
				)
			);

			$people = array_map( 'get_post', $person_ids );
			$people = array_filter( $people ); // Remove any null values

			// Build person ID -> name_key map for quick lookups
			$person_name_keys = [];
			// Build email -> person IDs map for ALL people
			$email_to_persons = [];
			// Build name -> person IDs map (first_name + last_name, lowercased)
			$name_to_persons = [];

			foreach ( $people as $person ) {
				// Collect name key for this person
				$first_name = get_field( 'first_name', $person->ID ) ?: '';
				$last_name  = get_field( 'last_name', $person->ID ) ?: '';
				$name_key   = strtolower( trim( trim( $first_name ) . ' ' . trim( $last_name ) ) );

				// Store name key for this person
				$person_name_keys[ $person->ID ] = $name_key;

				// Only use non-empty names for name-based matching
				if ( ! empty( $name_key ) ) {
					if ( ! isset( $name_to_persons[ $name_key ] ) ) {
						$name_to_persons[ $name_key ] = [];
					}
					$name_to_persons[ $name_key ][] = $person->ID;
				}

				// Collect emails for this person
				$contact_info = get_field( 'contact_info', $person->ID ) ?: [];
				foreach ( $contact_info as $contact ) {
					if ( 'email' === $contact['contact_type'] && ! empty( $contact['contact_value'] ) ) {
						$email = strtolower( trim( $contact['contact_value'] ) );
						if ( ! isset( $email_to_persons[ $email ] ) ) {
							$email_to_persons[ $email ] = [];
						}
						$email_to_persons[ $email ][] = $person->ID;
					}
				}
			}

			// Find duplicates: require BOTH same name AND same email
			$duplicate_sets = [];

			// For email matches, only consider them duplicates if names also match
			foreach ( $email_to_persons as $email => $email_person_ids ) {
				$unique_ids = array_unique( $email_person_ids );
				if ( count( $unique_ids ) <= 1 ) {
					continue;
				}

				// Group by name within this email group
				$name_groups = [];
				foreach ( $unique_ids as $pid ) {
					$name_key = $person_name_keys[ $pid ] ?? '';
					if ( ! empty( $name_key ) ) {
						if ( ! isset( $name_groups[ $name_key ] ) ) {
							$name_groups[ $name_key ] = [];
						}
						$name_groups[ $name_key ][] = $pid;
					}
				}

				// Only create duplicate sets for name groups with multiple people
				foreach ( $name_groups as $name_key => $group_ids ) {
					if ( count( $group_ids ) > 1 ) {
						sort( $group_ids ); // Lowest ID first (original)
						$key = 'email+name:' . $email . '|' . $name_key;
						$duplicate_sets[ $key ] = $group_ids;
					}
				}
			}

			// Also find name-only duplicates (same name, no shared email)
			foreach ( $name_to_persons as $name_key => $name_person_ids ) {
				$unique_ids = array_unique( $name_person_ids );
				if ( count( $unique_ids ) <= 1 ) {
					continue;
				}

				sort( $unique_ids ); // Lowest ID first (original)
				$key = 'name:' . $name_key;

				// Check if already covered by email+name match
				$already_covered = false;
				foreach ( $duplicate_sets as $existing_key => $existing_ids ) {
					if ( $unique_ids == $existing_ids ) {
						$already_covered = true;
						break;
					}
				}

				if ( ! $already_covered ) {
					$duplicate_sets[ $key ] = $unique_ids;
				}
			}

			$email_name_count = count( array_filter( array_keys( $duplicate_sets ), function( $k ) { return strpos( $k, 'email+name:' ) === 0; } ) );
			$name_count       = count( array_filter( array_keys( $duplicate_sets ), function( $k ) { return strpos( $k, 'name:' ) === 0; } ) );
			WP_CLI::log( sprintf( 'Found %d email+name and %d name-only duplicate set(s).', $email_name_count, $name_count ) );

			$merged  = 0;
			$skipped = 0;

			foreach ( $duplicate_sets as $match_key => $person_ids ) {
				$original_id = array_shift( $person_ids ); // Keep the lowest ID
				$original_title = get_the_title( $original_id );

				foreach ( $person_ids as $duplicate_id ) {
					$duplicate_title = get_the_title( $duplicate_id );

					WP_CLI::log( sprintf(
						'[MERGE] "%s" (ID: %d) -> "%s" (ID: %d) [%s]',
						$duplicate_title,
						$duplicate_id,
						$original_title,
						$original_id,
						$match_key
					) );

					if ( ! $dry_run ) {
						$this->merge_person( $duplicate_id, $original_id );
					}

					$merged++;
				}
			}

			if ( $dry_run ) {
				WP_CLI::success( sprintf( 'Would merge %d duplicate(s) from %d set(s).', $merged, count( $duplicate_sets ) ) );
			} else {
				WP_CLI::success( sprintf( 'Merged %d duplicate(s) from %d set(s).', $merged, count( $duplicate_sets ) ) );
			}
		}

		/**
		 * Merge a duplicate person into the original
		 *
		 * @param int $duplicate_id The duplicate person ID to merge from and delete.
		 * @param int $original_id  The original person ID to merge into.
		 */
		private function merge_person( $duplicate_id, $original_id ) {
			// Get relationships from both records
			$duplicate_relationships = get_field( 'relationships', $duplicate_id ) ?: [];
			$original_relationships  = get_field( 'relationships', $original_id ) ?: [];

			// Get existing related person IDs from original
			$existing_related_ids = array_map(
				function ( $r ) {
					return $r['related_person'];
				},
				$original_relationships
			);

			// Add relationships from duplicate that don't exist in original
			$new_relationships = [];
			foreach ( $duplicate_relationships as $rel ) {
				if ( ! in_array( $rel['related_person'], $existing_related_ids, true ) ) {
					$new_relationships[] = $rel;
				}
			}

			if ( ! empty( $new_relationships ) ) {
				$merged_relationships = array_merge( $original_relationships, $new_relationships );
				update_field( 'relationships', $merged_relationships, $original_id );
				WP_CLI::log( sprintf( '  - Added %d relationship(s) to original.', count( $new_relationships ) ) );
			}

			// Update any references to the duplicate in other people's relationships
			$this->update_relationship_references( $duplicate_id, $original_id );

			// Update any important_date records that reference the duplicate
			$this->update_date_references( $duplicate_id, $original_id );

			// Delete the duplicate
			wp_delete_post( $duplicate_id, true );
			WP_CLI::log( sprintf( '  - Deleted duplicate (ID: %d).', $duplicate_id ) );
		}

		/**
		 * Update relationship references from duplicate to original
		 *
		 * @param int $duplicate_id The duplicate person ID.
		 * @param int $original_id  The original person ID.
		 */
		private function update_relationship_references( $duplicate_id, $original_id ) {
			$people = get_posts(
				[
					'post_type'        => 'person',
					'posts_per_page'   => -1,
					'post_status'      => 'publish',
					'suppress_filters' => true,
					'meta_query'       => [
						[
							'key'     => 'relationships',
							'compare' => 'EXISTS',
						],
					],
				]
			);

			$updated = 0;
			foreach ( $people as $person ) {
				if ( $person->ID === $duplicate_id || $person->ID === $original_id ) {
					continue;
				}

				$relationships = get_field( 'relationships', $person->ID ) ?: [];
				$changed       = false;

				foreach ( $relationships as &$rel ) {
					if ( (int) $rel['related_person'] === $duplicate_id ) {
						$rel['related_person'] = $original_id;
						$changed               = true;
					}
				}
				unset( $rel );

				if ( $changed ) {
					update_field( 'relationships', $relationships, $person->ID );
					$updated++;
				}
			}

			if ( $updated > 0 ) {
				WP_CLI::log( sprintf( '  - Updated %d relationship reference(s).', $updated ) );
			}
		}

		/**
		 * Update important_date references from duplicate to original
		 *
		 * @param int $duplicate_id The duplicate person ID.
		 * @param int $original_id  The original person ID.
		 */
		private function update_date_references( $duplicate_id, $original_id ) {
			$dates = get_posts(
				[
					'post_type'        => 'important_date',
					'posts_per_page'   => -1,
					'post_status'      => 'publish',
					'suppress_filters' => true,
				]
			);

			$updated = 0;
			foreach ( $dates as $date ) {
				$related_people = get_field( 'related_people', $date->ID ) ?: [];
				$changed        = false;

				foreach ( $related_people as $index => $person_id ) {
					$pid = is_object( $person_id ) ? $person_id->ID : $person_id;
					if ( (int) $pid === $duplicate_id ) {
						$related_people[ $index ] = $original_id;
						$changed                  = true;
					}
				}

				if ( $changed ) {
					// Remove duplicates (in case original was already in the list)
					$related_people = array_unique( $related_people );
					update_field( 'related_people', array_values( $related_people ), $date->ID );
					$updated++;
				}
			}

			if ( $updated > 0 ) {
				WP_CLI::log( sprintf( '  - Updated %d important date reference(s).', $updated ) );
			}
		}

		/**
		 * Backfill volunteer status for all people
		 *
		 * Recalculates and updates the huidig-vrijwilliger field for all people
		 * based on their current work history entries.
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them.
		 *
		 * [--force]
		 * : Force update all records even if value unchanged (fixes ACF refs).
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm people backfill_volunteer_status
		 *     wp prm people backfill_volunteer_status --dry-run
		 *     wp prm people backfill_volunteer_status --force
		 *
		 * @when after_wp_load
		 */
		public function backfill_volunteer_status( $args, $assoc_args ) {
			global $wpdb;

			$dry_run = isset( $assoc_args['dry-run'] );
			$force   = isset( $assoc_args['force'] );

			if ( $dry_run ) {
				WP_CLI::log( 'Dry run mode - no changes will be made.' );
			}

			if ( $force ) {
				WP_CLI::log( 'Force mode - updating all records to fix ACF references.' );
			}

			// Get all person IDs using direct DB query to bypass access control hooks
			$person_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
					'person',
					'publish'
				)
			);

			$total   = count( $person_ids );
			$updated = 0;
			$skipped = 0;
			$set_true  = 0;
			$set_false = 0;

			WP_CLI::log( sprintf( 'Processing %d people...', $total ) );

			$volunteer_status = new \Stadion\Core\VolunteerStatus();
			$progress         = \WP_CLI\Utils\make_progress_bar( 'Backfilling volunteer status', $total );

			foreach ( $person_ids as $person_id ) {
				$person_id = (int) $person_id;

				// Get current value
				$current_value = get_post_meta( $person_id, 'huidig-vrijwilliger', true );

				// Calculate new value using reflection to access private method
				$reflection = new \ReflectionClass( $volunteer_status );
				$method     = $reflection->getMethod( 'is_current_volunteer' );
				$method->setAccessible( true );
				$is_volunteer = $method->invoke( $volunteer_status, $person_id );

				$new_value = $is_volunteer ? '1' : '0';

				// Check if value needs updating (or force mode is enabled)
				$needs_update = $force || $current_value !== $new_value;

				if ( $needs_update ) {
					if ( ! $dry_run ) {
						// Use update_field() to properly set ACF reference key
						update_field( 'field_custom_person_huidig-vrijwilliger', $new_value, $person_id );
					}
					$updated++;

					if ( $is_volunteer ) {
						$set_true++;
					} else {
						$set_false++;
					}

					// Only log actual changes (not force-updates with same value)
					if ( $current_value !== $new_value ) {
						$name = get_the_title( $person_id );
						WP_CLI::log( sprintf(
							'  %s (ID: %d): %s -> %s',
							$name,
							$person_id,
							$current_value === '' ? '(empty)' : ( $current_value === '1' ? 'true' : 'false' ),
							$is_volunteer ? 'true' : 'false'
						) );
					}
				} else {
					$skipped++;
				}

				$progress->tick();
			}

			$progress->finish();

			if ( $dry_run ) {
				WP_CLI::success( sprintf(
					'Would update %d people (%d set to true, %d set to false), %d already correct.',
					$updated,
					$set_true,
					$set_false,
					$skipped
				) );
			} else {
				WP_CLI::success( sprintf(
					'Updated %d people (%d set to true, %d set to false), %d already correct.',
					$updated,
					$set_true,
					$set_false,
					$skipped
				) );
			}
		}

		/**
		 * Clear all work history entries from all people
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Preview changes without making them.
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm people clear_work_history
		 *     wp prm people clear_work_history --dry-run
		 *
		 * @when after_wp_load
		 */
		public function clear_work_history( $args, $assoc_args ) {
			global $wpdb;

			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'Dry run mode - no changes will be made.' );
			}

			// Count work_history meta entries
			$count = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE 'work_history%'"
			);

			WP_CLI::log( sprintf( 'Found %d work_history meta entries.', $count ) );

			if ( $count === 0 ) {
				WP_CLI::success( 'No work history entries to delete.' );
				return;
			}

			if ( ! $dry_run ) {
				$deleted = $wpdb->query(
					"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'work_history%'"
				);

				WP_CLI::success( sprintf( 'Deleted %d work_history meta entries.', $deleted ) );
			} else {
				WP_CLI::log( sprintf( 'Would delete %d work_history meta entries.', $count ) );
			}
		}
	}

	/**
	 * Relationships WP-CLI Commands
	 */
	class STADION_Relationships_CLI_Command {

		/**
		 * Sync sibling relationships based on existing parent-child data
		 *
		 * ## OPTIONS
		 *
		 * [--dry-run]
		 * : Show what would be created without making changes
		 *
		 * ## EXAMPLES
		 *
		 *     wp prm relationships sync-siblings
		 *     wp prm relationships sync-siblings --dry-run
		 *
		 * @when after_wp_load
		 */
		public function sync_siblings( $args, $assoc_args ) {
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
				WP_CLI::log( '' );
			}

			WP_CLI::log( 'Analyzing parent-child relationships...' );

			// Get the sibling relationship type
			$sibling_term = get_term_by( 'slug', 'sibling', 'relationship_type' );
			if ( ! $sibling_term || is_wp_error( $sibling_term ) ) {
				WP_CLI::error( 'Sibling relationship type not found.' );
				return;
			}
			$sibling_type_id = $sibling_term->term_id;

			// Get all people
			$people = get_posts(
				[
					'post_type'      => 'person',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
				]
			);

			if ( empty( $people ) ) {
				WP_CLI::warning( 'No people found in the system.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d people to analyze.', count( $people ) ) );
			WP_CLI::log( '' );

			// Build a map of parents to their children
			$parent_children_map = [];

			foreach ( $people as $person ) {
				$relationships = get_field( 'relationships', $person->ID );
				if ( ! is_array( $relationships ) ) {
					continue;
				}

				foreach ( $relationships as $rel ) {
					// Normalize relationship data
					$related_person_id = null;
					if ( isset( $rel['related_person'] ) ) {
						if ( is_numeric( $rel['related_person'] ) ) {
							$related_person_id = (int) $rel['related_person'];
						} elseif ( is_object( $rel['related_person'] ) && isset( $rel['related_person']->ID ) ) {
							$related_person_id = (int) $rel['related_person']->ID;
						}
					}

					$relationship_type_id = null;
					if ( isset( $rel['relationship_type'] ) ) {
						if ( is_numeric( $rel['relationship_type'] ) ) {
							$relationship_type_id = (int) $rel['relationship_type'];
						} elseif ( is_object( $rel['relationship_type'] ) && isset( $rel['relationship_type']->term_id ) ) {
							$relationship_type_id = (int) $rel['relationship_type']->term_id;
						} elseif ( is_array( $rel['relationship_type'] ) && isset( $rel['relationship_type']['term_id'] ) ) {
							$relationship_type_id = (int) $rel['relationship_type']['term_id'];
						}
					}

					// Check if this is a Parent relationship (type 8)
					// Type 8 means "the related person is my parent"
					if ( $relationship_type_id == 8 && $related_person_id ) {
						// person->ID is the child, related_person_id is the parent
						if ( ! isset( $parent_children_map[ $related_person_id ] ) ) {
							$parent_children_map[ $related_person_id ] = [];
						}
						$parent_children_map[ $related_person_id ][] = $person->ID;
					}
				}
			}

			// Count parents with multiple children
			$families_count     = 0;
			$total_siblings     = 0;
			$created_count      = 0;
			$already_exist      = 0;

			foreach ( $parent_children_map as $parent_id => $children ) {
				if ( count( $children ) < 2 ) {
					continue; // Skip parents with only one child
				}

				$families_count++;
				$parent = get_post( $parent_id );
				$parent_name = $parent ? $parent->post_title : "ID $parent_id";

				WP_CLI::log( sprintf( 'Family: %s (%d children)', $parent_name, count( $children ) ) );

				// Create sibling relationships between all pairs of children
				for ( $i = 0; $i < count( $children ); $i++ ) {
					for ( $j = $i + 1; $j < count( $children ); $j++ ) {
						$child_a = $children[ $i ];
						$child_b = $children[ $j ];
						$total_siblings++;

						// Check if A -> B sibling relationship exists
						$a_to_b_exists = $this->has_sibling_relationship( $child_a, $child_b, $sibling_type_id );
						// Check if B -> A sibling relationship exists
						$b_to_a_exists = $this->has_sibling_relationship( $child_b, $child_a, $sibling_type_id );

						$child_a_post = get_post( $child_a );
						$child_b_post = get_post( $child_b );
						$child_a_name = $child_a_post ? $child_a_post->post_title : "ID $child_a";
						$child_b_name = $child_b_post ? $child_b_post->post_title : "ID $child_b";

						if ( ! $a_to_b_exists ) {
							if ( $dry_run ) {
								WP_CLI::log( sprintf( '  Would create: %s -> %s', $child_a_name, $child_b_name ) );
							} else {
								$this->add_sibling_relationship( $child_a, $child_b, $sibling_type_id );
								WP_CLI::log( sprintf( '  Created: %s -> %s', $child_a_name, $child_b_name ) );
							}
							$created_count++;
						} else {
							$already_exist++;
						}

						if ( ! $b_to_a_exists ) {
							if ( $dry_run ) {
								WP_CLI::log( sprintf( '  Would create: %s -> %s', $child_b_name, $child_a_name ) );
							} else {
								$this->add_sibling_relationship( $child_b, $child_a, $sibling_type_id );
								WP_CLI::log( sprintf( '  Created: %s -> %s', $child_b_name, $child_a_name ) );
							}
							$created_count++;
						} else {
							$already_exist++;
						}
					}
				}

				WP_CLI::log( '' );
			}

			// Summary
			WP_CLI::log( '---' );
			WP_CLI::log( sprintf( 'Families processed: %d', $families_count ) );
			WP_CLI::log( sprintf( 'Total sibling pairs: %d', $total_siblings ) );
			WP_CLI::log( sprintf( 'Already existed: %d', $already_exist ) );

			if ( $dry_run ) {
				WP_CLI::success( sprintf( 'Would create %d sibling relationships.', $created_count ) );
				WP_CLI::log( '' );
				WP_CLI::log( 'Run without --dry-run to apply changes.' );
			} else {
				WP_CLI::success( sprintf( 'Created %d sibling relationships.', $created_count ) );
			}
		}

		/**
		 * Check if a sibling relationship exists from person A to person B
		 *
		 * @param int $from_person_id Person who has the relationship
		 * @param int $to_person_id The sibling they're related to
		 * @param int $sibling_type_id The sibling relationship type ID
		 * @return bool True if relationship exists
		 */
		private function has_sibling_relationship( $from_person_id, $to_person_id, $sibling_type_id ) {
			$relationships = get_field( 'relationships', $from_person_id );
			if ( ! is_array( $relationships ) ) {
				return false;
			}

			foreach ( $relationships as $rel ) {
				$related_person_id = null;
				if ( isset( $rel['related_person'] ) ) {
					if ( is_numeric( $rel['related_person'] ) ) {
						$related_person_id = (int) $rel['related_person'];
					} elseif ( is_object( $rel['related_person'] ) && isset( $rel['related_person']->ID ) ) {
						$related_person_id = (int) $rel['related_person']->ID;
					}
				}

				$relationship_type_id = null;
				if ( isset( $rel['relationship_type'] ) ) {
					if ( is_numeric( $rel['relationship_type'] ) ) {
						$relationship_type_id = (int) $rel['relationship_type'];
					} elseif ( is_object( $rel['relationship_type'] ) && isset( $rel['relationship_type']->term_id ) ) {
						$relationship_type_id = (int) $rel['relationship_type']->term_id;
					} elseif ( is_array( $rel['relationship_type'] ) && isset( $rel['relationship_type']['term_id'] ) ) {
						$relationship_type_id = (int) $rel['relationship_type']['term_id'];
					}
				}

				if ( $related_person_id == $to_person_id && $relationship_type_id == $sibling_type_id ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Add a sibling relationship from person A to person B
		 *
		 * @param int $from_person_id Person who will have the relationship added
		 * @param int $to_person_id The sibling they're related to
		 * @param int $sibling_type_id The sibling relationship type ID
		 */
		private function add_sibling_relationship( $from_person_id, $to_person_id, $sibling_type_id ) {
			$relationships = get_field( 'relationships', $from_person_id );
			if ( ! is_array( $relationships ) ) {
				$relationships = [];
			}

			// Add the sibling relationship
			$relationships[] = [
				'related_person'     => $to_person_id,
				'relationship_type'  => $sibling_type_id,
				'relationship_label' => '',
			];

			// Save relationships
			update_field( 'relationships', $relationships, $from_person_id );
		}
	}

	/**
	 * Tasks WP-CLI Commands
	 */
	class STADION_Tasks_CLI_Command {

		/**
		 * Verify or fix task ownership
		 *
		 * Ensures all stadion_todo posts have valid post_author.
		 * Tasks with invalid authors can be fixed by inferring from related persons.
		 *
		 * ## OPTIONS
		 *
		 * [--verify]
		 * : Only verify ownership, report issues without fixing
		 *
		 * [--dry-run]
		 * : Show what would be fixed without making changes
		 *
		 * ## EXAMPLES
		 *
		 *     wp stadion tasks verify-ownership --verify
		 *     wp stadion tasks verify-ownership --dry-run
		 *     wp stadion tasks verify-ownership
		 *
		 * @when after_wp_load
		 */
		public function verify_ownership( $args, $assoc_args ) {
			$verify  = isset( $assoc_args['verify'] );
			$dry_run = isset( $assoc_args['dry-run'] );

			if ( $dry_run ) {
				WP_CLI::log( 'DRY RUN MODE - No changes will be made' );
			}

			WP_CLI::log( '' );
			WP_CLI::log( '╔════════════════════════════════════════════════════════════╗' );
			WP_CLI::log( '║         Task Ownership Verification                        ║' );
			WP_CLI::log( '╚════════════════════════════════════════════════════════════╝' );
			WP_CLI::log( '' );

			// Query all stadion_todo posts, bypass access control
			$todos = get_posts( [
				'post_type'        => 'stadion_todo',
				'posts_per_page'   => -1,
				'post_status'      => [ 'stadion_open', 'stadion_awaiting', 'stadion_completed', 'publish' ],
				'suppress_filters' => true,
			] );

			if ( empty( $todos ) ) {
				WP_CLI::success( 'No tasks found in the system.' );
				return;
			}

			WP_CLI::log( sprintf( 'Found %d task(s) to check.', count( $todos ) ) );
			WP_CLI::log( '' );

			$valid   = 0;
			$invalid = 0;
			$fixed   = 0;
			$failed  = 0;

			foreach ( $todos as $todo ) {
				$author_id = (int) $todo->post_author;
				$user      = get_userdata( $author_id );

				if ( $user && $author_id > 0 ) {
					if ( ! $verify && ! $dry_run ) {
						// Silent in fix mode for valid tasks
					} else {
						WP_CLI::log( sprintf( '  ✓ Task #%d: %s (author: %s, ID: %d)',
							$todo->ID,
							wp_trim_words( $todo->post_title, 8 ),
							$user->user_login,
							$author_id
						) );
					}
					$valid++;
				} else {
					WP_CLI::warning( sprintf( '  ✗ Task #%d has invalid author ID: %d', $todo->ID, $author_id ) );
					$invalid++;

					if ( ! $verify ) {
						// Attempt to determine correct author from related_persons
						$person_ids = get_field( 'related_persons', $todo->ID );
						if ( ! is_array( $person_ids ) ) {
							$person_ids = $person_ids ? [ $person_ids ] : [];
						}

						if ( empty( $person_ids ) ) {
							WP_CLI::warning( sprintf( '    Cannot fix: No related persons found' ) );
							$failed++;
							continue;
						}

						// Use the first related person's author
						$person_id = (int) $person_ids[0];
						$person    = get_post( $person_id );

						if ( ! $person ) {
							WP_CLI::warning( sprintf( '    Cannot fix: Related person #%d not found', $person_id ) );
							$failed++;
							continue;
						}

						$new_author_id = (int) $person->post_author;
						$new_user      = get_userdata( $new_author_id );

						if ( ! $new_user ) {
							WP_CLI::warning( sprintf( '    Cannot fix: Person #%d has invalid author ID: %d', $person_id, $new_author_id ) );
							$failed++;
							continue;
						}

						if ( $dry_run ) {
							WP_CLI::log( sprintf( '    Would set author to: %s (ID: %d)', $new_user->user_login, $new_author_id ) );
							$fixed++;
						} else {
							wp_update_post( [
								'ID'          => $todo->ID,
								'post_author' => $new_author_id,
							] );
							WP_CLI::log( sprintf( '    Fixed: Set author to %s (ID: %d)', $new_user->user_login, $new_author_id ) );
							$fixed++;
						}
					}
				}
			}

			WP_CLI::log( '' );
			WP_CLI::log( '────────────────────────────────────────────────────────────' );
			WP_CLI::log( 'Summary:' );
			WP_CLI::log( '────────────────────────────────────────────────────────────' );
			WP_CLI::log( sprintf( '  Total tasks: %d', count( $todos ) ) );
			WP_CLI::log( sprintf( '  Valid: %d', $valid ) );
			WP_CLI::log( sprintf( '  Invalid: %d', $invalid ) );

			if ( ! $verify ) {
				WP_CLI::log( sprintf( '  Fixed: %d', $fixed ) );
				WP_CLI::log( sprintf( '  Could not fix: %d', $failed ) );

				if ( $failed > 0 ) {
					WP_CLI::warning( sprintf( 'Completed with %d task(s) that could not be fixed.', $failed ) );
				} else {
					WP_CLI::success( $dry_run ? 'Dry run complete.' : 'All tasks verified/fixed!' );
				}
			} else {
				if ( $invalid > 0 ) {
					WP_CLI::warning( sprintf( 'Found %d task(s) with invalid ownership.', $invalid ) );
					WP_CLI::log( 'Run without --verify to fix.' );
				} else {
					WP_CLI::success( 'All tasks have valid ownership.' );
				}
			}
		}
	}

	/**
	 * Register WP-CLI commands
	 */
	WP_CLI::add_command( 'prm people', 'STADION_People_CLI_Command' );
	WP_CLI::add_command( 'stadion google-contacts', 'STADION_Google_Contacts_CLI_Command' );
	WP_CLI::add_command( 'prm reminders', 'STADION_Reminders_CLI_Command' );
	WP_CLI::add_command( 'prm migrate', 'STADION_Migration_CLI_Command' );
	WP_CLI::add_command( 'stadion migrate-birthdates', [ 'STADION_Migration_CLI_Command', 'migrate_birthdates' ] );
	WP_CLI::add_command( 'prm vcard', 'STADION_VCard_CLI_Command' );
	WP_CLI::add_command( 'prm carddav', 'STADION_CardDAV_CLI_Command' );
	WP_CLI::add_command( 'prm dates', 'STADION_Dates_CLI_Command' );
	WP_CLI::add_command( 'prm todos', 'STADION_Todos_CLI_Command' );
	WP_CLI::add_command( 'prm calendar', 'STADION_Calendar_CLI_Command' );
	WP_CLI::add_command( 'prm event', 'STADION_Event_CLI_Command' );
	WP_CLI::add_command( 'prm relationships', 'STADION_Relationships_CLI_Command' );
	WP_CLI::add_command( 'stadion tasks', 'STADION_Tasks_CLI_Command' );
}
