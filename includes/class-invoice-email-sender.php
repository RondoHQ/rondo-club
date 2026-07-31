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
use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice Email Sender class
 */
class InvoiceEmailSender {

	/**
	 * Resolve all recipient email addresses for an invoice.
	 *
	 * Rules:
	 * - Always include up to two email addresses of the invoice person.
	 * - If the person is a minor (<18), also include up to two parent persons.
	 * - For each parent person, include up to two email addresses.
	 * - Returned list is unique and validated.
	 *
	 * @param int $person_id Invoice person post ID.
	 * @return array<int, string>
	 */
	public static function resolve_invoice_recipient_emails( int $person_id ): array {
		$emails = self::get_person_email_addresses( $person_id );

		if ( self::is_minor( $person_id ) ) {
			$parent_ids = self::get_parent_person_ids( $person_id );
			foreach ( $parent_ids as $parent_id ) {
				$emails = array_merge( $emails, self::get_person_email_addresses( $parent_id ) );
			}
		}

		$emails = array_values( array_unique( array_filter( $emails, 'is_email' ) ) );
		return $emails;
	}

	/**
	 * Get up to two email addresses from a person's fixed email fields.
	 *
	 * @param int $person_id Person post ID.
	 * @return array<int, string>
	 */
	public static function get_person_email_addresses( int $person_id ): array {
		$emails = [];

		$email_1 = trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'email_1' ) );
		if ( is_email( $email_1 ) ) {
			$emails[] = $email_1;
		}

		$email_2 = trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'email_2' ) );
		if ( is_email( $email_2 ) && $email_2 !== $email_1 ) {
			$emails[] = $email_2;
		}

		return $emails;
	}

	/**
	 * Determine whether a person is younger than 18.
	 *
	 * @param int $person_id Person post ID.
	 * @return bool
	 */
	public static function is_minor( int $person_id ): bool {
		$birthdate = (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'birthdate' );
		if ( trim( $birthdate ) === '' ) {
			return false;
		}

		$birth_ts = strtotime( $birthdate );
		if ( $birth_ts === false ) {
			return false;
		}

		$today_ts = strtotime( current_time( 'Y-m-d' ) );
		if ( $today_ts === false || $birth_ts > $today_ts ) {
			return false;
		}

		$years = (int) date( 'Y', $today_ts ) - (int) date( 'Y', $birth_ts );
		if ( (int) date( 'md', $today_ts ) < (int) date( 'md', $birth_ts ) ) {
			--$years;
		}

		return $years < 18;
	}

	/**
	 * Get up to two parent person IDs from relationships repeater.
	 *
	 * @param int $person_id Person post ID.
	 * @return array<int, int>
	 */
	public static function get_parent_person_ids( int $person_id ): array {
		$relationships = \Rondo\Fields\Fields::get_for_post( $person_id, 'relationships' );
		if ( empty( $relationships ) || ! is_array( $relationships ) ) {
			return [];
		}

		$parents = [];
		foreach ( $relationships as $relationship ) {
			$related_person_id = (int) ( $relationship['related_person'] ?? 0 );
			if ( $related_person_id <= 0 ) {
				continue;
			}

			$relationship_type_id = (int) ( $relationship['relationship_type'] ?? 0 );
			if ( $relationship_type_id <= 0 ) {
				continue;
			}

			$term = get_term( $relationship_type_id, 'relationship_type' );
			if ( ! $term || is_wp_error( $term ) || $term->slug !== 'parent' ) {
				continue;
			}

			$parents[] = $related_person_id;
			if ( count( array_unique( $parents ) ) >= 2 ) {
				break;
			}
		}

		return array_values( array_unique( $parents ) );
	}

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
		$invoice_number = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_number' );
		$person_id      = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'person' );
		$total_amount   = (float) \Rondo\Fields\Fields::get_for_post( $invoice_id, 'total_amount' );
		$line_items     = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'line_items' );
		$payment_link   = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'payment_link' );
		$pdf_path       = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'pdf_path' );

		$person            = $person_id ? get_post( $person_id ) : null;
		$first_name        = '';
		$person_name       = (string) get_post_meta( $invoice_id, '_customer_name', true );
		$customer_email    = (string) get_post_meta( $invoice_id, '_customer_email', true );
		$customer_cc_email = (string) get_post_meta( $invoice_id, '_customer_cc_email', true );
		$recipient_emails  = [];

		if ( $person && $person->post_type === 'person' ) {
			$first_name  = (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) ?: \Rondo\Fields\Fields::get_for_post( $person_id, 'company_name' ) );
			$person_name = (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'company_name' ) ?: $person->post_title );

			$recipient_emails = self::resolve_invoice_recipient_emails( (int) $person_id );
		}

		if ( is_email( $customer_email ) ) {
			$recipient_emails[] = $customer_email;
		}

		if ( empty( $person_name ) ) {
			$person_name = __( 'Relatie', 'rondo' );
		}

		$recipient_emails = array_values( array_unique( array_filter( $recipient_emails, 'is_email' ) ) );

		// In test mode, redirect email to override address
		$recipient_email = $options['override_email'] ?? '';
		if ( ! empty( $recipient_email ) ) {
			$recipient_emails = [ $recipient_email ];
		}

		if ( empty( $recipient_emails ) ) {
			return new \WP_Error(
				'no_email',
				__( 'Geen e-mailadres gevonden voor deze factuur.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		// Get finance configuration
		$config       = new FinanceConfig();
		$invoice_type = (string) \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_type' );
		$invoice_kind = get_post_meta( $invoice_id, '_invoice_kind', true ) ?: 'normal';
		$is_credit    = $invoice_kind === 'credit';
		$template     = (string) ( $options['template'] ?? '' );
		if ( trim( $template ) === '' ) {
			if ( $is_credit ) {
				$template = $config->get_credit_email_template();
			} elseif ( $invoice_type === 'membership' ) {
				$template = $config->get_membership_email_template();
			} elseif ( $invoice_type === 'manual' ) {
				$template = $config->get_regular_invoice_email_body();
			} else {
				$template = $config->get_email_template();
			}
		}
		$org_name = $config->get_display_name();

		// Build discipline cases list as HTML table
		$tuchtzaken_lijst = '';
		if ( $line_items && is_array( $line_items ) ) {
			$table_rows = [];
			$row_index  = 0;
			foreach ( $line_items as $item ) {
				$row_bg   = ( $row_index % 2 === 1 ) ? ' background-color:#f9fafb;' : '';
				$td_style = 'padding:8px 12px;border-bottom:1px solid #e5e7eb;';

				if ( ! empty( $item['discipline_case'] ) ) {
					$case_id          = $item['discipline_case'];
					$match_date       = \Rondo\Fields\Fields::get_for_post( $case_id, 'match_date' );
					$match_desc       = esc_html( \Rondo\Fields\Fields::get_for_post( $case_id, 'match_description' ) ?: '-' );
					$amount           = (float) ( $item['amount'] ?? 0 );
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
					$charge_codes = \Rondo\Fields\Fields::get_for_post( $case_id, 'charge_codes' );
					$card_text    = '-';
					if ( ! empty( $charge_codes ) ) {
						$card_text = str_ends_with( $charge_codes, '-1' ) ? 'Geel' : 'Rood';
						// Append schorsing for uitsluiting sanctions
						$sanction_desc = \Rondo\Fields\Fields::get_for_post( $case_id, 'sanction_description' );
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
				++$row_index;
			}
			if ( ! empty( $table_rows ) ) {
				$th_style         = 'padding:8px 12px;text-align:left;border-bottom:2px solid #d1d5db;';
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
		$upload_dir   = wp_upload_dir();
		$qr_code_path = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'qr_code_path' );

		if ( ! empty( $qr_code_path ) ) {
			$qr_full_path = $upload_dir['basedir'] . '/' . $qr_code_path;
			if ( file_exists( $qr_full_path ) ) {
				$qr_url       = $upload_dir['baseurl'] . '/' . $qr_code_path;
				$qr_code_html = '<img src="' . esc_url( $qr_url ) . '" alt="QR Code betaallink" width="200" style="display:block;" />';
			}
		}

		// Build CTA button for {betaalknop} placeholder.
		$betaalknop = ! empty( $payment_link )
			? EmailTemplate::render_cta_button( $payment_link, 'Open betaallink' )
			: '';

		// Replace template variables
		if ( $invoice_type === 'manual' && ! self::contains_html_markup( $template ) ) {
			$template = nl2br( esc_html( $template ) );
		}

		$email_body = str_replace(
			[
				'{naam}',
				'{voornaam}',
				'{factuur_nummer}',
				'{tuchtzaken_lijst}',
				'{totaal_bedrag}',
				'{betaallink}',
				'{betaalknop}',
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
				$betaalknop,
				$qr_code_html,
				esc_html( $org_name ),
			],
			$template
		);

		$heading_type = $is_credit ? 'credit' : match ( $invoice_type ) {
			'membership' => 'membership',
			'manual'     => 'regular_invoice',
			default      => 'discipline',
		};

		$email_body = EmailTemplate::render(
			[
				'brand_name'    => $org_name,
				'preheader'     => sprintf( 'Factuur %s van %s', $invoice_number, $org_name ),
				'eyebrow'       => 'Factuur ' . $invoice_number,
				'heading'       => $config->get_email_heading( $heading_type ),
				'body_html'     => $email_body,
				'support_email' => $config->get_contact_email(),
			]
		);

		// Build email subject
		$subject = (string) ( $options['subject'] ?? '' );
		if ( trim( $subject ) === '' ) {
			if ( $is_credit ) {
				$subject = $config->get_credit_email_subject();
				if ( trim( $subject ) === '' ) {
					$subject = 'Creditfactuur ' . $invoice_number . ' - ' . $org_name;
				}
			} elseif ( $invoice_type === 'manual' ) {
				$subject = $config->get_regular_invoice_email_subject();
				if ( trim( $subject ) === '' ) {
					$subject = 'Factuur ' . $invoice_number . ' - ' . $org_name;
				}
			} else {
				$subject = 'Factuur ' . $invoice_number . ' - ' . $org_name;
			}
		}

		$subject = str_replace(
			[
				'{naam}',
				'{voornaam}',
				'{factuur_nummer}',
				'{organisatie_naam}',
				'{totaal_bedrag}',
			],
			[
				$person_name,
				$first_name,
				$invoice_number,
				$org_name,
				wp_strip_all_tags( $formatted_total ),
			],
			$subject
		);

		// In test mode, prefix subject to make test emails clearly identifiable
		if ( ! empty( $options['override_email'] ) || ! empty( $options['skip_bcc'] ) ) {
			$subject = '[TEST] ' . $subject;
		}

		// Build headers with From address and HTML content type
		$contact_email = $config->get_contact_email();
		$headers       = [
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

		if ( is_email( $customer_cc_email ) && empty( $options['override_email'] ) ) {
			$headers[] = 'Cc: ' . $customer_cc_email;
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
		$result = wp_mail( $recipient_emails, $subject, $email_body, $headers, $attachments );

		if ( ! $result ) {
			return new \WP_Error(
				'email_send_failed',
				__( 'Verzenden van e-mail is mislukt.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		return true;
	}

	/**
	 * Detect whether a template already contains HTML markup.
	 *
	 * @param string $template Email template.
	 * @return bool
	 */
	private static function contains_html_markup( string $template ): bool {
		return preg_match( '/<\s*[a-z!\/][^>]*>/i', $template ) === 1;
	}
}
