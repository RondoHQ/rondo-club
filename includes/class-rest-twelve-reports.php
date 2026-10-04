<?php
/**
 * REST API endpoints for Twelve daily revenue reports.
 *
 * Read-only overviews of the imported kassa rapportages at rondo/v1/twelve.
 * Reads require kassaomzet; billing writes additionally require financieel.
 */

namespace Rondo\REST;

use Rondo\Twelve\ReportAggregator;
use Rondo\Twelve\ReportRepository;
use Rondo\Twelve\ProductClassification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TwelveReports extends Base {

	private ReportRepository $repository;

	public function __construct() {
		parent::__construct();
		$this->repository = new ReportRepository();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/twelve/billing',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_billing' ],
					'permission_callback' => [ $this, 'check_kassa_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'create_billing' ],
					'permission_callback' => [ $this, 'check_billing_permission' ],
				],
			]
			);

		$range_args = [
			'from' => [
				'required'          => false,
				'sanitize_callback' => [ $this, 'sanitize_date_param' ],
				'validate_callback' => [ $this, 'validate_date_param' ],
			],
			'to'   => [
				'required'          => false,
				'sanitize_callback' => [ $this, 'sanitize_date_param' ],
				'validate_callback' => [ $this, 'validate_date_param' ],
			],
		];

		// Most recent imported reports.
		register_rest_route(
			'rondo/v1',
			'/twelve/reports',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_reports' ],
					'permission_callback' => [ $this, 'check_kassa_permission' ],
					'args'                => [
						'limit' => [
							'required'          => false,
							'default'           => 30,
							'sanitize_callback' => 'absint',
							'validate_callback' => static fn( $value ): bool => $value >= 1 && $value <= 365,
						],
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/twelve/product-groups',
			[
				[
					'methods'             => 'GET',
					'callback'            => [ $this, 'get_product_groups' ],
					'permission_callback' => [ $this, 'check_kassa_permission' ],
				],
				[
					'methods'             => 'POST',
					'callback'            => [ $this, 'set_product_group' ],
					'permission_callback' => fn() => $this->check_kassa_permission() && current_user_can( 'manage_options' ),
					'args'                => [
						'id'    => [
							'required' => true,
							'type'     => 'string',
							'pattern'  => '^[a-f0-9]{64}$',
						],
						'group' => [
							'required' => true,
							'type'     => 'string',
							'enum'     => array_keys( ProductClassification::GROUPS ),
						],
					],
				],
			]
		);

		// Omzet + transacties per dag of maand.
		register_rest_route(
			'rondo/v1',
			'/twelve/summary',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_summary' ],
					'permission_callback' => [ $this, 'check_kassa_permission' ],
					'args'                => array_merge(
						$range_args,
						[
							'group' => [
								'required'          => false,
								'default'           => 'day',
								'sanitize_callback' => 'sanitize_key',
								'validate_callback' => static fn( $value ): bool => in_array( $value, [ 'day', 'month' ], true ),
							],
						]
					),
				],
			]
		);

		// Omzet per categorie (Bestuur, Breuk en bederf, Businessclub).
		register_rest_route(
			'rondo/v1',
			'/twelve/categories',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_categories' ],
					'permission_callback' => [ $this, 'check_kassa_permission' ],
					'args'                => $range_args,
				],
			]
		);

		// Verkoop per product.
		register_rest_route(
			'rondo/v1',
			'/twelve/products',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_products' ],
					'permission_callback' => [ $this, 'check_kassa_permission' ],
					'args'                => $range_args,
				],
			]
		);

		// BTW-overzicht per tariefgroep.
		register_rest_route(
			'rondo/v1',
			'/twelve/vat',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_vat' ],
					'permission_callback' => [ $this, 'check_kassa_permission' ],
					'args'                => $range_args,
				],
			]
		);

		// Businessclub-omzet per dag voor een maand (basis voor facturatie).
		register_rest_route(
			'rondo/v1',
			'/twelve/businessclub',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_businessclub' ],
					'permission_callback' => [ $this, 'check_kassa_permission' ],
					'args'                => [
						'month' => [
							'required'          => true,
							'sanitize_callback' => [ $this, 'sanitize_month_param' ],
							'validate_callback' => [ $this, 'validate_month_param' ],
						],
					],
				],
			]
		);
	}

	public function get_product_groups() {
		$products = ReportAggregator::by_product( $this->repository->query( '1970-01-01', '9999-12-31' ) );
		return rest_ensure_response( [ 'products' => ProductClassification::catalog( $products ) ] );
	}

	public function set_product_group( $request ) {
		$id      = $request->get_param( 'id' );
		$catalog = $this->get_product_groups()->get_data()['products'];
		if ( ! in_array( $id, array_column( $catalog, 'id' ), true ) ) {
			return new \WP_Error( 'twelve_unknown_product', 'Dit product is niet gevonden.', [ 'status' => 404 ] );
		}
		$lock = ProductClassification::OPTION . '_lock';
		if ( ! add_option( $lock, time(), '', false ) ) {
			return new \WP_Error( 'twelve_groups_busy', 'Er wordt al een indeling opgeslagen. Probeer opnieuw.', [ 'status' => 409 ] );
		}
		try {
			$groups = get_option( ProductClassification::OPTION, [] );
			$group  = $request->get_param( 'group' );
			if ( $group === 'unassigned' ) {
				unset( $groups[ $id ] );
			} else {
				$groups[ $id ] = $group;
			}
			update_option( ProductClassification::OPTION, $groups, false );
			if ( get_option( ProductClassification::OPTION, [] ) !== $groups ) {
				return new \WP_Error( 'twelve_groups_save_failed', 'Opslaan mislukt. Probeer opnieuw.', [ 'status' => 500 ] );
			}
			return rest_ensure_response(
				[
					'id'    => $id,
					'group' => $group,
				]
				);
		} finally {
			delete_option( $lock );
		}
	}

	public function check_kassa_permission(): bool {
		return $this->check_user_approved() && \Rondo\Core\UserRoles::can_access_section( 'kassaomzet' );
	}

	public function check_billing_permission(): bool {
		return $this->check_kassa_permission() && \Rondo\Core\UserRoles::can_manage_finances();
	}

	public function get_billing() {
		return rest_ensure_response( ( new \Rondo\Twelve\BusinessclubInvoicing() )->overview() );
	}

	public function create_billing( $request ) {
		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$address = sanitize_textarea_field( (string) $request->get_param( 'address' ) );
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );
		if ( $name === '' || $address === '' || ! is_email( $email ) ) {
			return new \WP_Error( 'twelve_bad_recipient', 'Vul naam, factuuradres en geldig e-mailadres in.', [ 'status' => 400 ] );
		}
		$result = ( new \Rondo\Twelve\BusinessclubInvoicing() )->create_draft_invoice( '', compact( 'name', 'address', 'email' ) );
		return is_wp_error( $result ) ? $result : rest_ensure_response( [ 'id' => $result ] );
	}

	/**
	 * GET /twelve/reports
	 */
	public function get_reports( $request ) {
		$limit   = (int) $request->get_param( 'limit' );
		$reports = $this->repository->latest( $limit );

		return rest_ensure_response(
			[
				'reports' => array_map( [ $this, 'format_report' ], $reports ),
			]
		);
	}

	/**
	 * GET /twelve/summary
	 */
	public function get_summary( $request ) {
		[ $from, $to ] = $this->range( $request );
		$reports       = $this->repository->query( $from, $to );

		return rest_ensure_response(
			[
				'from'        => $from,
				'to'          => $to,
				'group'       => $request->get_param( 'group' ),
				'buckets'     => ReportAggregator::summarize( $reports, $request->get_param( 'group' ) ),
				'product_mix' => ProductClassification::summary( ReportAggregator::by_product( $reports ) ),
			]
		);
	}

	/**
	 * GET /twelve/categories
	 */
	public function get_categories( $request ) {
		[ $from, $to ] = $this->range( $request );
		$reports       = $this->repository->query( $from, $to );

		return rest_ensure_response(
			[
				'from'       => $from,
				'to'         => $to,
				'categories' => ReportAggregator::by_category( $reports ),
			]
		);
	}

	/**
	 * GET /twelve/products
	 */
	public function get_products( $request ) {
		[ $from, $to ] = $this->range( $request );
		$reports       = $this->repository->query( $from, $to );

		return rest_ensure_response(
			[
				'from'     => $from,
				'to'       => $to,
				'products' => ReportAggregator::by_product( $reports ),
			]
		);
	}

	/**
	 * GET /twelve/vat
	 */
	public function get_vat( $request ) {
		[ $from, $to ] = $this->range( $request );
		$reports       = $this->repository->query( $from, $to );

		return rest_ensure_response(
			[
				'from' => $from,
				'to'   => $to,
				'vat'  => ReportAggregator::vat_overview( $reports ),
			]
		);
	}

	/**
	 * GET /twelve/businessclub
	 */
	public function get_businessclub( $request ) {
		$month   = $request->get_param( 'month' );
		$reports = $this->repository->query( $month . '-01', $this->last_day( $month ) );
		$days    = ReportAggregator::businessclub_days( $reports );

		$total = 0.0;
		foreach ( $days as $day ) {
			$total = round( $total + $day['bedrag'], 2 );
		}

		return rest_ensure_response(
			[
				'month' => $month,
				'days'  => $days,
				'total' => $total,
			]
		);
	}

	/**
	 * Format one report for the list endpoint.
	 */
	private function format_report( array $report ): array {
		$data = $report['data'];
		return [
			...ReportAggregator::revenue_breakdown( $data ),
			'id'                => $report['id'],
			'period_start'      => $report['period_start'],
			'period_end'        => $report['period_end'],
			'club'              => $data['club'] ?? '',
			'omzet_excl_nosale' => ReportAggregator::omzet_excl_nosale( $data ),
			'omzet_incl_nosale' => ReportAggregator::omzet_incl_nosale( $data ),
			'producten'         => ReportAggregator::aantal_producten( $data ),
		];
	}

	/**
	 * Resolve the [from, to] date range, defaulting to the last 30 days.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function range( $request ): array {
		$to   = $request->get_param( 'to' ) ?: gmdate( 'Y-m-d' );
		$from = $request->get_param( 'from' ) ?: gmdate( 'Y-m-d', strtotime( '-29 days' ) );
		if ( $from > $to ) {
			[ $from, $to ] = [ $to, $from ];
		}
		return [ $from, $to ];
	}

	/**
	 * Last calendar day of a YYYY-MM month.
	 */
	private function last_day( string $month ): string {
		return (string) ( new \DateTime( $month . '-01' ) )->format( 'Y-m-t' );
	}

	public function sanitize_date_param( $value ): string {
		return (string) preg_replace( '/[^0-9-]/', '', (string) $value );
	}

	public function validate_date_param( $value ): bool {
		$value = (string) $value;
		return $value === '' || (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
	}

	public function sanitize_month_param( $value ): string {
		return (string) preg_replace( '/[^0-9-]/', '', (string) $value );
	}

	public function validate_month_param( $value ): bool {
		return (bool) preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $value );
	}
}
