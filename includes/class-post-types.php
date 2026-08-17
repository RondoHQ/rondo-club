<?php
/**
 * Custom Post Types Registration
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PostTypes {

	/**
	 * Capability slugs for each custom post type.
	 *
	 * Every CPT must use its own primitives. Reusing WordPress' generic `post`
	 * capabilities lets any role with `edit_posts` create or mutate every
	 * REST-exposed Rondo record, including invoices and discipline cases.
	 */
	public const CAPABILITY_DOMAINS = [
		'person'              => [ 'person', 'people' ],
		'team'                => [ 'team', 'teams' ],
		'commissie'           => [ 'commissie', 'commissies' ],
		'rondo_clothing_item' => [ 'clothing_item', 'clothing_items' ],
		'rondo_clothing_txn'  => [ 'clothing_transaction', 'clothing_transactions' ],
		'rondo_todo'          => [ 'todo', 'todos' ],
		'calendar_event'      => [ 'calendar_event', 'calendar_events' ],
		'rondo_feedback'      => [ 'feedback_item', 'feedback_items' ],
		'discipline_case'     => [ 'discipline_case', 'discipline_cases' ],
		'rondo_invoice'       => [ 'invoice', 'invoices' ],
		'rondo_display'       => [ 'display', 'displays' ],
		'rondo_signage_item'  => [ 'signage_item', 'signage_items' ],
		'rondo_signage_list'  => [ 'signage_playlist', 'signage_playlists' ],
		'rondo_sponsor'       => [ 'sponsor', 'sponsors' ],
		'dienst_type'         => [ 'dienst_type', 'dienst_types' ],
		'shift_template'      => [ 'shift_template', 'shift_templates' ],
		'dienst_shift'        => [ 'dienst_shift', 'dienst_shifts' ],
		'taakuitleg'          => [ 'taakuitleg', 'taakuitleg_items' ],
	];

	/**
	 * Build the complete primitive/meta capability map for a Rondo CPT.
	 *
	 * @param string $post_type Registered post type.
	 * @return array<string, string>
	 */
	public static function capability_map( string $post_type ): array {
		if ( ! isset( self::CAPABILITY_DOMAINS[ $post_type ] ) ) {
			return [];
		}

		[ $singular, $plural ] = self::CAPABILITY_DOMAINS[ $post_type ];

		return [
			'edit_post'              => 'edit_rondo_' . $singular,
			'read_post'              => 'read_rondo_' . $singular,
			'delete_post'            => 'delete_rondo_' . $singular,
			'edit_posts'             => 'edit_rondo_' . $plural,
			'edit_others_posts'      => 'edit_others_rondo_' . $plural,
			'publish_posts'          => 'publish_rondo_' . $plural,
			'read_private_posts'     => 'read_private_rondo_' . $plural,
			'delete_posts'           => 'delete_rondo_' . $plural,
			'delete_private_posts'   => 'delete_private_rondo_' . $plural,
			'delete_published_posts' => 'delete_published_rondo_' . $plural,
			'delete_others_posts'    => 'delete_others_rondo_' . $plural,
			'edit_private_posts'     => 'edit_private_rondo_' . $plural,
			'edit_published_posts'   => 'edit_published_rondo_' . $plural,
			'create_posts'           => 'create_rondo_' . $plural,
			'read'                   => 'read_rondo_' . $plural,
		];
	}

	/**
	 * Registration arguments shared by every Rondo CPT.
	 *
	 * @param string $post_type Registered post type.
	 * @return array<string, mixed>
	 */
	private static function capability_args( string $post_type ): array {
		return [
			'capabilities' => self::capability_map( $post_type ),
			'map_meta_cap' => true,
		];
	}

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
		$this->register_clothing_item_post_type();
		$this->register_clothing_assignment_post_type();
		$this->register_todo_statuses();
		$this->register_todo_post_type();
		$this->register_calendar_event_post_type();
		$this->register_feedback_post_type();
		$this->register_discipline_case_post_type();
		$this->register_invoice_statuses();
		$this->register_invoice_post_type();
		$this->register_display_post_type();
		$this->register_signage_item_post_type();
		$this->register_signage_playlist_post_type();
		$this->register_sponsor_post_type();
		$this->register_dienst_type_post_type();
		$this->register_shift_template_post_type();
		$this->register_dienst_shift_post_type();
		$this->register_taakuitleg_post_type();
	}

	/** Register private sponsor companies managed through Rondo's REST API. */
	private function register_sponsor_post_type() {
		$args = array_merge(
			[
				'labels'             => [
					'name'          => __( 'Sponsorbedrijven', 'rondo' ),
					'singular_name' => __( 'Sponsorbedrijf', 'rondo' ),
				],
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => [ 'title', 'author', 'thumbnail' ],
			],
			self::capability_args( 'rondo_sponsor' )
		);

		register_post_type( 'rondo_sponsor', $args );
	}

	/**
	 * Register the private narrowcasting display CPT.
	 *
	 * Player credentials and operational state are stored as native fields on
	 * these posts. The records are intentionally available only through Rondo's
	 * dedicated REST controller: exposing the generic wp/v2 endpoint would make
	 * it too easy to leak internal device metadata.
	 */
	private function register_display_post_type() {
		$labels = [
			'name'          => __( 'Narrowcasting displays', 'rondo' ),
			'singular_name' => __( 'Narrowcasting display', 'rondo' ),
		];

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'rondo_display' )
		);

		register_post_type( 'rondo_display', $args );
	}

	/** Register private playable narrowcasting scenes. */
	private function register_signage_item_post_type() {
		$args = array_merge(
			[
				'labels'             => [
					'name'          => __( 'Club TV-content', 'rondo' ),
					'singular_name' => __( 'Club TV-item', 'rondo' ),
				],
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'rondo_signage_item' )
		);

		register_post_type( 'rondo_signage_item', $args );
	}

	/** Register private ordered narrowcasting playlists. */
	private function register_signage_playlist_post_type() {
		$args = array_merge(
			[
				'labels'             => [
					'name'          => __( 'Club TV-playlists', 'rondo' ),
					'singular_name' => __( 'Club TV-playlist', 'rondo' ),
				],
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false,
				'show_in_menu'       => false,
				'show_in_rest'       => false,
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'rondo_signage_list' )
		);

		register_post_type( 'rondo_signage_list', $args );
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

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'people',
				'query_var'          => false,
				'rewrite'            => false, // Disable rewrite rules - React Router handles routing
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 5,
				'menu_icon'          => 'dashicons-groups',
				'supports'           => [ 'title', 'thumbnail', 'comments', 'author', 'custom-fields' ],
			],
			self::capability_args( 'person' )
			);

		register_post_type( 'person', $args );

		// Person meta: string fields exposed in REST (VOG, Sportlink, primary team).
		$person_string_meta = [
			'datum-vog',
			'vog_email_sent_date',
			'vog_justis_submitted_date',
			'vog_reminder_sent_date',
			'vrijwilliger-sinds',
			'team',
			'datum-iva',
			'vergoeding_reden',
			'vergoeding_tot',
			'vrijstelling_reden',
			'vrijstelling_seizoen',
		];
		foreach ( $person_string_meta as $key ) {
			register_post_meta(
				'person',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
				]
			);
		}

		// Person meta: boolean flags exposed in REST.
		$person_bool_meta = [
			'betaalde_vrijwilliger',
			'vrijgesteld_handmatig',
			'iva-approved',
		];
		foreach ( $person_bool_meta as $key ) {
			register_post_meta(
				'person',
				$key,
				[
					'type'              => 'boolean',
					'single'            => true,
					'show_in_rest'      => true,
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
				]
			);
		}

		// Contributie exclusion: boolean, write gated by 'financieel'.
		register_post_meta(
			'person',
			'_exclude_from_contributie',
			[
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
				'auth_callback'     => function () {
					return current_user_can( 'financieel' );
				},
			]
		);
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

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'teams',
				'query_var'          => false,
				'rewrite'            => false, // Disable rewrite rules - React Router handles routing
				'has_archive'        => false,
				'hierarchical'       => true, // Enable parent-child relationships
				'menu_position'      => 6,
				'menu_icon'          => 'dashicons-groups',
				'supports'           => [ 'title', 'editor', 'thumbnail', 'author', 'page-attributes' ],
			],
			self::capability_args( 'team' )
			);

		register_post_type( 'team', $args );

		// Volunteer-policy kickoff tracking (#13): per-team status of Guido's
		// vrijwilligersbeleid-gesprek aan het begin van het seizoen.
		register_post_meta(
			'team',
			'kickoff_done_at',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_text_field',
			]
		);
		register_post_meta(
			'team',
			'kickoff_notes',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_textarea_field',
			]
		);
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

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'commissies',
				'query_var'          => false,
				'rewrite'            => false, // Disable rewrite rules - React Router handles routing
				'has_archive'        => false,
				'hierarchical'       => true, // Enable parent-child relationships
				'menu_position'      => 7,
				'menu_icon'          => 'dashicons-businessperson',
				'supports'           => [ 'title', 'editor', 'thumbnail', 'author', 'page-attributes' ],
			],
			self::capability_args( 'commissie' )
			);

		register_post_type( 'commissie', $args );
	}

	/**
	 * Register Clothing Item CPT
	 */
	private function register_clothing_item_post_type() {
		$labels = [
			'name'               => _x( 'Kledingitems', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Kledingitem', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Kledingitems', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Clothing Item', 'rondo' ),
			'edit_item'          => __( 'Edit Clothing Item', 'rondo' ),
			'new_item'           => __( 'New Clothing Item', 'rondo' ),
			'view_item'          => __( 'View Clothing Item', 'rondo' ),
			'search_items'       => __( 'Search Clothing Items', 'rondo' ),
			'not_found'          => __( 'No clothing items found', 'rondo' ),
			'not_found_in_trash' => __( 'No clothing items found in Trash', 'rondo' ),
			'all_items'          => __( 'All Clothing Items', 'rondo' ),
		];

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'clothing-items',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 8,
				'menu_icon'          => 'dashicons-tickets-alt',
				'supports'           => [ 'title', 'thumbnail', 'author', 'custom-fields' ],
			],
			self::capability_args( 'rondo_clothing_item' )
			);

		register_post_type( 'rondo_clothing_item', $args );
	}

	/**
	 * Register Clothing Assignment CPT
	 */
	private function register_clothing_assignment_post_type() {
		$labels = [
			'name'               => _x( 'Kledinguitgiftes', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Kledinguitgifte', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Kledinguitgiftes', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Clothing Assignment', 'rondo' ),
			'edit_item'          => __( 'Edit Clothing Assignment', 'rondo' ),
			'new_item'           => __( 'New Clothing Assignment', 'rondo' ),
			'view_item'          => __( 'View Clothing Assignment', 'rondo' ),
			'search_items'       => __( 'Search Clothing Assignments', 'rondo' ),
			'not_found'          => __( 'No clothing assignments found', 'rondo' ),
			'not_found_in_trash' => __( 'No clothing assignments found in Trash', 'rondo' ),
			'all_items'          => __( 'All Clothing Assignments', 'rondo' ),
		];

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'clothing-assignments',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 9,
				'menu_icon'          => 'dashicons-clipboard',
				'supports'           => [ 'title', 'author', 'custom-fields' ],
			],
			self::capability_args( 'rondo_clothing_txn' )
			);

		register_post_type( 'rondo_clothing_txn', $args );
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
				// translators: %s is the number of open todos.
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
				// translators: %s is the number of todos awaiting response.
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
				// translators: %s is the number of completed todos.
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

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'todos',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 8,
				'menu_icon'          => 'dashicons-yes-alt',
				'supports'           => [ 'title', 'editor', 'author' ],
			],
			self::capability_args( 'rondo_todo' )
			);

		register_post_type( 'rondo_todo', $args );
	}

	/**
	 * Register Calendar Event CPT
	 *
	 * Used for caching calendar events synced from external calendars.
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

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => false, // No admin UI needed
				'show_in_menu'       => false,
				'show_in_rest'       => false, // Custom endpoints only
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'calendar_event' )
			);

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

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'feedback',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 26,
				'menu_icon'          => 'dashicons-megaphone',
				'supports'           => [ 'title', 'editor', 'author' ],
			],
			self::capability_args( 'rondo_feedback' )
			);

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

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'discipline-cases',
				'query_var'          => false,
				'rewrite'            => false, // React Router handles routing
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 9,
				'menu_icon'          => 'dashicons-warning',
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'discipline_case' )
			);

		register_post_type( 'discipline_case', $args );
	}

	/**
	 * Register custom post statuses for invoices
	 *
	 * Invoices use a lifecycle state flow: Draft → Sent → Paid/Overdue
	 * Using post_status for cleaner queries and native WordPress status handling.
	 */
	private function register_invoice_statuses() {
		register_post_status(
			'rondo_draft',
			[
				'label'                     => _x( 'Concept', 'Invoice status', 'rondo' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s is the number of draft invoices.
				'label_count'               => _n_noop( 'Concept <span class="count">(%s)</span>', 'Concept <span class="count">(%s)</span>', 'rondo' ),
			]
		);

		register_post_status(
			'rondo_sent',
			[
				'label'                     => _x( 'Verstuurd', 'Invoice status', 'rondo' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s is the number of sent invoices.
				'label_count'               => _n_noop( 'Verstuurd <span class="count">(%s)</span>', 'Verstuurd <span class="count">(%s)</span>', 'rondo' ),
			]
		);

		register_post_status(
			'rondo_paid',
			[
				'label'                     => _x( 'Betaald', 'Invoice status', 'rondo' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s is the number of paid invoices.
				'label_count'               => _n_noop( 'Betaald <span class="count">(%s)</span>', 'Betaald <span class="count">(%s)</span>', 'rondo' ),
			]
		);

		register_post_status(
			'rondo_overdue',
			[
				'label'                     => _x( 'Verlopen', 'Invoice status', 'rondo' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s is the number of overdue invoices.
				'label_count'               => _n_noop( 'Verlopen <span class="count">(%s)</span>', 'Verlopen <span class="count">(%s)</span>', 'rondo' ),
			]
		);

		register_post_status(
			'rondo_cancelled',
			[
				'label'                     => _x( 'Vervallen', 'Invoice status', 'rondo' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s is the number of cancelled invoices.
				'label_count'               => _n_noop( 'Vervallen <span class="count">(%s)</span>', 'Vervallen <span class="count">(%s)</span>', 'rondo' ),
			]
		);
	}

	/**
	 * Register Invoice CPT
	 *
	 * Invoices track financial charges to members for discipline cases or other fees.
	 * Each invoice is linked to a person and contains line items with associated discipline cases.
	 */
	private function register_invoice_post_type() {
		$labels = [
			'name'               => _x( 'Facturen', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Factuur', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Facturen', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Factuur', 'rondo' ),
			'edit_item'          => __( 'Edit Factuur', 'rondo' ),
			'new_item'           => __( 'New Factuur', 'rondo' ),
			'view_item'          => __( 'View Factuur', 'rondo' ),
			'search_items'       => __( 'Search Facturen', 'rondo' ),
			'not_found'          => __( 'No facturen found', 'rondo' ),
			'not_found_in_trash' => __( 'No facturen found in Trash', 'rondo' ),
			'all_items'          => __( 'All Facturen', 'rondo' ),
		];

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'invoices',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 10,
				'menu_icon'          => 'dashicons-media-text',
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'rondo_invoice' )
			);

		register_post_type( 'rondo_invoice', $args );
	}

	/**
	 * Register Dienst Type CPT
	 *
	 * Catalog of volunteer task categories (terreinmeester, kantine bar/keuken,
	 * schoonmaak, terreinonderhoud, …). Admin-only — there is no public view.
	 * Consumed by shift_template / dienst_shift for scheduling.
	 */
	private function register_dienst_type_post_type() {
		$labels = [
			'name'               => _x( 'Inschrijftaken', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Inschrijftaak', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Inschrijftaken', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Inschrijftaak', 'rondo' ),
			'edit_item'          => __( 'Edit Inschrijftaak', 'rondo' ),
			'new_item'           => __( 'New Inschrijftaak', 'rondo' ),
			'view_item'          => __( 'View Inschrijftaak', 'rondo' ),
			'search_items'       => __( 'Search Inschrijftaken', 'rondo' ),
			'not_found'          => __( 'No inschrijftaken found', 'rondo' ),
			'not_found_in_trash' => __( 'No inschrijftaken found in Trash', 'rondo' ),
			'all_items'          => __( 'All Inschrijftaken', 'rondo' ),
		];

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'dienst-types',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 11,
				'menu_icon'          => 'dashicons-clipboard',
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'dienst_type' )
			);

		register_post_type( 'dienst_type', $args );

		$dienst_type_bool_meta = [
			'vog_required',
			'iva_required',
			'sleutel_involved',
		];
		foreach ( $dienst_type_bool_meta as $key ) {
			register_post_meta(
				'dienst_type',
				$key,
				[
					'type'              => 'boolean',
					'single'            => true,
					'show_in_rest'      => true,
					'default'           => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
				]
			);
		}

		register_post_meta(
			'dienst_type',
			'default_capacity',
			[
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 1,
				'sanitize_callback' => 'absint',
			]
		);

		register_post_meta(
			'dienst_type',
			'color',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '#6b7280',
				'sanitize_callback' => 'sanitize_hex_color',
			]
		);

		register_post_meta(
			'dienst_type',
			'description',
			[
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => '',
				'sanitize_callback' => 'sanitize_textarea_field',
			]
		);

		register_post_meta(
			'dienst_type',
			'required_pool',
			[
				'type'              => 'integer',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => 0,
				'sanitize_callback' => 'absint',
			]
		);

		$dienst_type_text_meta = [
			'reminder_email_subject'                 => 'sanitize_text_field',
			'reminder_email_body'                    => 'sanitize_textarea_field',
			'cancellation_early_email_subject'       => 'sanitize_text_field',
			'cancellation_early_email_body'          => 'sanitize_textarea_field',
			'cancellation_last_minute_email_subject' => 'sanitize_text_field',
			'cancellation_last_minute_email_body'    => 'sanitize_textarea_field',
			'survey_email_subject'                   => 'sanitize_text_field',
			'survey_email_body'                      => 'sanitize_textarea_field',
			'survey_url'                             => 'esc_url_raw',
		];
		foreach ( $dienst_type_text_meta as $key => $sanitize_callback ) {
			register_post_meta(
				'dienst_type',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'default'           => '',
					'sanitize_callback' => $sanitize_callback,
				]
			);
		}
	}

	/**
	 * Register Shift Template CPT
	 *
	 * Seasonal recurring shift rules (e.g. "every Saturday 7:30–12, Kantine bar,
	 * capacity 2"). Template-expander cron expands these into concrete
	 * `dienst_shift` records for an upcoming window.
	 */
	private function register_shift_template_post_type() {
		$labels = [
			'name'               => _x( 'Inschrijftaaksjablonen', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Inschrijftaaksjabloon', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Inschrijftaaksjablonen', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Inschrijftaaksjabloon', 'rondo' ),
			'edit_item'          => __( 'Edit Inschrijftaaksjabloon', 'rondo' ),
			'new_item'           => __( 'New Inschrijftaaksjabloon', 'rondo' ),
			'view_item'          => __( 'View Inschrijftaaksjabloon', 'rondo' ),
			'search_items'       => __( 'Search Inschrijftaaksjablonen', 'rondo' ),
			'not_found'          => __( 'No inschrijftaaksjablonen found', 'rondo' ),
			'not_found_in_trash' => __( 'No inschrijftaaksjablonen found in Trash', 'rondo' ),
			'all_items'          => __( 'All Inschrijftaaksjablonen', 'rondo' ),
		];

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'shift-templates',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 12,
				'menu_icon'          => 'dashicons-calendar-alt',
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'shift_template' )
			);

		register_post_type( 'shift_template', $args );

		$shift_template_int_meta = [
			'dienst_type_id',
			'day_of_week',
			'capacity',
		];
		foreach ( $shift_template_int_meta as $key ) {
			register_post_meta(
				'shift_template',
				$key,
				[
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
				]
			);
		}

		$shift_template_string_meta = [
			'start_time',
			'end_time',
			'active_from',
			'active_until',
			'notes',
		];
		foreach ( $shift_template_string_meta as $key ) {
			register_post_meta(
				'shift_template',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
				]
			);
		}

		// Sjabloon-niveau IVA-override; expander schrijft deze waarde door naar
		// elke uitgerolde dienst_shift.
		register_post_meta(
			'shift_template',
			'iva_waived',
			[
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			]
		);
	}

	/**
	 * Register Dienst Shift CPT
	 *
	 * A concrete scheduled volunteer shift in time. Either expanded from a
	 * shift_template by the cron expander, or created ad-hoc by an admin
	 * (e.g. an evening match that needs extra bar staff).
	 */
	private function register_dienst_shift_post_type() {
		$labels = [
			'name'               => _x( 'Inschrijftaken', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Inschrijftaak', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Inschrijftaken', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Inschrijftaak', 'rondo' ),
			'edit_item'          => __( 'Edit Inschrijftaak', 'rondo' ),
			'new_item'           => __( 'New Inschrijftaak', 'rondo' ),
			'view_item'          => __( 'View Inschrijftaak', 'rondo' ),
			'search_items'       => __( 'Search Inschrijftaken', 'rondo' ),
			'not_found'          => __( 'No inschrijftaken found', 'rondo' ),
			'not_found_in_trash' => __( 'No inschrijftaken found in Trash', 'rondo' ),
			'all_items'          => __( 'All Inschrijftaken', 'rondo' ),
		];

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'dienst-shifts',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 13,
				'menu_icon'          => 'dashicons-clock',
				'supports'           => [ 'title', 'author' ],
			],
			self::capability_args( 'dienst_shift' )
			);

		register_post_type( 'dienst_shift', $args );

		$shift_int_meta = [
			'dienst_type_id',
			'template_id',
			'capacity',
		];
		foreach ( $shift_int_meta as $key ) {
			register_post_meta(
				'dienst_shift',
				$key,
				[
					'type'              => 'integer',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'absint',
				]
			);
		}

		$shift_string_meta = [
			'start_datetime',
			'end_datetime',
			'status',
			'notes',
		];
		foreach ( $shift_string_meta as $key ) {
			register_post_meta(
				'dienst_shift',
				$key,
				[
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
				]
			);
		}

		// Per-dienst override op het IVA-vereiste van het diensttype. Use case:
		// kantine-bardienst op zaterdag voor 15:00 — geen alcoholschenking, dus
		// IVA niet nodig ook al staat 'iva_required' op het diensttype.
		register_post_meta(
			'dienst_shift',
			'iva_waived',
			[
				'type'              => 'boolean',
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			]
		);

		// Assigned persons as a serialized array of post IDs.
		register_post_meta(
			'dienst_shift',
			'assigned_persons',
			[
				'type'         => 'array',
				'single'       => true,
				'show_in_rest' => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
				'default'      => [],
			]
		);
	}

	/**
	 * Register Taakuitleg CPT
	 *
	 * Volunteer-facing task instructions ("how to use and clean the frying pan").
	 * Each entry is a rich-text explanation with inline images, linked to one or
	 * more dienst_types. A printable QR code points at the public read-only page
	 * at /uitleg/{slug} (see PublicTaakuitlegPage) so a volunteer can scan a
	 * sticker without logging in.
	 *
	 * `public`/`publicly_queryable` are false — the CPT carries no SEO surface
	 * and is not exposed through WordPress' own routing. The public view is
	 * served by our own rewrite rule, exactly like the payment landing page.
	 * Editing happens in the React SPA (gated by the `vrijwilligers` capability).
	 */
	private function register_taakuitleg_post_type() {
		$labels = [
			'name'               => _x( 'Taakuitleg', 'Post type general name', 'rondo' ),
			'singular_name'      => _x( 'Taakuitleg', 'Post type singular name', 'rondo' ),
			'menu_name'          => _x( 'Taakuitleg', 'Admin Menu text', 'rondo' ),
			'add_new'            => __( 'Add New', 'rondo' ),
			'add_new_item'       => __( 'Add New Taakuitleg', 'rondo' ),
			'edit_item'          => __( 'Edit Taakuitleg', 'rondo' ),
			'new_item'           => __( 'New Taakuitleg', 'rondo' ),
			'view_item'          => __( 'View Taakuitleg', 'rondo' ),
			'search_items'       => __( 'Search Taakuitleg', 'rondo' ),
			'not_found'          => __( 'No taakuitleg found', 'rondo' ),
			'not_found_in_trash' => __( 'No taakuitleg found in Trash', 'rondo' ),
			'all_items'          => __( 'All Taakuitleg', 'rondo' ),
		];

		$args = array_merge(
			[
				'labels'             => $labels,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'rest_base'          => 'taakuitleg',
				'query_var'          => false,
				'rewrite'            => false,
				'has_archive'        => false,
				'hierarchical'       => false,
				'menu_position'      => 14,
				'menu_icon'          => 'dashicons-media-document',
				// `editor` stores the rich-text body (with inline images) in
				// post_content, exposed as the `content` field over REST; `revisions`
				// gives us a free edit history and the modified date.
				'supports'           => [ 'title', 'editor', 'author', 'revisions' ],
			],
			self::capability_args( 'taakuitleg' )
			);

		register_post_type( 'taakuitleg', $args );
	}
}
