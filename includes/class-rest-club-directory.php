<?php
/** Read-only Sportlink address book with short-lived native WordPress caching. */

namespace Rondo\REST;

use Rondo\Core\UserRoles;

class ClubDirectory extends Base {
	private const TTL = 15 * MINUTE_IN_SECONDS;

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/external-clubs',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'search' ],
				'permission_callback' => [ $this, 'can_read' ],
				'args'                => [
					'search' => [
						'type'      => 'string',
						'required'  => true,
						'minLength' => 2,
						'maxLength' => 80,
					],
					'field'  => [
						'type'    => 'string',
						'enum'    => [ 'name', 'city' ],
						'default' => 'name',
					],
				],
			]
			);
		register_rest_route(
			'rondo/v1',
			'/external-clubs/(?P<id>[A-Z0-9]{7})',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'detail' ],
				'permission_callback' => [ $this, 'can_read' ],
			]
			);
	}

	public function can_read(): bool {
		return $this->check_user_approved() && ( UserRoles::can_access_section( 'teams' ) || UserRoles::can_access_section( 'wedstrijdzaken' ) );
	}

	public function search( \WP_REST_Request $request ) {
		$query = trim( sanitize_text_field( $request['search'] ) );
		if ( mb_strlen( $query ) < 2 ) {
			return new \WP_Error( 'directory_query_short', 'Vul minimaal twee tekens in.', [ 'status' => 400 ] );
		}
		return $this->fetch(
			'',
			[
				'search' => $query,
				'field'  => $request['field'],
			],
			false
			);
	}

	public function detail( \WP_REST_Request $request ) {
		return $this->fetch( '/' . $request['id'], [], true, $request['id'] );
	}

	private function fetch( string $path, array $query, bool $detail, string $id = '' ) {
		if ( ! defined( 'RONDO_SYNC_URL' ) || ! defined( 'RONDO_SYNC_API_KEY' ) ) {
			return new \WP_Error( 'directory_not_configured', 'De verbinding met Sportlink is nog niet ingesteld.', [ 'status' => 503 ] );
		}
		$key  = 'rondo_directory_' . md5( RONDO_SYNC_URL . $path . wp_json_encode( $query ) );
		$data = get_transient( $key );
		if ( $data === false ) {
			$url = rtrim( RONDO_SYNC_URL, '/' ) . '/api/club-directory' . $path;
			if ( $query ) {
				$url = add_query_arg( $query, $url );
			}
			$response = wp_remote_get(
				$url,
				[
					'headers'             => [ 'X-Sync-API-Key' => RONDO_SYNC_API_KEY ],
					'timeout'             => 180,
					'redirection'         => 0,
					'limit_response_size' => 2 * MB_IN_BYTES,
				]
				);
			if ( is_wp_error( $response ) ) {
				return new \WP_Error( 'directory_unavailable', 'Sportlink kon niet worden geraadpleegd. Probeer het later opnieuw.', [ 'status' => 502 ] );
			}
			$status = wp_remote_retrieve_response_code( $response );
			if ( $status !== 200 ) {
				$message = $status === 404 ? 'Deze vereniging is niet meer beschikbaar in Sportlink.' : ( $status === 429 ? 'Het adresboek is bezig. Probeer het over enkele seconden opnieuw.' : 'Sportlink is tijdelijk niet beschikbaar. Probeer het later opnieuw.' );
				return new \WP_Error( 'directory_unavailable', $message, [ 'status' => in_array( $status, [ 404, 429 ], true ) ? $status : 502 ] );
			}
			$data = $this->validated( json_decode( wp_remote_retrieve_body( $response ), true ), $detail, $id );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			set_transient( $key, $data, self::TTL );
		}
		$response = rest_ensure_response( $data );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/** Allowlist response fields; an incomplete source is an error, not an empty directory. */
	private function validated( $data, bool $detail, string $id ) {
		$error = new \WP_Error( 'directory_invalid', 'Sportlink gaf onvolledige gegevens terug. Probeer het later opnieuw.', [ 'status' => 502 ] );
		if ( ! is_array( $data ) || empty( $data['fetched_at'] ) || ! is_string( $data['fetched_at'] ) || ! strtotime( $data['fetched_at'] ) ) {
			return $error;
		}
		$result = [ 'fetched_at' => sanitize_text_field( $data['fetched_at'] ) ];
		$rows   = $detail ? [ $data['club'] ?? null ] : ( $data['clubs'] ?? null );
		if ( ! is_array( $rows ) || ! array_is_list( $rows ) ) {
			return $error;
		}
		$clubs = [];
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['id'] ?? null ) || ! preg_match( '/^[A-Z0-9]{7}$/', $row['id'] ) || empty( $row['name'] ) || ( $detail && $row['id'] !== $id ) ) {
				return $error;
			}
			$club = $this->strings( $row, [ 'id', 'name', 'district', 'address', 'postcode', 'city', 'phone', 'email' ] );
			if ( $club === null ) {
				return $error;
			}
			$clubs[] = $club;
		}
		if ( ! $detail ) {
			if ( ! is_int( $data['total'] ?? null ) || $data['total'] < count( $clubs ) || ! is_bool( $data['truncated'] ?? null ) || $data['truncated'] !== ( $data['total'] > count( $clubs ) ) ) {
				return $error;
			}
			return $result + [
				'clubs'     => $clubs,
				'total'     => $data['total'],
				'truncated' => $data['truncated'],
			];
		}
		if ( ! isset( $data['contacts'] ) || ! is_array( $data['contacts'] ) || ! array_is_list( $data['contacts'] ) ) {
			return $error;
		}
		$contacts = [];
		foreach ( $data['contacts'] as $row ) {
			if ( ! is_array( $row ) || empty( $row['id'] ) || empty( $row['name'] ) ) {
				return $error;
			}
			$contact = $this->strings( $row, [ 'id', 'name', 'function', 'phone', 'mobile', 'email', 'source' ] );
			if ( $contact === null ) {
				return $error;
			}
			$contacts[] = $contact;
		}
		return $result + [
			'club'     => $clubs[0],
			'contacts' => $contacts,
		];
	}

	private function strings( array $row, array $keys ): ?array {
		$result = [];
		foreach ( $keys as $key ) {
			if ( ! isset( $row[ $key ] ) || ! is_string( $row[ $key ] ) ) {
				return null;
			}
			$result[ $key ] = $key === 'email' ? sanitize_email( $row[ $key ] ) : sanitize_text_field( $row[ $key ] );
		}
		return $result;
	}
}
