<?php
/**
 * Custom Post Types Registration
 */

namespace Stadion\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostTypes {

	public function __construct() {
		add_action( 'init', [ $this, 'register_post_types' ] );
	}

	/**
	 * Register all custom post types
	 */
	public function register_post_types() {
		$this->register_workspace_post_type();
		$this->register_workspace_invite_post_type();
		$this->register_person_post_type();
		$this->register_team_post_type();
		$this->register_important_date_post_type();
		$this->register_todo_statuses();
		$this->register_todo_post_type();
		$this->register_calendar_event_post_type();
		$this->register_feedback_post_type();
	}

	/**
	 * Register Workspace CPT
	 */
	private function register_workspace_post_type() {
		$labels = [
			'name'               => _x( 'Workspaces', 'Post type general name', 'stadion' ),
			'singular_name'      => _x( 'Workspace', 'Post type singular name', 'stadion' ),
			'menu_name'          => _x( 'Workspaces', 'Admin Menu text', 'stadion' ),
			'add_new'            => __( 'Add New', 'stadion' ),
			'add_new_item'       => __( 'Add New Workspace', 'stadion' ),
			'edit_item'          => __( 'Edit Workspace', 'stadion' ),
			'new_item'           => __( 'New Workspace', 'stadion' ),
			'view_item'          => __( 'View Workspace', 'stadion' ),
			'search_items'       => __( 'Search Workspaces', 'stadion' ),
			'not_found'          => __( 'No workspaces found', 'stadion' ),
			'not_found_in_trash' => __( 'No workspaces found in Trash', 'stadion' ),
			'all_items'          => __( 'All Workspaces', 'stadion' ),
		];

		$args = [
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
			'supports'           => [ 'title', 'editor', 'author', 'thumbnail' ],
		];

		register_post_type( 'workspace', $args );
	}

	/**
	 * Register Workspace Invite CPT
	 *
	 * Used for tracking workspace invitations sent to users via email.
	 * Not exposed via standard wp/v2 REST - uses custom endpoints only.
	 */
	private function register_workspace_invite_post_type() {
		$labels = [
			'name'               => _x( 'Workspace Invites', 'Post type general name', 'stadion' ),
			'singular_name'      => _x( 'Workspace Invite', 'Post type singular name', 'stadion' ),
			'menu_name'          => _x( 'Invites', 'Admin Menu text', 'stadion' ),
			'add_new'            => __( 'Add New', 'stadion' ),
			'add_new_item'       => __( 'Add New Invite', 'stadion' ),
			'edit_item'          => __( 'Edit Invite', 'stadion' ),
			'new_item'           => __( 'New Invite', 'stadion' ),
			'view_item'          => __( 'View Invite', 'stadion' ),
			'search_items'       => __( 'Search Invites', 'stadion' ),
			'not_found'          => __( 'No invites found', 'stadion' ),
			'not_found_in_trash' => __( 'No invites found in Trash', 'stadion' ),
			'all_items'          => __( 'All Invites', 'stadion' ),
		];

		$args = [
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
			'supports'           => [ 'title' ], // Title = email for easy identification
		];

		register_post_type( 'workspace_invite', $args );
	}

	/**
	 * Register Person CPT
	 */
	private function register_person_post_type() {
		$labels = [
			'name'               => _x( 'People', 'Post type general name', 'stadion' ),
			'singular_name'      => _x( 'Person', 'Post type singular name', 'stadion' ),
			'menu_name'          => _x( 'People', 'Admin Menu text', 'stadion' ),
			'add_new'            => __( 'Add New', 'stadion' ),
			'add_new_item'       => __( 'Add New Person', 'stadion' ),
			'edit_item'          => __( 'Edit Person', 'stadion' ),
			'new_item'           => __( 'New Person', 'stadion' ),
			'view_item'          => __( 'View Person', 'stadion' ),
			'search_items'       => __( 'Search People', 'stadion' ),
			'not_found'          => __( 'No people found', 'stadion' ),
			'not_found_in_trash' => __( 'No people found in Trash', 'stadion' ),
			'all_items'          => __( 'All People', 'stadion' ),
		];

		$args = [
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
			'supports'           => [ 'title', 'thumbnail', 'comments', 'author' ],
		];

		register_post_type( 'person', $args );
	}

	/**
	 * Register Team CPT
	 *
	 * Teams are synced from Sportlink and are read-only in this system.
	 */
	private function register_team_post_type() {
		$labels = [
			'name'               => _x( 'Teams', 'Post type general name', 'stadion' ),
			'singular_name'      => _x( 'Team', 'Post type singular name', 'stadion' ),
			'menu_name'          => _x( 'Teams', 'Admin Menu text', 'stadion' ),
			'add_new'            => __( 'Add New', 'stadion' ),
			'add_new_item'       => __( 'Add New Team', 'stadion' ),
			'edit_item'          => __( 'Edit Team', 'stadion' ),
			'new_item'           => __( 'New Team', 'stadion' ),
			'view_item'          => __( 'View Team', 'stadion' ),
			'search_items'       => __( 'Search Teams', 'stadion' ),
			'not_found'          => __( 'No teams found', 'stadion' ),
			'not_found_in_trash' => __( 'No teams found in Trash', 'stadion' ),
			'all_items'          => __( 'All Teams', 'stadion' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'teams',
			'query_var'          => false,
			'rewrite'            => false, // Disable rewrite rules - React Router handles routing
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => true, // Enable parent-child relationships
			'menu_position'      => 6,
			'menu_icon'          => 'dashicons-groups',
			'supports'           => [ 'title', 'editor', 'thumbnail', 'author', 'page-attributes' ],
		];

		register_post_type( 'team', $args );
	}

	/**
	 * Register Important Date CPT
	 */
	private function register_important_date_post_type() {
		$labels = [
			'name'               => _x( 'Important Dates', 'Post type general name', 'stadion' ),
			'singular_name'      => _x( 'Important Date', 'Post type singular name', 'stadion' ),
			'menu_name'          => _x( 'Dates', 'Admin Menu text', 'stadion' ),
			'add_new'            => __( 'Add New', 'stadion' ),
			'add_new_item'       => __( 'Add New Date', 'stadion' ),
			'edit_item'          => __( 'Edit Date', 'stadion' ),
			'new_item'           => __( 'New Date', 'stadion' ),
			'view_item'          => __( 'View Date', 'stadion' ),
			'search_items'       => __( 'Search Dates', 'stadion' ),
			'not_found'          => __( 'No dates found', 'stadion' ),
			'not_found_in_trash' => __( 'No dates found in Trash', 'stadion' ),
			'all_items'          => __( 'All Dates', 'stadion' ),
		];

		$args = [
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
			'supports'           => [ 'title', 'editor', 'author' ],
		];

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
			'stadion_open',
			[
				'label'                     => _x( 'Open', 'Todo status', 'stadion' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'stadion' ),
			]
		);

		register_post_status(
			'stadion_awaiting',
			[
				'label'                     => _x( 'Awaiting Response', 'Todo status', 'stadion' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Awaiting <span class="count">(%s)</span>', 'Awaiting <span class="count">(%s)</span>', 'stadion' ),
			]
		);

		register_post_status(
			'stadion_completed',
			[
				'label'                     => _x( 'Completed', 'Todo status', 'stadion' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>', 'stadion' ),
			]
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
		$labels = [
			'name'               => _x( 'Todos', 'Post type general name', 'stadion' ),
			'singular_name'      => _x( 'Todo', 'Post type singular name', 'stadion' ),
			'menu_name'          => _x( 'Todos', 'Admin Menu text', 'stadion' ),
			'add_new'            => __( 'Add New', 'stadion' ),
			'add_new_item'       => __( 'Add New Todo', 'stadion' ),
			'edit_item'          => __( 'Edit Todo', 'stadion' ),
			'new_item'           => __( 'New Todo', 'stadion' ),
			'view_item'          => __( 'View Todo', 'stadion' ),
			'search_items'       => __( 'Search Todos', 'stadion' ),
			'not_found'          => __( 'No todos found', 'stadion' ),
			'not_found_in_trash' => __( 'No todos found in Trash', 'stadion' ),
			'all_items'          => __( 'All Todos', 'stadion' ),
		];

		$args = [
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
			'supports'           => [ 'title', 'editor', 'author' ],
		];

		register_post_type( 'stadion_todo', $args );
	}

	/**
	 * Register Calendar Event CPT
	 *
	 * Used for caching calendar events synced from external calendars (Google, CalDAV).
	 * Not exposed via standard wp/v2 REST - uses custom endpoints only.
	 * No admin UI needed - events are managed via sync process.
	 */
	private function register_calendar_event_post_type() {
		$labels = [
			'name'               => _x( 'Calendar Events', 'Post type general name', 'stadion' ),
			'singular_name'      => _x( 'Calendar Event', 'Post type singular name', 'stadion' ),
			'menu_name'          => _x( 'Calendar Events', 'Admin Menu text', 'stadion' ),
			'add_new'            => __( 'Add New', 'stadion' ),
			'add_new_item'       => __( 'Add New Event', 'stadion' ),
			'edit_item'          => __( 'Edit Event', 'stadion' ),
			'new_item'           => __( 'New Event', 'stadion' ),
			'view_item'          => __( 'View Event', 'stadion' ),
			'search_items'       => __( 'Search Events', 'stadion' ),
			'not_found'          => __( 'No events found', 'stadion' ),
			'not_found_in_trash' => __( 'No events found in Trash', 'stadion' ),
			'all_items'          => __( 'All Events', 'stadion' ),
		];

		$args = [
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
			'supports'           => [ 'title', 'author' ],
		];

		register_post_type( 'calendar_event', $args );
	}

	/**
	 * Register Feedback CPT
	 *
	 * Used for collecting user feedback (bug reports and feature requests).
	 * Not workspace-scoped - feedback is global per installation.
	 */
	private function register_feedback_post_type() {
		$labels = [
			'name'               => _x( 'Feedback', 'Post type general name', 'stadion' ),
			'singular_name'      => _x( 'Feedback', 'Post type singular name', 'stadion' ),
			'menu_name'          => _x( 'Feedback', 'Admin Menu text', 'stadion' ),
			'add_new'            => __( 'Add New', 'stadion' ),
			'add_new_item'       => __( 'Add New Feedback', 'stadion' ),
			'edit_item'          => __( 'Edit Feedback', 'stadion' ),
			'new_item'           => __( 'New Feedback', 'stadion' ),
			'view_item'          => __( 'View Feedback', 'stadion' ),
			'search_items'       => __( 'Search Feedback', 'stadion' ),
			'not_found'          => __( 'No feedback found', 'stadion' ),
			'not_found_in_trash' => __( 'No feedback found in Trash', 'stadion' ),
			'all_items'          => __( 'All Feedback', 'stadion' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'feedback',
			'query_var'          => false,
			'rewrite'            => false,
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 26,
			'menu_icon'          => 'dashicons-megaphone',
			'supports'           => [ 'title', 'editor', 'author' ],
		];

		register_post_type( 'stadion_feedback', $args );
	}
}
