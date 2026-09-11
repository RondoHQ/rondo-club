<?php
namespace Tests\Wpunit;

use Rondo\VOG\VogDocument;
use Rondo\VOG\VogSubmissions as Store;
use Rondo\REST\VogSubmissions;
use Rondo\Fields\Fields;
use Tests\Support\RondoTestCase;

class VogSubmissionTest extends RondoTestCase {
	private int $member;
	private int $person;
	private int $reviewer;
	private \WP_REST_Server $server;
	private array $paths = [];

	protected function set_up(): void {
		parent::set_up();
		Store::register();
		$this->member = $this->createRondoUser();
		$this->person = $this->createPerson(
			[],
			[
				'first_name' => 'Test',
				'infix'      => 'van',
				'last_name'  => 'Voorbeeld',
				'birthdate'  => '1990-01-02',
			]
			);
		update_user_meta( $this->member, 'rondo_linked_person_id', $this->person );
		$this->reviewer = $this->createRondoUser();
		get_user_by( 'id', $this->reviewer )->add_cap( 'vog' );
		$this->server = $this->bootRestControllers( [ VogSubmissions::class ] );
		delete_option( Store::RULES );
		wp_set_current_user( $this->member );
	}

	protected function tear_down(): void {
		foreach ( $this->paths as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		parent::tear_down();
	}

	private function request( string $method, string $route, array $params = [] ) {
		$request = new \WP_REST_Request( $method, '/rondo/v1' . $route );
		$request->set_body_params( $params );
		return $this->server->dispatch( $request );
	}

	private function submission( string $source = 'digital', ?int $code = 0 ): int {
		$file = [
			'name' => bin2hex( random_bytes( 16 ) ) . '.pdf',
			'type' => 'application/pdf',
		];
		$path = Store::path( $file );
		file_put_contents( $path, '%PDF-synthetic' );
		$this->paths[]  = $path;
		$file['sha256'] = hash_file( 'sha256', $path );
		$id             = Store::create( $this->person, $this->member, $source, [ $file ], [] );
		$data           = Store::get( $id );
		$data['status'] = $source === 'paper' ? 'waiting_paper' : 'review';
		$data['code']   = $code;
		Store::save( $id, $data );
		return $id;
	}

	private function approval( int $id, array $extra = [] ) {
		return $this->request(
			'POST',
			'/vog/submissions/' . $id . '/review',
			array_merge(
			[
				'version'   => Store::get( $id )['version'],
				'action'    => 'approve',
				'method'    => 'gaav_manual',
				'date'      => gmdate( 'Y-m-d' ),
				'confirmed' => true,
				'note'      => 'Origineel en inhoud gecontroleerd.',
			],
			$extra
			)
			);
	}

	public function test_private_access_and_wrong_record_types(): void {
		$id = $this->submission();
		$this->assertFalse( current_user_can( 'edit_post', $id ) );
		$this->assertFalse( current_user_can( 'delete_post', $id ) );
		$this->assertSame( 403, $this->request( 'GET', '/vog/submissions' )->get_status() );
		$this->assertSame( 403, $this->approval( $id )->get_status() );
		$this->assertSame( 200, $this->request( 'GET', '/vog/submissions/' . $id . '/files/0' )->get_status() );
		$this->assertNull( $this->request( 'GET', '/vog/submissions/' . $id . '/files/0' )->get_data() );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $this->request( 'GET', '/vog/submissions/' . $id . '/files/0' )->get_status() );
		wp_set_current_user( $this->reviewer );
		$listed = $this->request( 'GET', '/vog/submissions' )->get_data();
		$this->assertSame( $id, $listed['items'][0]['id'] );
		$this->assertSame( 403, $this->request( 'POST', '/vog/submissions/' . $this->person . '/review' )->get_status() );
		$this->assertSame( 404, $this->request( 'GET', '/vog/submissions/' . $id . '/files/999' )->get_status() );
	}

	public function test_iva_approver_does_not_gain_vog_access(): void {
		$id   = $this->submission();
		$user = $this->createRondoUser();
		get_user_by( 'id', $user )->add_cap( 'rondo_iva_approve' );
		get_user_by( 'id', $user )->add_cap( 'vrijwilligers' );
		wp_set_current_user( $user );
		$this->assertSame( 403, $this->request( 'GET', '/vog/submissions' )->get_status() );
		$this->assertSame( 403, $this->request( 'GET', '/vog/submissions/' . $id . '/files/0' )->get_status() );
	}

	public function test_digital_review_requires_authenticity_and_confirmation(): void {
		foreach ( [ null, 1, 2, 3, 4, 5, 6, 7 ] as $code ) {
			$id = $this->submission( 'digital', $code );
			wp_set_current_user( $this->reviewer );
			$this->assertSame( 400, $this->approval( $id )->get_status() );
		}
		$id = $this->submission();
		update_post_meta( $this->person, 'vog_justis_submitted_date', '2026-01-01' );
		wp_update_post(
			[
				'ID'                => $this->person,
				'post_modified'     => '2020-01-01 00:00:00',
				'post_modified_gmt' => '2020-01-01 00:00:00',
			]
			);
		$this->assertSame( 400, $this->approval( $id, [ 'confirmed' => false ] )->get_status() );
		$this->assertSame( 200, $this->approval( $id )->get_status() );
		$this->assertSame( '', get_post_meta( $this->person, 'vog_justis_submitted_date', true ) );
		$this->assertGreaterThan( '2020-01-01 00:00:00', get_post( $this->person )->post_modified_gmt );
		$this->assertSame( gmdate( 'Ymd' ), Fields::get_for_post( $this->person, 'datum_vog' ) );
		$this->assertSame( [], Store::get( $id )['files'] );
		$this->assertSame( [], Store::get( $id )['parsed'] );
		$this->assertSame( 404, $this->request( 'GET', '/vog/submissions/' . $id . '/files/0' )->get_status() );
		$this->assertSame( 409, $this->approval( $id )->get_status() );
	}

	public function test_paper_requires_separate_explicit_original_check(): void {
		$id = $this->submission( 'paper', null );
		wp_set_current_user( $this->reviewer );
		$this->assertSame( 400, $this->approval( $id )->get_status() );
		$this->assertSame( 400, $this->request( 'POST', '/vog/submissions/' . $id . '/retry' )->get_status() );
		$this->assertSame( 200, $this->approval( $id, [ 'method' => 'paper_original' ] )->get_status() );
		$this->assertSame( 'paper_original', Store::get( $id )['method'] );
		$this->assertNull( Store::get( $id )['code'] );
		$scan = $this->submission( 'digital_scan', null );
		$this->assertSame( 400, $this->approval( $scan, [ 'method' => 'paper_original' ] )->get_status() );
	}

	public function test_stale_review_newer_date_and_changed_identity_do_not_write(): void {
		$id = $this->submission();
		wp_set_current_user( $this->reviewer );
		$this->assertSame( 409, $this->approval( $id, [ 'version' => 0 ] )->get_status() );
		Fields::update_for_post( $this->person, 'datum_vog', gmdate( 'Y-m-d' ) );
		$this->assertSame( 409, $this->approval( $id, [ 'date' => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) ] )->get_status() );
		update_user_meta( $this->member, 'rondo_linked_person_id', $this->createPerson() );
		$this->assertSame( 409, $this->approval( $id )->get_status() );
	}

	public function test_expiry_and_replacement_preserve_prior_validity(): void {
		Fields::update_for_post( $this->person, 'datum_vog', gmdate( 'Y-m-d' ) );
		$first  = $this->submission();
		$path   = Store::path( Store::get( $first )['files'][0] );
		$second = $this->submission();
		$this->assertFileDoesNotExist( $path );
		$this->assertSame( 'replaced', Store::get( $first )['status'] );
		$data            = Store::get( $second );
		$data['expires'] = time() - 1;
		Store::save( $second, $data );
		$this->assertSame( 404, $this->request( 'GET', '/vog/submissions/' . $second . '/files/0' )->get_status() );
		Store::cleanup();
		$this->assertSame( 'expired', Store::get( $second )['status'] );
		$this->assertSame( gmdate( 'Ymd' ), Fields::get_for_post( $this->person, 'datum_vog' ) );
	}

	public function test_former_member_and_demo_cannot_upload(): void {
		$this->assertTrue( ( new VogSubmissions() )->member_permission() );
		Fields::update_for_post( $this->person, 'former_member', true );
		$this->assertFalse( ( new VogSubmissions() )->member_permission() );
		Fields::update_for_post( $this->person, 'former_member', false );
		update_option( 'rondo_is_demo_site', true );
		$this->assertFalse( ( new VogSubmissions() )->member_permission() );
	}

	public function test_parser_and_rules_fail_closed(): void {
		$text = "Verklaring Omtrent het Gedrag\nDate Datum 4 september 2026\nOur reference Ons kenmerk 999999999\nSurname Geslachtsnaam Voorbeeld\nPrefix to surname Tussenvoegsels van\nGiven names Voorna(a)m(en) Test\nDate of birth Geboortedatum 2 januari 1990\nHierbij geef ik u de VOG die u nodig heeft voor:\nVrijwilliger bij Testclub\nEr is bij deze screening uitgegaan van het volgende profiel:\n84\nOp de volgende pagina\n";
		$data = VogDocument::parse( $text );
		$this->assertSame( [ '84' ], $data['codes'] );
		$this->assertSame( '1990-01-02', $data['birthdate'] );
		$this->assertSame( [], VogDocument::parse( $text . "Date Datum 5 september 2026\n" ) );
		$this->assertSame( [], VogDocument::parse( str_replace( '4 september 2026', '31 februari 2026', $text ) ) );
		$data['date'] = gmdate( 'Y-m-d' );
		$rule         = [
			[
				'organization' => 'Testclub',
				'function'     => 'Vrijwilliger',
				'codes'        => [ '84' ],
			],
		];
		$this->assertSame( [], VogDocument::reasons( $data, $this->person, $rule ) );
		$this->assertNotEmpty( VogDocument::reasons( $data, $this->person, [] ) );
		$rule[0]['codes'][] = '85';
		$this->assertNotEmpty( VogDocument::reasons( $data, $this->person, $rule ) );
		$data['first_name'] = 'T.';
		$this->assertNotEmpty( VogDocument::reasons( $data, $this->person, $rule ) );
	}

	public function test_encrypted_pdf_automatic_approval_and_empty_rules_review(): void {
		if ( getenv( 'RONDO_TEST_PYTHON' ) && ! defined( 'RONDO_VOG_PYTHON' ) ) {
			define( 'RONDO_VOG_PYTHON', getenv( 'RONDO_TEST_PYTHON' ) );
		}
		$fixture = '/tmp/rondo-vog-synthetic.pdf';
		if ( ! is_file( $fixture ) ) {
			$this->markTestSkipped( 'Generate the synthetic PDF with tests/python/make_vog_fixture.py first (required in CI).' );
		}
		$this->assertTrue( VogDocument::read( '--health' )['available'] ?? false );
		$mock = static fn() => [
			'response' => [ 'code' => 200 ],
			'body'     => '{"response_code":0}',
		];
		add_filter( 'pre_http_request', $mock );
		try {
			foreach ( [ false, true ] as $configured ) {
				$id   = $this->submission();
				$data = Store::get( $id );
				$path = Store::path( $data['files'][0] );
				copy( $fixture, $path );
				$data['files'][0]['sha256'] = hash_file( 'sha256', $path );
				$data['status']             = 'checking';
				Store::save( $id, $data );
				if ( $configured ) {
					update_option(
						Store::RULES,
						[
							[
								'organization' => 'Testclub',
								'function'     => 'Vrijwilliger',
								'codes'        => [ '84' ],
							],
						]
						);
				}
				Store::process( $id );
				$result = Store::get( $id );
				$this->assertSame( $configured ? 'approved' : 'review', $result['status'] );
				$this->assertSame( 0, $result['code'] );
				if ( $configured ) {
					$this->assertSame( 'gaav_auto', $result['method'] );
					$this->assertSame( gmdate( 'Ymd' ), Fields::get_for_post( $this->person, 'datum_vog' ) );
					$this->assertFileDoesNotExist( $path );
				} else {
					$this->assertNotEmpty( $result['reason'] );
					$this->assertEmpty( Fields::get_for_post( $this->person, 'datum_vog' ) );
				}
			}
		} finally {
			remove_filter( 'pre_http_request', $mock );
		}
	}

	public function test_gaav_contract_and_invalid_responses(): void {
		$path          = tempnam( sys_get_temp_dir(), 'vog-test-' );
		$this->paths[] = $path;
		file_put_contents( $path, 'synthetic unchanged bytes' );
		foreach ( [ [ 0, 0 ], [ '0', null ], [ false, null ], [ 8, null ], [ 2, 2 ] ] as [ $wire, $expected ] ) {
			$mock = function ( $pre, $args, $url ) use ( $wire ) {
				$this->assertSame( 'https://www.validatie.nl/api/valideer/', $url );
				$this->assertSame( 0, $args['redirection'] );
				$this->assertTrue( $args['sslverify'] );
				$this->assertStringContainsString( 'synthetic unchanged bytes', $args['body'] );
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode( [ 'response_code' => $wire ] ),
				];
			};
			add_filter( 'pre_http_request', $mock, 10, 3 );
			$this->assertSame( $expected, VogDocument::validate( $path ) );
			remove_filter( 'pre_http_request', $mock, 10 );
		}
	}

	public function test_changed_document_cannot_reuse_validation(): void {
		$id = $this->submission();
		file_put_contents( Store::path( Store::get( $id )['files'][0] ), 'modified document' );
		wp_set_current_user( $this->reviewer );
		$this->assertSame( 409, $this->approval( $id )->get_status() );
	}

	public function test_technical_retry_is_bounded_and_never_clears_date(): void {
		$id = $this->submission( 'digital', null );
		Fields::update_for_post( $this->person, 'datum_vog', gmdate( 'Y-m-d' ) );
		$data           = Store::get( $id );
		$data['status'] = 'checking';
		Store::save( $id, $data );
		$mock = static fn() => new \WP_Error( 'timeout', 'Synthetic timeout' );
		add_filter( 'pre_http_request', $mock );
		for ( $i = 0; $i < 7; ++$i ) {
			Store::process( $id );
		}
		remove_filter( 'pre_http_request', $mock );
		$this->assertSame( 5, Store::get( $id )['attempts'] );
		$this->assertSame( 'technical', Store::get( $id )['status'] );
		$this->assertFalse( wp_next_scheduled( 'rondo_vog_retry', [ $id ] ) );
		$this->assertSame( gmdate( 'Ymd' ), Fields::get_for_post( $this->person, 'datum_vog' ) );
	}

	public function test_rules_are_admin_only_and_empty_by_default(): void {
		$this->assertSame( [], get_option( Store::RULES, [] ) );
		wp_set_current_user( $this->reviewer );
		$this->assertSame( 403, $this->request( 'POST', '/vog/approval-rules', [ 'rules' => [] ] )->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame(
			400,
			$this->request(
			'POST',
			'/vog/approval-rules',
			[
				'rules' => [
					[
						'organization' => '',
						'function'     => 'Vrijwilliger',
						'codes'        => [ '84' ],
					],
				],
			]
			)->get_status()
			);
	}
}
