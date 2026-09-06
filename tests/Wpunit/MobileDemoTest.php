<?php
namespace Tests\Wpunit;

use Rondo\MobileDemo\Plugin;
use Tests\Support\RondoTestCase;

/** Demo opt-in never widens the AWC pilot's trust boundary. */
final class MobileDemoTest extends RondoTestCase {
	private int $reviewer;
	private int $person;
	private array $params;

	protected function setUp(): void {
		parent::setUp();
		if ( ! defined( 'RONDO_MOBILE_DEMO' ) ) {
			define( 'RONDO_MOBILE_DEMO', true );
		}
		require_once dirname( __DIR__, 2 ) . '/mobile/demo-plugin/rondo-mobile-demo.php';
		update_option( 'home', 'https://demo.rondo.club' );
		update_option( 'rondo_is_demo_site', true );
		$this->reviewer = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->person   = $this->createPerson(
			[],
			[
				'first_name' => 'Alex',
				'last_name'  => 'Voorbeeld',
			]
			);
		update_user_meta( $this->reviewer, 'rondo_linked_person_id', $this->person );
		update_user_meta( $this->reviewer, '_rondo_synthetic_apple_review', true );
		update_post_meta( $this->person, '_rondo_feature_demo_key', 'parent' );
		update_option(
			'rondo_mobile_demo',
			[
				'enabled' => true,
				'epoch'   => str_repeat( 'e', 32 ),
				'ends_at' => time() + DAY_IN_SECONDS,
				'testers' => [
					[
						'user_id'   => $this->reviewer,
						'person_id' => $this->person,
					],
				],
			],
			false
			);
		$this->params = [
			'client_id'             => Plugin::CLIENT,
			'redirect_uri'          => Plugin::CALLBACK,
			'scope'                 => Plugin::SCOPE,
			'response_type'         => 'code',
			'state'                 => str_repeat( 's', 43 ),
			'code_challenge_method' => 'S256',
			'code_challenge'        => rtrim( strtr( base64_encode( hash( 'sha256', str_repeat( 'a', 43 ), true ) ), '+/', '-_' ), '=' ),
		];
		$this->bootRestControllers( [ \Rondo\REST\UserSettings::class, \Rondo\REST\People::class, Plugin::class ] );
	}

	public function test_demo_requires_its_own_origin_flag_and_synthetic_identity(): void {
		$this->assertTrue( Plugin::enabled() );
		$this->assertFalse( \Rondo\MobilePilot\Plugin::enabled() );
		$this->assertIsString( Plugin::issue( $this->params, $this->reviewer ) );
		update_option( 'home', 'https://rondo.svawc.nl' );
		$this->assertFalse( Plugin::enabled() );
		update_option( 'home', 'https://demo.rondo.club' );
		update_option( 'rondo_is_demo_site', false );
		$this->assertFalse( Plugin::enabled() );
		update_option( 'rondo_is_demo_site', true );
		delete_user_meta( $this->reviewer, '_rondo_synthetic_apple_review' );
		$this->assertWPError( Plugin::issue( $this->params, $this->reviewer ) );
		update_user_meta( $this->reviewer, '_rondo_synthetic_apple_review', true );
		delete_post_meta( $this->person, '_rondo_feature_demo_key' );
		$this->assertWPError( Plugin::issue( $this->params, $this->reviewer ) );
		update_post_meta( $this->person, '_rondo_feature_demo_key', 'parent' );
		get_userdata( $this->reviewer )->set_role( 'administrator' );
		$this->assertWPError( Plugin::issue( $this->params, $this->reviewer ) );
	}

	public function test_awc_callback_and_write_scope_cannot_authorize_on_demo(): void {
		foreach ( [ [ 'redirect_uri' => \Rondo\MobilePilot\Plugin::CALLBACK ], [ 'scope' => Plugin::PROFILE_SCOPE ] ] as $override ) {
			$this->assertWPError( Plugin::issue( array_merge( $this->params, $override ), $this->reviewer ) );
		}
	}

	public function test_demo_token_reads_own_household_but_cannot_write_or_survive_removal(): void {
		$code    = Plugin::issue( $this->params, $this->reviewer );
		$request = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/token' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
			[
				'grant_type'    => 'authorization_code',
				'client_id'     => Plugin::CLIENT,
				'redirect_uri'  => Plugin::CALLBACK,
				'code'          => $code,
				'code_verifier' => str_repeat( 'a', 43 ),
			]
			)
			);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$token   = $response->get_data()['access_token'];
		$request = new \WP_REST_Request( 'GET', '/' . Plugin::NS . '/read' );
		$request->set_header( 'Authorization', 'Bearer ' . $token );
		$request->set_query_params( [ 'resource' => 'household' ] );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ $this->person ], array_column( $response->get_data(), 'id' ) );
		foreach ( [ 'shift', 'profile' ] as $route ) {
			$write = new \WP_REST_Request( 'POST', '/' . Plugin::NS . '/' . $route );
			$write->set_header( 'Authorization', 'Bearer ' . $token );
			$this->assertSame( 403, rest_do_request( $write )->get_status() );
		}
		delete_user_meta( $this->reviewer, '_rondo_synthetic_apple_review' );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
	}
}
