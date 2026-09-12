<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Onboarding\Foundation;
use Rondo\Onboarding\Recipients;
use Rondo\Onboarding\Dispatch;
use Rondo\REST\Onboarding;
use Tests\Support\RondoTestCase;

class OnboardingFoundationTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		Foundation::register();
		Dispatch::register();
	}

	private function person(): int {
		return $this->createPerson(
			[],
			[
				'first_name' => 'Emma',
				'knvb_id'    => 'TEST001',
				'lid_sinds'  => '2026-01-01',
				'email_1'    => 'emma@example.com',
				'birthdate'  => '2000-01-01',
			]
			);
	}

	private function input( int $id, string $state, int $offset = -10 ): array {
		return [
			'observation_id'   => wp_generate_uuid4(),
			'knvb_id'          => 'TEST001',
			'observed_at'      => gmdate( 'c', time() + $offset ),
			'membership_state' => $state,
			'snapshot_hash'    => Foundation::snapshot_hash( $id ),
			'coverage'         => array_fill_keys( Foundation::COVERAGE, true ),
		];
	}

	public function test_first_observation_is_baseline_and_simulation_cannot_send(): void {
		$id         = $this->person();
		$sent       = 0;
		$block_mail = static function () use ( &$sent ) {
			++$sent;
			return false;
		};
		add_filter( 'pre_wp_mail', $block_mail );
		try {
			$input  = $this->input( $id, 'definitive' );
			$result = Foundation::observe( $id, $input );
			$this->assertTrue( $result['baseline'] );
			$this->assertSame( 0, $result['round_id'] );
			$this->assertSame( $result, Foundation::observe( $id, $input ) );
			$before     = get_post_meta( $id );
			$simulation = Foundation::simulate( $id );
			$this->assertFalse( $simulation['sending_enabled'] );
			$this->assertNull( $simulation['due_at'] );
			$this->assertSame( $before, get_post_meta( $id ) );
			$this->assertSame( 0, $sent );
		} finally {
			remove_filter( 'pre_wp_mail', $block_mail );
		}
	}

	public function test_confirmed_transition_is_once_and_waits_for_future_start(): void {
		$id = $this->person();
		Foundation::observe( $id, $this->input( $id, 'preregistration', -20 ) );
		$date = current_datetime()->modify( '+5 days' )->format( 'Y-m-d' );
		Fields::update_for_post( $id, 'lid_sinds', $date );
		$input  = $this->input( $id, 'definitive' );
		$result = Foundation::observe( $id, $input );
		$this->assertGreaterThan( 0, $result['round_id'] );
		$this->assertSame( $result, Foundation::observe( $id, $input ) );
		$this->assertSame( Recipients::date( $date )->getTimestamp() + DAY_IN_SECONDS, (int) get_post_meta( $result['round_id'], '_onboarding_due', true ) );
		$next = Foundation::observe( $id, $this->input( $id, 'definitive', -5 ) );
		$this->assertSame( $result['round_id'], $next['round_id'] );
		$this->assertSame( 1, (int) wp_count_posts( Foundation::TYPE )->private );
	}

	public function test_rejoin_needs_actual_end_and_later_start(): void {
		$id = $this->person();
		Foundation::observe( $id, $this->input( $id, 'definitive', -30 ) );
		$this->assertWPError( Foundation::observe( $id, $this->input( $id, 'ended', -25 ) ) );
		$ended = current_datetime()->modify( '-10 days' )->format( 'Y-m-d' );
		Fields::update_for_post( $id, 'lid_tot', $ended );
		$this->assertIsArray( Foundation::observe( $id, $this->input( $id, 'ended', -20 ) ) );
		Fields::update_for_post( $id, 'lid_tot', null );
		Fields::update_for_post( $id, 'lid_sinds', $ended );
		$this->assertWPError( Foundation::observe( $id, $this->input( $id, 'definitive' ) ) );
		Fields::update_for_post( $id, 'lid_sinds', current_datetime()->format( 'Y-m-d' ) );
		$this->assertGreaterThan( 0, Foundation::observe( $id, $this->input( $id, 'definitive' ) )['round_id'] );
	}

	public function test_partial_coverage_does_not_open_round_and_blocks_existing_check(): void {
		$id = $this->person();
		Foundation::observe( $id, $this->input( $id, 'not_member', -30 ) );
		$input                      = $this->input( $id, 'definitive', -20 );
		$input['coverage']['teams'] = false;
		$result                     = Foundation::observe( $id, $input );
		$this->assertSame( 0, $result['round_id'] );
		$this->assertSame( 'not_member', $result['membership_state'] );
		$this->assertContains( 'De laatste broncontrole is onvolledig of mislukt.', Foundation::simulate( $id )['blockers'] );
		$this->assertGreaterThan( 0, Foundation::observe( $id, $this->input( $id, 'definitive' ) )['round_id'] );
	}

	public function test_stale_changed_conflicting_or_wrong_identity_observations_are_rejected(): void {
		$id    = $this->person();
		$input = $this->input( $id, 'not_member' );
		Foundation::observe( $id, $input );
		$input['membership_state'] = 'definitive';
		$this->assertWPError( Foundation::observe( $id, $input ) );
		$this->assertWPError( Foundation::observe( $id, $this->input( $id, 'definitive', -20 ) ) );
		$changed = $this->input( $id, 'definitive', -5 );
		Fields::update_for_post( $id, 'email_2', 'second@example.com' );
		$this->assertWPError( Foundation::observe( $id, $changed ) );
		$this->assertContains( 'Gegevens gewijzigd sinds de volledige broncontrole.', Foundation::simulate( $id )['blockers'] );
		$wrong            = $this->input( $id, 'definitive', -5 );
		$wrong['knvb_id'] = 'ANOTHER';
		$this->assertWPError( Foundation::observe( $id, $wrong ) );
	}

	public function test_recipient_age_boundary_deduplication_and_suppression(): void {
		$id     = $this->person();
		$parent = $this->createPerson(
			[],
			[
				'first_name' => 'Ouder van Emma',
				'email_1'    => 'shared@example.com',
				'email_2'    => 'other@example.com',
			]
			);
		$term   = get_term_by( 'slug', 'parent', 'relationship_type' );
		if ( ! $term ) {
			$term_id = wp_insert_term( 'Ouder', 'relationship_type', [ 'slug' => 'parent' ] )['term_id'];
		} else {
			$term_id = $term->term_id; }
		Fields::update_many_for_post(
			$id,
			[
				'birthdate'     => '2008-09-12',
				'email_2'       => 'shared@example.com',
				'relationships' => [
					[
						'related_person'    => $parent,
						'relationship_type' => $term_id,
					],
				],
			]
			);
		$before = Recipients::for_person( $id, new \DateTimeImmutable( '2026-09-11', wp_timezone() ) );
		$this->assertTrue( $before['minor'] );
		$this->assertCount( 3, $before['recipients'] );
		$after = Recipients::for_person( $id, new \DateTimeImmutable( '2026-09-12', wp_timezone() ) );
		$this->assertFalse( $after['minor'] );
		$this->assertCount( 2, $after['recipients'] );
		update_option( 'rondo_lettermint_suppressed_emails', [ 'emma@example.com' => [ 'reason' => 'hard_bounce' ] ] );
		$this->assertTrue( Recipients::for_person( $id )['recipients'][0]['blocked'] );
		Fields::update_for_post( $id, 'birthdate', null );
		$this->assertNull( Recipients::for_person( $id )['minor'] );
		$this->assertCount( 2, Recipients::for_person( $id )['recipients'] );
	}

	public function test_dispatch_reservation_is_per_round_and_address_and_never_reclaimed(): void {
		$id = $this->person();
		Foundation::observe( $id, $this->input( $id, 'not_member', -20 ) );
		$round = Foundation::observe( $id, $this->input( $id, 'definitive' ) )['round_id'];
		$first = Dispatch::reserve(
			$round,
			'welcome',
			'Emma@example.com',
			[
				'subject' => 'Welkom',
				'body'    => 'Test',
			]
			);
		$this->assertIsArray( $first );
		$this->assertWPError( Dispatch::reserve( $round, 'welcome', 'emma@example.com', [ 'subject' => 'Anders' ] ) );
		$this->assertIsArray( Dispatch::reserve( $round, 'welcome', 'parent@example.com', [ 'subject' => 'Welkom' ] ) );
		$this->assertWPError( Dispatch::accepted( $first['id'], '' ) );
		$this->assertSame( 'accepted', Dispatch::accepted( $first['id'], 'provider-1' )['status'] );
		$this->assertWPError( Dispatch::accepted( $first['id'], 'provider-2' ) );
		$this->assertWPError( Dispatch::reserve( $round, 'welcome', 'emma@example.com', [ 'subject' => 'Welkom' ] ) );
	}

	public function test_concurrent_lock_cannot_enter_twice(): void {
		$result = Foundation::locked( 'test', static fn() => Foundation::locked( 'test', static fn() => 'unsafe' ) );
		$this->assertWPError( $result );
		$this->assertSame( 'released', Foundation::locked( 'test', static fn() => 'released' ) );
	}

	/** Force the exact race: another insert wins after the first existence check. */
	public function test_interleaved_option_insert_cannot_steal_a_claim(): void {
		$key    = 'rondo_onboarding_lock_' . hash( 'sha256', 'interleaved' );
		$winner = static function ( $option, $value ) use ( $key, &$winner ) {
			if ( $option === $key ) {
				remove_action( 'add_option', $winner );
				add_option( $option, $value, '', false );
			}
		};
		add_action( 'add_option', $winner, 10, 2 );
		try {
			$this->assertWPError( Foundation::locked( 'interleaved', static fn() => 'unsafe' ) );
			$this->assertSame( 'locked', get_option( $key ) );
		} finally {
			remove_action( 'add_option', $winner );
			delete_option( $key );
		}
	}

	public function test_interleaved_dispatch_reservation_never_creates_two_records(): void {
		$id = $this->person();
		Foundation::observe( $id, $this->input( $id, 'not_member', -20 ) );
		$round  = Foundation::observe( $id, $this->input( $id, 'definitive' ) )['round_id'];
		$winner = static function ( $option ) use ( $round, &$winner ) {
			if ( str_starts_with( $option, 'rondo_onboarding_dispatch_' ) ) {
				remove_action( 'add_option', $winner );
				Dispatch::reserve( $round, 'welcome', 'emma@example.com', [ 'subject' => 'Winner' ] );
			}
		};
		add_action( 'add_option', $winner );
		try {
			$this->assertWPError( Dispatch::reserve( $round, 'welcome', 'emma@example.com', [ 'subject' => 'Loser' ] ) );
			$this->assertSame( 1, (int) wp_count_posts( Dispatch::TYPE )->private );
		} finally {
			remove_action( 'add_option', $winner );
		}
	}

	public function test_admin_only_api_is_read_only_and_paginated(): void {
		$id      = $this->person();
		$server  = $this->bootRestControllers( [ Onboarding::class ] );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/onboarding/simulation' );
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
		$this->assertEmpty( get_post_meta( $id, Foundation::META, true ) );
		$this->assertFalse( get_post_type_object( Foundation::TYPE )->show_in_rest );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		$post = new \WP_REST_Request( 'POST', '/rondo/v1/onboarding/observations/' . $id );
		$this->assertSame( 403, $server->dispatch( $post )->get_status() );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $server->dispatch( $request )->get_status() );
	}
}
