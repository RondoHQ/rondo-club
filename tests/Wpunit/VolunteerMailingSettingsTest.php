<?php
namespace Tests\Wpunit;

use Rondo\REST\Volunteer;
use Rondo\Volunteer\WeekendVolunteerMail;
use Tests\Support\RondoTestCase;

class VolunteerMailingSettingsTest extends RondoTestCase {
	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		$this->server = $this->bootRestControllers( [ Volunteer::class ] );
	}

	private function request( string $method = 'GET', array $params = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, '/rondo/v1/volunteer-mailing' );
		$request->set_body_params( $params );
		return $this->server->dispatch( $request );
	}

	public function test_volunteer_can_read_and_update_or_disable_overview_without_sending_mail(): void {
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		$mails = 0;
		add_filter(
			'pre_wp_mail',
			static function () use ( &$mails ) {
				++$mails;
				return true;
			}
			);
		$response = $this->request();
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$this->assertSame( 200, $this->request( 'PUT', [ 'overview_email' => ' clubhouse@example.org ' ] )->get_status() );
		$this->assertSame( 'clubhouse@example.org', get_option( WeekendVolunteerMail::OVERVIEW_EMAIL ) );
		foreach ( [ 'invalid-address', 'one@example.org,two@example.org', [ 'one@example.org' ] ] as $invalid ) {
			$this->assertSame( 400, $this->request( 'PUT', [ 'overview_email' => $invalid ] )->get_status() );
		}
		$this->assertSame( 'clubhouse@example.org', get_option( WeekendVolunteerMail::OVERVIEW_EMAIL ) );
		$this->assertSame( 200, $this->request( 'PUT', [ 'overview_email' => '' ] )->get_status() );
		$this->assertSame( '', get_option( WeekendVolunteerMail::OVERVIEW_EMAIL ) );
		$this->assertSame( 0, $mails );
	}

	public function test_members_cannot_view_status_or_change_the_recipient(): void {
		update_option( WeekendVolunteerMail::OVERVIEW_EMAIL, 'clubhouse@example.org' );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $this->request()->get_status() );
		$this->assertSame( 403, $this->request( 'PUT', [ 'overview_email' => 'other@example.org' ] )->get_status() );
		$this->assertSame( 'clubhouse@example.org', get_option( WeekendVolunteerMail::OVERVIEW_EMAIL ) );
	}
}
