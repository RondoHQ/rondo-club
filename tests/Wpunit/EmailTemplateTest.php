<?php

namespace Tests\Wpunit;

use Rondo\Config\FinanceConfig;
use Rondo\Notifications\EmailTemplate;
use Tests\Support\RondoTestCase;

class EmailTemplateTest extends RondoTestCase {
	public function test_club_colors_preserve_link_destinations_and_button_contrast(): void {
		update_option( FinanceConfig::OPTION_ACCENT_COLOR, '#006935' );
		update_option( FinanceConfig::OPTION_ACCENT_BACKGROUND_COLOR, '#EBF3EF' );
		$payment_url = 'https://example.com/pay?token=abc&installment=2';
		$button      = EmailTemplate::render_cta_button( $payment_url, 'Betaal nu', '#b91c1c' );
		$html        = EmailTemplate::render(
			[
				'accent_color' => '#b91c1c',
				'body_html'    => '<a id="payment" href="' . esc_url( $payment_url ) . '" style="COLOR:blue!important;text-decoration:underline;color:red">Betaallink</a>' . $button,
				'footer_html'  => '<a id="support" href="mailto:help@example.com">Contact</a>',
			]
		);

		$this->assertStringContainsString( 'background:#EBF3EF;', $html );
		$tags = new \WP_HTML_Tag_Processor( $html );
		$seen = [];
		while ( $tags->next_tag( 'A' ) ) {
			$id    = $tags->get_attribute( 'id' );
			$style = (string) $tags->get_attribute( 'style' );
			if ( $id === 'payment' ) {
				$this->assertSame( $payment_url, $tags->get_attribute( 'href' ) );
				$this->assertStringContainsString( 'color:#006935;', $style );
				$this->assertStringContainsString( 'text-decoration:underline', $style );
				$this->assertStringNotContainsString( 'blue', $style );
				$this->assertStringNotContainsString( 'red', $style );
				$seen[] = 'payment';
			} elseif ( $id === 'support' ) {
				$this->assertSame( 'mailto:help@example.com', $tags->get_attribute( 'href' ) );
				$this->assertStringContainsString( 'color:#006935;', $style );
				$seen[] = 'support';
			} elseif ( $tags->get_attribute( 'data-rondo-email-button' ) === 'true' ) {
				$this->assertSame( $payment_url, $tags->get_attribute( 'href' ) );
				$this->assertStringContainsString( 'color:#ffffff;', $style );
				$seen[] = 'button';
			}
		}
		$this->assertSame( [ 'payment', 'button', 'support' ], $seen );
		$this->assertStringContainsString( 'background:#006935;', $button );
	}

	public function test_missing_or_invalid_club_colors_keep_safe_fallbacks(): void {
		update_option( FinanceConfig::OPTION_ACCENT_COLOR, '' );
		update_option( FinanceConfig::OPTION_ACCENT_BACKGROUND_COLOR, 'invalid' );
		$this->assertSame( '#b91c1c', EmailTemplate::accent_color( '#b91c1c' ) );
		$this->assertSame( '#f3f7f6', EmailTemplate::background_color() );

		update_option( FinanceConfig::OPTION_ACCENT_COLOR, 'red;display:none' );
		$this->assertSame( '#0f766e', EmailTemplate::accent_color( 'invalid' ) );
	}
}
