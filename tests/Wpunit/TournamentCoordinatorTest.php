<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Tournaments;
use Rondo\Tournaments\TournamentService;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Member access to the tournament empty state and coordinator directory. */
class TournamentCoordinatorTest extends RondoTestCase {

	public function test_member_without_assignments_can_read_directory_and_empty_list_but_not_other_entries(): void {
		$coordinator_id = $this->coordinator( 'Toernooi Contact' );
		Fields::update_many_for_post(
			$coordinator_id,
			[
				'email_1'  => 'contact@example.test',
				'mobile_1' => '0612345678',
			]
			);
		$entry_id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::ENTRY_POST_TYPE,
				'post_status' => 'publish',
			]
			);
		wp_set_current_user( $this->createRondoUser() );
		$server = $this->bootRestControllers( [ Tournaments::class ] );

		$response = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournaments/coordinators' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				[
					'id'    => $coordinator_id,
					'name'  => 'Toernooi Contact',
					'email' => 'contact@example.test',
					'phone' => '0612345678',
				],
			],
			$response->get_data()
			);
		$mine = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournament-entries/mine' ) );
		$this->assertSame( 200, $mine->get_status() );
		$this->assertSame( [], $mine->get_data() );
		$this->assertSame( 403, $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournament-entries/' . $entry_id ) )->get_status() );
		$this->assertSame( 403, $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournaments' ) )->get_status() );
	}

	public function test_anonymous_visitors_cannot_read_coordinator_contacts(): void {
		$this->coordinator( 'Private Contact' );
		wp_set_current_user( 0 );
		$server   = $this->bootRestControllers( [ Tournaments::class ] );
		$response = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournaments/coordinators' ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_directory_uses_current_roles_without_requiring_an_account_and_deduplicates_people(): void {
		$z_id = $this->coordinator( 'Zoe Contact' );
		Fields::update_many_for_post(
			$z_id,
			[
				'email_2'     => 'zoe@example.test',
				'telephone_1' => '0241234567',
			]
			);
		$a_id      = $this->coordinator( 'Anna Contact', [ 'job_title' => ' coordinator TOERNOOIEN ' ] );
		$positions = Fields::get_for_post( $a_id, 'work_history' );
		Fields::update_for_post( $a_id, 'work_history', [ $positions[0], $positions[0] ] );
		$this->coordinator(
			'Ended Contact',
			[
				'is_current' => false,
				'end_date'   => '2020-01-01',
			]
			);
		$this->coordinator( 'Future Contact', [ 'start_date' => '2099-01-01' ] );
		$this->coordinator( 'Other Coordinator', [ 'job_title' => 'Coördinator vrijwilligers' ] );
		$former_id = $this->coordinator( 'Former Contact' );
		Fields::update_for_post( $former_id, 'former_member', true );
		$draft_id = $this->coordinator( 'Draft Contact' );
		wp_update_post(
			[
				'ID'          => $draft_id,
				'post_status' => 'draft',
			]
			);

		$contacts = ( new TournamentService() )->coordinators();
		$this->assertSame( [ $a_id, $z_id ], array_column( $contacts, 'id' ) );
		$this->assertSame( '', $contacts[0]['email'] );
		$this->assertSame( '', $contacts[0]['phone'] );
		$this->assertSame( 'zoe@example.test', $contacts[1]['email'] );
		$this->assertSame( '0241234567', $contacts[1]['phone'] );
	}

	public function test_directory_can_be_empty(): void {
		$this->assertSame( [], ( new TournamentService() )->coordinators() );
	}

	private function coordinator( string $name, array $position = [] ): int {
		return $this->createPerson(
			[ 'post_title' => $name ],
			[
				'first_name'   => $name,
				'work_history' => [
					array_merge(
					[
						'job_title'  => 'Coördinator toernooien',
						'is_current' => true,
					],
					$position
			),
				],
			]
		);
	}
}
