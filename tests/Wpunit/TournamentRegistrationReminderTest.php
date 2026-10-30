<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Tournaments;
use Rondo\Tournaments\TournamentRegistrationReminderService;
use Rondo\Tournaments\TournamentService;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class TournamentRegistrationReminderTest extends RondoTestCase {

	private TournamentRegistrationReminderService $service;
	private array $mail = [];
	private $capture;

	protected function set_up(): void {
		parent::set_up();
		$this->service = new TournamentRegistrationReminderService();
		$this->mail    = [];
		$this->capture = function ( $result, $args ) {
			$this->mail[] = $args;
			return ! in_array( 'failed@example.org', (array) $args['to'], true );
		};
		add_filter( 'pre_wp_mail', $this->capture, 10, 2 );
	}

	protected function tear_down(): void {
		remove_filter( 'pre_wp_mail', $this->capture, 10 );
		parent::tear_down();
	}

	public function test_preview_excludes_submitted_supplement_other_tournament_and_unpublished_entries(): void {
		$t      = $this->tournament();
		$person = $this->person( 'coach@example.org' );
		$a      = $this->entry( $t, 'O13-1', [ $person ] );
		$b      = $this->entry( $t, 'O13-2', [ $person, $person ] );
		$this->entry( $t, 'Submitted', [ $person ], 'submitted' );
		$this->entry( $t, 'Supplement', [ $person ], 'open', $a );
		$this->entry( $this->tournament(), 'Other', [ $person ] );
		$draft = $this->entry( $t, 'Draft', [ $person ] );
		wp_update_post(
			[
				'ID'          => $draft,
				'post_status' => 'draft',
			]
			);
		$this->entry( $t, 'Missing assignment', [] );
		$this->entry( $t, 'Missing email', [ $this->person( '' ) ] );
		$preview = $this->service->preview( $t, [], 1 );
		$this->assertIsArray( $preview );
		$this->assertCount( 4, $preview['teams'] );
		$this->assertCount( 1, $preview['recipients'] );
		$this->assertSame( [ $a, $b ], array_column( $preview['recipients'][0]['teams'], 'id' ) );
		$this->assertCount( 2, $preview['warnings'] );
		$this->assertSame( [], $this->mail );
	}

	public function test_delivery_uses_reviewed_message_and_recipient_specific_links_once(): void {
		$t       = $this->tournament();
		$a       = $this->entry( $t, 'Team Alpha', [ $this->person( 'alpha@example.org' ) ] );
		$b       = $this->entry( $t, 'Team Beta', [ $this->person( 'beta@example.org' ) ] );
		$preview = $this->service->preview(
			$t,
			[
				'subject' => 'Laatste oproep',
				'message' => "Schrijf je in!\nVragen? Laat het weten.",
			],
			1
			);
		$result  = $this->service->send( $t, $preview['token'], 1 );
		$this->assertSame(
			[
				'sent_count'      => 2,
				'failed_count'    => 0,
				'recipient_count' => 2,
			],
			$result
			);
		$this->assertCount( 2, $this->mail );
		$this->assertSame( 'Laatste oproep', $this->mail[0]['subject'] );
		$this->assertStringContainsString( 'Schrijf je in!', $this->mail[0]['message'] );
		$this->assertStringContainsString( '/mijn-toernooien/' . $a, $this->mail[0]['message'] );
		$this->assertStringNotContainsString( '/mijn-toernooien/' . $b, $this->mail[0]['message'] );
		$this->assertStringContainsString( '/activeren', $this->mail[0]['message'] );
		delete_transient( 'rondo_registration_reminder_' . $preview['token'] );
		$this->assertSame( $result, $this->service->send( $t, $preview['token'], 1 ) );
		$this->assertCount( 2, $this->mail );
	}

	public function test_recipient_changes_invalidate_preview_without_sending(): void {
		$t = $this->tournament();
		$p = $this->person( 'before@example.org' );
		$this->entry( $t, 'Team', [ $p ] );
		$preview = $this->service->preview( $t, [], 1 );
		Fields::update_for_post( $p, 'email_1', 'after@example.org' );
		$this->assertStale( $this->service->send( $t, $preview['token'], 1 ) );
		$this->assertSame( [], $this->mail );
	}

	public function test_submitted_team_and_changed_deadline_require_new_preview(): void {
		$t       = $this->tournament();
		$entry   = $this->entry( $t, 'Team', [ $this->person( 'coach@example.org' ) ] );
		$preview = $this->service->preview( $t, [], 1 );
		Fields::update_for_post( $entry, 'registration_status', 'submitted' );
		$this->assertStale( $this->service->send( $t, $preview['token'], 1 ) );
		Fields::update_for_post( $entry, 'registration_status', 'open' );
		Fields::update_for_post( $t, 'internal_deadline', '2036-06-10 23:59:59' );
		$this->assertStale( $this->service->send( $t, $preview['token'], 1 ) );
		$this->assertSame( [], $this->mail );
	}

	public function test_expired_cross_actor_and_cross_tournament_tokens_cannot_send(): void {
		$t = $this->tournament();
		$this->entry( $t, 'Team', [ $this->person( 'coach@example.org' ) ] );
		$preview = $this->service->preview( $t, [], 1 );
		$this->assertStale( $this->service->send( $t, $preview['token'], 2 ) );
		$this->assertStale( $this->service->send( $this->tournament(), $preview['token'], 1 ) );
		delete_transient( 'rondo_registration_reminder_' . $preview['token'] );
		$this->assertStale( $this->service->send( $t, $preview['token'], 1 ) );
		$this->assertSame( [], $this->mail );
	}

	public function test_closed_draft_archived_expired_and_missing_tournaments_cannot_be_reminded(): void {
		$t = $this->tournament();
		foreach ( [ 'closed', 'draft', 'archived' ] as $status ) {
			Fields::update_for_post( $t, 'lifecycle_status', $status );
			$this->assertWPError( $this->service->preview( $t, [], 1 ) );
		}
		Fields::update_for_post( $t, 'lifecycle_status', 'open' );
		Fields::update_for_post( $t, 'internal_deadline', '2020-01-01 00:00:00' );
		$this->assertWPError( $this->service->preview( $t, [], 1 ) );
		$this->assertWPError( $this->service->preview( 99999999, [], 1 ) );
		$this->assertSame( [], $this->mail );
	}

	public function test_partial_failures_are_recorded_and_not_retried(): void {
		$t = $this->tournament();
		$this->entry( $t, 'Team', [ $this->person( 'ok@example.org' ), $this->person( 'failed@example.org' ) ] );
		$preview = $this->service->preview( $t, [], 1 );
		$result  = $this->service->send( $t, $preview['token'], 1 );
		$this->assertSame( 1, $result['sent_count'] );
		$this->assertSame( 1, $result['failed_count'] );
		$this->assertSame( $result, $this->service->send( $t, $preview['token'], 1 ) );
		$this->assertCount( 2, $this->mail );
		$this->assertStale( $this->service->send( $t, $preview['token'], 2 ) );
	}

	public function test_interrupted_dispatch_cannot_be_replayed_and_shared_lock_blocks_mutations(): void {
		$t = $this->tournament();
		$this->entry( $t, 'Team', [ $this->person( 'coach@example.org' ) ] );
		$preview = $this->service->preview( $t, [], 1 );
		add_post_meta( $t, '_registration_reminder_' . hash( 'sha256', $preview['token'] ), [ 'actor_id' => 1 ], true );
		$this->assertSame( 'rondo_reminder_delivery_uncertain', $this->service->send( $t, $preview['token'], 1 )->get_error_code() );
		add_option( 'rondo_tournament_write_lock_' . $t, time(), '', false );
		$service = new TournamentService();
		$this->assertWPError( $this->service->send( $t, $preview['token'], 1 ) );
		$this->assertWPError( $service->update_lifecycle_status( $t, 'closed', 1 ) );
		$this->assertWPError( $service->delete_tournament( $t ) );
		$this->assertSame( 'publish', get_post_status( $t ) );
		$this->assertSame( [], $this->mail );
	}

	public function test_empty_audience_and_blank_message_cannot_send(): void {
		$t       = $this->tournament();
		$preview = $this->service->preview( $t, [], 1 );
		$this->assertWPError( $this->service->send( $t, $preview['token'], 1 ) );
		$this->assertWPError( $this->service->preview( $t, [ 'message' => '' ], 1 ) );
		$this->assertSame( [], $this->mail );
	}

	public function test_routes_require_manager_permission_and_never_send_on_preview(): void {
		$server = $this->bootRestControllers( [ Tournaments::class ] );
		$t      = $this->tournament();
		$this->entry( $t, 'Team', [ $this->person( 'coach@example.org' ) ] );
		wp_set_current_user( $this->createRondoUser() );
		foreach ( [ 'preview', 'send' ] as $action ) {
			$request = new WP_REST_Request( 'POST', '/rondo/v1/tournaments/' . $t . '/registration-reminder/' . $action );
			$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request  = new WP_REST_Request( 'POST', '/rondo/v1/tournaments/' . $t . '/registration-reminder/preview' );
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data()['recipients'] );
		$this->assertSame( [], $this->mail );
	}

	private function assertStale( $result ): void {
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_reminder_preview_stale', $result->get_error_code() );
	}

	private function tournament(): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::TOURNAMENT_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Voorjaarstoernooi',
			]
			);
		Fields::update_many_for_post(
			$id,
			[
				'internal_deadline' => '2035-06-10 23:59:59',
				'lifecycle_status'  => 'open',
			]
			);
		return $id;
	}

	private function person( string $email ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
			]
			);
		Fields::update_many_for_post(
			$id,
			[
				'first_name' => 'Coach',
				'last_name'  => 'Voorbeeld',
				'email_1'    => $email,
			]
			);
		return $id;
	}

	private function entry( int $tournament, string $name, array $people, string $status = 'open', int $parent = 0 ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::ENTRY_POST_TYPE,
				'post_status' => 'publish',
				'post_parent' => $parent,
				'post_title'  => $name,
			]
			);
		Fields::update_many_for_post(
			$id,
			[
				'tournament_id'       => $tournament,
				'team_name_snapshot'  => $name,
				'registration_status' => $status,
				'assignment_snapshot' => array_map(
				fn( $p ) => [
					'person_id' => $p,
					'user_id'   => 0,
					'name'      => 'Coach Voorbeeld',
					'role'      => 'Trainer',
					'email'     => 'stale@example.org',
				],
				$people
			),
			]
			);
		return $id;
	}
}
