<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Tests for the notes/activities/timeline capability surface.
 *
 * Notes are free prose and routinely carry finance and membership matters, so
 * no field-level redaction can protect them. They are gated on their own
 * predicate rather than inherited from "may I see this person".
 *
 * The timeline route is deliberately *not* gated the same way: it also carries
 * todos, which have their own author/assignee visibility, so a user without
 * notes access still reaches their own tasks — with the prose omitted.
 */
class PersonNotesAccessTest extends RondoTestCase {

	private int $person_id;

	protected function set_up(): void {
		parent::set_up();

		do_action( 'rest_api_init' );
		$this->person_id = $this->createPerson( [ 'post_title' => 'Jan Jansen' ] );
	}

	private function get( string $route ) {
		return rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) );
	}

	private function notes_route(): string {
		return '/rondo/v1/people/' . $this->person_id . '/notes';
	}

	private function timeline_route(): string {
		return '/rondo/v1/people/' . $this->person_id . '/timeline';
	}

	/** Roles that keep access, and the reason they need it. */
	public function test_ledenadministratie_and_finance_keep_notes_access(): void {
		foreach ( [ 'administrator', 'rondo_ledenadministratie', 'rondo_financieel', 'rondo_financieel_lezen' ] as $role ) {
			$user_id = self::factory()->user->create( [ 'role' => $role ] );
			wp_set_current_user( $user_id );

			$this->assertTrue(
				AccessControl::can_access_person_notes( $user_id ),
				$role . ' must keep notes access'
			);
			$this->assertSame( 200, $this->get( $this->notes_route() )->get_status(), $role . ' GET notes' );
		}
	}

	/** Everyone else loses it — read and write alike. */
	public function test_other_roles_are_refused_notes_and_activities(): void {
		$roles = [ 'rondo_vrijwilligers', 'rondo_fairplay', 'rondo_vog', 'rondo_sponsorbeheerder', 'rondo_user' ];

		foreach ( $roles as $role ) {
			$user_id = self::factory()->user->create( [ 'role' => $role ] );
			wp_set_current_user( $user_id );

			$this->assertFalse(
				AccessControl::can_access_person_notes( $user_id ),
				$role . ' must not have notes access'
			);

			$this->assertSame(
				403,
				$this->get( $this->notes_route() )->get_status(),
				$role . ' GET notes must be refused'
			);
			$this->assertSame(
				403,
				$this->get( '/rondo/v1/people/' . $this->person_id . '/activities' )->get_status(),
				$role . ' GET activities must be refused'
			);

			$create = new WP_REST_Request( 'POST', $this->notes_route() );
			$create->set_param( 'content', 'Poging tot notitie' );
			$this->assertSame(
				403,
				rest_get_server()->dispatch( $create )->get_status(),
				$role . ' POST note must be refused'
			);
		}
	}

	/** A logged-out request never reaches notes. */
	public function test_anonymous_request_is_refused(): void {
		wp_set_current_user( 0 );

		$this->assertFalse( AccessControl::can_access_person_notes( 0 ) );
		$this->assertSame( 401, $this->get( $this->notes_route() )->get_status() );
	}

	/**
	 * A member could previously read staff-written "shared" notes about
	 * themselves, because they pass the person-visibility check on their own
	 * record. Staff notes were never meant for the member's eyes.
	 */
	public function test_member_cannot_read_notes_on_their_own_record(): void {
		$user_id = $this->createRondoUser();
		update_user_meta( $user_id, 'rondo_linked_person_id', $this->person_id );
		wp_set_current_user( $user_id );
		AccessControl::flush_visible_person_ids_cache();

		$access_control = new AccessControl();
		$this->assertTrue(
			$access_control->user_can_access_post( $this->person_id ),
			'precondition: the member can still see their own person record'
		);

		$this->assertSame( 403, $this->get( $this->notes_route() )->get_status() );
	}

	/**
	 * The timeline still answers for users without notes access, because it
	 * also carries todos. It just returns no notes, activities or e-mails.
	 */
	public function test_timeline_stays_reachable_but_omits_prose(): void {
		$author_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $author_id );

		$create = new WP_REST_Request( 'POST', $this->notes_route() );
		$create->set_param( 'content', 'Vertrouwelijke notitie' );
		$create->set_param( 'visibility', 'shared' );
		$this->assertSame( 200, rest_get_server()->dispatch( $create )->get_status() );

		$admin_timeline = $this->get( $this->timeline_route() );
		$this->assertSame( 200, $admin_timeline->get_status() );
		$this->assertNotEmpty(
			array_filter( (array) $admin_timeline->get_data(), fn( $item ) => ( $item['type'] ?? '' ) === 'note' ),
			'precondition: the note is on the timeline for someone who may read it'
		);

		$coordinator_id = self::factory()->user->create( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $coordinator_id );

		$response = $this->get( $this->timeline_route() );
		$this->assertSame(
			200,
			$response->get_status(),
			'the timeline must not 403 — it carries todos, which have their own visibility'
		);
		$this->assertSame(
			[],
			array_filter( (array) $response->get_data(), fn( $item ) => in_array( $item['type'] ?? '', [ 'note', 'activity', 'email' ], true ) ),
			'no prose may survive for a user without notes access'
		);
	}

	/** Editing your own note is still author-or-admin; unchanged behaviour. */
	public function test_comment_edit_access_is_unchanged(): void {
		$author_id = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );
		wp_set_current_user( $author_id );

		$create = new WP_REST_Request( 'POST', $this->notes_route() );
		$create->set_param( 'content', 'Eigen notitie' );
		$created = rest_get_server()->dispatch( $create );
		$this->assertSame( 200, $created->get_status() );
		$note_id = $created->get_data()['id'];

		$other_id = self::factory()->user->create( [ 'role' => 'rondo_ledenadministratie' ] );
		wp_set_current_user( $other_id );

		$update = new WP_REST_Request( 'PUT', '/rondo/v1/notes/' . $note_id );
		$update->set_param( 'content', 'Andermans notitie' );
		$this->assertSame( 403, rest_get_server()->dispatch( $update )->get_status() );
	}
}
