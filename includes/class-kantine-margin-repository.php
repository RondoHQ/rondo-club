<?php
/** Private invoice history and theoretical product margins, using native fields. */
namespace Rondo\Twelve;

use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;

class MarginRepository {
	public const PURCHASE = 'rondo_purchase';
	public const PRODUCT  = 'rondo_kassa_product';
	public const UNITS    = [ 'stuk', 'liter', 'kg', 'plak', 'zakje', 'staafje', 'zak', 'beker', 'vork', 'rol', 'factuurregel' ];
	public const STATUSES = [
		'ready'    => 'Berekenbaar',
		'base'     => 'Basisinkoop',
		'mapping'  => 'Koppeling controleren',
		'recipe'   => 'Recept ontbreekt',
		'portion'  => 'Portie ontbreekt',
		'vat'      => 'Btw controleren',
		'missing'  => 'Inkoop ontbreekt',
		'excluded' => 'Geen kantineproduct',
	];

	public function __construct() {
		add_action( 'init', [ $this, 'register' ] );
	}

	public function register(): void {
		foreach ( [
			self::PURCHASE => 'Kantine inkoopfacturen',
			self::PRODUCT  => 'Kantine kostprijzen',
		] as $type => $label ) {
			register_post_type(
				$type,
				[
					'label'        => $label,
					'public'       => false,
					'show_ui'      => false,
					'show_in_rest' => false,
					'supports'     => [ 'title' ],
				]
				);
		}
	}

	public static function valid_date( $value ): bool {
		$date = is_string( $value ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $value ) : false;
		return $date && $date->format( 'Y-m-d' ) === $value;
	}

	private static function number( $value, float $min = 0 ): bool {
		return ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) && $value >= $min && $value < 10000000;
	}

	private static function error( string $message, int $status = 400 ) {
		return new \WP_Error( 'kantine_margin_invalid', $message, [ 'status' => $status ] );
	}

	private static function records( string $type ): array {
		$result = [];
		foreach ( get_posts(
			[
				'post_type'        => $type,
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
			) as $post ) {
			$result[] = array_merge( [ 'id' => $post->ID ], Formatter::for_wire( $type, Fields::all_for_post( $post->ID ) ) );
		}
		return $result;
	}

	public function invoices(): array {
		$records = self::records( self::PURCHASE );
		foreach ( $records as &$record ) {
			$record['has_pdf'] = (bool) get_post_meta( $record['id'], '_kantine_pdf_base64', true );
		}
		unset( $record );
		usort( $records, static fn( $a, $b ) => strcmp( $b['invoice_date'], $a['invoice_date'] ) ?: $b['id'] <=> $a['id'] );
		return $records;
	}

	/** One weighted price per article per invoice; deposits and credit lines are separate. */
	public function articles( string $date ): array {
		$articles = [];
		foreach ( $this->invoices() as $invoice ) {
			$groups = [];
			foreach ( $invoice['lines'] as $line ) {
				if ( ! self::number( $line['amount'] ?? null ) || ( is_numeric( $line['quantity'] ) && $line['quantity'] <= 0 ) || in_array( $line['category'], [ 'Emballage', 'Referentie', 'Voorheffing', 'Huur', 'Transport' ], true ) ) {
					continue;
				}
				$code = $line['article'];
				if ( ! isset( $groups[ $code ] ) ) {
					$groups[ $code ] = array_merge(
						$line,
						[
							'quantity' => 0,
							'amount'   => 0,
						]
						);
				}
				// A supplier unit change cannot silently change the meaning of a recipe.
				if ( $groups[ $code ]['unit'] !== $line['unit'] || ! self::number( $line['quantity'] ?? null, 0.0000001 ) || ! in_array( $line['unit'], self::UNITS, true ) ) {
					$groups[ $code ]['unit'] = '';
				}
				$groups[ $code ]['quantity'] += $line['quantity'] ?? 0;
				$groups[ $code ]['amount']   += $line['amount'];
			}
			foreach ( $groups as $code => $line ) {
				$price = [
					'date'           => $invoice['invoice_date'],
					'invoice_id'     => $invoice['id'],
					'invoice_number' => $invoice['invoice_number'],
					'supplier'       => $invoice['supplier'],
					'source_file'    => $invoice['source_file'],
					'unit'           => $line['unit'],
					'units_per_pack' => $line['units_per_pack'],
					'pack_content'   => $line['pack_content'],
					'price'          => $line['unit'] !== '' && $line['quantity'] > 0 ? $line['amount'] / $line['quantity'] : null,
					'quantity'       => $line['quantity'],
					'amount'         => $line['amount'],
					'vat_rate'       => $line['vat_rate'],
					'note'           => $line['note'],
				];
				if ( ! isset( $articles[ $code ] ) ) {
					$articles[ $code ] = [
						'article'     => (string) $code,
						'description' => $line['description'],
						'history'     => [],
						'latest'      => null,
					];
				}
				$articles[ $code ]['history'][] = $price;
				if ( $invoice['invoice_date'] <= $date && $articles[ $code ]['latest'] === null ) {
					$articles[ $code ]['latest'] = $price;
				}
			}
		}
		return $articles;
	}

	public static function calculate( array $product, array $articles, string $date ): array {
		$prices = array_filter( $product['sale_prices'] ?? [], static fn( $price ) => $price['effective_date'] <= $date );
		usort( $prices, static fn( $a, $b ) => strcmp( $b['effective_date'], $a['effective_date'] ) );
		$price                        = $prices[0] ?? null;
		$product['sale_price']        = $price;
		$product['sale_net']          = $price ? $price['amount'] / ( 1 + $price['vat_rate'] / 100 ) : null;
		$product['cost']              = null;
		$product['margin']            = null;
		$product['margin_percentage'] = null;
		$product['cost_sources']      = [];
		$product['calculation_note']  = '';
		if ( ! in_array( $product['cost_status'], [ 'ready', 'base' ], true ) ) {
			$product['calculation_note'] = self::STATUSES[ $product['cost_status'] ] ?? 'Inkoop ontbreekt';
			return $product;
		}
		if ( ! $price || $price['amount'] <= 0 ) {
			$product['calculation_note'] = 'Geen verkoopprijs bekend op deze datum';
			return $product;
		}
		if ( empty( $product['ingredients'] ) ) {
			$product['calculation_note'] = 'Geen ingrediënten gekoppeld';
			return $product;
		}
		$cost = 0;
		foreach ( $product['ingredients'] as $ingredient ) {
			$source = $articles[ $ingredient['article'] ]['latest'] ?? null;
			if ( ! $source || ! self::number( $source['price'] ?? null ) || $source['unit'] !== $ingredient['unit'] || ! self::number( $ingredient['quantity'], 0.0000001 ) ) {
				$product['calculation_note'] = 'Inkoopprijs, hoeveelheid of eenheid ontbreekt op deze datum';
				return $product;
			}
			$cost                     += $source['price'] * $ingredient['quantity'];
			$product['cost_sources'][] = array_merge(
				$source,
				[
					'article'       => $ingredient['article'],
					'description'   => $articles[ $ingredient['article'] ]['description'],
					'used_quantity' => $ingredient['quantity'],
				]
				);
		}
		$product['cost']              = $cost;
		$product['margin']            = $product['sale_net'] - $cost;
		$product['margin_percentage'] = 100 * $product['margin'] / $product['sale_net'];
		return $product;
	}

	public function catalog( string $date ): array {
		$articles = $this->articles( $date );
		$products = [];
		foreach ( self::records( self::PRODUCT ) as $product ) {
			$products[] = self::calculate( $product, $articles, $date );
		}
		usort( $products, static fn( $a, $b ) => strnatcasecmp( $a['product_name'], $b['product_name'] ) );
		$invoices = $this->invoices();
		foreach ( $invoices as &$invoice ) {
			$invoice['line_count'] = count( $invoice['lines'] );
			unset( $invoice['lines'] );
		}
		unset( $invoice );
		return [
			'date'     => $date,
			'products' => $products,
			'articles' => array_values( $articles ),
			'invoices' => $invoices,
			'statuses' => self::STATUSES,
			'units'    => self::UNITS,
		];
	}

	/** The same import can be retried after a lost response without creating duplicate invoices. */
	public function save_purchase( array $data, string $pdf = '' ) {
		if ( ! self::valid_date( $data['invoice_date'] ?? null ) || ! is_string( $data['invoice_number'] ?? null ) || trim( $data['invoice_number'] ) === '' || ! is_string( $data['supplier'] ?? null ) || trim( $data['supplier'] ) === '' || ! is_array( $data['lines'] ?? null ) || ! $data['lines'] || count( $data['lines'] ) > 1000 || ! self::number( $data['vat_amount'] ?? null, -1000000 ) || ! self::number( $data['total_amount'] ?? null, -1000000 ) ) {
			return self::error( 'Factuurnummer, leverancier, datum, regels en totalen zijn verplicht.' );
		}
		$lines = [];
		$base  = 0;
		foreach ( $data['lines'] as $line ) {
			if ( ! is_array( $line ) || ! self::number( $line['packs'] ?? 1, -1000000 ) || ! self::number( $line['units_per_pack'] ?? 0 ) || ! is_string( $line['article'] ?? null ) || $line['article'] === '' || ! is_string( $line['description'] ?? null ) || ! self::number( $line['amount'] ?? null, -1000000 ) || ! self::number( $line['deposit'] ?? null, -1000000 ) || ! in_array( $line['vat_rate'] ?? null, [ 0, 9, 21 ], true ) || ( ( $line['quantity'] ?? null ) !== null && ! self::number( $line['quantity'], -1000000 ) ) || ! in_array( $line['unit'] ?? '', array_merge( self::UNITS, [ '' ] ), true ) ) {
				return self::error( 'Een factuurregel bevat een ongeldig bedrag, aantal, artikel of eenheid.' );
			}
			$lines[] = [
				'article'        => sanitize_text_field( $line['article'] ),
				'description'    => sanitize_text_field( $line['description'] ),
				'amount'         => $line['amount'],
				'deposit'        => $line['deposit'],
				'vat_rate'       => $line['vat_rate'],
				'packs'          => (float) ( $line['packs'] ?? 1 ),
				'pack_content'   => sanitize_text_field( $line['pack_content'] ?? '' ),
				'units_per_pack' => (float) ( $line['units_per_pack'] ?? 0 ),
				'quantity'       => $line['quantity'] ?? null,
				'unit'           => $line['unit'] ?? '',
				'category'       => sanitize_text_field( $line['category'] ?? 'Voeding' ),
				'note'           => sanitize_text_field( $line['note'] ?? '' ),
			];
			$base   += $line['amount'] + $line['deposit'];
		}
		if ( abs( round( $base + $data['vat_amount'] - $data['total_amount'], 2 ) ) > 0.01 ) {
			return self::error( 'Factuurregels, emballage en btw sluiten niet aan op het factuurtotaal. Er is niets opgeslagen.' );
		}
		$fields = [
			'invoice_number' => sanitize_text_field( $data['invoice_number'] ),
			'supplier'       => sanitize_text_field( $data['supplier'] ),
			'invoice_date'   => $data['invoice_date'],
			'source_file'    => sanitize_file_name( $data['source_file'] ?? '' ),
			'source_hash'    => hash( 'sha256', $pdf !== '' ? $pdf : wp_json_encode( $lines ) ),
			'vat_amount'     => $data['vat_amount'],
			'total_amount'   => $data['total_amount'],
			'lines'          => $lines,
		];
		return $this->locked(
				function () use ( $fields, $pdf ) {
					foreach ( $this->invoices() as $invoice ) {
						if ( $invoice['supplier'] !== $fields['supplier'] && array_intersect( array_column( $invoice['lines'], 'article' ), array_column( $fields['lines'], 'article' ) ) ) {
							return self::error( 'Dit artikelnummer hoort bij een andere leverancier. Gebruik een uniek artikelnummer met een leveranciersprefix.' );
						}
						if ( $invoice['invoice_number'] === $fields['invoice_number'] && $invoice['supplier'] === $fields['supplier'] ) {
							if ( $invoice['source_hash'] !== $fields['source_hash'] ) {
									return self::error( 'Dit factuurnummer bestaat al met een andere inhoud. Controleer de bestaande factuur.', 409 );
							}
							return [
								'id'        => $invoice['id'],
								'unchanged' => true,
							];
						}
					}
					$id = wp_insert_post(
					[
						'post_type'   => self::PURCHASE,
						'post_status' => 'publish',
						'post_title'  => $fields['supplier'] . ' ' . $fields['invoice_number'],
					],
					true
					);
					if ( is_wp_error( $id ) ) {
						return $id;
					}
					$result = Fields::update_many_for_post( $id, array_merge( $fields, [ 'revision' => wp_generate_uuid4() ] ) );
					if ( is_wp_error( $result ) ) {
							wp_delete_post( $id, true );
							return $result;
					}
					if ( $pdf !== '' && ! add_post_meta( $id, '_kantine_pdf_base64', base64_encode( $pdf ), true ) ) {
						wp_delete_post( $id, true );
						return self::error( 'De bronfactuur kon niet worden bewaard. Probeer opnieuw.', 500 );
					}
					return [
						'id'        => $id,
						'unchanged' => false,
					];
				}
			);
	}

	/** Product edits use a revision to prevent two users overwriting each other's recipes. */
	public function save_product( array $data ) {
		$id = (int) ( $data['id'] ?? 0 );
		if ( ! is_string( $data['twelve_id'] ?? null ) || ! preg_match( '/^\d+$/', $data['twelve_id'] ) || ! is_string( $data['product_name'] ?? null ) || trim( $data['product_name'] ) === '' || ! is_bool( $data['active'] ?? null ) || ! isset( self::STATUSES[ $data['cost_status'] ?? '' ] ) || ! is_array( $data['ingredients'] ?? null ) || count( $data['ingredients'] ) > 30 || ! is_array( $data['sale_prices'] ?? null ) || ! $data['sale_prices'] || count( $data['sale_prices'] ) > 300 ) {
			return self::error( 'Product, Twelve-ID, status, ingrediënten en verkoopprijzen zijn verplicht.' );
		}
		$ingredients = [];
		foreach ( $data['ingredients'] as $ingredient ) {
			if ( ! is_array( $ingredient ) || ! is_string( $ingredient['article'] ?? null ) || $ingredient['article'] === '' || ! in_array( $ingredient['unit'] ?? '', self::UNITS, true ) || ( ( $ingredient['quantity'] ?? null ) !== null && ! self::number( $ingredient['quantity'], 0.0000001 ) ) ) {
				return self::error( 'Elk gekoppeld artikel heeft een geldige eenheid en een positief aantal nodig.' );
			}
			$ingredients[] = [
				'article'  => sanitize_text_field( $ingredient['article'] ),
				'quantity' => $ingredient['quantity'] ?? null,
				'unit'     => $ingredient['unit'],
			];
		}
		$prices = [];
		$dates  = [];
		foreach ( $data['sale_prices'] as $price ) {
			if ( ! is_array( $price ) || ! self::valid_date( $price['effective_date'] ?? null ) || isset( $dates[ $price['effective_date'] ] ) || ! self::number( $price['amount'] ?? null ) || ! in_array( $price['vat_rate'] ?? null, [ 0, 9, 21 ], true ) ) {
				return self::error( 'Verkoopprijzen moeten een unieke ingangsdatum, geldig bedrag en btw-tarief hebben.' );
			}
			$dates[ $price['effective_date'] ] = true;
			$prices[]                          = [
				'effective_date' => $price['effective_date'],
				'amount'         => $price['amount'],
				'vat_rate'       => $price['vat_rate'],
				'source'         => sanitize_text_field( $price['source'] ?? 'Handmatig' ),
			];
		}
		$fields = [
			'twelve_id'    => $data['twelve_id'],
			'product_name' => sanitize_text_field( $data['product_name'] ),
			'active'       => $data['active'],
			'cost_status'  => $data['cost_status'],
			'cost_note'    => sanitize_textarea_field( $data['cost_note'] ?? '' ),
			'ingredients'  => $ingredients,
			'sale_prices'  => $prices,
		];
		return $this->locked(
				function () use ( $id, $data, $fields ) {
					$record_id = $id;
					foreach ( self::records( self::PRODUCT ) as $existing ) {
						if ( $existing['twelve_id'] !== $fields['twelve_id'] ) {
							continue;
						}
						if ( $id === 0 ) {
							$existing_id = $existing['id'];
							unset( $existing['id'], $existing['revision'] );
							if ( $existing == $fields ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Native numbers normalize integer-valued floats.
								return [
									'id'        => $existing_id,
									'unchanged' => true,
								];
							}
							return self::error( 'Dit Twelve-product bestaat al. Open het product om wijzigingen op te slaan.', 409 );
						}
						if ( $existing['id'] !== $id || ( $data['revision'] ?? '' ) !== $existing['revision'] ) {
							return self::error( 'Het product is intussen gewijzigd. Herlaad het overzicht voordat je opslaat.', 409 );
						}
					}
					if ( $id && ( get_post_type( $id ) !== self::PRODUCT || Fields::get_for_post( $id, 'twelve_id' ) !== $fields['twelve_id'] ) ) {
						return self::error( 'Product niet gevonden.', 404 );
					}
					if ( ! $record_id ) {
						$record_id = wp_insert_post(
							[
								'post_type'   => self::PRODUCT,
								'post_status' => 'publish',
								'post_title'  => $fields['product_name'],
							],
							true
						);
						if ( is_wp_error( $record_id ) ) {
							return $record_id;
						}
					}
					$result = Fields::update_many_for_post( $record_id, array_merge( $fields, [ 'revision' => wp_generate_uuid4() ] ) );
					if ( is_wp_error( $result ) && ! $id ) {
						wp_delete_post( $record_id, true );
					}
					return is_wp_error( $result ) ? $result : [
						'id'        => $record_id,
						'unchanged' => false,
					];
				}
			);
	}

	private function locked( callable $callback ) {
		if ( ! add_option( 'rondo_kantine_margin_lock', time(), '', false ) ) {
			return self::error( 'Kostprijzen worden al bijgewerkt. Probeer opnieuw.', 409 );
		}
		try {
			return $callback();
		} finally {
			delete_option( 'rondo_kantine_margin_lock' );
		}
	}
}
