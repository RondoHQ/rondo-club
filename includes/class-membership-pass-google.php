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
	 * @param int   $person_id Person ID.
	 * @param array $options Generation options.
	 * @return string|\WP_Error
	 */
	public function get_add_to_wallet_url_for_person( int $person_id, array $options = [] ) {
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
		$fees   = new MembershipFees();
		$season = $fees->get_season_key();

		$qr_service = new MembershipPassQr();
		$qr_result  = $qr_service->issue_for_person( $person_id );
		if ( is_wp_error( $qr_result ) ) {
			return $qr_result;
		}

		$person_name = $this->get_person_full_name( $person_id );
		$details     = $this->get_pass_work_details( $person_id, (string) ( $options['work'] ?? '' ) );
		$team_name   = $details['teams'] !== '' ? $details['teams'] : '-';
		$functions   = $details['functions'] !== '' ? $details['functions'] : '-';
		$object_id   = $issuer_id . '.member_' . $person_id;
		if ( $details['selection'] !== '' ) {
			$object_id .= '_' . substr( hash( 'sha256', $details['selection'] ), 0, 12 );
		}

		try {
			$this->ensure_class( $service, $class_id );
			$this->upsert_object( $service, $object_id, $class_id, $person_name, $team_name, $functions, $season, $qr_result['token'] );
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
	 * @param string        $functions Functions label.
	 * @param string        $season Season key.
	 * @param string        $qr_payload QR payload.
	 */
	private function upsert_object( Walletobjects $service, string $object_id, string $class_id, string $person_name, string $team_name, string $functions, string $season, string $qr_payload ) {
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
							'header' => 'Teams',
							'body'   => $team_name,
						]
					),
					new TextModuleData(
						[
							'id'     => 'functions',
							'header' => 'Functies',
							'body'   => $functions,
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
	 * Resolve full person name with infix.
	 *
	 * @param int $person_id Person ID.
	 * @return string
	 */
	private function get_person_full_name( int $person_id ): string {
		$first_name = (string) get_field( 'first_name', $person_id );
		$infix      = (string) get_field( 'infix', $person_id );
		$last_name  = (string) get_field( 'last_name', $person_id );

		return trim( preg_replace( '/\s+/', ' ', $first_name . ' ' . $infix . ' ' . $last_name ) );
	}

	/**
	 * Get selectable current work options for a person.
	 *
	 * @param int $person_id Person ID.
	 * @return array<int,array{key:string,label:string,team:string,function:string}>
	 */
	public function get_work_options_for_person( int $person_id ): array {
		return $this->build_current_work_entries( $person_id );
	}

	/**
	 * Resolve current teams and functions from work history.
	 *
	 * @param int $person_id Person ID.
	 * @return array{teams:string,functions:string,selection:string}
	 */
	private function get_current_work_details( int $person_id ): array {
		return $this->get_pass_work_details( $person_id, '' );
	}

	/**
	 * Resolve current work details for pass, optionally narrowed to one selection.
	 *
	 * @param int    $person_id Person ID.
	 * @param string $selected_key Selected work option key.
	 * @return array{teams:string,functions:string,selection:string}
	 */
	private function get_pass_work_details( int $person_id, string $selected_key ): array {
		$entries = $this->build_current_work_entries( $person_id );
		if ( $selected_key !== '' ) {
			foreach ( $entries as $entry ) {
				if ( hash_equals( $entry['key'], $selected_key ) ) {
					return [
						'teams'     => $entry['team'],
						'functions' => $entry['function'],
						'selection' => $entry['key'],
					];
				}
			}
		}

		$teams = [];
		$roles = [];
		foreach ( $entries as $entry ) {
			if ( $entry['team'] !== '' ) {
				$teams[ $entry['team'] ] = true;
			}
			if ( $entry['function'] !== '' ) {
				$roles[ $entry['function'] ] = true;
			}
		}

		return [
			'teams'     => implode( ' • ', array_keys( $teams ) ),
			'functions' => implode( ' • ', array_keys( $roles ) ),
			'selection' => '',
		];
	}

	/**
	 * Build active work entries for pass selection.
	 *
	 * @param int $person_id Person ID.
	 * @return array<int,array{key:string,label:string,team:string,function:string}>
	 */
	private function build_current_work_entries( int $person_id ): array {
		$work_history = get_field( 'work_history', $person_id );
		if ( ! is_array( $work_history ) ) {
			return [];
		}

		$entries = [];

		foreach ( $work_history as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$is_current = ! empty( $entry['is_current'] );
			if ( ! $is_current ) {
				continue;
			}

			$job_title = isset( $entry['job_title'] ) ? trim( (string) $entry['job_title'] ) : '';
			$team_name = '';
			$team_id    = isset( $entry['team'] ) ? (int) $entry['team'] : 0;
			if ( $team_id > 0 ) {
				$title = get_the_title( $team_id );
				if ( is_string( $title ) && $title !== '' ) {
					$team_name = $title;
				}
			}

			if ( $team_name === '' && $job_title === '' ) {
				continue;
			}

			$key_raw = $team_id . '|' . $team_name . '|' . $job_title;
			$key     = substr( hash( 'sha256', $key_raw ), 0, 16 );
			$label   = $team_name;
			if ( $job_title !== '' ) {
				$label = $label !== '' ? $label . ' — ' . $job_title : $job_title;
			}

			$entries[ $key ] = [
				'key'      => $key,
				'label'    => $label,
				'team'     => $team_name,
				'function' => $job_title,
			];
		}

		return array_values( $entries );
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
