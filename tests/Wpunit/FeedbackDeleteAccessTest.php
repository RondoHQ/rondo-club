<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\REST\Feedback;
use Tests\Support\RondoTestCase;

/**
 * Feedback workflow management is administrator-only in this codebase, so the
 * read-level feedback section capability must not be enough to edit or
 * force-delete other people's feedback records.
 */
class FeedbackDeleteAccessTest extends RondoTestCase {
	private string $role;
	private int $reader_id;
	private int $author_id;
	private int $feedback_id;

	protected function set_up(): void {
		parent::set_up();
		$this->role      = UserRoles::add_custom_role( 'Feedback access test role' );
		$this->reader_id = $this->createRondoUser( [ 'role' => $this->role ] );
		get_role( $this->role )->add_cap( 'feedback' );
		UserRoles::sync_role_capabilities( $this->role );

		$this->author_id = $this->createRondoUser();
		wp_set_current_user( $this->author_id );
		$this->bootRestControllers( [ Feedback::class ] );
		add_filter( 'pre_wp_mail', '__return_true' );

		$created           = $this->request(
			'/rondo/v1/feedback',
			'POST',
			[
				'title'         => 'Te verwijderen feedback',
				'feedback_type' => 'bug',
			]
		);
		$this->feedback_id = (int) $created->get_data()['id'];
	}

	protected function tear_down(): void {
		UserRoles::remove_custom_role( $this->role );
		parent::tear_down();
	}

	private function request( string $route, string $method = 'GET', array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	public function test_section_reader_can_read_but_not_edit_or_delete(): void {
		wp_set_current_user( $this->reader_id );
		$route = '/rondo/v1/feedback/' . $this->feedback_id;

		$this->assertSame( 200, $this->request( $route )->get_status() );
		$this->assertSame( 403, $this->request( $route, 'PATCH', [ 'title' => 'Overschreven' ] )->get_status() );
		$this->assertSame( 403, $this->request( $route, 'DELETE' )->get_status() );
		$this->assertNotNull( get_post( $this->feedback_id ) );
	}

	public function test_author_can_edit_own_but_not_delete(): void {
		wp_set_current_user( $this->author_id );
		$route = '/rondo/v1/feedback/' . $this->feedback_id;

		$this->assertSame( 200, $this->request( $route, 'PATCH', [ 'title' => 'Aangepaste titel' ] )->get_status() );
		$this->assertSame( 403, $this->request( $route, 'DELETE' )->get_status() );
		$this->assertNotNull( get_post( $this->feedback_id ) );
	}

	public function test_administrator_can_delete(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$route = '/rondo/v1/feedback/' . $this->feedback_id;

		$this->assertSame( 200, $this->request( $route, 'DELETE' )->get_status() );
		$this->assertNull( get_post( $this->feedback_id ) );
	}
}
