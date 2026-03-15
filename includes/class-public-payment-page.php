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
use Rondo\Finance\InstallmentPaymentService;

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
		if ( $invoice_id === null ) {
			$this->render_error( 'Betaallink niet gevonden of verlopen.' );
			exit;
		}

		// Show success page if Mollie redirected back with ?betaald=1.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['betaald'] ) && $_GET['betaald'] === '1' ) {
			$this->render_success_page( $invoice_id );
			exit;
		}

		// Handle form submission (plan selection).
		if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
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
		$posts = get_posts(
			[
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
			]
		);

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
		$admin_fee = $membership_fees->get_installment_admin_fee( $season );

		// Read installment plan toggles for this season.
		$plan_3_enabled = $membership_fees->get_installment_plan_3_enabled( $season );
		$plan_8_enabled = $membership_fees->get_installment_plan_8_enabled( $season );

		// Per-invoice override: if installments disabled, hide both plans.
		if ( get_post_meta( $invoice_id, '_disable_installments', true ) ) {
			$plan_3_enabled = false;
			$plan_8_enabled = false;
		}

		// Calculate available payment dates (23rd of each month through April).
		$today            = current_time( 'Y-m-d' );
		$available_dates  = self::get_available_payment_dates( $today, $season );
		$max_installments = count( $available_dates );

		// Hide plans that don't fit within available dates.
		$plan_3_visible = $plan_3_enabled && $max_installments >= 3;

		$large_count = min( 8, $max_installments );
		// Keep existing behavior (4..8 dynamic) and additionally allow a late-season 2-installment fallback.
		$large_plan_visible = $plan_8_enabled && ( $large_count > 3 || $large_count === 2 );

		// Calculate per-plan amounts.
		$amount_3 = round( $total_amount / 3, 2 ) + $admin_fee;
		$total_3  = round( $total_amount / 3, 2 ) * 3 + $admin_fee * 3;

		$amount_large = $large_plan_visible ? round( $total_amount / $large_count, 2 ) + $admin_fee : 0;
		$total_large  = $large_plan_visible ? round( $total_amount / $large_count, 2 ) * $large_count + $admin_fee * $large_count : 0;

		$branding = $this->get_club_branding();

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$this->render_html_header(
			'Betaling — ' . esc_html( $branding['name'] ),
			$branding['accent_color'],
			$branding['accent_background_color'],
			$branding['logo_url']
		);
		?>

<div class="container">
	<!-- Header -->
		<?php $this->render_header_card( 'Contributie betalen' ); ?>

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
		<h2>Kies je betaalwijze</h2>

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

		<?php if ( $plan_3_visible ) : ?>
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
				<button type="submit" class="btn btn-secondary">Betaal in 3 termijnen</button>
			</div>
		</form>
		<?php endif; ?>

		<?php if ( $large_plan_visible ) : ?>
		<!-- <?php echo (int) $large_count; ?> termijnen -->
		<form method="POST" class="plan-form">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<input type="hidden" name="plan" value="monthly_8">
			<div class="plan-option">
				<div class="plan-title"><?php echo (int) $large_count; ?> termijnen</div>
				<div class="plan-amount"><?php echo esc_html( $this->format_currency( $amount_large ) ); ?> <span class="plan-period">per termijn</span></div>
				<?php if ( $admin_fee > 0 ) : ?>
				<div class="plan-detail">
					Inclusief <?php echo esc_html( $this->format_currency( $admin_fee ) ); ?> administratiekosten per termijn<br>
					Totaal: <?php echo esc_html( $this->format_currency( $total_large ) ); ?>
				</div>
				<?php else : ?>
				<div class="plan-detail">Totaal: <?php echo esc_html( $this->format_currency( $total_large ) ); ?></div>
				<?php endif; ?>
				<button type="submit" class="btn btn-secondary">Betaal in <?php echo (int) $large_count; ?> termijnen</button>
			</div>
		</form>
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

		$branding = $this->get_club_branding();

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$this->render_html_header(
			'Betaling ontvangen — ' . esc_html( $branding['name'] ),
			$branding['accent_color'],
			$branding['accent_background_color'],
			$branding['logo_url']
		);
		?>

<div class="container">
	<!-- Header -->
		<?php $this->render_header_card( 'Betaling' ); ?>

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
	 * Get club name, logo URL, and accent color from FinanceConfig.
	 *
	 * @return array{name: string, logo_url: string, accent_color: string, accent_background_color: string} Club branding.
	 */
	private function get_club_branding(): array {
		$config                  = new FinanceConfig();
		$name                    = $config->get_display_name();
		$accent_color            = $config->get_accent_color();
		$accent_background_color = $config->get_accent_background_color();
		$logo_url                = '';
		$logo_id                 = $config->get_club_logo_id();
		if ( $logo_id > 0 ) {
			$url = wp_get_attachment_url( $logo_id );
			if ( $url ) {
				$logo_url = $url;
			}
		}
		return [
			'name'                    => $name ?: get_bloginfo( 'name' ),
			'logo_url'                => $logo_url,
			'accent_color'            => $accent_color ?: '#0891b2',
			'accent_background_color' => $accent_background_color ?: '#f8fafc',
		];
	}

	/**
	 * Render the header card with club logo and name.
	 *
	 * @param string $heading Page heading text.
	 */
	private function render_header_card( string $heading ) {
		$branding = $this->get_club_branding();
		?>
	<div class="card header-card">
		<div class="club-brand">
			<?php if ( $branding['logo_url'] ) : ?>
				<img src="<?php echo esc_url( $branding['logo_url'] ); ?>" alt="<?php echo esc_attr( $branding['name'] ); ?>" class="club-logo" />
			<?php endif; ?>
		</div>
		<h1><?php echo esc_html( $heading ); ?></h1>
	</div>
		<?php
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

		$branding = $this->get_club_branding();
		$this->render_html_header(
			'Fout — ' . esc_html( $branding['name'] ),
			$branding['accent_color'],
			$branding['accent_background_color'],
			$branding['logo_url']
		);
		?>

<div class="container">
		<?php $this->render_header_card( 'Betaling' ); ?>
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
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$submitted_token = sanitize_key( $_POST['token'] ?? '' );
		if ( $submitted_token !== $token ) {
			$this->render_error( 'Ongeldige aanvraag.' );
			exit;
		}

		// Validate plan.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$plan          = sanitize_key( $_POST['plan'] ?? '' );
		$allowed_plans = [ 'full', 'quarterly_3', 'monthly_8' ];
		if ( ! in_array( $plan, $allowed_plans, true ) ) {
			$this->render_error( 'Ongeldige keuze.' );
			exit;
		}

		$fees_service     = new MembershipFees();
		$invoice_date     = get_the_date( 'Y-m-d', $invoice_id );
		$invoice_season   = $fees_service->get_season_key( $invoice_date );
		$available_dates  = self::get_available_payment_dates( current_time( 'Y-m-d' ), $invoice_season );
		$max_installments = count( $available_dates );

		// Check if selected installment plan is enabled and currently available.
		if ( $plan === 'quarterly_3' || $plan === 'monthly_8' ) {
			if ( $plan === 'quarterly_3' && ! $fees_service->get_installment_plan_3_enabled( $invoice_season ) ) {
				$this->render_error( 'Dit betalingsplan is niet beschikbaar.' );
				exit;
			}

			if ( $plan === 'monthly_8' && ! $fees_service->get_installment_plan_8_enabled( $invoice_season ) ) {
				$this->render_error( 'Dit betalingsplan is niet beschikbaar.' );
				exit;
			}

			if ( $plan === 'quarterly_3' && $max_installments < 3 ) {
				$this->render_error( 'Dit betalingsplan is niet beschikbaar.' );
				exit;
			}

			if ( $plan === 'monthly_8' ) {
				$large_count = min( 8, $max_installments );
				if ( ! ( $large_count > 3 || $large_count === 2 ) ) {
					$this->render_error( 'Dit betalingsplan is niet beschikbaar.' );
					exit;
				}
			}
		}

		// Per-invoice override: reject installment plan if disabled for this invoice.
		if ( ( $plan === 'quarterly_3' || $plan === 'monthly_8' ) && get_post_meta( $invoice_id, '_disable_installments', true ) ) {
			$this->render_error( 'Termijnbetaling is niet beschikbaar voor deze factuur.' );
			exit;
		}

		// Guard: if invoice is already paid, show a success page instead of creating a new payment.
		if ( get_post_status( $invoice_id ) === 'rondo_paid' ) {
			$this->render_success_page( $invoice_id );
			exit;
		}

		// Guard: if installment 1 is already confirmed paid, redirect to success page.
		// This prevents creating a duplicate payment for a member who already paid installment 1.
		if ( get_post_meta( $invoice_id, '_installment_1_status', true ) === 'betaald' ) {
			$this->render_success_page( $invoice_id );
			exit;
		}

		// Clear all previous installment data before writing new plan.
		// A member may change their plan selection on a return visit (e.g. quarterly → full),
		// so we must remove stale amounts, Mollie links, and reverse-lookup keys.
		$old_count   = (int) get_post_meta( $invoice_id, '_installment_count', true );
		$clear_up_to = max( $old_count, 8 );
		for ( $n = 1; $n <= $clear_up_to; $n++ ) {
			$old_mollie_id = get_post_meta( $invoice_id, '_installment_' . $n . '_mollie_payment_id', true );
			if ( $old_mollie_id ) {
				delete_post_meta( $invoice_id, '_mollie_pid_' . $old_mollie_id );
			}
			delete_post_meta( $invoice_id, '_installment_' . $n . '_amount' );
			delete_post_meta( $invoice_id, '_installment_' . $n . '_admin_fee' );
			delete_post_meta( $invoice_id, '_installment_' . $n . '_status' );
			delete_post_meta( $invoice_id, '_installment_' . $n . '_due_date' );
			delete_post_meta( $invoice_id, '_installment_' . $n . '_mollie_payment_id' );
			delete_post_meta( $invoice_id, '_installment_' . $n . '_payment_link' );
		}

		// Read amounts.
		$total     = (float) get_field( 'total_amount', $invoice_id );
		$admin_fee = $fees_service->get_installment_admin_fee( $invoice_season );

		// Store plan meta and write installment breakdown.
		update_post_meta( $invoice_id, '_installment_plan', $plan );

		if ( $plan === 'full' ) {
			update_post_meta( $invoice_id, '_installment_count', 1 );
			update_post_meta( $invoice_id, '_installment_1_amount', $total );
			update_post_meta( $invoice_id, '_installment_1_admin_fee', 0 );
			update_post_meta( $invoice_id, '_installment_1_status', 'pending' );
		} elseif ( $plan === 'quarterly_3' ) {
			$due_dates = self::calculate_installment_due_dates( 3, $available_dates, true );
			update_post_meta( $invoice_id, '_installment_count', 3 );
			$this->write_installment_meta( $invoice_id, 3, $total, $admin_fee, $due_dates );
		} else { // monthly_8 — actual count capped at available dates.
			$actual_count = min( 8, count( $available_dates ) );
			$due_dates    = self::calculate_installment_due_dates( $actual_count, $available_dates );
			update_post_meta( $invoice_id, '_installment_count', $actual_count );
			$this->write_installment_meta( $invoice_id, $actual_count, $total, $admin_fee, $due_dates );
		}

		// Create Mollie payment for the first installment via shared service.
		$checkout_url = InstallmentPaymentService::create_payment( $invoice_id, 1 );

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
	 * Stores _installment_N_amount, _installment_N_admin_fee, _installment_N_status,
	 * and _installment_N_due_date for each installment 1..N.
	 *
	 * Handles rounding remainder by adjusting the last installment so amounts
	 * sum exactly to the original total.
	 *
	 * @param int              $invoice_id Invoice post ID.
	 * @param int              $count      Total number of installments.
	 * @param float            $total      Invoice total amount.
	 * @param float            $admin_fee  Per-installment administration fee.
	 * @param array<int,string> $due_dates  Map of installment number => Y-m-d due date.
	 */
	private function write_installment_meta( int $invoice_id, int $count, float $total, float $admin_fee, array $due_dates ) {
		$base_amount = round( $total / $count, 2 );
		$accumulated = 0.0;

		for ( $n = 1; $n <= $count; $n++ ) {
			if ( $n === $count ) {
				// Last installment: use remainder to avoid rounding drift.
				$amount = round( $total - $accumulated, 2 );
			} else {
				$amount       = $base_amount;
				$accumulated += $amount;
			}

			update_post_meta( $invoice_id, '_installment_' . $n . '_amount', $amount );
			update_post_meta( $invoice_id, '_installment_' . $n . '_admin_fee', $admin_fee );
			update_post_meta( $invoice_id, '_installment_' . $n . '_status', 'pending' );

			if ( isset( $due_dates[ $n ] ) ) {
				update_post_meta( $invoice_id, '_installment_' . $n . '_due_date', $due_dates[ $n ] );
			}
		}
	}

	/**
	 * Get all available payment dates (23rd of each month) from a reference date through April.
	 *
	 * Returns Y-m-d strings for each 23rd that is on or after $from_date, up to and
	 * including April 23rd of the season's end year.
	 *
	 * @param string $from_date Reference date in Y-m-d format (typically today).
	 * @param string $season    Season key in 'YYYY-YYYY' format.
	 * @return string[] Array of Y-m-d date strings.
	 */
	public static function get_available_payment_dates( string $from_date, string $season ): array {
		$end_year  = (int) substr( $season, 5, 4 );
		$last_date = sprintf( '%04d-04-23', $end_year );

		$from_day   = (int) substr( $from_date, 8, 2 );
		$from_month = (int) substr( $from_date, 5, 2 );
		$from_year  = (int) substr( $from_date, 0, 4 );

		// Start from 23rd of current month if not yet passed, else next month.
		if ( $from_day <= 23 ) {
			$month = $from_month;
			$year  = $from_year;
		} else {
			$month = $from_month + 1;
			$year  = $from_year;
			if ( $month > 12 ) {
				$month = 1;
				++$year;
			}
		}

		$dates = [];
		while ( true ) {
			$date = sprintf( '%04d-%02d-23', $year, $month );
			if ( $date > $last_date ) {
				break;
			}
			$dates[] = $date;
			++$month;
			if ( $month > 12 ) {
				$month = 1;
				++$year;
			}
		}

		return $dates;
	}

	/**
	 * Select due dates for installments from available payment dates.
	 *
	 * For 3 installments with more dates available, evenly spaces them across
	 * the pool (first, middle, last). For other counts, takes sequential dates.
	 *
	 * @param int      $count           Number of installments.
	 * @param string[] $available_dates  Available payment dates (from get_available_payment_dates).
	 * @param bool     $evenly_spaced   Whether to evenly space across available dates.
	 * @return array<int, string> Map of installment number (1-based) => Y-m-d due date.
	 */
	private static function calculate_installment_due_dates( int $count, array $available_dates, bool $evenly_spaced = false ): array {
		$total_available = count( $available_dates );
		if ( $total_available === 0 || $count < 1 ) {
			return [];
		}

		$due_dates = [];

		if ( $evenly_spaced && $count < $total_available ) {
			for ( $i = 0; $i < $count; $i++ ) {
				$index               = ( $count === 1 ) ? 0 : (int) round( $i * ( $total_available - 1 ) / ( $count - 1 ) );
				$due_dates[ $i + 1 ] = $available_dates[ $index ];
			}
		} else {
			$max_dates = min( $count, $total_available );
			for ( $i = 0; $i < $max_dates; $i++ ) {
				$due_dates[ $i + 1 ] = $available_dates[ $i ];
			}
		}

		return $due_dates;
	}

	/**
	 * Render the HTML header with inline CSS for the standalone payment page.
	 *
	 * Mobile-first design with 16px input font-size to prevent iOS auto-zoom.
	 * No external CSS/JS dependencies — fully self-contained.
	 *
	 * @param string $title        Page title for the <title> tag.
	 * @param string $accent_color            Hex accent color for primary buttons (default #0891b2).
	 * @param string $accent_background_color Hex accent background color (default #f8fafc).
	 * @param string $logo_url                URL to club logo for favicon (optional).
	 */
	private function render_html_header(
		string $title,
		string $accent_color = '#0891b2',
		string $accent_background_color = '#f8fafc',
		string $logo_url = ''
	) {
		?>
<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $title ); ?></title>
		<?php if ( $logo_url ) : ?>
	<link rel="icon" href="<?php echo esc_url( $logo_url ); ?>">
	<?php endif; ?>
	<style>
		:root {
			--accent-color: <?php echo esc_attr( $accent_color ); ?>;
			--accent-background-color: <?php echo esc_attr( $accent_background_color ); ?>;
		}
		* {
			box-sizing: border-box;
			margin: 0;
			padding: 0;
		}

		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			background: var(--accent-background-color);
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

		.club-brand {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 0.5rem;
			margin-bottom: 0.5rem;
		}

		.club-logo {
			width: 5rem;
			height: 5rem;
			object-fit: contain;
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
			background: var(--accent-color);
			color: white;
		}

		.btn-secondary {
			background: #e2e8f0;
			color: #1e293b;
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
