<?php

namespace Tests\Wpunit;

use Rondo\Data\InverseRelationships;
use Rondo\Fields\Fields;
use Rondo\REST\Teams;
use Rondo\REST\UserSettings;
use Rondo\Teams\MyTeam;
use Tests\Support\RondoTestCase;

class MyTeamTest extends RondoTestCase {

	private int $user_id;
	private int $coach_id;
	private int $team_id;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->team_id  = $this->createOrganization( [ 'post_title' => 'JO13-1' ] );
		$this->coach_id = $this->createPerson(
			[],
			[
				'first_name'   => 'Coach',
				'work_history' => [ $this->position( $this->team_id, 'Trainer/coach' ) ],
			]
			);
		$this->user_id  = $this->createRondoUser();
		update_user_meta( $this->user_id, 'rondo_linked_person_id', $this->coach_id );
		$this->bootRestControllers( [ Teams::class, UserSettings::class ] );
		wp_set_current_user( $this->user_id );
	}

	private function position( int $team_id, string $role = 'Teamspeler', array $dates = [] ): array {
		return array_merge(
			[
				'team'       => $team_id,
				'job_title'  => $role,
				'is_current' => true,
			],
			$dates
			);
	}

	private function request(): \WP_REST_Response {
		return rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/my-teams' ) );
	}

	public function test_only_current_own_players_and_parent_contacts_are_returned(): void {
		wp_set_current_user( 1 );
		$parent     = $this->createPerson(
			[],
			[
				'first_name'    => 'Ouder',
				'email_1'       => 'ouder@example.org',
				'telephone_1'   => '024 123 4567',
				'former_member' => true,
			]
		);
		$sibling    = $this->createPerson(
			[],
			[
				'first_name' => 'Zus',
				'email_1'    => 'private@example.org',
			]
			);
		$trashed    = $this->createPerson( [ 'post_status' => 'trash' ], [ 'first_name' => 'Verwijderd' ] );
		$player     = $this->createPerson(
			[],
			[
				'first_name'    => 'Lange',
				'infix'         => 'van der',
				'last_name'     => 'Spelersnaam',
				'email_1'       => 'speler@example.org',
				'email_2'       => 'speler@example.org',
				'mobile_1'      => '06 1234 5678',
				'telephone_1'   => '0612345678',
				'work_history'  => [ $this->position( $this->team_id ), $this->position( $this->team_id, 'Keeper' ) ],
				'relationships' => [
					[
						'related_person'    => $parent,
						'relationship_type' => InverseRelationships::TYPE_PARENT,
					],
					[
						'related_person'    => $trashed,
						'relationship_type' => InverseRelationships::TYPE_PARENT,
					],
					[
						'related_person'    => $sibling,
						'relationship_type' => InverseRelationships::TYPE_CHILD,
					],
				],
			]
		);
		$other_team = $this->createOrganization( [ 'post_title' => 'JO15-1' ] );
		foreach ( [
			[ 'work_history' => [ $this->position( $other_team ) ] ],
			[ 'work_history' => [ $this->position( $this->team_id, 'Trainer' ) ] ],
			[
				'work_history'  => [ $this->position( $this->team_id ) ],
				'former_member' => true,
			],
			[ 'work_history' => [ $this->position( $this->team_id, 'Teamspeler', [ 'is_current' => false ] ) ] ],
			[ 'work_history' => [ $this->position( $this->team_id, 'Teamspeler', [ 'end_date' => '2000-01-01' ] ) ] ],
			[ 'work_history' => [ $this->position( $this->team_id, 'Teamspeler', [ 'start_date' => '2099-01-01' ] ) ] ],
		] as $fields ) {
			$this->createPerson( [], $fields );
		}
		$account = $this->createRondoUser();
		update_user_meta( $account, 'rondo_linked_person_id', $player );
		wp_set_current_user( $this->user_id );

		$response = $this->request();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame(
			[
				[
					'id'      => $this->team_id,
					'name'    => 'JO13-1',
					'players' => [
						[
							'id'        => $player,
							'name'      => 'Lange van der Spelersnaam',
							'emails'    => [ 'speler@example.org' ],
							'phones'    => [ '0612345678' ],
							'thumbnail' => null,
							'parents'   => [
								[
									'id'     => $parent,
									'name'   => 'Ouder',
									'emails' => [ 'ouder@example.org' ],
									'phones' => [ '024 123 4567' ],
								],
							],
						],
					],
				],
			],
			$response->get_data()
		);
		// Team contacts must not expand access to general person records.
		$this->assertFalse( \Rondo\Core\AccessControl::can_view_person( $player, $this->user_id ) );
		$this->assertFalse( \Rondo\Core\AccessControl::can_view_person( $parent, $this->user_id ) );
		$this->assertTrue( ( new UserSettings() )->get_current_user_data( $this->user_id )['has_my_teams'] );
	}

	public function test_authentication_assignment_and_read_only_contract(): void {
		$this->assertSame( 200, $this->request()->get_status() );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request()->get_status() );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $this->request()->get_status() );
		wp_set_current_user( $this->user_id );
		$this->assertSame( 404, rest_do_request( new \WP_REST_Request( 'POST', '/rondo/v1/my-teams' ) )->get_status() );
		delete_user_meta( $this->user_id, 'rondo_linked_person_id' );
		$this->assertSame( 403, $this->request()->get_status() );
	}

	public function test_player_thumbnail_is_returned_without_exposing_parent_photos(): void {
		wp_set_current_user( 1 );
		$parent     = $this->createPerson( [], [ 'first_name' => 'Ouder' ] );
		$player     = $this->createPerson(
			[],
			[
				'first_name'    => 'Speler',
				'work_history'  => [ $this->position( $this->team_id ) ],
				'relationships' => [
					[
						'related_person'    => $parent,
						'relationship_type' => InverseRelationships::TYPE_PARENT,
					],
				],
			]
		);
		$image      = wp_upload_bits( 'team-player.gif', null, base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' ) );
		$attachment = self::factory()->attachment->create_upload_object( $image['file'], $player );
		set_post_thumbnail( $player, $attachment );
		set_post_thumbnail( $parent, $attachment );
		wp_set_current_user( $this->user_id );

		$response = $this->request();
		$contact  = $response->get_data()[0]['players'][0];
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $player, $contact['id'] );
		$this->assertNotEmpty( $contact['thumbnail'] );
		$this->assertSame( wp_get_attachment_image_url( $attachment, 'thumbnail' ), $contact['thumbnail'] );
		$this->assertSame( $parent, $contact['parents'][0]['id'] );
		$this->assertArrayNotHasKey( 'thumbnail', $contact['parents'][0] );
		$this->assertFalse( \Rondo\Core\AccessControl::can_view_person( $player, $this->user_id ) );

		delete_post_thumbnail( $player );
		$this->assertNull( $this->request()->get_data()[0]['players'][0]['thumbnail'] );
	}

	public function test_only_coaching_roles_grant_access(): void {
		foreach ( [ 'Trainer', 'Coach', 'Trainer/coach', 'Hoofdtrainer', 'Assistent-trainer', 'Assistent-trainer/coach', 'Assistent-coach', 'Leider', 'Teamleider', ' teammanager ' ] as $role ) {
			Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, $role ) ] );
			$this->assertSame( 200, $this->request()->get_status(), $role );
		}
		foreach ( [ 'Teamspeler', 'Scheidsrechter', 'Coördinator', 'Materiaalman', 'Oud-trainer', '' ] as $role ) {
			Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, $role ) ] );
			$this->assertSame( 403, $this->request()->get_status(), $role );
		}
	}

	public function test_explicit_dates_override_stale_flags_and_revoke_access_immediately(): void {
		foreach ( [ [ 'end_date' => '20000101' ], [ 'start_date' => '20990101' ], [ 'end_date' => '20261399' ] ] as $dates ) {
			Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, 'Trainer', $dates ) ] );
			$this->assertSame( 403, $this->request()->get_status() );
		}
		$today = current_datetime()->format( 'Y-m-d' );
		Fields::update_for_post(
			$this->coach_id,
			'work_history',
			[
				$this->position(
				$this->team_id,
				'Trainer',
				[
					'start_date' => $today,
					'end_date'   => $today,
				]
			),
			]
			);
		$this->assertSame( 200, $this->request()->get_status() );
		Fields::update_for_post( $this->coach_id, 'work_history', [] );
		$this->assertSame( 403, $this->request()->get_status() );
	}

	public function test_multiple_teams_are_unique_and_request_parameters_cannot_expand_scope(): void {
		$second = $this->createOrganization( [ 'post_title' => 'JO13-2' ] );
		$other  = $this->createOrganization( [ 'post_title' => 'JO13-3' ] );
		Fields::update_for_post(
			$this->coach_id,
			'work_history',
			[
				$this->position( $second, 'Teammanager' ),
				$this->position( $this->team_id, 'Trainer' ),
				$this->position( $this->team_id, 'Coach' ),
			]
			);
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/my-teams' );
		$request->set_param( 'team_id', $other );
		$request->set_param( 'user_id', 1 );
		$this->assertSame( [ $this->team_id, $second ], array_column( rest_do_request( $request )->get_data(), 'id' ) );
	}

	public function test_former_or_unpublished_coaches_and_unpublished_teams_have_no_access(): void {
		Fields::update_for_post( $this->coach_id, 'former_member', true );
		$this->assertSame( 403, $this->request()->get_status() );
		Fields::update_for_post( $this->coach_id, 'former_member', false );
		Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, 'Trainer' ) ] );
		wp_update_post(
			[
				'ID'          => $this->coach_id,
				'post_status' => 'draft',
			]
			);
		$this->assertSame( 403, $this->request()->get_status() );
		wp_update_post(
			[
				'ID'          => $this->coach_id,
				'post_status' => 'publish',
			]
			);
		wp_update_post(
			[
				'ID'          => $this->team_id,
				'post_status' => 'draft',
			]
			);
		$this->assertSame( 403, $this->request()->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( [], MyTeam::teams_for_user() );
	}
}
