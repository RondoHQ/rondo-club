<?php
namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\MemberShifts;
use Rondo\REST\VolunteerPlans;
use Rondo\Volunteer\VolunteerPlanner;
use Rondo\Volunteer\ShiftAssignments;
use Rondo\Volunteer\PlannerAvailability;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Training\Schedules;
use Tests\Support\RondoTestCase;

class VolunteerPlannerTest extends RondoTestCase {
	private VolunteerPlanner $planner;
	private int $type;
	private array $mail = [];

	protected function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		VolunteerPlanner::register();
		$this->planner = new VolunteerPlanner();
		$this->bootRestControllers( [ MemberShifts::class,VolunteerPlans::class ] );
		$this->type = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Keukendienst',
			]
			);
		update_option( VolunteerPlanner::EMAIL, 'planning@example.org' );
		add_filter(
			'pre_wp_mail',
			function ( $pre, $atts ) {
				$this->mail[] = $atts;
				return true;
			},
			10,
			2
			);
	}

	private function family( string $name ): int {
		$parent = $this->createPerson(
			[ 'post_title' => $name ],
			[
				'first_name' => $name,
				'email_1'    => strtolower( $name ) . '@example.org',
			]
			);
		$child  = $this->createPerson( [ 'post_title' => $name . ' kind' ], [ 'leeftijdsgroep' => 'Onder 10' ] );
		Fields::update_for_post(
			$parent,
			'relationships',
			[
				[
					'related_person'    => $child,
					'relationship_type' => 3,
				],
			]
			);
		Fields::update_for_post(
			$child,
			'relationships',
			[
				[
					'related_person'    => $parent,
					'relationship_type' => 2,
				],
			]
			);
		return $parent;
	}
	private function shift( int $days = 25, ?int $type = null, array $assigned = [], int $capacity = 1 ): int {
		$start = current_datetime()->modify( '+' . $days . ' days' )->setTime( 12, 0 );
		$id    = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Dienst',
			]
			);
		foreach ( [
			'start_datetime'   => $start->format( 'Y-m-d H:i:s' ),
			'end_datetime'     => $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
			'dienst_type_id'   => $type ?? $this->type,
			'capacity'         => $capacity,
			'assigned_persons' => $assigned,
			'status'           => 'open',
		] as $key => $value ) {
			update_post_meta( $id, $key, $value );}
		return $id;
	}
	private function request( string $path, string $method = 'GET', array $params = [] ): \WP_REST_Response {
		$r = new \WP_REST_Request( $method, '/rondo/v1/volunteer-plans' . $path );
		$r->set_body_params( $params );
		return rest_do_request( $r );
	}
	private function plan(): int {
		$id = $this->planner->generate();
		$this->assertIsInt( $id );
		return $id;}

	public function test_draft_is_private_balances_families_and_does_not_assign_or_mail_members(): void {
		$a       = $this->family( 'Anne' );
		$b       = $this->family( 'Bram' );
		$near    = $this->shift( 2 );
		$later   = $this->shift( 25 );
		$outside = $this->shift( 60 );
		$id      = $this->plan();
		$data    = VolunteerPlanner::data( $id );
		$this->assertSame( VolunteerPlanner::TYPE, get_post_type( $id ) );
		$this->assertSame( 'private', get_post_status( $id ) );
		$this->assertFalse( current_user_can( 'do_not_allow' ) );
		$this->assertFalse( current_user_can( 'edit_post', $id ) );
		$this->assertSame( [ $id ], [ $this->planner->generate() ] );
		$this->assertCount( 2, $data['rows'] );
		$this->assertEqualsCanonicalizing( [ $a,$b ], array_column( $data['rows'], 'person_id' ) );
		$this->assertEqualsCanonicalizing( [ $near,$later ], array_column( $data['rows'], 'shift_id' ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $near ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $outside ) );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( [ 'planning@example.org' ], $this->mail[0]['to'] );
		$this->assertStringContainsString( '/vrijwilligers/voorstellen?plan=' . $id, $this->mail[0]['message'] );
		$this->assertSame( 0, $this->planner->view( $id )['coverage']['filled'] );
	}

	public function test_refresh_consolidates_legacy_drafts_and_preserves_choices_without_assigning(): void {
		$this->family( 'Anne' );
		$this->family( 'Bram' );
		$first_shift  = $this->shift( 15 );
		$first        = $this->plan();
		$first_data   = VolunteerPlanner::data( $first );
		$second_shift = $this->shift( 16 );
		// Reproduce two legacy drafts reserving different days of the same weekend.
		$second                   = self::factory()->post->create(
			[
				'post_type'   => VolunteerPlanner::TYPE,
				'post_status' => 'private',
			]
			);
		$second_data              = $first_data;
		$second_data['rows']      = [];
		$second_data['shift_ids'] = [ $second_shift ];
		update_post_meta( $second, '_planner_data', $second_data );
		update_post_meta( $second, '_planner_phase', 'draft' );
		$combined = $this->planner->generate( null, true );
		$data     = VolunteerPlanner::data( $combined );
		$this->assertEqualsCanonicalizing( [ $first_shift, $second_shift ], array_column( $data['rows'], 'shift_id' ) );
		$this->assertSame( $first_data['rows'][0]['person_id'], $data['rows'][0]['person_id'] );
		$this->assertCount( 2, array_unique( array_column( $data['rows'], 'person_id' ) ) );
		$this->assertSame( [ $combined ], array_column( VolunteerPlanner::plans( true ), 'ID' ) );
		$this->assertSame( $first_data, get_post_meta( $first, '_planner_data', true ) );
		$this->assertSame( $combined, $this->planner->view( $first )['superseded_by'] );
		$this->assertSame( 'closed', VolunteerPlanner::data( $first )['phase'] );
		$this->assertWPError( $this->planner->publish( $first, 1, false ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $first_shift ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $second_shift ) );
		$again = $this->planner->generate( null, true );
		$this->assertSame( $data['rows'], VolunteerPlanner::data( $again )['rows'] );
		$this->assertSame( $again, $this->planner->view( $first )['superseded_by'] );
		$this->assertTrue( $this->planner->publish( $again, 1, false ) );
		$this->assertCount( 1, ShiftAssignments::person_ids( $first_shift ) );
		$this->assertCount( 1, ShiftAssignments::person_ids( $second_shift ) );
	}

	public function test_refresh_preserves_manual_open_slots_and_drops_filled_or_deleted_shifts(): void {
		$this->family( 'Anne' );
		$this->family( 'Bram' );
		$open    = $this->shift( 15 );
		$filled  = $this->shift( 16 );
		$deleted = $this->shift( 17 );
		$id      = $this->plan();
		$this->assertTrue( $this->planner->edit( $id, 1, 0, 0 ) );
		$data = VolunteerPlanner::data( $id );
		update_post_meta( $filled, 'assigned_persons', [ $data['rows'][1]['person_id'] ] );
		wp_delete_post( $deleted, true );
		$latest = $this->planner->generate( null, true );
		$rows   = VolunteerPlanner::data( $latest )['rows'];
		$this->assertCount( 1, $rows );
		$this->assertSame( $open, $rows[0]['shift_id'] );
		$this->assertSame( 0, $rows[0]['person_id'] );
		$this->assertSame( 'Handmatig open gelaten', $rows[0]['issue'] );
	}

	public function test_refresh_refuses_an_interrupted_publication_and_keeps_its_recovery_available(): void {
		$this->family( 'Anne' );
		$this->shift();
		$id                       = $this->plan();
		$data                     = VolunteerPlanner::data( $id );
		$data['rows'][0]['state'] = 'publishing';
		update_post_meta( $id, '_planner_data', $data );
		$this->assertWPError( $this->planner->generate( null, true ) );
		$this->assertSame( [ $id ], array_column( VolunteerPlanner::plans( true ), 'ID' ) );
		$this->assertSame( $data, VolunteerPlanner::data( $id ) );
	}

	public function test_refresh_reserves_manual_choice_on_later_day_before_filling_new_earlier_gap(): void {
		$this->family( 'Anne' );
		$this->family( 'Bram' );
		$later   = $this->shift( 20 );
		$id      = $this->plan();
		$options = $this->planner->candidates( $id, 0 );
		$choice  = $options[1]['person_id'];
		$this->assertTrue( $this->planner->edit( $id, 1, 0, $choice ) );
		$earlier = $this->shift( 2 );
		$new     = $this->planner->generate( null, true, false );
		$rows    = array_column( VolunteerPlanner::data( $new )['rows'], null, 'shift_id' );
		$this->assertSame( $choice, $rows[ $later ]['person_id'] );
		$this->assertNotSame( $choice, $rows[ $earlier ]['person_id'] );
		$this->assertCount( 1, $this->mail );
	}

	public function test_failed_snapshot_write_does_not_supersede_existing_choices(): void {
		$this->family( 'Anne' );
		$this->shift();
		$id   = $this->plan();
		$data = VolunteerPlanner::data( $id );
		$fail = static fn( $check, $object, $key ) => $key === '_planner_data' ? false : $check;
		add_filter( 'add_post_metadata', $fail, 10, 3 );
		try {
			$this->planner->generate( null, true );
			$this->fail( 'A failed snapshot write must abort generation.' );
		} catch ( \RuntimeException $error ) {
			$this->assertStringContainsString( 'opgeslagen', $error->getMessage() );
		} finally {
			remove_filter( 'add_post_metadata', $fail, 10 );
		}
		$this->assertSame( $data, VolunteerPlanner::data( $id ) );
		$this->assertSame( [ $id ], array_column( VolunteerPlanner::plans( true ), 'ID' ) );
	}

	public function test_refresh_keeps_published_history_and_never_reopens_old_reservations(): void {
		$this->family( 'Anne' );
		$this->family( 'Bram' );
		$filled                   = $this->shift( 15 );
		$open                     = $this->shift( 16 );
		$id                       = $this->plan();
		$data                     = VolunteerPlanner::data( $id );
		$data['rows'][0]['state'] = 'published';
		$data['phase']            = 'partial';
		update_post_meta( $id, '_planner_data', $data );
		update_post_meta( $filled, 'assigned_persons', [ $data['rows'][0]['person_id'] ] );
		$new    = $this->planner->generate( null, true );
		$latest = VolunteerPlanner::data( $new );
		$this->assertSame( [ $open ], array_column( $latest['rows'], 'shift_id' ) );
		$this->assertSame( $data['rows'][1]['person_id'], $latest['rows'][0]['person_id'] );
		$this->assertSame( $data, get_post_meta( $id, '_planner_data', true ) );
		$this->assertTrue( $this->planner->discard( $new, 1 ) );
		$this->assertEmpty( VolunteerPlanner::plans( true ) );
		$this->assertWPError( $this->planner->publish( $id, 1, false ) );
		$this->assertSame( [ $data['rows'][0]['person_id'] ], ShiftAssignments::person_ids( $filled ) );
	}

	public function test_publish_rechecks_and_queues_once_and_can_be_cancelled_before_three_weeks(): void {
		$parent = $this->family( 'Anne' );
		$shift  = $this->shift();
		$id     = $this->plan();
		$this->assertTrue( $this->planner->publish( $id, 1, false ) );
		$this->assertSame( [ $parent ], ShiftAssignments::person_ids( $shift ) );
		$this->assertTrue( ShiftAssignments::is_duty_assignment( $shift, $parent ) );
		$this->assertTrue( $this->planner->publish( $id, 1, false ) );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( 'published', VolunteerPlanner::data( $id )['phase'] );
		$this->assertSame( $id, (int) get_post_meta( $shift, '_shift_plan_id_' . $parent, true ) );
		$member = $this->createRondoUser();
		update_user_meta( $member, 'rondo_linked_person_id', $parent );
		wp_set_current_user( $member );
		$r = rest_do_request( new \WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift . '/cancel' ) );
		$this->assertSame( 200, $r->get_status(), wp_json_encode( $r->get_data() ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $shift ) );
		$this->assertNotEmpty( get_post_meta( $shift, '_shift_declined_' . $parent, true ) );
		$this->assertNotFalse( wp_next_scheduled( VolunteerPlanner::REPAIR ) );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		$new  = $this->planner->generate( null, true );
		$rows = VolunteerPlanner::data( $new )['rows'];
		$this->assertSame( 0, $rows[0]['person_id'] );
	}

	public function test_stale_quota_and_revision_cannot_publish_or_overwrite(): void {
		$parent = $this->family( 'Anne' );
		$shift  = $this->shift();
		$id     = $this->plan();
		$this->assertWPError( $this->planner->edit( $id, 99, 0, 0 ) );
		$this->shift( 30, null, [ $parent ] );
		$this->shift( 31, null, [ $parent ] );
		$this->assertTrue( $this->planner->publish( $id, 1, false ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $shift ) );
		$row = VolunteerPlanner::data( $id )['rows'][0];
		$this->assertSame( 'pending', $row['state'] );
		$this->assertNotEmpty( $row['issue'] );
	}

	public function test_luik_conversion_requires_explicit_approval_and_applies_only_at_publication(): void {
		$parent = $this->family( 'Anne' );
		update_post_meta( $this->type, 'iva_required', true );
		$luik  = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Kantine luik',
			]
			);
		$shift = $this->shift();
		$id    = $this->plan();
		$row   = VolunteerPlanner::data( $id )['rows'][0];
		$this->assertSame( $luik, $row['target_type'] );
		$this->assertSame( $parent, $row['person_id'] );
		$this->assertSame( $this->type, (int) get_post_meta( $shift, 'dienst_type_id', true ) );
		$this->assertWPError( $this->planner->publish( $id, 1, false ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $shift ) );
		$this->assertTrue( $this->planner->publish( $id, 1, true ) );
		$this->assertSame( $luik, (int) get_post_meta( $shift, 'dienst_type_id', true ) );
		$this->assertSame( [ $parent ], ShiftAssignments::person_ids( $shift ) );
	}

	public function test_certificates_quota_other_drafts_and_changed_shifts_remain_guarded(): void {
		$parent = $this->family( 'Anne' );
		Fields::update_for_post( $parent, 'vrijgesteld_handmatig', true );
		$shift = $this->shift();
		$id    = $this->plan();
		$this->assertSame( 0, VolunteerPlanner::data( $id )['rows'][0]['person_id'] );
		Fields::update_for_post( $parent, 'vrijgesteld_handmatig', false );
		$id2 = $this->planner->generate( null, true );
		$this->assertSame( $parent, VolunteerPlanner::data( $id2 )['rows'][0]['person_id'] );
		$id3 = $this->planner->generate( null, true );
		$this->assertSame( $parent, VolunteerPlanner::data( $id3 )['rows'][0]['person_id'] );
		$this->assertWPError( $this->planner->publish( $id2, 1, false ) );
		update_post_meta( $shift, 'capacity', 2 );
		$this->assertTrue( $this->planner->publish( $id3, 1, false ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $shift ) );
		$this->assertStringContainsString( 'gewijzigd', VolunteerPlanner::data( $id3 )['rows'][0]['issue'] );
	}

	public function test_editing_closing_and_private_routes_preserve_existing_assignments(): void {
		$this->family( 'Anne' );
		$this->family( 'Bram' );
		$shift    = $this->shift();
		$id       = $this->plan();
		$response = $this->request( '/' . $id );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$options = $this->request( '/' . $id . '/rows/0/candidates' )->get_data();
		$this->assertCount( 2, $options );
		$change = $this->request(
			'/' . $id . '/rows/0',
			'PUT',
			[
				'revision'  => 1,
				'person_id' => $options[1]['person_id'],
			]
			);
		$this->assertSame( 200, $change->get_status() );
		$this->assertSame( 409, $this->request( '/' . $id . '/publish', 'POST', [ 'revision' => 1 ] )->get_status() );
		$this->assertTrue( $this->planner->discard( $id, 2 ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $shift ) );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $this->request( '/' . $id )->get_status() );
		$this->assertSame( 403, $this->request( '', 'POST' )->get_status() );
		$this->assertSame( 403, $this->request( '/' . $id . '/publish', 'POST', [ 'revision' => 3 ] )->get_status() );
	}

	public function test_coordinator_reminder_is_once_after_two_days_and_wednesday_schedule_is_weekly(): void {
		$this->family( 'Anne' );
		$this->shift();
		$id                 = $this->plan();
		$data               = VolunteerPlanner::data( $id );
		$data['created_at'] = current_datetime()->modify( '-3 days' )->format( DATE_ATOM );
		update_post_meta( $id, '_planner_data', $data );
		$this->planner->remind();
		$this->planner->remind();
		$this->assertCount( 2, $this->mail );
		$this->assertStringContainsString( 'Herinnering', $this->mail[1]['subject'] );
		$next = VolunteerPlanner::next_run( new \DateTimeImmutable( '2026-10-25 20:00:00', wp_timezone() ) );
		$this->assertSame( '2026-10-28 09:00', $next->format( 'Y-m-d H:i' ) );
		$this->assertSame( '2026-11-04 09:00', VolunteerPlanner::next_run( $next )->format( 'Y-m-d H:i' ) );
	}
	public function test_publication_is_chunked_and_continues_without_duplicate_assignments(): void {
		for ( $n = 0;$n < 11;++$n ) {
			$this->family( 'Family' . $n );
			$this->shift( $n + 1 ); }
		$id = $this->plan();
		$this->assertTrue( $this->planner->publish( $id, 1, false ) );
		$data = VolunteerPlanner::data( $id );
		$this->assertTrue( $data['publish_more'] );
		$this->assertCount( 10, array_filter( $data['rows'], static fn( $row )=>$row['state'] === 'published' ) );
		$this->assertTrue( $this->planner->publish( $id, 2, false ) );
		$data = VolunteerPlanner::data( $id );
		$this->assertFalse( $data['publish_more'] );
		$this->assertSame( 'published', $data['phase'] );
		$this->assertSame( 11, $this->planner->view( $id )['coverage']['filled'] );
	}
	public function test_certificate_requirement_changed_after_draft_is_enforced_on_publication(): void {
		$this->family( 'Anne' );
		$shift = $this->shift();
		$id    = $this->plan();
		update_post_meta( $this->type, 'vog_required', true );
		$this->assertTrue( $this->planner->publish( $id, 1, false ) );
		$this->assertEmpty( ShiftAssignments::person_ids( $shift ) );
		$this->assertNotEmpty( VolunteerPlanner::data( $id )['rows'][0]['issue'] );
	}
	public function test_interrupted_commit_resumes_confirmation_queue_without_a_second_assignment(): void {
		$parent                   = $this->family( 'Anne' );
		$shift                    = $this->shift();
		$id                       = $this->plan();
		$data                     = VolunteerPlanner::data( $id );
		$data['phase']            = 'partial';
		$data['rows'][0]['state'] = 'publishing';
		update_post_meta( $id, '_planner_data', $data );
		update_post_meta( $id, '_planner_phase', 'partial' );
		update_post_meta( $shift, '_shift_plan_id_' . $parent, $id );
		update_post_meta( $shift, 'assigned_persons', [ $parent ] );
		$this->assertTrue( $this->planner->publish( $id, 1, false ) );
		$this->assertSame( [ $parent ], ShiftAssignments::person_ids( $shift ) );
		$this->assertTrue( ShiftAssignments::is_duty_assignment( $shift, $parent ) );
		$this->assertNotFalse( wp_next_scheduled( \Rondo\Volunteer\ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $parent ] ) );
		$this->assertSame( 'published', VolunteerPlanner::data( $id )['phase'] );
		$this->assertTrue( $this->planner->publish( $id, 1, false ) );
		$this->assertSame( [ $parent ], ShiftAssignments::person_ids( $shift ) );
	}
}
