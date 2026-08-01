<?php

namespace Tests\Wpunit;

use Rondo\CustomFields\Manager;
use Rondo\Fields\AcfPreflightCli;
use Tests\Support\RondoTestCase;

class AcfPreflightCliTest extends RondoTestCase {
	public function test_dynamic_export_contains_definitions_and_counts_but_not_values(): void {
		$field = $this->create_dynamic_person_field( 'Preflight field', 'preflight-field' );
		$post  = $this->createPerson();
		update_post_meta( $post, 'preflight-field', 'private-member-value' );

		$document   = ( new AcfPreflightCli() )->build_dynamic_export();
		$definition = $this->find_definition( $document['contexts']['person'], $field['key'] );

		$this->assertSame( 'preflight-field', $definition['storage_name'] );
		$this->assertSame( 'preflight_field', $definition['canonical_name'] );
		$this->assertSame( 1, $definition['stored_posts'] );
		$this->assertSame( 1, $definition['populated_posts'] );
		$this->assertStringNotContainsString( 'private-member-value', wp_json_encode( $document ) );
	}

	public function test_persisted_audit_reports_identifiers_without_values_or_mutation(): void {
		$this->create_dynamic_person_field( 'Audit field', 'preflight-audit-field' );
		$user_id = self::factory()->user->create();
		$meta    = [
			'visible' => [ 'preflight-audit-field' ],
			'widths'  => [ 'custom_preflight-audit-field' => 180 ],
		];
		$option  = [
			'orderby' => 'custom_preflight-audit-field',
			'secret'  => 'private-option-value',
		];
		update_user_meta( $user_id, 'rondo_people_list_preferences', $meta );
		update_option( 'rondo_preflight_test_option', $option );

		$document     = ( new AcfPreflightCli() )->build_persisted_audit();
		$user_store   = $this->find_store( $document['stores'], 'user_meta.rondo_people_list_preferences' );
		$option_store = $this->find_store( $document['stores'], 'wp_options (including cron arguments)' );

		$this->assertSame( 'preflight_audit_field', $user_store['legacy_identifiers']['preflight-audit-field'] );
		$this->assertSame( 'field_preflight_audit_field', $user_store['legacy_identifiers']['custom_preflight-audit-field'] );
		$this->assertSame(
			'field_preflight_audit_field',
			$option_store['legacy_identifiers']['rondo_preflight_test_option']['custom_preflight-audit-field']
		);
		$this->assertStringNotContainsString( 'private-option-value', wp_json_encode( $document ) );
		$this->assertSame( $meta, get_user_meta( $user_id, 'rondo_people_list_preferences', true ) );
		$this->assertSame( $option, get_option( 'rondo_preflight_test_option' ) );
	}

	private function create_dynamic_person_field( string $label, string $name ): array {
		$field = ( new Manager() )->create_field(
			'person',
			[
				'label' => $label,
				'name'  => $name,
				'type'  => 'text',
			]
		);
		$this->assertIsArray( $field );
		return $field;
	}

	private function find_definition( array $definitions, string $key ): array {
		foreach ( $definitions as $definition ) {
			if ( $definition['key'] === $key ) {
				return $definition;
			}
		}
		$this->fail( "Definition {$key} not found in preflight export." );
	}

	private function find_store( array $stores, string $name ): array {
		foreach ( $stores as $store ) {
			if ( $store['store'] === $name ) {
				return $store;
			}
		}
		$this->fail( "Store {$name} not found in persisted-reference audit." );
	}
}
