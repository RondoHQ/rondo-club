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
use Rondo\Finance\RabobankOAuth;
use Rondo\Finance\RabobankPayment;
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

		// Create payment link + QR code BEFORE PDF generation so QR is embedded in PDF
		$oauth = new RabobankOAuth();
		if ( $oauth->is_connected() ) {
			$payment = new RabobankPayment( $oauth );
			$payment_result = $payment->create_payment_request( $invoice_id );
			// Log error but continue - payment link is non-blocking
			if ( is_wp_error( $payment_result ) ) {
				error_log( 'Rabobank payment link creation failed for invoice ' . $invoice_id . ': ' . $payment_result->get_error_message() );
			}
		}

		// Always (re)generate PDF so QR code and payment link are included
		$pdf_result = InvoicePdfGenerator::generate( $invoice_id );
		if ( is_wp_error( $pdf_result ) ) {
			return $pdf_result;
		}

		// Send email via InvoiceEmailSender
		$email_result = InvoiceEmailSender::send( $invoice_id );
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
		update_field( 'sent_date', $sent_date, $invoice_id );

		// Calculate and set due_date
		$config = new FinanceConfig();
		$payment_term_days = $config->get_payment_term_days();
		$due_date = date( 'Ymd', strtotime( "+{$payment_term_days} days" ) );
		update_field( 'due_date', $due_date, $invoice_id );

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

		// Send email via InvoiceEmailSender
		$email_result = InvoiceEmailSender::send( $invoice_id );
		if ( is_wp_error( $email_result ) ) {
			return $email_result;
		}

		// Return updated invoice detail
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

		$invoice['line_items']    = $formatted_items;
		$invoice['pdf_path']      = get_field( 'pdf_path', $post->ID ) ?: null;
		$invoice['qr_code_path']  = get_field( 'qr_code_path', $post->ID ) ?: null;

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
