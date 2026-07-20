<?php
/**
 * Temporary guardian account claims and person-link migration.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Volunteer\ShiftEmailScheduler;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lets a parent temporarily use an account linked to their child, then moves
 * the account and all account-owned volunteer data to the synced parent.
 */
class GuardianAccountService {
	public const ADMIN_EMAIL            = 'ledenadministratie@svawc.nl';
	public const META_NAME              = 'rondo_pending_guardian_name';
	public const META_CHILD_ID          = 'rondo_pending_guardian_child_id';
	public const META_CLAIMED_AT        = 'rondo_pending_guardian_claimed_at';
	public const META_NOTIFIED_AT       = 'rondo_pending_guardian_notified_at';
	public const SHIFT_USER_META_PREFIX = '_shift_signup_user_';
	public const SHIFT_NAME_META_PREFIX = '_shift_signup_guardian_name_';

	private const PERSON_TRANSFER_FIELDS = [
		'datum-vog',
		'vog_email_sent_date',
		'vog_justis_submitted_date',
		'vog_reminder_sent_date',
		'datum-iva',
		'iva-certificaat',
		'iva-approved',
	];

	private const PERSON_ACF_TRANSFER_FIELDS = [
		'datum-vog',
		'datum-iva',
		'iva-certificaat',
		'iva-approved',
	];

	private const SHIFT_META_PREFIXES = [
		'_shift_signup_at_',
		self::SHIFT_USER_META_PREFIX,
		self::SHIFT_NAME_META_PREFIX,
		'_shift_confirmation_queued_at_',
		'_shift_email_confirmation_sent_',
		'_shift_email_cancellation_sent_',
		'_shift_email_reminder_sent_',
		'_shift_email_survey_sent_',
		VolunteerObligationCalculator::NO_SHOW_META_PREFIX,
	];

	/**
	 * Return the pending guardian claim for a user.
	 *
	 * @return array{name: string, child_id: int, child_name: string, claimed_at: string}|null
	 */
	public static function pending_for_user( int $user_id ): ?array {
		$name     = trim( (string) get_user_meta( $user_id, self::META_NAME, true ) );
		$child_id = (int) get_user_meta( $user_id, self::META_CHILD_ID, true );
		if ( $name === '' || $child_id <= 0 || get_post_type( $child_id ) !== 'person' ) {
			return null;
		}

		return [
			'name'       => $name,
			'child_id'   => $child_id,
			'child_name' => get_the_title( $child_id ),
			'claimed_at' => (string) get_user_meta( $user_id, self::META_CLAIMED_AT, true ),
		];
	}

	/**
	 * Display a temporary guardian name instead of the linked child's name.
	 */
	public static function display_name_for_person( int $person_id ): string {
		$user_id = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
		$pending = $user_id > 0 ? self::pending_for_user( $user_id ) : null;
		if ( $pending && $pending['child_id'] === $person_id ) {
			return $pending['name'];
		}
		return get_the_title( $person_id );
	}

	/**
	 * First-name greeting, except while the account has a guardian identity.
	 */
	public static function greeting_name_for_person( int $person_id ): string {
		$user_id = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
		$pending = $user_id > 0 ? self::pending_for_user( $user_id ) : null;
		if ( $pending && $pending['child_id'] === $person_id ) {
			return $pending['name'];
		}

		$first_name = trim( (string) get_field( 'first_name', $person_id ) );
		return $first_name !== '' ? $first_name : get_the_title( $person_id );
	}

	/**
	 * Whether a person is in a youth age group that carries no personal duty.
	 */
	public static function is_youth_person( int $person_id ): bool {
		$age = ( new VolunteerEligibilityService() )->age_group_number( $person_id );
		return $age !== null && $age <= VolunteerEligibilityService::YOUTH_MAX_AGE;
	}

	/**
	 * Mark an account as temporarily used by a parent of its linked child.
	 *
	 * @return array|\WP_Error
	 */
	public static function claim( int $user_id, int $child_id, string $guardian_name ) {
		$guardian_name = trim( sanitize_text_field( $guardian_name ) );
		if ( mb_strlen( $guardian_name ) < 2 || mb_strlen( $guardian_name ) > 120 ) {
			return new \WP_Error( 'invalid_guardian_name', 'Vul je volledige naam in.', [ 'status' => 400 ] );
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error( 'invalid_user', 'Gebruikersaccount niet gevonden.', [ 'status' => 404 ] );
		}

		$linked_person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		if ( $linked_person_id !== $child_id || ! self::is_youth_person( $child_id ) ) {
			return new \WP_Error( 'invalid_guardian_child', 'Dit account is niet aan een jeugdlid gekoppeld.', [ 'status' => 400 ] );
		}

		$existing = self::pending_for_user( $user_id );
		if ( $existing && $existing['child_id'] !== $child_id ) {
			return new \WP_Error( 'guardian_already_claimed', 'Dit account wacht al op een andere ouderkoppeling.', [ 'status' => 409 ] );
		}

		$first_claim = ! $existing;
		update_user_meta( $user_id, self::META_NAME, $guardian_name );
		update_user_meta( $user_id, self::META_CHILD_ID, $child_id );
		if ( $first_claim ) {
			update_user_meta( $user_id, self::META_CLAIMED_AT, current_time( 'mysql', true ) );
		}

		wp_update_user(
			[
				'ID'           => $user_id,
				'display_name' => $guardian_name,
			]
		);

		$notification_sent = (bool) get_user_meta( $user_id, self::META_NOTIFIED_AT, true );
		if ( ! $notification_sent ) {
			$notification_sent = self::send_notification( $user_id );
			if ( ! $notification_sent && wp_next_scheduled( 'rondo_retry_guardian_notification', [ $user_id ] ) === false ) {
				wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'rondo_retry_guardian_notification', [ $user_id ] );
			}
		}

		return [
			'success'           => true,
			'pending_guardian'  => self::pending_for_user( $user_id ),
			'notification_sent' => $notification_sent,
		];
	}

	/**
	 * Retry a failed membership-administration notification.
	 */
	public static function retry_notification( int $user_id ): void {
		if ( self::pending_for_user( $user_id ) && ! get_user_meta( $user_id, self::META_NOTIFIED_AT, true ) ) {
			self::send_notification( $user_id );
		}
	}

	/**
	 * Store which logged-in account created a shift assignment.
	 */
	public static function mark_shift_signup( int $shift_id, int $person_id, int $user_id ): void {
		update_post_meta( $shift_id, self::SHIFT_USER_META_PREFIX . $person_id, $user_id );
		$pending = self::pending_for_user( $user_id );
		if ( $pending && $pending['child_id'] === $person_id ) {
			update_post_meta( $shift_id, self::SHIFT_NAME_META_PREFIX . $person_id, $pending['name'] );
		}
	}

	/**
	 * Remove account attribution when its shift assignment is cancelled.
	 */
	public static function unmark_shift_signup( int $shift_id, int $person_id ): void {
		delete_post_meta( $shift_id, self::SHIFT_USER_META_PREFIX . $person_id );
		delete_post_meta( $shift_id, self::SHIFT_NAME_META_PREFIX . $person_id );
	}

	/**
	 * Move an account from its current person to a newly synced person record.
	 *
	 * @return array|\WP_Error
	 */
	public static function relink( int $user_id, int $target_person_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return new \WP_Error( 'invalid_user', 'Gebruikersaccount niet gevonden.', [ 'status' => 404 ] );
		}

		$source_person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		$target           = get_post( $target_person_id );
		if ( $source_person_id <= 0 || ! $target || $target->post_type !== 'person' || $target->post_status !== 'publish' ) {
			return new \WP_Error( 'invalid_person_link', 'De huidige of nieuwe persoonskoppeling is ongeldig.', [ 'status' => 400 ] );
		}
		if ( $source_person_id === $target_person_id ) {
			return new \WP_Error( 'same_person_link', 'Dit account is al aan deze persoon gekoppeld.', [ 'status' => 400 ] );
		}

		$target_user_id = (int) get_post_meta( $target_person_id, UserProvisioning::META_USER_ID, true );
		if ( $target_user_id > 0 && $target_user_id !== $user_id && get_userdata( $target_user_id ) ) {
			return new \WP_Error( 'target_has_account', 'Deze persoon heeft al een ander Rondo-account.', [ 'status' => 409 ] );
		}

		$reverse_users = get_users(
			[
				'meta_key'   => 'rondo_linked_person_id',
				'meta_value' => $target_person_id,
				'fields'     => 'ID',
			]
		);
		foreach ( $reverse_users as $reverse_user_id ) {
			if ( (int) $reverse_user_id !== $user_id ) {
				return new \WP_Error( 'target_has_account', 'Deze persoon heeft al een ander Rondo-account.', [ 'status' => 409 ] );
			}
		}

		$conflicts = self::person_transfer_conflicts( $source_person_id, $target_person_id );
		if ( ! empty( $conflicts ) ) {
			return new \WP_Error(
				'person_data_conflict',
				'Op de nieuwe persoon staan al certificaatgegevens: ' . implode( ', ', $conflicts ) . '.',
				[
					'status' => 409,
					'fields' => $conflicts,
				]
			);
		}

		$migrated_shifts = self::migrate_shift_assignments( $user_id, $source_person_id, $target_person_id );
		$moved_fields    = self::move_person_fields( $source_person_id, $target_person_id );

		if ( (int) get_post_meta( $source_person_id, UserProvisioning::META_USER_ID, true ) === $user_id ) {
			delete_post_meta( $source_person_id, UserProvisioning::META_USER_ID );
		}
		update_post_meta( $target_person_id, UserProvisioning::META_USER_ID, $user_id );
		update_user_meta( $user_id, 'rondo_linked_person_id', $target_person_id );

		$knvb_id = trim( (string) get_post_meta( $target_person_id, 'knvb-id', true ) );
		if ( $knvb_id !== '' ) {
			update_user_meta( $user_id, UserProvisioning::META_KNVB_ID, $knvb_id );
		} else {
			delete_user_meta( $user_id, UserProvisioning::META_KNVB_ID );
		}

		$first = trim( (string) get_field( 'first_name', $target_person_id ) );
		$infix = trim( (string) get_field( 'infix', $target_person_id ) );
		$last  = trim( (string) get_field( 'last_name', $target_person_id ) );
		$name  = trim( implode( ' ', array_filter( [ $first, $infix, $last ] ) ) );
		$name  = $name !== '' ? $name : get_the_title( $target_person_id );
		wp_update_user(
			[
				'ID'           => $user_id,
				'display_name' => $name,
				'first_name'   => $first,
				'last_name'    => trim( implode( ' ', array_filter( [ $infix, $last ] ) ) ),
			]
		);

		self::clear_pending( $user_id );
		( new CapabilitySync() )->sync_user_by_person_id( $target_person_id );
		VolunteerEligibilityService::invalidate_cache();
		VolunteerObligationCalculator::invalidate_cache();

		return [
			'success'         => true,
			'user_id'         => $user_id,
			'old_person_id'   => $source_person_id,
			'person_id'       => $target_person_id,
			'person_name'     => $name,
			'migrated_shifts' => $migrated_shifts,
			'moved_fields'    => $moved_fields,
		];
	}

	/**
	 * Send the requested notification to the membership administration.
	 */
	private static function send_notification( int $user_id ): bool {
		$pending = self::pending_for_user( $user_id );
		if ( ! $pending ) {
			return false;
		}

		$message = sprintf(
			'De ouder/verzorger %s van %s heeft zich via het account van het kind aangemeld voor Rondo.',
			$pending['name'],
			$pending['child_name']
		);
		$sent    = wp_mail(
			self::ADMIN_EMAIL,
			'Ouder/verzorger aangemeld voor Rondo',
			$message,
			[ 'Content-Type: text/plain; charset=UTF-8' ]
		);
		if ( $sent ) {
			update_user_meta( $user_id, self::META_NOTIFIED_AT, current_time( 'mysql', true ) );
		} else {
			error_log( sprintf( '[Rondo] Guardian notification failed for user %d.', $user_id ) );
		}

		return (bool) $sent;
	}

	/**
	 * Clear the temporary guardian identity after a successful relink.
	 */
	private static function clear_pending( int $user_id ): void {
		delete_user_meta( $user_id, self::META_NAME );
		delete_user_meta( $user_id, self::META_CHILD_ID );
		delete_user_meta( $user_id, self::META_CLAIMED_AT );
		delete_user_meta( $user_id, self::META_NOTIFIED_AT );
		wp_clear_scheduled_hook( 'rondo_retry_guardian_notification', [ $user_id ] );
	}

	/**
	 * Refuse to overwrite certificate/process data on the new person silently.
	 *
	 * @return string[]
	 */
	private static function person_transfer_conflicts( int $source_person_id, int $target_person_id ): array {
		$conflicts = [];
		foreach ( self::PERSON_TRANSFER_FIELDS as $field ) {
			$source = get_post_meta( $source_person_id, $field, true );
			$target = get_post_meta( $target_person_id, $field, true );
			if ( self::has_value( $source ) && self::has_value( $target ) && $source !== $target ) {
				$conflicts[] = $field;
			}
		}
		return $conflicts;
	}

	/**
	 * Move VOG and IVA state from the temporary child identity.
	 *
	 * @return string[]
	 */
	private static function move_person_fields( int $source_person_id, int $target_person_id ): array {
		$moved = [];
		foreach ( self::PERSON_TRANSFER_FIELDS as $field ) {
			$value = get_post_meta( $source_person_id, $field, true );
			if ( ! self::has_value( $value ) ) {
				continue;
			}

			if ( in_array( $field, self::PERSON_ACF_TRANSFER_FIELDS, true ) ) {
				update_field( $field, $value, $target_person_id );
				delete_field( $field, $source_person_id );
			} else {
				update_post_meta( $target_person_id, $field, $value );
				delete_post_meta( $source_person_id, $field );
			}
			$moved[] = $field;

			if ( $field === 'iva-certificaat' ) {
				$attachment_id = (int) $value;
				if ( $attachment_id > 0 && get_post_type( $attachment_id ) === 'attachment' ) {
					wp_update_post(
						[
							'ID'          => $attachment_id,
							'post_parent' => $target_person_id,
						]
						);
				}
			}
		}
		return $moved;
	}

	/**
	 * Move every shift assignment created by this account to the new person.
	 */
	private static function migrate_shift_assignments( int $user_id, int $source_person_id, int $target_person_id ): int {
		$shift_ids = get_posts(
			[
				'post_type'      => 'dienst_shift',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::SHIFT_USER_META_PREFIX . $source_person_id,
				'meta_value'     => $user_id,
			]
		);

		$migrated    = 0;
		$queue_shift = 0;
		foreach ( $shift_ids as $shift_id ) {
			$assigned = array_values( array_unique( array_map( 'intval', (array) get_post_meta( $shift_id, 'assigned_persons', true ) ) ) );
			if ( ! in_array( $source_person_id, $assigned, true ) ) {
				continue;
			}

			$assigned = array_values(
				array_unique(
				array_map(
				fn( int $person_id ): int => $person_id === $source_person_id ? $target_person_id : $person_id,
				$assigned
				)
				)
				);
			update_post_meta( $shift_id, 'assigned_persons', $assigned );

			foreach ( self::SHIFT_META_PREFIXES as $prefix ) {
				$source_key = $prefix . $source_person_id;
				$target_key = $prefix . $target_person_id;
				$value      = get_post_meta( $shift_id, $source_key, true );
				if ( self::has_value( $value ) && ! self::has_value( get_post_meta( $shift_id, $target_key, true ) ) ) {
					update_post_meta( $shift_id, $target_key, $value );
					if ( $prefix === '_shift_confirmation_queued_at_' ) {
						$queue_shift = (int) $shift_id;
					}
				}
				delete_post_meta( $shift_id, $source_key );
			}

			++$migrated;
		}

		if ( $queue_shift > 0 ) {
			wp_clear_scheduled_hook( ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $source_person_id ] );
			ShiftEmailScheduler::queue_signup_confirmation( $target_person_id, $queue_shift );
		}

		return $migrated;
	}

	/**
	 * WordPress meta treats several scalar values as empty; true/false state is
	 * meaningful only when it is truthy for the transferable fields above.
	 */
	private static function has_value( $value ): bool {
		return ! ( $value === '' || $value === null || $value === false || $value === [] || $value === '0' || $value === 0 );
	}
}
