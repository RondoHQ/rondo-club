<?php
/**
 * Aggregations over parsed Twelve reports.
 *
 * Pure PHP on purpose: takes the parsed report arrays (as produced by
 * the browser import and stored by ReportRepository) and returns summaries for
 * the REST API and the businessclub invoicing. No WordPress dependencies.
 */

namespace Rondo\Twelve;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportAggregator {

	/**
	 * Omzet row helper: find rows by section, optionally filtered by label.
	 *
	 * @param array       $report Parsed report.
	 * @param string      $section Section name.
	 * @param string|null $label   Optional exact label.
	 * @return array<int, array>
	 */
	public static function omzet_rows( array $report, string $section, ?string $label = null ): array {
		$rows = [];
		foreach ( $report['omzet'] ?? [] as $row ) {
			if ( ( $row['section'] ?? '' ) !== $section ) {
				continue;
			}
			if ( $label !== null && ( $row['label'] ?? '' ) !== $label ) {
				continue;
			}
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Omzet excl. no-sale for one report (the "Omzet (excl. no-sale)" row).
	 */
	public static function omzet_excl_nosale( array $report ): float {
		$rows = self::omzet_rows( $report, 'totaal', 'Omzet (excl. no-sale)' );
		return (float) ( $rows[0]['bedrag'] ?? 0 );
	}

	/**
	 * Omzet incl. no-sale for one report (products total, gross).
	 */
	public static function omzet_incl_nosale( array $report ): float {
		return (float) ( $report['producten_totaal']['bruto'] ?? 0 );
	}

	/** Revenue is cash-register sales plus businessclub consumption billed separately. */
	public static function revenue_breakdown( array $report ): array {
		$cash     = self::omzet_excl_nosale( $report );
		$business = self::round2( array_sum( array_column( self::omzet_rows( $report, 'categorie', 'Businessclub' ), 'bedrag' ) ) );
		$total    = self::round2( $cash + $business );
		return [
			'kassaomzet'      => $cash,
			'businessclub'    => $business,
			'omzet_totaal'    => $total,
			'overig_verbruik' => self::round2( self::omzet_incl_nosale( $report ) - $total ),
		];
	}

	/**
	 * Number of sold products for one report.
	 */
	public static function aantal_producten( array $report ): int {
		return (int) ( $report['producten_totaal']['aantal'] ?? 0 );
	}

	/**
	 * Summarize reports grouped by day or month.
	 *
	 * @param array<int, array{id: int, period_start: string, data: array}> $reports
	 * @return array<int, array{periode: string, omzet_excl_nosale: float, omzet_incl_nosale: float, producten: int, betaalmethoden: array<string, float>}>
	 */
	public static function summarize( array $reports, string $group = 'day' ): array {
		$buckets = [];
		foreach ( $reports as $report ) {
			$date = substr( $report['period_start'], 0, 10 );
			$key  = $group === 'month' ? substr( $date, 0, 7 ) : $date;

			if ( ! isset( $buckets[ $key ] ) ) {
				$buckets[ $key ] = [
					'periode'           => $key,
					'omzet_excl_nosale' => 0.0,
					'omzet_incl_nosale' => 0.0,
					'producten'         => 0,
					'betaalmethoden'    => [],
					'kassaomzet'        => 0.0,
					'businessclub'      => 0.0,
					'omzet_totaal'      => 0.0,
					'overig_verbruik'   => 0.0,
					'provisional'       => false,
				];
			}

			$data                                 = $report['data'];
			$buckets[ $key ]['provisional']       = $buckets[ $key ]['provisional'] || ( isset( $data['source']['complete'] ) && ! $data['source']['complete'] );
			$buckets[ $key ]['omzet_excl_nosale'] = self::round2( $buckets[ $key ]['omzet_excl_nosale'] + self::omzet_excl_nosale( $data ) );
			$buckets[ $key ]['omzet_incl_nosale'] = self::round2( $buckets[ $key ]['omzet_incl_nosale'] + self::omzet_incl_nosale( $data ) );
			$buckets[ $key ]['producten']        += self::aantal_producten( $data );

			foreach ( self::revenue_breakdown( $data ) as $field => $amount ) {
				$buckets[ $key ][ $field ] = self::round2( $buckets[ $key ][ $field ] + $amount );
			}

			foreach ( self::omzet_rows( $data, 'betaalmethode' ) as $row ) {
				$label                                       = $row['label'];
				$buckets[ $key ]['betaalmethoden'][ $label ] = self::round2(
					( $buckets[ $key ]['betaalmethoden'][ $label ] ?? 0 ) + (float) $row['bedrag']
				);
			}
		}

		ksort( $buckets );
		return array_values( $buckets );
	}

	/**
	 * Totals per category (Bestuur, Breuk en bederf, Businessclub, ...).
	 *
	 * @return array<int, array{categorie: string, bedrag: float, netto: float, btw: float, transacties: int}>
	 */
	public static function by_category( array $reports ): array {
		$totals = [];
		foreach ( $reports as $report ) {
			foreach ( self::omzet_rows( $report['data'], 'categorie' ) as $row ) {
				$label = $row['label'];
				if ( ! isset( $totals[ $label ] ) ) {
					$totals[ $label ] = [
						'categorie'   => $label,
						'bedrag'      => 0.0,
						'netto'       => 0.0,
						'btw'         => 0.0,
						'transacties' => 0,
					];
				}
				$totals[ $label ]['bedrag']       = self::round2( $totals[ $label ]['bedrag'] + (float) $row['bedrag'] );
				$totals[ $label ]['netto']        = self::round2( $totals[ $label ]['netto'] + (float) $row['netto'] );
				$totals[ $label ]['btw']          = self::round2( $totals[ $label ]['btw'] + (float) $row['hoog'] + (float) $row['laag'] );
				$totals[ $label ]['transacties'] += (int) ( $row['transacties'] ?? 0 );
			}
		}

		ksort( $totals );
		return array_values( $totals );
	}

	/**
	 * Totals per product.
	 *
	 * @return array<int, array{product: string, aantal: int, bruto: float, btw: float, netto: float, btw_groep: string|null}>
	 */
	public static function by_product( array $reports ): array {
		$totals = [];
		foreach ( $reports as $report ) {
			foreach ( $report['data']['producten'] ?? [] as $product ) {
				$name = $product['product'];
				if ( ! isset( $totals[ $name ] ) ) {
					$totals[ $name ] = [
						'product'   => $name,
						'aantal'    => 0,
						'bruto'     => 0.0,
						'btw'       => 0.0,
						'netto'     => 0.0,
						'btw_groep' => $product['btw_groep'] ?? null,
					];
				}
				$totals[ $name ]['aantal'] += (int) $product['aantal'];
				$totals[ $name ]['bruto']   = self::round2( $totals[ $name ]['bruto'] + (float) $product['bruto'] );
				$totals[ $name ]['btw']     = self::round2( $totals[ $name ]['btw'] + (float) $product['btw'] );
				$totals[ $name ]['netto']   = self::round2( $totals[ $name ]['netto'] + (float) $product['netto'] );
			}
		}

		// Most sold first.
		usort(
			$totals,
			static fn( array $a, array $b ): int => $b['aantal'] <=> $a['aantal'] ?: strcmp( $a['product'], $b['product'] )
		);
		return array_values( $totals );
	}

	/**
	 * VAT overview per tariff group (from the btw_type omzet rows).
	 *
	 * @return array<int, array{tarief: string, netto: float, btw_hoog: float, btw_laag: float, btw_totaal: float, bruto: float}>
	 */
	public static function vat_overview( array $reports ): array {
		$totals = [];
		foreach ( $reports as $report ) {
			foreach ( self::omzet_rows( $report['data'], 'btw_type' ) as $row ) {
				$label = $row['label'];
				if ( ! isset( $totals[ $label ] ) ) {
					$totals[ $label ] = [
						'tarief'     => $label,
						'netto'      => 0.0,
						'btw_hoog'   => 0.0,
						'btw_laag'   => 0.0,
						'btw_totaal' => 0.0,
						'bruto'      => 0.0,
					];
				}
				$hoog                           = (float) $row['hoog'];
				$laag                           = (float) $row['laag'];
				$totals[ $label ]['netto']      = self::round2( $totals[ $label ]['netto'] + (float) $row['netto'] );
				$totals[ $label ]['btw_hoog']   = self::round2( $totals[ $label ]['btw_hoog'] + $hoog );
				$totals[ $label ]['btw_laag']   = self::round2( $totals[ $label ]['btw_laag'] + $laag );
				$totals[ $label ]['btw_totaal'] = self::round2( $totals[ $label ]['btw_totaal'] + $hoog + $laag );
				$totals[ $label ]['bruto']      = self::round2( $totals[ $label ]['bruto'] + (float) $row['bedrag'] );
			}
		}

		ksort( $totals );
		return array_values( $totals );
	}

	/**
	 * Businessclub turnover per day, for monthly invoicing.
	 *
	 * @return array<int, array{datum: string, bedrag: float, netto: float, btw: float}>
	 */
	public static function businessclub_days( array $reports ): array {
		$days = [];
		foreach ( $reports as $report ) {
			$rows = self::omzet_rows( $report['data'], 'categorie', 'Businessclub' );
			if ( empty( $rows ) ) {
				continue;
			}
			$row    = $rows[0];
			$days[] = [
				'datum'  => substr( $report['period_start'], 0, 10 ),
				'bedrag' => self::round2( (float) $row['bedrag'] ),
				'netto'  => self::round2( (float) $row['netto'] ),
				'btw'    => self::round2( (float) $row['hoog'] + (float) $row['laag'] ),
			];
		}

		usort( $days, static fn( array $a, array $b ): int => strcmp( $a['datum'], $b['datum'] ) );
		return $days;
	}

	/**
	 * Round to 2 decimals, avoiding float dust in summed money values.
	 */
	private static function round2( float $value ): float {
		return round( $value, 2 );
	}
}
