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
	private $controlled_post_types            = [ 'person', 'team', 'rondo_todo', 'rondo_clothing_item', 'rondo_clothing_txn' ];
	private const TODO_ASSIGNED_USER_META_KEY = 'assigned_user_id';

	/**
	 * When true, age-group filtering is suppressed (used by Kaderlijst).
	 *
	 * @var bool
	 */
	public static $suppress_age_group_filter = false;

	/**
	 * Management capabilities that bypass age-group filtering.
	 */
	private const AGE_GROUP_BYPASS_CAPS = [
		'manage_options',
		'fairplay',
		'vog',
		'financieel',
		'toegangscontrole',
		'manage_clothing',
	];

	public function __construct() {
		// Block person editing for users without the right capabilities
		add_filter( 'map_meta_cap', [ $this, 'restrict_person_editing' ], 10, 4 );

		// Allow any vrijwilligers-capable user to edit every volunteer CPT (shared model)
		add_filter( 'map_meta_cap', [ $this, 'grant_volunteer_editing' ], 10, 4 );

		// Filter queries to block unapproved users
		add_action( 'pre_get_posts', [ $this, 'filter_queries' ] );

		// Filter REST API queries
		add_filter( 'rest_person_query', fn( $args, $req ) => $this->filter_rest_query( $args, $req, 'person' ), 10, 2 );
		add_filter( 'rest_team_query', fn( $args, $req ) => $this->filter_rest_query( $args, $req, 'team' ), 10, 2 );
		add_filter( 'rest_rondo_todo_query', fn( $args, $req ) => $this->filter_rest_query( $args, $req, 'rondo_todo' ), 10, 2 );
		add_filter( 'rest_rondo_clothing_item_query', fn( $args, $req ) => $this->filter_rest_query( $args, $req, 'rondo_clothing_item' ), 10, 2 );
		add_filter( 'rest_rondo_clothing_txn_query', fn( $args, $req ) => $this->filter_rest_query( $args, $req, 'rondo_clothing_txn' ), 10, 2 );

		// Filter REST API single item access
		add_filter( 'rest_prepare_person', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_team', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_rondo_todo', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_rondo_clothing_item', [ $this, 'filter_rest_single_access' ], 10, 3 );
		add_filter( 'rest_prepare_rondo_clothing_txn', [ $this, 'filter_rest_single_access' ], 10, 3 );
	}

	/**
	 * Check if a user can edit people records.
	 *
	 * Users with fairplay, vog, financieel, or manage_options capabilities can edit people.
	 *
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return bool Whether the user can edit people.
	 */
	public static function can_edit_people( $user_id = null ) {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		return user_can( $user_id, 'manage_options' )
			|| user_can( $user_id, 'fairplay' )
			|| user_can( $user_id, 'vog' )
			|| user_can( $user_id, 'financieel' );
	}

	/**
	 * Restrict person editing via map_meta_cap filter.
	 *
	 * When a user without can_edit_people() tries to edit a person post,
	 * map the capability to 'do_not_allow'.
	 *
	 * @param string[] $caps    Required primitive capabilities.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Additional arguments (post ID at index 0 for edit_post).
	 * @return string[] Modified capabilities.
	 */
	public function restrict_person_editing( $caps, $cap, $user_id, $args ) {
		if ( $cap !== 'edit_post' || empty( $args[0] ) ) {
			return $caps;
		}

		$post = get_post( $args[0] );

		if ( ! $post || $post->post_type !== 'person' ) {
			return $caps;
		}

		if ( ! self::can_edit_people( $user_id ) ) {
			return [ 'do_not_allow' ];
		}

		return $caps;
	}

	/**
	 * Volunteer-management CPTs that follow the shared-access model.
	 *
	 * Any user who can reach the Vrijwilligers section (the `vrijwilligers`
	 * capability) may edit and delete every record of these types — including
	 * ones the seeder or another beheerder authored — so they can be managed
	 * entirely from the React frontend instead of wp-admin.
	 */
	private const VOLUNTEER_SHARED_CPTS = [
		'taakuitleg',
		'dienst_type',
		'shift_template',
		'dienst_shift',
	];

	/**
	 * Grant shared editing of the volunteer CPTs to vrijwilligers-capable users.
	 *
	 * By default map_meta_cap would require `edit_others_posts` for non-author
	 * edits, which Rondo Users do not have — so we remap to a primitive they do
	 * hold. Admins (manage_options) already pass. Everyone else is denied.
	 *
	 * @param string[] $caps    Required primitive capabilities.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User ID.
	 * @param array    $args    Additional arguments (post ID at index 0).
	 * @return string[] Modified capabilities.
	 */
	public function grant_volunteer_editing( $caps, $cap, $user_id, $args ) {
		$primitive = [
			'edit_post'   => 'edit_posts',
			'delete_post' => 'delete_posts',
		];

		if ( ! isset( $primitive[ $cap ] ) || empty( $args[0] ) ) {
			return $caps;
		}

		$post = get_post( $args[0] );

		if ( ! $post || ! in_array( $post->post_type, self::VOLUNTEER_SHARED_CPTS, true ) ) {
			return $caps;
		}

		if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'vrijwilligers' ) ) {
			return [ $primitive[ $cap ] ];
		}

		return [ 'do_not_allow' ];
	}

	/**
	 * Get user ID, defaulting to current user if not provided
	 *
	 * @param int|null $user_id User ID (optional).
	 * @return int User ID.
	 */
	private function get_user_id( $user_id = null ) {
		return $user_id ?? get_current_user_id();
	}

	/**
	 * Check if user should only see volunteers (VOG-only users)
	 *
	 * VOG-only users (users with VOG capability but not Fair Play) should only
	 * see people who are current volunteers (huidig-vrijwilliger=1).
	 *
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return bool Whether the user is a VOG-only user.
	 */
	private function is_vog_only_user( $user_id = null ) {
		$user_id = $this->get_user_id( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		// Admins see everything
		if ( user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		// VOG-only users (has VOG capability but not Fair Play) see only volunteers
		return user_can( $user_id, 'vog' ) && ! user_can( $user_id, 'fairplay' );
	}

	/**
	 * Apply VOG filter to query (limit to current volunteers only)
	 *
	 * @param \WP_Query $query Query object to modify.
	 */
	private function apply_vog_filter( $query ) {
		$meta_query   = $query->get( 'meta_query' ) ?: [];
		$meta_query[] = [
			'key'     => 'huidig-vrijwilliger',
			'value'   => '1',
			'compare' => '=',
		];
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Get permitted age groups for a user based on the rondo_age_group_access option.
	 *
	 * Returns null if the user has no age-group restriction (management capability,
	 * no config, or all roles have empty config). Returns an array of permitted
	 * leeftijdsgroep values when restricted.
	 *
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return string[]|null Array of permitted age groups, or null if unrestricted.
	 */
	public static function get_permitted_age_groups( $user_id = null ) {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return null;
		}

		// Users with any management capability bypass age-group filtering entirely.
		foreach ( self::AGE_GROUP_BYPASS_CAPS as $cap ) {
			if ( user_can( $user_id, $cap ) ) {
				return null;
			}
		}

		// Non-management users default to "see nobody" unless explicitly configured.
		// Read the per-role age-group config.
		$raw = get_option( 'rondo_age_group_access' );

		if ( ! $raw ) {
			// No config at all → non-management user sees nobody.
			return [];
		}

		$config = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

		if ( ! is_array( $config ) || empty( $config ) ) {
			// Empty or invalid config → non-management user sees nobody.
			return [];
		}

		$user = get_user_by( 'id', $user_id );

		if ( ! $user ) {
			return [];
		}

		// Collect permitted age groups across all user roles.
		$permitted = [];

		foreach ( $user->roles as $role ) {
			if ( ! isset( $config[ $role ] ) ) {
				continue;
			}

			$role_groups = $config[ $role ];

			if ( ! is_array( $role_groups ) || empty( $role_groups ) ) {
				continue;
			}

			$permitted = array_merge( $permitted, $role_groups );
		}

		// If no roles have age-group entries → non-management user sees nobody.
		if ( empty( $permitted ) ) {
			return [];
		}

		return array_values( array_unique( $permitted ) );
	}

	/**
	 * Check if the current user has age-group restrictions.
	 *
	 * @return bool True if the user is restricted to specific age groups.
	 */
	private function has_age_group_restriction() {
		return self::get_permitted_age_groups() !== null;
	}

	/**
	 * Apply age-group filter to a WP_Query (limit to permitted leeftijdsgroep values).
	 *
	 * @param \WP_Query $query Query object to modify.
	 */
	private function apply_age_group_filter( $query ) {
		$permitted = self::get_permitted_age_groups();

		if ( $permitted === null ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' ) ?: [];

		if ( empty( $permitted ) ) {
			// Empty array = see nobody. Use an impossible condition.
			$meta_query[] = [
				'key'     => 'leeftijdsgroep',
				'value'   => '___impossible___',
				'compare' => '=',
			];
		} else {
			$meta_query[] = [
				'key'     => 'leeftijdsgroep',
				'value'   => $permitted,
				'compare' => 'IN',
			];
		}

		$query->set( 'meta_query', $meta_query );
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
		$user_id = $this->get_user_id( $user_id );

		if ( ! $user_id ) {
			return false;
		}

		$post = get_post( $post_id );

		if ( ! $post ) {
			return false;
		}

		// Not a controlled post type - allow access
		if ( ! in_array( $post->post_type, $this->controlled_post_types, true ) ) {
			return true;
		}

		// Don't allow access to trashed posts
		if ( $post->post_status === 'trash' ) {
			return false;
		}

		return true;
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

		if ( ! $post_type || ! in_array( $post_type, $this->controlled_post_types, true ) ) {
			return;
		}

		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
			// Not logged in - show nothing
			$query->set( 'post__in', [ 0 ] );
			return;
		}

		// Task visibility: users see tasks they created OR tasks assigned to them.
		if ( $post_type === 'rondo_todo' ) {
			$visible_ids = $this->get_visible_todo_ids( get_current_user_id() );
			$query->set( 'post__in', ! empty( $visible_ids ) ? $visible_ids : [ 0 ] );
		}

		// Clothing data is only available to clothing managers/admins.
		if ( in_array( $post_type, [ 'rondo_clothing_item', 'rondo_clothing_txn' ], true ) ) {
			if ( ! current_user_can( 'manage_clothing' ) && ! current_user_can( 'manage_options' ) ) {
				$query->set( 'post__in', [ 0 ] );
			}
		}

		// VOG-only users see only volunteers for person post type
		if ( $post_type === 'person' && $this->is_vog_only_user() ) {
			$this->apply_vog_filter( $query );
		}

		// Age-group filtering for person post type
		if ( $post_type === 'person' && ! self::$suppress_age_group_filter && $this->has_age_group_restriction() ) {
			$this->apply_age_group_filter( $query );
		}
	}

	/**
	 * Filter REST API queries
	 *
	 * Logged-in users see all posts. Not logged-in users see nothing.
	 *
	 * @param array            $args      WP_Query arguments.
	 * @param \WP_REST_Request $request   REST request object.
	 * @param string           $post_type Post type being queried.
	 * @return array Modified query arguments.
	 */
	public function filter_rest_query( $args, $request, $post_type ) {
		if ( ! is_user_logged_in() ) {
			// Not logged in - show nothing
			$args['post__in'] = [ 0 ];
			return $args;
		}

		// Task visibility: users see tasks they created OR tasks assigned to them.
		if ( $post_type === 'rondo_todo' ) {
			$visible_ids      = $this->get_visible_todo_ids( get_current_user_id() );
			$args['post__in'] = ! empty( $visible_ids ) ? $visible_ids : [ 0 ];
		}

		// Clothing data is only available to clothing managers/admins.
		if ( in_array( $post_type, [ 'rondo_clothing_item', 'rondo_clothing_txn' ], true ) ) {
			if ( ! current_user_can( 'manage_clothing' ) && ! current_user_can( 'manage_options' ) ) {
				$args['post__in'] = [ 0 ];
			}
		}

		// Allow authenticated callers to suppress age-group filtering (e.g. Kaderlijst rebuild).
		if ( $post_type === 'person' && $request->get_param( 'suppress_age_group' ) && is_user_logged_in() ) {
			self::$suppress_age_group_filter = true;
		}

		// Age-group filtering for person post type
		if ( $post_type === 'person' && ! self::$suppress_age_group_filter && $this->has_age_group_restriction() ) {
			$permitted = self::get_permitted_age_groups();

			if ( $permitted !== null ) {
				$meta_query = $args['meta_query'] ?? [];

				if ( empty( $permitted ) ) {
					// Empty array = see nobody. Use an impossible condition.
					$meta_query[] = [
						'key'     => 'leeftijdsgroep',
						'value'   => '___impossible___',
						'compare' => '=',
					];
				} else {
					$meta_query[] = [
						'key'     => 'leeftijdsgroep',
						'value'   => $permitted,
						'compare' => 'IN',
					];
				}

				$args['meta_query'] = $meta_query;
			}
		}

		return $args;
	}

	/**
	 * Get todo IDs visible to a user.
	 *
	 * Visibility rule:
	 * - user is post author (creator), OR
	 * - user is the assigned user in post meta.
	 *
	 * @param int $user_id User ID.
	 * @return int[] List of visible todo post IDs.
	 */
	private function get_visible_todo_ids( int $user_id ): array {
		global $wpdb;

		if ( $user_id <= 0 ) {
			return [];
		}

		$sql = $wpdb->prepare(
			"SELECT DISTINCT p.ID
			 FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} pm
			   ON pm.post_id = p.ID
			  AND pm.meta_key = %s
			 WHERE p.post_type = %s
			   AND p.post_status <> 'trash'
			   AND (p.post_author = %d OR CAST(pm.meta_value AS UNSIGNED) = %d)",
			self::TODO_ASSIGNED_USER_META_KEY,
			'rondo_todo',
			$user_id,
			$user_id
		);

		$ids = $wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above.

		return array_values(
			array_filter(
				array_map( 'intval', (array) $ids ),
				static fn( $id ) => $id > 0
			)
		);
	}

	/**
	 * Filter REST API single item access
	 */
	public function filter_rest_single_access( $response, $post, $request ) {
		// Check if user is logged in
		if ( ! is_user_logged_in() ) {
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

		// Age-group filtering for person post type
		if ( $post->post_type === 'person' && ! self::$suppress_age_group_filter ) {
			$permitted = self::get_permitted_age_groups();

			if ( $permitted !== null ) {
				$person_age_group = get_post_meta( $post->ID, 'leeftijdsgroep', true );

				if ( ! in_array( $person_age_group, $permitted, true ) ) {
					return new \WP_Error(
						'rest_forbidden_age_group',
						__( 'You do not have permission to view this person.', 'rondo' ),
						[ 'status' => 403 ]
					);
				}
			}
		}

		return $response;
	}
}
