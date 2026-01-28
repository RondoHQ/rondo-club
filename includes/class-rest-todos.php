<?php
/**
 * REST API Endpoints for Todo Custom Post Type
 *
 * Provides CRUD operations for todos via the REST API,
 * replacing the comment-based todo endpoints.
 */

namespace Stadion\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Todos extends Base {

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
			'stadion/v1',
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
			'stadion/v1',
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
			'stadion/v1',
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
	 * Verifies user is approved and can access the todo via access control.
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

		if ( ! $todo || $todo->post_type !== 'stadion_todo' ) {
			return false;
		}

		// Verify it's a valid todo status
		$valid_statuses = [ 'stadion_open', 'stadion_awaiting', 'stadion_completed', 'publish' ];
		if ( ! in_array( $todo->post_status, $valid_statuses, true ) ) {
			return false;
		}

		// Use access control to check if user can access this todo
		$access_control = new \STADION_Access_Control();
		return $access_control->user_can_access_post( $todo_id );
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
		$todos = get_posts(
			[
				'post_type'      => 'stadion_todo',
				'posts_per_page' => -1,
				'post_status'    => [ 'stadion_open', 'stadion_awaiting', 'stadion_completed' ],
				'meta_query'     => [
					[
						'key'     => 'related_persons',
						'value'   => sprintf( '"%d"', $person_id ),
						'compare' => 'LIKE',
					],
				],
				'orderby'        => 'date',
				'order'          => 'DESC',
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

		// Accept person_ids array for multi-person support, fallback to URL person_id
		$person_ids = $request->get_param( 'person_ids' );
		if ( ! is_array( $person_ids ) || empty( $person_ids ) ) {
			// Single person_id from URL for backward compatibility
			$person_ids = $person_id ? [ $person_id ] : [];
		} else {
			$person_ids = array_map( 'intval', $person_ids );
		}

		if ( empty( $person_ids ) ) {
			return new \WP_Error( 'no_person', __( 'At least one person is required.', 'stadion' ), [ 'status' => 400 ] );
		}

		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', __( 'Todo content is required.', 'stadion' ), [ 'status' => 400 ] );
		}

		// Validate and determine post status
		$valid_statuses = [ 'open', 'awaiting', 'completed' ];
		if ( $status !== null && ! in_array( $status, $valid_statuses, true ) ) {
			return new \WP_Error( 'invalid_status', __( 'Invalid status. Use: open, awaiting, or completed.', 'stadion' ), [ 'status' => 400 ] );
		}
		$post_status = $status ? 'stadion_' . $status : 'stadion_open';

		// Create the todo post
		$post_id = wp_insert_post(
			[
				'post_type'   => 'stadion_todo',
				'post_title'  => $content,
				'post_status' => $post_status,
				'post_author' => get_current_user_id(),
			]
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create todo.', 'stadion' ), [ 'status' => 500 ] );
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
			'open'      => [ 'stadion_open' ],
			'awaiting'  => [ 'stadion_awaiting' ],
			'completed' => [ 'stadion_completed' ],
			'all'       => [ 'stadion_open', 'stadion_awaiting', 'stadion_completed' ],
		];

		$post_statuses = $status_map[ $status ] ?? [ 'stadion_open' ];

		// Build query args - access control filter will handle visibility
		$args = [
			'post_type'      => 'stadion_todo',
			'posts_per_page' => 100, // Reasonable limit
			'post_status'    => $post_statuses,
			'orderby'        => 'date',
			'order'          => 'DESC',
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

		if ( ! $todo || $todo->post_type !== 'stadion_todo' ) {
			return new \WP_Error( 'not_found', __( 'Todo not found.', 'stadion' ), [ 'status' => 404 ] );
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

		$todo = get_post( $todo_id );

		if ( ! $todo || $todo->post_type !== 'stadion_todo' ) {
			return new \WP_Error( 'not_found', __( 'Todo not found.', 'stadion' ), [ 'status' => 404 ] );
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
				return new \WP_Error( 'invalid_status', __( 'Invalid status. Use: open, awaiting, or completed.', 'stadion' ), [ 'status' => 400 ] );
			}

			$current_status  = $this->get_todo_status( $todo );
			$new_post_status = 'stadion_' . $status;

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
			'stadion_open'      => 'open',
			'stadion_awaiting'  => 'awaiting',
			'stadion_completed' => 'completed',
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

		if ( ! $todo || $todo->post_type !== 'stadion_todo' ) {
			return new \WP_Error( 'not_found', __( 'Todo not found.', 'stadion' ), [ 'status' => 404 ] );
		}

		$result = wp_delete_post( $todo_id, true ); // Force delete (bypass trash)

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete todo.', 'stadion' ), [ 'status' => 500 ] );
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
			'created'          => $post->post_date,
			'status'           => $status,
			'due_date'         => $due_date ?: null,
			'awaiting_since'   => $awaiting_since ?: null,
		];
	}
}
