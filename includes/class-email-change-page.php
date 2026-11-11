<?php
/**
 * Public email-change verification page.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Pages\PublicPageChrome;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmailChangePage {

	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
	}

	public function register_rewrite_rules(): void {
		add_rewrite_rule( '^email-wijzigen/([a-f0-9]{64})/?$', 'index.php?rondo_email_change_token=$matches[1]', 'top' );
	}

	public function add_query_vars( array $vars ): array {
		$vars[] = 'rondo_email_change_token';
		return $vars;
	}

	public function handle_request(): void {
		$token = sanitize_key( (string) get_query_var( 'rondo_email_change_token' ) );
		if ( $token === '' ) {
			return;
		}

		// A bare GET must never apply the change: mail scanners and link
		// prefetchers follow verification links automatically. GET renders a
		// confirmation form; only its POST, protected by a nonce, applies.
		$is_confirmation = isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST'
			&& isset( $_POST['rondo_email_change_nonce'] )
			&& wp_verify_nonce( sanitize_key( wp_unslash( $_POST['rondo_email_change_nonce'] ) ), 'rondo_email_change_confirm' );

		if ( $is_confirmation ) {
			$this->render_result( MemberProfileService::verify_email_token( $token ) );
		}

		$this->render_confirmation( MemberProfileService::peek_email_token( $token ) );
	}

	/**
	 * Render the outcome of an applied (or failed) email change.
	 *
	 * @param mixed $result Result of MemberProfileService::verify_email_token().
	 */
	private function render_result( $result ): void {
		$branding = PublicPageChrome::branding();
		$title    = is_wp_error( $result ) ? 'E-mailadres niet gewijzigd' : 'E-mailadres bevestigd';
		status_header( is_wp_error( $result ) ? 400 : 200 );
		PublicPageChrome::header( $title, $branding['accent_color'], $branding['accent_background_color'], $branding['logo_url'] );
		echo '<div class="container">';
		PublicPageChrome::header_card( $title );
		if ( is_wp_error( $result ) ) {
			echo '<div class="card error-card"><h2>De link werkt niet meer</h2><p>' . esc_html( $result->get_error_message() ) . '</p><p class="error-hint">Vraag vanuit Mijn gegevens een nieuwe verificatielink aan.</p></div>';
		} else {
			echo '<div class="card success-card"><div class="success-icon">&#10003;</div><h2>Je e-mailadres is aangepast</h2><p>De wijziging staat in Rondo en wordt verwerkt in Sportlink, het systeem van de KNVB.</p><p><a class="btn btn-primary" style="display:inline-block;margin-top:1rem" href="' . esc_url( home_url( '/mijn-gegevens' ) ) . '">Terug naar Mijn gegevens</a></p></div>';
		}
		echo '</div>';
		PublicPageChrome::footer();
		exit;
	}

	/**
	 * Render the confirmation form for a pending change, or the error card
	 * when the token no longer validates.
	 *
	 * @param mixed $pending Result of MemberProfileService::peek_email_token().
	 */
	private function render_confirmation( $pending ): void {
		if ( is_wp_error( $pending ) ) {
			$this->render_result( $pending );
		}

		$branding = PublicPageChrome::branding();
		$title    = 'E-mailadres bevestigen';
		status_header( 200 );
		PublicPageChrome::header( $title, $branding['accent_color'], $branding['accent_background_color'], $branding['logo_url'] );
		echo '<div class="container">';
		PublicPageChrome::header_card( $title );
		echo '<div class="card"><h2>Bevestig je nieuwe e-mailadres</h2><p>Je staat op het punt om <strong>' . esc_html( (string) $pending['new_email'] ) . '</strong> in te stellen als e-mailadres in Rondo. Dit wordt ook verwerkt in Sportlink, het systeem van de KNVB.</p>';
		echo '<form method="post" action="' . esc_url( home_url( '/email-wijzigen/' . get_query_var( 'rondo_email_change_token' ) ) ) . '">';
		wp_nonce_field( 'rondo_email_change_confirm', 'rondo_email_change_nonce' );
		echo '<p><button type="submit" class="btn btn-primary">E-mailadres wijzigen</button></p></form>';
		echo '<p class="error-hint">Heb je dit niet aangevraagd, sluit dan deze pagina. Er verandert niets.</p></div>';
		echo '</div>';
		PublicPageChrome::footer();
		exit;
	}
}
