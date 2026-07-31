<?php
/**
 * Mollie Configuration Service
 *
 * Owns the complete Mollie configuration surface: account storage (with
 * encrypted API keys), account lookups, default-account resolution per
 * invoice type, redirect URL, active payment provider, and payment-account
 * snapshot composition.
 *
 * Extracted from Rondo\Config\FinanceConfig in Phase 220 of the v34.0
 * Finance Service Decomposition milestone. Mirrors the MembershipFeeSettings
 * extraction pattern from v33.0 Phase 217.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;
use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mollie configuration service.
 *
 * Owns account storage (encrypted API keys), account lookups,
 * default-account resolution per invoice type, redirect URL, active
 * payment provider, and payment-account snapshot composition.
 */
class MollieConfig {

	/**
	 * Option key for Mollie accounts.
	 */
	const OPTION_MOLLIE_ACCOUNTS = 'rondo_finance_mollie_accounts';

	/**
	 * Option key for Mollie redirect URL.
	 */
	const OPTION_MOLLIE_REDIRECT_URL = 'rondo_finance_mollie_redirect_url';

	/**
	 * Option key for default Mollie account ID for membership invoices.
	 */
	const OPTION_MOLLIE_DEFAULT_MEMBERSHIP_ACCOUNT_ID = 'rondo_finance_mollie_default_membership_account_id';

	/**
	 * Option key for default Mollie account ID for discipline invoices.
	 */
	const OPTION_MOLLIE_DEFAULT_DISCIPLINE_ACCOUNT_ID = 'rondo_finance_mollie_default_discipline_account_id';

	/**
	 * Option key for default Mollie account ID for manual invoices.
	 */
	const OPTION_MOLLIE_DEFAULT_MANUAL_ACCOUNT_ID = 'rondo_finance_mollie_default_manual_account_id';

	/**
	 * Option key for active payment provider.
	 */
	const OPTION_ACTIVE_PAYMENT_PROVIDER = 'rondo_finance_active_payment_provider';

	/**
	 * Default values for Mollie-related settings.
	 *
	 * @var array<string, string>
	 */
	const DEFAULTS = [
		'mollie_default_membership_account_id' => '',
		'mollie_default_discipline_account_id' => '',
		'mollie_default_manual_account_id'     => '',
	];

	/**
	 * FinanceConfig reference for cross-service reads (org_name, iban).
	 *
	 * Used by get_payment_account_snapshot_for_invoice_type() until Phase 223
	 * extracts OrgInfo, at which point this will be replaced with an OrgInfo
	 * reference.
	 *
	 * @var FinanceConfig
	 */
	private FinanceConfig $finance_config;

	/**
	 * Constructor.
	 *
	 * @param FinanceConfig $finance_config FinanceConfig instance for cross-service reads.
	 */
	public function __construct( FinanceConfig $finance_config ) {
		$this->finance_config = $finance_config;
	}

	/**
	 * Get configured Mollie accounts without exposing API keys.
	 *
	 * @return array<int, array<string, string>>
	 */
	public function get_mollie_accounts(): array {
		$stored_accounts = get_option( self::OPTION_MOLLIE_ACCOUNTS, [] );
		$normalized      = $this->normalize_accounts_for_storage( is_array( $stored_accounts ) ? $stored_accounts : [] );

		if ( is_wp_error( $normalized ) ) {
			return [];
		}

		return array_map(
			function ( array $account ): array {
				$api_key = $this->decrypt_mollie_account_api_key( $account );

				return [
					'id'             => (string) ( $account['id'] ?? '' ),
					'internal_name'  => (string) ( $account['internal_name'] ?? '' ),
					'account_holder' => (string) ( $account['account_holder'] ?? '' ),
					'iban'           => (string) ( $account['iban'] ?? '' ),
					'has_api_key'    => $api_key !== '',
					'environment'    => $this->derive_mollie_environment( $api_key ),
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
		if ( $account_id === '' ) {
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
		if ( $account_id !== '' ) {
			$account = $this->get_mollie_account_by_id( $account_id );
			if ( is_array( $account ) && ! empty( $account['has_api_key'] ) ) {
				return $account;
			}
		}

		$usable_accounts = $this->get_usable_mollie_accounts();
		if ( count( $usable_accounts ) === 1 ) {
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
		if ( $provider !== 'mollie' ) {
			return [
				'id'              => '',
				'internal_name'   => '',
				'account_holder'  => $this->finance_config->get_org_name(),
				'iban'            => $this->finance_config->get_iban(),
				'linked_provider' => $provider,
			];
		}

		$account = null;
		if ( $invoice_type === 'manual' && $requested_account_id !== '' ) {
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
	 * Get Mollie redirect URL (where payer lands after payment).
	 *
	 * @return string The redirect URL, or empty string if not configured.
	 */
	public function get_mollie_redirect_url(): string {
		return get_option( self::OPTION_MOLLIE_REDIRECT_URL, '' );
	}

	/**
	 * Get active payment provider.
	 *
	 * @return string Active payment provider slug ('rabobank' or 'mollie'). Defaults to 'rabobank'.
	 */
	public function get_active_payment_provider(): string {
		return get_option( self::OPTION_ACTIVE_PAYMENT_PROVIDER, 'rabobank' );
	}

	/**
	 * Update active payment provider.
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
	 * Normalize and validate Mollie accounts before storage.
	 *
	 * Previously private on FinanceConfig as normalize_mollie_accounts_for_storage().
	 * Made public here so FinanceConfig::update_settings() can call it through the
	 * FinanceServices locator. The _mollie_ infix is dropped since this class is
	 * already scoped to Mollie.
	 *
	 * @param array $accounts Raw bank accounts payload.
	 * @return array<int, array<string, string>>|\WP_Error
	 */
	public function normalize_accounts_for_storage( array $accounts ) {
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
			$account_id     = sanitize_key( (string) ( $account['id'] ?? '' ) );
			$api_key        = sanitize_text_field( (string) ( $account['api_key'] ?? '' ) );

			if ( $internal_name === '' && $account_holder === '' && $iban === '' && $api_key === '' ) {
				continue;
			}

			if ( $internal_name === '' || $account_holder === '' || $iban === '' ) {
				return new \WP_Error(
					'invalid_mollie_account',
					// translators: %d is the Mollie account number (1-based index).
				sprintf( __( 'Mollie-rekening %d is onvolledig. Vul interne naam, tenaamstelling en IBAN in.', 'rondo' ), (int) $index + 1 ),
					[ 'status' => 400 ]
				);
			}

			if ( $account_id === '' ) {
				$account_id = 'mollie-' . sanitize_key( wp_generate_uuid4() );
			}

			$encrypted_api_key = (string) ( $existing_by_id[ $account_id ]['api_key_encrypted'] ?? '' );
			if ( $api_key !== '' ) {
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
	 * Previously private on FinanceConfig as build_safe_mollie_accounts_from_storage().
	 * Made public here so FinanceConfig::update_settings() can call it through the
	 * FinanceServices locator. The _mollie_ infix is dropped since this class is
	 * already scoped to Mollie.
	 *
	 * @param array<int, array<string, string>> $accounts Stored Mollie accounts.
	 * @return array<int, array<string, string|bool>>
	 */
	public function build_safe_accounts_from_storage( array $accounts ): array {
		return array_map(
			function ( array $account ): array {
				$api_key = $this->decrypt_mollie_account_api_key( $account );

				return [
					'id'             => (string) ( $account['id'] ?? '' ),
					'internal_name'  => (string) ( $account['internal_name'] ?? '' ),
					'account_holder' => (string) ( $account['account_holder'] ?? '' ),
					'iban'           => (string) ( $account['iban'] ?? '' ),
					'has_api_key'    => $api_key !== '',
					'environment'    => $this->derive_mollie_environment( $api_key ),
				];
			},
			$accounts
		);
	}

	/**
	 * Get a configured Mollie account by ID including its decrypted API key.
	 *
	 * @param string $account_id Mollie account ID.
	 * @return array<string, string>|null
	 */
	private function get_mollie_account_record_by_id( string $account_id ): ?array {
		$account_id = sanitize_key( $account_id );
		if ( $account_id === '' ) {
			return null;
		}

		$stored_accounts = get_option( self::OPTION_MOLLIE_ACCOUNTS, [] );
		$normalized      = $this->normalize_accounts_for_storage( is_array( $stored_accounts ) ? $stored_accounts : [] );
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
	 * Decrypt the stored API key for a Mollie account.
	 *
	 * @param array<string, string> $account Stored Mollie account record.
	 * @return string
	 */
	private function decrypt_mollie_account_api_key( array $account ): string {
		$encrypted = (string) ( $account['api_key_encrypted'] ?? '' );
		if ( $encrypted === '' ) {
			return '';
		}

		$data = CredentialEncryption::decrypt( $encrypted );
		return (string) ( $data['api_key'] ?? '' );
	}

	/**
	 * Derive Mollie environment from API key prefix.
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
