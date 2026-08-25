<?php

namespace Tests\Wpunit;

use Rondo\REST\Users;
use Rondo\Users\ActivationLog;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Contract tests for activation eligibility and its administrator-visible error log. */
class UsersActivationLogTest extends RondoTestCase {

	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		$this->server = $this->bootRestControllers( [ Users::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_former_sponsor_is_available_for_manual_account_creation(): void {
		$sponsor_id = $this->createPerson(
			[ 'post_title' => 'Oude Sponsor' ],
			[
				'first_name'    => 'Oude',
				'last_name'     => 'Sponsor',
				'email_1'       => 'sponsor@example.com',
				'former_member' => true,
				'is_sponsor'    => true,
			]
		);
		$this->createPerson(
			[ 'post_title' => 'Oud Geen Sponsor' ],
			[
				'first_name'    => 'Oud',
				'last_name'     => 'Geen Sponsor',
				'email_1'       => 'oud@example.com',
				'former_member' => true,
			]
		);

		$request = new WP_REST_Request( 'GET', '/rondo/v1/users/provisionable' );
		$request->set_param( 'search', 'Sponsor' );
		$response = $this->server->dispatch( $request );
		$ids      = array_map( 'intval', array_column( $response->get_data(), 'id' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ $sponsor_id ], $ids );
	}

	public function test_only_an_administrator_can_read_activation_errors(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Anne Jansen' ] );
		$log_id    = ActivationLog::record_failure(
			'activation_token_expired',
			'De activatielink was verlopen.',
			[ $person_id ],
			'anne@example.com'
		);

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/users/activation-errors' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $log_id, $data[0]['id'] );
		$this->assertSame( 'activation_token_expired', $data[0]['code'] );
		$this->assertSame( 'Anne Jansen', $data[0]['people'][0]['name'] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$forbidden = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/users/activation-errors' ) );
		$this->assertSame( 403, $forbidden->get_status() );
	}
}
