<?php
/**
 * Family Grouping Service
 *
 * Owns everything related to grouping persons into families by address and
 * assigning family-discount positions for the fee system: address parsing,
 * family key generation, building the full family-groups map, and the bulk
 * and per-person position recalculation flows.
 *
 * Extracted from MembershipFees in Phase 215 of the v33.0 Fee Service
 * Decomposition milestone. Phase 216 rewired the base-fee call through a
 * deferred closure pointing at FeeCalculator; Phase 217 swapped the
 * settings reads for MembershipFeeSettings; Phase 218 replaced the final
 * MembershipFees reference with PersonFeeContext, retiring the god class.
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Family grouping service.
 */
class FamilyGroupingService {

	/**
	 * Person fee context collaborator (Phase 218).
	 *
	 * Provides `is_former_member_in_season()` — reads canonical fields and
	 * compares timestamps. Before Phase 218 this was reached via a
	 * MembershipFees god-class reference; now it's on an explicit typed
	 * service with zero dependencies.
	 *
	 * @var PersonFeeContext
	 */
	private PersonFeeContext $person_context;

	/**
	 * Membership fee settings repository (Phase 217).
	 *
	 * Provides `get_youth_category_slugs()` and `get_family_discount_rate()`.
	 *
	 * @var MembershipFeeSettings
	 */
	private MembershipFeeSettings $settings;

	/**
	 * Deferred base-fee calculator callable.
	 *
	 * Signature: (int $person_id, ?string $season): ?array
	 *
	 * Wired in Phase 216 to point at FeeCalculator::calculate_fee(), via
	 * a closure that resolves the shared FeeCalculator instance at
	 * invocation time (not construction time). This breaks the circular
	 * dependency with FeeCalculator.
	 *
	 * @var callable
	 */
	private $fee_calculator;

	/**
	 * Constructor.
	 *
	 * @param PersonFeeContext      $person_context Person-data helper (Phase 218).
	 * @param MembershipFeeSettings $settings       Settings repository (Phase 217).
	 * @param callable              $fee_calculator Deferred base-fee calculator: (int, ?string) => ?array.
	 */
	public function __construct( PersonFeeContext $person_context, MembershipFeeSettings $settings, callable $fee_calculator ) {
		$this->person_context = $person_context;
		$this->settings       = $settings;
		$this->fee_calculator = $fee_calculator;
	}

	/**
	 * Invoke the deferred base-fee calculator.
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return array|null Fee data array or null if not calculable.
	 */
	private function calculate_base_fee( int $person_id, ?string $season = null ): ?array {
		return ( $this->fee_calculator )( $person_id, $season );
	}

	/**
	 * Normalize a Dutch postal code.
	 *
	 * Removes whitespace and converts to uppercase. Dutch postal codes
	 * have format "1234AB" (4 digits + 2 letters).
	 *
	 * @param string $postal_code The postal code to normalize.
	 * @return string Normalized postal code (e.g., "1234 ab" -> "1234AB").
	 */
	public function normalize_postal_code( string $postal_code ): string {
		// Trim, remove all whitespace, and convert to uppercase
		$trimmed   = trim( $postal_code );
		$no_spaces = preg_replace( '/\s+/', '', $trimmed );

		return strtoupper( $no_spaces );
	}

	/**
	 * Extract house number from a street address.
	 *
	 * Parses the house number (with optional addition) from a street address.
	 * Supports formats like "Kerkstraat 12", "Kerkstraat 12A", "Straat 7-bis".
	 *
	 * @param string $street The street address to parse.
	 * @return string|null House number with addition (e.g., "12A") or null if not found.
	 */
	public function extract_house_number( string $street ): ?string {
		$trimmed = trim( $street );

		if ( empty( $trimmed ) ) {
			return null;
		}

		// Match number at end of street, optionally followed by addition
		// Examples: "Straat 12", "Straat 12A", "Straat 12-A", "Straat 12/A"
		if ( preg_match( '/(\d+)\s*[-\/]?\s*([a-zA-Z0-9]*)$/', $trimmed, $matches ) ) {
			$number   = $matches[1];
			$addition = strtoupper( trim( $matches[2] ) );

			if ( ! empty( $addition ) ) {
				return $number . $addition;
			}

			return $number;
		}

		return null;
	}

	/**
	 * Get the family grouping key for a person.
	 *
	 * Generates a unique key based on the person's address for grouping
	 * family members. Uses postal code + house number (ignores street name).
	 * House number additions ARE significant (12A and 12B are different families).
	 *
	 * @param int $person_id The person post ID.
	 * @return string|null Family key (e.g., "1234AB-12A") or null if address incomplete.
	 */
	public function get_family_key( int $person_id ): ?string {
		// Get addresses from person
		$addresses = \Rondo\Fields\Fields::get_for_post( $person_id, 'addresses' ) ?: [];
		return $this->get_family_key_from_addresses( $addresses );
	}

	/**
	 * Build a family key from an address payload, including a pre-update value.
	 *
	 * @param mixed $addresses Native address repeater rows.
	 */
	public function get_family_key_from_addresses( $addresses ): ?string {

		if ( ! is_array( $addresses ) || empty( $addresses ) || ! is_array( $addresses[0] ) ) {
			return null;
		}

		// Use first address as primary
		$primary      = $addresses[0];
		$postal_code  = $primary['postal_code'] ?? '';
		$house_number = trim( ( $primary['house_number'] ?? '' ) . ( $primary['house_number_addition'] ?? '' ) );

		// Require both postal code and house number
		if ( empty( $postal_code ) || empty( $house_number ) ) {
			return null;
		}

		// Normalize postal code
		$normalized_postal = $this->normalize_postal_code( $postal_code );

		// Validate postal code format (4 digits + 2 letters)
		if ( ! preg_match( '/^\d{4}[A-Z]{2}$/', $normalized_postal ) ) {
			return null;
		}

		// Return family key: POSTALCODE-HOUSENUMBER
		return $normalized_postal . '-' . $house_number;
	}

	/**
	 * Build family groups from youth members.
	 *
	 * Groups youth members (mini, pupil, junior) by family key (address).
	 * Only includes members with valid addresses and calculable fees.
	 * Members within each family are sorted by base_fee descending.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return array{
	 *     families: array<string, array<int>>,
	 *     person_data: array<int, array{person_id: int, family_key: string, base_fee: int, category: string}>
	 * } Family groups and person data.
	 */
	public function build_family_groups( ?string $season = null ): array {
		// Resolve season for consistent usage
		$season = $season ?: SeasonKey::current();

		// Query all person posts (suppress_filters to bypass access control in CLI/cron contexts)
		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		$families    = [];
		$person_data = [];

		// Youth categories eligible for family discount
		$youth_categories = $this->settings->get_youth_category_slugs( $season );

		foreach ( $query->posts as $person_id ) {
			// Family discounts are based on the current household, never on former members.
			if ( (bool) \Rondo\Fields\Fields::get_for_post( $person_id, 'former_member' ) ) {
				continue;
			}

			// Calculate fee for this person using season-specific rates
			$fee_data = $this->calculate_base_fee( $person_id, $season );

			// Skip if not calculable
			if ( $fee_data === null ) {
				continue;
			}

			// Skip if not a youth category (FAM-05: only youth eligible)
			if ( ! in_array( $fee_data['category'], $youth_categories, true ) ) {
				continue;
			}

			// Get family key from address
			$family_key = $this->get_family_key( $person_id );

			// Skip if no valid address
			if ( $family_key === null ) {
				continue;
			}

			// Store person data
			$person_data[ $person_id ] = [
				'person_id'  => $person_id,
				'family_key' => $family_key,
				'base_fee'   => $fee_data['base_fee'],
				'category'   => $fee_data['category'],
			];

			// Add to family group
			if ( ! isset( $families[ $family_key ] ) ) {
				$families[ $family_key ] = [];
			}
			$families[ $family_key ][] = $person_id;
		}

		// Sort members within each family by base_fee descending (highest fee = position 1)
		foreach ( $families as $key => $members ) {
			usort(
				$members,
				function ( $a, $b ) use ( $person_data ) {
					$fee_a = $person_data[ $a ]['base_fee'];
					$fee_b = $person_data[ $b ]['base_fee'];

					// Sort by fee descending (highest first)
					if ( $fee_a !== $fee_b ) {
						return $fee_b - $fee_a;
					}

					// Tie-breaker: lower person_id first
					return $a - $b;
				}
			);

			$families[ $key ] = $members;
		}

		return [
			'families'    => $families,
			'person_data' => $person_data,
		];
	}

	/**
	 * Recalculate family discount positions for all persons.
	 *
	 * Builds family groups once and stores _family_discount_rate and
	 * _family_discount_position as flat post meta on each person.
	 * Non-youth, single-member family, or no-address persons get rate=0, position=empty.
	 *
	 * @param string|null $season Optional season key, defaults to current season.
	 * @return int Number of persons updated.
	 */
	public function recalculate_all_family_positions( ?string $season = null ): int {
		$season = $season ?: SeasonKey::current();
		$groups = $this->build_family_groups( $season );

		$families    = $groups['families'];
		$person_data = $groups['person_data'];
		$updated     = 0;

		// Track which person IDs we process via family groups
		$processed_ids = [];

		// Process multi-member families: assign positions and discount rates
		foreach ( $families as $family_key => $members ) {
			if ( count( $members ) <= 1 ) {
				// Single-member family: position 1, rate 0
				foreach ( $members as $member_id ) {
					update_post_meta( $member_id, '_family_discount_rate', '0' );
					update_post_meta( $member_id, '_family_discount_position', '1' );
					$processed_ids[] = $member_id;
					++$updated;
				}
				continue;
			}

			// Multi-member family: members are already sorted by fee descending
			foreach ( $members as $index => $member_id ) {
				$position      = $index + 1;
				$discount_rate = $this->settings->get_family_discount_rate( $position, $season );

				update_post_meta( $member_id, '_family_discount_rate', (string) $discount_rate );
				update_post_meta( $member_id, '_family_discount_position', (string) $position );
				$processed_ids[] = $member_id;
				++$updated;
			}
		}

		// Clear meta for all persons NOT in family groups (non-youth, no address, etc.)
		$all_persons = new \WP_Query(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		foreach ( $all_persons->posts as $person_id ) {
			if ( ! in_array( (int) $person_id, $processed_ids, true ) ) {
				update_post_meta( $person_id, '_family_discount_rate', '0' );
				update_post_meta( $person_id, '_family_discount_position', '' );
				++$updated;
			}
		}

		return $updated;
	}

	/**
	 * Recalculate family discount positions for a specific person's family.
	 *
	 * Finds all youth members at the same family_key and recalculates
	 * positions and discount rates for that small group.
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return int Number of persons updated.
	 */
	public function recalculate_family_positions_for_person( int $person_id, ?string $season = null ): int {
		$season     = $season ?: SeasonKey::current();
		$family_key = $this->get_family_key( $person_id );

		// No valid address: clear this person's meta and return
		if ( $family_key === null ) {
			update_post_meta( $person_id, '_family_discount_rate', '0' );
			update_post_meta( $person_id, '_family_discount_position', '' );
			return 1;
		}

		// Find all youth members at the same family key
		$youth_categories = $this->settings->get_youth_category_slugs( $season );
		$all_persons      = new \WP_Query(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		$family_members = [];

		foreach ( $all_persons->posts as $pid ) {
			$pid = (int) $pid;

			// Check if same family key
			$pid_key = $this->get_family_key( $pid );
			if ( $pid_key !== $family_key ) {
				continue;
			}

			// Former members never participate in the current household discount.
			if ( (bool) \Rondo\Fields\Fields::get_for_post( $pid, 'former_member' ) ) {
				update_post_meta( $pid, '_family_discount_rate', '0' );
				update_post_meta( $pid, '_family_discount_position', '' );
				continue;
			}

			// Check if youth category
			$fee_data = $this->calculate_base_fee( $pid, $season );
			if ( $fee_data === null || ! in_array( $fee_data['category'], $youth_categories, true ) ) {
				// Not youth: clear meta
				update_post_meta( $pid, '_family_discount_rate', '0' );
				update_post_meta( $pid, '_family_discount_position', '' );
				continue;
			}

			$family_members[] = [
				'person_id' => $pid,
				'base_fee'  => $fee_data['base_fee'],
			];
		}

		// Sort by base_fee descending, then person_id ascending as tiebreaker
		usort(
			$family_members,
			function ( $a, $b ) {
				$cmp = $b['base_fee'] <=> $a['base_fee'];
				if ( $cmp !== 0 ) {
					return $cmp;
				}
				return $a['person_id'] <=> $b['person_id'];
			}
		);

		$updated = 0;

		if ( count( $family_members ) <= 1 ) {
			// Single or no youth member: position 1 (or empty), rate 0
			foreach ( $family_members as $member ) {
				update_post_meta( $member['person_id'], '_family_discount_rate', '0' );
				update_post_meta( $member['person_id'], '_family_discount_position', '1' );
				++$updated;
			}
			return $updated;
		}

		// Multi-member: assign positions
		foreach ( $family_members as $index => $member ) {
			$position      = $index + 1;
			$discount_rate = $this->settings->get_family_discount_rate( $position, $season );

			update_post_meta( $member['person_id'], '_family_discount_rate', (string) $discount_rate );
			update_post_meta( $member['person_id'], '_family_discount_position', (string) $position );
			++$updated;
		}

		return $updated;
	}

	/**
	 * Clear family discount meta for all persons.
	 *
	 * Removes _family_discount_rate and _family_discount_position from all persons.
	 *
	 * @return int Number of persons cleared.
	 */
	public function clear_all_family_discount_meta(): int {
		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		$cleared = 0;
		foreach ( $query->posts as $person_id ) {
			delete_post_meta( $person_id, '_family_discount_rate' );
			delete_post_meta( $person_id, '_family_discount_position' );
			++$cleared;
		}

		return $cleared;
	}
}
