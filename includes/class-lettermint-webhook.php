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
			$owners = $this->get_secretaris_user_ids();
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
		$existing = get_posts(
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

		if ( ! empty( $existing ) ) {
			return (int) $existing[0];
		}

		$title_prefix = $flow === self::FLOW_EMAIL_VERIFICATION
			? 'Verificatiemail ' . strtolower( $this->event_label( $event_name ) )
			: $this->event_label( $event_name );
		$title        = sprintf( '%s: %s', $title_prefix, $recipient );
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

		$notes = $this->build_task_notes( $event_name, $recipient, $subject, $message_id, $timestamp, $tag, $event_id, $data, $flow );
		update_field( 'notes', wpautop( esc_html( $notes ) ), $post_id );

		if ( $person_id > 0 ) {
			update_field( 'related_persons', [ $person_id ], $post_id );
		}

		update_post_meta( $post_id, self::META_EVENT_ID, $event_id );
		update_post_meta( $post_id, self::META_EVENT_NAME, $event_name );
		update_post_meta( $post_id, self::META_RECIPIENT, $recipient );
		if ( $flow !== '' ) {
			update_post_meta( $post_id, self::META_FLOW, $flow );
		}

		return (int) $post_id;
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
		string $flow = ''
	): string {
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
		if ( $flow === self::FLOW_EMAIL_VERIFICATION ) {
			$lines[] = 'Actie: de verificatiemail is gebounced; markeer dit e-mailadres als onbruikbaar en vraag het lid om een werkend e-mailadres.';
		} else {
			$lines[] = 'Actie: controleer het e-mailadres, neem contact op met het lid en werk de gegevens bij indien nodig.';
		}

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
	 * @param int    $person_id  Person post ID.
	 * @param string $recipient  Recipient email.
	 * @param string $event_name Event name.
	 * @param string $event_id   Event ID.
	 * @return void
	 */
	private function mark_person_email_inactive( int $person_id, string $recipient, string $event_name, string $event_id ): void {
		$contact_info = get_field( 'contact_info', $person_id );
		if ( ! is_array( $contact_info ) ) {
			return;
		}

		$updated = false;
		foreach ( $contact_info as &$contact ) {
			$type  = strtolower( trim( (string) ( $contact['contact_type'] ?? '' ) ) );
			$value = strtolower( trim( sanitize_email( (string) ( $contact['contact_value'] ?? '' ) ) ) );
			if ( $type !== 'email' || $value !== $recipient ) {
				continue;
			}

			$label = trim( (string) ( $contact['contact_label'] ?? '' ) );
			if ( stripos( $label, 'inactief' ) === false ) {
				$contact['contact_label'] = $label !== '' ? $label . ' (inactief)' : 'Inactief';
				$updated                  = true;
			}
		}
		unset( $contact );

		if ( $updated ) {
			update_field( 'contact_info', $contact_info, $person_id );
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
	 * Find person by email from contact_info repeater.
	 *
	 * @param string $email Email address.
	 * @return int
	 */
	private function find_person_by_email( string $email ): int {
		$people = get_posts(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'post_status'      => 'publish',
				'suppress_filters' => true,
				'fields'           => 'ids',
			]
		);

		foreach ( $people as $person_id ) {
			$contact_info = get_field( 'contact_info', (int) $person_id ) ?: [];
			if ( ! is_array( $contact_info ) ) {
				continue;
			}

			foreach ( $contact_info as $contact ) {
				$type  = strtolower( trim( (string) ( $contact['contact_type'] ?? '' ) ) );
				$value = strtolower( trim( sanitize_email( (string) ( $contact['contact_value'] ?? '' ) ) ) );

				if ( $type === 'email' && $value !== '' && $value === $email ) {
					return (int) $person_id;
				}
			}
		}

		return 0;
	}

	/**
	 * Resolve users that should receive bounce/complaint tasks.
	 *
	 * Prefers users whose linked person currently has a functie containing
	 * "secretaris". Falls back to administrators.
	 *
	 * @return int[]
	 */
	private function get_secretaris_user_ids(): array {
		$user_ids = get_users(
			[
				'fields'   => 'ids',
				'meta_key' => 'rondo_linked_person_id',
			]
		);

		$owners = [];
		foreach ( $user_ids as $user_id ) {
			$person_id = (int) get_user_meta( (int) $user_id, 'rondo_linked_person_id', true );
			if ( $person_id <= 0 ) {
				continue;
			}
			if ( $this->person_has_current_secretaris_role( $person_id ) ) {
				$owners[] = (int) $user_id;
			}
		}

		$owners = array_values( array_unique( $owners ) );
		if ( ! empty( $owners ) ) {
			return $owners;
		}

		$admins = get_users(
			[
				'role'   => 'administrator',
				'fields' => 'ids',
			]
		);

		return array_values( array_unique( array_map( 'intval', $admins ) ) );
	}

	/**
	 * Check whether person has a current "secretaris" functie.
	 *
	 * @param int $person_id Person post ID.
	 * @return bool
	 */
	private function person_has_current_secretaris_role( int $person_id ): bool {
		$work_history = get_field( 'work_history', $person_id );
		if ( ! is_array( $work_history ) ) {
			return false;
		}

		foreach ( $work_history as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$job_title = trim( (string) ( $entry['job_title'] ?? '' ) );
			if ( $job_title === '' || stripos( $job_title, 'secretaris' ) === false ) {
				continue;
			}

			if ( $this->is_current_work_history_entry( $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether a work_history row is currently active.
	 *
	 * @param array $entry Work history row.
	 * @return bool
	 */
	private function is_current_work_history_entry( array $entry ): bool {
		if ( ! empty( $entry['is_current'] ) ) {
			return true;
		}

		$today_ts = strtotime( current_time( 'Y-m-d' ) );
		if ( $today_ts === false ) {
			return false;
		}

		$start_raw = trim( (string) ( $entry['start_date'] ?? '' ) );
		$end_raw   = trim( (string) ( $entry['end_date'] ?? '' ) );

		$start_ts = $start_raw !== '' ? strtotime( $start_raw ) : false;
		$end_ts   = $end_raw !== '' ? strtotime( $end_raw ) : false;

		if ( $start_ts !== false && $start_ts > $today_ts ) {
			return false;
		}
		if ( $end_ts !== false && $end_ts < $today_ts ) {
			return false;
		}

		return true;
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
