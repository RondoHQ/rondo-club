<?php
/**
 * REST API Endpoints for Feedback Custom Post Type
 *
 * Provides CRUD operations for feedback (bug reports and feature requests)
 * via the REST API at rondo/v1/feedback.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Feedback extends Base {

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
		// List feedback
		register_rest_route(
			'rondo/v1',
			'/feedback',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_feedback_list' ],
					'permission_callback' => function () {
						return is_user_logged_in();
					},
					'args'                => $this->get_list_args(),
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_feedback' ],
					'permission_callback' => function () {
						return is_user_logged_in();
					},
				],
			]
		);

		// Feedback comments (conversation thread)
		register_rest_route(
			'rondo/v1',
			'/feedback/(?P<id>\d+)/comments',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_feedback_comments' ],
					'permission_callback' => [ $this, 'check_feedback_access' ],
					'args'                => [
						'id' => [
							'validate_callback' => function ( $param ) {
								return is_numeric( $param );
							},
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_feedback_comment' ],
					'permission_callback' => [ $this, 'check_feedback_access' ],
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

		// Single feedback operations
		register_rest_route(
			'rondo/v1',
			'/feedback/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_feedback' ],
					'permission_callback' => [ $this, 'check_feedback_access' ],
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
					'callback'            => [ $this, 'update_feedback' ],
					'permission_callback' => [ $this, 'check_feedback_access' ],
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
					'callback'            => [ $this, 'delete_feedback' ],
					'permission_callback' => [ $this, 'check_feedback_access' ],
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
	 * Get list endpoint query parameter arguments
	 *
	 * @return array Query parameter definitions.
	 */
	/**
	 * Allowed project values.
	 */
	private const ALLOWED_PROJECTS = [ 'rondo-club', 'rondo-sync', 'website' ];

	private function get_list_args() {
		return [
			'type'     => [
				'default'           => '',
				'validate_callback' => function ( $param ) {
					return empty( $param ) || in_array( $param, [ 'bug', 'feature_request' ], true );
				},
			],
			'project'  => [
				'default'           => '',
				'validate_callback' => function ( $param ) {
					return empty( $param ) || in_array( $param, self::ALLOWED_PROJECTS, true );
				},
			],
			'status'   => [
				'default'           => '',
				'validate_callback' => function ( $param ) {
					return empty( $param ) || in_array( $param, [ 'new', 'approved', 'in_progress', 'in_review', 'resolved', 'declined', 'needs_info', 'open' ], true );
				},
			],
			'priority' => [
				'default'           => '',
				'validate_callback' => function ( $param ) {
					return empty( $param ) || in_array( $param, [ 'low', 'medium', 'high', 'critical' ], true );
				},
			],
			'per_page' => [
				'default'           => 10,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param >= 1 && $param <= 100;
				},
				'sanitize_callback' => 'absint',
			],
			'page'     => [
				'default'           => 1,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param >= 1;
				},
				'sanitize_callback' => 'absint',
			],
			'orderby'  => [
				'default'           => 'date',
				'validate_callback' => function ( $param ) {
					return in_array( $param, [ 'date', 'title', 'priority', 'status' ], true );
				},
			],
			'order'    => [
				'default'           => 'desc',
				'validate_callback' => function ( $param ) {
					return in_array( strtolower( $param ), [ 'asc', 'desc' ], true );
				},
			],
		];
	}

	/**
	 * Check if user can access a feedback post
	 *
	 * Permission callback for single-feedback operations.
	 * All logged-in users can view all feedback.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return bool True if user can access the feedback, false otherwise.
	 */
	public function check_feedback_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$feedback_id = $request->get_param( 'id' );
		$feedback    = get_post( $feedback_id );

		// Return false for non-existent posts
		if ( ! $feedback || $feedback->post_type !== 'rondo_feedback' ) {
			return false;
		}

		return true;
	}

	/**
	 * Get list of feedback
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response containing feedback list with pagination headers.
	 */
	public function get_feedback_list( $request ) {
		// Build query args — all logged-in users can see all feedback
		$args = [
			'post_type'      => 'rondo_feedback',
			'post_status'    => 'publish',
			'posts_per_page' => $request->get_param( 'per_page' ),
			'paged'          => $request->get_param( 'page' ),
		];

		// Add meta query filters
		$meta_query = [];

		$type = $request->get_param( 'type' );
		if ( ! empty( $type ) ) {
			$meta_query[] = [
				'key'     => 'feedback_type',
				'value'   => $type,
				'compare' => '=',
			];
		}

		$status = $request->get_param( 'status' );
		if ( ! empty( $status ) ) {
			if ( $status === 'open' ) {
				// "open" is a pseudo-status that matches everything except resolved and declined
				$meta_query[] = [
					'key'     => 'status',
					'value'   => [ 'resolved', 'declined' ],
					'compare' => 'NOT IN',
				];
			} else {
				$meta_query[] = [
					'key'     => 'status',
					'value'   => $status,
					'compare' => '=',
				];
			}
		}

		$priority = $request->get_param( 'priority' );
		if ( ! empty( $priority ) ) {
			$meta_query[] = [
				'key'     => 'priority',
				'value'   => $priority,
				'compare' => '=',
			];
		}

		$project = $request->get_param( 'project' );
		if ( ! empty( $project ) ) {
			$meta_query[] = [
				'key'     => '_feedback_project',
				'value'   => $project,
				'compare' => '=',
			];
		}

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		// Handle ordering
		$orderby = $request->get_param( 'orderby' );
		$order   = strtoupper( $request->get_param( 'order' ) );

		if ( in_array( $orderby, [ 'priority', 'status' ], true ) ) {
			// For meta field ordering
			$args['meta_key'] = $orderby;
			$args['orderby']  = 'meta_value';
			$args['order']    = $order;
		} else {
			$args['orderby'] = $orderby;
			$args['order']   = $order;
		}

		// Execute query
		$query    = new \WP_Query( $args );
		$feedback = array_map( [ $this, 'format_feedback' ], $query->posts );

		// Build response with pagination headers
		$response = rest_ensure_response( $feedback );
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	/**
	 * Create new feedback
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing created feedback or error.
	 */
	public function create_feedback( $request ) {
		$title         = $request->get_param( 'title' );
		$feedback_type = $request->get_param( 'feedback_type' );

		// Validate required fields
		if ( empty( $title ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'Title is required.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'title' => 'Title is required' ] ]
			);
		}

		if ( empty( $feedback_type ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'Feedback type is required.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'feedback_type' => 'Feedback type is required' ] ]
			);
		}

		// Validate feedback_type value
		if ( ! in_array( $feedback_type, [ 'bug', 'feature_request' ], true ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Invalid feedback type.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'feedback_type' => 'Must be "bug" or "feature_request"' ] ]
			);
		}

		// Create the post
		$post_id = wp_insert_post(
			[
				'post_type'    => 'rondo_feedback',
				'post_title'   => sanitize_text_field( $title ),
				'post_content' => wp_kses_post( $request->get_param( 'content' ) ?? '' ),
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			]
		);

		if ( is_wp_error( $post_id ) ) {
			return new \WP_Error(
				'rest_cannot_create',
				__( 'Failed to create feedback.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		// Save ACF fields
		update_field( 'feedback_type', $feedback_type, $post_id );

		// Save project (post meta, defaults to rondo-club)
		$project = $request->get_param( 'project' );
		if ( ! empty( $project ) && in_array( $project, self::ALLOWED_PROJECTS, true ) ) {
			update_post_meta( $post_id, '_feedback_project', $project );
		} else {
			update_post_meta( $post_id, '_feedback_project', 'rondo-club' );
		}

		// Determine default status: admins get 'approved', regular users get 'new'
		$default_status = current_user_can( 'manage_options' ) ? 'approved' : 'new';
		update_field( 'status', $request->get_param( 'status' ) ?? $default_status, $post_id );
		update_field( 'priority', $request->get_param( 'priority' ) ?? 'medium', $post_id );

		// Optional context fields
		$browser_info = $request->get_param( 'browser_info' );
		if ( ! empty( $browser_info ) ) {
			update_field( 'browser_info', sanitize_text_field( $browser_info ), $post_id );
		}

		$app_version = $request->get_param( 'app_version' );
		if ( ! empty( $app_version ) ) {
			update_field( 'app_version', sanitize_text_field( $app_version ), $post_id );
		}

		$url_context = $request->get_param( 'url_context' );
		if ( ! empty( $url_context ) ) {
			update_field( 'url_context', esc_url_raw( $url_context ), $post_id );
		}

		// Bug-specific fields
		if ( $feedback_type === 'bug' ) {
			$steps_to_reproduce = $request->get_param( 'steps_to_reproduce' );
			if ( ! empty( $steps_to_reproduce ) ) {
				update_field( 'steps_to_reproduce', sanitize_textarea_field( $steps_to_reproduce ), $post_id );
			}

			$expected_behavior = $request->get_param( 'expected_behavior' );
			if ( ! empty( $expected_behavior ) ) {
				update_field( 'expected_behavior', sanitize_textarea_field( $expected_behavior ), $post_id );
			}

			$actual_behavior = $request->get_param( 'actual_behavior' );
			if ( ! empty( $actual_behavior ) ) {
				update_field( 'actual_behavior', sanitize_textarea_field( $actual_behavior ), $post_id );
			}
		}

		// Feature-specific fields
		if ( $feedback_type === 'feature_request' ) {
			$use_case = $request->get_param( 'use_case' );
			if ( ! empty( $use_case ) ) {
				update_field( 'use_case', sanitize_textarea_field( $use_case ), $post_id );
			}
		}

		// Return formatted feedback
		$post = get_post( $post_id );
		return rest_ensure_response( $this->format_feedback( $post ) );
	}

	/**
	 * Get a single feedback item
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing feedback or error.
	 */
	public function get_feedback( $request ) {
		$feedback_id = (int) $request->get_param( 'id' );
		$feedback    = get_post( $feedback_id );

		if ( ! $feedback || $feedback->post_type !== 'rondo_feedback' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Feedback not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		return rest_ensure_response( $this->format_feedback( $feedback ) );
	}

	/**
	 * Update a feedback item
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing updated feedback or error.
	 */
	public function update_feedback( $request ) {
		$feedback_id = (int) $request->get_param( 'id' );
		$feedback    = get_post( $feedback_id );

		if ( ! $feedback || $feedback->post_type !== 'rondo_feedback' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Feedback not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$is_admin = current_user_can( 'manage_options' );

		// Check field-level permissions for status and priority
		$new_status = $request->get_param( 'status' );
		if ( $new_status !== null && ! $is_admin ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Only administrators can change feedback status.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		$new_priority = $request->get_param( 'priority' );
		if ( $new_priority !== null && ! $is_admin ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Only administrators can change feedback priority.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		// Validate field values
		$feedback_type = $request->get_param( 'feedback_type' );
		if ( $feedback_type !== null && ! in_array( $feedback_type, [ 'bug', 'feature_request' ], true ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Invalid feedback type.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'feedback_type' => 'Must be "bug" or "feature_request"' ] ]
			);
		}

		if ( $new_status !== null && ! in_array( $new_status, [ 'new', 'approved', 'in_progress', 'in_review', 'resolved', 'declined', 'needs_info' ], true ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Invalid status.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'status' => 'Must be "new", "approved", "in_progress", "in_review", "resolved", "declined", or "needs_info"' ] ]
			);
		}

		if ( $new_priority !== null && ! in_array( $new_priority, [ 'low', 'medium', 'high', 'critical' ], true ) ) {
			return new \WP_Error(
				'rest_invalid_param',
				__( 'Invalid priority.', 'rondo' ),
				[ 'status' => 400, 'params' => [ 'priority' => 'Must be "low", "medium", "high", or "critical"' ] ]
			);
		}

		// Update post fields if provided
		$update_args = [ 'ID' => $feedback_id ];

		$title = $request->get_param( 'title' );
		if ( $title !== null ) {
			$update_args['post_title'] = sanitize_text_field( $title );
		}

		$content = $request->get_param( 'content' );
		if ( $content !== null ) {
			$update_args['post_content'] = wp_kses_post( $content );
		}

		if ( count( $update_args ) > 1 ) {
			wp_update_post( $update_args );
		}

		// Update ACF fields
		if ( $feedback_type !== null ) {
			update_field( 'feedback_type', $feedback_type, $feedback_id );
		}

		if ( $new_status !== null ) {
			update_field( 'status', $new_status, $feedback_id );
		}

		if ( $new_priority !== null ) {
			update_field( 'priority', $new_priority, $feedback_id );
		}

		// Optional context fields
		$browser_info = $request->get_param( 'browser_info' );
		if ( $browser_info !== null ) {
			update_field( 'browser_info', sanitize_text_field( $browser_info ), $feedback_id );
		}

		$app_version = $request->get_param( 'app_version' );
		if ( $app_version !== null ) {
			update_field( 'app_version', sanitize_text_field( $app_version ), $feedback_id );
		}

		$url_context = $request->get_param( 'url_context' );
		if ( $url_context !== null ) {
			update_field( 'url_context', esc_url_raw( $url_context ), $feedback_id );
		}

		// Bug-specific fields
		$steps_to_reproduce = $request->get_param( 'steps_to_reproduce' );
		if ( $steps_to_reproduce !== null ) {
			update_field( 'steps_to_reproduce', sanitize_textarea_field( $steps_to_reproduce ), $feedback_id );
		}

		$expected_behavior = $request->get_param( 'expected_behavior' );
		if ( $expected_behavior !== null ) {
			update_field( 'expected_behavior', sanitize_textarea_field( $expected_behavior ), $feedback_id );
		}

		$actual_behavior = $request->get_param( 'actual_behavior' );
		if ( $actual_behavior !== null ) {
			update_field( 'actual_behavior', sanitize_textarea_field( $actual_behavior ), $feedback_id );
		}

		// Feature-specific fields
		$use_case = $request->get_param( 'use_case' );
		if ( $use_case !== null ) {
			update_field( 'use_case', sanitize_textarea_field( $use_case ), $feedback_id );
		}

		// Project meta
		$project = $request->get_param( 'project' );
		if ( $project !== null ) {
			if ( in_array( $project, self::ALLOWED_PROJECTS, true ) ) {
				update_post_meta( $feedback_id, '_feedback_project', $project );
			} else {
				return new \WP_Error(
					'rest_invalid_param',
					__( 'Invalid project.', 'rondo' ),
					[ 'status' => 400, 'params' => [ 'project' => 'Must be "rondo-club", "rondo-sync", or "website"' ] ]
				);
			}
		}

		// Agent meta fields (pr_url, agent_branch)
		$pr_url = $request->get_param( 'pr_url' );
		if ( $pr_url !== null ) {
			update_post_meta( $feedback_id, '_feedback_pr_url', esc_url_raw( $pr_url ) );
		}

		$agent_branch = $request->get_param( 'agent_branch' );
		if ( $agent_branch !== null ) {
			update_post_meta( $feedback_id, '_feedback_agent_branch', sanitize_text_field( $agent_branch ) );
		}

		$agent_plan = $request->get_param( 'agent_plan' );
		if ( $agent_plan !== null ) {
			update_post_meta( $feedback_id, '_feedback_agent_plan', wp_kses_post( $agent_plan ) );
		}

		// Return formatted updated feedback
		$feedback = get_post( $feedback_id );
		return rest_ensure_response( $this->format_feedback( $feedback ) );
	}

	/**
	 * Delete a feedback item
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response indicating success or error.
	 */
	public function delete_feedback( $request ) {
		$feedback_id = (int) $request->get_param( 'id' );
		$feedback    = get_post( $feedback_id );

		if ( ! $feedback || $feedback->post_type !== 'rondo_feedback' ) {
			return new \WP_Error(
				'rest_not_found',
				__( 'Feedback not found.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		$result = wp_delete_post( $feedback_id, true ); // Force delete (bypass trash)

		if ( ! $result ) {
			return new \WP_Error(
				'rest_cannot_delete',
				__( 'Failed to delete feedback.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( [ 'deleted' => true, 'id' => $feedback_id ] );
	}

	/**
	 * Get comments for a feedback item
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response containing feedback comments.
	 */
	public function get_feedback_comments( $request ) {
		$feedback_id = (int) $request->get_param( 'id' );

		$comments = get_comments(
			[
				'post_id' => $feedback_id,
				'type'    => 'rondo_fb_comment',
				'status'  => 'approve',
				'orderby' => 'comment_date',
				'order'   => 'ASC',
			]
		);

		$formatted = array_map( [ $this, 'format_feedback_comment' ], $comments );

		return rest_ensure_response( $formatted );
	}

	/**
	 * Create a comment on a feedback item
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response containing created comment or error.
	 */
	public function create_feedback_comment( $request ) {
		$feedback_id = (int) $request->get_param( 'id' );
		$content     = wp_kses_post( $request->get_param( 'content' ) );
		$author_type = sanitize_text_field( $request->get_param( 'author_type' ) ?: 'user' );

		if ( empty( $content ) ) {
			return new \WP_Error(
				'rest_missing_param',
				__( 'Comment content is required.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		if ( ! in_array( $author_type, [ 'user', 'agent' ], true ) ) {
			$author_type = 'user';
		}

		$user_id      = get_current_user_id();
		$comment_data = [
			'comment_post_ID'  => $feedback_id,
			'comment_content'  => $content,
			'comment_type'     => 'rondo_fb_comment',
			'user_id'          => $user_id,
			'comment_approved' => 1,
		];

		// Fill in author fields from user data to satisfy WordPress validation.
		if ( $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$comment_data['comment_author']       = $user->display_name;
				$comment_data['comment_author_email'] = $user->user_email;
			}
		}

		$comment_id = wp_insert_comment( $comment_data );

		if ( ! $comment_id ) {
			global $wpdb;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Rondo: Failed to insert feedback comment. DB error: ' . $wpdb->last_error . ' | Data: ' . wp_json_encode( $comment_data ) );
			return new \WP_Error(
				'rest_cannot_create',
				__( 'Failed to create comment.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		update_comment_meta( $comment_id, '_author_type', $author_type );

		// When a user replies to needs_info feedback, require re-approval
		if ( $author_type === 'user' ) {
			$current_status = get_field( 'status', $feedback_id );
			if ( $current_status === 'needs_info' ) {
				update_field( 'status', 'new', $feedback_id );
			}
		}

		$comment = get_comment( $comment_id );

		return rest_ensure_response( $this->format_feedback_comment( $comment ) );
	}

	/**
	 * Format a feedback comment for REST response
	 *
	 * @param \WP_Comment $comment The comment object.
	 * @return array Formatted comment data.
	 */
	private function format_feedback_comment( $comment ) {
		$author_type = get_comment_meta( $comment->comment_ID, '_author_type', true ) ?: 'user';

		return [
			'id'          => (int) $comment->comment_ID,
			'content'     => $comment->comment_content,
			'author_id'   => (int) $comment->user_id,
			'author_name' => get_the_author_meta( 'display_name', $comment->user_id ),
			'author_type' => $author_type,
			'created'     => $comment->comment_date,
		];
	}

	/**
	 * Format a feedback post for REST response
	 *
	 * @param \WP_Post $post The feedback post object.
	 * @return array Formatted feedback data.
	 */
	private function format_feedback( $post ) {
		$author = get_user_by( 'id', $post->post_author );

		return [
			'id'       => $post->ID,
			'title'    => $this->sanitize_text( $post->post_title ),
			'content'  => $this->sanitize_rich_content( $post->post_content ),
			'author'   => [
				'id'    => $author ? (int) $author->ID : 0,
				'name'  => $author ? $this->sanitize_text( $author->display_name ) : '',
				'email' => $author ? sanitize_email( $author->user_email ) : '',
			],
			'date'     => $post->post_date_gmt,
			'modified' => $post->post_modified_gmt,
			'meta'     => [
				'feedback_type'      => get_field( 'feedback_type', $post->ID ) ?: '',
				'status'             => get_field( 'status', $post->ID ) ?: 'new',
				'priority'           => get_field( 'priority', $post->ID ) ?: 'medium',
				'browser_info'       => $this->sanitize_text( get_field( 'browser_info', $post->ID ) ?: '' ),
				'app_version'        => $this->sanitize_text( get_field( 'app_version', $post->ID ) ?: '' ),
				'url_context'        => $this->sanitize_url( get_field( 'url_context', $post->ID ) ?: '' ),
				'steps_to_reproduce' => $this->sanitize_text( get_field( 'steps_to_reproduce', $post->ID ) ?: '' ),
				'expected_behavior'  => $this->sanitize_text( get_field( 'expected_behavior', $post->ID ) ?: '' ),
				'actual_behavior'    => $this->sanitize_text( get_field( 'actual_behavior', $post->ID ) ?: '' ),
				'use_case'           => $this->sanitize_text( get_field( 'use_case', $post->ID ) ?: '' ),
				'project'            => $this->sanitize_text( get_post_meta( $post->ID, '_feedback_project', true ) ?: 'rondo-club' ),
				'pr_url'             => $this->sanitize_url( get_post_meta( $post->ID, '_feedback_pr_url', true ) ?: '' ),
				'agent_branch'       => $this->sanitize_text( get_post_meta( $post->ID, '_feedback_agent_branch', true ) ?: '' ),
				'agent_plan'         => get_post_meta( $post->ID, '_feedback_agent_plan', true ) ?: '',
			],
		];
	}
}
