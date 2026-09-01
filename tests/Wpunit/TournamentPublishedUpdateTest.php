<?php

namespace Tests\Wpunit;

use DateTimeImmutable;
use Rondo\Fields\Fields;
use Rondo\REST\Tournaments;
use Rondo\Tournaments\TournamentActivityLog;
use Rondo\Tournaments\TournamentChangeNotificationService;
use Rondo\Tournaments\TournamentService;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Covers safe updates and optional notifications for published tournaments. */
class TournamentPublishedUpdateTest extends RondoTestCase {

	private TournamentService $service;

	protected function set_up(): void {
		parent::set_up();
		$this->service = new TournamentService();
	}

	public function test_published_operational_fields_support_time_and_optimistic_locking(): void {
		$admin_id   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$tournament = $this->create_tournament();
		$payload    = $this->payload(
			$tournament,
			[
				'location' => 'Nieuwe sporthal',
				'schedule' => [
					[
						'age_group'      => 'O6 t/m O19',
						'start_datetime' => '2030-06-22T14:30',
						'location'       => 'Nieuwe sporthal',
					],
				],
			]
		);

		$updated = $this->service->save_tournament( $payload, $admin_id, $tournament['id'] );

		$this->assertIsArray( $updated );
		$this->assertSame( 2, $updated['version'] );
		$this->assertSame( 'Nieuwe sporthal', $updated['location'] );
		$this->assertSame(
			( new DateTimeImmutable( '2030-06-22 14:30:00', wp_timezone() ) )->format( DATE_RFC3339 ),
			$updated['schedule'][0]['start_datetime']
		);
		$this->assertSame( [ 'location', 'schedule' ], $updated['change']['changed_fields'] );
		$this->assertGreaterThan( 0, $updated['change']['activity_id'] );

		$conflict = $this->service->save_tournament( $payload, $admin_id, $tournament['id'] );
		$this->assertWPError( $conflict );
		$this->assertSame( 'rondo_tournament_conflict', $conflict->get_error_code() );

		$locked_assignments = $this->payload( $updated, [ 'target_team_ids' => [ 123 ] ] );
		$locked             = $this->service->save_tournament( $locked_assignments, $admin_id, $tournament['id'] );
		$this->assertWPError( $locked );
		$this->assertSame( 'rondo_tournament_assignments_locked', $locked->get_error_code() );
	}

	public function test_pricing_locks_after_first_definitive_registration_without_changing_snapshots(): void {
		$admin_id   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$tournament = $this->create_tournament();
		$price_edit = $this->payload(
			$tournament,
			[
				'pricing_rules' => [
					[
						'min_age'     => 6,
						'max_age'     => 20,
						'amount'      => 55,
						'game_format' => '7 tegen 7',
					],
				],
			]
		);
		$priced     = $this->service->save_tournament( $price_edit, $admin_id, $tournament['id'] );
		$this->assertIsArray( $priced );
		$this->assertSame( 55.0, (float) $priced['pricing_rules'][0]['amount'] );

		$entry_id = $this->create_entry( $tournament['id'], 'submitted' );
		Fields::update_many_for_post(
			$entry_id,
			[
				'price_per_team' => 55,
				'total_amount'   => 110,
			]
		);

		$operational = $this->service->save_tournament( $this->payload( $priced, [ 'organizer' => 'Nieuwe organisator' ] ), $admin_id, $tournament['id'] );
		$this->assertIsArray( $operational );
		$this->assertFalse( $operational['can_edit_pricing'] );

		$blocked_payload                               = $this->payload( $operational );
		$blocked_payload['pricing_rules'][0]['amount'] = 60;
		$blocked                                       = $this->service->save_tournament( $blocked_payload, $admin_id, $tournament['id'] );
		$this->assertWPError( $blocked );
		$this->assertSame( 'rondo_tournament_pricing_locked', $blocked->get_error_code() );
		$this->assertSame( 55.0, (float) Fields::get_for_post( $entry_id, 'price_per_team' ) );
		$this->assertSame( 110.0, (float) Fields::get_for_post( $entry_id, 'total_amount' ) );
	}

	public function test_change_notification_uses_all_assignees_and_only_submitted_contacts_once(): void {
		$admin_id     = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$tournament   = $this->create_tournament();
		$shared_id    = $this->createRondoUser(
			[
				'display_name' => 'Gedeeld kaderlid',
				'user_email'   => 'shared@example.test',
			]
		);
		$other_id     = $this->createRondoUser(
			[
				'display_name' => 'Ander kaderlid',
				'user_email'   => 'other@example.test',
			]
		);
		$open_id      = $this->create_entry( $tournament['id'], 'open', 'AWC O11-1' );
		$submitted_id = $this->create_entry( $tournament['id'], 'submitted', 'AWC O13-1' );
		Fields::update_many_for_post(
			$open_id,
			[
				'assignment_snapshot' => [
					[
						'user_id' => $shared_id,
						'name'    => 'Gedeeld kaderlid',
					],
				],
				'contact_email'       => 'niet-ingeschreven@example.test',
				'contact_name'        => 'Niet ingeschreven contact',
			]
		);
		Fields::update_many_for_post(
			$submitted_id,
			[
				'assignment_snapshot' => [
					[
						'user_id' => $shared_id,
						'name'    => 'Gedeeld kaderlid',
					],
					[
						'user_id' => $other_id,
						'name'    => 'Ander kaderlid',
					],
				],
				'contact_email'       => 'contact@example.test',
				'contact_name'        => 'Contactpersoon',
			]
		);

		$updated = $this->service->save_tournament(
			$this->payload(
				$tournament,
				[
					'schedule' => [
						[
							'age_group'      => 'O6 t/m O19',
							'start_datetime' => '2030-06-22T16:45',
							'location'       => 'Sportpark',
						],
					],
				]
			),
			$admin_id,
			$tournament['id']
		);
		$preview = $updated['change']['preview'];
		$this->assertSame( 3, $preview['recipient_count'] );
		$this->assertSame( 1, $preview['deduplicated_count'] );
		$this->assertNotContains( 'niet-ingeschreven@example.test', array_column( $preview['recipients'], 'email' ) );

		$mails  = [];
		$filter = static function ( $return, array $atts ) use ( &$mails ) {
			$mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		$notifications = new TournamentChangeNotificationService();
		$sent          = $notifications->send(
			$tournament['id'],
			$updated['change']['activity_id'],
			[
				'subject' => 'Tijd gewijzigd',
				'message' => 'Let op de nieuwe aanvangstijd.',
			],
			$admin_id
		);
		remove_filter( 'pre_wp_mail', $filter, 10 );

		$this->assertIsArray( $sent );
		$this->assertSame( 3, $sent['sent_count'] );
		$this->assertCount( 3, $mails );
		$this->assertStringContainsString( '22 June 2030 16:45', $mails[0]['message'] );
		$this->assertStringContainsString( 'Let op de nieuwe aanvangstijd.', $mails[0]['message'] );

		$duplicate = $notifications->send( $tournament['id'], $updated['change']['activity_id'], [], $admin_id );
		$this->assertWPError( $duplicate );
		$this->assertSame( 'rondo_tournament_change_notification_sent', $duplicate->get_error_code() );
		$this->assertContains( 'tournament_change_notification_sent', array_column( TournamentActivityLog::recent( $tournament['id'] ), 'action' ) );
	}

	public function test_archived_tournament_is_read_only(): void {
		$admin_id   = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$tournament = $this->create_tournament( 'archived' );
		$result     = $this->service->save_tournament( $this->payload( $tournament, [ 'location' => 'Andere locatie' ] ), $admin_id, $tournament['id'] );

		$this->assertWPError( $result );
		$this->assertSame( 'rondo_tournament_archived', $result->get_error_code() );
	}

	public function test_change_notification_route_requires_a_manager(): void {
		$server      = $this->bootRestControllers( [ Tournaments::class ] );
		$admin_id    = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$assignee_id = $this->createRondoUser( [ 'user_email' => 'route-recipient@example.test' ] );
		$tournament  = $this->create_tournament();
		$entry_id    = $this->create_entry( $tournament['id'], 'open' );
		Fields::update_for_post(
			$entry_id,
			'assignment_snapshot',
			[
				[
					'user_id' => $assignee_id,
					'name'    => 'Kaderlid',
				],
			]
			);
		$updated     = $this->service->save_tournament( $this->payload( $tournament, [ 'location' => 'Routehal' ] ), $admin_id, $tournament['id'] );
		$activity_id = $updated['change']['activity_id'];
		$request     = new WP_REST_Request( 'POST', '/rondo/v1/tournaments/' . $tournament['id'] . '/change-notification' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( [ 'activity_id' => $activity_id ] ) );

		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );

		add_filter( 'pre_wp_mail', '__return_true' );
		wp_set_current_user( $admin_id );
		$response = $server->dispatch( $request );
		remove_filter( 'pre_wp_mail', '__return_true' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, $response->get_data()['sent_count'] );
	}

	private function create_tournament( string $status = 'open' ): array {
		$id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::TOURNAMENT_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Gepubliceerd testtoernooi',
			]
		);
		Fields::update_many_for_post(
			$id,
			[
				'description'           => 'Praktische informatie.',
				'external_deadline'     => '2030-06-15 23:59:59',
				'internal_deadline'     => '2030-06-10 23:59:59',
				'lifecycle_status'      => $status,
				'location'              => 'Sportpark',
				'organizer'             => 'Organisator',
				'payment_deadline'      => '2030-06-12 23:59:59',
				'payment_reminder_days' => [ [ 'days_before' => 7 ], [ 'days_before' => 2 ] ],
				'pricing_rules'         => [
					[
						'min_age'     => 6,
						'max_age'     => 20,
						'amount'      => 48,
						'game_format' => '5 tegen 5',
					],
				],
				'schedule'              => [
					[
						'age_group'      => 'O6 t/m O19',
						'start_datetime' => ( new DateTimeImmutable( '2030-06-20 10:00:00', wp_timezone() ) )->format( DATE_RFC3339 ),
						'location'       => 'Sportpark',
					],
				],
				'version'               => 1,
			]
		);
		return $this->service->format_tournament( $id, true );
	}

	private function create_entry( int $tournament_id, string $status, string $team_name = 'AWC O12-1' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::ENTRY_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $team_name,
			]
		);
		Fields::update_many_for_post(
			$id,
			[
				'age_group_snapshot'  => 'O12',
				'registration_status' => $status,
				'team_name_snapshot'  => $team_name,
				'tournament_id'       => $tournament_id,
			]
		);
		return $id;
	}

	private function payload( array $tournament, array $overrides = [] ): array {
		return array_replace(
			[
				'name'                  => $tournament['name'],
				'organizer'             => $tournament['organizer'],
				'location'              => $tournament['location'],
				'description'           => $tournament['description'],
				'internal_deadline'     => $tournament['internal_deadline'],
				'payment_deadline'      => $tournament['payment_deadline'],
				'external_deadline'     => $tournament['external_deadline'],
				'payment_reminder_days' => $tournament['payment_reminder_days'],
				'pricing_rules'         => $tournament['pricing_rules'],
				'schedule'              => $tournament['schedule'],
				'version'               => $tournament['version'],
			],
			$overrides
		);
	}
}
