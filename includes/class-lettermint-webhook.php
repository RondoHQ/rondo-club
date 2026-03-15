<?php
/**
 * Lettermint webhook handler.
 *
 * Processes Lettermint delivery events and creates follow-up tasks for the
 * Secretaris when bounces or spam complaints occur.
 *
 * @package Rondo\Notifications
 */

namespace Rondo\Notifications;

use Rondo\Core\RoleFinder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LettermintWebhook {

	/**
	 * Event names that require follow-up tasks.
	 */
	const ACTIONABLE_EVENTS = [
		'message.hard_bounced',
		'message.soft_bounced',
		'message.spam_complaint',
	];

	/**
	 * Option key for suppressed emails state.
	 */
	const OPTION_SUPPRESSED_EMAILS = 'rondo_lettermint_suppressed_emails';

	/**
	 * Todo meta key for webhook event id.
	 */
	const META_EVENT_ID = '_rondo_lettermint_event_id';

	/**
	 * Todo meta key for webhook event name.
	 */
	const META_EVENT_NAME = '_rondo_lettermint_event_name';

	/**
	 * Todo meta key for impacted recipient.
	 */
	const META_RECIPIENT = '_rondo_lettermint_recipient';

	/**
	 * Todo meta key for provider message ID.
	 */
	const META_MESSAGE_ID = '_rondo_lettermint_message_id';

	/**
	 * Todo meta key for flow (e.g. email verification).
	 */
	const META_FLOW = '_rondo_lettermint_flow';

	/**
	 * Metadata flow value for manual email verification sends.
	 */
	const FLOW_EMAIL_VERIFICATION = 'email_verification';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register webhook endpoint.
	 */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/lettermint/webhook',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_webhook' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function handle_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		if ( ! class_exists( '\Lettermint\Webhook' ) ) {
			return new \WP_REST_Response(
				[
					'ok'    => false,
					'error' => 'sdk_missing',
				],
				500
			);
		}

		$secret = LettermintConfig::get_webhook_secret();
		if ( $secret === '' ) {
			error_log( 'Lettermint webhook: missing signing secret' );
			return new \WP_REST_Response(
				[
					'ok'    => false,
					'error' => 'missing_secret',
				],
				503
			);
		}

		$payload_raw = (string) $request->get_body();
		$headers     = $this->flatten_headers( $request->get_headers() );

		try {
			$verifier = new \Lettermint\Webhook( $secret, LettermintConfig::get_webhook_tolerance() );
			$event    = $verifier->verifyHeaders( $headers, $payload_raw );
		} catch ( \Throwable $e ) {
			error_log( 'Lettermint webhook: signature verification failed - ' . $e->getMessage() );
			return new \WP_REST_Response(
				[
					'ok'    => false,
					'error' => 'invalid_signature',
				],
				401
			);
		}

		$event_name = sanitize_text_field( (string) ( $event['event'] ?? '' ) );
		if ( ! in_array( $event_name, self::ACTIONABLE_EVENTS, true ) ) {
			return rest_ensure_response(
				[
					'ok'      => true,
					'ignored' => true,
				]
			);
		}

		$result = $this->process_actionable_event( $event_name, $event );
		if ( is_wp_error( $result ) ) {
			error_log( 'Lettermint webhook: processing failed - ' . $result->get_error_message() );
			return new \WP_REST_Response(
				[
					'ok'    => false,
					'error' => 'processing_failed',
				],
				500
			);
		}

		return rest_ensure_response( [ 'ok' => true ] );
	}

	/**
	 * Process actionable delivery event.
	 *
	 * @param string $event_name Event name.
	 * @param array  $event      Decoded payload.
	 * @return true|\WP_Error
	 */
	private function process_actionable_event( string $event_name, array $event ) {
		$event_id = sanitize_text_field( (string) ( $event['id'] ?? '' ) );
		$data     = is_array( $event['data'] ?? null ) ? $event['data'] : [];

		if ( $event_id === '' ) {
			return new \WP_Error( 'lettermint_missing_event_id', 'Missing Lettermint event ID.' );
		}

		$recipient = strtolower( trim( sanitize_email( (string) ( $data['recipient'] ?? '' ) ) ) );
		if ( $recipient === '' ) {
			return new \WP_Error( 'lettermint_missing_recipient', 'Missing recipient in Lettermint event.' );
		}

		$subject    = sanitize_text_field( (string) ( $data['subject'] ?? '' ) );
		$message_id = sanitize_text_field( (string) ( $data['message_id'] ?? '' ) );
		$timestamp  = sanitize_text_field( (string) ( $data['timestamp'] ?? ( $event['timestamp'] ?? '' ) ) );
		$tag        = sanitize_text_field( (string) ( $data['tag'] ?? '' ) );
		$metadata   = $this->extract_metadata( $data );
		$flow       = sanitize_text_field( (string) ( $metadata['flow'] ?? '' ) );

		$person_id = $this->find_person_by_email( $recipient );
		if ( $person_id <= 0 && isset( $metadata['source_person_id'] ) ) {
			$source_person_id = (int) $metadata['source_person_id'];
			if ( $source_person_id > 0 ) {
				$person_id = $source_person_id;
			}
		}
		$this->upsert_suppressed_email(
			$recipient,
			$event_name,
			$event_id,
			$subject,
			$message_id,
			$timestamp,
			$tag,
			$person_id
		);

		if ( $flow === self::FLOW_EMAIL_VERIFICATION && $person_id > 0 ) {
			$this->mark_person_email_inactive( $person_id, $recipient, $event_name, $event_id );
		}

		$sender_user_id = isset( $metadata['sender_user_id'] ) ? (int) $metadata['sender_user_id'] : 0;
		if ( $flow === self::FLOW_EMAIL_VERIFICATION && $sender_user_id > 0 ) {
			$owners = [ $sender_user_id ];
		} else {
			$owners = RoleFinder::get_user_ids_by_role( 'Secretaris' );
		}

		if ( empty( $owners ) ) {
			return new \WP_Error( 'lettermint_no_secretaris', 'No Secretaris or admin user available for task assignment.' );
		}

		$errors = [];
		foreach ( $owners as $owner_id ) {
			$task_result = $this->create_follow_up_task(
				(int) $owner_id,
				$event_name,
				$event_id,
				$recipient,
				$subject,
				$message_id,
				$timestamp,
				$tag,
				$data,
				$person_id,
				$flow
			);

			if ( is_wp_error( $task_result ) ) {
				$errors[] = $task_result->get_error_message();
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'lettermint_task_create_failed', implode( '; ', $errors ) );
		}

		return true;
	}

	/**
	 * Create follow-up task for one owner.
	 *
	 * @param int    $owner_id   Task owner (post_author).
	 * @param string $event_name Event name.
	 * @param string $event_id   Event ID.
	 * @param string $recipient  Recipient email.
	 * @param string $subject    Message subject.
	 * @param string $message_id Provider message id.
	 * @param string $timestamp  Event timestamp.
	 * @param string $tag        Event tag.
	 * @param array  $data       Event data payload.
	 * @param int    $person_id  Matched person ID.
	 * @param string $flow       Optional event flow.
	 * @return int|\WP_Error
	 */
	private function create_follow_up_task(
		int $owner_id,
		string $event_name,
		string $event_id,
		string $recipient,
		string $subject,
		string $message_id,
		string $timestamp,
		string $tag,
		array $data,
		int $person_id = 0,
		string $flow = ''
	) {
		$existing = $this->find_existing_follow_up_task(
			$owner_id,
			$event_id,
			$event_name,
			$recipient,
			$message_id,
			$flow
		);

		if ( $existing > 0 ) {
			return $existing;
		}

		$person_name = $this->resolve_person_name( $person_id, $recipient );
		if ( $flow === self::FLOW_EMAIL_VERIFICATION ) {
			$title = sprintf( 'Het email adres van %s werkt niet meer.', $person_name );
		} else {
			$title = sprintf( '%s: %s', $this->event_label( $event_name ), $recipient );
		}
		$post_id = wp_insert_post(
			[
				'post_type'   => 'rondo_todo',
				'post_status' => 'rondo_open',
				'post_title'  => $title,
				'post_author' => $owner_id,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$notes = $this->build_task_notes( $event_name, $recipient, $subject, $message_id, $timestamp, $tag, $event_id, $data, $flow, $person_id );
		update_field( 'notes', wpautop( esc_html( $notes ) ), $post_id );

		if ( $person_id > 0 ) {
			update_field( 'related_persons', [ $person_id ], $post_id );
		}

		update_post_meta( $post_id, self::META_EVENT_ID, $event_id );
		update_post_meta( $post_id, self::META_EVENT_NAME, $event_name );
		update_post_meta( $post_id, self::META_RECIPIENT, $recipient );
		if ( $message_id !== '' ) {
			update_post_meta( $post_id, self::META_MESSAGE_ID, $message_id );
		}
		if ( $flow !== '' ) {
			update_post_meta( $post_id, self::META_FLOW, $flow );
		}

		return (int) $post_id;
	}

	/**
	 * Find existing follow-up task for this delivery event/mail.
	 *
	 * Primary dedupe key is event_id+event_name. Secondary key is
	 * message_id+recipient(+flow), which prevents duplicate tasks when a single
	 * mail triggers multiple webhook retries/events with different event IDs.
	 *
	 * @param int    $owner_id   Task owner (post_author).
	 * @param string $event_id   Event ID.
	 * @param string $event_name Event name.
	 * @param string $recipient  Recipient email.
	 * @param string $message_id Provider message ID.
	 * @param string $flow       Optional event flow.
	 * @return int Existing todo ID or 0 when not found.
	 */
	private function find_existing_follow_up_task(
		int $owner_id,
		string $event_id,
		string $event_name,
		string $recipient,
		string $message_id,
		string $flow
	): int {
		$by_event = get_posts(
			[
				'post_type'      => 'rondo_todo',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'author'         => $owner_id,
				'meta_query'     => [
					[
						'key'   => self::META_EVENT_ID,
						'value' => $event_id,
					],
					[
						'key'   => self::META_EVENT_NAME,
						'value' => $event_name,
					],
				],
			]
		);
		if ( ! empty( $by_event ) ) {
			return (int) $by_event[0];
		}

		if ( $message_id === '' || $recipient === '' ) {
			return 0;
		}

		$message_meta_query = [
			[
				'key'   => self::META_MESSAGE_ID,
				'value' => $message_id,
			],
			[
				'key'   => self::META_RECIPIENT,
				'value' => $recipient,
			],
		];
		if ( $flow !== '' ) {
			$message_meta_query[] = [
				'key'   => self::META_FLOW,
				'value' => $flow,
			];
		}

		$by_message = get_posts(
			[
				'post_type'      => 'rondo_todo',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'author'         => $owner_id,
				'meta_query'     => $message_meta_query,
			]
		);
		if ( ! empty( $by_message ) ) {
			return (int) $by_message[0];
		}

		return 0;
	}

	/**
	 * Build human-readable task notes.
	 *
	 * @param string $event_name Event name.
	 * @param string $recipient  Recipient email.
	 * @param string $subject    Message subject.
	 * @param string $message_id Provider message id.
	 * @param string $timestamp  Event timestamp.
	 * @param string $tag        Event tag.
	 * @param string $event_id   Event ID.
	 * @param array  $data       Event payload data.
	 * @param string $flow       Optional event flow.
	 * @param int    $person_id  Matched person ID.
	 * @return string
	 */
	private function build_task_notes(
		string $event_name,
		string $recipient,
		string $subject,
		string $message_id,
		string $timestamp,
		string $tag,
		string $event_id,
		array $data,
		string $flow = '',
		int $person_id = 0
	): string {
		if ( $flow === self::FLOW_EMAIL_VERIFICATION ) {
			$first_name  = $this->resolve_person_first_name( $person_id );
			$person_name = $this->resolve_person_name( $person_id, $recipient );

			$lines   = [];
			$lines[] = sprintf( 'We hebben ontdekt dat het e-mailadres %s van %s niet meer werkt. Zou je hem willen contacten en vragen om een werkend e-mailadres? Hieronder vind je een bericht wat je hem kan sturen:', $recipient, $person_name );
			$lines[] = '';
			$lines[] = sprintf( 'Hoi %s,', $first_name );
			$lines[] = '';
			$lines[] = sprintf( 'Onze secretaris heeft ontdekt dat je e-mailadres %s niet meer werkt. Zou je me een werkend nieuw e-mailadres kunnen sturen?', $recipient );
			$lines[] = '';
			$lines[] = 'Dank je wel!';

			return implode( "\n", $lines );
		}

		$reason = '';
		if ( is_array( $data['response'] ?? null ) ) {
			$reason = trim( (string) ( $data['response']['content'] ?? '' ) );
		}

		$lines   = [];
		$lines[] = 'Lettermint delivery event: ' . $this->event_label( $event_name );
		$lines[] = 'E-mailadres: ' . $recipient;
		if ( $subject !== '' ) {
			$lines[] = 'Onderwerp: ' . $subject;
		}
		if ( $message_id !== '' ) {
			$lines[] = 'Message ID: ' . $message_id;
		}
		if ( $event_id !== '' ) {
			$lines[] = 'Event ID: ' . $event_id;
		}
		if ( $timestamp !== '' ) {
			$lines[] = 'Timestamp: ' . $timestamp;
		}
		if ( $tag !== '' ) {
			$lines[] = 'Tag: ' . $tag;
		}
		if ( $flow !== '' ) {
			$lines[] = 'Flow: ' . $flow;
		}
		if ( $reason !== '' ) {
			$lines[] = 'Melding: ' . $reason;
		}

		$lines[] = '';
		$lines[] = 'Actie: controleer het e-mailadres, neem contact op met het lid en werk de gegevens bij indien nodig.';

		return implode( "\n", $lines );
	}

	/**
	 * Upsert suppressed-email state.
	 *
	 * @param string $recipient  Recipient email.
	 * @param string $event_name Event name.
	 * @param string $event_id   Event ID.
	 * @param string $subject    Subject.
	 * @param string $message_id Message ID.
	 * @param string $timestamp  Event timestamp.
	 * @param string $tag        Event tag.
	 * @param int    $person_id  Matched person ID.
	 * @return void
	 */
	private function upsert_suppressed_email(
		string $recipient,
		string $event_name,
		string $event_id,
		string $subject,
		string $message_id,
		string $timestamp,
		string $tag,
		int $person_id
	): void {
		$suppressed = get_option( self::OPTION_SUPPRESSED_EMAILS, [] );
		if ( ! is_array( $suppressed ) ) {
			$suppressed = [];
		}

		$current = is_array( $suppressed[ $recipient ] ?? null ) ? $suppressed[ $recipient ] : [];
		$count   = isset( $current['count'] ) ? (int) $current['count'] : 0;

		$suppressed[ $recipient ] = [
			'event'      => $event_name,
			'event_id'   => $event_id,
			'count'      => $count + 1,
			'subject'    => $subject,
			'message_id' => $message_id,
			'timestamp'  => $timestamp,
			'tag'        => $tag,
			'person_id'  => $person_id > 0 ? $person_id : null,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		];

		update_option( self::OPTION_SUPPRESSED_EMAILS, $suppressed, false );
	}

	/**
	 * Extract and sanitize provider metadata from event payload.
	 *
	 * @param array $data Event data payload.
	 * @return array<string, mixed>
	 */
	private function extract_metadata( array $data ): array {
		$metadata = [];
		if ( is_array( $data['metadata'] ?? null ) ) {
			$metadata = $data['metadata'];
		} elseif ( is_array( $data['meta'] ?? null ) ) {
			$metadata = $data['meta'];
		}

		$sanitized = [];
		foreach ( $metadata as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( $key === '' ) {
				continue;
			}

			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = $value;
				continue;
			}

			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Mark matching person contact email as inactive after verification bounce.
	 *
	 * Stores the inactive status in _rondo_inactive_emails post meta.
	 * The email value in the fixed field is kept unchanged.
	 *
	 * @param int    $person_id  Person post ID.
	 * @param string $recipient  Recipient email.
	 * @param string $event_name Event name.
	 * @param string $event_id   Event ID.
	 * @return void
	 */
	private function mark_person_email_inactive( int $person_id, string $recipient, string $event_name, string $event_id ): void {
		// Check if the recipient matches email_1 or email_2.
		$email_1 = strtolower( trim( (string) get_field( 'email_1', $person_id ) ) );
		$email_2 = strtolower( trim( (string) get_field( 'email_2', $person_id ) ) );

		if ( $recipient !== $email_1 && $recipient !== $email_2 ) {
			return;
		}

		$inactive = get_post_meta( $person_id, '_rondo_inactive_emails', true );
		if ( ! is_array( $inactive ) ) {
			$inactive = [];
		}

		$inactive[ $recipient ] = [
			'event'      => $event_name,
			'event_id'   => $event_id,
			'updated_at' => gmdate( 'Y-m-d H:i:s' ),
		];

		update_post_meta( $person_id, '_rondo_inactive_emails', $inactive );
	}

	/**
	 * Resolve display name for notes/titles.
	 *
	 * @param int    $person_id Person post ID.
	 * @param string $fallback  Fallback value.
	 * @return string
	 */
	private function resolve_person_name( int $person_id, string $fallback ): string {
		if ( $person_id <= 0 ) {
			return $fallback;
		}

		$first_name = trim( (string) get_field( 'first_name', $person_id ) );
		$last_name  = trim( (string) get_field( 'last_name', $person_id ) );
		$full_name  = trim( $first_name . ' ' . $last_name );

		return $full_name !== '' ? $full_name : $fallback;
	}

	/**
	 * Resolve first name for note template.
	 *
	 * @param int $person_id Person post ID.
	 * @return string
	 */
	private function resolve_person_first_name( int $person_id ): string {
		if ( $person_id <= 0 ) {
			return 'daar';
		}

		$first_name = trim( (string) get_field( 'first_name', $person_id ) );
		return $first_name !== '' ? $first_name : 'daar';
	}

	/**
	 * Find person by email from fixed email fields.
	 *
	 * @param string $email Email address.
	 * @return int
	 */
	private function find_person_by_email( string $email ): int {
		foreach ( [ 'email_1', 'email_2' ] as $field ) {
			$matches = get_posts(
				[
					'post_type'        => 'person',
					'posts_per_page'   => 1,
					'post_status'      => 'publish',
					'suppress_filters' => true,
					'fields'           => 'ids',
					'meta_query'       => [
						[
							'key'     => $field,
							'value'   => $email,
							'compare' => '=',
						],
					],
				]
			);

			if ( ! empty( $matches ) ) {
				return (int) $matches[0];
			}
		}

		return 0;
	}

	/**
	 * Convert request header arrays into scalar map for SDK verifier.
	 *
	 * @param array $headers REST request headers.
	 * @return array
	 */
	private function flatten_headers( array $headers ): array {
		$flat = [];

		foreach ( $headers as $key => $value ) {
			if ( is_array( $value ) ) {
				$flat[ (string) $key ] = (string) reset( $value );
			} else {
				$flat[ (string) $key ] = (string) $value;
			}
		}

		// WordPress normalizes headers in different ways depending on server stack.
		// Add canonical Lettermint keys expected by the SDK verifier.
		$signature = $this->pick_header_value(
			$flat,
			[
				'X-Lettermint-Signature',
				'x-lettermint-signature',
				'x_lettermint_signature',
				'HTTP_X_LETTERMINT_SIGNATURE',
			]
		);
		if ( $signature !== '' ) {
			$flat['X-Lettermint-Signature'] = $signature;
		}

		$delivery = $this->pick_header_value(
			$flat,
			[
				'X-Lettermint-Delivery',
				'x-lettermint-delivery',
				'x_lettermint_delivery',
				'HTTP_X_LETTERMINT_DELIVERY',
			]
		);
		if ( $delivery !== '' ) {
			$flat['X-Lettermint-Delivery'] = $delivery;
		}

		return $flat;
	}

	/**
	 * Pick first non-empty value from candidate header keys.
	 *
	 * @param array  $headers    Flattened headers.
	 * @param string[] $candidates Candidate keys.
	 * @return string
	 */
	private function pick_header_value( array $headers, array $candidates ): string {
		foreach ( $candidates as $candidate ) {
			if ( isset( $headers[ $candidate ] ) ) {
				$value = trim( (string) $headers[ $candidate ] );
				if ( $value !== '' ) {
					return $value;
				}
			}
		}

		return '';
	}

	/**
	 * Human-readable label for event.
	 *
	 * @param string $event_name Event name.
	 * @return string
	 */
	private function event_label( string $event_name ): string {
		return match ( $event_name ) {
			'message.hard_bounced' => 'Hard bounce',
			'message.soft_bounced' => 'Soft bounce',
			'message.spam_complaint' => 'Spamklacht',
			default => $event_name,
		};
	}
}
