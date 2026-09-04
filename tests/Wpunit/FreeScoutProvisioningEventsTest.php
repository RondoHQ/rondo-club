<?php

namespace Tests\Wpunit;

use Rondo\Identity\OidcClientRegistry;
use Rondo\Identity\OidcIdentity;
use Rondo\Integrations\FreeScout\Config;
use Rondo\Integrations\FreeScout\ProvisioningEvents;
use Tests\Support\RondoTestCase;

/** Covers durable, privacy-minimal Rondo-to-FreeScout access events. */
final class FreeScoutProvisioningEventsTest extends RondoTestCase {

	private const KEY      = 'test-signing-key-with-at-least-thirty-two-bytes';
	private const INSTANCE = 'https://support.example.test';

	private ProvisioningEvents $events;
	private $http_filter = null;

	protected function set_up(): void {
		parent::set_up();
		delete_option( Config::PROVISIONING_OPTION );
		delete_option( OidcClientRegistry::OPTION_CLIENTS );
		add_filter( 'rondo_freescout_signing_keys', [ $this, 'signing_keys' ] );
		$this->events = new ProvisioningEvents();
		$this->events->register_post_type();
		$client = OidcClientRegistry::create(
			[
				'label'              => 'FreeScout test',
				'redirect_uris'      => [ self::INSTANCE . '/rondo/oidc/callback' ],
				'freescout_base_url' => self::INSTANCE,
			]
		);
		$this->assertNotWPError( $client );
	}

	protected function tear_down(): void {
		remove_filter( 'rondo_freescout_signing_keys', [ $this, 'signing_keys' ] );
		if ( $this->http_filter !== null ) {
			remove_filter( 'pre_http_request', $this->http_filter, 10 );
		}
		wp_clear_scheduled_hook( ProvisioningEvents::CRON_HOOK );
		wp_clear_scheduled_hook( ProvisioningEvents::AUDIT_HOOK );
		parent::tear_down();
	}

	/** @return string[] */
	public function signing_keys(): array {
		return [ self::KEY ];
	}

	public function test_disabled_delivery_stores_nothing_and_enabling_seeds_existing_subjects(): void {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		OidcIdentity::subject( $user_id );

		$this->assertSame( [], $this->events->enqueue_user( $user_id ) );
		$this->assertTrue( Config::update_provisioning_events( true ) );
		$this->assertSame( 1, $this->events->seed_existing_identities() );
		$this->assertSame( 1, ProvisioningEvents::health()['pending'] );
	}

	public function test_repeated_capability_changes_coalesce_and_deliver_one_signed_event(): void {
		Config::update_provisioning_events( true );
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$subject = OidcIdentity::subject( $user_id );

		$this->assertCount( 1, $this->events->enqueue_user( $user_id ) );
		$this->assertCount( 1, $this->events->enqueue_user( $user_id ) );
		$this->assertSame( 1, ProvisioningEvents::health()['pending'] );

		$captured          = null;
		$this->http_filter = static function ( $preempt, $arguments, $url ) use ( &$captured ) {
			unset( $preempt );
			$captured = [
				'arguments' => $arguments,
				'url'       => $url,
			];
			return [
				'headers'  => [],
				'body'     => wp_json_encode( [ 'status' => 'reconciled' ] ),
				'response' => [
					'code'    => 200,
					'message' => 'OK',
				],
				'cookies'  => [],
			];
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$result = $this->events->process();

		$this->assertSame(
			[
				'processed' => 1,
				'delivered' => 1,
				'retrying'  => 0,
			],
			$result
			);
		$this->assertSame( 0, ProvisioningEvents::health()['pending'] );
		$this->assertSame( self::INSTANCE . '/rondo/integration/events/access', $captured['url'] );
		$body = (string) $captured['arguments']['body'];
		$data = json_decode( $body, true );
		$this->assertSame( 1, $data['version'] );
		$this->assertSame( $subject, $data['subject'] );
		$this->assertMatchesRegularExpression( '/^[a-f0-9-]{36}$/', $data['eventId'] );
		$this->assertSame(
			'v1=' . hash_hmac(
				'sha256',
				$captured['arguments']['headers']['X-Rondo-Timestamp'] . "\n"
					. $captured['arguments']['headers']['X-Rondo-Nonce'] . "\n" . $body,
				self::KEY
			),
			$captured['arguments']['headers']['X-Rondo-Signature']
		);
	}

	public function test_failed_delivery_remains_retryable_without_storing_personal_data(): void {
		Config::update_provisioning_events( true );
		$user_id = self::factory()->user->create(
			[
				'role'         => 'subscriber',
				'display_name' => 'Private Person',
				'user_email'   => 'private@example.test',
			]
		);
		OidcIdentity::subject( $user_id );
		$this->events->enqueue_user( $user_id );

		$this->http_filter = static function () {
			return new \WP_Error( 'http_request_failed', 'Connection failed' );
		};
		add_filter( 'pre_http_request', $this->http_filter, 10, 3 );

		$result = $this->events->process();
		$post   = get_posts(
			[
				'post_type'      => ProvisioningEvents::POST_TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 1,
			]
		)[0];

		$this->assertSame(
			[
				'processed' => 1,
				'delivered' => 0,
				'retrying'  => 1,
			],
			$result
			);
		$this->assertSame( 1, ProvisioningEvents::health()['pending'] );
		$this->assertStringNotContainsString( 'Private Person', $post->post_title );
		$this->assertStringNotContainsString( 'private@example.test', $post->post_title );
	}
}
