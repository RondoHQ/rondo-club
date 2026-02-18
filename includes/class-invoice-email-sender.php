<?php
/**
 * Invoice Email Sender Service
 *
 * Handles sending invoices via HTML email with configurable template, PDF attachment,
 * and inline CID-embedded QR code. Uses WordPress wp_mail() function with finance
 * configuration settings.
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
	 * Send invoice email with PDF attachment and inline QR code
	 *
	 * @param int   $invoice_id The invoice post ID.
	 * @param array $options    Optional. Associative array of options:
	 *                          - override_email (string) Send to this address instead of the person's email.
	 *                          - skip_bcc (bool)         When true, omit the BCC header.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function send( int $invoice_id, array $options = [] ) {
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

		// In test mode, redirect email to override address
		$recipient_email = $options['override_email'] ?? $person_email;

		// Get finance configuration
		$config = new FinanceConfig();
		$template = $config->get_email_template();
		$org_name = $config->get_org_name();

		// Build discipline cases list as HTML
		$tuchtzaken_lijst = '';
		if ( $line_items && is_array( $line_items ) ) {
			$list_items = [];
			foreach ( $line_items as $item ) {
				if ( ! empty( $item['discipline_case'] ) ) {
					$case_id = $item['discipline_case'];
					$match_desc = esc_html( get_field( 'match_description', $case_id ) );
					$sanction_desc = esc_html( get_field( 'sanction_description', $case_id ) );
					$amount = (float) ( $item['amount'] ?? 0 );
					$formatted_amount = '&euro; ' . number_format( $amount, 2, ',', '.' );

					$list_items[] = '<li>' . $match_desc . ': ' . $sanction_desc . ' &mdash; ' . $formatted_amount . '</li>';
				} elseif ( ! empty( $item['description'] ) ) {
					// Fallback to description if no discipline case linked
					$amount = (float) ( $item['amount'] ?? 0 );
					$formatted_amount = '&euro; ' . number_format( $amount, 2, ',', '.' );
					$list_items[] = '<li>' . esc_html( $item['description'] ) . ' &mdash; ' . $formatted_amount . '</li>';
				}
			}
			if ( ! empty( $list_items ) ) {
				$tuchtzaken_lijst = '<ul style="margin:0;padding-left:20px;">' . implode( '', $list_items ) . '</ul>';
			}
		}

		// Format total amount in Dutch currency format
		$formatted_total = '&euro; ' . number_format( $total_amount, 2, ',', '.' );

		// Format payment link as HTML anchor or fallback text
		$betaallink_text = ! empty( $payment_link )
			? '<a href="' . esc_url( $payment_link ) . '" style="color:#0891b2;text-decoration:underline;">' . esc_html( $payment_link ) . '</a>'
			: 'Neem contact op voor betaalinformatie.';

		// Build inline QR code HTML via CID embedding
		$qr_code_html = '';
		$qr_cid       = '';
		$qr_data      = '';
		$upload_dir    = wp_upload_dir();
		$qr_code_path  = get_field( 'qr_code_path', $invoice_id );

		if ( ! empty( $qr_code_path ) ) {
			$qr_full_path = $upload_dir['basedir'] . '/' . $qr_code_path;
			if ( file_exists( $qr_full_path ) ) {
				$qr_data = file_get_contents( $qr_full_path );
				$qr_cid  = 'qr-' . sanitize_file_name( $invoice_number ) . '@rondo';
				$qr_code_html = '<img src="cid:' . $qr_cid . '" alt="QR Code betaallink" width="200" style="display:block;" />';
			}
		}

		// Replace template variables
		$email_body = str_replace(
			[
				'{naam}',
				'{factuur_nummer}',
				'{tuchtzaken_lijst}',
				'{totaal_bedrag}',
				'{betaallink}',
				'{qr_code}',
				'{organisatie_naam}',
			],
			[
				esc_html( $person_name ),
				esc_html( $invoice_number ),
				$tuchtzaken_lijst,
				$formatted_total,
				$betaallink_text,
				$qr_code_html,
				esc_html( $org_name ),
			],
			$template
		);

		// Build email subject
		$subject = 'Factuur ' . $invoice_number . ' - ' . $org_name;

		// In test mode, prefix subject to make test emails clearly identifiable
		if ( ! empty( $options['override_email'] ) || ! empty( $options['skip_bcc'] ) ) {
			$subject = '[TEST] ' . $subject;
		}

		// Build headers with From address and HTML content type
		$contact_email = $config->get_contact_email();
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $org_name . ' <' . $contact_email . '>',
		];

		// Add BCC if configured and not suppressed (e.g. in test mode)
		if ( empty( $options['skip_bcc'] ) ) {
			$bcc_email = $config->get_bcc_email();
			if ( ! empty( $bcc_email ) ) {
				$headers[] = 'Bcc: ' . $bcc_email;
			}
		}

		// Build attachments (PDF only — QR code is embedded inline)
		$attachments = [];

		// Attach PDF if exists
		if ( ! empty( $pdf_path ) ) {
			$full_path = $upload_dir['basedir'] . '/' . $pdf_path;
			if ( file_exists( $full_path ) ) {
				$attachments[] = $full_path;
			}
		}

		// Add inline QR code via phpmailer_init hook (CID embedding)
		$phpmailer_hook = null;
		if ( ! empty( $qr_data ) && ! empty( $qr_cid ) ) {
			$phpmailer_hook = function ( $phpmailer ) use ( $qr_data, $qr_cid ) {
				$phpmailer->addStringEmbeddedImage( $qr_data, $qr_cid, 'qr-code.png', 'base64', 'image/png' );
			};
			add_action( 'phpmailer_init', $phpmailer_hook );
		}

		// Send email via wp_mail
		$result = wp_mail( $recipient_email, $subject, $email_body, $headers, $attachments );

		// Remove the phpmailer_init hook to avoid affecting other emails
		if ( $phpmailer_hook ) {
			remove_action( 'phpmailer_init', $phpmailer_hook );
		}

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
