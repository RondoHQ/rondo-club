<?php
/**
 * Copy a complete day of volunteer shifts to another date.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provides the manager-only bulk day-copy endpoint.
 */
class ShiftDayCopier {

	private const COPY_SOURCE_META = '_copied_from_shift_id';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the bulk copy endpoint.
	 */
	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/shifts/copy-day',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'rest_copy_day' ],
				'permission_callback' => [ $this, 'can_copy_day' ],
				'args'                => [
					'source_date' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'target_date' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	/**
	 * Only volunteer managers and administrators may copy planning days.
	 */
	public function can_copy_day(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'vrijwilligers' );
	}

	/**
	 * Copy every active shift on one date to another date.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_copy_day( \WP_REST_Request $request ) {
		$source_date = (string) $request->get_param( 'source_date' );
		$target_date = (string) $request->get_param( 'target_date' );
		$source_day  = $this->parse_date( $source_date );
		$target_day  = $this->parse_date( $target_date );

		if ( ! $source_day || ! $target_day ) {
			return new \WP_Error( 'rondo_invalid_shift_copy_date', 'Kies geldige bron- en doeldatums.', [ 'status' => 400 ] );
		}
		if ( $source_date === $target_date ) {
			return new \WP_Error( 'rondo_shift_copy_same_date', 'De doeldatum moet anders zijn dan de brondatum.', [ 'status' => 400 ] );
		}

		$today = current_datetime()->setTime( 0, 0, 0 );
		if ( $target_day < $today ) {
			return new \WP_Error( 'rondo_shift_copy_date_in_past', 'De doeldatum mag niet in het verleden liggen.', [ 'status' => 400 ] );
		}

		$source_shifts = $this->find_shifts_for_date( $source_date, true );
		if ( empty( $source_shifts ) ) {
			return new \WP_Error( 'rondo_shift_copy_source_empty', 'Op de brondatum staan geen actieve inschrijftaken.', [ 'status' => 404 ] );
		}

		$copy_rows = [];
		foreach ( $source_shifts as $source_shift ) {
			$row = $this->build_copy_row( $source_shift, $source_date, $target_date );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
			$copy_rows[] = $row;
		}

		$target_signatures = [];
		$copied_sources    = [];
		foreach ( $this->find_shifts_for_date( $target_date, false ) as $target_shift ) {
			$signature                       = $this->shift_signature(
				(int) get_post_meta( $target_shift->ID, 'dienst_type_id', true ),
				$this->normalize_datetime( (string) get_post_meta( $target_shift->ID, 'start_datetime', true ) ) ?: '',
				$this->normalize_datetime( (string) get_post_meta( $target_shift->ID, 'end_datetime', true ) ) ?: ''
			);
			$target_signatures[ $signature ] = true;

			$copied_from = (int) get_post_meta( $target_shift->ID, self::COPY_SOURCE_META, true );
			if ( $copied_from > 0 ) {
				$copied_sources[ $copied_from ] = true;
			}
		}

		$created_ids = [];
		$skipped     = [];
		foreach ( $copy_rows as $row ) {
			$signature = $this->shift_signature( $row['dienst_type_id'], $row['start_datetime'], $row['end_datetime'] );
			if ( isset( $target_signatures[ $signature ] ) || isset( $copied_sources[ $row['source_id'] ] ) ) {
				$skipped[] = [
					'source_id' => $row['source_id'],
					'reason'    => 'duplicate',
				];
				continue;
			}

			$post_id = wp_insert_post(
				[
					'post_type'   => 'dienst_shift',
					'post_status' => 'publish',
					'post_author' => get_current_user_id(),
					'post_title'  => $row['title'],
				],
				true
			);

			if ( is_wp_error( $post_id ) || $post_id === 0 ) {
				$this->rollback_created_shifts( $created_ids );
				return new \WP_Error( 'rondo_shift_day_copy_failed', 'De dagplanning kon niet volledig worden gekopieerd.', [ 'status' => 500 ] );
			}

			update_post_meta( $post_id, 'dienst_type_id', $row['dienst_type_id'] );
			update_post_meta( $post_id, 'start_datetime', $row['start_datetime'] );
			update_post_meta( $post_id, 'end_datetime', $row['end_datetime'] );
			update_post_meta( $post_id, 'capacity', $row['capacity'] );
			update_post_meta( $post_id, 'status', 'open' );
			update_post_meta( $post_id, 'iva_waived', $row['iva_waived'] ? 1 : 0 );
			update_post_meta( $post_id, 'assigned_persons', [] );
			if ( $row['notes'] !== '' ) {
				update_post_meta( $post_id, 'notes', $row['notes'] );
			}
			update_post_meta( $post_id, self::COPY_SOURCE_META, $row['source_id'] );
			update_post_meta( $post_id, '_copied_from_date', $source_date );
			update_post_meta( $post_id, '_copied_at', current_time( 'mysql', true ) );
			update_post_meta( $post_id, '_copied_by_user', get_current_user_id() );

			$created_ids[]                       = (int) $post_id;
			$target_signatures[ $signature ]     = true;
			$copied_sources[ $row['source_id'] ] = true;
		}

		do_action( 'rondo_shift_day_copied', $source_date, $target_date, $created_ids, $skipped, get_current_user_id() );

		return rest_ensure_response(
			[
				'source_date'   => $source_date,
				'target_date'   => $target_date,
				'source_count'  => count( $copy_rows ),
				'created'       => count( $created_ids ),
				'created_ids'   => $created_ids,
				'skipped'       => count( $skipped ),
				'skipped_items' => $skipped,
			]
		);
	}

	/**
	 * Find shifts whose start time falls on a date.
	 *
	 * @param string $date        Date in Y-m-d format.
	 * @param bool   $active_only Whether to return only open/full shifts.
	 * @return \WP_Post[]
	 */
	private function find_shifts_for_date( string $date, bool $active_only ): array {
		$meta_query = [
			'relation' => 'AND',
			[
				'key'     => 'start_datetime',
				'value'   => [ $date . ' 00:00:00', $date . ' 23:59:59' ],
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			],
		];
		if ( $active_only ) {
			$meta_query[] = [
				'key'     => 'status',
				'value'   => [ 'open', 'vol' ],
				'compare' => 'IN',
			];
		}

		$query = new \WP_Query(
			[
				'post_type'        => 'dienst_shift',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- one calendar day is naturally bounded.
				'posts_per_page'   => -1,
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => $active_only ? [ 'publish' ] : [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'meta_query'       => $meta_query,
				'meta_key'         => 'start_datetime',
				'orderby'          => 'meta_value',
				'order'            => 'ASC',
			]
		);

		return array_values( array_filter( $query->posts, static fn( $post ): bool => $post instanceof \WP_Post ) );
	}

	/**
	 * Validate and normalize one source shift before any writes occur.
	 *
	 * @param \WP_Post $source_shift Source shift.
	 * @param string   $source_date  Source date.
	 * @param string   $target_date  Target date.
	 * @return array|\WP_Error
	 */
	private function build_copy_row( \WP_Post $source_shift, string $source_date, string $target_date ) {
		$dienst_type_id = (int) get_post_meta( $source_shift->ID, 'dienst_type_id', true );
		$source_start   = $this->normalize_datetime( (string) get_post_meta( $source_shift->ID, 'start_datetime', true ) );
		$source_end     = $this->normalize_datetime( (string) get_post_meta( $source_shift->ID, 'end_datetime', true ) );

		if ( $dienst_type_id <= 0 || get_post_type( $dienst_type_id ) !== 'dienst_type' || ! $source_start || ! $source_end ) {
			return new \WP_Error( 'rondo_invalid_shift_copy_source', 'Een inschrijftaak op de brondatum bevat ongeldige planningsgegevens.', [ 'status' => 409 ] );
		}
		if ( substr( $source_start, 0, 10 ) !== $source_date || substr( $source_end, 0, 10 ) !== $source_date ) {
			return new \WP_Error( 'rondo_shift_copy_overnight_unsupported', 'Een inschrijftaak die over middernacht loopt kan nog niet als dagplanning worden gekopieerd.', [ 'status' => 409 ] );
		}

		$target_start = $target_date . substr( $source_start, 10 );
		$target_end   = $target_date . substr( $source_end, 10 );
		if ( $target_end <= $target_start ) {
			return new \WP_Error( 'rondo_invalid_shift_copy_time', 'Een inschrijftaak op de brondatum heeft geen geldige eindtijd.', [ 'status' => 409 ] );
		}

		$target_day = $this->parse_date( $target_date );
		if ( ! $target_day ) {
			return new \WP_Error( 'rondo_invalid_shift_copy_time', 'Een inschrijftaak op de brondatum heeft geen geldige begintijd.', [ 'status' => 409 ] );
		}

		return [
			'source_id'      => (int) $source_shift->ID,
			'dienst_type_id' => $dienst_type_id,
			'start_datetime' => $target_start,
			'end_datetime'   => $target_end,
			'capacity'       => max( 1, (int) get_post_meta( $source_shift->ID, 'capacity', true ) ),
			'iva_waived'     => (bool) get_post_meta( $source_shift->ID, 'iva_waived', true ),
			'notes'          => (string) get_post_meta( $source_shift->ID, 'notes', true ),
			'title'          => trim( get_the_title( $dienst_type_id ) . ' — ' . $target_day->format( 'd-m-Y' ) . ' ' . substr( $target_start, 11, 5 ) ),
		];
	}

	/**
	 * Parse an exact Y-m-d date in the WordPress timezone.
	 */
	private function parse_date( string $date ): ?\DateTimeImmutable {
		$parsed = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date, wp_timezone() );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( ! $parsed || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return null;
		}

		return $parsed->format( 'Y-m-d' ) === $date ? $parsed : null;
	}

	/**
	 * Normalize stored ACF datetimes to their canonical seconds precision.
	 */
	private function normalize_datetime( string $value ): ?string {
		$value  = trim( str_replace( 'T', ' ', $value ) );
		$format = strlen( $value ) === 16 ? '!Y-m-d H:i' : '!Y-m-d H:i:s';
		$parsed = \DateTimeImmutable::createFromFormat( $format, $value, wp_timezone() );
		$errors = \DateTimeImmutable::getLastErrors();
		if ( ! $parsed || ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
			return null;
		}

		return $parsed->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Build the duplicate-detection signature for a shift.
	 */
	private function shift_signature( int $dienst_type_id, string $start_datetime, string $end_datetime ): string {
		return implode( '|', [ $dienst_type_id, $start_datetime, $end_datetime ] );
	}

	/**
	 * Remove only posts created during the current failed request.
	 *
	 * @param int[] $created_ids Newly created shift IDs.
	 */
	private function rollback_created_shifts( array $created_ids ): void {
		foreach ( array_reverse( $created_ids ) as $post_id ) {
			wp_delete_post( (int) $post_id, true );
		}
	}
}
