<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\REST\SportparkClosures;
use Rondo\REST\UserSettings;
use Rondo\Sportpark\Closures;
use Rondo\Volunteer\ShiftTemplateExpander;
use Rondo\Volunteer\ShiftCancellationService;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class SportparkClosuresTest extends RondoTestCase {
	private $server;
	private int $board;
	private array $mail = [];

	protected function set_up(): void {
		parent::set_up();
		$this->server = $this->bootRestControllers( [ SportparkClosures::class, UserSettings::class ] );
		$this->board  = self::factory()->user->create( [ 'role' => 'rondo_bestuur' ] );
		wp_set_current_user( $this->board );
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		add_filter(
			'pre_wp_mail',
			function ( $result, $attributes ) {
				$this->mail[] = $attributes;
				return true;
			},
			10,
			2
			);
	}

	private function request( string $method, string $suffix = '', array $data = [] ) {
		$request = new WP_REST_Request( $method, '/rondo/v1/sportpark/closures' . $suffix );
		foreach ( $data as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $this->server->dispatch( $request );
	}

	private function payload( string $from = '2032-12-24', string $to = '2033-01-02' ): array {
		return [
			'title'       => 'Kerstsluiting',
			'description' => null,
			'fields'      => [
				'starts_at' => $from,
				'ends_at'   => $to,
			],
		];
	}

	private function create( string $from = '2032-12-24', string $to = '2033-01-02' ): int {
		$response = $this->request( 'POST', '', $this->payload( $from, $to ) );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		return $response->get_data()['closure']['id'];
	}

	private function shift( string $start, string $end, string $status = 'open' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Bardienst',
			]
			);
		Fields::update_many_for_post(
			$id,
			[
				'start_datetime' => $start,
				'end_datetime'   => $end,
				'status'         => $status,
			]
			);
		return $id;
	}

	public function test_board_and_admin_can_crud_with_partial_updates_and_audit_fields(): void {
		foreach ( [ $this->board, self::factory()->user->create( [ 'role' => 'administrator' ] ) ] as $user ) {
			wp_set_current_user( $user );
			$this->assertTrue( UserRoles::can_access_board() );
			$me = new WP_REST_Request( 'GET', '/rondo/v1/user/me' );
			$this->assertTrue( $this->server->dispatch( $me )->get_data()['can_access_bestuur'] );
			$id     = $this->create();
			$record = $this->request( 'GET', '/' . $id )->get_data();
			$this->assertSame( $user, $record['created_by'] );
			$this->assertNull( $record['description'] );
			$this->assertSame( $this->payload()['fields'], $record['fields'] );
			$this->assertNotEmpty( $record['created_at'] );
			$this->assertNotEmpty( $record['updated_at'] );
			$this->assertSame( '20321224', get_post_meta( $id, 'starts_at', true ) );
			$this->assertSame( 'field_park_closure_starts_at', get_post_meta( $id, '_starts_at', true ) );
			$response = $this->request(
				'PATCH',
				'/' . $id,
				[
					'title'       => "Oud & Nieuw's",
					'description' => 'Onderhoud',
					'created_by'  => 999,
					'fields'      => [ 'ends_at' => '2033-01-03' ],
				]
				);
			$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
			$this->assertSame( $user, $response->get_data()['closure']['created_by'] );
			$this->assertSame( "Oud & Nieuw's", $response->get_data()['closure']['title'] );
			$this->assertSame( '2032-12-24', $response->get_data()['closure']['fields']['starts_at'] );
			$this->assertCount( 1, $this->request( 'GET', '', [ 'year' => 2033 ] )->get_data() );
			$this->assertSame( 200, $this->request( 'PUT', '/' . $id, [ 'description' => null ] )->get_status() );
			$this->assertNull( Closures::record( $id )['description'] );
			$this->assertSame( 200, $this->request( 'DELETE', '/' . $id )->get_status() );
			$this->assertSame( 'trash', get_post_status( $id ) );
			$this->assertSame( 404, $this->request( 'GET', '/' . $id )->get_status() );
			$this->assertSame( [], $this->request( 'GET', '', [ 'year' => 2033 ] )->get_data() );
		}
	}

	public function test_board_access_does_not_depend_on_commission_section_access(): void {
		$user = get_user_by( 'id', $this->board );
		$user->add_cap( 'commissies', false );
		$this->assertFalse( UserRoles::can_manage_commissie_info( $this->board ) );
		$this->assertTrue( UserRoles::can_access_board( $this->board ) );
		$this->assertSame( 200, $this->request( 'GET' )->get_status() );
		$this->assertGreaterThan( 0, $this->create() );
	}

	public function test_every_endpoint_denies_other_roles_and_anonymous_users(): void {
		$id = $this->create();
		foreach ( [ 'subscriber', 'rondo_user', 'rondo_vrijwilligers', 'rondo_accommodatiebeheerder', 'rondo_fairplay', null ] as $role ) {
			wp_set_current_user( $role ? self::factory()->user->create( [ 'role' => $role ] ) : 0 );
			$this->assertFalse( UserRoles::can_access_board() );
			foreach ( [ [ 'GET', '' ], [ 'GET', '/' . $id ], [ 'POST', '' ], [ 'PUT', '/' . $id ], [ 'PATCH', '/' . $id ], [ 'DELETE', '/' . $id ] ] as [ $method, $suffix ] ) {
				$this->assertSame( $role ? 403 : 401, $this->request( $method, $suffix, $this->payload() )->get_status(), $role . ' ' . $method );
			}
		}
		wp_set_current_user( $this->board );
		$this->assertSame( 404, $this->server->dispatch( new WP_REST_Request( 'POST', '/wp/v2/rondo_park_closure' ) )->get_status() );
	}

	public function test_validation_and_wrong_post_type_never_mutate_records(): void {
		foreach ( [
			[ '2032-02-30', '2032-03-01' ],
			[ '2032-12-25', '2032-12-24' ],
			[ '2032-12-24T00:00:00Z', '2032-12-25' ],
			[ '', '2032-12-25' ],
		] as [ $from, $to ] ) {
			$this->assertSame( 400, $this->request( 'POST', '', $this->payload( $from, $to ) )->get_status() );
		}
		$this->assertSame( 400, $this->request( 'POST', '', array_merge( $this->payload(), [ 'title' => '  ' ] ) )->get_status() );
		$this->assertSame( 400, $this->request( 'POST', '', array_merge( $this->payload(), [ 'fields' => [ 'unknown' => 1 ] ] ) )->get_status() );
		$this->assertSame( 400, $this->request( 'POST', '', array_merge( $this->payload(), [ 'existing_tasks' => 'delete' ] ) )->get_status() );
		$id = self::factory()->post->create();
		foreach ( [ 'GET', 'PUT', 'DELETE' ] as $method ) {
			$this->assertSame( 404, $this->request( $method, '/' . $id, $this->payload() )->get_status() );
		}
		$this->assertSame( [], Closures::between( '2032-01-01', '2033-12-31' ) );
	}

	public function test_inclusive_boundaries_overlap_leap_day_and_deletion(): void {
		$id      = $this->create();
		$overlap = $this->create( '2033-01-01', '2033-01-05' );
		$this->create( '2032-02-29', '2032-02-29' );
		$periods = Closures::between( '2032-01-01', '2033-12-31' );
		foreach ( [ '2032-12-24', '2033-01-02', '2033-01-05', '2032-02-29' ] as $date ) {
			$this->assertTrue( Closures::covers( $date, $periods ), $date );
		}
		foreach ( [ '2032-12-23', '2033-01-06', '2032-02-28', '2032-03-01' ] as $date ) {
			$this->assertFalse( Closures::covers( $date, $periods ), $date );
		}
		$this->request( 'DELETE', '/' . $id );
		$this->assertTrue( Closures::covers( '2033-01-02', Closures::between( '2033-01-01', '2033-12-31' ) ) );
		$this->request( 'DELETE', '/' . $overlap );
		$this->assertFalse( Closures::covers( '2033-01-02', Closures::between( '2033-01-01', '2033-12-31' ) ) );
	}

	public function test_conflicts_require_an_explicit_fresh_choice_and_keep_is_non_destructive(): void {
		$id       = $this->shift( '2032-12-24 10:00:00', '2032-12-24 12:00:00' );
		$response = $this->request( 'POST', '', $this->payload() );
		$this->assertSame( 409, $response->get_status() );
		$this->assertCount( 1, $response->get_data()['data']['conflicts'] );
		$this->assertSame( [], Closures::between( '2032-12-24', '2033-01-02' ) );
		$this->assertSame( 'open', get_post_meta( $id, 'status', true ) );
		$data   = array_merge(
			$this->payload(),
			[
				'existing_tasks' => 'keep',
				'conflict_token' => $response->get_data()['data']['conflict_token'],
			]
			);
		$second = $this->shift( '2032-12-25 10:00:00', '2032-12-25 12:00:00' );
		$stale  = $this->request( 'POST', '', $data );
		$this->assertSame( 409, $stale->get_status() );
		$this->assertCount( 2, $stale->get_data()['data']['conflicts'] );
		$data['conflict_token'] = $stale->get_data()['data']['conflict_token'];
		$this->assertSame( 200, $this->request( 'POST', '', $data )->get_status() );
		$this->assertSame( 'open', get_post_meta( $id, 'status', true ) );
		$this->assertSame( 'open', get_post_meta( $second, 'status', true ) );
		$this->assertSame( [], $this->mail );
	}

	public function test_cancel_retains_assignments_and_audit_and_reports_uncancellable_tasks(): void {
		$id     = $this->shift( '2032-12-24 10:00:00', '2032-12-24 12:00:00' );
		$person = $this->createPerson( [], [ 'email_1' => 'closure-test@example.com' ] );
		$type   = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Bardienst',
			]
			);
		Fields::update_for_post( $id, 'dienst_type_id', $type );
		Fields::update_for_post( $id, 'assigned_persons', [ $person ] );
		$completed = $this->shift( '2032-12-25 10:00:00', '2032-12-25 12:00:00', 'voltooid' );
		$conflict  = $this->request( 'POST', '', $this->payload() )->get_data()['data'];
		$this->assertFalse( $conflict['conflicts'][1]['can_cancel'] );
		$result = $this->request(
			'POST',
			'',
			array_merge(
			$this->payload(),
			[
				'existing_tasks' => 'cancel',
				'conflict_token' => $conflict['conflict_token'],
			]
			)
			);
		$this->assertSame( 200, $result->get_status(), wp_json_encode( $result->get_data() ) );
		$this->assertSame( [ $id ], $result->get_data()['cancelled'] );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 'geannuleerd', get_post_meta( $id, 'status', true ) );
		$this->assertSame( [ $person ], array_map( 'intval', Fields::get_for_post( $id, 'assigned_persons' ) ) );
		$this->assertSame( $this->board, (int) get_post_meta( $id, ShiftCancellationService::META_CANCELLED_BY, true ) );
		$this->assertSame( 'Sportpark gesloten: Kerstsluiting', ShiftCancellationService::details( $id )['reason'] );
		$this->assertSame( 'voltooid', get_post_meta( $completed, 'status', true ) );
		$this->request( 'DELETE', '/' . $result->get_data()['closure']['id'] );
		$this->assertSame( 'geannuleerd', get_post_meta( $id, 'status', true ) );
	}

	public function test_update_also_prompts_and_overnight_tasks_overlap_correctly(): void {
		$id = $this->create( '2032-12-20', '2032-12-20' );
		$this->shift( '2032-12-23 23:00:00', '2032-12-24 01:00:00' );
		$this->shift( '2032-12-23 22:00:00', '2032-12-24 00:00:00' );
		$this->shift( '2032-12-25 00:00:00', '2032-12-25 01:00:00' );
		$this->shift( '2032-12-24 10:00:00', '2032-12-24 12:00:00', 'geannuleerd' );
		$result = $this->request( 'PUT', '/' . $id, $this->payload( '2032-12-24', '2032-12-24' ) );
		$this->assertSame( 409, $result->get_status() );
		$this->assertCount( 1, $result->get_data()['data']['conflicts'] );
		$this->assertSame( '2032-12-20', Closures::record( $id )['fields']['starts_at'] );
	}

	public function test_template_expansion_skips_closed_dates_and_resumes_after_removal(): void {
		$from     = '2032-12-24';
		$type     = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
			]
			);
		$template = self::factory()->post->create(
			[
				'post_type'   => 'shift_template',
				'post_status' => 'publish',
			]
			);
		Fields::update_many_for_post(
			$template,
			[
				'dienst_type_id' => $type,
				'day_of_week'    => (int) gmdate( 'N', strtotime( $from ) ),
				'start_time'     => '10:00',
				'end_time'       => '12:00',
				'capacity'       => 1,
				'active_from'    => $from,
			]
			);
		$id = $this->create( $from, '2032-12-31' );
		$this->assertSame( 1, ShiftTemplateExpander::expand_template( $template, $from, '2033-01-07' ) );
		$this->assertSame( 0, ShiftTemplateExpander::expand_template( $template, $from, '2033-01-07' ) );
		$this->assertSame( [], Closures::conflicts( $from, '2032-12-31' ) );
		// Ad-hoc planning stays available on closed dates.
		$manual = $this->shift( '2032-12-24 14:00:00', '2032-12-24 16:00:00' );
		$this->assertSame( 'publish', get_post_status( $manual ) );
		$this->request( 'DELETE', '/' . $id );
		$this->assertSame( 2, ShiftTemplateExpander::expand_template( $template, $from, '2033-01-07' ) );
	}

	public function test_shared_lock_prevents_closure_writes_and_releases_after_failure(): void {
		$result = Closures::locked( fn() => $this->request( 'POST', '', $this->payload() ) );
		$this->assertSame( 409, $result->get_status() );
		$this->assertSame( 'sportpark_busy', $result->get_data()['code'] );
		$this->create();
	}
}
