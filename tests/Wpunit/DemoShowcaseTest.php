<?php

namespace Tests\Wpunit;

use Rondo\Demo\DemoShowcase;
use Rondo\Fields\Fields;
use Rondo\Passes\MembershipPassService;
use Rondo\Teams\TeamMatches;
use Tests\Support\RondoTestCase;

/** Fictional fixture graph, relative dates and safe demo refresh contracts. */
class DemoShowcaseTest extends RondoTestCase {

	private function fixture(): array {
		return json_decode( file_get_contents( RONDO_THEME_DIR . '/fixtures/demo-showcase.json' ), true );
	}

	public function test_showcase_refuses_production_without_mutations(): void {
		delete_option( 'rondo_is_demo_site' );
		$person   = $this->createPerson();
		$importer = new DemoShowcase();
		$result   = $importer->import( $this->fixture() );
		$this->assertWPError( $result );
		$this->assertSame( 'demo_only', $result->get_error_code() );
		$importer->clean();
		$this->assertNotNull( get_post( $person ) );
		$this->assertFalse( get_option( 'rondo_demo_showcase_manifest' ) );
	}

	public function test_preflight_rejects_invalid_relationships_and_fields_without_writing(): void {
		update_option( 'rondo_is_demo_site', true );
		$fixture                                    = $this->fixture();
		$fixture['records'][0]['fields']['unknown'] = 'invalid';
		$this->assertWPError( ( new DemoShowcase() )->validate( $fixture ) );
		$fixture = $this->fixture();
		$fixture['records'][0]['fields']['work_history'] = [ [ 'team_id' => [ '$ref' => 'team:missing' ] ] ];
		$this->assertWPError( ( new DemoShowcase() )->validate( $fixture ) );
		$this->assertFalse( get_option( 'rondo_demo_showcase_manifest' ) );
	}

	public function test_showcase_mail_is_blocked_without_changing_normal_sites(): void {
		update_option( 'rondo_is_demo_site', true );
		update_option( 'rondo_demo_showcase_manifest', [ 'source' => 'fictional_showcase' ] );
		$protection = new \Rondo\Demo\DemoProtection();
		try {
			$this->assertFalse( wp_mail( 'recipient@club.example', 'Showcase', 'Fictional message' ) );
			delete_option( 'rondo_is_demo_site' );
			$this->assertNull( $protection->block_showcase_mail( null ) );
			$this->assertTrue( $protection->block_showcase_mail( true ) );
		} finally {
			remove_filter( 'pre_wp_mail', [ $protection, 'block_showcase_mail' ], 1 );
		}
	}

	public function test_showcase_explicitly_grants_volunteer_access_on_existing_demo_accounts(): void {
		update_option( 'rondo_is_demo_site', true );
		$user_id = $this->createRondoUser( [ 'user_login' => 'demo' ] );
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'vrijwilligers', false );
		$this->assertFalse( user_can( $user, 'vrijwilligers' ) );
		$fixture            = $this->fixture();
		$fixture['records'] = [
			[
				'_ref'      => 'person:p001',
				'post_type' => 'person',
				'title'     => 'Anna Bos',
				'fields'    => [
					'first_name' => 'Anna',
					'last_name'  => 'Bos',
				],
			],
		];
		foreach ( [ 'terms', 'settings', 'comments', 'coverage' ] as $section ) {
			$fixture[ $section ] = [];
		}
		unset( $fixture['demo_account']['user_meta']['_rondo_match_teams'] );
		$result = ( new DemoShowcase() )->import( $fixture );
		$this->assertNotWPError( $result );
		wp_set_current_user( $user_id );
		$this->bootRestControllers( [ \Rondo\REST\Volunteer::class ] );
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/volunteer-statistics' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( current_user_can( 'manage_options' ) );
	}

	public function test_complete_fixture_imports_native_fields_and_relative_dates_and_protects_existing_records(): void {
		update_option( 'rondo_is_demo_site', true );
		$user_id = $this->createRondoUser( [ 'user_login' => 'demo' ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$fixture  = $this->fixture();
		$importer = new DemoShowcase();
		$valid    = $importer->validate( $fixture );
		$this->assertSame( true, $valid, is_wp_error( $valid ) ? $valid->get_error_message() : '' );
		$ids = $importer->import( $fixture );
		$this->assertNotWPError( $ids );
		$this->assertCount( count( $fixture['records'] ) + count( $fixture['terms'] ), $ids );
		foreach ( $fixture['coverage'] as $refs ) {
			foreach ( $refs as $ref ) {
				$this->assertArrayHasKey( $ref, $ids );
			}
		}
		$this->assertSame( $ids['person:p001'], (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
		$this->assertFalse( user_can( $user_id, 'manage_options' ) );
		$team_counts = \Rondo\REST\Teams::get_all_member_counts( true );
		foreach ( $fixture['records'] as $record ) {
			if ( $record['post_type'] === 'team' ) {
				$this->assertSame( 16, $team_counts[ $ids[ $record['_ref'] ] ]['players'] );
			}
		}
		$this->assertSame( 1, $team_counts[ $ids['team:senior1'] ]['staff'] );
		$this->assertSame( 1, $team_counts[ $ids['team:jo13'] ]['staff'] );
		$this->assertTrue( user_can( $user_id, 'manage_training' ) );
		$this->assertTrue( \Rondo\Core\UserRoles::can_access_board( $user_id ) );
		$this->assertTrue( ( new \Rondo\Passes\GuestPassService() )->is_eligible_host( $ids['person:p001'] ) );
		$tournaments = new \Rondo\Tournaments\TournamentService();
		$this->assertCount( 3, $tournaments->entries_for_tournament( $ids['rondo_tournament:t1'] ) );
		$this->assertSame( 'open', $tournaments->format_entry( $ids['rondo_tourn_entry:e0'] )['registration_status'] );
		$this->assertSame( 'not_applicable', $tournaments->format_entry( $ids['rondo_tourn_entry:e0'] )['payment_state'] );
		$this->assertSame( 'error', $tournaments->format_entry( $ids['rondo_tourn_entry:e1'] )['payment_state'] );
		$this->assertSame( 'paid', $tournaments->format_entry( $ids['rondo_tourn_entry:e2'] )['payment_state'] );
		$this->assertNotEmpty( ( new \Rondo\Passes\GuestPassService() )->get_share_url( $ids['rondo_guest_pass:g1'] ) );
		$this->assertCount( 3, Fields::get_for_post( $ids['person:parent1'], 'relationships' ) );
		$this->assertSame( Fields::get_for_post( $ids['person:p113'], 'addresses' ), Fields::get_for_post( $ids['person:p129'], 'addresses' ) );
		$this->assertNotSame( Fields::get_for_post( $ids['person:p001'], 'addresses' ), Fields::get_for_post( $ids['person:p002'], 'addresses' ) );
		$roles = Fields::all_for_post( $ids['person:p001'] )['work_history'];
		$this->assertSame( $ids['team:senior1'], $roles[0]['team_id'] );
		$this->assertSame( $ids['person:parent1'], Fields::all_for_post( $ids['person:p113'] )['relationships'][0]['related_person_id'] );
		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		$this->assertSame( $today->modify( '-2 years' )->format( 'Ymd' ), Fields::get_for_post( $ids['person:p001'], 'lid_sinds' ) );
		$feed = ( new TeamMatches() )->get_feed( $ids['team:senior1'] );
		$this->assertNotWPError( $feed );
		$this->assertCount( 5, $feed['matches'] );
		$this->assertSame( [ true, false, true, false, true ], array_column( $feed['matches'], 'home' ) );
		$this->assertSame( 'SV Voorbeeld 1', $feed['matches'][0]['home_team'] );
		$this->assertSame( 'SV Voorbeeld 1', $feed['matches'][1]['away_team'] );
		$this->assertTrue( $feed['matches'][4]['cancelled'] );
		$report = json_decode( get_post_meta( $ids['rondo_twelve_report:r0'], '_twelve_report_data', true ), true );
		$this->assertTrue( \Rondo\Twelve\Activity::validate( $report ) );
		$this->assertNotNull( \Rondo\Twelve\Activity::hours( $report ) );
		$this->assertCount( 4, \Rondo\Twelve\ReportAggregator::by_product( [ [ 'data' => $report ] ] ) );
		$this->assertSame( 450.0, \Rondo\Twelve\ReportAggregator::omzet_excl_nosale( $report ) );
		$this->assertNotWPError( \Rondo\Training\Schedules::validate_blocks( \Rondo\Fields\Formatter::for_wire( 'rondo_training', Fields::all_for_post( $ids['rondo_training:active'] ) )['blocks'] ) );
		$this->assertSame( 'SV Voorbeeld', json_decode( get_post_meta( $ids['rondo_twelve_report:r0'], '_twelve_report_data', true ), true )['club'] );
		$this->assertSame( $ids['rondo_training:active'], (int) get_option( 'rondo_training_active' ) );
		$this->assertSame( '3', get_post_meta( $ids['rondo_invoice:i9'], '_installment_count', true ) );
		$this->assertFalse( metadata_exists( 'post', $ids['rondo_invoice:i9'], '_mollie_payment_id' ) );
		update_option( MembershipPassService::LEGACY_CLEANUP_OPTION, true, false );
		$result = $importer->clean();
		$this->assertWPError( $result );
		$this->assertSame( 'demo_cleanup_protected', $result->get_error_code() );
		foreach ( $fixture['records'] as $record ) {
			$this->assertNotNull( get_post( $ids[ $record['_ref'] ] ) );
		}
		$this->assertTrue( (bool) get_option( MembershipPassService::LEGACY_CLEANUP_OPTION ) );
		$this->assertNotFalse( get_userdata( $user_id ) );
	}

	public function test_unprotected_demo_modules_can_be_cleaned_without_removing_upgrade_state(): void {
		update_option( 'rondo_is_demo_site', true );
		update_option( MembershipPassService::LEGACY_CLEANUP_OPTION, true, false );
		$ids = [];
		foreach ( [ 'rondo_display', 'rondo_room_booking', 'dienst_shift', 'rondo_match_reg', 'rondo_tourn_entry' ] as $type ) {
			$ids[] = self::factory()->post->create(
				[
					'post_type'   => $type,
					'post_status' => 'private',
				]
				);
		}
		$core = self::factory()->post->create( [ 'post_type' => 'post' ] );
		$this->assertTrue( ( new DemoShowcase() )->clean() );
		foreach ( $ids as $id ) {
			$this->assertNull( get_post( $id ) );
		}
		$this->assertNotNull( get_post( $core ) );
		$this->assertTrue( (bool) get_option( MembershipPassService::LEGACY_CLEANUP_OPTION ) );
	}
}
