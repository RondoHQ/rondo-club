<?php
/**
 * REST API Endpoints for Invoice Custom Post Type
 *
 * Provides CRUD operations for invoices (facturen) via the REST API at rondo/v1/invoices.
 * All endpoints require the 'financieel' capability.
 */

namespace Rondo\REST;

use Rondo\Finance\InvoiceNumbering;
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

		// Filter by person if provided
		$person_id = $request->get_param( 'person_id' );
		if ( ! empty( $person_id ) ) {
			$args['meta_query'] = [
				[
					'key'     => 'person',
					'value'   => $person_id,
					'compare' => '=',
				],
			];
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
		$person_id  = $request->get_param( 'person_id' );
		$line_items = $request->get_param( 'line_items' );

		// Validate required fields
		if ( empty( $person_id ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'Person ID is required.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'person_id' => 'Person ID is required' ] ]
			);
		}

		if ( empty( $line_items ) || ! is_array( $line_items ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'Line items are required.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'line_items' => 'Line items are required' ] ]
			);
		}

		// Validate person exists
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Invalid person ID.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'person_id' => 'Person does not exist' ] ]
			);
		}

		// Generate invoice number
		$invoice_number = InvoiceNumbering::generate_next();

		// Calculate total amount
		$total_amount = 0;
		foreach ( $line_items as $item ) {
			$total_amount += (float) ( $item['amount'] ?? 0 );
		}

		// Create the invoice post
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

		// Set ACF fields
		update_field( 'invoice_number', $invoice_number, $post_id );
		update_field( 'person', $person_id, $post_id );
		update_field( 'status', 'draft', $post_id );
		update_field( 'total_amount', $total_amount, $post_id );

		// Set line items repeater
		$rows = [];
		foreach ( $line_items as $item ) {
			$rows[] = [
				'discipline_case' => $item['discipline_case_id'] ?? null,
				'description'     => sanitize_text_field( $item['description'] ?? '' ),
				'amount'          => (float) ( $item['amount'] ?? 0 ),
			];
		}
		update_field( 'line_items', $rows, $post_id );

		// Return the created invoice
		$invoice = get_post( $post_id );
		return rest_ensure_response( $this->format_invoice_detail( $invoice ) );
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
			update_field( 'sent_date', $sent_date, $invoice_id );

			// Calculate due date
			$finance_config   = new FinanceConfig();
			$payment_term_days = $finance_config->get_payment_term_days();
			$due_date         = date( 'Ymd', strtotime( "+{$payment_term_days} days" ) );
			update_field( 'due_date', $due_date, $invoice_id );
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
			$due_date = get_field( 'due_date', $invoice->ID );

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
		return [
			'id'             => $post->ID,
			'invoice_number' => get_field( 'invoice_number', $post->ID ),
			'person'         => $this->get_invoice_person_summary( $post->ID ),
			'total_amount'   => (float) get_field( 'total_amount', $post->ID ),
			'status'         => get_field( 'status', $post->ID ),
			'post_status'    => $post->post_status,
			'sent_date'      => get_field( 'sent_date', $post->ID ) ?: null,
			'due_date'       => get_field( 'due_date', $post->ID ) ?: null,
			'payment_link'   => get_field( 'payment_link', $post->ID ) ?: null,
			'created'        => $post->post_date,
		];
	}

	/**
	 * Format an invoice for detail response (single view)
	 *
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

		$invoice['line_items'] = $formatted_items;
		$invoice['pdf_path']   = get_field( 'pdf_path', $post->ID ) ?: null;

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
