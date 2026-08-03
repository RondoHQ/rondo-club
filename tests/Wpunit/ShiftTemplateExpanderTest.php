<?php

namespace Tests\Wpunit;

use Rondo\Volunteer\ShiftTemplateExpander;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Regression coverage for manually expanding volunteer shift templates.
 */
class ShiftTemplateExpanderTest extends RondoTestCase {

	private ShiftTemplateExpander $expander;

	protected function set_up(): void {
		parent::set_up();
		$this->expander = new ShiftTemplateExpander();
	}

	private function create_template( string $active_from, string $start_time = '10:00', string $end_time = '12:00' ): int {
		$type_id     = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Handmatige uitroltest',
			]
		);
		$template_id = self::factory()->post->create(
			[
				'post_type'   => 'shift_template',
				'post_status' => 'publish',
				'post_title'  => 'Wekelijkse uitroltest',
			]
		);

		update_post_meta( $template_id, 'dienst_type_id', $type_id );
		update_post_meta( $template_id, 'day_of_week', (int) gmdate( 'N', strtotime( $active_from ) ) );
		update_post_meta( $template_id, 'start_time', $start_time );
		update_post_meta( $template_id, 'end_time', $end_time );
		update_post_meta( $template_id, 'capacity', 2 );
		update_post_meta( $template_id, 'active_from', $active_from );

		return $template_id;
	}

	public function test_manual_expansion_uses_selected_end_date(): void {
		$until       = current_datetime()->modify( '+7 days' )->format( 'Y-m-d' );
		$template_id = $this->create_template( $until );
		$request     = new WP_REST_Request( 'POST', '/rondo/v1/shift-templates/expand' );
		$request->set_param( 'until', $until );

		$response = $this->expander->rest_expand_all( $request );
		$data     = $response->get_data();

		$this->assertSame( $until, $data['until'] );
		$this->assertGreaterThanOrEqual( 1, $data['created'] );

		$shift_ids = get_posts(
			[
				'post_type'      => 'dienst_shift',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'template_id',
				'meta_value'     => $template_id,
			]
		);

		$this->assertCount( 1, $shift_ids );
		$this->assertStringStartsWith( $until, get_post_meta( $shift_ids[0], 'start_datetime', true ) );
	}

	public function test_manual_expansion_rejects_invalid_and_past_dates(): void {
		$invalid_request = new WP_REST_Request( 'POST', '/rondo/v1/shift-templates/expand' );
		$invalid_request->set_param( 'until', '2026-02-30' );

		$invalid_response = $this->expander->rest_expand_all( $invalid_request );
		$this->assertWPError( $invalid_response );
		$this->assertSame( 'rondo_invalid_expansion_date', $invalid_response->get_error_code() );

		$past_request = new WP_REST_Request( 'POST', '/rondo/v1/shift-templates/expand' );
		$past_request->set_param( 'until', current_datetime()->modify( '-1 day' )->format( 'Y-m-d' ) );

		$past_response = $this->expander->rest_expand_all( $past_request );
		$this->assertWPError( $past_response );
		$this->assertSame( 'rondo_expansion_date_in_past', $past_response->get_error_code() );
	}

	/**
	 * The nightly window runs to the end of the season, not three months out.
	 * A rolling quarter meant coordinators could not plan the spring and members
	 * could not see it coming.
	 */
	public function test_default_window_runs_to_the_end_of_the_season(): void {
		$this->assertSame(
			'2027-06-30',
			ShiftTemplateExpander::default_window_end( '2026-09-15' ),
			'mid-season should expand to that season end'
		);
		$this->assertSame(
			'2027-06-30',
			ShiftTemplateExpander::default_window_end( '2027-02-01' ),
			'the second half belongs to the same season'
		);
	}

	/**
	 * Without the floor, late June would expand almost nothing and leave the club
	 * with an empty calendar exactly while next season is being set up.
	 */
	public function test_default_window_keeps_a_minimum_horizon_at_season_end(): void {
		$end = ShiftTemplateExpander::default_window_end( '2027-06-20' );

		$this->assertGreaterThan( '2027-06-30', $end );
		$this->assertSame(
			gmdate( 'Y-m-d', strtotime( '2027-06-20 +' . ShiftTemplateExpander::WINDOW_DAYS . ' days' ) ),
			$end
		);
	}

	/**
	 * Deleting a sjabloon must take its future, untouched rolled-out shifts
	 * with it — otherwise the calendar stays full of taken nobody manages.
	 */
	public function test_deleting_a_template_deletes_its_future_unprotected_shifts(): void {
		$start       = current_datetime()->modify( '+7 days' )->format( 'Y-m-d' );
		$template_id = $this->create_template( $start );

		ShiftTemplateExpander::expand_template( $template_id, $start, $start );
		$shift_ids = $this->template_shift_ids( $template_id );
		$this->assertCount( 1, $shift_ids, 'precondition: the template rolled out one shift' );

		wp_delete_post( $template_id, true );

		$this->assertNull( get_post( $shift_ids[0] ), 'the rolled-out shift is deleted with its template' );
	}

	/**
	 * Shifts with signups, customized shifts and past shifts survive a template
	 * delete, but lose their dangling template reference.
	 */
	public function test_deleting_a_template_detaches_protected_and_past_shifts(): void {
		$start       = current_datetime()->modify( '+7 days' )->format( 'Y-m-d' );
		$template_id = $this->create_template( $start );

		ShiftTemplateExpander::expand_template( $template_id, $start, $start );
		$shift_ids = $this->template_shift_ids( $template_id );
		$this->assertCount( 1, $shift_ids, 'precondition: the template rolled out one shift' );

		$signup_shift = (int) $shift_ids[0];
		$person_id    = self::factory()->post->create( [ 'post_type' => 'person' ] );
		update_post_meta( $signup_shift, 'assigned_persons', [ $person_id ] );

		$past_shift = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Verleden dienst',
			]
		);
		update_post_meta( $past_shift, 'template_id', $template_id );
		update_post_meta( $past_shift, 'start_datetime', current_datetime()->modify( '-7 days' )->format( 'Y-m-d' ) . ' 10:00:00' );

		wp_delete_post( $template_id, true );

		$this->assertNotNull( get_post( $signup_shift ), 'a shift with signups survives' );
		$this->assertNotNull( get_post( $past_shift ), 'a past shift survives for history' );
		$this->assertSame( '', get_post_meta( $signup_shift, 'template_id', true ), 'the survivor is detached' );
		$this->assertSame( '', get_post_meta( $past_shift, 'template_id', true ), 'the past shift is detached' );
	}

	/**
	 * Templates deleted before cascade-cleanup existed left orphaned shifts
	 * behind. The nightly sweep removes future untouched orphans, detaches the
	 * rest, and leaves shifts of a living template alone.
	 */
	public function test_orphan_sweep_cleans_up_shifts_of_already_deleted_templates(): void {
		$start       = current_datetime()->modify( '+7 days' )->format( 'Y-m-d' );
		$template_id = $this->create_template( $start );
		ShiftTemplateExpander::expand_template( $template_id, $start, $start );
		$healthy_ids = $this->template_shift_ids( $template_id );
		$this->assertCount( 1, $healthy_ids, 'precondition: a healthy template rolled out one shift' );

		$gone_template = 987654; // Never existed — simulates a pre-cleanup delete.

		$future_orphan = self::factory()->post->create( [ 'post_type' => 'dienst_shift' ] );
		update_post_meta( $future_orphan, 'template_id', $gone_template );
		update_post_meta( $future_orphan, 'start_datetime', $start . ' 10:00:00' );

		$past_orphan = self::factory()->post->create( [ 'post_type' => 'dienst_shift' ] );
		update_post_meta( $past_orphan, 'template_id', $gone_template );
		update_post_meta( $past_orphan, 'start_datetime', current_datetime()->modify( '-7 days' )->format( 'Y-m-d' ) . ' 10:00:00' );

		$result = ShiftTemplateExpander::cleanup_orphaned_shifts();

		$this->assertSame( 1, $result['deleted'] );
		$this->assertSame( 1, $result['detached'] );
		$this->assertNull( get_post( $future_orphan ), 'a future untouched orphan is deleted' );
		$this->assertNotNull( get_post( $past_orphan ), 'a past orphan survives for history' );
		$this->assertSame( '', get_post_meta( $past_orphan, 'template_id', true ), 'the past orphan is detached' );
		$this->assertNotNull( get_post( $healthy_ids[0] ), 'shifts of a living template are untouched' );
		$this->assertSame( (string) $template_id, get_post_meta( $healthy_ids[0], 'template_id', true ) );
	}

	private function template_shift_ids( int $template_id ): array {
		return get_posts(
=======
	 * Templates saved through wp-admin carry `start_time` as `HH:MM:SS`. The
	 * expander appends its own `:00`, so those used to expand into
	 * `2027-03-06 14:00:00:00` — unparseable, which rendered every such shift as
	 * "01-01-1970 00:00" in the calendar.
	 */
	public function test_expansion_accepts_template_times_with_seconds(): void {
		$until       = current_datetime()->modify( '+7 days' )->format( 'Y-m-d' );
		$template_id = $this->create_template( $until, '14:00:00', '17:00:00' );

		ShiftTemplateExpander::expand_range( current_datetime()->format( 'Y-m-d' ), $until );

		$shift_ids = get_posts(
>>>>>>> Stashed changes
			[
				'post_type'      => 'dienst_shift',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'template_id',
				'meta_value'     => $template_id,
			]
		);
<<<<<<< Updated upstream
=======

		$this->assertCount( 1, $shift_ids );
		$this->assertSame( $until . ' 14:00:00', get_post_meta( $shift_ids[0], 'start_datetime', true ) );
		$this->assertSame( $until . ' 17:00:00', get_post_meta( $shift_ids[0], 'end_datetime', true ) );
		$this->assertStringNotContainsString( '1970', get_the_title( $shift_ids[0] ) );
>>>>>>> Stashed changes
	}

	/** Expanding the same range twice must not duplicate anything. */
	public function test_expansion_is_idempotent_over_a_season(): void {
		// The other tests in this class create their own template; this one needs
		// one too, or the "first run creates something" precondition is vacuous.
		$this->create_template( current_datetime()->modify( '+7 days' )->format( 'Y-m-d' ) );

		$from = current_datetime()->format( 'Y-m-d' );
		$to   = current_datetime()->modify( '+120 days' )->format( 'Y-m-d' );

		$first  = ShiftTemplateExpander::expand_range( $from, $to );
		$second = ShiftTemplateExpander::expand_range( $from, $to );

		$this->assertGreaterThan( 0, $first, 'precondition: the first run creates shifts' );
		$this->assertSame( 0, $second, 'a second run over the same range creates nothing' );
	}
}
