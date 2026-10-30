<?php
namespace Tests\Wpunit;

use Rondo\REST\ClubDirectory;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class ClubDirectoryTest extends RondoTestCase {
	private array $payload;
	private int $status = 200;
	private int $calls  = 0;
	private array $keys = [];
	private string $query;
	private array $http_args = [];

	protected function set_up(): void {
		parent::set_up();
		if ( ! defined( 'RONDO_SYNC_URL' ) ) {
			define( 'RONDO_SYNC_URL', 'https://sync.example.test' ); }
		if ( ! defined( 'RONDO_SYNC_API_KEY' ) ) {
			define( 'RONDO_SYNC_API_KEY', 'test-only-key' ); }
		$this->query   = 'Club ' . wp_generate_password( 8, false );
		$this->payload = [
			'clubs'      => [ $this->club() ],
			'total'      => 1,
			'truncated'  => false,
			'fetched_at' => '2026-10-30T15:00:00Z',
		];
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->bootRestControllers( [ ClubDirectory::class ] );
		add_filter( 'pre_http_request', [ $this, 'http' ], 10, 3 );
	}

	protected function tear_down(): void {
		remove_filter( 'pre_http_request', [ $this, 'http' ], 10 );
		foreach ( $this->keys as $key ) {
			delete_transient( $key ); }
		parent::tear_down();
	}

	public function http( $pre, $args, $url ) {
		if ( strpos( $url, '/api/club-directory' ) === false ) {
			return $pre; }
		++$this->calls;
		$this->http_args = $args;
		return [
			'headers'  => [],
			'response' => [ 'code' => $this->status ],
			'body'     => wp_json_encode( $this->payload ),
		];
	}

	public function test_search_is_cached_but_authorization_is_checked_before_cached_reads(): void {
		$first = $this->request();
		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( 'Testclub', $first->get_data()['clubs'][0]['name'] );
		$this->assertSame( 'private, no-store', $first->get_headers()['Cache-Control'] );
		$this->request();
		$this->assertSame( 1, $this->calls );
		$this->assertSame( RONDO_SYNC_API_KEY, $this->http_args['headers']['X-Sync-API-Key'] );
		$this->assertSame( 0, $this->http_args['redirection'] );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->request()->get_status() );
		$id = $this->createRondoUser();
		wp_set_current_user( $id );
		$this->assertSame( 403, $this->request()->get_status() );
		( new \WP_User( $id ) )->add_cap( 'teams' );
		$this->assertSame( 200, $this->request()->get_status() );
		$this->assertSame( 1, $this->calls );
	}

	public function test_invalid_queries_are_rejected_without_contacting_sync(): void {
		foreach ( [
			[ 'search' => 'x' ],
			[ 'search' => '   ' ],
			[
				'search' => 'Valid',
				'field'  => 'password',
			],
			[ 'search' => str_repeat( 'x', 81 ) ],
		] as $params ) {
			$this->assertSame( 400, $this->request( '', $params )->get_status() );
		}
		$this->assertSame( 0, $this->calls );
	}

	public function test_source_errors_and_missing_fields_do_not_become_cached_empty_results(): void {
		$this->payload = [ 'error' => 'secret upstream details' ];
		$this->status  = 500;
		$response      = $this->request();
		$this->assertSame( 502, $response->get_status() );
		$this->assertStringNotContainsString( 'secret', wp_json_encode( $response->get_data() ) );
		$this->status = 200;
		$this->assertSame( 502, $this->request()->get_status() );
		$this->assertSame( 2, $this->calls );
		$this->payload = [
			'clubs'      => [],
			'total'      => 0,
			'truncated'  => false,
			'fetched_at' => '2026-10-30T15:00:00Z',
		];
		$this->assertSame( 200, $this->request()->get_status() );
		$this->assertSame( [], $this->request()->get_data()['clubs'] );
		$this->assertSame( 3, $this->calls );
	}

	public function test_detail_confirms_identity_and_allows_only_public_contract_fields(): void {
		$this->payload               = [
			'club'          => $this->club(),
			'contacts'      => [
				[
					'id'            => 'PERSON1',
					'name'          => '<b>Test Contact</b>',
					'function'      => 'Wedstrijdsecretaris',
					'phone'         => '',
					'mobile'        => '0612345678',
					'email'         => 'test@example.test',
					'source'        => 'Bond',
					'private_field' => 'do not expose',
				],
			],
			'fetched_at'    => '2026-10-30T15:00:00Z',
			'authorization' => 'secret',
		];
		$this->payload['club']['id'] = 'WRONG01';
		$this->assertSame( 502, $this->request( '/TEST001' )->get_status() );
		$this->payload['club']['id'] = 'TEST001';
		$response                    = $this->request( '/TEST001' );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Test Contact', $response->get_data()['contacts'][0]['name'] );
		$this->assertArrayNotHasKey( 'authorization', $response->get_data() );
		$this->assertArrayNotHasKey( 'private_field', $response->get_data()['contacts'][0] );
		$this->assertSame( 2, $this->calls );
	}

	public function test_busy_and_missing_clubs_remain_distinct_errors_and_are_not_cached(): void {
		foreach ( [ 429, 404 ] as $status ) {
			$this->status = $status;
			$this->assertSame( $status, $this->request( '/TEST001' )->get_status() );
		}
		$this->assertSame( 2, $this->calls );
	}

	public function test_non_scalar_contact_fields_and_missing_contact_lists_are_rejected(): void {
		$this->payload = [
			'club'       => $this->club(),
			'fetched_at' => '2026-10-30T15:00:00Z',
		];
		$this->assertSame( 502, $this->request( '/TEST001' )->get_status() );
		$this->payload['contacts'] = [
			[
				'id'       => 'PERSON1',
				'name'     => 'Contact',
				'function' => 'Secretaris',
				'phone'    => [],
				'mobile'   => '',
				'email'    => '',
				'source'   => '',
			],
		];
		$this->assertSame( 502, $this->request( '/TEST001' )->get_status() );
	}

	private function club(): array {
		return [
			'id'       => 'TEST001',
			'name'     => 'Testclub',
			'district' => 'Oost',
			'address'  => 'Teststraat 1',
			'postcode' => '1234 AB',
			'city'     => 'Teststad',
			'phone'    => '',
			'email'    => 'info@example.test',
		];
	}

	private function request( string $path = '', array $params = [] ) {
		$query        = $path ? [] : array_merge(
			[
				'search' => $this->query,
				'field'  => 'name',
			],
			$params
			);
		$this->keys[] = 'rondo_directory_' . md5( RONDO_SYNC_URL . $path . wp_json_encode( $query ) );
		$request      = new WP_REST_Request( 'GET', '/rondo/v1/external-clubs' . $path );
		$request->set_query_params( $query );
		return rest_do_request( $request );
	}
}
