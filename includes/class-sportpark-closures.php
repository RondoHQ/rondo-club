<?php
/**
 * Whole-day sportpark closures. Dates include both the first and last day.
 *
 * @package Rondo\Sportpark
 */

namespace Rondo\Sportpark;

use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Volunteer\ShiftAssignments;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Closures {
	public const POST_TYPE = 'rondo_park_closure';

	/** One private native post, with WordPress-owned author and audit timestamps. */
	public static function record( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== self::POST_TYPE || $post->post_status !== 'publish' ) {
			return null;
		}
		return [
			'id'          => $id,
			'title'       => html_entity_decode( $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'description' => $post->post_content !== '' ? $post->post_content : null,
			'fields'      => Formatter::for_wire( self::POST_TYPE, Fields::all_for_post( $id ) ),
			'created_by'  => (int) $post->post_author,
			'created_at'  => get_post_time( DATE_RFC3339, true, $post ),
			'updated_at'  => get_post_modified_time( DATE_RFC3339, true, $post ),
		];
	}

	/** Fetch overlapping periods, including periods crossing a year boundary. */
	public static function between( string $from, string $to ): array {
		$ids = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'orderby'          => 'meta_value',
				'meta_key'         => 'starts_at',
				'order'            => 'ASC',
				'meta_query'       => [
					[
						'key'     => 'starts_at',
						'value'   => str_replace( '-', '', $to ),
						'compare' => '<=',
					],
					[
						'key'     => 'ends_at',
						'value'   => str_replace( '-', '', $from ),
						'compare' => '>=',
					],
				],
			]
		);
		return array_values( array_filter( array_map( [ self::class, 'record' ], $ids ) ) );
	}

	/** Reuse the loaded periods for every day in a template's expansion window. */
	public static function covers( string $date, array $closures ): bool {
		foreach ( $closures as $closure ) {
			if ( $closure['fields']['starts_at'] <= $date && $closure['fields']['ends_at'] >= $date ) {
				return true;
			}
		}
		return false;
	}

	/** Detect all existing, non-cancelled tasks, including manually planned tasks. */
	public static function conflicts( string $from, string $to ): array {
		$next      = ( new \DateTimeImmutable( $to, wp_timezone() ) )->modify( '+1 day' )->format( 'Y-m-d' );
		$ids       = get_posts(
			[
				'post_type'        => 'dienst_shift',
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'meta_query'       => [
					[
						'key'     => 'start_datetime',
						'value'   => $next . ' 00:00:00',
						'compare' => '<',
						'type'    => 'DATETIME',
					],
					[
						'relation' => 'OR',
						[
							'key'     => 'end_datetime',
							'value'   => $from . ' 00:00:00',
							'compare' => '>',
							'type'    => 'DATETIME',
						],
						[
							'key'     => 'start_datetime',
							'value'   => $from . ' 00:00:00',
							'compare' => '>=',
							'type'    => 'DATETIME',
						],
					],
				],
			]
		);
		$conflicts = [];
		foreach ( $ids as $id ) {
			$status = (string) Fields::get_for_post( $id, 'status' );
			if ( $status === 'geannuleerd' ) {
				continue;
			}
			$start       = (string) Fields::get_for_post( $id, 'start_datetime' );
			$conflicts[] = [
				'id'             => (int) $id,
				'title'          => html_entity_decode( (string) get_post_field( 'post_title', $id, 'raw' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				'starts_at'      => $start,
				'ends_at'        => (string) Fields::get_for_post( $id, 'end_datetime' ),
				'status'         => $status,
				'assigned_count' => count( ShiftAssignments::person_ids( $id ) ),
				'can_cancel'     => $status !== 'voltooid' && new \DateTimeImmutable( $start, wp_timezone() ) > current_datetime(),
			];
		}
		return $conflicts;
	}

	/** Bind confirmation to the reviewed dates, record and current task snapshot. */
	public static function conflict_token( int $id, array $fields, array $conflicts ): string {
		return hash_hmac( 'sha256', wp_json_encode( [ $id, $fields, $conflicts ] ), wp_salt( 'nonce' ) );
	}

	/** Serialize closure writes and template expansion using a native option. */
	public static function locked( callable $callback ) {
		$key = 'rondo_sportpark_write_lock';
		wp_cache_get( $key, 'options', true );
		$old = get_option( $key, [] );
		if ( is_array( $old ) && ! empty( $old['time'] ) && $old['time'] < time() - 300 ) {
			delete_option( $key );
		}
		$token = wp_generate_uuid4();
		if ( ! add_option(
			$key,
			[
				'token' => $token,
				'time'  => time(),
			],
			'',
			false
			) ) {
			return new \WP_Error( 'sportpark_busy', 'De sportparkkalender wordt bijgewerkt. Probeer het opnieuw.', [ 'status' => 409 ] );
		}
		try {
			return $callback();
		} finally {
			wp_cache_get( $key, 'options', true );
			if ( ( get_option( $key, [] )['token'] ?? '' ) === $token ) {
				delete_option( $key );
			}
		}
	}
}
