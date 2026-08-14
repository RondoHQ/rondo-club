<?php

namespace Tests\Wpunit;

use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

/**
 * Covers one-time persisted field-identifier migration.
 */
class ListPreferenceMigrationTest extends RondoTestCase {

	public function test_all_people_list_preference_shapes_migrate_without_resetting_values(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'rondo_people_list_pref_version', 2 );
		update_user_meta( $user_id, 'rondo_people_list_preferences', [ 'team', 'knvb-id', 'datum-vog' ] );
		update_user_meta( $user_id, 'rondo_people_list_column_order', [ 'datum-vog', 'knvb-id', 'team' ] );
		update_user_meta(
			$user_id,
			'rondo_people_list_column_widths',
			[
				'knvb-id'   => 180,
				'datum-vog' => 140,
			]
		);

		$data = ( new UserSettings() )->get_list_preferences( new \WP_REST_Request( 'GET' ) )->get_data();

		$this->assertContains( 'knvb_id', $data['visible_columns'] );
		$this->assertContains( 'datum_vog', $data['visible_columns'] );
		$this->assertContains( 'characteristics', $data['visible_columns'] );
		$this->assertSame( 'datum_vog', $data['column_order'][0] );
		$this->assertSame( 180, $data['column_widths']['knvb_id'] );
		$this->assertSame( 140, $data['column_widths']['datum_vog'] );
		$this->assertSame( 4, (int) get_user_meta( $user_id, 'rondo_people_list_pref_version', true ) );
		$this->assertSame( [ 'characteristics', 'team', 'knvb_id', 'datum_vog', 'birthdate' ], get_user_meta( $user_id, 'rondo_people_list_preferences', true ) );
		$this->assertSame( 180, get_user_meta( $user_id, 'rondo_people_list_column_widths', true )['knvb_id'] );
	}
}
