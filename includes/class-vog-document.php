<?php
/** Local PDF extraction, official validation and conservative matching. */
namespace Rondo\VOG;

use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VogDocument {
	/** Execute the isolated reader without a shell or document content in logs. */
	public static function read( string $path ): array {
		if ( ! function_exists( 'proc_open' ) ) {
			return [];
		}
		$python = defined( 'RONDO_VOG_PYTHON' ) ? RONDO_VOG_PYTHON : '/bin/python3';
		// Bound local helper, array arguments; required for encrypted VOG PDFs.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
		$process = proc_open(
			[ $python, '-I', dirname( __DIR__ ) . '/bin/vog/read.py', $path ],
			[
				0 => [ 'pipe', 'r' ],
				1 => [ 'pipe', 'w' ],
				2 => [ 'file', '/dev/null', 'a' ],
			],
			$pipes
			);
		if ( ! is_resource( $process ) ) {
			return [];
		}
		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		$output = '';
		$until  = microtime( true ) + 10;
		do {
			$output .= stream_get_contents( $pipes[1], 262145 - strlen( $output ) );
			$status  = proc_get_status( $process );
			if ( ! $status['running'] ) {
				$output .= stream_get_contents( $pipes[1], max( 1, 262145 - strlen( $output ) ) );
				break;
			}
			usleep( 10000 );
		} while ( microtime( true ) < $until && strlen( $output ) < 262144 );
		if ( $status['running'] ) {
			proc_terminate( $process, 9 );
		}
		fclose( $pipes[1] );
		proc_close( $process );
		$data = json_decode( $output, true );
		return is_array( $data ) && empty( $data['error'] ) && strlen( $output ) <= 262144 ? $data : [];
	}

	/** Only send the unchanged PDF to the fixed official destination. */
	public static function validate( string $path ): ?int {
		$boundary = 'rondo-' . wp_generate_password( 32, false, false );
		$body     = '--' . $boundary . "\r\nContent-Disposition: form-data; name=\"file\"; filename=\"vog.pdf\"\r\nContent-Type: application/pdf\r\n\r\n" . file_get_contents( $path ) . "\r\n--" . $boundary . "--\r\n";
		$response = wp_remote_post(
			'https://www.validatie.nl/api/valideer/',
			[
				// GAAV rejects the generic WordPress user agent; identify this client explicitly.
				'user-agent'          => 'RondoClub/' . wp_get_theme()->get( 'Version' ) . ' (+' . home_url( '/' ) . ')',
				'timeout'             => 15,
				'redirection'         => 0,
				'sslverify'           => true,
				'limit_response_size' => 65536,
				'headers'             => [ 'Content-Type' => 'multipart/form-data; boundary=' . $boundary ],
				'body'                => $body,
			]
			);
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = $data['response_code'] ?? null;
		return is_int( $code ) && $code >= 0 && $code <= 7 ? $code : null;
	}

	/** Parse only the first page's explicit selection, never the reverse legend. */
	public static function parse( string $text ): array {
		if ( ! str_contains( $text, 'Verklaring Omtrent het Gedrag' ) ) {
			return [];
		}
		$patterns = [
			'date'       => '/Date\s+Datum\s+([^\r\n]+)/u',
			'reference'  => '/Our reference\s+Ons kenmerk\s+(\d+)/u',
			'last_name'  => '/Surname\s+Geslachtsnaam[^\S\r\n]+([^\r\n]+)/u',
			'infix'      => '/Prefix to surname\s+Tussenvoegsels[^\S\r\n]*([^\r\n]*)/u',
			'first_name' => '/Given names\s+Voorna\(a\)m\(en\)\s+([^\r\n]+)/u',
			'birthdate'  => '/Date of birth\s+Geboortedatum\s+([^\r\n]+)/u',
			'purpose'    => '/Hierbij geef ik u de VOG die u nodig heeft voor:\s*([^\r\n]+)/u',
			'codes'      => '/Er is bij deze screening uitgegaan van het volgende profiel:\s*([\d,; \t]+)\s*\r?\n/u',
		];
		$data     = [];
		foreach ( $patterns as $key => $pattern ) {
			if ( preg_match_all( $pattern, $text, $matches ) !== 1 ) {
				return [];
			}
			$data[ $key ] = trim( $matches[1][0] );
		}
		$data['date']      = self::dutch_date( $data['date'] );
		$data['birthdate'] = self::dutch_date( $data['birthdate'] );
		$data['codes']     = array_values( array_unique( preg_split( '/[,;\s]+/', $data['codes'], -1, PREG_SPLIT_NO_EMPTY ) ) );
		return $data['date'] && $data['birthdate'] && $data['codes'] ? $data : [];
	}

	private static function dutch_date( string $date ): string {
		$months = array_flip( [ 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december' ] );
		if ( ! preg_match( '/^(\d{1,2})\s+(\p{L}+)\s+(\d{4})$/u', mb_strtolower( $date ), $m ) || ! isset( $months[ $m[2] ] ) ) {
			return '';
		}
		$month = $months[ $m[2] ] + 1;
		return checkdate( $month, (int) $m[1], (int) $m[3] ) ? sprintf( '%04d-%02d-%02d', $m[3], $month, $m[1] ) : '';
	}

	public static function normalize( string $value ): string {
		return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', $value ) ) );
	}

	public static function valid_date( string $date ): bool {
		$d = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return $d && $d->format( 'Y-m-d' ) === $date && $date <= wp_date( 'Y-m-d' ) && $date > wp_date( 'Y-m-d', strtotime( '-3 years' ) );
	}

	/** Profile values used to bind a verified name to the same person identity. */
	public static function profile_identity( int $person_id ): array {
		$identity = [];
		foreach ( [ 'first_name', 'infix', 'last_name', 'birthdate' ] as $key ) {
			$value = (string) Fields::get_for_post( $person_id, $key );
			if ( $key === 'birthdate' && preg_match( '/^\d{8}$/', $value ) ) {
				$value = substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 );
			}
			$identity[ $key ] = $value;
		}
		return $identity;
	}

	public static function profile_hash( int $person_id ): string {
		return hash( 'sha256', wp_json_encode( array_map( [ self::class, 'normalize' ], self::profile_identity( $person_id ) ) ) );
	}

	/** Recalculate from current profile and settings, including already queued uploads. */
	public static function assessment( array $data, int $person_id, array $rules ): array {
		$profile   = self::profile_identity( $person_id );
		$verified  = get_post_meta( $person_id, VogSubmissions::IDENTITY, true );
		$use_saved = is_array( $verified ) && ( $verified['profile_hash'] ?? '' ) === self::profile_hash( $person_id );
		$rows      = [];
		foreach ( [
			'first_name' => 'Voornamen',
			'infix'      => 'Tussenvoegsel',
			'last_name'  => 'Achternaam',
			'birthdate'  => 'Geboortedatum',
		] as $key => $label ) {
			$expected = $use_saved && $key !== 'birthdate' ? (string) ( $verified['names'][ $key ] ?? '' ) : $profile[ $key ];
			$rows[]   = [
				'key'      => $key,
				'label'    => $label,
				'profile'  => $profile[ $key ],
				'expected' => $expected,
				'document' => (string) ( $data[ $key ] ?? '' ),
				'matches'  => self::normalize( $expected ) === self::normalize( (string) ( $data[ $key ] ?? '' ) ) && ( $key === 'infix' || $expected !== '' ),
			];
		}
		$matched = false;
		$codes   = false;
		foreach ( $rules as $rule ) {
			if ( self::normalize( (string) ( $data['purpose'] ?? '' ) ) === self::normalize( $rule['function'] . ' bij ' . $rule['organization'] ) ) {
				$matched = true;
				$codes   = $codes || ! array_diff( array_unique( array_merge( [ '84' ], $rule['codes'] ) ), $data['codes'] ?? [] );
			}
		}
		$checks = [
			[
				'key'    => 'document',
				'label'  => 'VOG met volledig leesbare gegevens',
				'passed' => ! empty( $data['first_name'] ) && ! empty( $data['last_name'] ) && ! empty( $data['birthdate'] ) && isset( $data['infix'] ) && ! empty( $data['purpose'] ) && ! empty( $data['date'] ) && ! empty( $data['codes'] ),
			],
			[
				'key'    => 'organization',
				'label'  => 'Organisatie en functie komen overeen met de VOG-instellingen',
				'passed' => $matched,
			],
			[
				'key'    => 'codes',
				'label'  => 'Code 84 en eventuele aanvullende clubcodes aanwezig',
				'passed' => in_array( '84', $data['codes'] ?? [], true ) && ( ! $matched || $codes ),
			],
			[
				'key'    => 'date',
				'label'  => 'Afgiftedatum binnen de clubtermijn',
				'passed' => self::valid_date( (string) ( $data['date'] ?? '' ) ),
			],
		];
		return [
			'identity_rows'    => $rows,
			'identity_matches' => ! in_array( false, array_column( $rows, 'matches' ), true ),
			'verified_at'      => $use_saved ? ( $verified['checked_at'] ?? '' ) : '',
			'content_checks'   => $checks,
			'content_passed'   => ! in_array( false, array_column( $checks, 'passed' ), true ),
			'revision'         => hash( 'sha256', wp_json_encode( [ $data, $profile, $verified, $rules ] ) ),
		];
	}

	/** Explain content differences without changing anyone's person record. */
	public static function reasons( array $data, int $person_id, array $rules ): array {
		$assessment = self::assessment( $data, $person_id, $rules );
		$reasons    = [];
		foreach ( $assessment['identity_rows'] as $row ) {
			if ( ! $row['matches'] ) {
				$reasons[] = $row['label'] . ' komt niet eenduidig overeen met de gecontroleerde identiteit of het ledenprofiel.';
			}
		}
		foreach ( $assessment['content_checks'] as $check ) {
			if ( ! $check['passed'] ) {
				$reasons[] = 'Niet bevestigd: ' . $check['label'] . '.';
			}
		}
		return $reasons;
	}
}
