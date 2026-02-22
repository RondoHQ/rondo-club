<?php
/**
 * Invoice Reminder Sender Service
 *
 * Handles email composition and sending for membership invoice reminders.
 * These reminders are sent to members who received a contributie invoice but have
 * not yet visited the payment page to select a payment plan.
 *
 * Unlike InstallmentEmailSender, no Mollie payment link creation is needed —
 * the betaallink (/betaling/{token}) already exists on the invoice and is valid
 * indefinitely until the member selects a plan.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Invoice Reminder Sender class
 *
 * Two public static methods:
 *   - send_reminder_1(): first reminder (14 days after sent_date)
 *   - send_reminder_2(): second reminder (28 days after sent_date, BCC to treasurer)
 *
 * Idempotency timestamps are written BEFORE wp_mail() to prevent duplicate sends
 * in the event of a cron re-run.
 */
class InvoiceReminderSender {

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
	 * Send the first invoice reminder email.
	 *
	 * Called when a membership invoice is 14+ days old with no payment plan selected.
	 * Uses the invoice_reminder_1_email_template from FinanceConfig.
	 *
	 * Timestamp is written BEFORE wp_mail() for idempotency.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function send_reminder_1( int $invoice_id ): true|\WP_Error {
		$config   = new FinanceConfig();
		$template = $config->get_invoice_reminder_1_email_template();

		// Write timestamp BEFORE wp_mail to prevent duplicate sends.
		update_post_meta( $invoice_id, '_invoice_reminder_1_sent_at', current_time( 'mysql' ) );

		$subject_prefix = 'Herinnering';

		return self::resolve_and_send( $invoice_id, $template, $subject_prefix, false );
	}

	/**
	 * Send the second (final) invoice reminder email.
	 *
	 * Called when a membership invoice is 28+ days old with no payment plan selected.
	 * Uses the invoice_reminder_2_email_template from FinanceConfig.
	 * The treasurer receives a BCC if configured.
	 *
	 * Timestamp is written BEFORE wp_mail() for idempotency.
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function send_reminder_2( int $invoice_id ): true|\WP_Error {
		$config   = new FinanceConfig();
		$template = $config->get_invoice_reminder_2_email_template();

		// Write timestamp BEFORE wp_mail to prevent duplicate sends.
		update_post_meta( $invoice_id, '_invoice_reminder_2_sent_at', current_time( 'mysql' ) );

		$subject_prefix = 'Tweede herinnering';

		return self::resolve_and_send( $invoice_id, $template, $subject_prefix, true );
	}

	/**
	 * Shared helper: resolve template variables and send email.
	 *
	 * Resolves person data, invoice meta, and builds the email from the given
	 * template. Constructs the full email subject, headers, and calls wp_mail().
	 *
	 * @param int    $invoice_id     Invoice post ID.
	 * @param string $template       Email template HTML with {variable} placeholders.
	 * @param string $subject_prefix Prefix for the email subject line.
	 * @param bool   $add_bcc        Whether to add BCC header to treasurer email.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	private static function resolve_and_send(
		int $invoice_id,
		string $template,
		string $subject_prefix,
		bool $add_bcc
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

		// Get person email from contact_info repeater.
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
				'Geen e-mailadres gevonden voor persoon ' . $person_id . '.'
			);
		}

		// Read payment_link ACF field — this is the /betaling/{token} URL.
		$payment_link = (string) get_field( 'payment_link', $invoice_id );

		if ( empty( $payment_link ) ) {
			return new \WP_Error(
				'no_payment_link',
				'Geen betaallink gevonden op factuur ' . $invoice_id . '.'
			);
		}

		// Format betaallink as styled HTML anchor.
		$betaallink = '<a href="' . esc_url( $payment_link ) . '" style="color:#0891b2;text-decoration:underline;">Betaal nu</a>';

		// Read invoice fields.
		$total_amount   = (float) get_field( 'total_amount', $invoice_id );
		$invoice_number = (string) get_field( 'invoice_number', $invoice_id );

		// Format total_amount in Dutch currency.
		$totaal_bedrag = '&euro; ' . number_format( $total_amount, 2, ',', '.' );

		// Read sent_date ACF field (stored as Ymd string) and format as Dutch long date.
		$sent_date    = (string) get_field( 'sent_date', $invoice_id );
		$factuurdatum = '';
		$days_since   = 0;

		if ( ! empty( $sent_date ) ) {
			$sent_ts = strtotime( $sent_date );
			if ( $sent_ts !== false ) {
				$day          = (int) date( 'j', $sent_ts );
				$month        = (int) date( 'n', $sent_ts );
				$year         = (int) date( 'Y', $sent_ts );
				$factuurdatum = $day . ' ' . ( self::$dutch_months[ $month ] ?? '' ) . ' ' . $year;

				$today_ts   = strtotime( current_time( 'Y-m-d' ) );
				if ( $today_ts !== false ) {
					$days_since = max( 0, (int) floor( ( $today_ts - $sent_ts ) / DAY_IN_SECONDS ) );
				}
			}
		}

		// Get org info.
		$config        = new FinanceConfig();
		$org_name      = $config->get_org_name();
		$contact_email = $config->get_contact_email();

		// Replace template variables.
		$email_body = str_replace(
			[
				'{naam}',
				'{voornaam}',
				'{factuur_nummer}',
				'{totaal_bedrag}',
				'{betaallink}',
				'{factuurdatum}',
				'{dagen_sinds_factuur}',
				'{organisatie_naam}',
			],
			[
				esc_html( $person_name ),
				esc_html( $first_name ),
				esc_html( $invoice_number ),
				$totaal_bedrag,
				$betaallink,
				esc_html( $factuurdatum ),
				(string) $days_since,
				esc_html( $org_name ),
			],
			$template
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
		$result = wp_mail( $person_email, $subject, $email_body, $headers );

		if ( ! $result ) {
			return new \WP_Error(
				'email_send_failed',
				'Verzenden van e-mail mislukt voor factuur ' . $invoice_id . '.'
			);
		}

		return true;
	}
}
