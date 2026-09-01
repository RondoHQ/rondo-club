<?php
/**
 * Public OpenID Connect endpoints and consent pages.
 *
 * @package Rondo\Identity
 */

namespace Rondo\Identity;

use Rondo\Pages\PublicPageChrome;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Route standards-facing HTTP requests into the OIDC service layer. */
final class OidcProvider {

	private const QUERY_ENDPOINT     = 'rondo_oidc_endpoint';
	private const QUERY_VERIFY_TOKEN = 'rondo_oidc_verification_token';

	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
		add_action( 'rondo_oidc_cleanup_token_lock', [ OidcAuthorizationService::class, 'cleanup_token_lock' ] );
	}

	/** Register stable public endpoints without exposing WordPress internals. */
	public function register_rewrite_rules(): void {
		add_rewrite_rule( '^oauth/\.well-known/openid-configuration/?$', 'index.php?' . self::QUERY_ENDPOINT . '=discovery', 'top' );
		add_rewrite_rule( '^oauth/authorize/?$', 'index.php?' . self::QUERY_ENDPOINT . '=authorize', 'top' );
		add_rewrite_rule( '^oauth/token/?$', 'index.php?' . self::QUERY_ENDPOINT . '=token', 'top' );
		add_rewrite_rule( '^oauth/userinfo/?$', 'index.php?' . self::QUERY_ENDPOINT . '=userinfo', 'top' );
		add_rewrite_rule( '^oauth/jwks/?$', 'index.php?' . self::QUERY_ENDPOINT . '=jwks', 'top' );
		add_rewrite_rule(
			'^oauth/verify-email/([A-Za-z0-9_-]{43})/?$',
			'index.php?' . self::QUERY_ENDPOINT . '=verify-email&' . self::QUERY_VERIFY_TOKEN . '=$matches[1]',
			'top'
		);
	}

	/** Add only the two internal query variables used by the endpoint router. */
	public function register_query_vars( array $vars ): array {
		$vars[] = self::QUERY_ENDPOINT;
		$vars[] = self::QUERY_VERIFY_TOKEN;

		return $vars;
	}

	/** Dispatch an OIDC request and terminate before the SPA template renders. */
	public function handle_request(): void {
		$endpoint = (string) get_query_var( self::QUERY_ENDPOINT );
		if ( $endpoint === '' ) {
			return;
		}

		switch ( $endpoint ) {
			case 'discovery':
			case 'metadata':
				$this->require_method( 'GET' );
				$this->json_response( OidcAuthorizationService::metadata() );
				break;
			case 'jwks':
				$this->require_method( 'GET' );
				$this->json_response( OidcKeyStore::jwks() );
				break;
			case 'token':
				$this->handle_token();
				break;
			case 'userinfo':
				$this->handle_userinfo();
				break;
			case 'authorize':
				$this->handle_authorize();
				break;
			case 'verify-email':
				$this->handle_email_verification();
				break;
			default:
				$this->json_response( [ 'error' => 'not_found' ], 404 );
		}
	}

	private function handle_token(): void {
		$this->require_method( 'POST' );
		$result = OidcAuthorizationService::exchange_code(
			$this->request_params(),
			$this->authorization_header()
		);
		$this->send_service_response( $result, 'Basic realm="Rondo OIDC"' );
	}

	private function handle_userinfo(): void {
		$this->require_method( 'GET' );
		$result = OidcAuthorizationService::userinfo( $this->authorization_header() );
		$this->send_service_response( $result, 'Bearer error="invalid_token"' );
	}

	private function handle_authorize(): void {
		$this->require_login();
		if ( $this->request_method() === 'POST' ) {
			$this->handle_authorize_post();
			return;
		}

		$this->require_method( 'GET' );
		$result = OidcAuthorizationService::prepare_authorization( $this->request_params(), get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->handle_authorization_error( $result );
			return;
		}

		if ( $result['status'] === 'verification_required' ) {
			$this->render_verification( $result );
			return;
		}

		$this->render_consent( $result );
	}

	private function handle_authorize_post(): void {
		$params = $this->request_params();
		if ( ! wp_verify_nonce( (string) ( $params['_wpnonce'] ?? '' ), 'rondo_oidc_authorize' ) ) {
			$this->render_error( 'De autorisatiepagina is verlopen. Start opnieuw vanuit FreeScout.', 403 );
			return;
		}

		$pending = (string) ( $params['pending_token'] ?? '' );
		$action  = (string) ( $params['rondo_oidc_action'] ?? '' );
		if ( $action === 'send_verification' ) {
			$result = OidcAuthorizationService::send_verification( $pending, get_current_user_id(), $this->remote_ip() );
			if ( is_wp_error( $result ) ) {
				$this->render_error( $result->get_error_message(), $this->error_status( $result ) );
				return;
			}
			$this->render_message( 'Controleer je e-mail', 'We hebben een beveiligde bevestigingslink verstuurd. Open die link om verder te gaan.' );
			return;
		}

		if ( $action !== 'approve' && $action !== 'deny' ) {
			$this->render_error( 'Deze keuze wordt niet herkend.', 400 );
			return;
		}

		$redirect = OidcAuthorizationService::decide( $pending, get_current_user_id(), $action === 'approve' );
		if ( is_wp_error( $redirect ) ) {
			$this->render_error( $redirect->get_error_message(), $this->error_status( $redirect ) );
			return;
		}

		wp_redirect( $redirect, 302, 'Rondo OIDC' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Exact registered client URI, revalidated by the service.
		exit;
	}

	private function handle_email_verification(): void {
		$this->require_method( 'GET' );
		$this->require_login();
		$token  = (string) get_query_var( self::QUERY_VERIFY_TOKEN );
		$result = OidcAuthorizationService::consume_verification( $token, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$this->render_error( $result->get_error_message(), $this->error_status( $result ) );
			return;
		}

		$this->render_consent( $result );
	}

	private function handle_authorization_error( \WP_Error $error ): void {
		$data     = (array) $error->get_error_data();
		$redirect = (string) ( $data['redirect_uri'] ?? '' );
		if ( $redirect !== '' ) {
			$url = OidcAuthorizationService::append_query(
				$redirect,
				array_filter(
					[
						'error' => (string) ( $data['oauth_error'] ?? 'invalid_request' ),
						'state' => (string) ( $data['state'] ?? '' ),
					],
					static fn( string $value ): bool => $value !== ''
				)
			);
			wp_redirect( $url, 302, 'Rondo OIDC' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Redirect URI was validated before it entered the error.
			exit;
		}

		$this->render_error( $error->get_error_message(), $this->error_status( $error ) );
	}

	private function render_consent( array $context ): void {
		$this->open_page( 'Inloggen bij FreeScout' );
		?>
		<div class="card">
			<h2>Toegang bevestigen</h2>
			<p><strong><?php echo esc_html( (string) $context['client_label'] ); ?></strong> ontvangt je vaste Rondo-identiteit en je bevestigde e-mailadres.</p>
			<?php if ( in_array( 'profile', (array) $context['scopes'], true ) ) : ?>
				<p class="confirmation-help">Ook je naam wordt gedeeld, zodat FreeScout je account herkenbaar kan tonen.</p>
			<?php endif; ?>
			<p class="confirmation-help"><strong><?php echo esc_html( (string) $context['email'] ); ?></strong></p>
		</div>
		<form method="post" class="plan-form">
			<?php wp_nonce_field( 'rondo_oidc_authorize' ); ?>
			<input type="hidden" name="pending_token" value="<?php echo esc_attr( (string) $context['pending_token'] ); ?>">
			<input type="hidden" name="rondo_oidc_action" value="approve">
			<button type="submit" class="btn btn-primary">Doorgaan naar FreeScout</button>
		</form>
		<form method="post" class="plan-form">
			<?php wp_nonce_field( 'rondo_oidc_authorize' ); ?>
			<input type="hidden" name="pending_token" value="<?php echo esc_attr( (string) $context['pending_token'] ); ?>">
			<input type="hidden" name="rondo_oidc_action" value="deny">
			<button type="submit" class="btn btn-secondary">Annuleren</button>
		</form>
		<?php
		$this->close_page();
	}

	private function render_verification( array $context ): void {
		$this->open_page( 'E-mailadres bevestigen' );
		?>
		<div class="card">
			<h2>Eenmalige controle</h2>
			<p>Voor FreeScout moet Rondo eerst controleren dat je toegang hebt tot <strong><?php echo esc_html( (string) $context['email'] ); ?></strong>.</p>
			<p class="confirmation-help">De bevestigingslink is twee uur geldig en bevat geen FreeScout-inloggegevens.</p>
		</div>
		<form method="post" class="plan-form">
			<?php wp_nonce_field( 'rondo_oidc_authorize' ); ?>
			<input type="hidden" name="pending_token" value="<?php echo esc_attr( (string) $context['pending_token'] ); ?>">
			<input type="hidden" name="rondo_oidc_action" value="send_verification">
			<button type="submit" class="btn btn-primary">Stuur bevestigingsmail</button>
		</form>
		<?php
		$this->close_page();
	}

	private function render_message( string $heading, string $message ): void {
		$this->open_page( $heading );
		?>
		<div class="card success-card">
			<div class="success-icon" aria-hidden="true">✓</div>
			<h2><?php echo esc_html( $heading ); ?></h2>
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
		$this->close_page();
	}

	private function render_error( string $message, int $status ): void {
		status_header( $status );
		$this->open_page( 'Inloggen niet gelukt' );
		?>
		<div class="card error-card">
			<h2>Dat lukte niet</h2>
			<p><?php echo esc_html( $message ); ?></p>
		</div>
		<?php
		$this->close_page();
	}

	private function open_page( string $heading ): void {
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );
		$branding = PublicPageChrome::branding();
		PublicPageChrome::header( $heading . ' — ' . $branding['name'], $branding['accent_color'], $branding['accent_background_color'], $branding['logo_url'] );
		echo '<div class="container">';
		PublicPageChrome::header_card( $heading );
	}

	private function close_page(): void {
		echo '</div>';
		PublicPageChrome::footer();
		exit;
	}

	private function send_service_response( $result, string $challenge ): void {
		if ( is_wp_error( $result ) ) {
			$data = (array) $result->get_error_data();
			if ( (int) ( $data['status'] ?? 400 ) === 401 ) {
				header( 'WWW-Authenticate: ' . $challenge );
			}
			$this->json_response(
				[
					'error'             => (string) ( $data['oauth_error'] ?? 'invalid_request' ),
					'error_description' => $result->get_error_message(),
				],
				$this->error_status( $result )
			);
		}

		$this->json_response( $result );
	}

	private function json_response( array $body, int $status = 200 ): void {
		status_header( $status );
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Cache-Control: no-store' );
		header( 'Pragma: no-cache' );
		echo wp_json_encode( $body );
		exit;
	}

	private function require_login(): void {
		if ( is_user_logged_in() ) {
			return;
		}
		wp_safe_redirect( wp_login_url( $this->current_url() ) );
		exit;
	}

	private function require_method( string $allowed ): void {
		if ( $this->request_method() === $allowed ) {
			return;
		}
		header( 'Allow: ' . $allowed );
		$this->json_response(
			[
				'error'             => 'invalid_request',
				'error_description' => 'Deze HTTP-methode wordt niet ondersteund.',
			],
			405
			);
	}

	private function request_params(): array {
		return array_map( 'wp_unslash', $this->request_method() === 'POST' ? $_POST : $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.NonceVerification.Missing -- OIDC GET parameters and token POSTs have protocol-level validation; browser form nonces are verified separately.
	}

	private function authorization_header(): string {
		return (string) ( $_SERVER['HTTP_AUTHORIZATION'] ?? '' );
	}

	private function request_method(): string {
		return strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
	}

	private function current_url(): string {
		$request_uri = (string) ( $_SERVER['REQUEST_URI'] ?? '/' );

		return home_url( wp_validate_redirect( $request_uri, '/' ) );
	}

	private function remote_ip(): string {
		$ip = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
	}

	private function error_status( \WP_Error $error ): int {
		$data = (array) $error->get_error_data();

		return max( 400, min( 599, (int) ( $data['status'] ?? 400 ) ) );
	}
}
