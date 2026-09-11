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

	/** Explain content differences without changing anyone's person record. */
	public static function reasons( array $data, int $person_id, array $rules ): array {
		if ( ! $data ) {
			return [ 'Gegevens niet volledig leesbaar; controleer het originele document.' ];
		}
		$reasons = [];
		foreach ( [
			'first_name' => 'Voornamen',
			'infix'      => 'Tussenvoegsel',
			'last_name'  => 'Achternaam',
			'birthdate'  => 'Geboortedatum',
		] as $key => $label ) {
			$value = (string) Fields::get_for_post( $person_id, $key );
			if ( $key === 'birthdate' && preg_match( '/^\d{8}$/', $value ) ) {
				$value = substr( $value, 0, 4 ) . '-' . substr( $value, 4, 2 ) . '-' . substr( $value, 6, 2 );
			}
			if ( self::normalize( $value ) !== self::normalize( $data[ $key ] ?? '' ) || ( $key !== 'infix' && $value === '' ) ) {
				$reasons[] = $label . ' komt niet eenduidig overeen met het lid.';
			}
		}
		if ( ! self::valid_date( $data['date'] ) ) {
			$reasons[] = 'De afgiftedatum valt buiten de clubtermijn of ligt in de toekomst.';
		}
		$matched = false;
		foreach ( $rules as $rule ) {
			if ( self::normalize( $data['purpose'] ) === self::normalize( $rule['function'] . ' bij ' . $rule['organization'] ) && ! array_diff( $rule['codes'], $data['codes'] ) ) {
				$matched = true;
			}
		}
		if ( ! $matched ) {
			$reasons[] = 'Geen bevestigde clubregel voor deze organisatie, functie en screeningscodes.';
		}
		return $reasons;
	}
}
