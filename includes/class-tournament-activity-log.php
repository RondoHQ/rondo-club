<?php
/**
 * Private activity history for tournaments and their team entries.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentActivityLog {

	public const COMMENT_TYPE = 'rondo_tourn_activity';

	private const LABELS = [
		'tournament_created'                              => 'Toernooi aangemaakt',
		'tournament_updated'                              => 'Toernooi bijgewerkt',
		'tournament_published_updated'                    => 'Gepubliceerd toernooi bijgewerkt',
		'tournament_published'                            => 'Toernooi gepubliceerd',
		'entry_created'                                   => 'Inschrijfopdracht aangemaakt',
		'entry_assignments_updated'                       => 'Toewijzing bijgewerkt',
		'draft_updated'                                   => 'Conceptinschrijving bijgewerkt',
		'entry_submitted'                                 => 'Inschrijving bevestigd',
		'payment_created'                                 => 'Factuur en betaallink aangemaakt',
		'payment_failed'                                  => 'Betaallink maken mislukt',
		'payment_email_sent'                              => 'Betaalmail verzonden',
		'payment_email_failed'                            => 'Betaalmail niet volledig verzonden',
		'payment_confirmed'                               => 'Betaling bevestigd',
		'entry_reopened'                                  => 'Inschrijving heropend',
		'deadline_changed'                                => 'Interne deadline gewijzigd',
		'external_status_changed'                         => 'Externe voortgang gewijzigd',
		'lifecycle_status_changed'                        => 'Toernooistatus gewijzigd',
		'planner_note_changed'                            => 'Interne notitie bijgewerkt',
		'program_saved'                                   => 'Programma bijgewerkt',
		'program_sent'                                    => 'Programma verzonden',
		'program_partially_failed'                        => 'Programma niet volledig verzonden',
		'tournament_change_notification_sent'             => 'Wijzigingsmail verzonden',
		'tournament_change_notification_partially_failed' => 'Wijzigingsmail niet volledig verzonden',
	];

	/** Store one private activity with a small JSON context snapshot. */
	public static function record( int $post_id, string $action, int $actor_user_id = 0, array $context = [] ): int {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, [ TournamentService::TOURNAMENT_POST_TYPE, TournamentService::ENTRY_POST_TYPE ], true ) ) {
			return 0;
		}

		$actor      = $actor_user_id > 0 ? get_userdata( $actor_user_id ) : false;
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $post_id,
				'comment_content'  => self::LABELS[ $action ] ?? sanitize_text_field( $action ),
				'comment_type'     => self::COMMENT_TYPE,
				'comment_approved' => 1,
				'user_id'          => $actor_user_id,
				'comment_author'   => $actor ? $actor->display_name : 'Rondo',
				'comment_date'     => current_time( 'mysql' ),
			]
		);

		if ( ! $comment_id ) {
			return 0;
		}

		add_comment_meta( $comment_id, 'action', sanitize_key( $action ), true );
		add_comment_meta( $comment_id, 'context', wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), true );
		return (int) $comment_id;
	}

	/** Return recent activity for a tournament and all of its current entries. */
	public static function recent( int $tournament_id, int $limit = 100 ): array {
		$post_ids  = [ $tournament_id ];
		$entry_ids = get_posts(
			[
				'post_type'        => TournamentService::ENTRY_POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'   => 'tournament_id',
						'value' => $tournament_id,
					],
				],
			]
		);
		$post_ids  = array_merge( $post_ids, array_map( 'intval', $entry_ids ) );
		$comments  = get_comments(
			[
				'post__in' => $post_ids,
				'type'     => self::COMMENT_TYPE,
				'status'   => 'approve',
				'number'   => max( 1, min( 250, $limit ) ),
				'orderby'  => 'comment_date_gmt',
				'order'    => 'DESC',
			]
		);

		return array_map(
			static function ( \WP_Comment $comment ): array {
				$action  = (string) get_comment_meta( $comment->comment_ID, 'action', true );
				$context = json_decode( (string) get_comment_meta( $comment->comment_ID, 'context', true ), true );
				$entry   = get_post_type( (int) $comment->comment_post_ID ) === TournamentService::ENTRY_POST_TYPE;
				return [
					'id'         => (int) $comment->comment_ID,
					'action'     => $action,
					'label'      => self::LABELS[ $action ] ?? $comment->comment_content,
					'actor_name' => $comment->comment_author ?: 'Rondo',
					'created_at' => mysql_to_rfc3339( $comment->comment_date ),
					'entry_id'   => $entry ? (int) $comment->comment_post_ID : null,
					'team_name'  => $entry ? (string) Fields::get_for_post( (int) $comment->comment_post_ID, 'team_name_snapshot' ) : '',
					'context'    => is_array( $context ) ? $context : [],
				];
			},
			$comments
		);
	}
}
