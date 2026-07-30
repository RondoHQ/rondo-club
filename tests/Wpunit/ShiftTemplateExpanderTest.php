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

	private function create_template( string $active_from ): int {
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
		update_post_meta( $template_id, 'start_time', '10:00' );
		update_post_meta( $template_id, 'end_time', '12:00' );
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
