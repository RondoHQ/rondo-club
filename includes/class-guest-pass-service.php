<?php
/**
 * Reusable guest-pass slots for team members and businessclub contacts.
 *
 * @package Rondo\Passes
 */

namespace Rondo\Passes;

use Rondo\Core\VolunteerStatus;
use Rondo\Config\ClubConfig;
use Rondo\Fields\Fields;
use Rondo\Sponsors\Relations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owns guest-pass eligibility, slot state and public claim tokens. */
class GuestPassService {

	public const SLOT_LIMIT = 2;

	private const SHARE_TOKEN_META_KEY = '_rondo_guest_pass_share_token';

	/** Return the linked person for the signed-in account. */
	public function get_current_host_person_id(): int {
		return get_current_user_id() > 0
			? (int) get_user_meta( get_current_user_id(), 'rondo_linked_person_id', true )
			: 0;
	}

	/** Return the team selected by an administrator. */
	public function get_eligible_team_id(): int {
		return ClubConfig::get_guest_pass_team_id();
	}

	/** Return the current title of the selected team. */
	public function get_eligible_team_name(): string {
		return ClubConfig::get_guest_pass_team_name();
	}

	/** Return active businessclub companies with this person as their only contact. */
	public function get_businessclub_sponsors( int $person_id ): array {
		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' || get_post_status( $person_id ) !== 'publish' ) {
			return [];
		}

		return array_values(
			array_filter(
				Relations::for_person( $person_id, true ),
				static function ( array $relationship ) use ( $person_id ): bool {
					if ( $relationship['sponsor_role'] !== 'businessclub' || empty( $relationship['receives_pass'] ) ) {
						return false;
					}
					$contacts = Fields::get_for_post( $relationship['sponsor_id'], 'contacts' );
					return is_array( $contacts ) && count( $contacts ) === 1 && (int) $contacts[0]['person_id'] === $person_id;
				}
			)
		);
	}

	/** Check a person's current entitlement for either team or company guest slots. */
	public function is_eligible_host( int $person_id, int $sponsor_id = 0 ): bool {
		if ( $sponsor_id !== 0 ) {
			return in_array( $sponsor_id, array_column( $this->get_businessclub_sponsors( $person_id ), 'sponsor_id' ), true );
		}

		$eligible_team_id = $this->get_eligible_team_id();
		if ( $eligible_team_id <= 0 ) {
			return false;
		}

		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return false;
		}
		if ( MembershipPassService::get_person_membership_status( $person_id )['status'] !== 'active' ) {
			return false;
		}

		$eligible_roles = array_unique(
			array_map(
				[ $this, 'normalize' ],
				array_merge( VolunteerStatus::get_player_roles(), VolunteerStatus::get_staff_roles() )
			)
		);
		$work_history   = Fields::get_for_post( $person_id, 'work_history' );
		if ( ! is_array( $work_history ) ) {
			return false;
		}

		foreach ( $work_history as $position ) {
			if ( ! is_array( $position ) || ! $this->position_is_current( $position ) ) {
				continue;
			}

			$team_id = isset( $position['team'] ) ? (int) $position['team'] : 0;
			if ( $team_id !== $eligible_team_id ) {
				continue;
			}

			$job_title = $this->normalize( (string) ( $position['job_title'] ?? '' ) );
			if ( $job_title !== '' && in_array( $job_title, $eligible_roles, true ) ) {
				return true;
			}
		}

		return false;
	}

	/** Backward-compatible alias for older callers. */
	public function is_eligible_player( int $person_id ): bool {
		return $this->is_eligible_host( $person_id );
	}

	/** Check the source and slot at every public, Wallet and scanner boundary. */
	public function is_eligible_pass( array $pass ): bool {
		return $pass['slot'] >= 1 && $pass['slot'] <= $this->get_slot_limit( $pass['sponsor_id'] )
			&& $this->is_eligible_host( $pass['host_person_id'], $pass['sponsor_id'] );
	}

	/** Businessclub companies have one guest slot; team members retain two. */
	public function get_slot_limit( int $sponsor_id = 0 ): int {
		return $sponsor_id > 0 ? 1 : self::SLOT_LIMIT;
	}

	/** Return fixed slots, including virtual slots that have not been created yet. */
	public function get_slots( int $host_person_id, int $sponsor_id = 0 ): array {
		$slots = [];
		$limit = $this->get_slot_limit( $sponsor_id );
		for ( $slot = 1; $slot <= $limit; ++$slot ) {
			$pass_id = $this->find_slot_id( $host_person_id, $slot, $sponsor_id );
			$slots[] = $pass_id > 0 ? $this->format_slot( $pass_id ) : $this->format_empty_slot( $slot );
		}
		return $slots;
	}

	/** Create a slot on first use and keep its identity stable afterwards. */
	public function ensure_slot( int $host_person_id, int $slot, int $sponsor_id = 0 ) {
		if ( ! $this->is_eligible_host( $host_person_id, $sponsor_id ) ) {
			return new \WP_Error( 'rondo_guest_pass_ineligible', 'Je hebt momenteel geen recht op deze gastpas.', [ 'status' => 403 ] );
		}
		if ( $slot < 1 || $slot > $this->get_slot_limit( $sponsor_id ) ) {
			return new \WP_Error( 'rondo_guest_pass_invalid_slot', 'Ongeldig gastslot.', [ 'status' => 400 ] );
		}

		$existing_id = $this->find_slot_id( $host_person_id, $slot, $sponsor_id );
		if ( $existing_id > 0 ) {
			return $this->format_slot( $existing_id );
		}
		$mapping_key = 'rondo_guest_pass_slot_' . $host_person_id . '_' . $slot;
		if ( $sponsor_id > 0 ) {
			$mapping_key .= '_sponsor_' . $sponsor_id;
		}
		$mapping_value = get_option( $mapping_key, false );
		if ( is_numeric( $mapping_value ) && $this->get_pass_data( (int) $mapping_value ) !== null ) {
			return $this->format_slot( (int) $mapping_value );
		}
		if ( ! add_option( $mapping_key, 'pending:' . time(), '', false ) ) {
			$existing_id = $this->find_slot_id( $host_person_id, $slot, $sponsor_id );
			if ( $existing_id > 0 ) {
				update_option( $mapping_key, $existing_id, false );
				return $this->format_slot( $existing_id );
			}
			return new \WP_Error( 'rondo_guest_pass_slot_busy', 'Deze gastlink wordt al gemaakt. Probeer het nogmaals.', [ 'status' => 409 ] );
		}

		$pass_id = wp_insert_post(
			[
				'post_type'   => 'rondo_guest_pass',
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
				'post_title'  => sprintf( 'Gastpas %s · slot %d', $this->get_person_name( $host_person_id ), $slot ),
			],
			true
		);
		if ( is_wp_error( $pass_id ) ) {
			delete_option( $mapping_key );
			return $pass_id;
		}

		$updated = Fields::update_many_for_post(
			(int) $pass_id,
			[
				'host_person_id' => $host_person_id,
				'sponsor_id'     => $sponsor_id > 0 ? $sponsor_id : null,
				'slot_number'    => $slot,
				'guest_name'     => '',
				'pass_status'    => 'unclaimed',
				'claimed_at'     => '',
				'pass_version'   => 1,
			]
		);
		if ( is_wp_error( $updated ) ) {
			wp_delete_post( (int) $pass_id, true );
			delete_option( $mapping_key );
			return $updated;
		}

		update_post_meta( (int) $pass_id, self::SHARE_TOKEN_META_KEY, $this->generate_token() );
		update_option( $mapping_key, (int) $pass_id, false );
		return $this->format_slot( (int) $pass_id );
	}

	/** Revoke the old QR/link and return the same slot as a new unclaimed pass. */
	public function replace_slot( int $host_person_id, int $slot, int $sponsor_id = 0 ) {
		if ( ! $this->is_eligible_host( $host_person_id, $sponsor_id ) ) {
			return new \WP_Error( 'rondo_guest_pass_ineligible', 'Je hebt momenteel geen recht op deze gastpas.', [ 'status' => 403 ] );
		}
		if ( $slot < 1 || $slot > $this->get_slot_limit( $sponsor_id ) ) {
			return new \WP_Error( 'rondo_guest_pass_invalid_slot', 'Ongeldig gastslot.', [ 'status' => 400 ] );
		}
		$pass_id = $this->find_slot_id( $host_person_id, $slot, $sponsor_id );
		if ( $pass_id <= 0 ) {
			return $this->ensure_slot( $host_person_id, $slot, $sponsor_id );
		}

		$version = max( 1, (int) Fields::get_for_post( $pass_id, 'pass_version' ) ) + 1;
		$updated = Fields::update_many_for_post(
			$pass_id,
			[
				'guest_name'   => '',
				'pass_status'  => 'unclaimed',
				'claimed_at'   => '',
				'pass_version' => $version,
			]
		);
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		update_post_meta( $pass_id, self::SHARE_TOKEN_META_KEY, $this->generate_token() );
		return $this->format_slot( $pass_id );
	}

	/** Claim an unassigned slot once with the guest's name. */
	public function claim( string $share_token, string $guest_name ) {
		$pass_id = $this->find_by_share_token( $share_token );
		if ( $pass_id <= 0 ) {
			return new \WP_Error( 'rondo_guest_pass_not_found', 'Deze gastlink is niet geldig.', [ 'status' => 404 ] );
		}

		$pass = $this->get_pass_data( $pass_id );
		if ( $pass === null || ! $this->is_eligible_pass( $pass ) ) {
			return new \WP_Error( 'rondo_guest_pass_host_ineligible', 'Deze gastpas is momenteel niet beschikbaar.', [ 'status' => 403 ] );
		}
		$status = $pass['status'];
		if ( $status === 'active' ) {
			return $this->format_slot( $pass_id );
		}

		$guest_name = trim( sanitize_text_field( $guest_name ) );
		if ( mb_strlen( $guest_name ) < 2 || mb_strlen( $guest_name ) > 100 ) {
			return new \WP_Error( 'rondo_guest_pass_invalid_name', 'Vul je volledige naam in.', [ 'status' => 400 ] );
		}

		$updated = Fields::update_many_for_post(
			$pass_id,
			[
				'guest_name'  => $guest_name,
				'pass_status' => 'active',
				'claimed_at'  => wp_date( DATE_RFC3339 ),
			]
		);
		return is_wp_error( $updated ) ? $updated : $this->format_slot( $pass_id );
	}

	/** Resolve a public bearer link to its private pass record. */
	public function get_by_share_token( string $share_token ): ?array {
		$pass_id = $this->find_by_share_token( $share_token );
		return $pass_id > 0 ? $this->format_slot( $pass_id ) : null;
	}

	/** Return scanner and Wallet data for one pass. */
	public function get_pass_data( int $pass_id ): ?array {
		$post = get_post( $pass_id );
		if ( ! $post || $post->post_type !== 'rondo_guest_pass' || $post->post_status !== 'publish' ) {
			return null;
		}

		$host_person_id = (int) Fields::get_for_post( $pass_id, 'host_person_id' );
		$sponsor_id     = (int) Fields::get_for_post( $pass_id, 'sponsor_id' );
		return [
			'id'             => $pass_id,
			'sponsor_id'     => $sponsor_id,
			'sponsor_name'   => $sponsor_id > 0 ? html_entity_decode( get_the_title( $sponsor_id ), ENT_QUOTES, 'UTF-8' ) : '',
			'valid_for'      => $sponsor_id > 0 ? 'Thuiswedstrijden ' . ClubConfig::get_club_name() : $this->get_eligible_team_name() . ' thuiswedstrijden',
			'host_person_id' => $host_person_id,
			'host_name'      => $this->get_person_name( $host_person_id ),
			'guest_name'     => (string) Fields::get_for_post( $pass_id, 'guest_name' ),
			'slot'           => (int) Fields::get_for_post( $pass_id, 'slot_number' ),
			'status'         => (string) Fields::get_for_post( $pass_id, 'pass_status' ),
			'pass_version'   => max( 1, (int) Fields::get_for_post( $pass_id, 'pass_version' ) ),
		];
	}

	/** Validate current state and match scope after a QR signature is verified. */
	public function validate_for_event( int $pass_id, int $token_version, int $event_id ) {
		$data = $this->get_pass_data( $pass_id );
		if ( $data === null ) {
			return new \WP_Error( 'rondo_guest_pass_not_found', 'Gastpas niet gevonden.', [ 'status' => 404 ] );
		}
		if ( $data['pass_version'] !== $token_version ) {
			return new \WP_Error( 'rondo_guest_pass_revoked', 'Deze gastpas is ingetrokken.', [ 'status' => 403 ] );
		}
		if ( $data['status'] !== 'active' || $data['guest_name'] === '' ) {
			return new \WP_Error( 'rondo_guest_pass_unclaimed', 'Deze gastpas is nog niet geregistreerd.', [ 'status' => 403 ] );
		}
		if ( ! $this->is_eligible_pass( $data ) ) {
			return new \WP_Error( 'rondo_guest_pass_host_ineligible', 'Deze gastpas is momenteel niet beschikbaar.', [ 'status' => 403 ] );
		}
		if ( get_post_type( $event_id ) !== 'rondo_access_event' ) {
			return new \WP_Error( 'rondo_guest_pass_event_required', 'Selecteer eerst een wedstrijd.', [ 'status' => 422 ] );
		}

		$home_team          = (string) Fields::get_for_post( $event_id, 'home_team' );
		$eligible_team_name = $this->get_eligible_team_name();
		if ( $data['sponsor_id'] === 0 && ( $eligible_team_name === '' || $this->normalize( $home_team ) !== $this->normalize( $eligible_team_name ) ) ) {
			return new \WP_Error( 'rondo_guest_pass_wrong_match', 'Deze gastpas is niet geldig voor de gekozen wedstrijd.', [ 'status' => 403 ] );
		}

		return $data;
	}

	/** Public URL for sharing one slot. */
	public function get_share_url( int $pass_id ): string {
		$token = (string) get_post_meta( $pass_id, self::SHARE_TOKEN_META_KEY, true );
		return $token !== '' ? home_url( '/gastpas/' . $token ) : '';
	}

	private function format_slot( int $pass_id ): array {
		$data = $this->get_pass_data( $pass_id );
		return [
			'id'         => $pass_id,
			'slot'       => $data['slot'],
			'status'     => $data['status'],
			'guest_name' => $data['guest_name'],
			'claimed_at' => Fields::get_for_post( $pass_id, 'claimed_at' ),
			'share_url'  => $this->get_share_url( $pass_id ),
		];
	}

	private function format_empty_slot( int $slot ): array {
		return [
			'id'         => null,
			'slot'       => $slot,
			'status'     => 'empty',
			'guest_name' => '',
			'claimed_at' => null,
			'share_url'  => '',
		];
	}

	private function find_slot_id( int $host_person_id, int $slot, int $sponsor_id = 0 ): int {
		$ids = get_posts(
			[
				'post_type'      => 'rondo_guest_pass',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [
					'relation' => 'AND',
					$sponsor_id > 0 ? [
						'key'   => 'sponsor_id',
						'value' => $sponsor_id,
					] : [
						'relation' => 'OR',
						[
							'key'     => 'sponsor_id',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'   => 'sponsor_id',
							'value' => 0,
							'type'  => 'NUMERIC',
						],
					],
					[
						'key'     => 'host_person_id',
						'value'   => $host_person_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
					[
						'key'     => 'slot_number',
						'value'   => $slot,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			]
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private function find_by_share_token( string $share_token ): int {
		if ( preg_match( '/^[a-f0-9]{64}$/', $share_token ) !== 1 ) {
			return 0;
		}
		$ids = get_posts(
			[
				'post_type'      => 'rondo_guest_pass',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => self::SHARE_TOKEN_META_KEY,
				'meta_value'     => $share_token,
			]
		);
		return $ids ? (int) $ids[0] : 0;
	}

	private function position_is_current( array $position ): bool {
		if ( \Rondo\Core\WorkHistory::is_inactive_without_end_date( $position ) ) {
			return false;
		}

		$today = wp_date( 'Y-m-d' );
		$start = $this->normalize_date( (string) ( $position['start_date'] ?? '' ) );
		$end   = $this->normalize_date( (string) ( $position['end_date'] ?? '' ) );
		if ( $start !== '' && $start > $today ) {
			return false;
		}
		if ( $end !== '' && $end < $today ) {
			return false;
		}
		return ! empty( $position['is_current'] ) || $end === '' || $end >= $today;
	}

	private function normalize_date( string $date ): string {
		$date = trim( $date );
		if ( preg_match( '/^\d{8}$/', $date ) === 1 ) {
			return substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
		}
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) === 1 ? $date : '';
	}

	private function get_person_name( int $person_id ): string {
		$first_name = (string) Fields::try_get_for_post( $person_id, 'first_name' );
		$infix      = (string) Fields::try_get_for_post( $person_id, 'infix' );
		$last_name  = (string) Fields::try_get_for_post( $person_id, 'last_name' );
		$name       = trim( preg_replace( '/\s+/', ' ', $first_name . ' ' . $infix . ' ' . $last_name ) );
		return $name !== '' ? $name : (string) get_the_title( $person_id );
	}

	private function normalize( string $value ): string {
		return strtolower( trim( preg_replace( '/\s+/', ' ', $value ) ) );
	}

	private function generate_token(): string {
		return bin2hex( random_bytes( 32 ) );
	}
}
