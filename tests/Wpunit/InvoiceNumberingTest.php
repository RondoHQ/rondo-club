<?php

namespace Tests\Wpunit;

use Rondo\Finance\InvoiceNumbering;
use Tests\Support\RondoTestCase;

/**
 * Tests invoice numbering prefix and sequence behavior.
 */
class InvoiceNumberingTest extends RondoTestCase {

	/**
	 * Create a test invoice with an existing invoice_number meta value.
	 *
	 * @param string $invoice_number Stored invoice number.
	 * @return int Created invoice post ID.
	 */
	private function createInvoiceWithNumber( string $invoice_number ): int {
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_draft',
				'post_title'  => $invoice_number,
			]
		);

		update_post_meta( $invoice_id, 'invoice_number', $invoice_number );

		return $invoice_id;
	}

	/**
	 * Manual invoices should continue the existing F-sequence.
	 */
	public function test_manual_invoices_continue_existing_f_sequence(): void {
		$year = gmdate( 'Y' );
		$this->createInvoiceWithNumber( $year . 'F0012' );
		$this->createInvoiceWithNumber( $year . 'F0104' );

		$this->assertEquals(
			$year . 'F105',
			InvoiceNumbering::generate_next( 'manual' )
		);
	}

	/**
	 * Discipline invoices should continue the existing T-sequence only.
	 */
	public function test_discipline_invoices_continue_existing_t_sequence(): void {
		$year = gmdate( 'Y' );
		$this->createInvoiceWithNumber( $year . 'T0007' );
		$this->createInvoiceWithNumber( $year . 'F0999' );

		$this->assertEquals(
			$year . 'T008',
			InvoiceNumbering::generate_next( 'discipline' )
		);
	}

	/**
	 * Membership invoices should continue the existing C-sequence only.
	 */
	public function test_membership_invoices_continue_existing_c_sequence(): void {
		$year = gmdate( 'Y' );
		$this->createInvoiceWithNumber( $year . 'C0003' );
		$this->createInvoiceWithNumber( $year . 'T0888' );

		$this->assertEquals(
			$year . 'C004',
			InvoiceNumbering::generate_next( 'membership' )
		);
	}

	/**
	 * Removed invoice types fall back to the manual sequence and are not valid prefixes.
	 */
	public function test_removed_volunteer_fine_type_is_not_supported(): void {
		$year = gmdate( 'Y' );
		$this->createInvoiceWithNumber( $year . 'F0004' );

		$this->assertSame( $year . 'F005', InvoiceNumbering::generate_next( 'volunteer_fine' ) );
		$this->assertFalse( InvoiceNumbering::is_valid( $year . 'V001' ) );
	}
}
