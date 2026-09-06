<?php

namespace Tests\Wpunit;

use Rondo\Demo\FeatureFixtures;
use Rondo\Fields\Fields;
use Tests\Support\RondoTestCase;

/** Protect unrelated data and the live-site boundary of the explicit demo seed. */
final class DemoFeatureFixturesTest extends RondoTestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__, 2 ) . '/includes/class-demo-feature-fixtures.php';
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
	}

	public function test_refuses_awc_even_with_demo_flag(): void {
		update_option( 'home', 'https://rondo.svawc.nl' );
		update_option( 'rondo_is_demo_site', true );
		$this->expectException( \RuntimeException::class );
		FeatureFixtures::seed();
	}

	public function test_demo_origin_also_requires_explicit_demo_flag(): void {
		update_option( 'home', 'https://demo.rondo.club' );
		update_option( 'rondo_is_demo_site', false );
		$this->expectException( \RuntimeException::class );
		FeatureFixtures::seed();
	}

	public function test_repeat_run_keeps_ids_and_unrelated_records_and_provides_member_journeys(): void {
		update_option( 'home', 'https://demo.rondo.club' );
		update_option( 'rondo_is_demo_site', true );
		$unrelated = $this->createPerson( [ 'post_title' => 'Existing example' ], [ 'first_name' => 'Existing' ] );
		$first     = FeatureFixtures::seed();
		$this->assertCount( 6, $first['people'] );
		$this->assertCount( 48, $first['shifts'] );
		$this->assertSame( $first, FeatureFixtures::seed() );
		$this->assertSame( 'Existing', Fields::get_for_post( $unrelated, 'first_name' ) );
		$this->assertSame( 'publish', get_post_status( $unrelated ) );
		$user = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user, 'rondo_linked_person_id', $first['people']['parent'] );
		wp_set_current_user( $user );
		$visible = \Rondo\Core\AccessControl::get_visible_person_ids();
		$this->assertContains( $first['people']['child-1'], $visible );
		$this->assertContains( $first['people']['child-2'], $visible );
		$this->assertNotContains( $unrelated, $visible );
		$this->assertSame( 'verenigingslid', \Rondo\Passes\MembershipPassService::get_person_standard_member_tier( $first['people']['parent'] ) );
		$this->assertSame( '', \Rondo\Passes\MembershipPassService::get_person_standard_member_tier( $first['people']['former'] ) );
		$this->assertSame( 'sponsor', \Rondo\Passes\MembershipPassService::get_person_member_tier( $first['people']['former'] ) );
		$this->bootRestControllers( [ \Rondo\REST\MemberShifts::class, \Rondo\REST\People::class ] );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/shifts/calendar' );
		$request->set_query_params(
			[
				'from' => $first['base_date'],
				'to'   => ( new \DateTimeImmutable( $first['base_date'] ) )->modify( '+30 days' )->format( 'Y-m-d' ),
			]
			);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertGreaterThan( 15, count( $response->get_data()['days'] ) );
		$this->assertContains( 'open', array_column( $response->get_data()['days'], 'state' ) );
	}
}
