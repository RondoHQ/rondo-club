<?php
/**
 * Membership Fees Service
 *
 * Handles membership fee settings storage and retrieval using the WordPress Options API.
 *
 * @package Stadion\Fees
 */

namespace Stadion\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Membership Fees service class
 */
class MembershipFees {

	/**
	 * Option key for storing all membership fee settings
	 */
	const OPTION_KEY = 'stadion_membership_fees';

	/**
	 * Default fee amounts (in euros)
	 *
	 * @var array<string, int>
	 */
	const DEFAULTS = [
		'mini'     => 130,
		'pupil'    => 180,
		'junior'   => 230,
		'senior'   => 255,
		'recreant' => 65,
		'donateur' => 55,
	];

	/**
	 * Valid fee type keys
	 *
	 * @var array<string>
	 */
	const VALID_TYPES = [ 'mini', 'pupil', 'junior', 'senior', 'recreant', 'donateur' ];

	/**
	 * Get all fee settings
	 *
	 * @return array<string, int> Array of fee type => amount pairs
	 */
	public function get_all_settings(): array {
		$stored = get_option( self::OPTION_KEY, [] );

		// Merge with defaults to ensure all keys exist
		$settings = array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : [] );

		// Ensure all values are integers
		return array_map( 'intval', $settings );
	}

	/**
	 * Get a single fee amount by type
	 *
	 * @param string $type The fee type (mini, pupil, junior, senior, recreant, donateur).
	 * @return int The fee amount in euros
	 */
	public function get_fee( string $type ): int {
		$settings = $this->get_all_settings();

		if ( ! isset( $settings[ $type ] ) ) {
			// Return 0 for unknown types
			return 0;
		}

		return $settings[ $type ];
	}

	/**
	 * Update fee settings
	 *
	 * @param array<string, mixed> $fees Array of fee type => amount pairs to update.
	 * @return bool True on success, false on failure
	 */
	public function update_settings( array $fees ): bool {
		// Get current settings
		$current = $this->get_all_settings();

		// Validate and merge new values
		foreach ( $fees as $type => $amount ) {
			// Skip invalid types
			if ( ! in_array( $type, self::VALID_TYPES, true ) ) {
				continue;
			}

			// Validate: must be numeric and non-negative
			if ( ! is_numeric( $amount ) || $amount < 0 ) {
				continue;
			}

			$current[ $type ] = (int) $amount;
		}

		return update_option( self::OPTION_KEY, $current );
	}
}
