<?php
/**
 * Access Control for Rondo CRM
 *
 * All logged-in users can see all data.
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccessControl {

	/**
	 * Post types that should have access control
	 */
	private $controlled_post_types = [ 'person', 'team', 'rondo_todo' ];

	public function __construct() {
		// Filter queries to block unapproved users
		add_action( 'pre_get_posts', [ $this, 'filter_queries' ] );

		// Filter REST API queries
		add_filter( 'rest_person_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'rest_company_query', [ $this, 'filter_rest_query' ], 10, 2 );
		add_filter( 'rest_rondo_todo_query', [ $this, 'filter_rest_query' ], 10, 2 );

		// Filter REST API single item access
		add_filter( 'rest_prepare_person', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_company', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_rondo_todo', [ $this, 'filter_rest_single_access' ], 10, 3 );
	}

	/**
	 * Check if a user is logged in and can access data
	 *
	 * All logged-in users can access data.
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

		// All logged-in users can access data
		return true;
	}

	/**
	 * Check if user should only see volunteers (VOG-only users)
	 *
	 * VOG-only users (users with VOG capability but not Fair Play) should only
	 * see people who are current volunteers (huidig-vrijwilliger=1).
	 *
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return bool Whether the user should only see volunteers.
	 */
	public function should_filter_volunteers_only( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		// Admins see everything
		if ( user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		// Check capabilities
		$has_vog      = user_can( $user_id, 'vog' );
		$has_fairplay = user_can( $user_id, 'fairplay' );

		// Regular users (no special capabilities) see everything
		if ( ! $has_vog && ! $has_fairplay ) {
			return false;
		}

		// Fair Play users see all members
		if ( $has_fairplay ) {
			return false;
		}

		// VOG-only users see only volunteers
		return true;
	}

	/**
	 * Check if a user can access a post
	 *
	 * All logged-in users can access all posts. Only trashed posts are hidden.
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
	 * Filter WP_Query for access control
	 *
	 * Logged-in users see all posts. Not logged-in users see nothing.
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

		// Check if user is logged in
		if ( ! $this->is_user_approved() ) {
			// Not logged in - show nothing
			$query->set( 'post__in', [ 0 ] );
			return;
		}

		// User isolation for tasks - users only see their own tasks
		if ( $post_type === 'rondo_todo' ) {
			$query->set( 'author', get_current_user_id() );
		}

		// VOG-only users see only volunteers for person post type
		if ( $post_type === 'person' && $this->should_filter_volunteers_only() ) {
			$meta_query   = $query->get( 'meta_query' ) ?: [];
			$meta_query[] = [
				'key'     => 'huidig-vrijwilliger',
				'value'   => '1',
				'compare' => '=',
			];
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Filter REST API queries
	 *
	 * Logged-in users see all posts. Not logged-in users see nothing.
	 */
	public function filter_rest_query( $args, $request ) {
		if ( ! $this->is_user_approved() ) {
			// Not logged in - show nothing
			$args['post__in'] = [ 0 ];
			return $args;
		}

		// User isolation for tasks - users only see their own tasks
		// Check if this is a rondo_todo query by examining current filter
		$current_filter = current_filter();
		if ( $current_filter === 'rest_rondo_todo_query' ) {
			$args['author'] = get_current_user_id();
		}

		return $args;
	}

	/**
	 * Filter REST API single item access
	 */
	public function filter_rest_single_access( $response, $post, $request ) {
		$user_id = get_current_user_id();

		// Check if user is logged in
		if ( ! $this->is_user_approved( $user_id ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		// Don't allow access to trashed posts
		if ( $post->post_status === 'trash' ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'This item has been deleted.', 'rondo' ),
				[ 'status' => 404 ]
			);
		}

		return $response;
	}
}
