<?php
/**
 * Access Control for Personal CRM
 *
 * Users can only see posts they created themselves.
 */

namespace Stadion\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccessControl {

	/**
	 * Post types that should have access control
	 */
	private $controlled_post_types = [ 'person', 'team', 'important_date', 'stadion_todo' ];

	/**
	 * Check if we're on the frontend (not admin area)
	 */
	private function is_frontend() {
		return ! is_admin();
	}

	public function __construct() {
		// Filter queries to only show accessible posts
		add_action( 'pre_get_posts', [ $this, 'filter_queries' ] );

		// Filter REST API queries
		add_filter( 'rest_person_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'rest_company_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'rest_important_date_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'rest_stadion_todo_query', [ $this, 'filter_rest_query' ], 10, 2 );

		// Check single post access
		add_filter( 'the_posts', [ $this, 'filter_single_post_access' ], 10, 2 );

		// Filter REST API single item access
		add_filter( 'rest_prepare_person', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_company', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_important_date', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_stadion_todo', [ $this, 'filter_rest_single_access' ], 10, 3 );
	}

	/**
	 * Check if a user can access a post
	 *
	 * Simple author-based access control:
	 * User can access a post if they are the author.
	 */
	public function user_can_access_post( $post_id, $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		// Check if user is approved (admins are always approved)
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			if ( ! \STADION_User_Roles::is_user_approved( $user_id ) ) {
				return false;
			}
		}

		$post = get_post( $post_id );

		if ( ! $post || ! in_array( $post->post_type, $this->controlled_post_types ) ) {
			return true; // Not a controlled post type
		}

		// Don't allow access to trashed posts
		if ( $post->post_status === 'trash' ) {
			return false;
		}

		// Check if user is the author
		if ( (int) $post->post_author === (int) $user_id ) {
			return true;
		}

		// Admins in admin area have full access
		if ( user_can( $user_id, 'manage_options' ) && ! $this->is_frontend() ) {
			return true;
		}

		return false;
	}

	/**
	 * Get user's permission level for a post
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID (optional, defaults to current user).
	 * @return string|false 'owner' or false.
	 */
	public function get_user_permission( $post_id, $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		if ( ! $this->user_can_access_post( $post_id, $user_id ) ) {
			return false;
		}

		$post = get_post( $post_id );

		// Owner
		if ( (int) $post->post_author === (int) $user_id ) {
			return 'owner';
		}

		return false;
	}

	/**
	 * Filter WP_Query to only show accessible posts
	 */
	public function filter_queries( $query ) {
		// Don't filter admin queries for admins
		if ( is_admin() && current_user_can( 'manage_options' ) ) {
			return;
		}

		// Only filter our controlled post types
		$post_type = $query->get( 'post_type' );

		if ( ! $post_type || ! in_array( $post_type, $this->controlled_post_types ) ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			// Not logged in - show nothing
			$query->set( 'post__in', [ 0 ] );
			return;
		}

		// Check if user is approved (admins are always approved)
		if ( ! current_user_can( 'manage_options' ) ) {
			if ( ! \STADION_User_Roles::is_user_approved( $user_id ) ) {
				// Unapproved user - show nothing
				$query->set( 'post__in', [ 0 ] );
				return;
			}
		}

		// On frontend, admins are also restricted
		// Only skip filtering for admins in admin area
		if ( current_user_can( 'manage_options' ) && is_admin() ) {
			return;
		}

		// Get IDs of posts authored by user
		$accessible_ids = $this->get_accessible_post_ids( $post_type, $user_id );

		if ( empty( $accessible_ids ) ) {
			$query->set( 'post__in', [ 0 ] ); // No accessible posts
		} else {
			$query->set( 'post__in', $accessible_ids );
		}
	}

	/**
	 * Get all post IDs accessible by a user
	 *
	 * Simple author-based access: returns posts where user is the author.
	 */
	public function get_accessible_post_ids( $post_type, $user_id ) {
		global $wpdb;

		// Determine valid post statuses based on post type
		$valid_statuses = [ 'publish' ];
		if ( $post_type === 'stadion_todo' ) {
			$valid_statuses = [ 'stadion_open', 'stadion_awaiting', 'stadion_completed' ];
		}
		$status_placeholders = implode( ',', array_fill( 0, count( $valid_statuses ), '%s' ) );

		// Posts authored by user
		$authored_params = array_merge( [ $post_type ], $valid_statuses, [ $user_id ] );
		$authored        = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s
             AND post_status IN ($status_placeholders)
             AND post_author = %d",
				$authored_params
			)
		);

		return $authored;
	}

	/**
	 * Filter REST API queries
	 */
	public function filter_rest_query( $args, $request ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			$args['post__in'] = [ 0 ];
			return $args;
		}

		// Check if user is approved (admins are always approved)
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			if ( ! \STADION_User_Roles::is_user_approved( $user_id ) ) {
				// Unapproved user - show nothing
				$args['post__in'] = [ 0 ];
				return $args;
			}
		}

		// REST API calls are typically from the frontend React app
		// Admins should be restricted on the frontend, so we filter REST API calls for admins too
		// The admin area uses WP_Query directly, not REST API

		$post_type      = $args['post_type'] ?? '';
		$accessible_ids = $this->get_accessible_post_ids( $post_type, $user_id );

		if ( empty( $accessible_ids ) ) {
			$args['post__in'] = [ 0 ];
		} else {
			$args['post__in'] = $accessible_ids;
		}

		return $args;
	}

	/**
	 * Filter single post access in queries
	 */
	public function filter_single_post_access( $posts, $query ) {
		if ( empty( $posts ) ) {
			return $posts;
		}

		$user_id = get_current_user_id();

		// On frontend, admins are also restricted
		// Only skip filtering for admins in admin area
		if ( current_user_can( 'manage_options' ) && is_admin() ) {
			return $posts;
		}

		$filtered = [];

		foreach ( $posts as $post ) {
			if ( ! in_array( $post->post_type, $this->controlled_post_types ) ) {
				$filtered[] = $post;
				continue;
			}

			if ( $this->user_can_access_post( $post->ID, $user_id ) ) {
				$filtered[] = $post;
			}
		}

		return $filtered;
	}

	/**
	 * Filter REST API single item access
	 */
	public function filter_rest_single_access( $response, $post, $request ) {
		$user_id = get_current_user_id();

		// Check if user is approved (admins are always approved)
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			if ( ! \STADION_User_Roles::is_user_approved( $user_id ) ) {
				return new \WP_Error(
					'rest_forbidden',
					__( 'Your account is pending approval. Please contact an administrator.', 'stadion' ),
					[ 'status' => 403 ]
				);
			}
		}

		// Don't allow access to trashed posts
		if ( $post->post_status === 'trash' ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'This item has been deleted.', 'stadion' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! $this->user_can_access_post( $post->ID, $user_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this item.', 'stadion' ),
				[ 'status' => 403 ]
			);
		}

		return $response;
	}
}
