<?php
/**
 * Capability Sync Service
 *
 * Reconciles WordPress user roles against Sportlink Functies by granting and
 * revoking roles based on the admin-configured FunctieCapabilityMap. Supports
 * manual override tracking so admin-granted or admin-revoked roles survive
 * automatic sync runs.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability sync service class.
 *
 * No hooks registered in constructor — pure service class, instantiated
 * on-demand by REST API callbacks. PSR-4 autoloaded.
 */
class CapabilitySync {

	/**
	 * User meta key for manually-granted roles (set by admin override).
	 * JSON-encoded array of role slugs.
	 *
	 * @var string
	 */
	const META_MANUAL_GRANTS = '_rondo_cap_manual_grants';

	/**
	 * User meta key for manually-revoked roles (set by admin override).
	 * JSON-encoded array of role slugs.
	 *
	 * @var string
	 */
	const META_MANUAL_REVOKES = '_rondo_cap_manual_revokes';

	/**
	 * Sync capabilities for a single WordPress user given their current Functies.
	 *
	 * Reconciles the user's syncable roles against the FunctieCapabilityMap,
	 * respecting manual override grants and revokes. Never touches rondo_user
	 * (the base role) or any roles assigned to administrator users.
	 *
	 * @param int   $user_id  WordPress user ID.
	 * @param array $functies Array of Functie strings (active ones from Sportlink).
	 * @return array|\WP_Error Result array with status and changes, or WP_Error.
	 */
	public function sync_user( int $user_id, array $functies ): array|\WP_Error {
		// Load the user.
		$user = new \WP_User( $user_id );
		if ( ! $user->exists() ) {
			return new \WP_Error(
				'user_not_found',
				sprintf( 'User %d not found.', $user_id ),
				[ 'status' => 404 ]
			);
		}

		// Administrator guard (CAPS-07): never touch administrator users.
		if ( in_array( 'administrator', $user->roles, true ) ) {
			return [
				'status' => 'skipped',
				'reason' => 'administrator',
			];
		}

		// Syncable roles: all Rondo roles except the base rondo_user role.
		// rondo_user is managed by provisioning/deletion only, never by sync.
		$syncable_roles = array_values(
			array_diff( \Rondo\Core\UserRoles::get_role_slugs(), [ 'rondo_user' ] )
		);

		// Compute mapped roles from Functies via FunctieCapabilityMap.
		$mapped_roles = [];
		foreach ( $functies as $functie ) {
			$roles = \Rondo\Config\FunctieCapabilityMap::get_roles_for_functie( $functie );
			foreach ( $roles as $role ) {
				if ( in_array( $role, $syncable_roles, true ) ) {
					$mapped_roles[] = $role;
				}
			}
		}
		$mapped_roles = array_unique( $mapped_roles );

		// Read manual overrides from user meta.
		$manual_grants_raw  = get_user_meta( $user_id, self::META_MANUAL_GRANTS, true );
		$manual_revokes_raw = get_user_meta( $user_id, self::META_MANUAL_REVOKES, true );

		$manual_grants  = (array) json_decode( $manual_grants_raw ?: '[]', true );
		$manual_revokes = (array) json_decode( $manual_revokes_raw ?: '[]', true );

		// Target roles = (mapped ∪ manual_grants) − manual_revokes, filtered to syncable.
		$target_roles = array_diff(
			array_intersect(
				array_unique( array_merge( $mapped_roles, $manual_grants ) ),
				$syncable_roles
			),
			$manual_revokes
		);
		$target_roles = array_values( $target_roles );

		// Current syncable roles on this user.
		$current_roles = array_values(
			array_intersect( (array) $user->roles, $syncable_roles )
		);

		// Compute diff.
		$to_grant  = array_values( array_diff( $target_roles, $current_roles ) );
		$to_revoke = array_values( array_diff( $current_roles, $target_roles ) );

		// Apply changes using add_role()/remove_role() — NEVER set_role() which wipes all roles.
		foreach ( $to_grant as $role ) {
			$user->add_role( $role );
		}
		foreach ( $to_revoke as $role ) {
			$user->remove_role( $role );
		}

		// Re-read current syncable roles after changes.
		$updated_user          = new \WP_User( $user_id );
		$current_roles_updated = array_values(
			array_intersect( (array) $updated_user->roles, $syncable_roles )
		);

		return [
			'status'        => 'synced',
			'user_id'       => $user_id,
			'granted'       => $to_grant,
			'revoked'       => $to_revoke,
			'current_roles' => $current_roles_updated,
		];
	}

	/**
	 * Sync capabilities for all provisioned WordPress users.
	 *
	 * Iterates all users with a KNVB ID stored in user meta. If $knvb_functies_map
	 * is provided, reads functies from it; otherwise falls back to deriving functies
	 * from the linked person's work_history ACF field (is_current entries only).
	 *
	 * @param array $knvb_functies_map Optional map of knvb_id => string[] functies.
	 *                                  Pass empty array to derive from ACF work_history.
	 * @return array Aggregated result: { total, synced, skipped, errors, details }.
	 */
	public function sync_all( array $knvb_functies_map = [] ): array {
		// Get all provisioned users (users who have a KNVB ID in user meta).
		$provisioned_users = get_users(
			[
				'meta_key'     => \Rondo\Users\UserProvisioning::META_KNVB_ID,
				'meta_compare' => '!=',
				'meta_value'   => '',
				'number'       => -1,
			]
		);

		$result = [
			'total'   => count( $provisioned_users ),
			'synced'  => 0,
			'skipped' => 0,
			'errors'  => [],
			'details' => [],
		];

		foreach ( $provisioned_users as $wp_user ) {
			$knvb_id = get_user_meta( $wp_user->ID, \Rondo\Users\UserProvisioning::META_KNVB_ID, true );
			if ( ! $knvb_id ) {
				continue;
			}

			// Determine functies: from provided map, or derive from ACF work_history.
			if ( ! empty( $knvb_functies_map ) && isset( $knvb_functies_map[ $knvb_id ] ) ) {
				$functies = (array) $knvb_functies_map[ $knvb_id ];
			} else {
				$functies = $this->derive_functies_from_work_history( $wp_user->ID );
			}

			$sync_result = $this->sync_user( $wp_user->ID, $functies );

			if ( is_wp_error( $sync_result ) ) {
				$result['errors'][] = [
					'user_id' => $wp_user->ID,
					'knvb_id' => $knvb_id,
					'message' => $sync_result->get_error_message(),
				];
			} elseif ( isset( $sync_result['status'] ) && 'skipped' === $sync_result['status'] ) {
				$result['skipped']++;
			} else {
				$result['synced']++;
			}

			$result['details'][] = [
				'user_id' => $wp_user->ID,
				'knvb_id' => $knvb_id,
				'result'  => $sync_result,
			];
		}

		return $result;
	}

	/**
	 * Sync capabilities for a single user identified by KNVB ID.
	 *
	 * Returns a no_user result (not a WP_Error) if no WordPress user has
	 * the given KNVB ID — this is expected for members without an account.
	 *
	 * @param string $knvb_id The Sportlink KNVB member ID.
	 * @param array  $functies Array of active Functie strings from Sportlink.
	 * @return array|\WP_Error Result array, or WP_Error on failure.
	 */
	public function sync_user_by_knvb_id( string $knvb_id, array $functies ): array|\WP_Error {
		// Look up WP user by KNVB ID.
		$users = get_users(
			[
				'meta_key'   => \Rondo\Users\UserProvisioning::META_KNVB_ID,
				'meta_value' => $knvb_id,
				'number'     => 1,
			]
		);

		// No provisioned user found — this is expected (not an error).
		if ( empty( $users ) ) {
			return [
				'status'  => 'no_user',
				'knvb_id' => $knvb_id,
			];
		}

		return $this->sync_user( $users[0]->ID, $functies );
	}

	/**
	 * Derive functies from a user's linked person's work_history ACF field.
	 *
	 * Reads the `work_history` repeater field on the linked person post and
	 * returns job_title values for entries where is_current is truthy.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string[] Array of active Functie strings.
	 */
	private function derive_functies_from_work_history( int $user_id ): array {
		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		if ( ! $person_id ) {
			return [];
		}

		$work_history = get_field( 'work_history', $person_id );
		if ( ! is_array( $work_history ) ) {
			return [];
		}

		$functies = [];
		foreach ( $work_history as $job ) {
			if ( ! empty( $job['job_title'] ) && ! empty( $job['is_current'] ) ) {
				$functies[] = $job['job_title'];
			}
		}

		return $functies;
	}
}
