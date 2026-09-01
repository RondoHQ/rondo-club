<?php
/**
 * Tournament payment emails and reminders.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Fields\Fields;
use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentPaymentEmail {

	/** Send the payment link once to each assigned staff member. */
	public static function send_initial( int $entry_id ): array|\WP_Error {
		return self::send( $entry_id, 'initial' );
	}

	/** Send one configured automatic reminder moment. */
	public static function send_automatic_reminder( int $entry_id, int $days_before ): array|\WP_Error {
		$moment_key = '_tournament_payment_reminder_' . $days_before . '_sent_at';
		if ( get_post_meta( $entry_id, $moment_key, true ) ) {
			return [
				'existing'   => true,
				'sent_count' => 0,
			];
		}
		return self::send( $entry_id, 'automatic', $days_before, $moment_key );
	}

	/** Send a manual reminder; managers may repeat this when needed. */
	public static function send_manual_reminder( int $entry_id ): array|\WP_Error {
		$result = self::send( $entry_id, 'manual' );
		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $entry_id, '_tournament_payment_manual_reminder_sent_at', current_time( 'mysql' ) );
			update_post_meta( $entry_id, '_tournament_payment_manual_reminder_count', (int) get_post_meta( $entry_id, '_tournament_payment_manual_reminder_count', true ) + 1 );
		}
		return $result;
	}

	private static function send( int $entry_id, string $kind, int $days_before = 0, string $moment_key = '' ): array|\WP_Error {
		$entry = get_post( $entry_id );
		if ( ! $entry || $entry->post_type !== TournamentService::ENTRY_POST_TYPE || $entry->post_status === 'trash' ) {
			return new \WP_Error( 'rondo_tournament_entry_not_found', __( 'Inschrijfopdracht niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		$fields = Fields::all_for_post( $entry_id );
		if ( (string) ( $fields['registration_status'] ?? '' ) !== 'submitted' ) {
			return new \WP_Error( 'rondo_tournament_entry_not_submitted', __( 'Alleen een definitieve inschrijving kan een betaalmail krijgen.', 'rondo' ), [ 'status' => 409 ] );
		}
		$invoice_id  = (int) ( $fields['invoice_id'] ?? 0 );
		$payment_url = $invoice_id > 0 ? (string) Fields::get_for_post( $invoice_id, 'payment_link' ) : '';
		if ( $invoice_id <= 0 || get_post_status( $invoice_id ) === 'rondo_paid' || Fields::get_for_post( $invoice_id, 'status' ) === 'paid' || $payment_url === '' ) {
			return new \WP_Error( 'rondo_tournament_payment_not_open', __( 'Deze inschrijving heeft geen open betaallink.', 'rondo' ), [ 'status' => 409 ] );
		}

		$recipients = array_values(
			array_filter(
				$fields['assignment_snapshot'] ?? [],
				static fn( $assignee ): bool => is_array( $assignee ) && is_email( sanitize_email( (string) ( $assignee['email'] ?? '' ) ) )
			)
		);
		if ( empty( $recipients ) ) {
			return new \WP_Error( 'rondo_tournament_payment_recipient_missing', __( 'Er is geen geldig e-mailadres voor het toegewezen kader.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( $moment_key !== '' ) {
			update_post_meta( $entry_id, $moment_key, current_time( 'mysql' ) );
		}

		$tournament_id     = (int) ( $fields['tournament_id'] ?? 0 );
		$tournament        = get_post( $tournament_id );
		$tournament_fields = Fields::all_for_post( $tournament_id );
		$tournament_name   = $tournament ? EmailTemplate::decode_title( $tournament->post_title ) : __( 'Toernooi', 'rondo' );
		$team_name         = (string) ( $fields['team_name_snapshot'] ?? '' );
		$deadline          = (string) ( $tournament_fields['payment_deadline'] ?? $tournament_fields['internal_deadline'] ?? '' );
		$deadline_label    = $deadline !== '' ? wp_date( 'j F Y', strtotime( $deadline ) ) : '';
		$total_label       = '€ ' . number_format_i18n( (float) ( $fields['total_amount'] ?? 0 ), 2 );
		$team_count        = (int) ( $fields['registered_team_count'] ?? 0 );
		$player_count      = (int) ( $fields['player_count'] ?? 0 );
		$is_reminder       = $kind !== 'initial';
		$subject           = $is_reminder
			? sprintf( '%s: herinnering voor betaling van %s', $tournament_name, $team_name )
			: sprintf( '%s: betaling voor %s', $tournament_name, $team_name );
		$results           = [];

		foreach ( $recipients as $assignee ) {
			$user_id  = (int) ( $assignee['user_id'] ?? 0 );
			$email    = sanitize_email( (string) $assignee['email'] );
			$sent_key = '_tournament_payment_email_sent_' . $user_id;
			if ( $kind === 'initial' && get_post_meta( $entry_id, $sent_key, true ) ) {
				$results[] = [
					'user_id'  => $user_id,
					'sent'     => true,
					'existing' => true,
				];
				continue;
			}
			if ( $kind === 'initial' ) {
				update_post_meta( $entry_id, $sent_key, current_time( 'mysql' ) );
			}
			$body      = sprintf(
				'<p>Hallo %s,</p><p>%s voor <strong>%s</strong> bij <strong>%s</strong>.</p><ul><li>%d %s</li><li>%d spelers</li><li>Totaal: <strong>%s</strong></li><li>Betaal uiterlijk: <strong>%s</strong></li></ul>',
				esc_html( (string) ( $assignee['name'] ?? '' ) ),
				$is_reminder ? 'Dit is een herinnering voor de openstaande betaling' : 'De inschrijving is bevestigd. Rond nu de betaling af',
				esc_html( $team_name ),
				esc_html( $tournament_name ),
				$team_count,
				_n( 'team', 'teams', $team_count, 'rondo' ),
				$player_count,
				esc_html( $total_label ),
				esc_html( $deadline_label )
			);
			$html      = EmailTemplate::render(
				[
					'preheader' => $is_reminder ? 'Herinnering voor een openstaande toernooibetaling.' : 'De betaallink voor de bevestigde toernooi-inschrijving.',
					'eyebrow'   => $is_reminder ? 'Betaalherinnering' : 'Toernooi-inschrijving',
					'heading'   => $is_reminder ? 'Betaling nog open' : 'Inschrijving bevestigd',
					'body_html' => $body,
					'cta_url'   => $payment_url,
					'cta_label' => 'Open betaallink',
				]
			);
			$sent      = (bool) wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
			$results[] = [
				'user_id' => $user_id,
				'sent'    => $sent,
			];
		}

		$sent_count = count( array_filter( $results, static fn( array $row ): bool => $row['sent'] ) );
		if ( $sent_count < count( $results ) ) {
			update_post_meta( $entry_id, '_tournament_payment_email_error', current_time( 'mysql' ) );
			return new \WP_Error( 'rondo_tournament_payment_email_failed', __( 'De betaalmail kon niet aan alle kaderleden worden verstuurd.', 'rondo' ), [ 'status' => 502 ] );
		}
		delete_post_meta( $entry_id, '_tournament_payment_email_error' );
		return [
			'days_before' => $days_before,
			'sent_count'  => $sent_count,
			'recipients'  => $results,
		];
	}
}
