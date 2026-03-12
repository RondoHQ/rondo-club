<?php
/**
 * Finance Configuration Service
 *
 * Handles finance-specific configuration settings storage and retrieval using the WordPress Options API.
 * Sensitive credentials (Rabobank API) are encrypted at rest using sodium encryption.
 *
 * @package Rondo\Config
 */

namespace Rondo\Config;

use Rondo\Data\CredentialEncryption;
use Rondo\Config\ClubConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finance Configuration service class
 *
 * Installment meta schema (flat numbered post meta on rondo_invoice):
 *   _installment_count          — Total installments (int)
 *   _installment_plan           — Plan type: 'full', 'quarterly_3', or 'monthly_8'
 *   _installment_N_amount       — Installment amount (float)
 *   _installment_N_admin_fee    — Admin fee portion (float)
 *   _installment_N_status       — Status: 'pending', 'sent', 'paid', 'overdue'
 *   _installment_N_due_date     — Due date (Y-m-d)
 *   _installment_N_sent_at      — DateTime sent (nullable)
 *   _installment_N_paid_at      — DateTime paid (nullable)
 *   _installment_N_mollie_payment_id — Mollie payment ID (nullable)
 *   _installment_N_payment_link — Mollie checkout URL (nullable)
 *
 * Reverse-lookup pattern for webhook O(1) matching:
 *   _mollie_pid_{payment_id} = installment_number (stored on invoice post)
 */
class FinanceConfig {

	/**
	 * Option keys for finance settings
	 */
	const OPTION_ORG_NAME              = 'rondo_finance_org_name';
	const OPTION_ORG_ADDRESS           = 'rondo_finance_org_address';
	const OPTION_CONTACT_EMAIL         = 'rondo_finance_contact_email';
	const OPTION_IBAN                  = 'rondo_finance_iban';
	const OPTION_MOLLIE_ACCOUNTS       = 'rondo_finance_mollie_accounts';
	const OPTION_PAYMENT_TERM_DAYS     = 'rondo_finance_payment_term_days';
	const OPTION_PAYMENT_CLAUSE        = 'rondo_finance_payment_clause';
	const OPTION_EMAIL_TEMPLATE        = 'rondo_finance_email_template';
	const OPTION_RABOBANK_CREDENTIALS  = 'rondo_finance_rabobank_credentials';
	const OPTION_CLUB_LOGO_ID          = 'rondo_finance_club_logo_id';
	const OPTION_ACCENT_COLOR          = 'rondo_finance_accent_color';
	const OPTION_ACCENT_BACKGROUND_COLOR = 'rondo_finance_accent_background_color';
	const OPTION_BCC_EMAIL             = 'rondo_finance_bcc_email';
	const OPTION_MOLLIE_REDIRECT_URL   = 'rondo_finance_mollie_redirect_url';
	const OPTION_MOLLIE_DEFAULT_MEMBERSHIP_ACCOUNT_ID = 'rondo_finance_mollie_default_membership_account_id';
	const OPTION_MOLLIE_DEFAULT_DISCIPLINE_ACCOUNT_ID = 'rondo_finance_mollie_default_discipline_account_id';
	const OPTION_MOLLIE_DEFAULT_MANUAL_ACCOUNT_ID     = 'rondo_finance_mollie_default_manual_account_id';
	const OPTION_ACTIVE_PAYMENT_PROVIDER = 'rondo_finance_active_payment_provider';
	const OPTION_ADMIN_FEE               = 'rondo_finance_admin_fee';
	const OPTION_INSTALLMENT_ADMIN_FEE   = 'rondo_finance_installment_admin_fee';
	const OPTION_MEMBERSHIP_PASS_APPLE_CERT_ATTACHMENT_ID = 'rondo_membership_pass_apple_cert_attachment_id';
	const OPTION_MEMBERSHIP_PASS_APPLE_CERT_PASSWORD      = 'rondo_membership_pass_apple_cert_password';
	const OPTION_MEMBERSHIP_PASS_APPLE_PASS_TYPE_IDENTIFIER = 'rondo_membership_pass_apple_pass_type_identifier';
	const OPTION_MEMBERSHIP_PASS_APPLE_TEAM_IDENTIFIER      = 'rondo_membership_pass_apple_team_identifier';
	const OPTION_MEMBERSHIP_PASS_APPLE_ORGANIZATION_NAME    = 'rondo_membership_pass_apple_organization_name';
	const OPTION_MEMBERSHIP_PASS_GOOGLE_SERVICE_ACCOUNT_ATTACHMENT_ID = 'rondo_membership_pass_google_service_account_attachment_id';
	const OPTION_MEMBERSHIP_PASS_GOOGLE_ISSUER_ID                    = 'rondo_membership_pass_google_issuer_id';
	const OPTION_MEMBERSHIP_PASS_GOOGLE_CLASS_SUFFIX                 = 'rondo_membership_pass_google_class_suffix';
	const OPTION_INSTALLMENT_EMAIL_TEMPLATE  = 'rondo_finance_installment_email_template';
	const OPTION_REMINDER_1_EMAIL_TEMPLATE   = 'rondo_finance_reminder_1_email_template';
	const OPTION_REMINDER_2_EMAIL_TEMPLATE   = 'rondo_finance_reminder_2_email_template';
	const OPTION_MEMBERSHIP_EMAIL_TEMPLATE   = 'rondo_finance_membership_email_template';
	const OPTION_MEMBERSHIP_PAYMENT_CLAUSE   = 'rondo_finance_membership_payment_clause';
	const OPTION_INVOICE_REMINDER_1_EMAIL_TEMPLATE = 'rondo_finance_invoice_reminder_1_email_template';
	const OPTION_INVOICE_REMINDER_2_EMAIL_TEMPLATE = 'rondo_finance_invoice_reminder_2_email_template';
	const OPTION_REGULAR_INVOICE_EMAIL_SUBJECT = 'rondo_finance_regular_invoice_email_subject';
	const OPTION_REGULAR_INVOICE_EMAIL_BODY    = 'rondo_finance_regular_invoice_email_body';
	const OPTION_REGULAR_INVOICE_EMAIL_HEADING    = 'rondo_finance_regular_invoice_email_heading';
	const OPTION_DISCIPLINE_EMAIL_HEADING         = 'rondo_finance_discipline_email_heading';
	const OPTION_MEMBERSHIP_EMAIL_HEADING         = 'rondo_finance_membership_email_heading';
	const OPTION_INSTALLMENT_EMAIL_HEADING        = 'rondo_finance_installment_email_heading';
	const OPTION_REMINDER_1_EMAIL_HEADING         = 'rondo_finance_reminder_1_email_heading';
	const OPTION_REMINDER_2_EMAIL_HEADING         = 'rondo_finance_reminder_2_email_heading';
	const OPTION_INVOICE_REMINDER_1_EMAIL_HEADING = 'rondo_finance_invoice_reminder_1_email_heading';
	const OPTION_INVOICE_REMINDER_2_EMAIL_HEADING = 'rondo_finance_invoice_reminder_2_email_heading';
	const OPTION_CREDIT_EMAIL_TEMPLATE            = 'rondo_finance_credit_email_template';
	const OPTION_CREDIT_EMAIL_HEADING              = 'rondo_finance_credit_email_heading';

	/**
	 * Default configuration values
	 *
	 * @var array<string, mixed>
	 */
	const DEFAULTS = [
		'org_name'           => '',
		'org_address'        => '',
		'contact_email'      => '',
		'iban'               => '',
		'mollie_accounts'    => [],
		'payment_term_days'  => 14,
		'payment_clause'     => '',
		'club_logo_id'       => 0,
		'accent_color'       => '',
		'accent_background_color' => '',
		'bcc_email'          => '',
		'admin_fee'              => 0.00,
		'installment_admin_fee'  => 0.00,
		'membership_pass_google_class_suffix' => 'rondo_membership',
		'email_template'         => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {naam},</p><p>Bijgevoegd vindt u de factuur {factuur_nummer} voor opgelegde boetes vanuit de tuchtcommissie.</p>{tuchtzaken_lijst}<p>Het totaalbedrag is <strong>{totaal_bedrag}</strong>.</p><p>U kunt betalen via de volgende link: {betaallink}</p>{qr_code}<p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
		'installment_email_template' => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Hierbij ontvangt u het betaalverzoek voor termijn {termijn_nummer} van {totaal_termijnen} van uw contributie (factuur {factuur_nummer}).</p><p><strong>Termijnbedrag:</strong> {termijn_bedrag}<br/><strong>Vervaldatum:</strong> {vervaldatum}</p><p>U kunt betalen via de volgende link:<br/>{betaallink}</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
		'reminder_1_email_template'  => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Wij hebben geconstateerd dat termijn {termijn_nummer} van {totaal_termijnen} van uw contributie (factuur {factuur_nummer}) nog niet is voldaan.</p><p><strong>Termijnbedrag:</strong> {termijn_bedrag}<br/><strong>Vervaldatum was:</strong> {vervaldatum}<br/><strong>Aantal dagen te laat:</strong> {dagen_te_laat}</p><p>Wij verzoeken u vriendelijk dit bedrag zo spoedig mogelijk te voldoen via:<br/>{betaallink}</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
		'reminder_2_email_template'  => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Dit is onze tweede en laatste herinnering voor termijn {termijn_nummer} van {totaal_termijnen} van uw contributie (factuur {factuur_nummer}).</p><p><strong>Termijnbedrag:</strong> {termijn_bedrag}<br/><strong>Vervaldatum was:</strong> {vervaldatum}<br/><strong>Aantal dagen te laat:</strong> {dagen_te_laat}</p><p>Wij verzoeken u dringend dit bedrag direct te voldoen via:<br/>{betaallink}</p><p>Indien u niet reageert, zullen wij de vordering overdragen aan ons bestuur.</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
		'membership_email_template'  => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Bijgevoegd vindt u de factuur {factuur_nummer} voor uw contributie.</p><p>Het totaalbedrag is <strong>{totaal_bedrag}</strong>.</p><p>U kunt betalen via de volgende link: {betaallink}</p>{qr_code}<p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
		'membership_payment_clause'  => '',
		'invoice_reminder_1_email_template' => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Op {factuurdatum} hebben wij u een factuur ({factuur_nummer}) gestuurd voor uw contributie ter hoogte van <strong>{totaal_bedrag}</strong>.</p><p>Wij hebben nog geen betaling ontvangen. Via onderstaande link kunt u uw betaalwijze kiezen en direct betalen:</p><p>{betaallink}</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
		'invoice_reminder_2_email_template' => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {voornaam},</p><p>Dit is onze tweede en laatste herinnering voor factuur {factuur_nummer} voor uw contributie ter hoogte van <strong>{totaal_bedrag}</strong>, verstuurd op {factuurdatum}.</p><p>Het is nu {dagen_sinds_factuur} dagen geleden dat deze factuur is verstuurd en wij hebben nog geen betaling ontvangen.</p><p>Wij verzoeken u dringend zo spoedig mogelijk te betalen via:<br/>{betaallink}</p><p>Indien u niet reageert, zullen wij contact met u opnemen.</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
		'regular_invoice_email_subject' => 'Factuur {factuur_nummer} - {organisatie_naam}',
		'regular_invoice_email_body'    => "Beste {naam},\n\nBijgevoegd vindt u factuur {factuur_nummer}.\n\nHet totaalbedrag is {totaal_bedrag}.\nU kunt betalen via: {betaallink}\n\nMet vriendelijke groet,\n{organisatie_naam}",
		'regular_invoice_email_heading'    => 'Factuur',
		'discipline_email_heading'         => 'Factuur',
		'membership_email_heading'         => 'Contributie',
		'installment_email_heading'        => 'Termijnbetaling',
		'reminder_1_email_heading'         => 'Herinnering termijn',
		'reminder_2_email_heading'         => 'Tweede herinnering',
		'invoice_reminder_1_email_heading' => 'Herinnering',
		'invoice_reminder_2_email_heading' => 'Tweede herinnering',
		'credit_email_template'          => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {naam},</p><p>Bijgevoegd vindt u de creditfactuur {factuur_nummer}.</p>{tuchtzaken_lijst}<p>Het totaal creditbedrag is <strong>{totaal_bedrag}</strong>.</p><p>Dit bedrag wordt verrekend met een openstaande factuur of aan u terugbetaald.</p><p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
		'credit_email_heading'           => 'Creditfactuur',
		'mollie_default_membership_account_id' => '',
		'mollie_default_discipline_account_id' => '',
		'mollie_default_manual_account_id'     => '',
	];

	/**
	 * Get organization name
	 *
	 * @return string The organization name (empty string if not configured)
	 */
	public function get_org_name(): string {
		return get_option( self::OPTION_ORG_NAME, self::DEFAULTS['org_name'] );
	}

	/**
	 * Get the display name that should be shown in user-facing finance flows.
	 *
	 * Uses Clubnaam first, then falls back to the legal organization name.
	 *
	 * @return string
	 */
	public function get_display_name(): string {
		$club_name = trim( ClubConfig::get_club_name() );
		if ( '' !== $club_name ) {
			return $club_name;
		}

		$org_name = trim( $this->get_org_name() );
		if ( '' !== $org_name ) {
			return $org_name;
		}

		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Get organization address
	 *
	 * @return string The organization address (empty string if not configured)
	 */
	public function get_org_address(): string {
		return get_option( self::OPTION_ORG_ADDRESS, self::DEFAULTS['org_address'] );
	}

	/**
	 * Get contact email
	 *
	 * @return string The contact email (empty string if not configured)
	 */
	public function get_contact_email(): string {
		return get_option( self::OPTION_CONTACT_EMAIL, self::DEFAULTS['contact_email'] );
	}

	/**
	 * Get IBAN
	 *
	 * @return string The IBAN (empty string if not configured)
	 */
	public function get_iban(): string {
		$default_account = 'mollie' === $this->get_active_payment_provider()
			? $this->get_default_mollie_account( 'manual' )
			: null;

		if ( is_array( $default_account ) && ! empty( $default_account['iban'] ) ) {
			return (string) $default_account['iban'];
		}

		return get_option( self::OPTION_IBAN, self::DEFAULTS['iban'] );
	}

	/**
	 * Get configured Mollie accounts without exposing API keys.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_mollie_accounts(): array {
		$stored_accounts = get_option( self::OPTION_MOLLIE_ACCOUNTS, [] );
		$normalized      = $this->normalize_mollie_accounts_for_storage( is_array( $stored_accounts ) ? $stored_accounts : [] );

		if ( is_wp_error( $normalized ) ) {
			return [];
		}

		return array_map(
			function ( array $account ): array {
				$api_key = $this->decrypt_mollie_account_api_key( $account );

				return [
					'id'           => (string) ( $account['id'] ?? '' ),
					'internal_name' => (string) ( $account['internal_name'] ?? '' ),
					'account_holder' => (string) ( $account['account_holder'] ?? '' ),
					'iban'         => (string) ( $account['iban'] ?? '' ),
					'has_api_key'  => '' !== $api_key,
					'environment'  => $this->derive_mollie_environment( $api_key ),
				];
			},
			$normalized
		);
	}

	/**
	 * Get a configured Mollie account by ID without exposing its API key.
	 *
	 * @param string $account_id Mollie account ID.
	 * @return array<string, string>|null
	 */
	public function get_mollie_account_by_id( string $account_id ): ?array {
		$account_id = sanitize_key( $account_id );
		if ( '' === $account_id ) {
			return null;
		}

		foreach ( $this->get_mollie_accounts() as $account ) {
			if ( ( $account['id'] ?? '' ) === $account_id ) {
				return $account;
			}
		}

		return null;
	}

	/**
	 * Get a configured Mollie account by ID including its decrypted API key.
	 *
	 * @param string $account_id Mollie account ID.
	 * @return array<string, string>|null
	 */
	private function get_mollie_account_record_by_id( string $account_id ): ?array {
		$account_id = sanitize_key( $account_id );
		if ( '' === $account_id ) {
			return null;
		}

		$stored_accounts = get_option( self::OPTION_MOLLIE_ACCOUNTS, [] );
		$normalized      = $this->normalize_mollie_accounts_for_storage( is_array( $stored_accounts ) ? $stored_accounts : [] );
		if ( is_wp_error( $normalized ) ) {
			return null;
		}

		foreach ( $normalized as $account ) {
			if ( ( $account['id'] ?? '' ) === $account_id ) {
				return $account;
			}
		}

		return null;
	}

	/**
	 * Get the decrypted Mollie API key for an account.
	 *
	 * @param string $account_id Mollie account ID.
	 * @return string
	 */
	public function get_mollie_api_key_for_account( string $account_id ): string {
		$account = $this->get_mollie_account_record_by_id( $account_id );
		if ( ! is_array( $account ) ) {
			return '';
		}

		return $this->decrypt_mollie_account_api_key( $account );
	}

	/**
	 * Get Mollie accounts that have an API key configured.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_usable_mollie_accounts(): array {
		return array_values(
			array_filter(
				$this->get_mollie_accounts(),
				static fn( array $account ): bool => ! empty( $account['has_api_key'] )
			)
		);
	}

	/**
	 * Get the configured default Mollie account ID for an invoice type.
	 *
	 * @param string $invoice_type Invoice type slug.
	 * @return string
	 */
	public function get_default_mollie_account_id( string $invoice_type ): string {
		return match ( $invoice_type ) {
			'membership' => (string) get_option( self::OPTION_MOLLIE_DEFAULT_MEMBERSHIP_ACCOUNT_ID, self::DEFAULTS['mollie_default_membership_account_id'] ),
			'discipline' => (string) get_option( self::OPTION_MOLLIE_DEFAULT_DISCIPLINE_ACCOUNT_ID, self::DEFAULTS['mollie_default_discipline_account_id'] ),
			default => (string) get_option( self::OPTION_MOLLIE_DEFAULT_MANUAL_ACCOUNT_ID, self::DEFAULTS['mollie_default_manual_account_id'] ),
		};
	}

	/**
	 * Get the default Mollie account for an invoice type.
	 *
	 * @param string $invoice_type Invoice type slug.
	 * @return array<string, string>|null
	 */
	public function get_default_mollie_account( string $invoice_type ): ?array {
		$account_id = $this->get_default_mollie_account_id( $invoice_type );
		if ( '' !== $account_id ) {
			$account = $this->get_mollie_account_by_id( $account_id );
			if ( is_array( $account ) && ! empty( $account['has_api_key'] ) ) {
				return $account;
			}
		}

		$usable_accounts = $this->get_usable_mollie_accounts();
		if ( 1 === count( $usable_accounts ) ) {
			return $usable_accounts[0];
		}

		return null;
	}

	/**
	 * Build a payment-account snapshot for the given invoice type.
	 *
	 * @param string $invoice_type Invoice type slug.
	 * @param string $requested_account_id Optional override account ID for manual invoices.
	 * @return array<string, string>|\WP_Error
	 */
	public function get_payment_account_snapshot_for_invoice_type( string $invoice_type, string $requested_account_id = '' ) {
		$provider = $this->get_active_payment_provider();
		if ( 'mollie' !== $provider ) {
			return [
				'id'              => '',
				'internal_name'   => '',
				'account_holder'  => $this->get_org_name(),
				'iban'            => $this->get_iban(),
				'linked_provider' => $provider,
			];
		}

		$account = null;
		if ( 'manual' === $invoice_type && '' !== $requested_account_id ) {
			$account = $this->get_mollie_account_by_id( $requested_account_id );
			if ( ! is_array( $account ) || empty( $account['has_api_key'] ) ) {
				return new \WP_Error(
					'invalid_payment_account',
					__( 'De gekozen Mollie-rekening bestaat niet of heeft geen API-sleutel.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}
		}

		if ( ! is_array( $account ) ) {
			$account = $this->get_default_mollie_account( $invoice_type );
		}

		if ( ! is_array( $account ) ) {
			return new \WP_Error(
				'mollie_account_not_configured',
				__( 'Er is geen standaard Mollie-rekening ingesteld voor dit factuurtype.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		return [
			'id'              => (string) ( $account['id'] ?? '' ),
			'internal_name'   => (string) ( $account['internal_name'] ?? '' ),
			'account_holder'  => (string) ( $account['account_holder'] ?? '' ),
			'iban'            => (string) ( $account['iban'] ?? '' ),
			'linked_provider' => 'mollie',
		];
	}

	/**
	 * Get payment term in days
	 *
	 * @return int Payment term in days
	 */
	public function get_payment_term_days(): int {
		return (int) get_option( self::OPTION_PAYMENT_TERM_DAYS, self::DEFAULTS['payment_term_days'] );
	}

	/**
	 * Get payment clause text
	 *
	 * @return string The payment clause text (empty string if not configured)
	 */
	public function get_payment_clause(): string {
		return get_option( self::OPTION_PAYMENT_CLAUSE, self::DEFAULTS['payment_clause'] );
	}

	/**
	 * Get email template
	 *
	 * @return string The email template (default template if not configured)
	 */
	public function get_email_template(): string {
		return get_option( self::OPTION_EMAIL_TEMPLATE, self::DEFAULTS['email_template'] );
	}

	/**
	 * Get installment email template
	 *
	 * Sent when an installment becomes due.
	 *
	 * @return string The installment email template (default template if not configured)
	 */
	public function get_installment_email_template(): string {
		return get_option( self::OPTION_INSTALLMENT_EMAIL_TEMPLATE, self::DEFAULTS['installment_email_template'] );
	}

	/**
	 * Get first reminder email template
	 *
	 * Sent 14 days after the installment due date when still unpaid.
	 *
	 * @return string The first reminder email template (default template if not configured)
	 */
	public function get_reminder_1_email_template(): string {
		return get_option( self::OPTION_REMINDER_1_EMAIL_TEMPLATE, self::DEFAULTS['reminder_1_email_template'] );
	}

	/**
	 * Get second reminder email template
	 *
	 * Sent 21 days after the installment due date when still unpaid.
	 * The treasurer receives a BCC.
	 *
	 * @return string The second reminder email template (default template if not configured)
	 */
	public function get_reminder_2_email_template(): string {
		return get_option( self::OPTION_REMINDER_2_EMAIL_TEMPLATE, self::DEFAULTS['reminder_2_email_template'] );
	}

	/**
	 * Get membership (contributie) email template
	 *
	 * Sent when a membership invoice is emailed. Uses a clean template without
	 * discipline/tuchtcommissie references, unlike the discipline invoice template.
	 *
	 * @return string The membership email template (default template if not configured)
	 */
	public function get_membership_email_template(): string {
		return get_option( self::OPTION_MEMBERSHIP_EMAIL_TEMPLATE, self::DEFAULTS['membership_email_template'] );
	}

	/**
	 * Get membership (contributie) payment clause text
	 *
	 * Shown at the bottom of the membership invoice PDF payment section.
	 *
	 * @return string The membership payment clause text (empty string if not configured)
	 */
	public function get_membership_payment_clause(): string {
		return get_option( self::OPTION_MEMBERSHIP_PAYMENT_CLAUSE, self::DEFAULTS['membership_payment_clause'] );
	}

	/**
	 * Get first invoice reminder email template
	 *
	 * Sent 14 days after the invoice sent_date when member hasn't selected a payment plan.
	 *
	 * @return string The first invoice reminder email template (default template if not configured)
	 */
	public function get_invoice_reminder_1_email_template(): string {
		return get_option( self::OPTION_INVOICE_REMINDER_1_EMAIL_TEMPLATE, self::DEFAULTS['invoice_reminder_1_email_template'] );
	}

	/**
	 * Get second invoice reminder email template
	 *
	 * Sent 28 days after the invoice sent_date when member hasn't selected a payment plan.
	 * The treasurer receives a BCC.
	 *
	 * @return string The second invoice reminder email template (default template if not configured)
	 */
	public function get_invoice_reminder_2_email_template(): string {
		return get_option( self::OPTION_INVOICE_REMINDER_2_EMAIL_TEMPLATE, self::DEFAULTS['invoice_reminder_2_email_template'] );
	}

	/**
	 * Get credit invoice email template
	 *
	 * Sent when a credit invoice is emailed. Uses a template without payment link
	 * or QR code references, since credit invoices represent money owed TO the member.
	 *
	 * @return string The credit email template (default template if not configured)
	 */
	public function get_credit_email_template(): string {
		return get_option( self::OPTION_CREDIT_EMAIL_TEMPLATE, self::DEFAULTS['credit_email_template'] );
	}

	/**
	 * Get regular invoice email subject template
	 *
	 * @return string Subject template for manual/regular invoices.
	 */
	public function get_regular_invoice_email_subject(): string {
		return get_option( self::OPTION_REGULAR_INVOICE_EMAIL_SUBJECT, self::DEFAULTS['regular_invoice_email_subject'] );
	}

	/**
	 * Get regular invoice email body template
	 *
	 * @return string Body template for manual/regular invoices.
	 */
	public function get_regular_invoice_email_body(): string {
		return get_option( self::OPTION_REGULAR_INVOICE_EMAIL_BODY, self::DEFAULTS['regular_invoice_email_body'] );
	}

	public function get_email_heading( string $type ): string {
		$key    = $type . '_email_heading';
		$option = match ( $type ) {
			'regular_invoice'    => self::OPTION_REGULAR_INVOICE_EMAIL_HEADING,
			'discipline'         => self::OPTION_DISCIPLINE_EMAIL_HEADING,
			'membership'         => self::OPTION_MEMBERSHIP_EMAIL_HEADING,
			'installment'        => self::OPTION_INSTALLMENT_EMAIL_HEADING,
			'reminder_1'         => self::OPTION_REMINDER_1_EMAIL_HEADING,
			'reminder_2'         => self::OPTION_REMINDER_2_EMAIL_HEADING,
			'invoice_reminder_1' => self::OPTION_INVOICE_REMINDER_1_EMAIL_HEADING,
			'invoice_reminder_2' => self::OPTION_INVOICE_REMINDER_2_EMAIL_HEADING,
			'credit'             => self::OPTION_CREDIT_EMAIL_HEADING,
			default              => null,
		};

		if ( null === $option ) {
			return '';
		}

		return get_option( $option, self::DEFAULTS[ $key ] ?? '' );
	}

	/**
	 * Get club logo ID
	 *
	 * @return int The club logo attachment ID (0 if not configured)
	 */
	public function get_club_logo_id(): int {
		return (int) get_option( self::OPTION_CLUB_LOGO_ID, self::DEFAULTS['club_logo_id'] );
	}

	/**
	 * Get accent color
	 *
	 * @return string The accent color hex code (empty string if not configured, defaults to #0891b2)
	 */
	public function get_accent_color(): string {
		return get_option( self::OPTION_ACCENT_COLOR, self::DEFAULTS['accent_color'] );
	}

	/**
	 * Get accent background color
	 *
	 * @return string The accent background color hex code (empty string if not configured)
	 */
	public function get_accent_background_color(): string {
		return get_option( self::OPTION_ACCENT_BACKGROUND_COLOR, self::DEFAULTS['accent_background_color'] );
	}

	/**
	 * Get BCC email for invoice sending
	 *
	 * @return string The BCC email address (empty string if not configured)
	 */
	public function get_bcc_email(): string {
		return get_option( self::OPTION_BCC_EMAIL, self::DEFAULTS['bcc_email'] );
	}

	/**
	 * Get administration fee per invoice
	 *
	 * @return float Administration fee amount (0.00 if not configured)
	 */
	public function get_admin_fee(): float {
		return (float) get_option( self::OPTION_ADMIN_FEE, self::DEFAULTS['admin_fee'] );
	}

	/**
	 * Get per-installment administration fee for membership payment plans
	 *
	 * This fee is charged per installment when a member chooses a multi-installment
	 * payment plan (3 or 8 installments). It is separate from the discipline invoice
	 * admin fee (OPTION_ADMIN_FEE).
	 *
	 * @return float Per-installment administration fee amount (0.00 if not configured)
	 */
	public function get_installment_admin_fee(): float {
		return (float) get_option( self::OPTION_INSTALLMENT_ADMIN_FEE, self::DEFAULTS['installment_admin_fee'] );
	}

	/**
	 * Get Rabobank credentials (decrypted, internal use only)
	 *
	 * @return array|null Array with client_id, client_secret, environment or null if not configured
	 */
	public function get_rabobank_credentials(): ?array {
		$encrypted = get_option( self::OPTION_RABOBANK_CREDENTIALS, '' );

		if ( empty( $encrypted ) ) {
			return null;
		}

		return CredentialEncryption::decrypt( $encrypted );
	}

	/**
	 * Get all configuration settings
	 *
	 * Returns safe representation of settings - Rabobank credentials are NOT exposed,
	 * only whether they exist and which environment is configured.
	 *
	 * @return array<string, mixed> Array of all configuration settings
	 */
	public function get_all_settings(): array {
		$rabobank_creds = $this->get_rabobank_credentials();
		$club_logo_id   = $this->get_club_logo_id();
		$club_logo_url  = '';
		if ( $club_logo_id > 0 ) {
			$url = wp_get_attachment_url( $club_logo_id );
			if ( $url ) {
				$club_logo_url = $url;
			}
		}

		$apple_cert_id  = $this->get_membership_pass_apple_cert_attachment_id();
		$google_sa_id   = $this->get_membership_pass_google_service_account_attachment_id();
		$apple_cert_url = $apple_cert_id > 0 ? ( wp_get_attachment_url( $apple_cert_id ) ?: '' ) : '';
		$google_sa_url  = $google_sa_id > 0 ? ( wp_get_attachment_url( $google_sa_id ) ?: '' ) : '';

		return [
			'org_name'              => $this->get_org_name(),
			'org_address'           => $this->get_org_address(),
			'contact_email'         => $this->get_contact_email(),
			'iban'                  => $this->get_iban(),
			'mollie_accounts'       => $this->get_mollie_accounts(),
			'payment_term_days'     => $this->get_payment_term_days(),
			'payment_clause'        => $this->get_payment_clause(),
			'membership_payment_clause' => $this->get_membership_payment_clause(),
			'email_template'             => $this->get_email_template(),
			'membership_email_template'  => $this->get_membership_email_template(),
			'installment_email_template' => $this->get_installment_email_template(),
			'reminder_1_email_template'  => $this->get_reminder_1_email_template(),
			'reminder_2_email_template'  => $this->get_reminder_2_email_template(),
			'invoice_reminder_1_email_template' => $this->get_invoice_reminder_1_email_template(),
			'invoice_reminder_2_email_template' => $this->get_invoice_reminder_2_email_template(),
			'credit_email_template'              => $this->get_credit_email_template(),
			'regular_invoice_email_subject' => $this->get_regular_invoice_email_subject(),
			'regular_invoice_email_body'    => $this->get_regular_invoice_email_body(),
			'regular_invoice_email_heading'    => $this->get_email_heading( 'regular_invoice' ),
			'discipline_email_heading'         => $this->get_email_heading( 'discipline' ),
			'membership_email_heading'         => $this->get_email_heading( 'membership' ),
			'installment_email_heading'        => $this->get_email_heading( 'installment' ),
			'reminder_1_email_heading'         => $this->get_email_heading( 'reminder_1' ),
			'reminder_2_email_heading'         => $this->get_email_heading( 'reminder_2' ),
			'invoice_reminder_1_email_heading' => $this->get_email_heading( 'invoice_reminder_1' ),
			'invoice_reminder_2_email_heading' => $this->get_email_heading( 'invoice_reminder_2' ),
			'credit_email_heading'             => $this->get_email_heading( 'credit' ),
			'club_logo_id'          => $club_logo_id,
			'club_logo_url'         => $club_logo_url,
			'accent_color'          => $this->get_accent_color(),
			'accent_background_color' => $this->get_accent_background_color(),
			'bcc_email'             => $this->get_bcc_email(),
			'admin_fee'             => $this->get_admin_fee(),
			'installment_admin_fee' => $this->get_installment_admin_fee(),
			'rabobank_has_credentials' => $rabobank_creds !== null,
			'rabobank_environment'  => $rabobank_creds['environment'] ?? '',
			'mollie_redirect_url'   => $this->get_mollie_redirect_url(),
			'mollie_default_membership_account_id' => $this->get_default_mollie_account_id( 'membership' ),
			'mollie_default_discipline_account_id' => $this->get_default_mollie_account_id( 'discipline' ),
			'mollie_default_manual_account_id'     => $this->get_default_mollie_account_id( 'manual' ),
			'active_payment_provider' => $this->get_active_payment_provider(),
			'membership_pass_apple_cert_attachment_id' => $apple_cert_id,
			'membership_pass_apple_cert_url'           => $apple_cert_url,
			'membership_pass_apple_has_cert_password'  => '' !== $this->get_membership_pass_apple_cert_password(),
			'membership_pass_apple_pass_type_identifier' => $this->get_membership_pass_apple_pass_type_identifier(),
			'membership_pass_apple_team_identifier'      => $this->get_membership_pass_apple_team_identifier(),
			'membership_pass_apple_organization_name'    => $this->get_membership_pass_apple_organization_name(),
			'membership_pass_google_service_account_attachment_id' => $google_sa_id,
			'membership_pass_google_service_account_url'           => $google_sa_url,
			'membership_pass_google_issuer_id'                     => $this->get_membership_pass_google_issuer_id(),
			'membership_pass_google_class_suffix'                  => $this->get_membership_pass_google_class_suffix(),
		];
	}

	/**
	 * Get individual setting by key
	 *
	 * @param string $key Setting key
	 * @return mixed Setting value
	 */
	public function get_setting( string $key ) {
		switch ( $key ) {
			case 'org_name':
				return $this->get_org_name();
			case 'org_address':
				return $this->get_org_address();
			case 'contact_email':
				return $this->get_contact_email();
			case 'iban':
				return $this->get_iban();
			case 'mollie_accounts':
				return $this->get_mollie_accounts();
			case 'payment_term_days':
				return $this->get_payment_term_days();
			case 'payment_clause':
				return $this->get_payment_clause();
			case 'membership_payment_clause':
				return $this->get_membership_payment_clause();
			case 'email_template':
				return $this->get_email_template();
			case 'membership_email_template':
				return $this->get_membership_email_template();
			case 'installment_email_template':
				return $this->get_installment_email_template();
			case 'reminder_1_email_template':
				return $this->get_reminder_1_email_template();
			case 'reminder_2_email_template':
				return $this->get_reminder_2_email_template();
			case 'invoice_reminder_1_email_template':
				return $this->get_invoice_reminder_1_email_template();
			case 'invoice_reminder_2_email_template':
				return $this->get_invoice_reminder_2_email_template();
			case 'credit_email_template':
				return $this->get_credit_email_template();
			case 'regular_invoice_email_subject':
				return $this->get_regular_invoice_email_subject();
			case 'regular_invoice_email_body':
				return $this->get_regular_invoice_email_body();
			case 'club_logo_id':
				return $this->get_club_logo_id();
			case 'accent_color':
				return $this->get_accent_color();
			case 'accent_background_color':
				return $this->get_accent_background_color();
			case 'bcc_email':
				return $this->get_bcc_email();
			case 'admin_fee':
				return $this->get_admin_fee();
			case 'installment_admin_fee':
				return $this->get_installment_admin_fee();
			default:
				return null;
		}
	}

	/**
	 * Update multiple settings at once
	 *
	 * @param array $data Associative array of settings to update
	 * @return bool True on success
	 */
	public function update_settings( array $data ) {
		$success = true;

		// Handle regular text fields
		if ( isset( $data['org_name'] ) ) {
			$success = update_option( self::OPTION_ORG_NAME, sanitize_text_field( $data['org_name'] ) ) && $success;
		}

		if ( isset( $data['org_address'] ) ) {
			$success = update_option( self::OPTION_ORG_ADDRESS, sanitize_textarea_field( $data['org_address'] ) ) && $success;
		}

		if ( isset( $data['contact_email'] ) ) {
			$success = update_option( self::OPTION_CONTACT_EMAIL, sanitize_email( $data['contact_email'] ) ) && $success;
		}

		if ( isset( $data['iban'] ) ) {
			// IBAN: uppercase, strip spaces
			$iban = strtoupper( str_replace( ' ', '', sanitize_text_field( $data['iban'] ) ) );
			$success = update_option( self::OPTION_IBAN, $iban ) && $success;
		}

		$resolved_mollie_accounts = null;
		if ( isset( $data['mollie_accounts'] ) ) {
			$resolved_mollie_accounts = $this->normalize_mollie_accounts_for_storage( is_array( $data['mollie_accounts'] ) ? $data['mollie_accounts'] : [] );
			if ( is_wp_error( $resolved_mollie_accounts ) ) {
				return $resolved_mollie_accounts;
			}

			$success = update_option( self::OPTION_MOLLIE_ACCOUNTS, $resolved_mollie_accounts ) && $success;

			if ( ! empty( $resolved_mollie_accounts ) ) {
				$default_iban = (string) ( $resolved_mollie_accounts[0]['iban'] ?? '' );
				if ( '' !== $default_iban ) {
					$success = update_option( self::OPTION_IBAN, $default_iban ) && $success;
				}
			}
		}

		if ( isset( $data['payment_term_days'] ) ) {
			$days = max( 1, absint( $data['payment_term_days'] ) );
			$success = update_option( self::OPTION_PAYMENT_TERM_DAYS, $days ) && $success;
		}

		if ( isset( $data['payment_clause'] ) ) {
			$success = update_option( self::OPTION_PAYMENT_CLAUSE, sanitize_textarea_field( $data['payment_clause'] ) ) && $success;
		}

		if ( isset( $data['membership_payment_clause'] ) ) {
			$success = update_option( self::OPTION_MEMBERSHIP_PAYMENT_CLAUSE, sanitize_textarea_field( $data['membership_payment_clause'] ) ) && $success;
		}

		if ( isset( $data['email_template'] ) ) {
			$success = update_option( self::OPTION_EMAIL_TEMPLATE, wp_kses_post( $data['email_template'] ) ) && $success;
		}

		if ( isset( $data['membership_email_template'] ) ) {
			$success = update_option( self::OPTION_MEMBERSHIP_EMAIL_TEMPLATE, wp_kses_post( $data['membership_email_template'] ) ) && $success;
		}

		if ( isset( $data['installment_email_template'] ) ) {
			$success = update_option( self::OPTION_INSTALLMENT_EMAIL_TEMPLATE, wp_kses_post( $data['installment_email_template'] ) ) && $success;
		}

		if ( isset( $data['reminder_1_email_template'] ) ) {
			$success = update_option( self::OPTION_REMINDER_1_EMAIL_TEMPLATE, wp_kses_post( $data['reminder_1_email_template'] ) ) && $success;
		}

		if ( isset( $data['reminder_2_email_template'] ) ) {
			$success = update_option( self::OPTION_REMINDER_2_EMAIL_TEMPLATE, wp_kses_post( $data['reminder_2_email_template'] ) ) && $success;
		}

		if ( isset( $data['invoice_reminder_1_email_template'] ) ) {
			$success = update_option( self::OPTION_INVOICE_REMINDER_1_EMAIL_TEMPLATE, wp_kses_post( $data['invoice_reminder_1_email_template'] ) ) && $success;
		}

		if ( isset( $data['invoice_reminder_2_email_template'] ) ) {
			$success = update_option( self::OPTION_INVOICE_REMINDER_2_EMAIL_TEMPLATE, wp_kses_post( $data['invoice_reminder_2_email_template'] ) ) && $success;
		}

		if ( isset( $data['credit_email_template'] ) ) {
			$success = update_option( self::OPTION_CREDIT_EMAIL_TEMPLATE, wp_kses_post( $data['credit_email_template'] ) ) && $success;
		}

		if ( isset( $data['regular_invoice_email_subject'] ) ) {
			$success = update_option( self::OPTION_REGULAR_INVOICE_EMAIL_SUBJECT, sanitize_text_field( $data['regular_invoice_email_subject'] ) ) && $success;
		}

		if ( isset( $data['regular_invoice_email_body'] ) ) {
			$success = update_option( self::OPTION_REGULAR_INVOICE_EMAIL_BODY, wp_kses_post( $data['regular_invoice_email_body'] ) ) && $success;
		}

		$heading_fields = [
			'regular_invoice_email_heading'    => self::OPTION_REGULAR_INVOICE_EMAIL_HEADING,
			'discipline_email_heading'         => self::OPTION_DISCIPLINE_EMAIL_HEADING,
			'membership_email_heading'         => self::OPTION_MEMBERSHIP_EMAIL_HEADING,
			'installment_email_heading'        => self::OPTION_INSTALLMENT_EMAIL_HEADING,
			'reminder_1_email_heading'         => self::OPTION_REMINDER_1_EMAIL_HEADING,
			'reminder_2_email_heading'         => self::OPTION_REMINDER_2_EMAIL_HEADING,
			'invoice_reminder_1_email_heading' => self::OPTION_INVOICE_REMINDER_1_EMAIL_HEADING,
			'invoice_reminder_2_email_heading' => self::OPTION_INVOICE_REMINDER_2_EMAIL_HEADING,
			'credit_email_heading'             => self::OPTION_CREDIT_EMAIL_HEADING,
		];

		foreach ( $heading_fields as $key => $option ) {
			if ( isset( $data[ $key ] ) ) {
				$success = update_option( $option, sanitize_text_field( $data[ $key ] ) ) && $success;
			}
		}

		if ( isset( $data['club_logo_id'] ) ) {
			$logo_id = absint( $data['club_logo_id'] );
			$success = update_option( self::OPTION_CLUB_LOGO_ID, $logo_id ) && $success;
		}

		if ( isset( $data['accent_color'] ) ) {
			$color = sanitize_hex_color( $data['accent_color'] ) ?? '';
			$success = update_option( self::OPTION_ACCENT_COLOR, $color ) && $success;
		}

		if ( isset( $data['accent_background_color'] ) ) {
			$color   = sanitize_hex_color( $data['accent_background_color'] ) ?? '';
			$success = update_option( self::OPTION_ACCENT_BACKGROUND_COLOR, $color ) && $success;
		}

		if ( isset( $data['bcc_email'] ) ) {
			$success = update_option( self::OPTION_BCC_EMAIL, sanitize_email( $data['bcc_email'] ) ) && $success;
		}

		if ( isset( $data['admin_fee'] ) ) {
			$fee     = max( 0.0, (float) $data['admin_fee'] );
			$success = update_option( self::OPTION_ADMIN_FEE, $fee ) && $success;
		}

		if ( isset( $data['installment_admin_fee'] ) ) {
			$fee     = max( 0.0, (float) $data['installment_admin_fee'] );
			$success = update_option( self::OPTION_INSTALLMENT_ADMIN_FEE, $fee ) && $success;
		}

		if ( isset( $data['membership_pass_apple_cert_attachment_id'] ) ) {
			$success = update_option(
				self::OPTION_MEMBERSHIP_PASS_APPLE_CERT_ATTACHMENT_ID,
				absint( $data['membership_pass_apple_cert_attachment_id'] )
			) && $success;
		}

		if ( isset( $data['membership_pass_apple_cert_password'] ) ) {
			$password = sanitize_text_field( $data['membership_pass_apple_cert_password'] );
			if ( '' !== $password ) {
				$success = update_option( self::OPTION_MEMBERSHIP_PASS_APPLE_CERT_PASSWORD, $password ) && $success;
			}
		}

		if ( isset( $data['membership_pass_apple_pass_type_identifier'] ) ) {
			$success = update_option(
				self::OPTION_MEMBERSHIP_PASS_APPLE_PASS_TYPE_IDENTIFIER,
				sanitize_text_field( $data['membership_pass_apple_pass_type_identifier'] )
			) && $success;
		}

		if ( isset( $data['membership_pass_apple_team_identifier'] ) ) {
			$success = update_option(
				self::OPTION_MEMBERSHIP_PASS_APPLE_TEAM_IDENTIFIER,
				sanitize_text_field( $data['membership_pass_apple_team_identifier'] )
			) && $success;
		}

		if ( isset( $data['membership_pass_apple_organization_name'] ) ) {
			$success = update_option(
				self::OPTION_MEMBERSHIP_PASS_APPLE_ORGANIZATION_NAME,
				sanitize_text_field( $data['membership_pass_apple_organization_name'] )
			) && $success;
		}

		if ( isset( $data['membership_pass_google_service_account_attachment_id'] ) ) {
			$success = update_option(
				self::OPTION_MEMBERSHIP_PASS_GOOGLE_SERVICE_ACCOUNT_ATTACHMENT_ID,
				absint( $data['membership_pass_google_service_account_attachment_id'] )
			) && $success;
		}

		if ( isset( $data['membership_pass_google_issuer_id'] ) ) {
			$success = update_option(
				self::OPTION_MEMBERSHIP_PASS_GOOGLE_ISSUER_ID,
				sanitize_text_field( $data['membership_pass_google_issuer_id'] )
			) && $success;
		}

		if ( isset( $data['membership_pass_google_class_suffix'] ) ) {
			$success = update_option(
				self::OPTION_MEMBERSHIP_PASS_GOOGLE_CLASS_SUFFIX,
				sanitize_key( $data['membership_pass_google_class_suffix'] )
			) && $success;
		}

		// Handle Rabobank credentials with encryption
		if ( isset( $data['rabobank_client_id'] ) && isset( $data['rabobank_client_secret'] ) && isset( $data['rabobank_environment'] ) ) {
			$success = $this->update_rabobank_credentials(
				sanitize_text_field( $data['rabobank_client_id'] ),
				sanitize_text_field( $data['rabobank_client_secret'] ),
				sanitize_text_field( $data['rabobank_environment'] )
			) && $success;
		} elseif ( isset( $data['rabobank_environment'] ) ) {
			// Only environment change - decrypt, update environment, re-encrypt
			$existing = $this->get_rabobank_credentials();
			if ( $existing ) {
				$existing['environment'] = sanitize_text_field( $data['rabobank_environment'] );
				$encrypted = CredentialEncryption::encrypt( $existing );
				$success = update_option( self::OPTION_RABOBANK_CREDENTIALS, $encrypted ) && $success;
			}
		}

		if ( isset( $data['mollie_redirect_url'] ) ) {
			$success = update_option( self::OPTION_MOLLIE_REDIRECT_URL, esc_url_raw( $data['mollie_redirect_url'] ) ) && $success;
		}

		$current_mollie_accounts = is_array( $resolved_mollie_accounts )
			? $this->build_safe_mollie_accounts_from_storage( $resolved_mollie_accounts )
			: $this->get_mollie_accounts();

		$default_keys = [
			'mollie_default_membership_account_id' => self::OPTION_MOLLIE_DEFAULT_MEMBERSHIP_ACCOUNT_ID,
			'mollie_default_discipline_account_id' => self::OPTION_MOLLIE_DEFAULT_DISCIPLINE_ACCOUNT_ID,
			'mollie_default_manual_account_id'     => self::OPTION_MOLLIE_DEFAULT_MANUAL_ACCOUNT_ID,
		];

		$usable_account_ids = array_map(
			static fn( array $account ): string => (string) $account['id'],
			array_filter(
				$current_mollie_accounts,
				static fn( array $account ): bool => ! empty( $account['has_api_key'] )
			)
		);

		foreach ( $default_keys as $key => $option ) {
			if ( isset( $data[ $key ] ) ) {
				$next_value = sanitize_key( (string) $data[ $key ] );
			} else {
				$next_value = (string) get_option( $option, self::DEFAULTS[ $key ] );
			}

			if ( '' !== $next_value && ! in_array( $next_value, $usable_account_ids, true ) ) {
				return new \WP_Error(
					'invalid_mollie_default_account',
					__( 'Een standaard Mollie-rekening moet een bestaande rekening met API-sleutel zijn.', 'rondo' ),
					[ 'status' => 400 ]
				);
			}

			if ( '' === $next_value && 1 === count( $usable_account_ids ) ) {
				$next_value = $usable_account_ids[0];
			}

			if ( isset( $data[ $key ] ) || null !== $resolved_mollie_accounts ) {
				$success = update_option( $option, $next_value ) && $success;
			}
		}

		// Handle active payment provider
		if ( isset( $data['active_payment_provider'] ) ) {
			$success = $this->update_active_payment_provider(
				sanitize_text_field( $data['active_payment_provider'] )
			) && $success;
		}

		return $success;
	}

	/**
	 * Normalize and validate Mollie accounts before storage.
	 *
	 * @param array $accounts Raw bank accounts payload.
	 * @return array<int, array<string, string>>|\WP_Error
	 */
	private function normalize_mollie_accounts_for_storage( array $accounts ) {
		$normalized        = [];
		$existing_accounts = get_option( self::OPTION_MOLLIE_ACCOUNTS, [] );
		$existing_by_id    = [];

		if ( is_array( $existing_accounts ) ) {
			foreach ( $existing_accounts as $existing_account ) {
				if ( is_array( $existing_account ) && ! empty( $existing_account['id'] ) ) {
					$existing_by_id[ sanitize_key( (string) $existing_account['id'] ) ] = $existing_account;
				}
			}
		}

		foreach ( $accounts as $index => $account ) {
			if ( ! is_array( $account ) ) {
				continue;
			}

			$internal_name  = sanitize_text_field( (string) ( $account['internal_name'] ?? '' ) );
			$account_holder = sanitize_text_field( (string) ( $account['account_holder'] ?? '' ) );
			$iban           = strtoupper( str_replace( ' ', '', sanitize_text_field( (string) ( $account['iban'] ?? '' ) ) ) );
			$account_id      = sanitize_key( (string) ( $account['id'] ?? '' ) );
			$api_key         = sanitize_text_field( (string) ( $account['api_key'] ?? '' ) );

			if ( '' === $internal_name && '' === $account_holder && '' === $iban && '' === $api_key ) {
				continue;
			}

			if ( '' === $internal_name || '' === $account_holder || '' === $iban ) {
				return new \WP_Error(
					'invalid_mollie_account',
					sprintf( __( 'Mollie-rekening %d is onvolledig. Vul interne naam, tenaamstelling en IBAN in.', 'rondo' ), (int) $index + 1 ),
					[ 'status' => 400 ]
				);
			}

			if ( '' === $account_id ) {
				$account_id = 'mollie-' . sanitize_key( wp_generate_uuid4() );
			}

			$encrypted_api_key = (string) ( $existing_by_id[ $account_id ]['api_key_encrypted'] ?? '' );
			if ( '' !== $api_key ) {
				$encrypted_api_key = CredentialEncryption::encrypt( [ 'api_key' => $api_key ] );
			}

			$normalized[] = [
				'id'                => $account_id,
				'internal_name'     => $internal_name,
				'account_holder'    => $account_holder,
				'iban'              => $iban,
				'api_key_encrypted' => $encrypted_api_key,
			];
		}

		return array_values( $normalized );
	}

	/**
	 * Convert stored Mollie accounts into their safe API representation.
	 *
	 * @param array<int, array<string, string>> $accounts Stored Mollie accounts.
	 * @return array<int, array<string, string|bool>>
	 */
	private function build_safe_mollie_accounts_from_storage( array $accounts ): array {
		return array_map(
			function ( array $account ): array {
				$api_key = $this->decrypt_mollie_account_api_key( $account );

				return [
					'id'           => (string) ( $account['id'] ?? '' ),
					'internal_name' => (string) ( $account['internal_name'] ?? '' ),
					'account_holder' => (string) ( $account['account_holder'] ?? '' ),
					'iban'         => (string) ( $account['iban'] ?? '' ),
					'has_api_key'  => '' !== $api_key,
					'environment'  => $this->derive_mollie_environment( $api_key ),
				];
			},
			$accounts
		);
	}

	/**
	 * Get Apple cert media attachment ID.
	 *
	 * @return int
	 */
	public function get_membership_pass_apple_cert_attachment_id(): int {
		return (int) get_option( self::OPTION_MEMBERSHIP_PASS_APPLE_CERT_ATTACHMENT_ID, 0 );
	}

	/**
	 * Get Apple cert password.
	 *
	 * @return string
	 */
	public function get_membership_pass_apple_cert_password(): string {
		return (string) get_option( self::OPTION_MEMBERSHIP_PASS_APPLE_CERT_PASSWORD, '' );
	}

	/**
	 * Get Apple pass type identifier.
	 *
	 * @return string
	 */
	public function get_membership_pass_apple_pass_type_identifier(): string {
		return (string) get_option( self::OPTION_MEMBERSHIP_PASS_APPLE_PASS_TYPE_IDENTIFIER, '' );
	}

	/**
	 * Get Apple team identifier.
	 *
	 * @return string
	 */
	public function get_membership_pass_apple_team_identifier(): string {
		return (string) get_option( self::OPTION_MEMBERSHIP_PASS_APPLE_TEAM_IDENTIFIER, '' );
	}

	/**
	 * Get Apple organization name override.
	 *
	 * @return string
	 */
	public function get_membership_pass_apple_organization_name(): string {
		return (string) get_option( self::OPTION_MEMBERSHIP_PASS_APPLE_ORGANIZATION_NAME, '' );
	}

	/**
	 * Get Google service account media attachment ID.
	 *
	 * @return int
	 */
	public function get_membership_pass_google_service_account_attachment_id(): int {
		return (int) get_option( self::OPTION_MEMBERSHIP_PASS_GOOGLE_SERVICE_ACCOUNT_ATTACHMENT_ID, 0 );
	}

	/**
	 * Get Google Wallet issuer ID.
	 *
	 * @return string
	 */
	public function get_membership_pass_google_issuer_id(): string {
		return (string) get_option( self::OPTION_MEMBERSHIP_PASS_GOOGLE_ISSUER_ID, '' );
	}

	/**
	 * Get Google Wallet class suffix.
	 *
	 * @return string
	 */
	public function get_membership_pass_google_class_suffix(): string {
		$value = (string) get_option(
			self::OPTION_MEMBERSHIP_PASS_GOOGLE_CLASS_SUFFIX,
			self::DEFAULTS['membership_pass_google_class_suffix']
		);
		$value = sanitize_key( $value );
		return '' !== $value ? $value : self::DEFAULTS['membership_pass_google_class_suffix'];
	}

	/**
	 * Update Rabobank credentials (encrypts and stores)
	 *
	 * @param string $client_id     Rabobank API client ID
	 * @param string $client_secret Rabobank API client secret
	 * @param string $environment   Environment (sandbox or production)
	 * @return bool True on success
	 */
	public function update_rabobank_credentials( string $client_id, string $client_secret, string $environment ): bool {
		$credentials = [
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'environment'   => $environment,
		];

		$encrypted = CredentialEncryption::encrypt( $credentials );
		return update_option( self::OPTION_RABOBANK_CREDENTIALS, $encrypted );
	}

	/**
	 * Decrypt the stored API key for a Mollie account.
	 *
	 * @param array<string, string> $account Stored Mollie account record.
	 * @return string
	 */
	private function decrypt_mollie_account_api_key( array $account ): string {
		$encrypted = (string) ( $account['api_key_encrypted'] ?? '' );
		if ( '' === $encrypted ) {
			return '';
		}

		$data = CredentialEncryption::decrypt( $encrypted );
		return (string) ( $data['api_key'] ?? '' );
	}

	/**
	 * Get Mollie redirect URL (where payer lands after payment)
	 *
	 * @return string The redirect URL, or empty string if not configured.
	 */
	public function get_mollie_redirect_url(): string {
		return get_option( self::OPTION_MOLLIE_REDIRECT_URL, '' );
	}

	/**
	 * Get active payment provider
	 *
	 * @return string Active payment provider slug ('rabobank' or 'mollie'). Defaults to 'rabobank'.
	 */
	public function get_active_payment_provider(): string {
		return get_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, 'rabobank' );
	}

	/**
	 * Update active payment provider
	 *
	 * @param string $provider Payment provider slug ('rabobank' or 'mollie')
	 * @return bool True on success, false for invalid provider
	 */
	public function update_active_payment_provider( string $provider ): bool {
		$allowed = [ 'rabobank', 'mollie' ];

		if ( ! in_array( $provider, $allowed, true ) ) {
			return false;
		}

		return update_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, $provider );
	}

	/**
	 * Derive Mollie environment from API key prefix
	 *
	 * @param string $api_key Mollie API key
	 * @return string 'live', 'test', or '' if no key is set
	 */
	private function derive_mollie_environment( string $api_key ): string {
		if ( empty( $api_key ) ) {
			return '';
		}

		return str_starts_with( $api_key, 'live_' ) ? 'live' : 'test';
	}

}
