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
		$this->assertSame( 'first_name', $data['column_order'][0] );
		$this->assertSame( 'last_name', $data['column_order'][1] );
		$this->assertSame( 'company_name', $data['column_order'][2] );
		$this->assertSame( 180, $data['column_widths']['knvb_id'] );
		$this->assertSame( 140, $data['column_widths']['datum_vog'] );
		$this->assertSame( 5, (int) get_user_meta( $user_id, 'rondo_people_list_pref_version', true ) );
		$this->assertSame( [ 'first_name', 'last_name', 'company_name', 'characteristics', 'team', 'knvb_id', 'datum_vog', 'birthdate' ], get_user_meta( $user_id, 'rondo_people_list_preferences', true ) );
		$this->assertSame( 180, get_user_meta( $user_id, 'rondo_people_list_column_widths', true )['knvb_id'] );
	}

	public function test_legacy_combined_name_width_is_split_between_name_columns(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $user_id );
		update_user_meta( $user_id, 'rondo_people_list_pref_version', 4 );
		update_user_meta( $user_id, 'rondo_people_list_preferences', [ 'characteristics', 'team' ] );
		update_user_meta( $user_id, 'rondo_people_list_column_order', [ 'name', 'team', 'characteristics' ] );
		update_user_meta( $user_id, 'rondo_people_list_column_widths', [ 'name' => 240 ] );

		$data = ( new UserSettings() )->get_list_preferences( new \WP_REST_Request( 'GET' ) )->get_data();

		$this->assertSame( [ 'first_name', 'last_name', 'company_name' ], array_slice( $data['column_order'], 0, 3 ) );
		$this->assertSame( 120, $data['column_widths']['first_name'] );
		$this->assertSame( 150, $data['column_widths']['last_name'] );
		$this->assertArrayNotHasKey( 'name', $data['column_widths'] );
	}
}
