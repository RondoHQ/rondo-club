<?php
/**
 * Invoice Numbering Service
 *
 * Handles generation and validation of invoice numbers.
 */

namespace Rondo\Finance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InvoiceNumbering {

	/**
	 * Number format mapping by invoice type.
	 *
	 * @var array<string,array{prefix:string,pad:int}>
	 */
	private const TYPE_FORMAT_MAP = [
		'manual'     => [
			'prefix' => 'F',
			'pad'    => 3,
		],
		'discipline' => [
			'prefix' => 'T',
			'pad'    => 3,
		],
		'membership' => [
			'prefix' => 'C',
			'pad'    => 3,
		],
	];

	/**
	 * Fallback type for unknown/legacy invoice types.
	 *
	 * @var string
	 */
	private const DEFAULT_TYPE = 'manual';

	/**
	 * Generate the next invoice number for the current calendar year.
	 *
	 * Format: 2026XNNN... (YYYY + type prefix + zero-padded sequential)
	 * Prefixes:
	 * - manual: F
	 * - discipline: T
	 * - membership/contributie: C
	 *
	 * Sequence is per prefix and resets every calendar year.
	 * Number suffix is zero-padded to 3 digits.
	 *
	 * Queries existing rondo_invoice posts for the current year and type prefix to find
	 * the highest existing number, then increments by 1.
	 *
	 * @param string $type Invoice type slug (manual|discipline|membership).
	 * @return string The generated invoice number (e.g., "2026F0001").
	 */
	public static function generate_next( string $type = 'manual' ): string {
		$format = self::get_type_format( $type );

		// Get current year.
		$year   = gmdate( 'Y' );
		$prefix = $year . $format['prefix'];
		$pad    = $format['pad'];

		// Query all invoices with numbers starting with this year's prefix.
		$query = new \WP_Query(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => 'invoice_number',
						'value'   => $prefix,
						'compare' => 'LIKE',
					],
				],
			]
		);

		$max_number = 0;

		// Extract numeric suffixes and find the highest.
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $post_id ) {
				$invoice_number = get_post_meta( $post_id, 'invoice_number', true );
				if ( $invoice_number && strpos( $invoice_number, $prefix ) === 0 ) {
					// Extract the numeric part after the prefix.
					$suffix = substr( $invoice_number, strlen( $prefix ) );
					if ( is_numeric( $suffix ) ) {
						$number = (int) $suffix;
						if ( $number > $max_number ) {
							$max_number = $number;
						}
					}
				}
			}
		}

		// Increment to get the next number.
		$next_number = $max_number + 1;

		// Return formatted number with zero-padding.
		return $prefix . str_pad( (string) $next_number, $pad, '0', STR_PAD_LEFT );
	}

	/**
	 * Resolve invoice type to number format.
	 *
	 * @param string $type Invoice type slug.
	 * @return array{prefix:string,pad:int} Format settings.
	 */
	private static function get_type_format( string $type ): array {
		$normalized = sanitize_key( $type );
		return self::TYPE_FORMAT_MAP[ $normalized ] ?? self::TYPE_FORMAT_MAP[ self::DEFAULT_TYPE ];
	}

	/**
	 * Validate invoice number format.
	 *
	 * Checks if the provided string matches the expected format:
	 * 4-digit year + one of F/T/C + at least 3 digits (e.g., "2026F0001")
	 *
	 * @param string $number The invoice number to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $number ): bool {
		return (bool) preg_match( '/^\d{4}[FTC]\d{3,}$/', $number );
	}
}
