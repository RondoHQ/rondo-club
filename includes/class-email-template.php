<?php
/**
 * Shared HTML email layout helper.
 *
 * @package Rondo\Notifications
 */

namespace Rondo\Notifications;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailTemplate {

	/**
	 * Render a branded HTML email shell around provided body content.
	 *
	 * @param array<string, mixed> $args Template arguments.
	 * @return string
	 */
	public static function render( array $args ): string {
		$brand_name    = trim( (string) ( $args['brand_name'] ?? get_bloginfo( 'name' ) ) );
		$brand_url     = (string) ( $args['brand_url'] ?? home_url( '/' ) );
		$preheader     = trim( (string) ( $args['preheader'] ?? '' ) );
		$eyebrow       = trim( (string) ( $args['eyebrow'] ?? '' ) );
		$heading       = trim( (string) ( $args['heading'] ?? '' ) );
		$intro         = trim( (string) ( $args['intro'] ?? '' ) );
		$body_html     = (string) ( $args['body_html'] ?? '' );
		$footer_html   = (string) ( $args['footer_html'] ?? '' );
		$cta_url       = trim( (string) ( $args['cta_url'] ?? '' ) );
		$cta_label     = trim( (string) ( $args['cta_label'] ?? '' ) );
		$accent_color  = self::accent_color( (string) ( $args['accent_color'] ?? '' ) );
		$support_email = trim( (string) ( $args['support_email'] ?? '' ) );

		$config           = new FinanceConfig();
		$background_color = sanitize_hex_color( $config->get_accent_background_color() ) ?: '#f3f7f6';

		if ( $body_html === '' ) {
			$body_html = '<p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;">&nbsp;</p>';
		}

		$cta_html = self::render_cta_button( $cta_url, $cta_label, $accent_color );

		$support_html = '';
		if ( $support_email !== '' && is_email( $support_email ) ) {
			$support_html = sprintf(
				'<p style="margin:16px 0 0;color:#475569;font-size:13px;line-height:1.6;">Vragen? Reageer op deze mail of neem contact op via <a href="mailto:%1$s" style="color:%2$s;text-decoration:none;">%1$s</a>.</p>',
				esc_html( $support_email ),
				esc_attr( $accent_color )
			);
		}

		if ( $footer_html === '' ) {
			$footer_html = '&nbsp;';
		}

		return sprintf(
			'<!doctype html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="x-apple-disable-message-reformatting"><title>%1$s</title><style>a { color:%4$s; }</style></head><body style="margin:0;padding:0;background:%13$s;color:#0f172a;"><div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">%2$s</div><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" bgcolor="%13$s" style="background:%13$s;"><tr><td style="padding:32px 16px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="max-width:640px;margin:0 auto;"><tr><td style="padding:0 0 24px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td style="vertical-align:middle;padding-right:16px;"><a href="%3$s" style="font-size:14px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:%4$s;text-decoration:none;">%5$s</a></td><td style="vertical-align:middle;">%7$s</td></tr></table></td></tr><tr><td style="background:#ffffff;border:1px solid #dbe4e1;border-radius:28px;padding:32px 28px;box-shadow:0 18px 45px rgba(15,23,42,0.08);">%6$s%8$s%9$s%10$s%11$s</td></tr><tr><td style="padding:18px 8px 0;">%12$s</td></tr></table></td></tr></table></body></html>',
			esc_html( $heading !== '' ? $heading : $brand_name ),
			esc_html( $preheader !== '' ? $preheader : $heading ),
			esc_url( $brand_url ),
			esc_attr( $accent_color ),
			self::render_brand( $brand_name ),
			$eyebrow !== '' ? '<p style="margin:0 0 10px;color:' . esc_attr( $accent_color ) . ';font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">' . esc_html( $eyebrow ) . '</p>' : '',
			$heading !== '' ? '<h1 style="margin:0;color:#0f172a;font-size:26px;line-height:1.2;font-weight:800;">' . esc_html( $heading ) . '</h1>' : '',
			$intro !== '' ? '<p style="margin:0 0 24px;color:#334155;font-size:16px;line-height:1.7;">' . esc_html( $intro ) . '</p>' : '',
			'<div style="color:#0f172a;font-size:16px;line-height:1.7;">' . $body_html . '</div>',
			$cta_html,
			$support_html,
			$footer_html,
			esc_attr( $background_color )
		);
	}

	/**
	 * Use the configured club accent, with a caller or template fallback.
	 *
	 * @param string $fallback Optional accent for clubs without a configured color.
	 * @return string
	 */
	private static function accent_color( string $fallback = '' ): string {
		$config = new FinanceConfig();
		return sanitize_hex_color( $config->get_accent_color() )
			?: sanitize_hex_color( $fallback )
			?: '#0f766e';
	}

	/**
	 * Render the configured club logo, retaining the brand name as a fallback.
	 *
	 * @param string $brand_name Fallback name for clubs without a usable logo.
	 * @return string
	 */
	private static function render_brand( string $brand_name ): string {
		$config = new FinanceConfig();
		$image  = wp_get_attachment_image_src( $config->get_club_logo_id(), 'medium' );
		if ( ! $image || $image[1] <= 0 || $image[2] <= 0 ) {
			return esc_html( $brand_name );
		}

		$scale  = min( 64 / $image[2], 160 / $image[1], 1 );
		$width  = max( 1, (int) round( $image[1] * $scale ) );
		$height = max( 1, (int) round( $image[2] * $scale ) );

		return sprintf(
			'<img src="%1$s" alt="%2$s" width="%3$d" height="%4$d" style="display:block;border:0;width:%3$dpx;height:%4$dpx;">',
			esc_url( $image[0] ),
			esc_attr( $config->get_display_name() ?: $brand_name ),
			$width,
			$height
		);
	}

	/**
	 * Render a standalone CTA button for use as a template placeholder.
	 *
	 * @param string $url          Button URL.
	 * @param string $label        Button label text.
	 * @param string $accent_color Fallback button color if no club accent is configured.
	 * @return string HTML table element or empty string if URL/label missing.
	 */
	public static function render_cta_button( string $url, string $label, string $accent_color = '#0f766e' ): string {
		$url   = trim( $url );
		$label = trim( $label );

		if ( $url === '' || $label === '' ) {
			return '';
		}

		return sprintf(
			'<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 0;"><tr><td style="border-radius:999px;background:%1$s;"><a href="%2$s" style="display:inline-block;padding:14px 22px;font-size:15px;font-weight:700;line-height:1;text-decoration:none;color:#ffffff;">%3$s</a></td></tr></table>',
			esc_attr( self::accent_color( $accent_color ) ),
			esc_url( $url ),
			esc_html( $label )
		);
	}

	/**
	 * Turn a post title into plain text fit for an email subject or body.
	 *
	 * `get_the_title()` runs the `the_title` filters, and `wptexturize()` emits
	 * curly quotes as HTML entities (`&#8216;`). Stripping tags leaves those
	 * entities intact, so an untreated title reaches the subject line as
	 * "Kopje &#8216;betaalde vrijwilliger&#8217; verwijderen". Decode them back
	 * to real characters. Callers that render into HTML still escape afterwards.
	 *
	 * @param string $title Raw title, typically from `get_the_title()`.
	 * @return string
	 */
	public static function decode_title( string $title ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' ) );
	}

	/**
	 * Convert plain text content into HTML paragraphs.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	public static function format_plain_text( string $text ): string {
		$text = str_replace( [ "\r\n", "\r" ], "\n", trim( $text ) );
		if ( $text === '' ) {
			return '';
		}

		$paragraphs = preg_split( "/\n{2,}/", $text ) ?: [];
		$html       = [];

		foreach ( $paragraphs as $paragraph ) {
			$paragraph = trim( $paragraph );
			if ( $paragraph === '' ) {
				continue;
			}

			$escaped = esc_html( $paragraph );
			$linked  = str_replace( '<a ', '<a style="color:' . esc_attr( self::accent_color() ) . ';" ', make_clickable( $escaped ) );
			$linked  = nl2br( $linked );

			$html[] = '<p style="margin:0 0 16px;color:#0f172a;font-size:16px;line-height:1.7;">' . $linked . '</p>';
		}

		return implode( '', $html );
	}
}
