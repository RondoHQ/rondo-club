<?php
/**
 * REST API Endpoints for Todo Custom Post Type
 *
 * Provides CRUD operations for todos via the REST API,
 * replacing the comment-based todo endpoints.
 */

namespace Rondo\REST;

use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Todos extends Base {
	private const ASSIGNED_USER_META_KEY = 'assigned_user_id';

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Person-scoped endpoints
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/todos',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_person_todos' ],
					'permission_callback' => [ $this, 'check_person_access' ],
					'args'                => [
						'person_id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_person_todo' ],
					'permission_callback' => [ $this, 'check_person_access' ],
					'args'                => [
						'person_id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);

		// Global endpoints
		register_rest_route(
			'rondo/v1',
			'/todos',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_all_todos' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'status' => [
						'default'           => 'open',
						'validate_callback' => function ( $param ) {
							return in_array( $param, [ 'open', 'awaiting', 'completed', 'all' ], true );
						},
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/todos/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_todo' ],
					'permission_callback' => [ $this, 'check_todo_access' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_todo' ],
					'permission_callback' => [ $this, 'check_todo_access' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_todo' ],
					'permission_callback' => [ $this, 'check_todo_access' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
			]
		);
	}

	/**
	 * Check if user can access a todo
	 *
	 * Permission callback for single-todo operations.
	 * Verifies user is approved and can view the todo.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can access the todo, false otherwise.
	 */
	public function check_todo_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Check approval first
		if ( ! $this->check_user_approved() ) {
			return false;
		}

		$todo_id = $request->get_param( 'id' );
		$todo    = get_post( $todo_id );

		if ( ! $todo || $todo->post_type !== 'rondo_todo' ) {
			return false;
		}

		// Verify it's a valid todo status
		$valid_statuses = [ 'rondo_open', 'rondo_awaiting', 'rondo_completed', 'publish' ];
		if ( ! in_array( $todo->post_status, $valid_statuses, true ) ) {
			return false;
		}

		$current_user_id = get_current_user_id();
		$assigned_user   = (int) get_post_meta( $todo_id, self::ASSIGNED_USER_META_KEY, true );

		if ( (int) $todo->post_author !== $current_user_id && $assigned_user !== $current_user_id ) {
			return false;
		}

		return true;
	}

	/**
	 * Get todos for a specific person
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing todos for the person.
	 */
	public function get_person_todos( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );

		// Use LIKE query since ACF stores serialized arrays
		// The serialized format contains the ID as a quoted string: "123"
		// Note: suppress_filters must be false for access control to apply
		$todos = get_posts(
			[
				'post_type'        => 'rondo_todo',
				'posts_per_page'   => -1,
				'post_status'      => [ 'rondo_open', 'rondo_awaiting', 'rondo_completed' ],
				'suppress_filters' => false,
				'meta_query'       => [
					[
						'key'     => 'related_persons',
						'value'   => sprintf( '"%d"', $person_id ),
						'compare' => 'LIKE',
					],
				],
				'orderby'          => 'date',
				'order'            => 'DESC',
			]
		);

		$formatted = array_map( [ $this, 'format_todo' ], $todos );

		return rest_ensure_response( $formatted );
	}

	/**
	 * Create a todo linked to a person
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing created todo or error.
	 */
	public function create_person_todo( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$content   = sanitize_textarea_field( $request->get_param( 'content' ) );
		$due_date  = sanitize_text_field( $request->get_param( 'due_date' ) );
		$status    = $request->get_param( 'status' );
		$notes     = $request->get_param( 'notes' );
		$assigned_user_id = $this->sanitize_assigned_user_id( $request->get_param( 'assigned_user_id' ) );

		// Accept person_ids array for multi-person support, fallback to URL person_id
		$person_ids = $request->get_param( 'person_ids' );
		if ( ! is_array( $person_ids ) || empty( $person_ids ) ) {
			// Single person_id from URL for backward compatibility
			$person_ids = $person_id ? [ $person_id ] : [];
		} else {
			$person_ids = array_map( 'intval', $person_ids );
		}

		if ( empty( $person_ids ) ) {
			return new \WP_Error( 'no_person', __( 'At least one person is required.', 'rondo' ), [ 'status' => 400 ] );
		}

		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', __( 'Todo content is required.', 'rondo' ), [ 'status' => 400 ] );
		}

		// Validate and determine post status
		$valid_statuses = [ 'open', 'awaiting', 'completed' ];
		if ( $status !== null && ! in_array( $status, $valid_statuses, true ) ) {
			return new \WP_Error( 'invalid_status', __( 'Invalid status. Use: open, awaiting, or completed.', 'rondo' ), [ 'status' => 400 ] );
		}
		$post_status = $status ? 'rondo_' . $status : 'rondo_open';

		// Create the todo post
		$post_id = wp_insert_post(
			[
				'post_type'   => 'rondo_todo',
				'post_title'  => $content,
				'post_status' => $post_status,
				'post_author' => get_current_user_id(),
			]
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create todo.', 'rondo' ), [ 'status' => 500 ] );
		}

		// Save ACF fields - use new multi-person field
		update_field( 'related_persons', $person_ids, $post_id );

		if ( ! empty( $due_date ) ) {
			update_field( 'due_date', $due_date, $post_id );
		}

		// Save notes if provided (sanitize HTML for XSS protection)
		if ( $notes !== null ) {
			update_field( 'notes', wp_kses_post( $notes ), $post_id );
		}

		if ( $assigned_user_id !== null ) {
			update_post_meta( $post_id, self::ASSIGNED_USER_META_KEY, $assigned_user_id );
		} else {
			delete_post_meta( $post_id, self::ASSIGNED_USER_META_KEY );
		}

		$this->maybe_send_assignment_notification_email( $post_id, null, $assigned_user_id, $content, $notes );

		// Set awaiting_since timestamp if creating with awaiting status
		if ( $status === 'awaiting' ) {
			update_field( 'awaiting_since', gmdate( 'Y-m-d H:i:s' ), $post_id );
		}

		$todo = get_post( $post_id );

		return rest_ensure_response( $this->format_todo( $todo ) );
	}

	/**
	 * Get all todos for the current user
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing all accessible todos.
	 */
	public function get_all_todos( $request ) {
		$status = $request->get_param( 'status' );

		// Map status param to post_status values
		$status_map = [
			'open'      => [ 'rondo_open' ],
			'awaiting'  => [ 'rondo_awaiting' ],
			'completed' => [ 'rondo_completed' ],
			'all'       => [ 'rondo_open', 'rondo_awaiting', 'rondo_completed' ],
		];

		$post_statuses = $status_map[ $status ] ?? [ 'rondo_open' ];

		// Build query args - access control filter will handle visibility
		// Note: suppress_filters must be false for access control to apply
		$args = [
			'post_type'        => 'rondo_todo',
			'posts_per_page'   => 100, // Reasonable limit
			'post_status'      => $post_statuses,
			'suppress_filters' => false,
			'orderby'          => 'date',
			'order'            => 'DESC',
		];

		$todos     = get_posts( $args );
		$formatted = array_map( [ $this, 'format_todo' ], $todos );

		// Sort: open by due date, awaiting by waiting time, completed by date
		usort(
			$formatted,
			function ( $a, $b ) {
				// Status priority: open first, awaiting second, completed last
				$status_order = [
					'open'      => 0,
					'awaiting'  => 1,
					'completed' => 2,
				];
				$a_order      = $status_order[ $a['status'] ] ?? 0;
				$b_order      = $status_order[ $b['status'] ] ?? 0;

				if ( $a_order !== $b_order ) {
					return $a_order - $b_order;
				}

				// For open todos, sort by due date (earliest first)
				if ( $a['status'] === 'open' ) {
					if ( $a['due_date'] && $b['due_date'] ) {
						return strtotime( $a['due_date'] ) - strtotime( $b['due_date'] );
					}
					if ( $a['due_date'] && ! $b['due_date'] ) {
						return -1;
					}
					if ( ! $a['due_date'] && $b['due_date'] ) {
						return 1;
					}
				}

				// For awaiting todos, sort by awaiting_since (oldest first = waiting longest)
				if ( $a['status'] === 'awaiting' ) {
					if ( $a['awaiting_since'] && $b['awaiting_since'] ) {
						return strtotime( $a['awaiting_since'] ) - strtotime( $b['awaiting_since'] );
					}
				}

				// Default: sort by creation date (newest first)
				return strtotime( $b['created'] ) - strtotime( $a['created'] );
			}
		);

		return rest_ensure_response( $formatted );
	}

	/**
	 * Get a single todo
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing todo or error.
	 */
	public function get_todo( $request ) {
		$todo_id = (int) $request->get_param( 'id' );
		$todo    = get_post( $todo_id );

		if ( ! $todo || $todo->post_type !== 'rondo_todo' ) {
			return new \WP_Error( 'not_found', __( 'Todo not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->format_todo( $todo ) );
	}

	/**
	 * Update a todo
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response containing updated todo or error.
	 */
	public function update_todo( $request ) {
		$todo_id    = (int) $request->get_param( 'id' );
		$content    = $request->get_param( 'content' );
		$due_date   = $request->get_param( 'due_date' );
		$status     = $request->get_param( 'status' );
		$person_ids = $request->get_param( 'person_ids' );
		$notes      = $request->get_param( 'notes' );
		$assigned_user_param = $request->get_param( 'assigned_user_id' );
		$previous_assigned_user_id = (int) get_post_meta( $todo_id, self::ASSIGNED_USER_META_KEY, true );
		$new_assigned_user_id      = $previous_assigned_user_id;

		$todo = get_post( $todo_id );

		if ( ! $todo || $todo->post_type !== 'rondo_todo' ) {
			return new \WP_Error( 'not_found', __( 'Todo not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		// Build update args
		$update_args = [ 'ID' => $todo_id ];

		// Update post title if content provided
		if ( $content !== null ) {
			$update_args['post_title'] = sanitize_textarea_field( $content );
		}

		// Handle status change
		if ( $status !== null ) {
			$valid_statuses = [ 'open', 'awaiting', 'completed' ];
			if ( ! in_array( $status, $valid_statuses, true ) ) {
				return new \WP_Error( 'invalid_status', __( 'Invalid status. Use: open, awaiting, or completed.', 'rondo' ), [ 'status' => 400 ] );
			}

			$current_status  = $this->get_todo_status( $todo );
			$new_post_status = 'rondo_' . $status;

			// Set awaiting_since timestamp when changing to awaiting
			if ( $status === 'awaiting' && $current_status !== 'awaiting' ) {
				update_field( 'awaiting_since', gmdate( 'Y-m-d H:i:s' ), $todo_id );
			}

			// Clear awaiting_since when leaving awaiting status
			if ( $status !== 'awaiting' && $current_status === 'awaiting' ) {
				update_field( 'awaiting_since', '', $todo_id );
			}

			$update_args['post_status'] = $new_post_status;
		}

		// Apply updates
		if ( count( $update_args ) > 1 ) {
			wp_update_post( $update_args );
		}

		// Update due_date ACF field
		if ( $due_date !== null ) {
			if ( empty( $due_date ) ) {
				update_field( 'due_date', '', $todo_id );
			} else {
				update_field( 'due_date', sanitize_text_field( $due_date ), $todo_id );
			}
		}

		// Update persons if provided (multi-person support)
		if ( $person_ids !== null ) {
			if ( is_array( $person_ids ) && ! empty( $person_ids ) ) {
				update_field( 'related_persons', array_map( 'intval', $person_ids ), $todo_id );
			}
		}

		// Update notes if provided (sanitize HTML for XSS protection)
		if ( $notes !== null ) {
			update_field( 'notes', wp_kses_post( $notes ), $todo_id );
		}

		if ( $assigned_user_param !== null ) {
			$assigned_user_id = $this->sanitize_assigned_user_id( $assigned_user_param );
			if ( $assigned_user_id !== null ) {
				update_post_meta( $todo_id, self::ASSIGNED_USER_META_KEY, $assigned_user_id );
				$new_assigned_user_id = $assigned_user_id;
			} else {
				delete_post_meta( $todo_id, self::ASSIGNED_USER_META_KEY );
				$new_assigned_user_id = null;
			}

			$this->maybe_send_assignment_notification_email( $todo_id, $previous_assigned_user_id, $new_assigned_user_id, $content, $notes );
		}

		// Refresh the post object
		$todo = get_post( $todo_id );

		return rest_ensure_response( $this->format_todo( $todo ) );
	}

	/**
	 * Get the status string from a todo post
	 *
	 * @param WP_Post $post The todo post object.
	 * @return string Status string (open, awaiting, completed).
	 */
	private function get_todo_status( $post ) {
		$status_map = [
			'rondo_open'      => 'open',
			'rondo_awaiting'  => 'awaiting',
			'rondo_completed' => 'completed',
			'publish'       => 'open', // Legacy fallback
		];

		return $status_map[ $post->post_status ] ?? 'open';
	}

	/**
	 * Delete a todo
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response indicating success or error.
	 */
	public function delete_todo( $request ) {
		$todo_id = (int) $request->get_param( 'id' );
		$todo    = get_post( $todo_id );

		if ( ! $todo || $todo->post_type !== 'rondo_todo' ) {
			return new \WP_Error( 'not_found', __( 'Todo not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		$result = wp_delete_post( $todo_id, true ); // Force delete (bypass trash)

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete todo.', 'rondo' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	/**
	 * Format a todo post for REST response
	 *
	 * @param WP_Post $post The todo post object.
	 * @return array Formatted todo data.
	 */
	private function format_todo( $post ) {
		// Get persons array (new multi-person format)
		$person_ids = get_field( 'related_persons', $post->ID ) ?: [];
		if ( ! is_array( $person_ids ) ) {
			$person_ids = $person_ids ? [ $person_ids ] : [];
		}

		// Build persons array with details
		$persons = [];
		foreach ( $person_ids as $pid ) {
			$persons[] = [
				'id'        => (int) $pid,
				'name'      => $this->sanitize_text( get_the_title( $pid ) ),
				'thumbnail' => $this->sanitize_url( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ),
			];
		}

		$status         = $this->get_todo_status( $post );
		$due_date       = get_field( 'due_date', $post->ID );
		$awaiting_since = get_field( 'awaiting_since', $post->ID );
		$notes          = get_field( 'notes', $post->ID );
		$assigned_user_id = (int) get_post_meta( $post->ID, self::ASSIGNED_USER_META_KEY, true );
		$assignee         = null;
		if ( $assigned_user_id > 0 ) {
			$user = get_userdata( $assigned_user_id );
			if ( $user ) {
				$assignee = [
					'id'           => (int) $user->ID,
					'display_name' => $this->sanitize_text( $user->display_name ),
					'email'        => sanitize_email( $user->user_email ),
					'avatar_url'   => $this->sanitize_url( get_avatar_url( $user->ID, [ 'size' => 48 ] ) ),
				];
			}
		}

		$lettermint_event_name = sanitize_text_field(
			(string) get_post_meta( $post->ID, \Rondo\Notifications\LettermintWebhook::META_EVENT_NAME, true )
		);
		$lettermint_recipient = sanitize_email(
			(string) get_post_meta( $post->ID, \Rondo\Notifications\LettermintWebhook::META_RECIPIENT, true )
		);
		$lettermint_flow = sanitize_text_field(
			(string) get_post_meta( $post->ID, \Rondo\Notifications\LettermintWebhook::META_FLOW, true )
		);
		$candidate_recipient = $lettermint_recipient !== '' ? $lettermint_recipient : $this->find_first_email_from_persons( $person_ids );
		$contains_bounce     = stripos( $post->post_title, 'bounce' ) !== false;
		$can_verify_email    = $candidate_recipient !== '' && ( $lettermint_event_name !== '' || $contains_bounce );

		return [
			'id'               => $post->ID,
			'type'             => 'todo',
			'content'          => $this->sanitize_text( $post->post_title ),
			// Deprecated fields for backward compatibility (first person only)
			'person_id'        => $persons[0]['id'] ?? null,
			'person_name'      => $persons[0]['name'] ?? '',
			'person_thumbnail' => $persons[0]['thumbnail'] ?? '',
			// New multi-person format
			'persons'          => $persons,
			'notes'            => $notes ?: null,
			'author_id'        => (int) $post->post_author,
			'assigned_user_id' => $assigned_user_id > 0 ? $assigned_user_id : null,
			'assignee'         => $assignee,
			'created'          => $post->post_date,
			'status'           => $status,
			'due_date'         => $due_date ?: null,
			'awaiting_since'   => $awaiting_since ?: null,
			'lettermint'       => [
				'event_name'          => $lettermint_event_name !== '' ? $lettermint_event_name : null,
				'recipient'           => $lettermint_recipient !== '' ? $lettermint_recipient : null,
				'flow'                => $lettermint_flow !== '' ? $lettermint_flow : null,
				'is_actionable_bounce'=> $lettermint_event_name !== '' ? in_array( $lettermint_event_name, \Rondo\Notifications\LettermintWebhook::ACTIONABLE_EVENTS, true ) : false,
			],
			'email_verification' => [
				'can_send'  => $can_verify_email,
				'recipient' => $candidate_recipient !== '' ? $candidate_recipient : null,
			],
		];
	}

	/**
	 * Normalize and validate the assigned user ID input.
	 *
	 * @param mixed $raw_value Raw request value.
	 * @return int|null User ID when valid, or null for unassigned/invalid.
	 */
	private function sanitize_assigned_user_id( $raw_value ) {
		if ( $raw_value === null || $raw_value === '' ) {
			return null;
		}

		$user_id = (int) $raw_value;
		if ( $user_id <= 0 ) {
			return null;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return null;
		}

		return $user_id;
	}

	/**
	 * Find first valid email address across related persons.
	 *
	 * @param int[] $person_ids Related person IDs.
	 * @return string
	 */
	private function find_first_email_from_persons( array $person_ids ): string {
		foreach ( $person_ids as $person_id ) {
			$email = sanitize_email( (string) get_field( 'email_1', (int) $person_id ) );
			if ( is_email( $email ) ) {
				return $email;
			}

			$email = sanitize_email( (string) get_field( 'email_2', (int) $person_id ) );
			if ( is_email( $email ) ) {
				return $email;
			}
		}

		return '';
	}

	/**
	 * Send assignment notification email when a todo gets assigned to a user.
	 *
	 * @param int         $todo_id               Todo post ID.
	 * @param int|null    $previous_assignee_id  Previous assignee user ID.
	 * @param int|null    $new_assignee_id       New assignee user ID.
	 * @param string|null $fallback_title        Optional title from request payload.
	 * @param string|null $fallback_description  Optional notes/description from request payload.
	 * @return void
	 */
	private function maybe_send_assignment_notification_email(
		int $todo_id,
		?int $previous_assignee_id,
		?int $new_assignee_id,
		?string $fallback_title = null,
		?string $fallback_description = null
	): void {
		$new_assignee_id      = $new_assignee_id !== null ? (int) $new_assignee_id : null;
		$previous_assignee_id = $previous_assignee_id !== null ? (int) $previous_assignee_id : null;

		if ( empty( $new_assignee_id ) ) {
			return;
		}

		if ( $previous_assignee_id !== null && $new_assignee_id === $previous_assignee_id ) {
			return;
		}

		$assignee = get_userdata( $new_assignee_id );
		if ( ! $assignee || ! is_email( $assignee->user_email ) ) {
			return;
		}

		$current_user = wp_get_current_user();
		$assigner     = trim( (string) ( $current_user->display_name ?: $current_user->user_login ?: '' ) );
		if ( $assigner === '' ) {
			$assigner = 'Een gebruiker';
		}

		$title = $fallback_title !== null ? $fallback_title : get_the_title( $todo_id );
		$title = trim( wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) );
		if ( $title === '' ) {
			$title = __( '(zonder titel)', 'rondo' );
		}

		$description = $fallback_description !== null ? $fallback_description : (string) get_field( 'notes', $todo_id );
		$description = $this->normalize_todo_description_for_email( $description );

		$subject = sprintf( '[Rondo] Nieuwe taak: %s', $title );
		$todo_url = home_url( '/todos' );
		$message  = EmailTemplate::render(
			[
				'brand_name' => get_bloginfo( 'name' ),
				'preheader'  => $subject,
				'eyebrow'    => 'Taak',
				'heading'    => $title,
				'body_html'  => EmailTemplate::format_plain_text(
					sprintf(
						"%s heeft een taak aan jou toegewezen.\n\nBeschrijving:\n%s",
						$assigner,
						$description
					)
				),
				'cta_url'    => $todo_url,
				'cta_label'  => 'Open taken',
			]
		);

		$sent = wp_mail(
			$assignee->user_email,
			$subject,
			$message,
			[
				'Content-Type: text/html; charset=UTF-8',
				'X-Rondo-Email-Tag: todo-assigned',
			]
		);

		if ( ! $sent ) {
			error_log( sprintf( 'Todo assignment email failed for todo %d to user %d', $todo_id, $new_assignee_id ) );
		}
	}

	/**
	 * Convert todo description HTML into plain text for assignment emails.
	 *
	 * @param string $description Raw description/notes value.
	 * @return string
	 */
	private function normalize_todo_description_for_email( string $description ): string {
		$text = html_entity_decode( $description, ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/<\s*br\s*\/?>/i', "\n", $text );
		$text = preg_replace( '/<\/p>/i', "\n\n", $text );
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		$text = trim( (string) $text );

		return $text;
	}
}
