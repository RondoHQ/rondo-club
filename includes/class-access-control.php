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
	private $controlled_post_types            = [
		'person',
		'team',
		'commissie',
		'rondo_todo',
		'rondo_feedback',
		'rondo_clothing_item',
		'rondo_clothing_txn',
		'discipline_case',
		'rondo_invoice',
		'dienst_type',
		'shift_template',
		'dienst_shift',
		'taakuitleg',
	];
	private const TODO_ASSIGNED_USER_META_KEY = 'assigned_user_id';

	/**
	 * Management capabilities that bypass age-group filtering.
	 *
	 * `financieel_read` is here because facturen and contributie span the whole club:
	 * scoping a read-only penningmeester to one age group hides the members their
	 * invoices belong to. It is a view bypass only — it grants no edit rights, so it
	 * is deliberately absent from can_edit_people().
	 */
	private const AGE_GROUP_BYPASS_CAPS = [
		'manage_options',
		'fairplay',
		'vog',
		'financieel',
		'financieel_read',
		'toegangscontrole',
		'manage_clothing',
		'ledenadministratie',
	];

	/**
	 * A child stops being visible to their parent at this age — by then they are an
	 * adult member who can hold their own account.
	 */
	private const CHILD_VISIBILITY_MAX_AGE = 18;

	/**
	 * ACF fields a scoped member may read on their own and their children's records.
	 *
	 * An allowlist, deliberately: a field added to the person CPT later is private
	 * until someone consciously adds it here. Notably absent are `financiele-blokkade`,
	 * `wacht_op_overschrijving` and `freescout-id`.
	 */
	private const MEMBER_VISIBLE_ACF_FIELDS = [
		'first_name',
		'infix',
		'last_name',
		'birthdate',
		'gender',
		'email_1',
		'email_2',
		'mobile_1',
		'telephone_1',
		'addresses',
		'knvb-id',
		'leeftijdsgroep',
		'spelactiviteit',
		'type-lid',
		'lid-sinds',
		'datum-vog',
		'huidig-vrijwilliger',
		'former_member',
		'relationships',
	];

	/**
	 * Memoised per-user visible person IDs. Keyed by user ID.
	 *
	 * @var array<int, int[]>
	 */
	private static $visible_person_ids_cache = [];

	public function __construct() {
		// Block person editing for users without the right capabilities
		add_filter( 'map_meta_cap', [ $this, 'restrict_person_editing' ], 10, 4 );

		// Allow any vrijwilligers-capable user to edit every volunteer CPT (shared model)
		add_filter( 'map_meta_cap', [ $this, 'grant_volunteer_editing' ], 10, 4 );

		// Filter queries to block unapproved users
		add_action( 'pre_get_posts', [ $this, 'filter_queries' ] );

		// Cover every core REST route for every Rondo CPT. Custom route permission
		// callbacks remain in place as a second layer.
		foreach ( $this->controlled_post_types as $post_type ) {
			add_filter(
				'rest_' . $post_type . '_query',
				fn( $args, $req ) => $this->filter_rest_query( $args, $req, $post_type ),
				10,
				2
			);
			add_filter( 'rest_prepare_' . $post_type, [ $this, 'filter_rest_single_access' ], 10, 3 );
		}
	}

	/**
	 * Check if a user can edit people records.
	 *
	 * Users with a people-administration capability can edit people.
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
			|| user_can( $user_id, 'financieel' )
			|| user_can( $user_id, 'ledenadministratie' );
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
		if ( ! in_array( $cap, [ 'edit_post', 'delete_post' ], true ) || empty( $args[0] ) ) {
			return $caps;
		}

		$post = get_post( $args[0] );

		if ( ! $post || ! in_array( $post->post_type, self::VOLUNTEER_SHARED_CPTS, true ) ) {
			return $caps;
		}

		if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'vrijwilligers' ) ) {
			$post_type_object = get_post_type_object( $post->post_type );
			if ( ! $post_type_object ) {
				return [ 'do_not_allow' ];
			}

			$primitive = $cap === 'delete_post'
				? $post_type_object->cap->delete_posts
				: $post_type_object->cap->edit_posts;

			return [ $primitive ];
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
	 * The single authority on "may this user see this person".
	 *
	 * Three tiers, in order:
	 *   1. Management users (`get_permitted_age_groups()` → null) see everyone.
	 *   2. Coordinators (a configured, non-empty list) see their own age groups.
	 *   3. Everyone else — plain members — see only themselves and their minor children.
	 *
	 * Every person-visibility decision must route through here: the REST collection
	 * filter, the single-item filter, the raw-SQL people endpoint, and
	 * user_can_access_post(). Do not re-derive the rule anywhere else.
	 *
	 * @param int      $person_id Person post ID.
	 * @param int|null $user_id   User ID (optional, defaults to current user).
	 * @return bool Whether the user may view this person.
	 */
	public static function can_view_person( $person_id, $user_id = null ) {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		$permitted = self::get_permitted_age_groups( $user_id );

		// Management: no restriction at all.
		if ( $permitted === null ) {
			return true;
		}

		// Coordinator: restricted to configured age groups.
		if ( ! empty( $permitted ) ) {
			$age_group = get_post_meta( $person_id, 'leeftijdsgroep', true );
			return in_array( $age_group, $permitted, true );
		}

		// Plain member: self and minor children only.
		return in_array( (int) $person_id, self::get_visible_person_ids( $user_id ), true );
	}

	/**
	 * Is this user scoped to their own household? True for plain members — the users
	 * whose permitted age-group list is empty, meaning "see nobody" by default.
	 *
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return bool
	 */
	public static function is_scoped_member( $user_id = null ) {
		return self::get_permitted_age_groups( $user_id ) === [];
	}

	/**
	 * The person records a scoped member may reach: their own, plus their children
	 * who are under CHILD_VISIBILITY_MAX_AGE.
	 *
	 * Returns an empty array when the user has no linked person.
	 *
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return int[] Person post IDs.
	 */
	public static function get_visible_person_ids( $user_id = null ) {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return [];
		}

		if ( isset( self::$visible_person_ids_cache[ $user_id ] ) ) {
			return self::$visible_person_ids_cache[ $user_id ];
		}

		$ids  = [];
		$self = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );

		if ( $self ) {
			$ids[] = $self;
			foreach ( self::minor_children_of( $self ) as $child_id ) {
				$ids[] = $child_id;
			}
		}

		self::$visible_person_ids_cache[ $user_id ] = array_values( array_unique( $ids ) );

		return self::$visible_person_ids_cache[ $user_id ];
	}

	/**
	 * Drop the memoised visible-person IDs. Call after changing a user's linked
	 * person or a person's relationships within the same request.
	 */
	public static function flush_visible_person_ids_cache() {
		self::$visible_person_ids_cache = [];
	}

	/**
	 * Person IDs of this person's children who are still minors.
	 *
	 * @param int $person_id Parent person post ID.
	 * @return int[] Child person post IDs.
	 */
	private static function minor_children_of( $person_id ) {
		$relationships = get_field( 'relationships', $person_id );

		if ( empty( $relationships ) || ! is_array( $relationships ) ) {
			return [];
		}

		$children = [];

		foreach ( $relationships as $relationship ) {
			$type = $relationship['relationship_type'] ?? null;
			if ( is_object( $type ) ) {
				$type = $type->term_id ?? null;
			} elseif ( is_array( $type ) ) {
				$type = $type['term_id'] ?? null;
			}

			if ( (int) $type !== \Rondo\Data\InverseRelationships::TYPE_CHILD ) {
				continue;
			}

			$related = $relationship['related_person'] ?? null;
			if ( is_object( $related ) ) {
				$related = $related->ID ?? null;
			} elseif ( is_array( $related ) ) {
				$related = $related['ID'] ?? ( $related[0] ?? null );
			}

			if ( $related && self::is_minor( (int) $related ) ) {
				$children[] = (int) $related;
			}
		}

		return array_values( array_unique( $children ) );
	}

	/**
	 * Is this person under CHILD_VISIBILITY_MAX_AGE?
	 *
	 * ACF `date_picker` stores `Ymd`, not `Y-m-d`. A person with no usable birthdate
	 * is treated as an adult — fail closed, do not expose a record on a missing field.
	 *
	 * @param int $person_id Person post ID.
	 * @return bool
	 */
	private static function is_minor( $person_id ) {
		$birthdate = (string) get_post_meta( $person_id, 'birthdate', true );
		$digits    = preg_replace( '/\D/', '', $birthdate );

		if ( strlen( $digits ) !== 8 ) {
			return false;
		}

		$birth = \DateTimeImmutable::createFromFormat( 'Ymd', $digits );

		if ( ! $birth ) {
			return false;
		}

		$age = $birth->diff( new \DateTimeImmutable( 'now' ) )->y;

		return $age < self::CHILD_VISIBILITY_MAX_AGE;
	}

	/**
	 * Strip a person's ACF payload down to what a scoped member may read.
	 *
	 * @param array $acf Full ACF payload.
	 * @return array Allowlisted subset.
	 */
	public static function filter_member_visible_acf( array $acf ) {
		return array_intersect_key( $acf, array_flip( self::MEMBER_VISIBLE_ACF_FIELDS ) );
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
		$scope = self::person_scope();

		if ( $scope === null ) {
			return;
		}

		if ( isset( $scope['post__in'] ) ) {
			$query->set( 'post__in', self::narrow_post_in( (array) $query->get( 'post__in' ), $scope['post__in'] ) );
			return;
		}

		$meta_query   = $query->get( 'meta_query' ) ?: [];
		$meta_query[] = [
			'key'     => 'leeftijdsgroep',
			'value'   => $scope['age_groups'],
			'compare' => 'IN',
		];
		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * How a user's person queries must be narrowed. The one place the rule lives.
	 *
	 * @param int|null $user_id User ID (optional, defaults to current user).
	 * @return array{age_groups?: string[], post__in?: int[]}|null Null when unrestricted.
	 */
	private static function person_scope( $user_id = null ) {
		$permitted = self::get_permitted_age_groups( $user_id );

		// Management: no narrowing at all.
		if ( $permitted === null ) {
			return null;
		}

		// Coordinator: narrow to their configured age groups.
		if ( ! empty( $permitted ) ) {
			return [ 'age_groups' => $permitted ];
		}

		// Scoped member: their own household, or nothing.
		$visible = self::get_visible_person_ids( $user_id );

		return [ 'post__in' => ! empty( $visible ) ? $visible : [ 0 ] ];
	}

	/**
	 * Intersect an existing post__in with the allowed set, so a caller-supplied
	 * `include` can narrow further but never widen. `[0]` means "no results".
	 *
	 * @param int[] $existing Current post__in, possibly empty.
	 * @param int[] $allowed  IDs the user may see.
	 * @return int[]
	 */
	private static function narrow_post_in( array $existing, array $allowed ) {
		$result = ! empty( $existing ) ? array_intersect( $existing, $allowed ) : $allowed;

		return ! empty( $result ) ? array_values( $result ) : [ 0 ];
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

		// Persons obey the visibility rule. This guard backs /people/{id}/notes,
		// /activities and /timeline; without it any logged-in user could read the
		// notes of any person in the club.
		if ( $post->post_type === 'person' ) {
			return self::can_view_person( $post->ID, $user_id );
		}

		if ( $post->post_type === 'rondo_todo' ) {
			return in_array( (int) $post->ID, $this->get_visible_todo_ids( (int) $user_id ), true );
		}

		if ( in_array( $post->post_type, [ 'rondo_clothing_item', 'rondo_clothing_txn' ], true ) ) {
			return user_can( $user_id, 'manage_clothing' ) || user_can( $user_id, 'manage_options' );
		}

		return user_can( $user_id, 'read_post', $post->ID );
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
		if ( $post_type === 'person' && $this->has_age_group_restriction() ) {
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

		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->read ) ) {
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

		// Person visibility: coordinators narrow to age groups, members to their household.
		if ( $post_type === 'person' ) {
			$scope = self::person_scope();

			if ( isset( $scope['post__in'] ) ) {
				$args['post__in'] = self::narrow_post_in( $args['post__in'] ?? [], $scope['post__in'] );
			} elseif ( isset( $scope['age_groups'] ) ) {
				$meta_query         = $args['meta_query'] ?? [];
				$meta_query[]       = [
					'key'     => 'leeftijdsgroep',
					'value'   => $scope['age_groups'],
					'compare' => 'IN',
				];
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

		if ( ! $this->user_can_access_post( $post->ID ) ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You do not have permission to access this resource.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		// Person visibility: management sees all, coordinators see their age groups,
		// members see their own household.
		if ( $post->post_type === 'person' ) {
			if ( ! self::can_view_person( $post->ID ) ) {
				// Error code kept for back-compat: PersonDetail.jsx branches on it.
				return new \WP_Error(
					'rest_forbidden_age_group',
					__( 'You do not have permission to view this person.', 'rondo' ),
					[ 'status' => 403 ]
				);
			}

			// A scoped member reads an allowlisted subset — never the financial flags.
			$data = $response->get_data();
			if ( isset( $data['acf'] ) && is_array( $data['acf'] ) && self::is_scoped_member() ) {
				$data['acf'] = self::filter_member_visible_acf( $data['acf'] );
				$response->set_data( $data );
			}
		}

		return $response;
	}
}
