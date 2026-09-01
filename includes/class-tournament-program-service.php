<?php
/**
 * Tournament program storage, recipient preview and delivery.
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

final class TournamentProgramService {

	/** Upload one PDF program to the WordPress media library. */
	public function upload( int $tournament_id, array $file ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'rondo_tournament_program_file_missing', __( 'Selecteer een programmabestand.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( (int) ( $file['size'] ?? 0 ) > 10 * MB_IN_BYTES ) {
			return new \WP_Error( 'rondo_tournament_program_file_too_large', __( 'Het programmabestand mag maximaal 10 MB zijn.', 'rondo' ), [ 'status' => 400 ] );
		}
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], [ 'pdf' => 'application/pdf' ] );
		if ( ( $checked['type'] ?? '' ) !== 'application/pdf' || ( $checked['ext'] ?? '' ) !== 'pdf' ) {
			return new \WP_Error( 'rondo_tournament_program_file_invalid', __( 'Alleen een geldig PDF-bestand is toegestaan.', 'rondo' ), [ 'status' => 400 ] );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$file['name'] = sanitize_file_name( 'programma-' . get_the_title( $tournament_id ) . '.pdf' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Authenticated REST upload; route permission and REST nonce are checked by WordPress.
		$previous               = $_FILES['program_file'] ?? null;
		$_FILES['program_file'] = $file;
		$attachment_id          = media_handle_upload( 'program_file', $tournament_id );
		if ( $previous === null ) {
			unset( $_FILES['program_file'] );
		} else {
			$_FILES['program_file'] = $previous;
		}
		return $attachment_id;
	}

	/** Save the editable program link, file and message. */
	public function save( int $tournament_id, array $payload, int $actor_user_id, ?int $attachment_id = null ) {
		$tournament = get_post( $tournament_id );
		if ( ! $tournament || $tournament->post_type !== TournamentService::TOURNAMENT_POST_TYPE || $tournament->post_status === 'trash' ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( Fields::get_for_post( $tournament_id, 'lifecycle_status' ) === 'archived' ) {
			return new \WP_Error( 'rondo_tournament_archived', __( 'Een gearchiveerd toernooi is alleen-lezen.', 'rondo' ), [ 'status' => 409 ] );
		}

		$url = trim( (string) ( $payload['program_url'] ?? Fields::get_for_post( $tournament_id, 'program_url' ) ) );
		if ( $url !== '' && filter_var( $url, FILTER_VALIDATE_URL ) === false ) {
			return new \WP_Error( 'rondo_tournament_program_url_invalid', __( 'Vul een geldige programmalink in.', 'rondo' ), [ 'status' => 400 ] );
		}
		$current_attachment = (int) Fields::get_for_post( $tournament_id, 'program_attachment_id' );
		$next_attachment    = $attachment_id ?? $current_attachment;
		if ( $next_attachment > 0 && get_post_type( $next_attachment ) !== 'attachment' ) {
			return new \WP_Error( 'rondo_tournament_program_attachment_invalid', __( 'Het programmabestand bestaat niet meer.', 'rondo' ), [ 'status' => 400 ] );
		}

		$before = [
			'program_attachment_id' => $current_attachment,
			'program_message'       => (string) Fields::get_for_post( $tournament_id, 'program_message' ),
			'program_url'           => (string) Fields::get_for_post( $tournament_id, 'program_url' ),
		];
		$after  = [
			'program_attachment_id' => $next_attachment ?: null,
			'program_message'       => wp_kses_post( (string) ( $payload['program_message'] ?? $before['program_message'] ) ),
			'program_url'           => $url,
		];
		Fields::update_many_for_post( $tournament_id, $after );
		if ( maybe_serialize( $before ) !== maybe_serialize( $after ) ) {
			TournamentActivityLog::record( $tournament_id, 'program_saved', $actor_user_id );
		}
		return $this->state( $tournament_id );
	}

	/** Return the unique valid recipients plus operational invalid-address warnings. */
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
			$fields = Fields::all_for_post( (int) $entry_id );
			if ( (string) ( $fields['registration_status'] ?? '' ) !== 'submitted' ) {
				continue;
			}
			++$submitted_entries;
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

	/** Send the program once to each unique current recipient. */
	public function send( int $tournament_id, array $payload, int $actor_user_id ) {
		$state = $this->state( $tournament_id );
		if ( empty( $state ) ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $state['lifecycle_status'] === 'draft' ) {
			return new \WP_Error( 'rondo_tournament_not_published', __( 'Publiceer het toernooi voordat je het programma verstuurt.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( $state['lifecycle_status'] === 'archived' ) {
			return new \WP_Error( 'rondo_tournament_archived', __( 'Een gearchiveerd toernooi is alleen-lezen.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( $state['program_url'] === '' && ! $state['program_attachment_id'] ) {
			return new \WP_Error( 'rondo_tournament_program_required', __( 'Voeg eerst een programmabestand of programmalink toe.', 'rondo' ), [ 'status' => 400 ] );
		}

		$preview = $this->recipients( $tournament_id );
		if ( $preview['recipient_count'] === 0 ) {
			return new \WP_Error( 'rondo_tournament_program_recipients_missing', __( 'Er zijn geen geldige ontvangers van definitief ingeschreven teams.', 'rondo' ), [ 'status' => 409 ] );
		}

		$attachment = '';
		if ( $state['program_attachment_id'] ) {
			$attachment = (string) get_attached_file( $state['program_attachment_id'] );
			if ( $attachment === '' || ! is_readable( $attachment ) ) {
				return new \WP_Error( 'rondo_tournament_program_file_unavailable', __( 'Het programmabestand kan niet worden gelezen.', 'rondo' ), [ 'status' => 409 ] );
			}
		}

		$subject = sanitize_text_field( (string) ( $payload['subject'] ?? '' ) );
		if ( $subject === '' ) {
			$subject = sprintf( 'Programma %s', $state['name'] );
		}
		$message = wp_kses_post( $state['program_message'] );
		if ( trim( wp_strip_all_tags( $message ) ) === '' ) {
			$message = '<p>Het programma van het toernooi is beschikbaar.</p>';
		}

		$results = [];
		foreach ( $preview['recipients'] as $recipient ) {
			$html      = EmailTemplate::render(
				[
					'preheader' => 'Het programma voor ' . $state['name'] . ' is beschikbaar.',
					'eyebrow'   => 'Toernooiprogramma',
					'heading'   => $state['name'],
					'body_html' => '<p>Hallo,</p>' . wpautop( $message ),
					'cta_url'   => $state['program_url'],
					'cta_label' => $state['program_url'] !== '' ? 'Open programma' : '',
				]
			);
			$sent      = (bool) wp_mail(
				$recipient['email'],
				$subject,
				$html,
				[ 'Content-Type: text/html; charset=UTF-8' ],
				$attachment !== '' ? [ $attachment ] : []
			);
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
			'subject'               => $subject,
			'message'               => $message,
			'program_url'           => $state['program_url'],
			'program_attachment_id' => $state['program_attachment_id'],
			'sent_at'               => $sent_at,
			'actor_user_id'         => $actor_user_id,
			'sent_count'            => $sent_count,
			'failed_count'          => $failed_count,
			'results'               => $results,
		];
		update_post_meta( $tournament_id, '_tournament_program_last_send', $snapshot );
		Fields::update_for_post( $tournament_id, 'program_sent_at', $sent_at );
		TournamentActivityLog::record(
			$tournament_id,
			$failed_count > 0 ? 'program_partially_failed' : 'program_sent',
			$actor_user_id,
			[
				'sent_count'   => $sent_count,
				'failed_count' => $failed_count,
			]
		);

		return [
			'sent_at'      => $sent_at,
			'sent_count'   => $sent_count,
			'failed_count' => $failed_count,
			'preview'      => $preview,
		];
	}

	/** Return planner-safe program state and the latest result summary. */
	public function state( int $tournament_id ): array {
		$post = get_post( $tournament_id );
		if ( ! $post || $post->post_type !== TournamentService::TOURNAMENT_POST_TYPE || $post->post_status === 'trash' ) {
			return [];
		}
		$fields        = Fields::all_for_post( $tournament_id );
		$attachment_id = (int) ( $fields['program_attachment_id'] ?? 0 );
		$last_send     = get_post_meta( $tournament_id, '_tournament_program_last_send', true );
		return [
			'id'                      => $tournament_id,
			'name'                    => get_the_title( $tournament_id ),
			'lifecycle_status'        => (string) ( $fields['lifecycle_status'] ?? 'draft' ),
			'program_attachment_id'   => $attachment_id ?: null,
			'program_attachment_url'  => $attachment_id ? (string) wp_get_attachment_url( $attachment_id ) : '',
			'program_attachment_name' => $attachment_id ? (string) get_the_title( $attachment_id ) : '',
			'program_url'             => (string) ( $fields['program_url'] ?? '' ),
			'program_message'         => (string) ( $fields['program_message'] ?? '' ),
			'program_sent_at'         => (string) ( $fields['program_sent_at'] ?? '' ),
			'last_send'               => is_array( $last_send ) ? [
				'sent_at'      => (string) ( $last_send['sent_at'] ?? '' ),
				'subject'      => (string) ( $last_send['subject'] ?? '' ),
				'sent_count'   => (int) ( $last_send['sent_count'] ?? 0 ),
				'failed_count' => (int) ( $last_send['failed_count'] ?? 0 ),
			] : null,
		];
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
