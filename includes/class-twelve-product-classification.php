<?php
/** Manual product groups for Twelve reporting; source reports remain unchanged. */
namespace Rondo\Twelve;

class ProductClassification {
	public const OPTION = 'rondo_twelve_product_groups';
	public const GROUPS = [
		'entree'      => 'Entree',
		'food'        => 'Eten',
		'non_food'    => 'Drank',
		'other'       => 'Overig',
		'merchandise' => 'Merchandise',
		'unassigned'  => 'Nog indelen',
	];

	public static function catalog( array $products ): array {
		$groups = get_option( self::OPTION, [] );
		foreach ( $products as &$product ) {
			$product['id']    = hash( 'sha256', $product['product'] );
			$product['group'] = $groups[ $product['id'] ] ?? 'unassigned';
		}
		unset( $product );
		usort( $products, static fn( $a, $b ) => strnatcasecmp( $a['product'], $b['product'] ) );
		return $products;
	}

	/** Product-name hashes excluded from revenue, while source records stay intact. */
	public static function excluded_products(): array {
		return array_keys( array_filter( get_option( self::OPTION, [] ), static fn( $group ) => in_array( $group, [ 'other', 'merchandise' ], true ) ) );
	}

	/** Shares of gross product value, including no-sale, with unassigned value explicit. */
	public static function summary( array $products ): array {
		return self::summarize_catalog( self::catalog( $products ) );
	}

	/** Product values and quantities per imported calendar bucket, including no-sale. */
	public static function by_period( array $reports, string $group ): array {
		$periods = [];
		foreach ( $reports as $report ) {
			$key               = substr( $report['period_start'], 0, $group === 'month' ? 7 : 10 );
			$periods[ $key ][] = $report;
		}
		$result = [];
		foreach ( $periods as $period => $items ) {
			$catalog                       = self::catalog( ReportAggregator::by_product( $items ) );
			$result[ $period ]             = self::summarize_catalog( $catalog );
			$result[ $period ]['products'] = array_map(
				static fn( $product ) => [
					'id'       => $product['id'],
					'name'     => $product['product'],
					'group'    => $product['group'],
					'amount'   => $product['bruto'],
					'quantity' => $product['aantal'],
				],
				$catalog
			);
		}
		return $result;
	}

	private static function summarize_catalog( array $catalog ): array {
		$unassigned = 0;
		$amounts    = array_fill_keys( array_keys( self::GROUPS ), 0 );
		foreach ( $catalog as $product ) {
			if ( $product['group'] === 'unassigned' ) {
				++$unassigned;
			}
			$amounts[ $product['group'] ] += (int) round( $product['bruto'] * 100 );
		}
		$total = array_sum( $amounts );
		$rows  = [];
		foreach ( self::GROUPS as $key => $label ) {
			$rows[] = [
				'group'      => $key,
				'label'      => $label,
				'amount'     => $amounts[ $key ] / 100,
				'percentage' => $total > 0 ? round( 100 * $amounts[ $key ] / $total, 2 ) : null,
			];
		}
		return [
			'unassigned_count' => $unassigned,
			'total'            => $total / 100,
			'groups'           => $rows,
		];
	}
}
