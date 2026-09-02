<?php
/**
 * Signed Rondo services consumed by the Rondo Integration FreeScout module.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Collaboration\CommentTypes;
use Rondo\Identity\OidcAuthorizationService;
use Rondo\Identity\OidcClientRegistry;
use Rondo\Identity\OidcIdentity;
use Rondo\Integrations\FreeScout\Config;
use Rondo\Integrations\FreeScout\PersonMatcher;
use Rondo\Integrations\FreeScout\RequestAuthenticator;
use Rondo\Integrations\FreeScout\SidebarRenderer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Publicly routable endpoints protected by exact-body HMAC authentication. */
final class FreeScoutIntegration extends Base {

	private const VERSION                = 1;
	private const MAILBOX_KEY            = 'ledenadministratie';
	private const REQUIRED_CAPABILITY    = 'ledenadministratie';
	private const SIDEBAR_POLICY         = 'ledenadministratie.v1';
	private const ACTIVITY_META_INSTANCE = '_rondo_freescout_instance';
	private const ACTIVITY_META_ID       = '_rondo_freescout_conversation_id';
	private const ACTIVITY_META_CUSTOMER = '_rondo_freescout_customer_id';
	private const ACTIVITY_META_MAILBOX  = '_rondo_freescout_mailbox_key';
	private const ACTIVITY_META_STATE    = '_rondo_freescout_match_state';
	private const ACTIVITY_META_UPDATED  = '_rondo_freescout_updated_at';

	private RequestAuthenticator $authenticator;
	private PersonMatcher $matcher;
	private SidebarRenderer $renderer;

	public function __construct() {
		$this->authenticator = new RequestAuthenticator();
		$this->matcher       = new PersonMatcher();
		$this->renderer      = new SidebarRenderer();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		foreach ( [ 'configuration', 'access', 'sidebar', 'activity' ] as $route ) {
			register_rest_route(
				'rondo/v1',
				'/integrations/freescout/' . $route,
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, $route ],
					'permission_callback' => '__return_true',
				]
			);
		}
	}

	/** Return the closed Rondo mailbox catalog and effective retention policy. */
	public function configuration( \WP_REST_Request $request ) {
		$body = $this->authenticator->authenticate( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( ! $this->valid_version( $body ) ) {
			return $this->error( 'rondo_freescout_version_invalid', 'Niet-ondersteunde integratieversie.', 400 );
		}

		$instance = $this->registered_instance( (string) ( $body['instance'] ?? '' ) );
		if ( is_wp_error( $instance ) ) {
			return $instance;
		}
		$retention = Config::retention_policy();
		if ( is_wp_error( $retention ) ) {
			return $retention;
		}

		return rest_ensure_response(
			[
				'version'      => self::VERSION,
				'mappings'     => [ $this->mailbox_mapping() ],
				'audit'        => $retention,
				'evaluated_at' => gmdate( DATE_ATOM ),
			]
		);
	}

	/** Return current managed mailbox keys for one exact bound OIDC subject. */
	public function access( \WP_REST_Request $request ) {
		$body = $this->authenticator->authenticate( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( ! $this->valid_version( $body ) ) {
			return $this->error( 'rondo_freescout_version_invalid', 'Niet-ondersteunde integratieversie.', 400 );
		}

		$issuer            = (string) ( $body['issuer'] ?? '' );
		$subject           = (string) ( $body['subject'] ?? '' );
		$freescout_user_id = $body['freescoutUserId'] ?? null;
		if ( $issuer !== OidcAuthorizationService::issuer()
			|| ! $this->valid_subject( $subject )
			|| ( $freescout_user_id !== null && ( ! is_int( $freescout_user_id ) || $freescout_user_id <= 0 ) )
		) {
			return $this->error( 'rondo_freescout_access_schema_invalid', 'De access request is ongeldig.', 400 );
		}
		$user_id = $this->resolve_subject( $issuer, $subject );
		$active  = $user_id > 0 && user_can( $user_id, self::REQUIRED_CAPABILITY );

		$audit_context = $freescout_user_id !== null ? [ 'freescout_user_id' => $freescout_user_id ] : [];
		$this->audit( 'access_evaluated', $active ? 'active' : 'inactive', $audit_context );

		return rest_ensure_response(
			[
				'subject'           => $subject,
				'active'            => $active,
				'managed_mailboxes' => $active ? [ self::MAILBOX_KEY ] : [],
				'evaluated_at'      => gmdate( DATE_ATOM ),
			]
		);
	}

	/** Render a live mailbox-specific sidebar as the exact effective Rondo user. */
	public function sidebar( \WP_REST_Request $request ) {
		$started = microtime( true );
		$body    = $this->authenticator->authenticate( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$valid = $this->validate_sidebar_body( $body );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$agent   = $body['agent'];
		$user_id = $this->resolve_subject( (string) $agent['issuer'], (string) $agent['subject'] );
		if ( $user_id <= 0 || ! user_can( $user_id, self::REQUIRED_CAPABILITY ) ) {
			$this->audit( 'sidebar_denied', 'unauthorized', [ 'freescout_user_id' => absint( $agent['freescoutUserId'] ) ] );
			return rest_ensure_response( $this->sidebar_response( 'unauthorized', $this->renderer->state( 'Je Rondo-toegang voor deze mailbox is niet actief.' ) ) );
		}

		$previous_user_id = get_current_user_id();
		wp_set_current_user( $user_id );
		try {
			$match = $this->matcher->match( $body['customerEmails'], 'sidebar', $user_id );
			if ( $match['status'] !== 'exact' || empty( $match['person_id'] ) ) {
				$public_status = $match['status'] === 'ambiguous' ? 'ambiguous' : 'no_match';
				$message       = $public_status === 'ambiguous'
					? 'Meerdere Rondo-profielen gebruiken dit e-mailadres.'
					: 'Geen gekoppeld Rondo-profiel gevonden.';
				$this->audit( 'sidebar_match', $match['status'], [ 'freescout_user_id' => absint( $agent['freescoutUserId'] ) ] );
				return rest_ensure_response( $this->sidebar_response( $public_status, $this->renderer->state( $message ) ) );
			}

			$html = $this->renderer->render( (int) $match['person_id'] );
			$this->audit(
				'sidebar_match',
				'exact',
				[
					'freescout_user_id' => absint( $agent['freescoutUserId'] ),
					'person_id'         => (int) $match['person_id'],
					'latency_ms'        => (int) round( ( microtime( true ) - $started ) * 1000 ),
				]
			);
			return rest_ensure_response( $this->sidebar_response( 'ok', $html ) );
		} finally {
			wp_set_current_user( $previous_user_id );
		}
	}

	/** Create, confirm, move, hide or restore an idempotent FreeScout activity pointer. */
	public function activity( \WP_REST_Request $request ) {
		$body = $this->authenticator->authenticate( $request );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$valid = $this->validate_activity_body( $body );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$instance = $this->registered_instance( (string) $body['instance'] );
		if ( is_wp_error( $instance ) ) {
			return $instance;
		}

		$conversation_id = absint( $body['conversationId'] );
		$existing        = $this->find_activity( $instance, $conversation_id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$match      = $this->matcher->match( $body['customerEmails'], 'integration' );
		$event_type = (string) $body['eventType'];
		if ( $match['status'] !== 'exact' || empty( $match['person_id'] ) ) {
			if ( $event_type === 'conversation_customer_changed' && $existing instanceof \WP_Comment ) {
				$this->hide_activity( $existing, $body, $match['status'] );
			}
			$this->audit( 'activity_match', $match['status'], [ 'conversation_id' => $conversation_id ] );
			return rest_ensure_response(
				[
					'status'          => $match['status'],
					'activity_id'     => $existing instanceof \WP_Comment ? (int) $existing->comment_ID : null,
					'conversation_id' => $conversation_id,
				]
			);
		}

		$person_id = (int) $match['person_id'];
		if ( $existing instanceof \WP_Comment ) {
			$result = $this->confirm_activity( $existing, $person_id, $body );
		} else {
			$result = $this->create_activity( $person_id, $instance, $body );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			[
				'status'          => $result['status'],
				'activity_id'     => $result['activity_id'],
				'conversation_id' => $conversation_id,
			]
		);
	}

	/** @return true|\WP_Error */
	private function validate_sidebar_body( array $body ) {
		if ( ! $this->valid_version( $body ) || (string) ( $body['mailboxKey'] ?? '' ) !== self::MAILBOX_KEY ) {
			return $this->error( 'rondo_freescout_sidebar_schema_invalid', 'De sidebar request is ongeldig.', 400 );
		}
		foreach ( [ 'conversationId', 'conversationNumber', 'customerId' ] as $field ) {
			if ( absint( $body[ $field ] ?? 0 ) <= 0 ) {
				return $this->error( 'rondo_freescout_sidebar_schema_invalid', 'De sidebar request is ongeldig.', 400 );
			}
		}
		if ( ! isset( $body['customerEmails'] ) || ! is_array( $body['customerEmails'] ) || count( $body['customerEmails'] ) > 10 || ! is_array( $body['agent'] ?? null ) ) {
			return $this->error( 'rondo_freescout_sidebar_schema_invalid', 'De sidebar request is ongeldig.', 400 );
		}
		$agent = $body['agent'];
		if ( absint( $agent['freescoutUserId'] ?? 0 ) <= 0 || ! $this->valid_subject( (string) ( $agent['subject'] ?? '' ) ) || (string) ( $agent['issuer'] ?? '' ) !== OidcAuthorizationService::issuer() ) {
			return $this->error( 'rondo_freescout_agent_invalid', 'De gekoppelde Rondo-gebruiker is ongeldig.', 403 );
		}

		return true;
	}

	/** @return true|\WP_Error */
	private function validate_activity_body( array $body ) {
		$event_types = [ 'conversation_created', 'conversation_customer_changed' ];
		if ( ! $this->valid_version( $body ) || ! in_array( (string) ( $body['eventType'] ?? '' ), $event_types, true ) || (string) ( $body['mailboxKey'] ?? '' ) !== self::MAILBOX_KEY ) {
			return $this->error( 'rondo_freescout_activity_schema_invalid', 'De activiteit request is ongeldig.', 400 );
		}
		if ( absint( $body['conversationId'] ?? 0 ) <= 0 || absint( $body['customerId'] ?? 0 ) <= 0 || ! is_array( $body['customerEmails'] ?? null ) || count( $body['customerEmails'] ) > 10 ) {
			return $this->error( 'rondo_freescout_activity_schema_invalid', 'De activiteit request is ongeldig.', 400 );
		}
		if ( mb_strlen( (string) ( $body['subject'] ?? '' ) ) > 998 || strtotime( (string) ( $body['createdAt'] ?? '' ) ) === false ) {
			return $this->error( 'rondo_freescout_activity_schema_invalid', 'De activiteit request is ongeldig.', 400 );
		}

		return true;
	}

	private function resolve_subject( string $issuer, string $subject ): int {
		if ( $issuer !== OidcAuthorizationService::issuer() || ! $this->valid_subject( $subject ) ) {
			return 0;
		}
		$users = get_users(
			[
				'meta_key'   => OidcIdentity::META_SUBJECT,
				'meta_value' => $subject,
				'fields'     => 'ids',
				'number'     => 2,
			]
		);
		if ( count( $users ) !== 1 ) {
			return 0;
		}
		$identity = OidcIdentity::resolve( (int) $users[0], false );

		return ! is_wp_error( $identity ) && hash_equals( $subject, (string) $identity['sub'] ) ? (int) $users[0] : 0;
	}

	private function valid_subject( string $subject ): bool {
		return preg_match( '/^[A-Za-z0-9_-]{43}$/', $subject ) === 1;
	}

	private function valid_version( array $body ): bool {
		return isset( $body['version'] ) && is_int( $body['version'] ) && $body['version'] === self::VERSION;
	}

	/** @return string|\WP_Error */
	private function registered_instance( string $instance ) {
		$normalized = untrailingslashit( esc_url_raw( trim( $instance ) ) );
		if ( $normalized === '' ) {
			return $this->error( 'rondo_freescout_instance_invalid', 'De FreeScout-installatie ontbreekt.', 400 );
		}
		foreach ( OidcClientRegistry::all() as $client ) {
			if ( ! empty( $client['enabled'] ) && hash_equals( untrailingslashit( (string) ( $client['freescout_base_url'] ?? '' ) ), $normalized ) ) {
				return $normalized;
			}
		}

		return $this->error( 'rondo_freescout_instance_unregistered', 'Deze FreeScout-installatie is niet geregistreerd.', 403 );
	}

	/** @return array<string,mixed> */
	private function mailbox_mapping(): array {
		return [
			'key'                 => self::MAILBOX_KEY,
			'label'               => 'Ledenadministratie',
			'required_capability' => self::REQUIRED_CAPABILITY,
			'sidebar_policy'      => self::SIDEBAR_POLICY,
			'enabled'             => true,
		];
	}

	/** @return array<string,mixed> */
	private function sidebar_response( string $status, string $html ): array {
		return [
			'version'      => self::VERSION,
			'status'       => $status,
			'html'         => $html,
			'generated_at' => gmdate( DATE_ATOM ),
		];
	}

	/** @return \WP_Comment|null|\WP_Error */
	private function find_activity( string $instance, int $conversation_id ) {
		$comments = get_comments(
			[
				'type'       => CommentTypes::TYPE_ACTIVITY,
				'status'     => 'all',
				'number'     => 2,
				'meta_query' => [
					'relation' => 'AND',
					[
						'key'     => self::ACTIVITY_META_INSTANCE,
						'value'   => $instance,
						'compare' => '=',
					],
					[
						'key'     => self::ACTIVITY_META_ID,
						'value'   => $conversation_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);
		if ( count( $comments ) > 1 ) {
			return $this->error( 'rondo_freescout_activity_duplicate', 'Er bestaan meerdere activiteiten voor deze conversatie.', 409 );
		}

		return $comments[0] ?? null;
	}

	/** @return array{status:string,activity_id:int}|\WP_Error */
	private function create_activity( int $person_id, string $instance, array $body ) {
		$created = strtotime( (string) $body['createdAt'] );
		$gmt     = gmdate( 'Y-m-d H:i:s', $created );
		$content = $this->activity_content( $instance, absint( $body['conversationId'] ), (string) $body['subject'] );
		$id      = wp_insert_comment(
			[
				'comment_post_ID'      => $person_id,
				'comment_content'      => $content,
				'comment_type'         => CommentTypes::TYPE_ACTIVITY,
				'comment_approved'     => 1,
				'comment_author'       => 'Rondo Integration',
				'comment_author_email' => '',
				'user_id'              => 0,
				'comment_date_gmt'     => $gmt,
				'comment_date'         => get_date_from_gmt( $gmt ),
			]
		);
		if ( ! $id ) {
			return $this->error( 'rondo_freescout_activity_create_failed', 'De activiteit kon niet worden gemaakt.', 500 );
		}
		$this->update_activity_meta( (int) $id, $instance, $body, 'matched' );
		update_comment_meta( (int) $id, 'activity_type', 'email' );
		update_comment_meta( (int) $id, 'activity_date', gmdate( 'Y-m-d', $created ) );
		update_comment_meta( (int) $id, 'activity_time', gmdate( 'H:i', $created ) );
		$this->audit(
			'activity_created',
			'exact',
			[
				'conversation_id' => absint( $body['conversationId'] ),
				'person_id'       => $person_id,
			]
		);

		return [
			'status'      => 'created',
			'activity_id' => (int) $id,
		];
	}

	/** @return array{status:string,activity_id:int}|\WP_Error */
	private function confirm_activity( \WP_Comment $comment, int $person_id, array $body ) {
		$old_person_id = (int) $comment->comment_post_ID;
		$was_hidden    = (string) $comment->comment_approved !== '1';
		$instance      = (string) get_comment_meta( $comment->comment_ID, self::ACTIVITY_META_INSTANCE, true );
		$result        = wp_update_comment(
			[
				'comment_ID'       => (int) $comment->comment_ID,
				'comment_post_ID'  => $person_id,
				'comment_content'  => $this->activity_content( $instance, absint( $body['conversationId'] ), (string) $body['subject'] ),
				'comment_approved' => 1,
			],
			true
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->update_activity_meta( (int) $comment->comment_ID, $instance, $body, 'matched' );
		$status = $old_person_id !== $person_id ? 'moved' : ( $was_hidden ? 'restored' : 'confirmed' );
		$this->audit(
			'activity_' . $status,
			'exact',
			[
				'conversation_id' => absint( $body['conversationId'] ),
				'old_person_id'   => $old_person_id,
				'new_person_id'   => $person_id,
			]
		);

		return [
			'status'      => $status,
			'activity_id' => (int) $comment->comment_ID,
		];
	}

	private function hide_activity( \WP_Comment $comment, array $body, string $match_state ): void {
		wp_set_comment_status( (int) $comment->comment_ID, 'hold' );
		$instance = (string) get_comment_meta( $comment->comment_ID, self::ACTIVITY_META_INSTANCE, true );
		$this->update_activity_meta( (int) $comment->comment_ID, $instance, $body, $match_state );
		$this->audit(
			'activity_hidden',
			$match_state,
			[
				'conversation_id' => absint( $body['conversationId'] ),
				'old_person_id'   => (int) $comment->comment_post_ID,
			]
		);
	}

	private function update_activity_meta( int $comment_id, string $instance, array $body, string $state ): void {
		update_comment_meta( $comment_id, self::ACTIVITY_META_INSTANCE, $instance );
		update_comment_meta( $comment_id, self::ACTIVITY_META_ID, absint( $body['conversationId'] ) );
		update_comment_meta( $comment_id, self::ACTIVITY_META_CUSTOMER, absint( $body['customerId'] ) );
		update_comment_meta( $comment_id, self::ACTIVITY_META_MAILBOX, self::MAILBOX_KEY );
		update_comment_meta( $comment_id, self::ACTIVITY_META_STATE, sanitize_key( $state ) );
		update_comment_meta( $comment_id, self::ACTIVITY_META_UPDATED, gmdate( DATE_ATOM ) );
	}

	private function activity_content( string $instance, int $conversation_id, string $subject ): string {
		$url = $instance . '/conversation/' . $conversation_id;

		return '<p><strong>' . esc_html( wp_strip_all_tags( $subject ) ) . '</strong></p><p><a href="' . esc_url( $url ) . '">Bekijk in FreeScout</a></p>';
	}

	private function audit( string $event, string $reason, array $context = [] ): void {
		do_action(
			'rondo_freescout_integration_audit',
			array_merge(
				[
					'event'       => $event,
					'outcome'     => str_contains( $event, 'denied' ) ? 'denied' : 'processed',
					'reason'      => $reason,
					'mailbox_key' => self::MAILBOX_KEY,
					'occurred_at' => gmdate( DATE_ATOM ),
				],
				$context
			)
		);
	}

	private function error( string $code, string $message, int $status ): \WP_Error {
		return new \WP_Error( $code, $message, [ 'status' => $status ] );
	}
}
