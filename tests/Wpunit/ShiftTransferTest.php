<?php
namespace Tests\Wpunit;

use Rondo\REST\MemberShifts;
use Rondo\Fields\Fields;
use Rondo\Data\InverseRelationships;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class ShiftTransferTest extends RondoTestCase {
	private int $source;
	private int $target;
	private int $other;
	private int $shift;
	private int $type;

	protected function set_up(): void {
		parent::set_up();
		$this->bootRestControllers( [ MemberShifts::class ] );
		$this->as_role( 'administrator' );
		$this->source = $this->createPerson( [ 'post_title' => 'Bron' ] );
		$this->target = $this->createPerson( [ 'post_title' => 'Doel' ] );
		$this->other  = $this->createPerson( [ 'post_title' => 'Andere vrijwilliger' ] );
		$this->type   = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Terrein',
			]
			);
		$this->shift  = $this->shift( 'vol' );
	}

	private function as_role( string $role ): int {
		$id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $id );
		return $id;
	}

	private function shift( string $status ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Testdienst',
			]
			);
		foreach ( [
			'dienst_type_id'   => $this->type,
			'assigned_persons' => [ $this->source, $this->other ],
			'status'           => $status,
			'start_datetime'   => gmdate( 'Y-m-d H:i:s', strtotime( $status === 'voltooid' ? '-7 days' : '+14 days' ) ),
			'end_datetime'     => gmdate( 'Y-m-d H:i:s', strtotime( $status === 'voltooid' ? '-7 days +2 hours' : '+14 days +2 hours' ) ),
			'capacity'         => 2,
			'template_id'      => 678,
		] as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		return $id;
	}

	private function request( string $method, string $suffix = '', array $data = [] ) {
		$request = new WP_REST_Request( $method, '/rondo/v1/people/' . $this->source . '/shift-transfer' . $suffix );
		foreach ( $data as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_get_server()->dispatch( $request );
	}

	private function payload( ?array $ids = null ): array {
		return [
			'target_id' => $this->target,
			'shift_ids' => $ids ?? [ $this->shift ],
		];
	}

	private function preview( ?array $ids = null ) {
		return $this->request( 'POST', '/preview', $this->payload( $ids ) );
	}

	private function family( int $from, int $to, int $type ): void {
		$rows   = Fields::get_for_post( $from, 'relationships' ) ?: [];
		$rows[] = [
			'related_person'    => $to,
			'relationship_type' => $type,
		];
		Fields::update_for_post( $from, 'relationships', $rows );
	}

	public function test_ledenadministratie_is_limited_to_family_even_with_forged_target(): void {
		$this->as_role( 'rondo_ledenadministratie' );
		$this->assertSame( 200, $this->request( 'GET' )->get_status() );
		$this->assertSame( 403, $this->preview()->get_status() );
		$this->assertSame( 403, $this->request( 'POST', '', $this->payload() + [ 'token' => str_repeat( 'a', 64 ) ] )->get_status() );
		$this->family( $this->source, $this->target, InverseRelationships::TYPE_PARENT );
		$response = $this->preview();
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertSame( [ $this->target ], array_column( $this->request( 'GET' )->get_data()['people'], 'id' ) );
		$this->assertSame( 200, $this->request( 'POST', '', $this->payload() + [ 'token' => $response->get_data()['token'] ] )->get_status() );
	}

	public function test_board_and_admin_may_transfer_outside_family(): void {
		foreach ( [ 'rondo_bestuur', 'administrator' ] as $role ) {
			$this->as_role( $role );
			$this->assertFalse( $this->request( 'GET' )->get_data()['family_only'] );
			$this->assertSame( 200, $this->preview()->get_status() );
		}
	}

	public function test_members_and_volunteer_coordinators_cannot_transfer(): void {
		foreach ( [ 'rondo_user', 'rondo_vrijwilligers' ] as $role ) {
			$this->as_role( $role );
			$this->assertSame( 403, $this->request( 'GET' )->get_status() );
			$this->assertSame( 403, $this->preview()->get_status() );
		}
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request( 'GET' )->get_status() );
	}

	public function test_selected_completed_and_planned_tasks_preserve_history_and_other_assignees(): void {
		$completed  = $this->shift( 'voltooid' );
		$cancelled  = $this->shift( 'geannuleerd' );
		$unselected = $this->shift( 'open' );
		$values     = [
			'_shift_signup_at_'              => '123456',
			'_shift_signup_user_'            => '123',
			'_shift_signup_guardian_name_'   => "O'Brien",
			'_shift_email_reminder_14_sent_' => '2026-09-01 10:00:00',
			'_shift_assignment_mode_'        => 'assigned',
			'_no_show_'                      => [
				'by' => 1,
				'at' => '2026-09-01',
			],
		];
		foreach ( $values as $prefix => $value ) {
			update_post_meta( $completed, $prefix . $this->source, $value );
		}
		$ids     = [ $this->shift, $completed ];
		$preview = $this->preview( $ids );
		$this->assertSame( 200, $preview->get_status(), wp_json_encode( $preview->get_data() ) );
		$payload  = $this->payload( $ids ) + [ 'token' => $preview->get_data()['token'] ];
		$response = $this->request( 'POST', '', $payload );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		foreach ( $ids as $id ) {
			$this->assertSame( [ $this->target, $this->other ], get_post_meta( $id, 'assigned_persons', true ) );
			$this->assertSame( '678', get_post_meta( $id, 'template_id', true ) );
			$this->assertSame( '', get_post_meta( $id, '_manually_edited', true ) );
		}
		$this->assertSame( 'voltooid', get_post_meta( $completed, 'status', true ) );
		$this->assertSame( 'vol', get_post_meta( $this->shift, 'status', true ) );
		foreach ( $values as $prefix => $value ) {
			$this->assertEquals( $value, get_post_meta( $completed, $prefix . $this->target, true ) );
			$this->assertFalse( metadata_exists( 'post', $completed, $prefix . $this->source ) );
		}
		foreach ( [ $cancelled, $unselected ] as $id ) {
			$this->assertSame( [ $this->source, $this->other ], get_post_meta( $id, 'assigned_persons', true ) );
		}
		$this->assertTrue( $this->request( 'POST', '', $payload )->get_data()['already_transferred'] );
		$this->assertCount( 1, get_post_meta( $completed, '_rondo_shift_transfer_' . $payload['token'], false ) );
	}

	public function test_stale_preview_and_cancelled_selection_write_nothing(): void {
		$preview = $this->preview()->get_data();
		update_post_meta( $this->shift, 'end_datetime', gmdate( 'Y-m-d H:i:s', strtotime( '+14 days +3 hours' ) ) );
		$this->assertSame( 409, $this->request( 'POST', '', $this->payload() + [ 'token' => $preview['token'] ] )->get_status() );
		$this->assertSame( 409, $this->preview( [ $this->shift, $this->shift( 'geannuleerd' ) ] )->get_status() );
		$this->assertSame( [ $this->source, $this->other ], get_post_meta( $this->shift, 'assigned_persons', true ) );
	}

	public function test_existing_registration_or_history_is_not_overwritten(): void {
		update_post_meta( $this->shift, 'assigned_persons', [ $this->source, $this->target ] );
		$this->assertSame( 409, $this->preview()->get_status() );
		update_post_meta( $this->shift, 'assigned_persons', [ $this->source ] );
		update_post_meta( $this->shift, '_no_show_' . $this->target, 1 );
		$this->assertSame( 409, $this->preview()->get_status() );
	}

	public function test_permissions_are_rechecked_after_preview(): void {
		$this->as_role( 'rondo_ledenadministratie' );
		$this->family( $this->source, $this->target, InverseRelationships::TYPE_PARENT );
		$preview = $this->preview()->get_data();
		Fields::update_for_post( $this->source, 'relationships', [] );
		$this->assertSame( 403, $this->request( 'POST', '', $this->payload() + [ 'token' => $preview['token'] ] )->get_status() );
		$this->assertSame( [ $this->source, $this->other ], get_post_meta( $this->shift, 'assigned_persons', true ) );
	}

	public function test_hidden_and_unpublished_targets_are_unavailable(): void {
		wp_update_post(
			[
				'ID'          => $this->target,
				'post_status' => 'draft',
			]
			);
		$this->assertSame( 400, $this->preview()->get_status() );
		$this->assertSame( [], $this->request( 'GET', '', [ 'search' => (string) $this->target ] )->get_data()['people'] );
	}

	public function test_busy_shift_prevents_the_entire_batch(): void {
		$second  = $this->shift( 'voltooid' );
		$ids     = [ $this->shift, $second ];
		$preview = $this->preview( $ids )->get_data();
		add_option(
			'rondo_shift_write_lock_' . $second,
			[
				'token'      => 'other',
				'created_at' => microtime( true ),
			],
			'',
			false
			);
		$this->assertSame( 503, $this->request( 'POST', '', $this->payload( $ids ) + [ 'token' => $preview['token'] ] )->get_status() );
		$this->assertSame( [ $this->source, $this->other ], get_post_meta( $this->shift, 'assigned_persons', true ) );
		$this->assertFalse( get_option( 'rondo_shift_write_lock_' . $this->shift ) );
	}

	public function test_family_includes_siblings_and_coparents_without_transitive_cousins(): void {
		$child = $this->createPerson();
		$this->family( $this->source, $child, InverseRelationships::TYPE_CHILD );
		$this->family( $child, $this->target, InverseRelationships::TYPE_PARENT );
		$this->as_role( 'rondo_ledenadministratie' );
		$this->assertContains( $this->target, array_column( $this->request( 'GET' )->get_data()['people'], 'id' ) );
		Fields::update_for_post( $this->source, 'relationships', [] );
		$this->family( $this->source, $child, InverseRelationships::TYPE_PARENT );
		$this->family( $child, $this->target, InverseRelationships::TYPE_CHILD );
		$this->family( $this->target, $this->other, InverseRelationships::TYPE_CHILD );
		$ids = array_column( $this->request( 'GET' )->get_data()['people'], 'id' );
		$this->assertContains( $this->target, $ids );
		$this->assertNotContains( $this->other, $ids );
	}

	public function test_certificate_rules_still_apply_to_planned_but_not_historical_tasks(): void {
		update_post_meta( $this->type, 'vog_required', 1 );
		$this->assertSame( 403, $this->preview()->get_status() );
		$this->assertSame( 200, $this->preview( [ $this->shift( 'voltooid' ) ] )->get_status() );
	}
	public function test_target_overlap_is_blocked(): void {
		$overlap = $this->shift( 'open' );
		update_post_meta( $overlap, 'assigned_persons', [ $this->target ] );
		$this->assertSame( 'transfer_overlap', $this->preview()->get_data()['code'] );
	}

	public function test_write_failure_restores_every_changed_registration(): void {
		$completed = $this->shift( 'voltooid' );
		$key       = '_shift_signup_at_' . $this->source;
		update_post_meta( $this->shift, $key, 123 );
		update_post_meta( $completed, $key, 456 );
		$ids     = [ $this->shift, $completed ];
		$preview = $this->preview( $ids )->get_data();
		$fail    = function ( $check, $id, $meta_key ) use ( $completed ) {
			return $id === $completed && $meta_key === '_shift_signup_at_' . $this->target ? false : $check;
		};
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$response = $this->request( 'POST', '', $this->payload( $ids ) + [ 'token' => $preview['token'] ] );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->assertSame( 500, $response->get_status() );
		foreach ( $ids as $id ) {
			$this->assertSame( [ $this->source, $this->other ], get_post_meta( $id, 'assigned_persons', true ) );
			$this->assertFalse( metadata_exists( 'post', $id, '_shift_signup_at_' . $this->target ) );
			$this->assertFalse( metadata_exists( 'post', $id, '_rondo_shift_transfer_' . $preview['token'] ) );
		}
		$this->assertSame( '123', get_post_meta( $this->shift, $key, true ) );
		$this->assertSame( '456', get_post_meta( $completed, $key, true ) );
	}

	public function test_iva_and_pool_rules_are_enforced(): void {
		update_post_meta( $this->type, 'iva_required', 1 );
		$this->assertSame( 'iva_required', $this->preview()->get_data()['code'] );
		delete_post_meta( $this->type, 'iva_required' );
		update_post_meta( $this->type, 'required_pool', 123 );
		$this->assertSame( 'pool_membership_required', $this->preview()->get_data()['code'] );
	}

	public function test_empty_duplicate_and_missing_selections_are_rejected(): void {
		$this->assertSame( 400, $this->preview( [] )->get_status() );
		$this->assertSame( 400, $this->preview( [ $this->shift, $this->shift ] )->get_status() );
		$this->assertSame( 409, $this->preview( [ $this->target ] )->get_status() );
		$this->target = $this->source;
		$this->assertSame( 400, $this->preview()->get_status() );
	}
}
