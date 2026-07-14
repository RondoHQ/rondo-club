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
}
