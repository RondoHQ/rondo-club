<?php

namespace Tests\Wpunit;

use Rondo\REST\Vog;
use Rondo\REST\Volunteer;
use Rondo\VOG\VOGEmail;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

class VogProfileTextTest extends RondoTestCase {

	private WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		$this->server = $this->bootRestControllers( [ Vog::class, Volunteer::class ] );
	}

	protected function tear_down(): void {
		delete_option( VOGEmail::OPTION_PROFILE_TEXT_MISSING );
		delete_option( VOGEmail::OPTION_PROFILE_TEXT_EXPIRED );
		delete_option( VOGEmail::OPTION_PROFILE_TEXT_RENEWAL );
		parent::tear_down();
	}

	public function test_default_profile_texts_are_available_without_configuration(): void {
		$this->assertSame( VOGEmail::DEFAULT_PROFILE_TEXTS, ( new VOGEmail() )->get_profile_texts() );
	}

	public function test_admin_can_update_profile_texts(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$request = new WP_REST_Request( 'POST', '/rondo/v1/vog/settings' );
		$request->set_body_params(
			[
				'profile_text_missing' => 'Vraag een VOG aan bij Team Veiligheid.',
				'profile_text_expired' => 'Je VOG is verlopen. Mail veiligheid@example.test.',
				'profile_text_renewal' => 'Je VOG verloopt binnenkort. Wij nemen contact op.',
			]
		);
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Vraag een VOG aan bij Team Veiligheid.', $data['profile_text_missing'] );
		$this->assertSame( 'Je VOG is verlopen. Mail veiligheid@example.test.', $data['profile_text_expired'] );
		$this->assertSame( 'Je VOG verloopt binnenkort. Wij nemen contact op.', $data['profile_text_renewal'] );
	}

	public function test_member_vog_response_contains_configured_profile_texts(): void {
		$vog_email = new VOGEmail();
		$vog_email->update_profile_text( 'expired', 'Neem contact op met Team Veiligheid.' );

		$person_id = $this->createPerson( [ 'post_title' => 'VOG Lid' ] );
		$user_id   = $this->createRondoUser( [ 'user_login' => 'vog_profile_member' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/rondo/v1/vog/me' );
		$response = $this->server->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Neem contact op met Team Veiligheid.', $data['profile_texts']['expired'] );
		$this->assertSame( VOGEmail::DEFAULT_PROFILE_TEXTS['missing'], $data['profile_texts']['missing'] );
	}
}
