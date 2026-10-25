<?php

namespace Tests\Wpunit;

use Rondo\Fields\Registry;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;

class InvoiceAddressBookContactTest extends RondoTestCase {

	private function payload(): array {
		return [
			'title'  => 'AWC Businessclub',
			'status' => 'publish',
			'fields' => [
				'person_type'       => 'contact',
				'company_name'      => 'AWC Businessclub',
				'invoice_attention' => 'Bestuur, t.a.v. Jan van der Meer',
				'invoice_address'   => "Postbus 42\n6600 AA Wijchen\nNederland",
				'email_1'           => 'facturen@example.org',
				'email_2'           => 'bestuur@example.org',
			],
		];
	}

	public function test_finance_can_create_and_retrieve_a_reusable_invoice_contact(): void {
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_financieel' ] ) );
		$server  = $this->bootRestControllers( [ People::class ] );
		$payload = $this->payload();
		$request = new \WP_REST_Request( 'POST', '/wp/v2/people' );
		$request->set_body_params( $payload );
		$response = $server->dispatch( $request );
		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$id = $response->get_data()['id'];
		$this->assertSame( 'person', get_post_type( $id ) );
		$this->assertSame( 'AWC Businessclub', get_the_title( $id ) );
		foreach ( $payload['fields'] as $key => $value ) {
			$this->assertSame( $value, get_post_meta( $id, $key, true ) );
		}
		$this->assertSame( 'textarea', Registry::resolve( 'person', 'invoice_address' )['type'] );
		$this->assertSame( 'invoice_address', Registry::resolve( 'person', 'invoice_address' )['storage_name'] );
		$read = $server->dispatch( new \WP_REST_Request( 'GET', '/wp/v2/people/' . $id ) );
		$this->assertSame( 200, $read->get_status() );
		foreach ( $payload['fields'] as $key => $value ) {
			$this->assertSame( $value, $read->get_data()['fields'][ $key ] );
		}
		$list = new \WP_REST_Request( 'GET', '/rondo/v1/people/filtered' );
		$list->set_param( 'person_type', 'contact' );
		$result = $server->dispatch( $list );
		$this->assertSame( 200, $result->get_status() );
		$this->assertContains( $id, array_column( $result->get_data()['people'], 'id' ) );
		$contact = array_values( array_filter( $result->get_data()['people'], static fn( $person ) => $person['id'] === $id ) )[0];
		$this->assertSame( $payload['fields']['invoice_address'], $contact['fields']['invoice_address'] );
		$this->assertSame( $payload['fields']['invoice_attention'], $contact['fields']['invoice_attention'] );
	}

	public function test_read_only_finance_cannot_create_address_book_contacts(): void {
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_financieel_lezen' ] ) );
		$server  = $this->bootRestControllers( [ People::class ] );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/people' );
		$request->set_body_params( $this->payload() );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
	}
}
