<?php
/**
 * Abstract base class for notification channels
 *
 * Provides the contract for notification channel implementations.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for notification channels
 */
abstract class PRM_Notification_Channel {

	/**
	 * Send notification to user via this channel
	 *
	 * @param int   $user_id     User ID
	 * @param array $digest_data Weekly digest data (today, tomorrow, rest_of_week)
	 * @return bool Success status
	 */
	abstract public function send( $user_id, $digest_data );

	/**
	 * Check if this channel is enabled for a user
	 *
	 * @param int $user_id User ID
	 * @return bool
	 */
	abstract public function is_enabled_for_user( $user_id );

	/**
	 * Get user-specific configuration for this channel
	 *
	 * @param int $user_id User ID
	 * @return array|false
	 */
	abstract public function get_user_config( $user_id );

	/**
	 * Get channel identifier
	 *
	 * @return string
	 */
	abstract public function get_channel_id();

	/**
	 * Get channel display name
	 *
	 * @return string
	 */
	abstract public function get_channel_name();

	/**
	 * Check if a person's name appears in a title and return the person if found
	 * Returns the first person whose name appears in the title, or null
	 *
	 * @param string $title The date title
	 * @param array  $people Array of person data
	 * @return array|null Person data if found, null otherwise
	 */
	protected function find_person_in_title( $title, $people ) {
		foreach ( $people as $person ) {
			$person_name = $person['name'];
			// Check if the person's name appears in the title (case-insensitive)
			if ( stripos( $title, $person_name ) !== false ) {
				return $person;
			}
		}
		return null;
	}
}
