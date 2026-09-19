<?php

namespace Tests\Wpunit;

use Rondo\Abilities\Registrar;
use Rondo\Fields\Fields;
use Rondo\REST\Feedback;
use Tests\Support\RondoTestCase;

/** Feedback workflow filtering and access through the read-only ability. */
class FeedbackAbilitiesTest extends RondoTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->bootRestControllers( [ Feedback::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	private function feedback( string $status, string $type = 'feature_request', string $project = 'rondo-club' ): int {
		$id = self::factory()->post->create(
			[
				'post_type'    => 'rondo_feedback',
				'post_status'  => 'publish',
				'post_title'   => 'Feedback ' . $status,
				'post_content' => 'Full description for triage.',
				'post_author'  => get_current_user_id(),
			]
		);
		Fields::update_for_post( $id, 'status', $status );
		Fields::update_for_post( $id, 'feedback_type', $type );
		Fields::update_for_post( $id, 'priority', 'high' );
		Fields::update_for_post( $id, 'use_case', 'Reduce administration.' );
		update_post_meta( $id, '_feedback_project', $project );
		return $id;
	}

	public function test_defaults_to_all_open_workflow_states_and_returns_triage_fields(): void {
		$open = [ 'new', 'approved', 'in_progress', 'in_review', 'needs_info' ];
		foreach ( array_merge( $open, [ 'resolved', 'declined' ] ) as $status ) {
			$this->feedback( $status );
		}

		$ability = wp_get_ability( 'rondo/list-feedback' );
		$this->assertTrue( $ability->get_meta_item( 'annotations' )['readonly'] );
		$result = $ability->execute( [] );
		$this->assertNotWPError( $result );
		$this->assertSame( 5, $result['total'] );
		$this->assertEqualsCanonicalizing( $open, array_column( array_column( $result['feedback'], 'meta' ), 'status' ) );
		$this->assertSame( 'Full description for triage.', $result['feedback'][0]['content'] );
		$this->assertSame( 'Reduce administration.', $result['feedback'][0]['meta']['use_case'] );
		$this->assertSame( 'high', $result['feedback'][0]['meta']['priority'] );
		$this->assertArrayNotHasKey( 'email', $result['feedback'][0]['author'] );
		$this->assertArrayNotHasKey( 'browser_info', $result['feedback'][0]['meta'] );
	}

	public function test_filters_workflow_type_and_project_with_real_pagination(): void {
		$ids = [ $this->feedback( 'approved', 'bug', 'rondo-sync' ), $this->feedback( 'approved', 'bug', 'rondo-sync' ) ];
		$this->feedback( 'new', 'bug', 'rondo-sync' );
		$this->feedback( 'approved', 'feature_request', 'rondo-sync' );
		$this->feedback( 'approved', 'bug' );
		$input   = [
			'status'   => 'approved',
			'type'     => 'bug',
			'project'  => 'rondo-sync',
			'priority' => 'high',
			'per_page' => 1,
		];
		$ability = wp_get_ability( 'rondo/list-feedback' );
		$first   = $ability->execute( $input );
		$second  = $ability->execute( array_merge( $input, [ 'page' => 2 ] ) );

		$this->assertNotWPError( $first );
		$this->assertSame( 2, $first['total'] );
		$this->assertSame( 2, $first['total_pages'] );
		$this->assertSame( 2, $second['page'] );
		$this->assertEqualsCanonicalizing( $ids, [ $first['feedback'][0]['id'], $second['feedback'][0]['id'] ] );
		$this->assertSame( [], $ability->execute( array_merge( $input, [ 'page' => 3 ] ) )['feedback'] );
	}

	public function test_closed_feedback_requires_an_explicit_filter(): void {
		$this->feedback( 'new' );
		$resolved = $this->feedback( 'resolved' );
		$this->feedback( 'declined' );
		$ability = wp_get_ability( 'rondo/list-feedback' );
		$this->assertSame( 3, $ability->execute( [ 'status' => 'all' ] )['total'] );
		$this->assertSame( [ $resolved ], array_column( $ability->execute( [ 'status' => 'resolved' ] )['feedback'], 'id' ) );
	}

	public function test_rejects_anonymous_and_users_without_feedback_section_access(): void {
		$this->feedback( 'new' );
		foreach ( [ 0, self::factory()->user->create( [ 'role' => 'subscriber' ] ) ] as $user_id ) {
			wp_set_current_user( $user_id );
			$result = wp_get_ability( 'rondo/list-feedback' )->execute( [] );
			$this->assertWPError( $result );
			$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
			$this->assertWPError( ( new Registrar() )->list_feedback( [] ) );
		}
	}

	public function test_accepts_a_user_with_explicit_feedback_capability(): void {
		$user = self::factory()->user->create_and_get( [ 'role' => 'subscriber' ] );
		$user->add_cap( 'feedback' );
		wp_set_current_user( $user->ID );
		$this->feedback( 'new' );
		$result = wp_get_ability( 'rondo/list-feedback' )->execute( [] );
		$this->assertNotWPError( $result );
		$this->assertSame( 1, $result['total'] );
	}

	public function test_invalid_status_or_page_size_is_rejected(): void {
		$ability = wp_get_ability( 'rondo/list-feedback' );
		foreach ( [ [ 'status' => 'publish' ], [ 'per_page' => 101 ], [ 'page' => 0 ], [ 'project' => 'unknown' ] ] as $input ) {
			$this->assertWPError( $ability->execute( $input ) );
		}
	}
}
