<?php
/** Agreement-based AWC 1 calculation and the verified Nmbrs workbook contract. */
namespace Rondo\Matches;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class CompensationPayroll {
	public static function month_name( string $month ): string {
		return [ 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' ][ (int) substr( $month, 5, 2 ) - 1 ];
	}
	public static function key( array $row ): string {
		return $row['person_id'] . ':' . ( $row['payment_route'] ?? '' ) . ':' . ( $row['nmbrs_employee_number'] ?? '' );
	}
	private static function row( array $person, array $agreement ): array {
		return [
			'person_id'             => $person['person_id'],
			'player_name'           => $person['player_name'],
			'payment_route'         => $agreement['payment_route'],
			'nmbrs_employee_number' => $agreement['nmbrs_employee_number'],
			'nmbrs_name'            => $agreement['nmbrs_name'],
			'days'                  => 0,
			'basis'                 => 0,
			'bank'                  => 0,
			'wins'                  => 0,
			'draws'                 => 0,
			'amount_cents'          => 0,
		];
	}
	public static function calculate( array $matches, array $people, string $month ): array {
		$rows        = [];
		$allocations = [];
		$errors      = [];
		$period      = [
			'valid_from'  => $month . '-01',
			'valid_until' => ( new \DateTimeImmutable( $month . '-01' ) )->format( 'Y-m-t' ),
		];
		$by_id       = array_column( $people, null, 'person_id' );
		foreach ( $people as $person ) {
			foreach ( $person['agreements'] as $agreement ) {
				if ( $agreement['payment_route'] === 'payroll' && CompensationAgreements::overlap( $agreement, $period ) ) {
					$row                       = self::row( $person, $agreement );
					$rows[ self::key( $row ) ] = $row;
				}
			}
		}
		foreach ( $matches as $match ) {
			if ( $match['phase'] !== 'completed' || ! in_array( $match['category'], [ 'competitie', 'nacompetitie' ], true ) ) {
				continue;
			}
			foreach ( $match['selection'] as $player ) {
				if ( ! in_array( $player['participation'], [ 'basis', 'bank' ], true ) ) {
					continue;
				}
				$agreements = array_values( array_filter( $by_id[ $player['person_id'] ]['agreements'] ?? [], static fn( $a ) => $a['valid_from'] <= $match['played_on'] && ( ! $a['valid_until'] || $a['valid_until'] >= $match['played_on'] ) ) );
				if ( count( $agreements ) !== 1 ) {
					$errors[]  = $player['player_name'] . ': geen eenduidige afspraak op ' . $match['played_on'] . '.';
					$agreement = [
						'payment_route'         => 'unknown',
						'nmbrs_employee_number' => '',
						'nmbrs_name'            => '',
					];
				} else {
					$agreement = $agreements[0];
				}
				$row = self::row( $player, $agreement );
				$key = self::key( $row );
				$allocations[ ( $match['id'] ?? $match['source_match_id'] ) . ':' . $player['person_id'] ] = [
					'person_id'     => $player['person_id'],
					'payment_route' => $agreement['payment_route'],
					'key'           => $key,
				];
				$rows[ $key ]      ??= $row;
				$single              = $match;
				$single['selection'] = [ $player ];
				$count               = CompensationCalculator::calculate( [ $single ], 'awc1' )[0];
				foreach ( [ 'days', 'basis', 'bank', 'wins', 'draws' ] as $field ) {
					$rows[ $key ][ $field ] += $count[ $field ];
				}
				if ( $agreement['payment_route'] === 'sepa_guest' ) {
					$rows[ $key ]['amount_cents'] += 2000 + $count['amount_cents'];
				}
			}
		}
		ksort( $rows );
		return [
			'allocations' => $allocations,
			'rows'        => array_values( $rows ),
			'errors'      => array_values( array_unique( $errors ) ),
		];
	}
	/** Strings are explicit cells, never formulas. Null counts remain truly empty. */
	public static function workbook( array $batch, array $config ) {
		$book = new Spreadsheet();
		$book->getActiveSheet()->setTitle( 'Start' )->setCellValueExplicit( 'A1', 'LooncomponentenVar', DataType::TYPE_STRING );
		$sheet = $book->createSheet()->setTitle( 'LooncomponentenVar' );
		$book->createSheet()->setTitle( 'Blad1' )->setSheetState( Worksheet::SHEETSTATE_HIDDEN );
		$book->createSheet()->setTitle( 'Kostenverdeling (variabel)' )->setSheetState( Worksheet::SHEETSTATE_HIDDEN );
		$headers = [
			'A1' => 'Import',
			'H1' => 'Wedstrijdvergoeding (basis) aantal',
			'I1' => 'Wedstrijdvergoeding (bank) aantal',
			'J1' => 'Wedstrijdpremie (winst)',
			'K1' => 'Wedstrijdpremie (gelijkspel)',
		];
		foreach ( $headers as $cell => $value ) {
			$sheet->setCellValueExplicit( $cell, $value, DataType::TYPE_STRING );
		}
		foreach ( [ 'Debiteurnr', 'Bedrijfnr', 'Persnr', 'Naam', 'Periode', 'Dagen', 'DagenVoorLC', $config['basis_code'], $config['bank_code'], $config['win_code'], $config['draw_code'] ] as $i => $value ) {
			$sheet->setCellValueExplicit( [ $i + 1, 2 ], $value, DataType::TYPE_STRING );
		}
		$n = 3;
		foreach ( $batch['rows'] as $row ) {
			if ( ! preg_match( '/^[1-9][0-9]{0,13}$/D', $row['nmbrs_employee_number'] ) ) {
				return CompensationService::error( 'Dit personeelsnummer past niet verliesloos in het gecontroleerde numerieke importformaat.' );
			}
			$values = [ (string) $config['debtor_number'], (int) $config['company_number'], (int) $row['nmbrs_employee_number'], $row['nmbrs_name'], $batch['month'], $row['days'] ?: null, null, $row['basis'] ?: null, $row['bank'] ?: null, $row['wins'] ?: null, $row['draws'] ?: null ];
			foreach ( $values as $i => $value ) {
				if ( $value !== null ) {
					$sheet->setCellValueExplicit( [ $i + 1, $n ], $value, is_int( $value ) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING );
				}
			}
			++$n;
		}
		$book->setActiveSheetIndex( 1 );
		$stream = fopen( 'php://memory', 'w+' );
		try {
			( new Xlsx( $book ) )->save( $stream );
			rewind( $stream );
			$content = stream_get_contents( $stream );
		} finally {
			fclose( $stream );
			$book->disconnectWorksheets();
		}
		return [
			'content'  => base64_encode( $content ),
			'encoding' => 'base64',
			'mime'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'filename' => $config['debtor_number'] . ' L ' . str_replace( '-', ' ', $batch['month'] ) . ' Import_sheet_mutaties_SV_AWC_' . self::month_name( $batch['month'] ) . '.xlsx',
		];
	}
}
