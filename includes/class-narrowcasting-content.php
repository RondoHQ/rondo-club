<?php
/**
 * Native WordPress content and playlist service for Club TV.
 *
 * @package Rondo\Narrowcasting
 */

namespace Rondo\Narrowcasting;

use DateTimeImmutable;
use InvalidArgumentException;
use Rondo\Core\SponsorStatus;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Build and resolve safe signage content without exposing private records. */
final class Content {
	public const ITEM_POST_TYPE     = 'rondo_signage_item';
	public const PLAYLIST_POST_TYPE = 'rondo_signage_list';
	public const DEFAULT_OPTION     = 'rondo_narrowcasting_default_playlist_id';

	private const CONTENT_TYPES = [
		'announcement',
		'cancellations',
		'fallback',
		'image',
		'matches',
		'results',
		'rooms',
		'sponsor',
		'video',
	];

	private const DYNAMIC_TYPES = [ 'matches', 'rooms', 'cancellations', 'results' ];
	private const IMAGE_MIMES   = [ 'image/jpeg', 'image/png', 'image/webp' ];
	private const VIDEO_MIMES   = [ 'video/mp4' ];

	/** Return all administrator/editor-visible items. */
	public function list_items( bool $sponsor_only = false ): array {
		$posts = get_posts(
			[
				'post_type'        => self::ITEM_POST_TYPE,
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'orderby'          => 'modified',
				'order'            => 'DESC',
				'suppress_filters' => true,
			]
		);

		$items = [];
		foreach ( $posts as $post ) {
			if ( $sponsor_only && Fields::get_for_post( (int) $post->ID, 'content_type' ) !== 'sponsor' ) {
				continue;
			}
			$items[] = $this->format_item_admin( (int) $post->ID );
		}
		return $items;
	}

	/** Create one content item. */
	public function create_item( array $payload, bool $sponsor_only = false ) {
		return $this->save_item( 0, $payload, $sponsor_only );
	}

	/** Update one content item. */
	public function update_item( int $item_id, array $payload, bool $sponsor_only = false ) {
		if ( ! $this->is_post_type( $item_id, self::ITEM_POST_TYPE ) ) {
			return new \WP_Error( 'rondo_signage_item_not_found', __( 'Club TV-item niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $sponsor_only && Fields::get_for_post( $item_id, 'content_type' ) !== 'sponsor' ) {
			return new \WP_Error( 'rondo_signage_sponsor_scope', __( 'Sponsorbeheerders kunnen alleen sponsoritems wijzigen.', 'rondo' ), [ 'status' => 403 ] );
		}
		return $this->save_item( $item_id, $payload, $sponsor_only );
	}

	/** Trash one content item. */
	public function delete_item( int $item_id, bool $sponsor_only = false ) {
		if ( ! $this->is_post_type( $item_id, self::ITEM_POST_TYPE ) ) {
			return new \WP_Error( 'rondo_signage_item_not_found', __( 'Club TV-item niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $sponsor_only && Fields::get_for_post( $item_id, 'content_type' ) !== 'sponsor' ) {
			return new \WP_Error( 'rondo_signage_sponsor_scope', __( 'Sponsorbeheerders kunnen alleen sponsoritems verwijderen.', 'rondo' ), [ 'status' => 403 ] );
		}
		return wp_trash_post( $item_id ) ? true : new \WP_Error( 'rondo_signage_delete_failed', __( 'Het Club TV-item kon niet worden verwijderd.', 'rondo' ), [ 'status' => 500 ] );
	}

	/** Return all playlists. */
	public function list_playlists(): array {
		$posts = get_posts(
			[
				'post_type'        => self::PLAYLIST_POST_TYPE,
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);
		return array_map( fn( $post ) => $this->format_playlist_admin( (int) $post->ID ), $posts );
	}

	/** Create one playlist. */
	public function create_playlist( array $payload ) {
		return $this->save_playlist( 0, $payload );
	}

	/** Update one playlist. */
	public function update_playlist( int $playlist_id, array $payload ) {
		if ( ! $this->is_post_type( $playlist_id, self::PLAYLIST_POST_TYPE ) ) {
			return new \WP_Error( 'rondo_signage_playlist_not_found', __( 'Playlist niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		return $this->save_playlist( $playlist_id, $payload );
	}

	/** Trash a playlist that is not assigned as the default. */
	public function delete_playlist( int $playlist_id ) {
		if ( ! $this->is_post_type( $playlist_id, self::PLAYLIST_POST_TYPE ) ) {
			return new \WP_Error( 'rondo_signage_playlist_not_found', __( 'Playlist niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $playlist_id === $this->default_playlist_id() ) {
			delete_option( self::DEFAULT_OPTION );
		}
		return wp_trash_post( $playlist_id ) ? true : new \WP_Error( 'rondo_signage_delete_failed', __( 'De playlist kon niet worden verwijderd.', 'rondo' ), [ 'status' => 500 ] );
	}

	/** Set or clear the site-wide fallback playlist. */
	public function set_default_playlist( int $playlist_id ): array|\WP_Error {
		if ( $playlist_id !== 0 && ! $this->is_post_type( $playlist_id, self::PLAYLIST_POST_TYPE ) ) {
			return new \WP_Error( 'rondo_signage_playlist_not_found', __( 'Playlist niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $playlist_id === 0 ) {
			delete_option( self::DEFAULT_OPTION );
		} else {
			update_option( self::DEFAULT_OPTION, $playlist_id, false );
		}
		return [ 'default_playlist_id' => $this->default_playlist_id() ?: null ];
	}

	/** Assign one playlist to a display. */
	public function assign_display_playlist( int $display_id, int $playlist_id ): array|\WP_Error {
		if ( ! $this->is_post_type( $display_id, 'rondo_display' ) ) {
			return new \WP_Error( 'rondo_display_not_found', __( 'Scherm niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $playlist_id !== 0 && ! $this->is_post_type( $playlist_id, self::PLAYLIST_POST_TYPE ) ) {
			return new \WP_Error( 'rondo_signage_playlist_not_found', __( 'Playlist niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		Fields::update_for_post( $display_id, 'assigned_playlist_id', $playlist_id );
		return [
			'display_id'  => $display_id,
			'playlist_id' => $playlist_id ?: null,
		];
	}

	/** Safe display choices for editors configuring overrides. */
	public function display_choices(): array {
		$posts = get_posts(
			[
				'post_type'        => 'rondo_display',
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);
		return array_map(
			static fn( $post ) => [
				'id'       => (int) $post->ID,
				'name'     => get_the_title( $post ),
				'location' => (string) Fields::get_for_post( (int) $post->ID, 'location' ),
			],
			$posts
		);
	}

	/** Public sponsor choices containing no contact fields. */
	public function sponsor_choices(): array {
		$posts = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'meta_key'         => 'is_sponsor',
				'meta_value'       => '1',
				'suppress_filters' => true,
			]
		);
		return array_map(
			static fn( $post ) => [
				'id'       => (int) $post->ID,
				'name'     => get_the_title( $post ),
				'logo_url' => get_the_post_thumbnail_url( $post, 'medium' ) ?: null,
			],
			$posts
		);
	}

	/**
	 * Resolve scheduling, weights, fallback and overrides into a player-safe manifest.
	 */
	public function resolve_manifest( int $display_id = 0, int $playlist_id = 0, ?DateTimeImmutable $at = null, bool $include_reasons = false ): array {
		$at          = $at ?: current_datetime();
		$playlist_id = $playlist_id ?: $this->playlist_for_display( $display_id );
		$excluded    = [];
		if ( $playlist_id && ! $this->is_post_type( $playlist_id, self::PLAYLIST_POST_TYPE ) ) {
			$excluded[]  = [
				'id'     => $playlist_id,
				'title'  => '',
				'reason' => __( 'Playlist bestaat niet.', 'rondo' ),
			];
			$playlist_id = 0;
		}
		$scenes   = $this->active_overrides( $display_id, $at );
		$override = $scenes !== [];

		if ( ! $override && $playlist_id ) {
			$playlist = $this->format_playlist_admin( $playlist_id );
			$reason   = $this->schedule_reason( $playlist['fields'], $at, 'playlist' );
			if ( $reason === '' ) {
				$scenes = $this->resolve_playlist_rows( $playlist, $at, $excluded );
			} else {
				$excluded[] = [
					'id'     => $playlist_id,
					'title'  => $playlist['title'],
					'reason' => $reason,
				];
			}

			if ( $scenes === [] && ! empty( $playlist['fields']['fallback_item_id'] ) ) {
				$fallback = $this->resolved_item( (int) $playlist['fields']['fallback_item_id'], $at );
				if ( is_array( $fallback ) ) {
					$scenes[] = $fallback;
				}
			}
		}

		if ( $scenes === [] ) {
			$scenes = $this->builtin_fallback_scenes();
		}

		$manifest = [
			'playlist_id'     => $playlist_id ?: null,
			'override'        => $override,
			'generated_at'    => gmdate( DATE_RFC3339 ),
			'effective_at'    => $at->format( DATE_RFC3339 ),
			'content_version' => substr( hash( 'sha256', (string) wp_json_encode( $scenes ) ), 0, 20 ),
			'cycle_seconds'   => array_sum( array_column( $scenes, 'duration_seconds' ) ),
			'scenes'          => array_values( $scenes ),
		];
		if ( $include_reasons ) {
			$manifest['excluded'] = $excluded;
		}
		return $manifest;
	}

	/** Store a validated item through the canonical field layer. */
	private function save_item( int $item_id, array $payload, bool $sponsor_only ) {
		$current = $item_id ? Fields::all_for_post( $item_id ) : [];
		$type    = sanitize_key( (string) ( $payload['content_type'] ?? $current['content_type'] ?? 'announcement' ) );
		if ( ! in_array( $type, self::CONTENT_TYPES, true ) ) {
			return new \WP_Error( 'rondo_signage_type_invalid', __( 'Kies een geldig Club TV-type.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( $sponsor_only && $type !== 'sponsor' ) {
			return new \WP_Error( 'rondo_signage_sponsor_scope', __( 'Sponsorbeheerders kunnen alleen sponsoritems maken.', 'rondo' ), [ 'status' => 403 ] );
		}

		$title = substr( sanitize_text_field( (string) ( $payload['title'] ?? ( $item_id ? get_the_title( $item_id ) : '' ) ) ), 0, 100 );
		if ( $title === '' ) {
			return new \WP_Error( 'rondo_signage_title_required', __( 'Geef het Club TV-item een titel.', 'rondo' ), [ 'status' => 400 ] );
		}

		$sponsor_id = absint( $payload['sponsor_person_id'] ?? $current['sponsor_person_id'] ?? 0 );
		if ( $type === 'sponsor' && ( ! $sponsor_id || ! SponsorStatus::is_sponsor( $sponsor_id ) ) ) {
			return new \WP_Error( 'rondo_signage_sponsor_required', __( 'Kies een bestaande sponsorrelatie.', 'rondo' ), [ 'status' => 400 ] );
		}

		$media_id    = absint( $payload['media_attachment_id'] ?? $current['media_attachment_id'] ?? 0 );
		$media_error = $this->validate_media( $type, $media_id );
		if ( is_wp_error( $media_error ) ) {
			return $media_error;
		}

		$values = [
			'content_type'         => $type,
			'enabled'              => rest_sanitize_boolean( $payload['enabled'] ?? $current['enabled'] ?? true ),
			'duration_seconds'     => (int) ( $payload['duration_seconds'] ?? $current['duration_seconds'] ?? 12 ),
			'valid_from'           => $payload['valid_from'] ?? $this->wire_value( self::ITEM_POST_TYPE, $current, 'valid_from' ),
			'valid_until'          => $payload['valid_until'] ?? $this->wire_value( self::ITEM_POST_TYPE, $current, 'valid_until' ),
			'priority'             => $sponsor_only ? 0 : (int) ( $payload['priority'] ?? $current['priority'] ?? 0 ),
			'sponsor_person_id'    => $sponsor_id,
			'media_attachment_id'  => $media_id,
			'body'                 => substr( sanitize_textarea_field( (string) ( $payload['body'] ?? $current['body'] ?? '' ) ), 0, 500 ),
			'cta_text'             => substr( sanitize_text_field( (string) ( $payload['cta_text'] ?? $current['cta_text'] ?? '' ) ), 0, 100 ),
			'background_color'     => $this->sanitize_color( $payload['background_color'] ?? $current['background_color'] ?? '#0f172a', '#0f172a' ),
			'text_color'           => $this->sanitize_color( $payload['text_color'] ?? $current['text_color'] ?? '#ffffff', '#ffffff' ),
			'accent_color'         => $this->sanitize_color( $payload['accent_color'] ?? $current['accent_color'] ?? '#22d3ee', '#22d3ee' ),
			'use_club_colors'      => rest_sanitize_boolean( $payload['use_club_colors'] ?? $current['use_club_colors'] ?? true ),
			'is_override'          => $sponsor_only ? false : rest_sanitize_boolean( $payload['is_override'] ?? $current['is_override'] ?? false ),
			'override_display_ids' => $sponsor_only ? [] : array_values( array_filter( array_map( 'absint', (array) ( $payload['override_display_ids'] ?? $current['override_display_ids'] ?? [] ) ) ) ),
		];

		try {
			$values = Formatter::for_storage( self::ITEM_POST_TYPE, $values );
		} catch ( InvalidArgumentException $error ) {
			return new \WP_Error( 'rondo_signage_fields_invalid', $error->getMessage(), [ 'status' => 400 ] );
		}
		if ( $values['valid_from'] && $values['valid_until'] && $values['valid_from'] >= $values['valid_until'] ) {
			return new \WP_Error( 'rondo_signage_window_invalid', __( 'De eindtijd moet na de starttijd liggen.', 'rondo' ), [ 'status' => 400 ] );
		}

		$is_new = $item_id === 0;
		if ( $is_new ) {
			$item_id = wp_insert_post(
				[
					'post_type'   => self::ITEM_POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_author' => get_current_user_id(),
				],
				true
			);
			if ( is_wp_error( $item_id ) ) {
				return $item_id;
			}
		} else {
			wp_update_post(
				[
					'ID'         => $item_id,
					'post_title' => $title,
				]
				);
		}

		$stored = Fields::update_many_for_post( $item_id, $values );
		if ( is_wp_error( $stored ) ) {
			if ( $is_new ) {
				wp_delete_post( $item_id, true );
			}
			return $stored;
		}
		return $this->format_item_admin( $item_id );
	}

	/** Store a validated playlist through the canonical field layer. */
	private function save_playlist( int $playlist_id, array $payload ) {
		$current = $playlist_id ? Fields::all_for_post( $playlist_id ) : [];
		$title   = substr( sanitize_text_field( (string) ( $payload['title'] ?? ( $playlist_id ? get_the_title( $playlist_id ) : '' ) ) ), 0, 100 );
		if ( $title === '' ) {
			return new \WP_Error( 'rondo_signage_title_required', __( 'Geef de playlist een naam.', 'rondo' ), [ 'status' => 400 ] );
		}

		$rows = [];
		foreach ( (array) ( $payload['items'] ?? $current['items'] ?? [] ) as $row ) {
			$item_id = absint( $row['item_id'] ?? 0 );
			if ( ! $this->is_post_type( $item_id, self::ITEM_POST_TYPE ) ) {
				return new \WP_Error( 'rondo_signage_playlist_item_invalid', __( 'Een playlistitem bestaat niet meer.', 'rondo' ), [ 'status' => 400 ] );
			}
			$rows[] = [
				'item_id'          => $item_id,
				'duration_seconds' => max( 0, min( 120, (int) ( $row['duration_seconds'] ?? 0 ) ) ),
				'weight'           => max( 1, min( 10, (int) ( $row['weight'] ?? 1 ) ) ),
			];
		}
		$fallback_item_id = absint( $payload['fallback_item_id'] ?? $current['fallback_item_id'] ?? 0 );
		if ( $fallback_item_id && ! $this->is_post_type( $fallback_item_id, self::ITEM_POST_TYPE ) ) {
			return new \WP_Error( 'rondo_signage_fallback_invalid', __( 'Het reservebeeld bestaat niet meer.', 'rondo' ), [ 'status' => 400 ] );
		}

		$values = [
			'enabled'          => rest_sanitize_boolean( $payload['enabled'] ?? $current['enabled'] ?? true ),
			'valid_from'       => $payload['valid_from'] ?? $this->wire_value( self::PLAYLIST_POST_TYPE, $current, 'valid_from' ),
			'valid_until'      => $payload['valid_until'] ?? $this->wire_value( self::PLAYLIST_POST_TYPE, $current, 'valid_until' ),
			'days_of_week'     => array_values( array_unique( array_map( 'sanitize_key', (array) ( $payload['days_of_week'] ?? $current['days_of_week'] ?? [] ) ) ) ),
			'start_time'       => $payload['start_time'] ?? $this->wire_value( self::PLAYLIST_POST_TYPE, $current, 'start_time' ),
			'end_time'         => $payload['end_time'] ?? $this->wire_value( self::PLAYLIST_POST_TYPE, $current, 'end_time' ),
			'fallback_item_id' => $fallback_item_id,
			'items'            => $rows,
		];

		try {
			$values = Formatter::for_storage( self::PLAYLIST_POST_TYPE, $values );
		} catch ( InvalidArgumentException $error ) {
			return new \WP_Error( 'rondo_signage_fields_invalid', $error->getMessage(), [ 'status' => 400 ] );
		}
		if ( $values['valid_from'] && $values['valid_until'] && $values['valid_from'] >= $values['valid_until'] ) {
			return new \WP_Error( 'rondo_signage_window_invalid', __( 'De eindtijd moet na de starttijd liggen.', 'rondo' ), [ 'status' => 400 ] );
		}

		$is_new = $playlist_id === 0;
		if ( $is_new ) {
			$playlist_id = wp_insert_post(
				[
					'post_type'   => self::PLAYLIST_POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_author' => get_current_user_id(),
				],
				true
			);
			if ( is_wp_error( $playlist_id ) ) {
				return $playlist_id;
			}
		} else {
			wp_update_post(
				[
					'ID'         => $playlist_id,
					'post_title' => $title,
				]
				);
		}

		$stored = Fields::update_many_for_post( $playlist_id, $values );
		if ( is_wp_error( $stored ) ) {
			if ( $is_new ) {
				wp_delete_post( $playlist_id, true );
			}
			return $stored;
		}
		return $this->format_playlist_admin( $playlist_id );
	}

	/** Format complete item data for authenticated editors. */
	private function format_item_admin( int $item_id ): array {
		$fields = Formatter::for_wire( self::ITEM_POST_TYPE, Fields::all_for_post( $item_id ) );

		return [
			'id'         => $item_id,
			'title'      => get_the_title( $item_id ),
			'fields'     => $fields,
			'media'      => $this->format_media( (int) ( $fields['media_attachment_id'] ?? 0 ) ),
			'is_default' => false,
			'modified'   => get_post_modified_time( DATE_RFC3339, false, $item_id ),
		];
	}

	/** Format complete playlist data for authenticated editors. */
	private function format_playlist_admin( int $playlist_id ): array {
		$fields = Formatter::for_wire( self::PLAYLIST_POST_TYPE, Fields::all_for_post( $playlist_id ) );
		return [
			'id'         => $playlist_id,
			'title'      => get_the_title( $playlist_id ),
			'fields'     => $fields,
			'is_default' => $playlist_id === $this->default_playlist_id(),
			'modified'   => get_post_modified_time( DATE_RFC3339, false, $playlist_id ),
		];
	}

	/** Resolve playlist rows, scheduling and weights. */
	private function resolve_playlist_rows( array $playlist, DateTimeImmutable $at, array &$excluded ): array {
		$weighted = [];
		foreach ( $playlist['fields']['items'] as $row ) {
			$item_id = (int) ( $row['item_id'] ?? 0 );
			$item    = $this->resolved_item( $item_id, $at );
			if ( is_wp_error( $item ) ) {
				$excluded[] = [
					'id'     => $item_id,
					'title'  => get_the_title( $item_id ),
					'reason' => $item->get_error_message(),
				];
				continue;
			}
			$override_duration = (int) ( $row['duration_seconds'] ?? 0 );
			if ( $override_duration >= 5 ) {
				$item['duration_seconds'] = $override_duration;
			}
			$weight = max( 1, min( 10, (int) ( $row['weight'] ?? 1 ) ) );
			for ( $index = 0; $index < $weight; $index++ ) {
				$weighted[] = $item;
			}
		}
		return $this->avoid_consecutive_duplicates( $weighted );
	}

	/** Return active high-priority takeover items for a display. */
	private function active_overrides( int $display_id, DateTimeImmutable $at ): array {
		$items = [];
		foreach ( $this->list_items() as $item ) {
			if ( empty( $item['fields']['is_override'] ) ) {
				continue;
			}
			$display_ids = (array) ( $item['fields']['override_display_ids'] ?? [] );
			if ( $display_ids && ( ! $display_id || ! in_array( $display_id, $display_ids, true ) ) ) {
				continue;
			}
			$resolved = $this->resolved_item( (int) $item['id'], $at );
			if ( is_array( $resolved ) ) {
				$resolved['priority'] = (int) ( $item['fields']['priority'] ?? 0 );
				$items[]              = $resolved;
			}
		}
		usort( $items, static fn( $left, $right ) => $right['priority'] <=> $left['priority'] );
		foreach ( $items as &$item ) {
			unset( $item['priority'] );
		}
		unset( $item );
		return $items;
	}

	/** Resolve and sanitize one scheduled item. */
	private function resolved_item( int $item_id, DateTimeImmutable $at ) {
		if ( ! $this->is_post_type( $item_id, self::ITEM_POST_TYPE ) ) {
			return new \WP_Error( 'not_found', __( 'Item bestaat niet.', 'rondo' ) );
		}
		$item   = $this->format_item_admin( $item_id );
		$reason = $this->schedule_reason( $item['fields'], $at, 'item' );
		if ( $reason !== '' ) {
			return new \WP_Error( 'excluded', $reason );
		}
		$type = (string) $item['fields']['content_type'];
		if ( in_array( $type, [ 'image', 'video' ], true ) && empty( $item['media']['url'] ) ) {
			return new \WP_Error( 'media_missing', __( 'Media ontbreekt.', 'rondo' ) );
		}

		$sponsor = null;
		if ( $type === 'sponsor' ) {
			$sponsor_id = (int) ( $item['fields']['sponsor_person_id'] ?? 0 );
			if ( ! $sponsor_id || ! SponsorStatus::is_sponsor( $sponsor_id ) ) {
				return new \WP_Error( 'sponsor_missing', __( 'Sponsorrelatie ontbreekt.', 'rondo' ) );
			}
			$sponsor = [
				'id'       => $sponsor_id,
				'name'     => get_the_title( $sponsor_id ),
				'logo_url' => get_the_post_thumbnail_url( $sponsor_id, 'large' ) ?: null,
			];
		}
		$use_club_colors = ! array_key_exists( 'use_club_colors', $item['fields'] ) || ! empty( $item['fields']['use_club_colors'] );

		return [
			'id'               => 'item-' . $item_id,
			'item_id'          => $item_id,
			'type'             => $type,
			'duration_seconds' => max( 5, min( 120, (int) $item['fields']['duration_seconds'] ) ),
			'title'            => substr( (string) $item['title'], 0, 100 ),
			'body'             => substr( (string) $item['fields']['body'], 0, 500 ),
			'cta_text'         => substr( (string) $item['fields']['cta_text'], 0, 100 ),
			'colors'           => [
				'background' => $use_club_colors ? null : $this->sanitize_color( $item['fields']['background_color'], '#0f172a' ),
				'text'       => $use_club_colors ? null : $this->sanitize_color( $item['fields']['text_color'], '#ffffff' ),
				'accent'     => $use_club_colors ? null : $this->sanitize_color( $item['fields']['accent_color'], '#22d3ee' ),
			],
			'media'            => $item['media'],
			'sponsor'          => $sponsor,
		];
	}

	/** Explain why a playlist or item is inactive. */
	private function schedule_reason( array $fields, DateTimeImmutable $at, string $kind ): string {
		if ( empty( $fields['enabled'] ) ) {
			return $kind === 'playlist' ? __( 'Playlist is uitgeschakeld.', 'rondo' ) : __( 'Item is uitgeschakeld.', 'rondo' );
		}
		$timestamp = $at->getTimestamp();
		$from      = ! empty( $fields['valid_from'] ) ? strtotime( (string) $fields['valid_from'] ) : 0;
		$until     = ! empty( $fields['valid_until'] ) ? strtotime( (string) $fields['valid_until'] ) : 0;
		if ( $from && $timestamp < $from ) {
			return __( 'Nog niet begonnen.', 'rondo' );
		}
		if ( $until && $timestamp >= $until ) {
			return __( 'Verlopen.', 'rondo' );
		}
		$days = (array) ( $fields['days_of_week'] ?? [] );
		if ( $days && ! in_array( strtolower( $at->format( 'D' ) ), $days, true ) ) {
			return __( 'Niet gepland op deze dag.', 'rondo' );
		}
		$start = substr( (string) ( $fields['start_time'] ?? '' ), 0, 5 );
		$end   = substr( (string) ( $fields['end_time'] ?? '' ), 0, 5 );
		$time  = $at->format( 'H:i' );
		if ( $start && $end ) {
			$inside = $start <= $end ? ( $time >= $start && $time < $end ) : ( $time >= $start || $time < $end );
			if ( ! $inside ) {
				return __( 'Buiten het geplande tijdvak.', 'rondo' );
			}
		}
		return '';
	}

	/** Ensure weighted content does not repeat while another item is available. */
	private function avoid_consecutive_duplicates( array $items ): array {
		for ( $index = 1, $count = count( $items ); $index < $count; $index++ ) {
			if ( $items[ $index ]['id'] !== $items[ $index - 1 ]['id'] ) {
				continue;
			}
			for ( $swap = $index + 1; $swap < $count; $swap++ ) {
				if ( $items[ $swap ]['id'] !== $items[ $index - 1 ]['id'] ) {
					[ $items[ $index ], $items[ $swap ] ] = [ $items[ $swap ], $items[ $index ] ];
					break;
				}
			}
		}
		return $items;
	}

	/** Built-in last-resort cycle preserves current matchday behavior. */
	private function builtin_fallback_scenes(): array {
		$scenes = [];
		foreach ( self::DYNAMIC_TYPES as $type ) {
			$scenes[] = [
				'id'               => 'builtin-' . $type,
				'item_id'          => null,
				'type'             => $type,
				'duration_seconds' => 12,
				'title'            => '',
				'body'             => '',
				'cta_text'         => '',
				'colors'           => [
					'background' => null,
					'text'       => null,
					'accent'     => null,
				],
				'media'            => null,
				'sponsor'          => null,
			];
		}
		$scenes[] = [
			'id'               => 'builtin-fallback',
			'item_id'          => null,
			'type'             => 'fallback',
			'duration_seconds' => 12,
			'title'            => __( 'Welkom bij de club', 'rondo' ),
			'body'             => '',
			'cta_text'         => '',
			'colors'           => [
				'background' => null,
				'text'       => null,
				'accent'     => null,
			],
			'media'            => null,
			'sponsor'          => null,
		];
		return $scenes;
	}

	/** Validate display media before an item can be scheduled. */
	private function validate_media( string $type, int $attachment_id ) {
		if ( ! in_array( $type, [ 'image', 'video' ], true ) && ! $attachment_id ) {
			return true;
		}
		if ( in_array( $type, [ 'image', 'video' ], true ) && ! $attachment_id ) {
			return new \WP_Error( 'rondo_signage_media_required', __( 'Upload eerst de afbeelding of video.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( $attachment_id && get_post_type( $attachment_id ) !== 'attachment' ) {
			return new \WP_Error( 'rondo_signage_media_invalid', __( 'De gekozen media bestaat niet.', 'rondo' ), [ 'status' => 400 ] );
		}
		$mime = (string) get_post_mime_type( $attachment_id );
		if ( in_array( $type, [ 'image', 'sponsor' ], true ) && ! in_array( $mime, self::IMAGE_MIMES, true ) ) {
			return new \WP_Error( 'rondo_signage_media_type', __( 'Gebruik JPEG, PNG of WebP voor een afbeelding.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( $type === 'video' && ! in_array( $mime, self::VIDEO_MIMES, true ) ) {
			return new \WP_Error( 'rondo_signage_media_type', __( 'Gebruik een H.264 MP4-video.', 'rondo' ), [ 'status' => 400 ] );
		}
		$file = get_attached_file( $attachment_id );
		if ( $file && is_file( $file ) && filesize( $file ) > 100 * MB_IN_BYTES ) {
			return new \WP_Error( 'rondo_signage_media_size', __( 'Club TV-media mag maximaal 100 MB zijn.', 'rondo' ), [ 'status' => 400 ] );
		}
		return true;
	}

	/** Return one versioned public media descriptor. */
	private function format_media( int $attachment_id ): ?array {
		if ( ! $attachment_id || get_post_type( $attachment_id ) !== 'attachment' ) {
			return null;
		}
		$post     = get_post( $attachment_id );
		$mime     = (string) get_post_mime_type( $attachment_id );
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$url      = wp_get_attachment_image_url( $attachment_id, 'large' ) ?: wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return null;
		}
		$url = add_query_arg( 'v', strtotime( (string) $post->post_modified_gmt ) ?: 1, $url );
		return [
			'id'        => $attachment_id,
			'url'       => $url,
			'mime_type' => $mime,
			'kind'      => str_starts_with( $mime, 'video/' ) ? 'video' : 'image',
			'width'     => is_array( $metadata ) ? (int) ( $metadata['width'] ?? 0 ) : 0,
			'height'    => is_array( $metadata ) ? (int) ( $metadata['height'] ?? 0 ) : 0,
			'alt'       => substr( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ), 0, 120 ),
		];
	}

	/** Convert a stored current field back to the canonical write shape. */
	private function wire_value( string $context, array $fields, string $name ) {
		if ( ! array_key_exists( $name, $fields ) || $fields[ $name ] === '' || $fields[ $name ] === null ) {
			return '';
		}
		return Formatter::for_wire( $context, [ $name => $fields[ $name ] ] )[ $name ];
	}

	private function playlist_for_display( int $display_id ): int {
		if ( $display_id && $this->is_post_type( $display_id, 'rondo_display' ) ) {
			$assigned = (int) Fields::get_for_post( $display_id, 'assigned_playlist_id' );
			if ( $assigned && $this->is_post_type( $assigned, self::PLAYLIST_POST_TYPE ) ) {
				return $assigned;
			}
		}
		return $this->default_playlist_id();
	}

	private function default_playlist_id(): int {
		$playlist_id = absint( get_option( self::DEFAULT_OPTION, 0 ) );
		return $this->is_post_type( $playlist_id, self::PLAYLIST_POST_TYPE ) ? $playlist_id : 0;
	}

	private function sanitize_color( $value, string $fallback ): string {
		$color = sanitize_hex_color( (string) $value );
		return $color ?: $fallback;
	}

	private function is_post_type( int $post_id, string $post_type ): bool {
		return $post_id > 0 && get_post_type( $post_id ) === $post_type && get_post_status( $post_id ) !== 'trash';
	}
}
