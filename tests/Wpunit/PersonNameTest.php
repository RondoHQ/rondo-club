<?php

namespace Tests\Wpunit;

use Rondo\People\PersonName;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

class PersonNameTest extends RondoTestCase {

	public function test_formats_optional_name_parts_without_extra_spaces(): void {
		$this->assertSame( 'Lars van der Meer', PersonName::format( 'Lars', 'van der', 'Meer' ) );
		$this->assertSame( 'Anne Jansen', PersonName::format( 'Anne', '', 'Jansen' ) );
		$this->assertSame( 'van der Meer', PersonName::format( '', 'van der', 'Meer' ) );
		$this->assertSame( '', PersonName::format( '', '', '' ) );
	}

	public function test_linking_and_reading_a_person_returns_the_complete_name(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$person_id = $this->createPerson(
			[],
			[
				'first_name' => 'Lars',
				'infix'      => 'van der',
				'last_name'  => 'Meer',
			]
			);
		$request   = new \WP_REST_Request( 'POST' );
		$request->set_param( 'person_id', $person_id );
		$settings = new UserSettings();

		$this->assertSame( 'Lars van der Meer', $settings->update_linked_person( $request )->get_data()['person']['name'] );
		$this->assertSame( 'Lars van der Meer', $settings->get_linked_person()->get_data()['person']['name'] );
	}
}
