<?php

namespace Tests\Wpunit;

use Rondo\Twelve\ReportParser;
use Rondo\Twelve\ReportParserException;
use Tests\Support\RondoTestCase;

/** Tests for the Twelve daily revenue report parser. */
class TwelveReportParserTest extends RondoTestCase {

	private function fixture_text(): string {
		return (string) file_get_contents( __DIR__ . '/../fixtures/twelve-rapportage.txt' );
	}

	public function test_parses_real_report(): void {
		$report = ReportParser::parse( $this->fixture_text() );

		$this->assertSame( 'AWC Wijchen, SV', $report['club'] );
		$this->assertSame( '2026-09-29 06:00', $report['period_start'] );
		$this->assertSame( '2026-09-30 06:00', $report['period_end'] );

		$by_label = [];
		foreach ( $report['omzet'] as $row ) {
			$by_label[ $row['label'] ] = $row;
		}

		$this->assertCount( 11, $report['omzet'] );
		$this->assertSame( 'btw_type', $by_label['Laag (Excl. no-sale)']['section'] );
		$this->assertSame( 15.75, $by_label['Laag (Excl. no-sale)']['bedrag'] );
		$this->assertNull( $by_label['Laag (Excl. no-sale)']['transacties'] );
		$this->assertSame( 0, $by_label['Omzet (excl. no-sale)']['transacties'] );
		$this->assertSame( 'categorie', $by_label['Bestuur']['section'] );
		$this->assertSame( 27.70, $by_label['Bestuur']['bedrag'] );
		$this->assertSame( 3, $by_label['Bestuur']['transacties'] );
		$this->assertSame( 'categorie', $by_label['Businessclub']['section'] );
		$this->assertSame( 0.0, $by_label['Businessclub']['bedrag'] );
		$this->assertSame( 'betaalmethode', $by_label['Omzet pin']['section'] );
		$this->assertSame( 15.75, $by_label['Omzet pin']['bedrag'] );

		$this->assertSame(
			[
				[
					'label'  => 'Betaalwijze',
					'aantal' => 2,
				],
			],
			$report['no_sale']
			);
		$this->assertSame(
			[
				'bijboekingen' => 0.0,
				'afboekingen'  => 0.0,
				'totaal'       => 0.0,
			],
			$report['rekeningen']
			);

		$methoden = [];
		foreach ( $report['betaalwijze'] as $methode ) {
			$methoden[ $methode['methode'] ] = $methode;
		}
		$this->assertSame( [ 'Cash', 'Pin', 'Rekeningen' ], array_keys( $methoden ) );
		$this->assertSame( 15.75, $methoden['Pin']['totaal'] );
		$this->assertSame(
			[
				[
					'type'   => 'Omzet pin',
					'bedrag' => 15.75,
				],
			],
			$methoden['Pin']['types']
			);

		$this->assertSame(
			[
				[
					'terminal'    => 'Kantine',
					'betaald'     => 15.75,
					'transacties' => 2,
				],
			],
			$report['terminals']
		);
		$this->assertSame(
			[
				'betaald'     => 15.75,
				'transacties' => 2,
			],
			$report['terminals_totaal']
			);

		$this->assertCount( 9, $report['producten'] );
		$this->assertSame( 'Cola (blikje)', $report['producten'][0]['product'] );
		$this->assertSame( 2.35, $report['producten'][0]['bruto'] );
		$this->assertSame( 'Laag 9%', $report['producten'][0]['btw_groep'] );
		$this->assertSame(
			[
				'bruto'  => 43.45,
				'btw'    => 3.59,
				'netto'  => 39.86,
				'aantal' => 20,
			],
			$report['producten_totaal']
		);

		$this->assertSame(
			[
				'op_bon_gezet' => 0.0,
				'saldo'        => 0.0,
			],
			$report['bonnen']
			);
		$this->assertSame( 491605.36, $report['cashflow']['beginsaldo'] );
		$this->assertSame( 491605.36, $report['cashflow']['totaal'] );
	}

	public function test_parses_dutch_amounts_with_thousands_separator(): void {
		$this->assertSame( 491605.36, ReportParser::parse_bedrag( '491.605,36' ) );
		$this->assertSame( 15.75, ReportParser::parse_bedrag( '15,75' ) );
		$this->assertSame( 0.0, ReportParser::parse_bedrag( '0,00' ) );
	}

	public function test_throws_on_empty_text(): void {
		$this->expectException( ReportParserException::class );
		ReportParser::parse( '' );
	}

	public function test_throws_on_missing_header(): void {
		$this->expectException( ReportParserException::class );
		ReportParser::parse( "Omzetoverzicht\n" );
	}

	public function test_unknown_labels_do_not_break_parsing(): void {
		$text   = str_replace( 'Bestuur', 'Vreemde nieuwe categorie', $this->fixture_text() );
		$report = ReportParser::parse( $text );

		$found = null;
		foreach ( $report['omzet'] as $row ) {
			if ( $row['label'] === 'Vreemde nieuwe categorie' ) {
				$found = $row;
			}
		}

		$this->assertNotNull( $found );
		$this->assertSame( 'unknown', $found['section'] );
		$this->assertSame( 27.70, $found['bedrag'] );
	}
	public function test_negative_amounts_preserve_revenue_rows(): void {
		$report = ReportParser::parse( str_replace( '15,75', '-15,75', $this->fixture_text() ) );
		$this->assertCount( 11, $report['omzet'] );
		$this->assertSame( -15.75, $report['omzet'][0]['bedrag'] );
		$this->assertSame( -1234.56, ReportParser::parse_bedrag( '-1.234,56' ) );
	}
	public function test_smalot_extraction_layout(): void {
		$report = ReportParser::parse( (string) file_get_contents( __DIR__ . '/../fixtures/twelve-smalot.txt' ) );
		$this->assertSame( '2026-09-29 06:00', $report['period_start'] );
		$this->assertCount( 11, $report['omzet'] );
		$this->assertCount( 9, $report['producten'] );
		$this->assertSame( 43.45, $report['producten_totaal']['bruto'] );
	}
}
