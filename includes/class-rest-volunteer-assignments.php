<?php
/** Private, paginated worklist for assigning outstanding volunteer duties. */

namespace Rondo\REST;

use Rondo\Core\AccessControl;
use Rondo\Core\PostTitle;
use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\People\CommunicationPolicy;
use Rondo\Volunteer\PeopleShiftProgress;
use Rondo\Volunteer\ShiftAssignments;
use Rondo\Volunteer\ShiftSignupEligibility;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerExemptionResolver;
use Rondo\Volunteer\VolunteerObligationCalculator;

class VolunteerAssignments extends Base {
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$identity = [
			'unit_id'   => [
				'type'     => 'string',
				'required' => true,
				'pattern'  => '^[a-f0-9]{64}$',
			],
			'person_id' => [
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			],
		];
		register_rest_route(
			'rondo/v1',
			'/volunteer-assignments',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_overview' ],
					'permission_callback' => [ $this, 'check_assignment_permission' ],
					'args'                => [
						'completed' => [
							'type'    => 'string',
							'enum'    => [ 'all', '0', '1' ],
							'default' => '0',
						],
						'planning'  => [
							'type'    => 'string',
							'enum'    => [ 'all', 'none', 'planned' ],
							'default' => 'none',
						],
						'search'    => [
							'type'              => 'string',
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						],
						'page'      => [
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						],
						'per_page'  => [
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
							'default' => 25,
						],
					],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'assign' ],
					'permission_callback' => [ $this, 'check_assignment_permission' ],
					'args'                => $identity + [
						'shift_id' => [
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						],
					],
				],
			]
		);
		register_rest_route(
			'rondo/v1',
			'/volunteer-assignments/shifts',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_shifts' ],
				'permission_callback' => [ $this, 'check_assignment_permission' ],
				'args'                => $identity + [
					'from' => [
						'type'     => 'string',
						'required' => true,
						'format'   => 'date',
					],
					'to'   => [
						'type'     => 'string',
						'required' => true,
						'format'   => 'date',
					],
				],
			]
		);
	}

	/** Match the restricted people-progress audience, including the IVA-only exclusion. */
	public function check_assignment_permission(): bool {
		return PeopleShiftProgress::can_view() && $this->check_vrijwilligers_permission();
	}

	/** Only return the minimum fields needed for this private worklist. */
	private function rows(): array {
		$season      = SeasonKey::current();
		$eligibility = new VolunteerEligibilityService();
		$units       = ( new VolunteerObligationCalculator() )->decorate_units( $eligibility->get_eligible_units( $season ), $season );
		$rows        = [];
		foreach ( $units as $unit ) {
			$needed = max( 0, (int) $unit['required_count'] - (int) $unit['completed_count'] - (int) $unit['pending_count'] );
			if ( $needed === 0 || VolunteerExemptionResolver::resolve_unit( $unit, $season ) !== null ) {
				continue;
			}
			// Do not reveal names, household membership or counts for hidden records.
			foreach ( $unit['person_ids'] as $person_id ) {
				if ( ! AccessControl::can_view_person( (int) $person_id ) ) {
					continue 2;
				}
			}
			$ids = array_map( 'intval', $unit['person_ids'] );
			if ( $unit['kind'] === 'gezin' ) {
				$ids = array_diff( $ids, array_map( 'intval', $unit['trigger_person_ids'] ) );
			}
			$people = [];
			foreach ( array_unique( $ids ) as $id ) {
				if ( ! $eligibility->may_volunteer( $id ) ) {
					continue;
				}
				$people[] = [
					'id'        => $id,
					'name'      => PostTitle::plain( $id ),
					'has_email' => (bool) CommunicationPolicy::primary_email( $id ),
				];
			}
			if ( ! $people ) {
				continue;
			}
			usort( $people, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
			$rows[] = [
				// Address-derived unit IDs must never leave the server.
				'unit_id'   => hash_hmac( 'sha256', $unit['unit_id'], wp_salt( 'auth' ) ),
				'kind'      => $unit['kind'],
				'people'    => $people,
				'name'      => implode( ' / ', array_column( $people, 'name' ) ),
				'required'  => (int) $unit['required_count'],
				'completed' => (int) $unit['completed_count'],
				'planned'   => (int) $unit['pending_count'],
				'needed'    => $needed,
			];
		}
		usort( $rows, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) ?: strcmp( $a['unit_id'], $b['unit_id'] ) );
		return $rows;
	}

	public function get_overview( \WP_REST_Request $request ) {
		$completed = $request['completed'];
		$planning  = $request['planning'];
		$search    = remove_accents( mb_strtolower( trim( $request['search'] ) ) );
		$rows      = array_values(
			array_filter(
				$this->rows(),
				static function ( $row ) use ( $completed, $planning, $search ) {
					return ( $completed === 'all' || $row['completed'] === (int) $completed )
						&& ( $planning === 'all' || ( $planning === 'none' ? $row['planned'] === 0 : $row['planned'] > 0 ) )
						&& ( $search === '' || str_contains( remove_accents( mb_strtolower( $row['name'] ) ), $search ) );
				}
			)
		);
		$total     = count( $rows );
		$per_page  = (int) $request['per_page'];
		return $this->private_response(
			[
				'season'      => SeasonKey::current(),
				'rows'        => array_slice( $rows, ( (int) $request['page'] - 1 ) * $per_page, $per_page ),
				'total'       => $total,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);
	}

	private function find_person( \WP_REST_Request $request ) {
		foreach ( $this->rows() as $row ) {
			if ( $row['unit_id'] === $request['unit_id'] && in_array( (int) $request['person_id'], array_column( $row['people'], 'id' ), true ) ) {
				return $row;
			}
		}
		return new \WP_Error( 'assignment_unit_unavailable', 'Deze verplichting is inmiddels ingevuld of niet meer beschikbaar. Ververs het overzicht.', [ 'status' => 409 ] );
	}

	/** A bounded date range; no contact details or other assignees leave the server. */
	public function get_shifts( \WP_REST_Request $request ) {
		$row = $this->find_person( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$from = \DateTimeImmutable::createFromFormat( '!Y-m-d', $request['from'], wp_timezone() );
		$to   = \DateTimeImmutable::createFromFormat( '!Y-m-d', $request['to'], wp_timezone() );
		if ( ! $from || ! $to || $from->format( 'Y-m-d' ) !== $request['from'] || $to->format( 'Y-m-d' ) !== $request['to'] ) {
			return new \WP_Error( 'assignment_invalid_date', 'Kies geldige datums.', [ 'status' => 400 ] );
		}
		if ( $to < $from || (int) $from->diff( $to )->days > 90 ) {
			return new \WP_Error( 'assignment_date_range', 'Kies een periode van maximaal 90 dagen.', [ 'status' => 400 ] );
		}
		$now    = current_datetime();
		$from   = max( $from, $now );
		$shifts = $this->shift_options( (int) $request['person_id'], $from, $to->setTime( 23, 59, 59 ) );
		return $this->private_response( [ 'shifts' => $shifts ] );
	}

	private function shift_options( int $person_id, \DateTimeImmutable $from, \DateTimeImmutable $to ): array {
		$posts  = get_posts(
			[
				'post_type'      => 'dienst_shift',
				'post_status'    => 'publish',
				'posts_per_page' => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- bounded to at most 91 calendar days.
				'meta_key'       => 'start_datetime',
				'orderby'        => 'meta_value',
				'order'          => 'ASC',
				'meta_query'     => [
		[
		'key'     => 'start_datetime',
		'value'   => [ $from->format( 'Y-m-d H:i:s' ), $to->format( 'Y-m-d H:i:s' ) ],
		'compare' => 'BETWEEN',
		'type'    => 'DATETIME',
				],
				],
			]
		);
		$rows   = [];
		$blocks = ShiftSignupEligibility::signup_blocks( $person_id );
		foreach ( $posts as $post ) {
			$type     = (int) Fields::get_for_post( $post->ID, 'dienst_type_id' );
			$assigned = ShiftAssignments::person_ids( $post->ID );
			$capacity = (int) Fields::get_for_post( $post->ID, 'capacity' );
			$start    = (string) Fields::get_for_post( $post->ID, 'start_datetime' );
			$end      = (string) Fields::get_for_post( $post->ID, 'end_datetime' );
			if ( Fields::get_for_post( $post->ID, 'status' ) !== 'open'
				|| get_post_type( $type ) !== 'dienst_type' || get_post_status( $type ) !== 'publish'
				|| in_array( $person_id, $assigned, true ) || ( $capacity > 0 && count( $assigned ) >= $capacity )
				|| ShiftSignupEligibility::block_reason( $post->ID, $person_id, $blocks ) !== null
				|| ! $start || ! $end || strtotime( $end ) <= strtotime( $start )
				|| SeasonKey::current( substr( $start, 0, 10 ) ) !== SeasonKey::current() ) {
				continue;
			}
			$rows[] = [
				'id'             => $post->ID,
				'name'           => PostTitle::plain( $type ),
				'start_datetime' => $start,
				'end_datetime'   => $end,
				'places'         => $capacity > 0 ? $capacity - count( $assigned ) : null,
			];
		}
		return $rows;
	}

	/** Reuse the existing assignment rules, write lock, audit trail and email queue. */
	public function assign( \WP_REST_Request $request ) {
		VolunteerObligationCalculator::invalidate_cache();
		$row = $this->find_person( $request );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$shift_id = (int) $request['shift_id'];
		$start    = (string) Fields::get_for_post( $shift_id, 'start_datetime' );
		$end      = (string) Fields::get_for_post( $shift_id, 'end_datetime' );
		$type     = (int) Fields::get_for_post( $shift_id, 'dienst_type_id' );
		if ( get_post_type( $shift_id ) !== 'dienst_shift' || get_post_status( $shift_id ) !== 'publish' || get_post_type( $type ) !== 'dienst_type' || get_post_status( $type ) !== 'publish' || ! $start || ! $end || strtotime( $end ) <= strtotime( $start ) || SeasonKey::current( substr( $start, 0, 10 ) ) !== SeasonKey::current() ) {
			return new \WP_Error( 'assignment_shift_unavailable', 'Kies een gepubliceerde dienst in het huidige seizoen.', [ 'status' => 409 ] );
		}
		$assignment = new \WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/assignees' );
		$assignment->set_param( 'person_id', (int) $request['person_id'] );
		$assignment->set_param( 'assignment_mode', 'assigned' );
		return rest_do_request( $assignment );
	}

	private function private_response( array $data ): \WP_REST_Response {
		$response = new \WP_REST_Response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}
}
