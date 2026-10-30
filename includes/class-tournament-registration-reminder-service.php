<?php
/**
 * Reviewed, manual reminders for outstanding tournament registrations.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Fields\Fields;
use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentRegistrationReminderService {

	/** Bind the reviewed audience and message to this manager for thirty minutes. */
	public function preview( int $tournament_id, array $payload, int $actor_user_id ) {
		$snapshot = $this->snapshot( $tournament_id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$subject = sanitize_text_field( $payload['subject'] ?? 'Herinnering inschrijving: ' . $snapshot['name'] );
		$message = sanitize_textarea_field( $payload['message'] ?? 'Wil je de inschrijving van je team voor dit toernooi afronden? Gebruik de link hieronder om de gegevens te controleren en definitief in te schrijven.' );
		if ( $subject === '' || $message === '' || mb_strlen( $subject ) > 200 || mb_strlen( $message ) > 10000 ) {
			return new \WP_Error( 'rondo_reminder_message_invalid', 'Vul een onderwerp (maximaal 200 tekens) en bericht (maximaal 10.000 tekens) in.', [ 'status' => 400 ] );
		}
		$token = bin2hex( random_bytes( 32 ) );
		$data  = [
			'tournament_id' => $tournament_id,
			'actor_id'      => $actor_user_id,
			'snapshot'      => $snapshot,
			'subject'       => $subject,
			'message'       => $message,
		];
		if ( ! set_transient( 'rondo_registration_reminder_' . $token, $data, 30 * MINUTE_IN_SECONDS ) ) {
			return new \WP_Error( 'rondo_reminder_preview_failed', 'De controle kon niet worden bewaard. Probeer het opnieuw.', [ 'status' => 500 ] );
		}
		return array_merge(
			$snapshot,
			[
				'token'   => $token,
				'subject' => $subject,
				'message' => $message,
			]
			);
	}

	/** One token can dispatch at most once, including after a lost HTTP response. */
	public function send( int $tournament_id, string $token, int $actor_user_id ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return $this->stale();
		}
		return ( new TournamentService() )->with_tournament_lock(
			$tournament_id,
			function () use ( $tournament_id, $token, $actor_user_id ) {
				$key     = '_registration_reminder_' . hash( 'sha256', $token );
				$receipt = get_post_meta( $tournament_id, $key, true );
				if ( $receipt ) {
					if ( (int) $receipt['actor_id'] !== $actor_user_id ) {
						return $this->stale();
					}
					return $receipt['result'] ?? new \WP_Error( 'rondo_reminder_delivery_uncertain', 'Deze verzending loopt nog of is onderbroken. Controleer de verzendstatus voordat je een nieuwe herinnering maakt.', [ 'status' => 409 ] );
				}
				$data = get_transient( 'rondo_registration_reminder_' . $token );
				if ( ! is_array( $data ) || $data['tournament_id'] !== $tournament_id || $data['actor_id'] !== $actor_user_id ) {
					return $this->stale();
				}
				$current = $this->snapshot( $tournament_id );
				if ( is_wp_error( $current ) ) {
					return $current;
				}
				if ( $current !== $data['snapshot'] ) {
					return $this->stale();
				}
				if ( empty( $current['recipients'] ) ) {
					return new \WP_Error( 'rondo_reminder_no_recipients', 'Er zijn geen bereikbare ontvangers voor een herinnering.', [ 'status' => 400 ] );
				}
				$receipt = [
					'actor_id'   => $actor_user_id,
					'started_at' => current_time( 'mysql' ),
				];
				if ( ! add_post_meta( $tournament_id, $key, $receipt, true ) ) {
					return new \WP_Error( 'rondo_reminder_receipt_failed', 'De verzending kon niet veilig worden vastgelegd. Er is niets verstuurd.', [ 'status' => 500 ] );
				}
				$result = [
					'sent_count'      => 0,
					'failed_count'    => 0,
					'recipient_count' => count( $current['recipients'] ),
				];
				foreach ( $current['recipients'] as $recipient ) {
					$html  = '<p>Beste ' . esc_html( $recipient['name'] ) . ',</p>' . wpautop( esc_html( $data['message'] ) );
					$html .= '<p><strong>' . esc_html( $current['name'] ) . '</strong><br>Inschrijven vóór ' . esc_html( wp_date( 'j F Y H:i', ( new \DateTimeImmutable( $current['deadline'], wp_timezone() ) )->getTimestamp() ) ) . '</p><ul>';
					foreach ( $recipient['teams'] as $team ) {
						$html .= '<li><a href="' . esc_url( home_url( '/mijn-toernooien/' . $team['id'] ) ) . '">' . esc_html( $team['name'] ) . ': inschrijving afronden</a></li>';
					}
					$html .= '</ul><p>Log in met je Rondo-account. Heb je nog geen account? <a href="' . esc_url( home_url( '/activeren' ) ) . '">Maak een Rondo-account aan</a> met het e-mailadres waarop je deze herinnering ontvangt.</p>';
					$sent  = wp_mail(
						$recipient['email'],
						$data['subject'],
						EmailTemplate::render(
						[
							'heading'   => $data['subject'],
							'body_html' => $html,
						]
						),
						[ 'Content-Type: text/html; charset=UTF-8' ]
						);
					++$result[ $sent ? 'sent_count' : 'failed_count' ];
				}
				$receipt['result'] = $result;
				update_post_meta( $tournament_id, $key, $receipt );
				TournamentActivityLog::record( $tournament_id, $result['failed_count'] ? 'registration_reminder_partially_failed' : 'registration_reminder_sent', $actor_user_id, $result );
				return $result;
			}
		);
	}

	/** Read only open root entries; never create payments while building an audience. */
	private function snapshot( int $tournament_id ) {
		$post = get_post( $tournament_id );
		if ( ! $post || $post->post_type !== TournamentService::TOURNAMENT_POST_TYPE || $post->post_status !== 'publish' ) {
			return new \WP_Error( 'rondo_tournament_not_found', 'Toernooi niet gevonden.', [ 'status' => 404 ] );
		}
		$deadline = (string) Fields::get_for_post( $tournament_id, 'internal_deadline' );
		$open     = false;
		if ( $deadline !== '' ) {
			try {
				$open = current_datetime() < new \DateTimeImmutable( $deadline, wp_timezone() );
			} catch ( \Exception $error ) {
				$open = false;
			}
		}
		if ( Fields::get_for_post( $tournament_id, 'lifecycle_status' ) !== 'open' || ! $open ) {
			return new \WP_Error( 'rondo_reminder_registration_closed', 'Herinneringen zijn alleen mogelijk voor een open toernooi vóór de interne inschrijfdeadline.', [ 'status' => 409 ] );
		}
		$ids        = get_posts(
			[
				'post_type'        => TournamentService::ENTRY_POST_TYPE,
				'post_status'      => 'publish',
				'post_parent'      => 0,
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'   => 'tournament_id',
						'value' => $tournament_id,
					],
					[
						'key'   => 'registration_status',
						'value' => 'open',
					],
				],
			]
		);
		$teams      = [];
		$recipients = [];
		$warnings   = [];
		foreach ( $ids as $id ) {
			$team      = [
				'id'   => (int) $id,
				'name' => (string) Fields::get_for_post( $id, 'team_name_snapshot' ),
			];
			$teams[]   = $team;
			$assignees = TournamentAssignees::for_entry( (int) $id );
			if ( empty( $assignees ) ) {
				$warnings[] = $team['name'] . ': geen kaderleden toegewezen.';
			}
			foreach ( $assignees as $assignee ) {
				$email = strtolower( sanitize_email( $assignee['email'] ?? '' ) );
				if ( ! is_email( $email ) ) {
					$warnings[] = $team['name'] . ': ' . ( $assignee['name'] ?? 'Kaderlid' ) . ' heeft geen geldig e-mailadres.';
					continue;
				}
				$recipients[ $email ]                   ??= [
					'email'          => $email,
					'name'           => $assignee['name'] ?? '',
					'teams'          => [],
					'assignment_ids' => [],
				];
				$recipients[ $email ]['teams'][ $id ]     = $team;
				$recipients[ $email ]['assignment_ids'][] = [ (int) ( $assignee['person_id'] ?? 0 ), (int) ( $assignee['user_id'] ?? 0 ) ];
			}
		}
		ksort( $recipients );
		foreach ( $recipients as &$recipient ) {
			$recipient['teams'] = array_values( $recipient['teams'] );
		}
		unset( $recipient );
		return [
			'name'       => $post->post_title,
			'deadline'   => $deadline,
			'teams'      => $teams,
			'recipients' => array_values( $recipients ),
			'warnings'   => $warnings,
		];
	}

	private function stale(): \WP_Error {
		return new \WP_Error( 'rondo_reminder_preview_stale', 'De controle is verlopen of de inschrijvingen of ontvangers zijn gewijzigd. Controleer de ontvangers opnieuw voordat je verstuurt.', [ 'status' => 409 ] );
	}
}
