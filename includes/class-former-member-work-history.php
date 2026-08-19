<?php
/**
 * Former-member work-history lifecycle.
 *
 * @package Rondo\Data
 */

namespace Rondo\Data;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keep former-member status and current work history consistent.
 */
final class FormerMemberWorkHistory {

	/**
	 * Register lifecycle hooks.
	 */
	public function __construct() {
		add_action( 'rondo_fields_saved_post', [ $this, 'close_current_positions' ], 15, 2 );
	}

	/**
	 * Close every logically current position when a person becomes a former member.
	 *
	 * Work history may be included in the same logical save as former_member, so
	 * this runs after the complete field payload has been persisted. Re-checking
	 * work_history saves also prevents a later integration write from restoring a
	 * current position on an existing former member.
	 *
	 * @param int   $post_id Person post ID.
	 * @param array $changes Logical field changes from Fields::update_many_for_post().
	 */
	public function close_current_positions( int $post_id, array $changes ): void {
		if ( get_post_type( $post_id ) !== 'person' || ! $this->has_relevant_change( $changes ) ) {
			return;
		}

		if ( ! (bool) Fields::get_for_post( $post_id, 'former_member' ) ) {
			return;
		}

		$work_history = Fields::get_for_post( $post_id, 'work_history' );
		if ( ! is_array( $work_history ) || empty( $work_history ) ) {
			return;
		}

		$end_date = $this->membership_end_date( $post_id );
		$changed  = false;

		foreach ( $work_history as $index => $position ) {
			if ( ! is_array( $position ) || ! VolunteerStatus::is_position_current( $position ) ) {
				continue;
			}

			$work_history[ $index ]['is_current'] = false;
			$work_history[ $index ]['end_date']   = $end_date;
			$changed                              = true;
		}

		if ( $changed ) {
			Fields::update_for_post( $post_id, 'work_history', $work_history );
		}
	}

	/**
	 * Check whether this save can affect the former-member invariant.
	 *
	 * @param array $changes Logical field changes.
	 */
	private function has_relevant_change( array $changes ): bool {
		foreach ( $changes as $change ) {
			$canonical_name = $change[0]['canonical_name'] ?? '';
			if ( in_array( $canonical_name, [ 'former_member', 'work_history' ], true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve a non-future end date for positions being closed.
	 *
	 * The membership end date is preferred because it preserves the actual
	 * lifecycle date. Missing, invalid, or future values fall back to today so
	 * VolunteerStatus cannot continue treating the position as current.
	 */
	private function membership_end_date( int $post_id ): string {
		$today   = current_datetime()->format( 'Y-m-d' );
		$raw     = trim( (string) Fields::get_for_post( $post_id, 'lid_tot' ) );
		$compact = str_replace( '-', '', $raw );

		if ( preg_match( '/^\d{8}$/', $compact ) !== 1 ) {
			return $today;
		}

		$date = \DateTimeImmutable::createFromFormat( '!Ymd', $compact, wp_timezone() );
		if ( $date === false || $date->format( 'Ymd' ) !== $compact ) {
			return $today;
		}

		$membership_end = $date->format( 'Y-m-d' );
		return $membership_end <= $today ? $membership_end : $today;
	}
}
