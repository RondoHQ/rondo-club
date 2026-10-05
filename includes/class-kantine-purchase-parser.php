<?php
/** Recognise Van Altena's fixed invoice layout; reconcile before offering a preview. */
namespace Rondo\Twelve;

class PurchaseParser {
	private static function amount( string $text ): ?float {
		$text = trim( str_replace( '*', '', $text ) );
		if ( $text === '' ) {
			return null;
		}
		if ( ! preg_match( '/^\d[\d.,]*-?$/', $text ) ) {
			throw new \RuntimeException( 'Onleesbaar factuurbedrag.' );
		}
		return (float) str_replace( [ '.', ',', '-' ], [ '', '.', '' ], $text ) * ( str_ends_with( $text, '-' ) ? -1 : 1 );
	}

	public function parse( string $bytes, string $filename, array $articles = [] ) {
		if ( strlen( $bytes ) > 5 * 1024 * 1024 || ! str_starts_with( $bytes, '%PDF-' ) ) {
			return new \WP_Error( 'kantine_pdf_invalid', 'Kies een PDF-factuur van maximaal 5 MB.', [ 'status' => 400 ] );
		}
		try {
			$pdf = ( new \Smalot\PdfParser\Parser() )->parseContent( $bytes );
			if ( count( $pdf->getPages() ) > 30 ) {
				throw new \RuntimeException( 'Factuur bevat te veel pagina’s.' );
			}
			$text = $pdf->getText();
			if ( ! preg_match( '/(\d{8})\s+(\d{2}\/\d{2}\/\d{2})/', $text, $header ) || ! preg_match( '/Totaal te betalen EUR\s+([\d.,]+-?)/', $text, $total ) ) {
				throw new \RuntimeException( 'Deze PDF heeft geen herkenbare Van Altena-factuuropmaak.' );
			}
			$date = \DateTimeImmutable::createFromFormat( '!d/m/y', $header[2] );
			if ( ! $date || $date->format( 'd/m/y' ) !== $header[2] ) {
				throw new \RuntimeException( 'Factuurdatum is ongeldig.' );
			}
			$lines = [];
			$vat   = null;
			foreach ( $pdf->getPages() as $page ) {
				foreach ( $page->getDataTm() as $item ) {
					$raw = $item[1];
					if ( str_contains( $raw, 'Totaal te betalen EUR' ) && preg_match( '/^\s*([\d.,]+-?)\s+([\d.,]+-?)\s+Totaal/', $raw, $footer ) ) {
						$vat = self::amount( $footer[2] );
					}
					$bounds  = [ 5, 13, 19, 26, 55, 65, 75, 84, 94, 104, 113, 117 ];
					$columns = [];
					for ( $i = 0; $i < 11; ++$i ) {
						$columns[] = trim( mb_substr( $raw, $bounds[ $i ], $bounds[ $i + 1 ] - $bounds[ $i ] ) );
					}
					$levy = str_contains( $columns[3], 'VOORHEFF.' );
					if ( ! $levy && ! preg_match( '/^\d{1,6}$/', $columns[0] ) ) {
						continue;
					}
					if ( ! preg_match( '/^\d+[-*]{0,2}$/', str_replace( ' ', '', $columns[1] ) ) ) {
						continue;
					}
					$code       = $levy ? 'VOORHEFF' : $columns[0];
					$category   = $levy ? 'Voorheffing' : ( str_starts_with( $code, '999' ) ? 'Emballage' : ( in_array( $code, [ '8', '9' ], true ) ? 'Referentie' : ( in_array( $code, [ '878545', '878577' ], true ) ? 'Huur' : 'Voeding' ) ) );
					$packs      = self::amount( str_replace( ' ', '', $columns[1] ) ) ?? 0;
					$known      = $articles[ $code ]['latest'] ?? null;
					$multiplier = ( $known['pack_content'] ?? '' ) === $columns[2] ? ( $known['units_per_pack'] ?? 0 ) : 0;
					$partial    = str_contains( $columns[2], '*' );
					$amount     = self::amount( $columns[9] ) ?? 0;
					$deposit    = self::amount( $columns[5] ) ?? 0;
					$lines[]    = [
						'article'        => $code,
						'description'    => $columns[3],
						'amount'         => $levy ? 0 : $amount,
						'deposit'        => $levy ? ( $deposit ?: $amount ) : $deposit,
						'vat_rate'       => $columns[10] === 'H' ? 21 : ( $columns[10] === 'L' ? 9 : 0 ),
						'packs'          => $packs,
						'pack_content'   => $columns[2],
						'units_per_pack' => $multiplier,
						'quantity'       => $partial ? null : ( $multiplier > 0 ? $packs * $multiplier : null ),
						'unit'           => $known['unit'] ?? '',
						'category'       => $category,
						'note'           => $partial ? 'Deellevering: controleer aantal en eenheid' : ( $known['note'] ?? 'Controleer de verpakking en vul het totale aantal eenheden in' ),
					];
				}
			}
			$sum   = array_sum( array_map( static fn( $line ) => $line['amount'] + $line['deposit'], $lines ) );
			$gross = self::amount( $total[1] );
			if ( ! $lines || $vat === null || abs( round( $sum + $vat - $gross, 2 ) ) > 0.01 ) {
				throw new \RuntimeException( 'Niet alle factuurregels sluiten aan op het totaal. De PDF is niet opgeslagen.' );
			}
			return [
				'invoice_number' => $header[1],
				'supplier'       => 'Van Altena',
				'invoice_date'   => $date->format( 'Y-m-d' ),
				'source_file'    => $filename,
				'vat_amount'     => $vat,
				'total_amount'   => $gross,
				'lines'          => $lines,
			];
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'kantine_pdf_parse', 'Factuur niet ingelezen: ' . $error->getMessage(), [ 'status' => 400 ] );
		}
	}
}
