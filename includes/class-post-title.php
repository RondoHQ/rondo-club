<?php
/**
 * PostTitle
 *
 * Plain-text post titles for storage and for JSON fields that clients render as
 * text rather than as HTML.
 *
 * `get_the_title()` runs the `the_title` filters, and `wptexturize()` rewrites
 * typographic sequences into HTML entities: a spaced hyphen becomes `&#8211;`,
 * apostrophes become `&#8217;`. That is correct for markup that a browser
 * parses, but wrong in two places we kept hitting:
 *
 *   - Titles we build and then persist with `wp_insert_post()`. The entity is
 *     stored verbatim, so the database ends up holding the literal seven
 *     characters `&#8211;` instead of an en dash.
 *   - Plain-text REST fields. React renders `{value}` as text, so an entity
 *     arrives on screen as `&#8211;` rather than as the dash it encodes.
 *
 * Reading the raw column skips texturization entirely and returns exactly what
 * the admin typed, which is what both cases want.
 *
 * @package Rondo\Core
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static utility for un-texturized post titles.
 */
class PostTitle {

	/**
	 * Get a post title as plain text, without HTML entities.
	 *
	 * Use this instead of `get_the_title()` whenever the result is stored in the
	 * database, put into an email, or emitted as a plain-text JSON field. Keep
	 * using `get_the_title()` when the value is rendered as HTML.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $fallback Value to return when the post has no title.
	 * @return string
	 */
	public static function plain( int $post_id, string $fallback = '' ): string {
		if ( $post_id <= 0 ) {
			return $fallback;
		}

		$title = trim( (string) get_post_field( 'post_title', $post_id, 'raw' ) );

		return $title !== '' ? $title : $fallback;
	}
}
