<?php
namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;
use Rondo\Matches\CompensationService as Service;
use Rondo\Matches\CompensationAgreements as Agreements;
use Rondo\Matches\CompensationPayroll as Payroll;
use Rondo\Fields\Fields;

class MatchCompensationAgreementsTest extends RondoTestCase {
	private int $team;
	private int $person;
	protected function set_up(): void {
		parent::set_up();
		Service::register_types();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->team   = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
			]
			);
		$this->person = $this->person();
		update_option(
			Service::CONFIG,
			[
				'teams'                         => [
					[
						'team_id' => $this->team,
						'scheme'  => 'awc1',
					],
				],
				'bank_code'                     => 'U2151',
				'basis_code'                    => 'U2150',
				'win_code'                      => 'L3090',
				'draw_code'                     => 'L3091',
				'debtor_number'                 => '23008',
				'company_number'                => '23008',
				'retention_policy'              => 'Test',
				'payroll_import_confirmed'      => true,
				'payroll_corrections_confirmed' => true,
			]
			);
		add_filter(
			'rondo_match_compensation_feed',
			static fn() => [
				'matches' => [],
				'stale'   => false,
				'matched' => true,
			]
			);
		$this->bootRestControllers( [ \Rondo\REST\MatchCompensation::class, \Rondo\REST\People::class ] );
	}
	private function person(): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
				'post_title'  => 'Fictieve speler',
			]
			);
		Fields::update_many_for_post(
			$id,
			[
				'iban'                => 'NL91ABNA0417164300',
				'bank_account_holder' => 'Test',
			]
			);
		return $id;
	}
	private function agreement( string $route = 'payroll', string $number = '1001' ): array {
		return [
			'team_id'               => $this->team,
			'valid_from'            => '2026-09-01',
			'valid_until'           => null,
			'payment_route'         => $route,
			'nmbrs_employee_number' => $route === 'payroll' ? $number : '',
			'nmbrs_name'            => $route === 'payroll' ? '=Geen formule' : '',
		];
	}
	private function save( int $id, array $rows ) {
		return Agreements::update(
			$id,
			[
				'request_id' => wp_generate_uuid4(),
				'version'    => Agreements::response( $id )['version'],
				'agreements' => $rows,
			]
			);
	}
	private function register( array $ids, string $date = '2026-09-01', int $home = 1, int $away = 0 ) {
		return Service::registration(
			0,
			[
				'request_id' => wp_generate_uuid4(),
				'team_id'    => $this->team,
				'fields'     => [
					'source_match_id' => 'manual:' . wp_generate_uuid4(),
					'played_on'       => $date,
					'opponent_name'   => 'Test',
					'home'            => true,
					'category'        => 'competitie',
					'home_score'      => $home,
					'away_score'      => $away,
					'phase'           => 'completed',
					'reason'          => 'Fictieve test',
					'selection'       => array_map(
					static fn( $id ) => [
						'person_id'     => $id,
						'participation' => 'bank',
						'guest'         => true,
					],
					$ids
				),
				],
			]
			);
	}
	private function close() {
		$p = Service::preview( $this->team, '2026-09' );
		$this->assertEmpty( $p['errors'], implode( '; ', $p['errors'] ) );
		$result = Service::close(
			[
				'request_id'            => wp_generate_uuid4(),
				'team_id'               => $this->team,
				'month'                 => '2026-09',
				'fingerprint'           => $p['fingerprint'],
				'all_matches_confirmed' => true,
			]
			);
		$this->assertNotInstanceOf( \WP_Error::class, $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		return $result;
	}
	private function act( int $id, string $action, array $input ) {
		return Service::batch_action( $id, $action, $input + [ 'request_id' => wp_generate_uuid4() ] );
	}
	public function test_native_storage_dates_cleanup_overlap_uniqueness_and_stale_write(): void {
		$a     = $this->agreement();
		$saved = $this->save( $this->person, [ $a ] );
		$this->assertNotInstanceOf( \WP_Error::class, $saved, is_wp_error( $saved ) ? $saved->get_error_message() : '' );
		$this->assertSame( [ $a ], $saved['agreements'] );
		$this->assertSame( '1', get_post_meta( $this->person, Agreements::FIELD, true ) );
		$this->assertSame( '20260901', get_post_meta( $this->person, Agreements::FIELD . '_0_valid_from', true ) );
		$this->assertSame( 'field_match_agreement_valid_from', get_post_meta( $this->person, '_' . Agreements::FIELD . '_0_valid_from', true ) );
		$this->assertTrue( is_wp_error( $this->save( $this->person, [ $a, $a ] ) ) );
		$this->assertTrue( is_wp_error( $this->save( $this->person(), [ $a ] ) ) );
		$this->assertTrue( is_wp_error( $this->save( $this->person, [ array_merge( $a, [ 'valid_from' => '2026-02-30' ] ) ] ) ) );
		$this->assertTrue(
			is_wp_error(
			Agreements::update(
			$this->person,
			[
				'request_id' => wp_generate_uuid4(),
				'version'    => 'stale',
				'agreements' => [],
			]
			)
			)
			);
		$this->assertTrue( is_wp_error( Fields::update_many_for_post( $this->person, [ Agreements::FIELD => [] ] ) ) );
		$this->assertSame( [], $this->save( $this->person, [] )['agreements'] );
		$this->assertFalse( metadata_exists( 'post', $this->person, Agreements::FIELD . '_0_valid_from' ) );
	}
	public function test_boundaries_unknown_routes_guests_and_full_payroll_roster(): void {
		$guest = $this->person();
		$zero  = $this->person();
		$this->save( $this->person, [ array_merge( $this->agreement(), [ 'valid_until' => '2026-09-15' ] ), array_merge( $this->agreement( 'sepa_guest' ), [ 'valid_from' => '2026-09-16' ] ) ] );
		$this->save( $guest, [ $this->agreement( 'sepa_guest' ) ] );
		$this->save( $zero, [ $this->agreement( 'payroll', '1002' ) ] );
		$this->register( [ $this->person, $guest ], '2026-09-15' );
		$this->register( [ $this->person, $guest ], '2026-09-16', 0, 0 );
		$this->register( [ $guest ], '2026-09-17', 0, 1 );
		$p = Service::preview( $this->team, '2026-09' );
		$this->assertEmpty( $p['errors'] );
		$rows = array_column( $p['rows'], null, 'nmbrs_employee_number' );
		$this->assertSame( 1, $rows['1001']['bank'] );
		$this->assertSame( 0, $rows['1001']['amount_cents'] );
		$this->assertSame( 0, $rows['1002']['days'] );
		$guest_rows = array_values( array_filter( $p['rows'], static fn( $row ) => $row['payment_route'] === 'sepa_guest' ) );
		$this->assertSame( 23000, array_sum( array_column( $guest_rows, 'amount_cents' ) ) );
		$this->register( [ $this->person() ], '2026-09-18' );
		$this->assertNotEmpty( Service::preview( $this->team, '2026-09' )['errors'] );
	}
	public function test_zero_roster_and_agreement_changes_invalidate_preview(): void {
		$this->save( $this->person, [ $this->agreement() ] );
		$before = Service::preview( $this->team, '2026-09' );
		$this->save( $this->person(), [ $this->agreement( 'payroll', '1002' ) ] );
		$this->assertNotSame( $before['fingerprint'], Service::preview( $this->team, '2026-09' )['fingerprint'] );
		$batch = $this->close();
		$this->assertCount( 2, $batch['rows'] );
		$this->assertSame( 0, $batch['rows'][0]['days'] );
	}
	public function test_xlsx_and_sepa_are_separate_immutable_outputs_and_partial_corrections(): void {
		$guest = $this->person();
		$this->save( $this->person, [ $this->agreement() ] );
		$this->save( $guest, [ $this->agreement( 'sepa_guest' ) ] );
		$match = $this->register( [ $this->person, $guest ] );
		$batch = $this->close();
		$xlsx  = $this->act(
			$batch['id'],
			'exports',
			[
				'route'     => 'payroll',
				'confirmed' => true,
			]
			);
		$this->assertNotInstanceOf( \WP_Error::class, $xlsx, is_wp_error( $xlsx ) ? $xlsx->get_error_message() : '' );
		$this->assertSame( 'base64', $xlsx['encoding'] );
		$this->assertSame( $xlsx, $this->act( $batch['id'], 'exports', [ 'route' => 'payroll' ] ) );
		$path = tempnam( sys_get_temp_dir(), 'rondo-xlsx-' );
		file_put_contents( $path, base64_decode( $xlsx['content'] ) );
		$book = \PhpOffice\PhpSpreadsheet\IOFactory::load( $path );
		unlink( $path );
		$this->assertSame( [ 'Start', 'LooncomponentenVar', 'Blad1', 'Kostenverdeling (variabel)' ], $book->getSheetNames() );
		$this->assertSame( 'hidden', $book->getSheet( 2 )->getSheetState() );
		$sheet = $book->getSheetByName( 'LooncomponentenVar' );
		$this->assertSame( 'U2151', $sheet->getCell( 'I2' )->getValue() );
		$this->assertSame( '=Geen formule', $sheet->getCell( 'D3' )->getValue() );
		$this->assertSame( 's', $sheet->getCell( 'D3' )->getDataType() );
		$this->assertSame( 's', $sheet->getCell( 'A3' )->getDataType() );
		$this->assertSame( 'n', $sheet->getCell( 'C3' )->getDataType() );
		$this->assertNull( $sheet->getCell( 'H3' )->getValue() );
		$this->assertSame( 1, $sheet->getCell( 'I3' )->getValue() );
		$this->assertSame( 3, $sheet->getHighestRow() );
		$xml = $this->act(
			$batch['id'],
			'exports',
			[
				'route'          => 'sepa_guest',
				'confirmed'      => true,
				'debtor_name'    => 'Club',
				'debtor_iban'    => 'NL44RABO0123456789',
				'execution_date' => current_time( 'Y-m-d' ),
			]
			);
		$this->assertNotInstanceOf( \WP_Error::class, $xml );
		$this->assertStringContainsString( '110.00', $xml['content'] );
		$this->assertSame( $xml, $this->act( $batch['id'], 'exports', [ 'route' => 'sepa_guest' ] ) );
		$this->act(
			$batch['id'],
			'processing',
			[
				'route'     => 'payroll',
				'kind'      => 'nmbrs',
				'date'      => current_time( 'Y-m-d' ),
				'reference' => 'Test import',
			]
			);
		$this->assertFalse( (bool) Service::route_summary( $batch )['sepa_guest']['processing'] );
		$this->assertTrue( is_wp_error( $this->act( $batch['id'], 'corrections', [ 'reason' => 'Test' ] ) ) );
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$this->act(
			$batch['id'],
			'corrections',
			[
				'reason'               => 'Test',
				'previous_file_unused' => true,
			]
			)
			);
		Service::registration(
			$match['id'],
			[
				'request_id'       => wp_generate_uuid4(),
				'expected_version' => $match['version'],
				'fields'           => [
					'home_score' => 0,
					'reason'     => 'Test gelijkspel',
				],
			]
			);
		$next = $this->close();
		$rows = array_column( $next['rows'], null, 'payment_route' );
		$this->assertSame( -1, $rows['payroll']['wins'] );
		$this->assertSame( 1, $rows['payroll']['draws'] );
		$this->assertSame( 5000, $rows['sepa_guest']['amount_cents'] );
	}
	public function test_paid_route_change_requires_explicit_external_reversal(): void {
		$this->save( $this->person, [ $this->agreement( 'sepa_guest' ) ] );
		$this->register( [ $this->person ] );
		$first = $this->close();
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$this->act(
			$first['id'],
			'processing',
			[
				'route'     => 'sepa_guest',
				'kind'      => 'paid',
				'date'      => current_time( 'Y-m-d' ),
				'reference' => 'Bank test',
			]
			)
			);
		$this->act( $first['id'], 'corrections', [ 'reason' => 'Loonafspraak bleek aanwezig' ] );
		$this->save( $this->person, [ $this->agreement() ] );
		$this->assertNotEmpty( Service::preview( $this->team, '2026-09' )['errors'] );
		$this->assertTrue(
			is_wp_error(
			$this->act(
			$first['id'],
			'route-corrections',
			[
				'person_id' => $this->person,
				'confirmed' => false,
			]
			)
			)
			);
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$this->act(
			$first['id'],
			'route-corrections',
			[
				'person_id' => $this->person,
				'confirmed' => true,
				'date'      => current_time( 'Y-m-d' ),
				'reference' => 'Terugbetaling bank test',
			]
			)
			);
		$second = $this->close();
		$this->assertCount( 1, $second['rows'] );
		$this->assertSame( 'payroll', $second['rows'][0]['payment_route'] );
		$this->assertSame( 1, $second['rows'][0]['wins'] );
		$this->assertSame( 11000, Service::record( $first['id'] )['rows'][0]['amount_cents'] );
	}
	public function test_import_gate_and_leading_zero_preservation(): void {
		$this->save( $this->person, [ $this->agreement( 'payroll', '00123' ) ] );
		$this->assertSame( '00123', Agreements::read( $this->person )[0]['nmbrs_employee_number'] );
		$batch = $this->close();
		$this->assertTrue(
			is_wp_error(
			$this->act(
			$batch['id'],
			'exports',
			[
				'route'     => 'payroll',
				'confirmed' => true,
			]
			)
			)
			);
		$config                             = Service::config();
		$config['payroll_import_confirmed'] = false;
		update_option( Service::CONFIG, $config );
		$this->assertTrue(
			is_wp_error(
			$this->act(
			$batch['id'],
			'exports',
			[
				'route'     => 'payroll',
				'confirmed' => true,
			]
			)
			)
			);
	}
	public function test_interrupted_agreement_storage_is_hidden_and_same_request_recovers(): void {
		$input = [
			'request_id' => wp_generate_uuid4(),
			'version'    => Agreements::response( $this->person )['version'],
			'agreements' => [ $this->agreement() ],
		];
		$fail  = static function ( $check, $id, $key ) {
			return str_contains( $key, '_0_nmbrs_name' ) ? false : $check;
		};
		add_filter( 'update_post_metadata', $fail, 10, 3 );
		$result = Agreements::update( $this->person, $input );
		remove_filter( 'update_post_metadata', $fail, 10 );
		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( [], Agreements::read( $this->person ) );
		$saved = Agreements::update( $this->person, $input );
		$this->assertNotInstanceOf( \WP_Error::class, $saved );
		$this->assertSame( $saved, Agreements::update( $this->person, $input ) );
	}
	public function test_legacy_exports_remain_byte_identical_and_new_metadata_is_not_inferred(): void {
		$this->save( $this->person, [ $this->agreement() ] );
		$batch  = $this->close();
		$legacy = $batch;
		unset( $legacy['schema_version'], $legacy['agreement_snapshot'], $legacy['payroll_config'] );
		update_post_meta( $batch['id'], '_match_committed', wp_slash( $legacy ) );
		$export = [
			'content'  => 'Original CSV bytes',
			'filename' => 'original.csv',
			'mime'     => 'text/csv',
		];
		update_post_meta( $batch['id'], '_match_export', $export );
		$this->assertSame( $export, $this->act( $batch['id'], 'exports', [] ) );
		$this->assertSame( [], Service::route_summary( $legacy ) );
	}

	public function test_swapping_processed_match_routes_is_blocked_even_with_equal_counts(): void {
		$payroll = array_merge( $this->agreement(), [ 'valid_until' => '2026-09-01' ] );
		$guest   = array_merge( $this->agreement( 'sepa_guest' ), [ 'valid_from' => '2026-09-02' ] );
		$this->save( $this->person, [ $payroll, $guest ] );
		$this->register( [ $this->person ], '2026-09-01', 1, 0 );
		$this->register( [ $this->person ], '2026-09-02', 0, 1 );
		$batch = $this->close();
		foreach ( [
			'payroll'    => 'nmbrs',
			'sepa_guest' => 'paid',
		] as $route => $kind ) {
			$this->assertNotInstanceOf(
				\WP_Error::class,
				$this->act(
				$batch['id'],
				'processing',
				[
					'route'     => $route,
					'kind'      => $kind,
					'date'      => current_time( 'Y-m-d' ),
					'reference' => 'Test',
				]
				)
				);
		}
		$this->act( $batch['id'], 'corrections', [ 'reason' => 'Test' ] );
		$payroll['valid_from']  = '2026-09-02';
		$payroll['valid_until'] = null;
		$guest['valid_from']    = '2026-09-01';
		$guest['valid_until']   = '2026-09-01';
		$this->save( $this->person, [ $guest, $payroll ] );
		$this->assertNotEmpty( Service::preview( $this->team, '2026-09' )['errors'] );
	}

	public function test_finance_reader_receives_no_agreement_identity_or_snapshot(): void {
		$this->save( $this->person, [ $this->agreement() ] );
		$user = self::factory()->user->create( [ 'role' => 'rondo_financieel_lezen' ] );
		wp_set_current_user( $user );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/match-compensation/months' );
		$request->set_param( 'team_id', $this->team );
		$request->set_param( 'month', '2026-09' );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'agreement_snapshot', $response->get_data() );
		$this->assertArrayNotHasKey( 'nmbrs_employee_number', $response->get_data()['rows'][0] );
		$this->assertTrue( \Rondo\Core\AccessControl::field_is_hidden( Agreements::FIELD ) );
		$this->assertSame( [], \Rondo\Core\AccessControl::filter_sensitive_fields( [ Agreements::FIELD => [] ] ) );
	}
	public function test_generic_rest_cannot_bypass_agreement_validation(): void {
		$request = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $this->person );
		$request->set_param( 'fields', [ Agreements::FIELD => [ $this->agreement() ] ] );
		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( [], Agreements::read( $this->person ) );
	}

	public function test_non_financial_and_linked_self_cannot_read_or_write_agreements(): void {
		$this->save( $this->person, [ $this->agreement() ] );
		$user = self::factory()->user->create( [ 'role' => 'rondo_user' ] );
		update_user_meta( $user, 'rondo_linked_person_id', $this->person );
		wp_set_current_user( $user );
		$this->assertTrue( is_wp_error( Agreements::response( $this->person ) ) );
		$this->assertTrue( is_wp_error( Agreements::update( $this->person, [] ) ) );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/match-compensation/people/' . $this->person . '/agreements' );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status() );
	}
}
