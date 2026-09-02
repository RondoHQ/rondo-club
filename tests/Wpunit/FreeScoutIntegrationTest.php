<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Identity\OidcAuthorizationService;
use Rondo\Identity\OidcClientRegistry;
use Rondo\Identity\OidcIdentity;
use Rondo\Integrations\FreeScout\Config;
use Rondo\Integrations\FreeScout\PersonMatcher;
use Rondo\REST\FreeScoutIntegration;
use Tests\Support\RondoTestCase;

/** Covers the signed FreeScout sidebar, access, configuration and activity contracts. */
class FreeScoutIntegrationTest extends RondoTestCase {

	private const KEY      = 'test-signing-key-with-at-least-thirty-two-bytes';
	private const INSTANCE = 'https://support.example.test';

	private \WP_REST_Server $server;
	private int $agent_id;
	private string $subject;
	private int $nonce_counter      = 0;
	private ?string $previous_https = null;

	protected function set_up(): void {
		parent::set_up();
		$this->previous_https = isset( $_SERVER['HTTPS'] ) ? (string) $_SERVER['HTTPS'] : null;
		$_SERVER['HTTPS']     = 'on';
		add_filter( 'rondo_freescout_signing_keys', [ $this, 'signing_keys' ] );
		$this->server = $this->bootRestControllers( [ FreeScoutIntegration::class ] );

		$this->agent_id  = self::factory()->user->create(
			[
				'role'       => 'rondo_user',
				'user_email' => 'agent@example.test',
			]
		);
		$agent_person_id = $this->createPerson( [ 'post_title' => 'Integration agent' ], [ 'email_1' => 'agent@example.test' ] );
		update_user_meta( $this->agent_id, 'rondo_linked_person_id', $agent_person_id );
		$user = get_userdata( $this->agent_id );
		$user->add_cap( 'ledenadministratie' );
		$this->subject = OidcIdentity::subject( $this->agent_id );

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
		if ( $this->previous_https === null ) {
			unset( $_SERVER['HTTPS'] );
		} else {
			$_SERVER['HTTPS'] = $this->previous_https;
		}
		parent::tear_down();
	}

	/** @return string[] */
	public function signing_keys(): array {
		return [ self::KEY ];
	}

	public function test_configuration_requires_a_valid_signature_and_returns_closed_catalog(): void {
		$response = $this->signed_request(
			'configuration',
			[
				'version'  => 1,
				'instance' => self::INSTANCE,
			]
			);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertSame( 1, $data['version'] );
		$this->assertSame( 'ledenadministratie', $data['mappings'][0]['key'] );
		$this->assertSame( 'ledenadministratie.v1', $data['mappings'][0]['sidebar_policy'] );
		$this->assertSame(
			[
				'retention_days' => 365,
				'source'         => 'default',
			],
			$data['audit']
			);

		$denied = $this->signed_request(
			'configuration',
			[
				'version'  => 1,
				'instance' => self::INSTANCE,
			],
			'bad-key'
			);
		$this->assertSame( 401, $denied->get_status() );
		$this->assertSame( 'rondo_freescout_signature_invalid', $denied->get_data()['code'] );
	}

	public function test_nonce_replay_is_rejected_after_signature_validation(): void {
		$body      = [
			'version'  => 1,
			'instance' => self::INSTANCE,
		];
		$timestamp = time();
		$nonce     = 'nonce_value_that_is_long_enough_123456';

		$first  = $this->signed_request( 'configuration', $body, self::KEY, $nonce, $timestamp );
		$second = $this->signed_request( 'configuration', $body, self::KEY, $nonce, $timestamp );

		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( 409, $second->get_status() );
		$this->assertSame( 'rondo_freescout_replay', $second->get_data()['code'] );
	}

	public function test_configuration_publishes_the_configurable_retention_policy(): void {
		$this->assertTrue( Config::update_retention( 420 ) );

		$response = $this->signed_request(
			'configuration',
			[
				'version'  => 1,
				'instance' => self::INSTANCE,
			]
			);

		$this->assertSame(
			[
				'retention_days' => 420,
				'source'         => 'rondo_setting',
			],
			$response->get_data()['audit']
			);
		$this->assertSame( 420, Config::retention_status()['retention_days'] );
		$this->assertFalse( Config::retention_status()['locked'] );
	}

	public function test_access_resolves_exact_subject_and_rechecks_current_capability(): void {
		$active = $this->signed_request(
			'access',
			[
				'version'         => 1,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
				'freescoutUserId' => 44,
			]
		);
		$this->assertTrue( $active->get_data()['active'] );
		$this->assertSame( [ 'ledenadministratie' ], $active->get_data()['managed_mailboxes'] );

		get_userdata( $this->agent_id )->remove_cap( 'ledenadministratie' );
		$inactive = $this->signed_request(
			'access',
			[
				'version'         => 1,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
				'freescoutUserId' => 44,
			]
		);
		$this->assertFalse( $inactive->get_data()['active'] );
		$this->assertSame( [], $inactive->get_data()['managed_mailboxes'] );
	}

	public function test_access_allows_missing_local_user_id_during_first_binding(): void {
		$without_id = $this->signed_request(
			'access',
			[
				'version'         => 1,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
				'freescoutUserId' => null,
			]
		);

		$this->assertSame( 200, $without_id->get_status(), wp_json_encode( $without_id->get_data() ) );
		$this->assertTrue( $without_id->get_data()['active'] );
		$this->assertSame( [ 'ledenadministratie' ], $without_id->get_data()['managed_mailboxes'] );
	}

	public function test_access_rejects_invalid_local_user_ids(): void {
		foreach ( [ 0, -1, '44' ] as $invalid_id ) {
			$response = $this->signed_request(
				'access',
				[
					'version'         => 1,
					'issuer'          => OidcAuthorizationService::issuer(),
					'subject'         => $this->subject,
					'freescoutUserId' => $invalid_id,
				]
			);

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( 'rondo_freescout_access_schema_invalid', $response->get_data()['code'] );
		}
	}

	public function test_sidebar_matches_secondary_email_and_omits_excluded_data(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Jan van Test' ],
			[
				'first_name'               => 'Jan',
				'infix'                    => 'van',
				'last_name'                => 'Test',
				'email_2'                  => 'member@example.test',
				'knvb_id'                  => 'KNVB123',
				'type_lid'                 => 'Bondslid',
				'nikki_contributie_status' => 'Privé financieel veld',
				'freescout_id'             => 987,
			]
		);

		$response = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'member@example.test' ] ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'ok', $data['status'] );
		$this->assertStringContainsString( 'Jan van Test', $data['html'] );
		$this->assertStringContainsString( 'KNVB123', $data['html'] );
		$this->assertStringNotContainsString( 'Privé financieel veld', $data['html'] );
		$this->assertStringNotContainsString( '987', $data['html'] );
		$this->assertStringNotContainsString( '<script', strtolower( $data['html'] ) );
		$this->assertSame( 0, get_current_user_id(), 'The anonymous server-request user is restored after rendering.' );
		$this->assertSame( 'publish', get_post_status( $person_id ) );
	}

	public function test_sidebar_shared_email_renders_accessible_profile_switcher(): void {
		$this->createPerson( [ 'post_title' => 'First private name' ], [ 'email_1' => 'shared@example.test' ] );
		$this->createPerson( [ 'post_title' => 'Second private name' ], [ 'email_2' => 'shared@example.test' ] );

		$data = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'shared@example.test' ] ) )->get_data();

		$this->assertSame( 'ambiguous', $data['status'] );
		$this->assertStringContainsString( 'data-rondo-profile-switcher', $data['html'] );
		$this->assertStringContainsString( 'First private name', $data['html'] );
		$this->assertStringContainsString( 'Second private name', $data['html'] );
		$this->assertStringContainsString( 'data-rondo-profile-panel', $data['html'] );
		$this->assertStringContainsString( 'hidden', $data['html'] );
	}

	public function test_matcher_discards_synthetic_and_malformed_emails(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Synthetic' ], [ 'email_1' => '123@members.rondo.invalid' ] );
		$this->createPerson( [ 'post_title' => 'Inaccessible' ], [ 'email_1' => 'real@example.test' ] );
		$matcher = new PersonMatcher();

		$this->assertSame( 'no_match', $matcher->match( [ '123@members.rondo.invalid', 'not-an-email' ] )['status'] );
		$this->assertSame( 'inaccessible', $matcher->match( [ 'real@example.test' ], 'sidebar', 0 )['status'] ?? 'no_match' );
		$this->assertSame( 'publish', get_post_status( $person_id ) );
	}

	public function test_activity_is_idempotent_and_customer_changes_move_hide_and_restore_it(): void {
		$first  = $this->createPerson( [ 'post_title' => 'First member' ], [ 'email_1' => 'first@example.test' ] );
		$second = $this->createPerson( [ 'post_title' => 'Second member' ], [ 'email_1' => 'second@example.test' ] );
		$body   = $this->activity_body( 'conversation_created', [ 'first@example.test' ] );

		$created   = $this->signed_request( 'activity', $body )->get_data();
		$confirmed = $this->signed_request( 'activity', $body )->get_data();
		$this->assertSame( 'created', $created['status'] );
		$this->assertSame( 'confirmed', $confirmed['status'] );
		$this->assertSame( $created['activity_id'], $confirmed['activity_id'] );
		$this->assertSame( $first, (int) get_comment( $created['activity_id'] )->comment_post_ID );

		$moved = $this->signed_request( 'activity', $this->activity_body( 'conversation_customer_changed', [ 'second@example.test' ] ) )->get_data();
		$this->assertSame( 'moved', $moved['status'] );
		$this->assertSame( $second, (int) get_comment( $created['activity_id'] )->comment_post_ID );

		$hidden = $this->signed_request( 'activity', $this->activity_body( 'conversation_customer_changed', [ 'missing@example.test' ] ) )->get_data();
		$this->assertSame( 'no_match', $hidden['status'] );
		$this->assertSame( '0', (string) get_comment( $created['activity_id'] )->comment_approved );

		$restored = $this->signed_request( 'activity', $this->activity_body( 'conversation_customer_changed', [ 'second@example.test' ] ) )->get_data();
		$this->assertSame( 'restored', $restored['status'] );
		$this->assertSame( '1', (string) get_comment( $created['activity_id'] )->comment_approved );
		$this->assertSame(
			1,
			count(
			get_comments(
			[
				'type'   => 'rondo_activity',
				'status' => 'all',
			]
			)
			)
			);
		$this->assertStringNotContainsString( 'second@example.test', serialize( get_comment_meta( $created['activity_id'] ) ) );
	}

	public function test_reply_activities_are_distinct_idempotent_and_keep_message_content_out(): void {
		$first  = $this->createPerson( [ 'post_title' => 'First member' ], [ 'email_1' => 'first@example.test' ] );
		$second = $this->createPerson( [ 'post_title' => 'Second member' ], [ 'email_1' => 'second@example.test' ] );
		$this->signed_request( 'activity', $this->activity_body( 'conversation_created', [ 'first@example.test' ] ) );

		$incoming_body            = $this->activity_body( 'customer_replied', [ 'first@example.test' ] );
		$incoming_body['eventId'] = 1001;
		$incoming                 = $this->signed_request( 'activity', $incoming_body )->get_data();
		$incoming_replay          = $this->signed_request( 'activity', $incoming_body )->get_data();
		$this->assertSame( 'created', $incoming['status'] );
		$this->assertSame( 'confirmed', $incoming_replay['status'] );
		$this->assertSame( $incoming['activity_id'], $incoming_replay['activity_id'] );

		$outgoing_body            = $this->activity_body( 'user_replied', [ 'first@example.test' ] );
		$outgoing_body['eventId'] = 1002;
		$outgoing_body['actor']   = [
			'freescoutUserId' => 44,
			'issuer'          => OidcAuthorizationService::issuer(),
			'subject'         => $this->subject,
		];
		get_userdata( $this->agent_id )->remove_cap( 'ledenadministratie' );
		$outgoing         = $this->signed_request( 'activity', $outgoing_body )->get_data();
		$outgoing_comment = get_comment( $outgoing['activity_id'] );
		$this->assertSame( $this->agent_id, (int) $outgoing_comment->user_id );
		$this->assertStringContainsString( 'Antwoord verzonden door', $outgoing_comment->comment_content );
		$this->assertStringNotContainsString( 'berichttekst', $outgoing_comment->comment_content );

		$all = get_comments(
			[
				'type'   => 'rondo_activity',
				'status' => 'all',
			]
			);
		$this->assertCount( 3, $all );
		$moved = $this->signed_request( 'activity', $this->activity_body( 'conversation_customer_changed', [ 'second@example.test' ] ) )->get_data();
		$this->assertSame( 'moved', $moved['status'] );
		$this->assertCount( 3, $moved['activity_ids'] );
		foreach ( $all as $activity ) {
			$this->assertSame( $first, (int) $activity->comment_post_ID );
			$this->assertSame( $second, (int) get_comment( $activity->comment_ID )->comment_post_ID );
		}
	}

	public function test_reply_activity_requires_a_thread_event_id(): void {
		$this->createPerson( [ 'post_title' => 'First member' ], [ 'email_1' => 'first@example.test' ] );
		$response = $this->signed_request( 'activity', $this->activity_body( 'customer_replied', [ 'first@example.test' ] ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rondo_freescout_activity_schema_invalid', $response->as_error()->get_error_code() );
	}

	public function test_activity_date_and_time_use_the_site_timezone(): void {
		$previous_timezone = get_option( 'timezone_string' );
		update_option( 'timezone_string', 'Europe/Amsterdam' );

		try {
			$this->createPerson( [ 'post_title' => 'First member' ], [ 'email_1' => 'first@example.test' ] );
			$response    = $this->signed_request( 'activity', $this->activity_body( 'conversation_created', [ 'first@example.test' ] ) );
			$activity_id = (int) $response->get_data()['activity_id'];

			$this->assertSame( '2026-09-01', get_comment_meta( $activity_id, 'activity_date', true ) );
			$this->assertSame( '14:00', get_comment_meta( $activity_id, 'activity_time', true ) );
			$this->assertSame( '2026-09-01 12:00:00', get_comment( $activity_id )->comment_date_gmt );
			$this->assertSame( '2026-09-01 14:00:00', get_comment( $activity_id )->comment_date );
		} finally {
			update_option( 'timezone_string', $previous_timezone );
		}
	}

	/** @return array<string,mixed> */
	private function sidebar_body( array $emails ): array {
		return [
			'version'            => 1,
			'mailboxKey'         => 'ledenadministratie',
			'conversationId'     => 3456,
			'conversationNumber' => 789,
			'customerId'         => 123,
			'customerEmails'     => $emails,
			'agent'              => [
				'freescoutUserId' => 44,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
			],
		];
	}

	/** @return array<string,mixed> */
	private function activity_body( string $event_type, array $emails ): array {
		return [
			'version'        => 1,
			'eventType'      => $event_type,
			'instance'       => self::INSTANCE,
			'mailboxKey'     => 'ledenadministratie',
			'conversationId' => 8765,
			'customerId'     => 321,
			'customerEmails' => $emails,
			'subject'        => 'Vraag over lidmaatschap',
			'createdAt'      => '2026-09-01T12:00:00Z',
		];
	}

	private function signed_request( string $route, array $body, string $key = self::KEY, ?string $nonce = null, ?int $timestamp = null ): \WP_REST_Response {
		$raw       = wp_json_encode( $body, JSON_UNESCAPED_SLASHES );
		$timestamp = $timestamp ?? time();
		$nonce     = $nonce ?? 'test_nonce_value_' . str_pad( (string) ++$this->nonce_counter, 32, 'x' );
		$signature = hash_hmac( 'sha256', $timestamp . "\n" . $nonce . "\n" . $raw, $key );
		$request   = new \WP_REST_Request( 'POST', '/rondo/v1/integrations/freescout/' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-Rondo-Timestamp', (string) $timestamp );
		$request->set_header( 'X-Rondo-Nonce', $nonce );
		$request->set_header( 'X-Rondo-Signature', 'v1=' . $signature );
		$request->set_body( $raw );
		wp_set_current_user( 0 );
		return $this->server->dispatch( $request );
	}
}
