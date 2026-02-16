<?php
/**
 * Invoice Email Sender Service
 *
 * Handles sending invoices via email with configurable template and PDF attachment.
 * Uses WordPress wp_mail() function with finance configuration settings.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice Email Sender class
 */
class InvoiceEmailSender {

	/**
	 * Send invoice email with PDF attachment
	 *
	 * @param int $invoice_id The invoice post ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function send( int $invoice_id ) {
		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'invalid_invoice',
				__( 'Factuur niet gevonden.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Gather invoice data
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$person_id      = get_field( 'person', $invoice_id );
		$total_amount   = (float) get_field( 'total_amount', $invoice_id );
		$line_items     = get_field( 'line_items', $invoice_id );
		$payment_link   = get_field( 'payment_link', $invoice_id );
		$pdf_path       = get_field( 'pdf_path', $invoice_id );

		// Validate person exists
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error(
				'invalid_person',
				__( 'Persoon niet gevonden voor deze factuur.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Build person name
		$first_name = get_field( 'first_name', $person_id );
		$infix      = get_field( 'infix', $person_id );
		$last_name  = get_field( 'last_name', $person_id );
		$name_parts = array_filter( [ $first_name, $infix, $last_name ] );
		$person_name = implode( ' ', $name_parts );

		// Get person email
		$contact_info = get_field( 'contact_info', $person_id );
		$person_email = '';
		if ( $contact_info && is_array( $contact_info ) ) {
			foreach ( $contact_info as $contact ) {
				if ( isset( $contact['contact_type'] ) &&
					 ( $contact['contact_type'] === 'email' || $contact['contact_type'] === 'Email' ) ) {
					$person_email = $contact['contact_value'] ?? '';
					break;
				}
			}
		}

		if ( empty( $person_email ) ) {
			return new \WP_Error(
				'no_email',
				__( 'Geen e-mailadres gevonden voor dit lid.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Get finance configuration
		$config = new FinanceConfig();
		$template = $config->get_email_template();
		$org_name = $config->get_org_name();

		// Build discipline cases list for email
		$tuchtzaken_lijst = '';
		if ( $line_items && is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				if ( ! empty( $item['discipline_case'] ) ) {
					$case_id = $item['discipline_case'];
					$match_desc = get_field( 'match_description', $case_id );
					$sanction_desc = get_field( 'sanction_description', $case_id );
					$amount = (float) ( $item['amount'] ?? 0 );
					$formatted_amount = '€ ' . number_format( $amount, 2, ',', '.' );

					$tuchtzaken_lijst .= '- ' . $match_desc . ': ' . $sanction_desc . ' — ' . $formatted_amount . "\n";
				} elseif ( ! empty( $item['description'] ) ) {
					// Fallback to description if no discipline case linked
					$amount = (float) ( $item['amount'] ?? 0 );
					$formatted_amount = '€ ' . number_format( $amount, 2, ',', '.' );
					$tuchtzaken_lijst .= '- ' . $item['description'] . ' — ' . $formatted_amount . "\n";
				}
			}
		}

		// Format total amount in Dutch currency format
		$formatted_total = '€ ' . number_format( $total_amount, 2, ',', '.' );

		// Format payment link or fallback text
		$betaallink_text = ! empty( $payment_link )
			? $payment_link
			: 'Neem contact op voor betaalinformatie.';

		// Replace template variables
		$email_body = str_replace(
			[
				'{naam}',
				'{factuur_nummer}',
				'{tuchtzaken_lijst}',
				'{totaal_bedrag}',
				'{betaallink}',
				'{organisatie_naam}',
			],
			[
				$person_name,
				$invoice_number,
				trim( $tuchtzaken_lijst ),
				$formatted_total,
				$betaallink_text,
				$org_name,
			],
			$template
		);

		// Build email subject
		$subject = 'Factuur ' . $invoice_number . ' - ' . $org_name;

		// Build headers with From address
		$contact_email = $config->get_contact_email();
		$headers = [
			'From: ' . $org_name . ' <' . $contact_email . '>',
		];

		// Build PDF attachment path if PDF exists
		$attachments = [];
		if ( ! empty( $pdf_path ) ) {
			$upload_dir = wp_upload_dir();
			$full_path = $upload_dir['basedir'] . '/' . $pdf_path;

			if ( file_exists( $full_path ) ) {
				$attachments[] = $full_path;
			}
		}

		// Send email via wp_mail
		$result = wp_mail( $person_email, $subject, $email_body, $headers, $attachments );

		if ( ! $result ) {
			return new \WP_Error(
				'email_send_failed',
				__( 'Verzenden van e-mail is mislukt.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		return true;
	}
}
