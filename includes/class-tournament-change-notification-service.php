<?php
/**
 * Recipient preview and delivery for published tournament changes.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Fields\Fields;
use Rondo\Notifications\EmailTemplate;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentChangeNotificationService {

	private const FIELD_LABELS = [
		'name'                  => 'Naam',
		'organizer'             => 'Organisator',
		'location'              => 'Algemene locatie',
		'description'           => 'Uitnodiging en praktische informatie',
		'internal_deadline'     => 'Interne deadline',
		'payment_deadline'      => 'Betaaldeadline',
		'external_deadline'     => 'Deadline organisatie',
		'payment_reminder_days' => 'Betaalherinneringen',
		'pricing_rules'         => 'Tarieven en spelvormen',
		'schedule'              => 'Datum, tijd en locatie',
	];

	/** Return all assigned staff plus contacts of definitive registrations. */
	public function recipients( int $tournament_id ): array {
		$entry_ids = get_posts(
			[
				'post_type'        => TournamentService::ENTRY_POST_TYPE,
				'post_status'      => 'publish',
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

		$unique            = [];
		$invalid           = [];
		$valid_source_rows = 0;
		$submitted_entries = 0;
		foreach ( $entry_ids as $entry_id ) {
			$fields    = Fields::all_for_post( (int) $entry_id );
			$team_name = (string) ( $fields['team_name_snapshot'] ?? '' );
			foreach ( $fields['assignment_snapshot'] ?? [] as $assignee ) {
				$user_id = (int) ( $assignee['user_id'] ?? 0 );
				$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
				$email   = $user ? sanitize_email( (string) ( UserProvisioning::contact_email( $user_id ) ?? '' ) ) : '';
				$this->add_recipient(
					$unique,
					$invalid,
					$valid_source_rows,
					$email,
					(string) ( $assignee['name'] ?? ( $user ? $user->display_name : '' ) ),
					$team_name,
					'Kaderlid'
				);
			}

			if ( (string) ( $fields['registration_status'] ?? '' ) !== 'submitted' ) {
				continue;
			}
			++$submitted_entries;
			$this->add_recipient(
				$unique,
				$invalid,
				$valid_source_rows,
				sanitize_email( (string) ( $fields['contact_email'] ?? '' ) ),
				(string) ( $fields['contact_name'] ?? '' ),
				$team_name,
				'Contactpersoon'
			);
		}

		return [
			'recipients'            => array_values( $unique ),
			'invalid'               => $invalid,
			'recipient_count'       => count( $unique ),
			'invalid_count'         => count( $invalid ),
			'deduplicated_count'    => max( 0, $valid_source_rows - count( $unique ) ),
			'submitted_entry_count' => $submitted_entries,
		];
	}

	/** Return one planner-safe preview for a saved published change. */
	public function preview( int $tournament_id, int $activity_id ) {
		$change = $this->change( $tournament_id, $activity_id );
		if ( is_wp_error( $change ) ) {
			return $change;
		}
		return [
			'activity_id' => $activity_id,
			'changes'     => $this->summaries( $change['changes'] ),
			'preview'     => $this->recipients( $tournament_id ),
			'sent_at'     => (string) get_comment_meta( $activity_id, 'notification_sent_at', true ),
		];
	}

	/** Send one saved change at most once. */
	public function send( int $tournament_id, int $activity_id, array $payload, int $actor_user_id ) {
		$tournament = get_post( $tournament_id );
		if ( ! $tournament || $tournament->post_type !== TournamentService::TOURNAMENT_POST_TYPE || $tournament->post_status === 'trash' ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		$lifecycle_status = (string) Fields::get_for_post( $tournament_id, 'lifecycle_status' );
		if ( ! in_array( $lifecycle_status, [ 'open', 'closed' ], true ) ) {
			return new \WP_Error( 'rondo_tournament_change_notification_locked', __( 'Alleen voor een open of gesloten toernooi kan een wijzigingsmail worden verstuurd.', 'rondo' ), [ 'status' => 409 ] );
		}

		$change = $this->change( $tournament_id, $activity_id );
		if ( is_wp_error( $change ) ) {
			return $change;
		}
		if ( get_comment_meta( $activity_id, 'notification_sent_at', true ) ) {
			return new \WP_Error( 'rondo_tournament_change_notification_sent', __( 'Voor deze wijziging is al een wijzigingsmail verstuurd.', 'rondo' ), [ 'status' => 409 ] );
		}

		$preview = $this->recipients( $tournament_id );
		if ( $preview['recipient_count'] === 0 ) {
			return new \WP_Error( 'rondo_tournament_change_recipients_missing', __( 'Er zijn geen geldige ontvangers voor deze wijzigingsmail.', 'rondo' ), [ 'status' => 409 ] );
		}

		$subject = sanitize_text_field( (string) ( $payload['subject'] ?? '' ) );
		if ( $subject === '' ) {
			$subject = sprintf( 'Wijziging %s', get_the_title( $tournament_id ) );
		}
		$message = wp_kses_post( (string) ( $payload['message'] ?? '' ) );
		$summary = $this->summaries( $change['changes'] );
		$items   = '';
		foreach ( $summary as $row ) {
			$items .= sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $row['label'] ), nl2br( esc_html( $row['after'] ) ) );
		}
		$body = '<p>Hallo,</p>';
		if ( trim( wp_strip_all_tags( $message ) ) !== '' ) {
			$body .= wpautop( $message );
		}
		$body .= '<p>De volgende toernooi-informatie is gewijzigd:</p><ul>' . $items . '</ul>';

		$results = [];
		foreach ( $preview['recipients'] as $recipient ) {
			$html      = EmailTemplate::render(
				[
					'preheader' => 'De informatie voor ' . get_the_title( $tournament_id ) . ' is gewijzigd.',
					'eyebrow'   => 'Wijziging toernooi',
					'heading'   => get_the_title( $tournament_id ),
					'body_html' => $body,
					'cta_url'   => home_url( '/mijn-toernooien' ),
					'cta_label' => 'Open Mijn toernooien',
				]
			);
			$sent      = (bool) wp_mail( $recipient['email'], $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
			$results[] = [
				'email'   => $recipient['email'],
				'sent'    => $sent,
				'teams'   => $recipient['teams'],
				'sources' => $recipient['sources'],
			];
		}

		$sent_at      = current_datetime()->format( 'Y-m-d H:i:s' );
		$sent_count   = count( array_filter( $results, static fn( array $row ): bool => $row['sent'] ) );
		$failed_count = count( $results ) - $sent_count;
		$snapshot     = [
			'activity_id'   => $activity_id,
			'actor_user_id' => $actor_user_id,
			'subject'       => $subject,
			'message'       => $message,
			'sent_at'       => $sent_at,
			'sent_count'    => $sent_count,
			'failed_count'  => $failed_count,
			'results'       => $results,
		];
		update_comment_meta( $activity_id, 'notification_sent_at', $sent_at );
		update_comment_meta( $activity_id, 'notification_snapshot', $snapshot );
		TournamentActivityLog::record(
			$tournament_id,
			$failed_count > 0 ? 'tournament_change_notification_partially_failed' : 'tournament_change_notification_sent',
			$actor_user_id,
			[
				'change_activity_id' => $activity_id,
				'sent_count'         => $sent_count,
				'failed_count'       => $failed_count,
			]
		);

		return [
			'sent_at'      => $sent_at,
			'sent_count'   => $sent_count,
			'failed_count' => $failed_count,
			'preview'      => $preview,
		];
	}

	private function change( int $tournament_id, int $activity_id ) {
		$comment = get_comment( $activity_id );
		$action  = $comment ? (string) get_comment_meta( $activity_id, 'action', true ) : '';
		if ( ! $comment || (int) $comment->comment_post_ID !== $tournament_id || $comment->comment_type !== TournamentActivityLog::COMMENT_TYPE || $action !== 'tournament_published_updated' ) {
			return new \WP_Error( 'rondo_tournament_change_invalid', __( 'Deze opgeslagen toernooiwijziging bestaat niet.', 'rondo' ), [ 'status' => 404 ] );
		}
		$context = json_decode( (string) get_comment_meta( $activity_id, 'context', true ), true );
		if ( ! is_array( $context['changes'] ?? null ) || empty( $context['changes'] ) ) {
			return new \WP_Error( 'rondo_tournament_change_empty', __( 'Deze wijziging bevat geen informatie om te versturen.', 'rondo' ), [ 'status' => 409 ] );
		}
		return [ 'changes' => $context['changes'] ];
	}

	private function summaries( array $changes ): array {
		$rows = [];
		foreach ( self::FIELD_LABELS as $field => $label ) {
			if ( ! isset( $changes[ $field ] ) ) {
				continue;
			}
			$rows[] = [
				'field'  => $field,
				'label'  => $label,
				'before' => $this->display_value( $field, $changes[ $field ]['before'] ?? '' ),
				'after'  => $this->display_value( $field, $changes[ $field ]['after'] ?? '' ),
			];
		}
		return $rows;
	}

	private function display_value( string $field, $value ): string {
		if ( in_array( $field, [ 'internal_deadline', 'payment_deadline', 'external_deadline' ], true ) ) {
			return $value ? wp_date( 'j F Y', strtotime( (string) $value ) ) : 'Niet ingesteld';
		}
		if ( $field === 'description' ) {
			return trim( wp_strip_all_tags( (string) $value ) ) ?: 'Geen informatie';
		}
		if ( $field === 'payment_reminder_days' ) {
			return empty( $value ) ? 'Geen' : implode( ', ', array_map( 'intval', (array) $value ) ) . ' dagen vooraf';
		}
		if ( $field === 'pricing_rules' ) {
			$lines = array_map(
				static fn( array $row ): string => sprintf( 'O%d t/m O%d: € %s per team%s', (int) $row['min_age'], (int) $row['max_age'], number_format_i18n( (float) $row['amount'], 2 ), empty( $row['game_format'] ) ? '' : ' · ' . $row['game_format'] ),
				(array) $value
			);
			return implode( "\n", $lines );
		}
		if ( $field === 'schedule' ) {
			$lines = array_map(
				static function ( array $row ): string {
					$timestamp = strtotime( (string) ( $row['start_datetime'] ?? '' ) );
					$date      = $timestamp ? wp_date( 'j F Y H:i', $timestamp ) : (string) ( $row['start_datetime'] ?? '' );
					return trim( (string) ( $row['age_group'] ?? '' ) . ': ' . $date . ( empty( $row['location'] ) ? '' : ' · ' . $row['location'] ) );
				},
				(array) $value
			);
			return implode( "\n", $lines );
		}
		return trim( wp_strip_all_tags( (string) $value ) ) ?: 'Niet ingesteld';
	}

	private function add_recipient( array &$unique, array &$invalid, int &$valid_source_rows, string $email, string $name, string $team_name, string $source ): void {
		if ( ! is_email( $email ) ) {
			$invalid[] = [
				'name'   => $name ?: 'Onbekende ontvanger',
				'team'   => $team_name,
				'source' => $source,
				'reason' => $email === '' ? 'E-mailadres ontbreekt' : 'E-mailadres is ongeldig',
			];
			return;
		}
		++$valid_source_rows;
		$key = strtolower( $email );
		if ( ! isset( $unique[ $key ] ) ) {
			$unique[ $key ] = [
				'email'   => $email,
				'names'   => [],
				'teams'   => [],
				'sources' => [],
			];
		}
		if ( $name !== '' && ! in_array( $name, $unique[ $key ]['names'], true ) ) {
			$unique[ $key ]['names'][] = $name;
		}
		if ( $team_name !== '' && ! in_array( $team_name, $unique[ $key ]['teams'], true ) ) {
			$unique[ $key ]['teams'][] = $team_name;
		}
		if ( ! in_array( $source, $unique[ $key ]['sources'], true ) ) {
			$unique[ $key ]['sources'][] = $source;
		}
	}
}
