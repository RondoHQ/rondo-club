<?php
/**
 * IvaCertificateParser
 *
 * Extracts the personalized fields from an official IVA e-learning
 * certificate PDF ("Verantwoord alcohol verstrekken", VWS / NOC*NSF,
 * e-learning "Voor elkaar"). Those PDFs are TCPDF-generated: the entire
 * design is a background image and only the personalized values (naam,
 * branche, user-id, e-learning, datum) exist as a text layer.
 *
 * Used by the member IVA upload to auto-approve a certificate when the
 * name on it matches the linked person and the behaaldatum is recent.
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

	/**
	 * Certificates older than this are not auto-approved, even though the
	 * formal validity (IvaStatus::VALIDITY_YEARS) is longer — older uploads
	 * go through manual review.
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
	 * @return array{name: string, datum: string, user_id: string}|null
	 *         Extracted fields (datum as Y-m-d), or null when the file is
	 *         not a recognizable IVA certificate.
	 */
	public static function parse( string $path ): ?array {
		if ( ! is_readable( $path ) || ! class_exists( '\Smalot\PdfParser\Parser' ) ) {
			return null;
		}

		try {
			$pdf  = ( new \Smalot\PdfParser\Parser() )->parseFile( $path );
			$text = (string) $pdf->getText();
		} catch ( \Throwable $e ) {
			return null;
		}

		return self::parse_text( $text );
	}

	/**
	 * Parse the extracted text layer of an IVA certificate.
	 *
	 * The text layer contains only the personalized values, but their order
	 * differs between extractors and values can be glued together
	 * ("Voor elkaar210777"), so matching is pattern-based instead of
	 * line-order based.
	 *
	 * @param string $text Raw text extracted from the PDF.
	 * @return array{name: string, datum: string, user_id: string}|null
	 */
	public static function parse_text( string $text ): ?array {
		// Require the e-learning marker so random PDFs never parse as IVA.
		if ( stripos( $text, 'Voor elkaar' ) === false ) {
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
		];
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

		return self::name_matches_person( $parsed['name'] ?? '', $person_id );
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
			if ( strcasecmp( $line, 'Sport' ) === 0 || strcasecmp( $line, 'Horeca' ) === 0 ) {
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
