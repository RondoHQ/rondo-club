<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Tests for partial person-editing capabilities.
 *
 * Two capabilities grant person edit without granting full people management —
 * `sponsorbeheer` (sponsor fields) and `vrijwilligers` (contact fields) — and a
 * role can hold both. They are enforced by a single guard computing the union
 * of what the caller may write; two independent guards would each refuse what
 * the other allows, leaving a dual-capability user able to edit nothing.
 */
class PersonContactScopeTest extends RondoTestCase {

	private int $person_id;

	protected function set_up(): void {
		parent::set_up();

		do_action( 'rest_api_init' );

		$this->person_id = $this->createPerson(
			[ 'post_title' => 'Jan Jansen' ],
			[
				'first_name'  => 'Jan',
				'last_name'   => 'Jansen',
				'email_1'     => 'jan@example.com',
				'mobile_1'    => '0612345678',
				'type-lid'    => 'Senior',
				'person_type' => 'member',
			]
		);
	}

	private function update_person( array $params ) {
		$request = new WP_REST_Request( 'POST', '/wp/v2/people/' . $this->person_id );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_get_server()->dispatch( $request );
	}

	private function as_role( string $role ): int {
		$user_id = self::factory()->user->create( [ 'role' => $role ] );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	public function test_coordinator_may_correct_contact_details(): void {
		$this->as_role( 'rondo_vrijwilligers' );

		$response = $this->update_person(
			[
				'acf' => [
					'email_1'  => 'nieuw@example.com',
					'mobile_1' => '0698765432',
				],
			]
		);

		$this->assertSame( 200, $response->get_status(), 'contact fields must be writable' );
		$this->assertSame( 'nieuw@example.com', get_field( 'email_1', $this->person_id ) );
	}

	public function test_coordinator_may_not_touch_other_fields(): void {
		$this->as_role( 'rondo_vrijwilligers' );

		$response = $this->update_person( [ 'acf' => [ 'type-lid' => 'Erelid' ] ] );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rondo_person_field_scope', $response->as_error()->get_error_code() );
		$this->assertContains( 'type-lid', $response->as_error()->get_error_data()['blocked_fields'] );
		$this->assertSame( 'Senior', get_field( 'type-lid', $this->person_id ), 'value must be untouched' );
	}

	/**
	 * Person writes in this app round-trip the whole ACF object, so the guard
	 * judges what changed rather than what was sent. Without this, editing a
	 * phone number would fail because the payload also carries type-lid.
	 */
	public function test_unchanged_fields_may_ride_along_in_the_payload(): void {
		$this->as_role( 'rondo_vrijwilligers' );

		$response = $this->update_person(
			[
				'acf' => [
					'email_1'     => 'nieuw@example.com',
					'type-lid'    => 'Senior',      // unchanged
					'first_name'  => 'Jan',         // unchanged
					'person_type' => 'member',      // unchanged
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * With edit_post granted, core post fields would otherwise be wide open —
	 * post_status alone would hide a member from every list in the app.
	 */
	public function test_coordinator_may_not_change_core_post_fields(): void {
		$this->as_role( 'rondo_vrijwilligers' );

		$this->assertSame( 403, $this->update_person( [ 'status' => 'draft' ] )->get_status() );
		$this->assertSame( 403, $this->update_person( [ 'title' => 'Andere naam' ] )->get_status() );
		$this->assertSame( 'publish', get_post_status( $this->person_id ) );
	}

	public function test_coordinator_may_not_create_or_delete_people(): void {
		$this->as_role( 'rondo_vrijwilligers' );

		$create = new WP_REST_Request( 'POST', '/wp/v2/people' );
		$create->set_param( 'title', 'Nieuw persoon' );
		$create->set_param( 'acf', [ 'first_name' => 'Nieuw' ] );
		$this->assertSame( 403, rest_get_server()->dispatch( $create )->get_status() );

		$delete = new WP_REST_Request( 'DELETE', '/wp/v2/people/' . $this->person_id );
		$delete->set_param( 'force', true );
		$this->assertSame( 403, rest_get_server()->dispatch( $delete )->get_status() );
		$this->assertNotNull( get_post( $this->person_id ) );
	}

	/**
	 * The reason the sponsor guard was folded into one union guard rather than
	 * left beside a second one: each would refuse what the other allows.
	 */
	public function test_dual_capability_user_may_write_both_field_sets(): void {
		$role_slug = 'rondo_test_sponsor_and_volunteer';
		add_role(
			$role_slug,
			'Test Sponsor + Vrijwilligers',
			[
				'read'          => true,
				'sponsorbeheer' => true,
				'vrijwilligers' => true,
			]
		);
		\Rondo\Core\UserRoles::sync_role_capabilities( $role_slug );

		// A dual-role record: a member who is also a sponsor.
		update_field( 'is_sponsor', true, $this->person_id );
		update_field( 'person_type', 'member', $this->person_id );

		$this->as_role( $role_slug );

		$contact = $this->update_person( [ 'acf' => [ 'mobile_1' => '0611111111' ] ] );
		$sponsor = $this->update_person( [ 'acf' => [ 'company_name' => 'Jansen BV' ] ] );

		remove_role( $role_slug );

		$this->assertSame( 200, $contact->get_status(), 'contact fields must stay writable' );
		$this->assertSame( 200, $sponsor->get_status(), 'sponsor fields must stay writable' );
	}

	/** Sponsor managers keep exactly the boundary they had before the refactor. */
	public function test_sponsor_manager_scope_is_unchanged(): void {
		update_field( 'is_sponsor', true, $this->person_id );
		update_field( 'person_type', 'member', $this->person_id );

		$this->as_role( 'rondo_sponsorbeheerder' );

		$this->assertSame(
			200,
			$this->update_person( [ 'acf' => [ 'company_name' => 'Jansen BV' ] ] )->get_status()
		);
		$this->assertSame(
			403,
			$this->update_person( [ 'acf' => [ 'mobile_1' => '0622222222' ] ] )->get_status(),
			'a sponsor manager without vrijwilligers may not touch contact fields'
		);
	}

	public function test_sponsor_manager_still_refused_on_non_sponsor_records(): void {
		$this->as_role( 'rondo_sponsorbeheerder' );

		$this->assertSame(
			403,
			$this->update_person( [ 'acf' => [ 'company_name' => 'Jansen BV' ] ] )->get_status()
		);
	}

	public function test_former_members_stay_read_only_for_coordinators(): void {
		update_field( 'former_member', true, $this->person_id );
		$this->as_role( 'rondo_vrijwilligers' );

		$response = $this->update_person( [ 'acf' => [ 'email_1' => 'oud@example.com' ] ] );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'rondo_former_member_readonly', $response->as_error()->get_error_code() );
	}

	/**
	 * Photos are part of the coordinator grant: someone maintaining their
	 * players' records updates photos as routinely as phone numbers.
	 */
	public function test_photo_route_is_open_to_coordinators_but_not_to_members(): void {
		$coordinator_id = $this->as_role( 'rondo_vrijwilligers' );
		$this->assertTrue(
			AccessControl::can_edit_person( $this->person_id, $coordinator_id ),
			'coordinators reach the photo route'
		);

		$member_id = $this->createRondoUser();
		$this->assertFalse(
			AccessControl::can_edit_person( $this->person_id, $member_id ),
			'plain members do not'
		);
	}

	/**
	 * The guard rail for the whole design: "may correct contact details" must
	 * never quietly become "is a people manager".
	 */
	public function test_contact_grant_does_not_imply_full_people_management(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'rondo_vrijwilligers' ] );

		$this->assertTrue( AccessControl::can_edit_person_contact( $user_id ) );
		$this->assertFalse( AccessControl::can_edit_people( $user_id ) );
		$this->assertFalse( AccessControl::can_delete_person( $this->person_id, $user_id ) );
	}
}
