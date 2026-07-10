<?php
/**
 * REST API Endpoints for Membership Fees
 *
 * Handles all fee-related endpoints: settings, fee lists, summaries,
 * per-person fees, recalculation, billing settings, and invoice creation.
 */

namespace Rondo\REST;

use Rondo\Fees\FeeServices;
use Rondo\Fees\SeasonKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fees extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register fee-related REST routes
	 */
	public function register_routes() {
		// Membership fee settings (admin only)
		register_rest_route(
			'rondo/v1',
			'/membership-fees/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_membership_fee_settings' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_membership_fee_settings' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'season'     => [
							'required'          => true,
							'type'              => 'string',
							'validate_callback' => function ( $param, $request, $key ) {
																$valid = [ SeasonKey::current(), SeasonKey::next() ];
								return in_array( $param, $valid, true );
							},
						],
						'categories' => [
							'required' => false,
							'type'     => 'object',
							'default'  => null,
						],
					],
				],
			]
		);

		// Copy season categories (admin only)
		register_rest_route(
			'rondo/v1',
			'/membership-fees/copy-season',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'copy_season_categories' ],
				'permission_callback' => [ $this, 'check_financieel_permission' ],
				'args'                => [
					'from_season' => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
					'to_season'   => [
						'required'          => true,
						'type'              => 'string',
						'validate_callback' => function ( $param ) {
							return preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Get membership fee list
		register_rest_route(
			'rondo/v1',
			'/fees',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fee_list' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'season'   => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
					'forecast' => [
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
						'validate_callback' => 'rest_is_boolean',
						'description'       => 'Calculate forecast for next season with 100% pro-rata',
					],
				],
			]
		);

		// Get fee summary (aggregated by category — lightweight for overview tab)
		register_rest_route(
			'rondo/v1',
			'/fees/summary',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_fee_summary' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'season'   => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
					'forecast' => [
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
						'validate_callback' => 'rest_is_boolean',
					],
				],
			]
		);

		// Get single person fee data
		register_rest_route(
			'rondo/v1',
			'/fees/person/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_person_fee' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'id'     => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					],
					'season' => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Bulk recalculate fees endpoint
		register_rest_route(
			'rondo/v1',
			'/fees/recalculate',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'recalculate_all_fees' ],
				'permission_callback' => [ $this, 'check_financieel_permission' ],
				'args'                => [
					'season' => [
						'default'           => null,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Billing settings (GET/POST) — admin only
		register_rest_route(
			'rondo/v1',
			'/fees/billing-settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_billing_settings' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'season' => [
							'default'           => null,
							'validate_callback' => function ( $param ) {
								return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
							},
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_billing_settings' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'season'                     => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return preg_match( '/^\d{4}-\d{4}$/', $param );
							},
						],
						'billing_method'             => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function ( $param ) {
								return in_array( $param, [ 'nikki', 'rondo' ], true );
							},
						],
						'installment_plan_3_enabled' => [
							'required' => false,
							'type'     => 'boolean',
						],
						'installment_plan_8_enabled' => [
							'required' => false,
							'type'     => 'boolean',
						],
						'installment_admin_fee'      => [
							'required' => false,
							'type'     => 'number',
						],
					],
				],
			]
		);

		// Bulk invoice creation — start job (admin only)
		register_rest_route(
			'rondo/v1',
			'/fees/bulk-create-invoices',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'start_bulk_invoice_job' ],
				'permission_callback' => [ $this, 'check_financieel_permission' ],
				'args'                => [
					'season' => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Bulk invoice job progress (admin only)
		register_rest_route(
			'rondo/v1',
			'/fees/bulk-invoice-job',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_bulk_invoice_job_status' ],
				'permission_callback' => [ $this, 'check_financieel_read_permission' ],
			]
		);

		// Single-member invoice creation (admin only)
		register_rest_route(
			'rondo/v1',
			'/fees/create-membership-invoice',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_single_membership_invoice' ],
				'permission_callback' => [ $this, 'check_financieel_permission' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'type'              => 'integer',
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && $param > 0;
						},
					],
					'season'    => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || preg_match( '/^\d{4}-\d{4}$/', $param );
						},
					],
				],
			]
		);

		// Get current season term
		register_rest_route(
			'rondo/v1',
			'/current-season',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_current_season' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
	}

	/**
	 * Get membership fee settings
	 *
	 * Returns fee categories, family discount, and entry discount config for
	 * both the current and next season.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with membership fee settings for both seasons.
	 */
	public function get_membership_fee_settings( $request ) {
				$current_season = SeasonKey::current();
		$next_season            = SeasonKey::next();

		return rest_ensure_response(
			[
				'current_season' => [
					'key'             => $current_season,
					'categories'      => FeeServices::settings()->get_categories_for_season( $current_season ),
					'family_discount' => FeeServices::settings()->get_family_discount_config( $current_season ),
					'entry_discount'  => FeeServices::settings()->get_entry_discount_config( $current_season ),
				],
				'next_season'    => [
					'key'             => $next_season,
					'categories'      => FeeServices::settings()->get_categories_for_season( $next_season ),
					'family_discount' => FeeServices::settings()->get_family_discount_config( $next_season ),
					'entry_discount'  => FeeServices::settings()->get_entry_discount_config( $next_season ),
				],
			]
		);
	}

	/**
	 * Update membership fee settings
	 *
	 * Updates settings for a specific season (current or next).
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with updated membership fee settings for both seasons.
	 */
	public function update_membership_fee_settings( $request ) {
				$current_season = SeasonKey::current();
		$next_season            = SeasonKey::next();
		$season                 = $request->get_param( 'season' );
		$categories             = $request->get_param( 'categories' );
		$family_discount        = $request->get_param( 'family_discount' );
		$entry_discount         = $request->get_param( 'entry_discount' );

		// Validate category structure (if provided)
		$validation = $categories !== null
			? $this->validate_category_config( $categories )
			: [
				'errors'   => [],
				'warnings' => [],
			];

		// Validate family discount config (if provided)
		$discount_validation = $this->validate_family_discount_config( $family_discount );

		// Validate entry discount config (if provided)
		$entry_validation = $this->validate_entry_discount_config( $entry_discount );

		// Merge all errors and warnings
		$all_errors   = array_merge( $validation['errors'], $discount_validation['errors'], $entry_validation['errors'] );
		$all_warnings = array_merge( $validation['warnings'], $discount_validation['warnings'], $entry_validation['warnings'] );

		if ( ! empty( $all_errors ) ) {
			return new \WP_Error(
				'invalid_settings',
				'Settings validation failed',
				[
					'status'   => 400,
					'errors'   => $all_errors,
					'warnings' => $all_warnings,
				]
			);
		}

		// Save categories for the specified season (if provided)
		if ( $categories !== null ) {
			FeeServices::settings()->save_categories_for_season( $categories, $season );
		}

		// Save family discount config (if provided)
		if ( $family_discount !== null ) {
			FeeServices::settings()->save_family_discount_config(
				[
					'second_child_percent' => (float) ( $family_discount['second_child_percent'] ?? 25 ),
					'third_child_percent'  => (float) ( $family_discount['third_child_percent'] ?? 50 ),
				],
				$season
			);
		}

		// Save entry discount config (if provided)
		if ( $entry_discount !== null ) {
			FeeServices::settings()->save_entry_discount_config( $entry_discount, $season );
		}

		// Return updated settings for both seasons
		$response = [
			'current_season' => [
				'key'             => $current_season,
				'categories'      => FeeServices::settings()->get_categories_for_season( $current_season ),
				'family_discount' => FeeServices::settings()->get_family_discount_config( $current_season ),
				'entry_discount'  => FeeServices::settings()->get_entry_discount_config( $current_season ),
			],
			'next_season'    => [
				'key'             => $next_season,
				'categories'      => FeeServices::settings()->get_categories_for_season( $next_season ),
				'family_discount' => FeeServices::settings()->get_family_discount_config( $next_season ),
				'entry_discount'  => FeeServices::settings()->get_entry_discount_config( $next_season ),
			],
		];

		// Include warnings if any
		if ( ! empty( $all_warnings ) ) {
			$response['warnings'] = $all_warnings;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Copy season categories from one season to another
	 *
	 * Copies both fee categories and family discount configuration from a source
	 * season to a destination season. Validates that destination is empty.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response with updated settings or error.
	 */
	public function copy_season_categories( $request ) {
				$from_season = $request->get_param( 'from_season' );
		$to_season           = $request->get_param( 'to_season' );

		// Validate seasons are different
		if ( $from_season === $to_season ) {
			return new \WP_Error(
				'invalid_copy',
				'Bron- en bestemmingsseizoen moeten verschillend zijn',
				[ 'status' => 400 ]
			);
		}

		// Check if destination season already has categories
		$existing_categories = FeeServices::settings()->get_categories_for_season( $to_season );
		if ( ! empty( $existing_categories ) ) {
			return new \WP_Error(
				'destination_not_empty',
				'Bestemmingsseizoen heeft al categorieën gedefinieerd',
				[ 'status' => 400 ]
			);
		}

		// Get source season data
		$source_categories = FeeServices::settings()->get_categories_for_season( $from_season );
		if ( empty( $source_categories ) ) {
			return new \WP_Error(
				'source_empty',
				'Bronseizoen heeft geen categorieën om te kopiëren',
				[ 'status' => 400 ]
			);
		}

		// Copy categories
		FeeServices::settings()->save_categories_for_season( $source_categories, $to_season );

		// Copy family discount config
		$source_discount = FeeServices::settings()->get_family_discount_config( $from_season );
		FeeServices::settings()->save_family_discount_config( $source_discount, $to_season );

		// Copy entry discount config
		$source_entry_discount = FeeServices::settings()->get_entry_discount_config( $from_season );
		FeeServices::settings()->save_entry_discount_config( $source_entry_discount, $to_season );

		// Return updated settings for both seasons
		$current_season = SeasonKey::current();
		$next_season    = SeasonKey::next();

		return rest_ensure_response(
			[
				'current_season' => [
					'key'             => $current_season,
					'categories'      => FeeServices::settings()->get_categories_for_season( $current_season ),
					'family_discount' => FeeServices::settings()->get_family_discount_config( $current_season ),
					'entry_discount'  => FeeServices::settings()->get_entry_discount_config( $current_season ),
				],
				'next_season'    => [
					'key'             => $next_season,
					'categories'      => FeeServices::settings()->get_categories_for_season( $next_season ),
					'family_discount' => FeeServices::settings()->get_family_discount_config( $next_season ),
					'entry_discount'  => FeeServices::settings()->get_entry_discount_config( $next_season ),
				],
			]
		);
	}

	/**
	 * Get membership fee list
	 *
	 * Returns all members with their calculated fees for a given season.
	 * Supports forecast mode for next season projections.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with fee list.
	 */
	public function get_fee_list( $request ) {
		$forecast = $request->get_param( 'forecast' );

		// Determine season
		if ( $forecast ) {
			$season = SeasonKey::next();
		} else {
			$season = $request->get_param( 'season' );
			if ( $season === null ) {
				$season = SeasonKey::current();
			}
		}

		$fee_cache_key = FeeServices::fee_cache()->get_fee_cache_meta_key( $season );
		$nikki_year    = substr( $season, 0, 4 );

		// Use fields => 'ids' for a lightweight query, then prime the meta cache
		// in a single query so all subsequent get_post_meta() calls are O(1).
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'no_found_rows'  => true,
				'fields'         => 'ids',
			]
		);

		// Prime meta cache for all person IDs in one query.
		// fields => 'ids' skips automatic meta cache priming, so we do it explicitly.
		update_meta_cache( 'post', $query->posts );

		// Season end date for former-member eligibility check
		$season_end_year = (int) substr( $season, 5, 4 );
		$season_end_ts   = strtotime( $season_end_year . '-07-01' );

		$results      = [];
		$uncached_ids = [];

		foreach ( $query->posts as $person_id ) {
			$is_former = ! empty( get_post_meta( $person_id, 'former_member', true ) );

			// Former members: check season eligibility inline
			if ( $is_former ) {
				if ( $forecast ) {
					continue;
				}
				$lid_sinds = get_post_meta( $person_id, 'lid-sinds', true );
				if ( empty( $lid_sinds ) ) {
					continue;
				}
				$lid_sinds_ts = strtotime( $lid_sinds );
				if ( $lid_sinds_ts === false || $lid_sinds_ts >= $season_end_ts ) {
					continue;
				}
			}

			// Read cached fee directly from meta (already in object cache)
			$fee_data = get_post_meta( $person_id, $fee_cache_key, true );

			if ( ! is_array( $fee_data ) || empty( $fee_data['category'] ) ) {
				$uncached_ids[] = $person_id;
				continue;
			}

			$result = [
				'id'                     => $person_id,
				'first_name'             => get_post_meta( $person_id, 'first_name', true ) ?: '',
				'last_name'              => get_post_meta( $person_id, 'last_name', true ) ?: '',
				'category'               => $fee_data['category'],
				'leeftijdsgroep'         => $fee_data['leeftijdsgroep'] ?? null,
				'base_fee'               => $fee_data['base_fee'],
				'family_discount_rate'   => $fee_data['family_discount_rate'] ?? 0.0,
				'family_discount_amount' => $fee_data['family_discount_amount'] ?? 0,
				'fee_after_discount'     => $fee_data['fee_after_discount'] ?? $fee_data['final_fee'],
				'prorata_percentage'     => $fee_data['prorata_percentage'] ?? 1.0,
				'final_fee'              => $fee_data['final_fee'],
				'family_key'             => $fee_data['family_key'] ?? null,
				'family_size'            => $fee_data['family_size'] ?? null,
				'family_position'        => $fee_data['family_position'] ?? null,
				'lid_sinds'              => $fee_data['registration_date'] ?? null,
				'from_cache'             => true,
				'calculated_at'          => $fee_data['calculated_at'] ?? null,
				'is_former_member'       => $is_former,
			];

			if ( ! $forecast ) {
				$nikki_total           = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_total', true );
				$nikki_saldo           = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_saldo', true );
				$result['nikki_total'] = $nikki_total !== '' ? (float) $nikki_total : null;
				$result['nikki_saldo'] = $nikki_saldo !== '' ? (float) $nikki_saldo : null;
			}

			$results[] = $result;
		}

		// Fallback: calculate fees for uncached members (rare after background recalculation)
		foreach ( $uncached_ids as $person_id ) {
			if ( $forecast ) {
				$fee_data = FeeServices::fee_calculator()->calculate_fee_with_family_discount( $person_id, $season );
				if ( $fee_data === null ) {
					continue;
				}
				$fee_data['prorata_percentage'] = 1.0;
				$fee_data['final_fee']          = $fee_data['fee_after_discount'] ?? $fee_data['final_fee'];
				$fee_data['registration_date']  = null;
				$fee_data['from_cache']         = false;
				$fee_data['calculated_at']      = current_time( 'Y-m-d H:i:s' );
			} else {
				$fee_data = FeeServices::fee_cache()->get_fee_for_person_cached( $person_id, $season );
				if ( $fee_data === null ) {
					continue;
				}
			}

			$result = [
				'id'                     => $person_id,
				'first_name'             => get_post_meta( $person_id, 'first_name', true ) ?: '',
				'last_name'              => get_post_meta( $person_id, 'last_name', true ) ?: '',
				'category'               => $fee_data['category'],
				'leeftijdsgroep'         => $fee_data['leeftijdsgroep'] ?? null,
				'base_fee'               => $fee_data['base_fee'],
				'family_discount_rate'   => $fee_data['family_discount_rate'] ?? 0.0,
				'family_discount_amount' => $fee_data['family_discount_amount'] ?? 0,
				'fee_after_discount'     => $fee_data['fee_after_discount'] ?? $fee_data['final_fee'],
				'prorata_percentage'     => $fee_data['prorata_percentage'] ?? 1.0,
				'final_fee'              => $fee_data['final_fee'],
				'family_key'             => $fee_data['family_key'] ?? null,
				'family_size'            => $fee_data['family_size'] ?? null,
				'family_position'        => $fee_data['family_position'] ?? null,
				'lid_sinds'              => $fee_data['registration_date'] ?? null,
				'from_cache'             => $fee_data['from_cache'] ?? false,
				'calculated_at'          => $fee_data['calculated_at'] ?? null,
				'is_former_member'       => false,
			];

			if ( ! $forecast ) {
				$nikki_total           = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_total', true );
				$nikki_saldo           = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_saldo', true );
				$result['nikki_total'] = $nikki_total !== '' ? (float) $nikki_total : null;
				$result['nikki_saldo'] = $nikki_saldo !== '' ? (float) $nikki_saldo : null;
			}

			$results[] = $result;
		}

		// Look up existing membership invoices for this season (skip for forecast).
		if ( ! $forecast ) {
			$invoice_query = new \WP_Query(
				[
					'post_type'      => 'rondo_invoice',
					'posts_per_page' => -1,
					'post_status'    => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ],
					'no_found_rows'  => true,
					'fields'         => 'ids',
					'meta_query'     => [
						'relation' => 'AND',
						[
							'key'   => '_invoice_season',
							'value' => $season,
						],
						[
							'key'   => 'invoice_type',
							'value' => 'membership',
						],
					],
				]
			);

			// Build person_id => { invoice_id, invoice_status } lookup.
			$invoice_map = [];
			if ( ! empty( $invoice_query->posts ) ) {
				update_meta_cache( 'post', $invoice_query->posts );
				foreach ( $invoice_query->posts as $inv_id ) {
					$inv_person = get_post_meta( $inv_id, 'person', true );
					if ( $inv_person ) {
						$invoice_map[ (int) $inv_person ] = [
							'id'     => $inv_id,
							'status' => get_post_meta( $inv_id, 'status', true ) ?: 'draft',
						];
					}
				}
			}

			// Enrich results with invoice data.
			foreach ( $results as &$result ) {
				$inv                      = $invoice_map[ $result['id'] ] ?? null;
				$result['invoice_id']     = $inv ? $inv['id'] : null;
				$result['invoice_status'] = $inv ? $inv['status'] : null;
			}
			unset( $result );
		}

		// Sort by category priority, then name
		$category_order = FeeServices::settings()->get_category_sort_order( $season );
		usort(
			$results,
			function ( $a, $b ) use ( $category_order ) {
				$cat_cmp = ( $category_order[ $a['category'] ] ?? 99 ) <=> ( $category_order[ $b['category'] ] ?? 99 );
				if ( $cat_cmp !== 0 ) {
					return $cat_cmp;
				}
				return strcasecmp( $a['first_name'] . ' ' . $a['last_name'], $b['first_name'] . ' ' . $b['last_name'] );
			}
		);

		// Get category metadata for frontend
		$categories_raw  = FeeServices::settings()->get_categories_for_season( $season );
		$categories_meta = [];
		foreach ( $categories_raw as $slug => $category ) {
			$categories_meta[ $slug ] = [
				'label'      => $category['label'] ?? $slug,
				'sort_order' => $category['sort_order'] ?? 999,
				'is_youth'   => $category['is_youth'] ?? false,
			];
		}

		$billing_method             = FeeServices::settings()->get_billing_method( $season );
		$installment_plan_3_enabled = FeeServices::settings()->get_installment_plan_3_enabled( $season );
		$installment_plan_8_enabled = FeeServices::settings()->get_installment_plan_8_enabled( $season );

		return rest_ensure_response(
			[
				'season'                     => $season,
				'forecast'                   => (bool) $forecast,
				'total'                      => count( $results ),
				'members'                    => $results,
				'categories'                 => $categories_meta,
				'billing_method'             => $billing_method,
				'installment_plan_3_enabled' => $installment_plan_3_enabled,
				'installment_plan_8_enabled' => $installment_plan_8_enabled,
			]
		);
	}

	/**
	 * Get fee summary aggregated by category.
	 *
	 * Lightweight endpoint for the Overzicht tab — reads only the fee cache meta
	 * key from postmeta in a single SQL query, aggregates in PHP. No full post
	 * objects or meta cache priming needed.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_fee_summary( $request ) {
		global $wpdb;

		$forecast = $request->get_param( 'forecast' );

		if ( $forecast ) {
			$season = SeasonKey::next();
		} else {
			$season = $request->get_param( 'season' );
			if ( $season === null ) {
				$season = SeasonKey::current();
			}
		}

		// Single SQL query to read only the fee cache meta values.
		// For forecast, we use the current season's cache but treat fee_after_discount
		// as final_fee (100% pro-rata) and exclude former members.
		$cache_season  = $forecast ? SeasonKey::current() : $season;
		$fee_cache_key = FeeServices::fee_cache()->get_fee_cache_meta_key( $cache_season );

		if ( $forecast ) {
			// Forecast: exclude members leaving before next season starts (lid-tot < July 1).
			$next_season_start = substr( $season, 0, 4 ) . '-07-01';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.meta_value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					LEFT JOIN {$wpdb->postmeta} lt ON lt.post_id = p.ID AND lt.meta_key = 'lid-tot'
					WHERE pm.meta_key = %s
					AND p.post_type = 'person'
					AND p.post_status = 'publish'
					AND (lt.meta_value IS NULL OR lt.meta_value = '' OR lt.meta_value >= %s)",
					$fee_cache_key,
					$next_season_start
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.meta_value
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s
					AND p.post_type = 'person'
					AND p.post_status = 'publish'",
					$fee_cache_key
				)
			);
		}

		// Aggregate in PHP (unserialize each cached fee record)
		$aggregates    = [];
		$total_members = 0;

		// Pre-load youth slugs for forecast reclassification (only youth members age up)
		$current_youth_slugs = $forecast ? FeeServices::settings()->get_youth_category_slugs( SeasonKey::current() ) : [];

		foreach ( $rows as $row ) {
			$fee_data = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $fee_data ) || empty( $fee_data['category'] ) ) {
				continue;
			}

			// Forecast: skip former members (they won't be members next season)
			if ( $forecast && ! empty( $fee_data['is_former_member'] ) ) {
				continue;
			}

			if ( $forecast ) {
				$current_cat = $fee_data['category'];

				// Only reclassify youth category members (non-youth are matched by team/werkfunctie)
				if ( in_array( $current_cat, $current_youth_slugs, true ) ) {
					$leeftijdsgroep = $fee_data['leeftijdsgroep'] ?? '';
					if ( ! empty( $leeftijdsgroep ) ) {
						$category_resolver = FeeServices::category_resolver();
						$next_age_class    = $category_resolver->predict_next_season_age_class( $leeftijdsgroep );
						$next_cat          = $category_resolver->get_category_by_age_class( $next_age_class, $season );
					} else {
						$next_cat = null;
					}
					$cat = $next_cat ?? $current_cat;
				} else {
					$cat = $current_cat;
				}
				$base_fee = FeeServices::settings()->get_fee( $cat, $season );

				// Recalculate family discount with new base fee but same rate
				$discount_rate   = $fee_data['family_discount_rate'] ?? 0;
				$discount_amount = round( $base_fee * $discount_rate, 2 );
				$final_fee       = $base_fee - $discount_amount;

				if ( ! isset( $aggregates[ $cat ] ) ) {
					$aggregates[ $cat ] = [
						'count'              => 0,
						'base_fee'           => 0,
						'family_discount'    => 0,
						'fee_after_discount' => 0,
						'prorata_amount'     => 0,
						'final_fee'          => 0,
					];
				}
				++$aggregates[ $cat ]['count'];
				$aggregates[ $cat ]['base_fee']           += $base_fee;
				$aggregates[ $cat ]['family_discount']    += $discount_amount;
				$aggregates[ $cat ]['fee_after_discount'] += $final_fee; // Forecast assumes full season
				$aggregates[ $cat ]['prorata_amount']     += 0; // No pro-rata in forecast
				$aggregates[ $cat ]['final_fee']          += $final_fee;
			} else {
				$cat = $fee_data['category'];
				if ( ! isset( $aggregates[ $cat ] ) ) {
					$aggregates[ $cat ] = [
						'count'              => 0,
						'base_fee'           => 0,
						'family_discount'    => 0,
						'fee_after_discount' => 0,
						'prorata_amount'     => 0,
						'final_fee'          => 0,
					];
				}
				++$aggregates[ $cat ]['count'];
				$aggregates[ $cat ]['base_fee']        += $fee_data['base_fee'] ?? 0;
				$aggregates[ $cat ]['family_discount'] += $fee_data['family_discount_amount'] ?? 0;

				// fee_after_discount exists in cache since calculate_full_fee (line 1702 in class-membership-fees.php)
				// Fallback calculation for older caches
				$fee_after_discount                        = $fee_data['fee_after_discount'] ?? ( $fee_data['base_fee'] - $fee_data['family_discount_amount'] );
				$aggregates[ $cat ]['fee_after_discount'] += $fee_after_discount;

				// prorata_amount = fee_after_discount - final_fee
				$final_fee                             = $fee_data['final_fee'] ?? 0;
				$prorata_amount                        = $fee_after_discount - $final_fee;
				$aggregates[ $cat ]['prorata_amount'] += $prorata_amount;

				$aggregates[ $cat ]['final_fee'] += $final_fee;
			}
			++$total_members;
		}

		// Round aggregated values to avoid floating point artifacts
		foreach ( $aggregates as &$agg ) {
			$agg['base_fee']           = round( $agg['base_fee'], 2 );
			$agg['family_discount']    = round( $agg['family_discount'], 2 );
			$agg['fee_after_discount'] = round( $agg['fee_after_discount'], 2 );
			$agg['prorata_amount']     = round( $agg['prorata_amount'], 2 );
			$agg['final_fee']          = round( $agg['final_fee'], 2 );
		}
		unset( $agg );

		// Get category metadata for frontend
		$categories_raw  = FeeServices::settings()->get_categories_for_season( $season );
		$categories_meta = [];
		foreach ( $categories_raw as $slug => $category ) {
			$categories_meta[ $slug ] = [
				'label'      => $category['label'] ?? $slug,
				'sort_order' => $category['sort_order'] ?? 999,
				'is_youth'   => $category['is_youth'] ?? false,
			];
		}

		$billing_method             = FeeServices::settings()->get_billing_method( $season );
		$installment_plan_3_enabled = FeeServices::settings()->get_installment_plan_3_enabled( $season );
		$installment_plan_8_enabled = FeeServices::settings()->get_installment_plan_8_enabled( $season );

		return rest_ensure_response(
			[
				'season'                     => $season,
				'forecast'                   => false,
				'total'                      => $total_members,
				'aggregates'                 => $aggregates,
				'categories'                 => $categories_meta,
				'billing_method'             => $billing_method,
				'installment_plan_3_enabled' => $installment_plan_3_enabled,
				'installment_plan_8_enabled' => $installment_plan_8_enabled,
			]
		);
	}

	/**
	 * Get fee data for a single person
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response with fee data or error.
	 */
	public function get_person_fee( $request ) {
		$person_id = (int) $request->get_param( 'id' );
		$season    = $request->get_param( 'season' );

		// Verify person exists
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'not_found', 'Person not found', [ 'status' => 404 ] );
		}

		if ( $season === null ) {
			$season = SeasonKey::current();
		}

		// Check if person is manually excluded from contributie
		if ( get_post_meta( $person_id, '_exclude_from_contributie', true ) ) {
			return rest_ensure_response(
				[
					'person_id'  => $person_id,
					'season'     => $season,
					'calculable' => false,
					'reason'     => 'manually_excluded',
					'message'    => 'Persoon is handmatig uitgesloten van contributie.',
				]
			);
		}

		// Check if person is a former member not in the requested season
		$is_former = ( get_field( 'former_member', $person_id ) === true );
		if ( $is_former && ! FeeServices::person_context()->is_former_member_in_season( $person_id, $season ) ) {
			return rest_ensure_response(
				[
					'person_id'        => $person_id,
					'season'           => $season,
					'calculable'       => false,
					'is_former_member' => true,
					'message'          => 'Oud-lid valt niet binnen dit seizoen.',
				]
			);
		}

		// Get fee data with caching
		$fee_data = FeeServices::fee_cache()->get_fee_for_person_cached( $person_id, $season );

		if ( $fee_data === null ) {
			// Person is not calculable (no valid category)
			return rest_ensure_response(
				[
					'person_id'  => $person_id,
					'season'     => $season,
					'calculable' => false,
					'message'    => 'Geen contributie berekening mogelijk voor deze persoon.',
				]
			);
		}

		// Look up category label from season config
		$season_categories = FeeServices::settings()->get_categories_for_season( $season );
		$category_label    = $season_categories[ $fee_data['category'] ]['label'] ?? $fee_data['category'];

		// Derive family_members and family_size from family_key if not already populated
		$family_members = $fee_data['family_members'] ?? [];
		$family_size    = $fee_data['family_size'];
		$family_key     = $fee_data['family_key'] ?? null;

		if ( $family_key !== null && empty( $family_members ) && ( $fee_data['family_position'] ?? 0 ) > 0 ) {
			// Derive siblings from family_key: find other youth persons at same address
			$groups         = FeeServices::family_grouping()->build_family_groups( $season );
			$group_families = $groups['families'];
			$group_members  = $group_families[ $family_key ] ?? [];

			$family_size = count( $group_members );
			foreach ( $group_members as $member_id ) {
				if ( (int) $member_id !== $person_id ) {
					$first_name = get_field( 'first_name', $member_id ) ?: '';
					$infix      = get_field( 'infix', $member_id ) ?: '';
					$last_name  = get_field( 'last_name', $member_id ) ?: '';
					$name       = implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) );
					if ( empty( $name ) ) {
						$name = get_the_title( $member_id );
					}
					$family_members[] = [
						'id'   => (int) $member_id,
						'name' => $name,
					];
				}
			}
		}

		// Get Nikki data for this year
		$nikki_year  = substr( $season, 0, 4 );
		$nikki_total = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_total', true );
		$nikki_saldo = get_post_meta( $person_id, '_nikki_' . $nikki_year . '_saldo', true );

		// Get financiele-blokkade field
		$financiele_blokkade = get_field( 'financiele-blokkade', $person_id );

		// Get billing method for this season
		$billing_method = FeeServices::settings()->get_billing_method( $season );

		return rest_ensure_response(
			[
				'person_id'              => $person_id,
				'season'                 => $season,
				'calculable'             => true,
				'category'               => $fee_data['category'],
				'category_label'         => $category_label,
				'leeftijdsgroep'         => $fee_data['leeftijdsgroep'],
				'base_fee'               => $fee_data['base_fee'],
				'family_discount_rate'   => $fee_data['family_discount_rate'],
				'family_discount_amount' => $fee_data['family_discount_amount'],
				'fee_after_discount'     => $fee_data['fee_after_discount'],
				'prorata_percentage'     => $fee_data['prorata_percentage'],
				'final_fee'              => $fee_data['final_fee'],
				'family_key'             => $family_key,
				'family_size'            => $family_size,
				'family_position'        => $fee_data['family_position'],
				'family_members'         => $family_members,
				'lid_sinds'              => $fee_data['registration_date'] ?? null,
				'from_cache'             => $fee_data['from_cache'] ?? false,
				'calculated_at'          => $fee_data['calculated_at'] ?? null,
				'nikki_total'            => $nikki_total !== '' ? (float) $nikki_total : null,
				'nikki_saldo'            => $nikki_saldo !== '' ? (float) $nikki_saldo : null,
				'financiele_blokkade'    => (bool) $financiele_blokkade,
				'is_former_member'       => $is_former,
				'billing_method'         => $billing_method,
			]
		);
	}

	/**
	 * Trigger bulk fee recalculation
	 *
	 * Admin-only endpoint to clear all fee caches and run recalculation synchronously.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with recalculation status.
	 */
	public function recalculate_all_fees( $request ) {
				$season = $request->get_param( 'season' );

		if ( $season === null ) {
			$season = SeasonKey::current();
		}

		// Clear all caches and family discount meta
		$cleared = FeeServices::fee_cache()->clear_all_fee_caches( $season );
		FeeServices::family_grouping()->clear_all_family_discount_meta();

		// Run recalculation synchronously
		$invalidator = new \Rondo\Fees\FeeCacheInvalidator();
		$invalidator->recalculate_all_fees_background( $season );

		return rest_ensure_response(
			[
				'success'       => true,
				'season'        => $season,
				'cleared_count' => $cleared,
				'message'       => sprintf(
					'%d contributies herberekend voor seizoen %s.',
					$cleared,
					$season
				),
			]
		);
	}

	/**
	 * Get billing settings for a season.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Billing settings.
	 */
	public function get_billing_settings( $request ) {
				$season = $request->get_param( 'season' );

		if ( $season === null ) {
			$season = SeasonKey::current();
		}

		return rest_ensure_response(
			[
				'season'                     => $season,
				'billing_method'             => FeeServices::settings()->get_billing_method( $season ),
				'installment_plan_3_enabled' => FeeServices::settings()->get_installment_plan_3_enabled( $season ),
				'installment_plan_8_enabled' => FeeServices::settings()->get_installment_plan_8_enabled( $season ),
				'installment_admin_fee'      => FeeServices::settings()->get_installment_admin_fee( $season ),
			]
		);
	}

	/**
	 * Update billing settings for a season.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Updated billing settings.
	 */
	public function update_billing_settings( $request ) {
				$season = $request->get_param( 'season' );

		$billing_method = $request->get_param( 'billing_method' );
		if ( $billing_method !== null ) {
			FeeServices::settings()->set_billing_method( $billing_method, $season );
		}

		$plan_3_enabled = $request->get_param( 'installment_plan_3_enabled' );
		if ( $plan_3_enabled !== null ) {
			FeeServices::settings()->set_installment_plan_3_enabled( (bool) $plan_3_enabled, $season );
		}

		$plan_8_enabled = $request->get_param( 'installment_plan_8_enabled' );
		if ( $plan_8_enabled !== null ) {
			FeeServices::settings()->set_installment_plan_8_enabled( (bool) $plan_8_enabled, $season );
		}

		$installment_admin_fee = $request->get_param( 'installment_admin_fee' );
		if ( $installment_admin_fee !== null ) {
			FeeServices::settings()->set_installment_admin_fee( (float) $installment_admin_fee, $season );
		}

		return rest_ensure_response(
			[
				'season'                     => $season,
				'billing_method'             => FeeServices::settings()->get_billing_method( $season ),
				'installment_plan_3_enabled' => FeeServices::settings()->get_installment_plan_3_enabled( $season ),
				'installment_plan_8_enabled' => FeeServices::settings()->get_installment_plan_8_enabled( $season ),
				'installment_admin_fee'      => FeeServices::settings()->get_installment_admin_fee( $season ),
			]
		);
	}

	/**
	 * Start a bulk invoice creation job.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Job status or error if already running.
	 */
	public function start_bulk_invoice_job( $request ) {
				$season = $request->get_param( 'season' );

		if ( $season === null ) {
			$season = SeasonKey::current();
		}

		$result = \Rondo\Finance\BulkInvoiceCreator::start_job( $season );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get bulk invoice job status.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Job status.
	 */
	public function get_bulk_invoice_job_status( $request ) {
		return rest_ensure_response( \Rondo\Finance\BulkInvoiceCreator::get_job_status() );
	}

	/**
	 * Create a membership invoice for a single person.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Invoice data or error.
	 */
	public function create_single_membership_invoice( $request ) {
				$person_id = (int) $request->get_param( 'person_id' );
		$season            = $request->get_param( 'season' );

		if ( $season === null ) {
			$season = SeasonKey::current();
		}

		// Verify person exists.
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'not_found', 'Person not found', [ 'status' => 404 ] );
		}

		// Check fee first to return appropriate error codes.
		$fee_data = FeeServices::fee_cache()->get_fee_for_person_cached( $person_id, $season );
		if ( $fee_data === null ) {
			return new \WP_Error(
				'no_fee',
				'Geen contributie berekening mogelijk voor deze persoon.',
				[ 'status' => 400 ]
			);
		}

		$creator = new \Rondo\Finance\BulkInvoiceCreator();
		$result  = $creator->create_membership_invoice( $person_id, $season );

		if ( $result === 'error' ) {
			return new \WP_Error(
				'invoice_creation_failed',
				'Factuur aanmaken mislukt.',
				[ 'status' => 500 ]
			);
		}

		if ( $result === 'skipped' ) {
			return new \WP_Error(
				'invoice_already_exists',
				'Er bestaat al een contributie factuur voor dit lid in dit seizoen.',
				[ 'status' => 409 ]
			);
		}

		// Created: find the new invoice.
		$invoices = get_posts(
			[
				'post_type'        => 'rondo_invoice',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'   => 'person',
						'value' => $person_id,
					],
					[
						'key'   => '_invoice_season',
						'value' => $season,
					],
					[
						'key'   => 'invoice_type',
						'value' => 'membership',
					],
				],
			]
		);

		$invoice_id = $invoices[0] ?? null;

		return rest_ensure_response(
			[
				'created'    => true,
				'invoice_id' => $invoice_id,
				'person_id'  => $person_id,
				'season'     => $season,
			]
		);
	}

	/**
	 * Get current season term
	 *
	 * Returns the current season taxonomy term. Made public static so other
	 * code can call it for determining the current season.
	 *
	 * @return \WP_REST_Response Response with current season data or null.
	 */
	public static function get_current_season() {
		$taxonomies     = new \RONDO_Taxonomies();
		$current_season = $taxonomies->get_current_season();

		if ( ! $current_season ) {
			return rest_ensure_response( null );
		}

		return rest_ensure_response(
			[
				'id'   => $current_season->term_id,
				'name' => $current_season->name,
				'slug' => $current_season->slug,
			]
		);
	}

	/**
	 * Validate category configuration structure
	 *
	 * Checks for required fields, duplicate slugs, and duplicate age class assignments.
	 * Returns both errors (block save) and warnings (informational).
	 *
	 * @param mixed $categories The categories data to validate.
	 * @return array Array with 'errors' and 'warnings' keys.
	 */
	private function validate_category_config( $categories ) {
		$errors   = [];
		$warnings = [];

		// Must be an array/object
		if ( ! is_array( $categories ) ) {
			$errors[] = [
				'field'   => 'categories',
				'message' => 'Categories must be an object',
			];
			return [
				'errors'   => $errors,
				'warnings' => $warnings,
			];
		}

		// Empty array is valid (per Phase 156 pattern: silent for missing config)
		if ( empty( $categories ) ) {
			return [
				'errors'   => [],
				'warnings' => [],
			];
		}

		$seen_slugs    = [];
		$age_class_map = [];

		foreach ( $categories as $slug => $category ) {
			// Validate slug is not empty
			if ( empty( $slug ) || ! is_string( $slug ) ) {
				$errors[] = [
					'field'   => 'slug',
					'message' => 'Category slug is required and must be a string',
				];
				continue;
			}

			// Validate slug format (lowercase, no spaces — use sanitize_title for normalization check)
			$normalized_slug = sanitize_title( $slug );
			if ( $normalized_slug !== $slug ) {
				$errors[] = [
					'field'   => "categories.{$slug}",
					'message' => "Invalid slug format. Use lowercase letters, numbers, and hyphens only. Suggested: '{$normalized_slug}'",
				];
			}

			// Check for duplicate slugs (case-insensitive)
			$lower_slug = strtolower( $slug );
			if ( isset( $seen_slugs[ $lower_slug ] ) ) {
				$errors[] = [
					'field'   => "categories.{$slug}",
					'message' => "Duplicate slug '{$slug}'",
				];
			}
			$seen_slugs[ $lower_slug ] = true;

			// Validate required field: label
			if ( ! isset( $category['label'] ) || ! is_string( $category['label'] ) || trim( $category['label'] ) === '' ) {
				$errors[] = [
					'field'   => "categories.{$slug}.label",
					'message' => 'Label is required',
				];
			}

			// Validate required field: amount (must be numeric, non-negative)
			if ( ! isset( $category['amount'] ) || ! is_numeric( $category['amount'] ) || (float) $category['amount'] < 0 ) {
				$errors[] = [
					'field'   => "categories.{$slug}.amount",
					'message' => 'Amount is required and must be a non-negative number',
				];
			}

			// Track age class assignments for overlap detection (warning, not error per API-04)
			if ( isset( $category['age_classes'] ) && is_array( $category['age_classes'] ) ) {
				foreach ( $category['age_classes'] as $age_class ) {
					if ( ! is_string( $age_class ) ) {
						continue;
					}
					$normalized_class = strtolower( trim( $age_class ) );
					if ( isset( $age_class_map[ $normalized_class ] ) ) {
						$warnings[] = [
							'field'      => "categories.{$slug}.age_classes",
							'message'    => "Age class '{$age_class}' is also assigned to category '{$age_class_map[ $normalized_class ]}'",
							'categories' => [ $age_class_map[ $normalized_class ], $slug ],
						];
					} else {
						$age_class_map[ $normalized_class ] = $slug;
					}
				}
			}

			// Validate matching_teams (optional, must be array of integers if present)
			if ( isset( $category['matching_teams'] ) ) {
				if ( ! is_array( $category['matching_teams'] ) ) {
					$errors[] = [
						'field'   => "categories.{$slug}.matching_teams",
						'message' => 'matching_teams must be an array',
					];
				} else {
					foreach ( $category['matching_teams'] as $team_id ) {
						if ( ! is_numeric( $team_id ) || (int) $team_id <= 0 ) {
							$errors[] = [
								'field'   => "categories.{$slug}.matching_teams",
								'message' => 'matching_teams must contain valid team IDs (positive integers)',
							];
							break;
						}
					}
				}
			}

			// Validate matching_werkfuncties (optional, must be array of strings if present)
			if ( isset( $category['matching_werkfuncties'] ) ) {
				if ( ! is_array( $category['matching_werkfuncties'] ) ) {
					$errors[] = [
						'field'   => "categories.{$slug}.matching_werkfuncties",
						'message' => 'matching_werkfuncties must be an array',
					];
				} else {
					foreach ( $category['matching_werkfuncties'] as $wf ) {
						if ( ! is_string( $wf ) || trim( $wf ) === '' ) {
							$errors[] = [
								'field'   => "categories.{$slug}.matching_werkfuncties",
								'message' => 'matching_werkfuncties must contain non-empty strings',
							];
							break;
						}
					}
				}
			}
		}

		return [
			'errors'   => $errors,
			'warnings' => $warnings,
		];
	}

	/**
	 * Validate family discount configuration
	 *
	 * Ensures percentages are valid numbers between 0 and 100.
	 * Null/missing config is valid (defaults will be used).
	 *
	 * @param mixed $config The family_discount config to validate.
	 * @return array Array with 'errors' and 'warnings' keys.
	 */
	private function validate_family_discount_config( $config ) {
		$errors   = [];
		$warnings = [];

		// Null/missing is valid (use defaults)
		if ( $config === null ) {
			return [
				'errors'   => [],
				'warnings' => [],
			];
		}

		// Must be an array
		if ( ! is_array( $config ) ) {
			$errors[] = [
				'field'   => 'family_discount',
				'message' => 'Familiekorting configuratie moet een object zijn',
			];
			return [
				'errors'   => $errors,
				'warnings' => $warnings,
			];
		}

		// Validate second_child_percent
		if ( isset( $config['second_child_percent'] ) ) {
			$value = $config['second_child_percent'];
			if ( ! is_numeric( $value ) || $value < 0 || $value > 100 ) {
				$errors[] = [
					'field'   => 'family_discount.second_child_percent',
					'message' => 'Korting tweede kind moet tussen 0 en 100 procent zijn',
				];
			}
		}

		// Validate third_child_percent
		if ( isset( $config['third_child_percent'] ) ) {
			$value = $config['third_child_percent'];
			if ( ! is_numeric( $value ) || $value < 0 || $value > 100 ) {
				$errors[] = [
					'field'   => 'family_discount.third_child_percent',
					'message' => 'Korting derde kind en verder moet tussen 0 en 100 procent zijn',
				];
			}
		}

		// Warning if second child discount >= third child discount
		$second = is_numeric( $config['second_child_percent'] ?? null ) ? (float) $config['second_child_percent'] : 25;
		$third  = is_numeric( $config['third_child_percent'] ?? null ) ? (float) $config['third_child_percent'] : 50;
		if ( $second > 0 && $third > 0 && $second >= $third ) {
			$warnings[] = [
				'field'   => 'family_discount',
				'message' => 'Korting tweede kind is doorgaans lager dan korting derde kind',
			];
		}

		return [
			'errors'   => $errors,
			'warnings' => $warnings,
		];
	}

	/**
	 * Validate entry discount (instapkorting) configuration structure
	 *
	 * Checks for required fields and valid ranges. Each period must have
	 * start_month (1-12), end_month (1-12), and discount_percent (0-100).
	 * Returns both errors (block save) and warnings (informational).
	 *
	 * @param mixed $config The entry discount config to validate.
	 * @return array Array with 'errors' and 'warnings' keys.
	 */
	private function validate_entry_discount_config( $config ) {
		$errors   = [];
		$warnings = [];

		// Null/missing is valid (use defaults)
		if ( $config === null ) {
			return [
				'errors'   => [],
				'warnings' => [],
			];
		}

		// Must be an array
		if ( ! is_array( $config ) ) {
			$errors[] = [
				'field'   => 'entry_discount',
				'message' => 'Instapkorting configuratie moet een object zijn',
			];
			return [
				'errors'   => $errors,
				'warnings' => $warnings,
			];
		}

		// Must have 'periods' key
		if ( ! isset( $config['periods'] ) || ! is_array( $config['periods'] ) ) {
			$errors[] = [
				'field'   => 'entry_discount.periods',
				'message' => 'Instapkorting configuratie moet een "periods" array bevatten',
			];
			return [
				'errors'   => $errors,
				'warnings' => $warnings,
			];
		}

		$covered_months = [];

		foreach ( $config['periods'] as $index => $period ) {
			$field_prefix = 'entry_discount.periods[' . $index . ']';

			// Validate start_month
			if ( ! isset( $period['start_month'] ) || ! is_numeric( $period['start_month'] ) || $period['start_month'] < 1 || $period['start_month'] > 12 ) {
				$errors[] = [
					'field'   => $field_prefix . '.start_month',
					'message' => 'Startmaand moet een getal zijn tussen 1 en 12',
				];
			}

			// Validate end_month
			if ( ! isset( $period['end_month'] ) || ! is_numeric( $period['end_month'] ) || $period['end_month'] < 1 || $period['end_month'] > 12 ) {
				$errors[] = [
					'field'   => $field_prefix . '.end_month',
					'message' => 'Eindmaand moet een getal zijn tussen 1 en 12',
				];
			}

			// Validate discount_percent
			if ( ! isset( $period['discount_percent'] ) || ! is_numeric( $period['discount_percent'] ) || $period['discount_percent'] < 0 || $period['discount_percent'] > 100 ) {
				$errors[] = [
					'field'   => $field_prefix . '.discount_percent',
					'message' => 'Kortingspercentage moet een getal zijn tussen 0 en 100',
				];
			}

			// Track covered months for overlap/gap detection (only if valid)
			if ( empty( $errors ) && isset( $period['start_month'], $period['end_month'] ) && is_numeric( $period['start_month'] ) && is_numeric( $period['end_month'] ) ) {
				$start = (int) $period['start_month'];
				$end   = (int) $period['end_month'];

				for ( $m = $start; $m <= $end; $m++ ) {
					if ( isset( $covered_months[ $m ] ) ) {
						$errors[] = [
							'field'   => $field_prefix,
							'message' => 'Periodes mogen niet overlappen (maand ' . $m . ' komt meerdere keren voor)',
						];
						break;
					}
					$covered_months[ $m ] = true;
				}
			}
		}

		// Warn if not all 12 months are covered
		if ( empty( $errors ) && count( $covered_months ) < 12 ) {
			$warnings[] = [
				'field'   => 'entry_discount',
				'message' => 'Niet alle maanden zijn gedekt door een instapkorting-periode. Leden die in een niet-gedekte maand instappen betalen het volledige tarief.',
			];
		}

		return [
			'errors'   => $errors,
			'warnings' => $warnings,
		];
	}
}
