<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Tests\Support\RondoTestCase;

/**
 * Tests for AccessControl::can_view_person() — the single authority on person visibility.
 *
 * Three tiers: management sees everyone, coordinators see their configured age groups,
 * plain members see only themselves and their minor children.
 */
class PersonVisibilityTest extends RondoTestCase {

	private const TYPE_CHILD = 3;

	protected function set_up(): void {
		parent::set_up();
		AccessControl::flush_visible_person_ids_cache();
		delete_option( 'rondo_age_group_access' );
	}

	private function person( string $name, ?string $birthdate = null, ?string $leeftijdsgroep = null ): int {
		$person_id = $this->createPerson( [ 'post_title' => $name ] );
		if ( $birthdate !== null ) {
			update_post_meta( $person_id, 'birthdate', $birthdate );
		}
		if ( $leeftijdsgroep !== null ) {
			update_post_meta( $person_id, 'leeftijdsgroep', $leeftijdsgroep );
		}
		return $person_id;
	}

	/** ACF date_picker stores Ymd. */
	private function years_ago( int $years ): string {
		return gmdate( 'Ymd', strtotime( "-{$years} years" ) );
	}

	private function add_child( int $parent_id, int $child_id ): void {
		$rels   = get_field( 'relationships', $parent_id ) ?: [];
		$rels[] = [
			'related_person'    => $child_id,
			'relationship_type' => self::TYPE_CHILD,
		];
		update_field( 'relationships', $rels, $parent_id );
		AccessControl::flush_visible_person_ids_cache();
	}

	private function member_linked_to( int $person_id, string $login ): int {
		$user_id = $this->createRondoUser( [ 'user_login' => $login ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		AccessControl::flush_visible_person_ids_cache();
		return $user_id;
	}

	// ---------------------------------------------------------------- members

	public function test_member_sees_their_own_record(): void {
		$me      = $this->person( 'Ik' );
		$user_id = $this->member_linked_to( $me, 'member_self' );

		$this->assertTrue( AccessControl::can_view_person( $me, $user_id ) );
	}

	public function test_member_sees_their_minor_child(): void {
		$me    = $this->person( 'Ouder' );
		$child = $this->person( 'Kind', $this->years_ago( 12 ) );
		$this->add_child( $me, $child );
		$user_id = $this->member_linked_to( $me, 'member_parent' );

		$this->assertTrue( AccessControl::can_view_person( $child, $user_id ) );
	}

	public function test_member_does_not_see_their_adult_child(): void {
		$me    = $this->person( 'Ouder' );
		$child = $this->person( 'Volwassen kind', $this->years_ago( 25 ) );
		$this->add_child( $me, $child );
		$user_id = $this->member_linked_to( $me, 'member_adult_child' );

		$this->assertFalse(
			AccessControl::can_view_person( $child, $user_id ),
			'An adult child holds their own account; their record is not their parent\'s to read'
		);
	}

	public function test_a_child_turning_eighteen_falls_out_of_view(): void {
		$me    = $this->person( 'Ouder' );
		$child = $this->person( 'Kind', $this->years_ago( 18 ) );
		$this->add_child( $me, $child );
		$user_id = $this->member_linked_to( $me, 'member_edge_18' );

		$this->assertFalse( AccessControl::can_view_person( $child, $user_id ), '18 is the cut-off, exclusive' );
	}

	public function test_a_child_with_no_birthdate_is_not_visible(): void {
		$me    = $this->person( 'Ouder' );
		$child = $this->person( 'Kind zonder geboortedatum' );
		$this->add_child( $me, $child );
		$user_id = $this->member_linked_to( $me, 'member_no_bd' );

		$this->assertFalse(
			AccessControl::can_view_person( $child, $user_id ),
			'Fail closed: a missing birthdate must not expose a record'
		);
	}

	public function test_member_does_not_see_a_stranger(): void {
		$me       = $this->person( 'Ik' );
		$stranger = $this->person( 'Onbekende', $this->years_ago( 12 ) );
		$user_id  = $this->member_linked_to( $me, 'member_stranger' );

		$this->assertFalse( AccessControl::can_view_person( $stranger, $user_id ) );
	}

	public function test_member_does_not_see_someone_elses_child(): void {
		$me           = $this->person( 'Ik' );
		$other_parent = $this->person( 'Andere ouder' );
		$other_child  = $this->person( 'Ander kind', $this->years_ago( 10 ) );
		$this->add_child( $other_parent, $other_child );
		$user_id = $this->member_linked_to( $me, 'member_other_child' );

		$this->assertFalse( AccessControl::can_view_person( $other_child, $user_id ) );
	}

	public function test_member_without_a_linked_person_sees_nobody(): void {
		$stranger = $this->person( 'Iemand' );
		$user_id  = $this->createRondoUser( [ 'user_login' => 'member_unlinked' ] );
		AccessControl::flush_visible_person_ids_cache();

		$this->assertSame( [], AccessControl::get_visible_person_ids( $user_id ) );
		$this->assertFalse( AccessControl::can_view_person( $stranger, $user_id ) );
	}

	// ----------------------------------------------------------- coordinators

	public function test_coordinator_sees_only_their_configured_age_groups(): void {
		$in_group  = $this->person( 'Pupil', null, 'Onder 11' );
		$out_group = $this->person( 'Senior', null, 'Senioren' );

		$user_id = $this->createRondoUser( [ 'user_login' => 'coordinator' ] );
		update_option( 'rondo_age_group_access', [ 'rondo_user' => [ 'Onder 11' ] ] );

		$this->assertTrue( AccessControl::can_view_person( $in_group, $user_id ) );
		$this->assertFalse( AccessControl::can_view_person( $out_group, $user_id ) );
	}

	// ------------------------------------------------------------- management

	public function test_administrator_sees_everyone(): void {
		$anyone  = $this->person( 'Wie dan ook', $this->years_ago( 40 ) );
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );

		$this->assertTrue( AccessControl::can_view_person( $anyone, $user_id ) );
		$this->assertFalse( AccessControl::is_scoped_member( $user_id ) );
	}

	public function test_logged_out_sees_nobody(): void {
		$anyone = $this->person( 'Wie dan ook' );
		wp_set_current_user( 0 );

		$this->assertFalse( AccessControl::can_view_person( $anyone ) );
	}

	// -------------------------------------------------- notes / comments guard

	public function test_user_can_access_post_obeys_person_visibility(): void {
		$me       = $this->person( 'Ik' );
		$stranger = $this->person( 'Onbekende' );
		$user_id  = $this->member_linked_to( $me, 'member_notes' );

		$access = new AccessControl();

		$this->assertTrue( $access->user_can_access_post( $me, $user_id ) );
		$this->assertFalse(
			$access->user_can_access_post( $stranger, $user_id ),
			'Notes and activities on a stranger must not be reachable'
		);
	}

	// ------------------------------------------------------------ field scope

	public function test_financial_flags_are_stripped_for_scoped_members(): void {
		$acf = [
			'first_name'              => 'Jan',
			'email_1'                 => 'jan@example.com',
			'financiele-blokkade'     => true,
			'wacht_op_overschrijving' => true,
			'freescout-id'            => 41822,
		];

		$filtered = AccessControl::filter_member_visible_acf( $acf );

		$this->assertSame( 'Jan', $filtered['first_name'] );
		$this->assertSame( 'jan@example.com', $filtered['email_1'] );
		$this->assertArrayNotHasKey( 'financiele-blokkade', $filtered );
		$this->assertArrayNotHasKey( 'wacht_op_overschrijving', $filtered );
		$this->assertArrayNotHasKey( 'freescout-id', $filtered );
	}

	public function test_an_unknown_future_acf_field_is_private_by_default(): void {
		$filtered = AccessControl::filter_member_visible_acf( [ 'some_new_sensitive_field' => 'secret' ] );

		$this->assertSame( [], $filtered, 'The allowlist must fail closed for fields added later' );
	}

	// ------------------------------------------------------------- REST layer

	/**
	 * The predicate is only worth as much as its enforcement. These drive the actual
	 * REST filters (`rest_person_query` and `rest_prepare_person`).
	 */
	public function test_rest_collection_returns_only_the_members_household(): void {
		$me    = $this->person( 'Ik' );
		$child = $this->person( 'Kind', $this->years_ago( 10 ) );
		$this->add_child( $me, $child );
		$this->person( 'Onbekende', $this->years_ago( 30 ) );

		$user_id = $this->member_linked_to( $me, 'member_rest_list' );
		wp_set_current_user( $user_id );

		$response = rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/people' ) );
		$ids      = wp_list_pluck( $response->get_data(), 'id' );

		sort( $ids );
		$expected = [ $me, $child ];
		sort( $expected );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $expected, $ids, 'A member sees exactly themselves and their minor child' );
	}

	public function test_rest_single_forbids_a_stranger(): void {
		$me       = $this->person( 'Ik' );
		$stranger = $this->person( 'Onbekende' );

		$user_id = $this->member_linked_to( $me, 'member_rest_single' );
		wp_set_current_user( $user_id );

		$response = rest_do_request( new \WP_REST_Request( 'GET', "/wp/v2/people/{$stranger}" ) );

		$this->assertSame( 403, $response->get_status() );
	}

	public function test_rest_single_strips_financial_flags_for_a_member(): void {
		$me = $this->person( 'Ik' );
		update_field( 'financiele-blokkade', true, $me );
		update_field( 'email_1', 'ik@example.com', $me );

		$user_id = $this->member_linked_to( $me, 'member_rest_acf' );
		wp_set_current_user( $user_id );

		$response = rest_do_request( new \WP_REST_Request( 'GET', "/wp/v2/people/{$me}" ) );
		$acf      = $response->get_data()['acf'] ?? [];

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'email_1', $acf );
		$this->assertArrayNotHasKey( 'financiele-blokkade', $acf, 'A member must not read their own payment block' );
	}

	public function test_rest_single_keeps_the_full_payload_for_an_administrator(): void {
		$someone = $this->person( 'Iemand' );
		update_field( 'financiele-blokkade', true, $someone );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$response = rest_do_request( new \WP_REST_Request( 'GET', "/wp/v2/people/{$someone}" ) );
		$acf      = $response->get_data()['acf'] ?? [];

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'financiele-blokkade', $acf, 'Management must still see everything' );
	}
}
