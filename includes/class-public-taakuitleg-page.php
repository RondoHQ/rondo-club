<?php
/**
 * Public Taakuitleg Landing Page
 *
 * Handles the public-facing task-instruction page at /uitleg/{slug} where
 * volunteers can read how to perform a task ("how to use and clean the frying
 * pan") without logging in. This is the target of the printable QR codes that
 * get stuck onto equipment.
 *
 * The page renders a standalone, print-friendly HTML page (no WordPress theme,
 * no React SPA). Editing happens in the authenticated React SPA; this page is
 * read-only and intentionally requires no authentication.
 *
 * It mirrors the PublicPaymentPage pattern: a custom rewrite rule, a query var,
 * and a template_redirect handler at priority 0 (before the SPA catch-all at
 * priority 1).
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Config\ClubConfig;
use Rondo\Core\PostTitle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public taakuitleg landing page handler.
 */
class PublicTaakuitlegPage {

	/**
	 * URL path prefix for the public page. Keep in sync with the React print
	 * view that builds the QR target and with the developer docs.
	 */
	const PATH_PREFIX = 'uitleg';

	/**
	 * Query var carrying the matched post slug.
	 */
	const QUERY_VAR = 'rondo_taakuitleg_slug';

	/**
	 * Constructor — register WordPress hooks.
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
	}

	/**
	 * Build the public URL for a given taakuitleg post.
	 *
	 * Shared helper so the slug-to-URL convention lives in exactly one place.
	 *
	 * @param string $slug Post slug (post_name).
	 * @return string Absolute URL to the public page.
	 */
	public static function get_public_url( string $slug ): string {
		return home_url( '/' . self::PATH_PREFIX . '/' . $slug );
	}

	/**
	 * Register the rewrite rule for the public page.
	 *
	 * Matches /uitleg/{slug} where slug is a standard WordPress post slug
	 * (lowercase letters, digits and hyphens).
	 */
	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^' . self::PATH_PREFIX . '/([a-z0-9-]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Add the slug query var to WordPress.
	 *
	 * @param array $vars Existing query vars.
	 * @return array Query vars with our slug var added.
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Main request dispatcher for /uitleg/{slug}.
	 *
	 * Fires at template_redirect priority 0, before the React SPA catch-all at
	 * priority 1. Always exits after rendering to prevent the SPA from firing.
	 */
	public function handle_request() {
		$slug = get_query_var( self::QUERY_VAR );
		if ( empty( $slug ) ) {
			return; // Not our request.
		}

		$slug = sanitize_title( $slug );
		$post = $this->get_published_taakuitleg_by_slug( $slug );

		if ( $post === null ) {
			$this->render_error();
			exit;
		}

		$this->render_page( $post );
		exit;
	}

	/**
	 * Look up a published taakuitleg post by its slug.
	 *
	 * Only `publish` status is matched — drafts and trashed posts return null so
	 * a printed QR for an unpublished entry shows the not-found page rather than
	 * leaking work-in-progress content.
	 *
	 * @param string $slug Sanitized post slug.
	 * @return \WP_Post|null The post, or null if not found / not published.
	 */
	private function get_published_taakuitleg_by_slug( string $slug ): ?\WP_Post {
		$posts = get_posts(
			[
				'post_type'        => 'taakuitleg',
				'name'             => $slug,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'suppress_filters' => true,
			]
		);

		return $posts[0] ?? null;
	}

	/**
	 * Render the read-only instruction page.
	 *
	 * @param \WP_Post $post The taakuitleg post.
	 */
	private function render_page( \WP_Post $post ) {
		// Raw title: the markup below escapes it, so a texturized `&#8211;` would
		// be double-escaped and shown as literal entity text.
		$title        = PostTitle::plain( (int) $post->ID );
		$body_html    = $this->prepare_body( $post->post_content );
		$dienst_types = $this->get_dienst_type_names( $post->ID );
		$modified     = get_the_modified_date( 'j F Y', $post );
		$branding     = $this->get_branding();

		status_header( 200 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$this->render_html_header( $title . ' — ' . $branding['name'], $branding );
		?>

<div class="container">
	<div class="card header-card">
		<?php if ( $branding['logo_url'] ) : ?>
			<img src="<?php echo esc_url( $branding['logo_url'] ); ?>" alt="<?php echo esc_attr( $branding['name'] ); ?>" class="club-logo" />
		<?php endif; ?>
		<h1><?php echo esc_html( $title ); ?></h1>
		<?php if ( ! empty( $dienst_types ) ) : ?>
			<div class="badges">
				<?php foreach ( $dienst_types as $name ) : ?>
					<span class="badge"><?php echo esc_html( $name ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="card content">
		<?php
		// Already sanitized in prepare_body() via wp_kses_post().
		echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>

	<div class="footer no-print">
		<button type="button" onclick="window.print()" class="btn btn-primary">Print deze uitleg</button>
		<p class="updated">Laatst bijgewerkt: <?php echo esc_html( $modified ); ?></p>
	</div>
</div>

		<?php
		$this->render_html_footer();
	}

	/**
	 * Sanitize and normalize the stored rich-text body for public output.
	 *
	 * The body is authored in the SPA's Tiptap editor and stored as HTML in
	 * post_content. We run it through wp_kses_post() so only safe markup
	 * survives, then ensure inline images carry loading="lazy".
	 *
	 * @param string $content Raw post_content HTML.
	 * @return string Safe HTML ready to echo.
	 */
	private function prepare_body( string $content ): string {
		$content = wp_kses_post( $content );

		if ( trim( wp_strip_all_tags( $content ) ) === '' && strpos( $content, '<img' ) === false ) {
			return '<p class="empty">Voor deze taak is nog geen uitleg toegevoegd.</p>';
		}

		return $content;
	}

	/**
	 * Resolve the linked dienst_type names for display badges.
	 *
	 * @param int $post_id Taakuitleg post ID.
	 * @return string[] List of dienst_type titles.
	 */
	private function get_dienst_type_names( int $post_id ): array {
		$ids = get_post_meta( $post_id, 'dienst_types', true );
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return [];
		}

		$names = [];
		foreach ( $ids as $id ) {
			$name = PostTitle::plain( (int) $id );
			if ( $name !== '' ) {
				$names[] = $name;
			}
		}

		return $names;
	}

	/**
	 * Render the not-found page (HTTP 404).
	 */
	private function render_error() {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		$branding = $this->get_branding();
		$this->render_html_header( 'Niet gevonden — ' . $branding['name'], $branding );
		?>

<div class="container">
	<div class="card header-card">
		<?php if ( $branding['logo_url'] ) : ?>
			<img src="<?php echo esc_url( $branding['logo_url'] ); ?>" alt="<?php echo esc_attr( $branding['name'] ); ?>" class="club-logo" />
		<?php endif; ?>
		<h1>Uitleg niet gevonden</h1>
	</div>
	<div class="card content">
		<p>Deze taakuitleg bestaat niet (meer) of is nog niet gepubliceerd.</p>
		<p class="updated">Controleer of je de juiste QR-code hebt gescand.</p>
	</div>
</div>

		<?php
		$this->render_html_footer();
	}

	/**
	 * Resolve club branding (name + logo) for the standalone page.
	 *
	 * @return array{name: string, logo_url: string, accent_color: string}
	 */
	private function get_branding(): array {
		$name = ClubConfig::get_club_name();
		if ( $name === '' ) {
			$name = get_bloginfo( 'name' );
		}

		$logo_url = '';
		$logo_id  = (int) get_theme_mod( 'custom_logo' );
		if ( $logo_id > 0 ) {
			$url = wp_get_attachment_url( $logo_id );
			if ( $url ) {
				$logo_url = $url;
			}
		}

		return [
			'name'         => $name,
			'logo_url'     => $logo_url,
			'accent_color' => '#0891b2',
		];
	}

	/**
	 * Render the HTML header with inline, print-friendly CSS.
	 *
	 * @param string $title    Page <title>.
	 * @param array  $branding Branding array from get_branding().
	 */
	private function render_html_header( string $title, array $branding ) {
		?>
<!DOCTYPE html>
<html lang="nl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex">
	<title><?php echo esc_html( $title ); ?></title>
		<?php if ( $branding['logo_url'] ) : ?>
	<link rel="icon" href="<?php echo esc_url( $branding['logo_url'] ); ?>">
	<?php endif; ?>
	<style>
		:root {
			--accent-color: <?php echo esc_attr( $branding['accent_color'] ); ?>;
		}
		* { box-sizing: border-box; margin: 0; padding: 0; }
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			background: #f8fafc;
			color: #1e293b;
			line-height: 1.6;
			padding: 1rem;
		}
		.container { max-width: 720px; margin: 0 auto; }
		.card {
			background: #fff;
			border-radius: 12px;
			padding: 1.5rem;
			margin-bottom: 1rem;
			box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
		}
		.header-card { text-align: center; padding-top: 2rem; }
		.club-logo { width: 4rem; height: 4rem; object-fit: contain; margin-bottom: 0.75rem; }
		h1 { font-size: 1.75rem; font-weight: 700; color: #0f172a; }
		.badges { margin-top: 0.75rem; display: flex; flex-wrap: wrap; gap: 0.375rem; justify-content: center; }
		.badge {
			display: inline-block;
			background: #e0f2fe;
			color: #075985;
			font-size: 0.8125rem;
			font-weight: 600;
			padding: 0.2rem 0.625rem;
			border-radius: 9999px;
		}
		.content { font-size: 1.0625rem; }
		.content p { margin-bottom: 1rem; }
		.content p:last-child { margin-bottom: 0; }
		.content ul, .content ol { margin: 0 0 1rem 1.5rem; }
		.content li { margin-bottom: 0.375rem; }
		.content a { color: var(--accent-color); }
		.content img {
			max-width: 100%;
			height: auto;
			border-radius: 8px;
			margin: 0.5rem 0;
			display: block;
		}
		.content .empty { color: #64748b; font-style: italic; }
		.footer { text-align: center; margin-top: 0.5rem; }
		.updated { font-size: 0.8125rem; color: #94a3b8; margin-top: 0.75rem; }
		.btn {
			display: inline-block;
			padding: 0.75rem 1.5rem;
			font-size: 1rem;
			font-weight: 600;
			border: none;
			border-radius: 8px;
			cursor: pointer;
			font-family: inherit;
		}
		.btn-primary { background: var(--accent-color); color: #fff; }
		.btn:hover { opacity: 0.9; }
		@media print {
			body { background: #fff; padding: 0; }
			.card { box-shadow: none; border-radius: 0; margin-bottom: 0; padding: 0.5rem 0; }
			.no-print { display: none !important; }
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
}
