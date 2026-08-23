<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\REST\People;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Response;

/** Regression coverage for roles shown on the admin account card. */
class LinkedUserRolesTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();

		update_option(
			UserRoles::CUSTOM_ROLES_OPTION,
			[ 'rondo_wedstrijdzaken' => 'Wedstrijdzaken' ]
		);
		add_role( 'rondo_wedstrijdzaken', 'Wedstrijdzaken', [ 'read' => true ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	protected function tear_down(): void {
		remove_role( 'rondo_wedstrijdzaken' );
		delete_option( UserRoles::CUSTOM_ROLES_OPTION );
		parent::tear_down();
	}

	public function test_account_card_data_includes_custom_rondo_role_and_label(): void {
		$user_id = $this->createRondoUser( [ 'user_login' => 'wedstrijdzaken_account' ] );
		( new \WP_User( $user_id ) )->add_role( 'rondo_wedstrijdzaken' );

		$person_id = $this->createPerson( [ 'post_title' => 'Wedstrijdsecretaris' ] );
		update_post_meta( $person_id, UserProvisioning::META_USER_ID, $user_id );

		$response = ( new People() )->add_person_computed_fields(
			new WP_REST_Response( [ 'id' => $person_id ] ),
			get_post( $person_id ),
			new WP_REST_Request( 'GET', '/wp/v2/people/' . $person_id )
		);
		$data     = $response->get_data();

		$this->assertSame( [ 'rondo_user', 'rondo_wedstrijdzaken' ], $data['linked_user_roles'] );
		$this->assertSame(
			[
				'rondo_user'           => 'Rondo User',
				'rondo_wedstrijdzaken' => 'Wedstrijdzaken',
			],
			$data['linked_user_role_labels']
		);
		$this->assertArrayNotHasKey( 'linked_user_switch_url', $data );
	}
}
