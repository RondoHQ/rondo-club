<?php

namespace Tests\Wpunit;

use Rondo\Core\PostTitle;
use Rondo\Volunteer\ShiftTemplateExpander;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Regression coverage for HTML entities leaking out of post titles.
 *
 * `wptexturize()` rewrites a spaced hyphen into `&#8211;`, so a dienst type
 * named "Gastheer/gastvrouw - Bestuurslid van dienst" came back from
 * `get_the_title()` carrying that entity. Generated shift titles were built
 * from the filtered value and written straight into `post_title`, which put the
 * literal seven characters `&#8211;` in the database — and the React client,
 * which renders titles as text nodes, printed them verbatim on screen.
 */
class PostTitleEntityTest extends RondoTestCase {

	private const TEXTURIZED_TITLE = 'AWC-1 Gastheer/gastvrouw - Bestuurslid van dienst';

	public function test_plain_returns_the_raw_title_where_get_the_title_texturizes(): void {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => self::TEXTURIZED_TITLE,
			]
		);

		// Guard the premise: without this fix the filtered title carries the entity.
		$this->assertStringContainsString(
			'&#8211;',
			get_the_title( $post_id ),
			'wptexturize should still be the thing we are working around'
		);

		$this->assertSame( self::TEXTURIZED_TITLE, PostTitle::plain( $post_id ) );
		$this->assertStringNotContainsString( '&#', PostTitle::plain( $post_id ) );
	}

	public function test_plain_falls_back_for_missing_and_untitled_posts(): void {
		$this->assertSame( 'Dienst', PostTitle::plain( 0, 'Dienst' ) );
		$this->assertSame( '', PostTitle::plain( -1 ) );

		$untitled = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => '',
			]
		);
		$this->assertSame( 'Dienst', PostTitle::plain( $untitled, 'Dienst' ) );
	}

	/**
	 * The bug as reported: an expanded shift stored `&#8211;` in `post_title`.
	 */
	public function test_expanded_shift_titles_are_stored_without_entities(): void {
		$until = current_datetime()->modify( '+7 days' )->format( 'Y-m-d' );

		$type_id     = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => self::TEXTURIZED_TITLE,
			]
		);
		$template_id = self::factory()->post->create(
			[
				'post_type'   => 'shift_template',
				'post_status' => 'publish',
				'post_title'  => 'Wekelijkse entiteittest',
			]
		);

		update_post_meta( $template_id, 'dienst_type_id', $type_id );
		update_post_meta( $template_id, 'day_of_week', (int) gmdate( 'N', strtotime( $until ) ) );
		update_post_meta( $template_id, 'start_time', '10:00' );
		update_post_meta( $template_id, 'end_time', '12:00' );
		update_post_meta( $template_id, 'capacity', 2 );
		update_post_meta( $template_id, 'active_from', $until );

		$request = new WP_REST_Request( 'POST', '/rondo/v1/shift-templates/expand' );
		$request->set_param( 'until', $until );
		( new ShiftTemplateExpander() )->rest_expand_all( $request );

		$shift_ids = get_posts(
			[
				'post_type'      => 'dienst_shift',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => 'template_id',
				'meta_value'     => $template_id,
			]
		);

		$this->assertNotEmpty( $shift_ids, 'expansion should have created a shift to inspect' );

		foreach ( $shift_ids as $shift_id ) {
			$stored = get_post_field( 'post_title', $shift_id, 'raw' );

			$this->assertStringNotContainsString( '&#8211;', $stored );
			$this->assertStringContainsString( 'Gastheer/gastvrouw - Bestuurslid van dienst', $stored );
		}
	}
}
