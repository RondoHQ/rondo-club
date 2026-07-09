<?php
/**
 * ContactEmailRouter
 *
 * Households share one mailbox, but WordPress insists on a unique `user_email`. The
 * second and later members of a household therefore carry an undeliverable
 * `.invalid` placeholder, with their real address in `rondo_contact_email` user meta.
 *
 * WordPress itself does not know about that meta. Core mails — most importantly the
 * password-reset link from `retrieve_password()` — are addressed straight to
 * `user_email`. Without this router those mails would vanish and the account would be
 * unrecoverable.
 *
 * So: rewrite every synthetic recipient to the member's real address, and drop it
 * entirely when no real address is known. Fail closed — a mail that cannot reach the
 * right person must not reach the wrong one.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ContactEmailRouter {

	public function __construct() {
		add_filter( 'wp_mail', [ __CLASS__, 'reroute_synthetic_recipients' ] );
	}

	/**
	 * Replace `.invalid` placeholder recipients with the member's real address.
	 *
	 * @param array $atts wp_mail() arguments.
	 * @return array Same arguments, with `to` rewritten.
	 */
	public static function reroute_synthetic_recipients( $atts ) {
		$recipients = $atts['to'] ?? [];

		if ( is_string( $recipients ) ) {
			$recipients = array_map( 'trim', explode( ',', $recipients ) );
		}

		$rewritten = [];

		foreach ( (array) $recipients as $recipient ) {
			if ( ! UserProvisioning::is_synthetic_email( (string) $recipient ) ) {
				$rewritten[] = $recipient;
				continue;
			}

			$real = self::real_address_for( (string) $recipient );

			if ( $real ) {
				$rewritten[] = $real;
			}
			// No real address on file: drop this recipient rather than send into the void.
		}

		$atts['to'] = $rewritten;

		return $atts;
	}

	/**
	 * The real address behind a synthetic placeholder, or null.
	 *
	 * @param string $synthetic The `.invalid` address.
	 * @return string|null
	 */
	private static function real_address_for( string $synthetic ): ?string {
		$user = get_user_by( 'email', $synthetic );

		if ( ! $user ) {
			return null;
		}

		return UserProvisioning::contact_email( $user->ID );
	}
}
