<?php
/**
 * Storage for Twelve daily revenue reports.
 *
 * Each imported report becomes one `rondo_twelve_report` post. The full
 * parsed structure is stored JSON-encoded in `_twelve_report_data`; a few
 * scalar fields are stored separately so reports can be queried by period
 * without decoding every row.
 */

namespace Rondo\Twelve;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportRepository {

	public const POST_TYPE = 'rondo_twelve_report';

	public const META_PERIOD_START = '_twelve_period_start';
	public const META_PERIOD_END   = '_twelve_period_end';
	public const META_MESSAGE_ID   = '_twelve_message_id';
	public const META_DATA         = '_twelve_report_data';
	public const META_TOTAL_GROSS  = '_twelve_total_gross';

	/**
	 * Find a report by AgentMail message id (import idempotency).
	 */
	public function find_by_message_id( string $message_id ): ?int {
		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'   => self::META_MESSAGE_ID,
						'value' => $message_id,
					],
				],
			]
		);

		return empty( $posts ) ? null : (int) $posts[0];
	}

	/**
	 * Find a report by period end (second idempotency guard).
	 */
	public function find_by_period_end( string $period_end ): ?int {
		$posts = get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'   => self::META_PERIOD_END,
						'value' => $period_end,
					],
				],
			]
		);

		return empty( $posts ) ? null : (int) $posts[0];
	}

	/**
	 * Store a parsed report.
	 *
	 * @param array  $parsed       Output of ReportParser::parse().
	 * @param string $message_id   AgentMail message id.
	 * @param string $pdf_filename Original attachment filename.
	 * @param string $pdf_bytes    Raw PDF bytes (stored in protected post metadata).
	 * @return int|\WP_Error Post id of the created report.
	 */
	public function store( array $parsed, string $message_id, string $pdf_filename, string $pdf_bytes ) {
		if ( empty( $parsed['omzet'] ) && empty( $parsed['producten'] ) ) {
			return new \WP_Error( 'twelve_empty_report', 'Rapport bevat geen omzet- of productregels; niet opgeslagen.' );
		}

		$period_start = $parsed['period_start'] . ':00';
		$period_end   = $parsed['period_end'] . ':00';

		$existing = $this->find_by_message_id( $message_id ) ?? $this->find_by_period_end( $period_end );
		if ( $existing !== null ) {
			return new \WP_Error(
				'twelve_duplicate_report',
				sprintf( 'Rapport voor periode %s is al geïmporteerd (post %d).', $parsed['period_start'], $existing ),
				[ 'post_id' => $existing ]
			);
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => self::POST_TYPE,
				'post_title'  => sprintf( 'Twelve rapportage %s', substr( $period_start, 0, 10 ) ),
				'post_status' => 'publish',
				'post_author' => 0,
			],
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, self::META_PERIOD_START, $period_start );
		update_post_meta( $post_id, self::META_PERIOD_END, $period_end );
		update_post_meta( $post_id, self::META_MESSAGE_ID, $message_id );
		update_post_meta( $post_id, self::META_DATA, wp_json_encode( $parsed ) );
		update_post_meta( $post_id, self::META_TOTAL_GROSS, (float) ( $parsed['producten_totaal']['bruto'] ?? 0 ) );

		// Keep financial documents out of publicly served uploads entirely.
		$saved = add_post_meta( $post_id, '_twelve_pdf_base64', base64_encode( $pdf_bytes ), true );
		if ( ! $saved ) {
			wp_delete_post( $post_id, true );
			return new \WP_Error( 'twelve_pdf_storage', 'PDF opslaan mislukt; import kan opnieuw worden geprobeerd.' );
		}
		update_post_meta( $post_id, '_twelve_pdf_filename', sanitize_file_name( $pdf_filename ) );
		return $post_id;
	}

	/**
	 * Load parsed reports whose period start falls within [from, to] (dates).
	 *
	 * @return array<int, array{id: int, period_start: string, period_end: string, data: array}>
	 */
	public function query( string $from, string $to ): array {
		$query = new \WP_Query(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => 'meta_value',
				'meta_key'         => self::META_PERIOD_START,
				'order'            => 'ASC',
				'suppress_filters' => true,
				'no_found_rows'    => true,
				'meta_query'       => [
					[
						'key'     => self::META_PERIOD_START,
						'value'   => [ $from . ' 00:00:00', $to . ' 23:59:59' ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
				],
			]
		);

		$reports = [];
		foreach ( $query->posts as $post ) {
			$data = json_decode( (string) get_post_meta( $post->ID, self::META_DATA, true ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$reports[] = [
				'id'           => $post->ID,
				'period_start' => (string) get_post_meta( $post->ID, self::META_PERIOD_START, true ),
				'period_end'   => (string) get_post_meta( $post->ID, self::META_PERIOD_END, true ),
				'data'         => $data,
			];
		}

		return $reports;
	}

	/**
	 * Load the most recent reports.
	 *
	 * @return array<int, array{id: int, period_start: string, period_end: string, data: array}>
	 */
	public function latest( int $limit = 30 ): array {
		$query = new \WP_Query(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => $limit,
				'orderby'          => 'meta_value',
				'meta_key'         => self::META_PERIOD_START,
				'order'            => 'DESC',
				'suppress_filters' => true,
				'no_found_rows'    => true,
			]
		);

		$reports = [];
		foreach ( $query->posts as $post ) {
			$data = json_decode( (string) get_post_meta( $post->ID, self::META_DATA, true ), true );
			if ( ! is_array( $data ) ) {
				continue;
			}
			$reports[] = [
				'id'           => $post->ID,
				'period_start' => (string) get_post_meta( $post->ID, self::META_PERIOD_START, true ),
				'period_end'   => (string) get_post_meta( $post->ID, self::META_PERIOD_END, true ),
				'data'         => $data,
			];
		}

		return $reports;
	}
}
