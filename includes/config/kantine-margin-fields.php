<?php
/** Native fields for private purchase invoices and Twelve product costing. */
return ( static function () {
	$field       = static function ( string $context, string $name, string $type = 'text', array $extra = [] ): array {
		return array_merge(
			[
				'canonical_name' => $name,
				'storage_name'   => $name,
				'name'           => $name,
				'key'            => 'field_' . $context . '_' . $name,
				'label'          => $name,
				'type'           => $type,
			],
			$extra
			);
	};
	$definitions = [
		'rondo_purchase'      => [
			'invoice_number' => 'text',
			'supplier'       => 'text',
			'invoice_date'   => 'date_picker',
			'source_file'    => 'text',
			'source_hash'    => 'text',
			'vat_amount'     => 'number',
			'total_amount'   => 'number',
			'revision'       => 'text',
		],
		'rondo_kassa_product' => [
			'twelve_id'    => 'text',
			'product_name' => 'text',
			'active'       => 'true_false',
			'cost_status'  => 'text',
			'cost_note'    => 'textarea',
			'revision'     => 'text',
		],
	];
	$repeaters   = [
		'rondo_purchase'      => [
			'lines' => [
				'article'        => 'text',
				'description'    => 'text',
				'amount'         => 'number',
				'deposit'        => 'number',
				'vat_rate'       => 'number',
				'packs'          => 'number',
				'pack_content'   => 'text',
				'units_per_pack' => 'number',
				'quantity'       => 'number',
				'unit'           => 'text',
				'category'       => 'text',
				'note'           => 'text',
			],
		],
		'rondo_kassa_product' => [
			'ingredients' => [
				'article'  => 'text',
				'quantity' => 'number',
				'unit'     => 'text',
			],
			'sale_prices' => [
				'effective_date' => 'date_picker',
				'amount'         => 'number',
				'vat_rate'       => 'number',
				'source'         => 'text',
			],
		],
	];
	$result      = [];
	foreach ( $definitions as $context => $types ) {
		$fields = [];
		foreach ( $types as $name => $type ) {
			$fields[ $name ] = $field( $context, $name, $type );
		}
		foreach ( $repeaters[ $context ] as $name => $children ) {
			$sub_fields = [];
			foreach ( $children as $child => $type ) {
				$sub_fields[ $child ] = $field( $context . '_' . $name, $child, $type );
			}
			$fields[ $name ] = $field( $context, $name, 'repeater', [ 'sub_fields' => $sub_fields ] );
		}
		$result[ $context ] = [
			'kind'   => 'post',
			'fields' => $fields,
		];
	}
	return $result;
} )();
