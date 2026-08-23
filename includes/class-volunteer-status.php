<?php
/**
 * Auto-update huidig-vrijwilliger (current volunteer) status based on active roles.
 *
 * A person is considered a current volunteer if they have an active position where:
 * - Position is in a commissie (any role), OR
 * - Position is in a team with a staff role (not a player role)
 *
 * @package Rondo
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VolunteerStatus
 */
class VolunteerStatus {

	/**
	 * Option key for player roles.
	 *
	 * @var string
	 */
	const OPTION_PLAYER_ROLES = 'rondo_player_roles';

	/**
	 * Option key for excluded roles.
	 *
	 * @var string
	 */
	const OPTION_EXCLUDED_ROLES = 'rondo_excluded_roles';

	/**
	 * Option key for staff roles that exempt the holder from the 2-diensten-plicht.
	 * A person with an active work_history entry whose job_title is in this list
	 * is treated as already volunteering enough through their staff role.
	 *
	 * @var string
	 */
	const OPTION_STAFF_ROLES = 'rondo_volunteer_staff_roles';

	/**
	 * Default player roles that do NOT count as volunteer positions.
	 * These are actual player positions on a team.
	 * Used as fallback when no option is set.
	 *
	 * @var array
	 */
	private const DEFAULT_PLAYER_ROLES = [
		'Aanvaller',
		'Verdediger',
		'Keeper',
		'Middenvelder',
		'Teamspeler',
		'Speler',
		'Champions league',
		'Zondag recranten',
		'Zaterdag recreanten',
	];

	/**
	 * Default honorary/membership roles that do NOT count as volunteer positions.
	 * These are passive membership types, not active volunteering.
	 * Used as fallback when no option is set.
	 *
	 * @var array
	 */
	private const DEFAULT_EXCLUDED_ROLES = [
		'Donateur',
		'Erelid',
		'Lid van Verdienste',
		'Verenigingslid voor het leven (contributievrij)',
	];

	/**
	 * Default staff roles that grant exemption from the 2-diensten-plicht.
	 * Used as fallback when no option is set.
	 *
	 * @var array
	 */
	private const DEFAULT_STAFF_ROLES = [
		'Trainer',
		'Trainer/coach',
		'Hoofdtrainer',
		'Assistent-trainer',
		'Leider',
		'Teamleider',
		'Teammanager',
		'Coördinator',
		'Scheidsrechter',
	];

	/**
	 * The canonical field key for huidig-vrijwilliger.
	 *
	 * @var string
	 */
	private const VOLUNTEER_FIELD_KEY = 'field_huidig_vrijwilliger';

	/**
	 * Get the configured player roles from options, with defaults as fallback.
	 *
	 * @return array Player role names.
	 */
	public static function get_player_roles(): array {
		$roles = get_option( self::OPTION_PLAYER_ROLES, null );
		return is_array( $roles ) ? $roles : self::DEFAULT_PLAYER_ROLES;
	}

	/**
	 * Get the configured excluded roles from options, with defaults as fallback.
	 *
	 * @return array Excluded role names.
	 */
	public static function get_excluded_roles(): array {
		$roles = get_option( self::OPTION_EXCLUDED_ROLES, null );
		return is_array( $roles ) ? $roles : self::DEFAULT_EXCLUDED_ROLES;
	}

	/**
	 * Get the default player roles.
	 *
	 * @return array Default player role names.
	 */
	public static function get_default_player_roles(): array {
		return self::DEFAULT_PLAYER_ROLES;
	}

	/**
	 * Get the default excluded roles.
	 *
	 * @return array Default excluded role names.
	 */
	public static function get_default_excluded_roles(): array {
		return self::DEFAULT_EXCLUDED_ROLES;
	}

	/**
	 * Get the configured staff roles (exempt from 2-diensten-plicht), with defaults as fallback.
	 *
	 * @return array Staff role names.
	 */
	public static function get_staff_roles(): array {
		$roles = get_option( self::OPTION_STAFF_ROLES, null );
		return is_array( $roles ) ? $roles : self::DEFAULT_STAFF_ROLES;
	}

	/**
	 * Get the default staff roles.
	 *
	 * @return array Default staff role names.
	 */
	public static function get_default_staff_roles(): array {
		return self::DEFAULT_STAFF_ROLES;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rondo_fields_saved_post', [ $this, 'update_volunteer_status' ], 25 );
	}

	/**
	 * Update volunteer status when person is saved via native field/admin.
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
	public function calculate_and_update_status( $post_id ) {
		$was_volunteer = (bool) \Rondo\Fields\Fields::get_for_post( $post_id, 'huidig_vrijwilliger' );
		$is_volunteer  = $this->is_current_volunteer( $post_id );
		$updates       = [ self::VOLUNTEER_FIELD_KEY => $is_volunteer ];

		// Sportlink does not expose a separate reliable volunteer-start field.
		// When work history first makes someone a volunteer, derive the date from
		// the earliest active volunteer position. Team-roster responses do not
		// include a start date, so a genuine false-to-true transition falls back
		// to today. Existing volunteers without a date are not treated as new.
		// Existing dates always win.
		$volunteer_since = \Rondo\Fields\Fields::get_for_post( $post_id, 'vrijwilliger_sinds' );
		if ( $is_volunteer && empty( $volunteer_since ) ) {
			$derived_start_date = $this->get_volunteer_start_date( $post_id );
			if ( $derived_start_date === null && ! $was_volunteer ) {
				$derived_start_date = current_datetime()->format( 'Y-m-d' );
			}
			if ( $derived_start_date !== null ) {
				$updates['vrijwilliger_sinds'] = $derived_start_date;
			}
		}

		\Rondo\Fields\Fields::update_many_for_post( $post_id, $updates );
	}

	/**
	 * Derive the earliest start date among active volunteer positions.
	 *
	 * @param int $post_id The person post ID.
	 * @return string|null Date in Y-m-d format, or null when no valid date exists.
	 */
	private function get_volunteer_start_date( int $post_id ): ?string {
		$work_history = \Rondo\Fields\Fields::get_for_post( $post_id, 'work_history' );
		if ( empty( $work_history ) || ! is_array( $work_history ) ) {
			return null;
		}

		$earliest = null;
		foreach ( $work_history as $position ) {
			if (
				! is_array( $position )
				|| ! self::is_position_current( $position )
				|| ! self::is_volunteer_position( $position )
			) {
				continue;
			}

			$start_date = self::normalize_work_history_date( (string) ( $position['start_date'] ?? '' ) );
			if ( $start_date !== null && ( $earliest === null || $start_date < $earliest ) ) {
				$earliest = $start_date;
			}
		}

		if ( $earliest === null ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Ymd', $earliest, wp_timezone() );
		return $date === false ? null : $date->format( 'Y-m-d' );
	}

	/**
	 * Check if a person is a current volunteer.
	 *
	 * @param int $post_id The person post ID.
	 * @return bool True if the person is a current volunteer.
	 */
	private function is_current_volunteer( $post_id ) {
		$work_history = \Rondo\Fields\Fields::get_for_post( $post_id, 'work_history' );

		if ( empty( $work_history ) || ! is_array( $work_history ) ) {
			return false;
		}

		foreach ( $work_history as $position ) {
			// Check if position is current
			if ( ! self::is_position_current( $position ) ) {
				continue;
			}

			// Check if it's a volunteer position
			if ( self::is_volunteer_position( $position ) ) {
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
	 * - end_date is in the future (positions ending today are NOT considered current)
	 *
	 * Accepts both the compact Ymd storage format and the canonical Y-m-d wire
	 * format used by work_history dates.
	 *
	 * @param array $position The position data.
	 * @return bool True if the position is current.
	 */
	public static function is_position_current( array $position ): bool {
		// Check is_current flag first
		if ( ! empty( $position['is_current'] ) ) {
			return true;
		}

		// Check end_date
		$end_date = trim( (string) ( $position['end_date'] ?? '' ) );

		// No end date means position is still active
		if ( empty( $end_date ) ) {
			// But only if there's a start date (to filter out empty rows)
			return ! empty( $position['start_date'] ) || ! empty( $position['team'] );
		}

		$normalized_end_date = self::normalize_work_history_date( $end_date );
		if ( $normalized_end_date === null ) {
			return false;
		}

		// Use tomorrow's date so positions ending today are no longer current.
		$cutoff = current_datetime()->modify( '+1 day' )->format( 'Ymd' );
		return $normalized_end_date >= $cutoff;
	}

	/**
	 * Normalize a work-history date for safe lexical comparison.
	 *
	 * @param string $value Date in Ymd or Y-m-d format.
	 * @return string|null Date in Ymd format, or null for an invalid value.
	 */
	private static function normalize_work_history_date( string $value ): ?string {
		$compact = str_replace( '-', '', trim( $value ) );
		if ( preg_match( '/^\d{8}$/', $compact ) !== 1 ) {
			return null;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Ymd', $compact, wp_timezone() );
		if ( $date === false || $date->format( 'Ymd' ) !== $compact ) {
			return null;
		}

		return $compact;
	}

	/**
	 * Check if a position qualifies as a volunteer position.
	 *
	 * Volunteer positions are:
	 * - Any position in a commissie (except excluded roles and exempt commissies)
	 * - Staff positions in a team (non-player, non-excluded roles)
	 *
	 * @param array $position The position data.
	 * @return bool True if this is a volunteer position.
	 */
	public static function is_volunteer_position( $position ): bool {
		$entity_type = $position['entity_type'] ?? '';
		$job_title   = $position['job_title'] ?? '';

		// Check if it's an excluded role (honorary/membership positions)
		if ( ! empty( $job_title ) && in_array( $job_title, self::get_excluded_roles(), true ) ) {
			return false;
		}

		// Get exempt commissies list
		$exempt_commissies = get_option( 'rondo_vog_exempt_commissies', [] );
		if ( ! is_array( $exempt_commissies ) ) {
			$exempt_commissies = [];
		}

		// Commissie positions are volunteer positions (unless excluded above or commissie is exempt)
		if ( $entity_type === 'commissie' ) {
			// Check if the commissie is exempt from VOG requirements
			$commissie_id = $position['team'] ?? 0;
			if ( $commissie_id && in_array( (int) $commissie_id, array_map( 'intval', $exempt_commissies ), true ) ) {
				return false; // Exempt commissie - not a volunteer position for VOG purposes
			}
			return true;
		}

		// Team positions: only staff (non-player) roles count as volunteer
		if ( $entity_type === 'team' ) {
			// If no job title, we can't determine - assume not volunteer
			if ( empty( $job_title ) ) {
				return false;
			}

			// Check if it's NOT a player role
			return ! in_array( $job_title, self::get_player_roles(), true );
		}

		// If entity_type is not set but team is set, try to determine from post type
		if ( ! empty( $position['team'] ) ) {
			$team_id        = $position['team'];
			$team_post_type = get_post_type( $team_id );

			if ( $team_post_type === 'commissie' ) {
				// Check if the commissie is exempt from VOG requirements
				if ( in_array( (int) $team_id, array_map( 'intval', $exempt_commissies ), true ) ) {
					return false; // Exempt commissie - not a volunteer position for VOG purposes
				}
				return true;
			}

			if ( $team_post_type === 'team' ) {
				if ( empty( $job_title ) ) {
					return false;
				}
				return ! in_array( $job_title, self::get_player_roles(), true );
			}
		}

		return false;
	}
}
