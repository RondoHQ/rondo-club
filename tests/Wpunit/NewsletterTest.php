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
	private array $test_emails = [];

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
		} elseif ( $path === '/campaign/campaign1/action/testmail' && $args['method'] === 'POST' ) {
			$this->test_emails[] = $body['email'];
			if ( $this->failure === 'test_timeout' ) {
				return new \WP_Error( 'timeout', 'timeout' );
			}
			$data = [ 'campaign' => $this->remote ];
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

	public function test_images_survive_save_preview_and_export_without_unsafe_attributes(): void {
		$this->prepare();
		$image = '<img src="https://example.org/photo.jpg" alt="Clubfoto" title="Onze club" width="1600" height="900">';
		$r     = $this->save( [ 'newsletter_body' => '<p>Onze club</p>' . str_replace( '>', ' onerror="evil()" style="position:fixed" class="editor-image">', $image ) . '<p>Sportieve groet</p>' ] );
		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		$this->assertSame( '<p>Onze club</p>' . $image . '<p>Sportieve groet</p>', $this->request()->get_data()['fields']['newsletter_body'] );
		$this->assertSame( '<p>Onze club</p>' . $image . '<p>Sportieve groet</p>', Fields::get_for_post( $this->id, 'newsletter_body' ) );
		$preview = $this->request( '/preview', 'POST' );
		$this->assertSame( 200, $preview->get_status() );
		$this->assertStringContainsString( 'src="https://example.org/photo.jpg"', $preview->get_data()['html'] );
		$this->assertStringContainsString( 'max-width:100%;height:auto;display:block;margin:12px 0;', $preview->get_data()['html'] );
		$review = $this->request( '/review', 'POST', [ 'revision' => $r->get_data()['revision'] ] );
		$this->assertSame( 200, $review->get_status(), wp_json_encode( $review->get_data() ) );
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $review->get_data()['token'] ] )->get_status() );
		$this->assertStringContainsString( 'src="https://example.org/photo.jpg"', $this->html );
		$this->assertStringContainsString( 'alt="Clubfoto"', $this->html );
		$this->assertStringContainsString( 'max-width:100%;height:auto;display:block;margin:12px 0;', $this->html );
		$this->assertStringContainsString( 'height="900"><p>Sportieve groet</p>', $this->html );
		$this->assertStringNotContainsString( 'onerror', $this->html );
		$this->assertStringNotContainsString( 'position:fixed', $this->html );
		$this->assertStringNotContainsString( 'javascript:', Newsletter::sanitize_body( '<img src="javascript:evil()" onerror="evil()">' ) );
		$this->assertStringNotContainsString( 'data:', Newsletter::sanitize_body( '<img src="data:image/png;base64,AAAA">' ) );
	}

	public function test_heading_spacing_survives_preview_and_export(): void {
		$this->prepare();
		$body = '<p>Intro</p><h2>Kop</h2><p>Tekst</p><ul><li>Punt</li></ul><h3>Tussenkop</h3><p>Meer tekst</p>';
		$r    = $this->save( [ 'newsletter_body' => $body ] );
		$this->assertSame( 200, $r->get_status() );
		$this->assertSame( $body, Fields::get_for_post( $this->id, 'newsletter_body' ) );
		$preview = $this->request( '/preview', 'POST' );
		$this->assertSame( 200, $preview->get_status() );
		$review = $this->request( '/review', 'POST', [ 'revision' => $r->get_data()['revision'] ] );
		$this->assertSame( 200, $review->get_status() );
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $review->get_data()['token'] ] )->get_status() );
		foreach ( [ $preview->get_data()['html'], $this->html ] as $html ) {
			$this->assertStringContainsString( '<h2 style="margin:24px 0 8px;line-height:1.4;">Kop</h2>', $html );
			$this->assertStringContainsString( '<h3 style="margin:24px 0 8px;line-height:1.4;">Tussenkop</h3>', $html );
		}
	}

	public function test_list_spacing_is_compact_in_preview_and_export_and_preserves_paragraphs(): void {
		$this->prepare();
		$body = '<p>Intro</p><ul><li><p>Punt</p><ol><li><p>Genest</p></li></ol></li><li><p>Tweede punt</p></li></ul><p>Na de lijst</p>';
		$r    = $this->save( [ 'newsletter_body' => $body ] );
		$this->assertSame( 200, $r->get_status() );
		$this->assertSame( $body, Fields::get_for_post( $this->id, 'newsletter_body' ) );
		$preview = $this->request( '/preview', 'POST' );
		$this->assertSame( 200, $preview->get_status() );
		$review = $this->request( '/review', 'POST', [ 'revision' => $r->get_data()['revision'] ] );
		$this->assertSame( 200, $review->get_status() );
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $review->get_data()['token'] ] )->get_status() );
		foreach ( [ $preview->get_data()['html'], $this->html ] as $html ) {
			$this->assertStringContainsString( '<ul style="margin:0 0 12px;padding:0 0 0 24px;">', $html );
			$this->assertStringContainsString( '<ol style="margin:0 0 12px;padding:0 0 0 24px;">', $html );
			$this->assertStringContainsString( '<li style="margin:0 0 4px;"><p style="margin:0!important;">Punt</p>', $html );
			$this->assertStringContainsString( '<p style="margin:0!important;">Genest</p>', $html );
			$this->assertStringContainsString( '<p style="margin:0!important;">Tweede punt</p>', $html );
			$this->assertStringContainsString( '<p>Intro</p>', $html );
			$this->assertStringContainsString( '<p>Na de lijst</p>', $html );
		}
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

	public function test_testmail_sends_only_to_the_requested_address_without_changing_the_campaign(): void {
		$token = $this->prepare();
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$before      = get_post_meta( $this->id, Newsletter::EXPORT, true );
		$remote      = $this->remote;
		$this->calls = [];
		$r           = $this->request(
			'/testmail',
			'POST',
			[
				'email'    => ' tester@example.org ',
				'revision' => Newsletter::revision( $this->id ),
			]
			);
		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		$this->assertSame(
			[
				'email'  => 'tester@example.org',
				'status' => 'requested',
			],
			$r->get_data()
			);
		$this->assertSame( [ 'tester@example.org' ], $this->test_emails );
		$this->assertSame( [ [ 'POST', '/campaign/campaign1/action/testmail' ] ], array_values( array_filter( $this->calls, fn( $c ) => $c[0] === 'POST' ) ) );
		$this->assertSame( $remote, $this->remote );
		$this->assertSame( $before, get_post_meta( $this->id, Newsletter::EXPORT, true ) );
		$this->assertSame( 'concept', Fields::get_for_post( $this->id, 'status' ) );
	}

	public function test_testmail_rejects_invalid_recipients_stale_or_unexported_drafts(): void {
		$revision = Newsletter::revision( $this->id );
		foreach ( [ '', 'invalid', 'a@example.org,b@example.org', "a@example.org\r\nBcc:b@example.org", [ 'a@example.org' ] ] as $email ) {
			$this->assertSame(
				400,
				$this->request(
				'/testmail',
				'POST',
				[
					'email'    => $email,
					'revision' => $revision,
				]
				)->get_status()
				);
		}
		$this->assertSame(
			409,
			$this->request(
			'/testmail',
			'POST',
			[
				'email'    => 'a@example.org',
				'revision' => $revision,
			]
			)->get_status()
			);
		$token = $this->prepare();
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->calls = [];
		$this->assertSame(
			409,
			$this->request(
			'/testmail',
			'POST',
			[
				'email'    => 'a@example.org',
				'revision' => 'stale',
			]
			)->get_status()
			);
		$this->assertSame(
			400,
			$this->request(
			'/testmail',
			'POST',
			[
				'email'       => 'a@example.org',
				'revision'    => Newsletter::revision( $this->id ),
				'campaign_id' => 'other',
			]
			)->get_status()
			);
		$this->save( [ 'newsletter_body' => '<p>Changed</p>' ] );
		$this->assertSame(
			409,
			$this->request(
			'/testmail',
			'POST',
			[
				'email'    => 'a@example.org',
				'revision' => Newsletter::revision( $this->id ),
			]
			)->get_status()
			);
		$this->assertEmpty( $this->calls );
		$this->assertEmpty( $this->test_emails );
	}

	public function test_testmail_rejects_remote_edits_and_scheduled_or_sent_campaigns(): void {
		$token = $this->prepare();
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$body        = [
			'email'    => 'a@example.org',
			'revision' => Newsletter::revision( $this->id ),
		];
		$html        = $this->html;
		$this->calls = [];
		$this->html  = '<p>Edited in Laposta</p>';
		$this->assertSame( 409, $this->request( '/testmail', 'POST', $body )->get_status() );
		$this->html = $html;
		foreach ( [ 'delivery_requested', 'delivery_started', 'delivery_ended' ] as $field ) {
			$this->remote[ $field ] = '2026-10-06 12:00:00';
			$this->assertSame( 409, $this->request( '/testmail', 'POST', $body )->get_status() );
			$this->remote[ $field ] = null;
		}
		$this->assertEmpty( array_filter( $this->calls, fn( $c ) => $c[0] === 'POST' ) );
		$this->assertEmpty( $this->test_emails );
	}

	public function test_testmail_respects_permissions_and_shared_lock_and_does_not_retry_timeouts(): void {
		$token = $this->prepare();
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$body        = [
			'email'    => 'a@example.org',
			'revision' => Newsletter::revision( $this->id ),
		];
		$this->calls = [];
		add_option( 'rondo_comm_edit_' . $this->id, time() );
		$this->assertSame( 409, $this->request( '/testmail', 'POST', $body )->get_status() );
		delete_option( 'rondo_comm_edit_' . $this->id );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $this->request( '/testmail', 'POST', $body )->get_status() );
		wp_set_current_user( 0 );
		$this->assertNotSame( 200, $this->request( '/testmail', 'POST', $body )->get_status() );
		$this->assertEmpty( $this->calls );
		wp_set_current_user( $this->user_id );
		$this->failure = 'test_timeout';
		$r             = $this->request( '/testmail', 'POST', $body );
		$this->assertSame( 502, $r->get_status() );
		$this->assertSame( 'newsletter_test_uncertain', $r->get_data()['code'] );
		$this->assertSame( [ 'a@example.org' ], $this->test_emails );
		$this->assertNull( $this->remote['delivery_requested'] );
	}

	public function test_planned_campaign_and_send_endpoints_are_blocked(): void {
		$token = $this->prepare();
		$this->assertSame( 200, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->remote['delivery_requested'] = '2026-10-02 12:00:00';
		$this->calls                        = [];
		$this->assertSame( 409, $this->request( '/export', 'POST', [ 'token' => $token ] )->get_status() );
		$this->assertEmpty( array_filter( $this->calls, fn( $c ) => $c[0] === 'POST' ) );
		$this->assertWPError( ( new LapostaClient() )->request( 'POST', '/campaign/campaign1/action', [ 'action' => 'send' ] ) );
		$client = new LapostaClient();
		foreach ( [ '/campaign/campaign1/action/send', '/campaign/campaign1/action/schedule', '/campaign/campaign1/action/testmail/extra' ] as $path ) {
			$this->assertWPError( $client->request( 'POST', $path, [ 'email' => 'a@example.org' ] ) );
		}
		$this->assertWPError( $client->request( 'GET', '/campaign/campaign1/action/testmail', [ 'email' => 'a@example.org' ] ) );
		$this->assertWPError( $client->request( 'POST', '/campaign/campaign1/action/testmail', [ 'email' => 'invalid' ] ) );
		$this->assertWPError(
			$client->request(
			'POST',
			'/campaign/campaign1/action/testmail',
			[
				'email'  => 'a@example.org',
				'action' => 'send',
			]
			)
			);
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
