<?php

namespace Tests\Wpunit;

use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;

/**
 * Covers company/contact-person naming on invoice API responses.
 */
class InvoiceCompanyContactTest extends RondoTestCase {

	private function format_customer( \WP_Post $person ): array {
		$method = new \ReflectionMethod( Invoices::class, 'format_invoice_person_summary' );
		return $method->invoke( new Invoices(), $person );
	}

	public function test_company_is_primary_invoice_customer_and_person_is_contact(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Roel de Bruijn' ],
			[
				'first_name'   => 'Roel',
				'infix'        => 'de',
				'last_name'    => 'Bruijn',
				'company_name' => 'Businessclub AWC',
				'person_type'  => 'contact',
			]
		);

		$summary = $this->format_customer( get_post( $person_id ) );

		$this->assertSame( 'Businessclub AWC', $summary['name'] );
		$this->assertSame( 'Businessclub AWC', $summary['company_name'] );
		$this->assertSame( 'Roel de Bruijn', $summary['contact_name'] );
	}

	public function test_regular_member_keeps_personal_name(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Jan Jansen' ],
			[
				'first_name' => 'Jan',
				'last_name'  => 'Jansen',
			]
		);

		$summary = $this->format_customer( get_post( $person_id ) );

		$this->assertSame( 'Jan Jansen', $summary['name'] );
		$this->assertArrayNotHasKey( 'company_name', $summary );
		$this->assertArrayNotHasKey( 'contact_name', $summary );
	}
}
