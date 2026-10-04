<?php
/** Manual product groups for Twelve reporting; source reports remain unchanged. */
namespace Rondo\Twelve;

class ProductClassification {
	public const OPTION = 'rondo_twelve_product_groups';
	public const GROUPS = [
		'entree'     => 'Entree',
		'food'       => 'Food',
		'non_food'   => 'Non-food',
		'unassigned' => 'Nog indelen',
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

	/** Shares of gross product value, including no-sale, with unassigned value explicit. */
	public static function summary( array $products ): array {
		$amounts = array_fill_keys( array_keys( self::GROUPS ), 0 );
		foreach ( self::catalog( $products ) as $product ) {
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
			'total'  => $total / 100,
			'groups' => $rows,
		];
	}
}
