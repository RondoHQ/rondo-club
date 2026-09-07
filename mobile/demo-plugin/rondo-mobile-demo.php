<?php
/**
 * Plugin Name: Rondo Demo Mobile Pilot
 * Description: Read-only synthetic review account access on the dedicated demo site.
 * Version: 0.9.0
 *
 * @package Rondo\MobileDemo
 */
namespace Rondo\MobileDemo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once dirname( __DIR__ ) . '/pilot-plugin/rondo-mobile-pilot.php';

/** Same pilot protocol and checks; separate origin, callback and explicit configuration. */
final class Plugin extends \Rondo\MobilePilot\Plugin {
	protected const ORIGIN        = 'https://demo.rondo.club';
	protected const FLAG          = 'RONDO_MOBILE_DEMO';
	protected const CONFIG_OPTION = 'rondo_mobile_demo';
	public const CALLBACK         = 'https://demo.rondo.club/rondo-app/callback';

	public static function enabled(): bool {
		return parent::enabled() && (bool) get_option( 'rondo_is_demo_site', false );
	}

	protected static function user_allowed( \WP_User $user ): bool {
		$person_id = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
		return parent::user_allowed( $user )
			&& $user->roles === [ 'subscriber' ]
			&& (bool) get_user_meta( $user->ID, '_rondo_synthetic_apple_review', true )
			&& get_post_meta( $person_id, '_rondo_feature_demo_key', true ) === 'parent';
	}
}

new Plugin();
