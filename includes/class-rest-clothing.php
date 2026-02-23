<?php
/**
 * REST API Endpoints for Clothing Management.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Clothing extends Base {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/clothing/items',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_items' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/clothing/items/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/clothing/assignments',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_assignments' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_assignment' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/clothing/person/(?P<person_id>\d+)/profile',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_person_profile' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/clothing/overview',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_overview' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/clothing/export',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'export_assignments_csv' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/clothing/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'check_clothing_permission' ],
				],
			]
		);
	}

	/**
	 * Get clothing settings with defaults.
	 *
	 * @return array
	 */
	private function get_clothing_settings() {
		$defaults = [
			'eligibility_enabled' => true,
			'cooldown_seasons' => 3,
			'current_season'   => $this->guess_current_season(),
		];

		$saved = get_option( 'rondo_clothing_settings', [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		$settings                      = wp_parse_args( $saved, $defaults );
		$settings['eligibility_enabled'] = rest_sanitize_boolean( $settings['eligibility_enabled'] );
		$settings['cooldown_seasons'] = max( 1, (int) $settings['cooldown_seasons'] );
		$settings['current_season']   = sanitize_text_field( $settings['current_season'] );

		return $settings;
	}

	/**
	 * Guess current season (YYYY-YYYY).
	 *
	 * @return string
	 */
	private function guess_current_season() {
		$year  = (int) gmdate( 'Y' );
		$month = (int) gmdate( 'n' );
		$start = $month >= 7 ? $year : $year - 1;
		$end   = $start + 1;
		return sprintf( '%d-%d', $start, $end );
	}

	/**
	 * Parse a season string to start year.
	 *
	 * @param string $season Season string.
	 * @return int
	 */
	private function season_start_year( $season ) {
		if ( preg_match( '/^(\d{4})-\d{4}$/', (string) $season, $matches ) ) {
			return (int) $matches[1];
		}
		return 0;
	}

	/**
	 * Normalize sizes payload.
	 *
	 * @param mixed $sizes Sizes value.
	 * @return string[]
	 */
	private function normalize_sizes( $sizes ) {
		if ( is_string( $sizes ) ) {
			$sizes = array_map( 'trim', explode( ',', $sizes ) );
		}

		if ( ! is_array( $sizes ) ) {
			return [];
		}

		$clean = array_values(
			array_filter(
				array_map(
					function ( $value ) {
						return sanitize_text_field( (string) $value );
					},
					$sizes
				)
			)
		);

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Format item.
	 *
	 * @param int $item_id Item ID.
	 * @return array
	 */
	private function format_item( $item_id ) {
		$post = get_post( $item_id );
		if ( ! $post || 'rondo_clothing_item' !== $post->post_type ) {
			return [];
		}

		$category_terms = wp_get_post_terms( $item_id, 'clothing_category', [ 'fields' => 'names' ] );
		if ( is_wp_error( $category_terms ) ) {
			$category_terms = [];
		}

		$sizes = get_post_meta( $item_id, '_clothing_available_sizes', true );
		if ( ! is_array( $sizes ) ) {
			$sizes = [];
		}

		return [
			'id'             => $item_id,
			'name'           => $this->sanitize_text( $post->post_title ),
			'brand'          => sanitize_text_field( (string) get_post_meta( $item_id, '_clothing_brand', true ) ),
			'category'       => $category_terms,
			'available_sizes' => array_values( $sizes ),
			'color'          => sanitize_text_field( (string) get_post_meta( $item_id, '_clothing_color', true ) ),
			'season'         => sanitize_text_field( (string) get_post_meta( $item_id, '_clothing_season', true ) ),
			'unit_cost'      => (float) get_post_meta( $item_id, '_clothing_unit_cost', true ),
			'deposit_amount' => (float) get_post_meta( $item_id, '_clothing_deposit_amount', true ),
			'active'         => (bool) get_post_meta( $item_id, '_clothing_active', true ),
		];
	}

	/**
	 * Create/update item meta and taxonomy.
	 *
	 * @param int   $item_id Item ID.
	 * @param array $data Request data.
	 */
	private function save_item_fields( $item_id, $data ) {
		if ( array_key_exists( 'brand', $data ) ) {
			update_post_meta( $item_id, '_clothing_brand', sanitize_text_field( (string) $data['brand'] ) );
		}
		if ( array_key_exists( 'color', $data ) ) {
			update_post_meta( $item_id, '_clothing_color', sanitize_text_field( (string) $data['color'] ) );
		}
		if ( array_key_exists( 'season', $data ) ) {
			update_post_meta( $item_id, '_clothing_season', sanitize_text_field( (string) $data['season'] ) );
		}
		if ( array_key_exists( 'unit_cost', $data ) ) {
			update_post_meta( $item_id, '_clothing_unit_cost', (float) $data['unit_cost'] );
		}
		if ( array_key_exists( 'deposit_amount', $data ) ) {
			update_post_meta( $item_id, '_clothing_deposit_amount', (float) $data['deposit_amount'] );
		}
		if ( array_key_exists( 'active', $data ) ) {
			update_post_meta( $item_id, '_clothing_active', rest_sanitize_boolean( $data['active'] ) ? '1' : '0' );
		}
		if ( array_key_exists( 'available_sizes', $data ) ) {
			update_post_meta( $item_id, '_clothing_available_sizes', $this->normalize_sizes( $data['available_sizes'] ) );
		}
		if ( array_key_exists( 'category', $data ) ) {
			$categories = $data['category'];
			if ( is_string( $categories ) ) {
				$categories = array_map( 'trim', explode( ',', $categories ) );
			}
			if ( ! is_array( $categories ) ) {
				$categories = [];
			}
			$categories = array_values( array_filter( array_map( 'sanitize_text_field', $categories ) ) );
			wp_set_post_terms( $item_id, $categories, 'clothing_category', false );
		}
	}

	/**
	 * Get clothing items.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items() {
		$items = get_posts(
			[
				'post_type'        => 'rondo_clothing_item',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
				'orderby'          => 'title',
				'order'            => 'ASC',
			]
		);

		$data = array_map(
			function ( $item ) {
				return $this->format_item( $item->ID );
			},
			$items
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Create clothing item.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_item( $request ) {
		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new \WP_Error( 'missing_name', __( 'Name is required.', 'rondo' ), [ 'status' => 400 ] );
		}

		$item_id = wp_insert_post(
			[
				'post_type'   => 'rondo_clothing_item',
				'post_status' => 'publish',
				'post_title'  => $name,
			]
		);

		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}

		$this->save_item_fields( $item_id, $request->get_params() );

		return rest_ensure_response( $this->format_item( $item_id ) );
	}

	/**
	 * Update clothing item.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_item( $request ) {
		$item_id = (int) $request->get_param( 'id' );
		$post    = get_post( $item_id );

		if ( ! $post || 'rondo_clothing_item' !== $post->post_type ) {
			return new \WP_Error( 'item_not_found', __( 'Clothing item not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		$name = $request->get_param( 'name' );
		if ( null !== $name ) {
			wp_update_post(
				[
					'ID'         => $item_id,
					'post_title' => sanitize_text_field( (string) $name ),
				]
			);
		}

		$this->save_item_fields( $item_id, $request->get_params() );

		return rest_ensure_response( $this->format_item( $item_id ) );
	}

	/**
	 * Delete clothing item.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_item( $request ) {
		$item_id = (int) $request->get_param( 'id' );
		$post    = get_post( $item_id );

		if ( ! $post || 'rondo_clothing_item' !== $post->post_type ) {
			return new \WP_Error( 'item_not_found', __( 'Clothing item not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		wp_delete_post( $item_id, true );

		return rest_ensure_response( [ 'success' => true ] );
	}

	/**
	 * Format assignment row.
	 *
	 * @param int $assignment_id Assignment ID.
	 * @return array
	 */
	private function format_assignment( $assignment_id ) {
		$post = get_post( $assignment_id );
		if ( ! $post || 'rondo_clothing_txn' !== $post->post_type ) {
			return [];
		}

		$person_id = (int) get_post_meta( $assignment_id, '_clothing_person_id', true );
		$item_id   = (int) get_post_meta( $assignment_id, '_clothing_item_id', true );
		$person    = get_post( $person_id );
		$item      = get_post( $item_id );

		return [
			'id'               => $assignment_id,
			'person_id'        => $person_id,
			'person_name'      => $person ? $this->sanitize_text( $person->post_title ) : '',
			'item_id'          => $item_id,
			'item_name'        => $item ? $this->sanitize_text( $item->post_title ) : '',
			'size'             => sanitize_text_field( (string) get_post_meta( $assignment_id, '_clothing_size', true ) ),
			'condition'        => sanitize_text_field( (string) get_post_meta( $assignment_id, '_clothing_condition', true ) ),
			'date'             => sanitize_text_field( (string) get_post_meta( $assignment_id, '_clothing_date', true ) ),
			'handled_by'       => (int) get_post_meta( $assignment_id, '_clothing_handled_by', true ),
			'in_or_out'        => sanitize_text_field( (string) get_post_meta( $assignment_id, '_clothing_in_or_out', true ) ),
			'deposit_paid'     => (bool) get_post_meta( $assignment_id, '_clothing_deposit_paid', true ),
			'deposit_returned' => (bool) get_post_meta( $assignment_id, '_clothing_deposit_returned', true ),
			'notes'            => sanitize_textarea_field( (string) get_post_meta( $assignment_id, '_clothing_notes', true ) ),
			'season'           => sanitize_text_field( (string) get_post_meta( $assignment_id, '_clothing_season', true ) ),
			'override_reason'  => sanitize_textarea_field( (string) get_post_meta( $assignment_id, '_clothing_override_reason', true ) ),
		];
	}

	/**
	 * Get assignments.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_assignments( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$item_id   = (int) $request->get_param( 'item_id' );
		$season    = sanitize_text_field( (string) $request->get_param( 'season' ) );

		$meta_query = [];
		if ( $person_id > 0 ) {
			$meta_query[] = [
				'key'   => '_clothing_person_id',
				'value' => $person_id,
			];
		}
		if ( $item_id > 0 ) {
			$meta_query[] = [
				'key'   => '_clothing_item_id',
				'value' => $item_id,
			];
		}
		if ( '' !== $season ) {
			$meta_query[] = [
				'key'   => '_clothing_season',
				'value' => $season,
			];
		}

		$args = [
			'post_type'        => 'rondo_clothing_txn',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'suppress_filters' => false,
			'orderby'          => 'date',
			'order'            => 'DESC',
		];
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		$rows  = get_posts( $args );
		$data = array_map(
			function ( $row ) {
				return $this->format_assignment( $row->ID );
			},
			$rows
		);

		return rest_ensure_response( $data );
	}

	/**
	 * Get eligibility for a person and item.
	 *
	 * @param int    $person_id Person ID.
	 * @param int    $item_id Item ID.
	 * @param string $season Season.
	 * @param string $override_reason Override reason.
	 * @return array
	 */
	private function evaluate_eligibility( $person_id, $item_id, $season, $override_reason = '' ) {
		$settings = $this->get_clothing_settings();
		if ( empty( $settings['eligibility_enabled'] ) ) {
			return [
				'eligible' => true,
				'reason'   => 'Eligibility-regels zijn uitgeschakeld.',
			];
		}

		if ( '' !== trim( (string) $override_reason ) ) {
			return [
				'eligible' => true,
				'reason'   => 'Handmatige override door beheerder.',
			];
		}

		$cooldown      = (int) $settings['cooldown_seasons'];
		$current_start = $this->season_start_year( $season ?: $settings['current_season'] );

		$history = get_posts(
			[
				'post_type'        => 'rondo_clothing_txn',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'   => '_clothing_person_id',
						'value' => $person_id,
					],
					[
						'key'   => '_clothing_item_id',
						'value' => $item_id,
					],
					[
						'key'   => '_clothing_in_or_out',
						'value' => 'out',
					],
				],
			]
		);

		if ( empty( $history ) ) {
			return [
				'eligible' => true,
				'reason'   => 'Nog niet eerder uitgegeven.',
			];
		}

		$last_season = sanitize_text_field( (string) get_post_meta( $history[0]->ID, '_clothing_season', true ) );
		$last_start  = $this->season_start_year( $last_season );

		if ( $current_start > 0 && $last_start > 0 ) {
			$diff = $current_start - $last_start;
			if ( $diff >= $cooldown ) {
				return [
					'eligible' => true,
					'reason'   => sprintf( 'Cooldown verstreken (%d seizoenen).', $diff ),
				];
			}

			return [
				'eligible' => false,
				'reason'   => sprintf( 'Laatst uitgegeven in %s. Opnieuw na %d seizoenen.', $last_season, $cooldown ),
			];
		}

		return [
			'eligible' => false,
			'reason'   => 'Eerdere uitgifte gevonden. Geen eenduidige seizoensvergelijking mogelijk.',
		];
	}

	/**
	 * Create assignment.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_assignment( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$item_id   = (int) $request->get_param( 'item_id' );
		$size      = sanitize_text_field( (string) $request->get_param( 'size' ) );
		$condition = sanitize_text_field( (string) $request->get_param( 'condition' ) );
		$date      = sanitize_text_field( (string) $request->get_param( 'date' ) );
		$in_or_out = sanitize_text_field( (string) $request->get_param( 'in_or_out' ) );
		$notes     = sanitize_textarea_field( (string) $request->get_param( 'notes' ) );
		$season    = sanitize_text_field( (string) $request->get_param( 'season' ) );

		if ( ! get_post( $person_id ) || 'person' !== get_post_type( $person_id ) ) {
			return new \WP_Error( 'invalid_person', __( 'Invalid person.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( ! get_post( $item_id ) || 'rondo_clothing_item' !== get_post_type( $item_id ) ) {
			return new \WP_Error( 'invalid_item', __( 'Invalid clothing item.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( '' === $size ) {
			return new \WP_Error( 'missing_size', __( 'Size is required.', 'rondo' ), [ 'status' => 400 ] );
		}

		$available_sizes = get_post_meta( $item_id, '_clothing_available_sizes', true );
		if ( ! is_array( $available_sizes ) || empty( $available_sizes ) ) {
			return new \WP_Error( 'item_sizes_missing', __( 'This item has no configured sizes.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( ! in_array( $size, $available_sizes, true ) ) {
			return new \WP_Error( 'invalid_size_for_item', __( 'Selected size is not available for this item.', 'rondo' ), [ 'status' => 400 ] );
		}

		if ( '' === $date ) {
			$date = gmdate( 'Y-m-d' );
		}
		if ( ! in_array( $in_or_out, [ 'in', 'out' ], true ) ) {
			return new \WP_Error( 'invalid_direction', __( 'in_or_out must be in or out.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( '' === $season ) {
			$season = $this->get_clothing_settings()['current_season'];
		}

		$override_reason = sanitize_textarea_field( (string) $request->get_param( 'override_reason' ) );
		if ( 'out' === $in_or_out ) {
			$eligibility = $this->evaluate_eligibility( $person_id, $item_id, $season, $override_reason );
			if ( ! $eligibility['eligible'] ) {
				return new \WP_Error( 'not_eligible', $eligibility['reason'], [ 'status' => 409 ] );
			}
		}

		$item_name   = get_the_title( $item_id );
		$person_name = get_the_title( $person_id );
		$title       = sprintf( '%s %s - %s (%s)', strtoupper( $in_or_out ), $item_name, $person_name, $date );

		$assignment_id = wp_insert_post(
			[
				'post_type'   => 'rondo_clothing_txn',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);

		if ( is_wp_error( $assignment_id ) ) {
			return $assignment_id;
		}

		update_post_meta( $assignment_id, '_clothing_person_id', $person_id );
		update_post_meta( $assignment_id, '_clothing_item_id', $item_id );
		update_post_meta( $assignment_id, '_clothing_size', $size );
		update_post_meta( $assignment_id, '_clothing_condition', $condition );
		update_post_meta( $assignment_id, '_clothing_date', $date );
		update_post_meta( $assignment_id, '_clothing_handled_by', get_current_user_id() );
		update_post_meta( $assignment_id, '_clothing_in_or_out', $in_or_out );
		update_post_meta( $assignment_id, '_clothing_deposit_paid', rest_sanitize_boolean( $request->get_param( 'deposit_paid' ) ) ? '1' : '0' );
		update_post_meta( $assignment_id, '_clothing_deposit_returned', rest_sanitize_boolean( $request->get_param( 'deposit_returned' ) ) ? '1' : '0' );
		update_post_meta( $assignment_id, '_clothing_notes', $notes );
		update_post_meta( $assignment_id, '_clothing_season', $season );
		update_post_meta( $assignment_id, '_clothing_override_reason', $override_reason );

		return rest_ensure_response( $this->format_assignment( $assignment_id ) );
	}

	/**
	 * Compute current possessions.
	 *
	 * @param array $assignments Formatted assignments sorted newest->oldest.
	 * @return array
	 */
	private function compute_current_possessions( $assignments ) {
		$current = [];

		// Reverse to process oldest -> newest.
		$ordered = array_reverse( $assignments );
		foreach ( $ordered as $row ) {
			$key = sprintf( '%d|%s', (int) $row['item_id'], (string) $row['size'] );
			if ( 'out' === $row['in_or_out'] ) {
				if ( ! isset( $current[ $key ] ) ) {
					$current[ $key ] = [];
				}
				$current[ $key ][] = $row;
			} elseif ( 'in' === $row['in_or_out'] && ! empty( $current[ $key ] ) ) {
				array_shift( $current[ $key ] );
			}
		}

		$flattened = [];
		foreach ( $current as $rows ) {
			foreach ( $rows as $row ) {
				$flattened[] = $row;
			}
		}

		return $flattened;
	}

	/**
	 * Get person clothing profile.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_person_profile( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		if ( ! get_post( $person_id ) || 'person' !== get_post_type( $person_id ) ) {
			return new \WP_Error( 'invalid_person', __( 'Invalid person.', 'rondo' ), [ 'status' => 404 ] );
		}

		$rows = get_posts(
			[
				'post_type'        => 'rondo_clothing_txn',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'meta_query'       => [
					[
						'key'   => '_clothing_person_id',
						'value' => $person_id,
					],
				],
			]
		);
		$history = array_map(
			function ( $row ) {
				return $this->format_assignment( $row->ID );
			},
			$rows
		);

		$current_items = $this->compute_current_possessions( $history );

		$outstanding_deposit = 0.0;
		foreach ( $current_items as $item_row ) {
			if ( ! empty( $item_row['deposit_paid'] ) && empty( $item_row['deposit_returned'] ) ) {
				$item_deposit        = (float) get_post_meta( (int) $item_row['item_id'], '_clothing_deposit_amount', true );
				$outstanding_deposit += $item_deposit;
			}
		}

		$settings = $this->get_clothing_settings();
		return rest_ensure_response(
			[
				'person_id'            => $person_id,
				'current_items'        => array_values( $current_items ),
				'history'              => $history,
				'outstanding_deposit'  => round( $outstanding_deposit, 2 ),
				'eligibility'          => [
					'eligible' => true,
					'reason'   => sprintf( 'Standaard cooldown: %d seizoenen.', (int) $settings['cooldown_seasons'] ),
				],
			]
		);
	}

	/**
	 * Get overview metrics.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_overview() {
		$rows = get_posts(
			[
				'post_type'        => 'rondo_clothing_txn',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
				'orderby'          => 'date',
				'order'            => 'DESC',
			]
		);

		$distributed = 0;
		$returned    = 0;

		foreach ( $rows as $row ) {
			$direction = (string) get_post_meta( $row->ID, '_clothing_in_or_out', true );
			if ( 'out' === $direction ) {
				$distributed++;
			}
			if ( 'in' === $direction ) {
				$returned++;
			}
		}

		$items = get_posts(
			[
				'post_type'        => 'rondo_clothing_item',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
			]
		);

		return rest_ensure_response(
			[
				'total_items'  => count( $items ),
				'distributed'  => $distributed,
				'returned'     => $returned,
				'outstanding'  => max( 0, $distributed - $returned ),
			]
		);
	}

	/**
	 * Export assignments as CSV.
	 *
	 * @return \WP_REST_Response
	 */
	public function export_assignments_csv() {
		$rows = get_posts(
			[
				'post_type'        => 'rondo_clothing_txn',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
				'orderby'          => 'date',
				'order'            => 'DESC',
			]
		);

		$fp = fopen( 'php://temp', 'r+' );
		fputcsv( $fp, [ 'Datum', 'Lid', 'Item', 'Maat', 'Type', 'Conditie', 'Seizoen' ] );
		foreach ( $rows as $row ) {
			$assignment = $this->format_assignment( $row->ID );
			fputcsv(
				$fp,
				[
					$assignment['date'],
					$assignment['person_name'],
					$assignment['item_name'],
					$assignment['size'],
					$assignment['in_or_out'],
					$assignment['condition'],
					$assignment['season'],
				]
			);
		}
		rewind( $fp );
		$csv = stream_get_contents( $fp );
		fclose( $fp );

		$response = new \WP_REST_Response( $csv, 200 );
		$response->header( 'Content-Type', 'text/csv; charset=utf-8' );
		$response->header( 'Content-Disposition', 'attachment; filename="kleding-overzicht.csv"' );
		return $response;
	}

	/**
	 * Get settings endpoint.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( $this->get_clothing_settings() );
	}

	/**
	 * Update settings endpoint.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( $request ) {
		$current = $this->get_clothing_settings();
		$next    = $current;

		if ( null !== $request->get_param( 'eligibility_enabled' ) ) {
			$next['eligibility_enabled'] = rest_sanitize_boolean( $request->get_param( 'eligibility_enabled' ) );
		}
		if ( null !== $request->get_param( 'cooldown_seasons' ) ) {
			$next['cooldown_seasons'] = max( 1, (int) $request->get_param( 'cooldown_seasons' ) );
		}
		if ( null !== $request->get_param( 'current_season' ) ) {
			$next['current_season'] = sanitize_text_field( (string) $request->get_param( 'current_season' ) );
		}

		update_option( 'rondo_clothing_settings', $next, false );

		return rest_ensure_response( $next );
	}
}
