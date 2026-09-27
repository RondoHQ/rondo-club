<?php
/**
 * Board-only sportpark calendar API.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Sportpark\Closures;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SportparkClosures extends Base {
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		foreach ( [
			[ '/sportpark/closures', 'GET', 'index' ],
			[ '/sportpark/closures', 'POST', 'save' ],
			[ '/sportpark/closures/(?P<id>\d+)', 'GET', 'show' ],
			[ '/sportpark/closures/(?P<id>\d+)', 'PUT,PATCH', 'save' ],
			[ '/sportpark/closures/(?P<id>\d+)', 'DELETE', 'remove' ],
		] as [ $route, $method, $callback ] ) {
			register_rest_route(
				'rondo/v1',
				$route,
				[
					'methods'             => $method,
					'callback'            => [ $this, $callback ],
					'permission_callback' => static fn() => UserRoles::can_access_board(),
				]
				);
		}
	}

	private function response( $data, int $status = 200 ) {
		return is_wp_error( $data ) ? $data : new \WP_REST_Response( $data, $status, [ 'Cache-Control' => 'no-store, private' ] );
	}

	private function missing() {
		return new \WP_Error( 'closure_not_found', 'Sluitingsperiode niet gevonden.', [ 'status' => 404 ] );
	}

	public function index( $request ) {
		$year = $request->get_param( 'year' ) ?? current_datetime()->format( 'Y' );
		if ( ! is_scalar( $year ) || ! preg_match( '/^[1-9][0-9]{3}$/', (string) $year ) || (int) $year > 9998 ) {
			return new \WP_Error( 'invalid_year', 'Kies een geldig jaar.', [ 'status' => 400 ] );
		}
		return $this->response( Closures::between( $year . '-01-01', $year . '-12-31' ) );
	}

	public function show( $request ) {
		return $this->response( Closures::record( (int) $request['id'] ) ?? $this->missing() );
	}

	public function save( $request ) {
		return $this->response( Closures::locked( fn() => $this->save_locked( $request ) ) );
	}

	private function save_locked( $request ) {
		$id       = (int) $request['id'];
		$existing = $id ? Closures::record( $id ) : null;
		if ( $id && ! $existing ) {
			return $this->missing();
		}
		$payload = $request->get_param( 'fields' ) ?? [];
		if ( ! is_array( $payload ) ) {
			return new \WP_Error( 'invalid_fields', 'Stuur de datums als fields-object.', [ 'status' => 400 ] );
		}
		try {
			// Validate unknown fields and canonical dates before any write.
			Formatter::for_storage( Closures::POST_TYPE, $payload );
			$fields = array_merge( $existing['fields'] ?? [], $payload );
			foreach ( [ 'starts_at', 'ends_at' ] as $name ) {
				$value = $fields[ $name ] ?? null;
				$date  = is_string( $value ) ? \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() ) : false;
				if ( ! $date || $date->format( 'Y-m-d' ) !== $value || $value < '1000-01-01' || $value > '9998-12-31' ) {
					throw new \InvalidArgumentException( 'Vul een geldige begin- en einddatum in (JJJJ-MM-DD).' );
				}
			}
			if ( $fields['ends_at'] < $fields['starts_at'] ) {
				throw new \InvalidArgumentException( 'De einddatum mag niet vóór de begindatum liggen.' );
			}
			$title       = $request->has_param( 'title' ) ? $request['title'] : ( $existing['title'] ?? '' );
			$description = $request->has_param( 'description' ) ? $request['description'] : ( $existing['description'] ?? null );
			if ( ! is_string( $title ) || trim( sanitize_text_field( $title ) ) === '' || ( $description !== null && ! is_string( $description ) ) ) {
				throw new \InvalidArgumentException( 'Vul een titel en eventueel een omschrijving in.' );
			}
			$action = $request->get_param( 'existing_tasks' );
			if ( $action !== null && ! in_array( $action, [ 'keep', 'cancel' ], true ) ) {
				throw new \InvalidArgumentException( 'Kies behouden of annuleren voor bestaande inschrijftaken.' );
			}
		} catch ( \InvalidArgumentException $error ) {
			return new \WP_Error( 'invalid_closure', $error->getMessage(), [ 'status' => 400 ] );
		}

		$conflicts = Closures::conflicts( $fields['starts_at'], $fields['ends_at'] );
		$token     = Closures::conflict_token( $id, $fields, $conflicts );
		if ( $conflicts && ( $action === null || ! is_string( $request['conflict_token'] ) || ! hash_equals( $token, $request['conflict_token'] ) ) ) {
			return new \WP_Error(
				'closure_conflicts',
				'Er bestaan inschrijftaken in deze periode. Kies wat hiermee moet gebeuren.',
				[
					'status'         => 409,
					'conflicts'      => $conflicts,
					'conflict_token' => $token,
				]
				);
		}

		$post = [
			'post_type'    => Closures::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => sanitize_textarea_field( $description ?? '' ),
		];
		if ( $id ) {
			$post['ID'] = $id;
		} else {
			$post['post_author'] = get_current_user_id();
		}
		$saved = wp_insert_post( wp_slash( $post ), true );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$result = Fields::update_many_for_post( $saved, Formatter::for_storage( Closures::POST_TYPE, $fields ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$notification_warnings = [];
		$cancelled             = [];
		$failed                = [];
		if ( $action === 'cancel' ) {
			$controller = new MemberShifts();
			foreach ( $conflicts as $conflict ) {
				if ( ! $conflict['can_cancel'] ) {
					continue;
				}
				$cancel = new \WP_REST_Request();
				$cancel->set_param( 'id', $conflict['id'] );
				$cancel->set_param( 'reason', 'Sportpark gesloten: ' . sanitize_text_field( $title ) );
				$result = $controller->cancel_shift( $cancel );
				if ( is_wp_error( $result ) ) {
					$failed[] = [
						'id'      => $conflict['id'],
						'message' => $result->get_error_message(),
					];
				} else {
					$cancelled[]   = $conflict['id'];
					$notifications = $result->get_data()['notifications'];
					if ( $notifications['failed'] || $notifications['no_email'] ) {
						$notification_warnings[] = [
							'id'       => $conflict['id'],
							'failed'   => $notifications['failed'],
							'no_email' => $notifications['no_email'],
						];
					}
				}
			}
		}
		return [
			'closure'               => Closures::record( $saved ),
			'cancelled'             => $cancelled,
			'failed'                => $failed,
			'notification_warnings' => $notification_warnings,
		];
	}

	public function remove( $request ) {
		return $this->response(
			Closures::locked(
				function () use ( $request ) {
					$id = (int) $request['id'];
					if ( ! Closures::record( $id ) ) {
							return $this->missing();
					}
					if ( ! wp_trash_post( $id ) ) {
						return new \WP_Error( 'closure_delete_failed', 'De sluiting kon niet worden verwijderd. Probeer het opnieuw.', [ 'status' => 500 ] );
					}
					return [
						'deleted' => true,
						'id'      => $id,
					];
				}
			)
			);
	}
}
