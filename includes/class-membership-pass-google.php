<?php
/**
 * Google Wallet membership pass generation.
 */

namespace Rondo\Passes;

use Google\Client as GoogleClient;
use Google\Service\Walletobjects;
use Google\Service\Walletobjects\Barcode;
use Google\Service\Walletobjects\GenericClass;
use Google\Service\Walletobjects\GenericObject;
use Google\Service\Walletobjects\Image;
use Google\Service\Walletobjects\ImageUri;
use Google\Service\Walletobjects\TextModuleData;
use Rondo\Config\FinanceConfig;
use Rondo\Fees\MembershipFees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembershipPassGoogle {

	const LEGACY_OPTION_SERVICE_ACCOUNT_PATH = 'rondo_membership_pass_google_service_account_path';

	/**
	 * Check if Google Wallet config exists.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		$issuer_id = $this->get_issuer_id();
		$json_path = $this->get_service_account_path();

		return $issuer_id !== '' && $json_path !== '' && file_exists( $json_path );
	}

	/**
	 * Create/update wallet object and return Add-to-Wallet URL.
	 *
	 * @param int $person_id Person ID.
	 * @return string|\WP_Error
	 */
	public function get_add_to_wallet_url_for_person( int $person_id ) {
		$post = get_post( $person_id );
		if ( ! $post || $post->post_type !== 'person' ) {
			return new \WP_Error( 'membership_pass_person_not_found', 'Persoon niet gevonden.' );
		}

		$knvb_id = (string) get_field( 'knvb-id', $person_id );
		if ( $knvb_id === '' ) {
			return new \WP_Error( 'membership_pass_missing_knvb', 'KNVB ID ontbreekt voor dit lid.' );
		}

		$issuer_id = $this->get_issuer_id();
		$json_path = $this->get_service_account_path();
		if ( $issuer_id === '' || $json_path === '' || ! file_exists( $json_path ) ) {
			return new \WP_Error( 'membership_pass_google_not_configured', 'Google Wallet is nog niet geconfigureerd.' );
		}

		$service_account = json_decode( (string) file_get_contents( $json_path ), true );
		if ( ! is_array( $service_account ) || empty( $service_account['client_email'] ) || empty( $service_account['private_key'] ) ) {
			return new \WP_Error( 'membership_pass_google_invalid_service_account', 'Google service-account JSON is ongeldig.' );
		}

		$client = new GoogleClient();
		$client->setAuthConfig( $json_path );
		$client->addScope( 'https://www.googleapis.com/auth/wallet_object.issuer' );

		$service = new Walletobjects( $client );

		$class_suffix = $this->get_class_suffix();
		$class_id     = $issuer_id . '.' . $class_suffix;
		$object_id    = $issuer_id . '.member_' . $person_id;

		$fees   = new MembershipFees();
		$season = $fees->get_season_key();

		$qr_service = new MembershipPassQr();
		$qr_result  = $qr_service->issue_for_person( $person_id );
		if ( is_wp_error( $qr_result ) ) {
			return $qr_result;
		}

		$person_name = trim( (string) get_field( 'first_name', $person_id ) . ' ' . (string) get_field( 'last_name', $person_id ) );
		$team_name   = $this->get_current_team_name( $person_id );

		try {
			$this->ensure_class( $service, $class_id );
			$this->upsert_object( $service, $object_id, $class_id, $person_name, $team_name, $season, $qr_result['token'] );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'membership_pass_google_api_error', 'Google Wallet API fout: ' . $e->getMessage() );
		}

		$claims = [
			'iss'     => $service_account['client_email'],
			'aud'     => 'google',
			'typ'     => 'savetowallet',
			'origins' => [ home_url() ],
			'payload' => [
				'genericObjects' => [
					[
						'id'      => $object_id,
						'classId' => $class_id,
					],
				],
			],
		];

		$jwt = $this->create_rs256_jwt( $claims, (string) $service_account['private_key'] );
		if ( is_wp_error( $jwt ) ) {
			return $jwt;
		}

		return 'https://pay.google.com/gp/v/save/' . $jwt;
	}

	/**
	 * Ensure pass class exists.
	 *
	 * @param Walletobjects $service Wallet service.
	 * @param string        $class_id Class ID.
	 */
	private function ensure_class( Walletobjects $service, string $class_id ) {
		try {
			$service->genericclass->get( $class_id );
			return;
		} catch ( \Throwable $e ) {
			// Continue with create.
		}

		$config = new FinanceConfig();
		$name   = $config->get_org_name();
		if ( $name === '' ) {
			$name = get_bloginfo( 'name' );
		}

		$class = new GenericClass(
			[
				'id'           => $class_id,
				'issuerName'   => $name,
				'reviewStatus' => 'UNDER_REVIEW',
			]
		);

		try {
			$service->genericclass->insert( $class );
		} catch ( \Throwable $e ) {
			// Class may already exist or be pending review; ignore here.
		}
	}

	/**
	 * Insert or patch wallet object for person.
	 *
	 * @param Walletobjects $service Wallet service.
	 * @param string        $object_id Object ID.
	 * @param string        $class_id Class ID.
	 * @param string        $person_name Person name.
	 * @param string        $team_name Team label.
	 * @param string        $season Season key.
	 * @param string        $qr_payload QR payload.
	 */
	private function upsert_object( Walletobjects $service, string $object_id, string $class_id, string $person_name, string $team_name, string $season, string $qr_payload ) {
		$object = new GenericObject(
			[
				'id'             => $object_id,
				'classId'        => $class_id,
				'state'          => 'ACTIVE',
				'cardTitle'      => [
					'defaultValue' => [
						'language' => 'nl-NL',
						'value'    => $person_name,
					],
				],
				'barcode'        => new Barcode(
					[
						'type'  => 'QR_CODE',
						'value' => $qr_payload,
					]
				),
				'textModulesData' => [
					new TextModuleData(
						[
							'id'     => 'team',
							'header' => 'Team',
							'body'   => $team_name,
						]
					),
					new TextModuleData(
						[
							'id'     => 'season',
							'header' => 'Seizoen',
							'body'   => $season,
						]
					),
				],
			]
		);

		$hero = $this->get_hero_image_url();
		if ( $hero !== '' ) {
			$object->setHeroImage(
				new Image(
					[
						'sourceUri' => new ImageUri( [ 'uri' => $hero ] ),
					]
				)
			);
		}

		try {
			$service->genericobject->get( $object_id );
			$service->genericobject->patch( $object_id, $object );
		} catch ( \Throwable $e ) {
			$service->genericobject->insert( $object );
		}
	}

	/**
	 * Build and sign RS256 JWT.
	 *
	 * @param array  $claims JWT claims.
	 * @param string $private_key PEM private key.
	 * @return string|\WP_Error
	 */
	private function create_rs256_jwt( array $claims, string $private_key ) {
		$header  = [ 'alg' => 'RS256', 'typ' => 'JWT' ];
		$encoded_header  = $this->base64url_encode( wp_json_encode( $header ) );
		$encoded_payload = $this->base64url_encode( wp_json_encode( $claims ) );
		$signing_input   = $encoded_header . '.' . $encoded_payload;

		$signature = '';
		$ok        = openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			return new \WP_Error( 'membership_pass_google_jwt_failed', 'Kon Google Wallet JWT niet ondertekenen.' );
		}

		return $signing_input . '.' . $this->base64url_encode( $signature );
	}

	/**
	 * Base64url encode helper.
	 *
	 * @param string $data Raw bytes/string.
	 * @return string
	 */
	private function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Resolve service account path.
	 *
	 * @return string
	 */
	private function get_service_account_path(): string {
		$config        = new FinanceConfig();
		$attachment_id = $config->get_membership_pass_google_service_account_attachment_id();
		if ( $attachment_id > 0 ) {
			$path = get_attached_file( $attachment_id );
			if ( is_string( $path ) && file_exists( $path ) ) {
				return $path;
			}
		}

		$legacy_path = (string) get_option( self::LEGACY_OPTION_SERVICE_ACCOUNT_PATH, '' );
		if ( $legacy_path !== '' ) {
			return $legacy_path;
		}
		return defined( 'RONDO_MEMBERSHIP_PASS_GOOGLE_SERVICE_ACCOUNT_PATH' ) ? (string) RONDO_MEMBERSHIP_PASS_GOOGLE_SERVICE_ACCOUNT_PATH : '';
	}

	/**
	 * Resolve issuer ID.
	 *
	 * @return string
	 */
	private function get_issuer_id(): string {
		$config = new FinanceConfig();
		$value  = $config->get_membership_pass_google_issuer_id();
		if ( $value !== '' ) {
			return $value;
		}
		return defined( 'RONDO_MEMBERSHIP_PASS_GOOGLE_ISSUER_ID' ) ? (string) RONDO_MEMBERSHIP_PASS_GOOGLE_ISSUER_ID : '';
	}

	/**
	 * Resolve class suffix.
	 *
	 * @return string
	 */
	private function get_class_suffix(): string {
		$config = new FinanceConfig();
		return $config->get_membership_pass_google_class_suffix();
	}

	/**
	 * Resolve active team label.
	 *
	 * @param int $person_id Person ID.
	 * @return string
	 */
	private function get_current_team_name( int $person_id ): string {
		$work_history = get_field( 'work_history', $person_id );
		if ( ! is_array( $work_history ) ) {
			return '';
		}

		foreach ( $work_history as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$is_current = ! empty( $entry['is_current'] );
			$team_id    = isset( $entry['team'] ) ? (int) $entry['team'] : 0;
			if ( $is_current && $team_id > 0 ) {
				$title = get_the_title( $team_id );
				if ( is_string( $title ) ) {
					return $title;
				}
			}
		}

		return '';
	}

	/**
	 * Resolve hero image URL (club logo fallback).
	 *
	 * @return string
	 */
	private function get_hero_image_url(): string {
		$config  = new FinanceConfig();
		$logo_id = $config->get_club_logo_id();
		if ( $logo_id > 0 ) {
			$url = wp_get_attachment_url( $logo_id );
			if ( is_string( $url ) ) {
				return $url;
			}
		}
		return '';
	}
}
