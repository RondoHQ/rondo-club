<?php
namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Fields\RestFields;
use Rondo\REST\MemberShifts;
use Rondo\Volunteer\PlannerContext;
use Rondo\Volunteer\VolunteerCacheInvalidator;
use Rondo\Volunteer\VolunteerDutyCredits;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Tests\Support\RondoTestCase;

class VolunteerDutyCreditsTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		new VolunteerCacheInvalidator();
		$this->bootRestControllers( [ MemberShifts::class ] );
	}

	private function credit( string $date = '2026-10-25', string $reference = 'feedback-14839' ): array {
		return [
			'date'        => $date,
			'reference'   => $reference,
			'reason'      => 'Dienst binnen 48 uur door de club afgezegd.',
			'recorded_at' => '2026-11-10T10:00:00+01:00',
			'recorded_by' => get_current_user_id(),
		];
	}

	private function progress( int $id, string $season = '2026-2027' ): array {
		$units = ( new VolunteerEligibilityService() )->get_eligible_units_for_person( $id, $season );
		return ( new VolunteerObligationCalculator() )->decorate_units( $units, $season )[0];
	}

	public function test_credit_updates_cached_progress_and_planner_without_a_shift_or_email(): void {
		$id = $this->createPerson();
		Fields::update_for_post( $id, 'leeftijdsgroep', 'Senioren' );
		$this->assertSame( 0, $this->progress( $id )['completed_count'] );
		$emails = 0;
		$spy    = static function ( $pre ) use ( &$emails ) {
			++$emails;
			return true;
		};
		add_filter( 'pre_wp_mail', $spy );
		Fields::update_for_post( $id, 'volunteer_duty_credits', [ $this->credit() ] );
		remove_filter( 'pre_wp_mail', $spy );
		$this->assertSame( 0, $emails );
		$this->assertSame( 1, $this->progress( $id )['completed_count'] );
		$this->assertSame( 2, $this->progress( $id )['required_count'] );
		$this->assertSame( 1, $this->progress( $id )['remaining'] );
		$context = new PlannerContext( new \DateTimeImmutable( '2026-11-10', wp_timezone() ), 0, null, '2026-2027' );
		$unit    = array_values( array_filter( $context->units, static fn( $unit ) => $unit['kind'] === 'speler' && in_array( $id, $unit['person_ids'], true ) ) )[0];
		$this->assertSame( 1, $unit['remaining'] );
		$this->assertSame( [ 1, 0 ], $unit['half'] );
		$this->assertSame( 0, $this->progress( $id, '2027-2028' )['completed_count'] );
		Fields::update_for_post( $id, 'volunteer_duty_credits', [] );
		$this->assertSame( 0, $this->progress( $id )['completed_count'] );
		$this->assertSame( '', get_post_meta( $id, 'volunteer_duty_credits_0_reason', true ) );
	}

	public function test_native_storage_wire_format_season_boundaries_and_duplicate_references(): void {
		$id = $this->createPerson();
		Fields::update_for_post( $id, 'volunteer_duty_credits', [ $this->credit(), $this->credit(), $this->credit( '2026-06-30', 'previous' ), $this->credit( '2027-07-01', 'next' ) ] );
		$this->assertSame( '4', get_post_meta( $id, 'volunteer_duty_credits', true ) );
		$this->assertSame( '20261025', get_post_meta( $id, 'volunteer_duty_credits_0_date', true ) );
		$this->assertSame( 'feedback-14839', get_post_meta( $id, 'volunteer_duty_credits_0_reference', true ) );
		$credits = VolunteerDutyCredits::for_season( $id, '2026-2027' );
		$this->assertCount( 1, $credits );
		$this->assertSame( '2026-10-25', $credits[0]['date'] );
		$this->assertSame( '2026-11-10T10:00:00+01:00', $credits[0]['recorded_at'] );
		$this->assertCount( 1, VolunteerDutyCredits::for_season( $id, '2025-2026' ) );
		$this->assertCount( 1, VolunteerDutyCredits::for_season( $id, '2027-2028' ) );
	}

	public function test_credits_are_private_and_cannot_be_written_through_general_person_rest(): void {
		$id      = $this->createPerson();
		$request = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $id );
		$request->set_body_params( [ 'fields' => [ 'volunteer_duty_credits' => [ $this->credit() ] ] ] );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		$this->assertEmpty( VolunteerDutyCredits::for_season( $id, '2026-2027' ) );
		Fields::update_for_post( $id, 'volunteer_duty_credits', [ $this->credit() ] );
		$user = $this->createRondoUser();
		( new \WP_User( $user ) )->add_cap( 'ledenadministratie' );
		wp_set_current_user( $user );
		$this->assertArrayNotHasKey( 'volunteer_duty_credits', RestFields::for_post( 'person', $id ) );
	}
}
