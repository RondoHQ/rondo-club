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
		'email_template'     => "Beste {naam},

Bijgevoegd vindt u de factuur {factuur_nummer} voor opgelegde boetes vanuit de tuchtcommissie.

{tuchtzaken_lijst}

Het totaalbedrag is {totaal_bedrag}.

U kunt betalen via de volgende link: {betaallink}

Met vriendelijke groet,
{organisatie_naam}",
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

		return [
			'org_name'              => $this->get_org_name(),
			'org_address'           => $this->get_org_address(),
			'contact_email'         => $this->get_contact_email(),
			'iban'                  => $this->get_iban(),
			'payment_term_days'     => $this->get_payment_term_days(),
			'payment_clause'        => $this->get_payment_clause(),
			'email_template'        => $this->get_email_template(),
			'rabobank_has_credentials' => $rabobank_creds !== null,
			'rabobank_environment'  => $rabobank_creds['environment'] ?? '',
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
			$success = update_option( self::OPTION_EMAIL_TEMPLATE, sanitize_textarea_field( $data['email_template'] ) ) && $success;
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
	 * Update organization name
	 *
	 * @param string $name The organization name
	 * @return bool True on success
	 */
	public function update_org_name( string $name ): bool {
		return update_option( self::OPTION_ORG_NAME, sanitize_text_field( $name ) );
	}

	/**
	 * Update organization address
	 *
	 * @param string $address The organization address
	 * @return bool True on success
	 */
	public function update_org_address( string $address ): bool {
		return update_option( self::OPTION_ORG_ADDRESS, sanitize_textarea_field( $address ) );
	}

	/**
	 * Update contact email
	 *
	 * @param string $email The contact email
	 * @return bool True on success
	 */
	public function update_contact_email( string $email ): bool {
		return update_option( self::OPTION_CONTACT_EMAIL, sanitize_email( $email ) );
	}

	/**
	 * Update IBAN
	 *
	 * @param string $iban The IBAN
	 * @return bool True on success
	 */
	public function update_iban( string $iban ): bool {
		$iban = strtoupper( str_replace( ' ', '', sanitize_text_field( $iban ) ) );
		return update_option( self::OPTION_IBAN, $iban );
	}

	/**
	 * Update payment term in days
	 *
	 * @param int $days Payment term in days (minimum 1)
	 * @return bool True on success
	 */
	public function update_payment_term_days( int $days ): bool {
		$days = max( 1, absint( $days ) );
		return update_option( self::OPTION_PAYMENT_TERM_DAYS, $days );
	}

	/**
	 * Update payment clause
	 *
	 * @param string $clause The payment clause text
	 * @return bool True on success
	 */
	public function update_payment_clause( string $clause ): bool {
		return update_option( self::OPTION_PAYMENT_CLAUSE, sanitize_textarea_field( $clause ) );
	}

	/**
	 * Update email template
	 *
	 * @param string $template The email template
	 * @return bool True on success
	 */
	public function update_email_template( string $template ): bool {
		return update_option( self::OPTION_EMAIL_TEMPLATE, sanitize_textarea_field( $template ) );
	}
}
