<?php

namespace Tests\Wpunit;

use Lettermint\Client\HttpClient;
use Lettermint\Lettermint;
use Rondo\Config\FinanceConfig;
use Rondo\Fields\Fields;
use Rondo\Finance\InvoiceEmailSender;
use Rondo\Finance\InvoiceReminderSender;
use Rondo\Finance\InvoiceStorage;
use Rondo\Notifications\LettermintMailer;
use Tests\Support\RondoTestCase;

/** Exercise actual wp_mail and SDK payloads without sending external mail. */
class InvoiceEmbeddedQrTest extends RondoTestCase {
	private int $invoice_id;
	private string $qr_path;
	private string $pdf_path;
	private array $payloads = [];

	protected function set_up(): void {
		parent::set_up();
		remove_all_filters( 'pre_wp_mail' );
		update_option( FinanceConfig::OPTION_CONTACT_EMAIL, 'finance@example.com' );
		update_option( FinanceConfig::OPTION_MEMBERSHIP_EMAIL_TEMPLATE, '<p>{qr_code}</p>{betaallink}' );
		update_option( FinanceConfig::OPTION_INVOICE_REMINDER_1_EMAIL_TEMPLATE, '<p>{qr_code}</p>{betaallink}' );

		$person_id        = $this->createPerson(
			[],
			[
				'first_name' => 'Test',
				'email_1'    => 'member@example.com',
			]
			);
		$this->invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_sent',
			]
			);
		foreach ( [
			'person'         => $person_id,
			'invoice_type'   => 'membership',
			'invoice_number' => 'QR-TEST',
			'total_amount'   => 50,
			'payment_link'   => 'https://example.com/pay',
		] as $field => $value ) {
			Fields::update_for_post( $this->invoice_id, $field, $value );
		}

		$this->qr_path  = InvoiceStorage::path_for_write( 'qr-test-' . $this->invoice_id . '.png' );
		$this->pdf_path = InvoiceStorage::path_for_write( 'factuur-test-' . $this->invoice_id . '.pdf' );
		file_put_contents( $this->qr_path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=' ) );
		file_put_contents( $this->pdf_path, '%PDF-test' );
		Fields::update_for_post( $this->invoice_id, 'qr_code_path', 'invoices/' . basename( $this->qr_path ) );
		Fields::update_for_post( $this->invoice_id, 'pdf_path', 'invoices/' . basename( $this->pdf_path ) );
	}

	protected function tear_down(): void {
		unlink( $this->qr_path );
		unlink( $this->pdf_path );
		parent::tear_down();
	}

	private function capture_lettermint(): void {
		$http = $this->createMock( HttpClient::class );
		$http->method( 'post' )->willReturnCallback(
			function ( $path, $payload ) {
				$this->payloads[] = $payload;
				return [
					'message_id' => 'test',
					'status'     => 'pending',
				];
			}
		);
		$client   = new Lettermint( 'test-no-network' );
		$property = new \ReflectionProperty( $client, 'httpClient' );
		$property->setAccessible( true );
		$property->setValue( $client, $http );

		$mailer   = new LettermintMailer();
		$property = new \ReflectionProperty( $mailer, 'client' );
		$property->setAccessible( true );
		$property->setValue( $mailer, $client );
	}

	private function assert_qr_payload( array $payload ): void {
		$this->assertStringContainsString( 'cid:' . InvoiceStorage::QR_CID, $payload['html'] );
		$images = array_values( array_filter( $payload['attachments'], static fn( $file ) => ( $file['content_id'] ?? '' ) === InvoiceStorage::QR_CID ) );
		$this->assertCount( 1, $images );
		$this->assertSame( file_get_contents( $this->qr_path ), base64_decode( $images[0]['content'] ) );
		$this->assertSame( basename( $this->qr_path ), $images[0]['filename'] );
	}

	public function test_invoice_and_reminder_include_qr_in_lettermint_payload(): void {
		$this->capture_lettermint();
		$this->assertTrue( InvoiceEmailSender::send( $this->invoice_id ) );
		$this->assertTrue( InvoiceReminderSender::send_reminder_1( $this->invoice_id ) );
		$this->assertCount( 2, $this->payloads );
		$this->assert_qr_payload( $this->payloads[0] );
		$this->assert_qr_payload( $this->payloads[1] );
		$pdfs = array_values( array_filter( $this->payloads[0]['attachments'], static fn( $file ) => ! isset( $file['content_id'] ) ) );
		$this->assertCount( 1, $pdfs );
		$this->assertSame( '%PDF-test', base64_decode( $pdfs[0]['content'] ) );

		$this->assertTrue( wp_mail( 'member@example.com', 'Unrelated', 'No invoice here' ) );
		$this->assertEmpty( $this->payloads[2]['attachments'] ?? [], 'embeds must not leak into subsequent mail' );
	}

	public function test_missing_qr_file_keeps_payment_link_and_pdf(): void {
		Fields::update_for_post( $this->invoice_id, 'qr_code_path', 'invoices/missing-qr.png' );
		$this->capture_lettermint();
		$this->assertTrue( InvoiceEmailSender::send( $this->invoice_id ) );
		$this->assertStringNotContainsString( 'cid:', $this->payloads[0]['html'] );
		$this->assertStringContainsString( 'https://example.com/pay', $this->payloads[0]['html'] );
		$this->assertCount( 1, $this->payloads[0]['attachments'] );
		$this->assertArrayNotHasKey( 'content_id', $this->payloads[0]['attachments'][0] );
	}

	public function test_wordpress_mailer_fallback_also_embeds_qr(): void {
		$captures = [];
		add_action(
			'phpmailer_init',
			static function ( $mailer ) use ( &$captures ) {
				$captures[] = $mailer->getAttachments();
			}
		);
		// wp-browser installs MockPHPMailer, so the fallback never sends mail.
		$this->assertTrue( InvoiceEmailSender::send( $this->invoice_id ) );
		$this->assertTrue( InvoiceReminderSender::send_reminder_1( $this->invoice_id ) );
		$this->assertCount( 2, $captures );
		foreach ( $captures as $attachments ) {
			$images = array_values( array_filter( $attachments, static fn( $file ) => $file[6] === 'inline' && $file[7] === InvoiceStorage::QR_CID ) );
			$this->assertCount( 1, $images );
			$this->assertSame( $this->qr_path, $images[0][0] );
		}
	}
}
