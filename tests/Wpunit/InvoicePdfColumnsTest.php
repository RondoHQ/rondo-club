<?php

namespace Tests\Wpunit;

use Rondo\Finance\InvoicePdfGenerator;
use Tests\Support\RondoTestCase;

/** Invoice PDF columns must match the invoice type. */
class InvoicePdfColumnsTest extends RondoTestCase {

	public function test_columns_and_totals_match_invoice_type(): void {
		$method = new \ReflectionMethod( InvoicePdfGenerator::class, 'build_html' );
		foreach ( [
			[ 'manual', 2, false, false ],
			[ 'membership', 2, false, false ],
			[ 'discipline', 4, false, false ],
			[ 'manual', 2, false, true ],
			[ 'membership', 2, false, true ],
			[ 'discipline', 4, false, true ],
			[ 'manual', 2, true, false ],
			[ 'membership', 2, true, false ],
			[ 'discipline', 4, true, false ],
			[ 'manual', 2, true, true ],
			[ 'membership', 2, true, true ],
			[ 'discipline', 4, true, true ],
		] as [ $type, $columns, $is_paid, $is_credit ] ) {
			$html = $method->invoke(
				null,
				'TEST-12767',
				'20261003',
				'20261017',
				'Test customer',
				'',
				'',
				'',
				'',
				[],
				[
					[
						'description' => 'Gebruik sportvelden',
						'amount'      => 943.17,
					],
					[
						'description' => 'Correctie',
						'amount'      => -10,
					],
				],
				933.17,
				'Test club',
				'',
				'',
				'',
				'',
				'Regular payment instructions',
				null,
				'/tmp/payment-qr.png',
				'#0891b2',
				$type,
				'',
				'Membership payment instructions',
				$is_paid,
				$is_credit
			);
			$dom  = new \DOMDocument();
			$dom->loadHTML( $html );
			$xpath = new \DOMXPath( $dom );
			$table = '//table[@class="line-items"]';
			$this->assertSame( $columns, $xpath->query( $table . '/thead/tr/th' )->length, $type );
			foreach ( $xpath->query( $table . '/tbody/tr[not(@class)]' ) as $row ) {
				$this->assertSame( $columns, $row->getElementsByTagName( 'td' )->length, $type );
			}
			$this->assertSame( $type === 'discipline' ? 1 : 0, $xpath->query( $table . '//th[text()="Kaart"]' )->length );
			$this->assertSame( $type === 'discipline' ? 1 : 0, $xpath->query( $table . '//th[text()="Schorsing"]' )->length );
			$total_label = $xpath->query( $table . '/tbody/tr[@class="total-row"]/td' )->item( 0 );
			$this->assertSame( $columns - 1, (int) ( $total_label->getAttribute( 'colspan' ) ?: 1 ) );
			$this->assertStringContainsString( 'Gebruik sportvelden', $html );
			$this->assertStringContainsString( '- € 10,00', $html );
			$this->assertStringContainsString( '€ 933,17', $html );
			$show_payment_instructions = ! $is_paid && ! $is_credit;
			$this->assertSame( $show_payment_instructions, str_contains( $html, '<div class="payment-section">' ) );
			$this->assertSame( $show_payment_instructions, str_contains( $html, 'Betaalgegevens' ) );
			$this->assertSame( $show_payment_instructions, str_contains( $html, 'Scan om te betalen' ) );
			$this->assertSame( $show_payment_instructions, str_contains( $html, '/tmp/payment-qr.png' ) );
			$this->assertSame( $show_payment_instructions && $type === 'membership', str_contains( $html, 'Membership payment instructions' ) );
			$this->assertSame( $show_payment_instructions && $type !== 'membership', str_contains( $html, 'Regular payment instructions' ) );
			$this->assertSame( $type !== 'membership', str_contains( $html, 'Vervaldatum:' ) );
		}
	}
}
