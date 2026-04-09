<?php
/**
 * Fee Services
 *
 * Static service locator for the fee service graph. Lazily constructs
 * and caches the single shared instance of each fee-related service so
 * external callers don't have to rewire the full DI graph at every call
 * site.
 *
 * Created in Phase 218 of the v33.0 Fee Service Decomposition milestone
 * to replace the `MembershipFees` god class facade. Unlike MembershipFees
 * this class has **zero methods of its own** — it is pure wiring. Each
 * accessor returns a collaborator instance that actually does the work.
 *
 * Usage:
 *
 *     // Read settings:
 *     $categories = FeeServices::settings()->get_categories_for_season( $season );
 *
 *     // Calculate a fee:
 *     $fee = FeeServices::fee_calculator()->calculate_full_fee( $id, $lid_sinds, $season );
 *
 *     // Use the cached fast path:
 *     $fee = FeeServices::fee_cache()->get_fee_for_person_cached( $id, $season );
 *
 *     // Person data:
 *     $is_former = FeeServices::person_context()->is_former_member_in_season( $id, $season );
 *
 * The graph is constructed lazily on first call. Subsequent calls return
 * the cached instances, so within a single request all callers share the
 * same collaborators and benefit from the same process-level option cache.
 *
 * Services can always also be instantiated directly — e.g.
 * `new MembershipFeeSettings()` — this locator is for ergonomics, not a
 * dependency rule.
 *
 * @package Rondo\Fees
 */

namespace Rondo\Fees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static fee-service locator.
 */
class FeeServices {

	/** @var MembershipFeeSettings|null */
	private static ?MembershipFeeSettings $settings = null;

	/** @var PersonFeeContext|null */
	private static ?PersonFeeContext $person_context = null;

	/** @var FeeCategoryResolver|null */
	private static ?FeeCategoryResolver $category_resolver = null;

	/** @var FamilyGroupingService|null */
	private static ?FamilyGroupingService $family_grouping = null;

	/** @var FeeCalculator|null */
	private static ?FeeCalculator $fee_calculator = null;

	/** @var FeeCache|null */
	private static ?FeeCache $fee_cache = null;

	/**
	 * Get the shared MembershipFeeSettings instance.
	 *
	 * @return MembershipFeeSettings
	 */
	public static function settings(): MembershipFeeSettings {
		if ( self::$settings === null ) {
			self::$settings = new MembershipFeeSettings();
		}

		return self::$settings;
	}

	/**
	 * Get the shared PersonFeeContext instance.
	 *
	 * @return PersonFeeContext
	 */
	public static function person_context(): PersonFeeContext {
		if ( self::$person_context === null ) {
			self::$person_context = new PersonFeeContext();
		}

		return self::$person_context;
	}

	/**
	 * Get the shared FeeCategoryResolver instance.
	 *
	 * Wired with a closure provider that reads categories from the
	 * shared MembershipFeeSettings instance.
	 *
	 * @return FeeCategoryResolver
	 */
	public static function category_resolver(): FeeCategoryResolver {
		if ( self::$category_resolver === null ) {
			$settings                = self::settings();
			self::$category_resolver = new FeeCategoryResolver(
				fn( string $season ): array => $settings->get_categories_for_season( $season )
			);
		}

		return self::$category_resolver;
	}

	/**
	 * Get the shared FamilyGroupingService instance.
	 *
	 * Wired with the shared PersonFeeContext, MembershipFeeSettings and
	 * a deferred fee-calculator closure that resolves to
	 * {@see FeeCalculator::calculate_fee()} at invocation time — this
	 * keeps the FeeCalculator ↔ FamilyGroupingService cycle from
	 * triggering at construction time.
	 *
	 * @return FamilyGroupingService
	 */
	public static function family_grouping(): FamilyGroupingService {
		if ( self::$family_grouping === null ) {
			self::$family_grouping = new FamilyGroupingService(
				self::person_context(),
				self::settings(),
				fn( int $person_id, ?string $season ) => self::fee_calculator()->calculate_fee( $person_id, $season )
			);
		}

		return self::$family_grouping;
	}

	/**
	 * Get the shared FeeCalculator instance.
	 *
	 * Wired with FeeCategoryResolver, FamilyGroupingService,
	 * MembershipFeeSettings and PersonFeeContext as explicit typed
	 * collaborators.
	 *
	 * @return FeeCalculator
	 */
	public static function fee_calculator(): FeeCalculator {
		if ( self::$fee_calculator === null ) {
			self::$fee_calculator = new FeeCalculator(
				self::category_resolver(),
				self::family_grouping(),
				self::settings(),
				self::person_context()
			);
		}

		return self::$fee_calculator;
	}

	/**
	 * Get the shared FeeCache instance.
	 *
	 * Wired with a deferred full-fee calculator closure that resolves to
	 * {@see FeeCalculator::calculate_full_fee()} at invocation time.
	 *
	 * @return FeeCache
	 */
	public static function fee_cache(): FeeCache {
		if ( self::$fee_cache === null ) {
			self::$fee_cache = new FeeCache(
				fn( int $person_id, ?string $registration_date, ?string $season )
					=> self::fee_calculator()->calculate_full_fee( $person_id, $registration_date, $season )
			);
		}

		return self::$fee_cache;
	}

	/**
	 * Reset all cached service instances.
	 *
	 * Used by tests and long-running CLI processes that need a fresh
	 * graph after settings changes. Should not be needed in normal
	 * request handling.
	 */
	public static function reset(): void {
		self::$settings          = null;
		self::$person_context    = null;
		self::$category_resolver = null;
		self::$family_grouping   = null;
		self::$fee_calculator    = null;
		self::$fee_cache         = null;
	}
}
