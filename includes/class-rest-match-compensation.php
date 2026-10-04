<?php
/** Authenticated module routes; all writes use server-side policy and durable receipts. */
namespace Rondo\REST;

use Rondo\Matches\CompensationService as Service;
use Rondo\Matches\CompensationLock;

final class MatchCompensation extends Base {
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}
	public function register_routes(): void {
		$routes = [
			'/imports/preview'                      => [ 'POST' ],
			'/imports'                              => [ 'POST' ],
			'/people/(?P<id>\d+)/nmbrs'             => [ 'PATCH' ],
			'/settings'                             => [ 'GET', 'POST' ],
			'/teams/(?P<team_id>\d+)/registrations' => [ 'GET' ],
			'/registrations'                        => [ 'POST' ],
			'/registrations/(?P<id>\d+)'            => [ 'PATCH' ],
			'/months'                               => [ 'GET' ],
			'/batches'                              => [ 'POST' ],
			'/batches/(?P<id>\d+)'                  => [ 'GET' ],
			'/batches/(?P<id>\d+)/(?P<action>exports|processing|corrections)' => [ 'POST' ],
		];
		foreach ( $routes as $route => $methods ) {
			register_rest_route(
				'rondo/v1',
				'/match-compensation' . $route,
				[
					'methods'             => $methods,
					'callback'            => [ $this, 'dispatch' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
				]
				);
		}
	}
	public function dispatch( \WP_REST_Request $request ) {
		$route  = $request->get_route();
		$params = $request->get_url_params();
		$input  = $request->get_json_params() ?: [];
		if ( ! is_array( $input ) ) {
			return Service::error( 'Ongeldige invoer.' );
		}
		if ( str_contains( $route, '/imports' ) ) {
			$result = Service::import( $input, str_ends_with( $route, '/imports' ) );
		} elseif ( str_ends_with( $route, '/nmbrs' ) ) {
			$id = (int) ( $params['id'] ?? 0 );
			if ( ! Service::finance( true ) || ! \Rondo\Finance\PersonBankAccount::can_write( $id ) || array_diff( array_keys( $input ), [ 'nmbrs_name', 'request_id' ] ) ) {
				return Service::error( 'Geen toegang tot deze Nmbrs-gegevens.', 403 ); }
			$result = \Rondo\Fields\Fields::update_many_for_post( $id, [ 'nmbrs_name' => $input['nmbrs_name'] ?? null ] );
		} elseif ( str_ends_with( $route, '/settings' ) ) {
			$result = $request->get_method() === 'GET' ? Service::settings() : CompensationLock::run( static fn() => Service::configure( $input ) );
		} elseif ( isset( $request->get_url_params()['team_id'] ) ) {
			$result = Service::registrations( (int) $params['team_id'] );
		} elseif ( str_contains( $route, '/registrations' ) ) {
			$result = Service::registration( (int) ( $params['id'] ?? 0 ), $input );
		} elseif ( str_ends_with( $route, '/months' ) ) {
			$result = Service::preview( (int) $request['team_id'], (string) $request['month'] );
		} elseif ( str_ends_with( $route, '/batches' ) ) {
			$result = Service::close( $input );
		} elseif ( $request->get_method() === 'GET' ) {
			$record = Service::record( (int) ( $params['id'] ?? 0 ) );
			$result = Service::finance() && get_post_type( (int) ( $params['id'] ?? 0 ) ) === Service::BATCH && $record ? $record + [ 'summary' => Service::batch_summary( $record ) ] : Service::error( 'Geen toegang tot deze maand.', 403 );
		} else {
			$result = Service::batch_action( (int) ( $params['id'] ?? 0 ), (string) ( $params['action'] ?? '' ), $input );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! Service::finance( true ) ) {
			foreach ( [ 'rows', 'settlement_rows' ] as $key ) {
				if ( ! isset( $result[ $key ] ) ) {
					continue; }
				foreach ( $result[ $key ] as &$row ) {
					$row['iban'] = empty( $row['iban'] ) ? '' : '•••• ' . substr( $row['iban'], -4 );
					unset( $row['bank_account_holder'] );
				}
				unset( $row );
			}
		}
		return new \WP_REST_Response( $result, 200, [ 'Cache-Control' => 'private, no-store' ] );
	}
}
