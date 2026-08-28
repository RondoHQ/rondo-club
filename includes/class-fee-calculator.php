<?php
/**
 * Fee Calculator
 *
 * Owns the actual fee math for the Rondo Club membership fee system:
 * resolves a person's base fee via category matching, applies family
 * discount, applies pro-rata based on registration date, and returns the
 * final fee.
 *
 * Extracted from {@see MembershipFees} in Phase 216 of the v33.0 Fee
 * Service Decomposition milestone. This is the most sensitive extraction
 * in the milestone — every invoice, forecast, and payment link ultimately
 * routes through here. The fee snapshot tool shipped in Phase 214
 * (bin/fee-snapshot.sh) is the regression net: pre- and post-phase
 * snapshots must diff empty.
 *
 * The calculator takes FeeCategoryResolver, FamilyGroupingService,
 * MembershipFeeSettings and PersonFeeContext as explicit constructor
 * collaborators (per STRU-02). Phase 217 added the MembershipFeeSettings
 * dependency for all fee-settings reads; Phase 218 replaced the last
 * MembershipFees god-class reference with PersonFeeContext for the
 * per-person data helpers, retiring the god class entirely.
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fee calculator service.
 */
class FeeCalculator {

	/**
	 * Category resolver collaborator (Phase 214).
	 *
	 * @var FeeCategoryResolver
	 */
	private FeeCategoryResolver $category_resolver;

	/**
	 * Family grouping collaborator (Phase 215).
	 *
	 * @var FamilyGroupingService
	 */
	private FamilyGroupingService $family_grouping;

	/**
	 * Settings repository collaborator (Phase 217).
	 *
	 * Owns all fee-settings reads: get_youth_category_slugs, get_fee,
	 * get_entry_discount_config, get_family_discount_rate. Before Phase
	 * 217 these were reached via `$this->fees->X()`; now they are on an
	 * explicit typed dependency.
	 *
	 * @var MembershipFeeSettings
	 */
	private MembershipFeeSettings $settings;

	/**
	 * Person fee context collaborator (Phase 218).
	 *
	 * Provides get_current_teams, get_effective_werkfuncties and
	 * normalize_werkfuncties_for_fee_match. Before Phase 218 these were
	 * reached via a MembershipFees god-class reference; now they are on
	 * an explicit typed service with zero dependencies.
	 *
	 * @var PersonFeeContext
	 */
	private PersonFeeContext $person_context;

	/**
	 * Constructor.
	 *
	 * @param FeeCategoryResolver   $category_resolver Category resolver collaborator (Phase 214).
	 * @param FamilyGroupingService $family_grouping   Family grouping collaborator (Phase 215).
	 * @param MembershipFeeSettings $settings          Settings repository (Phase 217).
	 * @param PersonFeeContext      $person_context    Person-data helper (Phase 218).
	 */
	public function __construct(
		FeeCategoryResolver $category_resolver,
		FamilyGroupingService $family_grouping,
		MembershipFeeSettings $settings,
		PersonFeeContext $person_context
	) {
		$this->category_resolver = $category_resolver;
		$this->family_grouping   = $family_grouping;
		$this->settings          = $settings;
		$this->person_context    = $person_context;
	}

	/**
	 * Calculate the base fee for a person.
	 *
	 * Determines the correct fee category and amount based on the person's
	 * age group, team membership, and work functions.
	 *
	 * Priority order: Youth age class > Team matching > Werkfunctie matching > Non-youth age class
	 * - Youth categories: Matched by age class, return immediately (highest priority)
	 * - Team matching: Config-driven matching via matching_teams arrays (player roles only)
	 * - Werkfunctie matching: Config-driven matching via matching_werkfuncties arrays
	 * - Non-youth age class: e.g., senior — fallback when no team/werkfunctie match
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key for fee lookup, defaults to current season.
	 * @return array{category: string, base_fee: int, leeftijdsgroep: string|null, person_id: int}|null
	 *         Fee data array or null if person cannot be calculated.
	 */
	public function calculate_fee( int $person_id, ?string $season = null ): ?array {
		// Skip persons manually excluded from contributie
		if ( get_post_meta( $person_id, '_exclude_from_contributie', true ) ) {
			return null;
		}

		// Age-based fees apply only to players. Sportlink's game activity is the
		// primary signal; a current player role is the fallback for valid cases
		// such as recreational teams whose activity is not populated. Function-
		// based categories (for example Donateur) remain eligible without either.
		$spelactiviteit  = trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'spelactiviteit' ) );
		$teams           = $this->person_context->get_current_teams( $person_id );
		$has_play_signal = ( $spelactiviteit !== '' && $spelactiviteit !== '-' ) || ! empty( $teams );

		// Get leeftijdsgroep from person
		$leeftijdsgroep     = \Rondo\Fields\Fields::get_for_post( $person_id, 'leeftijdsgroep' );
		$age_class_category = null;

		// Parse age group if available
		if ( ! empty( $leeftijdsgroep ) ) {
			$age_class_category = $this->category_resolver->get_category_by_age_class( $leeftijdsgroep, $season );
		}

		// Youth categories: Return immediately (priority over everything)
		$youth_categories = $this->settings->get_youth_category_slugs( $season );
		if ( $has_play_signal && $age_class_category && in_array( $age_class_category, $youth_categories, true ) ) {
			return [
				'category'       => $age_class_category,
				'base_fee'       => $this->settings->get_fee( $age_class_category, $season ),
				'leeftijdsgroep' => $leeftijdsgroep,
				'person_id'      => $person_id,
			];
		}

		// Check team matching (config-driven, player roles only)
		if ( ! empty( $teams ) ) {
			$team_matched_category = $this->category_resolver->get_category_by_team_match( $teams, $season );
			if ( $team_matched_category !== null ) {
				return [
					'category'       => $team_matched_category,
					'base_fee'       => $this->settings->get_fee( $team_matched_category, $season ),
					'leeftijdsgroep' => $leeftijdsgroep,
					'person_id'      => $person_id,
				];
			}
		}

		// Check werkfunctie matching (config-driven)
		$werkfuncties = $this->person_context->normalize_werkfuncties_for_fee_match(
			$this->person_context->get_effective_werkfuncties( $person_id )
		);
		if ( ! empty( $werkfuncties ) ) {
			$werkfunctie_matched_category = $this->category_resolver->get_category_by_werkfunctie_match( $werkfuncties, $season );
			if ( $werkfunctie_matched_category !== null ) {
				return [
					'category'       => $werkfunctie_matched_category,
					'base_fee'       => $this->settings->get_fee( $werkfunctie_matched_category, $season ),
					'leeftijdsgroep' => $leeftijdsgroep,
					'person_id'      => $person_id,
				];
			}
		}

		// Fallback: Non-youth age class match (e.g., senior)
		if ( $has_play_signal && $age_class_category !== null ) {
			return [
				'category'       => $age_class_category,
				'base_fee'       => $this->settings->get_fee( $age_class_category, $season ),
				'leeftijdsgroep' => $leeftijdsgroep,
				'person_id'      => $person_id,
			];
		}

		// No valid category found - exclude
		return null;
	}

	/**
	 * Calculate fee with family discount for a person.
	 *
	 * Calculates the base fee and applies family discount based on position
	 * within the family group. Most expensive youth member pays full fee,
	 * second gets 25% off, third+ get 50% off.
	 *
	 * Non-youth members (senior, recreant, donateur) are not eligible for
	 * family discount per FAM-05 requirement.
	 *
	 * @param int         $person_id The person post ID.
	 * @param string|null $season    Optional season key, defaults to current season.
	 * @return array|null Fee data with family discount info, or null if not calculable.
	 */
	public function calculate_fee_with_family_discount( int $person_id, ?string $season = null ): ?array {
		// Resolve season for consistent usage
		$season = $season ?: SeasonKey::current();

		// Get base fee using calculate_fee with season
		$fee_data = $this->calculate_fee( $person_id, $season );

		if ( $fee_data === null ) {
			return null;
		}

		// A former member may still owe a fee for the season, but never receives
		// a discount based on the club's current household composition.
		if ( (bool) \Rondo\Fields\Fields::get_for_post( $person_id, 'former_member' ) ) {
			return array_merge(
				$fee_data,
				[
					'family_discount_rate'   => 0.0,
					'family_discount_amount' => 0,
					'final_fee'              => $fee_data['base_fee'],
					'family_position'        => null,
					'family_key'             => null,
					'family_size'            => null,
				]
			);
		}

		// Youth categories eligible for family discount
		$youth_categories = $this->settings->get_youth_category_slugs( $season );

		// Non-youth: no family discount eligible
		if ( ! in_array( $fee_data['category'], $youth_categories, true ) ) {
			return array_merge(
				$fee_data,
				[
					'family_discount_rate'   => 0.0,
					'family_discount_amount' => 0,
					'final_fee'              => $fee_data['base_fee'],
					'family_position'        => null,
					'family_key'             => null,
					'family_size'            => null,
				]
			);
		}

		// Get family key for this person
		$family_key = $this->family_grouping->get_family_key( $person_id );

		// No valid address: no discount possible
		if ( $family_key === null ) {
			return array_merge(
				$fee_data,
				[
					'family_discount_rate'   => 0.0,
					'family_discount_amount' => 0,
					'final_fee'              => $fee_data['base_fee'],
					'family_position'        => null,
					'family_key'             => null,
					'family_size'            => 1,
				]
			);
		}

		// Try stored meta first (fast path)
		$stored_rate     = get_post_meta( $person_id, '_family_discount_rate', true );
		$stored_position = get_post_meta( $person_id, '_family_discount_position', true );

		if ( $stored_rate !== '' && $stored_position !== '' ) {
			$discount_rate   = (float) $stored_rate;
			$position        = (int) $stored_position;
			$discount_amount = round( $fee_data['base_fee'] * $discount_rate, 2 );
			$final_fee       = $fee_data['base_fee'] - $discount_amount;

			return array_merge(
				$fee_data,
				[
					'family_discount_rate'   => $discount_rate,
					'family_discount_amount' => $discount_amount,
					'final_fee'              => $final_fee,
					'family_position'        => $position,
					'family_key'             => $family_key,
					'family_size'            => null, // Derived on demand in REST endpoint
					'family_members'         => [],   // Derived on demand in REST endpoint
				]
			);
		}

		// Fallback: build family groups (backward-compatible for uncached state)
		$groups         = $this->family_grouping->build_family_groups( $season );
		$families       = $groups['families'];
		$person_data    = $groups['person_data'];
		$family_members = $families[ $family_key ] ?? [];

		// Family has only 1 member: no discount
		if ( count( $family_members ) <= 1 ) {
			return array_merge(
				$fee_data,
				[
					'family_discount_rate'   => 0.0,
					'family_discount_amount' => 0,
					'final_fee'              => $fee_data['base_fee'],
					'family_position'        => 1,
					'family_key'             => $family_key,
					'family_size'            => count( $family_members ),
					'family_members'         => [],
				]
			);
		}

		// Build sorted list by base_fee descending (most expensive first)
		$sorted = [];
		foreach ( $family_members as $member_id ) {
			$sorted[] = [
				'person_id' => $member_id,
				'base_fee'  => $person_data[ $member_id ]['base_fee'],
			];
		}

		usort(
			$sorted,
			function ( $a, $b ) {
				// Sort descending by base_fee
				$cmp = $b['base_fee'] <=> $a['base_fee'];
				if ( $cmp !== 0 ) {
					return $cmp;
				}
				// Tie-breaker: lower person_id first (older record = full fee)
				return $a['person_id'] <=> $b['person_id'];
			}
		);

		// Find position of current person (1-indexed)
		$position = 1;
		foreach ( $sorted as $index => $member ) {
			if ( $member['person_id'] === $person_id ) {
				$position = $index + 1;
				break;
			}
		}

		// Calculate discount
		$discount_rate   = $this->settings->get_family_discount_rate( $position, $season );
		$discount_amount = round( $fee_data['base_fee'] * $discount_rate, 2 );
		$final_fee       = $fee_data['base_fee'] - $discount_amount;

		// Build family members array with names (excluding current person)
		$siblings = [];
		foreach ( $sorted as $member ) {
			if ( $member['person_id'] !== $person_id ) {
				$first_name = \Rondo\Fields\Fields::get_for_post( $member['person_id'], 'first_name' ) ?: '';
				$last_name  = \Rondo\Fields\Fields::get_for_post( $member['person_id'], 'last_name' ) ?: '';
				$name       = trim( $first_name . ' ' . $last_name );
				if ( empty( $name ) ) {
					$name = get_the_title( $member['person_id'] );
				}
				$siblings[] = [
					'id'   => $member['person_id'],
					'name' => $name,
				];
			}
		}

		return array_merge(
			$fee_data,
			[
				'family_discount_rate'   => $discount_rate,
				'family_discount_amount' => $discount_amount,
				'final_fee'              => $final_fee,
				'family_position'        => $position,
				'family_key'             => $family_key,
				'family_size'            => count( $family_members ),
				'family_members'         => $siblings,
			]
		);
	}

	/**
	 * Calculate complete fee with family discount and pro-rata.
	 *
	 * Calculates: base_fee -> apply family discount -> apply pro-rata -> final_fee
	 *
	 * The registration_date should come from canonical field 'lid-sinds' (membership join date).
	 *
	 * @param int         $person_id         The person post ID.
	 * @param string|null $registration_date Sportlink registration date (Y-m-d format).
	 * @param string|null $season            Optional season key, defaults to current season.
	 * @return array|null Complete fee data or null if not calculable.
	 */
	public function calculate_full_fee( int $person_id, ?string $registration_date = null, ?string $season = null ): ?array {
		// Get fee with family discount
		$fee_data = $this->calculate_fee_with_family_discount( $person_id, $season );

		if ( $fee_data === null ) {
			return null;
		}

		// Get pro-rata percentage (pass season to compare against season start date)
		$season             = $season ?: SeasonKey::current();
		$prorata_percentage = $this->get_prorata_percentage( $registration_date, $season );

		// Calculate pro-rata amount (applied to fee after family discount)
		$fee_after_discount = $fee_data['final_fee'];
		$prorata_amount     = round( $fee_after_discount * $prorata_percentage, 2 );

		// Add former member flag for diagnostics
		$is_former = (bool) \Rondo\Fields\Fields::get_for_post( $person_id, 'former_member' );

		// Add pro-rata fields to result
		return array_merge(
			$fee_data,
			[
				'registration_date'  => $registration_date,
				'prorata_percentage' => $prorata_percentage,
				'fee_after_discount' => $fee_after_discount,
				'final_fee'          => $prorata_amount,  // Override final_fee with pro-rata amount
				'is_former_member'   => $is_former,
			]
		);
	}

	/**
	 * Get pro-rata percentage based on registration date relative to season.
	 *
	 * Members who joined BEFORE the current season starts (before July 1 of season start year)
	 * pay 100% - they were already members when the season began.
	 *
	 * Members who join DURING the current season get pro-rata based on configurable periods
	 * (stored via get_entry_discount_config). Each period defines a start_month, end_month,
	 * and discount_percent (how much discount they GET, so 75% discount = 0.25 prorata).
	 * Default periods match the previous hardcoded quarterly structure.
	 *
	 * @param string|null $registration_date Date in Y-m-d format (lid-sinds field), or null for 100%.
	 * @param string|null $season            Optional season key (e.g., "2025-2026"), defaults to current season.
	 * @return float Pro-rata percentage (0.0 to 1.0).
	 */
	public function get_prorata_percentage( ?string $registration_date, ?string $season = null ): float {
		// Null date = full fee (100%)
		if ( $registration_date === null || trim( $registration_date ) === '' ) {
			return 1.0;
		}

		$timestamp = strtotime( $registration_date );
		if ( $timestamp === false ) {
			return 1.0; // Invalid date = full fee
		}

		// Determine the season start date
		$season            = $season ?: SeasonKey::current();
		$season_start_year = (int) substr( $season, 0, 4 );
		$season_start_date = strtotime( $season_start_year . '-07-01' );

		// If member joined BEFORE the season started, they pay 100%
		if ( $timestamp < $season_start_date ) {
			return 1.0;
		}

		// Member joined during the current season - find matching configured period
		$month  = (int) date( 'n', $timestamp );
		$config = $this->settings->get_entry_discount_config( $season );

		foreach ( $config['periods'] as $period ) {
			$start = (int) ( $period['start_month'] ?? 0 );
			$end   = (int) ( $period['end_month'] ?? 0 );

			if ( $month >= $start && $month <= $end ) {
				$discount_percent = (float) ( $period['discount_percent'] ?? 0 );
				return ( 100.0 - $discount_percent ) / 100.0;
			}
		}

		// No period matched - safe fallback: full fee
		return 1.0;
	}
}
