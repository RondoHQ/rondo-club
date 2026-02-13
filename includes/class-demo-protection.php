<?php
/**
 * Demo User Protection
 *
 * Prevents the demo user on demo sites from changing their password,
 * email, or accessing the WordPress profile page.
 *
 * @package Rondo\Demo
 */

namespace Rondo\Demo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DemoProtection {

	public function __construct() {
		// Only activate on demo sites.
		if ( ! get_option( 'rondo_is_demo_site', false ) ) {
			return;
		}

		// Block wp-admin profile page for demo user.
		add_action( 'admin_init', [ $this, 'block_profile_page' ] );

		// Prevent password/email changes for demo user via any method.
		add_filter( 'wp_pre_insert_user_data', [ $this, 'protect_demo_user_data' ], 10, 4 );
	}

	/**
	 * Check if a given user ID belongs to the demo user.
	 *
	 * @param int|null $user_id User ID. Defaults to current user.
	 * @return bool
	 */
	private function is_demo_user( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );

		return $user && $user->user_login === 'demo';
	}

	/**
	 * Redirect demo user away from wp-admin/profile.php.
	 */
	public function block_profile_page() {
		global $pagenow;

		if ( 'profile.php' === $pagenow && $this->is_demo_user() ) {
			wp_safe_redirect( home_url() );
			exit;
		}
	}

	/**
	 * Prevent password and email changes for the demo user.
	 *
	 * Fires on all user updates (admin, REST API, WP-CLI) via wp_insert_user().
	 *
	 * @param array    $data    User data to be inserted/updated.
	 * @param bool     $update  Whether this is an update (true) or insert (false).
	 * @param int|null $user_id The user ID being updated, or null for new users.
	 * @param array    $userdata Raw userdata passed to wp_insert_user().
	 * @return array Modified user data.
	 */
	public function protect_demo_user_data( $data, $update, $user_id, $userdata ) {
		if ( ! $update || ! $user_id ) {
			return $data;
		}

		$user = get_userdata( $user_id );

		if ( ! $user || 'demo' !== $user->user_login ) {
			return $data;
		}

		// Preserve existing password hash — blocks all password changes.
		$data['user_pass'] = $user->user_pass;

		// Preserve existing email.
		$data['user_email'] = $user->user_email;

		return $data;
	}
}
