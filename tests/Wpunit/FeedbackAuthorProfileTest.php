<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\REST\Feedback;
use Tests\Support\RondoTestCase;

class FeedbackAuthorProfileTest extends RondoTestCase {

	public function test_author_profile_link_respects_visibility_and_record_state(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );
		$author = $this->createRondoUser();
		$person = $this->createPerson();
		$id     = self::factory()->post->create(
			[
				'post_type'   => 'rondo_feedback',
				'post_status' => 'publish',
				'post_author' => $author,
			]
		);
		update_user_meta( $author, 'rondo_linked_person_id', $person );
		$this->bootRestControllers( [ Feedback::class ] );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/feedback/' . $id );
		$data    = rest_do_request( $request )->get_data();
		$this->assertSame( $author, $data['author']['id'] );
		$this->assertSame( $person, $data['author']['person_id'] );

		$reader = $this->createRondoUser();
		get_user_by( 'id', $reader )->add_cap( 'feedback' );
		wp_set_current_user( $reader );
		AccessControl::flush_visible_person_ids_cache();
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $response->get_data()['author']['person_id'] );

		wp_set_current_user( $admin );
		wp_trash_post( $person );
		$this->assertNull( rest_do_request( $request )->get_data()['author']['person_id'] );
		update_user_meta( $author, 'rondo_linked_person_id', $id );
		$this->assertNull( rest_do_request( $request )->get_data()['author']['person_id'] );
		delete_user_meta( $author, 'rondo_linked_person_id' );
		$this->assertNull( rest_do_request( $request )->get_data()['author']['person_id'] );
	}
}
