<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Core\VolunteerStatus;
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
		AccessControl::flush_visible_person_ids_cache();
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
					'id'                => $this->team_id,
					'name'              => 'JO13-1',
					'can_view_contacts' => true,
					'players'           => [
						[
							'id'            => $player,
							'name'          => 'Lange van der Spelersnaam',
							'emails'        => [ 'speler@example.org' ],
							'phones'        => [ '0612345678' ],
							'mobile_phones' => [ '06 1234 5678' ],
							'thumbnail'     => null,
							'parents'       => [
								[
									'id'            => $parent,
									'name'          => 'Ouder',
									'emails'        => [ 'ouder@example.org' ],
									'phones'        => [ '024 123 4567' ],
									'mobile_phones' => [],
								],
							],
						],
					],
				],
			],
			array_map(
				static function ( array $team ): array {
					unset( $team['staff'] );
					return $team;
				},
				$response->get_data()
			)
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

	public function test_only_coaching_roles_grant_contact_access(): void {
		foreach ( [ 'Trainer', 'Coach', 'Trainer/coach', 'Hoofdtrainer', 'Assistent-trainer', 'Assistent-trainer/coach', 'Assistent-coach', 'Leider', 'Teamleider', ' teammanager ' ] as $role ) {
			Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, $role ) ] );
			$this->assertSame( 200, $this->request()->get_status(), $role );
			$this->assertTrue( $this->request()->get_data()[0]['can_view_contacts'], $role );
		}
		foreach ( VolunteerStatus::get_player_roles() as $role ) {
			Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, $role ) ] );
			$this->assertSame( 200, $this->request()->get_status(), $role );
			$this->assertFalse( $this->request()->get_data()[0]['can_view_contacts'], $role );
		}
		foreach ( [ 'Scheidsrechter', 'Coördinator', 'Materiaalman', 'Oud-trainer', '' ] as $role ) {
			Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, $role ) ] );
			$this->assertSame( 403, $this->request()->get_status(), $role );
		}
	}

	public function test_players_receive_only_teammate_names_and_photos(): void {
		wp_set_current_user( 1 );
		Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id ) ] );
		$parent     = $this->createPerson(
			[],
			[
				'first_name' => 'Ouder',
				'email_1'    => 'ouder@example.org',
			]
			);
		$player     = $this->createPerson(
			[],
			[
				'first_name'    => 'Speler',
				'email_1'       => 'private@example.org',
				'mobile_1'      => '0612345678',
				'work_history'  => [ $this->position( $this->team_id ) ],
				'relationships' => [
					[
						'related_person'    => $parent,
						'relationship_type' => InverseRelationships::TYPE_PARENT,
					],
				],
			]
		);
		$image      = wp_upload_bits( 'teammate.gif', null, base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' ) );
		$attachment = self::factory()->attachment->create_upload_object( $image['file'], $player );
		set_post_thumbnail( $player, $attachment );
		$other_team = $this->createOrganization( [ 'post_title' => 'JO15-1' ] );
		$this->createPerson(
			[],
			[
				'first_name'   => 'Andere speler',
				'work_history' => [ $this->position( $other_team ) ],
			]
			);
		wp_set_current_user( $this->user_id );

		$request = new \WP_REST_Request( 'GET', '/rondo/v1/my-teams' );
		$request->set_param( 'can_view_contacts', true );
		$request->set_param( 'team_id', $other_team );
		$request->set_param( 'user_id', 1 );
		$response = rest_do_request( $request );
		$team     = $response->get_data()[0];
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( [ $this->team_id ], array_column( $response->get_data(), 'id' ) );
		$this->assertFalse( $team['can_view_contacts'] );
		$this->assertSame(
			[
				[
					'id'        => $this->coach_id,
					'name'      => 'Coach',
					'thumbnail' => null,
				],
				[
					'id'        => $player,
					'name'      => 'Speler',
					'thumbnail' => wp_get_attachment_image_url( $attachment, 'thumbnail' ),
				],
			],
			$team['players']
		);
		$this->assertNotEmpty( $team['players'][1]['thumbnail'] );
		$this->assertTrue( ( new UserSettings() )->get_current_user_data( $this->user_id )['has_my_teams'] );
		$this->assertFalse( \Rondo\Core\AccessControl::can_view_person( $player, $this->user_id ) );
		$this->assertFalse( \Rondo\Core\AccessControl::can_view_person( $parent, $this->user_id ) );
	}

	public function test_contact_access_is_per_team_even_for_players_shared_between_teams(): void {
		wp_set_current_user( 1 );
		$second = $this->createOrganization( [ 'post_title' => 'JO13-2' ] );
		$player = $this->createPerson(
			[],
			[
				'first_name' => 'Speler',
				'email_1'    => 'private@example.org',
			]
			);
		$roles  = [
			$this->position( $this->team_id ),
			$this->position( $this->team_id, 'Trainer' ),
			$this->position( $second ),
		];
		foreach ( [ $roles, array_reverse( $roles ) ] as $assignments ) {
			Fields::update_for_post( $this->coach_id, 'work_history', $assignments );
			foreach ( [ [ $this->team_id, $second ], [ $second, $this->team_id ] ] as $team_ids ) {
				Fields::update_for_post( $player, 'work_history', array_map( fn( int $id ): array => $this->position( $id ), $team_ids ) );
				wp_set_current_user( $this->user_id );
				$teams    = $this->request()->get_data();
				$contacts = array_column( $teams[0]['players'], null, 'id' );
				$basic    = array_column( $teams[1]['players'], null, 'id' );
				$this->assertTrue( $teams[0]['can_view_contacts'] );
				$this->assertFalse( $teams[1]['can_view_contacts'] );
				$this->assertSame( [ 'private@example.org' ], $contacts[ $player ]['emails'] );
				$this->assertSame(
					[
						'id'        => $player,
						'name'      => 'Speler',
						'thumbnail' => null,
					],
					$basic[ $player ]
					);
			}
		}

		// A player with an expired coaching role keeps only the basic roster.
		$roles[1] = $this->position( $this->team_id, 'Trainer', [ 'end_date' => '2000-01-01' ] );
		Fields::update_for_post( $this->coach_id, 'work_history', $roles );
		foreach ( $this->request()->get_data() as $team ) {
			$this->assertFalse( $team['can_view_contacts'] );
			foreach ( $team['players'] as $teammate ) {
				$this->assertSame( [ 'id', 'name', 'thumbnail' ], array_keys( $teammate ) );
			}
		}
	}

	private function link_children( array $child_ids ): void {
		Fields::update_for_post(
			$this->coach_id,
			'relationships',
			array_map(
				static fn( int $id ): array => [
					'related_person'    => $id,
					'relationship_type' => InverseRelationships::TYPE_CHILD,
				],
				$child_ids
			)
		);
		// Each HTTP request starts with a fresh household scope.
		AccessControl::flush_visible_person_ids_cache();
	}

	public function test_parent_sees_child_teams_without_contacts_or_general_teammate_access(): void {
		wp_set_current_user( 1 );
		Fields::update_for_post( $this->coach_id, 'work_history', [] );
		$second   = $this->createOrganization( [ 'post_title' => 'JO13-2' ] );
		$children = [];
		foreach ( [ $this->team_id, $second, $this->team_id ] as $team_id ) {
			$children[] = $this->createPerson(
				[],
				[
					'birthdate'    => current_datetime()->modify( '-12 years' )->format( 'Y-m-d' ),
					'work_history' => [ $this->position( $team_id ) ],
				]
			);
		}
		$player = $this->createPerson(
			[],
			[
				'first_name'   => 'Teamgenoot',
				'email_1'      => 'private@example.org',
				'mobile_1'     => '0612345678',
				'work_history' => [ $this->position( $this->team_id ), $this->position( $second ) ],
			]
		);
		$this->link_children( $children );
		wp_set_current_user( $this->user_id );
		$response = $this->request();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( [ $this->team_id, $second ], array_column( $response->get_data(), 'id' ) );
		foreach ( $response->get_data() as $team ) {
			$this->assertFalse( $team['can_view_contacts'] );
			$this->assertContains( $player, array_column( $team['players'], 'id' ) );
			foreach ( $team['players'] as $teammate ) {
				$this->assertSame( [ 'id', 'name', 'thumbnail' ], array_keys( $teammate ) );
			}
		}
		$this->assertTrue( ( new UserSettings() )->get_current_user_data( $this->user_id )['has_my_teams'] );
		$this->assertFalse( AccessControl::can_view_person( $player, $this->user_id ) );

		// Former membership does not erase parenthood, but cannot grant staff rights.
		Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, 'Trainer' ) ] );
		Fields::update_for_post( $this->coach_id, 'former_member', true );
		$this->assertSame( [ false, false ], array_column( $this->request()->get_data(), 'can_view_contacts' ) );
		$this->link_children( [] );
		$this->assertSame( 403, $this->request()->get_status() );
		$this->assertFalse( ( new UserSettings() )->get_current_user_data( $this->user_id )['has_my_teams'] );
	}

	public function test_parent_contact_rights_come_only_from_own_current_coaching_role(): void {
		wp_set_current_user( 1 );
		$second             = $this->createOrganization( [ 'post_title' => 'JO13-2' ] );
		$child_coached_team = $this->createOrganization( [ 'post_title' => 'JO13-3' ] );
		$child              = $this->createPerson(
			[],
			[
				'birthdate'    => current_datetime()->modify( '-12 years' )->format( 'Y-m-d' ),
				'email_1'      => 'child@example.org',
				'work_history' => [
					$this->position( $this->team_id ),
					$this->position( $second ),
					$this->position( $second, 'Trainer' ),
					$this->position( $child_coached_team, 'Trainer' ),
				],
			]
		);
		$this->link_children( [ $child ] );
		wp_set_current_user( $this->user_id );
		$teams = $this->request()->get_data();
		$this->assertSame( [ $this->team_id, $second ], array_column( $teams, 'id' ) );
		$this->assertSame( [ true, false ], array_column( $teams, 'can_view_contacts' ) );
		$this->assertSame( [ 'child@example.org' ], $teams[0]['players'][0]['emails'] );
		$this->assertSame( [ 'id', 'name', 'thumbnail' ], array_keys( $teams[1]['players'][0] ) );
		Fields::update_for_post( $this->coach_id, 'work_history', [] );
		$this->assertSame( [ false, false ], array_column( $this->request()->get_data(), 'can_view_contacts' ) );
	}

	public function test_parent_scope_excludes_adult_unknown_former_unpublished_and_unrelated_children(): void {
		wp_set_current_user( 1 );
		Fields::update_for_post( $this->coach_id, 'work_history', [] );
		$minor_birthdate = current_datetime()->modify( '-12 years' )->format( 'Y-m-d' );
		foreach ( [
			[ 'birthdate' => current_datetime()->modify( '-18 years' )->format( 'Y-m-d' ) ],
			[ 'birthdate' => null ],
			[ 'former_member' => true ],
		] as $fields ) {
			$child = $this->createPerson(
				[],
				$fields + [
					'birthdate'    => $minor_birthdate,
					'work_history' => [ $this->position( $this->team_id ) ],
				]
				);
			$this->link_children( [ $child ] );
			wp_set_current_user( $this->user_id );
			$this->assertSame( 403, $this->request()->get_status() );
		}
		$child = $this->createPerson(
			[ 'post_status' => 'draft' ],
			[
				'birthdate'    => $minor_birthdate,
				'work_history' => [ $this->position( $this->team_id ) ],
			]
			);
		$this->link_children( [ $child ] );
		$this->assertSame( 403, $this->request()->get_status() );
		wp_update_post(
			[
				'ID'          => $child,
				'post_status' => 'publish',
			]
			);
		Fields::update_for_post(
			$this->coach_id,
			'relationships',
			[
				[
					'related_person'    => $child,
					'relationship_type' => InverseRelationships::TYPE_PARENT,
				],
			]
			);
		AccessControl::flush_visible_person_ids_cache();
		$this->assertSame( 403, $this->request()->get_status() );
		$this->link_children( [] );
		$this->assertSame( 403, $this->request()->get_status() );
	}

	public function test_child_assignments_must_be_current_and_team_published(): void {
		wp_set_current_user( 1 );
		Fields::update_for_post( $this->coach_id, 'work_history', [] );
		$child = $this->createPerson( [], [ 'birthdate' => current_datetime()->modify( '-12 years' )->format( 'Y-m-d' ) ] );
		$this->link_children( [ $child ] );
		wp_set_current_user( $this->user_id );
		foreach ( [ [ 'is_current' => false ], [ 'end_date' => '2000-01-01' ], [ 'start_date' => '2099-01-01' ] ] as $dates ) {
			Fields::update_for_post( $child, 'work_history', [ $this->position( $this->team_id, 'Teamspeler', $dates ) ] );
			$this->assertSame( 403, $this->request()->get_status() );
		}
		Fields::update_for_post( $child, 'work_history', [ $this->position( $this->team_id ) ] );
		$this->assertSame( 200, $this->request()->get_status() );
		wp_update_post(
			[
				'ID'          => $this->team_id,
				'post_status' => 'draft',
			]
			);
		$this->assertSame( 403, $this->request()->get_status() );
	}

	public function test_staff_contacts_are_available_to_players_and_parents_only_in_the_staff_team(): void {
		wp_set_current_user( 1 );
		$second     = $this->createOrganization( [ 'post_title' => 'JO13-2' ] );
		$unrelated  = $this->createOrganization( [ 'post_title' => 'JO13-3' ] );
		$parent     = $this->createPerson( [], [ 'email_1' => 'staff-parent@example.org' ] );
		$staff      = $this->createPerson(
			[],
			[
				'first_name'    => 'Staflid',
				'email_1'       => 'staff@example.org',
				'mobile_1'      => '0612345678',
				'mobile_2'      => '+32470123456',
				'work_history'  => [
					$this->position( $this->team_id, 'Trainer' ),
					$this->position( $this->team_id, 'Materialman' ),
					$this->position( $this->team_id, 'Trainer' ),
					$this->position( $this->team_id, 'Leider', [ 'end_date' => '2000-01-01' ] ),
					$this->position( $second ),
				],
				'relationships' => [
					[
						'related_person'    => $parent,
						'relationship_type' => InverseRelationships::TYPE_PARENT,
					],
				],
			]
		);
		$image      = wp_upload_bits( 'team-staff.gif', null, base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' ) );
		$attachment = self::factory()->attachment->create_upload_object( $image['file'], $staff );
		set_post_thumbnail( $staff, $attachment );
		foreach ( [
			[ 'work_history' => [ $this->position( $unrelated, 'Trainer' ) ] ],
			[ 'work_history' => [ $this->position( $this->team_id, '' ) ] ],
			[ 'work_history' => [ $this->position( $this->team_id, 'Trainer', [ 'is_current' => false ] ) ] ],
			[ 'work_history' => [ $this->position( $this->team_id, 'Trainer', [ 'start_date' => '2099-01-01' ] ) ] ],
			[
				'former_member' => true,
				'work_history'  => [ $this->position( $this->team_id, 'Trainer' ) ],
			],
		] as $fields ) {
			$this->createPerson( [], $fields );
		}
		$this->createPerson( [ 'post_status' => 'draft' ], [ 'work_history' => [ $this->position( $this->team_id, 'Trainer' ) ] ] );
		$child = $this->createPerson(
			[],
			[
				'birthdate'    => current_datetime()->modify( '-12 years' )->format( 'Y-m-d' ),
				'work_history' => [ $this->position( $this->team_id ), $this->position( $second ) ],
			]
		);
		foreach ( [ 'player', 'parent' ] as $viewer ) {
			Fields::update_for_post( $this->coach_id, 'work_history', $viewer === 'player' ? [ $this->position( $this->team_id ), $this->position( $second ) ] : [] );
			$this->link_children( $viewer === 'parent' ? [ $child ] : [] );
			wp_set_current_user( $this->user_id );
			$teams = $this->request()->get_data();
			$this->assertSame( [ $this->team_id, $second ], array_column( $teams, 'id' ) );
			$this->assertSame(
				[
					[
						'id'            => $staff,
						'name'          => 'Staflid',
						'emails'        => [ 'staff@example.org' ],
						'phones'        => [ '0612345678', '+32470123456' ],
						'mobile_phones' => [ '0612345678', '+32470123456' ],
						'thumbnail'     => get_the_post_thumbnail_url( $staff, 'thumbnail' ),
						'roles'         => [ 'Trainer', 'Materialman' ],
					],
				],
				$teams[0]['staff']
			);
			$this->assertSame( [], $teams[1]['staff'] );
			$players = array_column( $teams[1]['players'], null, 'id' );
			$this->assertSame( [ 'id', 'name', 'thumbnail' ], array_keys( $players[ $staff ] ) );
			$this->assertFalse( AccessControl::can_view_person( $staff, $this->user_id ) );
			$this->assertSame( [ false, false ], array_column( $teams, 'can_view_contacts' ) );
		}
		Fields::update_for_post( $staff, 'work_history', [ $this->position( $second ) ] );
		$this->assertSame( [], $this->request()->get_data()[0]['staff'] );
		wp_delete_attachment( $attachment, true );
	}

	public function test_non_current_player_assignments_do_not_grant_roster_access(): void {
		foreach ( [ [ 'is_current' => false ], [ 'end_date' => '2000-01-01' ], [ 'start_date' => '2099-01-01' ] ] as $dates ) {
			Fields::update_for_post( $this->coach_id, 'work_history', [ $this->position( $this->team_id, 'Teamspeler', $dates ) ] );
			$this->assertSame( 403, $this->request()->get_status() );
			$this->assertFalse( ( new UserSettings() )->get_current_user_data( $this->user_id )['has_my_teams'] );
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
