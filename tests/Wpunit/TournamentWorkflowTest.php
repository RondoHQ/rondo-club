<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Tournaments;
use Rondo\Tournaments\TournamentAccess;
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

	public function test_assignment_options_only_include_current_team_kader_with_an_account(): void {
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
		$this->assertSame( [ $kader_id ], array_column( $team['assignees'], 'user_id' ) );
	}

	public function test_shared_entry_supports_multiple_tournament_teams_and_one_contact(): void {
		$admin_id  = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id   = $this->createOrganization( [ 'post_title' => 'AWC O15-1' ] );
		$first_id  = $this->createRondoUser(
			[
				'display_name' => 'Eerste trainer',
				'user_email'   => 'eerste@example.test',
			]
			);
		$second_id = $this->createRondoUser(
			[
				'display_name' => 'Tweede trainer',
				'user_email'   => 'tweede@example.test',
			]
			);
		$this->link_user( $first_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
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
				'version'        => 1,
				'contact_name'   => 'Gedeelde contactpersoon',
				'contact_email'  => 'contact@example.test',
				'contact_mobile' => '0612345678',
				'team_entries'   => [ [ 'player_count' => 6 ], [ 'player_count' => 7 ] ],
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
		$this->assertSame( 'Gedeelde contactpersoon', $submitted['contact_name'] );
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

	public function test_submission_cannot_record_no_participation(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id  = $this->createOrganization( [ 'post_title' => 'AWC O10-1' ] );
		$kader_id = $this->createRondoUser( [ 'user_email' => 'trainer@example.test' ] );
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

		$result = $this->service->submit_entry(
			$published['entries'][0]['id'],
			[
				'version'        => 1,
				'contact_name'   => 'Contact',
				'contact_email'  => 'contact@example.test',
				'contact_mobile' => '0612345678',
				'team_entries'   => [],
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
		$server      = $this->bootRestControllers( [ Tournaments::class ] );
		$admin_id    = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$team_id     = $this->createOrganization( [ 'post_title' => 'AWC O13-1' ] );
		$assigned_id = $this->createRondoUser( [ 'user_email' => 'toernooi@example.test' ] );
		$this->link_user( $assigned_id, [ $this->position( $team_id, 'team', 'Trainer' ) ] );
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
		$submitted  = $this->service->submit_entry(
			$entry_id,
			[
				'version'        => 1,
				'contact_name'   => 'Contactpersoon',
				'contact_email'  => 'contact@example.test',
				'contact_mobile' => '0612345678',
				'team_entries'   => [ [ 'player_count' => 12 ] ],
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
				'version'        => 1,
				'contact_name'   => '',
				'contact_email'  => '',
				'contact_mobile' => '',
				'team_entries'   => [ [ 'player_count' => '' ] ],
			],
			$kader_id
		);

		$this->assertIsArray( $draft );
		$this->assertSame( 0, $draft['draft_team_entries'][0]['player_count'] );
		$this->assertSame( 'open', $draft['registration_status'] );
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
		$person_id = $this->createPerson(
			[ 'post_title' => get_userdata( $user_id )->display_name ],
			[ 'first_name' => get_userdata( $user_id )->display_name ]
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
