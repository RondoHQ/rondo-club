<?php
/** Small, cacheable queries for dashboard person sets. */

namespace Rondo\Dashboard;

final class DashboardPeople {

	/** Return published person IDs within the current user's visibility. */
	public static function ids( array $meta_query = [] ): array {
		return array_map(
			'intval',
			get_posts(
				[
					'post_type'              => 'person',
					'post_status'            => 'publish',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'suppress_filters'       => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => $meta_query,
				]
			)
		);
	}

	/** Avoid the expensive NOT EXISTS/OR metadata join for active people. */
	public static function active_ids(): array {
		return array_values(
			array_diff(
				self::ids(),
				self::ids(
					[
						[
							'key'   => 'former_member',
							'value' => '1',
						],
					]
					)
			)
		);
	}
}
