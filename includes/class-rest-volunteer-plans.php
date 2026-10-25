<?php
/** Private review and publication endpoints for weekly volunteer proposals. */
namespace Rondo\REST;

use Rondo\Volunteer\VolunteerPlanner;
final class VolunteerPlans extends Base {
	public function __construct() {
		add_action( 'rest_api_init', [ $this,'register_routes' ] );
	}
	public function register_routes(): void {
		$revision = [
			'revision' => [
				'type'     => 'integer',
				'required' => true,
				'minimum'  => 1,
			],
		];
		$routes   = [
			'/volunteer-plans'                     => [
				[
					'methods'  => 'GET',
					'callback' => [ $this, 'listing' ],
				],
				[
					'methods'  => 'POST',
					'callback' => [ $this, 'generate' ],
				],
			],
			'/volunteer-plans/(?P<id>\d+)'         => [
				[
					'methods'  => 'GET',
					'callback' => [ $this, 'view' ],
				],
			],
			'/volunteer-plans/(?P<id>\d+)/rows/(?P<index>\d+)/candidates' => [
				[
					'methods'  => 'GET',
					'callback' => [ $this, 'candidates' ],
				],
			],
			'/volunteer-plans/(?P<id>\d+)/rows/(?P<index>\d+)' => [
				[
					'methods'  => 'PUT',
					'callback' => [ $this, 'edit' ],
					'args'     => $revision + [
						'person_id' => [
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 0,
						],
					],
				],
			],
			'/volunteer-plans/(?P<id>\d+)/publish' => [
				[
					'methods'  => 'POST',
					'callback' => [ $this, 'publish' ],
					'args'     => $revision + [
						'accept_conversions' => [
							'type'    => 'boolean',
							'default' => false,
						],
					],
				],
			],
			'/volunteer-plans/(?P<id>\d+)/close'   => [
				[
					'methods'  => 'POST',
					'callback' => [ $this, 'close' ],
					'args'     => $revision,
				],
			],
		];
		foreach ( $routes as $path => $endpoints ) {
			foreach ( $endpoints as &$endpoint ) {
				$endpoint['permission_callback'] = [ $this, 'check_vrijwilligers_permission' ];
			}
			unset( $endpoint );
			register_rest_route( 'rondo/v1', $path, $endpoints );
		}
	}
	private function response( $data ) {
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}
	public function listing( $request ) {
		$planner = new VolunteerPlanner();
		$plans   = [];
		foreach ( array_slice( VolunteerPlanner::plans(), 0, 30 ) as $post ) {
			$view = $planner->view( $post->ID );
			if ( ! is_wp_error( $view ) ) {
				$plans[] = array_intersect_key( $view, array_flip( [ 'id','phase','created_at','from','until' ] ) );
			}
		}
		return $this->response(
		[
			'plans'              => $plans,
			'next_at'            => VolunteerPlanner::next_run( VolunteerPlanner::now() )->format( DATE_ATOM ),
			'notification_email' => VolunteerPlanner::email(),
		]
		);
	}
	public function generate( $request ) {
		$planner = new VolunteerPlanner();
		$id      = $planner->generate( null, true );
		return is_wp_error( $id ) ? $id : $this->response( $planner->view( $id ) );
	}
	public function view( $request ) {
		return $this->response( ( new VolunteerPlanner() )->view( (int) $request['id'] ) );
	}
	private function access( $request ) {
		return ( new VolunteerPlanner() )->view( (int) $request['id'] );
	}
	public function candidates( $request ) {
		$view = $this->access( $request );
		return is_wp_error( $view ) ? $view : $this->response( ( new VolunteerPlanner() )->candidates( (int) $request['id'], (int) $request['index'] ) );
	}
	public function edit( $request ) {
		$view = $this->access( $request );
		if ( is_wp_error( $view ) ) {
			return $view;
		}
		$result = ( new VolunteerPlanner() )->edit( (int) $request['id'], (int) $request['revision'], (int) $request['index'], (int) $request['person_id'] );
		return is_wp_error( $result ) ? $result : $this->view( $request );
	}
	public function publish( $request ) {
		$view = $this->access( $request );
		if ( is_wp_error( $view ) ) {
			return $view;
		}
		$result = ( new VolunteerPlanner() )->publish( (int) $request['id'], (int) $request['revision'], (bool) $request['accept_conversions'] );
		return is_wp_error( $result ) ? $result : $this->view( $request );
	}
	public function close( $request ) {
		$view = $this->access( $request );
		if ( is_wp_error( $view ) ) {
			return $view;
		}
		$result = ( new VolunteerPlanner() )->discard( (int) $request['id'], (int) $request['revision'] );
		return is_wp_error( $result ) ? $result : $this->view( $request );
	}
}
