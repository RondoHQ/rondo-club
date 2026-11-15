<?php
namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Core\WordPressAccess;
use Rondo\Collaboration\CommentTypes;
use Rondo\Fields\Fields;
use Rondo\REST\People;
use Rondo\REST\Vog;
use Tests\Support\RondoTestCase;

class WordPressAccessTest extends RondoTestCase {
	protected function set_up(): void {
		parent::set_up();
		( new \Rondo\Core\PostTypes() )->register_post_types();
		( new \Rondo\Fields\FieldSchema() )->register();
		$this->bootRestControllers( [ People::class, Vog::class, CommentTypes::class ] );
	}

	public function test_core_comments_are_denied_for_all_non_admin_methods_and_case_variants(): void {
		$person  = $this->createPerson();
		$comment = self::factory()->comment->create(
			[
				'comment_post_ID'  => $person,
				'comment_approved' => 1,
				'comment_content'  => 'Private fixture',
				'comment_type'     => CommentTypes::TYPE_NOTE,
			]
			);
		foreach ( [ 0, $this->createRondoUser(), self::factory()->user->create( [ 'role' => 'rondo_fairplay' ] ) ] as $user ) {
			wp_set_current_user( $user );
			foreach ( [ 'GET', 'HEAD', 'OPTIONS', 'POST', 'PUT', 'DELETE' ] as $method ) {
				foreach ( [ '/wp/v2/comments', '/WP/v2/Comments/' . $comment ] as $route ) {
					$response = rest_do_request( new \WP_REST_Request( $method, $route ) );
					$this->assertSame( $user ? 403 : 401, $response->get_status(), $method . $route );
				}
			}
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/comments/' . $comment ) );
		$this->assertSame( 200, $response->get_status() );
		foreach ( [ 'email_content_snapshot', 'email_recipient', 'activity_date', '_note_visibility' ] as $key ) {
			$this->assertArrayNotHasKey( $key, (array) ( $response->get_data()['meta'] ?? [] ) );
		}
	}

	public function test_custom_notes_preserve_private_visibility_and_historic_email_html_is_sanitized(): void {
		$author  = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );
		$other   = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );
		$person  = $this->createPerson();
		$private = self::factory()->comment->create(
			[
				'comment_post_ID'  => $person,
				'user_id'          => $author,
				'comment_type'     => CommentTypes::TYPE_NOTE,
				'comment_approved' => 1,
			]
			);
		update_comment_meta( $private, '_note_visibility', 'private' );
		$shared = self::factory()->comment->create(
			[
				'comment_post_ID'  => $person,
				'user_id'          => $author,
				'comment_type'     => CommentTypes::TYPE_NOTE,
				'comment_approved' => 1,
			]
			);
		update_comment_meta( $shared, '_note_visibility', 'shared' );
		$email = self::factory()->comment->create(
			[
				'comment_post_ID'  => $person,
				'comment_type'     => CommentTypes::TYPE_EMAIL,
				'comment_approved' => 1,
			]
			);
		update_comment_meta( $email, 'email_content_snapshot', '<p>Safe</p><script>alert(1)</script><img src="x" onerror="alert(1)"><a href="javascript:alert(1)">Link</a>' );
		wp_set_current_user( $other );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/people/' . $person . '/timeline' );
		$request->set_param( 'person_id', $person );
		$data = ( new CommentTypes() )->get_timeline( $request )->get_data();
		$this->assertNotContains( $private, array_column( $data, 'id' ) );
		$this->assertContains( $shared, array_column( $data, 'id' ) );
		$rows = array_values( array_filter( $data, fn( $row ) => $row['type'] === 'email' ) );
		$this->assertCount( 1, $rows );
		$html = $rows[0]['email_content_snapshot'];
		$this->assertStringContainsString( '<p>Safe</p>', $html );
		foreach ( [ '<script', 'onerror', 'javascript:' ] as $unsafe ) {
			$this->assertStringNotContainsString( $unsafe, $html );
		}
		wp_set_current_user( $author );
		$this->assertContains( $private, array_column( ( new CommentTypes() )->get_notes( $request )->get_data(), 'id' ) );
	}

	public function test_sensitive_and_raw_meta_writes_fail_before_core_mutation(): void {
		$person = $this->createPerson( [], [ 'first_name' => 'Before' ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_fairplay' ] ) );
		foreach ( [ [ 'fields' => [ 'financiele_blokkade' => true ] ], [ 'fields' => [ 'datum_vog' => '2026-01-01' ] ], [ 'meta' => [ 'iva-approved' => true ] ] ] as $payload ) {
			$before  = get_post( $person )->post_title;
			$request = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person );
			$request->set_body_params( array_merge( [ 'title' => 'Must not save' ], $payload ) );
			$response = rest_do_request( $request );
			$this->assertSame( 403, $response->get_status(), wp_json_encode( $response->get_data() ) );
			$this->assertSame( $before, get_post( $person )->post_title );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person );
		$request->set_body_params(
			[
				'fields' => [
					'datum_vog'          => '2026-01-01',
					'vrijwilliger_sinds' => '2020-01-02',
					'first_name'         => 'After',
				],
				'meta'   => [ 'team' => 'Synthetic team' ],
			]
			);
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( 'After', Fields::get_for_post( $person, 'first_name' ) );
		$this->assertSame( 'Synthetic team', get_post_meta( $person, 'team', true ) );
		$this->assertSame( '20200102', get_post_meta( $person, 'vrijwilliger-sinds', true ) );
	}

	public function test_former_member_noop_fields_cannot_smuggle_core_changes(): void {
		$person = $this->createPerson(
			[],
			[
				'former_member' => true,
				'first_name'    => 'Before',
			]
			);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_fairplay' ] ) );
		foreach ( [ [ 'title' => 'Changed' ], [ 'status' => 'draft' ], [ 'featured_media' => 0 ], [ 'meta' => [] ] ] as $payload ) {
			$request = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person );
			$request->set_body_params( array_merge( [ 'fields' => [ 'first_name' => 'Before' ] ], $payload ) );
			$this->assertSame( 403, rest_do_request( $request )->get_status() );
		}
		$this->assertSame( 'publish', get_post_status( $person ) );
	}

	public function test_vog_scope_matches_single_records_media_and_bulk_writes(): void {
		$person    = $this->createPerson();
		$volunteer = $this->createPerson();
		update_post_meta( $volunteer, 'huidig-vrijwilliger', '1' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_vog' ] ) );
		$this->assertFalse( AccessControl::can_view_person( $person ) );
		$this->assertFalse( AccessControl::can_edit_person( $person ) );
		$this->assertTrue( AccessControl::can_view_person( $volunteer ) );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/people/' . $person ) )->get_status() );
		$this->assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/people/' . $volunteer ) )->get_status() );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/vog/bulk-mark-justis' );
		$request->set_param( 'ids', [ $volunteer, $person ] );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
		$this->assertEmpty( get_post_meta( $volunteer, 'vog_justis_submitted_date', true ) );
		$request->set_param( 'ids', [ $volunteer ] );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertNotEmpty( get_post_meta( $volunteer, 'vog_justis_submitted_date', true ) );
	}

	public function test_finance_write_is_distinct_from_read_and_preserves_fee_exemptions(): void {
		$person = $this->createPerson(
			[],
			[
				'first_name' => 'Test',
				'last_name'  => 'Fixture',
			]
			);
		$reader = self::factory()->user->create( [ 'role' => 'rondo_financieel_lezen' ] );
		wp_set_current_user( $reader );
		$this->assertFalse( WordPressAccess::can_write_field( 'financiele_blokkade', $person ) );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_financieel' ] ) );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person );
		$request->set_body_params(
			[
				'fields' => [ 'financiele_blokkade' => true ],
				'meta'   => [ '_exclude_from_contributie' => true ],
			]
			);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertTrue( (bool) Fields::get_for_post( $person, 'financiele_blokkade' ) );
		$this->assertTrue( (bool) get_post_meta( $person, '_exclude_from_contributie', true ) );
	}

	public function test_xmlrpc_exposes_no_methods(): void {
		$this->assertFalse( apply_filters( 'xmlrpc_enabled', true ) );
		$this->assertSame(
			[],
			apply_filters(
			'xmlrpc_methods',
			[
				'wp.getPost'    => 'callback',
				'pingback.ping' => 'callback',
			]
			)
			);
	}
}
