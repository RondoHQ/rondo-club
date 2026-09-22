<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Tournaments;
use Rondo\Tournaments\TournamentAccess;
use Rondo\Tournaments\TournamentActivityLog;
use Rondo\Tournaments\TournamentPaymentService;
use Rondo\Tournaments\TournamentService;
use Tests\Support\RondoTestCase;
use Tests\Support\TournamentPaymentMollieStub;
use WP_REST_Request;

/** End-to-end coverage for tournament creation, assignment and positive registration. */
class TournamentWorkflowTest extends RondoTestCase {

	private TournamentService $service;

	protected function set_up(): void {
		parent::set_up();
		$this->service = new TournamentService(
			new TournamentPaymentService(
				new TournamentPaymentMollieStub(),
				static fn(): array => [
					'id'              => 'toernooien',
					'internal_name'   => 'Toernooien',
					'account_holder'  => 'AWC',
					'iban'            => 'NL00TEST0000000000',
					'linked_provider' => 'mollie',
				]
			)
		);
	}

	public function test_private_post_types_are_registered(): void {
		$tournament = get_post_type_object( TournamentService::TOURNAMENT_POST_TYPE );
		$entry      = get_post_type_object( TournamentService::ENTRY_POST_TYPE );

		$this->assertNotNull( $tournament );
		$this->assertFalse( $tournament->public );
		$this->assertFalse( $tournament->show_in_rest );
		$this->assertNotNull( $entry );
		$this->assertFalse( $entry->public );
		$this->assertFalse( $entry->show_in_rest );
	}

	public function test_exact_current_coordinator_role_and_admin_can_manage(): void {
		$manager_id = $this->createRondoUser();
		$this->link_user( $manager_id, [ $this->position( 0, '', 'Coördinator toernooien' ) ] );
		$this->assertTrue( TournamentAccess::can_manage( $manager_id ) );

		$expired_id = $this->createRondoUser();
		$this->link_user(
			$expired_id,
			[
				[
					'job_title'  => 'Coördinator toernooien',
					'is_current' => false,
					'start_date' => '2020-01-01',
					'end_date'   => '2021-01-01',
				],
			]
		);
		$this->assertFalse( TournamentAccess::can_manage( $expired_id ) );
		$this->assertTrue( TournamentAccess::can_manage( self::factory()->user->create( [ 'role' => 'administrator' ] ) ) );
	}

	public function test_assignment_options_include_current_team_kader_with_and_without_accounts(): void {
		$team_id  = $this->createOrganization( [ 'post_title' => 'AWC O15-1' ] );
		$kader_id = $this->createRondoUser( [ 'display_name' => 'Actuele trainer' ] );
		$this->link_user( $kader_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );

		$player_id = $this->createRondoUser( [ 'display_name' => 'Speler met account' ] );
		$this->link_user( $player_id, [ $this->position( $team_id, 'team', 'Speler' ) ] );

		$person_without_account = $this->createPerson( [ 'post_title' => 'Leider zonder account' ] );
		Fields::update_for_post( $person_without_account, 'work_history', [ $this->position( $team_id, 'team', 'Leider' ) ] );

		$options = $this->service->assignment_options();
		$team    = current( array_filter( $options, static fn( array $row ): bool => $row['id'] === $team_id ) );

		$this->assertIsArray( $team );
		$this->assertSame( 'O15', $team['age_group'] );
		$this->assertSame( [ $kader_id, 0 ], array_column( $team['assignees'], 'user_id' ) );
		$this->assertSame( $person_without_account, $team['assignees'][1]['person_id'] );
	}

	public function test_assignment_options_count_current_players_without_requiring_accounts(): void {
		$team_id  = $this->createOrganization( [ 'post_title' => 'AWC O15-1' ] );
		$empty_id = $this->createOrganization( [ 'post_title' => 'AWC JO16-2' ] );
		$kader_id = $this->createRondoUser();
		$this->link_user( $kader_id, [ $this->position( $empty_id, 'team', 'Trainer' ) ] );

		$player_id = $this->createPerson();
		Fields::update_for_post( $player_id, 'work_history', [ $this->position( $team_id, 'team', 'Teamspeler' ) ] );
		$former_id = $this->createPerson();
		Fields::update_for_post( $former_id, 'former_member', true );
		Fields::update_for_post( $former_id, 'work_history', [ $this->position( $empty_id, 'team', 'Teamspeler' ) ] );
		$expired_id            = $this->createPerson();
		$expired               = $this->position( $empty_id, 'team', 'Keeper' );
		$expired['end_date']   = '2021-01-01';
		$expired['is_current'] = false;
		Fields::update_for_post( $expired_id, 'work_history', [ $expired ] );

		$options = array_column( $this->service->assignment_options(), null, 'id' );
		$this->assertSame( 1, $options[ $team_id ]['player_count'] );
		$this->assertSame( 0, $options[ $empty_id ]['player_count'] );
		$this->assertSame( [ $kader_id ], array_column( $options[ $empty_id ]['assignees'], 'user_id' ) );

		Fields::update_for_post( $player_id, 'work_history', [ $this->position( $empty_id, 'team', 'Keeper' ) ] );
		$options = array_column( $this->service->assignment_options(), null, 'id' );
		$this->assertSame( 0, $options[ $team_id ]['player_count'] );
		$this->assertSame( 1, $options[ $empty_id ]['player_count'] );
	}

	public function test_shared_entry_supports_multiple_tournament_teams_and_one_contact(): void {
		$admin_id        = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id         = $this->createOrganization( [ 'post_title' => 'AWC O15-1' ] );
		$first_id        = $this->createRondoUser(
			[
				'display_name' => 'Eerste trainer',
				'user_email'   => 'eerste@example.test',
			]
			);
		$second_id       = $this->createRondoUser(
			[
				'display_name' => 'Tweede trainer',
				'user_email'   => 'tweede@example.test',
			]
			);
		$first_person_id = $this->link_user( $first_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$this->link_user( $second_id, [ $this->position( $team_id, 'team', 'Leider' ) ] );
		$tournament = $this->create_tournament( $admin_id );

		$published = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $first_id, $second_id ],
				],
			],
			$admin_id
		);
		$this->assertIsArray( $published );
		$this->assertCount( 1, $published['entries'] );
		$entry = $published['entries'][0];
		$this->assertSame( [ $first_id, $second_id ], $entry['assigned_user_ids'] );
		$this->assertTrue( TournamentAccess::is_assigned( $entry['id'], $first_id ) );
		$this->assertTrue( TournamentAccess::is_assigned( $entry['id'], $second_id ) );

		$draft = $this->service->save_draft(
			$entry['id'],
			[
				'version'           => 1,
				'contact_person_id' => $first_person_id,
				'team_entries'      => [ [ 'player_count' => 6 ], [ 'player_count' => 7 ] ],
			],
			$first_id
		);
		$this->assertIsArray( $draft );
		$this->assertSame( 2, $draft['version'] );

		$conflict = $this->service->save_draft(
			$entry['id'],
			[
				'version'      => 1,
				'team_entries' => [],
			],
			$second_id
			);
		$this->assertWPError( $conflict );
		$this->assertSame( 'rondo_tournament_entry_conflict', $conflict->get_error_code() );

		$submitted = $this->service->submit_entry( $entry['id'], [ 'version' => 2 ], $second_id );
		$this->assertIsArray( $submitted );
		$this->assertSame( 'submitted', $submitted['registration_status'] );
		$this->assertSame( 2, $submitted['registered_team_count'] );
		$this->assertSame( 13, $submitted['player_count'] );
		$this->assertSame( 96.0, $submitted['total_amount'] );
		$this->assertSame( $first_person_id, $submitted['contact_person_id'] );
		$this->assertSame( 'Eerste trainer', $submitted['contact_name'] );
		$this->assertSame( $first_person_id, (int) Fields::get_for_post( (int) $submitted['invoice_id'], 'person' ) );
	}

	public function test_contact_must_be_an_assigned_rondo_person(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id  = $this->createOrganization( [ 'post_title' => 'AWC O14-1' ] );
		$kader_id = $this->createRondoUser( [ 'user_email' => 'kader@example.test' ] );
		$this->link_user( $kader_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$tournament = $this->create_tournament( $admin_id );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $kader_id ],
				],
			],
			$admin_id
		);

		$result = $this->service->save_draft(
			$published['entries'][0]['id'],
			[
				'version'           => 1,
				'contact_person_id' => $this->createPerson( [ 'post_title' => 'Niet toegewezen' ] ),
				'team_entries'      => [ [ 'player_count' => 10 ] ],
			],
			$kader_id
		);

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_tournament_contact_invalid', $result->get_error_code() );
	}

	public function test_only_assigned_users_can_read_or_write_entry_routes(): void {
		$server      = $this->bootRestControllers( [ Tournaments::class ] );
		$admin_id    = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id     = $this->createOrganization( [ 'post_title' => 'AWC O12-2' ] );
		$assigned_id = $this->createRondoUser(
			[
				'display_name' => 'Teamleider',
				'user_email'   => 'leider@example.test',
			]
			);
		$this->link_user( $assigned_id, [ $this->position( $team_id, 'team', 'Leider' ) ] );
		$tournament = $this->create_tournament( $admin_id );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $assigned_id ],
				],
			],
			$admin_id
			);
		$entry_id   = $published['entries'][0]['id'];

		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournament-entries/' . $entry_id ) )->get_status() );

		wp_set_current_user( $assigned_id );
		$response = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournament-entries/' . $entry_id ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'AWC O12-2', $response->get_data()['team_name'] );
	}

	public function test_manager_can_reassign_open_entry_and_invites_only_new_staff(): void {
		$admin_id         = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id          = $this->createOrganization( [ 'post_title' => 'AWC O12-3' ] );
		$first_id         = $this->createRondoUser(
			[
				'display_name' => 'Eerste leider',
				'user_email'   => 'eerste-leider@example.test',
			]
			);
		$second_id        = $this->createRondoUser(
			[
				'display_name' => 'Nieuwe leider',
				'user_email'   => 'nieuwe-leider@example.test',
			]
			);
		$outsider_id      = $this->createRondoUser(
			[
				'display_name' => 'Buitenstaander',
				'user_email'   => 'buiten@example.test',
			]
			);
		$first_person_id  = $this->link_user( $first_id, [ $this->position( $team_id, 'team', 'Leider' ) ] );
		$second_person_id = $this->link_user( $second_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$tournament       = $this->create_tournament( $admin_id );
		$mails            = [];
		$mail_filter      = static function ( $return, array $atts ) use ( &$mails ) {
			$mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $mail_filter, 10, 2 );
		$published = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $first_id ],
				],
			],
			$admin_id
		);
		$entry_id  = $published['entries'][0]['id'];
		$draft     = $this->service->save_draft(
			$entry_id,
			[
				'version'           => 1,
				'contact_person_id' => $first_person_id,
				'team_entries'      => [ [ 'player_count' => 12 ] ],
			],
			$first_id
		);
		$updated   = $this->service->update_entry_assignees( $entry_id, [ $second_id ], (int) $draft['version'], $admin_id );
		remove_filter( 'pre_wp_mail', $mail_filter, 10 );

		$this->assertIsArray( $updated );
		$this->assertSame( [ $second_id ], $updated['assigned_user_ids'] );
		$this->assertSame( 3, $updated['version'] );
		$this->assertSame( 0, $updated['contact_person_id'] );
		$this->assertSame( '', $updated['contact_name'] );
		$this->assertSame(
			[
				'added_count'          => 1,
				'removed_count'        => 1,
				'snapshot_refreshed'   => true,
				'email_sent_count'     => 1,
				'email_existing_count' => 0,
				'email_failed_count'   => 0,
			],
			$updated['assignment_update']
		);
		$this->assertCount( 2, $mails );
		$this->assertStringContainsString( 'nieuwe-leider@example.test', wp_json_encode( $mails[1]['to'] ) );
		$this->assertFalse( TournamentAccess::is_assigned( $entry_id, $first_id ) );
		$this->assertTrue( TournamentAccess::is_assigned( $entry_id, $second_id ) );
		$this->assertSame( [], $this->service->entries_for_user( $first_id ) );
		$this->assertSame( $entry_id, $this->service->entries_for_user( $second_id )[0]['id'] );
		$this->assertSame( '', (string) get_post_meta( $entry_id, '_tournament_assigned_user_' . $first_id, true ) );
		$this->assertSame( '1', (string) get_post_meta( $entry_id, '_tournament_assigned_user_' . $second_id, true ) );
		$this->assertContains( 'entry_assignments_updated', array_column( TournamentActivityLog::recent( $tournament['id'] ), 'action' ) );

		Fields::update_for_post( $second_person_id, 'work_history', [ $this->position( $team_id, 'team', 'Hoofdtrainer' ) ] );
		$synced = $this->service->update_entry_assignees( $entry_id, [ $second_id ], 3, $admin_id );
		$this->assertIsArray( $synced );
		$this->assertSame( 4, $synced['version'] );
		$this->assertSame( 'Hoofdtrainer', $synced['assignees'][0]['role'] );
		$this->assertTrue( $synced['assignment_update']['snapshot_refreshed'] );
		$this->assertSame( 0, $synced['assignment_update']['email_sent_count'] );

		$invalid = $this->service->update_entry_assignees( $entry_id, [ $outsider_id ], 4, $admin_id );
		$this->assertWPError( $invalid );
		$this->assertSame( 'rondo_tournament_assignees_invalid', $invalid->get_error_code() );
		$this->assertSame( [ $second_id ], $this->service->format_entry( $entry_id )['assigned_user_ids'] );

		$conflict = $this->service->update_entry_assignees( $entry_id, [ $first_id ], 3, $admin_id );
		$this->assertWPError( $conflict );
		$this->assertSame( 'rondo_tournament_assignment_conflict', $conflict->get_error_code() );

		$readded_mails  = [];
		$readded_filter = static function ( $return, array $atts ) use ( &$readded_mails ) {
			$readded_mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $readded_filter, 10, 2 );
		$readded = $this->service->update_entry_assignees( $entry_id, [ $first_id ], 4, $admin_id );
		remove_filter( 'pre_wp_mail', $readded_filter, 10 );
		$this->assertIsArray( $readded );
		$this->assertSame( 0, $readded['assignment_update']['email_sent_count'] );
		$this->assertSame( 1, $readded['assignment_update']['email_existing_count'] );
		$this->assertSame( [], $readded_mails );
	}

	public function test_reassignment_preserves_submitted_contact_and_route_requires_manager(): void {
		$server          = $this->bootRestControllers( [ Tournaments::class ] );
		$admin_id        = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id         = $this->createOrganization( [ 'post_title' => 'AWC O14-2' ] );
		$first_id        = $this->createRondoUser(
			[
				'display_name' => 'Eerste trainer',
				'user_email'   => 'eerste-trainer@example.test',
			]
			);
		$second_id       = $this->createRondoUser(
			[
				'display_name' => 'Tweede trainer',
				'user_email'   => 'tweede-trainer@example.test',
			]
			);
		$first_person_id = $this->link_user( $first_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$this->link_user( $second_id, [ $this->position( $team_id, 'team', 'Leider' ) ] );
		$tournament = $this->create_tournament( $admin_id );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $first_id ],
				],
			],
			$admin_id
		);
		$entry_id   = $published['entries'][0]['id'];
		$draft      = $this->service->save_draft(
			$entry_id,
			[
				'version'           => 1,
				'contact_person_id' => $first_person_id,
				'team_entries'      => [ [ 'player_count' => 10 ] ],
			],
			$first_id
		);
		$submitted  = $this->service->submit_entry( $entry_id, [ 'version' => $draft['version'] ], $first_id );

		wp_set_current_user( $second_id );
		$forbidden = new WP_REST_Request( 'PATCH', '/rondo/v1/tournament-entries/' . $entry_id . '/assignees' );
		$forbidden->set_header( 'Content-Type', 'application/json' );
		$forbidden->set_body(
			wp_json_encode(
			[
				'user_ids' => [ $second_id ],
				'version'  => $submitted['version'],
			]
			)
			);
		$this->assertSame( 403, $server->dispatch( $forbidden )->get_status() );

		wp_set_current_user( $admin_id );
		$request = new WP_REST_Request( 'PATCH', '/rondo/v1/tournament-entries/' . $entry_id . '/assignees' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
			[
				'user_ids' => [ $second_id ],
				'version'  => $submitted['version'],
			]
			)
			);
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ $second_id ], $response->get_data()['assigned_user_ids'] );
		$this->assertSame( $first_person_id, $response->get_data()['contact_person_id'] );
		$this->assertSame( 'Eerste trainer', $response->get_data()['contact_name'] );

		Fields::update_for_post( $tournament['id'], 'lifecycle_status', 'archived' );
		$readonly = $this->service->update_entry_assignees( $entry_id, [ $first_id ], (int) $response->get_data()['version'], $admin_id );
		$this->assertWPError( $readonly );
		$this->assertSame( 'rondo_tournament_assignment_readonly', $readonly->get_error_code() );
	}

	public function test_submission_cannot_record_no_participation(): void {
		$admin_id          = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id           = $this->createOrganization( [ 'post_title' => 'AWC O10-1' ] );
		$kader_id          = $this->createRondoUser( [ 'user_email' => 'trainer@example.test' ] );
		$contact_person_id = $this->link_user( $kader_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$tournament        = $this->create_tournament( $admin_id );
		$published         = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $kader_id ],
				],
			],
			$admin_id
			);

		$result = $this->service->submit_entry(
			$published['entries'][0]['id'],
			[
				'version'           => 1,
				'contact_person_id' => $contact_person_id,
				'team_entries'      => [],
			],
			$kader_id
		);

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_tournament_team_required', $result->get_error_code() );
	}

	public function test_manager_can_extend_internal_deadline_but_not_past_external_deadline(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id  = $this->createOrganization( [ 'post_title' => 'AWC O11-1' ] );
		$kader_id = $this->createRondoUser( [ 'user_email' => 'kader@example.test' ] );
		$this->link_user( $kader_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$tournament = $this->create_tournament( $admin_id );
		$this->assertIsArray(
			$this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $kader_id ],
				],
			],
			$admin_id
			)
			);

		$extended = $this->service->extend_deadline( $tournament['id'], current_datetime()->modify( '+6 days' )->format( DATE_RFC3339 ) );
		$this->assertIsArray( $extended );
		$this->assertStringStartsWith( current_datetime()->modify( '+6 days' )->format( 'Y-m-d' ), $extended['internal_deadline'] );

		$invalid = $this->service->extend_deadline( $tournament['id'], current_datetime()->modify( '+9 days' )->format( DATE_RFC3339 ) );
		$this->assertWPError( $invalid );
		$this->assertSame( 'rondo_tournament_deadline_invalid', $invalid->get_error_code() );
	}

	public function test_manager_can_delete_published_tournament_and_linked_entries(): void {
		$server            = $this->bootRestControllers( [ Tournaments::class ] );
		$admin_id          = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id           = $this->createOrganization( [ 'post_title' => 'AWC O13-1' ] );
		$assigned_id       = $this->createRondoUser( [ 'user_email' => 'toernooi@example.test' ] );
		$contact_person_id = $this->link_user( $assigned_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$tournament        = $this->create_tournament( $admin_id );
		$published         = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $assigned_id ],
				],
			],
			$admin_id
		);
		$entry_id          = $published['entries'][0]['id'];
		$submitted         = $this->service->submit_entry(
			$entry_id,
			[
				'version'           => 1,
				'contact_person_id' => $contact_person_id,
				'team_entries'      => [ [ 'player_count' => 12 ] ],
			],
			$assigned_id
		);
		$this->assertSame( 'submitted', $submitted['registration_status'] );

		wp_set_current_user( $assigned_id );
		$this->assertSame( 403, $server->dispatch( new WP_REST_Request( 'DELETE', '/rondo/v1/tournaments/' . $tournament['id'] ) )->get_status() );

		wp_set_current_user( $admin_id );
		$response = $server->dispatch( new WP_REST_Request( 'DELETE', '/rondo/v1/tournaments/' . $tournament['id'] ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			[
				'deleted'     => true,
				'id'          => $tournament['id'],
				'entry_count' => 1,
			],
			$response->get_data()
		);
		$this->assertSame( 'trash', get_post_status( $tournament['id'] ) );
		$this->assertSame( 'trash', get_post_status( $entry_id ) );
		$this->assertSame( [], $this->service->format_tournament( $tournament['id'], true ) );
		$this->assertSame( [], $this->service->format_entry( $entry_id ) );
		$this->assertSame( [], $this->service->entries_for_user( $assigned_id ) );
	}

	public function test_date_only_values_are_stored_with_day_boundaries(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$result   = $this->service->save_tournament(
			[
				'name'              => 'Datumtoernooi',
				'internal_deadline' => '2030-05-10',
				'external_deadline' => '2030-05-12',
				'schedule'          => [
					[
						'age_group'      => 'O8',
						'start_datetime' => '2030-05-20',
						'location'       => 'Sportpark',
					],
				],
				'pricing_rules'     => [
					[
						'min_age'     => 8,
						'max_age'     => 8,
						'amount'      => 35,
						'game_format' => '6 tegen 6',
					],
				],
			],
			$admin_id
		);

		$this->assertIsArray( $result );
		$this->assertSame( '2030-05-10 23:59:59', $result['internal_deadline'] );
		$this->assertSame( '2030-05-10 23:59:59', $result['payment_deadline'] );
		$this->assertSame( [ 7, 2 ], $result['payment_reminder_days'] );
		$this->assertSame( '2030-05-12 23:59:59', $result['external_deadline'] );
		$this->assertSame(
			\DateTimeImmutable::createFromFormat( '!Y-m-d', '2030-05-20', wp_timezone() )->format( DATE_RFC3339 ),
			$result['schedule'][0]['start_datetime']
		);
	}

	public function test_publication_is_blocked_without_tournament_payment_account(): void {
		$admin_id   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$tournament = $this->create_tournament( $admin_id );
		$service    = new TournamentService(
			new TournamentPaymentService(
				new TournamentPaymentMollieStub(),
				static fn() => new \WP_Error( 'missing_account', 'Geen rekening.' )
			)
		);

		$result = $service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => 123,
					'user_ids' => [ 456 ],
				],
			],
			$admin_id
			);
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_tournament_payment_account_required', $result->get_error_code() );
		$this->assertSame( 'draft', Fields::get_for_post( $tournament['id'], 'lifecycle_status' ) );
	}

	public function test_incomplete_shared_draft_can_be_saved(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id  = $this->createOrganization( [ 'post_title' => 'AWC O9-1' ] );
		$kader_id = $this->createRondoUser( [ 'user_email' => 'concept@example.test' ] );
		$this->link_user( $kader_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
		$tournament = $this->create_tournament( $admin_id );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team_id,
					'user_ids' => [ $kader_id ],
				],
			],
			$admin_id
			);

		$draft = $this->service->save_draft(
			$published['entries'][0]['id'],
			[
				'version'           => 1,
				'contact_person_id' => null,
				'team_entries'      => [ [ 'player_count' => '' ] ],
			],
			$kader_id
		);

		$this->assertIsArray( $draft );
		$this->assertSame( 0, $draft['draft_team_entries'][0]['player_count'] );
		$this->assertSame( 'open', $draft['registration_status'] );
	}

	public function test_invites_people_without_accounts_and_later_grants_only_the_linked_person_access(): void {
		$server     = $this->bootRestControllers( [ Tournaments::class ] );
		$admin      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team       = $this->createOrganization( [ 'post_title' => 'AWC O10-4' ] );
		$person     = $this->pending_staff( $team, 'A trainer zonder account', 'gedeeld@example.test' );
		$other      = $this->pending_staff( $team, 'B andere trainer', 'gedeeld@example.test' );
		$tournament = $this->create_tournament( $admin );
		$mails      = [];
		$filter     = static function ( $return, $atts ) use ( &$mails ) {
			$mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		try {
			$published = $this->service->publish(
				$tournament['id'],
				[
					[
						'team_id'    => $team,
						'person_ids' => [ $person, $other ],
					],
				],
				$admin
				);
			$this->assertIsArray( $published );
			$entry = $published['entries'][0];
			$this->assertCount( 2, $mails );
			$this->assertStringContainsString( 'Je hebt nog geen Rondo-account', $mails[0]['message'] );
			$this->assertStringContainsString( home_url( '/activeren/' ), $mails[0]['message'] );
			$this->assertStringContainsString( '/mijn-toernooien/' . $entry['id'], $mails[0]['message'] );
			$this->assertSame( [], $entry['assigned_user_ids'] );
			$this->assertFalse( metadata_exists( 'post', $entry['id'], '_tournament_assigned_user_0' ) );
			$this->assertSame( '2', get_post_meta( $entry['id'], 'assignment_snapshot', true ) );
			$this->assertSame( $person, (int) get_post_meta( $entry['id'], 'assignment_snapshot_0_person_id', true ) );
			$this->assertSame( '1', get_post_meta( $entry['id'], '_tournament_assigned_person_' . $person, true ) );

			// Sharing the inbox never gives an unrelated account tournament access.
			$unrelated = $this->createRondoUser( [ 'user_email' => 'gedeeld@example.test' ] );
			$this->link_user( $unrelated, [] );
			wp_set_current_user( $unrelated );
			$this->assertFalse( TournamentAccess::has_assignments( $unrelated ) );
			$this->assertSame( 403, $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournament-entries/' . $entry['id'] ) )->get_status() );

			$account = $this->createRondoUser( [ 'user_email' => 'eigen-account@example.test' ] );
			update_user_meta( $account, 'rondo_linked_person_id', $person );
			wp_set_current_user( $account );
			$this->assertTrue( TournamentAccess::has_assignments( $account ) );
			$this->assertTrue( TournamentAccess::is_assigned( $entry['id'], $account ) );
			$this->assertSame( $entry['id'], $this->service->entries_for_user( $account )[0]['id'] );
			$response = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournament-entries/' . $entry['id'] ) );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( $person, $response->get_data()['contact_candidates'][0]['person_id'] );
			$this->assertTrue( $response->get_data()['contact_candidates'][0]['is_current_user'] );
			$this->assertTrue( $response->get_data()['assignees'][0]['invitation_sent'] );
			$unchanged = $this->service->update_entry_people( $entry['id'], [ $person, $other ], 1, $admin );
			$this->assertSame( 0, $unchanged['assignment_update']['email_sent_count'] );
			$this->assertCount( 2, $mails );
			// Relinking the same account cannot retain access via an old account index.
			update_user_meta( $account, 'rondo_linked_person_id', $this->createPerson() );
			$this->assertFalse( TournamentAccess::is_assigned( $entry['id'], $account ) );
			$this->assertFalse( TournamentAccess::has_assignments( $account ) );
		} finally {
			remove_filter( 'pre_wp_mail', $filter, 10 );
		}
	}

	public function test_additional_teams_preserve_submitted_entries_and_are_safe_to_retry(): void {
		$admin      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team       = $this->createOrganization( [ 'post_title' => 'AWC O10-1' ] );
		$user       = $this->createRondoUser();
		$person     = $this->link_user( $user, [ $this->position( $team, 'team', 'Trainer' ) ] );
		$tournament = $this->create_tournament( $admin );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team,
					'user_ids' => [ $user ],
				],
			],
			$admin
			);
		$entry      = $published['entries'][0];
		$submitted  = $this->service->submit_entry(
			$entry['id'],
			[
				'version'           => 1,
				'contact_person_id' => $person,
				'team_entries'      => [ [ 'player_count' => 10 ] ],
			],
			$user
			);
		$this->assertIsArray( $submitted );
		$before     = Fields::all_for_post( $entry['id'] );
		$extra_team = $this->createOrganization( [ 'post_title' => 'AWC O10-4' ] );
		$pending    = $this->pending_staff( $extra_team, 'Nieuwe leider', 'leider@example.test' );
		$selection  = [
			[
				'team_id'    => $extra_team,
				'person_ids' => [ $pending ],
			],
		];
		$mails      = [];
		$filter     = static function ( $return, $atts ) use ( &$mails ) {
			$mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		try {
			$added = $this->service->invite_teams( $tournament['id'], $selection, $admin );
			$this->assertIsArray( $added );
			$this->assertEqualsCanonicalizing( [ $team, $extra_team ], $added['tournament']['target_team_ids'] );
			$this->assertCount( 2, $this->service->entries_for_tournament( $tournament['id'] ) );
			$this->assertSame( $before, Fields::all_for_post( $entry['id'] ) );
			$again = $this->service->invite_teams( $tournament['id'], $selection, $admin );
			$this->assertSame( $added['entries'][0]['id'], $again['entries'][0]['id'] );
			$this->assertCount( 1, $mails );
			$this->assertTrue( $again['emails'][ $added['entries'][0]['id'] ][0]['existing'] );
			$this->assertSame( $before, Fields::all_for_post( $entry['id'] ) );
		} finally {
			remove_filter( 'pre_wp_mail', $filter, 10 ); }
	}

	public function test_extra_invitation_route_is_manager_only_and_rejects_closed_or_invalid_batches(): void {
		$server     = $this->bootRestControllers( [ Tournaments::class ] );
		$admin      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$tournament = $this->create_tournament( $admin );
		Fields::update_for_post( $tournament['id'], 'lifecycle_status', 'open' );
		$team      = $this->createOrganization( [ 'post_title' => 'AWC O10-4' ] );
		$person    = $this->pending_staff( $team, 'Leider', 'leider@example.test' );
		$selection = [
			[
				'team_id'    => $team,
				'person_ids' => [ $person ],
			],
		];
		$request   = new WP_REST_Request( 'POST', '/rondo/v1/tournaments/' . $tournament['id'] . '/invite' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'assignments' => $selection ] ) );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		wp_set_current_user( $admin );
		$invalid = $this->service->invite_teams(
			$tournament['id'],
			array_merge(
			$selection,
			[
				[
					'team_id'    => $team,
					'person_ids' => [ $this->createPerson() ],
				],
			]
			),
			$admin
			);
		$this->assertWPError( $invalid );
		$this->assertSame( [], $this->service->entries_for_tournament( $tournament['id'] ) );
		foreach ( [ 'draft', 'closed', 'archived' ] as $status ) {
			Fields::update_for_post( $tournament['id'], 'lifecycle_status', $status );
			$this->assertSame( 409, $server->dispatch( $request )->get_status() );
		}
		Fields::update_for_post( $tournament['id'], 'lifecycle_status', 'open' );
		Fields::update_for_post( $tournament['id'], 'internal_deadline', '2020-01-01 23:59:59' );
		$this->assertSame( 409, $server->dispatch( $request )->get_status() );
		Fields::update_for_post( $tournament['id'], 'internal_deadline', current_datetime()->modify( '+5 days' )->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
		$other = $this->pending_staff( $team, 'Andere leider', 'andere@example.test' );
		$this->assertWPError(
			$this->service->invite_teams(
			$tournament['id'],
			[
				[
					'team_id'    => $team,
					'person_ids' => [ $other ],
				],
			],
			$admin
			)
			);
	}

	public function test_pending_assignment_can_be_added_and_revoked_before_account_creation(): void {
		$server     = $this->bootRestControllers( [ Tournaments::class ] );
		$admin      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team       = $this->createOrganization( [ 'post_title' => 'AWC O10-1' ] );
		$user       = $this->createRondoUser();
		$person     = $this->link_user( $user, [ $this->position( $team, 'team', 'Trainer' ) ] );
		$pending    = $this->pending_staff( $team, 'Later account', 'later@example.test' );
		$tournament = $this->create_tournament( $admin );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'  => $team,
					'user_ids' => [ $user ],
				],
			],
			$admin
			);
		$entry      = $published['entries'][0];
		wp_set_current_user( $admin );
		$request = new WP_REST_Request( 'PATCH', '/rondo/v1/tournament-entries/' . $entry['id'] . '/assignees' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
			[
				'version'    => 1,
				'person_ids' => [ $person, $pending ],
			]
			)
			);
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_data()['assignment_update']['email_sent_count'] );
		$removed = $this->service->update_entry_people( $entry['id'], [ $person ], $response->get_data()['version'], $admin );
		$this->assertSame( [ $person ], $removed['assigned_person_ids'] );
		$this->assertFalse( metadata_exists( 'post', $entry['id'], '_tournament_assigned_person_' . $pending ) );
		$this->assertFalse( metadata_exists( 'post', $entry['id'], 'assignment_snapshot_1_person_id' ) );
		$later = $this->createRondoUser();
		update_user_meta( $later, 'rondo_linked_person_id', $pending );
		$this->assertFalse( TournamentAccess::is_assigned( $entry['id'], $later ) );
		$this->assertSame( [], $this->service->entries_for_user( $later ) );
	}

	public function test_failed_pending_invitation_can_be_retried_without_resending_successful_mail(): void {
		$admin      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team       = $this->createOrganization( [ 'post_title' => 'AWC O10-1' ] );
		$first      = $this->pending_staff( $team, 'Eerste', 'eerste@example.test' );
		$second     = $this->pending_staff( $team, 'Tweede', 'tweede@example.test' );
		$tournament = $this->create_tournament( $admin );
		$fail       = static fn( $return, $atts ) => ! in_array( 'tweede@example.test', (array) $atts['to'], true );
		add_filter( 'pre_wp_mail', $fail, 10, 2 );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'    => $team,
					'person_ids' => [ $first, $second ],
				],
			],
			$admin
			);
		remove_filter( 'pre_wp_mail', $fail, 10 );
		$entry_id = $published['entries'][0]['id'];
		$this->assertTrue( $published['emails'][ $entry_id ][0]['sent'] );
		$this->assertFalse( $published['emails'][ $entry_id ][1]['sent'] );
		$mails  = [];
		$filter = static function ( $return, $atts ) use ( &$mails ) {
			$mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		try {
			$retried = $this->service->update_entry_people( $entry_id, [ $first, $second ], 1, $admin );
			$this->assertSame( 1, $retried['assignment_update']['email_sent_count'] );
			$this->assertCount( 1, $mails );
			$this->assertContains( 'tweede@example.test', (array) $mails[0]['to'] );
			$this->assertTrue( $retried['assignees'][1]['invitation_sent'] );
			$again = $this->service->update_entry_people( $entry_id, [ $first, $second ], $retried['version'], $admin );
			$this->assertSame( 0, $again['assignment_update']['email_sent_count'] );
			$this->assertCount( 1, $mails );
		} finally {
			remove_filter( 'pre_wp_mail', $filter, 10 ); }
	}

	public function test_legacy_invitation_receipts_are_respected_and_pending_payment_receipts_are_distinct(): void {
		$admin      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team       = $this->createOrganization( [ 'post_title' => 'AWC O10-1' ] );
		$user       = $this->createRondoUser();
		$person     = $this->link_user( $user, [ $this->position( $team, 'team', 'Trainer' ) ] );
		$first      = $this->pending_staff( $team, 'Pending 1', 'pending1@example.test' );
		$second     = $this->pending_staff( $team, 'Pending 2', 'pending2@example.test' );
		$tournament = $this->create_tournament( $admin );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'    => $team,
					'person_ids' => [ $person, $first, $second ],
				],
			],
			$admin
			);
		$entry_id   = $published['entries'][0]['id'];
		delete_post_meta( $entry_id, '_tournament_assignment_email_sent_person_' . $person );
		update_post_meta( $entry_id, '_tournament_assignment_email_sent_' . $user, current_time( 'mysql' ) );
		$unchanged = $this->service->update_entry_people( $entry_id, [ $person, $first, $second ], 1, $admin );
		$this->assertSame( 0, $unchanged['assignment_update']['email_sent_count'] );
		$submitted = $this->service->submit_entry(
			$entry_id,
			[
				'version'           => $unchanged['version'],
				'contact_person_id' => $person,
				'team_entries'      => [ [ 'player_count' => 10 ] ],
			],
			$user
			);
		$this->assertIsArray( $submitted );
		$this->assertNotEmpty( get_post_meta( $entry_id, '_tournament_payment_email_sent_person_' . $first, true ) );
		$this->assertNotEmpty( get_post_meta( $entry_id, '_tournament_payment_email_sent_person_' . $second, true ) );
		$late_user = $this->createRondoUser();
		update_user_meta( $late_user, 'rondo_linked_person_id', $first );
		$result = \Rondo\Tournaments\TournamentPaymentEmail::send_initial( $entry_id );
		$this->assertSame( 0, $result['sent_count'] );
	}

	public function test_normal_account_activation_reveals_the_invitation_without_sending_it_again(): void {
		$admin      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team       = $this->createOrganization( [ 'post_title' => 'AWC O10-4' ] );
		$person     = $this->pending_staff( $team, 'Nieuwe trainer', 'nieuwe-trainer@example.test' );
		$tournament = $this->create_tournament( $admin );
		$published  = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'    => $team,
					'person_ids' => [ $person ],
				],
			],
			$admin
			);
		$entry_id   = $published['entries'][0]['id'];
		$token      = \Rondo\Users\ActivationService::create_token( 'nieuwe-trainer@example.test' );
		$url        = \Rondo\Users\ActivationService::activate( $token, $person );
		$this->assertIsString( $url );
		$account = (int) get_post_meta( $person, \Rondo\Users\UserProvisioning::META_USER_ID, true );
		$this->assertGreaterThan( 0, $account );
		$this->assertTrue( TournamentAccess::is_assigned( $entry_id, $account ) );
		$this->assertSame( $entry_id, $this->service->entries_for_user( $account )[0]['id'] );
		$this->assertTrue( $this->service->format_entry( $entry_id )['assignees'][0]['invitation_sent'] );
	}

	public function test_invalid_staff_and_missing_email_are_not_invited_and_lock_prevents_parallel_delivery(): void {
		$admin   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team    = $this->createOrganization( [ 'post_title' => 'AWC O10-1' ] );
		$person  = $this->pending_staff( $team, 'Trainer', 'trainer@example.test' );
		$missing = $this->pending_staff( $team, 'Geen e-mail', '' );
		$former  = $this->pending_staff( $team, 'Oud lid', 'oud@example.test' );
		Fields::update_for_post( $former, 'former_member', true );
		$ended = $this->pending_staff( $team, 'Oude trainer', 'oude-trainer@example.test' );
		Fields::update_for_post(
			$ended,
			'work_history',
			[
				array_merge(
				$this->position( $team, 'team', 'Trainer' ),
				[
					'is_current' => false,
					'end_date'   => '2020-01-02',
				]
			),
			]
			);
		$tournament = $this->create_tournament( $admin );
		foreach ( [ $missing, $former, $ended ] as $invalid_id ) {
			$this->assertWPError(
				$this->service->publish(
				$tournament['id'],
				[
					[
						'team_id'    => $team,
						'person_ids' => [ $invalid_id ],
					],
				],
				$admin
				)
				);
		}
		add_option( 'rondo_tournament_write_lock_' . $tournament['id'], time(), '', false );
		$blocked = $this->service->publish(
			$tournament['id'],
			[
				[
					'team_id'    => $team,
					'person_ids' => [ $person ],
				],
			],
			$admin
			);
		$this->assertWPError( $blocked );
		$this->assertSame( 'rondo_tournament_write_locked', $blocked->get_error_code() );
		$this->assertSame( [], $this->service->entries_for_tournament( $tournament['id'] ) );
		delete_option( 'rondo_tournament_write_lock_' . $tournament['id'] );
	}

	private function pending_staff( int $team_id, string $name, string $email ): int {
		return $this->createPerson(
			[ 'post_title' => $name ],
			[
				'first_name'   => $name,
				'email_1'      => $email,
				'mobile_1'     => '0612345678',
				'work_history' => [ $this->position( $team_id, 'team', 'Trainer' ) ],
			]
			);
	}

	private function create_tournament( int $actor_user_id ): array {
		$start  = current_datetime()->modify( '+10 days' )->setTime( 10, 0 );
		$result = $this->service->save_tournament(
			[
				'name'              => 'Kersttoernooi 2026',
				'organizer'         => 'Kersttoernooi',
				'location'          => 'Sporthal',
				'description'       => 'Iedereen doet mee.',
				'internal_deadline' => current_datetime()->modify( '+5 days' )->format( DATE_RFC3339 ),
				'external_deadline' => current_datetime()->modify( '+8 days' )->format( DATE_RFC3339 ),
				'schedule'          => [
					[
						'age_group'      => 'O6 t/m O19',
						'start_datetime' => $start->format( DATE_RFC3339 ),
						'location'       => 'Sporthal',
					],
				],
				'pricing_rules'     => [
					[
						'min_age'     => 6,
						'max_age'     => 7,
						'amount'      => 28,
						'game_format' => '4 tegen 4',
					],
					[
						'min_age'     => 8,
						'max_age'     => 20,
						'amount'      => 48,
						'game_format' => '5 tegen 5',
					],
				],
			],
			$actor_user_id
		);
		$this->assertIsArray( $result );
		return $result;
	}

	private function link_user( int $user_id, array $positions ): int {
		$user      = get_userdata( $user_id );
		$person_id = $this->createPerson(
			[ 'post_title' => $user->display_name ],
			[
				'email_1'    => $user->user_email,
				'first_name' => $user->display_name,
				'mobile_1'   => '0612345678',
			]
		);
		Fields::update_for_post( $person_id, 'work_history', $positions );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		return $person_id;
	}

	private function position( int $entity_id, string $entity_type, string $job_title ): array {
		return [
			'team'        => $entity_id,
			'entity_type' => $entity_type,
			'job_title'   => $job_title,
			'start_date'  => '2020-01-01',
			'end_date'    => '',
			'is_current'  => true,
		];
	}
}
