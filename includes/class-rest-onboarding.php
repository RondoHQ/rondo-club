<?php
/** Administrator-only, non-sending onboarding foundation API. */

namespace Rondo\REST;

use Rondo\Onboarding\Foundation;
use Rondo\Onboarding\Sources;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Onboarding extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		foreach ( [
			'sources'        => 'ingest',
			'sources/finish' => 'finish',
		] as $path => $method ) {
			register_rest_route(
				'rondo/v1',
				'/onboarding/' . $path,
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'callback'            => static function ( $request ) use ( $method ) {
						$input = $request->get_json_params();
						return is_array( $input ) ? rest_ensure_response( Sources::$method( $input ) ) : new \WP_Error( 'onboarding_contract', 'Een JSON-object is vereist.', [ 'status' => 400 ] );
					},
				]
				);
		}
		register_rest_route(
			'rondo/v1',
			'/onboarding/simulation',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'callback'            => [ $this, 'simulate' ],
				'args'                => [
					'page'   => [
						'type'    => 'integer',
						'minimum' => 1,
						'default' => 1,
					],
					'search' => [
						'type'              => 'string',
						'maxLength'         => 100,
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
			);
		register_rest_route(
			'rondo/v1',
			'/onboarding/observations/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'callback'            => static function ( $request ) {
					$input  = $request->get_json_params();
					$result = is_array( $input ) ? Foundation::observe( (int) $request['id'], $input ) : new \WP_Error( 'onboarding_contract', 'Een JSON-object is vereist.', [ 'status' => 400 ] );
					return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
				},
			]
			);
		register_rest_route(
			'rondo/v1',
			'/onboarding/simulation/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'callback'            => static function ( $request ) {
					$id = (int) $request['id'];
					if ( get_post_type( $id ) !== 'person' || get_post_status( $id ) !== 'publish' ) {
						return new \WP_Error( 'onboarding_person', 'Persoon niet gevonden.', [ 'status' => 404 ] );
					}
					return rest_ensure_response( Foundation::simulate( $id ) );
				},
			]
			);
	}

	public function simulate( $request ) {
		$query    = new \WP_Query(
			[
				'post_type'      => 'person',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'paged'          => $request['page'],
				's'              => $request['search'],
				'orderby'        => 'ID',
				'order'          => 'DESC',
			]
			);
		$response = rest_ensure_response(
			[
				'simulation_only' => true,
				'sending_enabled' => false,
				'people'          => array_map( static fn( $person ) => Foundation::simulate( $person->ID ), $query->posts ),
				'page'            => (int) $request['page'],
				'total_pages'     => (int) $query->max_num_pages,
				'total'           => (int) $query->found_posts,
			]
			);
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
