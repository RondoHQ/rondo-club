<?php
/** AgentMail inbox reader for Twelve reports. */
namespace Rondo\Twelve;

use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgentMailClient {
	public const OPTION = 'rondo_twelve_agentmail_credentials';
	public const SENDER = 'noreply@twelve.eu';
	private array $credentials;

	public function __construct( array $credentials ) {
		$this->credentials = $credentials;
	}

	public static function from_stored_credentials() {
		$credentials = json_decode( CredentialEncryption::get_secret_option( self::OPTION, '' ), true );
		if ( empty( $credentials['api_key'] ) || empty( $credentials['inbox_id'] ) ) {
			return new \WP_Error( 'twelve_no_credentials', 'Configureer AgentMail via wp rondo twelve auth.' );
		}
		return new self( $credentials );
	}

	public static function store_credentials( string $api_key, string $inbox_id ): bool {
		$json = (string) wp_json_encode( compact( 'api_key', 'inbox_id' ) );
		if ( CredentialEncryption::get_secret_option( self::OPTION, '' ) === $json ) {
			return true;
		}
		return CredentialEncryption::update_secret_option( self::OPTION, $json );
	}

	public static function clear_credentials(): bool {
		return CredentialEncryption::update_secret_option( self::OPTION, '' );
	}

	public static function has_credentials(): bool {
		return ! is_wp_error( self::from_stored_credentials() );
	}

	private function request( string $path, array $query = [] ) {
		$url      = 'https://api.agentmail.to/v0/inboxes/' . rawurlencode( $this->credentials['inbox_id'] ) . $path;
		$response = wp_remote_get(
			add_query_arg( $query, $url ),
			[
				'headers'     => [ 'Authorization' => 'Bearer ' . $this->credentials['api_key'] ],
				'timeout'     => 30,
				'redirection' => 0,
			]
			);
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'twelve_agentmail_error', 'AgentMail verzoek mislukt.' );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( wp_remote_retrieve_response_code( $response ) !== 200 || ! is_array( $data ) ) {
			return new \WP_Error( 'twelve_agentmail_error', 'AgentMail gaf een ongeldige respons.' );
		}
		return $data;
	}

	/** Scan all pages, oldest first, so missed runs can catch up. */
	public function list_report_message_ids() {
		$ids   = [];
		$seen  = [];
		$query = [
			'limit'     => 100,
			'ascending' => 'true',
			'labels'    => 'received',
		];
		do {
			$page = $this->request( '/messages', $query );
			if ( is_wp_error( $page ) ) {
				return $page;
			}
			if ( ! isset( $page['messages'] ) || ! is_array( $page['messages'] ) ) {
				return new \WP_Error( 'twelve_agentmail_error', 'AgentMail berichtenlijst ontbreekt.' );
			}
			foreach ( $page['messages'] as $message ) {
				$from = strtolower( trim( (string) ( $message['from'] ?? '' ) ) );
				if ( preg_match( '/<([^>]+)>$/', $from, $match ) ) {
					$from = $match[1];
				}
				if ( $from === self::SENDER && ! empty( $message['message_id'] ) ) {
					$ids[] = $message['message_id'];
				}
			}
			$token = $page['next_page_token'] ?? '';
			if ( $token !== '' && isset( $seen[ $token ] ) ) {
				return new \WP_Error( 'twelve_agentmail_error', 'AgentMail herhaalde een paginatoken.' );
			}
			$seen[ $token ]      = true;
			$query['page_token'] = $token;
		} while ( $token !== '' );
		return array_values( array_unique( $ids ) );
	}

	public function download_report_pdf( string $message_id ) {
		$path    = '/messages/' . rawurlencode( $message_id );
		$message = $this->request( $path );
		if ( is_wp_error( $message ) ) {
			return $message;
		}
		foreach ( $message['attachments'] ?? [] as $attachment ) {
			if ( ( $attachment['content_type'] ?? '' ) !== 'application/pdf' ) {
				continue;
			}
			$download = $this->request( $path . '/attachments/' . rawurlencode( $attachment['attachment_id'] ) );
			if ( is_wp_error( $download ) ) {
				return $download;
			}
			$url = $download['download_url'] ?? '';
			if ( wp_parse_url( $url, PHP_URL_SCHEME ) !== 'https' ) {
				return new \WP_Error( 'twelve_bad_pdf', 'Ongeldige download-URL.' );
			}
			// Signed download URL; never forward the API key to its host.
			$response = wp_safe_remote_get(
				$url,
				[
					'timeout'             => 60,
					'limit_response_size' => 10485761,
				]
				);
			if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
				return new \WP_Error( 'twelve_bad_pdf', 'PDF downloaden mislukt.' );
			}
			$bytes = wp_remote_retrieve_body( $response );
			if ( strlen( $bytes ) > 10485760 || ! str_starts_with( $bytes, '%PDF' ) ) {
				return new \WP_Error( 'twelve_bad_pdf', 'PDF ongeldig of groter dan 10 MB.' );
			}
			return [
				'filename' => $attachment['filename'] ?? 'rapportage.pdf',
				'bytes'    => $bytes,
			];
		}
		return new \WP_Error( 'twelve_no_pdf', 'Geen PDF-bijlage gevonden.' );
	}
}
