<?php
/**
 * Gmail client for the Twelve daily revenue reports.
 *
 * Uses google/apiclient (already a composer dependency) with a refresh token
 * to read the mailbox that receives the Twelve mails. Credentials are never
 * stored in the repo: they live encrypted in a WordPress option (see
 * Rondo\Data\CredentialEncryption) and are set via `wp rondo twelve auth`.
 */

namespace Rondo\Twelve;

use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GmailClient {

	/** Option holding the encrypted credentials JSON. */
	public const OPTION = 'rondo_twelve_gmail_credentials';

	/** Sender of the daily Twelve reports. */
	public const SENDER = 'noreply@twelve.eu';

	/** @var array{client_id: string, client_secret: string, refresh_token: string} */
	private array $credentials;

	/**
	 * @param array{client_id: string, client_secret: string, refresh_token: string} $credentials
	 */
	public function __construct( array $credentials ) {
		$this->credentials = $credentials;
	}

	/**
	 * Build a client from the stored (encrypted) credentials.
	 *
	 * @return self|\WP_Error
	 */
	public static function from_stored_credentials() {
		$json = CredentialEncryption::get_secret_option( self::OPTION, '' );
		if ( $json === '' ) {
			return new \WP_Error(
				'twelve_no_credentials',
				'Geen Gmail-credentials gevonden. Draai eerst: wp rondo twelve auth --client-id=... --client-secret=... --refresh-token=...'
			);
		}

		$credentials = json_decode( $json, true );
		if ( ! is_array( $credentials )
			|| empty( $credentials['client_id'] )
			|| empty( $credentials['client_secret'] )
			|| empty( $credentials['refresh_token'] )
		) {
			return new \WP_Error( 'twelve_bad_credentials', 'Opgeslagen Gmail-credentials zijn onvolledig.' );
		}

		return new self( $credentials );
	}

	/**
	 * Store credentials encrypted. Overwrites any previous value.
	 */
	public static function store_credentials( string $client_id, string $client_secret, string $refresh_token ): bool {
		return CredentialEncryption::update_secret_option(
			self::OPTION,
			(string) wp_json_encode(
				[
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
				]
			)
		);
	}

	/**
	 * Remove the stored credentials.
	 */
	public static function clear_credentials(): bool {
		return CredentialEncryption::update_secret_option( self::OPTION, '' );
	}

	/**
	 * Whether credentials are stored (without exposing them).
	 */
	public static function has_credentials(): bool {
		return CredentialEncryption::get_secret_option( self::OPTION, '' ) !== '';
	}

	/**
	 * Find the most recent Twelve report message.
	 *
	 * @return array{id: string, subject: string, date: string}|\WP_Error|null Null when no message found.
	 */
	public function find_latest_message() {
		try {
			$gmail = $this->service();
			$list  = $gmail->users_messages->listUsersMessages(
				'me',
				[
					'q'          => 'from:' . self::SENDER . ' newer_than:3d',
					'maxResults' => 5,
				]
			);
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'twelve_gmail_error', 'Gmail zoeken mislukt: ' . $e->getMessage() );
		}

		$messages = $list->getMessages() ?: [];
		if ( empty( $messages ) ) {
			return null;
		}

		return $this->message_summary( $messages[0]->getId() );
	}

	/**
	 * Get id, subject and date for one message.
	 *
	 * @return array{id: string, subject: string, date: string}|\WP_Error
	 */
	public function message_summary( string $message_id ) {
		try {
			$full    = $this->service()->users_messages->get(
				'me',
				$message_id,
				[
					'format'          => 'metadata',
					'metadataHeaders' => [ 'Subject', 'Date' ],
				]
				);
			$headers = [];
			foreach ( (array) $full->getPayload()->getHeaders() as $header ) {
				$headers[ strtolower( $header->getName() ) ] = $header->getValue();
			}
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'twelve_gmail_error', 'Gmail-message ophalen mislukt: ' . $e->getMessage() );
		}

		return [
			'id'      => $message_id,
			'subject' => $headers['subject'] ?? '',
			'date'    => $headers['date'] ?? '',
		];
	}

	/**
	 * Download the PDF attachment of a Twelve report message.
	 *
	 * @return array{filename: string, bytes: string}|\WP_Error
	 */
	public function download_report_pdf( string $message_id ) {
		try {
			$gmail = $this->service();
			$full  = $gmail->users_messages->get( 'me', $message_id, [ 'format' => 'full' ] );
			$found = $this->find_pdf_part( $full->getPayload() );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'twelve_gmail_error', 'PDF downloaden mislukt: ' . $e->getMessage() );
		}

		if ( $found === null ) {
			return new \WP_Error( 'twelve_no_pdf', 'Geen PDF-bijlage gevonden in dit bericht.' );
		}

		try {
			$attachment = $gmail->users_messages_attachments->get( 'me', $message_id, $found['attachment_id'] );
			$bytes      = (string) base64_decode( strtr( (string) $attachment->getData(), '-_', '+/' ) );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'twelve_gmail_error', 'Bijlage ophalen mislukt: ' . $e->getMessage() );
		}

		if ( $bytes === '' || ! str_starts_with( $bytes, '%PDF' ) ) {
			return new \WP_Error( 'twelve_bad_pdf', 'De bijlage is geen geldige PDF.' );
		}

		return [
			'filename' => $found['filename'],
			'bytes'    => $bytes,
		];
	}

	/**
	 * Build an authenticated Gmail service.
	 *
	 * @throws \Exception When the token cannot be refreshed.
	 */
	private function service(): \Google_Service_Gmail {
		$client = new \Google_Client();
		$client->setClientId( $this->credentials['client_id'] );
		$client->setClientSecret( $this->credentials['client_secret'] );
		$client->setAccessType( 'offline' );

		$token = $client->fetchAccessTokenWithRefreshToken( $this->credentials['refresh_token'] );
		if ( isset( $token['error'] ) ) {
			$error = is_string( $token['error'] ) ? $token['error'] : 'onbekende fout';
			throw new \Exception( 'Token verversen mislukt: ' . $error );
		}
		$client->setAccessToken( $token );

		return new \Google_Service_Gmail( $client );
	}

	/**
	 * Recursively find the first PDF part in a message payload.
	 *
	 * @param \Google_Service_Gmail_MessagePart $part
	 * @return array{filename: string, attachment_id: string}|null
	 */
	private function find_pdf_part( $part ): ?array {
		$filename = (string) $part->getFilename();
		$mime     = (string) $part->getMimeType();
		$body     = $part->getBody();

		if ( $filename !== ''
			&& ( str_ends_with( strtolower( $filename ), '.pdf' ) || $mime === 'application/pdf' )
			&& $body
			&& $body->getAttachmentId()
		) {
			return [
				'filename'      => $filename,
				'attachment_id' => $body->getAttachmentId(),
			];
		}

		foreach ( (array) $part->getParts() as $sub ) {
			$found = $this->find_pdf_part( $sub );
			if ( $found !== null ) {
				return $found;
			}
		}

		return null;
	}
}
