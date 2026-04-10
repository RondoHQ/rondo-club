<?php
/**
 * Invoice PDF Generator Service
 *
 * Generates PDF documents for invoices using mPDF library.
 * Builds HTML template with club branding, member details, discipline case line items,
 * and payment instructions, then renders to PDF and stores in WordPress uploads.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;
use Mpdf\WatermarkText;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice PDF Generator class
 */
class InvoicePdfGenerator {

	/**
	 * Generate PDF for an invoice
	 *
	 * @param int $invoice_id The invoice post ID.
	 * @return string|\WP_Error The relative PDF path on success, WP_Error on failure.
	 */
	public static function generate( int $invoice_id ) {
		// Validate invoice exists
		$invoice = get_post( $invoice_id );
		if ( ! $invoice || $invoice->post_type !== 'rondo_invoice' ) {
			return new \WP_Error(
				'invalid_invoice',
				__( 'Invoice not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		// Gather invoice data
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$person_id      = get_field( 'person', $invoice_id );
		$total_amount   = (float) get_field( 'total_amount', $invoice_id );
		$line_items     = get_field( 'line_items', $invoice_id );
		$sent_date      = get_field( 'sent_date', $invoice_id );
		$due_date       = get_field( 'due_date', $invoice_id );
		$invoice_type   = get_field( 'invoice_type', $invoice_id ) ?: 'discipline';
		$payment_link   = get_field( 'payment_link', $invoice_id );
		$invoice_status = get_field( 'status', $invoice_id ) ?: str_replace( 'rondo_', '', (string) $invoice->post_status );
		$is_paid        = ( $invoice_status === 'paid' || $invoice->post_status === 'rondo_paid' );
		$invoice_kind   = (string) get_post_meta( $invoice_id, '_invoice_kind', true ) ?: 'normal';
		$is_credit      = ( $invoice_kind === 'credit' );

		// Use current date as invoice date if not sent yet (draft preview)
		if ( empty( $sent_date ) ) {
			$sent_date = current_time( 'Ymd' );
		}

		// Calculate due date if not set
		if ( empty( $due_date ) ) {
			$finance_config    = new FinanceConfig();
			$payment_term_days = $finance_config->get_payment_term_days();
			$invoice_timestamp = strtotime( substr( $sent_date, 0, 4 ) . '-' . substr( $sent_date, 4, 2 ) . '-' . substr( $sent_date, 6, 2 ) );
			$due_date          = date( 'Ymd', strtotime( "+{$payment_term_days} days", $invoice_timestamp ) );
		}

		// Gather customer data (member-linked and/or custom invoice data)
		$person_name       = (string) get_post_meta( $invoice_id, '_customer_name', true );
		$person_attention  = (string) get_post_meta( $invoice_id, '_customer_attention', true );
		$person_street     = '';
		$person_city       = '';
		$person_email      = (string) get_post_meta( $invoice_id, '_customer_email', true );
		$address_text      = (string) get_post_meta( $invoice_id, '_customer_address', true );
		$custom_fields_raw = (string) get_post_meta( $invoice_id, '_custom_fields', true );
		$custom_fields     = json_decode( $custom_fields_raw, true );

		$person = $person_id ? get_post( $person_id ) : null;
		if ( $person && $person->post_type === 'person' ) {
			$first_name  = get_field( 'first_name', $person_id );
			$infix       = get_field( 'infix', $person_id );
			$last_name   = get_field( 'last_name', $person_id );
			$name_parts  = array_filter( [ $first_name, $infix, $last_name ] );
			$member_name = implode( ' ', $name_parts );
			if ( $person_name === '' ) {
				$person_name = $member_name;
			}

			$addresses = get_field( 'addresses', $person_id );
			if ( $addresses && is_array( $addresses ) && count( $addresses ) > 0 ) {
				$format_address = function ( array $addr ): array {
					return [
						'street' => trim( ( $addr['street_name'] ?? '' ) . ' ' . ( $addr['house_number'] ?? '' ) . ' ' . ( $addr['house_number_addition'] ?? '' ) ),
						'city'   => $addr['city'] ?? '',
					];
				};

				$selected_address = null;
				foreach ( $addresses as $addr ) {
					if ( strcasecmp( $addr['address_label'] ?? '', 'Factuur' ) === 0 ) {
						$selected_address = $addr;
						break;
					}
				}
				if ( $selected_address === null ) {
					$selected_address = $addresses[0];
				}

				$formatted     = $format_address( $selected_address );
				$person_street = $formatted['street'];
				$person_city   = $formatted['city'];
			}

			if ( trim( $person_email ) === '' ) {
				$person_email = (string) get_field( 'email_1', $person_id );
			}
			if ( trim( $person_email ) === '' ) {
				$person_email = (string) get_field( 'email_2', $person_id );
			}
		}

		if ( trim( $address_text ) !== '' ) {
			$address_lines = preg_split( '/\r\n|\r|\n/', $address_text );
			$person_street = $address_lines[0] ?? '';
			$person_city   = implode( ' ', array_filter( array_slice( $address_lines, 1 ) ) );
		}
		if ( $person_name === '' ) {
			$person_name = __( 'Relatie', 'rondo' );
		}

		// Gather finance settings
		$finance_config            = new FinanceConfig();
		$org_name                  = $finance_config->get_org_name();
		$org_address               = $finance_config->get_org_address();
		$contact_email             = $finance_config->get_contact_email();
		$payment_account           = self::get_invoice_payment_account( $invoice_id, $finance_config );
		$iban                      = (string) ( $payment_account['iban'] ?? '' );
		$account_holder            = (string) ( $payment_account['account_holder'] ?? $org_name );
		$payment_clause            = $finance_config->get_payment_clause();
		$membership_payment_clause = $finance_config->get_membership_payment_clause();

		// Get accent color with fallback
		$accent_color = $finance_config->get_accent_color();
		if ( empty( $accent_color ) ) {
			$accent_color = '#0891b2';
		}

		// Get club logo with fallback to Rondo logo
		$club_logo_id = $finance_config->get_club_logo_id();
		if ( $club_logo_id > 0 ) {
			$logo_path   = get_attached_file( $club_logo_id );
			$logo_exists = $logo_path && file_exists( $logo_path );
		} else {
			$logo_path   = get_template_directory() . '/public/icons/rondo-logo.png';
			$logo_exists = file_exists( $logo_path );
		}

		// Get QR code path if available
		$qr_code_path    = get_field( 'qr_code_path', $invoice_id );
		$qr_code_abspath = null;
		if ( ! empty( $qr_code_path ) ) {
			$upload_dir   = wp_upload_dir();
			$qr_full_path = $upload_dir['basedir'] . '/' . $qr_code_path;
			if ( file_exists( $qr_full_path ) ) {
				$qr_code_abspath = $qr_full_path;
			}
		}
		if ( $is_paid ) {
			// Do not show payment QR for already-paid invoices.
			$qr_code_abspath = null;
		}

		// Build HTML template
		$html = self::build_html(
			$invoice_number,
			$sent_date,
			$due_date,
			$person_name,
			$person_attention,
			$person_street,
			$person_city,
			$person_email,
			is_array( $custom_fields ) ? $custom_fields : [],
			$line_items,
			$total_amount,
			$org_name,
			$org_address,
			$contact_email,
			$iban,
			$account_holder,
			$payment_clause,
			$logo_exists ? $logo_path : null,
			$qr_code_abspath,
			$accent_color,
			$invoice_type,
			$payment_link,
			$membership_payment_clause,
			$is_paid
		);

		// Generate PDF with mPDF
		try {
			$mpdf = new \Mpdf\Mpdf(
				[
					'mode'              => 'utf-8',
					'format'            => 'A4',
					'default_font_size' => 10,
					'default_font'      => 'dejavusans',
					'margin_left'       => 20,
					'margin_right'      => 20,
					'margin_top'        => 15,
					'margin_bottom'     => 15,
				]
			);
			if ( $is_credit || $is_paid ) {
				// Use mPDF native watermark so angle and opacity are applied reliably.
				$watermark_text = $is_credit ? 'CREDIT' : 'BETAALD';
				$mpdf->SetWatermarkText( new WatermarkText( $watermark_text, 96, 45, $accent_color, 0.5 ) );
				$mpdf->showWatermarkText = true;
			}
			$mpdf->WriteHTML( $html );

			// Save PDF to WordPress uploads
			$upload_dir   = wp_upload_dir();
			$invoices_dir = $upload_dir['basedir'] . '/invoices';

			// Create invoices directory if it doesn't exist
			if ( ! file_exists( $invoices_dir ) ) {
				wp_mkdir_p( $invoices_dir );
			}

			$filename      = 'factuur-' . $invoice_number . '.pdf';
			$full_path     = $invoices_dir . '/' . $filename;
			$relative_path = 'invoices/' . $filename;

			$mpdf->Output( $full_path, \Mpdf\Output\Destination::FILE );

			// Store PDF path on invoice
			update_field( 'pdf_path', $relative_path, $invoice_id );

			return $relative_path;

		} catch ( \Mpdf\MpdfException $e ) {
			return new \WP_Error(
				'pdf_generation_failed',
				__( 'Failed to generate PDF: ', 'rondo' ) . $e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Build HTML template for invoice PDF
	 *
	 * @param string      $invoice_number Invoice number.
	 * @param string      $invoice_date   Invoice date (Ymd format).
	 * @param string      $due_date       Due date (Ymd format).
	 * @param string      $person_name    Person's full name.
	 * @param string      $person_attention Person attention line.
	 * @param string      $person_street  Person's street address.
	 * @param string      $person_city    Person's city.
	 * @param string      $person_email   Person's email.
	 * @param array       $custom_fields  Extra invoice metadata rows.
	 * @param array       $line_items     Invoice line items.
	 * @param float       $total_amount   Total invoice amount.
	 * @param string      $org_name       Organization name.
	 * @param string      $org_address    Organization address.
	 * @param string      $contact_email  Organization contact email.
	 * @param string      $iban           Bank IBAN.
	 * @param string      $account_holder Account holder name.
	 * @param string      $payment_clause Payment clause text.
	 * @param string|null $logo_path      Path to logo file (null if not exists).
	 * @param string|null $qr_code_path   Absolute path to QR code PNG (null if not exists).
	 * @param string      $accent_color   Accent color hex code (e.g., '#0891b2').
	 * @param string      $invoice_type            Invoice type: 'membership' or 'discipline'.
	 * @param string      $payment_link            Payment link URL (for membership invoices).
	 * @param string      $membership_payment_clause Membership payment clause text (shown below membership payment section).
	 * @param bool        $is_paid                 Whether the invoice is paid.
	 * @return string HTML content.
	 */
	private static function build_html(
		$invoice_number,
		$invoice_date,
		$due_date,
		$person_name,
		$person_attention,
		$person_street,
		$person_city,
		$person_email,
		$custom_fields,
		$line_items,
		$total_amount,
		$org_name,
		$org_address,
		$contact_email,
		$iban,
		$account_holder,
		$payment_clause,
		$logo_path,
		$qr_code_path = null,
		$accent_color = '#0891b2',
		$invoice_type = 'discipline',
		$payment_link = '',
		$membership_payment_clause = '',
		$is_paid = false
	) {
		$person_attention = preg_replace( '/^\s*(t\.?\s*a\.?\s*v\.?:?\s*)/i', '', (string) $person_attention );

		// Format dates
		$formatted_invoice_date = self::format_dutch_date( $invoice_date );
		$formatted_due_date     = self::format_dutch_date( $due_date );

		// Format currency
		$formatted_total = '€ ' . number_format( $total_amount, 2, ',', '.' );

		// Format IBAN with spaces for readability
		$formatted_iban = $iban;
		if ( strlen( $iban ) > 4 ) {
			$formatted_iban = chunk_split( $iban, 4, ' ' );
			$formatted_iban = trim( $formatted_iban );
		}

		// Build line items table rows
		$is_membership      = ( $invoice_type === 'membership' );
		$line_items_html    = '';
		$custom_fields_html = '';

		if ( is_array( $custom_fields ) ) {
			foreach ( array_slice( $custom_fields, 0, 2 ) as $field ) {
				$label = trim( (string) ( $field['label'] ?? '' ) );
				$text  = trim( (string) ( $field['text'] ?? '' ) );
				if ( $label === '' && $text === '' ) {
					continue;
				}

				if ( preg_match( '/^ter attentie van$/i', $label ) ) {
					$text = preg_replace( '/^\s*(t\.?\s*a\.?\s*v\.?:?\s*)/i', '', $text );
				}

				$custom_fields_html .= '
		<tr>
			<td class="label">' . esc_html( $label ?: __( 'Extra veld', 'rondo' ) ) . ':</td>
			<td>' . nl2br( esc_html( $text ) ) . '</td>
		</tr>';
			}
		}
		if ( $line_items && is_array( $line_items ) ) {
			foreach ( $line_items as $item ) {
				$amount = (float) ( $item['amount'] ?? 0 );
				if ( $amount < 0 ) {
					$formatted_amount = '- € ' . number_format( abs( $amount ), 2, ',', '.' );
				} else {
					$formatted_amount = '€ ' . number_format( $amount, 2, ',', '.' );
				}

				if ( $is_membership ) {
					// Membership: 2-column layout (description + amount).
					$description      = $item['description'] ?? '';
					$line_items_html .= '<tr>';
					$line_items_html .= '<td>' . esc_html( $description ) . '</td>';
					$line_items_html .= '<td style="text-align: right;">' . $formatted_amount . '</td>';
					$line_items_html .= '</tr>';
				} else {
					// Discipline: 4-column layout with card type and suspension.
					$description = '';
					$card_type   = '';
					$suspension  = '';

					if ( ! empty( $item['discipline_case'] ) ) {
						$case_id       = $item['discipline_case'];
						$match_desc    = get_field( 'match_description', $case_id );
						$sanction_desc = get_field( 'sanction_description', $case_id );
						$charge_codes  = get_field( 'charge_codes', $case_id );

						$description = $match_desc ?: ( $item['description'] ?? '' );

						if ( ! empty( $charge_codes ) ) {
							if ( substr( $charge_codes, -2 ) === '-1' ) {
								$card_type = '<span style="color: #ca8a04;">Geel</span>';
							} else {
								$card_type = '<span style="color: #dc2626;">Rood</span>';
							}
						}

						if ( $sanction_desc === 'uitsluiting' ) {
							$suspension = 'Ja';
						}
					} else {
						$description = $item['description'] ?? '';
					}

					$line_items_html .= '<tr>';
					$line_items_html .= '<td>' . esc_html( $description ) . '</td>';
					$line_items_html .= '<td>' . $card_type . '</td>';
					$line_items_html .= '<td>' . esc_html( $suspension ) . '</td>';
					$line_items_html .= '<td style="text-align: right;">' . $formatted_amount . '</td>';
					$line_items_html .= '</tr>';
				}
			}
		}

		// Build logo HTML
		$logo_html = '';
		if ( $logo_path ) {
			$logo_html = '<img src="' . $logo_path . '" style="height: 70px;" />';
		}

		// Build HTML
		$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body {
	font-family: DejaVu Sans, sans-serif;
	font-size: 10pt;
	color: #333;
	line-height: 1.5;
}
.header {
	margin-bottom: 30px;
	border-bottom: 2px solid ' . esc_attr( $accent_color ) . ';
	padding-bottom: 15px;
}
.header table {
	width: 100%;
	border: none;
}
.header td {
	border: none;
	padding: 0;
	vertical-align: middle;
}
.header .org-name {
	font-weight: bold;
	font-size: 14pt;
	color: ' . esc_attr( $accent_color ) . ';
}
.header .org-details {
	font-size: 9pt;
	color: #666;
}
h1 {
	font-size: 24pt;
	font-weight: bold;
	color: ' . esc_attr( $accent_color ) . ';
	margin: 20px 0;
}
.invoice-meta {
	margin-bottom: 30px;
	font-size: 10pt;
}
.invoice-meta table {
	border: none;
}
.invoice-meta td {
	padding: 3px 0;
	border: none;
}
.invoice-meta .label {
	font-weight: bold;
	width: 140px;
}
.recipient {
	margin-bottom: 30px;
	padding: 15px;
	background-color: #f8f8f8;
	border-radius: 4px;
}
.recipient .label {
	font-weight: bold;
	margin-bottom: 5px;
}
table.line-items {
	width: 100%;
	border-collapse: collapse;
	margin: 30px 0;
}
table.line-items th {
	text-align: left;
	padding: 10px 8px;
	border-bottom: 2px solid #333;
	font-weight: bold;
	font-size: 11pt;
}
table.line-items td {
	padding: 8px;
	border-bottom: 1px solid #ddd;
}
table.line-items .total-row td {
	border-top: 2px solid #333;
	border-bottom: none;
	font-weight: bold;
	padding-top: 12px;
}
.payment-section {
	margin-top: 40px;
	padding: 20px;
	background-color: #f8f8f8;
	border-radius: 4px;
}
.payment-section h2 {
	font-size: 12pt;
	font-weight: bold;
	margin: 0 0 15px 0;
	color: ' . esc_attr( $accent_color ) . ';
}
.payment-section .iban {
	font-size: 12pt;
	font-weight: bold;
	margin: 10px 0;
}
.payment-clause {
	margin-top: 15px;
	font-size: 9pt;
	color: #666;
	white-space: pre-line;
}
</style>
</head>
<body>

<div class="header">
	<table><tr>
		<td>
			<div class="org-name">' . esc_html( $org_name ) . '</div>
			<div class="org-details">' . nl2br( esc_html( $org_address ) ) . '</div>
			<div class="org-details">' . esc_html( $contact_email ) . '</div>
		</td>
		' . ( $logo_html ? '<td style="text-align: right; width: 100px;">' . $logo_html . '</td>' : '' ) . '
	</tr></table>
</div>

<h1>FACTUUR</h1>

<div class="invoice-meta">
	<table>
		<tr>
			<td class="label">Factuurnummer:</td>
			<td>' . esc_html( $invoice_number ) . '</td>
		</tr>
		<tr>
			<td class="label">Factuurdatum:</td>
			<td>' . esc_html( $formatted_invoice_date ) . '</td>
		</tr>'
		. ( ! $is_membership ? '
		<tr>
			<td class="label">Vervaldatum:</td>
			<td>' . esc_html( $formatted_due_date ) . '</td>
		</tr>' : '' )
		. $custom_fields_html . '
	</table>
</div>

<div class="recipient">
	<div class="label">Aan:</div>
	<div>' . esc_html( $person_name ) . '</div>
	' . ( ! empty( $person_attention ) ? '<div>T.a.v. ' . esc_html( $person_attention ) . '</div>' : '' ) . '
	' . ( ! empty( $person_street ) ? '<div>' . esc_html( $person_street ) . '</div>' : '' ) . '
	' . ( ! empty( $person_city ) ? '<div>' . esc_html( $person_city ) . '</div>' : '' ) . '
	' . ( ! empty( $person_email ) ? '<div>' . esc_html( $person_email ) . '</div>' : '' ) . '
</div>

' . ( $is_membership ? '
<table class="line-items">
	<thead>
		<tr>
			<th style="width: 70%;">Omschrijving</th>
			<th style="width: 30%; text-align: right;">Bedrag</th>
		</tr>
	</thead>
	<tbody>
		' . $line_items_html . '
		<tr class="total-row">
			<td style="text-align: right;">Totaal</td>
			<td style="text-align: right;">' . $formatted_total . '</td>
		</tr>
	</tbody>
</table>' : '
<table class="line-items">
	<thead>
		<tr>
			<th style="width: 45%;">Omschrijving</th>
			<th style="width: 15%;">Kaart</th>
			<th style="width: 20%;">Schorsing</th>
			<th style="width: 20%; text-align: right;">Bedrag</th>
		</tr>
	</thead>
	<tbody>
		' . $line_items_html . '
		<tr class="total-row">
			<td colspan="3" style="text-align: right;">Totaal</td>
			<td style="text-align: right;">' . $formatted_total . '</td>
		</tr>
	</tbody>
</table>' ) . '

		' . ( ! $is_paid && $is_membership ? '
<div class="payment-section">
	<h2>Betaalgegevens</h2>
	<table style="width: 100%; border: none;"><tr>
		<td style="border: none; vertical-align: top; padding: 0;">
			' . ( $formatted_iban !== '' ? '<div class="iban">IBAN: ' . esc_html( $formatted_iban ) . '</div>' : '' ) . '
			' . ( $account_holder !== '' ? '<div>t.n.v. ' . esc_html( $account_holder ) . '</div>' : '' ) . '
			' . ( ! empty( $membership_payment_clause ) ? '<div class="payment-clause">' . nl2br( esc_html( $membership_payment_clause ) ) . '</div>' : '' ) . '
		</td>'
		. ( $qr_code_path ? '
		<td style="border: none; text-align: center; vertical-align: top; width: 180px; padding: 0;">
			<img src="' . $qr_code_path . '" style="width: 170px;" />
			<div style="font-size: 8pt; color: #666; margin-top: 5px;">Scan om te betalen</div>
		</td>' : '' ) . '
	</tr></table>
</div>' : ( ! $is_paid ? '
<div class="payment-section">
	<h2>Betaalgegevens</h2>
	<table style="width: 100%; border: none;"><tr>
		<td style="border: none; vertical-align: top; padding: 0;">
			<div class="iban">IBAN: ' . esc_html( $formatted_iban ) . '</div>
			<div>t.n.v. ' . esc_html( $account_holder ?: $org_name ) . '</div>
			' . ( ! empty( $payment_clause ) ? '<div class="payment-clause">' . nl2br( esc_html( $payment_clause ) ) . '</div>' : '' ) . '
		</td>'
		. ( $qr_code_path ? '
		<td style="border: none; text-align: center; vertical-align: top; width: 180px; padding: 0;">
			<img src="' . $qr_code_path . '" style="width: 170px;" />
			<div style="font-size: 8pt; color: #666; margin-top: 5px;">Scan om te betalen</div>
		</td>' : '' ) . '
	</tr></table>
</div>' : '' ) ) . '

</body>
</html>';

		return $html;
	}

	/**
	 * Format date from ACF Ymd format to Dutch date format
	 *
	 * @param string $ymd_date Date in Ymd format (e.g., "20260216").
	 * @return string Formatted Dutch date (e.g., "16 februari 2026").
	 */
	private static function format_dutch_date( $ymd_date ) {
		if ( empty( $ymd_date ) || strlen( $ymd_date ) !== 8 ) {
			return '';
		}

		$dutch_months = [
			1  => 'januari',
			2  => 'februari',
			3  => 'maart',
			4  => 'april',
			5  => 'mei',
			6  => 'juni',
			7  => 'juli',
			8  => 'augustus',
			9  => 'september',
			10 => 'oktober',
			11 => 'november',
			12 => 'december',
		];

		$year  = substr( $ymd_date, 0, 4 );
		$month = (int) substr( $ymd_date, 4, 2 );
		$day   = (int) substr( $ymd_date, 6, 2 );

		$month_name = $dutch_months[ $month ] ?? '';

		return $day . ' ' . $month_name . ' ' . $year;
	}

	/**
	 * Resolve the stored payment account snapshot for an invoice.
	 *
	 * @param int           $invoice_id Invoice post ID.
	 * @param FinanceConfig $finance_config Finance config service.
	 * @return array<string, string>
	 */
	private static function get_invoice_payment_account( int $invoice_id, FinanceConfig $finance_config ): array {
		$invoice_type    = (string) get_field( 'invoice_type', $invoice_id );
		$account_id      = (string) get_post_meta( $invoice_id, '_payment_account_id', true );
		$internal_name   = (string) get_post_meta( $invoice_id, '_payment_account_internal_name', true );
		$account_holder  = (string) get_post_meta( $invoice_id, '_payment_account_account_holder', true );
		$iban            = (string) get_post_meta( $invoice_id, '_payment_account_iban', true );
		$linked_provider = (string) get_post_meta( $invoice_id, '_payment_account_linked_provider', true );

		if ( $account_id !== '' || $internal_name !== '' || $account_holder !== '' || $iban !== '' ) {
			return [
				'id'              => $account_id,
				'internal_name'   => $internal_name,
				'account_holder'  => $account_holder,
				'iban'            => $iban,
				'linked_provider' => $linked_provider,
			];
		}

		$default = FinanceServices::mollie()->get_payment_account_snapshot_for_invoice_type( $invoice_type ?: 'manual' );
		if ( is_array( $default ) ) {
			return $default;
		}

		return [
			'id'              => '',
			'internal_name'   => '',
			'account_holder'  => $finance_config->get_org_name(),
			'iban'            => $finance_config->get_iban(),
			'linked_provider' => '',
		];
	}
}
