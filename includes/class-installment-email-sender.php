<?php
/**
 * Installment Email Sender Service
 *
 * Handles email composition and sending for installment-related emails.
 * Separate from InvoiceEmailSender (which handles discipline invoices with PDF,
 * QR code, and discipline table). This class sends simple HTML emails for
 * membership fee installment payments and overdue reminders.
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
 * Installment Email Sender class
 *
 * Three public static methods for:
 *   - Initial installment email (sent when installment becomes due)
 *   - Reminder 1 (14 days overdue)
 *   - Reminder 2 (21 days overdue — treasurer receives BCC)
 *
 * Each method creates a fresh Mollie payment link before composing the email.
 * Status timestamps are written BEFORE wp_mail() to prevent duplicate sends
 * in the event of a cron re-run.
 */
class InstallmentEmailSender {

	/**
	 * Dutch month names (1-indexed)
	 *
	 * @var array<int, string>
	 */
	private static array $dutch_months = [
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

	/**
	 * Send the initial installment email.
	 *
	 * Called when an installment's status is 'pending' and its due date has arrived.
	 * Creates a fresh Mollie payment link, composes the installment email using the
	 * configured template, and sends it to the member.
	 *
	 * Status is written to 'sent' BEFORE wp_mail() for idempotency. If Mollie
	 * create_payment fails, the method returns early (status stays 'pending',
	 * will retry next day).
	 *
	 * @param int $invoice_id        Invoice post ID.
	 * @param int $installment_number 1-based installment number.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function send_installment_email( int $invoice_id, int $installment_number ): true|\WP_Error {
		// Create fresh Mollie payment link — must succeed before we send the email.
		$checkout_url = InstallmentPaymentService::create_payment( $invoice_id, $installment_number );
		if ( is_wp_error( $checkout_url ) ) {
			return $checkout_url;
		}

		$config   = new FinanceConfig();
		$template = $config->get_installment_email_template();

		// Write status BEFORE wp_mail to prevent duplicate sends.
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_status', 'sent' );
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_sent_at', current_time( 'mysql' ) );

		$subject_prefix = 'Termijn ' . $installment_number;

		return self::resolve_and_send( $invoice_id, $installment_number, $template, $checkout_url, $subject_prefix, false, 'installment' );
	}

	/**
	 * Send the first overdue reminder email.
	 *
	 * Called when an installment's status is 'sent' and it is 14+ days past the due date.
	 * Creates a fresh Mollie payment link and sends a reminder using the configured template.
	 *
	 * Timestamp is written BEFORE wp_mail() for idempotency.
	 *
	 * @param int $invoice_id        Invoice post ID.
	 * @param int $installment_number 1-based installment number.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function send_reminder_1( int $invoice_id, int $installment_number ): true|\WP_Error {
		// Create fresh Mollie payment link — links expire, each email needs its own.
		$checkout_url = InstallmentPaymentService::create_payment( $invoice_id, $installment_number );
		if ( is_wp_error( $checkout_url ) ) {
			return $checkout_url;
		}

		$config   = new FinanceConfig();
		$template = $config->get_reminder_1_email_template();

		// Write timestamp BEFORE wp_mail to prevent duplicate sends.
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_reminder_1_sent_at', current_time( 'mysql' ) );

		$subject_prefix = 'Herinnering termijn ' . $installment_number;

		return self::resolve_and_send( $invoice_id, $installment_number, $template, $checkout_url, $subject_prefix, false, 'reminder_1' );
	}

	/**
	 * Send the second (final) overdue reminder email.
	 *
	 * Called when an installment's status is 'sent' and it is 21+ days past the due date.
	 * Creates a fresh Mollie payment link and sends a second reminder using the configured
	 * template. The treasurer receives a BCC if configured.
	 *
	 * Timestamp is written BEFORE wp_mail() for idempotency.
	 *
	 * @param int $invoice_id        Invoice post ID.
	 * @param int $installment_number 1-based installment number.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function send_reminder_2( int $invoice_id, int $installment_number ): true|\WP_Error {
		// Create fresh Mollie payment link — links expire, each email needs its own.
		$checkout_url = InstallmentPaymentService::create_payment( $invoice_id, $installment_number );
		if ( is_wp_error( $checkout_url ) ) {
			return $checkout_url;
		}

		$config   = new FinanceConfig();
		$template = $config->get_reminder_2_email_template();

		// Write timestamp BEFORE wp_mail to prevent duplicate sends.
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_reminder_2_sent_at', current_time( 'mysql' ) );

		$subject_prefix = 'Tweede herinnering termijn ' . $installment_number;

		return self::resolve_and_send( $invoice_id, $installment_number, $template, $checkout_url, $subject_prefix, true, 'reminder_2' );
	}

	/**
	 * Shared helper: resolve template variables and send email.
	 *
	 * Resolves person data, installment meta, and builds the email from the given
	 * template. Constructs the full email subject, headers, and calls wp_mail().
	 *
	 * @param int    $invoice_id        Invoice post ID.
	 * @param int    $installment_number 1-based installment number.
	 * @param string $template          Email template HTML with {variable} placeholders.
	 * @param string $checkout_url      Mollie checkout URL for the {betaallink} anchor.
	 * @param string $subject_prefix    Prefix for the email subject line.
	 * @param bool   $add_bcc           Whether to add BCC header to treasurer email.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private static function resolve_and_send(
		int $invoice_id,
		int $installment_number,
		string $template,
		string $checkout_url,
		string $subject_prefix,
		bool $add_bcc,
		string $heading_type = 'installment'
	): true|\WP_Error {
		// Resolve person from invoice.
		$person_id = get_field( 'person', $invoice_id );
		$person    = $person_id ? get_post( $person_id ) : null;

		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error(
				'invalid_person',
				'Persoon niet gevonden voor factuur ' . $invoice_id . '.'
			);
		}

		// Build person name.
		$first_name  = (string) get_field( 'first_name', $person_id );
		$infix       = (string) get_field( 'infix', $person_id );
		$last_name   = (string) get_field( 'last_name', $person_id );
		$name_parts  = array_filter( [ $first_name, $infix, $last_name ] );
		$person_name = implode( ' ', $name_parts );

		// Resolve all invoice recipients (person emails + parents if minor).
		$recipient_emails = InvoiceEmailSender::resolve_invoice_recipient_emails( (int) $person_id );

		if ( empty( $recipient_emails ) ) {
			return new \WP_Error(
				'no_email',
				'Geen e-mailadres gevonden voor persoon ' . $person_id . '.'
			);
		}

		// Read installment meta.
		$amount      = (float) get_post_meta( $invoice_id, '_installment_' . $installment_number . '_amount', true );
		$admin_fee   = (float) get_post_meta( $invoice_id, '_installment_' . $installment_number . '_admin_fee', true );
		$due_date    = (string) get_post_meta( $invoice_id, '_installment_' . $installment_number . '_due_date', true );
		$total_count = (int) get_post_meta( $invoice_id, '_installment_count', true );

		// Format termijn_bedrag in Dutch currency.
		$termijn_bedrag = '&euro; ' . number_format( $amount + $admin_fee, 2, ',', '.' );

		// Format vervaldatum as Dutch long date (e.g. "25 november 2025").
		$vervaldatum = '';
		if ( ! empty( $due_date ) ) {
			$ts = strtotime( $due_date );
			if ( $ts !== false ) {
				$day         = (int) date( 'j', $ts );
				$month       = (int) date( 'n', $ts );
				$year        = (int) date( 'Y', $ts );
				$vervaldatum = $day . ' ' . ( self::$dutch_months[ $month ] ?? '' ) . ' ' . $year;
			}
		}

		// Calculate dagen_te_laat.
		$days_overdue = 0;
		if ( ! empty( $due_date ) ) {
			$today_ts = strtotime( current_time( 'Y-m-d' ) );
			$due_ts   = strtotime( $due_date );
			if ( $today_ts !== false && $due_ts !== false ) {
				$days_overdue = max( 0, (int) floor( ( $today_ts - $due_ts ) / DAY_IN_SECONDS ) );
			}
		}

		// Format betaallink as styled HTML anchor.
		$betaallink = '<a href="' . esc_url( $checkout_url ) . '" style="color:#0891b2;text-decoration:underline;">Betaal nu</a>';

		// Build CTA button for {betaalknop} placeholder.
		$betaalknop = EmailTemplate::render_cta_button( $checkout_url, 'Betaal nu' );

		// Get invoice number and org info.
		$invoice_number = (string) get_field( 'invoice_number', $invoice_id );
		$config         = new FinanceConfig();
		$org_name       = $config->get_display_name();
		$contact_email  = $config->get_contact_email();

		// Replace template variables.
		$email_body = str_replace(
			[
				'{naam}',
				'{voornaam}',
				'{factuur_nummer}',
				'{termijn_nummer}',
				'{totaal_termijnen}',
				'{termijn_bedrag}',
				'{betaallink}',
				'{betaalknop}',
				'{vervaldatum}',
				'{organisatie_naam}',
				'{dagen_te_laat}',
			],
			[
				esc_html( $person_name ),
				esc_html( $first_name ),
				esc_html( $invoice_number ),
				(string) $installment_number,
				(string) $total_count,
				$termijn_bedrag,
				$betaallink,
				$betaalknop,
				esc_html( $vervaldatum ),
				esc_html( $org_name ),
				(string) $days_overdue,
			],
			$template
		);

		$email_body = EmailTemplate::render(
			[
				'brand_name'    => $org_name,
				'preheader'     => sprintf( 'Termijn %d voor factuur %s', $installment_number, $invoice_number ),
				'eyebrow'       => 'Factuur ' . $invoice_number,
				'heading'       => $config->get_email_heading( $heading_type ),
				'body_html'     => $email_body,
				'support_email' => $contact_email,
			]
		);

		// Build email subject.
		$subject = $subject_prefix . ' - Factuur ' . $invoice_number . ' - ' . $org_name;

		// Build headers.
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $org_name . ' <' . $contact_email . '>',
		];

		// Conditionally add BCC for reminder 2.
		if ( $add_bcc ) {
			$bcc_email = $config->get_bcc_email();
			if ( ! empty( $bcc_email ) ) {
				$headers[] = 'Bcc: ' . $bcc_email;
			}
		}

		// Send email via wp_mail.
		$result = wp_mail( $recipient_emails, $subject, $email_body, $headers );

		if ( ! $result ) {
			return new \WP_Error(
				'email_send_failed',
				'Verzenden van e-mail mislukt voor factuur ' . $invoice_id . ', termijn ' . $installment_number . '.'
			);
		}

		return true;
	}
}
