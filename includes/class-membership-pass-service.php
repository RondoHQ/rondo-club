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
	const TOKEN_QUERY_ARG                   = '_wallet_token';
	const LEGACY_TOKEN_META_KEY             = '_membership_pass_token';
	const LEGACY_URL_META_KEY               = '_membership_pass_url';
	const LEGACY_BACKFILL_OPTION            = 'rondo_membership_pass_backfill_v2_done';
	const LEGACY_CLEANUP_OPTION             = 'rondo_membership_pass_private_actions_v1_done';
	const PASS_VERSION_META_KEY             = '_rondo_membership_pass_version';
	const SPONSOR_PASS_VARIANT_BUSINESSCLUB = 'businessclub';
	const SPONSOR_PASS_VARIANT_AWC_SPONSOR  = 'awc_sponsor';
	const SPONSOR_PASS_SELECTION            = 'sponsor_pass';

	public function __construct() {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle_wallet_action' ] );
		add_action( 'admin_post_nopriv_' . self::ACTION, [ $this, 'reject_unauthenticated_action' ] );
		add_action( 'init', [ $this, 'maybe_remove_legacy_public_pass_data' ], 20 );
		add_action( 'rondo_fields_updated', [ $this, 'maybe_bump_pass_version_for_field' ], 20, 4 );
		add_action( 'transition_post_status', [ $this, 'maybe_bump_sponsor_contact_versions' ], 20, 3 );
	}

	/** Return the current revocation version for a person's passes. */
	public static function get_pass_version( int $person_id ): int {
		return max( 1, (int) get_post_meta( $person_id, self::PASS_VERSION_META_KEY, true ) );
	}

	/** Revoke all previously issued passes for a person. */
	public static function bump_pass_version( int $person_id ): int {
		$version = self::get_pass_version( $person_id ) + 1;
		update_post_meta( $person_id, self::PASS_VERSION_META_KEY, $version );
		return $version;
	}

	/** Resolve the current membership status used by every pass surface. */
	public static function get_person_membership_status( int $person_id ): array {
		$is_former   = (bool) \Rondo\Fields\Fields::get_for_post( $person_id, 'former_member' );
		$lid_tot_raw = \Rondo\Fields\Fields::get_for_post( $person_id, 'lid_tot' );
		$lid_tot     = \Rondo\Fields\Formatter::for_wire( 'person', [ 'lid_tot' => $lid_tot_raw ] )['lid_tot'];
		$today       = wp_date( 'Y-m-d' );
		$status      = 'active';

		if ( $is_former ) {
			$status = 'former';
		} elseif ( is_string( $lid_tot ) && $lid_tot !== '' && $lid_tot < $today ) {
			$status = 'expired';
		}

		return [
			'status'        => $status,
			'former_member' => $is_former,
			'lid_tot'       => $lid_tot,
		];
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
		$work_options   = $apple_service->get_work_options_for_person( $person_id );
		$role_options   = array_map(
			static function ( array $option ): array {
				return [
					'key'   => (string) $option['key'],
					'label' => (string) $option['label'],
				];
			},
			$work_options
		);

		$standard_member_tier = self::get_person_standard_member_tier( $person_id );
		if ( $member_tier === 'sponsor' && $standard_member_tier !== '' && $work_options !== [] ) {
			$sponsor_label = $label;
			$label         = 'Lidpassen';
			$role_options  = [
				[
					'key'   => self::SPONSOR_PASS_SELECTION,
					'label' => $sponsor_label,
				],
			];
			foreach ( $work_options as $option ) {
				$role_options[] = [
					'key'   => (string) $option['key'],
					'label' => 'AWC-pas — ' . (string) $option['label'],
				];
			}
		}

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
		if ( self::get_person_membership_status( $person_id )['status'] !== 'active' ) {
			return '';
		}

		if ( SponsorStatus::is_sponsor( $person_id ) ) {
			return self::get_sponsor_pass_variant( $person_id ) !== '' ? 'sponsor' : '';
		}

		return self::get_person_standard_member_tier( $person_id );
	}

	/** Resolve the regular membership tier without sponsor precedence. */
	public static function get_person_standard_member_tier( int $person_id ): string {
		$type_lid = strtolower( trim( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'type_lid' ) ) );
		if ( $type_lid === 'bondslid' ) {
			return 'bondslid';
		}
		if ( $type_lid === 'verenigingslid' ) {
			return 'verenigingslid';
		}

		return '';
	}

	/** Resolve and validate the tier requested by a wallet generator. */
	public static function resolve_person_member_tier( int $person_id, string $requested_tier = '' ): string {
		if ( self::get_person_membership_status( $person_id )['status'] !== 'active' ) {
			return '';
		}

		if ( $requested_tier === '' ) {
			return self::get_person_member_tier( $person_id );
		}

		if ( $requested_tier === 'sponsor' ) {
			return SponsorStatus::is_sponsor( $person_id ) && self::get_sponsor_pass_variant( $person_id ) !== '' ? 'sponsor' : '';
		}

		$standard_member_tier = self::get_person_standard_member_tier( $person_id );
		return $requested_tier === $standard_member_tier ? $standard_member_tier : '';
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

	/** Handle a session-bound Apple download or Google redirect. */
	public function handle_wallet_action() {
		$person_id = isset( $_GET['person_id'] ) ? absint( wp_unslash( $_GET['person_id'] ) ) : 0;
		$wallet    = isset( $_GET['wallet'] ) ? sanitize_key( wp_unslash( $_GET['wallet'] ) ) : '';
		$role      = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : '';
		$token     = isset( $_GET[ self::TOKEN_QUERY_ARG ] ) ? sanitize_text_field( wp_unslash( $_GET[ self::TOKEN_QUERY_ARG ] ) ) : '';

		if ( $person_id <= 0 || ! in_array( $wallet, [ 'apple', 'google' ], true ) ) {
			wp_die( esc_html__( 'Ongeldige walletactie.', 'rondo' ), '', [ 'response' => 400 ] );
		}

		$legacy_nonce_valid = isset( $_GET['_wpnonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::nonce_action( $person_id, $wallet ) );
		if ( ! self::verify_wallet_token( $person_id, $wallet, $token ) && ! $legacy_nonce_valid ) {
			wp_die( esc_html__( 'Deze walletlink is niet meer geldig. Ververs Mijn gegevens en probeer opnieuw.', 'rondo' ), '', [ 'response' => 403 ] );
		}

		$access_control = new AccessControl();
		if ( ! $access_control->user_can_access_post( $person_id ) ) {
			wp_die( esc_html__( 'Je hebt geen toegang tot deze ledenpas.', 'rondo' ), '', [ 'response' => 403 ] );
		}

		if ( self::get_person_member_tier( $person_id ) === '' ) {
			wp_die( esc_html__( 'Voor dit lid is geen geldige ledenpas beschikbaar.', 'rondo' ), '', [ 'response' => 404 ] );
		}

		$apple_service = new MembershipPassApple();
		$selection     = self::resolve_selected_pass( $person_id, $role, $apple_service->get_work_options_for_person( $person_id ) );
		if ( $selection === null ) {
			wp_die( esc_html__( 'Kies eerst welke pas je wilt toevoegen.', 'rondo' ), '', [ 'response' => 400 ] );
		}

		if ( $wallet === 'apple' ) {
			$this->output_apple_pass( $person_id, $selection['work'], $selection['member_tier'] );
		}

		$this->redirect_to_google_wallet( $person_id, $selection['work'], $selection['member_tier'] );
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

	/** Revoke passes when a person field that determines eligibility changes. */
	public function maybe_bump_pass_version_for_field( int $post_id, string $field_name, $new_value, $old_value ): void {
		if ( get_post_type( $post_id ) === 'person' ) {
			$person_fields = [ 'former_member', 'lid_tot', 'type_lid', 'is_sponsor', 'sponsor_pass_variant' ];
			if ( in_array( $field_name, $person_fields, true ) ) {
				self::bump_pass_version( $post_id );
			}
			return;
		}

		if ( get_post_type( $post_id ) !== 'rondo_sponsor' || ! in_array( $field_name, [ 'contacts', 'sponsor_role' ], true ) ) {
			return;
		}

		if ( $field_name === 'contacts' ) {
			$this->bump_changed_contact_versions(
				is_array( $old_value ) ? $old_value : [],
				is_array( $new_value ) ? $new_value : []
			);
			return;
		}

		$contacts = \Rondo\Fields\Fields::get_for_post( $post_id, 'contacts' );
		$this->bump_contact_versions( is_array( $contacts ) ? $contacts : [] );
	}

	/** Revoke sponsor passes when their company becomes active or inactive. */
	public function maybe_bump_sponsor_contact_versions( string $new_status, string $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || $post->post_type !== 'rondo_sponsor' || $new_status === $old_status ) {
			return;
		}

		$contacts = \Rondo\Fields\Fields::get_for_post( (int) $post->ID, 'contacts' );
		$this->bump_contact_versions( is_array( $contacts ) ? $contacts : [] );
	}

	/** Revoke passes for every unique person in sponsor contact rows. */
	private function bump_contact_versions( array $contacts ): void {
		$person_ids = [];
		foreach ( $contacts as $contact ) {
			if ( is_array( $contact ) ) {
				$person_ids[] = absint( $contact['person_id'] ?? 0 );
			}
		}

		foreach ( array_unique( array_filter( $person_ids ) ) as $person_id ) {
			self::bump_pass_version( (int) $person_id );
		}
	}

	/** Revoke only contacts whose sponsor-pass entitlement changed. */
	private function bump_changed_contact_versions( array $old_contacts, array $new_contacts ): void {
		$old_entitlements = $this->contact_entitlements( $old_contacts );
		$new_entitlements = $this->contact_entitlements( $new_contacts );
		$person_ids       = array_unique( array_merge( array_keys( $old_entitlements ), array_keys( $new_entitlements ) ) );

		foreach ( $person_ids as $person_id ) {
			if ( ( $old_entitlements[ $person_id ] ?? null ) !== ( $new_entitlements[ $person_id ] ?? null ) ) {
				self::bump_pass_version( (int) $person_id );
			}
		}
	}

	/** Map sponsor contacts to the fields that determine their pass right. */
	private function contact_entitlements( array $contacts ): array {
		$entitlements = [];
		foreach ( $contacts as $contact ) {
			if ( ! is_array( $contact ) ) {
				continue;
			}
			$person_id = absint( $contact['person_id'] ?? 0 );
			if ( $person_id <= 0 ) {
				continue;
			}
			$entitlements[ $person_id ] = [
				'receives_pass'   => ! empty( $contact['receives_pass'] ),
				'is_primary_pass' => ! empty( $contact['is_primary_pass'] ),
			];
		}
		ksort( $entitlements );
		return $entitlements;
	}

	/** Build an authenticated direct action URL for one wallet. */
	private static function get_wallet_action_url( int $person_id, string $wallet ): string {
		$url = add_query_arg(
			[
				'action'              => self::ACTION,
				'person_id'           => $person_id,
				'wallet'              => $wallet,
				self::TOKEN_QUERY_ARG => self::create_wallet_token( $person_id, $wallet ),
			],
			admin_url( 'admin-post.php' )
		);

		return $url;
	}

	/** Create a wallet token bound to the current user and login session. */
	private static function create_wallet_token( int $person_id, string $wallet ): string {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '';
		}

		$payload = implode(
			'|',
			[
				(string) $user_id,
				wp_get_session_token(),
				(string) $person_id,
				$wallet,
			]
		);

		return hash_hmac( 'sha256', $payload, wp_salt( 'nonce' ) );
	}

	/** Verify a wallet token for the current user and login session. */
	private static function verify_wallet_token( int $person_id, string $wallet, string $token ): bool {
		return $token !== '' && hash_equals( self::create_wallet_token( $person_id, $wallet ), $token );
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

	/** Resolve the requested sponsor or regular AWC pass and its work role. */
	private static function resolve_selected_pass( int $person_id, string $selected_role, array $work_options ): ?array {
		$member_tier          = self::get_person_member_tier( $person_id );
		$standard_member_tier = self::get_person_standard_member_tier( $person_id );

		if ( $member_tier === 'sponsor' && $standard_member_tier !== '' && $work_options !== [] ) {
			if ( $selected_role === self::SPONSOR_PASS_SELECTION ) {
				return [
					'member_tier' => 'sponsor',
					'work'        => '',
				];
			}

			foreach ( $work_options as $option ) {
				if ( isset( $option['key'] ) && hash_equals( (string) $option['key'], $selected_role ) ) {
					return [
						'member_tier' => $standard_member_tier,
						'work'        => (string) $option['key'],
					];
				}
			}

			return null;
		}

		$resolved_role = self::resolve_selected_role( $selected_role, $work_options );
		if ( $resolved_role === null ) {
			return null;
		}

		return [
			'member_tier' => $member_tier,
			'work'        => $resolved_role,
		];
	}

	private function output_apple_pass( int $person_id, string $selected_role, string $member_tier ) {
		$service = new MembershipPassApple();
		$result  = $service->generate_for_person(
			$person_id,
			[
				'work'        => $selected_role,
				'member_tier' => $member_tier,
			]
		);
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

	private function redirect_to_google_wallet( int $person_id, string $selected_role, string $member_tier ) {
		$service = new MembershipPassGoogle();
		$result  = $service->get_add_to_wallet_url_for_person(
			$person_id,
			[
				'work'        => $selected_role,
				'member_tier' => $member_tier,
			]
		);
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => 500 ] );
		}

		wp_redirect( $result );
		exit;
	}
}
