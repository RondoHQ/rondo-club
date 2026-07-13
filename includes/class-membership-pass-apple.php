<?php
/**
 * Apple Wallet membership pass generation.
 */

namespace Rondo\Passes;

use PKPass\PKPass;
use Rondo\Config\FinanceConfig;
use Rondo\Fees\SeasonKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembershipPassApple {

	const LEGACY_OPTION_CERT_PATH = 'rondo_membership_pass_apple_cert_path';

	/**
	 * Check whether minimum Apple pass config exists.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return class_exists( PKPass::class )
			&& $this->get_cert_path() !== ''
			&& $this->get_cert_password() !== ''
			&& $this->get_pass_type_identifier() !== ''
			&& $this->get_team_identifier() !== '';
	}

	/**
	 * Generate Apple Wallet pass for person.
	 *
	 * @param int   $person_id Person ID.
	 * @param array $options Generation options.
	 * @return array|\WP_Error
	 */
	public function generate_for_person( int $person_id, array $options = [] ) {
		if ( ! class_exists( PKPass::class ) ) {
			return new \WP_Error( 'membership_pass_apple_library_missing', 'Apple pass library ontbreekt (pkpass/pkpass niet geïnstalleerd).' );
		}

		$post = get_post( $person_id );
		if ( ! $post || $post->post_type !== 'person' ) {
			return new \WP_Error( 'membership_pass_person_not_found', 'Persoon niet gevonden.' );
		}

		$knvb_id     = (string) get_field( 'knvb-id', $person_id );
		$member_tier = PublicMembershipPassPage::get_person_member_tier( $person_id );
		if ( $member_tier === '' ) {
			return new \WP_Error( 'membership_pass_ineligible_member', 'Dit lidtype komt niet in aanmerking voor een ledenpas.' );
		}

		$is_sponsor   = $member_tier === 'sponsor';
		$member_label = $is_sponsor
			? 'Sponsor'
			: ( $member_tier === 'verenigingslid' ? 'Verenigingslid' : 'Bondslid' );

		$cert_path = $this->get_cert_path();
		if ( $cert_path === '' || ! file_exists( $cert_path ) ) {
			return new \WP_Error( 'membership_pass_apple_cert_missing', 'Apple pas-certificaat niet gevonden op de server.' );
		}

		$qr_service = new MembershipPassQr();
		$qr_result  = $qr_service->issue_for_person( $person_id );
		if ( is_wp_error( $qr_result ) ) {
			return $qr_result;
		}

		$season = SeasonKey::current();

		$person_name = $this->get_person_full_name( $person_id );
		$details     = $this->get_pass_work_details( $person_id, (string) ( $options['work'] ?? '' ) );
		$team_name   = $details['teams'] !== '' ? $details['teams'] : '-';
		$functions   = $details['functions'] !== '' ? $details['functions'] : '-';
		$serial      = 'person-' . $person_id;
		if ( $details['selection'] !== '' ) {
			$serial .= '-' . substr( hash( 'sha256', $details['selection'] ), 0, 12 );
		}

		$pass_data = [
			'formatVersion'      => 1,
			'passTypeIdentifier' => $this->get_pass_type_identifier(),
			'serialNumber'       => $serial,
			'teamIdentifier'     => $this->get_team_identifier(),
			'organizationName'   => $this->get_organization_name(),
			'description'        => $is_sponsor ? 'Rondo toegangspas' : 'Rondo lidmaatschapspas',
			'logoText'           => $this->get_organization_name(),
			'foregroundColor'    => $is_sponsor ? 'rgb(17,24,39)' : 'rgb(255,255,255)',
			'labelColor'         => $is_sponsor ? 'rgb(55,65,81)' : 'rgb(255,255,255)',
			'backgroundColor'    => $this->get_background_color( $member_tier ),
			'generic'            => [
				'primaryFields'   => [
					[
						'key'   => 'member_name',
						'label' => strtoupper( $member_label ),
						'value' => $person_name,
					],
				],
				'secondaryFields' => [
					[
						'key'   => 'team',
						'label' => 'TEAMS',
						'value' => $team_name,
					],
					[
						'key'   => 'season',
						'label' => 'SEIZOEN',
						'value' => $season,
					],
				],
				'auxiliaryFields' => array_values(
					array_filter(
						[
							[
								'key'   => 'functions',
								'label' => 'FUNCTIES',
								'value' => $functions,
							],
							[
								'key'   => 'knvb_id',
								'label' => 'KNVB ID',
								'value' => ( $member_tier === 'bondslid' && $knvb_id !== '' ) ? $knvb_id : null,
							],
						],
						static function ( $field ) {
							return isset( $field['value'] ) && $field['value'] !== null && $field['value'] !== '';
						}
					)
				),
			],
			'barcode'            => [
				'format'          => 'PKBarcodeFormatQR',
				'message'         => $qr_result['token'],
				'messageEncoding' => 'iso-8859-1',
			],
		];

		$pass = new PKPass( $cert_path, $this->get_cert_password() );

		try {
			$pass->setData( $pass_data );
			$this->add_default_images( $pass, $person_id );
			$binary = $pass->create( false );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'membership_pass_apple_generate_failed', 'Genereren van Apple pass mislukt: ' . $e->getMessage() );
		}

		if ( ! is_string( $binary ) || $binary === '' ) {
			return new \WP_Error( 'membership_pass_apple_generate_failed', 'Genereren van Apple pass mislukt: lege output.' );
		}

		return [
			'filename' => 'rondo-' . ( $is_sponsor ? 'toegangspas' : 'lidpas' ) . '-' . $person_id . '.pkpass',
			'content'  => $binary,
		];
	}

	/**
	 * Add pass image assets.
	 *
	 * @param PKPass $pass Pass instance.
	 * @param int    $person_id Person ID.
	 */
	private function add_default_images( PKPass $pass, int $person_id ) {
		$theme_dir = get_template_directory();
		$default   = $theme_dir . '/public/icons/apple-touch-icon-180x180.png';
		$logo_path = $default;

		$config  = new FinanceConfig();
		$logo_id = $config->get_club_logo_id();
		if ( $logo_id > 0 ) {
			$path = get_attached_file( $logo_id );
			if ( is_string( $path ) && file_exists( $path ) ) {
				$logo_path = $path;
			}
		}

		$thumbnail_path = $this->get_person_photo_path( $person_id );

		$default_bytes = $this->read_file_bytes( $default );
		$logo_bytes    = $this->read_file_bytes( $logo_path );

		if ( $default_bytes !== null ) {
			$pass->addFileContent( $default_bytes, 'icon.png' );
			$pass->addFileContent( $default_bytes, 'icon@2x.png' );
		}
		if ( $logo_bytes !== null ) {
			$pass->addFileContent( $logo_bytes, 'logo.png' );
			$pass->addFileContent( $logo_bytes, 'logo@2x.png' );
		}

		if ( $thumbnail_path !== '' ) {
			$thumbnail_bytes = $this->read_file_bytes( $thumbnail_path );
			if ( $thumbnail_bytes !== null ) {
				$pass->addFileContent( $thumbnail_bytes, 'thumbnail.png' );
				$pass->addFileContent( $thumbnail_bytes, 'thumbnail@2x.png' );
			}
		}
	}

	/**
	 * Resolve local person photo path.
	 *
	 * @param int $person_id Person ID.
	 * @return string
	 */
	private function get_person_photo_path( int $person_id ): string {
		$thumb_id = get_post_thumbnail_id( $person_id );
		if ( ! $thumb_id ) {
			return '';
		}

		$path = get_attached_file( $thumb_id );
		if ( ! is_string( $path ) || ! file_exists( $path ) ) {
			return '';
		}

		return $path;
	}

	/**
	 * Read file bytes.
	 *
	 * @param string $path File path.
	 * @return string|null
	 */
	private function read_file_bytes( string $path ): ?string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = @file_get_contents( $path );
		if ( ! is_string( $contents ) || $contents === '' ) {
			return null;
		}
		return $contents;
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
	 * Get configured certificate path.
	 *
	 * @return string
	 */
	private function get_cert_path(): string {
		$config        = new FinanceConfig();
		$attachment_id = $config->get_membership_pass_apple_cert_attachment_id();
		if ( $attachment_id > 0 ) {
			$path = get_attached_file( $attachment_id );
			if ( is_string( $path ) && file_exists( $path ) ) {
				return $path;
			}
		}

		$legacy_path = (string) get_option( self::LEGACY_OPTION_CERT_PATH, '' );
		if ( $legacy_path !== '' ) {
			return $legacy_path;
		}
		return defined( 'RONDO_MEMBERSHIP_PASS_APPLE_CERT_PATH' ) ? (string) RONDO_MEMBERSHIP_PASS_APPLE_CERT_PATH : '';
	}

	/**
	 * Get configured certificate password.
	 *
	 * @return string
	 */
	private function get_cert_password(): string {
		$config = new FinanceConfig();
		$value  = $config->get_membership_pass_apple_cert_password();
		if ( $value !== '' ) {
			return $value;
		}
		return defined( 'RONDO_MEMBERSHIP_PASS_APPLE_CERT_PASSWORD' ) ? (string) RONDO_MEMBERSHIP_PASS_APPLE_CERT_PASSWORD : '';
	}

	/**
	 * Get configured pass type identifier.
	 *
	 * @return string
	 */
	private function get_pass_type_identifier(): string {
		$config = new FinanceConfig();
		$value  = $config->get_membership_pass_apple_pass_type_identifier();
		if ( $value !== '' ) {
			return $value;
		}
		return defined( 'RONDO_MEMBERSHIP_PASS_APPLE_PASS_TYPE_IDENTIFIER' ) ? (string) RONDO_MEMBERSHIP_PASS_APPLE_PASS_TYPE_IDENTIFIER : '';
	}

	/**
	 * Get configured Apple team identifier.
	 *
	 * @return string
	 */
	private function get_team_identifier(): string {
		$config = new FinanceConfig();
		$value  = $config->get_membership_pass_apple_team_identifier();
		if ( $value !== '' ) {
			return $value;
		}
		return defined( 'RONDO_MEMBERSHIP_PASS_APPLE_TEAM_IDENTIFIER' ) ? (string) RONDO_MEMBERSHIP_PASS_APPLE_TEAM_IDENTIFIER : '';
	}

	/**
	 * Get organization display name.
	 *
	 * @return string
	 */
	private function get_organization_name(): string {
		$config = new FinanceConfig();
		$name   = $config->get_membership_pass_apple_organization_name();
		if ( $name === '' ) {
			$name = $config->get_display_name();
		}
		return $name !== '' ? $name : get_bloginfo( 'name' );
	}

	/**
	 * Resolve pass background color.
	 *
	 * @return string
	 */
	private function get_background_color( string $member_tier = '' ): string {
		if ( $member_tier === 'sponsor' ) {
			return 'rgb(255,255,255)';
		}

		$config = new FinanceConfig();
		$hex    = trim( $config->get_accent_color() );
		if ( preg_match( '/^#?([a-f0-9]{6})$/i', $hex, $matches ) ) {
			$hex = $matches[1];
			$r   = hexdec( substr( $hex, 0, 2 ) );
			$g   = hexdec( substr( $hex, 2, 2 ) );
			$b   = hexdec( substr( $hex, 4, 2 ) );
			return sprintf( 'rgb(%d,%d,%d)', $r, $g, $b );
		}
		return 'rgb(15,118,110)';
	}
}
