<?php
/**
 * PublicPageChrome
 *
 * Shared HTML shell for Rondo's logged-out, standalone pages: the payment page at
 * /betaling/{token} and the activation page at /activeren. Club branding, the CSS,
 * the header card and the closing tags live here so the two pages cannot drift apart.
 *
 * These pages render outside the React SPA and outside the WordPress theme, so they
 * carry their own styles inline.
 *
 * @package Rondo\Pages
 */

namespace Rondo\Pages;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PublicPageChrome {

	const DEFAULT_ACCENT     = '#0891b2';
	const DEFAULT_BACKGROUND = '#f8fafc';

	/**
	 * Club name, logo and accent colours, with sensible fallbacks.
	 *
	 * @return array{name: string, logo_url: string, accent_color: string, accent_background_color: string}
	 */
	public static function branding(): array {
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
			'accent_color'            => $accent_color ?: self::DEFAULT_ACCENT,
			'accent_background_color' => $accent_background_color ?: self::DEFAULT_BACKGROUND,
		];
	}

	/**
	 * Open the document: doctype, head, inline CSS, and the opening body tag.
	 *
	 * @param string $title                   Document title.
	 * @param string $accent_color            Brand accent.
	 * @param string $accent_background_color Page background.
	 * @param string $logo_url                Favicon URL, optional.
	 */
	public static function header(
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

		.activation-email-field {
			margin-top: 1.25rem;
		}

		.activation-email-label {
			display: inline-block;
			font-weight: 700;
		}

		.activation-submit {
			margin-top: 1rem;
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

		.confirmation-help {
			color: #475569;
			line-height: 1.5;
			margin-top: 0.75rem;
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
	 * Render the header card with club logo and page heading.
	 *
	 * @param string $heading Page heading text.
	 */
	public static function header_card( string $heading ) {
		$branding = self::branding();
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
	 * Render the closing HTML tags.
	 */
	public static function footer() {
		?>
</body>
</html>
		<?php
	}
}
