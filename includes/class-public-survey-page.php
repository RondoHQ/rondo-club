<?php
/** Logged-out survey shell, also used for authenticated draft previews. */
namespace Rondo\Surveys;

use Rondo\Pages\PublicPageChrome;
use Rondo\Core\UserRoles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PublicSurveyPage {
	public function __construct() {
		add_action( 'init', [ $this, 'rewrite' ] );
		add_filter(
			'query_vars',
			static function ( $vars ) {
				$vars[] = 'rondo_survey_id';
				return $vars;
			}
			);
		add_action( 'template_redirect', [ $this, 'render' ], 0 );
	}

	public function rewrite(): void {
		add_rewrite_rule( '^enquete/([0-9]+)/?$', 'index.php?rondo_survey_id=$matches[1]', 'top' );
	}

	public function render(): void {
		$id = absint( get_query_var( 'rondo_survey_id' ) );
		if ( ! $id ) {
			return;
		}
		$preview = isset( $_GET['preview'] ) && UserRoles::can_access_section( 'enquetes' );
		$token   = isset( $_GET['uitnodiging'] ) ? sanitize_text_field( wp_unslash( $_GET['uitnodiging'] ) ) : '';
		$data    = null;
		if ( $preview ) {
			$data = Surveys::read( $id );
			if ( ! is_wp_error( $data ) ) {
				$data['preview'] = true;
			}
		}
		$branding = PublicPageChrome::branding();
		nocache_headers();
		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Cache-Control: no-store, private, max-age=0' );
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		PublicPageChrome::header( 'Enquête — ' . $branding['name'], $branding['accent_color'], $branding['accent_background_color'], $branding['logo_url'] );
		?>
<style>
.container{max-width:720px}.survey-options{display:grid;gap:.6rem;margin-top:.8rem}.survey-option{display:flex;align-items:flex-start;gap:.7rem;border:1px solid #cbd5e1;border-radius:8px;padding:.8rem;cursor:pointer}.survey-option:has(input:checked){border-color:var(--accent-color);background:#f0f9ff}.survey-option input{width:1.2rem;height:1.2rem;flex-shrink:0;margin-top:.1rem}.survey-question{border:0;padding:0;margin:1.5rem 0}.survey-question legend{font-weight:600;padding:0}.survey-question textarea{width:100%;min-height:120px;margin-top:.7rem;padding:.7rem;border:1px solid #94a3b8;border-radius:8px;font:inherit}.survey-note{white-space:pre-wrap;color:#475569;margin:.7rem 0;line-height:1.6}.survey-actions{display:flex;justify-content:space-between;gap:1rem;margin-top:1rem}.survey-actions button{font:inherit;padding:.8rem 1.2rem;border-radius:8px;border:1px solid #cbd5e1;cursor:pointer}.survey-actions button[type=submit]{background:var(--accent-color);color:white}.survey-error{color:#b91c1c;background:#fef2f2;padding:.8rem;border-radius:8px;margin-top:1rem}.survey-progress{width:100%;margin:.8rem 0}.survey-trap{position:absolute;left:-10000px}.survey-question :focus-visible,button:focus-visible{outline:3px solid var(--accent-color);outline-offset:3px}button:disabled{opacity:.6;cursor:wait}
</style>
<div class="container"><div class="card header-card">
		<?php if ( $branding['logo_url'] ) : ?>
<img class="club-logo" src="<?php echo esc_url( $branding['logo_url'] ); ?>" alt="<?php echo esc_attr( $branding['name'] ); ?>">
<?php endif; ?>
<p><?php echo esc_html( $branding['name'] ); ?></p>
</div><main id="survey-root" tabindex="-1" class="card" aria-live="polite"><p>Enquête laden…</p></main></div>
<script>window.rondoSurvey = 
		<?php
		echo wp_json_encode(
		[
			'id'         => $id,
			'api'        => rest_url( 'rondo/v1/public-surveys/' . $id ),
			'invitation' => $token,
			'draft'      => $preview && ! is_wp_error( $data ) ? $data : null,
		],
		JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
							);
		?>
								;</script>
<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Standalone shell intentionally does not run wp_head/wp_footer. ?>
<script defer src="<?php echo esc_url( get_template_directory_uri() . '/public/survey.js?ver=' . wp_get_theme()->get( 'Version' ) ); ?>"></script>
		<?php
		PublicPageChrome::footer();
		exit;
	}
}
