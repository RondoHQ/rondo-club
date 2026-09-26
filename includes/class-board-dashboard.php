<?php
/** Board summaries reuse existing record and section permissions. */

namespace Rondo\Dashboard;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\REST\Reminders;
use Rondo\Volunteer\VolunteerStatistics;
use Rondo\VOG\VOGRequirement;

final class BoardDashboard {

	/** Return only the board blocks the current user may read. */
	public static function overview(): array {
		if ( ! RoleDashboard::context()['board'] ) {
			return [];
		}
		$data = [];
		if ( UserRoles::can_access_section( 'jubilarissen' ) ) {
			$data['anniversaries'] = array_values(
				array_filter(
					( new Reminders() )->get_upcoming_anniversaries_data( 89, 0 ),
					static fn( array $item ): bool => $item['type'] === 'member' && AccessControl::can_view_person( (int) $item['person']['id'] )
				)
			);
		}
		if ( current_user_can( 'ledenadministratie' ) ) {
			$data['membership'] = self::membership();
		}
		if ( current_user_can( 'vrijwilligers' ) ) {
			$data['volunteers'] = ( new VolunteerStatistics() )->upcoming_shortages();
		}
		if ( current_user_can( 'vog' ) ) {
			$data['vog'] = self::vog_counts();
		}
		return $data;
	}

	/** Query through WordPress and check record visibility again before aggregating. */
	private static function people( array $meta_query = [] ): array {
		$people = get_posts(
			[
				'post_type'              => 'person',
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'no_found_rows'          => true,
				'suppress_filters'       => false,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query,
			]
		);
		$ids    = array_values( array_filter( wp_list_pluck( $people, 'ID' ), static fn( $id ): bool => AccessControl::can_view_person( (int) $id ) ) );
		update_meta_cache( 'post', $ids );
		return $ids;
	}

	/** Membership dates describe the current season to today, not historical snapshots. */
	private static function membership(): array {
		$season = SeasonKey::current();
		$start  = substr( $season, 0, 4 ) . '-07-01';
		$today  = current_datetime()->format( 'Y-m-d' );
		$data   = [
			'season'       => $season,
			'from'         => $start,
			'to'           => $today,
			'active'       => 0,
			'joined'       => 0,
			'left'         => 0,
			'left_unknown' => 0,
		];
		foreach ( self::people() as $id ) {
			$type     = strtolower( trim( (string) Fields::get_for_post( $id, 'type_lid' ) ) );
			$activity = trim( (string) Fields::get_for_post( $id, 'spelactiviteit' ) );
			// Retain former playing association members for season departures.
			if ( ! in_array( $type, [ 'bondslid', 'oud bondslid' ], true ) ) {
				continue;
			}
			$dates = Formatter::for_wire(
				'person',
				[
					'lid_sinds' => Fields::get_for_post( $id, 'lid_sinds' ),
					'lid_tot'   => Fields::get_for_post( $id, 'lid_tot' ),
				]
				);
			if ( $activity === '' || $activity === '-' ) {
				// Without historical activity, a departed association member cannot be classified.
				if ( $dates['lid_tot'] && $dates['lid_tot'] >= $start && $dates['lid_tot'] <= $today ) {
					++$data['left_unknown'];
				}
				continue;
			}
			if ( $type === 'bondslid' && ! Fields::get_for_post( $id, 'former_member' ) && ( ! $dates['lid_sinds'] || $dates['lid_sinds'] <= $today ) && ( ! $dates['lid_tot'] || $dates['lid_tot'] >= $today ) ) {
				++$data['active'];
			}
			foreach ( [
				'joined' => 'lid_sinds',
				'left'   => 'lid_tot',
			] as $key => $field ) {
				$date = $dates[ $field ];
				if ( is_string( $date ) && $date >= $start && $date <= $today ) {
					++$data[ $key ];
				}
			}
		}
		return $data;
	}

	/** Match the VOG overview: only visible volunteers with a VOG-required role count. */
	public static function vog_counts(): array {
		$counts = [
			'not_submitted_to_justis' => 0,
			'submitted_to_justis'     => 0,
			'expiring_soon'           => 0,
		];
		if ( ! current_user_can( 'vog' ) ) {
			return $counts;
		}
		$cutoff = gmdate( 'Y-m-d', strtotime( '-3 years' ) );
		$soon   = gmdate( 'Y-m-d', strtotime( '+30 days -3 years' ) );
		$ids    = VOGRequirement::get_required_person_ids();
		update_meta_cache( 'post', $ids );
		foreach ( $ids as $id ) {
			if ( ! AccessControl::can_view_person( $id ) || Fields::get_for_post( $id, 'former_member' ) ) {
				continue;
			}
			$date = Formatter::for_wire( 'person', [ 'datum_vog' => Fields::get_for_post( $id, 'datum_vog' ) ] )['datum_vog'];
			if ( ! $date || $date <= $cutoff ) {
				$key = get_post_meta( $id, 'vog_justis_submitted_date', true ) ? 'submitted_to_justis' : 'not_submitted_to_justis';
				++$counts[ $key ];
			} elseif ( $date <= $soon ) {
				++$counts['expiring_soon'];
			}
		}
		return $counts;
	}
}
