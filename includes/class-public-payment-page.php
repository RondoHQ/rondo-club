<?php
/**
 * Public Payment Landing Page
 *
 * Handles the public-facing payment page at /betaling/{token} where members
 * can view their invoice details and select a payment plan without logging in.
 *
 * The page renders a standalone HTML page (no WordPress theme, no React SPA)
 * and creates Mollie payments for the selected installment plan.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Config\FinanceConfig;
use Rondo\Fees\MembershipFees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public payment landing page handler.
 *
 * Registers a rewrite rule at /betaling/{64-char-hex-token} and handles both
 * GET (render invoice + plan selection) and POST (create Mollie payment + redirect).
 *
 * Token generation (generate_token) is a static utility called by Phase 196
 * bulk invoice creation to generate the token and set the payment_link ACF field
 * so InvoiceEmailSender can include the URL in emails via {betaallink}.
 */
class PublicPaymentPage {

	/**
	 * Post meta key for storing the payment token on rondo_invoice posts.
	 */
	const TOKEN_META_KEY = '_payment_token';

	/**
	 * Token length in bytes. Hex-encoded to 64 characters.
	 */
	const TOKEN_LENGTH = 32;

	/**
	 * Constructor — register WordPress hooks.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
	}

	/**
	 * Register rewrite rule for the public payment page.
	 *
	 * Matches /betaling/{64-char-hex-token} — the regex enforces exactly 64
	 * lowercase hex characters (32 bytes hex-encoded via bin2hex).
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^betaling/([a-f0-9]{64})$',
			'index.php?rondo_payment_token=$matches[1]',
			'top'
		);
	}

	/**
	 * Add the payment token query var to WordPress.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Query vars with rondo_payment_token added.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'rondo_payment_token';
		return $vars;
	}

	/**
	 * Main request dispatcher — handles both GET and POST for /betaling/{token}.
	 *
	 * Fires at template_redirect priority 0, before the React SPA catch-all at
	 * priority 1. Always exits after rendering to prevent the SPA from firing.
	 */
	public function handle_request() {
		$token = get_query_var( 'rondo_payment_token' );
		if ( empty( $token ) ) {
			return; // Not our request.
		}

		// Sanitize: lowercase, strip non-alphanumeric.
		$token = sanitize_key( $token );

		// Look up the invoice by token.
		$invoice_id = self::get_invoice_by_token( $token );
		if ( null === $invoice_id ) {
			$this->render_error( 'Betaallink niet gevonden of verlopen.' );
			exit;
		}

		// Show success page if Mollie redirected back with ?betaald=1.
		if ( isset( $_GET['betaald'] ) && '1' === $_GET['betaald'] ) {
			$this->render_success_page( $invoice_id );
			exit;
		}

		// Handle form submission (plan selection).
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$this->handle_plan_selection( $invoice_id, $token );
			return; // handle_plan_selection manages its own exit.
		}

		// Default: render the plan selection page.
		$this->render_page( $invoice_id, $token );
		exit;
	}

	/**
	 * Generate a secure token for an invoice and set the payment_link ACF field.
	 *
	 * Called by Phase 196 bulk invoice creation when generating membership invoices.
	 * Sets both _payment_token post meta (for token lookup) and the payment_link
	 * ACF field (so InvoiceEmailSender can include the URL via {betaallink}).
	 *
	 * @param int $invoice_id Invoice post ID.
	 * @return string The 64-character hex token.
	 */
	public static function generate_token( int $invoice_id ): string {
		$token = bin2hex( random_bytes( self::TOKEN_LENGTH ) );
		update_post_meta( $invoice_id, self::TOKEN_META_KEY, $token );
		update_field( 'payment_link', home_url( '/betaling/' . $token ), $invoice_id );
		return $token;
	}

	/**
	 * Look up an invoice post by its payment token.
	 *
	 * @param string $token The 64-character hex payment token.
	 * @return int|null Invoice post ID, or null if not found.
	 */
	public static function get_invoice_by_token( string $token ): ?int {
		$posts = get_posts( [
			'post_type'      => 'rondo_invoice',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					'key'   => self::TOKEN_META_KEY,
					'value' => $token,
				],
			],
		] );

		return $posts[0] ?? null;
	}

	/**
	 * Render the invoice details and plan selection page (GET handler).
	 *
	 * Outputs a standalone mobile-first HTML page with inline CSS.
	 * No external dependencies, no wp_head(), no WordPress template.
	 *
	 * @param int    $invoice_id Invoice post ID.
	 * @param string $token      Payment token (used in form hidden field for CSRF).
	 */
	private function render_page( int $invoice_id, string $token ) {
		// Read invoice data.
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$total_amount   = (float) get_field( 'total_amount', $invoice_id );
		$person_id      = get_field( 'person', $invoice_id );
		$person_name    = $person_id ? get_the_title( $person_id ) : '';

		// Derive season from invoice creation date.
		$membership_fees = new MembershipFees();
		$invoice_date    = get_the_date( 'Y-m-d', $invoice_id );
		$season          = $membership_fees->get_season_key( $invoice_date );

		// Read installment admin fee.
		$config     = new FinanceConfig();
		$admin_fee  = $config->get_installment_admin_fee();

		// Calculate per-plan amounts.
		$amount_3  = round( $total_amount / 3, 2 ) + $admin_fee;
		$amount_8  = round( $total_amount / 8, 2 ) + $admin_fee;

		$total_3 = round( $total_amount / 3, 2 ) * 3 + $admin_fee * 3;
		$total_8 = round( $total_amount / 8, 2 ) * 8 + $admin_fee * 8;

		$club_name = get_bloginfo( 'name' );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$this->render_html_header( 'Betaling — ' . esc_html( $club_name ) );
		?>

<div class="container">
	<!-- Header -->
	<div class="card header-card">
		<div class="club-name"><?php echo esc_html( $club_name ); ?></div>
		<h1>Contributie betalen</h1>
	</div>

	<!-- Invoice summary -->
	<div class="card">
		<h2>Factuuroverzicht</h2>
		<table class="invoice-table">
			<tr>
				<th>Lid</th>
				<td><?php echo esc_html( $person_name ); ?></td>
			</tr>
			<tr>
				<th>Factuur</th>
				<td><?php echo esc_html( $invoice_number ); ?></td>
			</tr>
			<tr>
				<th>Seizoen</th>
				<td><?php echo esc_html( $season ); ?></td>
			</tr>
			<tr>
				<th>Totaalbedrag</th>
				<td class="amount"><?php echo esc_html( $this->format_currency( $total_amount ) ); ?></td>
			</tr>
		</table>
	</div>

	<!-- Plan selection -->
	<div class="card">
		<h2>Kies uw betaalwijze</h2>

		<!-- Volledig betalen -->
		<form method="POST" class="plan-form">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="plan" value="full">
			<div class="plan-option">
				<div class="plan-title">Volledig betalen</div>
				<div class="plan-amount"><?php echo esc_html( $this->format_currency( $total_amount ) ); ?></div>
				<div class="plan-detail">Eenmalige betaling, geen extra kosten</div>
				<button type="submit" class="btn btn-primary">Volledig betalen</button>
			</div>
		</form>

		<!-- 3 termijnen -->
		<form method="POST" class="plan-form">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="plan" value="quarterly_3">
			<div class="plan-option">
				<div class="plan-title">3 termijnen</div>
				<div class="plan-amount"><?php echo esc_html( $this->format_currency( $amount_3 ) ); ?> <span class="plan-period">per termijn</span></div>
				<?php if ( $admin_fee > 0 ) : ?>
				<div class="plan-detail">
					Inclusief <?php echo esc_html( $this->format_currency( $admin_fee ) ); ?> administratiekosten per termijn<br>
					Totaal: <?php echo esc_html( $this->format_currency( $total_3 ) ); ?>
				</div>
				<?php else : ?>
				<div class="plan-detail">Totaal: <?php echo esc_html( $this->format_currency( $total_3 ) ); ?></div>
				<?php endif; ?>
				<button type="submit" class="btn btn-secondary">Betalen in 3 termijnen</button>
			</div>
		</form>

		<!-- 8 termijnen -->
		<form method="POST" class="plan-form">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="plan" value="monthly_8">
			<div class="plan-option">
				<div class="plan-title">8 termijnen</div>
				<div class="plan-amount"><?php echo esc_html( $this->format_currency( $amount_8 ) ); ?> <span class="plan-period">per termijn</span></div>
				<?php if ( $admin_fee > 0 ) : ?>
				<div class="plan-detail">
					Inclusief <?php echo esc_html( $this->format_currency( $admin_fee ) ); ?> administratiekosten per termijn<br>
					Totaal: <?php echo esc_html( $this->format_currency( $total_8 ) ); ?>
				</div>
				<?php else : ?>
				<div class="plan-detail">Totaal: <?php echo esc_html( $this->format_currency( $total_8 ) ); ?></div>
				<?php endif; ?>
				<button type="submit" class="btn btn-secondary">Betalen in 8 termijnen</button>
			</div>
		</form>

		<?php if ( $admin_fee > 0 ) : ?>
		<p class="admin-fee-note">Per termijn wordt <?php echo esc_html( $this->format_currency( $admin_fee ) ); ?> administratiekosten in rekening gebracht.</p>
		<?php endif; ?>
	</div>
</div>

		<?php
		$this->render_html_footer();
	}

	/**
	 * Render the success page after Mollie redirects back with ?betaald=1.
	 *
	 * @param int $invoice_id Invoice post ID.
	 */
	private function render_success_page( int $invoice_id ) {
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$total_amount   = (float) get_field( 'total_amount', $invoice_id );
		$person_id      = get_field( 'person', $invoice_id );
		$person_name    = $person_id ? get_the_title( $person_id ) : '';

		$membership_fees = new MembershipFees();
		$invoice_date    = get_the_date( 'Y-m-d', $invoice_id );
		$season          = $membership_fees->get_season_key( $invoice_date );

		$club_name = get_bloginfo( 'name' );

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$this->render_html_header( 'Betaling ontvangen — ' . esc_html( $club_name ) );
		?>

<div class="container">
	<!-- Header -->
	<div class="card header-card">
		<div class="club-name"><?php echo esc_html( $club_name ); ?></div>
		<h1>Betaling</h1>
	</div>

	<!-- Success message -->
	<div class="card success-card">
		<div class="success-icon" aria-hidden="true">&#10003;</div>
		<h2>Betaling geregistreerd</h2>
		<p>Uw betaling is verwerkt. U ontvangt een bevestiging per e-mail.</p>
	</div>

	<!-- Invoice summary for reference -->
	<div class="card">
		<h2>Factuuroverzicht</h2>
		<table class="invoice-table">
			<tr>
				<th>Lid</th>
				<td><?php echo esc_html( $person_name ); ?></td>
			</tr>
			<tr>
				<th>Factuur</th>
				<td><?php echo esc_html( $invoice_number ); ?></td>
			</tr>
			<tr>
				<th>Seizoen</th>
				<td><?php echo esc_html( $season ); ?></td>
			</tr>
			<tr>
				<th>Totaalbedrag</th>
				<td class="amount"><?php echo esc_html( $this->format_currency( $total_amount ) ); ?></td>
			</tr>
		</table>
	</div>
</div>

		<?php
		$this->render_html_footer();
	}

	/**
	 * Render a Dutch error page and set appropriate HTTP status.
	 *
	 * @param string $message Dutch error message to display.
	 */
	private function render_error( string $message ) {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$club_name = get_bloginfo( 'name' );
		$this->render_html_header( 'Fout — ' . esc_html( $club_name ) );
		?>

<div class="container">
	<div class="card header-card">
		<div class="club-name"><?php echo esc_html( $club_name ); ?></div>
		<h1>Betaling</h1>
	</div>
	<div class="card error-card">
		<h2>Betaallink niet gevonden</h2>
		<p><?php echo esc_html( $message ); ?></p>
		<p class="error-hint">Controleer of u de juiste link heeft gebruikt.</p>
	</div>
</div>

		<?php
		$this->render_html_footer();
	}

	/**
	 * Handle plan selection form submission (POST handler).
	 *
	 * Validates CSRF token, validates plan choice, checks idempotency,
	 * stores installment meta, creates Mollie payment, and redirects to checkout.
	 *
	 * @param int    $invoice_id Invoice post ID.
	 * @param string $token      Payment token from URL (used for CSRF validation).
	 */
	private function handle_plan_selection( int $invoice_id, string $token ) {
		// CSRF check: submitted token must match URL token.
		$submitted_token = sanitize_key( $_POST['token'] ?? '' );
		if ( $submitted_token !== $token ) {
			$this->render_error( 'Ongeldige aanvraag.' );
			exit;
		}

		// Validate plan.
		$plan          = sanitize_key( $_POST['plan'] ?? '' );
		$allowed_plans = [ 'full', 'quarterly_3', 'monthly_8' ];
		if ( ! in_array( $plan, $allowed_plans, true ) ) {
			$this->render_error( 'Ongeldige keuze.' );
			exit;
		}

		// Idempotency check: if installment 1 payment ID already exists, redirect to existing checkout.
		$existing_payment_id = get_post_meta( $invoice_id, '_installment_1_mollie_payment_id', true );
		if ( ! empty( $existing_payment_id ) ) {
			$existing_url = get_post_meta( $invoice_id, '_installment_1_payment_link', true );
			if ( ! empty( $existing_url ) ) {
				wp_redirect( $existing_url );
				exit;
			}
		}

		// Read amounts.
		$total     = (float) get_field( 'total_amount', $invoice_id );
		$config    = new FinanceConfig();
		$admin_fee = $config->get_installment_admin_fee();

		// Store plan meta and calculate first installment amount.
		update_post_meta( $invoice_id, '_installment_plan', $plan );

		if ( 'full' === $plan ) {
			update_post_meta( $invoice_id, '_installment_count', 1 );
			$first_amount = $total;
		} elseif ( 'quarterly_3' === $plan ) {
			update_post_meta( $invoice_id, '_installment_count', 3 );
			$this->write_installment_meta( $invoice_id, 3, $total, $admin_fee );
			$first_amount = round( $total / 3, 2 ) + $admin_fee;
		} else { // monthly_8
			update_post_meta( $invoice_id, '_installment_count', 8 );
			$this->write_installment_meta( $invoice_id, 8, $total, $admin_fee );
			$first_amount = round( $total / 8, 2 ) + $admin_fee;
		}

		// Create Mollie payment for the first installment.
		$checkout_url = $this->create_installment_payment( $invoice_id, $first_amount, 1, $token );

		if ( is_wp_error( $checkout_url ) ) {
			$this->render_error( 'Betaling aanmaken mislukt. Probeer het later opnieuw.' );
			exit;
		}

		wp_redirect( $checkout_url );
		exit;
	}

	/**
	 * Write flat numbered post meta for each installment.
	 *
	 * Stores _installment_N_amount, _installment_N_admin_fee, and
	 * _installment_N_status for each installment 1..N.
	 *
	 * Handles rounding remainder by adjusting the last installment so amounts
	 * sum exactly to the original total.
	 *
	 * @param int   $invoice_id Invoice post ID.
	 * @param int   $count      Total number of installments (3 or 8).
	 * @param float $total      Invoice total amount.
	 * @param float $admin_fee  Per-installment administration fee.
	 */
	private function write_installment_meta( int $invoice_id, int $count, float $total, float $admin_fee ) {
		$base_amount = round( $total / $count, 2 );
		$accumulated = 0.0;

		for ( $n = 1; $n <= $count; $n++ ) {
			if ( $n === $count ) {
				// Last installment: use remainder to avoid rounding drift.
				$amount = round( $total - $accumulated, 2 );
			} else {
				$amount = $base_amount;
				$accumulated += $amount;
			}

			update_post_meta( $invoice_id, '_installment_' . $n . '_amount', $amount );
			update_post_meta( $invoice_id, '_installment_' . $n . '_admin_fee', $admin_fee );
			update_post_meta( $invoice_id, '_installment_' . $n . '_status', 'pending' );
		}
	}

	/**
	 * Create a Mollie payment for an installment and store results on the invoice.
	 *
	 * Stores the Mollie payment ID, checkout URL, and reverse-lookup meta
	 * (_mollie_pid_{payment_id}) for O(1) webhook lookup.
	 *
	 * @param int    $invoice_id          Invoice post ID.
	 * @param float  $amount              Payment amount (base + admin fee for installments).
	 * @param int    $installment_number  Which installment (1 for first payment).
	 * @param string $token               Payment token (used in redirect URL).
	 * @return string|\WP_Error Mollie checkout URL on success, WP_Error on failure.
	 */
	private function create_installment_payment( int $invoice_id, float $amount, int $installment_number, string $token ) {
		// Guard: Mollie API key must be configured.
		$config  = new FinanceConfig();
		$api_key = $config->get_mollie_api_key();
		if ( empty( $api_key ) ) {
			return new \WP_Error( 'mollie_not_configured', 'Mollie API-sleutel niet geconfigureerd.' );
		}

		// Build description based on plan type.
		$invoice_number = get_field( 'invoice_number', $invoice_id );
		$plan           = get_post_meta( $invoice_id, '_installment_plan', true );
		$count          = (int) get_post_meta( $invoice_id, '_installment_count', true );

		if ( 'full' === $plan ) {
			$description = 'Factuur ' . $invoice_number;
		} else {
			$description = 'Termijn ' . $installment_number . '/' . $count . ' - Factuur ' . $invoice_number;
		}

		// Build Mollie payment payload.
		$payload = [
			'amount'      => [
				'currency' => 'EUR',
				'value'    => number_format( $amount, 2, '.', '' ),
			],
			'description' => $description,
			'redirectUrl' => home_url( '/betaling/' . $token . '?betaald=1' ),
			'metadata'    => [
				'invoice_id'         => $invoice_id,
				'installment_number' => $installment_number,
			],
		];

		// Conditionally add webhookUrl — omit on localhost/.local environments.
		$site_url = get_site_url();
		if ( false === strpos( $site_url, 'localhost' ) && false === strpos( $site_url, '.local' ) ) {
			$payload['webhookUrl'] = rest_url( 'rondo/v1/mollie/webhook' );
		}

		// Call Mollie SDK.
		try {
			$mollie_client = new MollieClient();
			$mollie        = $mollie_client->get();
			$payment       = $mollie->payments->create( $payload );
		} catch ( \Mollie\Api\Exceptions\ApiException $e ) {
			error_log( 'Mollie API exception (installment): ' . $e->getMessage() );
			return new \WP_Error( 'mollie_api_error', $e->getMessage() );
		}

		// Store payment results on invoice.
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_mollie_payment_id', $payment->id );
		update_post_meta( $invoice_id, '_installment_' . $installment_number . '_payment_link', $payment->getCheckoutUrl() );

		// Reverse-lookup meta for O(1) webhook matching.
		update_post_meta( $invoice_id, '_mollie_pid_' . $payment->id, $installment_number );

		return $payment->getCheckoutUrl();
	}

	/**
	 * Render the HTML header with inline CSS for the standalone payment page.
	 *
	 * Mobile-first design with 16px input font-size to prevent iOS auto-zoom.
	 * No external CSS/JS dependencies — fully self-contained.
	 *
	 * @param string $title Page title for the <title> tag.
	 */
	private function render_html_header( string $title ) {
		?>
<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			background: #f8fafc;
			color: #1e293b;
			min-height: 100vh;
			padding: 1rem;
		}

		.container {
			max-width: 480px;
			margin: 0 auto;
		}

		.card {
			background: white;
			border-radius: 12px;
			padding: 1.5rem;
			margin-bottom: 1rem;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		}

		.header-card {
			text-align: center;
			padding-top: 2rem;
			padding-bottom: 1.5rem;
		}

		.club-name {
			font-size: 0.875rem;
			font-weight: 500;
			color: #64748b;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			margin-bottom: 0.5rem;
		}

		h1 {
			font-size: 1.5rem;
			font-weight: 700;
			color: #0f172a;
		}

		h2 {
			font-size: 1rem;
			font-weight: 600;
			color: #475569;
			text-transform: uppercase;
			letter-spacing: 0.05em;
			margin-bottom: 1rem;
		}

		.invoice-table {
			width: 100%;
			border-collapse: collapse;
		}

		.invoice-table th,
		.invoice-table td {
			padding: 0.5rem 0;
			font-size: 0.9375rem;
			border-bottom: 1px solid #f1f5f9;
			text-align: left;
		}

		.invoice-table th {
			font-weight: 500;
			color: #64748b;
			width: 40%;
		}

		.invoice-table tr:last-child th,
		.invoice-table tr:last-child td {
			border-bottom: none;
		}

		.invoice-table .amount {
			font-weight: 700;
			font-size: 1.25rem;
			color: #0f172a;
		}

		.plan-form {
			margin-bottom: 1rem;
		}

		.plan-form:last-of-type {
			margin-bottom: 0;
		}

		.plan-option {
			border: 1px solid #e2e8f0;
			border-radius: 8px;
			padding: 1rem;
		}

		.plan-title {
			font-size: 1rem;
			font-weight: 600;
			color: #1e293b;
			margin-bottom: 0.25rem;
		}

		.plan-amount {
			font-size: 1.5rem;
			font-weight: 700;
			color: #0f172a;
			margin-bottom: 0.25rem;
		}

		.plan-period {
			font-size: 0.875rem;
			font-weight: 400;
			color: #64748b;
		}

		.plan-detail {
			font-size: 0.875rem;
			color: #64748b;
			margin-bottom: 0.75rem;
			line-height: 1.5;
		}

		.btn {
			display: block;
			width: 100%;
			padding: 1rem;
			font-size: 1rem;
			font-weight: 600;
			border: none;
			border-radius: 8px;
			cursor: pointer;
			min-height: 48px;
			transition: opacity 0.15s ease;
			font-family: inherit;
		}

		.btn:hover {
			opacity: 0.9;
		}

		.btn:active {
			opacity: 0.8;
		}

		.btn-primary {
			background: #0891b2;
			color: white;
		}

		.btn-secondary {
			background: #e2e8f0;
			color: #1e293b;
		}

		.admin-fee-note {
			font-size: 0.8125rem;
			color: #94a3b8;
			margin-top: 1rem;
			text-align: center;
		}

		.success-card {
			text-align: center;
			padding: 2rem 1.5rem;
		}

		.success-icon {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			width: 64px;
			height: 64px;
			background: #dcfce7;
			color: #16a34a;
			border-radius: 50%;
			font-size: 2rem;
			font-weight: 700;
			margin-bottom: 1rem;
		}

		.success-card h2 {
			font-size: 1.25rem;
			font-weight: 700;
			color: #16a34a;
			text-transform: none;
			letter-spacing: 0;
			margin-bottom: 0.5rem;
		}

		.success-card p {
			color: #475569;
			font-size: 0.9375rem;
			line-height: 1.5;
		}

		.error-card {
			text-align: center;
			padding: 2rem 1.5rem;
		}

		.error-card h2 {
			font-size: 1.25rem;
			font-weight: 700;
			color: #dc2626;
			text-transform: none;
			letter-spacing: 0;
			margin-bottom: 0.5rem;
		}

		.error-card p {
			color: #475569;
			font-size: 0.9375rem;
			line-height: 1.5;
			margin-bottom: 0.5rem;
		}

		.error-hint {
			font-size: 0.875rem;
			color: #94a3b8;
		}

		input[type="text"],
		input[type="hidden"],
		select,
		textarea {
			font-size: 16px;
		}
	</style>
</head>
<body>
		<?php
	}

	/**
	 * Render the closing HTML tags.
	 */
	private function render_html_footer() {
		?>
</body>
</html>
		<?php
	}

	/**
	 * Format a currency amount in Dutch style.
	 *
	 * @param float $amount Amount to format.
	 * @return string Dutch-formatted currency string (e.g., "€ 12,50").
	 */
	private function format_currency( float $amount ): string {
		return '€ ' . number_format( $amount, 2, ',', '.' );
	}
}
