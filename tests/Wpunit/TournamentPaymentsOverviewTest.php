<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\Tournaments;
use Rondo\Tournaments\TournamentService;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Shared payment reporting must not widen invoice or tournament management access. */
class TournamentPaymentsOverviewTest extends RondoTestCase {

	public function test_finance_readers_and_coordinators_can_read_but_members_cannot(): void {
		$server  = $this->bootRestControllers( [ Tournaments::class ] );
		$request = new WP_REST_Request( 'GET', '/rondo/v1/tournaments/payments' );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $server->dispatch( $request )->get_status() );

		$member = $this->createRondoUser();
		wp_set_current_user( $member );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		foreach ( [ 'financieel_read', 'financieel' ] as $capability ) {
			$user_id = $this->createRondoUser();
			get_user_by( 'id', $user_id )->add_cap( $capability );
			wp_set_current_user( $user_id );
			$response = $server->dispatch( $request );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
			$this->assertSame( 403, $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/tournaments' ) )->get_status() );
			$this->assertSame( 403, $server->dispatch( new WP_REST_Request( 'POST', '/rondo/v1/tournaments' ) )->get_status() );
		}

		$person = $this->createPerson(
			[],
			[
				'work_history' => [
					[
						'job_title'  => 'Coördinator toernooien',
						'is_current' => true,
					],
				],
			]
			);
		update_user_meta( $member, 'rondo_linked_person_id', $person );
		wp_set_current_user( $member );
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
		Fields::update_for_post( $person, 'work_history', [] );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
	}

	public function test_overview_uses_live_invoice_status_and_dates_without_read_side_effects(): void {
		$entry   = $this->entry();
		$invoice = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_sent',
			]
			);
		Fields::update_many_for_post(
			$invoice,
			[
				'invoice_number' => '2026O001',
				'invoice_type'   => 'tournament',
				'status'         => 'sent',
				'payment_link'   => 'https://pay.example.test/private',
			]
			);
		Fields::update_for_post( $entry, 'invoice_id', $invoice );
		Fields::update_for_post( $entry, 'payment_state', 'error' );
		$service = new TournamentService();
		$before  = _get_cron_array();
		$rows    = $service->payment_overview();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'open', $rows[0]['payment_state'] );
		$this->assertSame( '2026O001', $rows[0]['invoice_number'] );
		$this->assertSame( 30.0, $rows[0]['total_amount'] );
		$this->assertSame( 'JO10-1', $rows[0]['team_name'] );
		$this->assertNull( $rows[0]['paid_at'] );
		$this->assertArrayNotHasKey( 'payment_url', $rows[0] );
		$this->assertArrayNotHasKey( 'contact_email', $rows[0] );
		$this->assertArrayNotHasKey( 'assignees', $rows[0] );
		$this->assertSame( $before, _get_cron_array() );

		wp_update_post(
			[
				'ID'          => $invoice,
				'post_status' => 'rondo_paid',
			]
			);
		Fields::update_for_post( $invoice, 'status', 'paid' );
		update_post_meta( $invoice, '_mollie_paid_at', '2026-09-22T12:30:00+02:00' );
		update_post_meta( $invoice, '_mollie_payment_method', 'ideal' );
		$paid = $service->payment_overview()[0];
		$this->assertSame( 'paid', $paid['payment_state'] );
		$this->assertEquals( new \DateTimeImmutable( '2026-09-22T12:30:00+02:00' ), new \DateTimeImmutable( $paid['paid_at'] ) );
		$this->assertSame( 'ideal', $paid['payment_method'] );

		update_post_meta( $invoice, '_manually_marked_paid_at', '2026-09-23 14:30:00' );
		$manual = $service->payment_overview()[0];
		$this->assertSame( 'manual', $manual['payment_method'] );
		$this->assertStringStartsWith( '2026-09-23T14:30:00', $manual['paid_at'] );
	}

	public function test_missing_links_free_registrations_and_archived_tournaments_are_reported_but_drafts_and_trash_are_not(): void {
		$missing = $this->entry();
		$free    = $this->entry( [ 'total_amount' => 0 ] );
		$open    = $this->entry( [ 'registration_status' => 'open' ] );
		$trashed = $this->entry();
		wp_trash_post( $trashed );
		$orphan = $this->entry();
		wp_trash_post( (int) Fields::get_for_post( $orphan, 'tournament_id' ) );
		$archived = $this->entry();
		Fields::update_for_post( (int) Fields::get_for_post( $archived, 'tournament_id' ), 'lifecycle_status', 'archived' );
		$before = _get_cron_array();
		$rows   = ( new TournamentService() )->payment_overview();
		$by_id  = array_column( $rows, null, 'id' );
		$this->assertCount( 3, $rows );
		$this->assertSame( 'error', $by_id[ $missing ]['payment_state'] );
		$this->assertSame( 'not_applicable', $by_id[ $free ]['payment_state'] );
		$this->assertArrayHasKey( $archived, $by_id );
		$this->assertArrayNotHasKey( $open, $by_id );
		$this->assertArrayNotHasKey( $orphan, $by_id );
		$this->assertSame( $before, _get_cron_array() );
	}

	private function entry( array $fields = [] ): int {
		$tournament = self::factory()->post->create(
			[
				'post_type'   => TournamentService::TOURNAMENT_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Voorbeeldtoernooi',
			]
			);
		$entry      = self::factory()->post->create(
			[
				'post_type'   => TournamentService::ENTRY_POST_TYPE,
				'post_status' => 'publish',
			]
			);
		Fields::update_many_for_post(
			$entry,
			array_merge(
				[
					'tournament_id'         => $tournament,
					'team_name_snapshot'    => 'JO10-1',
					'registration_status'   => 'submitted',
					'total_amount'          => 30,
					'registered_team_count' => 2,
				],
				$fields
			)
		);
		return $entry;
	}
}
