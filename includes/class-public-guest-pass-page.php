<?php
/**
 * Public guest-pass claim, Wallet and QR page.
 *
 * @package Rondo\Passes
 */

namespace Rondo\Passes;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Serves /gastpas/{token} without requiring a Rondo account. */
class PublicGuestPassPage {

	private GuestPassService $service;

	public function __construct( ?GuestPassService $service = null ) {
		$this->service = $service ?? new GuestPassService();
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
	}

	/** Register the 64-character bearer URL. */
	public function register_rewrite_rules(): void {
		add_rewrite_rule( '^gastpas/([a-f0-9]{64})/?$', 'index.php?rondo_guest_pass_token=$matches[1]', 'top' );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'rondo_guest_pass_token';
		return $vars;
	}

	/** Render, claim or download the pass belonging to this bearer URL. */
	public function handle_request(): void {
		$share_token = sanitize_key( (string) get_query_var( 'rondo_guest_pass_token' ) );
		if ( $share_token === '' ) {
			return;
		}

		$slot = $this->service->get_by_share_token( $share_token );
		if ( $slot === null ) {
			$this->render_error( 'Deze gastlink is niet meer geldig.' );
		}

		$pass = $this->service->get_pass_data( (int) $slot['id'] );
		if ( $pass === null || ! $this->service->is_eligible_player( $pass['host_person_id'] ) ) {
			$this->render_error( 'Deze gastpas is momenteel niet beschikbaar.' );
		}

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
			$this->handle_claim( $share_token, (int) $slot['id'] );
			$slot = $this->service->get_by_share_token( $share_token );
			$pass = $this->service->get_pass_data( (int) $slot['id'] );
		}

		// The bearer URL is the authorization boundary for guest-owned Wallet downloads.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$wallet = isset( $_GET['wallet'] ) ? sanitize_key( wp_unslash( $_GET['wallet'] ) ) : '';
		if ( $wallet !== '' ) {
			if ( $pass['status'] !== 'active' ) {
				$this->render_error( 'Registreer eerst je naam voordat je de gastpas toevoegt.' );
			}
			$this->handle_wallet( (int) $slot['id'], $wallet );
		}

		$this->render_page( $share_token, $slot, $pass );
	}

	private function handle_claim( string $share_token, int $pass_id ): void {
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rondo_guest_pass_claim_' . $pass_id ) ) {
			$this->render_error( 'De pagina was te lang open. Vernieuw de pagina en probeer opnieuw.' );
		}

		$guest_name = isset( $_POST['guest_name'] ) ? sanitize_text_field( wp_unslash( $_POST['guest_name'] ) ) : '';
		$result     = $this->service->claim( $share_token, $guest_name );
		if ( is_wp_error( $result ) ) {
			$this->render_error( $result->get_error_message(), 400 );
		}
	}

	private function handle_wallet( int $pass_id, string $wallet ): void {
		if ( $wallet === 'apple' ) {
			$result = ( new MembershipPassApple() )->generate_for_guest( $pass_id );
			if ( is_wp_error( $result ) ) {
				$this->render_error( $result->get_error_message(), 500 );
			}
			nocache_headers();
			header( 'Content-Type: application/vnd.apple.pkpass' );
			header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $result['filename'] ) . '"' );
			header( 'Content-Length: ' . strlen( $result['content'] ) );
			echo $result['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Signed binary pass.
			exit;
		}

		if ( $wallet === 'google' ) {
			$url = ( new MembershipPassGoogle() )->get_add_to_wallet_url_for_guest( $pass_id );
			if ( is_wp_error( $url ) ) {
				$this->render_error( $url->get_error_message(), 500 );
			}
			wp_redirect( $url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Google Wallet is the intended external destination.
			exit;
		}

		$this->render_error( 'Onbekende walletkeuze.', 400 );
	}

	private function render_page( string $share_token, array $slot, array $pass ): void {
		$config     = new FinanceConfig();
		$brand_name = $config->get_display_name() ?: get_bloginfo( 'name' );
		$accent     = MembershipPassService::get_background_color_hex();
		$logo_url   = $this->get_logo_url( $config );
		$team_name  = $this->service->get_eligible_team_name();

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Referrer-Policy: no-referrer', true );
		?>
<!doctype html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<title><?php echo esc_html( 'Gastpas — ' . $brand_name ); ?></title>
	<style>
		:root { color-scheme: light; --accent: <?php echo esc_html( $accent ); ?>; }
		* { box-sizing: border-box; }
		body { margin: 0; min-height: 100vh; background: #f3f4f6; color: #111827; font: 16px/1.5 system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
		main { width: min(100% - 32px, 520px); margin: 0 auto; padding: max(24px, env(safe-area-inset-top)) 0 40px; }
		.card { overflow: hidden; border-radius: 20px; background: white; box-shadow: 0 12px 35px rgba(17, 24, 39, .12); }
		.pass-head { padding: 24px; background: var(--accent); color: white; }
		.logo { display: block; width: 72px; height: 72px; margin-bottom: 20px; object-fit: contain; border-radius: 14px; background: white; padding: 7px; }
		h1 { margin: 0; font-size: 1.65rem; line-height: 1.2; }
		.subtitle { margin: 6px 0 0; opacity: .9; }
		.content { padding: 24px; }
		h2 { margin: 0 0 8px; font-size: 1.15rem; }
		p { margin: 0 0 18px; }
		label { display: block; margin-bottom: 6px; font-weight: 650; }
		input { width: 100%; border: 1px solid #d1d5db; border-radius: 10px; padding: 12px 14px; font: inherit; }
		button, .button { display: inline-flex; min-height: 46px; align-items: center; justify-content: center; border: 0; border-radius: 10px; padding: 11px 17px; background: var(--accent); color: white; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
		.wallets { display: flex; flex-wrap: wrap; gap: 10px; margin: 18px 0; }
		.wallet { display: inline-flex; min-height: 48px; align-items: center; border-radius: 8px; background: #050505; padding: 7px 12px; }
		.wallet img { display: block; height: 34px; width: auto; }
		.qr { display: block; width: min(100%, 310px); margin: 18px auto 0; }
		.meta { display: grid; gap: 12px; margin: 20px 0; }
		.meta div { border-top: 1px solid #e5e7eb; padding-top: 12px; }
		.meta span { display: block; color: #6b7280; font-size: .8rem; }
		.note { color: #4b5563; font-size: .9rem; }
	</style>
</head>
<body>
<main>
	<section class="card">
		<header class="pass-head">
			<?php
			if ( $logo_url !== '' ) :
				?>
				<img class="logo" src="<?php echo esc_url( $logo_url ); ?>" alt=""><?php endif; ?>
			<h1>Gastpas <?php echo esc_html( $brand_name ); ?></h1>
			<p class="subtitle">Voor thuiswedstrijden van <?php echo esc_html( $team_name ); ?></p>
		</header>
		<div class="content">
			<?php if ( $pass['status'] !== 'active' ) : ?>
				<h2>Registreer je gastpas</h2>
				<p>Vul één keer je naam in. Daarna kun je deze pas bij iedere thuiswedstrijd opnieuw gebruiken.</p>
				<form method="post">
					<?php wp_nonce_field( 'rondo_guest_pass_claim_' . (int) $slot['id'] ); ?>
					<label for="guest_name">Je volledige naam</label>
					<input id="guest_name" name="guest_name" type="text" minlength="2" maxlength="100" autocomplete="name" required autofocus>
					<button type="submit" style="margin-top: 14px">Gastpas activeren</button>
				</form>
			<?php else : ?>
				<h2><?php echo esc_html( $pass['guest_name'] ); ?></h2>
				<div class="meta">
					<div><span>Gast van</span><?php echo esc_html( $pass['host_name'] ); ?></div>
					<div><span>Geldig voor</span><?php echo esc_html( $team_name ); ?> thuiswedstrijden</div>
				</div>
				<p>Bewaar deze pas op je telefoon. Je hoeft voor de volgende wedstrijd geen nieuwe pas aan te vragen.</p>
				<div class="wallets">
					<?php if ( ( new MembershipPassApple() )->is_configured() ) : ?>
						<a class="wallet" href="<?php echo esc_url( add_query_arg( 'wallet', 'apple', home_url( '/gastpas/' . $share_token ) ) ); ?>"><img src="<?php echo esc_url( get_template_directory_uri() . '/public/icons/NL_Add_to_Apple_Wallet_RGB_101921.svg' ); ?>" alt="Voeg toe aan Apple Wallet"></a>
					<?php endif; ?>
					<?php if ( ( new MembershipPassGoogle() )->is_configured() ) : ?>
						<a class="wallet" href="<?php echo esc_url( add_query_arg( 'wallet', 'google', home_url( '/gastpas/' . $share_token ) ) ); ?>"><img src="<?php echo esc_url( get_template_directory_uri() . '/public/icons/nl_add_to_google_wallet_add-wallet-badge.svg' ); ?>" alt="Voeg toe aan Google Wallet"></a>
					<?php endif; ?>
				</div>
				<img class="qr" src="<?php echo esc_attr( $this->render_qr_data_uri( (int) $slot['id'] ) ); ?>" alt="QR-code van de gastpas">
				<p class="note">Per gastpas kan per wedstrijd één persoon naar binnen. Bij vervanging door de speler wordt deze QR-code ongeldig.</p>
			<?php endif; ?>
		</div>
	</section>
</main>
</body>
</html>
		<?php
		exit;
	}

	private function render_qr_data_uri( int $pass_id ): string {
		$result = ( new MembershipPassQr() )->issue_for_guest( $pass_id );
		if ( is_wp_error( $result ) ) {
			return '';
		}
		$options = new QROptions(
			[
				'outputBase64'  => true,
				'scale'         => 8,
				'quietzoneSize' => 3,
			]
		);
		return (string) ( new QRCode( $options ) )->render( $result['token'] );
	}

	private function get_logo_url( FinanceConfig $config ): string {
		$logo_id = $config->get_club_logo_id();
		$url     = $logo_id > 0 ? wp_get_attachment_url( $logo_id ) : '';
		return is_string( $url ) && $url !== '' ? $url : get_template_directory_uri() . '/public/icons/apple-touch-icon-180x180.png';
	}

	private function render_error( string $message, int $status = 404 ): void {
		status_header( $status );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, nofollow', true );
		echo '<!doctype html><html lang="nl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gastpas</title><body style="font:16px/1.5 system-ui;background:#f3f4f6;color:#111827"><main style="max-width:520px;margin:64px auto;padding:24px;background:white;border-radius:16px"><h1>Gastpas niet beschikbaar</h1><p>' . esc_html( $message ) . '</p></main></body></html>';
		exit;
	}
}
