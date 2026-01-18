<?php
/**
 * Custom Fields REST API Endpoints
 *
 * Provides REST API endpoints for managing custom field definitions.
 * Exposes the CustomFields Manager to the React frontend for CRUD operations.
 *
 * @package Caelis\REST
 */

namespace Caelis\REST;

use Caelis\CustomFields\Manager;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST controller for custom field definitions.
 *
 * Endpoints:
 * - GET    /prm/v1/custom-fields/{post_type}           - List all fields
 * - POST   /prm/v1/custom-fields/{post_type}           - Create new field
 * - GET    /prm/v1/custom-fields/{post_type}/{key}     - Get single field
 * - PUT    /prm/v1/custom-fields/{post_type}/{key}     - Update field
 * - DELETE /prm/v1/custom-fields/{post_type}/{key}     - Deactivate field
 */
class CustomFields extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'prm/v1';

	/**
	 * REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'custom-fields';

	/**
	 * CustomFields Manager instance.
	 *
	 * @var Manager
	 */
	protected Manager $manager;

	/**
	 * Constructor.
	 *
	 * Initializes the Manager and registers routes.
	 */
	public function __construct() {
		$this->manager = new Manager();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		// Collection route: list and create.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<post_type>person|company)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => $this->get_create_params(),
				),
			)
		);

		// Single item route: get, update, delete.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<post_type>person|company)/(?P<field_key>[a-z0-9_]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
					'args'                => $this->get_update_params(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'delete_item_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check if user can list custom fields.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can manage options.
	 */
	public function get_items_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can create custom fields.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can manage options.
	 */
	public function create_item_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can get a single custom field.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can manage options.
	 */
	public function get_item_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can update custom fields.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can manage options.
	 */
	public function update_item_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if user can delete (deactivate) custom fields.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool True if user can manage options.
	 */
	public function delete_item_permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get all custom fields for a post type.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response containing field array.
	 */
	public function get_items( $request ): WP_REST_Response {
		$post_type        = $request->get_param( 'post_type' );
		$include_inactive = $request->get_param( 'include_inactive' ) === true;

		$fields = $this->manager->get_fields( $post_type, $include_inactive );

		return rest_ensure_response( $fields );
	}

	/**
	 * Create a new custom field.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Created field or error.
	 */
	public function create_item( $request ) {
		$post_type    = $request->get_param( 'post_type' );
		$field_config = array(
			'label' => $request->get_param( 'label' ),
			'type'  => $request->get_param( 'type' ),
		);

		// Add optional params if present.
		$optional_params = array( 'name', 'instructions', 'required', 'choices', 'default_value', 'placeholder' );
		foreach ( $optional_params as $param ) {
			if ( $request->has_param( $param ) ) {
				$field_config[ $param ] = $request->get_param( $param );
			}
		}

		$result = $this->manager->create_field( $post_type, $field_config );

		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get a single custom field.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Field data or error.
	 */
	public function get_item( $request ) {
		$field_key = $request->get_param( 'field_key' );
		$field     = $this->manager->get_field( $field_key );

		if ( ! $field ) {
			return new WP_Error(
				'not_found',
				'Field not found',
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response( $field );
	}

	/**
	 * Update an existing custom field.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Updated field or error.
	 */
	public function update_item( $request ) {
		$field_key = $request->get_param( 'field_key' );
		$updates   = array();

		// Collect provided update params.
		$updatable_params = array( 'label', 'name', 'instructions', 'required', 'choices', 'default_value', 'placeholder' );
		foreach ( $updatable_params as $param ) {
			if ( $request->has_param( $param ) ) {
				$updates[ $param ] = $request->get_param( $param );
			}
		}

		$result = $this->manager->update_field( $field_key, $updates );

		if ( is_wp_error( $result ) ) {
			$error_data = array( 'status' => 400 );
			if ( $result->get_error_code() === 'field_not_found' ) {
				$error_data['status'] = 404;
			}
			$result->add_data( $error_data );
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Delete (deactivate) a custom field.
	 *
	 * Performs a soft delete by setting active=0. The field and its stored
	 * values are preserved in the database.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Success response or error.
	 */
	public function delete_item( $request ) {
		$field_key = $request->get_param( 'field_key' );
		$result    = $this->manager->deactivate_field( $field_key );

		if ( is_wp_error( $result ) ) {
			$error_data = array( 'status' => 400 );
			if ( $result->get_error_code() === 'field_not_found' ) {
				$error_data['status'] = 404;
			}
			$result->add_data( $error_data );
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'field'   => $result,
			)
		);
	}

	/**
	 * Get collection query parameters.
	 *
	 * @return array Parameter definitions.
	 */
	protected function get_collection_params(): array {
		return array(
			'include_inactive' => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Include deactivated fields in response',
			),
		);
	}

	/**
	 * Get parameters for creating a field.
	 *
	 * @return array Parameter definitions.
	 */
	protected function get_create_params(): array {
		return array(
			'label'         => array(
				'type'        => 'string',
				'required'    => true,
				'description' => 'Field label displayed to users',
			),
			'type'          => array(
				'type'        => 'string',
				'required'    => true,
				'description' => 'ACF field type (text, textarea, number, url, email, select, checkbox, radio, true_false)',
			),
			'name'          => array(
				'type'        => 'string',
				'description' => 'Field name (defaults to sanitized label)',
			),
			'instructions'  => array(
				'type'        => 'string',
				'description' => 'Help text displayed below field',
			),
			'required'      => array(
				'type'        => 'boolean',
				'default'     => false,
				'description' => 'Whether field is required',
			),
			'choices'       => array(
				'type'        => 'object',
				'description' => 'Choices for select/checkbox/radio fields',
			),
			'default_value' => array(
				'description' => 'Default value for new posts',
			),
			'placeholder'   => array(
				'type'        => 'string',
				'description' => 'Placeholder text for empty field',
			),
		);
	}

	/**
	 * Get parameters for updating a field.
	 *
	 * Same as create params but nothing is required.
	 * Type is excluded as it cannot be changed after creation.
	 *
	 * @return array Parameter definitions.
	 */
	protected function get_update_params(): array {
		$params = $this->get_create_params();

		// Remove required flags - all updates are optional.
		unset( $params['label']['required'] );
		unset( $params['type']['required'] );

		// Cannot change type after creation.
		unset( $params['type'] );

		return $params;
	}
}
