<?php
namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;
use Rondo\Matches\CompensationService as Service;
use Rondo\Matches\CompensationCalculator as Calculator;
use Rondo\Fields\Fields;

class MatchCompensationTest extends RondoTestCase {
	private int $team;
	private int $person;
	protected function set_up(): void {
		parent::set_up();
		Service::register_types();
		add_filter(
			'rondo_match_compensation_feed',
			static fn() => [
				'matches' => [],
				'stale'   => false,
				'matched' => true,
			]
			);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->team   = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
			]
			);
		$this->person = self::factory()->post->create(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
				'post_title'  => 'Test speler',
			]
			);
		update_option(
			Service::CONFIG,
			[
				'teams'            => [
					[
						'team_id' => $this->team,
						'scheme'  => 'jo23',
					],
				],
				'bank_code'        => '',
				'retention_policy' => 'Test policy',
			]
			);
		Fields::update_many_for_post(
			$this->person,
			[
				'iban'                => 'NL91ABNA0417164300',
				'bank_account_holder' => 'Rekeninghouder & partner',
			]
			);
		$this->bootRestControllers( [ \Rondo\REST\MatchCompensation::class ] );
	}
	private function input( string $source = 'manual:test' ): array {
		return [
			'request_id' => wp_generate_uuid4(),
			'team_id'    => $this->team,
			'fields'     => [
				'source_match_id' => $source,
				'played_on'       => '2026-09-01',
				'opponent_name'   => 'Tegenstander',
				'home'            => true,
				'category'        => 'competitie',
				'home_score'      => 3,
				'away_score'      => 0,
				'phase'           => 'completed',
				'reason'          => 'Synthetische wedstrijd met één speler',
				'selection'       => [
					[
						'person_id'     => $this->person,
						'participation' => 'bank',
						'guest'         => true,
					],
				],
			],
		];
	}
	private function close(): array {
		$preview = Service::preview( $this->team, '2026-09' );
		$this->assertNotInstanceOf( \WP_Error::class, $preview );
		$this->assertEmpty( $preview['errors'] );
		$result = Service::close(
			[
				'team_id'               => $this->team,
				'month'                 => '2026-09',
				'request_id'            => wp_generate_uuid4(),
				'fingerprint'           => $preview['fingerprint'],
				'all_matches_confirmed' => true,
			]
			);
		$this->assertNotInstanceOf( \WP_Error::class, $result, is_wp_error( $result ) ? $result->get_error_message() : '' );
		return $result;
	}
	public function test_registration_replay_storage_and_duplicate_prevention(): void {
		$input = $this->input();
		$first = Service::registration( 0, $input );
		$this->assertNotInstanceOf( \WP_Error::class, $first );
		$this->assertSame( $first, Service::registration( 0, $input ) );
		$this->assertSame( '1', get_post_meta( $first['id'], 'selection', true ) );
		$this->assertSame( (string) $this->person, get_post_meta( $first['id'], 'selection_0_person_id', true ) );
		$this->assertTrue( is_wp_error( Service::registration( 0, $this->input() ) ) );
		$input['fields']['home_score'] = 4;
		$this->assertTrue( is_wp_error( Service::registration( 0, $input ) ) );
	}
	public function test_bank_changes_invalidate_preview_and_closed_snapshot_stays_fixed(): void {
		$reg     = Service::registration( 0, $this->input() );
		$preview = Service::preview( $this->team, '2026-09' );
		Fields::update_for_post( $this->person, 'bank_account_holder', 'Nieuwe naam' );
		$this->assertTrue(
			is_wp_error(
			Service::close(
			[
				'team_id'               => $this->team,
				'month'                 => '2026-09',
				'request_id'            => wp_generate_uuid4(),
				'fingerprint'           => $preview['fingerprint'],
				'all_matches_confirmed' => true,
			]
			)
			)
			);
		$batch = $this->close();
		Fields::update_for_post( $this->person, 'iban', null );
		$this->assertSame( 'NL91ABNA0417164300', Service::record( $batch['id'] )['rows'][0]['iban'] );
		$input                     = $this->input();
		$input['expected_version'] = $reg['version'];
		$this->assertTrue( is_wp_error( Service::registration( $reg['id'], $input ) ) );
	}
	public function test_multi_recipient_xml_schema_totals_and_redownload(): void {
		$second = self::factory()->post->create(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
			]
			);
		Fields::update_many_for_post(
			$second,
			[
				'iban'                => 'BE68539007547034',
				'bank_account_holder' => 'Tweede ontvanger',
			]
			);
		$input                          = $this->input();
		$input['fields']['selection'][] = [
			'person_id'     => $second,
			'participation' => 'basis',
		];
		Service::registration( 0, $input );
		$batch  = $this->close();
		$data   = [
			'request_id'     => wp_generate_uuid4(),
			'confirmed'      => true,
			'debtor_name'    => 'Testclub',
			'debtor_iban'    => 'NL44RABO0123456789',
			'execution_date' => current_time( 'Y-m-d' ),
		];
		$export = Service::batch_action( $batch['id'], 'exports', $data );
		$this->assertNotInstanceOf( \WP_Error::class, $export );
		$xml = new \DOMDocument();
		$xml->loadXML( $export['content'] );
		$this->assertTrue( $xml->schemaValidate( dirname( __DIR__ ) . '/fixtures/sepa/pain.001.001.09.xsd' ) );
		$xpath = new \DOMXPath( $xml );
		$xpath->registerNamespace( 's', 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09' );
		$this->assertSame( '2', $xpath->evaluate( 'string(//s:GrpHdr/s:NbOfTxs)' ) );
		$this->assertSame( '90.00', $xpath->evaluate( 'string(//s:GrpHdr/s:CtrlSum)' ) );
		$this->assertSame( 2, $xpath->query( '//s:CdtTrfTxInf' )->length );
		$this->assertNotSame( $xpath->query( '//s:EndToEndId' )->item( 0 )->textContent, $xpath->query( '//s:EndToEndId' )->item( 1 )->textContent );
		$this->assertSame( $export, Service::batch_action( $batch['id'], 'exports', $data ) );
		$this->assertEmpty( get_post_meta( $batch['id'], '_match_processing', true ) );
	}
	public function test_bank_premiums_same_day_guest_and_excluded_categories(): void {
		$match                                = $this->input()['fields'];
		$match['selection'][0]['player_name'] = 'Speler';
		$draw                                 = $match;
		$draw['away_score']                   = 3;
		$cup                                  = $match;
		$cup['category']                      = 'beker';
		$rows                                 = Calculator::calculate( [ $match, $draw, $cup ], 'awc1' );
		$this->assertSame( 2, $rows[0]['days'] );
		$this->assertSame( 2, $rows[0]['bank'] );
		$this->assertSame( 12000, $rows[0]['amount_cents'] );
		$this->assertSame( 6000, Calculator::calculate( [ $match, $draw ], 'jo23' )[0]['amount_cents'] );
	}
	public function test_plain_member_denied_on_real_routes(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/match-compensation/months' );
		$request->set_param( 'team_id', $this->team );
		$request->set_param( 'month', '2026-09' );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
		$this->assertTrue( is_wp_error( Service::registration( 0, $this->input() ) ) );
	}
	private function act( int $id, string $action, array $input = [] ) {
		return Service::batch_action( $id, $action, $input + [ 'request_id' => wp_generate_uuid4() ] );
	}
	public function test_processed_corrections_are_deltas_and_negative_amounts_require_manual_resolution(): void {
		$reg   = Service::registration( 0, $this->input() );
		$first = $this->close();
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$this->act(
			$first['id'],
			'processing',
			[
				'kind'      => 'paid',
				'date'      => '2026-09-30',
				'reference' => 'Bankbewijs',
			]
			)
			);
		$this->assertNotInstanceOf( \WP_Error::class, $this->act( $first['id'], 'corrections', [ 'reason' => 'Uitslag was gelijkspel' ] ) );
		$input                         = $this->input();
		$input['expected_version']     = $reg['version'];
		$input['fields']['away_score'] = 3;
		$changed                       = Service::registration( $reg['id'], $input );
		$this->assertNotInstanceOf( \WP_Error::class, $changed );
		$second = $this->close();
		$this->assertSame( -3000, $second['rows'][0]['amount_cents'] );
		$this->assertSame( 4500, Service::record( $first['id'] )['rows'][0]['amount_cents'] );
		$this->assertTrue( is_wp_error( $this->act( $second['id'], 'exports', [ 'confirmed' => true ] ) ) );
		$this->assertTrue(
			is_wp_error(
			$this->act(
			$second['id'],
			'processing',
			[
				'kind'      => 'paid',
				'date'      => '2026-09-30',
				'reference' => 'Fout',
			]
			)
			)
			);
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$this->act(
			$second['id'],
			'processing',
			[
				'kind'      => 'manual_correction',
				'date'      => '2026-09-30',
				'reference' => 'Handmatig terugontvangen',
			]
			)
			);
		$this->act( $second['id'], 'corrections', [ 'reason' => 'Herstel uitslag' ] );
		$input                     = $this->input();
		$input['expected_version'] = $changed['version'];
		$this->assertNotInstanceOf( \WP_Error::class, Service::registration( $reg['id'], $input ) );
		$third = $this->close();
		$this->assertSame( 3000, $third['rows'][0]['amount_cents'] );
	}
	public function test_unprocessed_replacement_remains_full_and_requires_unused_file_confirmation(): void {
		$reg   = Service::registration( 0, $this->input() );
		$first = $this->close();
		$this->assertTrue( is_wp_error( $this->act( $first['id'], 'corrections', [ 'reason' => 'Correctie' ] ) ) );
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$this->act(
			$first['id'],
			'corrections',
			[
				'reason'               => 'Correctie',
				'previous_file_unused' => true,
			]
			)
			);
		$input                         = $this->input();
		$input['expected_version']     = $reg['version'];
		$input['fields']['away_score'] = 3;
		Service::registration( $reg['id'], $input );
		$replacement = $this->close();
		$this->assertSame( 1500, $replacement['rows'][0]['amount_cents'] );
		$this->assertTrue( is_wp_error( $this->act( $first['id'], 'exports', [ 'confirmed' => true ] ) ) );
	}
	public function test_import_preview_is_read_only_and_commit_replays_without_duplicates(): void {
		$payload = [ 'registrations' => [ $this->input( 'manual:import-one' ), $this->input( 'manual:import-two' ) ] ];
		$preview = Service::import( $payload );
		$this->assertEmpty( $preview['errors'] );
		$this->assertCount( 0, Service::posts( Service::REG, $this->team ) );
		$this->assertTrue( is_wp_error( Service::import( $payload, true ) ) );
		$payload += [
			'preview_hash'     => $preview['preview_hash'],
			'confirmed'        => true,
			'backup_reference' => 'Lokale testbronkopie',
		];
		$first    = Service::import( $payload, true );
		$this->assertNotInstanceOf( \WP_Error::class, $first );
		$this->assertCount( 2, $first['imported_ids'] );
		$this->assertSame( $first, Service::import( $payload, true ) );
		$this->assertCount( 2, Service::posts( Service::REG, $this->team ) );
	}
	public function test_lost_receipt_and_concurrent_attempt_do_not_duplicate_registration(): void {
		$input = $this->input();
		$first = Service::registration( 0, $input );
		delete_option( '_rondo_match_op_' . hash( 'sha256', get_current_user_id() . ':' . $input['request_id'] ) );
		$this->assertSame( $first, Service::registration( 0, $input ) );
		$this->assertCount( 1, Service::posts( Service::REG, $this->team ) );
		$held = \Rondo\Matches\CompensationLock::acquire();
		try {
			$this->assertSame( 'rondo_match_busy', Service::registration( 0, $this->input( 'manual:busy' ) )->get_error_code() );
		} finally {
			flock( $held, LOCK_UN );
			fclose( $held ); }
		$stale                     = $this->input();
		$stale['expected_version'] = 0;
		$this->assertTrue( is_wp_error( Service::registration( $first['id'], $stale ) ) );
	}
	public function test_source_drift_blocks_closure_and_unknown_kind_requires_review(): void {
		$source = [
			'id'          => 'sportlink:123',
			'date'        => '2026-09-01',
			'home'        => true,
			'home_team'   => 'AWC',
			'away_team'   => 'Bezoekers',
			'competition' => 'Regulier',
			'result'      => '3-0',
			'cancelled'   => false,
		];
		$feed   = [
			'matches' => [ $source ],
			'matched' => true,
			'stale'   => false,
		];
		add_filter(
			'rondo_match_compensation_feed',
			static function () use ( &$feed ) {
				return $feed;
			},
			20
			);
		$this->assertNotEmpty( Service::preview( $this->team, '2026-09' )['errors'] );
		$reg = Service::registration( 0, $this->input( 'sportlink:123' ) );
		$this->assertNotInstanceOf( \WP_Error::class, $reg );
		$this->assertEmpty( Service::preview( $this->team, '2026-09' )['errors'] );
		$feed['matches'][0]['result'] = '3-3';
		$this->assertNotEmpty( Service::preview( $this->team, '2026-09' )['errors'] );
		$feed['stale']             = true;
		$input                     = $this->input( 'sportlink:123' );
		$input['expected_version'] = $reg['version'];
		$this->assertTrue( is_wp_error( Service::registration( $reg['id'], $input ) ) );
	}
	public function test_financial_reader_receives_masked_bank_data_and_cannot_close(): void {
		Service::registration( 0, $this->input() );
		$user = self::factory()->user->create( [ 'role' => 'rondo_financieel_lezen' ] );
		wp_set_current_user( $user );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/match-compensation/months' );
		$request->set_param( 'team_id', $this->team );
		$request->set_param( 'month', '2026-09' );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( '•••• 4300', $response->get_data()['rows'][0]['iban'] );
		$this->assertArrayNotHasKey( 'bank_account_holder', $response->get_data()['rows'][0] );
		$this->assertTrue( is_wp_error( Service::close( [] ) ) );
		$this->assertTrue( is_wp_error( Service::registrations( $this->team ) ) );
	}
	public function test_closed_month_rejects_new_matches_and_nmbrs_requires_confirmed_name(): void {
		Service::registration( 0, $this->input() );
		$batch = $this->close();
		$this->assertTrue( is_wp_error( Service::registration( 0, $this->input( 'manual:new-in-closed-month' ) ) ) );
		$this->act(
			$batch['id'],
			'corrections',
			[
				'reason'               => 'Test',
				'previous_file_unused' => true,
			]
			);
		$config                       = Service::config();
		$config['teams'][0]['scheme'] = 'awc1';
		update_option( Service::CONFIG, $config );
		$this->assertNotEmpty( Service::preview( $this->team, '2026-09' )['errors'] );
		$this->assertNotInstanceOf( \WP_Error::class, Fields::update_for_post( $this->person, 'nmbrs_name', 'Speler zoals in Nmbrs' ) );
		$batch  = $this->close();
		$export = $this->act( $batch['id'], 'exports', [ 'confirmed' => true ] );
		$this->assertStringContainsString( 'Speler zoals in Nmbrs', $export['content'] );
		$this->assertStringContainsString( 'Bankvergoeding (code nog niet ingesteld)', $export['content'] );
	}
	public function test_registrar_is_limited_to_assigned_team(): void {
		$user    = self::factory()->user->create( [ 'role' => 'rondo_financieel' ] );
		$account = new \WP_User( $user );
		$account->add_cap( 'wedstrijdregistratie' );
		$account->add_cap( 'teams' );
		update_user_meta( $user, '_rondo_match_teams', [ $this->team ] );
		wp_set_current_user( $user );
		$this->assertTrue( Service::registrar( $this->team ) );
		$this->assertNotInstanceOf( \WP_Error::class, Service::registration( 0, $this->input() ) );
		update_user_meta( $user, '_rondo_match_teams', [] );
		$this->assertFalse( Service::registrar( $this->team ) );
		$this->assertTrue( is_wp_error( Service::registrations( $this->team ) ) );
	}
	public function test_partial_storage_failure_is_not_published_and_same_request_recovers(): void {
		$input = $this->input();
		$fail  = static function ( $check, $id, $key ) {
			return $key === 'home_score' && get_post_type( $id ) === Service::REG ? false : $check;
		};
		add_filter( 'update_post_metadata', $fail, 10, 3 );
		$this->assertTrue( is_wp_error( Service::registration( 0, $input ) ) );
		$this->assertEmpty( Service::registrations( $this->team ) );
		remove_filter( 'update_post_metadata', $fail, 10 );
		$recovered = Service::registration( 0, $input );
		$this->assertNotInstanceOf( \WP_Error::class, $recovered );
		$this->assertSame( 3, $recovered['home_score'] );
		$this->assertCount( 1, Service::posts( Service::REG, $this->team ) );
	}
	public function test_settings_only_return_real_team_assignments(): void {
		$unassigned = self::factory()->user->create();
		$empty      = self::factory()->user->create();
		$assigned   = self::factory()->user->create();
		update_user_meta( $empty, '_rondo_match_teams', [ 0, '' ] );
		update_user_meta( $assigned, '_rondo_match_teams', [ (string) $this->team ] );
		$users = array_column( Service::settings()['available_users'], null, 'id' );
		$this->assertSame( [], $users[ $unassigned ]['teams'] );
		$this->assertSame( [], $users[ $empty ]['teams'] );
		$this->assertSame( [ $this->team ], $users[ $assigned ]['teams'] );
	}
}
