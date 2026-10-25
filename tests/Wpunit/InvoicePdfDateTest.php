<?php

namespace Tests\Wpunit;

use Rondo\Config\FinanceConfig;
use Rondo\Fields\Fields;
use Rondo\Finance\InvoicePdfGenerator;
use Smalot\PdfParser\Parser;
use Tests\Support\RondoTestCase;

/** Read the generated PDFs to verify dates used in invoice previews. */
class InvoicePdfDateTest extends RondoTestCase {
	private array $files = [];

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		update_option( FinanceConfig::OPTION_PAYMENT_TERM_DAYS, 14 );
	}

	protected function tear_down(): void {
		foreach ( $this->files as $file ) {
			wp_delete_file( $file );
		}
		parent::tear_down();
	}

	private function invoice(): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_draft',
			]
		);
		Fields::update_for_post( $id, 'invoice_number', 'PDF-DATE-TEST-' . $id );
		Fields::update_for_post( $id, 'invoice_type', 'manual' );
		Fields::update_for_post( $id, 'status', 'draft' );
		Fields::update_for_post( $id, 'total_amount', 25 );
		Fields::update_for_post(
			$id,
			'line_items',
			[
				[
					'description' => 'Testfactuur',
					'amount'      => 25,
				],
			]
			);
		update_post_meta( $id, '_customer_name', 'PDF datumtest' );
		return $id;
	}

	private function pdf_text( int $id ): string {
		$result = InvoicePdfGenerator::generate( $id );
		$this->assertNotWPError( $result );
		$file          = wp_upload_dir()['basedir'] . '/' . $result;
		$this->files[] = $file;
		return ( new Parser() )->parseFile( $file )->getText();
	}

	public function test_scheduled_preview_uses_planned_date_and_derives_missing_due_date_from_it(): void {
		$id = $this->invoice();
		update_post_meta( $id, '_scheduled_send_date', '20270807' );
		$text = $this->pdf_text( $id );
		// Keep a report with the example from feedback #14427 for visual review.
		copy( end( $this->files ), codecept_output_dir( 'scheduled-invoice-preview.pdf' ) );
		$this->assertStringContainsString( '7 augustus 2027', $text );
		$this->assertStringContainsString( '21 augustus 2027', $text );
		$this->assertEmpty( Fields::get_for_post( $id, 'sent_date' ), 'A preview must not mark the invoice as sent.' );
		$this->assertSame( 'rondo_draft', get_post_status( $id ) );
	}

	public function test_rescheduling_and_cancelling_change_the_preview_date(): void {
		$id = $this->invoice();
		update_post_meta( $id, '_scheduled_send_date', '20270807' );
		$this->assertStringContainsString( '7 augustus 2027', $this->pdf_text( $id ) );
		update_post_meta( $id, '_scheduled_send_date', '20270910' );
		$this->assertStringContainsString( '10 september 2027', $this->pdf_text( $id ) );
		delete_post_meta( $id, '_scheduled_send_date' );
		$text = $this->pdf_text( $id );
		$this->assertStringNotContainsString( '10 september 2027', $text );
		$formatter = new \ReflectionMethod( InvoicePdfGenerator::class, 'format_dutch_date' );
		$this->assertStringContainsString( $formatter->invoke( null, current_time( 'Ymd' ) ), $text );
	}

	public function test_sent_invoice_keeps_actual_date_even_with_stale_schedule(): void {
		$id = $this->invoice();
		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'rondo_sent',
			]
			);
		Fields::update_for_post( $id, 'status', 'sent' );
		Fields::update_for_post( $id, 'sent_date', '20270809' );
		Fields::update_for_post( $id, 'due_date', '20270823' );
		update_post_meta( $id, '_scheduled_send_date', '20270807' );
		$text = $this->pdf_text( $id );
		$this->assertStringContainsString( '9 augustus 2027', $text );
		$this->assertStringContainsString( '23 augustus 2027', $text );
		$this->assertStringNotContainsString( '7 augustus 2027', $text );
	}
}
