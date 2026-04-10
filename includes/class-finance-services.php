<?php
/**
 * Finance Services
 *
 * Static service locator for the finance service graph. Mirrors FeeServices
 * from v33.0 Phase 218: lazy per-request caching, zero business logic.
 *
 * Created in Phase 220 of v34.0 Finance Service Decomposition as the
 * ergonomic entry point to the services extracted from FinanceConfig.
 * Initially only exposes mollie(); phases 221-223 will add email_templates(),
 * membership_pass(), org_info(), payment_terms(), rabobank().
 *
 * Usage:
 *
 *     // Get Mollie accounts:
 *     $accounts = FinanceServices::mollie()->get_mollie_accounts();
 *
 *     // Check active payment provider:
 *     $provider = FinanceServices::mollie()->get_active_payment_provider();
 *
 *     // Get API key for a specific account:
 *     $key = FinanceServices::mollie()->get_mollie_api_key_for_account( $account_id );
 *
 * The graph is constructed lazily on first call. Subsequent calls return the
 * cached instance, so within a single request all callers share the same
 * collaborator and benefit from the same process-level option cache.
 *
 * Services can always also be instantiated directly — e.g.
 * `new MollieConfig( new FinanceConfig() )` — this locator is for ergonomics,
 * not a dependency rule.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static finance-service locator.
 */
class FinanceServices {

	/** @var MollieConfig|null */
	private static ?MollieConfig $mollie = null;

	/**
	 * Get the shared MollieConfig instance.
	 *
	 * Lazily constructs a MollieConfig with a fresh FinanceConfig on first
	 * call; subsequent calls within the same request return the cached instance.
	 *
	 * @return MollieConfig
	 */
	public static function mollie(): MollieConfig {
		if ( self::$mollie === null ) {
			self::$mollie = new MollieConfig( new FinanceConfig() );
		}

		return self::$mollie;
	}

	/**
	 * Reset all cached service instances.
	 *
	 * Used by tests and long-running CLI processes that need a fresh graph
	 * after settings changes. Should not be needed in normal request handling.
	 */
	public static function reset(): void {
		self::$mollie = null;
	}
}
