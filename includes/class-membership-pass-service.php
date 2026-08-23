<?php
/**
 * Authenticated membership pass actions and eligibility summaries.
 */

namespace Rondo\Passes;

use Rondo\Core\AccessControl;
use Rondo\Core\SponsorStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembershipPassService {

	const ACTION                            = 'rondo_membership_pass_wallet';
	const NONCE_ACTION_PREFIX               = 'rondo_membership_pass_wallet';
	const LEGACY_TOKEN_META_KEY             = '_membership_pass_token';
	const LEGACY_URL_META_KEY               = '_membership_pass_url';
	const LEGACY_BACKFILL_OPTION            = 'rondo_membership_pass_backfill_v2_done';
	const LEGACY_CLEANUP_OPTION             = 'rondo_membership_pass_private_actions_v1_done';
	const SPONSOR_PASS_VARIANT_BUSINESSCLUB = 'businessclub';
	const SPONSOR_PASS_VARIANT_AWC_SPONSOR  = 'awc_sponsor';

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_wallet_action' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'reject_unauthenticated_action' ] );
		add_action( 'init', [ $this, 'maybe_remove_legacy_public_pass_data' ], 20 );
	}

	/**
	 * Return the client-safe membership pass summary for one person.
	 *
	 * @param int $person_id Person post ID.
	 * @return array|null
	 */
	public static function get_person_pass_summary( int $person_id ): ?array {
		$post = get_post( $person_id );
		if ( ! $post || $post->post_type !== 'person' ) {
			return null;
		}

		$member_tier = self::get_person_member_tier( $person_id );
		if ( $member_tier === '' ) {
			return null;
		}

		$type  = $member_tier;
		$label = 'Ledenpas';
		if ( $member_tier === 'sponsor' ) {
			$type = self::get_sponsor_pass_variant( $person_id );
			if ( $type === '' ) {
				return null;
			}
			$label = $type === self::SPONSOR_PASS_VARIANT_BUSINESSCLUB ? 'Businessclubpas' : 'Sponsorpas';
		}

		$apple_service  = new MembershipPassApple();
		$google_service = new MembershipPassGoogle();
		$role_options   = array_map(
			static function ( array $option ): array {
				return [
					'key'   => (string) $option['key'],
					'label' => (string) $option['label'],
				];
			},
			$apple_service->get_work_options_for_person( $person_id )
		);

		$apple_available  = $apple_service->is_configured();
		$google_available = $google_service->is_configured();

		return [
			'type'          => $type,
			'label'         => $label,
			'wallets'       => [
				'apple'  => [
					'available' => $apple_available,
					'url'       => self::get_wallet_action_url( $person_id, 'apple' ),
				],
				'google' => [
					'available' => $google_available,
					'url'       => self::get_wallet_action_url( $person_id, 'google' ),
				],
			],
			'role_options'  => $role_options,
			'requires_role' => count( $role_options ) > 1,
		];
	}

	/** Resolve pass eligibility tier for one person. */
	public static function get_person_member_tier( int $person_id ): string {
		if ( SponsorStatus::is_sponsor( $person_id ) ) {
			return self::get_sponsor_pass_variant( $person_id ) !== '' ? 'sponsor' : '';
		}

		$type_lid = strtolower( trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'type_lid' ) ) );
		if ( $type_lid === 'bondslid' ) {
			return 'bondslid';
		}
		if ( $type_lid === 'verenigingslid' ) {
			return 'verenigingslid';
		}

		return '';
	}

	/** Resolve the required wallet pass variant for a Sponsor. */
	public static function get_sponsor_pass_variant( int $person_id ): string {
		$relationship_variant = \Rondo\Sponsors\Relations::pass_variant_for_person( $person_id );
		if ( $relationship_variant !== '' ) {
			return $relationship_variant;
		}

		$variant = sanitize_key( (string) ( \Rondo\Fields\Fields::get_for_post( $person_id, 'sponsor_pass_variant' ) ?: get_post_meta( $person_id, 'sponsor_pass_variant', true ) ) );
		$allowed = [
			self::SPONSOR_PASS_VARIANT_BUSINESSCLUB,
			self::SPONSOR_PASS_VARIANT_AWC_SPONSOR,
		];

		return in_array( $variant, $allowed, true ) ? $variant : '';
	}

	/** Return the company belonging to the selected sponsor-pass relationship. */
	public static function get_sponsor_company_name( int $person_id ): string {
		$relationship = \Rondo\Sponsors\Relations::pass_relationship_for_person( $person_id );
		if ( $relationship ) {
			return (string) $relationship['sponsor_name'];
		}

		return trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'company_name' ) );
	}

	/** Handle a nonce-protected Apple download or Google redirect. */
	public function handle_wallet_action() {
		$person_id = isset( $_GET['person_id'] ) ? absint( wp_unslash( $_GET['person_id'] ) ) : 0;
		$wallet    = isset( $_GET['wallet'] ) ? sanitize_key( wp_unslash( $_GET['wallet'] ) ) : '';
		$role      = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';

		if ( $person_id <= 0 || ! in_array( $wallet, [ 'apple', 'google' ], true ) ) {
			wp_die( esc_html__( 'Ongeldige walletactie.', 'rondo' ), '', [ 'response' => 400 ] );
		}

		check_admin_referer( self::nonce_action( $person_id, $wallet ) );

		$access_control = new AccessControl();
		if ( ! $access_control->user_can_access_post( $person_id ) ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze ledenpas.', 'rondo' ), '', [ 'response' => 403 ] );
		}

		if ( self::get_person_member_tier( $person_id ) === '' ) {
			wp_die( esc_html__( 'Voor dit lid is geen geldige ledenpas beschikbaar.', 'rondo' ), '', [ 'response' => 404 ] );
		}

		$apple_service = new MembershipPassApple();
		$role          = self::resolve_selected_role( $role, $apple_service->get_work_options_for_person( $person_id ) );
		if ( $role === null ) {
			wp_die( esc_html__( 'Kies eerst precies één rol voor je ledenpas.', 'rondo' ), '', [ 'response' => 400 ] );
		}

		if ( $wallet === 'apple' ) {
			$this->output_apple_pass( $person_id, $role );
		}

		$this->redirect_to_google_wallet( $person_id, $role );
	}

	/** Reject direct unauthenticated requests without exposing pass data. */
	public function reject_unauthenticated_action() {
		wp_die( esc_html__( 'Log in om deze ledenpas te openen.', 'rondo' ), '', [ 'response' => 401 ] );
	}

	/** Remove obsolete public tokens, URLs and rewrite rules once. */
	public function maybe_remove_legacy_public_pass_data() {
		if ( get_option( self::LEGACY_CLEANUP_OPTION, false ) ) {
			return;
		}

		$people = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'     => self::LEGACY_TOKEN_META_KEY,
						'compare' => 'EXISTS',
					],
					[
						'key'     => self::LEGACY_URL_META_KEY,
						'compare' => 'EXISTS',
					],
				],
			]
		);

		foreach ( $people as $person_id ) {
			delete_post_meta( (int) $person_id, self::LEGACY_TOKEN_META_KEY );
			delete_post_meta( (int) $person_id, self::LEGACY_URL_META_KEY );
		}

		delete_option( self::LEGACY_BACKFILL_OPTION );
		update_option( self::LEGACY_CLEANUP_OPTION, true, false );
		flush_rewrite_rules( false );
	}

	/** Build an authenticated direct action URL for one wallet. */
	private static function get_wallet_action_url( int $person_id, string $wallet ): string {
		$url = add_query_arg(
			[
				'action'    => self::ACTION,
				'person_id' => $person_id,
				'wallet'    => $wallet,
			],
			admin_url( 'admin-post.php' )
		);

		return add_query_arg( '_wpnonce', wp_create_nonce( self::nonce_action( $person_id, $wallet ) ), $url );
	}

	private static function nonce_action( int $person_id, string $wallet ): string {
		return self::NONCE_ACTION_PREFIX . ':' . $person_id . ':' . $wallet;
	}

	/** Return the validated role, an empty role, or null when a choice is required. */
	private static function resolve_selected_role( string $selected_role, array $role_options ): ?string {
		$keys = [];
		foreach ( $role_options as $option ) {
			if ( isset( $option['key'] ) ) {
				$keys[ (string) $option['key'] ] = true;
			}
		}

		if ( $selected_role !== '' && isset( $keys[ $selected_role ] ) ) {
			return $selected_role;
		}
		if ( count( $role_options ) === 1 && isset( $role_options[0]['key'] ) ) {
			return (string) $role_options[0]['key'];
		}

		return count( $role_options ) > 1 ? null : '';
	}

	private function output_apple_pass( int $person_id, string $selected_role ) {
		$service = new MembershipPassApple();
		$result  = $service->generate_for_person( $person_id, [ 'work' => $selected_role ] );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 500 ] );
		}

		nocache_headers();
		header( 'Content-Type: application/vnd.apple.pkpass' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $result['filename'] ) . '"' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary pkpass payload.
		echo $result['content'];
		exit;
	}

	private function redirect_to_google_wallet( int $person_id, string $selected_role ) {
		$service = new MembershipPassGoogle();
		$result  = $service->get_add_to_wallet_url_for_person( $person_id, [ 'work' => $selected_role ] );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 500 ] );
		}

		wp_redirect( $result );
		exit;
	}
}
