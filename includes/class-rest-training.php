<?php
/**
 * Training schedule API. Schedule reads are public; management remains feature-gated.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Config\FeatureToggles;
use Rondo\Training\Schedules;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Training extends Base {
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		foreach ( [
			[ '/training/schedules', 'GET', 'index', false ],
			[ '/training/schedules', 'POST', 'save', true ],
			[ '/training/schedules/(?P<id>\d+)', 'GET', 'show', false ],
			[ '/training/schedules/(?P<id>\d+)', 'PUT', 'save', true ],
			[ '/training/schedules/(?P<id>\d+)', 'DELETE', 'remove', true ],
			[ '/training/schedules/(?P<id>\d+)/copy', 'POST', 'copy', true ],
			[ '/training/schedules/(?P<id>\d+)/activate', 'POST', 'activate', true ],
			[ '/training/active', 'GET', 'active', false ],
			[ '/training/settings', 'GET', 'settings', true ],
			[ '/training/settings', 'PUT', 'update_settings', true ],
		] as [ $route, $method, $callback, $admin ] ) {
			register_rest_route(
				'rondo/v1',
				$route,
				[
					'methods'             => $method,
					'callback'            => [ $this, $callback ],
					'permission_callback' => [ $this, $admin ? 'can_manage' : 'can_read' ],
				]
				);
		}
	}

	public function can_read(): bool {
		return true;
	}

	public function can_manage(): bool {
		return FeatureToggles::can_access( 'training' ) && current_user_can( 'manage_options' );
	}

	private function response( $result ) {
		return is_wp_error( $result ) ? $result : new \WP_REST_Response( $result, 200, [ 'Cache-Control' => 'no-store, private' ] );
	}

	public function index() {
		return $this->response( Schedules::feed() );
	}

	public function show( $request ) {
		$schedule = Schedules::schedule( (int) $request['id'] );
		return $this->response(
			is_wp_error( $schedule ) ? $schedule : [
				'schedule'  => $schedule,
				'pitches'   => Schedules::settings()['pitches'],
				'timezone'  => wp_timezone_string(),
				'active_id' => (int) get_option( Schedules::ACTIVE, 0 ),
			]
			);
	}

	public function active() {
		$id = (int) get_option( Schedules::ACTIVE, 0 );
		return $this->response(
			[
				'schedule' => $id && Schedules::exists( $id ) ? Schedules::schedule( $id ) : null,
				'pitches'  => Schedules::settings()['pitches'],
				'timezone' => wp_timezone_string(),
			]
			);
	}

	public function settings() {
		return $this->response(
			[
				'settings' => Schedules::settings(),
				'teams'    => Schedules::team_directory(),
			]
			);
	}

	public function update_settings( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return Schedules::error( 'Stuur de instellingen als JSON-object.' );
		}
		return $this->response( Schedules::locked( static fn() => Schedules::save_settings( $data ) ) );
	}

	public function save( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return Schedules::error( 'Stuur het schema als JSON-object.' );
		}
		return $this->response( Schedules::locked( static fn() => Schedules::save( (int) $request['id'], $data ) ) );
	}

	public function copy( $request ) {
		return $this->response(
			Schedules::locked(
			static function () use ( $request ) {
				$source = Schedules::schedule( (int) $request['id'] );
				if ( is_wp_error( $source ) ) {
					return $source;
				}
				foreach ( $source['blocks'] as &$block ) {
					unset( $block['team_names'], $block['color'] );
				}
				unset( $block );
				return Schedules::save(
				0,
				[
					'name'     => $request->get_param( 'name' ),
					'season'   => $source['season'],
					'revision' => 0,
					'blocks'   => $source['blocks'],
				]
				);
			}
			)
			);
	}

	public function activate( $request ) {
		return $this->response(
			Schedules::locked(
			static function () use ( $request ) {
				$id       = (int) $request['id'];
				$schedule = Schedules::schedule( $id );
				if ( is_wp_error( $schedule ) ) {
					return $schedule;
				}
				if ( $request->get_param( 'revision' ) !== $schedule['revision'] ) {
					return Schedules::error( 'Het schema is gewijzigd. Vernieuw de pagina.', 409 );
				}
				$blocks    = array_map(
				static function ( $block ) {
					unset( $block['team_names'], $block['color'] );
					return $block;
				},
				$schedule['blocks']
				);
				$validated = Schedules::validate_blocks( $blocks );
				if ( is_wp_error( $validated ) ) {
					return $validated;
				}
				update_option( Schedules::ACTIVE, $id, false );
				return [ 'active_id' => $id ];
			}
			)
			);
	}

	public function remove( $request ) {
		return $this->response(
			Schedules::locked(
			static function () use ( $request ) {
				$id = (int) $request['id'];
				if ( ! Schedules::exists( $id ) ) {
					return Schedules::error( 'Trainingsschema niet gevonden.', 404 );
				}
				if ( (int) get_option( Schedules::ACTIVE, 0 ) === $id ) {
					return Schedules::error( 'Activeer eerst een ander schema voordat je dit schema verwijdert.', 409 );
				}
				if ( $request->get_param( 'revision' ) !== (int) \Rondo\Fields\Fields::get_for_post( $id, 'revision' ) ) {
					return Schedules::error( 'Het schema is gewijzigd. Vernieuw de pagina.', 409 );
				}
				return wp_trash_post( $id ) ? [ 'deleted' => true ] : Schedules::error( 'Verwijderen mislukt.', 500 );
			}
			)
			);
	}
}
