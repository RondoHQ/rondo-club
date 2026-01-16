<?php
/**
 * Access Control for Personal CRM
 *
 * Users can only see posts they created themselves.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Access_Control {

	/**
	 * Post types that should have access control
	 */
	private $controlled_post_types = array( 'person', 'company', 'important_date', 'prm_todo' );

	/**
	 * Check if we're on the frontend (not admin area)
	 */
	private function is_frontend() {
		return ! is_admin();
	}

	public function __construct() {
		// Filter queries to only show accessible posts
		add_action( 'pre_get_posts', array( $this, 'filter_queries' ) );

		// Filter REST API queries
		add_filter( 'rest_person_query', array( $this, 'filter_rest_query' ), 10, 2 );
		add_filter( 'rest_company_query', array( $this, 'filter_rest_query' ), 10, 2 );
		add_filter( 'rest_important_date_query', array( $this, 'filter_rest_query' ), 10, 2 );
		add_filter( 'rest_prm_todo_query', array( $this, 'filter_rest_query' ), 10, 2 );

		// Check single post access
		add_filter( 'the_posts', array( $this, 'filter_single_post_access' ), 10, 2 );

		// Filter REST API single item access
		add_filter( 'rest_prepare_person', array( $this, 'filter_rest_single_access' ), 10, 3 );
		add_filter( 'rest_prepare_company', array( $this, 'filter_rest_single_access' ), 10, 3 );
		add_filter( 'rest_prepare_important_date', array( $this, 'filter_rest_single_access' ), 10, 3 );
		add_filter( 'rest_prepare_prm_todo', array( $this, 'filter_rest_single_access' ), 10, 3 );

		// Convert workspace post IDs to term IDs when saving via REST API
		add_action( 'rest_after_insert_person', array( $this, 'convert_workspace_ids_after_rest_insert' ), 10, 2 );
		add_action( 'rest_after_insert_company', array( $this, 'convert_workspace_ids_after_rest_insert' ), 10, 2 );
		add_action( 'rest_after_insert_important_date', array( $this, 'convert_workspace_ids_after_rest_insert' ), 10, 2 );
		add_action( 'rest_after_insert_prm_todo', array( $this, 'convert_workspace_ids_after_rest_insert' ), 10, 2 );

		// Convert term IDs back to workspace post IDs when loading
		add_filter( 'acf/load_value/name=_assigned_workspaces', array( $this, 'convert_term_ids_to_workspace_ids' ), 10, 3 );
	}

	/**
	 * Convert workspace post IDs to term IDs after REST API insert/update
	 *
	 * The frontend sends workspace post IDs, but the ACF taxonomy field needs
	 * workspace_access term IDs. This action converts and saves them properly.
	 *
	 * @param WP_Post $post The inserted/updated post
	 * @param WP_REST_Request $request The request object
	 */
	public function convert_workspace_ids_after_rest_insert( $post, $request ) {
		$params = $request->get_json_params();

		// Check if workspace IDs were sent
		if ( ! isset( $params['acf']['_assigned_workspaces'] ) ) {
			return;
		}

		$workspace_ids = $params['acf']['_assigned_workspaces'];

		// Handle empty array - clear all terms
		if ( empty( $workspace_ids ) || ! is_array( $workspace_ids ) ) {
			wp_set_object_terms( $post->ID, array(), 'workspace_access' );
			update_field( '_assigned_workspaces', array(), $post->ID );
			return;
		}

		// Convert workspace post IDs to term IDs
		$term_ids = array();
		foreach ( $workspace_ids as $workspace_id ) {
			$term_slug = 'workspace-' . intval( $workspace_id );
			$term      = get_term_by( 'slug', $term_slug, 'workspace_access' );

			if ( $term && ! is_wp_error( $term ) ) {
				$term_ids[] = $term->term_id;
			}
		}

		// Set the terms on the post
		wp_set_object_terms( $post->ID, $term_ids, 'workspace_access' );

		// Update the ACF field with term IDs
		update_field( '_assigned_workspaces', $term_ids, $post->ID );
	}

	/**
	 * Convert workspace_access term IDs to workspace post IDs when loading
	 *
	 * The ACF taxonomy field stores term IDs, but the frontend expects workspace
	 * post IDs. This filter converts between the two formats.
	 *
	 * @param mixed $value The loaded value (term IDs)
	 * @param int $post_id The post ID
	 * @param array $field The field array
	 * @return array Array of workspace post IDs
	 */
	public function convert_term_ids_to_workspace_ids( $value, $post_id, $field ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}

		$workspace_ids = array();
		foreach ( $value as $term_id ) {
			$term = get_term( $term_id, 'workspace_access' );
			if ( $term && ! is_wp_error( $term ) ) {
				// The term slug format is 'workspace-{post_id}'
				if ( preg_match( '/^workspace-(\d+)$/', $term->slug, $matches ) ) {
					$workspace_ids[] = intval( $matches[1] );
				}
			}
		}

		return $workspace_ids;
	}

	/**
	 * Check if a user can access a post
	 *
	 * Permission resolution order:
	 * 1. Is user the author? → Full access
	 * 2. Check _shared_with for user → Allow with specified permission (overrides visibility)
	 * 3. Is _visibility = 'private'? → Deny (unless #1 or #2)
	 * 4. Is _visibility = 'workspace'? → Check workspace membership
	 * 5. Deny
	 */
	public function user_can_access_post( $post_id, $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		// Check if user is approved (admins are always approved)
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			if ( ! PRM_User_Roles::is_user_approved( $user_id ) ) {
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

		// 1. Check if user is the author → Full access
		if ( (int) $post->post_author === (int) $user_id ) {
			return true;
		}

		// Admins in admin area have full access
		if ( user_can( $user_id, 'manage_options' ) && ! $this->is_frontend() ) {
			return true;
		}

		// 2. Check direct shares first (overrides visibility)
		if ( PRM_Visibility::user_has_share( $post_id, $user_id ) ) {
			return true;
		}

		// 3. Check visibility
		$visibility = PRM_Visibility::get_visibility( $post_id );

		// Private = only author (already checked above), no shares (checked above)
		if ( $visibility === PRM_Visibility::VISIBILITY_PRIVATE ) {
			return false;
		}

		// 4. Workspace visibility check
		if ( $visibility === PRM_Visibility::VISIBILITY_WORKSPACE ) {
			// Get user's workspace IDs
			$user_workspace_ids = PRM_Workspace_Members::get_user_workspace_ids( $user_id );

			if ( ! empty( $user_workspace_ids ) ) {
				// Check if post has any matching workspace_access terms
				$post_terms = wp_get_post_terms( $post_id, 'workspace_access', array( 'fields' => 'slugs' ) );

				if ( ! is_wp_error( $post_terms ) ) {
					foreach ( $post_terms as $slug ) {
						// Term slug format: 'workspace-{ID}'
						$workspace_id = (int) str_replace( 'workspace-', '', $slug );
						if ( in_array( $workspace_id, $user_workspace_ids ) ) {
							return true;
						}
					}
				}
			}
		}

		// 5. Deny
		return false;
	}

	/**
	 * Get user's permission level for a post
	 *
	 * @param int $post_id Post ID.
	 * @param int $user_id User ID (optional, defaults to current user).
	 * @return string|false 'owner', 'admin', 'member', 'viewer', 'edit', 'view', or false.
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

		// Workspace role
		$visibility = PRM_Visibility::get_visibility( $post_id );
		if ( $visibility === PRM_Visibility::VISIBILITY_WORKSPACE ) {
			$user_workspace_ids = PRM_Workspace_Members::get_user_workspace_ids( $user_id );
			$post_terms         = wp_get_post_terms( $post_id, 'workspace_access', array( 'fields' => 'slugs' ) );

			if ( ! is_wp_error( $post_terms ) ) {
				foreach ( $post_terms as $slug ) {
					$workspace_id = (int) str_replace( 'workspace-', '', $slug );
					if ( in_array( $workspace_id, $user_workspace_ids ) ) {
						$role = PRM_Workspace_Members::get_user_role( $workspace_id, $user_id );
						if ( $role ) {
							return $role; // 'admin', 'member', or 'viewer'
						}
					}
				}
			}
		}

		// Direct share permission
		$share_permission = PRM_Visibility::get_share_permission( $post_id, $user_id );
		if ( $share_permission ) {
			return $share_permission; // 'edit' or 'view'
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
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		// Check if user is approved (admins are always approved)
		if ( ! current_user_can( 'manage_options' ) ) {
			if ( ! PRM_User_Roles::is_user_approved( $user_id ) ) {
				// Unapproved user - show nothing
				$query->set( 'post__in', array( 0 ) );
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
			$query->set( 'post__in', array( 0 ) ); // No accessible posts
		} else {
			$query->set( 'post__in', $accessible_ids );
		}
	}

	/**
	 * Get all post IDs accessible by a user
	 *
	 * Includes posts where:
	 * 1. User is author
	 * 2. _visibility = 'workspace' AND user is member of any assigned workspace
	 * 3. User appears in _shared_with meta
	 */
	public function get_accessible_post_ids( $post_type, $user_id ) {
		global $wpdb;

		// Determine valid post statuses based on post type
		$valid_statuses = array( 'publish' );
		if ( $post_type === 'prm_todo' ) {
			$valid_statuses = array( 'prm_open', 'prm_awaiting', 'prm_completed' );
		}
		$status_placeholders = implode( ',', array_fill( 0, count( $valid_statuses ), '%s' ) );

		// 1. Posts authored by user
		$authored_params = array_merge( array( $post_type ), $valid_statuses, array( $user_id ) );
		$authored        = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
             WHERE post_type = %s
             AND post_status IN ($status_placeholders)
             AND post_author = %d",
				$authored_params
			)
		);

		// 2. Workspace-visible posts where user is member
		$workspace_ids   = PRM_Workspace_Members::get_user_workspace_ids( $user_id );
		$workspace_posts = array();

		if ( ! empty( $workspace_ids ) ) {
			// Build term slugs from workspace IDs (format: workspace-{ID})
			$term_slugs = array_map(
				function ( $id ) {
					return 'workspace-' . $id;
				},
				$workspace_ids
			);

			$term_placeholders = implode( ',', array_fill( 0, count( $term_slugs ), '%s' ) );

			// Prepare query parameters: post_type first, then statuses, then term slugs
			$query_params = array_merge( array( $post_type ), $valid_statuses, $term_slugs );

			$workspace_posts = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT p.ID
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                 INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                 INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                 WHERE p.post_type = %s
                 AND p.post_status IN ($status_placeholders)
                 AND pm.meta_key = '_visibility'
                 AND pm.meta_value = 'workspace'
                 AND tt.taxonomy = 'workspace_access'
                 AND t.slug IN ($term_placeholders)",
					$query_params
				)
			);
		}

		// 3. Posts shared directly with user
		// _shared_with is serialized array, so we use LIKE for the user_id
		$shared_params = array_merge( array( $post_type ), $valid_statuses, array( '%"user_id":' . $user_id . '%' ) );
		$shared_posts  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = %s
             AND p.post_status IN ($status_placeholders)
             AND pm.meta_key = '_shared_with'
             AND pm.meta_value LIKE %s",
				$shared_params
			)
		);

		// Merge and dedupe
		$all_ids = array_unique( array_merge( $authored, $workspace_posts, $shared_posts ) );

		return $all_ids;
	}

	/**
	 * Filter REST API queries
	 */
	public function filter_rest_query( $args, $request ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			$args['post__in'] = array( 0 );
			return $args;
		}

		// Check if user is approved (admins are always approved)
		if ( ! user_can( $user_id, 'manage_options' ) ) {
			if ( ! PRM_User_Roles::is_user_approved( $user_id ) ) {
				// Unapproved user - show nothing
				$args['post__in'] = array( 0 );
				return $args;
			}
		}

		// REST API calls are typically from the frontend React app
		// Admins should be restricted on the frontend, so we filter REST API calls for admins too
		// The admin area uses WP_Query directly, not REST API

		$post_type      = $args['post_type'] ?? '';
		$accessible_ids = $this->get_accessible_post_ids( $post_type, $user_id );

		if ( empty( $accessible_ids ) ) {
			$args['post__in'] = array( 0 );
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

		$filtered = array();

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
			if ( ! PRM_User_Roles::is_user_approved( $user_id ) ) {
				return new WP_Error(
					'rest_forbidden',
					__( 'Your account is pending approval. Please contact an administrator.', 'caelis' ),
					array( 'status' => 403 )
				);
			}
		}

		// Don't allow access to trashed posts
		if ( $post->post_status === 'trash' ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'This item has been deleted.', 'caelis' ),
				array( 'status' => 404 )
			);
		}

		if ( ! $this->user_can_access_post( $post->ID, $user_id ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this item.', 'caelis' ),
				array( 'status' => 403 )
			);
		}

		return $response;
	}
}
