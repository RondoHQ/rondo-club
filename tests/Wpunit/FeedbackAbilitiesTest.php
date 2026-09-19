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

	public function test_create_uses_current_author_defaults_and_notifies_admin(): void {
		$mail = [];
		add_filter(
			'pre_wp_mail',
			static function ( $result, $atts ) use ( &$mail ) {
				$mail[] = $atts;
				return true;
			},
			10,
			2
			);
		$member = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $member );
		$ability = wp_get_ability( 'rondo/create-feedback' );
		$this->assertFalse( $ability->get_meta_item( 'annotations' )['readonly'] );
		$this->assertFalse( $ability->get_meta_item( 'annotations' )['idempotent'] );
		$result = $ability->execute(
			[
				'title'         => 'Taakuitleg in de mail',
				'feedback_type' => 'feature_request',
				'content'       => 'Maak de uitleg vindbaar.',
				'use_case'      => 'Vrijwilligers helpen.',
			]
			);
		$this->assertNotWPError( $result );
		$this->assertSame( $member, $result['author']['id'] );
		$this->assertSame( 'new', $result['meta']['status'] );
		$this->assertSame( 'Vrijwilligers helpen.', $result['meta']['use_case'] );
		$this->assertCount( 1, $mail );
		$this->assertNotEmpty( $result['notification_sent_at']['created'] );
		$this->assertArrayNotHasKey( 'email', $result['author'] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame(
			'approved',
			$ability->execute(
			[
				'title'         => 'Bug',
				'feedback_type' => 'bug',
			]
			)['meta']['status']
			);
	}

	public function test_creation_rejects_invalid_input_and_impersonation_without_writes(): void {
		$ability = wp_get_ability( 'rondo/create-feedback' );
		$before  = (int) wp_count_posts( 'rondo_feedback' )->publish;
		foreach ( [ [ 'status' => 'resolved' ], [ 'author' => 123 ], [ 'project' => 'unknown' ], [ 'title' => '  ' ], [ 'priority' => 'invalid' ] ] as $invalid ) {
			$input = array_merge(
				[
					'title'         => 'Unchanged',
					'feedback_type' => 'bug',
				],
				$invalid
				);
			$this->assertWPError( $ability->execute( $input ) );
			$this->assertWPError( ( new Registrar() )->create_feedback( $input ) );
		}
		$this->assertSame( $before, (int) wp_count_posts( 'rondo_feedback' )->publish );
		wp_set_current_user( 0 );
		$this->assertWPError(
			$ability->execute(
			[
				'title'         => 'Anonymous',
				'feedback_type' => 'bug',
			]
			)
			);
	}

	public function test_update_requires_admin_and_closing_explanation_before_changing_content(): void {
		$id      = $this->feedback( 'new' );
		$ability = wp_get_ability( 'rondo/update-feedback' );
		foreach ( [ 'resolved', 'declined' ] as $status ) {
			$this->assertWPError(
				$ability->execute(
				[
					'id'     => $id,
					'title'  => 'Must not change',
					'status' => $status,
				]
				)
				);
			$this->assertSame( 'Feedback new', get_post( $id )->post_title );
			$this->assertSame( 'new', Fields::get_for_post( $id, 'status' ) );
		}
		$this->assertWPError(
			$ability->execute(
			[
				'id'      => $id,
				'title'   => 'Must not change',
				'project' => 'invalid',
			]
			)
			);
		$this->assertSame( 'Feedback new', get_post( $id )->post_title );
		$other = self::factory()->post->create( [ 'post_type' => 'post' ] );
		$this->assertWPError(
			$ability->execute(
			[
				'id'     => $other,
				'status' => 'approved',
			]
			)
			);
		foreach ( [ 0, self::factory()->user->create( [ 'role' => 'subscriber' ] ) ] as $user ) {
			wp_set_current_user( $user );
			$this->assertWPError(
				$ability->execute(
				[
					'id'     => $id,
					'status' => 'approved',
				]
				)
				);
			$this->assertWPError( ( new Registrar() )->update_feedback( [ 'id' => $id ] ) );
		}
	}

	public function test_workflow_updates_preserve_content_and_resolution_sends_once(): void {
		$id   = $this->feedback( 'new' );
		$mail = [];
		add_filter(
			'pre_wp_mail',
			static function ( $result, $atts ) use ( &$mail ) {
				$mail[] = $atts;
				return true;
			},
			10,
			2
			);
		$ability = wp_get_ability( 'rondo/update-feedback' );
		$result  = $ability->execute(
			[
				'id'           => $id,
				'status'       => 'in_review',
				'agent_branch' => 'codex/feedback',
				'pr_url'       => 'https://github.com/RondoHQ/rondo-club/pull/1',
			]
			);
		$this->assertNotWPError( $result );
		$this->assertSame( 'Full description for triage.', $result['content'] );
		$this->assertSame( 'codex/feedback', $result['meta']['agent_branch'] );
		$this->assertCount( 0, $mail );
		$input  = [
			'id'                 => $id,
			'status'             => 'resolved',
			'resolution_summary' => 'De uitleg is toegevoegd.',
		];
		$result = $ability->execute( $input );
		$this->assertNotWPError( $result );
		$this->assertSame( 'resolved', $result['meta']['status'] );
		$this->assertSame( 'sent', $result['resolution_email']['status'] );
		$this->assertArrayNotHasKey( 'recipient', $result['resolution_email'] );
		$this->assertNotEmpty( $result['notification_sent_at']['resolved'] );
		$this->assertCount( 1, $mail );
		$this->assertStringContainsString( 'De uitleg is toegevoegd.', $mail[0]['message'] );
		$this->assertSame( $result['notification_sent_at'], $ability->execute( $input )['notification_sent_at'] );
		$this->assertCount( 1, $mail );
		$this->assertSame( 'resolved', $ability->execute( [ 'id' => $id ] )['meta']['status'] );
	}

	public function test_resolution_mail_failure_is_reported_without_claiming_delivery(): void {
		$id = $this->feedback( 'new' );
		add_filter( 'pre_wp_mail', '__return_false' );
		$result = wp_get_ability( 'rondo/update-feedback' )->execute(
			[
				'id'                 => $id,
				'status'             => 'resolved',
				'resolution_summary' => 'Opgelost.',
			]
			);
		$this->assertNotWPError( $result );
		$this->assertSame( 'resolved', $result['meta']['status'] );
		$this->assertSame( 'send_failed', $result['resolution_email']['status'] );
		$this->assertSame( '', $result['notification_sent_at']['resolved'] );
	}

	public function test_decline_uses_normal_notification_and_reason(): void {
		$id   = $this->feedback( 'new' );
		$mail = [];
		add_filter(
			'pre_wp_mail',
			static function ( $result, $atts ) use ( &$mail ) {
				$mail[] = $atts;
				return true;
			},
			10,
			2
			);
		$input  = [
			'id'             => $id,
			'status'         => 'declined',
			'decline_reason' => 'Dit past niet bij de planning.',
		];
		$result = wp_get_ability( 'rondo/update-feedback' )->execute( $input );
		$this->assertNotWPError( $result );
		$this->assertSame( 'declined', $result['meta']['status'] );
		$this->assertNotEmpty( $result['notification_sent_at']['declined'] );
		$this->assertCount( 1, $mail );
		wp_get_ability( 'rondo/update-feedback' )->execute( $input );
		$this->assertCount( 1, $mail );
	}
}
