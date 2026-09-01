<?php
/**
 * Tournament editions, assignments and team registrations.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use DateTimeImmutable;
use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentService {

	public const TOURNAMENT_POST_TYPE = 'rondo_tournament';
	public const ENTRY_POST_TYPE      = 'rondo_tourn_entry';

	private TournamentPaymentService $payments;

	public function __construct( ?TournamentPaymentService $payments = null ) {
		$this->payments = $payments ?? new TournamentPaymentService();
	}

	/** Return every tournament for a manager. */
	public function tournaments(): array {
		$ids = get_posts(
			[
				'post_type'        => self::TOURNAMENT_POST_TYPE,
				'post_status'      => [ 'draft', 'publish' ],
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			]
		);

		return array_map( fn( int $id ): array => $this->format_tournament( $id, true ), array_map( 'intval', $ids ) );
	}

	/** Create or update one draft tournament. */
	public function save_tournament( array $payload, int $actor_user_id, int $tournament_id = 0 ) {
		$is_new = $tournament_id <= 0;
		if ( $tournament_id > 0 && get_post_type( $tournament_id ) !== self::TOURNAMENT_POST_TYPE ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		if ( $tournament_id > 0 && Fields::get_for_post( $tournament_id, 'lifecycle_status' ) !== 'draft' ) {
			return new \WP_Error( 'rondo_tournament_not_draft', __( 'Een gepubliceerd toernooi kan in deze fase niet meer worden gewijzigd.', 'rondo' ), [ 'status' => 409 ] );
		}

		$name = sanitize_text_field( (string) ( $payload['name'] ?? '' ) );
		if ( $name === '' ) {
			return new \WP_Error( 'rondo_tournament_name_required', __( 'Geef het toernooi een naam.', 'rondo' ), [ 'status' => 400 ] );
		}

		$internal_deadline = $this->parse_datetime( $payload['internal_deadline'] ?? '', true );
		$external_deadline = $this->parse_datetime( $payload['external_deadline'] ?? '', true );
		if ( is_wp_error( $internal_deadline ) || is_wp_error( $external_deadline ) ) {
			return is_wp_error( $internal_deadline ) ? $internal_deadline : $external_deadline;
		}
		if ( $external_deadline <= $internal_deadline ) {
			return new \WP_Error( 'rondo_tournament_deadline_order', __( 'De externe deadline moet na de interne deadline liggen.', 'rondo' ), [ 'status' => 400 ] );
		}
		$payment_deadline = $this->parse_datetime( $payload['payment_deadline'] ?? $internal_deadline->format( DATE_RFC3339 ), true );
		if ( is_wp_error( $payment_deadline ) ) {
			return $payment_deadline;
		}
		if ( $payment_deadline > $external_deadline ) {
			return new \WP_Error( 'rondo_tournament_payment_deadline_order', __( 'De betaaldeadline mag niet na de deadline van de organisatie liggen.', 'rondo' ), [ 'status' => 400 ] );
		}
		$payment_reminder_days = $this->sanitize_payment_reminder_days( $payload['payment_reminder_days'] ?? [ 7, 2 ] );
		if ( is_wp_error( $payment_reminder_days ) ) {
			return $payment_reminder_days;
		}

		$pricing_rules = $this->sanitize_pricing_rules( $payload['pricing_rules'] ?? [] );
		if ( is_wp_error( $pricing_rules ) ) {
			return $pricing_rules;
		}
		$schedule = $this->sanitize_schedule( $payload['schedule'] ?? [] );
		if ( is_wp_error( $schedule ) ) {
			return $schedule;
		}

		$postarr = [
			'post_type'   => self::TOURNAMENT_POST_TYPE,
			'post_status' => 'draft',
			'post_title'  => $name,
			'post_author' => $actor_user_id,
		];
		if ( $tournament_id > 0 ) {
			$postarr['ID'] = $tournament_id;
		}

		$saved_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $saved_id ) ) {
			return $saved_id;
		}

		Fields::update_many_for_post(
			(int) $saved_id,
			[
				'created_by_user_id'    => $tournament_id > 0
					? (int) Fields::get_for_post( $tournament_id, 'created_by_user_id' )
					: $actor_user_id,
				'description'           => wp_kses_post( (string) ( $payload['description'] ?? '' ) ),
				'external_deadline'     => $external_deadline->format( 'Y-m-d H:i:s' ),
				'internal_deadline'     => $internal_deadline->format( 'Y-m-d H:i:s' ),
				'lifecycle_status'      => 'draft',
				'location'              => sanitize_text_field( (string) ( $payload['location'] ?? '' ) ),
				'organizer'             => sanitize_text_field( (string) ( $payload['organizer'] ?? '' ) ),
				'payment_deadline'      => $payment_deadline->format( 'Y-m-d H:i:s' ),
				'payment_reminder_days' => array_map( static fn( int $days ): array => [ 'days_before' => $days ], $payment_reminder_days ),
				'pricing_rules'         => $pricing_rules,
				'schedule'              => $schedule,
			]
		);
		TournamentActivityLog::record( (int) $saved_id, $is_new ? 'tournament_created' : 'tournament_updated', $actor_user_id );

		return $this->format_tournament( (int) $saved_id, true );
	}

	/** Format one tournament for the REST API. */
	public function format_tournament( int $tournament_id, bool $include_entries = false ): array {
		$post = get_post( $tournament_id );
		if ( ! $post || $post->post_type !== self::TOURNAMENT_POST_TYPE || $post->post_status === 'trash' ) {
			return [];
		}

		$fields = Fields::all_for_post( $tournament_id );
		$result = [
			'id'                         => $tournament_id,
			'name'                       => get_the_title( $tournament_id ),
			'organizer'                  => (string) ( $fields['organizer'] ?? '' ),
			'location'                   => (string) ( $fields['location'] ?? '' ),
			'description'                => (string) ( $fields['description'] ?? '' ),
			'internal_deadline'          => (string) ( $fields['internal_deadline'] ?? '' ),
			'external_deadline'          => (string) ( $fields['external_deadline'] ?? '' ),
			'external_status'            => (string) ( $fields['external_status'] ?? 'not_processed' ),
			'external_status_changed_at' => (string) ( $fields['external_status_changed_at'] ?? '' ),
			'payment_deadline'           => (string) ( $fields['payment_deadline'] ?? $fields['internal_deadline'] ?? '' ),
			'payment_reminder_days'      => $this->format_payment_reminder_days( $fields['payment_reminder_days'] ?? [] ),
			'pricing_rules'              => array_values( $fields['pricing_rules'] ?? [] ),
			'schedule'                   => array_values( $fields['schedule'] ?? [] ),
			'target_team_ids'            => array_values( array_map( 'intval', $fields['target_team_ids'] ?? [] ) ),
			'lifecycle_status'           => (string) ( $fields['lifecycle_status'] ?? 'draft' ),
			'published_at'               => (string) ( $fields['published_at'] ?? '' ),
			'published_by_user_id'       => (int) ( $fields['published_by_user_id'] ?? 0 ),
			'program_attachment_id'      => (int) ( $fields['program_attachment_id'] ?? 0 ) ?: null,
			'program_attachment_url'     => ! empty( $fields['program_attachment_id'] ) ? (string) wp_get_attachment_url( (int) $fields['program_attachment_id'] ) : '',
			'program_attachment_name'    => ! empty( $fields['program_attachment_id'] ) ? (string) get_the_title( (int) $fields['program_attachment_id'] ) : '',
			'program_url'                => (string) ( $fields['program_url'] ?? '' ),
			'program_message'            => (string) ( $fields['program_message'] ?? '' ),
			'program_sent_at'            => (string) ( $fields['program_sent_at'] ?? '' ),
			'can_manage'                 => TournamentAccess::can_manage(),
		];

		if ( $include_entries ) {
			$entries                         = $this->entries_for_tournament( $tournament_id );
			$totals                          = $this->totals( $entries );
			$result['entry_count']           = $totals['overall']['selected_team_count'];
			$result['submitted_entry_count'] = $totals['overall']['submitted_entry_count'];
			$result['registered_team_count'] = $totals['overall']['registered_team_count'];
			$result['player_count']          = $totals['overall']['player_count'];
			$result['receivable_amount']     = $totals['overall']['receivable_amount'];
			$result['received_amount']       = $totals['overall']['received_amount'];
			$result['outstanding_amount']    = $totals['overall']['outstanding_amount'];
			$result['open_payment_count']    = $totals['overall']['open_payment_count'];
			$result['totals']                = $totals;
			$result['activity']              = TournamentActivityLog::recent( $tournament_id );
			$last_send                       = get_post_meta( $tournament_id, '_tournament_program_last_send', true );
			$result['program_last_send']     = is_array( $last_send ) ? [
				'sent_at'      => (string) ( $last_send['sent_at'] ?? '' ),
				'subject'      => (string) ( $last_send['subject'] ?? '' ),
				'sent_count'   => (int) ( $last_send['sent_count'] ?? 0 ),
				'failed_count' => (int) ( $last_send['failed_count'] ?? 0 ),
			] : null;
		}

		return $result;
	}

	/** Calculate authoritative totals for the whole tournament and per age group. */
	public function totals( array $entries ): array {
		$empty   = [
			'selected_team_count'   => 0,
			'submitted_entry_count' => 0,
			'registered_team_count' => 0,
			'player_count'          => 0,
			'receivable_amount'     => 0.0,
			'received_amount'       => 0.0,
			'outstanding_amount'    => 0.0,
			'open_payment_count'    => 0,
		];
		$overall = $empty;
		$by_age  = [];
		foreach ( $entries as $entry ) {
			$age_group = (string) ( $entry['age_group'] ?? '' ) ?: 'Onbekend';
			if ( ! isset( $by_age[ $age_group ] ) ) {
				$by_age[ $age_group ] = [
					'age_group'  => $age_group,
					'age_number' => (int) ( $entry['age_number'] ?? 0 ),
				] + $empty;
			}
			++$overall['selected_team_count'];
			++$by_age[ $age_group ]['selected_team_count'];
			if ( ( $entry['registration_status'] ?? '' ) !== 'submitted' ) {
				continue;
			}
			++$overall['submitted_entry_count'];
			++$by_age[ $age_group ]['submitted_entry_count'];
			foreach ( [ 'registered_team_count', 'player_count' ] as $field ) {
				$overall[ $field ]              += (int) ( $entry[ $field ] ?? 0 );
				$by_age[ $age_group ][ $field ] += (int) ( $entry[ $field ] ?? 0 );
			}
			$amount                                     = (float) ( $entry['total_amount'] ?? 0 );
			$overall['receivable_amount']              += $amount;
			$by_age[ $age_group ]['receivable_amount'] += $amount;
			if ( ( $entry['payment_state'] ?? '' ) === 'paid' ) {
				$overall['received_amount']              += $amount;
				$by_age[ $age_group ]['received_amount'] += $amount;
			} elseif ( $amount > 0 ) {
				$overall['outstanding_amount']              += $amount;
				$by_age[ $age_group ]['outstanding_amount'] += $amount;
				++$overall['open_payment_count'];
				++$by_age[ $age_group ]['open_payment_count'];
			}
		}
		usort(
			$by_age,
			static fn( array $left, array $right ): int => $right['age_number'] <=> $left['age_number']
		);
		return [
			'overall'      => $overall,
			'by_age_group' => array_values( $by_age ),
		];
	}

	/** Return team and kader options for the publication review. */
	public function assignment_options(): array {
		$teams      = [];
		$candidates = $this->kader_candidates_by_team();
		$team_ids   = get_posts(
			[
				'post_type'        => 'team',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);

		foreach ( $team_ids as $team_id ) {
			$team_id   = (int) $team_id;
			$age_group = $this->age_group_for_team( $team_id );
			if ( $age_group === '' ) {
				continue;
			}
			$teams[] = [
				'id'         => $team_id,
				'name'       => html_entity_decode( get_the_title( $team_id ), ENT_QUOTES, 'UTF-8' ),
				'age_group'  => $age_group,
				'age_number' => $this->age_number( $age_group ),
				'assignees'  => array_values( $candidates[ $team_id ] ?? [] ),
			];
		}

		usort(
			$teams,
			static fn( array $left, array $right ): int => $left['age_number'] === $right['age_number']
				? strnatcasecmp( $left['name'], $right['name'] )
				: $right['age_number'] <=> $left['age_number']
		);

		return $teams;
	}

	/** Publish a tournament and create one shared entry per selected team. */
	public function publish( int $tournament_id, array $assignments, int $actor_user_id ) {
		$tournament = $this->format_tournament( $tournament_id, false );
		if ( empty( $tournament ) ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $tournament['lifecycle_status'] !== 'draft' ) {
			return new \WP_Error( 'rondo_tournament_already_published', __( 'Dit toernooi is al gepubliceerd.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( empty( $assignments ) ) {
			return new \WP_Error( 'rondo_tournament_assignments_required', __( 'Selecteer minimaal één team.', 'rondo' ), [ 'status' => 400 ] );
		}
		$payment_configuration = $this->payments->validate_configuration();
		if ( is_wp_error( $payment_configuration ) ) {
			return $payment_configuration;
		}

		$available_by_team = [];
		foreach ( $this->assignment_options() as $team ) {
			$available_by_team[ (int) $team['id'] ] = $team;
		}

		$prepared = [];
		foreach ( $assignments as $assignment ) {
			$team_id  = absint( $assignment['team_id'] ?? 0 );
			$user_ids = array_values( array_unique( array_filter( array_map( 'absint', $assignment['user_ids'] ?? [] ) ) ) );
			if ( ! isset( $available_by_team[ $team_id ] ) ) {
				return new \WP_Error( 'rondo_tournament_team_invalid', __( 'Een geselecteerd team bestaat niet meer.', 'rondo' ), [ 'status' => 400 ] );
			}
			$allowed_ids = array_map( static fn( array $candidate ): int => (int) $candidate['user_id'], $available_by_team[ $team_id ]['assignees'] );
			if ( empty( $user_ids ) || array_diff( $user_ids, $allowed_ids ) ) {
				/* translators: %s: team name. */
				$message = sprintf( __( 'Kies minimaal één actueel kaderlid voor %s.', 'rondo' ), $available_by_team[ $team_id ]['name'] );
				return new \WP_Error( 'rondo_tournament_assignees_invalid', $message, [ 'status' => 400 ] );
			}
			$prepared[ $team_id ] = [
				'team'     => $available_by_team[ $team_id ],
				'user_ids' => $user_ids,
			];
		}

		$entry_results = [];
		foreach ( $prepared as $team_id => $item ) {
			$entry = $this->create_entry( $tournament_id, $item['team'], $item['user_ids'], $actor_user_id );
			if ( is_wp_error( $entry ) ) {
				return $entry;
			}
			$entry_results[] = $entry;
		}

		Fields::update_many_for_post(
			$tournament_id,
			[
				'lifecycle_status'     => 'open',
				'published_at'         => current_datetime()->format( 'Y-m-d H:i:s' ),
				'published_by_user_id' => $actor_user_id,
				'target_team_ids'      => array_map( 'intval', array_keys( $prepared ) ),
			]
		);
		wp_update_post(
			[
				'ID'          => $tournament_id,
				'post_status' => 'publish',
			]
			);
		TournamentActivityLog::record(
			$tournament_id,
			'tournament_published',
			$actor_user_id,
			[ 'entry_count' => count( $entry_results ) ]
		);

		$email_results = [];
		foreach ( $entry_results as $entry ) {
			$email_results[ $entry['id'] ] = $this->send_assignment_emails( $entry, $tournament );
		}

		return [
			'tournament' => $this->format_tournament( $tournament_id, true ),
			'entries'    => $entry_results,
			'emails'     => $email_results,
		];
	}

	/** Extend the internal response deadline for an open tournament. */
	public function extend_deadline( int $tournament_id, $value, int $actor_user_id = 0 ) {
		$tournament = $this->format_tournament( $tournament_id, false );
		if ( empty( $tournament ) ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $tournament['lifecycle_status'] !== 'open' ) {
			return new \WP_Error( 'rondo_tournament_not_open', __( 'Alleen van een open toernooi kan de deadline worden verlengd.', 'rondo' ), [ 'status' => 409 ] );
		}

		$deadline = $this->parse_datetime( $value, true );
		if ( is_wp_error( $deadline ) ) {
			return $deadline;
		}
		$external = $this->parse_datetime( $tournament['external_deadline'] );
		if ( is_wp_error( $external ) || $deadline <= current_datetime() || $deadline >= $external ) {
			return new \WP_Error( 'rondo_tournament_deadline_invalid', __( 'Kies een toekomstige interne deadline vóór de deadline van de organisatie.', 'rondo' ), [ 'status' => 400 ] );
		}

		$before = $tournament['internal_deadline'];
		$after  = $deadline->format( 'Y-m-d H:i:s' );
		Fields::update_for_post( $tournament_id, 'internal_deadline', $after );
		TournamentActivityLog::record(
			$tournament_id,
			'deadline_changed',
			$actor_user_id,
			[
				'before' => $before,
				'after'  => $after,
			]
			);
		return $this->format_tournament( $tournament_id, true );
	}

	/** Update the one external-processing status for a published tournament. */
	public function update_external_status( int $tournament_id, string $status, int $actor_user_id ) {
		$tournament = $this->format_tournament( $tournament_id, false );
		if ( empty( $tournament ) ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $tournament['lifecycle_status'] === 'draft' ) {
			return new \WP_Error( 'rondo_tournament_not_published', __( 'Publiceer het toernooi voordat je de externe voortgang bijwerkt.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( $tournament['lifecycle_status'] === 'archived' ) {
			return new \WP_Error( 'rondo_tournament_archived', __( 'Een gearchiveerd toernooi is alleen-lezen.', 'rondo' ), [ 'status' => 409 ] );
		}
		$status = sanitize_key( $status );
		if ( ! in_array( $status, [ 'not_processed', 'submitted', 'confirmed' ], true ) ) {
			return new \WP_Error( 'rondo_tournament_external_status_invalid', __( 'Kies een geldige externe voortgang.', 'rondo' ), [ 'status' => 400 ] );
		}
		$before = $tournament['external_status'];
		if ( $before !== $status ) {
			$changed_at = current_datetime()->format( 'Y-m-d H:i:s' );
			Fields::update_many_for_post(
				$tournament_id,
				[
					'external_status'            => $status,
					'external_status_changed_at' => $changed_at,
				]
			);
			TournamentActivityLog::record(
				$tournament_id,
				'external_status_changed',
				$actor_user_id,
				[
					'before' => $before,
					'after'  => $status,
				]
				);
		}
		return $this->format_tournament( $tournament_id, true );
	}

	/** Close, reopen or archive a published tournament. */
	public function update_lifecycle_status( int $tournament_id, string $status, int $actor_user_id ) {
		$tournament = $this->format_tournament( $tournament_id, false );
		if ( empty( $tournament ) ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		$status = sanitize_key( $status );
		if ( ! in_array( $status, [ 'open', 'closed', 'archived' ], true ) || $tournament['lifecycle_status'] === 'draft' ) {
			return new \WP_Error( 'rondo_tournament_status_invalid', __( 'Kies een geldige toernooistatus.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( $tournament['lifecycle_status'] === 'archived' && $status !== 'archived' ) {
			return new \WP_Error( 'rondo_tournament_archived', __( 'Een gearchiveerd toernooi is alleen-lezen.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( $tournament['lifecycle_status'] !== $status ) {
			Fields::update_for_post( $tournament_id, 'lifecycle_status', $status );
			TournamentActivityLog::record(
				$tournament_id,
				'lifecycle_status_changed',
				$actor_user_id,
				[
					'before' => $tournament['lifecycle_status'],
					'after'  => $status,
				]
				);
		}
		return $this->format_tournament( $tournament_id, true );
	}

	/** Move a tournament and all linked entries to the WordPress trash. */
	public function delete_tournament( int $tournament_id ) {
		$post = get_post( $tournament_id );
		if ( ! $post || $post->post_type !== self::TOURNAMENT_POST_TYPE || $post->post_status === 'trash' ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		$entry_ids = array_map(
			'intval',
			get_posts(
				[
					'post_type'        => self::ENTRY_POST_TYPE,
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
			)
		);

		$trashed_entry_ids = [];
		foreach ( $entry_ids as $entry_id ) {
			if ( ! wp_trash_post( $entry_id ) ) {
				foreach ( array_reverse( $trashed_entry_ids ) as $trashed_entry_id ) {
					wp_untrash_post( $trashed_entry_id );
				}
				return new \WP_Error( 'rondo_tournament_delete_failed', __( 'Het toernooi kon niet volledig worden verwijderd.', 'rondo' ), [ 'status' => 500 ] );
			}
			$trashed_entry_ids[] = $entry_id;
		}

		if ( ! wp_trash_post( $tournament_id ) ) {
			foreach ( array_reverse( $trashed_entry_ids ) as $trashed_entry_id ) {
				wp_untrash_post( $trashed_entry_id );
			}
			return new \WP_Error( 'rondo_tournament_delete_failed', __( 'Het toernooi kon niet worden verwijderd.', 'rondo' ), [ 'status' => 500 ] );
		}
		foreach ( $entry_ids as $entry_id ) {
			$this->payments->cancel_unpaid_payment( $entry_id );
		}

		return [
			'deleted'     => true,
			'id'          => $tournament_id,
			'entry_count' => count( $entry_ids ),
		];
	}

	/** Return all entries for one tournament. */
	public function entries_for_tournament( int $tournament_id ): array {
		$ids = get_posts(
			[
				'post_type'        => self::ENTRY_POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'   => 'tournament_id',
						'value' => $tournament_id,
					],
				],
			]
		);

		$entries = array_map( fn( int $id ): array => $this->format_entry( $id ), array_map( 'intval', $ids ) );
		usort(
			$entries,
			static fn( array $left, array $right ): int => $left['age_number'] === $right['age_number']
				? strnatcasecmp( $left['team_name'], $right['team_name'] )
				: $right['age_number'] <=> $left['age_number']
		);
		return $entries;
	}

	/** Return entries assigned to one user. */
	public function entries_for_user( int $user_id ): array {
		$ids = get_posts(
			[
				'post_type'        => self::ENTRY_POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => '_tournament_assigned_user_' . $user_id,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		return array_map( fn( int $id ): array => $this->format_entry( $id ), array_map( 'intval', $ids ) );
	}

	/** Format one shared team entry. */
	public function format_entry( int $entry_id ): array {
		$post = get_post( $entry_id );
		if ( ! $post || $post->post_type !== self::ENTRY_POST_TYPE || $post->post_status === 'trash' ) {
			return [];
		}

		$fields        = Fields::all_for_post( $entry_id );
		$tournament_id = (int) ( $fields['tournament_id'] ?? 0 );
		$tournament    = $this->format_tournament( $tournament_id, false );
		$age_group     = (string) ( $fields['age_group_snapshot'] ?? '' );
		$status        = (string) ( $fields['registration_status'] ?? 'open' );
		$payment       = $this->payments->payment_summary( $entry_id, $fields );

		return array_merge(
			[
				'id'                     => $entry_id,
				'tournament_id'          => $tournament_id,
				'tournament'             => $tournament,
				'team_id'                => (int) ( $fields['team_id'] ?? 0 ),
				'team_name'              => (string) ( $fields['team_name_snapshot'] ?? '' ),
				'age_group'              => $age_group,
				'age_number'             => $this->age_number( $age_group ),
				'assigned_user_ids'      => array_values( array_map( static fn( array $row ): int => (int) ( $row['user_id'] ?? 0 ), $fields['assignment_snapshot'] ?? [] ) ),
				'assignees'              => array_values( $fields['assignment_snapshot'] ?? [] ),
				'registration_status'    => $status,
				'draft_team_entries'     => array_values( $fields['draft_team_entries'] ?? [] ),
				'submitted_team_entries' => array_values( $fields['submitted_team_entries'] ?? [] ),
				'contact_name'           => (string) ( $fields['contact_name'] ?? '' ),
				'contact_email'          => (string) ( $fields['contact_email'] ?? '' ),
				'contact_mobile'         => (string) ( $fields['contact_mobile'] ?? '' ),
				'registered_team_count'  => (int) ( $fields['registered_team_count'] ?? 0 ),
				'player_count'           => (int) ( $fields['player_count'] ?? 0 ),
				'price_per_team'         => (float) ( $fields['price_per_team'] ?? 0 ),
				'total_amount'           => (float) ( $fields['total_amount'] ?? 0 ),
				'last_payment_email_at'  => (string) ( $fields['last_payment_email_at'] ?? '' ),
				'payment_reminder_log'   => array_values( $fields['payment_reminder_log'] ?? [] ),
				'planner_note'           => (string) ( $fields['planner_note'] ?? '' ),
				'submitted_at'           => (string) ( $fields['submitted_at'] ?? '' ),
				'submitted_by_user_id'   => (int) ( $fields['submitted_by_user_id'] ?? 0 ),
				'version'                => max( 1, (int) ( $fields['version'] ?? 1 ) ),
				'can_edit'               => $status === 'open' && $this->deadline_is_open( $tournament ),
				'can_retry_payment'      => $status === 'submitted' && ( TournamentAccess::can_manage() || TournamentAccess::is_assigned( $entry_id ) ),
			],
			$payment
		);
	}

	/** Save a shared draft with optimistic locking. */
	public function save_draft( int $entry_id, array $payload, int $actor_user_id ) {
		$entry = $this->format_entry( $entry_id );
		if ( empty( $entry ) ) {
			return new \WP_Error( 'rondo_tournament_entry_not_found', __( 'Inschrijfopdracht niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $entry['registration_status'] !== 'open' ) {
			return new \WP_Error( 'rondo_tournament_entry_submitted', __( 'Deze inschrijving is al definitief bevestigd.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( ! $this->deadline_is_open( $entry['tournament'] ) ) {
			return new \WP_Error( 'rondo_tournament_deadline_passed', __( 'De interne inschrijfdeadline is verstreken.', 'rondo' ), [ 'status' => 409 ] );
		}
		$expected_version = absint( $payload['version'] ?? 0 );
		if ( $expected_version !== (int) $entry['version'] ) {
			return new \WP_Error(
				'rondo_tournament_entry_conflict',
				__( 'Een ander kaderlid heeft deze inschrijving intussen gewijzigd. Laad de actuele versie opnieuw.', 'rondo' ),
				[
					'status'  => 409,
					'current' => $entry,
				]
				);
		}

		$teams = $this->sanitize_team_entries( $payload['team_entries'] ?? [], false );
		if ( is_wp_error( $teams ) ) {
			return $teams;
		}

		Fields::update_many_for_post(
			$entry_id,
			[
				'contact_email'      => sanitize_email( (string) ( $payload['contact_email'] ?? '' ) ),
				'contact_mobile'     => sanitize_text_field( (string) ( $payload['contact_mobile'] ?? '' ) ),
				'contact_name'       => sanitize_text_field( (string) ( $payload['contact_name'] ?? '' ) ),
				'draft_team_entries' => $teams,
				'version'            => $entry['version'] + 1,
			]
		);
		update_post_meta( $entry_id, '_tournament_last_draft_user_id', $actor_user_id );
		update_post_meta( $entry_id, '_tournament_last_draft_at', current_datetime()->format( 'Y-m-d H:i:s' ) );
		TournamentActivityLog::record( $entry_id, 'draft_updated', $actor_user_id, [ 'version' => $entry['version'] + 1 ] );

		return $this->format_entry( $entry_id );
	}

	/** Confirm a positive registration. */
	public function submit_entry( int $entry_id, array $payload, int $actor_user_id ) {
		$entry = $this->format_entry( $entry_id );
		if ( empty( $entry ) ) {
			return new \WP_Error( 'rondo_tournament_entry_not_found', __( 'Inschrijfopdracht niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $entry['registration_status'] === 'submitted' ) {
			$this->payments->ensure_payment( $entry_id, $actor_user_id );
			return $this->format_entry( $entry_id );
		}
		if ( ! $this->deadline_is_open( $entry['tournament'] ) ) {
			return new \WP_Error( 'rondo_tournament_deadline_passed', __( 'De interne inschrijfdeadline is verstreken.', 'rondo' ), [ 'status' => 409 ] );
		}
		$expected_version = absint( $payload['version'] ?? 0 );
		if ( $expected_version !== (int) $entry['version'] ) {
			return new \WP_Error(
				'rondo_tournament_entry_conflict',
				__( 'Een ander kaderlid heeft deze inschrijving intussen gewijzigd. Laad de actuele versie opnieuw.', 'rondo' ),
				[
					'status'  => 409,
					'current' => $entry,
				]
				);
		}

		$teams = $this->sanitize_team_entries( $payload['team_entries'] ?? $entry['draft_team_entries'], true );
		if ( is_wp_error( $teams ) ) {
			return $teams;
		}
		$contact_name   = sanitize_text_field( (string) ( $payload['contact_name'] ?? $entry['contact_name'] ) );
		$contact_email  = sanitize_email( (string) ( $payload['contact_email'] ?? $entry['contact_email'] ) );
		$contact_mobile = sanitize_text_field( (string) ( $payload['contact_mobile'] ?? $entry['contact_mobile'] ) );
		if ( $contact_name === '' || ! is_email( $contact_email ) || $contact_mobile === '' ) {
			return new \WP_Error( 'rondo_tournament_contact_required', __( 'Vul één volledige contactpersoon voor deze inschrijving in.', 'rondo' ), [ 'status' => 400 ] );
		}

		$pricing = $this->pricing_for_age( $entry['tournament']['pricing_rules'], (int) $entry['age_number'] );
		if ( $pricing === null ) {
			return new \WP_Error( 'rondo_tournament_price_missing', __( 'Voor deze leeftijdslaag is geen tarief ingesteld.', 'rondo' ), [ 'status' => 409 ] );
		}

		$team_count   = count( $teams );
		$player_count = array_sum( array_map( static fn( array $team ): int => (int) $team['player_count'], $teams ) );
		$price        = (float) $pricing['amount'];
		Fields::update_many_for_post(
			$entry_id,
			[
				'contact_email'          => $contact_email,
				'contact_mobile'         => $contact_mobile,
				'contact_name'           => $contact_name,
				'draft_team_entries'     => $teams,
				'player_count'           => $player_count,
				'payment_state'          => $price * $team_count > 0 ? 'creating' : 'not_applicable',
				'price_per_team'         => $price,
				'registered_team_count'  => $team_count,
				'registration_status'    => 'submitted',
				'submitted_at'           => current_datetime()->format( 'Y-m-d H:i:s' ),
				'submitted_by_user_id'   => $actor_user_id,
				'submitted_team_entries' => $teams,
				'total_amount'           => $price * $team_count,
				'version'                => $entry['version'] + 1,
			]
		);
		TournamentActivityLog::record(
			$entry_id,
			'entry_submitted',
			$actor_user_id,
			[
				'registered_team_count' => $team_count,
				'player_count'          => $player_count,
				'total_amount'          => $price * $team_count,
			]
		);

		$this->payments->ensure_payment( $entry_id, $actor_user_id );
		return $this->format_entry( $entry_id );
	}

	/** Retry the idempotent invoice and payment-link creation for a submitted entry. */
	public function retry_payment( int $entry_id, int $actor_user_id ) {
		$result = $this->payments->ensure_payment( $entry_id, $actor_user_id );
		return is_wp_error( $result ) ? $result : $this->format_entry( $entry_id );
	}

	/** Send a manager-triggered reminder for one unpaid submitted entry. */
	public function send_payment_reminder( int $entry_id ) {
		return TournamentPaymentEmail::send_manual_reminder( $entry_id );
	}

	/** Save one planner-only operational note for a team entry. */
	public function update_planner_note( int $entry_id, string $note, int $actor_user_id ) {
		$entry = $this->format_entry( $entry_id );
		if ( empty( $entry ) ) {
			return new \WP_Error( 'rondo_tournament_entry_not_found', __( 'Inschrijfopdracht niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $entry['tournament']['lifecycle_status'] === 'archived' ) {
			return new \WP_Error( 'rondo_tournament_archived', __( 'Een gearchiveerd toernooi is alleen-lezen.', 'rondo' ), [ 'status' => 409 ] );
		}
		$before = $entry['planner_note'];
		$after  = sanitize_textarea_field( $note );
		if ( $before !== $after ) {
			Fields::update_for_post( $entry_id, 'planner_note', $after );
			TournamentActivityLog::record( $entry_id, 'planner_note_changed', $actor_user_id );
		}
		return $this->format_entry( $entry_id );
	}

	/** Reopen an unpaid submitted entry and retire its existing invoice. */
	public function reopen_entry( int $entry_id, int $actor_user_id ) {
		$entry = $this->format_entry( $entry_id );
		if ( empty( $entry ) ) {
			return new \WP_Error( 'rondo_tournament_entry_not_found', __( 'Inschrijfopdracht niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		if ( $entry['registration_status'] !== 'submitted' ) {
			return new \WP_Error( 'rondo_tournament_entry_not_submitted', __( 'Alleen een definitieve inschrijving kan worden heropend.', 'rondo' ), [ 'status' => 409 ] );
		}
		$cancelled = $this->payments->cancel_unpaid_payment( $entry_id );
		if ( is_wp_error( $cancelled ) ) {
			return $cancelled;
		}

		Fields::update_many_for_post(
			$entry_id,
			[
				'invoice_id'             => null,
				'last_payment_email_at'  => null,
				'payment_reminder_log'   => [],
				'payment_state'          => 'not_applicable',
				'player_count'           => 0,
				'price_per_team'         => 0,
				'registered_team_count'  => 0,
				'registration_status'    => 'open',
				'submitted_at'           => null,
				'submitted_by_user_id'   => 0,
				'submitted_team_entries' => [],
				'total_amount'           => 0,
				'version'                => (int) $entry['version'] + 1,
			]
		);
		delete_post_meta( $entry_id, '_tournament_payment_error' );
		foreach ( $entry['assigned_user_ids'] as $user_id ) {
			delete_post_meta( $entry_id, '_tournament_payment_email_sent_' . (int) $user_id );
		}
		foreach ( $entry['tournament']['payment_reminder_days'] ?? [ 7, 2 ] as $days_before ) {
			delete_post_meta( $entry_id, '_tournament_payment_reminder_' . absint( $days_before ) . '_sent_at' );
		}
		update_post_meta( $entry_id, '_tournament_reopened_at', current_time( 'mysql' ) );
		update_post_meta( $entry_id, '_tournament_reopened_by_user_id', $actor_user_id );
		TournamentActivityLog::record( $entry_id, 'entry_reopened', $actor_user_id );
		return $this->format_entry( $entry_id );
	}

	private function create_entry( int $tournament_id, array $team, array $user_ids, int $actor_user_id ) {
		$existing = $this->find_entry( $tournament_id, (int) $team['id'] );
		if ( $existing > 0 ) {
			return $this->format_entry( $existing );
		}

		$entry_id = wp_insert_post(
			wp_slash(
				[
					'post_type'   => self::ENTRY_POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => get_the_title( $tournament_id ) . ' · ' . $team['name'],
					'post_author' => $actor_user_id,
				]
			),
			true
		);
		if ( is_wp_error( $entry_id ) ) {
			return $entry_id;
		}

		$candidates = [];
		foreach ( $team['assignees'] as $candidate ) {
			if ( in_array( (int) $candidate['user_id'], $user_ids, true ) ) {
				$candidates[] = $candidate;
			}
		}
		Fields::update_many_for_post(
			(int) $entry_id,
			[
				'age_group_snapshot'  => $team['age_group'],
				'assignment_snapshot' => $candidates,
				'registration_status' => 'open',
				'payment_state'       => 'not_applicable',
				'team_id'             => (int) $team['id'],
				'team_name_snapshot'  => $team['name'],
				'tournament_id'       => $tournament_id,
				'version'             => 1,
			]
		);
		foreach ( $user_ids as $user_id ) {
			update_post_meta( (int) $entry_id, '_tournament_assigned_user_' . $user_id, 1 );
		}
		TournamentActivityLog::record( (int) $entry_id, 'entry_created', $actor_user_id, [ 'assigned_user_count' => count( $user_ids ) ] );

		return $this->format_entry( (int) $entry_id );
	}

	private function find_entry( int $tournament_id, int $team_id ): int {
		$ids = get_posts(
			[
				'post_type'        => self::ENTRY_POST_TYPE,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'AND',
					[
						'key'   => 'tournament_id',
						'value' => $tournament_id,
					],
					[
						'key'   => 'team_id',
						'value' => $team_id,
					],
				],
			]
		);
		return empty( $ids ) ? 0 : (int) $ids[0];
	}

	private function kader_candidates_by_team(): array {
		$users   = get_users(
			[
				'fields'   => [ 'ID', 'display_name', 'user_email' ],
				'meta_key' => 'rondo_linked_person_id',
				'number'   => -1,
			]
		);
		$by_team = [];

		foreach ( $users as $user ) {
			$person_id = (int) get_user_meta( (int) $user->ID, 'rondo_linked_person_id', true );
			if ( get_post_type( $person_id ) !== 'person' || Fields::get_for_post( $person_id, 'former_member' ) ) {
				continue;
			}
			$roles_by_team = [];
			foreach ( Fields::get_for_post( $person_id, 'work_history' ) ?: [] as $position ) {
				if ( ! is_array( $position ) || ! VolunteerStatus::is_position_current( $position ) || ! VolunteerStatus::is_volunteer_position( $position ) ) {
					continue;
				}
				$team_id = (int) ( $position['team'] ?? $position['team_id'] ?? 0 );
				if ( get_post_type( $team_id ) !== 'team' ) {
					continue;
				}
				$role = sanitize_text_field( (string) ( $position['job_title'] ?? '' ) );
				if ( $role !== '' ) {
					$roles_by_team[ $team_id ][] = $role;
				}
			}

			$name  = $this->person_name( $person_id, (string) $user->display_name );
			$email = sanitize_email( (string) ( UserProvisioning::contact_email( (int) $user->ID ) ?? '' ) );
			foreach ( $roles_by_team as $team_id => $roles ) {
				$by_team[ $team_id ][] = [
					'user_id'   => (int) $user->ID,
					'person_id' => $person_id,
					'name'      => $name,
					'role'      => implode( ', ', array_values( array_unique( $roles ) ) ),
					'email'     => $email,
				];
			}
		}

		foreach ( $by_team as &$candidates ) {
			usort( $candidates, static fn( array $left, array $right ): int => strcasecmp( $left['name'], $right['name'] ) );
		}
		unset( $candidates );
		return $by_team;
	}

	private function send_assignment_emails( array $entry, array $tournament ): array {
		$results = [];
		foreach ( $entry['assignees'] as $assignee ) {
			$user_id = (int) ( $assignee['user_id'] ?? 0 );
			$email   = sanitize_email( (string) ( $assignee['email'] ?? '' ) );
			if ( $user_id <= 0 || ! is_email( $email ) ) {
				$results[] = [
					'user_id' => $user_id,
					'sent'    => false,
				];
				continue;
			}
			$sent_key = '_tournament_assignment_email_sent_' . $user_id;
			if ( get_post_meta( $entry['id'], $sent_key, true ) ) {
				$results[] = [
					'user_id'  => $user_id,
					'sent'     => true,
					'existing' => true,
				];
				continue;
			}

			$url     = home_url( '/mijn-toernooien/' . $entry['id'] );
			$subject = sprintf( '%s: inschrijving voor %s', $tournament['name'], $entry['team_name'] );
			$message = sprintf(
				'<p>Hallo %s,</p><p>De inschrijving voor <strong>%s</strong> is aan het kader van <strong>%s</strong> toegewezen.</p><p>De interne deadline is <strong>%s</strong>.</p>%s<p><a href="%s">Open de inschrijving in Rondo</a></p>',
				esc_html( (string) ( $assignee['name'] ?? '' ) ),
				esc_html( $tournament['name'] ),
				esc_html( $entry['team_name'] ),
				esc_html( wp_date( 'j F Y', strtotime( $tournament['internal_deadline'] ) ) ),
				wpautop( wp_kses_post( $tournament['description'] ) ),
				esc_url( $url )
			);
			$sent    = wp_mail( $email, $subject, $message, [ 'Content-Type: text/html; charset=UTF-8' ] );
			if ( $sent ) {
				update_post_meta( $entry['id'], $sent_key, current_datetime()->format( 'Y-m-d H:i:s' ) );
			}
			$results[] = [
				'user_id' => $user_id,
				'sent'    => (bool) $sent,
			];
		}
		return $results;
	}

	private function sanitize_pricing_rules( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return new \WP_Error( 'rondo_tournament_pricing_required', __( 'Voeg minimaal één tariefregel toe.', 'rondo' ), [ 'status' => 400 ] );
		}
		$rules = [];
		foreach ( $raw as $row ) {
			$min_age = absint( $row['min_age'] ?? 0 );
			$max_age = absint( $row['max_age'] ?? 0 );
			$amount  = (float) ( $row['amount'] ?? -1 );
			if ( $min_age <= 0 || $max_age < $min_age || $amount < 0 ) {
				return new \WP_Error( 'rondo_tournament_pricing_invalid', __( 'Controleer de leeftijdsgrenzen en bedragen.', 'rondo' ), [ 'status' => 400 ] );
			}
			$rules[] = [
				'min_age'     => $min_age,
				'max_age'     => $max_age,
				'amount'      => round( $amount, 2 ),
				'game_format' => sanitize_text_field( (string) ( $row['game_format'] ?? '' ) ),
			];
		}
		return $rules;
	}

	private function sanitize_payment_reminder_days( $raw ) {
		if ( ! is_array( $raw ) ) {
			return new \WP_Error( 'rondo_tournament_payment_reminders_invalid', __( 'Controleer de betaalherinneringen.', 'rondo' ), [ 'status' => 400 ] );
		}
		$days = [];
		foreach ( $raw as $row ) {
			$value = is_array( $row ) ? ( $row['days_before'] ?? null ) : $row;
			if ( ! is_numeric( $value ) || (int) $value < 0 || (int) $value > 60 ) {
				return new \WP_Error( 'rondo_tournament_payment_reminders_invalid', __( 'Een betaalherinnering moet 0 tot en met 60 dagen voor de deadline staan.', 'rondo' ), [ 'status' => 400 ] );
			}
			$days[] = (int) $value;
		}
		$days = array_values( array_unique( $days ) );
		rsort( $days, SORT_NUMERIC );
		return $days;
	}

	private function format_payment_reminder_days( $rows ): array {
		if ( ! is_array( $rows ) || empty( $rows ) ) {
			return [ 7, 2 ];
		}
		$days = array_map( static fn( $row ): int => absint( is_array( $row ) ? ( $row['days_before'] ?? 0 ) : $row ), $rows );
		$days = array_values( array_unique( $days ) );
		rsort( $days, SORT_NUMERIC );
		return $days;
	}

	private function sanitize_schedule( $raw ) {
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return new \WP_Error( 'rondo_tournament_schedule_required', __( 'Voeg minimaal één toernooimoment toe.', 'rondo' ), [ 'status' => 400 ] );
		}
		$rows = [];
		foreach ( $raw as $row ) {
			$age_group      = sanitize_text_field( (string) ( $row['age_group'] ?? '' ) );
			$start_datetime = $this->parse_datetime( $row['start_datetime'] ?? '' );
			if ( $age_group === '' || is_wp_error( $start_datetime ) ) {
				return new \WP_Error( 'rondo_tournament_schedule_invalid', __( 'Controleer de leeftijd en datum van ieder toernooimoment.', 'rondo' ), [ 'status' => 400 ] );
			}
			$rows[] = [
				'age_group'      => $age_group,
				'location'       => sanitize_text_field( (string) ( $row['location'] ?? '' ) ),
				'start_datetime' => $start_datetime->format( DATE_RFC3339 ),
			];
		}
		return $rows;
	}

	private function sanitize_team_entries( $raw, bool $required ) {
		if ( ! is_array( $raw ) ) {
			$raw = [];
		}
		if ( $required && empty( $raw ) ) {
			return new \WP_Error( 'rondo_tournament_team_required', __( 'Schrijf minimaal één team in.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( count( $raw ) > 20 ) {
			return new \WP_Error( 'rondo_tournament_team_limit', __( 'Per Rondo-team kunnen maximaal twintig teams worden ingeschreven.', 'rondo' ), [ 'status' => 400 ] );
		}
		$rows = [];
		foreach ( array_values( $raw ) as $index => $row ) {
			$players = absint( $row['player_count'] ?? 0 );
			if ( $required && $players <= 0 ) {
				/* translators: %d: sequence number of the tournament team. */
				$message = sprintf( __( 'Vul het aantal spelers voor team %d in.', 'rondo' ), $index + 1 );
				return new \WP_Error( 'rondo_tournament_players_required', $message, [ 'status' => 400 ] );
			}
			$rows[] = [
				'sequence'     => $index + 1,
				'player_count' => $players,
			];
		}
		return $rows;
	}

	private function parse_datetime( $value, bool $date_is_end_of_day = false ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return new \WP_Error( 'rondo_tournament_datetime_required', __( 'Vul alle verplichte datums in.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) === 1 ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, wp_timezone() );
			if ( $date instanceof DateTimeImmutable && $date->format( 'Y-m-d' ) === $value ) {
				return $date_is_end_of_day ? $date->setTime( 23, 59, 59 ) : $date;
			}
		}
		try {
			return new DateTimeImmutable( $value, wp_timezone() );
		} catch ( \Exception $error ) {
			return new \WP_Error( 'rondo_tournament_datetime_invalid', __( 'Een datum is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
		}
	}

	private function deadline_is_open( array $tournament ): bool {
		if ( ( $tournament['lifecycle_status'] ?? '' ) !== 'open' || empty( $tournament['internal_deadline'] ) ) {
			return false;
		}
		try {
			return current_datetime() <= new DateTimeImmutable( $tournament['internal_deadline'], wp_timezone() );
		} catch ( \Exception $error ) {
			return false;
		}
	}

	private function pricing_for_age( array $rules, int $age ): ?array {
		foreach ( $rules as $rule ) {
			if ( $age >= (int) ( $rule['min_age'] ?? 0 ) && $age <= (int) ( $rule['max_age'] ?? 0 ) ) {
				return $rule;
			}
		}
		return null;
	}

	private function age_group_for_team( int $team_id ): string {
		$seen = [];
		while ( $team_id > 0 && ! isset( $seen[ $team_id ] ) ) {
			$seen[ $team_id ] = true;
			$title            = html_entity_decode( get_the_title( $team_id ), ENT_QUOTES, 'UTF-8' );
			if ( preg_match( '/\b(?:J|M)?O\s?-?(\d{1,2})\b/iu', $title, $matches ) === 1 ) {
				return 'O' . (int) $matches[1];
			}
			$team_id = (int) wp_get_post_parent_id( $team_id );
		}
		return '';
	}

	private function age_number( string $age_group ): int {
		return preg_match( '/^O(\d{1,2})$/', $age_group, $matches ) === 1 ? (int) $matches[1] : 0;
	}

	private function person_name( int $person_id, string $fallback ): string {
		$parts = [
			Fields::get_for_post( $person_id, 'first_name' ),
			Fields::get_for_post( $person_id, 'infix' ),
			Fields::get_for_post( $person_id, 'last_name' ),
		];
		$name  = trim( implode( ' ', array_filter( array_map( 'strval', $parts ) ) ) );
		return $name !== '' ? $name : $fallback;
	}
}
