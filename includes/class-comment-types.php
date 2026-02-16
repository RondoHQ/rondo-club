<?php
/**
 * Custom Comment Types for Notes and Activities
 */

namespace Rondo\Collaboration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommentTypes {

	/**
	 * Registered comment types
	 */
	const TYPE_NOTE             = 'rondo_note';
	const TYPE_ACTIVITY         = 'rondo_activity';
	const TYPE_EMAIL            = 'rondo_email';
	const TYPE_FEEDBACK_COMMENT = 'rondo_fb_comment';

	/**
	 * Comment type to string mapping
	 */
	private const TYPE_MAP = [
		self::TYPE_ACTIVITY => 'activity',
		self::TYPE_EMAIL    => 'email',
	];

	/**
	 * Post status to frontend status mapping
	 */
	private const STATUS_MAP = [
		'rondo_open'      => 'open',
		'rondo_awaiting'  => 'awaiting',
		'rondo_completed' => 'completed',
	];

	public function __construct() {
		// Register REST API routes for notes and activities
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Register comment meta on REST API init (when function is available)
		add_action( 'rest_api_init', [ $this, 'register_comment_meta' ] );

		// Exclude custom comment types from regular comment queries
		add_filter( 'pre_get_comments', [ $this, 'exclude_from_regular_queries' ] );
	}

	/**
	 * Register comment meta fields
	 */
	public function register_comment_meta() {
		// Activity-specific meta
		register_comment_meta(
			'comment',
			'activity_type',
			[
				'type'         => 'string',
				'description'  => 'Type of activity',
				'single'       => true,
				'show_in_rest' => true,
			]
		);

		register_comment_meta(
			'comment',
			'activity_date',
			[
				'type'         => 'string',
				'description'  => 'Date of the activity',
				'single'       => true,
				'show_in_rest' => true,
			]
		);

		register_comment_meta(
			'comment',
			'activity_time',
			[
				'type'         => 'string',
				'description'  => 'Time of the activity',
				'single'       => true,
				'show_in_rest' => true,
			]
		);

		register_comment_meta(
			'comment',
			'participants',
			[
				'type'         => 'array',
				'description'  => 'IDs of other people involved',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
			]
		);

		// Email-specific meta
		register_comment_meta(
			'comment',
			'email_template_type',
			[
				'type'         => 'string',
				'description'  => 'Email template type (new or renewal)',
				'single'       => true,
				'show_in_rest' => true,
			]
		);

		register_comment_meta(
			'comment',
			'email_recipient',
			[
				'type'         => 'string',
				'description'  => 'Email recipient address',
				'single'       => true,
				'show_in_rest' => true,
			]
		);

		register_comment_meta(
			'comment',
			'email_subject',
			[
				'type'         => 'string',
				'description'  => 'Email subject line',
				'single'       => true,
				'show_in_rest' => true,
			]
		);

		register_comment_meta(
			'comment',
			'email_content_snapshot',
			[
				'type'         => 'string',
				'description'  => 'Full rendered HTML content of the email',
				'single'       => true,
				'show_in_rest' => true,
			]
		);

		// Note visibility meta
		register_comment_meta(
			'comment',
			'_note_visibility',
			[
				'type'         => 'string',
				'description'  => 'Note visibility: private (only author) or shared (anyone who can see the contact)',
				'single'       => true,
				'show_in_rest' => true,
			]
		);
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		// Notes endpoints
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/notes',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_notes' ],
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
					'callback'            => [ $this, 'create_note' ],
					'permission_callback' => [ $this, 'check_person_access' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/notes/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_note' ],
					'permission_callback' => [ $this, 'check_comment_access' ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_note' ],
					'permission_callback' => [ $this, 'check_comment_access' ],
				],
			]
		);

		// Activities endpoints
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/activities',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_activities' ],
					'permission_callback' => [ $this, 'check_person_access' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_activity' ],
					'permission_callback' => [ $this, 'check_person_access' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/activities/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_activity' ],
					'permission_callback' => [ $this, 'check_comment_access' ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_note' ],
					'permission_callback' => [ $this, 'check_comment_access' ],
				],
			]
		);

		// Timeline endpoint (combined notes + activities)
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/timeline',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_timeline' ],
				'permission_callback' => [ $this, 'check_person_access' ],
			]
		);
	}

	/**
	 * Check if user can access the person
	 */
	public function check_person_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$person_id      = $request->get_param( 'person_id' );
		$access_control = new \Rondo\Core\AccessControl();

		return $access_control->user_can_access_post( $person_id );
	}

	/**
	 * Check if user can access/modify the comment
	 */
	public function check_comment_access( $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$comment_id = $request->get_param( 'id' );
		$comment    = get_comment( $comment_id );

		if ( ! $comment ) {
			return false;
		}

		// Check if user owns the comment or is admin
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return get_current_user_id() === (int) $comment->user_id;
	}

	/**
	 * Get notes for a person
	 */
	public function get_notes( $request ) {
		$person_id = $request->get_param( 'person_id' );

		$comments = get_comments(
			[
				'post_id' => $person_id,
				'type'    => self::TYPE_NOTE,
				'status'  => 'approve',
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			]
		);

		// Filter notes based on visibility
		$comments = $this->filter_notes_by_visibility( $comments );

		return rest_ensure_response( $this->format_comments( $comments, 'note' ) );
	}

	/**
	 * Create a note
	 */
	public function create_note( $request ) {
		$person_id = $request->get_param( 'person_id' );
		// Use wp_kses_post to allow safe HTML (bold, italic, lists, links, etc.)
		$content    = wp_kses_post( $request->get_param( 'content' ) );
		$visibility = $this->sanitize_visibility( $request->get_param( 'visibility' ) );

		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', __( 'Note content is required.', 'rondo' ), [ 'status' => 400 ] );
		}

		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $person_id,
				'comment_content'  => $content,
				'comment_type'     => self::TYPE_NOTE,
				'user_id'          => get_current_user_id(),
				'comment_approved' => 1,
			]
		);

		if ( ! $comment_id ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create note.', 'rondo' ), [ 'status' => 500 ] );
		}

		// Save visibility meta
		update_comment_meta( $comment_id, '_note_visibility', $visibility );

		// Parse and save @mentions, fire action if any mentions found
		$mentioned_ids = \RONDO_Mentions::save_mentions( $comment_id, $content );
		if ( ! empty( $mentioned_ids ) ) {
			do_action( 'rondo_user_mentioned', $comment_id, $mentioned_ids, get_current_user_id() );
		}

		$comment = get_comment( $comment_id );

		return rest_ensure_response( $this->format_comment( $comment, 'note' ) );
	}

	/**
	 * Update a note
	 */
	public function update_note( $request ) {
		$comment_id = $request->get_param( 'id' );
		// Use wp_kses_post to allow safe HTML (bold, italic, lists, links, etc.)
		$content    = wp_kses_post( $request->get_param( 'content' ) );
		$visibility = $request->get_param( 'visibility' );

		$result = wp_update_comment(
			[
				'comment_ID'      => $comment_id,
				'comment_content' => $content,
			]
		);

		// wp_update_comment returns false on failure, 0 if no changes, 1 if updated.
		if ( false === $result || is_wp_error( $result ) ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update note.', 'rondo' ), [ 'status' => 500 ] );
		}

		// Update visibility if provided.
		if ( null !== $visibility ) {
			update_comment_meta( $comment_id, '_note_visibility', $this->sanitize_visibility( $visibility ) );
		}

		// Update @mentions (check for new mentions to notify)
		$old_mentions   = \RONDO_Mentions::get_mentions( $comment_id );
		$new_mentions   = \RONDO_Mentions::save_mentions( $comment_id, $content );
		$added_mentions = array_diff( $new_mentions, $old_mentions );
		if ( ! empty( $added_mentions ) ) {
			do_action( 'rondo_user_mentioned', $comment_id, $added_mentions, get_current_user_id() );
		}

		$comment = get_comment( $comment_id );

		return rest_ensure_response( $this->format_comment( $comment, 'note' ) );
	}

	/**
	 * Delete a note
	 */
	public function delete_note( $request ) {
		$comment_id = $request->get_param( 'id' );

		$result = wp_delete_comment( $comment_id, true );

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete note.', 'rondo' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	/**
	 * Get activities for a person
	 */
	public function get_activities( $request ) {
		$person_id = $request->get_param( 'person_id' );

		$comments = get_comments(
			[
				'post_id' => $person_id,
				'type'    => self::TYPE_ACTIVITY,
				'status'  => 'approve',
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			]
		);

		return rest_ensure_response( $this->format_comments( $comments, 'activity' ) );
	}

	/**
	 * Create an activity
	 */
	public function create_activity( $request ) {
		$person_id = $request->get_param( 'person_id' );
		// Use wp_kses_post to allow safe HTML (bold, italic, lists, links, etc.)
		$content       = wp_kses_post( $request->get_param( 'content' ) );
		$activity_type = sanitize_text_field( $request->get_param( 'activity_type' ) );
		$activity_date = sanitize_text_field( $request->get_param( 'activity_date' ) );
		$activity_time = sanitize_text_field( $request->get_param( 'activity_time' ) );
		$participants  = $request->get_param( 'participants' ) ?: [];

		if ( empty( $content ) ) {
			return new \WP_Error( 'empty_content', __( 'Activity description is required.', 'rondo' ), [ 'status' => 400 ] );
		}

		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $person_id,
				'comment_content'  => $content,
				'comment_type'     => self::TYPE_ACTIVITY,
				'user_id'          => get_current_user_id(),
				'comment_approved' => 1,
			]
		);

		if ( ! $comment_id ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create activity.', 'rondo' ), [ 'status' => 500 ] );
		}

		// Save meta
		$this->update_meta_if_provided(
			$comment_id,
			[
				'activity_type' => $activity_type,
				'activity_date' => $activity_date,
				'activity_time' => $activity_time,
				'participants'  => ! empty( $participants ) ? array_map( 'intval', $participants ) : null,
			]
		);

		$comment = get_comment( $comment_id );

		return rest_ensure_response( $this->format_comment( $comment, 'activity' ) );
	}

	/**
	 * Update an activity
	 */
	public function update_activity( $request ) {
		$comment_id = $request->get_param( 'id' );
		// Use wp_kses_post to allow safe HTML (bold, italic, lists, links, etc.)
		$content       = wp_kses_post( $request->get_param( 'content' ) );
		$activity_type = sanitize_text_field( $request->get_param( 'activity_type' ) );
		$activity_date = sanitize_text_field( $request->get_param( 'activity_date' ) );
		$activity_time = sanitize_text_field( $request->get_param( 'activity_time' ) );
		$participants  = $request->get_param( 'participants' );

		$result = wp_update_comment(
			[
				'comment_ID'      => $comment_id,
				'comment_content' => $content,
			]
		);

		// wp_update_comment returns false on failure, 0 if no changes, 1 if updated.
		if ( false === $result || is_wp_error( $result ) ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update activity.', 'rondo' ), [ 'status' => 500 ] );
		}

		// Update meta.
		$this->update_meta_if_provided(
			$comment_id,
			[
				'activity_type' => $activity_type,
				'activity_date' => $activity_date,
				'activity_time' => $activity_time,
				'participants'  => null !== $participants ? array_map( 'intval', $participants ) : null,
			]
		);

		$comment = get_comment( $comment_id );

		return rest_ensure_response( $this->format_comment( $comment, 'activity' ) );
	}

	/**
	 * Get combined timeline (notes + activities + todos)
	 */
	public function get_timeline( $request ) {
		$person_id       = $request->get_param( 'person_id' );
		$current_user_id = get_current_user_id();

		$comments = get_comments(
			[
				'post_id'  => $person_id,
				'type__in' => [ self::TYPE_NOTE, self::TYPE_ACTIVITY, self::TYPE_EMAIL ],
				'status'   => 'approve',
				'orderby'  => 'comment_date',
				'order'    => 'DESC',
			]
		);

		$timeline = [];

		// Filter notes by visibility and format all comments
		$filtered_comments = $this->filter_notes_by_visibility( $comments );
		foreach ( $filtered_comments as $comment ) {
			$type       = self::TYPE_MAP[ $comment->comment_type ] ?? 'note';
			$timeline[] = $this->format_comment( $comment, $type );
		}

		// Also fetch todos from the rondo_todo CPT
		// Access control is automatic via RONDO_Access_Control hooks on WP_Query
		// Use LIKE query since ACF stores serialized arrays for related_persons
		$todos = get_posts(
			[
				'post_type'      => 'rondo_todo',
				'post_status'    => [ 'rondo_open', 'rondo_awaiting', 'rondo_completed' ],
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'     => 'related_persons',
						'value'   => sprintf( '"%d"', $person_id ),
						'compare' => 'LIKE',
					],
				],
			]
		);

		foreach ( $todos as $todo ) {
			// Get all related persons for this todo
			$related_person_ids = get_field( 'related_persons', $todo->ID ) ?: [];
			if ( ! is_array( $related_person_ids ) ) {
				$related_person_ids = $related_person_ids ? [ $related_person_ids ] : [];
			}

			// Build persons array with details
			$persons = [];
			foreach ( $related_person_ids as $pid ) {
				$persons[] = [
					'id'        => (int) $pid,
					'name'      => get_the_title( $pid ),
					'thumbnail' => get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: null,
				];
			}

			$timeline[] = [
				'id'             => $todo->ID,
				'type'           => 'todo',
				'content'        => $todo->post_title,
				'author_id'      => (int) $todo->post_author,
				'created'        => $todo->post_date,
				// Keep deprecated fields for backward compatibility
				'person_id'      => (int) $person_id,
				'person_name'    => get_the_title( $person_id ),
				// New multi-person format
				'persons'        => $persons,
				'notes'          => get_field( 'notes', $todo->ID ) ?: null,
				'status'         => self::STATUS_MAP[ $todo->post_status ] ?? 'open',
				'is_completed'   => 'rondo_completed' === $todo->post_status,
				'due_date'       => get_field( 'due_date', $todo->ID ) ?: null,
				'awaiting_since' => get_field( 'awaiting_since', $todo->ID ) ?: null,
			];
		}

		// Sort timeline by created date descending
		usort(
			$timeline,
			function ( $a, $b ) {
				return strtotime( $b['created'] ) - strtotime( $a['created'] );
			}
		);

		return rest_ensure_response( $timeline );
	}

	/**
	 * Format comments for REST response
	 */
	private function format_comments( $comments, $type ) {
		return array_map(
			function ( $comment ) use ( $type ) {
				return $this->format_comment( $comment, $type );
			},
			$comments
		);
	}

	/**
	 * Format a single comment for REST response
	 *
	 * @param WP_Comment $comment The comment object.
	 * @param string     $type    The type of comment ('note' or 'activity').
	 * @return array Formatted comment data.
	 */
	private function format_comment( $comment, $type ) {
		// Process content for activities and notes
		$content = ( 'activity' === $type || 'note' === $type )
			? $this->process_content_for_display( $comment->comment_content )
			: $comment->comment_content;

		$data = [
			'id'        => (int) $comment->comment_ID,
			'type'      => $type,
			'content'   => $content,
			'person_id' => (int) $comment->comment_post_ID,
			'author_id' => (int) $comment->user_id,
			'author'    => get_the_author_meta( 'display_name', $comment->user_id ),
			'created'   => $comment->comment_date,
			'modified'  => $comment->comment_date, // Comments don't track modified date
		];

		// Add activity-specific meta.
		if ( 'activity' === $type ) {
			$data['activity_type'] = get_comment_meta( $comment->comment_ID, 'activity_type', true );
			$data['activity_date'] = get_comment_meta( $comment->comment_ID, 'activity_date', true );
			$data['activity_time'] = get_comment_meta( $comment->comment_ID, 'activity_time', true );
			$data['participants']  = get_comment_meta( $comment->comment_ID, 'participants', true ) ?: [];
		}

		// Add note-specific meta (visibility).
		if ( 'note' === $type ) {
			$visibility = get_comment_meta( $comment->comment_ID, '_note_visibility', true );
			// Default to 'private' for backward compatibility with existing notes
			$data['visibility'] = $visibility ?: 'private';
		}

		// Add email-specific meta.
		if ( 'email' === $type ) {
			$data['email_template_type']   = get_comment_meta( $comment->comment_ID, 'email_template_type', true );
			$data['email_recipient']       = get_comment_meta( $comment->comment_ID, 'email_recipient', true );
			$data['email_subject']         = get_comment_meta( $comment->comment_ID, 'email_subject', true );
			$data['email_content_snapshot'] = get_comment_meta( $comment->comment_ID, 'email_content_snapshot', true );
		}

		return $data;
	}

	/**
	 * Filter notes based on visibility.
	 *
	 * - Author always sees their own notes
	 * - Shared notes are visible to anyone who can see the contact
	 * - Private notes are only visible to the author
	 * - Activities and emails are not filtered (always visible)
	 *
	 * @param array $comments Array of comment objects
	 * @return array Filtered array of comments
	 */
	private function filter_notes_by_visibility( $comments ) {
		$current_user_id = get_current_user_id();

		return array_filter(
			$comments,
			function ( $comment ) use ( $current_user_id ) {
				// Only filter notes, not activities or emails
				if ( self::TYPE_NOTE !== $comment->comment_type ) {
					return true;
				}

				// Author always sees their own notes
				if ( (int) $comment->user_id === $current_user_id ) {
					return true;
				}

				// Check visibility setting
				$visibility = get_comment_meta( $comment->comment_ID, '_note_visibility', true );

				// Default to private for backward compatibility
				if ( empty( $visibility ) ) {
					$visibility = 'private';
				}

				// Shared notes are visible to anyone who can see the contact.
				return 'shared' === $visibility;
			}
		);
	}

	/**
	 * Sanitize note visibility value
	 *
	 * @param mixed $visibility The visibility value to sanitize.
	 * @return string 'private' or 'shared', defaults to 'private' if invalid.
	 */
	private function sanitize_visibility( $visibility ) {
		$visibility = sanitize_text_field( $visibility );
		return in_array( $visibility, [ 'private', 'shared' ], true ) ? $visibility : 'private';
	}

	/**
	 * Update comment meta if value is provided (not null/empty)
	 *
	 * @param int   $comment_id The comment ID.
	 * @param array $meta_map   Associative array of meta_key => value pairs.
	 */
	private function update_meta_if_provided( $comment_id, $meta_map ) {
		foreach ( $meta_map as $key => $value ) {
			if ( null !== $value && '' !== $value ) {
				update_comment_meta( $comment_id, $key, $value );
			}
		}
	}

	/**
	 * Process content for display in timeline
	 *
	 * Renders @mentions, makes URLs clickable, and adds security attributes to links.
	 *
	 * @param string $content The raw content.
	 * @return string Processed content.
	 */
	private function process_content_for_display( $content ) {
		// Render @mentions as styled spans before URL processing
		$content = \RONDO_Mentions::render_mentions( $content );
		$content = make_clickable( $content );
		// Add target="_blank" and rel="noopener noreferrer" to links for security
		return str_replace( '<a href=', '<a target="_blank" rel="noopener noreferrer" href=', $content );
	}

	/**
	 * Exclude custom comment types from regular comment queries
	 */
	public function exclude_from_regular_queries( $query ) {
		// Only modify frontend queries and queries without explicit type
		// Check for 'type', 'type__in', and 'type__not_in' to avoid affecting our own queries
		if ( is_admin() ||
			! empty( $query->query_vars['type'] ) ||
			! empty( $query->query_vars['type__in'] ) ) {
			return;
		}

		// Ensure type__not_in is an array
		$existing_types = $query->query_vars['type__not_in'] ?? [];
		if ( ! is_array( $existing_types ) ) {
			$existing_types = [];
		}

		// Exclude our custom types from regular comment displays
		$query->query_vars['type__not_in'] = array_merge(
			$existing_types,
			[ self::TYPE_NOTE, self::TYPE_ACTIVITY, self::TYPE_EMAIL, self::TYPE_FEEDBACK_COMMENT ]
		);
	}

	/**
	 * Create an email log comment
	 *
	 * @param int   $person_id Person post ID.
	 * @param array $data      Email data (template_type, recipient, subject, content).
	 * @return int|WP_Error Comment ID on success, WP_Error on failure.
	 */
	public function create_email_log( $person_id, $data ) {
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $person_id,
				'comment_content'  => $data['subject'], // Summary for display
				'comment_type'     => self::TYPE_EMAIL,
				'user_id'          => get_current_user_id(),
				'comment_approved' => 1,
			]
		);

		if ( ! $comment_id ) {
			return new \WP_Error( 'create_failed', 'Failed to log email.' );
		}

		update_comment_meta( $comment_id, 'email_template_type', $data['template_type'] );
		update_comment_meta( $comment_id, 'email_recipient', $data['recipient'] );
		update_comment_meta( $comment_id, 'email_subject', $data['subject'] );
		update_comment_meta( $comment_id, 'email_content_snapshot', $data['content'] );

		return $comment_id;
	}
}
