<?php
/**
 * Durable Rondo-to-FreeScout mailbox-access event delivery.
 *
 * @package Rondo\Integrations\FreeScout
 */

namespace Rondo\Integrations\FreeScout;

use Rondo\Identity\OidcAuthorizationService;
use Rondo\Identity\OidcClientRegistry;
use Rondo\Identity\OidcIdentity;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Queue capability changes in native WordPress storage and deliver them at least once. */
final class ProvisioningEvents {

	public const POST_TYPE  = 'rondo_fs_event';
	public const CRON_HOOK  = 'rondo_deliver_freescout_access_events';
	public const AUDIT_HOOK = 'rondo_audit_freescout_access_events';
	public const BATCH_SIZE = 10;

	private const CLAIM_TTL       = 600;
	private const RETRY_DELAYS    = [ 60, 300, 900, 3600 ];
	private const META_UUID       = '_rondo_fs_event_uuid';
	private const META_SUBJECT    = '_rondo_fs_subject';
	private const META_CLIENT_ID  = '_rondo_fs_client_id';
	private const META_STATE      = '_rondo_fs_state';
	private const META_ATTEMPTS   = '_rondo_fs_attempts';
	private const META_NEXT_AT    = '_rondo_fs_next_attempt_at';
	private const META_REASON     = '_rondo_fs_last_reason';
	private const META_CLAIMED_AT = '_rondo_fs_claimed_at';

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_type' ] );
		add_action( 'init', [ $this, 'ensure_schedules' ], 20 );
		add_action( self::CRON_HOOK, [ $this, 'process' ] );
		add_action( self::AUDIT_HOOK, [ $this, 'audit' ] );
		add_action( 'added_user_meta', [ $this, 'capabilities_meta_changed' ], 10, 3 );
		add_action( 'updated_user_meta', [ $this, 'capabilities_meta_changed' ], 10, 3 );
		add_action( 'deleted_user_meta', [ $this, 'capabilities_meta_changed' ], 10, 3 );
		add_action( 'rondo_freescout_role_access_changed', [ $this, 'enqueue_role' ] );
		add_action( 'rondo_freescout_provisioning_enabled', [ $this, 'seed_existing_identities' ] );
	}

	public function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			[
				'label'               => 'FreeScout integration events',
				'public'              => false,
				'publicly_queryable'  => false,
				'show_ui'             => false,
				'show_in_rest'        => false,
				'exclude_from_search' => true,
				'supports'            => [ 'title' ],
			]
		);
	}

	/** Keep a bounded repair worker and nightly aggregate audit scheduled. */
	public function ensure_schedules(): void {
		if ( ! Config::provisioning_events_enabled() ) {
			return;
		}
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::AUDIT_HOOK ) ) {
			wp_schedule_event( strtotime( 'tomorrow 03:15' ), 'daily', self::AUDIT_HOOK );
		}
	}

	/** Queue a current-state event when one user's roles or direct capabilities change. */
	public function capabilities_meta_changed( $meta_id, int $user_id, string $meta_key ): void {
		unset( $meta_id );
		global $wpdb;
		if ( $meta_key === $wpdb->prefix . 'capabilities' ) {
			$this->enqueue_user( $user_id );
		}
	}

	/** Queue every previously identified user belonging to a changed role. */
	public function enqueue_role( string $role ): void {
		if ( ! Config::provisioning_events_enabled() || ! get_role( $role ) ) {
			return;
		}
		$user_ids = get_users(
			[
				'role'         => $role,
				'fields'       => 'ids',
				'meta_key'     => OidcIdentity::META_SUBJECT,
				'meta_compare' => 'EXISTS',
			]
		);
		foreach ( $user_ids as $user_id ) {
			$this->enqueue_user( (int) $user_id );
		}
	}

	/** Seed the queue when realtime delivery is enabled after identities already exist. */
	public function seed_existing_identities(): int {
		if ( ! Config::provisioning_events_enabled() ) {
			return 0;
		}
		$user_ids = get_users(
			[
				'fields'       => 'ids',
				'meta_key'     => OidcIdentity::META_SUBJECT,
				'meta_compare' => 'EXISTS',
			]
		);
		$queued   = 0;
		foreach ( $user_ids as $user_id ) {
			$queued += count( $this->enqueue_user( (int) $user_id ) );
		}

		return $queued;
	}

	/** Queue one current-state re-evaluation per enabled FreeScout client. */
	public function enqueue_user( int $user_id ): array {
		if ( ! Config::provisioning_events_enabled() ) {
			return [];
		}
		$subject = (string) get_user_meta( $user_id, OidcIdentity::META_SUBJECT, true );
		if ( preg_match( '/^[A-Za-z0-9_-]{43}$/', $subject ) !== 1 ) {
			return [];
		}

		$queued = [];
		foreach ( OidcClientRegistry::all() as $client ) {
			if ( empty( $client['enabled'] ) || empty( $client['client_id'] ) || empty( $client['freescout_base_url'] ) ) {
				continue;
			}
			$post_id = $this->enqueue( $subject, (string) $client['client_id'] );
			if ( $post_id > 0 ) {
				$queued[] = $post_id;
			}
		}
		if ( $queued !== [] ) {
			$this->schedule_worker( time() + 1 );
		}

		return $queued;
	}

	/** Process a bounded set of due queue posts. */
	public function process(): array {
		$totals = [
			'processed' => 0,
			'delivered' => 0,
			'retrying'  => 0,
		];
		if ( ! Config::provisioning_events_enabled() ) {
			return $totals;
		}
		$this->release_stale_claims();
		$post_ids = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => self::BATCH_SIZE,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'     => self::META_STATE,
						'value'   => [ 'pending', 'retry' ],
						'compare' => 'IN',
					],
					[
						'key'     => self::META_NEXT_AT,
						'value'   => time(),
						'compare' => '<=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);
		foreach ( $post_ids as $post_id ) {
			if ( ! $this->claim( (int) $post_id ) ) {
				continue;
			}
			++$totals['processed'];
			$result = $this->deliver( (int) $post_id );
			if ( $result === true ) {
				wp_delete_post( (int) $post_id, true );
				++$totals['delivered'];
			} else {
				$this->retry( (int) $post_id, (string) $result );
				++$totals['retrying'];
			}
		}
		if ( count( $post_ids ) === self::BATCH_SIZE ) {
			$this->schedule_worker( time() + 1 );
		}

		return $totals;
	}

	/** Record aggregate health without user, subject or capability data. */
	public function audit(): array {
		$health = self::health();
		do_action(
			'rondo_freescout_integration_audit',
			[
				'event'       => 'provisioning_queue_audit',
				'outcome'     => $health['unresolved'] > 0 ? 'attention' : 'ok',
				'reason'      => $health['unresolved'] > 0 ? 'delivery_failures' : 'healthy',
				'pending'     => $health['pending'],
				'unresolved'  => $health['unresolved'],
				'occurred_at' => gmdate( DATE_ATOM ),
			]
		);

		return $health;
	}

	/** Return aggregate queue health for the administrator settings screen. */
	public static function health(): array {
		$post_ids   = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
		);
		$unresolved = 0;
		foreach ( $post_ids as $post_id ) {
			if ( (int) get_post_meta( $post_id, self::META_ATTEMPTS, true ) >= 5 ) {
				++$unresolved;
			}
		}

		return [
			'enabled'    => Config::provisioning_events_enabled(),
			'pending'    => count( $post_ids ),
			'unresolved' => $unresolved,
		];
	}

	private function enqueue( string $subject, string $client_id ): int {
		$existing = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'   => self::META_SUBJECT,
						'value' => $subject,
					],
					[
						'key'   => self::META_CLIENT_ID,
						'value' => $client_id,
					],
					[
						'key'     => self::META_STATE,
						'value'   => [ 'pending', 'retry' ],
						'compare' => 'IN',
					],
				],
			]
		);
		if ( $existing !== [] ) {
			update_post_meta( (int) $existing[0], self::META_STATE, 'pending' );
			update_post_meta( (int) $existing[0], self::META_NEXT_AT, time() );
			return (int) $existing[0];
		}

		$uuid    = wp_generate_uuid4();
		$post_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_status' => 'private',
				'post_title'  => 'FreeScout access event ' . substr( $uuid, 0, 8 ),
			],
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return 0;
		}
		update_post_meta( $post_id, self::META_UUID, $uuid );
		update_post_meta( $post_id, self::META_SUBJECT, $subject );
		update_post_meta( $post_id, self::META_CLIENT_ID, $client_id );
		update_post_meta( $post_id, self::META_STATE, 'pending' );
		update_post_meta( $post_id, self::META_ATTEMPTS, 0 );
		update_post_meta( $post_id, self::META_NEXT_AT, time() );

		return (int) $post_id;
	}

	private function claim( int $post_id ): bool {
		$state = (string) get_post_meta( $post_id, self::META_STATE, true );
		if ( ! in_array( $state, [ 'pending', 'retry' ], true ) ) {
			return false;
		}
		if ( ! update_post_meta( $post_id, self::META_STATE, 'processing', $state ) ) {
			return false;
		}
		update_post_meta( $post_id, self::META_CLAIMED_AT, time() );

		return true;
	}

	/** @return true|string */
	private function deliver( int $post_id ) {
		$client_id = (string) get_post_meta( $post_id, self::META_CLIENT_ID, true );
		$client    = OidcClientRegistry::find( $client_id );
		if ( ! is_array( $client ) || empty( $client['enabled'] ) || empty( $client['freescout_base_url'] ) ) {
			return 'client_unavailable';
		}
		$keys = Config::signing_keys();
		if ( $keys === [] ) {
			return 'signing_key_missing';
		}
		$payload = [
			'version' => 1,
			'eventId' => (string) get_post_meta( $post_id, self::META_UUID, true ),
			'issuer'  => OidcAuthorizationService::issuer(),
			'subject' => (string) get_post_meta( $post_id, self::META_SUBJECT, true ),
		];
		$body    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $body ) ) {
			return 'payload_encoding_failed';
		}
		$timestamp = (string) time();
		$nonce     = rtrim( strtr( base64_encode( random_bytes( 24 ) ), '+/', '-_' ), '=' );
		$signature = hash_hmac( 'sha256', $timestamp . "\n" . $nonce . "\n" . $body, $keys[0] );
		$response  = wp_remote_post(
			untrailingslashit( (string) $client['freescout_base_url'] ) . '/rondo/integration/events/access',
			[
				'timeout'     => 5,
				'redirection' => 0,
				'sslverify'   => true,
				'headers'     => [
					'Accept'            => 'application/json',
					'Content-Type'      => 'application/json',
					'X-Rondo-Timestamp' => $timestamp,
					'X-Rondo-Nonce'     => $nonce,
					'X-Rondo-Signature' => 'v1=' . $signature,
				],
				'body'        => $body,
			]
		);
		if ( is_wp_error( $response ) ) {
			return sanitize_key( $response->get_error_code() ?: 'request_failed' );
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 ) {
			return 'http_' . $status;
		}
		if ( ! is_array( $data ) || ! in_array( (string) ( $data['status'] ?? '' ), [ 'reconciled', 'unbound', 'already_processed' ], true ) ) {
			return 'response_invalid';
		}
		do_action(
			'rondo_freescout_integration_audit',
			[
				'event'       => 'provisioning_event_delivered',
				'outcome'     => 'processed',
				'reason'      => (string) $data['status'],
				'occurred_at' => gmdate( DATE_ATOM ),
			]
		);

		return true;
	}

	private function retry( int $post_id, string $reason ): void {
		$attempts = (int) get_post_meta( $post_id, self::META_ATTEMPTS, true ) + 1;
		$index    = min( $attempts - 1, count( self::RETRY_DELAYS ) - 1 );
		$next_at  = time() + self::RETRY_DELAYS[ $index ];
		update_post_meta( $post_id, self::META_ATTEMPTS, $attempts );
		update_post_meta( $post_id, self::META_REASON, sanitize_key( $reason ) );
		update_post_meta( $post_id, self::META_NEXT_AT, $next_at );
		update_post_meta( $post_id, self::META_STATE, 'retry' );
		delete_post_meta( $post_id, self::META_CLAIMED_AT );
		$this->schedule_worker( $next_at );
	}

	private function release_stale_claims(): void {
		$post_ids = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'private',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => self::META_STATE,
				'meta_value'       => 'processing',
			]
		);
		foreach ( $post_ids as $post_id ) {
			$claimed_at = (int) get_post_meta( $post_id, self::META_CLAIMED_AT, true );
			if ( $claimed_at > time() - self::CLAIM_TTL ) {
				continue;
			}
			update_post_meta( $post_id, self::META_STATE, 'retry' );
			update_post_meta( $post_id, self::META_NEXT_AT, time() );
			update_post_meta( $post_id, self::META_REASON, 'delivery_interrupted' );
		}
	}

	private function schedule_worker( int $timestamp ): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) || wp_next_scheduled( self::CRON_HOOK ) > $timestamp ) {
			wp_schedule_single_event( $timestamp, self::CRON_HOOK );
		}
	}
}
