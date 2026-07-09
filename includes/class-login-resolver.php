<?php
/**
 * LoginResolver
 *
 * Members do not know the username Rondo generated for them, and the second member of
 * a household cannot log in with the family email address — that address belongs to
 * the first claimant's account (see ContactEmailRouter for the why).
 *
 * So accept two extra identifiers and translate them to the real `user_login` before
 * WordPress authenticates:
 *
 *   - the KNVB-ID (`_rondo_knvb_id`), which every playing member has on their card
 *   - the real contact address (`rondo_contact_email`), when it identifies exactly one
 *     member — for a shared family mailbox it does not, and we leave it alone
 *
 * We hook the `wp_authenticate` action, which passes `$username` by reference, rather
 * than the `authenticate` filter. That way core still performs the password check, the
 * rate limiting, and every security filter hanging off it. We only rewrite the identifier.
 *
 * That action fires inside `wp_signon()` — which is what `wp-login.php` calls — and NOT
 * inside `wp_authenticate()`. Any test must drive `wp_signon()` or it will silently
 * exercise nothing.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoginResolver {

	public function __construct() {
		add_action( 'wp_authenticate', [ $this, 'resolve_identifier' ], 10, 2 );
	}

	/**
	 * Rewrite an alternative identifier to the user's real `user_login`.
	 *
	 * Leaves `$username` untouched whenever it already identifies a user, is empty, or
	 * is ambiguous. Never reveals whether an identifier exists — an unresolved value
	 * simply falls through to core's normal "unknown username" failure.
	 *
	 * @param string $username Login identifier, by reference.
	 * @param string $password Password, by reference. Unused, required by the hook.
	 */
	public function resolve_identifier( &$username, &$password ) {
		$identifier = trim( (string) $username );

		if ( $identifier === '' ) {
			return;
		}

		// Already a username, or an email core can resolve on its own.
		if ( get_user_by( 'login', $identifier ) || get_user_by( 'email', $identifier ) ) {
			return;
		}

		$resolved = $this->user_by_knvb_id( $identifier ) ?? $this->user_by_contact_email( $identifier );

		if ( $resolved ) {
			$username = $resolved->user_login;
		}
	}

	/**
	 * The single user carrying this KNVB-ID, or null.
	 *
	 * @param string $knvb_id Candidate KNVB member ID.
	 * @return \WP_User|null
	 */
	private function user_by_knvb_id( string $knvb_id ): ?\WP_User {
		return $this->single_user_by_meta( UserProvisioning::META_KNVB_ID, $knvb_id );
	}

	/**
	 * The single user whose real address is this, or null.
	 *
	 * A shared household mailbox matches several members. That is ambiguous, so we
	 * refuse to guess and let the login fail — those members sign in with their
	 * KNVB-ID instead.
	 *
	 * @param string $email Candidate contact address.
	 * @return \WP_User|null
	 */
	private function user_by_contact_email( string $email ): ?\WP_User {
		if ( ! is_email( $email ) ) {
			return null;
		}

		return $this->single_user_by_meta( UserProvisioning::META_CONTACT_EMAIL, $email );
	}

	/**
	 * Look up a user by meta, returning null unless exactly one matches.
	 *
	 * @param string $meta_key   Meta key to match.
	 * @param string $meta_value Value to match.
	 * @return \WP_User|null
	 */
	private function single_user_by_meta( string $meta_key, string $meta_value ): ?\WP_User {
		$users = get_users(
			[
				'meta_key'   => $meta_key,
				'meta_value' => $meta_value,
				'number'     => 2,
			]
		);

		return count( $users ) === 1 ? $users[0] : null;
	}
}
