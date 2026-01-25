<?php
/**
 * Integration tests for CustomFields Manager.
 *
 * Tests CRUD operations for ACF custom field definitions.
 *
 * @package Stadion\Tests\CustomFields
 */

namespace Tests\Wpunit\CustomFields;

use Stadion\CustomFields\Manager;
use Tests\Support\StadionTestCase;
use WP_Error;

/**
 * Test class for CustomFields Manager.
 */
class ManagerTest extends StadionTestCase {

	/**
	 * Manager instance.
	 *
	 * @var Manager
	 */
	private Manager $manager;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->manager = new Manager();
		$this->cleanup_test_fields();
	}

	/**
	 * Clean up test environment.
	 */
	public function tear_down(): void {
		$this->cleanup_test_fields();
		parent::tear_down();
	}

	/**
	 * Remove test field groups.
	 */
	private function cleanup_test_fields(): void {
		foreach ( array( 'person', 'team' ) as $post_type ) {
			$group = acf_get_field_group( 'group_custom_fields_' . $post_type );
			if ( $group ) {
				acf_delete_field_group( $group['ID'] );
			}
		}
	}

	/**
	 * Test ensure_field_group creates a group for person post type.
	 */
	public function test_ensure_field_group_creates_for_person(): void {
		$group = $this->manager->ensure_field_group( 'person' );

		$this->assertIsArray( $group );
		$this->assertEquals( 'group_custom_fields_person', $group['key'] );
		$this->assertEquals( 'Custom Fields', $group['title'] );
		$this->assertArrayHasKey( 'ID', $group );
	}

	/**
	 * Test ensure_field_group creates a group for team post type.
	 */
	public function test_ensure_field_group_creates_for_team(): void {
		$group = $this->manager->ensure_field_group( 'team' );

		$this->assertIsArray( $group );
		$this->assertEquals( 'group_custom_fields_team', $group['key'] );
	}

	/**
	 * Test ensure_field_group returns WP_Error for invalid post type.
	 */
	public function test_ensure_field_group_rejects_invalid_post_type(): void {
		$result = $this->manager->ensure_field_group( 'invalid_type' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_post_type', $result->get_error_code() );
	}

	/**
	 * Test ensure_field_group returns existing group on second call.
	 */
	public function test_ensure_field_group_returns_existing(): void {
		$group1 = $this->manager->ensure_field_group( 'person' );
		$group2 = $this->manager->ensure_field_group( 'person' );

		$this->assertEquals( $group1['ID'], $group2['ID'] );
		$this->assertEquals( $group1['key'], $group2['key'] );
	}

	/**
	 * Test generate_field_key creates unique keys with correct pattern.
	 */
	public function test_generate_field_key_creates_unique_key(): void {
		$key = $this->manager->generate_field_key( 'Test Label', 'person' );

		$this->assertStringStartsWith( 'field_custom_person_', $key );
		$this->assertStringContainsString( 'test-label', $key );
	}

	/**
	 * Test generate_field_key adds suffix for duplicate labels.
	 */
	public function test_generate_field_key_handles_duplicate_labels(): void {
		// Create first field.
		$this->manager->ensure_field_group( 'person' );
		$this->manager->create_field(
			'person',
			array(
				'label' => 'Duplicate Test',
				'type'  => 'text',
			)
		);

		// Generate key for same label.
		$key = $this->manager->generate_field_key( 'Duplicate Test', 'person' );

		// Should have unique suffix.
		$this->assertStringStartsWith( 'field_custom_person_duplicate-test_', $key );
		$this->assertGreaterThan( strlen( 'field_custom_person_duplicate-test' ), strlen( $key ) );
	}

	/**
	 * Test create_field creates a text field successfully.
	 */
	public function test_create_field_success(): void {
		$field = $this->manager->create_field(
			'person',
			array(
				'label'        => 'Nickname',
				'type'         => 'text',
				'instructions' => 'Enter a nickname',
			)
		);

		$this->assertIsArray( $field );
		$this->assertArrayHasKey( 'key', $field );
		$this->assertEquals( 'Nickname', $field['label'] );
		$this->assertEquals( 'text', $field['type'] );

		// Verify it persists.
		$retrieved = acf_get_field( $field['key'] );
		$this->assertIsArray( $retrieved );
		$this->assertEquals( $field['key'], $retrieved['key'] );
	}

	/**
	 * Test create_field returns WP_Error when label is missing.
	 */
	public function test_create_field_requires_label(): void {
		$result = $this->manager->create_field(
			'person',
			array(
				'type' => 'text',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'missing_required', $result->get_error_code() );
	}

	/**
	 * Test create_field returns WP_Error when type is missing.
	 */
	public function test_create_field_requires_type(): void {
		$result = $this->manager->create_field(
			'person',
			array(
				'label' => 'Test Field',
			)
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'missing_required', $result->get_error_code() );
	}

	/**
	 * Test update_field changes the label.
	 */
	public function test_update_field_changes_label(): void {
		// Create a field.
		$field = $this->manager->create_field(
			'person',
			array(
				'label' => 'Original Label',
				'type'  => 'text',
			)
		);

		// Update the label.
		$updated = $this->manager->update_field(
			$field['key'],
			array( 'label' => 'New Label' )
		);

		$this->assertIsArray( $updated );
		$this->assertEquals( 'New Label', $updated['label'] );

		// Verify persistence.
		$retrieved = acf_get_field( $field['key'] );
		$this->assertEquals( 'New Label', $retrieved['label'] );
	}

	/**
	 * Test update_field does not change the key (key is immutable).
	 */
	public function test_update_field_preserves_key(): void {
		// Create a field.
		$field = $this->manager->create_field(
			'person',
			array(
				'label' => 'Test Field',
				'type'  => 'text',
			)
		);
		$original_key = $field['key'];

		// Attempt to change the key via update.
		$updated = $this->manager->update_field(
			$field['key'],
			array( 'key' => 'field_new_key' )
		);

		// Key should remain unchanged.
		$this->assertEquals( $original_key, $updated['key'] );
	}

	/**
	 * Test update_field returns WP_Error for non-existent field.
	 */
	public function test_update_field_returns_error_for_missing_field(): void {
		$result = $this->manager->update_field(
			'field_does_not_exist',
			array( 'label' => 'New Label' )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'field_not_found', $result->get_error_code() );
	}

	/**
	 * Test deactivate_field sets active to 0.
	 */
	public function test_deactivate_field_sets_inactive(): void {
		// Create a field.
		$field = $this->manager->create_field(
			'person',
			array(
				'label' => 'Test Field',
				'type'  => 'text',
			)
		);

		// Deactivate it.
		$deactivated = $this->manager->deactivate_field( $field['key'] );

		$this->assertIsArray( $deactivated );
		$this->assertEquals( 0, $deactivated['active'] );

		// Verify persistence.
		$retrieved = acf_get_field( $field['key'] );
		$this->assertEquals( 0, $retrieved['active'] );
	}

	/**
	 * Test reactivate_field sets active to 1.
	 */
	public function test_reactivate_field_sets_active(): void {
		// Create and deactivate a field.
		$field = $this->manager->create_field(
			'person',
			array(
				'label' => 'Test Field',
				'type'  => 'text',
			)
		);
		$this->manager->deactivate_field( $field['key'] );

		// Reactivate it.
		$reactivated = $this->manager->reactivate_field( $field['key'] );

		$this->assertIsArray( $reactivated );
		$this->assertEquals( 1, $reactivated['active'] );

		// Verify persistence.
		$retrieved = acf_get_field( $field['key'] );
		$this->assertEquals( 1, $retrieved['active'] );
	}

	/**
	 * Test get_fields returns only active fields by default.
	 */
	public function test_get_fields_returns_active_only_by_default(): void {
		// Create two fields.
		$field1 = $this->manager->create_field(
			'person',
			array(
				'label' => 'Active Field',
				'type'  => 'text',
			)
		);
		$field2 = $this->manager->create_field(
			'person',
			array(
				'label' => 'Inactive Field',
				'type'  => 'text',
			)
		);

		// Deactivate one.
		$this->manager->deactivate_field( $field2['key'] );

		// Get fields (default - active only).
		$fields = $this->manager->get_fields( 'person' );

		$this->assertCount( 1, $fields );
		$this->assertEquals( 'Active Field', $fields[0]['label'] );
	}

	/**
	 * Test get_fields includes inactive when requested.
	 */
	public function test_get_fields_includes_inactive_when_requested(): void {
		// Create two fields.
		$field1 = $this->manager->create_field(
			'person',
			array(
				'label' => 'Active Field',
				'type'  => 'text',
			)
		);
		$field2 = $this->manager->create_field(
			'person',
			array(
				'label' => 'Inactive Field',
				'type'  => 'text',
			)
		);

		// Deactivate one.
		$this->manager->deactivate_field( $field2['key'] );

		// Get all fields including inactive.
		$fields = $this->manager->get_fields( 'person', true );

		$this->assertCount( 2, $fields );
	}
}
