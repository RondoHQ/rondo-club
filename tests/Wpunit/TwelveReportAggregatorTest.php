<?php

namespace Tests\Wpunit;

use Rondo\Twelve\ReportAggregator;
use Rondo\Twelve\ReportParser;
use Tests\Support\RondoTestCase;

/** Tests for aggregations over parsed Twelve reports. */
class TwelveReportAggregatorTest extends RondoTestCase {

	/**
	 * Two parsed reports: the real fixture plus a copy dated one day later,
	 * so sums can be verified by doubling.
	 *
	 * @return array<int, array{id: int, period_start: string, period_end: string, data: array}>
	 */
	private function two_reports(): array {
		$data = ReportParser::parse( (string) file_get_contents( __DIR__ . '/../fixtures/twelve-rapportage.txt' ) );

		$second                 = $data;
		$second['period_start'] = '2026-09-30 06:00';
		$second['period_end']   = '2026-10-01 06:00';

		return [
			[
				'id'           => 1,
				'period_start' => '2026-09-29 06:00:00',
				'period_end'   => '2026-09-30 06:00:00',
				'data'         => $data,
			],
			[
				'id'           => 2,
				'period_start' => '2026-09-30 06:00:00',
				'period_end'   => '2026-10-01 06:00:00',
				'data'         => $second,
			],
		];
	}

	public function test_summarize_per_day(): void {
		$buckets = ReportAggregator::summarize( $this->two_reports(), 'day' );

		$this->assertCount( 2, $buckets );
		$this->assertSame( '2026-09-29', $buckets[0]['periode'] );
		$this->assertSame( 15.75, $buckets[0]['omzet_excl_nosale'] );
		$this->assertSame( 43.45, $buckets[0]['omzet_incl_nosale'] );
		$this->assertSame( 20, $buckets[0]['producten'] );
		$this->assertSame( 15.75, $buckets[0]['betaalmethoden']['Omzet pin'] );
	}

	public function test_summarize_per_month_sums_days(): void {
		$reports = $this->two_reports();
		$reports[1]['period_start'] = '2026-10-01 06:00:00';
		$reports[1]['period_end']   = '2026-10-02 06:00:00';
		$reports[1]['data']['period_start'] = '2026-10-01 06:00';
		$reports[1]['data']['period_end']   = '2026-10-02 06:00';

		$buckets = ReportAggregator::summarize( $reports, 'month' );

		$this->assertCount( 2, $buckets );
		$this->assertSame( '2026-09', $buckets[0]['periode'] );
		$this->assertSame( 15.75, $buckets[0]['omzet_excl_nosale'] );
		$this->assertSame( '2026-10', $buckets[1]['periode'] );
		$this->assertSame( 15.75, $buckets[1]['omzet_excl_nosale'] );
	}

	public function test_by_category(): void {
		$categories = ReportAggregator::by_category( $this->two_reports() );
		$by_name    = [];
		foreach ( $categories as $category ) {
			$by_name[ $category['categorie'] ] = $category;
		}

		$this->assertSame( 55.40, $by_name['Bestuur']['bedrag'] );
		$this->assertSame( 6, $by_name['Bestuur']['transacties'] );
		$this->assertSame( 0.0, $by_name['Businessclub']['bedrag'] );
		$this->assertSame( 0.0, $by_name['Breuk en bederf']['bedrag'] );
	}

	public function test_by_product_orders_by_quantity(): void {
		$products = ReportAggregator::by_product( $this->two_reports() );

		$this->assertSame( 'Snoepzakje', $products[0]['product'] );
		$this->assertSame( 12, $products[0]['aantal'] );
		$this->assertSame( 21.0, $products[0]['bruto'] );
		$this->assertSame( 'Laag 9%', $products[0]['btw_groep'] );
	}

	public function test_vat_overview(): void {
		$vat = ReportAggregator::vat_overview( $this->two_reports() );

		$this->assertCount( 1, $vat );
		$this->assertSame( 'Laag (Excl. no-sale)', $vat[0]['tarief'] );
		$this->assertSame( 28.90, $vat[0]['netto'] );
		$this->assertSame( 2.60, $vat[0]['btw_laag'] );
		$this->assertSame( 2.60, $vat[0]['btw_totaal'] );
		$this->assertSame( 31.50, $vat[0]['bruto'] );
	}

	public function test_businessclub_days(): void {
		$reports = $this->two_reports();

		// Give the second day a businessclub turnover.
		foreach ( $reports[1]['data']['omzet'] as &$row ) {
			if ( $row['label'] === 'Businessclub' ) {
				$row['bedrag'] = 120.00;
				$row['netto']  = 110.09;
				$row['laag']   = 9.91;
			}
		}
		unset( $row );

		$days = ReportAggregator::businessclub_days( $reports );

		$this->assertCount( 2, $days );
		$this->assertSame( '2026-09-29', $days[0]['datum'] );
		$this->assertSame( 0.0, $days[0]['bedrag'] );
		$this->assertSame( '2026-09-30', $days[1]['datum'] );
		$this->assertSame( 120.0, $days[1]['bedrag'] );
		$this->assertSame( 110.09, $days[1]['netto'] );
		$this->assertSame( 9.91, $days[1]['btw'] );
	}
}
