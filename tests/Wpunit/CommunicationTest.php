<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\REST\Communication;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

class CommunicationTest extends RondoTestCase {
	private string $role;
	private int $user_id;

	protected function set_up(): void {
		parent::set_up();
		$this->role    = UserRoles::add_custom_role( 'Communication test role' );
		$this->user_id = $this->createRondoUser( [ 'role' => $this->role ] );
		wp_set_current_user( $this->user_id );
		$this->bootRestControllers( [ Communication::class, UserSettings::class ] );
	}

	protected function tear_down(): void {
		UserRoles::remove_custom_role( $this->role );
		parent::tear_down();
	}

	private function request( string $route, string $method = 'GET', array $body = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		if ( $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	}

	private function grant_access(): void {
		get_role( $this->role )->add_cap( 'communicatie' );
		UserRoles::sync_role_capabilities( $this->role );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
	}

	public function test_communication_requires_dedicated_capability(): void {
		$this->assertSame( 403, $this->request( '/rondo/v1/communications' )->get_status() );
		$this->assertFalse( $this->request( '/rondo/v1/user/me' )->get_data()['can_access_communicatie'] );

		$this->grant_access();

		$this->assertSame( 200, $this->request( '/rondo/v1/communications' )->get_status() );
		$this->assertTrue( $this->request( '/rondo/v1/user/me' )->get_data()['can_access_communicatie'] );
	}

	public function test_monthly_series_creates_separate_occurrences_without_duplicates(): void {
		$this->grant_access();
		$start = wp_date( 'Y-m-15' );
		$end   = wp_date( 'Y-m-15', strtotime( '+2 months' ) );
		$body  = [
			'title'        => 'Maandnieuws',
			'channel'      => 'newsletter',
			'status'       => 'concept',
			'recurrence'   => 'monthly',
			'start_date'   => $start,
			'planned_date' => $start,
			'end_date'     => $end,
		];

		$response = $this->request( '/rondo/v1/communications', 'POST', $body );
		$this->assertSame( 201, $response->get_status() );
		$this->assertNotEmpty( $response->get_data()['series_id'] );
		$list = $this->request( '/rondo/v1/communications' )->get_data()['items'];
		$this->assertCount( 3, $list );

		$again = $this->request( '/rondo/v1/communications' )->get_data()['items'];
		$this->assertCount( 3, $again );
		$this->assertCount( 3, array_unique( array_column( $again, 'planned_date' ) ) );
	}

	public function test_completion_requires_preparation_fields_and_can_be_reopened(): void {
		$this->grant_access();
		$created = $this->request(
			'/rondo/v1/communications',
			'POST',
			[
				'title'      => 'Los idee',
				'channel'    => 'website',
				'status'     => 'concept',
				'recurrence' => 'none',
			]
		);
		$this->assertSame( 201, $created->get_status() );
		$id = $created->get_data()['id'];
		$this->assertSame( 400, $this->request( "/rondo/v1/communications/{$id}/action", 'POST', [ 'action' => 'complete' ] )->get_status() );

		$updated = $this->request(
			"/rondo/v1/communications/{$id}",
			'PUT',
			[
				'title'        => 'Los idee',
				'channel'      => 'website',
				'status'       => 'ready',
				'recurrence'   => 'none',
				'description'  => 'Publiceer dit bericht.',
				'planned_date' => wp_date( 'Y-m-d' ),
				'audience'     => 'Alle leden',
				'assignee_id'  => $this->user_id,
			]
		);
		$this->assertSame( 200, $updated->get_status() );
		$completed = $this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'      => 'complete',
				'actual_date' => wp_date( 'Y-m-d' ),
			]
			);
		$this->assertSame( 'sent', $completed->get_data()['status'] );
		$reopened = $this->request( "/rondo/v1/communications/{$id}/action", 'POST', [ 'action' => 'reopen' ] );
		$this->assertSame( 'ready', $reopened->get_data()['status'] );
	}
}
