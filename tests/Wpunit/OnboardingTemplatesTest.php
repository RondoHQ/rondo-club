<?php

namespace Tests\Wpunit;

use Rondo\Onboarding\Templates;
use Rondo\Notifications\OnboardingEmailSender;
use Rondo\REST\Onboarding;
use Tests\Support\RondoTestCase;

class OnboardingTemplatesTest extends RondoTestCase {
	protected function set_up(): void {
		parent::set_up();
		delete_option( Templates::OPTION );
		delete_option( Templates::LOCK );
	}

	private function input( array $scenario = [] ): array {
		return [
			'blocks'   => Templates::settings()['blocks'],
			'scenario' => array_merge(
				[
					'type'      => 'member',
					'age'       => 'adult',
					'recipient' => 'self',
					'account'   => 'activate',
					'vog'       => 'none',
					'clothing'  => 'no',
					'returning' => 'no',
				],
				$scenario
				),
		];
	}

	public function test_drafts_and_previews_preserve_legacy_templates_and_never_send(): void {
		$legacy = '<p>Eigen contributieregels <strong>ongewijzigd</strong></p>';
		update_option( OnboardingEmailSender::OPTION_LID_BODY, $legacy );
		$settings = Templates::settings();
		$this->assertSame( $legacy, $settings['legacy']['lid']['body'] );
		$this->assertFalse( get_option( Templates::OPTION ) );
		$mail_count = 0;
		$trap       = static function () use ( &$mail_count ) {
			++$mail_count;
			return false;
		};
		add_filter( 'pre_wp_mail', $trap );
		$crons = _get_cron_array();
		try {
			$input                          = $this->input();
			$input['blocks']['member_fees'] = 'Onze eigen contributietekst.';
			$preview                        = Templates::preview( $input );
			$this->assertStringContainsString( 'Onze eigen contributietekst.', $preview['html'] );
			$this->assertFalse( get_option( Templates::OPTION ) );
			$saved = Templates::save(
				[
					'blocks'   => $input['blocks'],
					'revision' => $settings['revision'],
				]
				);
			$this->assertSame( $preview['template_version'], $saved['revision'] );
			$this->assertSame( $legacy, get_option( OnboardingEmailSender::OPTION_LID_BODY ) );
			$this->assertFalse( $saved['sending_enabled'] );
			$this->assertSame( $crons, _get_cron_array() );
			$this->assertSame( 0, $mail_count );
		} finally {
			remove_filter( 'pre_wp_mail', $trap );
		}
	}

	public function test_combined_mail_selects_exactly_one_vog_and_clothing_variant(): void {
		foreach ( [ 'none', 'missing', 'requested', 'review', 'valid', 'renew', 'resubmit' ] as $vog ) {
			foreach ( [ 'no', 'yes' ] as $returning ) {
				$input                              = $this->input(
					[
						'type'      => 'combined',
						'vog'       => $vog,
						'clothing'  => 'yes',
						'returning' => $returning,
					]
					);
				$input['blocks']['member_training'] = 'Informatie over trainingen.';
				$preview                            = Templates::preview( $input );
				$ids                                = $preview['block_ids'];
				$this->assertContains( 'intro_member_adult', $ids );
				$this->assertContains( 'intro_volunteer', $ids );
				$this->assertContains( 'member_training', $ids );
				$this->assertSame( $vog === 'none' ? [] : [ 'vog_' . $vog ], array_values( array_filter( $ids, static fn( $id ) => str_starts_with( $id, 'vog_' ) ) ) );
				$expected = 'clothing_' . ( $returning === 'yes' ? 'return_' : '' ) . ( in_array( $vog, [ 'none', 'valid' ], true ) ? 'ready' : 'blocked' );
				$this->assertSame( [ $expected ], array_values( array_filter( $ids, static fn( $id ) => str_starts_with( $id, 'clothing_' ) ) ) );
				$this->assertSame( $ids, array_unique( $ids ) );
			}
		}
		$review = Templates::preview(
			$this->input(
			[
				'type' => 'volunteer',
				'vog'  => 'review',
			]
			)
			);
		$this->assertStringContainsString( 'Je hoeft nu niets te doen.', $review['html'] );
		$this->assertStringNotContainsString( 'uploaden', $review['html'] );
		$this->assertStringNotContainsString( 'aanvraag', $review['html'] );
		$this->assertNotContains( 'intro_member_adult', $review['block_ids'] );
	}

	public function test_member_ignores_volunteer_conditions_and_omits_empty_optional_blocks(): void {
		$preview = Templates::preview(
			$this->input(
			[
				'vog'      => 'requested',
				'clothing' => 'yes',
			]
			)
			);
		$this->assertNotContains( 'member_fees', $preview['block_ids'] );
		$this->assertNotContains( 'vog_requested', $preview['block_ids'] );
		$this->assertNotContains( 'clothing_blocked', $preview['block_ids'] );
		$this->assertStringNotContainsString( 'ouders', $preview['html'] );
	}

	public function test_parent_account_choice_is_per_recipient_and_adults_cannot_mail_parents(): void {
		$parent  = $this->input(
			[
				'age'       => 'minor',
				'recipient' => 'parent',
				'account'   => 'activate',
			]
			);
		$preview = Templates::preview( $parent );
		$this->assertSame( 'ouder@example.invalid', $preview['recipient'] );
		$this->assertContains( 'account_activate', $preview['block_ids'] );
		$this->assertContains( 'account_parents', $preview['block_ids'] );
		$this->assertStringContainsString( 'Beste Emma en eventuele ouders/verzorgers', $preview['html'] );
		$this->assertStringContainsString( home_url( '/activeren' ), $preview['html'] );
		$parent['scenario']['account'] = 'exists';
		$existing                      = Templates::preview( $parent );
		$this->assertContains( 'account_exists', $existing['block_ids'] );
		$this->assertNotContains( 'account_activate', $existing['block_ids'] );
		$this->assertStringContainsString( home_url( '/login' ), $existing['html'] );
		$parent['scenario']['age'] = 'adult';
		$this->assertSame( 'onboarding_parent', Templates::preview( $parent )->get_error_code() );
	}

	public function test_unknown_variables_and_malformed_drafts_cannot_partially_save(): void {
		$settings = Templates::settings();
		foreach ( [ '{set_password_url}', '{{execute()}}', [ 'nested' ], str_repeat( 'x', 20001 ), '' ] as $value ) {
			$blocks            = $settings['blocks'];
			$blocks['closing'] = $value;
			$this->assertWPError(
				Templates::save(
				[
					'blocks'   => $blocks,
					'revision' => $settings['revision'],
				]
				)
				);
			$this->assertFalse( get_option( Templates::OPTION ) );
		}
		$blocks                = $settings['blocks'];
		$blocks['eligibility'] = 'always';
		$this->assertWPError( Templates::preview( [ 'blocks' => $blocks ] ) );
		$input                    = $this->input();
		$input['scenario']['vog'] = 'arbitrary';
		$this->assertWPError( Templates::preview( $input ) );
	}

	public function test_text_is_escaped_and_save_conflicts_preserve_newer_draft(): void {
		$settings          = Templates::settings();
		$blocks            = $settings['blocks'];
		$blocks['closing'] = '<script>alert(1)</script><img src=x onerror=alert(2)>Veilig {first_name}';
		$saved             = Templates::save(
			[
				'blocks'   => $blocks,
				'revision' => $settings['revision'],
			]
			);
		$this->assertIsArray( $saved );
		$preview = Templates::preview( $this->input() );
		$this->assertStringNotContainsString( '<script', $preview['html'] );
		$this->assertStringNotContainsString( 'onerror=', $preview['html'] );
		$this->assertStringContainsString( 'Veilig Robin', $preview['html'] );
		$conflict = Templates::save(
			[
				'blocks'   => $settings['blocks'],
				'revision' => $settings['revision'],
			]
			);
		$this->assertSame( 409, $conflict->get_error_data()['status'] );
		$this->assertSame( $saved['blocks'], Templates::settings()['blocks'] );
		add_option( Templates::LOCK, 'locked', '', false );
		$busy = Templates::save(
			[
				'blocks'   => $saved['blocks'],
				'revision' => $saved['revision'],
			]
			);
		$this->assertSame( 'onboarding_templates_busy', $busy->get_error_code() );
		$this->assertSame( 'locked', get_option( Templates::LOCK ) );
	}

	public function test_rest_routes_require_admin_and_preview_does_not_write(): void {
		$server = $this->bootRestControllers( [ Onboarding::class ] );
		foreach ( [ 'subscriber', 'administrator' ] as $role ) {
			wp_set_current_user( self::factory()->user->create( [ 'role' => $role ] ) );
			foreach ( [ [ 'GET', '/onboarding/templates' ], [ 'POST', '/onboarding/templates' ], [ 'POST', '/onboarding/templates/preview' ] ] as [ $method, $path ] ) {
				$request           = new \WP_REST_Request( $method, '/rondo/v1' . $path );
				$input             = $this->input();
				$input['revision'] = Templates::settings()['revision'];
				$request->set_header( 'Content-Type', 'application/json' );
				$request->set_body( wp_json_encode( $input ) );
				$response = $server->dispatch( $request );
				$this->assertSame( $role === 'administrator' ? 200 : 403, $response->get_status() );
				if ( $role === 'administrator' ) {
					$this->assertSame( 'no-store', $response->get_headers()['Cache-Control'] );
				}
			}
		}
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/onboarding/templates/preview' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( '42' );
		$this->assertSame( 400, $server->dispatch( $request )->get_status() );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/onboarding/templates' ) )->get_status() );
	}
}
