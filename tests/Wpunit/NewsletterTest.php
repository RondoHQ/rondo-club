<?php
namespace Tests\Wpunit;

use Rondo\Communication\Newsletter;
use Rondo\Core\UserRoles;
use Rondo\Data\CredentialEncryption;
use Rondo\Fields\Fields;
use Rondo\Integrations\LapostaClient;
use Rondo\REST\Communication;
use Rondo\REST\NewsletterController;
use Tests\Support\RondoTestCase;

class NewsletterTest extends RondoTestCase {
	private string $role;
	private int $user_id;
	private int $id;
	private array $calls       = [];
	private array $remote      = [];
	private string $html       = '';
	private string $failure    = '';
	private string $definition = 'field = value';

	protected function set_up(): void {
		parent::set_up();
		$this->role = UserRoles::add_custom_role( 'Newsletter tests' );
		get_role( $this->role )->add_cap( 'communicatie' );
		UserRoles::sync_role_capabilities( $this->role );
		$this->user_id = $this->createRondoUser( [ 'role' => $this->role ] );
		wp_set_current_user( $this->user_id );
		$this->bootRestControllers( [ Communication::class, NewsletterController::class ] );
		delete_option( 'rondo_communication_channels' );
		delete_transient( 'rondo_laposta_retry_at' );
		CredentialEncryption::update_secret_option( LapostaClient::KEY_OPTION, 'test-secret' );
		update_option( Newsletter::CONFIG, [ 'template' => '<html><head><title>%%DOCUMENT_TITLE%%</title></head><body><div>%%PREHEADER%%</div><p>%%SALUTATION%%</p><h1>%%HEADING%%</h1>%%BODY_HTML%%<p>%%SIGNER_NAME%% %%SIGNER_ROLE%%</p><unsubscribe>Afmelden</unsubscribe><webversion>Online</webversion></body></html>' ] );
		update_user_meta(
			$this->user_id,
			Newsletter::PROFILE,
			[
				'active'     => true,
				'name'       => 'Test User',
				'role'       => 'Secretaris',
				'from_name'  => 'Test User | Club',
				'from_email' => 'secretaris@example.org',
				'reply_to'   => 'secretaris@example.org',
			]
			);
		$this->id = $this->request(
			'/rondo/v1/communications',
			'POST',
			[
				'title'       => 'Nieuwsbrief test',
				'channel_ids' => [ 'newsletter' ],
				'status'      => 'concept',
			]
			)->get_data()['id'];
		add_filter( 'pre_http_request', [ $this, 'http' ], 10, 3 );
	}

	protected function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'http' ], 10 );
		UserRoles::remove_custom_role( $this->role );
		delete_option( Newsletter::CONFIG );
		delete_option( LapostaClient::KEY_OPTION );
		delete_transient( 'rondo_newsletter_lists' );
		delete_transient( 'rondo_laposta_retry_at' );
		parent::tear_down();
	}

	private function request( string $route = '', string $method = 'GET', array $body = [] ): \WP_REST_Response {
		if ( ! str_starts_with( $route, '/rondo/' ) ) {
			$route = '/rondo/v1/communications/' . $this->id . '/newsletter' . $route;
		}
		$r = new \WP_REST_Request( $method, $route );
		if ( $body ) {
			$r->set_header( 'content-type', 'application/json' );
			$r->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $r );
	}

	public function http( $pre, $args, $url ) {
		if ( ! str_starts_with( $url, 'https://api.laposta.nl/v2/' ) ) {
			return $pre;
		}
		$path          = substr( wp_parse_url( $url, PHP_URL_PATH ), 3 );
		$this->calls[] = [ $args['method'], $path ];
		parse_str( $args['body'] ?? '', $body );
		if ( $path === '/list/list1' ) {
			$data = [
				'list' => [
					'list_id' => 'list1',
					'state'   => 'active',
					'name'    => 'Members',
				],
			];
		} elseif ( $path === '/segment/segment1' ) {
			$data = [
				'segment' => [
					'segment_id' => 'segment1',
					'list_id'    => 'list1',
					'name'       => 'Youth',
					'definition' => $this->definition,
				],
			];
		} elseif ( $path === '/campaign' && $args['method'] === 'POST' ) {
			$this->remote = array_merge(
				$body,
				[
					'campaign_id'        => 'campaign1',
					'delivery_requested' => null,
					'delivery_started'   => null,
					'delivery_ended'     => null,
				]
				);
			$this->normalize_lists();
			if ( $this->failure === 'create_timeout' ) {
				$this->failure = '';
				return new \WP_Error( 'timeout', 'timeout' );
			}
			$data = [ 'campaign' => $this->remote ];
		} elseif ( $path === '/campaign' ) {
			$data = [ 'data' => $this->remote ? [ [ 'campaign' => $this->remote ] ] : [] ];
		} elseif ( $path === '/campaign/campaign1' ) {
			if ( $args['method'] === 'POST' ) {
				$this->remote = array_merge( $this->remote, $body );
				$this->normalize_lists();
			}
			$data = [ 'campaign' => $this->remote ];
		} elseif ( $path === '/campaign/campaign1/content' ) {
			if ( $args['method'] === 'POST' ) {
				$this->html = $body['html'];
				if ( $this->failure === 'content_timeout' ) {
					$this->failure = '';
					return new \WP_Error( 'timeout', 'timeout' );
				}
			}
			$data = [
				'campaign' => [
					'html'   => $this->html,
					'report' => [],
				],
			];
		} else {
			$this->fail( 'Unexpected Laposta call: ' . $args['method'] . ' ' . $path );
		}
		return [
			'headers'  => [],
			'body'     => wp_json_encode( $data ),
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
		];
	}

	private function normalize_lists(): void {
		$lists = [];
		foreach ( $this->remote['list_ids'] as $key => $value ) {
			if ( is_int( $key ) ) {
				$lists[ $value ] = [];
			} else {
				$lists[ $key ] = $value;
			}
		}
		$this->remote['list_ids'] = $lists;
	}

	private function save( array $fields = [] ): \WP_REST_Response {
		return $this->request(
			'',
			'PUT',
			[
				'revision' => $this->request()->get_data()['revision'],
				'fields'   => $fields,
			]
			);
	}

	private function prepare( string $scope = 'segment' ): string {
		$r = $this->save(
			[
				'assignee_id'          => $this->user_id,
				'newsletter_subject'   => 'Subject',
				'newsletter_heading'   => 'Heading',
				'newsletter_body'      => '<p>Hello <strong>members</strong> &amp; friends.</p>',
				'newsletter_audiences' => [
					[
						'list_id'    => 'list1',
						'scope'      => $scope,
						'segment_id' => $scope === 'segment' ? 'segment1' : '',
					],
				],
			]
			);
		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		$r = $this->request( '/review', 'POST', [ 'revision' => $r->get_data()['revision'] ] );
		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		return $r->get_data()['token'];
	}

	public function test_draft_defaults_partial_sanitized_writes_stale_rejection_and_repeater_cleanup(): void {
		$initial = $this->request()->get_data();
		$this->assertSame( '', $initial['fields']['newsletter_body'] );
		$r = $this->save(
			[
				'newsletter_body'      => '<p onclick="evil()">Hallo <a href="javascript:evil()">leden</a></p>',
				'newsletter_audiences' => [
					[
						'list_id'    => 'list1',
						'scope'      => '',
						'segment_id' => '',
					],
				],
			]
			);
		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		$this->assertStringNotContainsString( 'onclick', $r->get_data()['fields']['newsletter_body'] );
		$this->assertStringNotContainsString( 'javascript:', $r->get_data()['fields']['newsletter_body'] );
		$this->assertSame( 'list1', get_post_meta( $this->id, 'newsletter_audiences_0_list_id', true ) );
		$this->assertSame(
			409,
			$this->request(
			'',
			'PUT',
			[
				'revision' => $initial['revision'],
				'fields'   => [ 'newsletter_subject' => 'Stale' ],
			]
			)->get_status()
			);
		$this->assertSame( 200, $this->save( [ 'newsletter_audiences' => [] ] )->get_status() );
		$this->assertSame( '', get_post_meta( $this->id, 'newsletter_audiences_0_list_id', true ) );
		$this->assertNotEmpty( Fields::get_for_post( $this->id, 'newsletter_body' ) );
	}

	public function test_permissions_and_configuration_secret_redaction(): void {
		$this->assertSame( 403, $this->request( '/rondo/v1/newsletter/settings' )->get_status() );
		$this->assertStringNotContainsString( 'test-secret', wp_json_encode( $this->request( '/rondo/v1/newsletter' )->get_data() ) );
		$this->assertNotSame( 'test-secret', get_option( LapostaClient::KEY_OPTION ) );
		get_role( $this->role )->remove_cap( 'communicatie' );
		UserRoles::sync_role_capabilities( $this->role );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
		$this->assertSame( 403, $this->request()->get_status() );
		wp_set_current_user( 0 );
		$this->assertNotSame( 200, $this->request()->get_status() );
	}

	public function test_create_readback_and_repeat_export_are_idempotent_and_never_complete_planning(): void {
		$token = $this->prepare();
		$r     = $this->request( '/export', 'POST', [ 'token' => $token ] );
		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		$this->assertSame( 'exported', $r->get_data()['status'] );
		$this->assertSame( [ 'list1' => [ 'segment1' ] ], $this->remote['list_ids'] );
		$this->assertStringContainsString( 'Test User Secretaris', $this->html );
		$this->assertSame( 'concept', Fields::get_for_post( $this->id, 'status' ) );
		$writes = count( array_filter( $this->calls, fn( $c ) => $c[0] === 'POST' ) );
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertCount( $writes, array_filter( $this->calls, fn( $c ) => $c[0] === 'POST' ) );
	}

	public function test_whole_list_is_explicit_and_survives_form_encoding(): void {
		$token = $this->prepare( 'all' );
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertSame( [ 'list1' => [] ], $this->remote['list_ids'] );
		$this->save(
			[
				'newsletter_audiences' => [
					[
						'list_id'    => 'list1',
						'scope'      => '',
						'segment_id' => '',
					],
				],
			]
			);
		$this->assertSame( 400, $this->request( '/review', 'POST', [ 'revision' => $this->request()->get_data()['revision'] ] )->get_status() );
	}

	public function test_review_expires_for_profile_or_audience_changes(): void {
		$token            = $this->prepare();
		$this->definition = 'changed';
		$this->assertSame( 409, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertEmpty( $this->remote );
		$this->definition = 'field = value';
		update_user_meta( $this->user_id, Newsletter::PROFILE, array_merge( Newsletter::profile( $this->user_id ), [ 'role' => 'Voorzitter' ] ) );
		$this->assertSame( 409, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertEmpty( $this->remote );
	}

	public function test_unknown_creation_result_recovers_without_duplicate(): void {
		$token         = $this->prepare();
		$this->failure = 'create_timeout';
		$this->assertSame( 502, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$r = $this->request( '/export', 'POST', [ 'token' => $token ] );
		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		$this->assertCount( 1, array_filter( $this->calls, fn( $c ) => $c === [ 'POST', '/campaign' ] ) );
	}

	public function test_interrupted_content_update_resumes_but_external_edits_are_never_overwritten(): void {
		$token         = $this->prepare();
		$this->failure = 'content_timeout';
		$this->assertSame( 502, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->html  = '<p>External edit</p>';
		$this->calls = [];
		$this->assertSame( 409, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertEmpty( array_filter( $this->calls, fn( $c ) => $c[0] === 'POST' ) );
	}

	public function test_planned_campaign_and_send_endpoints_are_blocked(): void {
		$token = $this->prepare();
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->remote['delivery_requested'] = '2026-10-02 12:00:00';
		$this->calls                        = [];
		$this->assertSame( 409, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertEmpty( array_filter( $this->calls, fn( $c ) => $c[0] === 'POST' ) );
		$this->assertWPError( ( new LapostaClient() )->request( 'POST', '/campaign/campaign1/action', [ 'action' => 'send' ] ) );
	}
	public function test_body_backslashes_and_link_quotes_survive_storage_and_duplicate_is_unlinked(): void {
		$body = '<p>Pad C:\\nieuws <a href="https://example.org/?a=1&amp;b=2">link</a></p>';
		$r    = $this->save( [ 'newsletter_body' => $body ] );
		$this->assertSame( $body, $r->get_data()['fields']['newsletter_body'] );
		$token = $this->prepare();
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$copy = $this->request( '/rondo/v1/communications/' . $this->id . '/action', 'POST', [ 'action' => 'duplicate' ] );
		$this->assertEmpty( get_post_meta( $copy->get_data()['id'], Newsletter::EXPORT, true ) );
	}

	public function test_shared_lock_and_review_user_binding(): void {
		$token = $this->prepare();
		add_option( 'rondo_comm_edit_' . $this->id, time() );
		$this->assertSame( 409, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		delete_option( 'rondo_comm_edit_' . $this->id );
		$other = $this->createRondoUser( [ 'role' => $this->role ] );
		wp_set_current_user( $other );
		$this->assertSame( 409, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertEmpty( $this->remote );
	}
}
