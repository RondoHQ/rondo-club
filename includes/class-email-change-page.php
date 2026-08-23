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
		$token = (string) get_query_var( 'rondo_email_change_token' );
		if ( $token === '' ) {
			return;
		}

		$result   = MemberProfileService::verify_email_token( sanitize_key( $token ) );
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
}
