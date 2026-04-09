<?php
/**
 * Finance settings snapshot generator.
 *
 * Runs on WordPress via `wp eval-file`. Emits a deterministic JSON envelope
 * containing (a) all 48 finance-surface WordPress options (40 rondo_finance_*
 * + 8 rondo_membership_pass_* user-settings) and (b) the full
 * /rondo/v1/finance-settings REST response via
 * Rondo\Config\FinanceConfig::get_all_settings(). Used as a before/after
 * regression baseline during the v34.0 Finance Service Decomposition
 * milestone.
 *
 * Deliberately read-only: no writes, no emails, no side effects. Safe to run
 * against production. Output is key-sorted so `jq -S` diffs are stable.
 *
 * Usage (from repo root):
 *   source .env && ssh -p "$DEPLOY_SSH_PORT" "$DEPLOY_SSH_USER@$DEPLOY_SSH_HOST" \
 *       "cd $DEPLOY_REMOTE_WP_PATH && wp eval-file -" \
 *       < bin/finance-settings-snapshot.php > snapshot.json
 *
 * Or via the bin/finance-settings-snapshot.sh wrapper.
 *
 * @package Rondo\Config\Tools
 */

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.DevelopmentFunctions

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Must run under WordPress (wp eval-file).\n" );
	exit( 1 );
}

if ( ! class_exists( '\Rondo\Config\FinanceConfig' ) ) {
	fwrite( STDERR, "Rondo\\Config\\FinanceConfig not loaded.\n" );
	exit( 1 );
}

/**
 * Canonical 48-key allowlist for the finance settings surface.
 *
 * 40 rondo_finance_* keys (settings, not internal state) +
 * 8 rondo_membership_pass_* user-settings keys.
 * Listed alphabetically for stable diffs.
 *
 * EXCLUDED (internal state — do NOT add):
 *   rondo_membership_pass_apple_cert_path            (derived from attachment)
 *   rondo_membership_pass_google_service_account_path (derived from attachment)
 *   rondo_membership_pass_token                      (ephemeral)
 *   rondo_membership_pass_jwt_secret                 (ephemeral)
 *   rondo_membership_pass_backfill_v2_done           (migration marker)
 */
$finance_option_keys = [
	// 40 rondo_finance_* keys (alphabetical).
	'rondo_finance_accent_background_color',
	'rondo_finance_accent_color',
	'rondo_finance_active_payment_provider',
	'rondo_finance_admin_fee',
	'rondo_finance_bcc_email',
	'rondo_finance_club_logo_id',
	'rondo_finance_contact_email',
	'rondo_finance_credit_email_heading',
	'rondo_finance_credit_email_subject',
	'rondo_finance_credit_email_template',
	'rondo_finance_discipline_email_heading',
	'rondo_finance_email_template',
	'rondo_finance_iban',
	'rondo_finance_installment_admin_fee',
	'rondo_finance_installment_email_heading',
	'rondo_finance_installment_email_template',
	'rondo_finance_invoice_reminder_1_email_heading',
	'rondo_finance_invoice_reminder_1_email_template',
	'rondo_finance_invoice_reminder_2_email_heading',
	'rondo_finance_invoice_reminder_2_email_template',
	'rondo_finance_membership_email_heading',
	'rondo_finance_membership_email_template',
	'rondo_finance_membership_payment_clause',
	'rondo_finance_mollie_accounts',
	'rondo_finance_mollie_default_discipline_account_id',
	'rondo_finance_mollie_default_manual_account_id',
	'rondo_finance_mollie_default_membership_account_id',
	'rondo_finance_mollie_redirect_url',
	'rondo_finance_org_address',
	'rondo_finance_org_name',
	'rondo_finance_payment_clause',
	'rondo_finance_payment_term_days',
	'rondo_finance_rabobank_credentials',
	'rondo_finance_regular_invoice_email_body',
	'rondo_finance_regular_invoice_email_heading',
	'rondo_finance_regular_invoice_email_subject',
	'rondo_finance_reminder_1_email_heading',
	'rondo_finance_reminder_1_email_template',
	'rondo_finance_reminder_2_email_heading',
	'rondo_finance_reminder_2_email_template',
	// 8 rondo_membership_pass_* user-settings keys (alphabetical).
	'rondo_membership_pass_apple_cert_attachment_id',
	'rondo_membership_pass_apple_cert_password',
	'rondo_membership_pass_apple_organization_name',
	'rondo_membership_pass_apple_pass_type_identifier',
	'rondo_membership_pass_apple_team_identifier',
	'rondo_membership_pass_google_class_suffix',
	'rondo_membership_pass_google_issuer_id',
	'rondo_membership_pass_google_service_account_attachment_id',
];

$expected_count = 48;

// Collect all option values.
$options = [];
foreach ( $finance_option_keys as $key ) {
	$options[ $key ] = get_option( $key, null );
}

// Drift tripwire: assert count matches expected.
if ( count( $options ) !== $expected_count ) {
	fwrite(
		STDERR,
		sprintf(
			"Error: expected %d options but got %d. The allowlist is out of sync.\n",
			$expected_count,
			count( $options )
		)
	);
	exit( 1 );
}

// Instantiate FinanceConfig and capture get_all_settings().
$finance_config = new \Rondo\Config\FinanceConfig();
$rest_response  = $finance_config->get_all_settings();

// Recursive key-sort helper for nested associative arrays.
// Ensures byte-for-byte stability without requiring `jq -S` (though we use it
// for defense in depth in the bash wrapper too).
$ksort_recursive = function ( $value ) use ( &$ksort_recursive ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}
	ksort( $value );
	foreach ( $value as $k => $v ) {
		$value[ $k ] = $ksort_recursive( $v );
	}
	return $value;
};

ksort( $options );
$options       = $ksort_recursive( $options );
$rest_response = $ksort_recursive( $rest_response );

$envelope = [
	'schema_version' => 1,
	'generated_at'   => gmdate( 'c' ),
	'site_url'       => home_url(),
	'option_count'   => count( $options ),  // MUST be 48 by assertion above.
	'options'        => $options,           // 48 raw WP option values, key-sorted.
	'rest_response'  => $rest_response,     // Full get_all_settings() output, key-sorted.
];

fwrite( STDOUT, wp_json_encode( $envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n" );
