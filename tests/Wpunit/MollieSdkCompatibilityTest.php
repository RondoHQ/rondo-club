<?php

namespace Tests\Wpunit;

use Mollie\Api\Fake\MockResponse;
use Mollie\Api\Http\Requests\GetPaymentRequest;
use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\PaymentLink;
use Mollie\Api\Types\PaymentMethod;
use Rondo\Finance\MollieWebhook;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Verify SDK hydration, persisted payment details, and their REST consumers together. */
class MollieSdkCompatibilityTest extends RondoTestCase {
	public function test_known_unknown_and_missing_methods_remain_scalar_for_full_and_installment_payments(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$server  = $this->bootRestControllers( [ Invoices::class ] );
		$webhook = new MollieWebhook();

		foreach ( [ 'ideal', 'future_method', null ] as $method ) {
			$client  = MollieApiClient::fake(
				[
					GetPaymentRequest::class => MockResponse::ok(
						[
							'resource' => 'payment',
							'id'       => 'tr_test123',
							'status'   => 'paid',
							'method'   => $method,
							'paidAt'   => '2026-11-12T08:00:00+00:00',
							'details'  => [
								'consumerName'    => 'Test Member',
								'consumerAccount' => 'NL13TEST0123456789',
							],
							'_links'   => [ 'dashboard' => [ 'href' => 'https://www.mollie.com/dashboard/test' ] ],
						]
					),
				]
			);
			$payment = $client->payments->get( 'tr_test123' );
			$this->assertSame( $method === 'ideal' ? PaymentMethod::Ideal : $method, $payment->method );
			$link = $this->createMock( PaymentLink::class );
			$link->method( 'payments' )->willReturn( [ $payment ] );
			$invoice_id = self::factory()->post->create(
				[
					'post_type'   => 'rondo_invoice',
					'post_status' => 'rondo_paid',
				]
				);
			update_post_meta( $invoice_id, '_installment_plan', 'quarterly_3' );
			update_post_meta( $invoice_id, '_installment_count', 1 );

			( new \ReflectionMethod( $webhook, 'extract_payment_details' ) )->invoke( $webhook, $link, $invoice_id );
			( new \ReflectionMethod( $webhook, 'extract_installment_payment_details' ) )->invoke( $webhook, $link, $invoice_id, 1 );
			$this->assertSame( $method ?? '', get_post_meta( $invoice_id, '_mollie_payment_method', true ) );
			$this->assertSame( $method ?? '', get_post_meta( $invoice_id, '_installment_1_mollie_method', true ) );
			$this->assertSame( 'Test Member', get_post_meta( $invoice_id, '_mollie_consumer_name', true ) );
			$this->assertSame( 'NL13TEST0123456789', get_post_meta( $invoice_id, '_installment_1_mollie_consumer_account', true ) );
			$response = $server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/invoices/' . $invoice_id ) );
			$this->assertSame( 200, $response->get_status() );
			$this->assertSame( $method, $response->get_data()['mollie_payment_method'] );
			$this->assertSame( $method, $response->get_data()['installments'][0]['mollie_method'] );
		}
	}
}
