<?php
/** Private bank field endpoints for member self-service and financial management. */
namespace Rondo\REST;

use Rondo\Finance\PersonBankAccount;

final class BankAccounts extends Base {
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		foreach ( [ '/user/profile-bank-account', '/people/(?P<id>\d+)/bank-account' ] as $route ) {
			register_rest_route(
				'rondo/v1',
				$route,
				[
					'methods'             => [ 'GET', 'PATCH' ],
					'permission_callback' => [ $this, 'check_user_approved' ],
					'callback'            => [ $this, 'account' ],
				]
				);
		}
	}

	public function account( \WP_REST_Request $request ) {
		$id = (int) ( $request->get_url_params()['id'] ?? get_user_meta( get_current_user_id(), 'rondo_linked_person_id', true ) );
		if ( $request->get_method() === 'GET' ) {
			$result = PersonBankAccount::read( $id );
		} else {
			$input  = $request->get_json_params();
			$result = is_array( $input ) ? PersonBankAccount::update( $id, $input ) : new \WP_Error( 'rondo_bank_account', 'Ongeldige invoer.', [ 'status' => 400 ] );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response( $result, 200, [ 'Cache-Control' => 'private, no-store' ] );
	}
}
