<?php
/** Newsletter fields, profiles and deterministic HTML rendering. */
namespace Rondo\Communication;

use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Newsletter {
	public const CONFIG  = 'rondo_newsletter_config';
	public const PROFILE = 'rondo_newsletter_profile';
	public const EXPORT  = '_rondo_newsletter_export';
	public const FIELDS  = [ 'newsletter_subject', 'newsletter_preheader', 'newsletter_heading', 'newsletter_body', 'newsletter_audiences' ];

	public static function config(): array {
		return wp_parse_args(
			get_option( self::CONFIG, [] ),
			[
				'template'   => '',
				'salutation' => 'Beste lezer,',
				'channel_id' => 'newsletter',
			]
			);
	}

	public static function profile( int $user_id ): array {
		$data = get_user_meta( $user_id, self::PROFILE, true );
		return wp_parse_args(
			is_array( $data ) ? $data : [],
			[
				'name'             => '',
				'role'             => '',
				'from_name'        => '',
				'from_email'       => '',
				'reply_to'         => '',
				'signature_url'    => '',
				'signature_height' => 80,
				'active'           => false,
			]
			);
	}

	public static function profile_ready( array $profile ): bool {
		return ! empty( $profile['active'] ) && trim( $profile['name'] ) !== '' && trim( $profile['role'] ) !== '' && trim( $profile['from_name'] ) !== '' && is_email( $profile['from_email'] ) && is_email( $profile['reply_to'] );
	}

	public static function sanitize_body( string $html ): string {
		return wp_kses(
			$html,
			[
				'p'      => [],
				'br'     => [],
				'strong' => [],
				'b'      => [],
				'em'     => [],
				'i'      => [],
				's'      => [],
				'ul'     => [],
				'ol'     => [],
				'li'     => [],
				'h2'     => [],
				'h3'     => [],
				'img'    => [
					'src'    => true,
					'alt'    => true,
					'title'  => true,
					'width'  => true,
					'height' => true,
				],
				'a'      => [
					'href'  => true,
					'title' => true,
				],
			],
			[ 'https', 'http', 'mailto' ]
			);
	}

	private static function render_body( string $html ): string {
		$processor       = new \WP_HTML_Tag_Processor( self::sanitize_body( $html ) );
		$list_item_depth = 0;
		while ( $processor->next_tag( [ 'tag_closers' => 'visit' ] ) ) {
			$tag = $processor->get_tag();
			if ( $processor->is_tag_closer() ) {
				if ( $tag === 'LI' ) {
					$list_item_depth = max( 0, $list_item_depth - 1 );
				}
				continue;
			}
			// Email clients need inline styles matching the editor's spacing.
			if ( $tag === 'IMG' ) {
				$processor->set_attribute( 'style', 'max-width:100%;height:auto;display:block;margin:12px 0;' );
			} elseif ( in_array( $tag, [ 'H2', 'H3' ], true ) ) {
				$processor->set_attribute( 'style', 'margin:24px 0 8px;line-height:1.4;' );
			} elseif ( in_array( $tag, [ 'UL', 'OL' ], true ) ) {
				$processor->set_attribute( 'style', 'margin:0 0 12px;padding:0 0 0 24px;' );
			} elseif ( $tag === 'LI' ) {
				++$list_item_depth;
				$processor->set_attribute( 'style', 'margin:0 0 4px;' );
			} elseif ( $tag === 'P' && $list_item_depth > 0 ) {
				// Override the template's important paragraph rule only inside lists.
				$processor->set_attribute( 'style', 'margin:0!important;' );
			}
		}
		return $processor->get_updated_html();
	}

	public static function draft( int $id ): array {
		$draft = [ 'assignee_id' => (int) Fields::get_for_post( $id, 'assignee_id' ) ];
		foreach ( self::FIELDS as $name ) {
			$draft[ $name ] = Fields::get_for_post( $id, $name ) ?? '';
		}
		$draft['newsletter_audiences'] = is_array( $draft['newsletter_audiences'] ) ? $draft['newsletter_audiences'] : [];
		return $draft;
	}

	public static function revision( int $id ): string {
		return hash( 'sha256', wp_json_encode( self::draft( $id ) ) );
	}

	public static function fingerprint( int $id ): string {
		$draft = self::draft( $id );
		return hash( 'sha256', wp_json_encode( [ $draft, self::profile( $draft['assignee_id'] ), self::config() ] ) );
	}

	public static function sanitize_draft( array $data ) {
		$draft = [];
		if ( array_diff( array_keys( $data ), array_merge( self::FIELDS, [ 'assignee_id' ] ) ) ) {
			return new \WP_Error( 'newsletter_fields', 'De nieuwsbrief bevat onbekende velden.', [ 'status' => 400 ] );
		}
		foreach ( [
			'newsletter_subject'   => 200,
			'newsletter_preheader' => 250,
			'newsletter_heading'   => 200,
			'newsletter_body'      => 100000,
		] as $key => $limit ) {
			$value = $data[ $key ] ?? '';
			if ( ! is_string( $value ) || mb_strlen( $value ) > $limit ) {
				return new \WP_Error(
					'newsletter_value',
					'Controleer de lengte en inhoud van de nieuwsbriefvelden.',
					[
						'status' => 400,
						'field'  => $key,
					]
					);
			}
			$draft[ $key ] = $key === 'newsletter_body' ? self::sanitize_body( $value ) : sanitize_text_field( $value );
		}
		$assignee = $data['assignee_id'] ?? 0;
		if ( ! is_int( $assignee ) || $assignee < 0 || ( $assignee && ! UserRoles::can_access_section( 'communicatie', $assignee ) ) ) {
			return new \WP_Error( 'newsletter_assignee', 'Kies een verantwoordelijke met toegang tot Communicatie.', [ 'status' => 400 ] );
		}
		$draft['assignee_id'] = $assignee;
		$rows                 = $data['newsletter_audiences'] ?? [];
		if ( ! is_array( $rows ) || count( $rows ) > 10 ) {
			return new \WP_Error( 'newsletter_audiences', 'Kies maximaal tien lijsten.', [ 'status' => 400 ] );
		}
		$seen = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || array_diff( array_keys( $row ), [ 'list_id', 'segment_id', 'scope' ] ) || ! isset( $row['list_id'], $row['segment_id'], $row['scope'] ) || ! is_string( $row['list_id'] ) || ! is_string( $row['segment_id'] ) || ! in_array( $row['scope'], [ '', 'all', 'segment' ], true ) || ! preg_match( '/^[a-zA-Z0-9]*$/D', $row['list_id'] ) || ! preg_match( '/^[a-zA-Z0-9]*$/D', $row['segment_id'] ) || strlen( $row['list_id'] ) > 40 || strlen( $row['segment_id'] ) > 40 || ( $row['list_id'] && in_array( $row['list_id'], $seen, true ) ) || ( $row['scope'] === 'all' && $row['segment_id'] !== '' ) ) {
				return new \WP_Error( 'newsletter_audiences', 'Kies per lijst één segment of expliciet de hele lijst.', [ 'status' => 400 ] );
			}
			$seen[] = $row['list_id'];
		}
		$draft['newsletter_audiences'] = array_values( $rows );
		return $draft;
	}

	public static function render( array $draft ) {
		$config  = self::config();
		$profile = self::profile( $draft['assignee_id'] );
		if ( ! self::profile_ready( $profile ) ) {
			return new \WP_Error( 'newsletter_profile_missing', 'Het ondertekeningsprofiel is onvolledig. Laat een beheerder dit instellen.', [ 'status' => 400 ] );
		}
		if ( ! $config['template'] ) {
			return new \WP_Error( 'newsletter_template_missing', 'Laat een beheerder de nieuwsbrief-template instellen.', [ 'status' => 400 ] );
		}
		$values = [
			'DOCUMENT_TITLE'   => $draft['newsletter_subject'],
			'HEADING'          => $draft['newsletter_heading'],
			'PREHEADER'        => $draft['newsletter_preheader'],
			'SALUTATION'       => $config['salutation'],
			'SIGNER_NAME'      => $profile['name'],
			'SIGNER_ROLE'      => $profile['role'],
			'SIGNATURE_URL'    => $profile['signature_url'],
			'SIGNATURE_HEIGHT' => (string) $profile['signature_height'],
		];
		$html   = preg_replace_callback(
			'/%%([A-Z_]+)%%/',
			static function ( $match ) use ( $values, $draft ) {
				return $match[1] === 'BODY_HTML' ? self::render_body( $draft['newsletter_body'] ) : esc_html( $values[ $match[1] ] ?? '' );
			},
			$config['template']
			);
		if ( $profile['signature_url'] === '' ) {
			$html = preg_replace( '/<img\b[^>]*\bsrc=""[^>]*>/i', '', $html );
		}
		return rtrim( $html );
	}

	public static function preview_html( string $html ): string {
		// Sandbox plus CSP: mail links cannot navigate and template scripts cannot run.
		$html = preg_replace( '/<a\b[^>]*>/i', '<a>', $html );
		$csp  = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src https:; style-src \'unsafe-inline\'; font-src https:;">';
		return preg_replace( '/<head\b[^>]*>/i', '<head>' . $csp, $html, 1 );
	}

	public static function audit( int $id, string $event ): void {
		$log   = get_post_meta( $id, 'audit_log', true );
		$log   = is_array( $log ) ? $log : [];
		$log[] = [
			'event'     => $event,
			'details'   => [],
			'user_id'   => get_current_user_id(),
			'user_name' => wp_get_current_user()->display_name,
			'date'      => current_time( 'c', true ),
		];
		update_post_meta( $id, 'audit_log', array_slice( $log, -200 ) );
	}
}
