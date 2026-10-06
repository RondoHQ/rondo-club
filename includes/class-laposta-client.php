<?php
/** Restricted Laposta client: campaign drafts, audience metadata and testmail. */
namespace Rondo\Integrations;

use Rondo\Data\CredentialEncryption;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LapostaClient {
	public const KEY_OPTION = 'rondo_laposta_campaign_key';

	public function request( string $method, string $path, array $data = [] ) {
		// No member, campaign send, schedule or delete endpoint can pass this boundary.
		$read  = preg_match( '#^/(list|segment|campaign)(/[a-zA-Z0-9]+)?(/content)?$#', $path );
		$write = preg_match( '#^/campaign(/[a-zA-Z0-9]+)?(/content)?$#', $path );
		$test  = preg_match( '#^/campaign/[a-zA-Z0-9]+/action/testmail$#', $path )
			&& array_keys( $data ) === [ 'email' ] && is_string( $data['email'] ) && is_email( $data['email'] );
		if ( ! ( $method === 'GET' && $read ) && ! ( $method === 'POST' && ( $write || $test ) ) ) {
			return new \WP_Error( 'laposta_endpoint', 'Deze Laposta-actie is niet toegestaan.', [ 'status' => 400 ] );
		}
		$key = CredentialEncryption::get_secret_option( self::KEY_OPTION );
		if ( $key === '' || get_option( 'rondo_is_demo_site', false ) ) {
			return new \WP_Error( 'laposta_unconfigured', 'Laat een beheerder de Laposta-verbinding instellen.', [ 'status' => 400 ] );
		}
		$retry_at = (int) get_transient( 'rondo_laposta_retry_at' );
		if ( $retry_at > time() ) {
			return new \WP_Error(
				'laposta_rate_limit',
				'Laposta vraagt even te wachten. Probeer het straks opnieuw.',
				[
					'status'      => 429,
					'retry_after' => $retry_at - time(),
				]
				);
		}
		$url  = 'https://api.laposta.nl/v2' . $path;
		$args = [
			'method'      => $method,
			'timeout'     => 25,
			'redirection' => 0,
			'headers'     => [
				'Authorization' => 'Basic ' . base64_encode( $key . ':' ),
				'Accept'        => 'application/json',
			],
		];
		if ( $method === 'GET' && $data ) {
			$url = add_query_arg( $data, $url );
		} elseif ( $method === 'POST' ) {
			$args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
			$args['body']                    = http_build_query( $data, '', '&' );
		}
		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'laposta_unreachable', 'Laposta kon niet worden bereikt. De uitkomst wordt bij de volgende poging gecontroleerd.', [ 'status' => 502 ] );
		}
		$status = wp_remote_retrieve_response_code( $response );
		$json   = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $status === 429 ) {
			$wait = max( 1, min( 3600, (int) wp_remote_retrieve_header( $response, 'retry-after' ) ?: 60 ) );
			set_transient( 'rondo_laposta_retry_at', time() + $wait, $wait );
		}
		if ( $status < 200 || $status >= 300 || ! is_array( $json ) || isset( $json['error'] ) ) {
			// Never surface remote response bodies, credential material or submitted HTML.
			$message = $status === 429 ? 'Laposta vraagt even te wachten. Probeer het straks opnieuw.' : 'Laposta heeft het verzoek niet bevestigd. Controleer de verbinding, doelgroep en het goedgekeurde afzendadres.';
			return new \WP_Error(
				'laposta_request_failed',
				$message,
				[
					'status'      => $status === 429 ? 429 : 502,
					'http_status' => $status,
				]
				);
		}
		return $json;
	}
}
