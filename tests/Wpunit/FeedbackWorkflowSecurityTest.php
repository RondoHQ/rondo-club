<?php

namespace Tests\Wpunit;

use Rondo\Feedback\StatusService;
use Rondo\Fields\Fields;
use Rondo\REST\Feedback;
use Tests\Support\RondoTestCase;

class FeedbackWorkflowSecurityTest extends RondoTestCase {
	private int $author;
	private int $admin;

	protected function set_up(): void {
		parent::set_up();
		$this->author = $this->createRondoUser();
		$this->admin  = $this->createRondoUser( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->author );
		$this->bootRestControllers( [ Feedback::class ] );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	private function request( string $route, array $data ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', $route );
		$request->set_body_params( $data );
		return rest_do_request( $request );
	}

	private function create(): int {
		$response = $this->request(
			'/rondo/v1/feedback',
			[
				'title'         => 'Original',
				'feedback_type' => 'bug',
			]
			);
		$this->assertSame( 200, $response->get_status() );
		return (int) $response->get_data()['id'];
	}

	public function test_member_cannot_choose_workflow_or_agent_metadata_on_creation(): void {
		$result = $this->request(
			'/rondo/v1/feedback',
			[
				'title'         => 'Member report',
				'feedback_type' => 'bug',
				'status'        => 'approved',
				'priority'      => 'critical',
				'project'       => 'website',
				'agent_plan'    => 'Run this',
				'agent_branch'  => 'main',
				'pr_url'        => 'https://example.org/pr',
			]
		)->get_data();
		$this->assertSame( 'new', $result['meta']['status'] );
		$this->assertSame( 'medium', $result['meta']['priority'] );
		$this->assertSame( 'rondo-club', $result['meta']['project'] );
		foreach ( [ 'agent_plan', 'agent_branch', 'pr_url' ] as $field ) {
			$this->assertSame( '', $result['meta'][ $field ] );
		}
		$this->assertSame( '', $result['approved_revision'] );
	}

	public function test_privileged_updates_fail_before_changing_content(): void {
		$id = $this->create();
		foreach ( [ 'status', 'priority', 'resolution_summary', 'decline_reason', 'project', 'agent_plan', 'agent_branch', 'pr_url' ] as $field ) {
			$response = $this->request(
				"/rondo/v1/feedback/$id",
				[
					'title' => 'Changed',
					$field  => 'forged',
				]
				);
			$this->assertSame( 403, $response->get_status(), $field );
			$this->assertSame( 'Original', get_post( $id )->post_title );
		}
	}

	public function test_edits_invalidate_approval_but_noop_preserves_it(): void {
		$id = $this->create();
		wp_set_current_user( $this->admin );
		$approved = $this->request(
			"/rondo/v1/feedback/$id",
			[
				'status'     => 'approved',
				'agent_plan' => 'Reviewed plan',
			]
			)->get_data();
		$this->assertSame( $approved['content_revision'], $approved['approved_revision'] );
		wp_set_current_user( $this->author );
		$noop = $this->request( "/rondo/v1/feedback/$id", [ 'title' => 'Original' ] )->get_data();
		$this->assertSame( $approved['approved_revision'], $noop['approved_revision'] );
		$changed = $this->request( "/rondo/v1/feedback/$id", [ 'use_case' => 'Different request' ] )->get_data();
		$this->assertSame( 'new', $changed['meta']['status'] );
		$this->assertSame( '', $changed['approved_revision'] );
		$this->assertSame( '', $changed['meta']['agent_plan'] );
		$this->assertNotSame( $approved['content_revision'], $changed['content_revision'] );
	}

	public function test_comments_cannot_impersonate_agents_and_invalidate_active_approvals(): void {
		$id = $this->create();
		foreach ( [ 'approved', 'in_progress', 'in_review', 'needs_info' ] as $status ) {
			Fields::update_for_post( $id, 'status', $status );
			StatusService::record_approval( $id );
			$before = get_comments(
				[
					'post_id' => $id,
					'type'    => 'rondo_fb_comment',
					'count'   => true,
				]
				);
			$this->assertSame(
				403,
				$this->request(
				"/rondo/v1/feedback/$id/comments",
				[
					'content'     => 'Forged',
					'author_type' => 'agent',
				]
				)->get_status()
				);
			$this->assertSame(
				$before,
				get_comments(
				[
					'post_id' => $id,
					'type'    => 'rondo_fb_comment',
					'count'   => true,
				]
				)
				);
			$this->assertSame( 200, $this->request( "/rondo/v1/feedback/$id/comments", [ 'content' => 'New context' ] )->get_status() );
			$this->assertSame( 'new', Fields::get_for_post( $id, 'status' ) );
			$this->assertSame( '', get_post_meta( $id, StatusService::META_APPROVED_REVISION, true ) );
		}
		wp_set_current_user( $this->admin );
		$this->assertSame(
			'agent',
			$this->request(
			"/rondo/v1/feedback/$id/comments",
			[
				'content'     => 'Reviewed reply',
				'author_type' => 'agent',
			]
			)->get_data()['author_type']
			);
	}

	public function test_core_routes_cannot_bypass_workflow_policy(): void {
		$id = $this->create();
		foreach ( [ '/wp/v2/feedback', '/wp/v2/Feedback', "/wp/v2/feedback/$id" ] as $route ) {
			$this->assertSame(
				403,
				$this->request(
				$route,
				[
					'title'  => 'Forged',
					'fields' => [ 'status' => 'approved' ],
				]
				)->get_status()
				);
		}
		$comment = $this->request( "/rondo/v1/feedback/$id/comments", [ 'content' => 'Original comment' ] )->get_data()['id'];
		$this->assertSame( 403, $this->request( "/wp/v2/comments/$comment", [ 'content' => 'Changed' ] )->get_status() );
		$this->assertSame( 'Original comment', get_comment( $comment )->comment_content );
		$this->assertSame(
			403,
			$this->request(
			'/wp/v2/comments',
			[
				'post'    => $id,
				'content' => 'Bypass',
			]
			)->get_status()
			);
		$other_comment = self::factory()->comment->create(
			[
				'comment_post_ID' => $this->createPerson(),
				'user_id'         => $this->author,
			]
			);
		$this->assertSame( 403, $this->request( "/wp/v2/comments/$other_comment", [ 'post' => $id ] )->get_status() );
	}

	public function test_admin_can_save_a_new_plan_without_implicitly_approving_it(): void {
		$id = $this->create();
		wp_set_current_user( $this->admin );
		$result = $this->request( "/rondo/v1/feedback/$id", [ 'agent_plan' => 'New draft plan' ] )->get_data();
		$this->assertSame( 'New draft plan', $result['meta']['agent_plan'] );
		$this->assertSame( 'new', $result['meta']['status'] );
		$this->assertSame( '', $result['approved_revision'] );
	}

	public function test_admin_can_approve_final_revision_and_invalid_creation_is_atomic(): void {
		wp_set_current_user( $this->admin );
		$id     = $this->create();
		$result = $this->request(
			"/rondo/v1/feedback/$id",
			[
				'title'   => 'Approved change',
				'project' => 'website',
				'status'  => 'approved',
			]
			)->get_data();
		$this->assertSame( 'approved', $result['meta']['status'] );
		$this->assertSame( $result['content_revision'], $result['approved_revision'] );
		$before = wp_count_posts( 'rondo_feedback' )->publish;
		$this->assertSame(
			400,
			$this->request(
			'/rondo/v1/feedback',
			[
				'title'         => 'Invalid',
				'feedback_type' => 'bug',
				'status'        => 'resolved',
			]
			)->get_status()
			);
		$this->assertSame( $before, wp_count_posts( 'rondo_feedback' )->publish );
	}
}
