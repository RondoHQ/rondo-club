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
use Rondo\Fees\SeasonKey;
use Rondo\Config\ClubConfig;

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

		$member_tier = PublicMembershipPassPage::get_person_member_tier( $person_id );
		if ( $member_tier === '' ) {
			return new \WP_Error( 'membership_pass_ineligible_member', 'Dit lidtype komt niet in aanmerking voor een ledenpas.' );
		}

		$issuer_id = $this->get_issuer_id();
		$json_path = $this->get_service_account_path();
		if ( $issuer_id === '' || $json_path === '' || ! file_exists( $json_path ) ) {
			return new \WP_Error( 'membership_pass_google_not_configured', 'Google Wallet is nog niet geconfigureerd.' );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
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
		$season       = SeasonKey::current();

		$qr_service = new MembershipPassQr();
		$qr_result  = $qr_service->issue_for_person( $person_id );
		if ( is_wp_error( $qr_result ) ) {
			return $qr_result;
		}

		$person_name  = $this->get_person_full_name( $person_id );
		$issuer_name  = $this->get_issuer_name();
		$member_type  = $this->get_member_type_label( $member_tier );
		$knvb_id      = trim( (string) get_field( 'knvb-id', $person_id ) );
		$details      = $this->get_pass_work_details( $person_id, (string) ( $options['work'] ?? '' ) );
		$team_name    = $details['teams'] !== '' ? $details['teams'] : '-';
		$functions    = $details['functions'] !== '' ? $details['functions'] : '-';
		$company_name = trim( (string) get_field( 'company_name', $person_id ) );
		$card_title   = $this->get_card_title( $issuer_name, $member_tier );
		$object_id    = $issuer_id . '.member_' . $person_id;
		if ( $details['selection'] !== '' ) {
			$object_id .= '_' . substr( hash( 'sha256', $details['selection'] ), 0, 12 );
		}

		try {
			$this->ensure_class( $service, $class_id );
			$this->upsert_object( $service, $object_id, $class_id, $card_title, $person_name, $member_type, $team_name, $functions, $company_name, $knvb_id, $season, $qr_result['token'], $member_tier );
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

		$name = $this->get_issuer_name();

		$class = new GenericClass(
			[
				'id'           => $class_id,
				'issuerName'   => $name,
				'reviewStatus' => 'UNDER_REVIEW',
			]
		);
		$logo  = $this->get_logo_image_url();
		if ( $logo !== '' ) {
			$class->setLogo(
				$this->build_logo_image( $logo )
			);
		}

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
	 * @param string        $card_title Top card title.
	 * @param string        $person_name Person name.
	 * @param string        $member_type Membership type label.
	 * @param string        $team_name Team label.
	 * @param string        $functions Functions label.
	 * @param string        $company_name Company name.
	 * @param string        $knvb_id KNVB ID.
	 * @param string        $season Season key.
	 * @param string        $qr_payload QR payload.
	 * @param string        $member_tier Resolved pass tier.
	 */
	private function upsert_object( Walletobjects $service, string $object_id, string $class_id, string $card_title, string $person_name, string $member_type, string $team_name, string $functions, string $company_name, string $knvb_id, string $season, string $qr_payload, string $member_tier ) {
		$text_modules = array_map(
			static function ( array $module ): TextModuleData {
				return new TextModuleData( $module );
			},
			$this->get_text_module_definitions( $member_tier, $team_name, $functions, $company_name, $knvb_id, $season )
		);

		$object = new GenericObject(
			[
				'id'                 => $object_id,
				'classId'            => $class_id,
				'state'              => 'ACTIVE',
				'cardTitle'          => [
					'defaultValue' => [
						'language' => 'nl-NL',
						'value'    => $card_title,
					],
				],
				'header'             => [
					'defaultValue' => [
						'language' => 'nl-NL',
						'value'    => $person_name,
					],
				],
				'subheader'          => [
					'defaultValue' => [
						'language' => 'nl-NL',
						'value'    => $member_type,
					],
				],
				'barcode'            => new Barcode(
					[
						'type'          => 'QR_CODE',
						'value'         => $qr_payload,
						'alternateText' => '',
					]
				),
				'textModulesData'    => $text_modules,
				'hexBackgroundColor' => $this->get_hex_background_color( $member_tier ),
			]
		);
		$logo   = $this->get_logo_image_url( $member_tier );
		if ( $logo !== '' ) {
			$object->setLogo(
				$this->build_logo_image( $logo, $member_tier )
			);
		}

		try {
			$service->genericobject->get( $object_id );
			$service->genericobject->update( $object_id, $object );
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
		$header          = [
			'alg' => 'RS256',
			'typ' => 'JWT',
		];
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

		$name = trim( preg_replace( '/\s+/', ' ', $first_name . ' ' . $infix . ' ' . $last_name ) );
		return $name !== '' ? $name : trim( (string) get_field( 'company_name', $person_id ) );
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
			$team_id   = isset( $entry['team'] ) ? (int) $entry['team'] : 0;
			if ( $team_id > 0 ) {
				$title = get_the_title( $team_id );
				if ( is_string( $title ) && $title !== '' ) {
					$team_name = $this->normalize_team_name( $title );
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
	 * Normalize team names for wallet card readability.
	 *
	 * @param string $team_name Team title.
	 * @return string
	 */
	private function normalize_team_name( string $team_name ): string {
		if ( strtolower( $team_name ) === 'verenigingsbreed' ) {
			return 'Vereniging';
		}
		return $team_name;
	}

	/**
	 * Resolve logo image URL.
	 *
	 * @param string $member_tier Resolved pass tier.
	 * @return string
	 */
	private function get_logo_image_url( string $member_tier = '' ): string {
		if ( $member_tier === 'sponsor' ) {
			$businessclub_logo = get_template_directory() . '/public/icons/businessclub-awc-logo.png';
			if ( file_exists( $businessclub_logo ) ) {
				return get_template_directory_uri() . '/public/icons/businessclub-awc-logo.png';
			}
		}

		$config  = new FinanceConfig();
		$logo_id = $config->get_club_logo_id();
		if ( $logo_id > 0 ) {
			$padded = $this->get_padded_logo_image_url( $logo_id );
			if ( $padded !== '' ) {
				return $padded;
			}

			$url = wp_get_attachment_url( $logo_id );
			if ( is_string( $url ) ) {
				return $url;
			}
		}
		return '';
	}

	/**
	 * Build logo image payload with localized content description.
	 *
	 * @param string $logo_url Logo URL.
	 * @param string $member_tier Resolved pass tier.
	 * @return Image
	 */
	private function build_logo_image( string $logo_url, string $member_tier = '' ): Image {
		$logo_name = $this->get_card_title( $this->get_issuer_name(), $member_tier );

		return new Image(
			[
				'sourceUri'          => new ImageUri( [ 'uri' => $logo_url ] ),
				'contentDescription' => [
					'defaultValue' => [
						'language' => 'nl-NL',
						'value'    => $logo_name . ' Logo',
					],
				],
			]
		);
	}

	/**
	 * Build (and cache) a padded PNG variant of the club logo.
	 *
	 * @param int $logo_id Attachment ID.
	 * @return string
	 */
	private function get_padded_logo_image_url( int $logo_id ): string {
		if ( ! function_exists( 'imagecreatefromstring' ) || ! function_exists( 'imagepng' ) ) {
			return '';
		}

		$path = get_attached_file( $logo_id );
		if ( ! is_string( $path ) || ! file_exists( $path ) ) {
			return '';
		}

		$uploads = wp_get_upload_dir();
		if ( empty( $uploads['basedir'] ) || empty( $uploads['baseurl'] ) ) {
			return '';
		}

		$mtime = filemtime( $path );
		if ( ! is_int( $mtime ) ) {
			return '';
		}

		$subdir      = 'rondo-wallet';
		$target_dir  = trailingslashit( (string) $uploads['basedir'] ) . $subdir;
		$target_name = 'logo-' . $logo_id . '-' . $mtime . '-padded.png';
		$target_path = trailingslashit( $target_dir ) . $target_name;
		$target_url  = trailingslashit( (string) $uploads['baseurl'] ) . $subdir . '/' . $target_name;

		if ( file_exists( $target_path ) ) {
			return $target_url;
		}

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$source_data = file_get_contents( $path );
		if ( ! is_string( $source_data ) || $source_data === '' ) {
			return '';
		}

		$source = imagecreatefromstring( $source_data );
		if ( ! is_resource( $source ) && ! ( $source instanceof \GdImage ) ) {
			return '';
		}

		$width  = imagesx( $source );
		$height = imagesy( $source );
		if ( $width <= 0 || $height <= 0 ) {
			imagedestroy( $source );
			return '';
		}

		$max_side    = max( $width, $height );
		$padding     = (int) ceil( $max_side * 0.16 );
		$canvas_size = $max_side + ( 2 * $padding );
		$dest        = imagecreatetruecolor( $canvas_size, $canvas_size );
		if ( ! is_resource( $dest ) && ! ( $dest instanceof \GdImage ) ) {
			imagedestroy( $source );
			return '';
		}

		imagealphablending( $dest, false );
		$transparent = imagecolorallocatealpha( $dest, 0, 0, 0, 127 );
		imagefill( $dest, 0, 0, $transparent );
		imagesavealpha( $dest, true );

		$dst_x = (int) floor( ( $canvas_size - $width ) / 2 );
		$dst_y = (int) floor( ( $canvas_size - $height ) / 2 );
		imagecopy( $dest, $source, $dst_x, $dst_y, 0, 0, $width, $height );

		$saved = imagepng( $dest, $target_path, 6 );
		imagedestroy( $source );
		imagedestroy( $dest );

		if ( ! $saved ) {
			return '';
		}

		return $target_url;
	}

	/**
	 * Resolve issuer display name.
	 *
	 * @return string
	 */
	private function get_issuer_name(): string {
		$config = new ClubConfig();
		$name   = $config->get_club_name();
		if ( $name !== '' ) {
			return $name;
		}
		return (string) get_bloginfo( 'name' );
	}

	/**
	 * Resolve membership type label shown above the member name.
	 *
	 * @param string $member_tier Resolved pass tier.
	 * @return string
	 */
	private function get_member_type_label( string $member_tier ): string {
		if ( $member_tier === 'sponsor' ) {
			return 'Sponsor';
		}
		if ( $member_tier === 'verenigingslid' ) {
			return 'Verenigingslid';
		}
		return 'Bondslid';
	}

	/**
	 * Resolve the title shown at the top of the pass.
	 *
	 * @param string $issuer_name Configured issuer name.
	 * @param string $member_tier Resolved pass tier.
	 * @return string
	 */
	private function get_card_title( string $issuer_name, string $member_tier ): string {
		return $member_tier === 'sponsor' ? 'Businessclub ' . $issuer_name : $issuer_name;
	}

	/**
	 * Build Google Wallet text module definitions.
	 *
	 * @param string $member_tier Resolved pass tier.
	 * @param string $team_name Team label.
	 * @param string $functions Functions label.
	 * @param string $company_name Company name.
	 * @param string $knvb_id KNVB ID.
	 * @param string $season Season key.
	 * @return array<int, array{id: string, header: string, body: string}>
	 */
	private function get_text_module_definitions( string $member_tier, string $team_name, string $functions, string $company_name, string $knvb_id, string $season ): array {
		if ( $member_tier === 'sponsor' ) {
			$modules = [
				[
					'id'     => 'bedrijf',
					'header' => 'BEDRIJF',
					'body'   => $company_name !== '' ? $company_name : '-',
				],
			];
		} else {
			$modules = [
				[
					'id'     => 'functie',
					'header' => 'FUNCTIE',
					'body'   => $functions,
				],
				[
					'id'     => 'team',
					'header' => 'TEAM',
					'body'   => $team_name,
				],
			];
		}

		if ( $member_tier === 'bondslid' && $knvb_id !== '' ) {
			$modules[] = [
				'id'     => 'knvb_id',
				'header' => 'KNVB ID',
				'body'   => $knvb_id,
			];
		}
		$modules[] = [
			'id'     => 'seizoen',
			'header' => 'SEIZOEN',
			'body'   => $season,
		];

		return $modules;
	}

	/**
	 * Resolve Google pass background color in hex.
	 *
	 * @return string
	 */
	private function get_hex_background_color( string $member_tier = '' ): string {
		if ( $member_tier === 'sponsor' ) {
			return '#ffffff';
		}

		$config = new FinanceConfig();
		$hex    = trim( $config->get_accent_color() );
		if ( preg_match( '/^#?[a-f0-9]{6}$/i', $hex ) ) {
			return '#' . ltrim( strtolower( $hex ), '#' );
		}
		return '#006935';
	}
}
