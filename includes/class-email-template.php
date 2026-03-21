<?php
/**
 * Shared HTML email layout helper.
 *
 * @package Rondo\Notifications
 */

namespace Rondo\Notifications;

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
		$accent_color  = trim( (string) ( $args['accent_color'] ?? '#0f766e' ) );
		$support_email = trim( (string) ( $args['support_email'] ?? '' ) );

		if ( $body_html === '' ) {
			$body_html = '<p style="margin:0;color:#0f172a;font-size:16px;line-height:1.7;">&nbsp;</p>';
		}

		$cta_html = '';
		if ( $cta_url !== '' && $cta_label !== '' ) {
			$cta_html = sprintf(
				'<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 0;"><tr><td style="border-radius:999px;background:%1$s;"><a href="%2$s" style="display:inline-block;padding:14px 22px;font-size:15px;font-weight:700;line-height:1;text-decoration:none;color:#ffffff;">%3$s</a></td></tr></table>',
				esc_attr( $accent_color ),
				esc_url( $cta_url ),
				esc_html( $cta_label )
			);
		}

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
			'<!doctype html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><meta name="x-apple-disable-message-reformatting"><title>%1$s</title></head><body style="margin:0;padding:0;background:#f3f7f6;color:#0f172a;"><div style="display:none;max-height:0;overflow:hidden;opacity:0;mso-hide:all;">%2$s</div><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="background:linear-gradient(180deg,#e7f6f2 0%%,#f3f7f6 220px);"><tr><td style="padding:32px 16px;"><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%%" style="max-width:640px;margin:0 auto;"><tr><td style="padding:0 0 18px;"><a href="%3$s" style="font-size:14px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:%4$s;text-decoration:none;">%5$s</a></td></tr><tr><td style="background:#ffffff;border:1px solid #dbe4e1;border-radius:28px;padding:32px 28px;box-shadow:0 18px 45px rgba(15,23,42,0.08);"><div style="height:6px;width:84px;border-radius:999px;background:%4$s;margin:0 0 24px;"></div>%6$s%7$s%8$s%9$s%10$s%11$s</td></tr><tr><td style="padding:18px 8px 0;">%12$s</td></tr></table></td></tr></table></body></html>',
			esc_html( $heading !== '' ? $heading : $brand_name ),
			esc_html( $preheader !== '' ? $preheader : $heading ),
			esc_url( $brand_url ),
			esc_attr( $accent_color ),
			esc_html( $brand_name ),
			$eyebrow !== '' ? '<p style="margin:0 0 10px;color:' . esc_attr( $accent_color ) . ';font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;">' . esc_html( $eyebrow ) . '</p>' : '',
			$heading !== '' ? '<h1 style="margin:0 0 14px;color:#0f172a;font-size:30px;line-height:1.2;font-weight:800;">' . esc_html( $heading ) . '</h1>' : '',
			$intro !== '' ? '<p style="margin:0 0 24px;color:#334155;font-size:16px;line-height:1.7;">' . esc_html( $intro ) . '</p>' : '',
			'<div style="color:#0f172a;font-size:16px;line-height:1.7;">' . $body_html . '</div>',
			$cta_html,
			$support_html,
			$footer_html
		);
	}

	/**
	 * Render a standalone CTA button for use as a template placeholder.
	 *
	 * @param string $url          Button URL.
	 * @param string $label        Button label text.
	 * @param string $accent_color Hex color for the button background.
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
			esc_attr( $accent_color ),
			esc_url( $url ),
			esc_html( $label )
		);
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
			$linked  = make_clickable( $escaped );
			$linked  = nl2br( $linked );

			$html[] = '<p style="margin:0 0 16px;color:#0f172a;font-size:16px;line-height:1.7;">' . $linked . '</p>';
		}

		return implode( '', $html );
	}
}
