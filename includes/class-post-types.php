<?php
/**
 * Custom Post Types Registration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_Post_Types {

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * Register all custom post types
	 */
	public function register_post_types() {
		$this->register_workspace_post_type();
		$this->register_workspace_invite_post_type();
		$this->register_person_post_type();
		$this->register_company_post_type();
		$this->register_important_date_post_type();
		$this->register_todo_statuses();
		$this->register_todo_post_type();
		$this->register_calendar_event_post_type();
	}

	/**
	 * Register Workspace CPT
	 */
	private function register_workspace_post_type() {
		$labels = array(
			'name'               => _x( 'Workspaces', 'Post type general name', 'caelis' ),
			'singular_name'      => _x( 'Workspace', 'Post type singular name', 'caelis' ),
			'menu_name'          => _x( 'Workspaces', 'Admin Menu text', 'caelis' ),
			'add_new'            => __( 'Add New', 'caelis' ),
			'add_new_item'       => __( 'Add New Workspace', 'caelis' ),
			'edit_item'          => __( 'Edit Workspace', 'caelis' ),
			'new_item'           => __( 'New Workspace', 'caelis' ),
			'view_item'          => __( 'View Workspace', 'caelis' ),
			'search_items'       => __( 'Search Workspaces', 'caelis' ),
			'not_found'          => __( 'No workspaces found', 'caelis' ),
			'not_found_in_trash' => __( 'No workspaces found in Trash', 'caelis' ),
			'all_items'          => __( 'All Workspaces', 'caelis' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'workspaces',
			'query_var'          => false,
			'rewrite'            => false, // Disable rewrite rules - React Router handles routing
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 4,
			'menu_icon'          => 'dashicons-networking',
			'supports'           => array( 'title', 'editor', 'author', 'thumbnail' ),
		);

		register_post_type( 'workspace', $args );
	}

	/**
	 * Register Workspace Invite CPT
	 *
	 * Used for tracking workspace invitations sent to users via email.
	 * Not exposed via standard wp/v2 REST - uses custom endpoints only.
	 */
	private function register_workspace_invite_post_type() {
		$labels = array(
			'name'               => _x( 'Workspace Invites', 'Post type general name', 'caelis' ),
			'singular_name'      => _x( 'Workspace Invite', 'Post type singular name', 'caelis' ),
			'menu_name'          => _x( 'Invites', 'Admin Menu text', 'caelis' ),
			'add_new'            => __( 'Add New', 'caelis' ),
			'add_new_item'       => __( 'Add New Invite', 'caelis' ),
			'edit_item'          => __( 'Edit Invite', 'caelis' ),
			'new_item'           => __( 'New Invite', 'caelis' ),
			'view_item'          => __( 'View Invite', 'caelis' ),
			'search_items'       => __( 'Search Invites', 'caelis' ),
			'not_found'          => __( 'No invites found', 'caelis' ),
			'not_found_in_trash' => __( 'No invites found in Trash', 'caelis' ),
			'all_items'          => __( 'All Invites', 'caelis' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => 'edit.php?post_type=workspace', // Submenu under Workspaces
			'show_in_rest'       => false, // Custom endpoints only
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title' ), // Title = email for easy identification
		);

		register_post_type( 'workspace_invite', $args );
	}

	/**
	 * Register Person CPT
	 */
	private function register_person_post_type() {
		$labels = array(
			'name'               => _x( 'People', 'Post type general name', 'caelis' ),
			'singular_name'      => _x( 'Person', 'Post type singular name', 'caelis' ),
			'menu_name'          => _x( 'People', 'Admin Menu text', 'caelis' ),
			'add_new'            => __( 'Add New', 'caelis' ),
			'add_new_item'       => __( 'Add New Person', 'caelis' ),
			'edit_item'          => __( 'Edit Person', 'caelis' ),
			'new_item'           => __( 'New Person', 'caelis' ),
			'view_item'          => __( 'View Person', 'caelis' ),
			'search_items'       => __( 'Search People', 'caelis' ),
			'not_found'          => __( 'No people found', 'caelis' ),
			'not_found_in_trash' => __( 'No people found in Trash', 'caelis' ),
			'all_items'          => __( 'All People', 'caelis' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'people',
			'query_var'          => false,
			'rewrite'            => false, // Disable rewrite rules - React Router handles routing
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-groups',
			'supports'           => array( 'title', 'thumbnail', 'comments', 'author' ),
		);

		register_post_type( 'person', $args );
	}

	/**
	 * Register Company CPT
	 */
	private function register_company_post_type() {
		$labels = array(
			'name'               => _x( 'Organizations', 'Post type general name', 'caelis' ),
			'singular_name'      => _x( 'Organization', 'Post type singular name', 'caelis' ),
			'menu_name'          => _x( 'Organizations', 'Admin Menu text', 'caelis' ),
			'add_new'            => __( 'Add New', 'caelis' ),
			'add_new_item'       => __( 'Add New Organization', 'caelis' ),
			'edit_item'          => __( 'Edit Organization', 'caelis' ),
			'new_item'           => __( 'New Organization', 'caelis' ),
			'view_item'          => __( 'View Organization', 'caelis' ),
			'search_items'       => __( 'Search Organizations', 'caelis' ),
			'not_found'          => __( 'No organizations found', 'caelis' ),
			'not_found_in_trash' => __( 'No organizations found in Trash', 'caelis' ),
			'all_items'          => __( 'All Organizations', 'caelis' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'companies',
			'query_var'          => false,
			'rewrite'            => false, // Disable rewrite rules - React Router handles routing
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => true, // Enable parent-child relationships
			'menu_position'      => 6,
			'menu_icon'          => 'dashicons-building',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'author', 'page-attributes' ),
		);

		register_post_type( 'company', $args );
	}

	/**
	 * Register Important Date CPT
	 */
	private function register_important_date_post_type() {
		$labels = array(
			'name'               => _x( 'Important Dates', 'Post type general name', 'caelis' ),
			'singular_name'      => _x( 'Important Date', 'Post type singular name', 'caelis' ),
			'menu_name'          => _x( 'Dates', 'Admin Menu text', 'caelis' ),
			'add_new'            => __( 'Add New', 'caelis' ),
			'add_new_item'       => __( 'Add New Date', 'caelis' ),
			'edit_item'          => __( 'Edit Date', 'caelis' ),
			'new_item'           => __( 'New Date', 'caelis' ),
			'view_item'          => __( 'View Date', 'caelis' ),
			'search_items'       => __( 'Search Dates', 'caelis' ),
			'not_found'          => __( 'No dates found', 'caelis' ),
			'not_found_in_trash' => __( 'No dates found in Trash', 'caelis' ),
			'all_items'          => __( 'All Dates', 'caelis' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'important-dates',
			'query_var'          => false,
			'rewrite'            => false, // Disable rewrite rules - React Router handles routing
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 7,
			'menu_icon'          => 'dashicons-calendar-alt',
			'supports'           => array( 'title', 'editor', 'author' ),
		);

		register_post_type( 'important_date', $args );
	}

	/**
	 * Register custom post statuses for todos
	 *
	 * Todos use a linear state flow: Open → Awaiting Response → Completed
	 * Using post_status instead of meta fields for cleaner queries.
	 */
	private function register_todo_statuses() {
		register_post_status(
			'prm_open',
			array(
				'label'                     => _x( 'Open', 'Todo status', 'caelis' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'caelis' ),
			)
		);

		register_post_status(
			'prm_awaiting',
			array(
				'label'                     => _x( 'Awaiting Response', 'Todo status', 'caelis' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Awaiting <span class="count">(%s)</span>', 'Awaiting <span class="count">(%s)</span>', 'caelis' ),
			)
		);

		register_post_status(
			'prm_completed',
			array(
				'label'                     => _x( 'Completed', 'Todo status', 'caelis' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>', 'caelis' ),
			)
		);
	}

	/**
	 * Register Todo CPT
	 *
	 * Used for tracking todos/tasks related to people. Migrated from comment-based
	 * system to CPT for better query capabilities, visibility/workspace support,
	 * and consistent REST API patterns.
	 */
	private function register_todo_post_type() {
		$labels = array(
			'name'               => _x( 'Todos', 'Post type general name', 'caelis' ),
			'singular_name'      => _x( 'Todo', 'Post type singular name', 'caelis' ),
			'menu_name'          => _x( 'Todos', 'Admin Menu text', 'caelis' ),
			'add_new'            => __( 'Add New', 'caelis' ),
			'add_new_item'       => __( 'Add New Todo', 'caelis' ),
			'edit_item'          => __( 'Edit Todo', 'caelis' ),
			'new_item'           => __( 'New Todo', 'caelis' ),
			'view_item'          => __( 'View Todo', 'caelis' ),
			'search_items'       => __( 'Search Todos', 'caelis' ),
			'not_found'          => __( 'No todos found', 'caelis' ),
			'not_found_in_trash' => __( 'No todos found in Trash', 'caelis' ),
			'all_items'          => __( 'All Todos', 'caelis' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'todos',
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 8,
			'menu_icon'          => 'dashicons-yes-alt',
			'supports'           => array( 'title', 'editor', 'author' ),
		);

		register_post_type( 'prm_todo', $args );
	}

	/**
	 * Register Calendar Event CPT
	 *
	 * Used for caching calendar events synced from external calendars (Google, CalDAV).
	 * Not exposed via standard wp/v2 REST - uses custom endpoints only.
	 * No admin UI needed - events are managed via sync process.
	 */
	private function register_calendar_event_post_type() {
		$labels = array(
			'name'               => _x( 'Calendar Events', 'Post type general name', 'caelis' ),
			'singular_name'      => _x( 'Calendar Event', 'Post type singular name', 'caelis' ),
			'menu_name'          => _x( 'Calendar Events', 'Admin Menu text', 'caelis' ),
			'add_new'            => __( 'Add New', 'caelis' ),
			'add_new_item'       => __( 'Add New Event', 'caelis' ),
			'edit_item'          => __( 'Edit Event', 'caelis' ),
			'new_item'           => __( 'New Event', 'caelis' ),
			'view_item'          => __( 'View Event', 'caelis' ),
			'search_items'       => __( 'Search Events', 'caelis' ),
			'not_found'          => __( 'No events found', 'caelis' ),
			'not_found_in_trash' => __( 'No events found in Trash', 'caelis' ),
			'all_items'          => __( 'All Events', 'caelis' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => false, // No admin UI needed
			'show_in_menu'       => false,
			'show_in_rest'       => false, // Custom endpoints only
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'map_meta_cap'       => true,
			'has_archive'        => false,
			'hierarchical'       => false,
			'supports'           => array( 'title', 'author' ),
		);

		register_post_type( 'calendar_event', $args );
	}
}
