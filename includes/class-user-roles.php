<?php
/**
 * User Roles for Rondo
 *
 * Registers custom user roles for Rondo users with minimal permissions.
 * Base roles are hardcoded; custom roles are admin-created and stored in a wp_option.
 */

namespace Rondo\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserRoles {

	const ROLE_NAME                     = 'rondo_user';
	const ROLE_DISPLAY_NAME             = 'Rondo User';
	const FAIRPLAY_CAPABILITY           = 'fairplay';
	const VOG_CAPABILITY                = 'vog';
	const FINANCIEEL_CAPABILITY         = 'financieel';
	const FINANCIEEL_READ_CAPABILITY    = 'financieel_read';
	const TOEGANG_CAPABILITY            = 'toegangscontrole';
	const CLOTHING_CAPABILITY           = 'manage_clothing';
	const LEDENADMINISTRATIE_CAPABILITY = 'ledenadministratie';
	const SPONSORBEHEER_CAPABILITY      = 'sponsorbeheer';
	const VRIJWILLIGERS_CAPABILITY      = 'vrijwilligers';
	const IVA_APPROVE_CAPABILITY        = 'rondo_iva_approve';

	/**
	 * Option key holding the schema version of the registered roles.
	 * Bump ROLES_VERSION whenever BASE_ROLES gains a capability that existing
	 * installs must also receive; add_role() does not touch existing roles.
	 */
	const ROLES_VERSION_OPTION = 'rondo_roles_version';
	const ROLES_VERSION        = 6;

	/** Generic WordPress write capabilities removed from non-admin Rondo roles. */
	private const LEGACY_GENERIC_WRITE_CAPS = [
		'edit_posts',
		'publish_posts',
		'delete_posts',
		'edit_published_posts',
		'delete_published_posts',
		'upload_files',
	];

	/**
	 * WordPress option key for admin-created custom roles.
	 * Stored as [ slug => label ] associative array.
	 */
	const CUSTOM_ROLES_OPTION = 'rondo_custom_roles';

	/**
	 * Built-in Rondo roles: slug => [ display_name, extra capabilities ]
	 * Each role gets the base rondo_user capabilities plus the listed extras.
	 * Custom roles are stored separately in the rondo_custom_roles wp_option.
	 */
	const BASE_ROLES = [
		'rondo_user'               => [ 'Rondo User', [] ],
		'rondo_fairplay'           => [ 'Rondo FairPlay', [ 'fairplay' ] ],
		'rondo_vog'                => [ 'Rondo VOG', [ 'vog' ] ],
		'rondo_financieel'         => [ 'Rondo Financieel', [ 'financieel', 'financieel_read' ] ],
		'rondo_financieel_lezen'   => [ 'Rondo Financieel Lezen', [ 'financieel_read' ] ],
		'rondo_toegangscontrole'   => [ 'Rondo Toegangscontrole', [ 'toegangscontrole' ] ],
		'rondo_clothing_manager'   => [ 'Rondo Kledingbeheer', [ 'manage_clothing' ] ],
		'rondo_ledenadministratie' => [ 'Rondo Ledenadministratie', [ 'ledenadministratie' ] ],
		'rondo_sponsorbeheerder'   => [ 'Rondo Sponsorbeheerder', [ 'sponsorbeheer' ] ],
		'rondo_vrijwilligers'      => [ 'Rondo Vrijwilligers', [ 'vrijwilligers' ] ],
		'rondo_iva_approver'       => [ 'Rondo IVA Goedkeurder (Bestuurslid Kantine)', [ 'rondo_iva_approve', 'vrijwilligers' ] ],
		'rondo_pool_schoonmaak'    => [ 'Rondo Schoonmaakpoule', [] ],
		'rondo_pool_activiteiten'  => [ 'Rondo Activiteitenpoule', [] ],
		'rondo_pool_werkploeg'     => [ 'Rondo Werkploeg terreinonderhoud', [] ],
		'rondo_bestuur'            => [ 'Rondo Bestuur', [ 'fairplay', 'vog', 'financieel', 'financieel_read', 'toegangscontrole', 'manage_clothing', 'ledenadministratie', 'sponsorbeheer', 'vrijwilligers', 'rondo_iva_approve' ] ],
	];

	/**
	 * May the user see finance data (facturen, contributie, betaalstatus)?
	 *
	 * `financieel` implies read: a custom role may carry the write capability
	 * alone, and a treasurer who cannot read their own invoices is nonsense.
	 *
	 * @param int|null $user_id User ID, defaults to the current user.
	 * @return bool True if the user may view finance data.
	 */
	public static function can_view_finances( $user_id = null ) {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		return user_can( $user_id, self::FINANCIEEL_READ_CAPABILITY )
			|| user_can( $user_id, self::FINANCIEEL_CAPABILITY );
	}

	/**
	 * May the user change finance data — create, send, delete, mark paid?
	 *
	 * @param int|null $user_id User ID, defaults to the current user.
	 * @return bool True if the user may write finance data.
	 */
	public static function can_manage_finances( $user_id = null ) {
		$user_id = $user_id ?? get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		return user_can( $user_id, self::FINANCIEEL_CAPABILITY );
	}

	public function __construct() {
		// Register role on theme activation
		add_action( 'after_switch_theme', [ $this, 'register_role' ] );

		// Remove role on theme deactivation
		add_action( 'switch_theme', [ $this, 'remove_role' ] );

		// Ensure role exists on init (in case theme was already active)
		add_action( 'init', [ $this, 'ensure_role_exists' ], 20 );

		// Backfill capabilities added to BASE_ROLES after this install was created.
		add_action( 'init', [ $this, 'maybe_upgrade_roles' ], 21 );

		// Delete user's posts when user is deleted
		add_action( 'delete_user', [ $this, 'delete_user_posts' ], 10, 1 );
	}

	/**
	 * Get all Rondo roles: base + custom.
	 *
	 * Returns the same shape as BASE_ROLES: slug => [ display_name, extra_capabilities ].
	 * Custom roles always have empty extra capabilities (managed via capability matrix).
	 *
	 * @return array<string, array{0: string, 1: string[]}> All roles.
	 */
	public static function get_all_roles(): array {
		$roles = self::BASE_ROLES;

		foreach ( self::get_custom_roles() as $slug => $label ) {
			$roles[ $slug ] = [ $label, [] ];
		}

		return $roles;
	}

	/**
	 * Get admin-created custom roles from wp_option.
	 *
	 * @return array<string, string> Slug => label pairs. Empty array if none.
	 */
	public static function get_custom_roles(): array {
		$custom = get_option( self::CUSTOM_ROLES_OPTION, [] );
		return is_array( $custom ) ? $custom : [];
	}

	/**
	 * Create a new custom role.
	 *
	 * Generates a slug from the label with rondo_ prefix, registers the WP role
	 * with base capabilities, and stores it in the custom roles option.
	 *
	 * @param string $label Human-readable role name (e.g. "Coördinator Pupillen").
	 * @return string|\WP_Error The role slug on success, or WP_Error on failure.
	 */
	public static function add_custom_role( string $label ): string|\WP_Error {
		$label = trim( $label );

		if ( empty( $label ) ) {
			return new \WP_Error(
				'empty_label',
				'Role label cannot be empty.',
				[ 'status' => 400 ]
			);
		}

		// Generate slug: rondo_ prefix + sanitized label.
		$slug = 'rondo_' . sanitize_title( $label );

		// Ensure no conflict with existing WP roles (base + custom + core).
		if ( get_role( $slug ) ) {
			return new \WP_Error(
				'role_exists',
				sprintf( 'A role with slug "%s" already exists.', $slug ),
				[ 'status' => 409 ]
			);
		}

		// New roles start at the least-privilege member baseline.
		$capabilities = self::base_capabilities();

		$result = add_role( $slug, $label, $capabilities );

		if ( ! $result ) {
			return new \WP_Error(
				'role_creation_failed',
				'WordPress add_role() failed.',
				[ 'status' => 500 ]
			);
		}

		self::sync_role_capabilities( $slug );

		// Persist in custom roles option.
		$custom          = self::get_custom_roles();
		$custom[ $slug ] = $label;
		update_option( self::CUSTOM_ROLES_OPTION, $custom );

		return $slug;
	}

	/**
	 * Delete a custom role.
	 *
	 * Removes the WP role, strips it from all users who have it, and removes
	 * it from the custom roles option. Base roles cannot be deleted.
	 *
	 * @param string $slug Role slug to remove.
	 * @return true|\WP_Error True on success, or WP_Error on failure.
	 */
	public static function remove_custom_role( string $slug ): true|\WP_Error {
		// Prevent deleting base roles.
		if ( isset( self::BASE_ROLES[ $slug ] ) || $slug === 'administrator' ) {
			return new \WP_Error(
				'base_role_protected',
				sprintf( 'Cannot delete built-in role "%s".', $slug ),
				[ 'status' => 403 ]
			);
		}

		$custom = self::get_custom_roles();

		if ( ! isset( $custom[ $slug ] ) ) {
			return new \WP_Error(
				'role_not_found',
				sprintf( 'Custom role "%s" not found.', $slug ),
				[ 'status' => 404 ]
			);
		}

		// Remove the role from all users who have it.
		$users = get_users( [ 'role' => $slug ] );
		foreach ( $users as $user ) {
			$user->remove_role( $slug );
		}

		// Remove the WP role definition.
		remove_role( $slug );

		// Remove from stored custom roles.
		unset( $custom[ $slug ] );
		update_option( self::CUSTOM_ROLES_OPTION, $custom );

		return true;
	}

	/**
	 * Ensure all roles exist (for themes already active)
	 */
	public function ensure_role_exists() {
		foreach ( self::get_all_roles() as $slug => $_ ) {
			if ( ! get_role( $slug ) ) {
				$this->register_role();
				return;
			}
		}
	}

	/**
	 * Backfill capabilities that BASE_ROLES gained after this install was created.
	 *
	 * `add_role()` is a no-op for a role that already exists, so widening BASE_ROLES
	 * never reaches installs that registered the role earlier. Roles are also live,
	 * admin-edited state (see the capability matrix in Settings → Admin), which rules
	 * out simply re-adding them.
	 *
	 * Version 2: every role holding `financieel` also gets `financieel_read`.
	 * Version 3: generic post/media access is replaced by dedicated CPT caps.
	 * Version 4: the Sponsorbeheerder role and `sponsorbeheer` capability are added.
	 * Version 5: `vrijwilligers` roles gain person edit primitives for contact fields.
	 * Version 6: administrators gain primitives for the private narrowcasting display CPT.
	 */
	public function maybe_upgrade_roles() {
		$installed_version = (int) get_option( self::ROLES_VERSION_OPTION, 0 );
		if ( $installed_version >= self::ROLES_VERSION ) {
			return;
		}

		$slugs = array_merge( self::get_role_slugs(), [ 'administrator' ] );

		foreach ( $slugs as $slug ) {
			$role = get_role( $slug );
			if ( ! $role ) {
				continue;
			}

			if ( ! empty( $role->capabilities[ self::FINANCIEEL_CAPABILITY ] )
				&& empty( $role->capabilities[ self::FINANCIEEL_READ_CAPABILITY ] ) ) {
				$role->add_cap( self::FINANCIEEL_READ_CAPABILITY );
			}

			if ( $installed_version < 4
				&& in_array( $slug, [ 'rondo_sponsorbeheerder', 'rondo_bestuur', 'administrator' ], true ) ) {
				$role->add_cap( self::SPONSORBEHEER_CAPABILITY );
			}

			self::sync_role_capabilities( $slug );
		}

		update_option( self::ROLES_VERSION_OPTION, self::ROLES_VERSION );
	}

	/**
	 * Register all Rondo roles (base + custom)
	 */
	public function register_role() {
		$base_capabilities = $this->get_role_capabilities();

		foreach ( self::get_all_roles() as $slug => [ $display_name, $extra_caps ] ) {
			$capabilities = $base_capabilities;
			foreach ( $extra_caps as $cap ) {
				$capabilities[ $cap ] = true;
			}
			add_role( $slug, $display_name, $capabilities );
			self::sync_role_capabilities( $slug );
		}

		// Add app-specific capabilities to administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( self::FAIRPLAY_CAPABILITY );
			$admin_role->add_cap( self::VOG_CAPABILITY );
			$admin_role->add_cap( self::FINANCIEEL_CAPABILITY );
			$admin_role->add_cap( self::FINANCIEEL_READ_CAPABILITY );
			$admin_role->add_cap( self::TOEGANG_CAPABILITY );
			$admin_role->add_cap( self::CLOTHING_CAPABILITY );
			$admin_role->add_cap( self::LEDENADMINISTRATIE_CAPABILITY );
			$admin_role->add_cap( self::SPONSORBEHEER_CAPABILITY );
			$admin_role->add_cap( self::VRIJWILLIGERS_CAPABILITY );
			$admin_role->add_cap( self::IVA_APPROVE_CAPABILITY );
			self::sync_role_capabilities( 'administrator' );
		}
	}

	/**
	 * Synchronize the dedicated CPT capabilities derived from business caps.
	 *
	 * @param string $slug WordPress role slug.
	 */
	public static function sync_role_capabilities( string $slug ): void {
		$role = get_role( $slug );
		if ( ! $role ) {
			return;
		}

		$is_admin = $slug === 'administrator';

		if ( ! $is_admin ) {
			foreach ( self::LEGACY_GENERIC_WRITE_CAPS as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		// Always rebuild derived primitives so capability-matrix removals propagate.
		foreach ( array_keys( PostTypes::CAPABILITY_DOMAINS ) as $post_type ) {
			foreach ( array_values( PostTypes::capability_map( $post_type ) ) as $cap ) {
				$role->remove_cap( $cap );
			}
		}

		$desired = [ 'read' ];

		if ( $is_admin ) {
			foreach ( array_keys( PostTypes::CAPABILITY_DOMAINS ) as $post_type ) {
				$desired = array_merge( $desired, self::cpt_capabilities( $post_type, 'manage' ) );
			}
			$desired[] = 'upload_files';
		} else {
			// Core member surfaces: household people plus read-only club structure.
			foreach ( [ 'person', 'team', 'commissie' ] as $post_type ) {
				$desired = array_merge( $desired, self::cpt_capabilities( $post_type, 'read' ) );
			}

			if ( $role->has_cap( self::FAIRPLAY_CAPABILITY ) ) {
				$desired = array_merge(
					$desired,
					self::cpt_capabilities( 'person', 'manage' ),
					self::cpt_capabilities( 'discipline_case', 'read' )
				);
			}

			if ( $role->has_cap( self::VOG_CAPABILITY ) ) {
				$desired = array_merge( $desired, self::cpt_capabilities( 'person', 'manage' ) );
			}

			if ( $role->has_cap( self::LEDENADMINISTRATIE_CAPABILITY ) ) {
				$desired = array_merge( $desired, self::cpt_capabilities( 'person', 'manage' ) );
			}

			if ( $role->has_cap( self::SPONSORBEHEER_CAPABILITY ) ) {
				$desired = array_merge( $desired, self::cpt_capabilities( 'person', 'manage' ) );
			}

			if ( $role->has_cap( self::FINANCIEEL_CAPABILITY ) ) {
				$desired = array_merge(
					$desired,
					self::cpt_capabilities( 'person', 'manage' ),
					self::cpt_capabilities( 'rondo_invoice', 'manage' ),
					self::cpt_capabilities( 'discipline_case', 'read' )
				);
			} elseif ( $role->has_cap( self::FINANCIEEL_READ_CAPABILITY ) ) {
				$desired = array_merge(
					$desired,
					self::cpt_capabilities( 'rondo_invoice', 'read' ),
					self::cpt_capabilities( 'discipline_case', 'read' )
				);
			}

			if ( $role->has_cap( self::CLOTHING_CAPABILITY ) ) {
				$desired = array_merge(
					$desired,
					self::cpt_capabilities( 'rondo_clothing_item', 'manage' ),
					self::cpt_capabilities( 'rondo_clothing_txn', 'manage' )
				);
			}

			if ( $role->has_cap( self::VRIJWILLIGERS_CAPABILITY ) ) {
				foreach ( [ 'dienst_type', 'shift_template', 'dienst_shift', 'taakuitleg' ] as $post_type ) {
					$desired = array_merge( $desired, self::cpt_capabilities( $post_type, 'manage' ) );
				}

				// Coordinators run into wrong phone numbers and e-mail addresses
				// constantly. `edit` — not `manage` — so they can correct a record
				// but never create or delete one. Which *fields* they may write is
				// narrowed separately by AccessControl::CONTACT_WRITE_FIELDS.
				$desired = array_merge( $desired, self::cpt_capabilities( 'person', 'edit' ) );
			}

			if (
				$role->has_cap( self::FAIRPLAY_CAPABILITY )
				|| $role->has_cap( self::VOG_CAPABILITY )
				|| $role->has_cap( self::FINANCIEEL_CAPABILITY )
				|| $role->has_cap( self::CLOTHING_CAPABILITY )
				|| $role->has_cap( self::LEDENADMINISTRATIE_CAPABILITY )
				|| $role->has_cap( self::SPONSORBEHEER_CAPABILITY )
				|| $role->has_cap( self::VRIJWILLIGERS_CAPABILITY )
			) {
				$desired[] = 'upload_files';
			}
		}

		foreach ( array_unique( $desired ) as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Primitive capabilities for a CPT access level.
	 *
	 * @param string $post_type Registered post type.
	 * @param string $level     `read` or `manage`.
	 * @return string[]
	 */
	private static function cpt_capabilities( string $post_type, string $level ): array {
		$map = PostTypes::capability_map( $post_type );
		if ( empty( $map ) ) {
			return [];
		}

		$read = array_values(
			array_filter(
				[
					$map['read'] ?? null,
					$map['read_post'] ?? null,
					$map['read_private_posts'] ?? null,
				]
			)
		);

		if ( $level === 'read' ) {
			return $read;
		}

		// `edit` sits between read and manage: change existing records, but never
		// create or delete them. Volunteer coordinators correct contact details on
		// members whose records belong to Sportlink — creating or deleting a person
		// is a different decision with a different owner.
		if ( $level === 'edit' ) {
			return array_values(
				array_unique(
					array_merge(
						$read,
						array_values(
							array_filter(
								[
									$map['edit_post'] ?? null,
									$map['edit_posts'] ?? null,
									$map['edit_others_posts'] ?? null,
									$map['edit_published_posts'] ?? null,
									$map['edit_private_posts'] ?? null,
								]
							)
						)
					)
				)
			);
		}

		return array_values( array_unique( $map ) );
	}

	/**
	 * Remove all Rondo roles (base + custom) on theme deactivation
	 */
	public function remove_role() {
		// Remove app-specific capabilities from administrator role
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->remove_cap( self::FAIRPLAY_CAPABILITY );
			$admin_role->remove_cap( self::VOG_CAPABILITY );
			$admin_role->remove_cap( self::FINANCIEEL_CAPABILITY );
			$admin_role->remove_cap( self::FINANCIEEL_READ_CAPABILITY );
			$admin_role->remove_cap( self::TOEGANG_CAPABILITY );
			$admin_role->remove_cap( self::CLOTHING_CAPABILITY );
			$admin_role->remove_cap( self::LEDENADMINISTRATIE_CAPABILITY );
			$admin_role->remove_cap( self::SPONSORBEHEER_CAPABILITY );
			$admin_role->remove_cap( self::VRIJWILLIGERS_CAPABILITY );
			$admin_role->remove_cap( self::IVA_APPROVE_CAPABILITY );
		}

		foreach ( self::get_all_roles() as $slug => $_ ) {
			// Reassign users to subscriber before removing role
			$users = get_users( [ 'role' => $slug ] );
			foreach ( $users as $user ) {
				$user->set_role( 'subscriber' );
			}
			remove_role( $slug );
		}
	}

	/**
	 * Get capabilities for Rondo User role
	 *
	 * Plain members receive only WordPress login/read access here. Dedicated
	 * read capabilities for their household and club structure are derived by
	 * sync_role_capabilities().
	 */
	private function get_role_capabilities() {
		return self::base_capabilities();
	}

	/** @return array<string, bool> */
	private static function base_capabilities(): array {
		return [ 'read' => true ];
	}


	/**
	 * Get all Rondo role slugs (base + custom)
	 *
	 * @return string[] Array of role slugs.
	 */
	public static function get_role_slugs() {
		return array_keys( self::get_all_roles() );
	}

	/**
	 * Check if a user has any Rondo role (base or custom)
	 *
	 * @param \WP_User $user User to check.
	 * @return bool True if user has any Rondo role.
	 */
	public static function has_rondo_role( $user ) {
		return ! empty( array_intersect( self::get_role_slugs(), $user->roles ) );
	}

	/**
	 * Check if a user ID is valid
	 *
	 * Kept for backward compatibility with existing code.
	 * Simply returns true if user ID exists.
	 *
	 * @param int $user_id User ID to check.
	 * @return bool True if user ID is valid.
	 */
	public static function is_user_approved( $user_id ) {
		return (bool) $user_id;
	}


	/**
	 * Delete all posts belonging to a user when user is deleted
	 * This is called by WordPress before the user is actually deleted
	 */
	public function delete_user_posts( $user_id ) {
		$post_types = [ 'person', 'team' ];

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				[
					'post_type'      => $post_type,
					'author'         => $user_id,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				]
			);

			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true ); // Force delete (bypass trash)
			}
		}
	}
}
