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
	 * Generate the next invoice number for the current calendar year.
	 * Format: 2026T001 (YYYY + T + zero-padded 3-digit sequential)
	 *
	 * Queries existing rondo_invoice posts for the current year to find the
	 * highest existing number, then increments by 1.
	 *
	 * @return string The generated invoice number (e.g., "2026T001")
	 */
	public static function generate_next(): string {
		// Get current year.
		$year = gmdate( 'Y' );
		$prefix = $year . 'T';

		// Query all invoices with numbers starting with this year's prefix.
		$query = new \WP_Query(
			[
				'post_type'      => 'rondo_invoice',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
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
		return $prefix . str_pad( (string) $next_number, 3, '0', STR_PAD_LEFT );
	}

	/**
	 * Validate invoice number format.
	 *
	 * Checks if the provided string matches the expected format:
	 * 4-digit year + 'T' + at least 3 digits (e.g., "2026T001")
	 *
	 * @param string $number The invoice number to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public static function is_valid( string $number ): bool {
		return (bool) preg_match( '/^\d{4}T\d{3,}$/', $number );
	}
}
