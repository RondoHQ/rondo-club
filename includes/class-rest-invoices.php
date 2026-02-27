<?php
/**
 * REST API Endpoints for Invoice Custom Post Type
 *
 * Provides CRUD operations for invoices (facturen) via the REST API at rondo/v1/invoices.
 * All endpoints require the 'financieel' capability.
 */

namespace Rondo\REST;

use Rondo\Finance\InvoiceNumbering;
use Rondo\Finance\InvoicePdfGenerator;
use Rondo\Finance\InvoiceEmailSender;
use Rondo\Finance\PublicPaymentPage;
use Rondo\Finance\RabobankOAuth;
use Rondo\Finance\RabobankPayment;
use Rondo\Finance\MolliePayment;
use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Invoices extends Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Get invoiced discipline case IDs for a person
		register_rest_route(
			'rondo/v1',
			'/invoices/invoiced-cases',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_invoiced_case_ids' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'person_id' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
						],
					],
				],
			]
		);

		// Get discipline case IDs that already have invoices (all persons)
		register_rest_route(
			'rondo/v1',
			'/invoices/all-invoiced-cases',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_all_invoiced_case_ids' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
				],
			]
		);

		// Bulk create invoices from discipline case IDs
		register_rest_route(
			'rondo/v1',
			'/invoices/bulk',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'bulk_create_invoices' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
				],
			]
		);

		// Preview next invoice number
		register_rest_route(
			'rondo/v1',
			'/invoices/next-number',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_next_invoice_number' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'invoice_type' => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return empty( $param ) || in_array( $param, [ 'manual', 'discipline', 'membership' ], true );
							},
						],
					],
				],
			]
		);

		// List invoices
		register_rest_route(
			'rondo/v1',
			'/invoices',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_invoice_list' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'status'    => [
							'default'           => '',
							'validate_callback' => function ( $param ) {
								return empty( $param ) || in_array( $param, [ 'draft', 'sent', 'paid', 'overdue' ], true );
							},
						],
						'person_id' => [
							'default'           => 0,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
							'sanitize_callback' => 'absint',
						],
						'type'      => [
							'default'           => '',
							'validate_callback' => function ( $param ) {
								return empty( $param ) || in_array( $param, [ 'membership', 'discipline', 'manual' ], true );
							},
						],
						'payment_plan' => [
							'default'           => '',
							'validate_callback' => function ( $param ) {
								return empty( $param ) || in_array( $param, [ 'full', 'quarterly_3', 'monthly_8' ], true );
							},
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_invoice' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
				],
			]
		);

		// Single invoice operations
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_invoice' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_invoice' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Update invoice status
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/status',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_invoice_status' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Add line item to a draft invoice (manual correction/discount)
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/draft-line-items',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_draft_line_item' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
						'description' => [
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						],
						'amount' => [
							'required'          => true,
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Generate PDF for invoice
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/generate-pdf',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'generate_pdf' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Download invoice PDF
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/pdf',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'download_pdf' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Send invoice via email
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/send',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'send_invoice' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Resend invoice via email
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/resend',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'resend_invoice' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Regenerate payment link for invoice
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/regenerate-payment-link',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'regenerate_payment_link' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Download invoice QR code
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/qr',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'download_qr' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Reset payment state (test mode only)
		register_rest_route(
			'rondo/v1',
			'/invoices/(?P<id>\d+)/reset-payment-state',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'reset_payment_state' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => fn( $p ) => is_numeric( $p ),
						],
					],
				],
			]
		);

			// Toggle installments disabled flag
			register_rest_route(
				'rondo/v1',
				'/invoices/(?P<id>\d+)/toggle-installments',
			[
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'toggle_installments' ],
					'permission_callback' => [ $this, 'check_financieel_permission' ],
					'args'                => [
						'id' => [
							'validate_callback' => fn( $p ) => is_numeric( $p ),
						],
						'disabled' => [
							'required'          => true,
							'type'              => 'boolean',
							'sanitize_callback' => 'rest_sanitize_boolean',
						],
					],
					],
				]
			);

			// Update membership discounts on sent/unpaid invoices
			register_rest_route(
				'rondo/v1',
				'/invoices/(?P<id>\d+)/membership-discount',
				[
					[
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => [ $this, 'update_membership_discount' ],
						'permission_callback' => [ $this, 'check_financieel_permission' ],
						'args'                => [
							'id' => [
								'validate_callback' => fn( $p ) => is_numeric( $p ),
							],
							'family_discount_percent' => [
								'required'          => false,
								'validate_callback' => fn( $p ) => is_numeric( $p ),
							],
							'entry_discount_percent' => [
								'required'          => false,
								'validate_callback' => fn( $p ) => is_numeric( $p ),
							],
						],
					],
				]
			);
		}

	/**
	 * Check if user has financieel capability
	 *
	 * Permission callback for invoice endpoints.
	 *
	 * @return bool True if user has financieel capability, false otherwise.
	 */
	public function check_financieel_permission() {
		return current_user_can( 'financieel' );
	}

	/**
	 * Get discipline case IDs that already have invoices for a person
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response containing array of discipline case IDs.
	 */
	public function get_invoiced_case_ids( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );

		// Query all invoices for this person (all non-trash statuses)
		$args = [
			'post_type'      => 'rondo_invoice',
			'post_status'    => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ],
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => 'person',
					'value'   => $person_id,
					'compare' => '=',
				],
			],
		];

		$query    = new \WP_Query( $args );
		$case_ids = [];

		// Extract discipline case IDs from line items
		foreach ( $query->posts as $invoice ) {
			$line_items = get_field( 'line_items', $invoice->ID );

			if ( $line_items && is_array( $line_items ) ) {
				foreach ( $line_items as $item ) {
					if ( ! empty( $item['discipline_case'] ) ) {
						$case_ids[] = (int) $item['discipline_case'];
					}
				}
			}
		}

		// Return unique case IDs
		$case_ids = array_values( array_unique( $case_ids ) );

		return rest_ensure_response( [ 'case_ids' => $case_ids ] );
	}

	/**
	 * Get all discipline case IDs that already have invoices (across all persons)
	 *
	 * @return \WP_REST_Response Response containing array of discipline case IDs.
	 */
	public function get_all_invoiced_case_ids() {
		// Query all invoices (all non-trash statuses, all persons)
		$args = [
			'post_type'      => 'rondo_invoice',
			'post_status'    => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ],
			'posts_per_page' => -1,
		];

		$query    = new \WP_Query( $args );
		$case_ids = [];

		// Extract discipline case IDs from line items across all invoices
		foreach ( $query->posts as $invoice ) {
			$line_items = get_field( 'line_items', $invoice->ID );

			if ( $line_items && is_array( $line_items ) ) {
				foreach ( $line_items as $item ) {
					if ( ! empty( $item['discipline_case'] ) ) {
						$case_ids[] = (int) $item['discipline_case'];
					}
				}
			}
		}

		// Return unique case IDs
		$case_ids = array_values( array_unique( $case_ids ) );

		return rest_ensure_response( [ 'case_ids' => $case_ids ] );
	}

	/**
	 * Bulk create invoices from an array of discipline case IDs
	 *
	 * Groups cases by person and creates one invoice per person.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response containing created invoices and any errors.
	 */
	public function bulk_create_invoices( $request ) {
		$case_ids = $request->get_param( 'case_ids' );

		// Validate: non-empty array
		if ( empty( $case_ids ) || ! is_array( $case_ids ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'case_ids is required and must be a non-empty array.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Validate: all numeric
		foreach ( $case_ids as $id ) {
			if ( ! is_numeric( $id ) ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'All case_ids must be numeric.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}
		}

		// Load each discipline case and group by person
		$cases_by_person = [];

		foreach ( $case_ids as $case_id ) {
			$case_id = (int) $case_id;
			$case    = get_post( $case_id );

			if ( ! $case || $case->post_type !== 'discipline_case' ) {
				continue; // Skip invalid cases
			}

			// Skip discipline cases for teams configured as charging exceptions.
			if ( $this->is_discipline_case_charging_exception( $case_id ) ) {
				continue;
			}

			$person_id = (int) get_field( 'person', $case_id );

			if ( empty( $person_id ) ) {
				continue; // Skip cases without a person
			}

			if ( ! isset( $cases_by_person[ $person_id ] ) ) {
				$cases_by_person[ $person_id ] = [];
			}

			$cases_by_person[ $person_id ][] = $case;
		}

		if ( empty( $cases_by_person ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'No valid discipline cases found.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Create one invoice per person group
		$created_invoices = [];
		$errors           = [];

		foreach ( $cases_by_person as $person_id => $person_cases ) {
			// Build line items for this person's cases
			$line_items = [];

			foreach ( $person_cases as $case ) {
				$acf         = get_fields( $case->ID );
				$description = ! empty( $acf['match_description'] ) ? $acf['match_description'] : ( $acf['sanction_description'] ?? '' );
				$amount      = (float) ( $acf['administrative_fee'] ?? 0 );

				$line_items[] = [
					'discipline_case_id' => $case->ID,
					'description'        => $description,
					'amount'             => $amount,
				];
			}

			// Build a WP_REST_Request and call create_invoice
			$sub_request = new \WP_REST_Request( 'POST', '/rondo/v1/invoices' );
			$sub_request->set_param( 'person_id', $person_id );
			$sub_request->set_param( 'line_items', $line_items );
			$sub_request->set_param( 'invoice_type', 'discipline' );

			$result = $this->create_invoice( $sub_request );

			if ( is_wp_error( $result ) ) {
				$errors[] = [
					'person_id' => $person_id,
					'error'     => $result->get_error_message(),
				];
			} else {
				$created_invoices[] = $result->get_data();
			}
		}

		return rest_ensure_response(
			[
				'invoices' => $created_invoices,
				'errors'   => $errors,
			]
		);
	}

	/**
	 * Check whether a discipline case belongs to a team that is exempt from charging.
	 *
	 * @param int $case_id Discipline case post ID.
	 * @return bool True when case should be treated as exception.
	 */
	private function is_discipline_case_charging_exception( int $case_id ): bool {
		$vog_email    = new \Rondo\VOG\VOGEmail();
		$exempt_teams = $vog_email->get_exempt_discipline_teams();
		if ( empty( $exempt_teams ) ) {
			return false;
		}

		$home_team = get_field( 'home_team', $case_id );
		$away_team = get_field( 'away_team', $case_id );

		$team_id = 0;
		if ( is_numeric( $home_team ) && (int) $home_team > 0 ) {
			$team_id = (int) $home_team;
		} elseif ( is_numeric( $away_team ) && (int) $away_team > 0 ) {
			$team_id = (int) $away_team;
		}

		if ( $team_id > 0 && in_array( $team_id, $exempt_teams, true ) ) {
			return true;
		}

		// Fallback: match by team_name text when home/away team IDs are missing.
		$team_name = get_field( 'team_name', $case_id );
		if ( ! is_string( $team_name ) || '' === trim( $team_name ) ) {
			return false;
		}
		$team_name = trim( wp_strip_all_tags( $team_name ) );

		foreach ( $exempt_teams as $exempt_team_id ) {
			$title = get_the_title( (int) $exempt_team_id );
			if ( ! is_string( $title ) || '' === $title ) {
				continue;
			}
			if ( 0 === strcasecmp( trim( $title ), $team_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get preview of the next invoice number.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function get_next_invoice_number( $request ) {
		$invoice_type = sanitize_key( (string) ( $request->get_param( 'invoice_type' ) ?: 'manual' ) );
		if ( ! in_array( $invoice_type, [ 'manual', 'discipline', 'membership' ], true ) ) {
			$invoice_type = 'manual';
		}

		return rest_ensure_response( [
			'invoice_number' => InvoiceNumbering::generate_next( $invoice_type ),
		] );
	}

	/**
	 * Get list of invoices
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response containing invoice list.
	 */
	public function get_invoice_list( $request ) {
		// Update overdue invoices before returning list
		$this->check_overdue_invoices();

		// Build query args
		$args = [
			'post_type'      => 'rondo_invoice',
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		// Filter by status if provided
		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) ) {
			$args['post_status'] = 'rondo_' . $status;
		} else {
			$args['post_status'] = [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue' ];
		}

		// Initialize meta_query — filters are composed via AND relation
		$args['meta_query'] = [];

		// Filter by person if provided
		$person_id = $request->get_param( 'person_id' );
		if ( ! empty( $person_id ) ) {
			$args['meta_query'][] = [
				'key'     => 'person',
				'value'   => $person_id,
				'compare' => '=',
			];
		}

		// Filter by invoice type if provided
		$type = $request->get_param( 'type' );
		if ( ! empty( $type ) ) {
			if ( 'discipline' === $type ) {
				// Include null/empty invoice_type for legacy discipline invoices
				$args['meta_query'][] = [
					'relation' => 'OR',
					[
						'key'   => 'invoice_type',
						'value' => 'discipline',
					],
					[
						'key'     => 'invoice_type',
						'compare' => 'NOT EXISTS',
					],
					[
						'key'   => 'invoice_type',
						'value' => '',
					],
				];
			} else {
				$args['meta_query'][] = [
					'key'   => 'invoice_type',
					'value' => $type,
				];
			}
		}

		// Filter by payment plan if provided
		$payment_plan = $request->get_param( 'payment_plan' );
		if ( ! empty( $payment_plan ) ) {
			$args['meta_query'][] = [
				'key'   => '_installment_plan',
				'value' => $payment_plan,
			];
		}

		// Add AND relation when multiple meta_query clauses exist
		if ( count( $args['meta_query'] ) > 1 ) {
			$args['meta_query']['relation'] = 'AND';
		}

		// Remove empty meta_query to avoid WP_Query warnings
		if ( empty( $args['meta_query'] ) ) {
			unset( $args['meta_query'] );
		}

		// Execute query
		$query    = new \WP_Query( $args );
		$invoices = array_map( [ $this, 'format_invoice' ], $query->posts );

		return rest_ensure_response( $invoices );
	}

	/**
	 * Get a single invoice
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing invoice or error.
	 */
	public function get_invoice( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );
		$invoice    = get_post( $invoice_id );

		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Invoice not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Create new invoice
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing created invoice or error.
	 */
	public function create_invoice( $request ) {
		$person_id           = (int) $request->get_param( 'person_id' );
		$line_items          = $request->get_param( 'line_items' );
		$invoice_kind        = $request->get_param( 'invoice_kind' ) === 'credit' ? 'credit' : 'normal';
		$invoice_type        = sanitize_key( (string) ( $request->get_param( 'invoice_type' ) ?: 'manual' ) );
		$customer_name       = sanitize_text_field( (string) $request->get_param( 'customer_name' ) );
		$customer_address    = sanitize_textarea_field( (string) $request->get_param( 'customer_address' ) );
		$due_date_override   = preg_replace( '/[^0-9]/', '', (string) $request->get_param( 'payment_terms_due_date' ) );
		$email_subject       = sanitize_text_field( (string) $request->get_param( 'email_subject' ) );
		$email_body_override = wp_kses_post( (string) $request->get_param( 'email_body_override' ) );
		$custom_fields       = $request->get_param( 'custom_fields' );

		if ( empty( $line_items ) || ! is_array( $line_items ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'Line items are required.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'line_items' => 'Line items are required' ] ]
			);
		}

		$rows = [];
		$total_amount = 0.0;
		foreach ( $line_items as $item ) {
			$amount = (float) ( $item['amount'] ?? 0 );
			$rows[] = [
				'discipline_case' => ! empty( $item['discipline_case_id'] ) ? absint( $item['discipline_case_id'] ) : null,
				'description'     => sanitize_text_field( $item['description'] ?? '' ),
				'amount'          => $amount,
			];
			$total_amount += $amount;
		}

		if ( empty( $rows ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'At least one line item is required.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$person = null;
		if ( $person_id > 0 ) {
			$person = get_post( $person_id );
			if ( ! $person || $person->post_type !== 'person' ) {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'Invalid person ID.', 'rondo' ),
					[ 'status' => 400, 'params' => [ 'person_id' => 'Person does not exist' ] ]
				);
			}
		}

		if ( $person_id <= 0 && ( '' === $customer_name || '' === $customer_address ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'Customer name and address are required when no member is linked.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! in_array( $invoice_type, [ 'discipline', 'membership', 'manual' ], true ) ) {
			$invoice_type = 'manual';
		}

		$invoice_number = InvoiceNumbering::generate_next( $invoice_type );
		$post_id = wp_insert_post(
			[
				'post_type'   => 'rondo_invoice',
				'post_title'  => $invoice_number,
				'post_status' => 'rondo_draft',
				'post_author' => get_current_user_id(),
			]
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error(
				'rest_cannot_create',
				__( 'Failed to create invoice.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		update_field( 'invoice_number', $invoice_number, $post_id );
		update_field( 'person', $person_id > 0 ? $person_id : null, $post_id );
		update_field( 'status', 'draft', $post_id );
		update_field( 'invoice_type', $invoice_type, $post_id );
		update_field( 'total_amount', $total_amount, $post_id );
		update_field( 'line_items', $rows, $post_id );

		update_post_meta( $post_id, '_invoice_kind', $invoice_kind );
		update_post_meta( $post_id, '_customer_name', $customer_name );
		update_post_meta( $post_id, '_customer_address', $customer_address );
		update_post_meta( $post_id, '_email_subject', $email_subject );
		update_post_meta( $post_id, '_email_body_override', $email_body_override );
		if ( preg_match( '/^\d{8}$/', $due_date_override ) ) {
			update_field( 'field_invoice_due_date', $due_date_override, $post_id );
		}

		if ( is_array( $custom_fields ) ) {
			$normalized = [];
			foreach ( array_slice( $custom_fields, 0, 2 ) as $field ) {
				$label = sanitize_text_field( (string) ( $field['label'] ?? '' ) );
				$text  = sanitize_textarea_field( (string) ( $field['text'] ?? '' ) );
				if ( '' !== $label || '' !== $text ) {
					$normalized[] = [
						'label' => $label,
						'text'  => $text,
					];
				}
			}
			update_post_meta( $post_id, '_custom_fields', wp_json_encode( $normalized ) );
		}

		$invoice = get_post( $post_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Delete a draft invoice
	 *
	 * Permanently deletes a draft invoice, cleaning up associated files (PDF, QR code),
	 * clearing payment data, and resetting linked discipline cases so the invoice number
	 * can be reused by generate_next().
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response confirming deletion or error.
	 */
	public function delete_invoice( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		// Validate invoice exists and is correct post type
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Factuur niet gevonden.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Guard: only draft invoices can be deleted
		if ( $invoice->post_status !== 'rondo_draft' ) {
			return new \WP_Error(
				'invoice_not_draft',
				__( 'Alleen conceptfacturen kunnen worden verwijderd.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Clean up PDF file from disk
		$this->clear_pdf( $invoice_id );

		// Clean up QR code file from disk
		$this->clear_qr_code( $invoice_id );

		// Clear payment provider data
		delete_post_meta( $invoice_id, '_mollie_payment_link_id' );
		delete_post_meta( $invoice_id, '_rabobank_payment_request_id' );

		// Reset linked discipline cases (is_charged back to empty)
		$line_items = get_field( 'line_items', $invoice_id );
		if ( $line_items && is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				if ( ! empty( $item['discipline_case'] ) ) {
					update_field( 'is_charged', '', (int) $item['discipline_case'] );
				}
			}
		}

		// Force delete (skip trash) so the invoice number is freed for generate_next()
		wp_delete_post( $invoice_id, true );

		return rest_ensure_response( [ 'deleted' => true, 'id' => $invoice_id ] );
	}

	/**
	 * Update invoice status
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing updated invoice or error.
	 */
	public function update_invoice_status( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );
		$status     = $request->get_param( 'status' );

		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Invoice not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Validate status
		if ( empty( $status ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'Status is required.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'status' => 'Status is required' ] ]
			);
		}

		if ( ! in_array( $status, [ 'draft', 'sent', 'paid', 'overdue' ], true ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Invalid status.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'status' => 'Must be "draft", "sent", "paid", or "overdue"' ] ]
			);
		}

		// Map status to post_status
		$post_status = 'rondo_' . $status;

		// Update post status
		wp_update_post(
			[
				'ID'          => $invoice_id,
				'post_status' => $post_status,
			]
		);

		// Update ACF status field
		update_field( 'status', $status, $invoice_id );

			// If transitioning to "sent", set sent_date and calculate due_date
			if ( $status === 'sent' ) {
				$sent_date = current_time( 'Ymd' );
				update_field( 'field_invoice_sent_date', $sent_date, $invoice_id );

			// Calculate due date
			$finance_config   = new FinanceConfig();
			$payment_term_days = $finance_config->get_payment_term_days();
				$due_date         = date( 'Ymd', strtotime( "+{$payment_term_days} days" ) );
				update_field( 'field_invoice_due_date', $due_date, $invoice_id );
			}

			// If transitioning to paid, remove payment artifacts (link/QR/provider IDs).
			if ( $status === 'paid' ) {
				update_field( 'payment_link', '', $invoice_id );
				delete_post_meta( $invoice_id, '_mollie_payment_link_id' );
				delete_post_meta( $invoice_id, '_rabobank_payment_request_id' );
				$this->clear_qr_code( $invoice_id );
			}

			// Return updated invoice
			$invoice = get_post( $invoice_id );
			return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
		}

	/**
	 * Add a manual line item to a draft invoice.
	 *
	 * Used for post-creation corrections, including positive surcharges
	 * or negative discounts.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing updated invoice or error.
	 */
	public function add_draft_line_item( $request ) {
		$invoice_id  = (int) $request->get_param( 'id' );
		$description = sanitize_text_field( (string) $request->get_param( 'description' ) );
		$amount      = round( (float) $request->get_param( 'amount' ), 2 );

		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Factuur niet gevonden.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		if ( 'rondo_draft' !== $invoice->post_status ) {
			return new \WP_Error(
				'invoice_not_draft',
				__( 'Alleen conceptfacturen kunnen worden aangepast.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		if ( '' === trim( $description ) ) {
			return new \WP_Error(
				'invalid_description',
				__( 'Omschrijving is verplicht.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		if ( abs( $amount ) < 0.01 ) {
			return new \WP_Error(
				'invalid_amount',
				__( 'Bedrag moet groter dan 0 zijn.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$line_items = get_field( 'line_items', $invoice_id );
		if ( ! is_array( $line_items ) ) {
			$line_items = [];
		}

		$line_items[] = [
			'discipline_case' => null,
			'description'     => $description,
			'amount'          => $amount,
		];

		$new_total = 0.0;
		foreach ( $line_items as $item ) {
			$new_total += (float) ( $item['amount'] ?? 0 );
		}
		$new_total = round( $new_total, 2 );

		update_field( 'line_items', $line_items, $invoice_id );
		update_field( 'total_amount', $new_total, $invoice_id );

		// Existing PDF can have stale totals after manual correction.
		$this->clear_pdf( $invoice_id );

		$invoice = get_post( $invoice_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Generate PDF for an invoice
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing updated invoice or error.
	 */
	public function generate_pdf( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Invoice not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Generate PDF
		$result = InvoicePdfGenerator::generate( $invoice_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Return updated invoice detail (now includes pdf_path)
		$invoice = get_post( $invoice_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Download invoice PDF
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return void Serves PDF file directly, exits script.
	 */
	public function download_pdf( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Invoice not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$pdf_path = get_field( 'pdf_path', $invoice_id );
		if ( empty( $pdf_path ) ) {
			return new \WP_Error(
				'rest_no_pdf',
				__( 'No PDF generated for this invoice.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$upload_dir = wp_upload_dir();
		$full_path  = $upload_dir['basedir'] . '/' . $pdf_path;

		if ( ! file_exists( $full_path ) ) {
			return new \WP_Error(
				'rest_file_not_found',
				__( 'PDF file not found on disk.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Get invoice number for filename
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$filename = 'factuur-' . $invoice_number . '.pdf';

		// Serve PDF file directly
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $full_path ) );
		readfile( $full_path );
		exit;
	}

	/**
	 * Download invoice QR code
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return void|\WP_Error Serves QR PNG file directly, exits script.
	 */
	public function download_qr( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Invoice not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$qr_path = get_field( 'qr_code_path', $invoice_id );
		if ( empty( $qr_path ) ) {
			return new \WP_Error(
				'rest_no_qr',
				__( 'No QR code available for this invoice.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$upload_dir = wp_upload_dir();
		$full_path  = $upload_dir['basedir'] . '/' . $qr_path;

		if ( ! file_exists( $full_path ) ) {
			return new \WP_Error(
				'rest_file_not_found',
				__( 'QR code file not found on disk.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$filename       = 'qr-' . $invoice_number . '.png';

		header( 'Content-Type: image/png' );
		header( 'Content-Disposition: inline; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $full_path ) );
		readfile( $full_path );
		exit;
	}

	/**
	 * Send invoice via email
	 *
	 * Orchestrates the full send flow: PDF generation, payment link creation,
	 * email delivery, and status transition to Sent.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing updated invoice or error.
	 */
	public function send_invoice( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Factuur niet gevonden.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Check invoice is in draft status
		if ( $invoice->post_status !== 'rondo_draft' ) {
			return new \WP_Error(
				'invoice_not_draft',
				__( 'Alleen conceptfacturen kunnen worden verstuurd.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Create payment link + QR code BEFORE PDF generation so QR is embedded in PDF.
		// Membership invoices use the public payment page (/betaling/{token}) for plan selection,
		// so skip direct Mollie/Rabobank payment link creation — it would overwrite the token URL.
		$invoice_type   = get_post_meta( $invoice_id, 'invoice_type', true );
		$finance_config = new FinanceConfig();
		$invoice_kind   = get_post_meta( $invoice_id, '_invoice_kind', true ) ?: 'normal';

		if ( 'credit' === $invoice_kind ) {
			// Credit invoices should not create payment links; they represent a financial adjustment.
			delete_post_meta( $invoice_id, '_mollie_payment_link_id' );
			delete_post_meta( $invoice_id, '_rabobank_payment_request_id' );
			update_field( 'payment_link', '', $invoice_id );
			$this->clear_qr_code( $invoice_id );
		} elseif ( 'membership' === $invoice_type ) {
			// Membership invoices: QR points to /betaling/{token} plan selection page.
			$payment_url = get_field( 'payment_link', $invoice_id );
			if ( ! empty( $payment_url ) ) {
				$qr_result = \Rondo\Finance\QrCodeGenerator::generate( $payment_url, $invoice_id );
				if ( is_wp_error( $qr_result ) ) {
					error_log( 'QR code generation failed for invoice ' . $invoice_id . ': ' . $qr_result->get_error_message() );
				}
			}
		} else {
			// Discipline/other invoices: create direct Mollie/Rabobank payment link + QR.
			$active_provider = $finance_config->get_active_payment_provider();

			if ( 'mollie' === $active_provider ) {
				$mollie_payment = new MolliePayment();
				$payment_result = $mollie_payment->create_payment_link( $invoice_id );
				if ( is_wp_error( $payment_result ) ) {
					error_log( 'Mollie payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
				} elseif ( ! empty( $payment_result ) ) {
					$qr_result = \Rondo\Finance\QrCodeGenerator::generate( $payment_result, $invoice_id );
					if ( is_wp_error( $qr_result ) ) {
						error_log( 'QR code generation failed for invoice ' . $invoice_id . ': ' . $qr_result->get_error_message() );
					}
				}
			} else {
				$oauth = new RabobankOAuth();
				if ( $oauth->is_connected() ) {
					$payment        = new RabobankPayment( $oauth );
					$payment_result = $payment->create_payment_request( $invoice_id );
					if ( is_wp_error( $payment_result ) ) {
						error_log( 'Rabobank payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
					}
				}
			}
		}

		// Always (re)generate PDF so QR code and payment link are included
		$pdf_result = InvoicePdfGenerator::generate( $invoice_id );
		if ( is_wp_error( $pdf_result ) ) {
			return $pdf_result;
		}

		// Build email options — redirect to current user in test mode
		$email_options = [];
		if ( $this->is_test_mode_active() ) {
			$current_user = wp_get_current_user();
			if ( ! empty( $current_user->user_email ) ) {
				$email_options['override_email'] = $current_user->user_email;
				$email_options['skip_bcc']       = true;
			}
		}

		$custom_email_subject = (string) get_post_meta( $invoice_id, '_email_subject', true );
		$custom_email_body    = (string) get_post_meta( $invoice_id, '_email_body_override', true );
		if ( '' !== trim( $custom_email_subject ) ) {
			$email_options['subject'] = $custom_email_subject;
		}
		if ( '' !== trim( $custom_email_body ) ) {
			$email_options['template'] = $custom_email_body;
		}

		// Select email template based on invoice type
		$invoice_type = get_field( 'invoice_type', $invoice_id );
		if ( 'membership' === $invoice_type && empty( $email_options['template'] ) ) {
			$email_options['template'] = $finance_config->get_membership_email_template();
		}

		// Send email via InvoiceEmailSender
		$email_result = InvoiceEmailSender::send( $invoice_id, $email_options );
		if ( is_wp_error( $email_result ) ) {
			return $email_result;
		}

		// Transition status to Sent
		wp_update_post(
			[
				'ID'          => $invoice_id,
				'post_status' => 'rondo_sent',
			]
		);

		// Update ACF status field
		update_field( 'status', 'sent', $invoice_id );

		// Set sent_date
		$sent_date = current_time( 'Ymd' );
		update_field( 'field_invoice_sent_date', $sent_date, $invoice_id );

		// Calculate and set due_date
		$due_date_override = (string) get_field( 'due_date', $invoice_id );
		if ( preg_match( '/^\d{8}$/', $due_date_override ) ) {
			$due_date = $due_date_override;
		} else {
			$config = new FinanceConfig();
			$payment_term_days = $config->get_payment_term_days();
			$due_date = date( 'Ymd', strtotime( "+{$payment_term_days} days" ) );
		}
		update_field( 'field_invoice_due_date', $due_date, $invoice_id );

		// Mark linked discipline cases as charged via Rondo
		$line_items = get_field( 'line_items', $invoice_id );
		if ( $line_items && is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				if ( ! empty( $item['discipline_case'] ) ) {
					update_field( 'is_charged', 'rondo', (int) $item['discipline_case'] );
				}
			}
		}

		if ( 'credit' === $invoice_kind ) {
			wp_update_post(
				[
					'ID'          => $invoice_id,
					'post_status' => 'rondo_paid',
				]
			);
			update_field( 'status', 'paid', $invoice_id );
			update_post_meta( $invoice_id, '_credit_payment_adjustment_recorded_at', current_time( 'mysql' ) );
		}

		// Return updated invoice detail
		$invoice = get_post( $invoice_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Resend invoice email
	 *
	 * Allows re-sending the invoice email for sent or overdue invoices.
	 * Uses the existing InvoiceEmailSender service to send the email again.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing updated invoice or error.
	 */
	public function resend_invoice( $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Factuur niet gevonden.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Check invoice status is sent or overdue
		if ( ! in_array( $invoice->post_status, [ 'rondo_sent', 'rondo_overdue' ], true ) ) {
			return new \WP_Error(
				'invoice_not_sent',
				__( 'Alleen verstuurde of verlopen facturen kunnen opnieuw worden verstuurd.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Build email options — redirect to current user in test mode
		$email_options = [];
		if ( $this->is_test_mode_active() ) {
			$current_user = wp_get_current_user();
			if ( ! empty( $current_user->user_email ) ) {
				$email_options['override_email'] = $current_user->user_email;
				$email_options['skip_bcc']       = true;
			}
		}

		$custom_email_subject = (string) get_post_meta( $invoice_id, '_email_subject', true );
		$custom_email_body    = (string) get_post_meta( $invoice_id, '_email_body_override', true );
		if ( '' !== trim( $custom_email_subject ) ) {
			$email_options['subject'] = $custom_email_subject;
		}
		if ( '' !== trim( $custom_email_body ) ) {
			$email_options['template'] = $custom_email_body;
		}

		// Select email template based on invoice type
		$invoice_type = get_field( 'invoice_type', $invoice_id );
		if ( 'membership' === $invoice_type ) {
			$config = new FinanceConfig();
			$email_options['template'] = $config->get_membership_email_template();
		}

		// Send email via InvoiceEmailSender
		$email_result = InvoiceEmailSender::send( $invoice_id, $email_options );
		if ( is_wp_error( $email_result ) ) {
			return $email_result;
		}

		// Return updated invoice detail
		$invoice = get_post( $invoice_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Regenerate payment link for an invoice
	 *
	 * Clears the existing payment link and creates a new one for any unpaid invoice.
	 * For Mollie, clears the stored payment ID to bypass idempotency.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing updated invoice or error.
	 */
	public function regenerate_payment_link( \WP_REST_Request $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Factuur niet gevonden.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Only unpaid invoices can have payment links regenerated
		$status = get_field( 'status', $invoice_id );
		if ( $status === 'paid' ) {
			return new \WP_Error(
				'invoice_paid',
				__( 'Betaalde facturen kunnen geen nieuwe betaallink krijgen.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$finance_config  = new FinanceConfig();
		$active_provider = $finance_config->get_active_payment_provider();

		if ( 'mollie' === $active_provider ) {
			// Clear Mollie payment link ID to bypass idempotency and force a new payment link
			delete_post_meta( $invoice_id, '_mollie_payment_link_id' );
			update_field( 'payment_link', '', $invoice_id );

			$mollie_payment = new MolliePayment();
			$result         = $mollie_payment->create_payment_link( $invoice_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Generate branded QR code from new payment URL (non-blocking)
			if ( ! empty( $result ) ) {
				$qr_result = \Rondo\Finance\QrCodeGenerator::generate( $result, $invoice_id );
				if ( is_wp_error( $qr_result ) ) {
					error_log( 'QR code generation failed for invoice ' . $invoice_id . ': ' . $qr_result->get_error_message() );
				}
			}
		} else {
			$oauth = new RabobankOAuth();
			if ( ! $oauth->is_connected() ) {
				return new \WP_Error(
					'rabobank_not_connected',
					__( 'Rabobank is niet gekoppeld.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}
			$payment = new RabobankPayment( $oauth );
			$result  = $payment->create_payment_request( $invoice_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Regenerate PDF to reflect new payment link and QR code state
		$pdf_result = InvoicePdfGenerator::generate( $invoice_id );
		if ( is_wp_error( $pdf_result ) ) {
			return $pdf_result;
		}

		// Return updated invoice
		$invoice = get_post( $invoice_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Toggle installments disabled flag for a membership invoice.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function toggle_installments( \WP_REST_Request $request ) {
		$invoice_id = (int) $request->get_param( 'id' );
		$invoice    = get_post( $invoice_id );

		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error( 'not_found', 'Factuur niet gevonden.', [ 'status' => 404 ] );
		}

		$disabled = (bool) $request->get_param( 'disabled' );

		if ( $disabled ) {
			update_post_meta( $invoice_id, '_disable_installments', '1' );
			// Clear betaling page token — send_invoice() creates a direct Mollie link instead.
			delete_post_meta( $invoice_id, '_payment_token' );
			update_field( 'payment_link', '', $invoice_id );
		} else {
			delete_post_meta( $invoice_id, '_disable_installments' );
			// Generate betaling page token so member can choose a payment plan.
			PublicPaymentPage::generate_token( $invoice_id );
		}

		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Update family/entry discount percentages on a sent/unpaid membership invoice.
	 *
	 * Guardrails:
	 * - membership invoices only
	 * - sent/overdue only (not draft/paid)
	 * - blocked when a paid installment exists
	 * - blocked when installment Mollie payment links were already issued
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error Response or error.
	 */
	public function update_membership_discount( \WP_REST_Request $request ) {
		$invoice_id             = (int) $request->get_param( 'id' );
		$has_family_param       = null !== $request->get_param( 'family_discount_percent' );
		$has_entry_param        = null !== $request->get_param( 'entry_discount_percent' );
		$family_discount_percent = $has_family_param ? round( (float) $request->get_param( 'family_discount_percent' ), 2 ) : null;
		$entry_discount_percent  = $has_entry_param ? round( (float) $request->get_param( 'entry_discount_percent' ), 2 ) : null;

		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error( 'not_found', __( 'Factuur niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		$invoice_type = (string) get_field( 'invoice_type', $invoice_id );
		if ( 'membership' !== $invoice_type ) {
			return new \WP_Error(
				'invalid_invoice_type',
				__( 'Alleen contributiefacturen kunnen hier worden aangepast.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$status = (string) get_field( 'status', $invoice_id );
		if ( ! in_array( $status, [ 'sent', 'overdue' ], true ) ) {
			return new \WP_Error(
				'invoice_status_invalid',
				__( 'Alleen verstuurde of verlopen contributiefacturen kunnen worden aangepast.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! $has_family_param && ! $has_entry_param ) {
			return new \WP_Error(
				'missing_discount_param',
				__( 'Geef minimaal één korting op om aan te passen.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}
		if ( $has_family_param && ( $family_discount_percent < 0 || $family_discount_percent > 100 ) ) {
			return new \WP_Error(
				'invalid_family_discount_percent',
				__( 'Gezinskorting moet tussen 0 en 100 liggen.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}
		if ( $has_entry_param && ( $entry_discount_percent < 0 || $entry_discount_percent > 100 ) ) {
			return new \WP_Error(
				'invalid_entry_discount_percent',
				__( 'Instapkorting moet tussen 0 en 100 liggen.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$installment_count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		for ( $n = 1; $n <= max( $installment_count, 8 ); $n++ ) {
			$installment_status = (string) get_post_meta( $invoice_id, '_installment_' . $n . '_status', true );
			if ( 'betaald' === $installment_status ) {
				return new \WP_Error(
					'installment_paid',
					__( 'Deze factuur heeft al betaalde termijn(en) en kan niet meer worden aangepast.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}

			$issued_payment_link_id = (string) get_post_meta( $invoice_id, '_installment_' . $n . '_mollie_payment_id', true );
			if ( '' !== $issued_payment_link_id ) {
				return new \WP_Error(
					'installment_link_issued',
					__( 'Deze factuur heeft al een actieve termijnbetaallink. Pas eerst het betaalplan aan via de publieke betaalpagina.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}
		}

		$line_items = get_field( 'line_items', $invoice_id );
		if ( ! is_array( $line_items ) || empty( $line_items ) ) {
			return new \WP_Error( 'missing_line_items', __( 'Factuurregels ontbreken.', 'rondo' ), [ 'status' => 400 ] );
		}

		$family_line_index          = -1;
		$entry_line_index           = -1;
		$current_family_rate_percent = 0.0;
		$current_family_discount_abs = 0.0;
		$current_entry_rate_percent  = 0.0;
		$current_entry_discount_abs  = 0.0;
		$base_line_index             = -1;
		$base_amount                 = 0.0;

		foreach ( $line_items as $index => $item ) {
			$description = (string) ( $item['description'] ?? '' );
			$amount      = (float) ( $item['amount'] ?? 0 );

			if ( $base_line_index < 0 && $amount > 0 && 0 === stripos( trim( $description ), 'Contributie ' ) ) {
				$base_line_index = (int) $index;
				$base_amount     = $amount;
			}

				if ( preg_match( '/^Gezinskorting\s*\(([\d\.,]+)%\)/i', trim( $description ), $matches ) ) {
					$family_line_index           = (int) $index;
					$current_family_discount_abs = abs( $amount );
					$current_family_rate_percent = (float) str_replace( ',', '.', $matches[1] );
				}

				if ( preg_match( '/^Instapkorting\s*\(([\d\.,]+)%\)/i', trim( $description ), $matches ) ) {
					$entry_line_index           = (int) $index;
					$current_entry_discount_abs = abs( $amount );
					$current_entry_rate_percent = (float) str_replace( ',', '.', $matches[1] );
				}
			}

			if ( $base_amount <= 0 && $current_family_discount_abs > 0 && $current_family_rate_percent > 0 ) {
				$base_amount = round( $current_family_discount_abs / ( $current_family_rate_percent / 100 ), 2 );
			}
			if ( $base_amount <= 0 && $current_entry_discount_abs > 0 && $current_entry_rate_percent > 0 ) {
				$base_amount = round( $current_entry_discount_abs / ( $current_entry_rate_percent / 100 ), 2 );
			}

		if ( $base_amount <= 0 ) {
			return new \WP_Error(
				'missing_base_fee',
				__( 'Kon basis contributiebedrag niet bepalen voor kortingsherberekening.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		$effective_family_percent = $has_family_param ? $family_discount_percent : $current_family_rate_percent;
		$effective_entry_percent  = $has_entry_param ? $entry_discount_percent : $current_entry_rate_percent;
		$family_discount_abs      = round( $base_amount * ( $effective_family_percent / 100 ), 2 );
		$fee_after_family         = round( $base_amount - $family_discount_abs, 2 );
		$entry_discount_abs       = round( $fee_after_family * ( $effective_entry_percent / 100 ), 2 );

		$updated_line_items = $line_items;
		if ( $family_line_index >= 0 ) {
			unset( $updated_line_items[ $family_line_index ] );
		}
		if ( $entry_line_index >= 0 ) {
			unset( $updated_line_items[ $entry_line_index ] );
		}
		$updated_line_items = array_values( $updated_line_items );

		$insert_index = $base_line_index >= 0 ? $base_line_index + 1 : count( $updated_line_items );
		if ( $family_discount_abs > 0 ) {
			$family_label     = 'Gezinskorting (' . $this->format_percentage_label( $effective_family_percent ) . '%)';
			$family_discount_row = [
				'discipline_case' => null,
				'description'     => $family_label,
				'amount'          => -$family_discount_abs,
			];
			array_splice( $updated_line_items, $insert_index, 0, [ $family_discount_row ] );
			$insert_index++;
		}
		if ( $entry_discount_abs > 0 ) {
			$entry_label      = 'Instapkorting (' . $this->format_percentage_label( $effective_entry_percent ) . '%) - omdat je later in het seizoen start';
			$entry_discount_row = [
				'discipline_case' => null,
				'description'     => $entry_label,
				'amount'          => -$entry_discount_abs,
			];
			array_splice( $updated_line_items, $insert_index, 0, [ $entry_discount_row ] );
		}

		$updated_line_items = array_values( $updated_line_items );
		$new_total          = 0.0;
		foreach ( $updated_line_items as $item ) {
			$new_total += (float) ( $item['amount'] ?? 0 );
		}
		$new_total = round( $new_total, 2 );

		update_field( 'line_items', $updated_line_items, $invoice_id );
		update_field( 'total_amount', $new_total, $invoice_id );

		// Existing PDF contains outdated totals; force explicit regeneration.
		$this->clear_pdf( $invoice_id );

		$invoice = get_post( $invoice_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Check whether the active payment provider is in test/sandbox mode
	 *
	 * Used to gate the reset-payment-state endpoint so it is never
	 * available in production/live environments.
	 *
	 * @return bool True if the active provider is in test/sandbox mode.
	 */
	private function is_test_mode_active(): bool {
		$config   = new FinanceConfig();
		$settings = $config->get_all_settings();
		$provider = $settings['active_payment_provider'] ?? '';

		if ( 'mollie' === $provider ) {
			return ( $settings['mollie_environment'] ?? '' ) === 'test';
		}

		if ( 'rabobank' === $provider ) {
			return ( $settings['rabobank_environment'] ?? '' ) === 'sandbox';
		}

		return false;
	}

	/**
	 * Reset payment state for an invoice (test mode only)
	 *
	 * Clears all payment-related data (payment link, provider payment IDs,
	 * QR code) and resets a paid invoice back to sent status.
	 * Returns 403 when the active payment provider is not in test/sandbox mode.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing updated invoice or error.
	 */
	public function reset_payment_state( \WP_REST_Request $request ) {
		$invoice_id = (int) $request->get_param( 'id' );

		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Factuur niet gevonden.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Guard: only available in test/sandbox mode
		if ( ! $this->is_test_mode_active() ) {
			return new \WP_Error(
				'test_mode_required',
				__( 'Betaalstatus resetten is alleen beschikbaar in testmodus.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		// Clear Mollie payment data
		delete_post_meta( $invoice_id, '_mollie_payment_link_id' );
		update_field( 'payment_link', '', $invoice_id );

		// Clear Rabobank payment data (also clear payment_link — idempotent)
		delete_post_meta( $invoice_id, '_rabobank_payment_request_id' );

		// Clear QR code file and field
		$this->clear_qr_code( $invoice_id );

		// Clear PDF file and field
		$this->clear_pdf( $invoice_id );

		// Clear sending dates
		update_field( 'sent_date', '', $invoice_id );
		update_field( 'due_date', '', $invoice_id );
		delete_post_meta( $invoice_id, 'sent_date' );
		delete_post_meta( $invoice_id, 'due_date' );

		// Reset invoice status back to draft (concept)
		wp_update_post(
			[
				'ID'          => $invoice_id,
				'post_status' => 'rondo_draft',
			]
		);
		update_field( 'status', 'draft', $invoice_id );

		// Reset discipline cases doorbelast back to "Nee"
		$line_items = get_field( 'line_items', $invoice_id );
		if ( $line_items && is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				if ( ! empty( $item['discipline_case'] ) ) {
					update_field( 'is_charged', '', (int) $item['discipline_case'] );
				}
			}
		}

		// Return updated invoice
		$invoice = get_post( $invoice_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
	}

	/**
	 * Check for overdue invoices and update their status
	 *
	 * Runs on every list request to keep invoice statuses current.
	 */
	private function check_overdue_invoices() {
		$args = [
			'post_type'      => 'rondo_invoice',
			'post_status'    => 'rondo_sent',
			'posts_per_page' => -1,
			'meta_query'     => [
				[
					'key'     => 'due_date',
					'compare' => 'EXISTS',
				],
			],
		];

		$query = new \WP_Query( $args );
		$today = current_time( 'Ymd' );

		foreach ( $query->posts as $invoice ) {
			$due_date = get_post_meta( $invoice->ID, 'due_date', true );

			if ( $due_date && $due_date < $today ) {
				// Update to overdue status
				wp_update_post(
					[
						'ID'          => $invoice->ID,
						'post_status' => 'rondo_overdue',
					]
				);
				update_field( 'status', 'overdue', $invoice->ID );
			}
		}
	}

	/**
	 * Format an invoice for summary response (list view)
	 *
	 * @param \WP_Post $post The invoice post object.
	 * @return array Formatted invoice data.
	 */
	private function format_invoice( $post ) {
		$status          = get_field( 'status', $post->ID );
		$raw_payment_link = get_field( 'payment_link', $post->ID ) ?: null;
		$payment_link     = ( 'paid' === $status ) ? null : $raw_payment_link;

		return [
			'id'               => $post->ID,
			'invoice_number'   => get_field( 'invoice_number', $post->ID ),
			'person'           => $this->get_invoice_person_summary( $post->ID ),
			'customer_name'    => (string) get_post_meta( $post->ID, '_customer_name', true ),
			'customer_address' => (string) get_post_meta( $post->ID, '_customer_address', true ),
			'invoice_kind'     => get_post_meta( $post->ID, '_invoice_kind', true ) ?: 'normal',
			'total_amount'     => (float) get_field( 'total_amount', $post->ID ),
			'status'           => $status,
			'post_status'      => $post->post_status,
			'sent_date'        => get_post_meta( $post->ID, 'sent_date', true ) ?: null,
			'due_date'         => get_post_meta( $post->ID, 'due_date', true ) ?: null,
			'payment_link'     => $payment_link,
			'created'          => $post->post_date,
			'invoice_type'     => get_field( 'invoice_type', $post->ID ) ?: null,
			'installment_plan' => get_post_meta( $post->ID, '_installment_plan', true ) ?: null,
		];
	}

	/**
	 * Format an invoice for detail response (single view)
	 *
	 * Clear QR code file and field for an invoice.
	 *
	 * Used when switching to a provider that doesn't support QR codes (e.g. Mollie),
	 * or when regenerating a payment link to avoid stale QR codes.
	 *
	 * @param int $invoice_id The invoice post ID.
	 */
	private function clear_qr_code( $invoice_id ) {
		$qr_path = get_field( 'qr_code_path', $invoice_id );
		if ( ! empty( $qr_path ) ) {
			$upload_dir = wp_upload_dir();
			$full_path  = $upload_dir['basedir'] . '/' . $qr_path;
			if ( file_exists( $full_path ) ) {
				unlink( $full_path );
			}
			update_field( 'qr_code_path', '', $invoice_id );
		}
	}

	/**
	 * Clear PDF file and field for an invoice.
	 *
	 * Used when resetting an invoice to draft state, so the PDF can be
	 * regenerated fresh on next send.
	 *
	 * @param int $invoice_id The invoice post ID.
	 */
	private function clear_pdf( $invoice_id ) {
		$pdf_path = get_field( 'pdf_path', $invoice_id );
		if ( ! empty( $pdf_path ) ) {
			$upload_dir = wp_upload_dir();
			$full_path  = $upload_dir['basedir'] . '/' . $pdf_path;
			if ( file_exists( $full_path ) ) {
				unlink( $full_path );
			}
			update_field( 'pdf_path', '', $invoice_id );
		}
	}

	/**
	 * Format percentage for labels without trailing zeros.
	 *
	 * @param float $value Percentage value.
	 * @return string Formatted value (e.g. "25" or "12.5").
	 */
	private function format_percentage_label( float $value ): string {
		$formatted = number_format( $value, 2, '.', '' );
		$formatted = rtrim( rtrim( $formatted, '0' ), '.' );
		return '' === $formatted ? '0' : $formatted;
	}

	/**
	 * @param \WP_Post $post The invoice post object.
	 * @return array Formatted invoice data with full details.
	 */
	private function format_invoice_detail( $post ) {
		$invoice = $this->format_invoice( $post );

		// Add line items with discipline case details
		$line_items = get_field( 'line_items', $post->ID );
		$formatted_items = [];

		if ( $line_items && is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				$formatted_item = [
					'description' => $this->sanitize_text( $item['description'] ?? '' ),
					'amount'      => (float) ( $item['amount'] ?? 0 ),
				];

				// Add discipline case summary if linked
				if ( ! empty( $item['discipline_case'] ) ) {
					$case = get_post( $item['discipline_case'] );
					if ( $case && $case->post_type === 'discipline_case' ) {
						$formatted_item['discipline_case'] = [
							'id'                    => $case->ID,
							'dossier_id'            => get_field( 'dossier_id', $case->ID ) ?: '',
							'match_description'     => get_field( 'match_description', $case->ID ) ?: '',
							'charge_description'    => get_field( 'charge_description', $case->ID ) ?: '',
							'sanction_description'  => get_field( 'sanction_description', $case->ID ) ?: '',
						];
					} else {
						$formatted_item['discipline_case'] = null;
					}
				} else {
					$formatted_item['discipline_case'] = null;
				}

				$formatted_items[] = $formatted_item;
			}
		}

		$invoice['line_items']          = $formatted_items;
		$invoice['pdf_path']            = get_field( 'pdf_path', $post->ID ) ?: null;
		$invoice['qr_code_path']        = get_field( 'qr_code_path', $post->ID ) ?: null;
		$invoice['email_subject']       = (string) get_post_meta( $post->ID, '_email_subject', true );
		$invoice['email_body_override'] = (string) get_post_meta( $post->ID, '_email_body_override', true );
		$invoice['payment_adjusted_at'] = (string) get_post_meta( $post->ID, '_credit_payment_adjustment_recorded_at', true ) ?: null;
		$custom_fields_raw = (string) get_post_meta( $post->ID, '_custom_fields', true );
		$custom_fields = json_decode( $custom_fields_raw, true );
		$invoice['custom_fields'] = is_array( $custom_fields ) ? $custom_fields : [];

		// Add installment data for multi-installment invoices
		$plan  = get_post_meta( $post->ID, '_installment_plan', true ) ?: null;
		$count = (int) get_post_meta( $post->ID, '_installment_count', true );

		$installments = [];
		if ( $count >= 1 && $plan && $plan !== 'full' ) {
			for ( $n = 1; $n <= $count; $n++ ) {
				$amount    = (float) get_post_meta( $post->ID, '_installment_' . $n . '_amount', true );
				$admin_fee = (float) get_post_meta( $post->ID, '_installment_' . $n . '_admin_fee', true );
				$installments[] = [
					'number'   => $n,
					'amount'   => $amount + $admin_fee,
					'status'   => (string) get_post_meta( $post->ID, '_installment_' . $n . '_status', true ) ?: 'pending',
					'due_date' => (string) get_post_meta( $post->ID, '_installment_' . $n . '_due_date', true ) ?: null,
					'paid_at'  => (string) get_post_meta( $post->ID, '_installment_' . $n . '_paid_at', true ) ?: null,
					'sent_at'  => (string) get_post_meta( $post->ID, '_installment_' . $n . '_sent_at', true ) ?: null,
				];
			}
		}

		$invoice['installment_plan']    = $plan;
		$invoice['installment_count']   = $count;
		$invoice['installments']        = $installments;
		$invoice['disable_installments'] = (bool) get_post_meta( $post->ID, '_disable_installments', true );

		return $invoice;
	}

	/**
	 * Get person summary for invoice
	 *
	 * @param int $invoice_id The invoice post ID.
	 * @return array|null Person summary data or null if no valid person linked.
	 */
	private function get_invoice_person_summary( $invoice_id ) {
		$person_id = get_field( 'person', $invoice_id );

		if ( empty( $person_id ) ) {
			return null;
		}

		$person = get_post( $person_id );

		if ( ! $person || $person->post_type !== 'person' ) {
			return null;
		}

		return $this->format_person_summary( $person );
	}
}
