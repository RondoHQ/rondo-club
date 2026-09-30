<?php
/** Selective, permission-scoped transfers of existing shift registrations. */

namespace Rondo\REST;

use Rondo\Core\AccessControl;
use Rondo\Core\PostTitle;
use Rondo\Data\InverseRelationships;
use Rondo\Fields\Fields;
use Rondo\Volunteer\ShiftAssignments;
use Rondo\Volunteer\ShiftEmailScheduler;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;

trait ShiftTransfers {
	/** Board membership is explicit: unrelated section capabilities do not suffice. */
	private function can_transfer_outside_family(): bool {
		return current_user_can( 'manage_options' ) || in_array( 'rondo_bestuur', wp_get_current_user()->roles, true );
	}

	public function check_shift_transfer_permission( $request ): bool {
		return ( $this->can_transfer_outside_family() || current_user_can( 'ledenadministratie' ) )
			&& $this->transfer_person_visible( (int) $request['person_id'] );
	}

	private function transfer_person_visible( int $id ): bool {
		return get_post_type( $id ) === 'person' && get_post_status( $id ) === 'publish'
			&& ( new AccessControl() )->user_can_access_post( $id );
	}

	private function register_shift_transfer_routes(): void {
		$base = '/people/(?P<person_id>\d+)/shift-transfer';
		$args = [
			'target_id' => [
				'type'     => 'integer',
				'minimum'  => 1,
				'required' => true,
			],
			'shift_ids' => [
				'type'        => 'array',
				'required'    => true,
				'minItems'    => 1,
				'uniqueItems' => true,
				'items'       => [
					'type'    => 'integer',
					'minimum' => 1,
				],
			],
		];
		register_rest_route(
			'rondo/v1',
			$base,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_shift_transfer_options' ],
				'permission_callback' => [ $this, 'check_shift_transfer_permission' ],
				'args'                => [
					'search' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
			);
		register_rest_route(
			'rondo/v1',
			$base . '/preview',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'preview_shift_transfer' ],
				'permission_callback' => [ $this, 'check_shift_transfer_permission' ],
				'args'                => $args,
			]
			);
		$args['token'] = [
			'type'     => 'string',
			'required' => true,
			'pattern'  => '^[a-f0-9]{64}$',
		];
		register_rest_route(
			'rondo/v1',
			$base,
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'execute_shift_transfer' ],
				'permission_callback' => [ $this, 'check_shift_transfer_permission' ],
				'args'                => $args,
			]
			);
	}

	/** Immediate family only; do not traverse an unbounded family graph. */
	private function transfer_family_ids( int $source ): array {
		$relations = static function ( int $person, array $types ): array {
			$ids = [];
			foreach ( (array) Fields::get_for_post( $person, 'relationships' ) as $row ) {
				if ( in_array( (int) ( $row['relationship_type'] ?? 0 ), $types, true ) ) {
					$id = (int) ( $row['related_person'] ?? 0 );
					if ( get_post_type( $id ) === 'person' && get_post_status( $id ) === 'publish' ) {
						$ids[] = $id;
					}
				}
			}
			return $ids;
		};
		$parents   = $relations( $source, [ InverseRelationships::TYPE_PARENT ] );
		$children  = $relations( $source, [ InverseRelationships::TYPE_CHILD ] );
		$family    = array_merge( $parents, $children, $relations( $source, [ InverseRelationships::TYPE_SIBLING ] ) );
		foreach ( $parents as $parent ) {
			$family = array_merge( $family, $relations( $parent, [ InverseRelationships::TYPE_CHILD ] ) );
		}
		foreach ( $children as $child ) {
			$family = array_merge( $family, $relations( $child, [ InverseRelationships::TYPE_PARENT ] ) );
		}
		return array_values( array_diff( array_unique( $family ), [ $source ] ) );
	}

	public function get_shift_transfer_options( \WP_REST_Request $request ) {
		$source = (int) $request['person_id'];
		$family = $this->transfer_family_ids( $source );
		$search = trim( (string) $request['search'] );
		$ids    = $family;
		if ( $this->can_transfer_outside_family() && $search !== '' ) {
			$ids = ctype_digit( $search ) ? [ (int) $search ] : get_posts(
				[
					'post_type'        => 'person',
					'post_status'      => 'publish',
					's'                => $search,
					'posts_per_page'   => 20,
					'fields'           => 'ids',
					'suppress_filters' => false,
				]
				);
		}
		$people = [];
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( $id !== $source && $this->transfer_person_visible( $id ) ) {
				$name = PostTitle::plain( $id );
				if ( $this->can_transfer_outside_family() || $search === '' || stripos( $name, $search ) !== false || (string) $id === $search ) {
					$people[] = [
						'id'   => $id,
						'name' => $name,
					];
				}
			}
		}
		$shifts = array_values( array_filter( $this->query_shifts_for_person( $source, false, -1 ), static fn( $shift ) => in_array( $shift['status'], [ 'open', 'vol', 'voltooid' ], true ) ) );
		return rest_ensure_response(
			[
				'people'      => $people,
				'shifts'      => $shifts,
				'family_only' => ! $this->can_transfer_outside_family(),
			]
			);
	}

	private function validate_transfer_pair( int $source, int $target ) {
		if ( $source === $target || ! $this->transfer_person_visible( $target ) ) {
			return new \WP_Error( 'invalid_transfer_target', 'Kies een andere toegankelijke persoon.', [ 'status' => 400 ] );
		}
		if ( ! $this->can_transfer_outside_family() && ! in_array( $target, $this->transfer_family_ids( $source ), true ) ) {
			return new \WP_Error( 'transfer_family_only', 'Ledenadministratie mag inschrijftaken alleen binnen dezelfde familie overzetten.', [ 'status' => 403 ] );
		}
		return true;
	}

	/** Include numbered reminder keys and attendance, without guessing a fixed prefix list. */
	private function transfer_registration_meta( int $shift, int $person ): array {
		$values = [];
		foreach ( get_post_meta( $shift ) as $key => $rows ) {
			if ( preg_match( '/^(?:_shift_.+|_no_show)_' . $person . '$/', $key ) ) {
				$values[ $key ] = array_map( 'maybe_unserialize', $rows );
			}
		}
		ksort( $values );
		return $values;
	}

	/** Validate the entire selection before writing any registrations. */
	private function shift_transfer_plan( int $source, int $target, array $ids ) {
		$permission = $this->validate_transfer_pair( $source, $target );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$plan = [];
		foreach ( $ids as $id ) {
			if ( get_post_type( $id ) !== 'dienst_shift' || get_post_status( $id ) !== 'publish' ) {
				return new \WP_Error( 'invalid_transfer_shift', 'Een geselecteerde inschrijftaak bestaat niet meer.', [ 'status' => 409 ] );
			}
			$status   = Fields::get_for_post( $id, 'status' );
			$assigned = ShiftAssignments::person_ids( $id );
			if ( ! in_array( $status, [ 'open', 'vol', 'voltooid' ], true ) || ! in_array( $source, $assigned, true ) ) {
				return new \WP_Error( 'transfer_shift_changed', 'Een geselecteerde inschrijftaak is gewijzigd. Controleer de selectie opnieuw.', [ 'status' => 409 ] );
			}
			if ( in_array( $target, $assigned, true ) || $this->transfer_registration_meta( $id, $target ) ) {
				return new \WP_Error( 'transfer_registration_conflict', 'De ontvanger heeft al een registratie of registratiehistorie bij ' . PostTitle::plain( $id ) . '.', [ 'status' => 409 ] );
			}
			if ( in_array( $status, [ 'open', 'vol' ], true ) ) {
				if ( ! ( new VolunteerEligibilityService() )->may_volunteer( $target ) ) {
					return new \WP_Error( 'not_eligible', 'Deze persoon kan niet voor een ingeplande inschrijftaak worden aangemeld.', [ 'status' => 403 ] );
				}
				$blocked = $this->assert_person_may_take_shift( $target, $id );
				if ( is_wp_error( $blocked ) ) {
					return $blocked;
				}
				if ( $this->find_overlapping_shift( $target, $id ) !== null ) {
					return new \WP_Error( 'transfer_overlap', 'De ontvanger heeft al een overlappende inschrijftaak.', [ 'status' => 409 ] );
				}
			}
			$plan[] = [
				'id'             => $id,
				'title'          => PostTitle::plain( $id ),
				'status'         => $status,
				'start'          => Fields::get_for_post( $id, 'start_datetime' ),
				'end'            => Fields::get_for_post( $id, 'end_datetime' ),
				'dienst_type_id' => Fields::get_for_post( $id, 'dienst_type_id' ),
				'capacity'       => Fields::get_for_post( $id, 'capacity' ),
				'iva_waived'     => Fields::get_for_post( $id, 'iva_waived' ),
				'assigned'       => $assigned,
				'meta'           => $this->transfer_registration_meta( $id, $source ),
			];
		}
		return $plan;
	}

	private function shift_transfer_token( int $source, int $target, array $plan ): string {
		return hash_hmac( 'sha256', wp_json_encode( [ get_current_user_id(), $source, $target, $plan ] ), wp_salt( 'nonce' ) );
	}

	public function preview_shift_transfer( \WP_REST_Request $request ) {
		$source = (int) $request['person_id'];
		$target = (int) $request['target_id'];
		$ids    = $request['shift_ids'];
		sort( $ids, SORT_NUMERIC );
		$plan = $this->shift_transfer_plan( $source, $target, $ids );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}
		return rest_ensure_response(
			[
				'token'  => $this->shift_transfer_token( $source, $target, $plan ),
				'source' => [
					'id'   => $source,
					'name' => PostTitle::plain( $source ),
				],
				'target' => [
					'id'   => $target,
					'name' => PostTitle::plain( $target ),
				],
				'count'  => count( $plan ),
			]
			);
	}

	/** Acquire all existing assignment locks in order, without waiting while holding one. */
	private function with_transfer_locks( array $ids, callable $callback ) {
		if ( ! $ids ) {
			return $callback();
		}
		$id = array_shift( $ids );
		return $this->with_shift_write_lock( $id, fn() => $this->with_transfer_locks( $ids, $callback ), 0 );
	}

	public function execute_shift_transfer( \WP_REST_Request $request ) {
		$source = (int) $request['person_id'];
		$target = (int) $request['target_id'];
		$ids    = $request['shift_ids'];
		sort( $ids, SORT_NUMERIC );
		return $this->with_transfer_locks(
			$ids,
			function () use ( $source, $target, $ids, $request ) {
				$permission = $this->validate_transfer_pair( $source, $target );
				if ( is_wp_error( $permission ) ) {
					return $permission;
				}
				$token     = (string) $request['token'];
				$audit_key = '_rondo_shift_transfer_' . $token;
				$replayed  = true;
				foreach ( $ids as $id ) {
					$audit    = get_post_meta( $id, $audit_key, true );
					$replayed = $replayed && is_array( $audit ) && $audit['source_id'] === $source && $audit['target_id'] === $target && $audit['user_id'] === get_current_user_id() && $audit['shift_ids'] === $ids;
				}
				if ( $replayed ) {
					return rest_ensure_response(
						[
							'transferred'         => $ids,
							'already_transferred' => true,
						]
						);
				}
				$plan = $this->shift_transfer_plan( $source, $target, $ids );
				if ( is_wp_error( $plan ) ) {
					return $plan;
				}
				if ( ! hash_equals( $this->shift_transfer_token( $source, $target, $plan ), $token ) ) {
					return new \WP_Error( 'transfer_preview_expired', 'De inschrijftaken zijn gewijzigd. Controleer het overzicht opnieuw.', [ 'status' => 409 ] );
				}
				$changed = [];
				try {
					foreach ( $plan as $item ) {
						$id        = $item['id'];
						$changed[] = $item;
						$assigned  = array_map( static fn( $person ) => $person === $source ? $target : $person, $item['assigned'] );
						Fields::update_for_post( $id, 'assigned_persons', $assigned );
						if ( ShiftAssignments::person_ids( $id ) !== $assigned ) {
							throw new \RuntimeException( 'Assignment write failed' );
						}
						foreach ( $item['meta'] as $key => $values ) {
							$target_key = substr( $key, 0, -strlen( (string) $source ) ) . $target;
							foreach ( $values as $value ) {
								if ( ! add_post_meta( $id, $target_key, wp_slash( $value ) ) ) {
									throw new \RuntimeException( 'Registration write failed' );
								}
							}
							if ( ! delete_post_meta( $id, $key ) ) {
								throw new \RuntimeException( 'Registration removal failed' );
							}
						}
						$audit = [
							'source_id' => $source,
							'target_id' => $target,
							'user_id'   => get_current_user_id(),
							'at'        => current_time( 'mysql' ),
							'shift_ids' => $ids,
							'before'    => $item,
						];
						if ( ! add_post_meta( $id, $audit_key, wp_slash( $audit ), true ) ) {
							throw new \RuntimeException( 'Audit write failed' );
						}
					}
				} catch ( \Throwable $error ) {
					foreach ( $changed as $item ) {
						Fields::update_for_post( $item['id'], 'assigned_persons', $item['assigned'] );
						foreach ( $item['meta'] as $key => $values ) {
							delete_post_meta( $item['id'], substr( $key, 0, -strlen( (string) $source ) ) . $target );
							delete_post_meta( $item['id'], $key );
							foreach ( $values as $value ) {
								add_post_meta( $item['id'], $key, wp_slash( $value ) );
							}
						}
						delete_post_meta( $item['id'], $audit_key );
					}
					VolunteerObligationCalculator::invalidate_cache();
					return new \WP_Error( 'transfer_failed', 'Overzetten is niet gelukt. De registraties zijn teruggezet; laad de gegevens opnieuw.', [ 'status' => 500 ] );
				}
				// Keep an already pending confirmation scheduled for its new registration owner.
				foreach ( $plan as $item ) {
					if ( isset( $item['meta'][ '_shift_confirmation_queued_at_' . $source ] ) && ! wp_next_scheduled( ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $target ] ) ) {
						wp_schedule_single_event( time() + MINUTE_IN_SECONDS, ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $target ] );
					}
				}
				VolunteerObligationCalculator::invalidate_cache();
				return rest_ensure_response(
					[
						'transferred'         => $ids,
						'already_transferred' => false,
					]
					);
			}
			);
	}
}
