<?php
/**
 * Access Control for Stadion CRM
 *
 * All approved users can see all data. Unapproved users see nothing.
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

	public function __construct() {
		// Filter queries to block unapproved users
		add_action( 'pre_get_posts', [ $this, 'filter_queries' ] );

		// Filter REST API queries
		add_filter( 'rest_person_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'rest_company_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'rest_important_date_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'rest_stadion_todo_query', [ $this, 'filter_rest_query' ], 10, 2 );

		// Filter REST API single item access
		add_filter( 'rest_prepare_person', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_company', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_important_date', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_stadion_todo', [ $this, 'filter_rest_single_access' ], 10, 3 );
	}

	/**
	 * Check if a user is approved and can access data
	 *
	 * All approved users can see all data. Admins are always approved.
	 *
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return bool Whether the user can access data.
	 */
	public function is_user_approved( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		// Admins are always approved
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		return \STADION_User_Roles::is_user_approved( $user_id );
	}

	/**
	 * Check if a user can access a post
	 *
	 * All approved users can access all posts. Only trashed posts are hidden.
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return bool Whether the user can access the post.
	 */
	public function user_can_access_post( $post_id, $user_id = null ) {
		if ( ! $this->is_user_approved( $user_id ) ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		// Not a controlled post type - allow access
		if ( ! in_array( $post->post_type, $this->controlled_post_types ) ) {
			return true;
		}

		// Don't allow access to trashed posts
		if ( $post->post_status === 'trash' ) {
			return false;
		}

		return true;
	}

	/**
	 * Get user's permission level for a post
	 *
	 * @param int      $post_id Post ID.
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return string|false 'owner' if user is the author, 'editor' otherwise, or false if no access.
	 */
	public function get_user_permission( $post_id, $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		if ( ! $this->user_can_access_post( $post_id, $user_id ) ) {
			return false;
		}

		$post = get_post( $post_id );

		// Owner - user created this post
		if ( (int) $post->post_author === (int) $user_id ) {
			return 'owner';
		}

		// All other approved users are editors
		return 'editor';
	}

	/**
	 * Filter WP_Query to block unapproved users
	 *
	 * Approved users see all posts. Unapproved users see nothing.
	 */
	public function filter_queries( $query ) {
		// Respect suppress_filters flag
		if ( $query->get( 'suppress_filters' ) ) {
			return;
		}

		// Only filter our controlled post types
		$post_type = $query->get( 'post_type' );

		if ( ! $post_type || ! in_array( $post_type, $this->controlled_post_types ) ) {
			return;
		}

		// Check if user is approved
		if ( ! $this->is_user_approved() ) {
			// Unapproved or not logged in - show nothing
			$query->set( 'post__in', [ 0 ] );
		}

		// Approved users see all posts - no filtering needed
	}

	/**
	 * Filter REST API queries
	 *
	 * Approved users see all posts. Unapproved users see nothing.
	 */
	public function filter_rest_query( $args, $request ) {
		if ( ! $this->is_user_approved() ) {
			// Unapproved or not logged in - show nothing
			$args['post__in'] = [ 0 ];
		}

		// Approved users see all posts - no filtering needed
		return $args;
	}

	/**
	 * Filter REST API single item access
	 */
	public function filter_rest_single_access( $response, $post, $request ) {
		$user_id = get_current_user_id();

		// Check if user is approved
		if ( ! $this->is_user_approved( $user_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'Your account is pending approval. Please contact an administrator.', 'stadion' ),
				[ 'status' => 403 ]
			);
		}

		// Don't allow access to trashed posts
		if ( $post->post_status === 'trash' ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'This item has been deleted.', 'stadion' ),
				[ 'status' => 404 ]
			);
		}

		return $response;
	}
}
