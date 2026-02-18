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
	const OPTION_PAYMENT_TERM_DAYS     = 'rondo_finance_payment_term_days';
	const OPTION_PAYMENT_CLAUSE        = 'rondo_finance_payment_clause';
	const OPTION_EMAIL_TEMPLATE        = 'rondo_finance_email_template';
	const OPTION_RABOBANK_CREDENTIALS  = 'rondo_finance_rabobank_credentials';
	const OPTION_CLUB_LOGO_ID          = 'rondo_finance_club_logo_id';
	const OPTION_ACCENT_COLOR          = 'rondo_finance_accent_color';
	const OPTION_BCC_EMAIL             = 'rondo_finance_bcc_email';
	const OPTION_MOLLIE_API_KEY        = 'rondo_finance_mollie_api_key';
	const OPTION_MOLLIE_REDIRECT_URL   = 'rondo_finance_mollie_redirect_url';
	const OPTION_ACTIVE_PAYMENT_PROVIDER = 'rondo_finance_active_payment_provider';
	const OPTION_ADMIN_FEE               = 'rondo_finance_admin_fee';
	const OPTION_INSTALLMENT_ADMIN_FEE   = 'rondo_finance_installment_admin_fee';

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
		'payment_term_days'  => 14,
		'payment_clause'     => '',
		'club_logo_id'       => 0,
		'accent_color'       => '',
		'bcc_email'          => '',
		'admin_fee'              => 0.00,
		'installment_admin_fee'  => 0.00,
		'email_template'         => '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#333;"><p>Beste {naam},</p><p>Bijgevoegd vindt u de factuur {factuur_nummer} voor opgelegde boetes vanuit de tuchtcommissie.</p>{tuchtzaken_lijst}<p>Het totaalbedrag is <strong>{totaal_bedrag}</strong>.</p><p>U kunt betalen via de volgende link: {betaallink}</p>{qr_code}<p>Met vriendelijke groet,<br/>{organisatie_naam}</p></div>',
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
		return get_option( self::OPTION_IBAN, self::DEFAULTS['iban'] );
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

		$mollie_api_key = $this->get_mollie_api_key();

		return [
			'org_name'              => $this->get_org_name(),
			'org_address'           => $this->get_org_address(),
			'contact_email'         => $this->get_contact_email(),
			'iban'                  => $this->get_iban(),
			'payment_term_days'     => $this->get_payment_term_days(),
			'payment_clause'        => $this->get_payment_clause(),
			'email_template'        => $this->get_email_template(),
			'club_logo_id'          => $club_logo_id,
			'club_logo_url'         => $club_logo_url,
			'accent_color'          => $this->get_accent_color(),
			'bcc_email'             => $this->get_bcc_email(),
			'admin_fee'             => $this->get_admin_fee(),
			'installment_admin_fee' => $this->get_installment_admin_fee(),
			'rabobank_has_credentials' => $rabobank_creds !== null,
			'rabobank_environment'  => $rabobank_creds['environment'] ?? '',
			'mollie_has_api_key'    => ! empty( $mollie_api_key ),
			'mollie_environment'    => $this->derive_mollie_environment( $mollie_api_key ),
			'mollie_redirect_url'   => $this->get_mollie_redirect_url(),
			'active_payment_provider' => $this->get_active_payment_provider(),
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
			case 'payment_term_days':
				return $this->get_payment_term_days();
			case 'payment_clause':
				return $this->get_payment_clause();
			case 'email_template':
				return $this->get_email_template();
			case 'club_logo_id':
				return $this->get_club_logo_id();
			case 'accent_color':
				return $this->get_accent_color();
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
	public function update_settings( array $data ): bool {
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

		if ( isset( $data['payment_term_days'] ) ) {
			$days = max( 1, absint( $data['payment_term_days'] ) );
			$success = update_option( self::OPTION_PAYMENT_TERM_DAYS, $days ) && $success;
		}

		if ( isset( $data['payment_clause'] ) ) {
			$success = update_option( self::OPTION_PAYMENT_CLAUSE, sanitize_textarea_field( $data['payment_clause'] ) ) && $success;
		}

		if ( isset( $data['email_template'] ) ) {
			$success = update_option( self::OPTION_EMAIL_TEMPLATE, wp_kses_post( $data['email_template'] ) ) && $success;
		}

		if ( isset( $data['club_logo_id'] ) ) {
			$logo_id = absint( $data['club_logo_id'] );
			$success = update_option( self::OPTION_CLUB_LOGO_ID, $logo_id ) && $success;
		}

		if ( isset( $data['accent_color'] ) ) {
			$color = sanitize_hex_color( $data['accent_color'] ) ?? '';
			$success = update_option( self::OPTION_ACCENT_COLOR, $color ) && $success;
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

		// Handle Mollie API key with encryption
		if ( isset( $data['mollie_api_key'] ) ) {
			$success = $this->update_mollie_api_key(
				sanitize_text_field( $data['mollie_api_key'] )
			) && $success;
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
	 * Get Mollie API key (decrypted, internal use only)
	 *
	 * @return string Decrypted API key, or empty string if not configured
	 */
	public function get_mollie_api_key(): string {
		$encrypted = get_option( self::OPTION_MOLLIE_API_KEY, '' );

		if ( empty( $encrypted ) ) {
			return '';
		}

		$data = CredentialEncryption::decrypt( $encrypted );

		return $data['api_key'] ?? '';
	}

	/**
	 * Update Mollie API key (encrypts and stores)
	 *
	 * Passing an empty string removes the stored key.
	 *
	 * @param string $api_key Mollie API key (live_ or test_ prefix)
	 * @return bool True on success
	 */
	public function update_mollie_api_key( string $api_key ): bool {
		if ( empty( $api_key ) ) {
			return (bool) delete_option( self::OPTION_MOLLIE_API_KEY );
		}

		$encrypted = CredentialEncryption::encrypt( [ 'api_key' => $api_key ] );

		return update_option( self::OPTION_MOLLIE_API_KEY, $encrypted );
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
