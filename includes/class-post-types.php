<?php
/**
 * Custom Post Types Registration
 */

namespace Rondo\Core;

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
		$this->register_person_post_type();
		$this->register_team_post_type();
		$this->register_commissie_post_type();
		$this->register_todo_statuses();
		$this->register_todo_post_type();
		$this->register_calendar_event_post_type();
		$this->register_feedback_post_type();
		$this->register_discipline_case_post_type();
	}

	/**
	 * Register Person CPT
	 */
	private function register_person_post_type() {
		$labels = [
			'name'               => _x( 'People', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Person', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'People', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Person', 'rondo' ),
			'edit_item'          => __( 'Edit Person', 'rondo' ),
			'new_item'           => __( 'New Person', 'rondo' ),
			'view_item'          => __( 'View Person', 'rondo' ),
			'search_items'       => __( 'Search People', 'rondo' ),
			'not_found'          => __( 'No people found', 'rondo' ),
			'not_found_in_trash' => __( 'No people found in Trash', 'rondo' ),
			'all_items'          => __( 'All People', 'rondo' ),
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
			'name'               => _x( 'Teams', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Team', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Teams', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Team', 'rondo' ),
			'edit_item'          => __( 'Edit Team', 'rondo' ),
			'new_item'           => __( 'New Team', 'rondo' ),
			'view_item'          => __( 'View Team', 'rondo' ),
			'search_items'       => __( 'Search Teams', 'rondo' ),
			'not_found'          => __( 'No teams found', 'rondo' ),
			'not_found_in_trash' => __( 'No teams found in Trash', 'rondo' ),
			'all_items'          => __( 'All Teams', 'rondo' ),
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
	 * Register Commissie CPT
	 *
	 * Commissies (committees) are synced from Sportlink and are read-only in this system.
	 */
	private function register_commissie_post_type() {
		$labels = [
			'name'               => _x( 'Commissies', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Commissie', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Commissies', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Commissie', 'rondo' ),
			'edit_item'          => __( 'Edit Commissie', 'rondo' ),
			'new_item'           => __( 'New Commissie', 'rondo' ),
			'view_item'          => __( 'View Commissie', 'rondo' ),
			'search_items'       => __( 'Search Commissies', 'rondo' ),
			'not_found'          => __( 'No commissies found', 'rondo' ),
			'not_found_in_trash' => __( 'No commissies found in Trash', 'rondo' ),
			'all_items'          => __( 'All Commissies', 'rondo' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'commissies',
			'query_var'          => false,
			'rewrite'            => false, // Disable rewrite rules - React Router handles routing
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => true, // Enable parent-child relationships
			'menu_position'      => 7,
			'menu_icon'          => 'dashicons-businessperson',
			'supports'           => [ 'title', 'editor', 'thumbnail', 'author', 'page-attributes' ],
		];

		register_post_type( 'commissie', $args );
	}

	/**
	 * Register custom post statuses for todos
	 *
	 * Todos use a linear state flow: Open → Awaiting Response → Completed
	 * Using post_status instead of meta fields for cleaner queries.
	 */
	private function register_todo_statuses() {
		register_post_status(
			'rondo_open',
			[
				'label'                     => _x( 'Open', 'Todo status', 'rondo' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'rondo' ),
			]
		);

		register_post_status(
			'rondo_awaiting',
			[
				'label'                     => _x( 'Awaiting Response', 'Todo status', 'rondo' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Awaiting <span class="count">(%s)</span>', 'Awaiting <span class="count">(%s)</span>', 'rondo' ),
			]
		);

		register_post_status(
			'rondo_completed',
			[
				'label'                     => _x( 'Completed', 'Todo status', 'rondo' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Completed <span class="count">(%s)</span>', 'Completed <span class="count">(%s)</span>', 'rondo' ),
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
			'name'               => _x( 'Todos', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Todo', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Todos', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Todo', 'rondo' ),
			'edit_item'          => __( 'Edit Todo', 'rondo' ),
			'new_item'           => __( 'New Todo', 'rondo' ),
			'view_item'          => __( 'View Todo', 'rondo' ),
			'search_items'       => __( 'Search Todos', 'rondo' ),
			'not_found'          => __( 'No todos found', 'rondo' ),
			'not_found_in_trash' => __( 'No todos found in Trash', 'rondo' ),
			'all_items'          => __( 'All Todos', 'rondo' ),
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

		register_post_type( 'rondo_todo', $args );
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
			'name'               => _x( 'Calendar Events', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Calendar Event', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Calendar Events', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Event', 'rondo' ),
			'edit_item'          => __( 'Edit Event', 'rondo' ),
			'new_item'           => __( 'New Event', 'rondo' ),
			'view_item'          => __( 'View Event', 'rondo' ),
			'search_items'       => __( 'Search Events', 'rondo' ),
			'not_found'          => __( 'No events found', 'rondo' ),
			'not_found_in_trash' => __( 'No events found in Trash', 'rondo' ),
			'all_items'          => __( 'All Events', 'rondo' ),
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
			'name'               => _x( 'Feedback', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Feedback', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Feedback', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Feedback', 'rondo' ),
			'edit_item'          => __( 'Edit Feedback', 'rondo' ),
			'new_item'           => __( 'New Feedback', 'rondo' ),
			'view_item'          => __( 'View Feedback', 'rondo' ),
			'search_items'       => __( 'Search Feedback', 'rondo' ),
			'not_found'          => __( 'No feedback found', 'rondo' ),
			'not_found_in_trash' => __( 'No feedback found in Trash', 'rondo' ),
			'all_items'          => __( 'All Feedback', 'rondo' ),
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

		register_post_type( 'rondo_feedback', $args );
	}

	/**
	 * Register Discipline Case CPT
	 *
	 * Discipline cases are synced from Sportlink and track sports disciplinary actions.
	 * Each case is linked to a person and includes details about the incident, charges, and sanctions.
	 */
	private function register_discipline_case_post_type() {
		$labels = [
			'name'               => _x( 'Tuchtzaken', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Tuchtzaak', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Tuchtzaken', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Tuchtzaak', 'rondo' ),
			'edit_item'          => __( 'Edit Tuchtzaak', 'rondo' ),
			'new_item'           => __( 'New Tuchtzaak', 'rondo' ),
			'view_item'          => __( 'View Tuchtzaak', 'rondo' ),
			'search_items'       => __( 'Search Tuchtzaken', 'rondo' ),
			'not_found'          => __( 'No tuchtzaken found', 'rondo' ),
			'not_found_in_trash' => __( 'No tuchtzaken found in Trash', 'rondo' ),
			'all_items'          => __( 'All Tuchtzaken', 'rondo' ),
		];

		$args = [
			'labels'             => $labels,
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'rest_base'          => 'discipline-cases',
			'query_var'          => false,
			'rewrite'            => false, // React Router handles routing
			'capability_type'    => 'post',
			'has_archive'        => false,
			'hierarchical'       => false,
			'menu_position'      => 9,
			'menu_icon'          => 'dashicons-warning',
			'supports'           => [ 'title', 'author' ],
		];

		register_post_type( 'discipline_case', $args );
	}
}
