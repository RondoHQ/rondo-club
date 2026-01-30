<?php
/**
 * Auto-update huidig-vrijwilliger (current volunteer) status based on active roles.
 *
 * A person is considered a current volunteer if they have an active position where:
 * - Position is in a commissie (any role), OR
 * - Position is in a team with a staff role (not a player role)
 *
 * @package Stadion
 */

namespace Stadion\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VolunteerStatus
 */
class VolunteerStatus {

	/**
	 * Player roles that do NOT count as volunteer positions.
	 * These are actual player positions on a team.
	 *
	 * @var array
	 */
	private const PLAYER_ROLES = [
		'Aanvaller',
		'Verdediger',
		'Keeper',
		'Middenvelder',
		'Teamspeler',
		'Speler',
	];

	/**
	 * Honorary/membership roles that do NOT count as volunteer positions.
	 * These are passive membership types, not active volunteering.
	 *
	 * @var array
	 */
	private const EXCLUDED_ROLES = [
		'Donateur',
		'Lid van Verdienste',
		'Verenigingslid voor het leven (contributievrij)',
	];

	/**
	 * The ACF field key for huidig-vrijwilliger.
	 *
	 * @var string
	 */
	private const VOLUNTEER_FIELD_KEY = 'field_custom_person_huidig-vrijwilliger';

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Hook into ACF save for person post type (priority 25 = after auto-title)
		add_action( 'acf/save_post', [ $this, 'update_volunteer_status' ], 25 );

		// Also hook into REST API updates
		add_action( 'rest_after_insert_person', [ $this, 'update_volunteer_status_rest' ], 25, 2 );
	}

	/**
	 * Update volunteer status when person is saved via ACF/admin.
	 *
	 * @param int $post_id The post ID.
	 */
	public function update_volunteer_status( $post_id ) {
		// Skip autosaves and revisions
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only for person post type
		if ( get_post_type( $post_id ) !== 'person' ) {
			return;
		}

		$this->calculate_and_update_status( $post_id );
	}

	/**
	 * Update volunteer status when person is saved via REST API.
	 *
	 * @param \WP_Post         $post    The post object.
	 * @param \WP_REST_Request $request The request object.
	 */
	public function update_volunteer_status_rest( $post, $request ) {
		$this->calculate_and_update_status( $post->ID );
	}

	/**
	 * Calculate and update the volunteer status for a person.
	 *
	 * @param int $post_id The person post ID.
	 */
	private function calculate_and_update_status( $post_id ) {
		$is_volunteer = $this->is_current_volunteer( $post_id );

		// Update the custom field using ACF's update_field for proper reference handling
		update_field( self::VOLUNTEER_FIELD_KEY, $is_volunteer ? '1' : '0', $post_id );
	}

	/**
	 * Check if a person is a current volunteer.
	 *
	 * @param int $post_id The person post ID.
	 * @return bool True if the person is a current volunteer.
	 */
	private function is_current_volunteer( $post_id ) {
		$work_history = get_field( 'work_history', $post_id );

		if ( empty( $work_history ) || ! is_array( $work_history ) ) {
			return false;
		}

		$today = gmdate( 'Y-m-d' );

		foreach ( $work_history as $position ) {
			// Check if position is current
			if ( ! $this->is_position_current( $position, $today ) ) {
				continue;
			}

			// Check if it's a volunteer position
			if ( $this->is_volunteer_position( $position ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if a position is currently active.
	 *
	 * A position is current if:
	 * - is_current flag is true, OR
	 * - end_date is empty/null, OR
	 * - end_date is in the future or today
	 *
	 * @param array  $position The position data.
	 * @param string $today    Today's date in Y-m-d format.
	 * @return bool True if the position is current.
	 */
	private function is_position_current( $position, $today ) {
		// Check is_current flag first
		if ( ! empty( $position['is_current'] ) ) {
			return true;
		}

		// Check end_date
		$end_date = $position['end_date'] ?? '';

		// No end date means position is still active
		if ( empty( $end_date ) ) {
			// But only if there's a start date (to filter out empty rows)
			return ! empty( $position['start_date'] ) || ! empty( $position['team'] );
		}

		// End date in future or today means still active
		return $end_date >= $today;
	}

	/**
	 * Check if a position qualifies as a volunteer position.
	 *
	 * Volunteer positions are:
	 * - Any position in a commissie (except excluded roles)
	 * - Staff positions in a team (non-player, non-excluded roles)
	 *
	 * @param array $position The position data.
	 * @return bool True if this is a volunteer position.
	 */
	private function is_volunteer_position( $position ) {
		$entity_type = $position['entity_type'] ?? '';
		$job_title   = $position['job_title'] ?? '';

		// Check if it's an excluded role (honorary/membership positions)
		if ( ! empty( $job_title ) && in_array( $job_title, self::EXCLUDED_ROLES, true ) ) {
			return false;
		}

		// Commissie positions are volunteer positions (unless excluded above)
		if ( $entity_type === 'commissie' ) {
			return true;
		}

		// Team positions: only staff (non-player) roles count as volunteer
		if ( $entity_type === 'team' ) {
			// If no job title, we can't determine - assume not volunteer
			if ( empty( $job_title ) ) {
				return false;
			}

			// Check if it's NOT a player role
			return ! in_array( $job_title, self::PLAYER_ROLES, true );
		}

		// If entity_type is not set but team is set, try to determine from post type
		if ( ! empty( $position['team'] ) ) {
			$team_post_type = get_post_type( $position['team'] );

			if ( $team_post_type === 'commissie' ) {
				return true;
			}

			if ( $team_post_type === 'team' ) {
				if ( empty( $job_title ) ) {
					return false;
				}
				return ! in_array( $job_title, self::PLAYER_ROLES, true );
			}
		}

		return false;
	}
}
