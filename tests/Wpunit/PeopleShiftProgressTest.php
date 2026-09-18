<?php

namespace Tests\Wpunit;

use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\REST\People;
use Rondo\REST\UserSettings;
use Rondo\Volunteer\PeopleShiftProgress;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Tests\Support\RondoTestCase;

class PeopleShiftProgressTest extends RondoTestCase {
	private \WP_REST_Server $server;
	private int $viewer;

	protected function set_up(): void {
		parent::set_up();
		$this->viewer = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $this->viewer );
		$this->server = $this->bootRestControllers( [ People::class, UserSettings::class ] );
	}

	private function request( array $params = [], string $route = '/people/filtered' ): \WP_REST_Response {
		VolunteerEligibilityService::invalidate_cache();
		VolunteerObligationCalculator::invalidate_cache();
		$request = new \WP_REST_Request( 'GET', '/rondo/v1' . $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	private function player( string $name ): int {
		return $this->createPerson(
			[ 'post_title' => $name ],
			[
				'first_name'     => $name,
				'leeftijdsgroep' => 'Senioren',
			]
			);
	}

	private function shift( int $person, string $status = 'voltooid' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
			]
			);
		update_post_meta( $id, 'start_datetime', $status === 'voltooid' ? substr( SeasonKey::current(), 0, 4 ) . '-07-02 10:00:00' : current_datetime()->modify( '+1 day' )->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $id, 'status', $status );
		update_post_meta( $id, 'assigned_persons', [ $person ] );
		return $id;
	}

	public function test_only_explicit_roles_can_filter_read_counts_and_discover_columns(): void {
		$this->player( 'Player' );
		foreach ( [
			'rondo_bestuur'       => true,
			'rondo_vrijwilligers' => true,
			'administrator'       => false,
			'rondo_user'          => false,
			'rondo_iva_approver'  => false,
		] as $role => $allowed ) {
			$user = $this->createRondoUser( [ 'role' => $role ] );
			wp_set_current_user( $user );
			$this->assertSame( $allowed, PeopleShiftProgress::can_view(), $role );
			$this->assertSame( $allowed ? 200 : 403, $this->request( [ 'include_shift_progress' => true ] )->get_status(), $role );
			$this->assertSame( $allowed ? 200 : 403, $this->request( [ 'shift_status' => 'not_started' ] )->get_status(), $role );
			$preferences = $this->request( [], '/user/list-preferences' )->get_data();
			$this->assertSame( $allowed, in_array( 'shift_required', array_column( $preferences['available_columns'], 'id' ), true ), $role );
			$profile = ( new UserSettings() )->get_current_user_data( $user );
			$this->assertSame( $allowed, $profile['can_view_people_shift_progress'], $role );
			foreach ( $this->request()->get_data()['people'] as $person ) {
				$this->assertArrayNotHasKey( 'shift_progress', $person );
			}
		}
	}

	public function test_counts_filters_and_pagination_agree(): void {
		$a = $this->player( 'A' );
		$b = $this->player( 'B' );
		$c = $this->player( 'C' );
		$d = $this->player( 'D' );
		$e = $this->player( 'E' );
		$f = $this->player( 'F' );
		$this->createPerson( [ 'post_title' => 'No duty' ] );
		$this->shift( $b, 'open' );
		$this->shift( $c, 'open' );
		$this->shift( $c, 'open' );
		$this->shift( $d );
		$this->shift( $d );
		Fields::update_for_post( $e, 'vrijgesteld_handmatig', true );
		Fields::update_for_post( $e, 'vrijstelling_seizoen', SeasonKey::current() );
		foreach ( [
			'not_started'  => [ $a, $f ],
			'insufficient' => [ $b ],
			'planned'      => [ $c ],
			'completed'    => [ $d ],
			'exempt'       => [ $e ],
		] as $status => $ids ) {
			$response = $this->request( [ 'shift_status' => $status ] );
			$this->assertSame( 200, $response->get_status() );
			$data = $response->get_data();
			$this->assertEqualsCanonicalizing( $ids, array_column( $data['people'], 'id' ), $status );
			$this->assertSame( count( $ids ), $data['total'] );
			foreach ( $data['people'] as $person ) {
				$this->assertSame( $status, $person['shift_progress']['status'] );
			}
		}
		$first = $this->request(
			[
				'shift_status' => 'not_started',
				'per_page'     => 1,
			]
			)->get_data();
		$next  = $this->request(
			[
				'shift_status' => 'not_started',
				'per_page'     => 1,
				'page'         => 2,
			]
			)->get_data();
		$this->assertSame( 2, $first['total_pages'] );
		$this->assertSame( [ $a, $f ], [ $first['people'][0]['id'], $next['people'][0]['id'] ] );
		$this->assertSame(
			0,
			$this->request(
			[
				'shift_status' => 'not_started',
				'first_name'   => 'Missing',
			]
			)->get_data()['total']
			);
		$this->assertSame( 400, $this->request( [ 'shift_status' => 'unknown' ] )->get_status() );
	}

	public function test_partner_shifts_count_for_both_parents_but_not_as_a_child_duty(): void {
		$parents       = [ $this->createPerson( [ 'post_title' => 'Parent A' ] ), $this->createPerson( [ 'post_title' => 'Parent B' ] ) ];
		$child         = $this->createPerson( [ 'post_title' => 'Child' ], [ 'leeftijdsgroep' => 'Onder 12' ] );
		$relationships = [];
		foreach ( $parents as $parent ) {
			$relationships[] = [
				'related_person'    => $parent,
				'relationship_type' => 2,
			];
			Fields::update_for_post(
				$parent,
				'relationships',
				[
					[
						'related_person'    => $child,
						'relationship_type' => 3,
					],
				]
				);
		}
		Fields::update_for_post( $child, 'relationships', $relationships );
		$this->shift( $parents[0] );
		$this->shift( $parents[0], 'open' );
		$data = $this->request( [ 'shift_status' => 'planned' ] )->get_data();
		$this->assertEqualsCanonicalizing( $parents, array_column( $data['people'], 'id' ) );
		foreach ( $data['people'] as $person ) {
			$this->assertSame(
				[
					'required'  => 2,
					'completed' => 1,
					'planned'   => 1,
					'family'    => true,
					'status'    => 'planned',
				],
				$person['shift_progress']
				);
		}
	}

	public function test_excess_work_on_one_duty_does_not_hide_another_duty(): void {
		$units = [
			[
				'kind'            => 'speler',
				'is_exempt'       => false,
				'required_count'  => 2,
				'completed_count' => 4,
				'pending_count'   => 0,
			],
			[
				'kind'            => 'gezin',
				'is_exempt'       => false,
				'required_count'  => 2,
				'completed_count' => 0,
				'pending_count'   => 0,
			],
		];
		$this->assertSame( 'insufficient', ( new PeopleShiftProgress() )->summarize( $units )['status'] );
	}

	public function test_cancelled_no_show_and_out_of_season_shifts_do_not_satisfy_a_duty(): void {
		$person = $this->player( 'Player' );
		$this->shift( $person, 'geannuleerd' );
		$no_show = $this->shift( $person );
		update_post_meta( $no_show, '_no_show_' . $person, '1' );
		$previous = $this->shift( $person );
		update_post_meta( $previous, 'start_datetime', ( (int) substr( SeasonKey::current(), 0, 4 ) - 1 ) . '-09-01 10:00:00' );
		$data = $this->request( [ 'shift_status' => 'not_started' ] )->get_data();
		$this->assertSame( [ $person ], array_column( $data['people'], 'id' ) );
		$this->assertSame( 0, $data['people'][0]['shift_progress']['completed'] );
	}

	public function test_existing_age_group_filter_still_applies(): void {
		$this->player( 'Senior' );
		$this->assertSame(
			0,
			$this->request(
			[
				'shift_status'   => 'not_started',
				'leeftijdsgroep' => 'Onder 17',
			]
			)->get_data()['total']
			);
		$this->assertSame(
			1,
			$this->request(
			[
				'shift_status'   => 'not_started',
				'leeftijdsgroep' => 'Senioren',
			]
			)->get_data()['total']
			);
	}
}
