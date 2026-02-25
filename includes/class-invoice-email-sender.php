<?php
/**
 * Invoice Email Sender Service
 *
 * Handles sending invoices via HTML email with configurable template, PDF attachment,
 * and inline QR code image. Uses WordPress wp_mail() function with finance
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
	 * Send invoice email with PDF attachment and QR code image
	 *
	 * @param int   $invoice_id The invoice post ID.
	 * @param array $options    Optional. Associative array of options:
	 *                          - override_email (string) Send to this address instead of the person's email.
	 *                          - skip_bcc (bool)         When true, omit the BCC header.
	 *                          - template (string)       Custom email template HTML. Defaults to discipline template from FinanceConfig.
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

		$person = $person_id ? get_post( $person_id ) : null;
		$first_name = '';
		$person_name = (string) get_post_meta( $invoice_id, '_customer_name', true );
		$person_email = '';

		if ( $person && $person->post_type === 'person' ) {
			$first_name = (string) get_field( 'first_name', $person_id );
			$infix      = (string) get_field( 'infix', $person_id );
			$last_name  = (string) get_field( 'last_name', $person_id );
			$name_parts = array_filter( [ $first_name, $infix, $last_name ] );
			$person_name = implode( ' ', $name_parts );

			$contact_info = get_field( 'contact_info', $person_id );
			if ( $contact_info && is_array( $contact_info ) ) {
				foreach ( $contact_info as $contact ) {
					if ( isset( $contact['contact_type'] ) &&
						 ( $contact['contact_type'] === 'email' || $contact['contact_type'] === 'Email' ) ) {
						$person_email = $contact['contact_value'] ?? '';
						break;
					}
				}
			}
		}

		if ( empty( $person_name ) ) {
			$person_name = __( 'Relatie', 'rondo' );
		}

		// In test mode, redirect email to override address
		$recipient_email = $options['override_email'] ?? $person_email;
		if ( empty( $recipient_email ) ) {
			return new \WP_Error(
				'no_email',
				__( 'Geen e-mailadres gevonden voor deze factuur.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Get finance configuration
		$config = new FinanceConfig();
		$template = $options['template'] ?? $config->get_email_template();
		$org_name = $config->get_org_name();

		// Build discipline cases list as HTML table
		$tuchtzaken_lijst = '';
		if ( $line_items && is_array( $line_items ) ) {
			$table_rows = [];
			$row_index  = 0;
			foreach ( $line_items as $item ) {
				$row_bg = ( $row_index % 2 === 1 ) ? ' background-color:#f9fafb;' : '';
				$td_style = 'padding:8px 12px;border-bottom:1px solid #e5e7eb;';

				if ( ! empty( $item['discipline_case'] ) ) {
					$case_id    = $item['discipline_case'];
					$match_date = get_field( 'match_date', $case_id );
					$match_desc = esc_html( get_field( 'match_description', $case_id ) ?: '-' );
					$amount     = (float) ( $item['amount'] ?? 0 );
					$formatted_amount = '&euro; ' . number_format( $amount, 2, ',', '.' );

					// Format date from Ymd to d-m-Y
					$formatted_date = '-';
					if ( ! empty( $match_date ) ) {
						$timestamp = strtotime( $match_date );
						if ( $timestamp !== false ) {
							$formatted_date = date( 'd-m-Y', $timestamp );
						}
					}

					// Derive card type from charge_codes field
					$charge_codes = get_field( 'charge_codes', $case_id );
					$card_text    = '-';
					if ( ! empty( $charge_codes ) ) {
						$card_text = str_ends_with( $charge_codes, '-1' ) ? 'Geel' : 'Rood';
						// Append schorsing for uitsluiting sanctions
						$sanction_desc = get_field( 'sanction_description', $case_id );
						if ( ! empty( $sanction_desc ) && strcasecmp( $sanction_desc, 'uitsluiting' ) === 0 ) {
							$card_text .= ' en schorsing';
						}
					}

					$table_rows[] = '<tr style="' . $row_bg . '">'
						. '<td style="' . $td_style . '">' . esc_html( $formatted_date ) . '</td>'
						. '<td style="' . $td_style . '">' . $match_desc . '</td>'
						. '<td style="' . $td_style . '">' . esc_html( $card_text ) . '</td>'
						. '<td style="' . $td_style . 'text-align:right;">' . $formatted_amount . '</td>'
						. '</tr>';
				} elseif ( ! empty( $item['description'] ) ) {
					// Fallback row for non-discipline items: description spans first 3 columns
					$amount = (float) ( $item['amount'] ?? 0 );
					if ( $amount < 0 ) {
						$formatted_amount = '- &euro; ' . number_format( abs( $amount ), 2, ',', '.' );
					} else {
						$formatted_amount = '&euro; ' . number_format( $amount, 2, ',', '.' );
					}
					$table_rows[] = '<tr style="' . $row_bg . '">'
						. '<td colspan="3" style="' . $td_style . '">' . esc_html( $item['description'] ) . '</td>'
						. '<td style="' . $td_style . 'text-align:right;">' . $formatted_amount . '</td>'
						. '</tr>';
				}
				$row_index++;
			}
			if ( ! empty( $table_rows ) ) {
				$th_style = 'padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;';
				$tuchtzaken_lijst = '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
					. '<thead><tr style="background-color:#f3f4f6;">'
					. '<th style="' . $th_style . '">Datum</th>'
					. '<th style="' . $th_style . '">Wedstrijd</th>'
					. '<th style="' . $th_style . '">Kaart</th>'
					. '<th style="' . $th_style . 'text-align:right;">Bedrag</th>'
					. '</tr></thead>'
					. '<tbody>' . implode( '', $table_rows ) . '</tbody>'
					. '</table>';
			}
		}

		// Format total amount in Dutch currency format
		$formatted_total = '&euro; ' . number_format( $total_amount, 2, ',', '.' );

		// Format payment link as HTML anchor or fallback text
		$betaallink_text = ! empty( $payment_link )
			? '<a href="' . esc_url( $payment_link ) . '" style="color:#0891b2;text-decoration:underline;">' . esc_html( $payment_link ) . '</a>'
			: 'Neem contact op voor betaalinformatie.';

		// Build inline QR code HTML via public URL (CID images are blocked by most email clients)
		$qr_code_html = '';
		$upload_dir    = wp_upload_dir();
		$qr_code_path  = get_field( 'qr_code_path', $invoice_id );

		if ( ! empty( $qr_code_path ) ) {
			$qr_full_path = $upload_dir['basedir'] . '/' . $qr_code_path;
			if ( file_exists( $qr_full_path ) ) {
				$qr_url = $upload_dir['baseurl'] . '/' . $qr_code_path;
				$qr_code_html = '<img src="' . esc_url( $qr_url ) . '" alt="QR Code betaallink" width="200" style="display:block;" />';
			}
		}

		// Replace template variables
		$email_body = str_replace(
			[
				'{naam}',
				'{voornaam}',
				'{factuur_nummer}',
				'{tuchtzaken_lijst}',
				'{totaal_bedrag}',
				'{betaallink}',
				'{qr_code}',
				'{organisatie_naam}',
			],
			[
				esc_html( $person_name ),
				esc_html( $first_name ),
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
		$subject = (string) ( $options['subject'] ?? '' );
		if ( '' === trim( $subject ) ) {
			$subject = 'Factuur ' . $invoice_number . ' - ' . $org_name;
		}

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

		// Send email via wp_mail
		$result = wp_mail( $recipient_email, $subject, $email_body, $headers, $attachments );

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
