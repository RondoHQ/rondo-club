<?php

namespace Tests\Wpunit;

use Rondo\Finance\InvoicePdfGenerator;
use Tests\Support\RondoTestCase;

/** Invoice PDF columns must match the invoice type. */
class InvoicePdfColumnsTest extends RondoTestCase {

	public function test_columns_and_totals_match_invoice_type(): void {
		$method = new \ReflectionMethod( InvoicePdfGenerator::class, 'build_html' );
		foreach ( [
			'manual'     => 2,
			'membership' => 2,
			'discipline' => 4,
		] as $type => $columns ) {
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
				null,
				'#0891b2',
				$type,
				'',
				'Membership payment instructions'
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
			$this->assertStringContainsString( $type === 'membership' ? 'Membership payment instructions' : 'Regular payment instructions', $html );
			$this->assertSame( $type !== 'membership', str_contains( $html, 'Vervaldatum:' ) );
		}
	}
}
