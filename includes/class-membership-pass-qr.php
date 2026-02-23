<?php
/**
 * Membership pass QR token service.
 *
 * Issues and verifies signed JWT payloads for membership passes.
 */

namespace Rondo\Passes;

use Rondo\Fees\MembershipFees;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MembershipPassQr {

	const OPTION_JWT_SECRET = 'rondo_membership_pass_jwt_secret';
	const DEFAULT_TTL_DAYS  = 365;
	const TOKEN_AUDIENCE    = 'rondo-membership-pass';

	/**
	 * Issue a signed QR token for a person.
	 *
	 * @param int   $person_id Person post ID.
	 * @param array $options   Optional params: season, ttl_days.
	 * @return array|\WP_Error
	 */
	public function issue_for_person( int $person_id, array $options = [] ) {
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'membership_pass_person_not_found', 'Persoon niet gevonden.', [ 'status' => 404 ] );
		}

		$season = isset( $options['season'] ) && is_string( $options['season'] ) ? sanitize_text_field( $options['season'] ) : '';
		if ( $season === '' ) {
			$season = ( new MembershipFees() )->get_season_key();
		}

		if ( ! preg_match( '/^\d{4}-\d{4}$/', $season ) ) {
			return new \WP_Error( 'membership_pass_invalid_season', 'Ongeldig seizoenformaat.', [ 'status' => 400 ] );
		}

		$ttl_days = isset( $options['ttl_days'] ) ? (int) $options['ttl_days'] : self::DEFAULT_TTL_DAYS;
		$ttl_days = max( 1, min( 730, $ttl_days ) );

		$status = $this->get_person_status( $person_id, $season );
		$now    = time();

		$payload = [
			'iss'    => home_url( '/' ),
			'aud'    => self::TOKEN_AUDIENCE,
			'sub'    => (string) $person_id,
			'pid'    => $person_id,
			'season' => $season,
			'status' => $status['status'],
			'iat'    => $now,
			'nbf'    => $now - 30,
			'exp'    => $now + ( DAY_IN_SECONDS * $ttl_days ),
			'jti'    => wp_generate_uuid4(),
		];

		$knvb_id = get_field( 'knvb-id', $person_id );
		if ( ! empty( $knvb_id ) ) {
			$payload['knvb_id'] = (string) $knvb_id;
		}

		$token = $this->encode_jwt( $payload );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		return [
			'token'   => $token,
			'payload' => $payload,
			'person'  => $this->get_person_summary( $person_id, $status, $season ),
		];
	}

	/**
	 * Verify a signed token.
	 *
	 * @param string $token JWT token.
	 * @return array|\WP_Error
	 */
	public function verify_token( string $token ) {
		$parts = explode( '.', trim( $token ) );
		if ( count( $parts ) !== 3 ) {
			return new \WP_Error( 'membership_pass_invalid_token', 'Ongeldig tokenformaat.', [ 'status' => 400 ] );
		}

		list( $encoded_header, $encoded_payload, $encoded_signature ) = $parts;

		$header = $this->decode_json_part( $encoded_header );
		if ( is_wp_error( $header ) ) {
			return $header;
		}

		if ( ! isset( $header['alg'] ) || $header['alg'] !== 'HS256' ) {
			return new \WP_Error( 'membership_pass_invalid_alg', 'Ongeldig token-algoritme.', [ 'status' => 400 ] );
		}

		$payload = $this->decode_json_part( $encoded_payload );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$secret = $this->get_or_create_secret();
		if ( is_wp_error( $secret ) ) {
			return $secret;
		}

		$expected_signature = $this->base64url_encode( hash_hmac( 'sha256', $encoded_header . '.' . $encoded_payload, $secret, true ) );
		if ( ! hash_equals( $expected_signature, $encoded_signature ) ) {
			return new \WP_Error( 'membership_pass_invalid_signature', 'Tokenhandtekening ongeldig.', [ 'status' => 401 ] );
		}

		$now = time();
		if ( isset( $payload['nbf'] ) && is_numeric( $payload['nbf'] ) && $now < (int) $payload['nbf'] ) {
			return new \WP_Error( 'membership_pass_not_yet_valid', 'Token is nog niet geldig.', [ 'status' => 401 ] );
		}
		if ( ! isset( $payload['exp'] ) || ! is_numeric( $payload['exp'] ) || $now >= (int) $payload['exp'] ) {
			return new \WP_Error( 'membership_pass_expired', 'Token is verlopen.', [ 'status' => 401 ] );
		}
		if ( ! isset( $payload['aud'] ) || self::TOKEN_AUDIENCE !== $payload['aud'] ) {
			return new \WP_Error( 'membership_pass_invalid_audience', 'Token audience ongeldig.', [ 'status' => 400 ] );
		}

		return [
			'header'  => $header,
			'payload' => $payload,
		];
	}

	/**
	 * Resolve membership status from person data.
	 *
	 * @param int    $person_id Person ID.
	 * @param string $season Season key.
	 * @return array
	 */
	public function get_person_status( int $person_id, string $season ): array {
		$is_former = (bool) get_field( 'former_member', $person_id );
		$lid_tot   = get_field( 'lid-tot', $person_id );
		$today     = gmdate( 'Y-m-d' );

		$status = 'active';
		if ( $is_former ) {
			$status = 'former';
		} elseif ( ! empty( $lid_tot ) && is_string( $lid_tot ) && $lid_tot < $today ) {
			$status = 'expired';
		}

		$season_end = $this->season_end_date( $season );

		return [
			'status'        => $status,
			'former_member' => $is_former,
			'lid_tot'       => ! empty( $lid_tot ) ? $lid_tot : null,
			'season_end'    => $season_end,
		];
	}

	/**
	 * Build summary data needed by scanners/wallet providers.
	 *
	 * @param int    $person_id Person ID.
	 * @param array  $status Status array.
	 * @param string $season Season key.
	 * @return array
	 */
	private function get_person_summary( int $person_id, array $status, string $season ): array {
		$first_name = (string) ( get_field( 'first_name', $person_id ) ?: '' );
		$infix      = (string) ( get_field( 'infix', $person_id ) ?: '' );
		$last_name  = (string) ( get_field( 'last_name', $person_id ) ?: '' );
		$full_name  = trim( preg_replace( '/\s+/', ' ', $first_name . ' ' . $infix . ' ' . $last_name ) );

		$knvb_id = get_field( 'knvb-id', $person_id );

		return [
			'id'             => $person_id,
			'name'           => $full_name,
			'first_name'     => $first_name,
			'last_name'      => $last_name,
			'knvb_id'        => ! empty( $knvb_id ) ? (string) $knvb_id : null,
			'team'           => $this->get_current_team_name( $person_id ),
			'season'         => $season,
			'status'         => $status['status'],
			'former_member'  => $status['former_member'],
			'lid_tot'        => $status['lid_tot'],
			'season_end'     => $status['season_end'],
			'photo_thumbnail' => get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: '',
		];
	}

	/**
	 * Encode JWT using HS256.
	 *
	 * @param array $payload JWT payload.
	 * @return string|\WP_Error
	 */
	private function encode_jwt( array $payload ) {
		$secret = $this->get_or_create_secret();
		if ( is_wp_error( $secret ) ) {
			return $secret;
		}

		$header = [
			'alg' => 'HS256',
			'typ' => 'JWT',
		];
		$encoded_header  = $this->base64url_encode( wp_json_encode( $header ) );
		$encoded_payload = $this->base64url_encode( wp_json_encode( $payload ) );
		$signature       = hash_hmac( 'sha256', $encoded_header . '.' . $encoded_payload, $secret, true );

		return $encoded_header . '.' . $encoded_payload . '.' . $this->base64url_encode( $signature );
	}

	/**
	 * Decode a base64url JSON part.
	 *
	 * @param string $encoded Encoded value.
	 * @return array|\WP_Error
	 */
	private function decode_json_part( string $encoded ) {
		$decoded = $this->base64url_decode( $encoded );
		if ( $decoded === false ) {
			return new \WP_Error( 'membership_pass_invalid_encoding', 'Token encoding ongeldig.', [ 'status' => 400 ] );
		}

		$data = json_decode( $decoded, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'membership_pass_invalid_payload', 'Token payload ongeldig.', [ 'status' => 400 ] );
		}

		return $data;
	}

	/**
	 * Get secret key from options or create it on first use.
	 *
	 * @return string|\WP_Error
	 */
	private function get_or_create_secret() {
		$secret = (string) get_option( self::OPTION_JWT_SECRET, '' );
		if ( strlen( $secret ) >= 32 ) {
			return $secret;
		}

		try {
			$secret = bin2hex( random_bytes( 32 ) );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'membership_pass_secret_failed', 'Kon geen geheime sleutel genereren.', [ 'status' => 500 ] );
		}

		update_option( self::OPTION_JWT_SECRET, $secret, false );
		return $secret;
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
	 * Base64url decode helper.
	 *
	 * @param string $data Encoded value.
	 * @return string|false
	 */
	private function base64url_decode( string $data ) {
		$remainder = strlen( $data ) % 4;
		if ( $remainder > 0 ) {
			$data .= str_repeat( '=', 4 - $remainder );
		}
		return base64_decode( strtr( $data, '-_', '+/' ), true );
	}

	/**
	 * Get active/current team label from work history.
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
	 * Resolve season end date (YYYY-06-30).
	 *
	 * @param string $season Season key.
	 * @return string|null
	 */
	private function season_end_date( string $season ): ?string {
		if ( ! preg_match( '/^\d{4}-(\d{4})$/', $season, $matches ) ) {
			return null;
		}
		return $matches[1] . '-06-30';
	}
}
