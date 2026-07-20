<?php

namespace Tests\Wpunit;

use Rondo\REST\Api;
use Tests\Support\RondoTestCase;

/**
 * Covers exact person-email lookup used by rondo-sync parent resolution.
 */
class PersonEmailLookupTest extends RondoTestCase {

	private Api $controller;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new Api();
	}

	public function test_lookup_returns_published_and_trashed_matches(): void {
		$published_id = $this->createPerson(
			[ 'post_title' => 'Published child' ],
			[ 'email_1' => 'family@example.com' ]
		);
		$trashed_id   = $this->createPerson(
			[ 'post_title' => 'Trashed parent' ],
			[ 'email_1' => 'family@example.com' ]
		);
		wp_trash_post( $trashed_id );

		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'email', 'family@example.com' );

		$data = $this->controller->find_person_by_email( $request )->get_data();

		$this->assertSame( $published_id, $data['id'] );
		$this->assertSame(
			[
				[
					'id'     => $published_id,
					'status' => 'publish',
				],
				[
					'id'     => $trashed_id,
					'status' => 'trash',
				],
			],
			$data['matches']
		);
	}

	public function test_lookup_uses_trashed_match_as_legacy_id_when_no_published_match_exists(): void {
		$trashed_id = $this->createPerson(
			[ 'post_title' => 'Returning parent' ],
			[ 'email_1' => 'returning@example.com' ]
		);
		wp_trash_post( $trashed_id );

		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'email', 'returning@example.com' );

		$data = $this->controller->find_person_by_email( $request )->get_data();

		$this->assertSame( $trashed_id, $data['id'] );
		$this->assertSame( 'trash', $data['status'] );
	}

	public function test_standard_rest_update_restores_a_trashed_person(): void {
		$trashed_id = $this->createPerson(
			[ 'post_title' => 'REST-restored parent' ],
			[ 'email_1' => 'restored@example.com' ]
		);
		wp_trash_post( $trashed_id );

		$request = new \WP_REST_Request( 'PUT', '/wp/v2/people/' . $trashed_id );
		$request->set_param( 'id', $trashed_id );
		$request->set_param( 'status', 'publish' );

		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'publish', get_post_status( $trashed_id ) );
	}
}
