<?php

namespace Tests\Wpunit;

use Rondo\Notifications\LettermintConfig;
use Rondo\Notifications\LettermintWebhook;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

class LettermintWebhookTest extends RondoTestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;

		new \RONDO_Post_Types();
		new LettermintWebhook();

		do_action( 'rest_api_init' );
	}

	protected function tear_down(): void {
		delete_option( LettermintConfig::OPTION_WEBHOOK_SECRET );
		delete_option( LettermintWebhook::OPTION_SUPPRESSED_EMAILS );
		parent::tear_down();
	}

	public function test_hard_bounce_creates_idempotent_tasks_and_tracks_recipient(): void {
		$secret = 'test-lettermint-secret';
		update_option( LettermintConfig::OPTION_WEBHOOK_SECRET, $secret );

		$person_id = $this->createPerson( [ 'post_title' => 'Bounced Member' ] );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'email_1', 'bounce@example.com' );

		$payload = [
			'event' => 'message.hard_bounced',
			'id'    => 'evt_test_001',
			'data'  => [
				'recipient'  => 'bounce@example.com',
				'subject'    => 'Test delivery',
				'message_id' => 'msg_test_001',
				'timestamp'  => gmdate( 'c' ),
				'tag'        => 'rondo-wp-mail',
				'response'   => [
					'content' => 'Mailbox not found',
				],
			],
		];

		$first_response = $this->dispatch_signed_webhook( $payload, $secret );
		$this->assertEquals( 200, $first_response->get_status() );

		$tasks_after_first = $this->get_tasks_for_event( 'evt_test_001' );
		$this->assertNotEmpty( $tasks_after_first, 'Expected at least one follow-up task for the webhook event.' );

		$related_people = \Rondo\Fields\Fields::get_for_post( $tasks_after_first[0], 'related_persons' ) ?: [];
		$this->assertContains( $person_id, array_map( 'intval', (array) $related_people ) );

		$suppressed = get_option( LettermintWebhook::OPTION_SUPPRESSED_EMAILS, [] );
		$this->assertIsArray( $suppressed );
		$this->assertArrayHasKey( 'bounce@example.com', $suppressed );
		$this->assertSame( 'message.hard_bounced', $suppressed['bounce@example.com']['event'] ?? null );

		$second_response = $this->dispatch_signed_webhook( $payload, $secret );
		$this->assertEquals( 200, $second_response->get_status() );

		$tasks_after_second = $this->get_tasks_for_event( 'evt_test_001' );
		$this->assertCount(
			count( $tasks_after_first ),
			$tasks_after_second,
			'Replaying the same webhook event should not create duplicate tasks.'
		);
	}

	public function test_soft_bounce_is_ignored_without_creating_a_task(): void {
		$secret = 'test-lettermint-secret';
		update_option( LettermintConfig::OPTION_WEBHOOK_SECRET, $secret );

		$payload = [
			'event' => 'message.soft_bounced',
			'id'    => 'evt_soft_bounce_001',
			'data'  => [
				'recipient'  => 'temporary@example.com',
				'subject'    => 'Temporary delivery failure',
				'message_id' => 'msg_soft_bounce_001',
				'timestamp'  => gmdate( 'c' ),
			],
		];

		$response = $this->dispatch_signed_webhook( $payload, $secret );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['ignored'] ?? false );
		$this->assertSame( [], $this->get_tasks_for_event( 'evt_soft_bounce_001' ) );
		$this->assertSame( [], get_option( LettermintWebhook::OPTION_SUPPRESSED_EMAILS, [] ) );
	}

	/**
	 * Dispatch a signed Lettermint webhook request.
	 *
	 * @param array  $payload Event payload.
	 * @param string $secret  Webhook secret.
	 * @return \WP_REST_Response
	 */
	private function dispatch_signed_webhook( array $payload, string $secret ): \WP_REST_Response {
		$raw       = wp_json_encode( $payload );
		$timestamp = time();
		$signature = hash_hmac( 'sha256', $timestamp . '.' . $raw, $secret );

		$request = new WP_REST_Request( 'POST', '/rondo/v1/lettermint/webhook' );
		$request->set_body( $raw );
		$request->set_header( 'X-Lettermint-Signature', sprintf( 't=%d,v1=%s', $timestamp, $signature ) );
		$request->set_header( 'X-Lettermint-Delivery', (string) $timestamp );

		return $this->server->dispatch( $request );
	}

	/**
	 * Get all task IDs created for a specific Lettermint event.
	 *
	 * @param string $event_id Event ID.
	 * @return array
	 */
	private function get_tasks_for_event( string $event_id ): array {
		return get_posts(
			[
				'post_type'      => 'rondo_todo',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					[
						'key'   => LettermintWebhook::META_EVENT_ID,
						'value' => $event_id,
					],
				],
			]
		);
	}
}
