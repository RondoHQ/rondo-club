<?php
/**
 * Public Membership Pass Landing Page
 *
 * Serves a unique public landing page per person at /lidpas/{token} where members
 * can add their pass to Apple Wallet or Google Wallet.
 */

namespace Rondo\Passes;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PublicMembershipPassPage {

	/**
	 * Person post meta key for pass token.
	 */
	const TOKEN_META_KEY = '_membership_pass_token';

	/**
	 * Person post meta key for pass landing URL.
	 */
	const URL_META_KEY = '_membership_pass_url';

	/**
	 * Query var key.
	 */
	const QUERY_VAR = 'rondo_membership_pass_token';

	/**
	 * Token length in bytes (hex-encoded => 64 chars).
	 */
	const TOKEN_LENGTH = 32;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
		add_action( 'init', [ $this, 'maybe_backfill_pass_urls' ], 20 );

		add_action( 'save_post_person', [ $this, 'sync_person_pass_meta' ], 20, 3 );
		add_action( 'acf/save_post', [ $this, 'sync_person_pass_meta_on_acf_save' ], 20 );
	}

	/**
	 * Backfill pass URLs once for existing KNVB members.
	 */
	public function maybe_backfill_pass_urls() {
		$done = (bool) get_option( 'rondo_membership_pass_backfill_done', false );
		if ( $done ) {
			return;
		}

		$people = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'     => 'knvb-id',
						'compare' => 'EXISTS',
					],
					[
						'key'     => 'knvb-id',
						'value'   => '',
						'compare' => '!=',
					],
				],
			]
		);

		foreach ( $people as $person_id ) {
			self::ensure_person_pass_url( (int) $person_id );
		}

		update_option( 'rondo_membership_pass_backfill_done', true, false );
	}

	/**
	 * Register /lidpas/{token} rewrite rule.
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^lidpas/([a-f0-9]{64})$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Add custom query var.
	 *
	 * @param array $vars Query vars.
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Request handler for membership pass pages.
	 */
	public function handle_request() {
		$token = get_query_var( self::QUERY_VAR );
		if ( empty( $token ) ) {
			return;
		}

		$token    = sanitize_key( $token );
		$person_id = self::get_person_by_token( $token );
		if ( $person_id === null ) {
			$this->render_error( 'Ledenpas-link niet gevonden of verlopen.' );
			exit;
		}

		$knvb_id = get_field( 'knvb-id', $person_id );
		if ( empty( $knvb_id ) ) {
			$this->render_error( 'Voor dit lid is geen KNVB ID beschikbaar.' );
			exit;
		}

		$wallet = sanitize_key( $_GET['wallet'] ?? '' );
		if ( $wallet === 'apple' ) {
			$this->output_apple_pass( $person_id );
			exit;
		}

		if ( $wallet === 'google' ) {
			$this->redirect_to_google_wallet( $person_id );
			exit;
		}

		$this->render_page( $person_id, $token );
		exit;
	}

	/**
	 * Ensure membership pass URL meta for a person.
	 *
	 * @param int $person_id Person post ID.
	 * @return string Pass URL or empty string when not eligible.
	 */
	public static function ensure_person_pass_url( int $person_id ): string {
		$post = get_post( $person_id );
		if ( ! $post || $post->post_type !== 'person' ) {
			return '';
		}

		$knvb_id = get_field( 'knvb-id', $person_id );
		if ( empty( $knvb_id ) ) {
			delete_post_meta( $person_id, self::TOKEN_META_KEY );
			delete_post_meta( $person_id, self::URL_META_KEY );
			return '';
		}

		$token = get_post_meta( $person_id, self::TOKEN_META_KEY, true );
		if ( ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			try {
				$token = bin2hex( random_bytes( self::TOKEN_LENGTH ) );
			} catch ( \Throwable $e ) {
				return '';
			}
			update_post_meta( $person_id, self::TOKEN_META_KEY, $token );
		}

		$url = home_url( '/lidpas/' . $token );
		update_post_meta( $person_id, self::URL_META_KEY, esc_url_raw( $url ) );

		return $url;
	}

	/**
	 * Lookup person by token.
	 *
	 * @param string $token Pass token.
	 * @return int|null
	 */
	public static function get_person_by_token( string $token ): ?int {
		$posts = get_posts(
			[
				'post_type'      => 'person',
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

		if ( empty( $posts ) ) {
			return null;
		}

		return (int) $posts[0];
	}

	/**
	 * save_post_person hook handler.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post Post object.
	 * @param bool     $update Whether this is an update.
	 */
	public function sync_person_pass_meta( $post_id, $post, $update ) {
		unset( $post, $update );

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		self::ensure_person_pass_url( (int) $post_id );
	}

	/**
	 * acf/save_post hook handler for person records.
	 *
	 * @param mixed $post_id Post ID or ACF identifier.
	 */
	public function sync_person_pass_meta_on_acf_save( $post_id ) {
		$person_id = (int) $post_id;
		if ( $person_id <= 0 ) {
			return;
		}

		$post = get_post( $person_id );
		if ( ! $post || $post->post_type !== 'person' ) {
			return;
		}

		self::ensure_person_pass_url( $person_id );
	}

	/**
	 * Render landing page.
	 *
	 * @param int    $person_id Person ID.
	 * @param string $token URL token.
	 */
	private function render_page( int $person_id, string $token ) {
		$first_name = (string) ( get_field( 'first_name', $person_id ) ?: '' );
		$infix      = (string) ( get_field( 'infix', $person_id ) ?: '' );
		$last_name  = (string) ( get_field( 'last_name', $person_id ) ?: '' );
		$name       = trim( preg_replace( '/\s+/', ' ', $first_name . ' ' . $infix . ' ' . $last_name ) );
		$knvb_id    = (string) get_field( 'knvb-id', $person_id );

		$fees   = new \Rondo\Fees\MembershipFees();
		$season = $fees->get_season_key();

		$apple_service  = new MembershipPassApple();
		$google_service = new MembershipPassGoogle();

		$apple_available  = $apple_service->is_configured();
		$google_available = $google_service->is_configured();

		$apple_url  = home_url( '/lidpas/' . $token . '?wallet=apple' );
		$google_url = home_url( '/lidpas/' . $token . '?wallet=google' );

		$branding = $this->get_club_branding();

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$this->render_html_header( 'Ledenpas — ' . esc_html( $branding['name'] ), $branding['accent_color'] );
		?>
<div class="container">
	<div class="card header-card">
		<?php if ( $branding['logo_url'] ) : ?>
			<img src="<?php echo esc_url( $branding['logo_url'] ); ?>" alt="<?php echo esc_attr( $branding['name'] ); ?>" class="club-logo" />
		<?php endif; ?>
		<h1>Digitale ledenpas</h1>
	</div>

	<div class="card">
		<h2>Gegevens</h2>
		<table class="info-table">
			<tr><th>Lid</th><td><?php echo esc_html( $name ); ?></td></tr>
			<tr><th>KNVB ID</th><td><?php echo esc_html( $knvb_id ); ?></td></tr>
			<tr><th>Seizoen</th><td><?php echo esc_html( $season ); ?></td></tr>
		</table>
	</div>

	<div class="card">
		<h2>Voeg toe aan Wallet</h2>
		<div class="wallet-actions">
		<?php if ( $apple_available ) : ?>
			<a href="<?php echo esc_url( $apple_url ); ?>" class="wallet-badge wallet-badge-apple" aria-label="Add to Apple Wallet">
				<span class="wallet-badge-apple-icon" aria-hidden="true"></span>
				<span class="wallet-badge-label">Add to Apple Wallet</span>
			</a>
		<?php else : ?>
			<div class="hint">Apple Wallet is nog niet geconfigureerd.</div>
		<?php endif; ?>

		<?php if ( $google_available ) : ?>
			<a href="<?php echo esc_url( $google_url ); ?>" class="wallet-badge wallet-badge-google" aria-label="Add to Google Wallet">
				<img
					src="https://developers.google.com/static/wallet/images/branding/temp-wallet-primary.png"
					alt="Add to Google Wallet"
					loading="lazy"
					decoding="async"
					class="wallet-badge-google-img"
				/>
			</a>
		<?php else : ?>
			<div class="hint">Google Wallet is nog niet geconfigureerd.</div>
		<?php endif; ?>
		</div>
	</div>
</div>
		<?php
		$this->render_html_footer();
	}

	/**
	 * Output Apple pkpass payload.
	 *
	 * @param int $person_id Person ID.
	 */
	private function output_apple_pass( int $person_id ) {
		$service = new MembershipPassApple();
		$result  = $service->generate_for_person( $person_id );

		if ( is_wp_error( $result ) ) {
			$this->render_error( $result->get_error_message() );
			return;
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.apple.pkpass' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $result['filename'] ) . '"' );
		echo $result['content'];
	}

	/**
	 * Redirect to Google add-to-wallet URL.
	 *
	 * @param int $person_id Person ID.
	 */
	private function redirect_to_google_wallet( int $person_id ) {
		$service = new MembershipPassGoogle();
		$result  = $service->get_add_to_wallet_url_for_person( $person_id );

		if ( is_wp_error( $result ) ) {
			$this->render_error( $result->get_error_message() );
			return;
		}

		wp_redirect( $result );
		exit;
	}

	/**
	 * Render error page.
	 *
	 * @param string $message Error message.
	 */
	private function render_error( string $message ) {
		$branding = $this->get_club_branding();

		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$this->render_html_header( 'Ledenpas — fout', $branding['accent_color'] );
		?>
<div class="container">
	<div class="card error-card">
		<h1>Ledenpas niet beschikbaar</h1>
		<p><?php echo esc_html( $message ); ?></p>
	</div>
</div>
		<?php
		$this->render_html_footer();
	}

	/**
	 * Club branding helper.
	 *
	 * @return array{name:string,logo_url:string,accent_color:string}
	 */
	private function get_club_branding(): array {
		$config   = new FinanceConfig();
		$logo_id  = $config->get_club_logo_id();
		$logo_url = '';
		if ( $logo_id > 0 ) {
			$url = wp_get_attachment_url( $logo_id );
			if ( is_string( $url ) ) {
				$logo_url = $url;
			}
		}

		$name = $config->get_org_name();
		if ( $name === '' ) {
			$name = get_bloginfo( 'name' );
		}

		$accent = $config->get_accent_color();
		if ( $accent === '' ) {
			$accent = '#0f766e';
		}

		return [
			'name'         => $name,
			'logo_url'     => $logo_url,
			'accent_color' => $accent,
		];
	}

	/**
	 * Render HTML page header.
	 *
	 * @param string $title Page title.
	 * @param string $accent Accent color.
	 */
	private function render_html_header( string $title, string $accent ) {
		?>
<!doctype html>
<html lang="nl">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		:root { --accent: <?php echo esc_html( $accent ); ?>; }
		* { box-sizing: border-box; }
		body { margin: 0; background: #f7fafc; color: #1f2937; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		.container { max-width: 720px; margin: 0 auto; padding: 20px 14px 36px; }
		.card { background: #fff; border-radius: 12px; border: 1px solid #e5e7eb; padding: 18px; margin-bottom: 12px; }
		.header-card { text-align: center; }
		.club-logo { width: 76px; height: 76px; object-fit: contain; margin-bottom: 8px; }
		h1, h2 { margin: 0 0 10px; }
		.info-table { width: 100%; border-collapse: collapse; }
		.info-table th, .info-table td { border-bottom: 1px solid #e5e7eb; text-align: left; padding: 8px 0; }
		.info-table th { width: 110px; color: #6b7280; font-weight: 600; }
		.wallet-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
		.wallet-badge { display: inline-flex; align-items: center; justify-content: center; text-decoration: none; margin-top: 0; height: 54px; }
		.wallet-badge-apple { background: #000; color: #fff; border-radius: 10px; padding: 0 16px; font-size: 17px; font-weight: 600; letter-spacing: .01em; }
		.wallet-badge-apple-icon { font-size: 20px; line-height: 1; margin-right: 8px; }
		.wallet-badge-label { line-height: 1; white-space: nowrap; }
		.wallet-badge-google { border-radius: 999px; overflow: hidden; }
		.wallet-badge-google-img { display: block; width: auto; height: 54px; }
		.hint { margin-top: 10px; color: #6b7280; font-size: 14px; }
		.error-card { border-color: #fecaca; background: #fef2f2; }
		@media (max-width: 520px) {
			.wallet-actions { flex-direction: column; align-items: stretch; }
			.wallet-badge { width: 100%; }
			.wallet-badge-google { justify-content: center; }
			.wallet-badge-google-img { max-width: 100%; margin: 0 auto; }
		}
	</style>
</head>
<body>
		<?php
	}

	/**
	 * Render HTML footer.
	 */
	private function render_html_footer() {
		?>
</body>
</html>
		<?php
	}
}
