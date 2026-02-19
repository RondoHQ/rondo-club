<?php
/**
 * Bulk Invoice Creator
 *
 * Handles async bulk membership invoice creation via WP-Cron batch processing.
 * Processes members in batches of 50 to avoid PHP timeouts on large clubs.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Fees\MembershipFees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bulk Invoice Creator class.
 *
 * Manages a single job state stored in WordPress options.
 * One active job can run at a time; a new start_job() call while running returns an error.
 */
class BulkInvoiceCreator {

	/**
	 * WP-Cron hook name for batch processing.
	 */
	const CRON_HOOK = 'rondo_bulk_invoice_batch';

	/**
	 * WordPress option key for job state storage.
	 */
	const JOB_OPTION = 'rondo_bulk_invoice_job';

	/**
	 * Number of invoices to create per cron batch.
	 */
	const BATCH_SIZE = 50;

	/**
	 * Constructor — register cron hook.
	 */
	public function __construct() {
		add_action( self::CRON_HOOK, [ $this, 'run_batch' ] );
	}

	/**
	 * Start a bulk invoice creation job.
	 *
	 * Queries all published persons, saves job state, and schedules the first batch.
	 * Returns immediately — actual invoice creation happens in cron batches.
	 *
	 * @param string $season Season key in "YYYY-YYYY" format (e.g., "2025-2026").
	 * @return array|\WP_Error Job state array (without person_ids) on success, WP_Error if already running.
	 */
	public static function start_job( string $season ) {
		// Check if a job is already running.
		$existing = get_option( self::JOB_OPTION, [] );
		if ( ! empty( $existing ) && isset( $existing['status'] ) && $existing['status'] === 'running' ) {
			return new \WP_Error(
				'job_already_running',
				'Er is al een bulkfactuur job actief.',
				[ 'status' => 409 ]
			);
		}

		// Query all published person IDs.
		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		$person_ids = array_map( 'intval', $query->posts );
		$total      = count( $person_ids );

		// Build job state.
		$state = [
			'season'     => $season,
			'status'     => 'running',
			'total'      => $total,
			'offset'     => 0,
			'created'    => 0,
			'skipped'    => 0,
			'errors'     => 0,
			'started_at' => current_time( 'Y-m-d H:i:s' ),
			'finished_at' => null,
			'person_ids' => $person_ids,
		];

		// Save state (autoload=false — this can be large).
		update_option( self::JOB_OPTION, $state, false );

		// Schedule first batch in 2 seconds.
		wp_schedule_single_event( time() + 2, self::CRON_HOOK );

		// Return state without person_ids (keep response small).
		$response = $state;
		unset( $response['person_ids'] );

		return $response;
	}

	/**
	 * Run one batch of invoice creation.
	 *
	 * Called by WP-Cron. Reads job state, processes BATCH_SIZE persons,
	 * updates counters, and reschedules until all persons are processed.
	 */
	public function run_batch(): void {
		$state = get_option( self::JOB_OPTION, [] );

		if ( empty( $state ) || ( $state['status'] ?? '' ) !== 'running' ) {
			return;
		}

		$person_ids = $state['person_ids'] ?? [];
		$offset     = (int) ( $state['offset'] ?? 0 );
		$total      = (int) ( $state['total'] ?? 0 );
		$season     = $state['season'] ?? '';

		// Slice the batch.
		$batch = array_slice( $person_ids, $offset, self::BATCH_SIZE );

		foreach ( $batch as $person_id ) {
			$result = $this->create_membership_invoice( $person_id, $season );

			if ( $result === 'created' ) {
				$state['created']++;
			} elseif ( $result === 'skipped' ) {
				$state['skipped']++;
			} else {
				$state['errors']++;
			}
		}

		// Advance offset.
		$new_offset        = $offset + count( $batch );
		$state['offset']   = $new_offset;

		if ( $new_offset >= $total ) {
			// All persons processed.
			$state['status']      = 'done';
			$state['finished_at'] = current_time( 'Y-m-d H:i:s' );
		} else {
			// Schedule next batch.
			wp_schedule_single_event( time() + 2, self::CRON_HOOK );
		}

		update_option( self::JOB_OPTION, $state, false );
	}

	/**
	 * Create a membership invoice for a single person.
	 *
	 * Checks fee eligibility, idempotency, then creates a concept invoice with
	 * ACF fields, line items, season meta, and payment token.
	 *
	 * @param int    $person_id Person post ID.
	 * @param string $season    Season key in "YYYY-YYYY" format.
	 * @return string 'created', 'skipped', or 'error'.
	 */
	public function create_membership_invoice( int $person_id, string $season ): string {
		$fees = new MembershipFees();

		// Get fee for this person.
		$fee_data = $fees->get_fee_for_person_cached( $person_id, $season );

		if ( $fee_data === null ) {
			return 'skipped';
		}

		// Check former member eligibility.
		$is_former = ( get_field( 'former_member', $person_id ) == true );
		if ( $is_former ) {
			$lid_sinds = get_field( 'lid-sinds', $person_id );
			if ( empty( $lid_sinds ) ) {
				return 'skipped';
			}
			$season_end_year = (int) substr( $season, 5, 4 );
			$season_end_ts   = strtotime( $season_end_year . '-07-01' );
			$lid_sinds_ts    = strtotime( $lid_sinds );
			if ( $lid_sinds_ts === false || $lid_sinds_ts >= $season_end_ts ) {
				return 'skipped';
			}
		}

		// Skip zero-fee members.
		$final_fee = $fee_data['final_fee'] ?? 0;
		if ( $final_fee <= 0 ) {
			return 'skipped';
		}

		// Idempotency: check if invoice already exists for this person+season.
		$existing = get_posts(
			[
				'post_type'      => 'rondo_invoice',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'suppress_filters' => true,
				'meta_query'     => [
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

		if ( ! empty( $existing ) ) {
			return 'skipped';
		}

		// Generate invoice number.
		$invoice_number = InvoiceNumbering::generate_next( 'membership' );

		// Create the invoice post.
		$post_id = wp_insert_post(
			[
				'post_type'   => 'rondo_invoice',
				'post_title'  => $invoice_number,
				'post_status' => 'rondo_draft',
				'post_author' => 0,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 'error';
		}

		// Set ACF fields.
		update_field( 'invoice_number', $invoice_number, $post_id );
		update_field( 'person', $person_id, $post_id );
		update_field( 'status', 'draft', $post_id );
		update_field( 'invoice_type', 'membership', $post_id );
		update_field( 'total_amount', $final_fee, $post_id );

		// Build line items with discount breakdown.
		$line_items = [
			[
				'discipline_case' => null,
				'description'     => 'Contributie ' . $season,
				'amount'          => $fee_data['base_fee'],
			],
		];

		// Family discount line (only if applicable).
		$family_discount_amount = $fee_data['family_discount_amount'] ?? 0;
		if ( $family_discount_amount > 0 ) {
			$family_rate_pct = round( ( $fee_data['family_discount_rate'] ?? 0 ) * 100 );
			$line_items[] = [
				'discipline_case' => null,
				'description'     => 'Gezinskorting (' . $family_rate_pct . '%)',
				'amount'          => -$family_discount_amount,
			];
		}

		// Pro-rata discount line (only if applicable).
		$prorata_percentage = $fee_data['prorata_percentage'] ?? 1.0;
		if ( $prorata_percentage < 1.0 ) {
			$prorata_discount_pct = round( ( 1 - $prorata_percentage ) * 100 );
			$fee_after_discount   = $fee_data['fee_after_discount'] ?? $fee_data['base_fee'];
			$prorata_discount_amt = round( $fee_after_discount - $final_fee, 2 );
			$line_items[] = [
				'discipline_case' => null,
				'description'     => 'Instapkorting (' . $prorata_discount_pct . '%)',
				'amount'          => -$prorata_discount_amt,
			];
		}

		update_field( 'line_items', $line_items, $post_id );

		// Store season meta.
		update_post_meta( $post_id, '_invoice_season', $season );

		// Default: disable installments for bulk-created membership invoices.
		update_post_meta( $post_id, '_disable_installments', '1' );

		// Only generate betaling page token if installments are enabled.
		// When disabled, send_invoice() creates a direct Mollie link instead.
		if ( ! get_post_meta( $post_id, '_disable_installments', true ) ) {
			PublicPaymentPage::generate_token( $post_id );
		}

		return 'created';
	}

	/**
	 * Get current job status (without the large person_ids array).
	 *
	 * @return array Job state array, or empty array if no job has been run.
	 */
	public static function get_job_status(): array {
		$state = get_option( self::JOB_OPTION, [] );

		if ( empty( $state ) ) {
			return [];
		}

		// Remove person_ids to keep response small.
		$sanitized = $state;
		unset( $sanitized['person_ids'] );

		return $sanitized;
	}
}
