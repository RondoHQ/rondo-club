<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Tests\Support\RondoTestCase;

class LoginRedirectTest extends RondoTestCase {

	public function test_password_login_preserves_posted_oauth_destination(): void {
		// phpcs:disable WordPress.Security.NonceVerification -- Synthetic login request globals in a regression test.
		$user = self::factory()->user->create_and_get();
		$url  = add_query_arg(
			[
				'client_id' => 'freescout',
				'state'     => 'original-state',
			],
			home_url( '/oauth/authorize' )
			);
		$get  = $_GET;
		$post = $_POST;
		try {
			$_GET                 = [];
			$_POST['redirect_to'] = $url;
			$this->assertSame( $url, apply_filters( 'login_redirect', $url, $url, $user ) );
			// Magic Login supplies its default admin URL separately from the requested URL.
			$this->assertSame( $url, apply_filters( 'login_redirect', admin_url(), $url, $user ) );
		} finally {
			$_GET  = $get;
			$_POST = $post;
		}
		// phpcs:enable WordPress.Security.NonceVerification
	}

	public function test_default_and_external_destinations_return_home(): void {
		$user = self::factory()->user->create_and_get();
		$this->assertSame( home_url( '/' ), rondo_login_redirect( admin_url(), '', $user ) );
		$this->assertSame( home_url( '/' ), rondo_login_redirect( 'https://attacker.test/', 'https://attacker.test/', $user ) );
	}

	public function test_entrance_accounts_keep_the_scanner_destination(): void {
		( new UserRoles() )->register_role();
		$user = self::factory()->user->create_and_get( [ 'role' => UserRoles::ENTREE_ROLE ] );
		$url  = home_url( '/oauth/authorize' );
		$this->assertSame( home_url( '/lidpas-scanner' ), rondo_login_redirect( $url, $url, $user ) );
	}
}
