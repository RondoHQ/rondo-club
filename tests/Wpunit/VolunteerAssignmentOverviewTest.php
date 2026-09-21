<?php
namespace Tests\Wpunit;

use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\REST\MemberShifts;
use Rondo\REST\VolunteerAssignments;
use Rondo\Volunteer\ShiftAssignments;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Tests\Support\RondoTestCase;

class VolunteerAssignmentOverviewTest extends RondoTestCase {
	private \WP_REST_Server $server;
	private int $type;

	protected function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		$this->server = $this->bootRestControllers( [ VolunteerAssignments::class, MemberShifts::class ] );
		$this->type   = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Club host',
			]
			);
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	private function request( array $params = [], string $method = 'GET', string $suffix = '' ): \WP_REST_Response {
		VolunteerEligibilityService::invalidate_cache();
		VolunteerObligationCalculator::invalidate_cache();
		$request = new \WP_REST_Request( $method, '/rondo/v1/volunteer-assignments' . $suffix );
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
				'email_1'        => 'test@example.org',
			]
			);
	}

	private function shift( array $people = [], string $status = 'open', int $day = 2 ): int {
		$id   = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
			]
			);
		$date = $status === 'voltooid' ? new \DateTimeImmutable( substr( SeasonKey::current(), 0, 4 ) . '-07-02', wp_timezone() ) : current_datetime()->modify( '+' . $day . ' days' );
		foreach ( [
			'start_datetime'   => $date->setTime( 10, 0 )->format( 'Y-m-d H:i:s' ),
			'end_datetime'     => $date->setTime( 12, 0 )->format( 'Y-m-d H:i:s' ),
			'dienst_type_id'   => $this->type,
			'status'           => $status,
			'assigned_persons' => $people,
			'capacity'         => 2,
		] as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	private function identity( int $person ): array {
		return [
			'unit_id'   => hash_hmac( 'sha256', 'speler-' . $person, wp_salt( 'auth' ) ),
			'person_id' => $person,
		];
	}

	private function period(): array {
		return [
			'from' => current_datetime()->format( 'Y-m-d' ),
			'to'   => current_datetime()->modify( '+30 days' )->format( 'Y-m-d' ),
		];
	}

	public function test_private_routes_require_volunteer_management_access(): void {
		$person = $this->player( 'Member' );
		$shift  = $this->shift();
		foreach ( [ 'rondo_user', 'rondo_iva_approver', 'administrator' ] as $role ) {
			wp_set_current_user( $this->createRondoUser( [ 'role' => $role ] ) );
			$this->assertSame( 403, $this->request()->get_status() );
			$this->assertSame( 403, $this->request( $this->identity( $person ) + $this->period(), 'GET', '/shifts' )->get_status() );
			$this->assertSame( 403, $this->request( $this->identity( $person ) + [ 'shift_id' => $shift ], 'POST' )->get_status() );
		}
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request()->get_status() );
		$this->assertEmpty( ShiftAssignments::person_ids( $shift ) );
	}

	public function test_filters_counts_pagination_and_minimal_payload(): void {
		$a = $this->player( 'Anne' );
		$b = $this->player( 'Bram' );
		$c = $this->player( 'Carin' );
		$d = $this->player( 'Daan' );
		$e = $this->player( 'Exempt' );
		$this->shift( [ $b ] );
		$this->shift( [ $c ], 'voltooid' );
		$this->shift( [ $d ], 'open', 3 );
		$this->shift( [ $d ], 'open', 4 );
		Fields::update_for_post( $e, 'vrijgesteld_handmatig', true );
		Fields::update_for_post( $e, 'vrijstelling_seizoen', SeasonKey::current() );
		$response = $this->request();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( [ 'Anne' ], array_column( $response->get_data()['rows'], 'name' ) );
		$this->assertSame( [ 'Carin' ], array_column( $this->request( [ 'completed' => '1' ] )->get_data()['rows'], 'name' ) );
		$planned = $this->request( [ 'planning' => 'planned' ] )->get_data()['rows'];
		$this->assertSame( [ 'Bram' ], array_column( $planned, 'name' ) );
		$this->assertSame( 1, $planned[0]['needed'] );
		$all = $this->request(
			[
				'completed' => 'all',
				'planning'  => 'all',
				'per_page'  => 1,
				'page'      => 2,
			]
			)->get_data();
		$this->assertSame( 3, $all['total'] );
		$this->assertSame( 3, $all['total_pages'] );
		$this->assertSame( 'Bram', $all['rows'][0]['name'] );
		$this->assertSame( [ 'Anne' ], array_column( $this->request( [ 'search' => 'ann' ] )->get_data()['rows'], 'name' ) );
		$this->assertSame( [ 'id', 'name', 'has_email' ], array_keys( $response->get_data()['rows'][0]['people'][0] ) );
		$this->assertArrayNotHasKey( 'address_key', $response->get_data()['rows'][0] );
		$this->assertSame( 400, $this->request( [ 'per_page' => 1000 ] )->get_status() );
		$this->assertSame( 400, $this->request( [ 'completed' => 'invalid' ] )->get_status() );
	}

	public function test_household_is_one_row_and_partner_bookings_count(): void {
		$a             = $this->createPerson( [ 'post_title' => 'Parent A' ] );
		$b             = $this->createPerson( [ 'post_title' => 'Parent B' ] );
		$child         = $this->createPerson( [], [ 'leeftijdsgroep' => 'Onder 12' ] );
		$relationships = [];
		foreach ( [ $a, $b ] as $parent ) {
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
		$this->shift( [ $b ] );
		$data = $this->request( [ 'planning' => 'all' ] )->get_data();
		$this->assertSame( 1, $data['total'] );
		$row = $data['rows'][0];
		$this->assertSame( 'gezin', $row['kind'] );
		$this->assertSame( 1, $row['planned'] );
		$this->assertSame( 1, $row['needed'] );
		$this->assertEqualsCanonicalizing( [ $a, $b ], array_column( $row['people'], 'id' ) );
		$this->assertSame( 0, $this->request()->get_data()['total'] );
		$this->assertFalse( $row['people'][0]['has_email'] );
		$shift = $this->shift( [], 'open', 3 );
		$this->assertSame(
			409,
			$this->request(
			[
				'unit_id'   => $row['unit_id'],
				'person_id' => $child,
				'shift_id'  => $shift,
			],
			'POST'
			)->get_status()
			);
		$this->assertSame(
			200,
			$this->request(
			[
				'unit_id'   => $row['unit_id'],
				'person_id' => $a,
				'shift_id'  => $shift,
			],
			'POST'
			)->get_status()
			);
		$this->assertSame( 0, $this->request( [ 'planning' => 'all' ] )->get_data()['total'] );
	}

	public function test_options_exclude_full_closed_iva_and_other_assignees(): void {
		$person = $this->player( 'Player' );
		$open   = $this->shift();
		$full   = $this->shift( [ $this->createPerson(), $this->createPerson() ] );
		$closed = $this->shift( [], 'geannuleerd' );
		$owned  = $this->shift( [ $person ], 'open', 4 );
		$data   = $this->request( $this->identity( $person ) + $this->period(), 'GET', '/shifts' )->get_data();
		$this->assertSame( [ $open ], array_column( $data['shifts'], 'id' ) );
		$this->assertArrayNotHasKey( 'assigned_persons', $data['shifts'][0] );
		update_post_meta( $this->type, 'iva_required', true );
		$this->assertEmpty( $this->request( $this->identity( $person ) + $this->period(), 'GET', '/shifts' )->get_data()['shifts'] );
		$this->assertSame( 403, $this->request( $this->identity( $person ) + [ 'shift_id' => $open ], 'POST' )->get_status() );
		$this->assertSame(
			400,
			$this->request(
			$this->identity( $person ) + [
				'from' => 'bad',
				'to'   => '2026-10-01',
			],
			'GET',
			'/shifts'
			)->get_status()
			);
		$this->assertSame(
			400,
			$this->request(
			$this->identity( $person ) + [
				'from' => '2026-01-01',
				'to'   => '2026-12-31',
			],
			'GET',
			'/shifts'
			)->get_status()
			);
	}

	public function test_assignment_uses_duty_mode_audit_and_confirmation_queue_and_rechecks_progress(): void {
		$person   = $this->player( 'Player' );
		$shift    = $this->shift();
		$response = $this->request( $this->identity( $person ) + [ 'shift_id' => $shift ], 'POST' );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertTrue( ShiftAssignments::is_duty_assignment( $shift, $person ) );
		$this->assertSame( get_current_user_id(), (int) get_post_meta( $shift, '_shift_assigned_by_' . $person, true ) );
		$this->assertTrue( $response->get_data()['notification']['queued'] );
		$this->assertNotEmpty( get_post_meta( $shift, '_shift_confirmation_queued_at_' . $person, true ) );
		$this->assertSame( 0, $this->request()->get_data()['total'] );
		$this->shift( [ $person ], 'open', 3 );
		$another = $this->shift( [], 'open', 5 );
		$this->assertSame( 409, $this->request( $this->identity( $person ) + [ 'shift_id' => $another ], 'POST' )->get_status() );
		$this->assertEmpty( ShiftAssignments::person_ids( $another ) );
	}

	public function test_wrong_person_full_shift_overlap_and_draft_are_rejected(): void {
		$person = $this->player( 'Player' );
		$other  = $this->player( 'Other' );
		$shift  = $this->shift();
		$this->assertSame(
			409,
			$this->request(
			[
				'unit_id'   => $this->identity( $person )['unit_id'],
				'person_id' => $other,
				'shift_id'  => $shift,
			],
			'POST'
			)->get_status()
			);
		update_post_meta( $shift, 'capacity', 1 );
		update_post_meta( $shift, 'assigned_persons', [ $other ] );
		$this->assertSame( 409, $this->request( $this->identity( $person ) + [ 'shift_id' => $shift ], 'POST' )->get_status() );
		$overlap = $this->shift();
		$this->shift( [ $person ] );
		$result = $this->request( $this->identity( $person ) + [ 'shift_id' => $overlap ], 'POST' );
		$this->assertSame( 'overlap_warning', $result->get_data()['code'] );
		wp_update_post(
			[
				'ID'          => $overlap,
				'post_status' => 'draft',
			]
			);
		$this->assertSame( 409, $this->request( $this->identity( $person ) + [ 'shift_id' => $overlap ], 'POST' )->get_status() );
	}
}
