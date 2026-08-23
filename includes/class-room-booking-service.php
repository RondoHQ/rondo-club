<?php
/**
 * Authoritative room and booking domain service.
 *
 * @package Rondo\Rooms
 */

namespace Rondo\Rooms;

use DateInterval;
use DateTimeImmutable;
use Rondo\Fields\Fields;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BookingService {

	public const ROOM_POST_TYPE    = 'rondo_room';
	public const BOOKING_POST_TYPE = 'rondo_room_booking';
	public const ACTIVITY_TYPE     = 'rondo_room_activity';

	private const LOCK_TIMEOUT_SECONDS = 10;
	private const LOCK_STALE_SECONDS   = 15;

	/** Return member-visible rooms, including admin-only archived rooms when requested. */
	public function rooms( bool $include_archived = false ): array {
		$room_ids = get_posts(
			[
				'post_type'        => self::ROOM_POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);
		$rooms    = [];
		foreach ( $room_ids as $room_id ) {
			$room = $this->format_room( (int) $room_id );
			if ( ! $include_archived && $room['archived'] ) {
				continue;
			}
			$rooms[] = $room;
		}
		usort(
			$rooms,
			static fn( array $left, array $right ): int => [ $left['sort_order'], $left['name'] ] <=> [ $right['sort_order'], $right['name'] ]
		);
		return $rooms;
	}

	/** Create or update one room from an administrator payload. */
	public function save_room( array $payload, int $room_id = 0 ) {
		$name = trim( sanitize_text_field( (string) ( $payload['name'] ?? ( $room_id ? get_the_title( $room_id ) : '' ) ) ) );
		if ( $name === '' ) {
			return new \WP_Error( 'rondo_room_name_required', __( 'Geef de ruimte een naam.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( $room_id && get_post_type( $room_id ) !== self::ROOM_POST_TYPE ) {
			return new \WP_Error( 'rondo_room_not_found', __( 'Ruimte niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}

		$display_id = absint( $payload['display_id'] ?? ( $room_id ? Fields::get_for_post( $room_id, 'display_id' ) : 0 ) );
		if ( $display_id > 0 && get_post_type( $display_id ) !== 'rondo_display' ) {
			return new \WP_Error( 'rondo_room_display_invalid', __( 'Kies een bestaand Club TV-scherm.', 'rondo' ), [ 'status' => 400 ] );
		}
		if ( $display_id > 0 ) {
			foreach ( $this->rooms( true ) as $other_room ) {
				if ( (int) $other_room['display_id'] === $display_id && (int) $other_room['id'] !== $room_id && ! $other_room['archived'] ) {
					return new \WP_Error( 'rondo_room_display_in_use', __( 'Dit scherm is al aan een andere ruimte gekoppeld.', 'rondo' ), [ 'status' => 409 ] );
				}
			}
		}

		$opening_hours = $this->sanitize_opening_hours( $payload['opening_hours'] ?? null );
		if ( is_wp_error( $opening_hours ) ) {
			return $opening_hours;
		}
		if ( $opening_hours === null ) {
			$opening_hours = $room_id ? Fields::get_for_post( $room_id, 'opening_hours' ) : $this->default_opening_hours();
		}

		if ( $room_id <= 0 ) {
			$room_id = wp_insert_post(
				[
					'post_type'   => self::ROOM_POST_TYPE,
					'post_status' => 'publish',
					'post_title'  => $name,
					'post_author' => get_current_user_id(),
				],
				true
			);
			if ( is_wp_error( $room_id ) ) {
				return $room_id;
			}
		} else {
			$result = wp_update_post(
				[
					'ID'         => $room_id,
					'post_title' => $name,
				],
				true
				);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$facilities = $payload['facilities'] ?? null;
		if ( is_string( $facilities ) ) {
			$facilities = array_map( static fn( string $value ): array => [ 'name' => sanitize_text_field( trim( $value ) ) ], explode( ',', $facilities ) );
		}
		if ( ! is_array( $facilities ) ) {
			$facilities = Fields::get_for_post( $room_id, 'facilities' ) ?: [];
		}
		$facilities = array_values(
			array_filter(
				array_map(
					static fn( $facility ): array => [ 'name' => sanitize_text_field( (string) ( is_array( $facility ) ? ( $facility['name'] ?? '' ) : $facility ) ) ],
					$facilities
				),
				static fn( array $facility ): bool => $facility['name'] !== ''
			)
		);

		$current = $this->format_room( $room_id );
		$fields  = [
			'location'                    => sanitize_text_field( (string) ( $payload['location'] ?? $current['location'] ) ),
			'description'                 => sanitize_textarea_field( (string) ( $payload['description'] ?? $current['description'] ) ),
			'capacity'                    => max( 0, absint( $payload['capacity'] ?? $current['capacity'] ) ),
			'facilities'                  => $facilities,
			'booking_enabled'             => array_key_exists( 'booking_enabled', $payload ) ? rest_sanitize_boolean( $payload['booking_enabled'] ) : $current['booking_enabled'],
			'display_id'                  => $display_id ?: null,
			'presentation_controlled'     => array_key_exists( 'presentation_controlled', $payload ) ? rest_sanitize_boolean( $payload['presentation_controlled'] ) : $current['presentation_controlled'],
			'opening_hours'               => $opening_hours,
			'minimum_duration_minutes'    => $this->bounded_int( $payload, 'minimum_duration_minutes', $current['minimum_duration_minutes'], 5, 1440 ),
			'maximum_duration_minutes'    => $this->bounded_int( $payload, 'maximum_duration_minutes', $current['maximum_duration_minutes'], 5, 1440 ),
			'booking_interval_minutes'    => $this->bounded_int( $payload, 'booking_interval_minutes', $current['booking_interval_minutes'], 5, 120 ),
			'minimum_notice_minutes'      => $this->bounded_int( $payload, 'minimum_notice_minutes', $current['minimum_notice_minutes'], 0, 525600 ),
			'maximum_advance_days'        => $this->bounded_int( $payload, 'maximum_advance_days', $current['maximum_advance_days'], 1, 730 ),
			'changeover_buffer_minutes'   => $this->bounded_int( $payload, 'changeover_buffer_minutes', $current['changeover_buffer_minutes'], 0, 240 ),
			'access_before_minutes'       => $this->bounded_int( $payload, 'access_before_minutes', $current['access_before_minutes'], 0, 120 ),
			'extension_increment_minutes' => $this->bounded_int( $payload, 'extension_increment_minutes', $current['extension_increment_minutes'], 5, 120 ),
			'sort_order'                  => (int) ( $payload['sort_order'] ?? $current['sort_order'] ),
			'member_instructions'         => sanitize_textarea_field( (string) ( $payload['member_instructions'] ?? $current['member_instructions'] ) ),
			'archived'                    => array_key_exists( 'archived', $payload ) ? rest_sanitize_boolean( $payload['archived'] ) : $current['archived'],
		];
		if ( $fields['maximum_duration_minutes'] < $fields['minimum_duration_minutes'] ) {
			return new \WP_Error( 'rondo_room_duration_invalid', __( 'De maximale duur moet minimaal gelijk zijn aan de minimale duur.', 'rondo' ), [ 'status' => 400 ] );
		}
		$result = Fields::update_many_for_post( $room_id, $fields );
		return is_wp_error( $result ) ? $result : $this->format_room( $room_id );
	}

	/** Create a member reservation or management block. */
	public function create_booking( array $payload, int $actor_user_id, bool $manager = false ) {
		$booking_type   = sanitize_key( (string) ( $payload['booking_type'] ?? 'member_reservation' ) );
		$holder_user_id = $manager ? absint( $payload['holder_user_id'] ?? 0 ) : $actor_user_id;
		if ( $booking_type === 'management_block' ) {
			if ( ! $manager ) {
				return new \WP_Error( 'rondo_room_block_forbidden', __( 'Alleen accommodatiebeheerders kunnen een ruimte blokkeren.', 'rondo' ), [ 'status' => 403 ] );
			}
			$holder_user_id = 0;
		} elseif ( $booking_type !== 'member_reservation' ) {
			return new \WP_Error( 'rondo_room_booking_type_invalid', __( 'Ongeldig reserveringstype.', 'rondo' ), [ 'status' => 400 ] );
		}

		$prepared = $this->prepare_booking_values( $payload, $holder_user_id, $actor_user_id, $manager, $booking_type );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		return $this->with_room_lock(
			(int) $prepared['room_id'],
			function () use ( $prepared, $actor_user_id ) {
				$conflict = $this->find_conflict( (int) $prepared['room_id'], $prepared['start_datetime'], $prepared['end_datetime'] );
				if ( $conflict ) {
					return $this->conflict_error( $conflict );
				}

				$post_id = wp_insert_post(
					[
						'post_type'   => self::BOOKING_POST_TYPE,
						'post_status' => 'publish',
						'post_title'  => $this->booking_title( $prepared ),
						'post_author' => $actor_user_id,
					],
					true
				);
				if ( is_wp_error( $post_id ) ) {
					return $post_id;
				}
				$result = Fields::update_many_for_post( $post_id, $prepared );
				if ( is_wp_error( $result ) ) {
					wp_delete_post( $post_id, true );
					return $result;
				}

				$booking = $this->format_booking( $post_id, true );
				$this->record_activity( $post_id, 'created', $actor_user_id, [], $booking );
				$booking['notification'] = BookingNotification::send_to_holder( $booking, 'created', $actor_user_id );
				BookingNotification::send_to_presenters( $booking, $prepared['authorized_presenter_user_ids'] );
				return $booking;
			}
		);
	}

	/** Update an existing reservation with holder or manager permissions already checked. */
	public function update_booking( int $booking_id, array $payload, int $actor_user_id, bool $manager ) {
		if ( get_post_type( $booking_id ) !== self::BOOKING_POST_TYPE ) {
			return new \WP_Error( 'rondo_room_booking_not_found', __( 'Reservering niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		$current = $this->format_booking( $booking_id, true );
		if ( $current['status'] === 'cancelled' ) {
			return new \WP_Error( 'rondo_room_booking_cancelled', __( 'Een geannuleerde reservering kan niet worden gewijzigd.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( ! $manager && new DateTimeImmutable( $current['start_datetime'] ) <= current_datetime() ) {
			return new \WP_Error( 'rondo_room_booking_started', __( 'Een begonnen reservering kan niet meer worden gewijzigd.', 'rondo' ), [ 'status' => 409 ] );
		}

		$merged           = [
			'room_id'                       => $payload['room_id'] ?? $current['room_id'],
			'start_datetime'                => $payload['start_datetime'] ?? $current['start_datetime'],
			'end_datetime'                  => $payload['end_datetime'] ?? $current['end_datetime'],
			'booking_type'                  => $current['booking_type'],
			'holder_user_id'                => $payload['holder_user_id'] ?? $current['holder_user_id'],
			'booking_context_type'          => $payload['booking_context_type'] ?? $current['booking_context_type'],
			'commissie_id'                  => $payload['commissie_id'] ?? $current['commissie_id'],
			'age_group_key'                 => $payload['age_group_key'] ?? $current['age_group_key'],
			'purpose'                       => $payload['purpose'] ?? $current['purpose'],
			'private_notes'                 => $payload['private_notes'] ?? $current['private_notes'],
			'authorized_presenter_user_ids' => $payload['authorized_presenter_user_ids'] ?? $current['authorized_presenter_user_ids'],
		];
		$holder_id        = $manager ? absint( $merged['holder_user_id'] ) : (int) $current['holder_user_id'];
		$submitted_age    = BookingEligibility::normalize_age_group( (string) $merged['age_group_key'] );
		$context_changed  = $holder_id !== (int) $current['holder_user_id']
			|| sanitize_key( (string) $merged['booking_context_type'] ) !== $current['booking_context_type']
			|| absint( $merged['commissie_id'] ) !== (int) $current['commissie_id']
			|| $submitted_age !== $current['age_group_key'];
		$existing_context = null;
		if ( ! $context_changed && $current['booking_type'] === 'member_reservation' ) {
			$existing_context = [
				'type'                => $current['booking_context_type'],
				'commissie_id'        => $current['commissie_id'] ?: null,
				'age_group_key'       => $current['age_group_key'],
				'label'               => $current['context_label'],
				'eligibility_team_id' => $current['eligibility_team_id'] ?: null,
			];
		}
		$prepared = $this->prepare_booking_values( $merged, $holder_id, $actor_user_id, $manager, $current['booking_type'], $existing_context );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		// Existing confirmed bookings survive a later role change. Recheck only
		// when the holder or selected context actually changes.
		if ( ! $context_changed && $current['booking_type'] === 'member_reservation' ) {
			$prepared['context_label_snapshot'] = $current['context_label'];
			$prepared['eligibility_team_id']    = $current['eligibility_team_id'];
		}
		$interval_changed = $prepared['start_datetime'] !== $this->storage_datetime( $current['start_datetime'] )
			|| $prepared['end_datetime'] !== $this->storage_datetime( $current['end_datetime'] );

		return $this->with_room_lock(
			(int) $prepared['room_id'],
			function () use ( $booking_id, $prepared, $current, $actor_user_id, $interval_changed ) {
				$conflict = $this->find_conflict( (int) $prepared['room_id'], $prepared['start_datetime'], $prepared['end_datetime'], $booking_id );
				if ( $conflict ) {
					return $this->conflict_error( $conflict );
				}

				$updates = $prepared;
				unset( $updates['status'], $updates['created_by_user_id'], $updates['original_end_datetime'] );
				$updates['last_changed_by_user_id'] = $actor_user_id;
				if ( $interval_changed ) {
					$updates['original_end_datetime'] = $prepared['end_datetime'];
					$updates['extended_until']        = '';
				}
				$result = Fields::update_many_for_post( $booking_id, $updates );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				wp_update_post(
					[
						'ID'         => $booking_id,
						'post_title' => $this->booking_title( $updates ),
					]
					);

				$booking = $this->format_booking( $booking_id, true );
				$this->record_activity( $booking_id, 'edited', $actor_user_id, $current, $booking );
				$booking['notification'] = BookingNotification::send_to_holder( $booking, 'edited', $actor_user_id );
				$new_presenters          = array_diff( $booking['authorized_presenter_user_ids'], $current['authorized_presenter_user_ids'] );
				BookingNotification::send_to_presenters( $booking, $new_presenters );
				return $booking;
			}
		);
	}

	/** Cancel and retain one reservation. */
	public function cancel_booking( int $booking_id, int $actor_user_id, string $reason, bool $manager ) {
		if ( get_post_type( $booking_id ) !== self::BOOKING_POST_TYPE ) {
			return new \WP_Error( 'rondo_room_booking_not_found', __( 'Reservering niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		$before = $this->format_booking( $booking_id, true );
		if ( $before['status'] === 'cancelled' ) {
			return $before;
		}
		if ( ! $manager && new DateTimeImmutable( $before['start_datetime'] ) <= current_datetime() ) {
			return new \WP_Error( 'rondo_room_booking_started', __( 'Een begonnen reservering kan alleen door een accommodatiebeheerder worden geannuleerd.', 'rondo' ), [ 'status' => 409 ] );
		}
		$reason = sanitize_textarea_field( $reason );
		if ( $manager && $reason === '' ) {
			return new \WP_Error( 'rondo_room_cancellation_reason_required', __( 'Geef een reden voor de annulering.', 'rondo' ), [ 'status' => 400 ] );
		}

		Fields::update_many_for_post(
			$booking_id,
			[
				'status'                  => 'cancelled',
				'cancelled_at'            => current_datetime()->format( 'Y-m-d H:i:s' ),
				'cancelled_by_user_id'    => $actor_user_id,
				'cancellation_reason'     => $reason,
				'last_changed_by_user_id' => $actor_user_id,
			]
		);
		$after = $this->format_booking( $booking_id, true );
		$this->record_activity( $booking_id, 'cancelled', $actor_user_id, $before, $after, $reason );
		do_action( 'rondo_room_presentation_stop', (int) $after['display_id'] );
		$after['notification'] = BookingNotification::send_to_holder( $after, 'cancelled', $actor_user_id );
		return $after;
	}

	/** Extend one currently active booking by the configured room increment. */
	public function extend_booking( int $booking_id, int $actor_user_id ) {
		if ( get_post_type( $booking_id ) !== self::BOOKING_POST_TYPE ) {
			return new \WP_Error( 'rondo_room_booking_not_found', __( 'Reservering niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		$before = $this->format_booking( $booking_id, true );
		$now    = current_datetime();
		if ( $before['status'] !== 'confirmed' || new DateTimeImmutable( $before['start_datetime'] ) > $now || new DateTimeImmutable( $before['effective_end_datetime'] ) <= $now ) {
			return new \WP_Error( 'rondo_room_booking_not_active', __( 'Alleen een actieve reservering kan worden verlengd.', 'rondo' ), [ 'status' => 409 ] );
		}
		$room      = $this->format_room( (int) $before['room_id'] );
		$increment = max( 5, (int) $room['extension_increment_minutes'] );
		$new_end   = ( new DateTimeImmutable( $before['effective_end_datetime'] ) )->add( new DateInterval( 'PT' . $increment . 'M' ) );
		$maximum   = ( new DateTimeImmutable( $before['start_datetime'] ) )->add( new DateInterval( 'PT' . (int) $room['maximum_duration_minutes'] . 'M' ) );
		if ( $new_end > $maximum || ! $this->within_opening_hours( $room, new DateTimeImmutable( $before['start_datetime'] ), $new_end ) ) {
			return new \WP_Error( 'rondo_room_extension_limit', __( 'Deze reservering kan niet verder worden verlengd.', 'rondo' ), [ 'status' => 409 ] );
		}

		return $this->with_room_lock(
			(int) $before['room_id'],
			function () use ( $booking_id, $actor_user_id, $before, $new_end ) {
				$conflict = $this->find_conflict( (int) $before['room_id'], $before['start_datetime'], $new_end->format( 'Y-m-d H:i:s' ), $booking_id );
				if ( $conflict ) {
					return $this->conflict_error( $conflict );
				}
				Fields::update_many_for_post(
					$booking_id,
					[
						'extended_until'          => $new_end->format( 'Y-m-d H:i:s' ),
						'last_changed_by_user_id' => $actor_user_id,
					]
				);
				$after = $this->format_booking( $booking_id, true );
				$this->record_activity( $booking_id, 'extended', $actor_user_id, $before, $after );
				$after['notification'] = BookingNotification::send_to_holder( $after, 'extended', $actor_user_id );
				return $after;
			}
		);
	}

	/** Manager override for an active booking's presentation entitlement. */
	public function set_presentation_override( int $booking_id, string $action, int $actor_user_id ) {
		if ( get_post_type( $booking_id ) !== self::BOOKING_POST_TYPE ) {
			return new \WP_Error( 'rondo_room_booking_not_found', __( 'Reservering niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		$value = [
			'start' => 'force_on',
			'stop'  => 'force_off',
			'reset' => 'inherit',
		][ $action ] ?? '';
		if ( $value === '' ) {
			return new \WP_Error( 'rondo_room_presentation_action_invalid', __( 'Ongeldige presentatieactie.', 'rondo' ), [ 'status' => 400 ] );
		}
		$before = $this->format_booking( $booking_id, true );
		$now    = current_datetime();
		if ( $action === 'start' && ( $before['status'] !== 'confirmed' || new DateTimeImmutable( $before['start_datetime'] ) > $now || new DateTimeImmutable( $before['effective_end_datetime'] ) <= $now ) ) {
			return new \WP_Error( 'rondo_room_booking_not_active', __( 'Presenteren kan alleen voor een actieve reservering worden gestart.', 'rondo' ), [ 'status' => 409 ] );
		}
		Fields::update_many_for_post(
			$booking_id,
			[
				'presentation_override'   => $value,
				'last_changed_by_user_id' => $actor_user_id,
			]
		);
		$after = $this->format_booking( $booking_id, true );
		$this->record_activity( $booking_id, $action === 'stop' ? 'presentation_stopped' : 'presentation_started', $actor_user_id, $before, $after );
		if ( $action === 'stop' ) {
			do_action( 'rondo_room_presentation_stop', (int) $after['display_id'] );
		}
		return $after;
	}

	/** Return bookings overlapping a requested range. */
	public function bookings_between( DateTimeImmutable $start, DateTimeImmutable $end, ?int $holder_user_id = null ): array {
		$ids      = $this->all_booking_ids();
		$bookings = [];
		foreach ( $ids as $booking_id ) {
			$booking = $this->format_booking( (int) $booking_id, true );
			if ( $holder_user_id !== null && (int) $booking['holder_user_id'] !== $holder_user_id ) {
				continue;
			}
			$booking_start = new DateTimeImmutable( $booking['start_datetime'] );
			$booking_end   = new DateTimeImmutable( $booking['effective_end_datetime'] );
			if ( $booking_start < $end && $booking_end > $start ) {
				$bookings[] = $booking;
			}
		}
		usort( $bookings, static fn( array $left, array $right ): int => strcmp( $left['start_datetime'], $right['start_datetime'] ) );
		return $bookings;
	}

	/** Full or presenter-safe representation for one booking. */
	public function format_booking( int $booking_id, bool $full = false ): array {
		$fields        = Fields::all_for_post( $booking_id );
		$room_id       = (int) ( $fields['room_id'] ?? 0 );
		$room          = $room_id ? $this->format_room( $room_id ) : null;
		$start         = $this->wire_datetime( (string) ( $fields['start_datetime'] ?? '' ) );
		$end           = $this->wire_datetime( (string) ( $fields['end_datetime'] ?? '' ) );
		$extended      = $this->wire_datetime( (string) ( $fields['extended_until'] ?? '' ) );
		$effective_end = $extended ?: $end;
		$status        = (string) ( $fields['status'] ?? 'confirmed' );
		if ( $status === 'confirmed' && $effective_end && new DateTimeImmutable( $effective_end ) <= current_datetime() ) {
			$status = 'completed';
		}

		$data = [
			'id'                            => $booking_id,
			'room_id'                       => $room_id,
			'room_name'                     => $room['name'] ?? '',
			'display_id'                    => (int) ( $room['display_id'] ?? 0 ),
			'booking_type'                  => (string) ( $fields['booking_type'] ?? 'member_reservation' ),
			'start_datetime'                => $start,
			'end_datetime'                  => $end,
			'effective_end_datetime'        => $effective_end,
			'original_end_datetime'         => $this->wire_datetime( (string) ( $fields['original_end_datetime'] ?? '' ) ),
			'extended_until'                => $extended,
			'purpose'                       => (string) ( $fields['purpose'] ?? '' ),
			'holder_user_id'                => (int) ( $fields['holder_user_id'] ?? 0 ),
			'holder_person_id'              => (int) ( $fields['holder_person_id'] ?? 0 ),
			'holder_name'                   => $this->holder_name( (int) ( $fields['holder_user_id'] ?? 0 ), (int) ( $fields['holder_person_id'] ?? 0 ) ),
			'booking_context_type'          => (string) ( $fields['booking_context_type'] ?? '' ),
			'commissie_id'                  => (int) ( $fields['commissie_id'] ?? 0 ),
			'age_group_key'                 => (string) ( $fields['age_group_key'] ?? '' ),
			'context_label'                 => (string) ( $fields['context_label_snapshot'] ?? '' ),
			'eligibility_team_id'           => (int) ( $fields['eligibility_team_id'] ?? 0 ),
			'authorized_presenter_user_ids' => array_values( array_map( 'absint', $fields['authorized_presenter_user_ids'] ?? [] ) ),
			'status'                        => $status,
			'presentation_override'         => (string) ( $fields['presentation_override'] ?? 'inherit' ),
			'presentation'                  => $this->presentation_summary( $booking_id, $room, $start, $effective_end, $status ),
		];
		if ( $full ) {
			$data += [
				'private_notes'           => (string) ( $fields['private_notes'] ?? '' ),
				'cancelled_at'            => $this->wire_datetime( (string) ( $fields['cancelled_at'] ?? '' ) ),
				'cancelled_by_user_id'    => (int) ( $fields['cancelled_by_user_id'] ?? 0 ),
				'cancellation_reason'     => (string) ( $fields['cancellation_reason'] ?? '' ),
				'created_by_user_id'      => (int) ( $fields['created_by_user_id'] ?? 0 ),
				'last_changed_by_user_id' => (int) ( $fields['last_changed_by_user_id'] ?? 0 ),
				'holder_email'            => UserProvisioning::contact_email( (int) ( $fields['holder_user_id'] ?? 0 ) ),
			];
		}
		return $data;
	}

	/** Public availability blocks without holder or purpose data. */
	public function availability( DateTimeImmutable $start, DateTimeImmutable $end ): array {
		return array_map(
			static fn( array $booking ): array => [
				'id'             => $booking['id'],
				'room_id'        => $booking['room_id'],
				'start_datetime' => $booking['start_datetime'],
				'end_datetime'   => $booking['effective_end_datetime'],
				'status'         => $booking['status'],
			],
			array_values( array_filter( $this->bookings_between( $start, $end ), static fn( array $booking ): bool => $booking['status'] !== 'cancelled' ) )
		);
	}

	/** Return immutable audit entries newest first. */
	public function activity( int $booking_id ): array {
		$comments = get_comments(
			[
				'post_id' => $booking_id,
				'type'    => self::ACTIVITY_TYPE,
				'status'  => 'approve',
				'order'   => 'DESC',
			]
		);
		return array_map(
			static fn( \WP_Comment $comment ): array => [
				'id'         => (int) $comment->comment_ID,
				'action'     => (string) get_comment_meta( $comment->comment_ID, 'action', true ),
				'actor_id'   => (int) $comment->user_id,
				'actor_name' => (string) $comment->comment_author,
				'created_at' => mysql_to_rfc3339( $comment->comment_date ),
				'changes'    => json_decode( (string) get_comment_meta( $comment->comment_ID, 'changes', true ), true ) ?: [],
				'reason'     => (string) get_comment_meta( $comment->comment_ID, 'reason', true ),
			],
			$comments
		);
	}

	/** Whether a user is holder, presenter, or accommodation manager. */
	public function user_can_read_booking( int $booking_id, int $user_id ): bool {
		if ( user_can( $user_id, 'manage_options' ) || user_can( $user_id, 'accommodatiebeheer' ) ) {
			return true;
		}
		$fields = Fields::all_for_post( $booking_id );
		return (int) ( $fields['holder_user_id'] ?? 0 ) === $user_id
			|| in_array( $user_id, array_map( 'absint', $fields['authorized_presenter_user_ids'] ?? [] ), true );
	}

	/** Whether a user owns a booking. */
	public function user_is_holder( int $booking_id, int $user_id ): bool {
		return get_post_type( $booking_id ) === self::BOOKING_POST_TYPE
			&& (int) Fields::get_for_post( $booking_id, 'holder_user_id' ) === $user_id;
	}

	/** Resolve current reservation-controlled presentation state for a display. */
	public function presentation_entitlement_for_display( int $display_id, int $user_id = 0 ): ?array {
		$room_id = $this->room_id_for_display( $display_id );
		if ( $room_id <= 0 || ! Fields::get_for_post( $room_id, 'presentation_controlled' ) ) {
			return null;
		}
		$now     = current_datetime();
		$room    = $this->format_room( $room_id );
		$booking = null;
		foreach ( $this->all_booking_ids() as $booking_id ) {
			if ( (int) Fields::get_for_post( (int) $booking_id, 'room_id' ) !== $room_id || Fields::get_for_post( (int) $booking_id, 'status' ) !== 'confirmed' ) {
				continue;
			}
			$candidate = $this->format_booking( (int) $booking_id, true );
			$start     = new DateTimeImmutable( $candidate['start_datetime'] );
			$access    = $start->sub( new DateInterval( 'PT' . max( 0, (int) $room['access_before_minutes'] ) . 'M' ) );
			$end       = new DateTimeImmutable( $candidate['effective_end_datetime'] );
			$override  = $candidate['presentation_override'];
			$is_active = $override === 'force_on' ? $now < $end : ( $now >= $access && $now < $end );
			if ( $override !== 'force_off' && $is_active ) {
				$booking = $candidate;
				break;
			}
		}
		if ( ! $booking ) {
			return [
				'controlled' => true,
				'allowed'    => false,
				'room_id'    => $room_id,
				'room_name'  => $room['name'],
			];
		}

		$allowed_user = $user_id <= 0
			|| (int) $booking['holder_user_id'] === $user_id
			|| in_array( $user_id, $booking['authorized_presenter_user_ids'], true )
			|| user_can( $user_id, 'accommodatiebeheer' )
			|| user_can( $user_id, 'manage_options' );
		return [
			'controlled' => true,
			'allowed'    => $allowed_user,
			'booking_id' => $booking['id'],
			'room_id'    => $room_id,
			'room_name'  => $room['name'],
			'starts_at'  => $booking['start_datetime'],
			'ends_at'    => $booking['effective_end_datetime'],
		];
	}

	/** Whether a display is governed by a room even when no booking is active. */
	public function display_is_reservation_controlled( int $display_id ): bool {
		$room_id = $this->room_id_for_display( $display_id );
		return $room_id > 0 && (bool) Fields::get_for_post( $room_id, 'presentation_controlled' );
	}

	/** Find the active room linked to a display. */
	public function room_id_for_display( int $display_id ): int {
		$rooms = get_posts(
			[
				'post_type'        => self::ROOM_POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'   => 'display_id',
						'value' => $display_id,
					],
					[
						'key'     => 'archived',
						'value'   => '1',
						'compare' => '!=',
					],
				],
			]
		);
		return (int) ( $rooms[0] ?? 0 );
	}

	/** Format one room. */
	public function format_room( int $room_id ): array {
		$fields = Fields::all_for_post( $room_id );
		return [
			'id'                          => $room_id,
			'name'                        => get_the_title( $room_id ),
			'location'                    => (string) ( $fields['location'] ?? '' ),
			'description'                 => (string) ( $fields['description'] ?? '' ),
			'capacity'                    => (int) ( $fields['capacity'] ?? 0 ),
			'facilities'                  => array_values( array_filter( array_map( static fn( $item ): string => (string) ( $item['name'] ?? '' ), $fields['facilities'] ?? [] ) ) ),
			'booking_enabled'             => (bool) ( $fields['booking_enabled'] ?? true ),
			'display_id'                  => (int) ( $fields['display_id'] ?? 0 ),
			'display_online'              => ! empty( $fields['display_id'] ) && get_transient( 'rondo_nc_online_' . (int) $fields['display_id'] ) !== false,
			'presentation_controlled'     => (bool) ( $fields['presentation_controlled'] ?? false ),
			'opening_hours'               => $fields['opening_hours'] ?: $this->default_opening_hours(),
			'minimum_duration_minutes'    => (int) ( $fields['minimum_duration_minutes'] ?: 30 ),
			'maximum_duration_minutes'    => (int) ( $fields['maximum_duration_minutes'] ?: 240 ),
			'booking_interval_minutes'    => (int) ( $fields['booking_interval_minutes'] ?: 15 ),
			'minimum_notice_minutes'      => (int) ( $fields['minimum_notice_minutes'] ?? 0 ),
			'maximum_advance_days'        => (int) ( $fields['maximum_advance_days'] ?: 90 ),
			'changeover_buffer_minutes'   => (int) ( $fields['changeover_buffer_minutes'] ?? 0 ),
			'access_before_minutes'       => (int) ( $fields['access_before_minutes'] ?? 5 ),
			'extension_increment_minutes' => (int) ( $fields['extension_increment_minutes'] ?: 15 ),
			'sort_order'                  => (int) ( $fields['sort_order'] ?? 0 ),
			'member_instructions'         => (string) ( $fields['member_instructions'] ?? '' ),
			'archived'                    => (bool) ( $fields['archived'] ?? false ),
		];
	}

	/** Parse a required API datetime and convert it to the site timezone. */
	public function parse_api_datetime( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' || preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $value ) !== 1 ) {
			return new \WP_Error( 'rondo_room_datetime_invalid', __( 'Gebruik een datum en tijd met expliciete tijdzone.', 'rondo' ), [ 'status' => 400 ] );
		}
		try {
			return ( new DateTimeImmutable( $value ) )->setTimezone( wp_timezone() );
		} catch ( \Exception $error ) {
			return new \WP_Error( 'rondo_room_datetime_invalid', __( 'De datum of tijd is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
		}
	}

	/** Prepare and validate all write fields shared by create and update. */
	private function prepare_booking_values( array $payload, int $holder_user_id, int $actor_user_id, bool $manager, string $booking_type, ?array $existing_context = null ) {
		$room_id = absint( $payload['room_id'] ?? 0 );
		if ( get_post_type( $room_id ) !== self::ROOM_POST_TYPE ) {
			return new \WP_Error( 'rondo_room_not_found', __( 'Ruimte niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		$room = $this->format_room( $room_id );
		if ( $room['archived'] || ( $booking_type === 'member_reservation' && ! $room['booking_enabled'] ) ) {
			return new \WP_Error( 'rondo_room_unavailable', __( 'Deze ruimte kan niet worden gereserveerd.', 'rondo' ), [ 'status' => 409 ] );
		}

		$start = $this->parse_api_datetime( $payload['start_datetime'] ?? '' );
		$end   = $this->parse_api_datetime( $payload['end_datetime'] ?? '' );
		if ( is_wp_error( $start ) || is_wp_error( $end ) ) {
			return is_wp_error( $start ) ? $start : $end;
		}
		$policy = $this->validate_interval( $room, $start, $end, $manager, $booking_type );
		if ( is_wp_error( $policy ) ) {
			return $policy;
		}

		$purpose = sanitize_text_field( (string) ( $payload['purpose'] ?? '' ) );
		if ( $purpose === '' ) {
			return new \WP_Error( 'rondo_room_purpose_required', __( 'Geef kort aan waarvoor je de ruimte reserveert.', 'rondo' ), [ 'status' => 400 ] );
		}

		$context = $existing_context;
		if ( $booking_type === 'member_reservation' ) {
			if ( ! get_userdata( $holder_user_id ) ) {
				return new \WP_Error( 'rondo_room_holder_invalid', __( 'Kies een geldige reserveringshouder.', 'rondo' ), [ 'status' => 400 ] );
			}
			if ( ! $context ) {
				$context_type = sanitize_key( (string) ( $payload['booking_context_type'] ?? '' ) );
				$context      = BookingEligibility::match(
					$holder_user_id,
					$context_type,
					absint( $payload['commissie_id'] ?? 0 ),
					(string) ( $payload['age_group_key'] ?? '' )
				);
			}
			if ( ! $context ) {
				return new \WP_Error( 'rondo_room_context_forbidden', __( 'De reserveringshouder heeft geen actuele vrijwilligersfunctie voor deze commissie of jaarlaag.', 'rondo' ), [ 'status' => 403 ] );
			}
		}

		$presenters = array_values( array_unique( array_filter( array_map( 'absint', $payload['authorized_presenter_user_ids'] ?? [] ) ) ) );
		foreach ( $presenters as $presenter_id ) {
			if ( ! get_userdata( $presenter_id ) ) {
				return new \WP_Error( 'rondo_room_presenter_invalid', __( 'Een gekozen presentator bestaat niet meer.', 'rondo' ), [ 'status' => 400 ] );
			}
		}

		$person_id = $holder_user_id ? (int) get_user_meta( $holder_user_id, 'rondo_linked_person_id', true ) : 0;
		return [
			'room_id'                       => $room_id,
			'start_datetime'                => $start->format( 'Y-m-d H:i:s' ),
			'end_datetime'                  => $end->format( 'Y-m-d H:i:s' ),
			'booking_type'                  => $booking_type,
			'purpose'                       => $purpose,
			'private_notes'                 => sanitize_textarea_field( (string) ( $payload['private_notes'] ?? '' ) ),
			'holder_user_id'                => $holder_user_id,
			'holder_person_id'              => $person_id ?: null,
			'booking_context_type'          => $context['type'] ?? '',
			'commissie_id'                  => $context['commissie_id'] ?? null,
			'age_group_key'                 => $context['age_group_key'] ?? '',
			'context_label_snapshot'        => $context['label'] ?? '',
			'eligibility_team_id'           => $context['eligibility_team_id'] ?? null,
			'authorized_presenter_user_ids' => $presenters,
			'status'                        => 'confirmed',
			'created_by_user_id'            => $actor_user_id,
			'last_changed_by_user_id'       => $actor_user_id,
			'original_end_datetime'         => $end->format( 'Y-m-d H:i:s' ),
			'extended_until'                => '',
			'presentation_override'         => 'inherit',
		];
	}

	private function validate_interval( array $room, DateTimeImmutable $start, DateTimeImmutable $end, bool $manager, string $booking_type ) {
		if ( $end <= $start ) {
			return new \WP_Error( 'rondo_room_interval_invalid', __( 'De eindtijd moet na de starttijd liggen.', 'rondo' ), [ 'status' => 400 ] );
		}
		$minutes  = (int) round( ( $end->getTimestamp() - $start->getTimestamp() ) / 60 );
		$interval = max( 5, (int) $room['booking_interval_minutes'] );
		if ( ( (int) $start->format( 'i' ) % $interval ) !== 0 || ( (int) $end->format( 'i' ) % $interval ) !== 0 ) {
			/* translators: %d: booking interval in minutes. */
			return new \WP_Error( 'rondo_room_interval_step_invalid', sprintf( __( 'Kies tijden op stappen van %d minuten.', 'rondo' ), $interval ), [ 'status' => 400 ] );
		}
		if ( $booking_type !== 'management_block' && ! $this->within_opening_hours( $room, $start, $end ) ) {
			return new \WP_Error( 'rondo_room_closed', __( 'De gekozen tijd valt buiten de openingstijden van deze ruimte.', 'rondo' ), [ 'status' => 409 ] );
		}
		if ( ! $manager ) {
			if ( $minutes < (int) $room['minimum_duration_minutes'] || $minutes > (int) $room['maximum_duration_minutes'] ) {
				return new \WP_Error( 'rondo_room_duration_invalid', __( 'De gekozen reserveringsduur is niet toegestaan.', 'rondo' ), [ 'status' => 400 ] );
			}
			$now = current_datetime();
			if ( $start < $now->add( new DateInterval( 'PT' . max( 0, (int) $room['minimum_notice_minutes'] ) . 'M' ) ) ) {
				return new \WP_Error( 'rondo_room_notice_invalid', __( 'Deze reservering begint te snel.', 'rondo' ), [ 'status' => 409 ] );
			}
			if ( $start > $now->add( new DateInterval( 'P' . max( 1, (int) $room['maximum_advance_days'] ) . 'D' ) ) ) {
				return new \WP_Error( 'rondo_room_advance_invalid', __( 'Deze reservering ligt te ver in de toekomst.', 'rondo' ), [ 'status' => 409 ] );
			}
		}
		return true;
	}

	private function within_opening_hours( array $room, DateTimeImmutable $start, DateTimeImmutable $end ): bool {
		if ( $start->format( 'Y-m-d' ) !== $end->format( 'Y-m-d' ) ) {
			return false;
		}
		$day = (int) $start->format( 'N' );
		foreach ( $room['opening_hours'] as $window ) {
			if ( (int) ( $window['day'] ?? 0 ) !== $day ) {
				continue;
			}
			$open  = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $start->format( 'Y-m-d' ) . ' ' . ( $window['start_time'] ?? '' ), wp_timezone() );
			$close = DateTimeImmutable::createFromFormat( 'Y-m-d H:i', $start->format( 'Y-m-d' ) . ' ' . ( $window['end_time'] ?? '' ), wp_timezone() );
			if ( $open && $close && $start >= $open && $end <= $close ) {
				return true;
			}
		}
		return false;
	}

	private function find_conflict( int $room_id, string $start_value, string $end_value, int $exclude_id = 0 ): ?array {
		$start  = new DateTimeImmutable( $start_value, wp_timezone() );
		$end    = new DateTimeImmutable( $end_value, wp_timezone() );
		$buffer = max( 0, (int) Fields::get_for_post( $room_id, 'changeover_buffer_minutes' ) );
		if ( $buffer > 0 ) {
			$start = $start->sub( new DateInterval( 'PT' . $buffer . 'M' ) );
			$end   = $end->add( new DateInterval( 'PT' . $buffer . 'M' ) );
		}
		foreach ( $this->all_booking_ids() as $booking_id ) {
			$booking_id = (int) $booking_id;
			if ( $booking_id === $exclude_id || (int) Fields::get_for_post( $booking_id, 'room_id' ) !== $room_id || Fields::get_for_post( $booking_id, 'status' ) !== 'confirmed' ) {
				continue;
			}
			$other_start = new DateTimeImmutable( (string) Fields::get_for_post( $booking_id, 'start_datetime' ), wp_timezone() );
			$extended    = (string) Fields::get_for_post( $booking_id, 'extended_until' );
			$other_end   = new DateTimeImmutable( $extended ?: (string) Fields::get_for_post( $booking_id, 'end_datetime' ), wp_timezone() );
			if ( $start < $other_end && $end > $other_start ) {
				return $this->format_booking( $booking_id, true );
			}
		}
		return null;
	}

	private function conflict_error( array $booking ): \WP_Error {
		return new \WP_Error(
			'rondo_room_conflict',
			__( 'Deze ruimte is op dat moment al bezet.', 'rondo' ),
			[
				'status'   => 409,
				'conflict' => [
					'start_datetime' => $booking['start_datetime'],
					'end_datetime'   => $booking['effective_end_datetime'],
				],
			]
		);
	}

	private function with_room_lock( int $room_id, callable $callback ) {
		$key      = 'rondo_room_write_lock_' . $room_id;
		$deadline = microtime( true ) + self::LOCK_TIMEOUT_SECONDS;
		$token    = wp_generate_uuid4();
		do {
			wp_cache_get( $key, 'options', true );
			if ( add_option(
				$key,
				[
					'token'      => $token,
					'created_at' => microtime( true ),
				],
				'',
				false
				) ) {
				try {
					return $callback();
				} finally {
					wp_cache_get( $key, 'options', true );
					$current = get_option( $key, [] );
					if ( is_array( $current ) && ( $current['token'] ?? '' ) === $token ) {
						delete_option( $key );
					}
				}
			}
			wp_cache_get( $key, 'options', true );
			$current = get_option( $key, [] );
			if ( is_array( $current ) && (float) ( $current['created_at'] ?? 0 ) < microtime( true ) - self::LOCK_STALE_SECONDS ) {
				delete_option( $key );
			}
			usleep( 25000 );
		} while ( microtime( true ) < $deadline );
		return new \WP_Error( 'rondo_room_busy', __( 'Deze ruimte wordt op dit moment bijgewerkt. Probeer het opnieuw.', 'rondo' ), [ 'status' => 503 ] );
	}

	private function record_activity( int $booking_id, string $action, int $actor_user_id, array $before, array $after, string $reason = '' ): void {
		$actor   = get_userdata( $actor_user_id );
		$tracked = [ 'room_id', 'start_datetime', 'effective_end_datetime', 'purpose', 'holder_user_id', 'context_label', 'authorized_presenter_user_ids', 'status', 'presentation_override' ];
		$changes = [];
		foreach ( $tracked as $field ) {
			$old = $before[ $field ] ?? null;
			$new = $after[ $field ] ?? null;
			if ( maybe_serialize( $old ) !== maybe_serialize( $new ) ) {
				$changes[ $field ] = [
					'before' => $old,
					'after'  => $new,
				];
			}
		}
		$comment_id = wp_insert_comment(
			[
				'comment_post_ID'  => $booking_id,
				'comment_content'  => sanitize_text_field( $action ),
				'comment_type'     => self::ACTIVITY_TYPE,
				'comment_approved' => 1,
				'user_id'          => $actor_user_id,
				'comment_author'   => $actor ? $actor->display_name : 'Rondo',
				'comment_date'     => current_time( 'mysql' ),
			]
		);
		if ( $comment_id ) {
			add_comment_meta( $comment_id, 'action', $action, true );
			add_comment_meta( $comment_id, 'changes', wp_json_encode( $changes ), true );
			if ( $reason !== '' ) {
				add_comment_meta( $comment_id, 'reason', $reason, true );
			}
		}
	}

	private function presentation_summary( int $booking_id, ?array $room, ?string $start, ?string $end, string $status ): array {
		if ( ! $room || ! $room['display_id'] || ! $room['presentation_controlled'] || $status !== 'confirmed' || ! $start || ! $end ) {
			return [
				'available'     => false,
				'available_now' => false,
			];
		}
		$access_start = ( new DateTimeImmutable( $start ) )->sub( new DateInterval( 'PT' . max( 0, (int) $room['access_before_minutes'] ) . 'M' ) );
		$access_end   = new DateTimeImmutable( $end );
		$now          = current_datetime();
		$override     = (string) Fields::get_for_post( $booking_id, 'presentation_override' );
		$available    = $override !== 'force_off';
		return [
			'available'        => $available,
			'available_now'    => $available && ( $override === 'force_on' ? $now < $access_end : ( $now >= $access_start && $now < $access_end ) ),
			'display_online'   => (bool) ( $room['display_online'] ?? false ),
			'access_starts_at' => $access_start->format( DATE_RFC3339 ),
			'access_ends_at'   => $access_end->format( DATE_RFC3339 ),
		];
	}

	private function sanitize_opening_hours( $value ) {
		if ( $value === null ) {
			return null;
		}
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'rondo_room_opening_hours_invalid', __( 'Openingstijden moeten een lijst zijn.', 'rondo' ), [ 'status' => 400 ] );
		}
		$windows = [];
		foreach ( $value as $window ) {
			$day   = absint( $window['day'] ?? 0 );
			$start = trim( (string) ( $window['start_time'] ?? '' ) );
			$end   = trim( (string) ( $window['end_time'] ?? '' ) );
			if ( $day < 1 || $day > 7 || preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start ) !== 1 || preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end ) !== 1 || $end <= $start ) {
				return new \WP_Error( 'rondo_room_opening_hours_invalid', __( 'Een openingstijd is ongeldig.', 'rondo' ), [ 'status' => 400 ] );
			}
			$windows[] = [
				'day'        => $day,
				'start_time' => $start,
				'end_time'   => $end,
			];
		}
		return $windows;
	}

	private function default_opening_hours(): array {
		$hours = [];
		for ( $day = 1; $day <= 7; $day++ ) {
			$hours[] = [
				'day'        => $day,
				'start_time' => '08:00',
				'end_time'   => '23:00',
			];
		}
		return $hours;
	}

	private function bounded_int( array $payload, string $key, int $current, int $minimum, int $maximum ): int {
		$value = array_key_exists( $key, $payload ) ? (int) $payload[ $key ] : $current;
		return max( $minimum, min( $maximum, $value ) );
	}

	private function all_booking_ids(): array {
		return get_posts(
			[
				'post_type'        => self::BOOKING_POST_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);
	}

	private function booking_title( array $values ): string {
		$room_name = get_the_title( (int) $values['room_id'] );
		return sprintf( '%s · %s · %s', $room_name, (string) $values['start_datetime'], (string) $values['purpose'] );
	}

	private function holder_name( int $user_id, int $person_id ): string {
		if ( $person_id > 0 && get_post_type( $person_id ) === 'person' ) {
			return get_the_title( $person_id );
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : '';
	}

	private function wire_datetime( string $value ): ?string {
		if ( trim( $value ) === '' ) {
			return null;
		}
		try {
			return ( new DateTimeImmutable( $value, wp_timezone() ) )->format( DATE_RFC3339 );
		} catch ( \Exception $error ) {
			return null;
		}
	}

	private function storage_datetime( ?string $value ): string {
		if ( ! $value ) {
			return '';
		}
		try {
			return ( new DateTimeImmutable( $value ) )->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $error ) {
			return '';
		}
	}
}
