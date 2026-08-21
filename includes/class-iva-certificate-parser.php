<?php
/**
 * IvaCertificateParser
 *
 * Extracts the personalized fields from an official IVA e-learning
 * certificate PDF ("Verantwoord alcohol verstrekken"). Both the legacy
 * VWS / NOC*NSF "Voor elkaar" certificate and the newer
 * VrijwilligerswerkNL certificate are TCPDF-generated: the entire design is
 * a background image and only the personalized values exist as a text layer.
 *
 * Used by the member IVA upload to auto-approve an official IVA certificate
 * when the name on it matches the linked person and the behaaldatum is recent.
 * Any parse failure returns null so the upload falls back to the manual
 * bestuurslid-kantine review — parsing is best-effort, never blocking.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IvaCertificateParser {

	private const FORMAT_VOOR_ELKAAR          = 'voor_elkaar';
	private const FORMAT_VRIJWILLIGERSWERK_NL = 'vrijwilligerswerknl';

	/**
	 * Certificates older than this are not auto-approved. They remain valid
	 * indefinitely after a manager has reviewed and approved them manually.
	 */
	const AUTO_APPROVE_MAX_AGE_YEARS = 2;

	/**
	 * Dutch month names as they appear on the certificate (lowercase).
	 *
	 * @var array<string, int>
	 */
	private const MONTHS = [
		'januari'   => 1,
		'februari'  => 2,
		'maart'     => 3,
		'april'     => 4,
		'mei'       => 5,
		'juni'      => 6,
		'juli'      => 7,
		'augustus'  => 8,
		'september' => 9,
		'oktober'   => 10,
		'november'  => 11,
		'december'  => 12,
	];

	/**
	 * Parse an IVA certificate PDF from disk.
	 *
	 * @param string $path Absolute path to the PDF file.
	 * @return array{name: string, datum: string, user_id: string, format: string}|null
	 *         Extracted fields (datum as Y-m-d), or null when the file is
	 *         not a recognizable IVA certificate.
	 */
	public static function parse( string $path ): ?array {
		if ( ! is_readable( $path ) || ! class_exists( '\Smalot\PdfParser\Parser' ) ) {
			return null;
		}

		try {
			$pdf     = ( new \Smalot\PdfParser\Parser() )->parseFile( $path );
			$text    = (string) $pdf->getText();
			$details = $pdf->getDetails();
		} catch ( \Throwable $e ) {
			return null;
		}

		return self::parse_text( $text, is_array( $details ) ? $details : [] );
	}

	/**
	 * Parse the extracted text layer of an IVA certificate.
	 *
	 * The text layer contains only the personalized values, but their order
	 * differs between extractors and values can be glued together
	 * ("Voor elkaar210777"), so matching is pattern-based instead of
	 * line-order based.
	 *
	 * @param string              $text    Raw text extracted from the PDF.
	 * @param array<string,mixed> $details PDF document metadata.
	 * @return array{name: string, datum: string, user_id: string, format: string}|null
	 */
	public static function parse_text( string $text, array $details = [] ): ?array {
		$format = self::detect_format( $text, $details );
		if ( $format === null ) {
			return null;
		}

		$datum = self::extract_dutch_date( $text );
		if ( $datum === null ) {
			return null;
		}

		// User-id: langste losse cijferreeks, met de datumregel verwijderd zodat
		// het jaartal niet matcht. "Voor elkaar210777" kan aan elkaar geplakt
		// zitten, dus geen \b-anker aan de voorkant.
		$months       = implode( '|', array_keys( self::MONTHS ) );
		$without_date = (string) preg_replace( '/\b\d{1,2}\s+(' . $months . ')\s+\d{4}\b/iu', '', $text );
		$user_id      = '';
		if ( preg_match_all( '/(?<!\d)(\d{4,})(?!\d)/', $without_date, $m ) ) {
			foreach ( $m[1] as $candidate ) {
				if ( strlen( $candidate ) > strlen( $user_id ) ) {
					$user_id = $candidate;
				}
			}
		}

		$name = self::extract_name( $text );
		if ( $name === '' ) {
			return null;
		}

		return [
			'name'    => $name,
			'datum'   => $datum,
			'user_id' => $user_id,
			'format'  => $format,
		];
	}

	/**
	 * Identify the supported certificate generations without trusting a
	 * user-supplied filename. Legacy certificates carry their marker in the
	 * text layer. The newer generation identifies itself in TCPDF metadata.
	 *
	 * @param string              $text    Raw text extracted from the PDF.
	 * @param array<string,mixed> $details PDF document metadata.
	 */
	private static function detect_format( string $text, array $details ): ?string {
		if ( stripos( $text, 'Voor elkaar' ) !== false ) {
			return self::FORMAT_VOOR_ELKAAR;
		}

		$title    = (string) ( $details['Title'] ?? $details['dc:title'] ?? '' );
		$producer = (string) ( $details['Producer'] ?? $details['pdf:producer'] ?? '' );
		$title    = self::normalize_document_marker( $title );

		if (
			stripos( $producer, 'TCPDF' ) !== false
			&& str_contains( $title, 'certificaat iva' )
			&& str_contains( $title, 'vrijwilligerswerknl' )
		) {
			return self::FORMAT_VRIJWILLIGERSWERK_NL;
		}

		return null;
	}

	/**
	 * Does the name on the certificate match this person?
	 *
	 * Compares against the post title, first+last name, and nickname+last
	 * name, all diacritics-insensitively, with a small typo tolerance.
	 *
	 * @param string $cert_name Name as printed on the certificate.
	 * @param int    $person_id Person post ID.
	 */
	public static function name_matches_person( string $cert_name, int $person_id ): bool {
		$cert = self::normalize_name( $cert_name );
		if ( $cert === '' ) {
			return false;
		}

		$first    = (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' );
		$last     = (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'last_name' );
		$nickname = (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'nickname' );

		$candidates = [
			get_the_title( $person_id ),
			trim( $first . ' ' . $last ),
			trim( $nickname . ' ' . $last ),
		];

		foreach ( $candidates as $candidate ) {
			$candidate = self::normalize_name( $candidate );
			if ( $candidate === '' ) {
				continue;
			}
			if ( $candidate === $cert ) {
				return true;
			}
			// Small typo tolerance, scaled down for short names.
			$max_distance = min( 2, (int) floor( strlen( $candidate ) / 8 ) );
			if ( $max_distance > 0 && levenshtein( $candidate, $cert ) <= $max_distance ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is a parsed certificate eligible for auto-approval for this person?
	 *
	 * Requires a name match and a behaaldatum within
	 * AUTO_APPROVE_MAX_AGE_YEARS, not in the future.
	 *
	 * @param array{name: string, datum: string} $parsed    Result of parse().
	 * @param int                                $person_id Person post ID.
	 */
	public static function should_auto_approve( array $parsed, int $person_id ): bool {
		if ( ! self::is_datum_auto_approvable( $parsed['datum'] ?? '' ) ) {
			return false;
		}

		if ( self::name_matches_person( $parsed['name'] ?? '', $person_id ) ) {
			return true;
		}

		return ( $parsed['format'] ?? '' ) === self::FORMAT_VRIJWILLIGERSWERK_NL
			&& self::truncated_name_matches_person( $parsed['name'] ?? '', $person_id );
	}

	/**
	 * The newer VrijwilligerswerkNL PDFs can expose only the final character
	 * of the given name through their embedded font map. Accept that known
	 * extraction defect only when the remaining multi-word surname exactly
	 * matches the linked person's title and the unexplained prefix is at most
	 * two characters. Other formats retain the stricter full-name comparison.
	 */
	private static function truncated_name_matches_person( string $cert_name, int $person_id ): bool {
		$cert     = self::normalize_name( $cert_name );
		$title    = self::normalize_name( get_the_title( $person_id ) );
		$first    = self::normalize_name( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) );
		$nickname = self::normalize_name( (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'nickname' ) );

		if ( $cert === '' || $title === '' ) {
			return false;
		}

		foreach ( array_unique( array_filter( [ $first, $nickname ] ) ) as $given_name ) {
			if ( ! str_starts_with( $title, $given_name . ' ' ) ) {
				continue;
			}

			$surname = trim( substr( $title, strlen( $given_name ) ) );
			if ( substr_count( $surname, ' ' ) < 1 ) {
				continue;
			}

			if ( $cert === $surname ) {
				return true;
			}

			if ( ! str_ends_with( $cert, ' ' . $surname ) ) {
				continue;
			}

			$unknown_prefix = trim( substr( $cert, 0, -strlen( $surname ) ) );
			if ( strlen( str_replace( ' ', '', $unknown_prefix ) ) <= 2 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Is this behaaldatum recent enough for auto-approval (and not future)?
	 *
	 * @param string $datum Y-m-d date.
	 */
	public static function is_datum_auto_approvable( string $datum ): bool {
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ) {
			return false;
		}

		$today  = gmdate( 'Y-m-d' );
		$oldest = gmdate( 'Y-m-d', (int) strtotime( $today . ' -' . self::AUTO_APPROVE_MAX_AGE_YEARS . ' years' ) );

		return $datum >= $oldest && $datum <= $today;
	}

	/**
	 * Find a Dutch long-form date ("31 mei 2026") and return it as Y-m-d.
	 */
	private static function extract_dutch_date( string $text ): ?string {
		$months = implode( '|', array_keys( self::MONTHS ) );
		if ( ! preg_match( '/\b(\d{1,2})\s+(' . $months . ')\s+(\d{4})\b/iu', $text, $m ) ) {
			return null;
		}

		$day   = (int) $m[1];
		$month = self::MONTHS[ strtolower( $m[2] ) ];
		$year  = (int) $m[3];
		if ( ! checkdate( $month, $day, $year ) ) {
			return null;
		}

		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * The name is the only line that is not a known constant, a date, a
	 * number, or extractor boilerplate.
	 */
	private static function extract_name( string $text ): string {
		$months = implode( '|', array_keys( self::MONTHS ) );

		foreach ( preg_split( '/\R/u', $text ) ?: [] as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				continue;
			}
			if ( stripos( $line, 'TCPDF' ) !== false || stripos( $line, 'Voor elkaar' ) !== false ) {
				continue;
			}
			if (
				strcasecmp( $line, 'Sport' ) === 0
				|| strcasecmp( $line, 'Horeca' ) === 0
				|| strcasecmp( $line, 'Niet ingesteld' ) === 0
			) {
				continue;
			}
			if ( preg_match( '/^\d+$/', $line ) ) {
				continue;
			}
			if ( preg_match( '/\b(' . $months . ')\b/iu', $line ) ) {
				continue;
			}
			if ( ! preg_match( '/\p{L}/u', $line ) ) {
				continue;
			}

			return $line;
		}

		return '';
	}

	/**
	 * Normalize a metadata marker into lowercase words.
	 */
	private static function normalize_document_marker( string $value ): string {
		if ( function_exists( 'remove_accents' ) ) {
			$value = remove_accents( $value );
		}

		$value = strtolower( $value );
		$value = (string) preg_replace( '/[^a-z0-9]+/', ' ', $value );

		return trim( (string) preg_replace( '/\s+/', ' ', $value ) );
	}

	/**
	 * Lowercase, strip diacritics, collapse whitespace.
	 */
	public static function normalize_name( string $name ): string {
		$name = trim( (string) preg_replace( '/\s+/u', ' ', $name ) );
		if ( $name === '' ) {
			return '';
		}

		if ( function_exists( 'remove_accents' ) ) {
			$name = remove_accents( $name );
		}

		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $name );
		}

		return strtolower( $name );
	}
}
