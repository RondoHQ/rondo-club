<?php
/**
 * vCard Export Class
 *
 * Generates vCard 3.0 format from person data for CardDAV server.
 *
 * @package Rondo
 */

namespace Rondo\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VCard {

	/**
	 * Escape special characters in vCard values
	 *
	 * @param string $value The value to escape
	 * @return string Escaped value
	 */
	public static function escape_value( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ';', '\\;', $value );
		$value = str_replace( ',', '\\,', $value );
		$value = str_replace( "\n", '\\n', $value );

		return $value;
	}

	/**
	 * Format phone number for vCard
	 *
	 * @param string $phone Phone number
	 * @return string Formatted phone number
	 */
	public static function format_phone( $phone ) {
		if ( empty( $phone ) ) {
			return '';
		}
		// Remove all non-digit characters except +
		return preg_replace( '/[^\d+]/', '', $phone );
	}

	/**
	 * Format date for vCard (YYYYMMDD)
	 *
	 * @param string $date Date value
	 * @return string Formatted date
	 */
	public static function format_date( $date ) {
		if ( empty( $date ) ) {
			return '';
		}

		$timestamp = strtotime( $date );
		if ( $timestamp === false ) {
			return '';
		}

		return gmdate( 'Ymd', $timestamp );
	}

	/**
	 * Get current job title and organization from work history
	 *
	 * @param array $work_history Work history array
	 * @return array ['title' => string, 'org' => string]
	 */
	public static function get_current_job( $work_history ) {
		if ( ! is_array( $work_history ) || empty( $work_history ) ) {
			return [
				'title' => '',
				'org'   => '',
			];
		}

		// Find current job
		foreach ( $work_history as $job ) {
			if ( ! empty( $job['is_current'] ) ) {
				$team_name = '';
				if ( ! empty( $job['team'] ) ) {
					$team = get_post( $job['team'] );
					if ( $team ) {
						$team_name = $team->post_title;
					}
				}
				return [
					'title' => $job['job_title'] ?? '',
					'org'   => $team_name,
				];
			}
		}

		// If no current job, get the most recent one
		usort(
			$work_history,
			function ( $a, $b ) {
				$date_a = strtotime( $a['start_date'] ?? '1970-01-01' );
				$date_b = strtotime( $b['start_date'] ?? '1970-01-01' );
				return $date_b - $date_a;
			}
		);

		if ( ! empty( $work_history[0] ) ) {
			$job          = $work_history[0];
			$team_name = '';
			if ( ! empty( $job['team'] ) ) {
				$team = get_post( $job['team'] );
				if ( $team ) {
					$team_name = $team->post_title;
				}
			}
			return [
				'title' => $job['job_title'] ?? '',
				'org'   => $team_name,
			];
		}

		return [
			'title' => '',
			'org'   => '',
		];
	}

	/**
	 * Get birthday from person record
	 *
	 * @param int $person_id Person post ID
	 * @return string|null Birthday date in Y-m-d format or null
	 */
	public static function get_birthday( $person_id ) {
		$birthdate = get_field( 'birthdate', $person_id );
		return ! empty( $birthdate ) ? $birthdate : null;
	}

	/**
	 * Get photo as base64 encoded data for vCard inline embedding
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array|null Array with 'type' and 'data' keys, or null if failed
	 */
	private static function get_photo_base64( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return null;
		}

		// Get mime type
		$mime_type = get_post_mime_type( $attachment_id );

		// Map mime type to vCard photo type
		$type_map = [
			'image/jpeg' => 'JPEG',
			'image/jpg'  => 'JPEG',
			'image/png'  => 'PNG',
			'image/gif'  => 'GIF',
		];

		$photo_type = $type_map[ $mime_type ] ?? null;
		if ( ! $photo_type ) {
			// Unsupported image type, fall back to URL approach
			return null;
		}

		// Read and encode the file
		$file_contents = file_get_contents( $file_path );
		if ( $file_contents === false ) {
			return null;
		}

		// Base64 encode
		$base64_data = base64_encode( $file_contents );

		return [
			'type' => $photo_type,
			'data' => $base64_data,
		];
	}

	/**
	 * Generate vCard 3.0 format from person post
	 *
	 * @param int|WP_Post $person Person post ID or object
	 * @return string vCard content
	 */
	public static function generate( $person ) {
		if ( is_int( $person ) ) {
			$person = get_post( $person );
		}

		if ( ! $person || $person->post_type !== 'person' ) {
			return '';
		}

		$acf   = get_fields( $person->ID ) ?: [];
		$lines = [];

		// BEGIN:VCARD
		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';

		// UID - use post ID with domain
		$site_url = parse_url( home_url(), PHP_URL_HOST );
		$lines[]  = 'UID:' . $person->ID . '@' . $site_url;

		// Name fields
		$first_name = $acf['first_name'] ?? '';
		$infix      = $acf['infix'] ?? '';
		$last_name  = $acf['last_name'] ?? '';
		$full_name  = $person->post_title ?: implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) ) ?: 'Unknown';

		// FN (Full Name) - required
		$lines[] = 'FN:' . self::escape_value( $full_name );

		// N (Name) - Family;Given;Additional;Prefix;Suffix
		$lines[] = 'N:' . self::escape_value( $last_name ) . ';' . self::escape_value( $first_name ) . ';' . self::escape_value( $infix ) . ';;';

		// Nickname
		if ( ! empty( $acf['nickname'] ) ) {
			$lines[] = 'NICKNAME:' . self::escape_value( $acf['nickname'] );
		}

		// Gender (vCard 4.0 style, but widely supported)
		if ( ! empty( $acf['gender'] ) ) {
			$gender_map  = [
				'male'              => 'M',
				'female'            => 'F',
				'other'             => 'O',
				'prefer_not_to_say' => 'N',
			];
			$gender_code = $gender_map[ $acf['gender'] ] ?? '';
			if ( $gender_code ) {
				$lines[] = "GENDER:{$gender_code}";
			}
		}

		// Pronouns (RFC 9554 + Apple X- extension for compatibility)
		if ( ! empty( $acf['pronouns'] ) ) {
			$pronouns = self::escape_value( $acf['pronouns'] );
			$lines[]  = "X-PRONOUNS:{$pronouns}";  // Apple compatibility
			$lines[]  = "PRONOUNS:{$pronouns}";     // RFC 9554 standard
		}

		// Contact information from fixed fields
		foreach ( [ 'email_1', 'email_2' ] as $field ) {
			$value = $acf[ $field ] ?? '';
			if ( ! empty( $value ) ) {
				$lines[] = 'EMAIL;TYPE=INTERNET:' . self::escape_value( $value );
			}
		}

		foreach ( [ 'mobile_1', 'mobile_2' ] as $field ) {
			$value = $acf[ $field ] ?? '';
			if ( ! empty( $value ) ) {
				$formatted_phone = self::format_phone( $value );
				if ( $formatted_phone ) {
					$lines[] = "TEL;TYPE=CELL:{$formatted_phone}";
				}
			}
		}

		foreach ( [ 'telephone_1', 'telephone_2' ] as $field ) {
			$value = $acf[ $field ] ?? '';
			if ( ! empty( $value ) ) {
				$formatted_phone = self::format_phone( $value );
				if ( $formatted_phone ) {
					$lines[] = "TEL;TYPE=VOICE:{$formatted_phone}";
				}
			}
		}

		// Addresses (structured format)
		if ( ! empty( $acf['addresses'] ) && is_array( $acf['addresses'] ) ) {
			foreach ( $acf['addresses'] as $address ) {
				$addr_type = ! empty( $address['address_label'] ) ?
					'ADR;TYPE=' . strtoupper( $address['address_label'] ) :
					'ADR;TYPE=HOME';

				$street      = self::escape_value( $address['street'] ?? '' );
				$city        = self::escape_value( $address['city'] ?? '' );
				$state       = self::escape_value( $address['state'] ?? '' );
				$postal_code = self::escape_value( $address['postal_code'] ?? '' );
				$country     = self::escape_value( $address['country'] ?? '' );

				// Only add if there's at least some address data
				if ( $street || $city || $state || $postal_code || $country ) {
					// ADR: POBox;Extended;Street;City;State;PostalCode;Country
					$lines[] = "{$addr_type}:;;{$street};{$city};{$state};{$postal_code};{$country}";
				}
			}
		}

		// Organization and title from work history
		$job = self::get_current_job( $acf['work_history'] ?? [] );
		if ( $job['org'] ) {
			$lines[] = 'ORG:' . self::escape_value( $job['org'] );
		}
		if ( $job['title'] ) {
			$lines[] = 'TITLE:' . self::escape_value( $job['title'] );
		}

		// Birthday
		$birthday = self::get_birthday( $person->ID );
		if ( $birthday ) {
			$bday = self::format_date( $birthday );
			if ( $bday ) {
				$lines[] = "BDAY:{$bday}";
			}
		}

		// Photo (include inline as base64 per RFC 2426)
		$thumbnail_id = get_post_thumbnail_id( $person->ID );
		if ( $thumbnail_id ) {
			$photo_data = self::get_photo_base64( $thumbnail_id );
			if ( $photo_data ) {
				$lines[] = "PHOTO;ENCODING=b;TYPE={$photo_data['type']}:{$photo_data['data']}";
			}
		}

		// REV (Revision) - last modified date
		$modified = $person->post_modified_gmt;
		if ( $modified ) {
			$rev_date = gmdate( 'Ymd\THis\Z', strtotime( $modified ) );
			$lines[]  = "REV:{$rev_date}";
		}

		// END:VCARD
		$lines[] = 'END:VCARD';

		return implode( "\r\n", $lines );
	}

	/**
	 * Generate vCard from array data (used by CardDAV backend)
	 *
	 * @param array $data Person data array with ACF fields
	 * @return string vCard content
	 */
	public static function generate_from_array( $data ) {
		$lines = [];

		// BEGIN:VCARD
		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';

		// UID
		if ( ! empty( $data['uid'] ) ) {
			$lines[] = 'UID:' . $data['uid'];
		}

		// Name fields
		$first_name = $data['first_name'] ?? '';
		$infix      = $data['infix'] ?? '';
		$last_name  = $data['last_name'] ?? '';
		$full_name  = $data['full_name'] ?? implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) ) ?: 'Unknown';

		// FN (Full Name) - required
		$lines[] = 'FN:' . self::escape_value( $full_name );

		// N (Name) - Family;Given;Additional;Prefix;Suffix
		$lines[] = 'N:' . self::escape_value( $last_name ) . ';' . self::escape_value( $first_name ) . ';' . self::escape_value( $infix ) . ';;';

		// Nickname
		if ( ! empty( $data['nickname'] ) ) {
			$lines[] = 'NICKNAME:' . self::escape_value( $data['nickname'] );
		}

		// Gender
		if ( ! empty( $data['gender'] ) ) {
			$gender_map  = [
				'male'              => 'M',
				'female'            => 'F',
				'other'             => 'O',
				'prefer_not_to_say' => 'N',
			];
			$gender_code = $gender_map[ $data['gender'] ] ?? '';
			if ( $gender_code ) {
				$lines[] = "GENDER:{$gender_code}";
			}
		}

		// Pronouns
		if ( ! empty( $data['pronouns'] ) ) {
			$pronouns = self::escape_value( $data['pronouns'] );
			$lines[]  = "X-PRONOUNS:{$pronouns}";
			$lines[]  = "PRONOUNS:{$pronouns}";
		}

		// Contact info from fixed fields
		foreach ( [ 'email_1', 'email_2' ] as $field ) {
			$value = $data[ $field ] ?? '';
			if ( ! empty( $value ) ) {
				$lines[] = 'EMAIL;TYPE=INTERNET:' . self::escape_value( $value );
			}
		}

		foreach ( [ 'mobile_1', 'mobile_2' ] as $field ) {
			$value = $data[ $field ] ?? '';
			if ( ! empty( $value ) ) {
				$formatted_phone = self::format_phone( $value );
				if ( $formatted_phone ) {
					$lines[] = "TEL;TYPE=CELL:{$formatted_phone}";
				}
			}
		}

		foreach ( [ 'telephone_1', 'telephone_2' ] as $field ) {
			$value = $data[ $field ] ?? '';
			if ( ! empty( $value ) ) {
				$formatted_phone = self::format_phone( $value );
				if ( $formatted_phone ) {
					$lines[] = "TEL;TYPE=VOICE:{$formatted_phone}";
				}
			}
		}

		// Addresses
		if ( ! empty( $data['addresses'] ) && is_array( $data['addresses'] ) ) {
			foreach ( $data['addresses'] as $address ) {
				$addr_type = ! empty( $address['address_label'] ) ?
					'ADR;TYPE=' . strtoupper( $address['address_label'] ) :
					'ADR;TYPE=HOME';

				$street      = self::escape_value( $address['street'] ?? '' );
				$city        = self::escape_value( $address['city'] ?? '' );
				$state       = self::escape_value( $address['state'] ?? '' );
				$postal_code = self::escape_value( $address['postal_code'] ?? '' );
				$country     = self::escape_value( $address['country'] ?? '' );

				if ( $street || $city || $state || $postal_code || $country ) {
					$lines[] = "{$addr_type}:;;{$street};{$city};{$state};{$postal_code};{$country}";
				}
			}
		}

		// Organization
		if ( ! empty( $data['org'] ) ) {
			$lines[] = 'ORG:' . self::escape_value( $data['org'] );
		}

		// Title
		if ( ! empty( $data['title'] ) ) {
			$lines[] = 'TITLE:' . self::escape_value( $data['title'] );
		}

		// Birthday
		if ( ! empty( $data['birthday'] ) ) {
			$bday = self::format_date( $data['birthday'] );
			if ( $bday ) {
				$lines[] = "BDAY:{$bday}";
			}
		}

		// Photo
		if ( ! empty( $data['photo_url'] ) ) {
			$lines[] = 'PHOTO;VALUE=URI:' . $data['photo_url'];
		}

		// REV.
		if ( ! empty( $data['modified'] ) ) {
			$rev_date = gmdate( 'Ymd\THis\Z', strtotime( $data['modified'] ) );
			$lines[]  = "REV:{$rev_date}";
		}

		// END:VCARD
		$lines[] = 'END:VCARD';

		return implode( "\r\n", $lines );
	}

	/**
	 * Parse a vCard string and extract data
	 *
	 * @param string $vcard_data Raw vCard string
	 * @return array Parsed data
	 */
	public static function parse( $vcard_data ) {
		// Use sabre/vobject for parsing if available
		if ( class_exists( 'Sabre\VObject\Reader' ) ) {
			try {
				$vcard = \Sabre\VObject\Reader::read( $vcard_data );
				return self::vobject_to_array( $vcard );
			} catch ( \Exception $e ) {
				// Fall back to manual parsing
			}
		}

		// Manual parsing fallback
		return self::manual_parse( $vcard_data );
	}

	/**
	 * Convert sabre/vobject VCard to array
	 *
	 * @param \Sabre\VObject\Component\VCard $vcard
	 * @return array
	 */
	private static function vobject_to_array( $vcard ) {
		$data = [
			'first_name'   => '',
			'infix'        => '',
			'last_name'    => '',
			'full_name'    => '',
			'nickname'     => '',
			'gender'       => '',
			'pronouns'     => '',
			'email_1'      => '',
			'email_2'      => '',
			'mobile_1'     => '',
			'mobile_2'     => '',
			'telephone_1'  => '',
			'telephone_2'  => '',
			'addresses'    => [],
			'org'          => '',
			'title'        => '',
			'birthday'     => '',
			'photo_url'    => '',
			'uid'          => '',
			'notes'        => [],
		];

		// UID
		if ( isset( $vcard->UID ) ) {
			$data['uid'] = (string) $vcard->UID;
		}

		// Name
		if ( isset( $vcard->N ) ) {
			$n                  = $vcard->N->getParts();
			$data['last_name']  = $n[0] ?? '';
			$data['first_name'] = $n[1] ?? '';
			$data['infix']      = $n[2] ?? '';
		}

		// Full name
		if ( isset( $vcard->FN ) ) {
			$data['full_name'] = (string) $vcard->FN;
		}

		// Nickname
		if ( isset( $vcard->NICKNAME ) ) {
			$data['nickname'] = (string) $vcard->NICKNAME;
		}

		// Email — map to email_1, email_2 (first two only)
		if ( isset( $vcard->EMAIL ) ) {
			$email_index = 1;
			foreach ( $vcard->EMAIL as $email ) {
				if ( $email_index > 2 ) {
					break;
				}
				$data[ "email_{$email_index}" ] = (string) $email;
				$email_index++;
			}
		}

		// Phone — map CELL/MOBILE to mobile_1/mobile_2, others to telephone_1/telephone_2
		if ( isset( $vcard->TEL ) ) {
			$mobile_index    = 1;
			$telephone_index = 1;
			foreach ( $vcard->TEL as $tel ) {
				$is_mobile  = false;
				$type_param = $tel['TYPE'];
				if ( $type_param ) {
					$types = is_array( $type_param ) ? $type_param : ( is_object( $type_param ) ? $type_param->getParts() : [ $type_param ] );
					foreach ( $types as $t ) {
						$t_upper = strtoupper( (string) $t );
						if ( $t_upper === 'CELL' || $t_upper === 'MOBILE' ) {
							$is_mobile = true;
						}
					}
				}

				if ( $is_mobile && $mobile_index <= 2 ) {
					$data[ "mobile_{$mobile_index}" ] = (string) $tel;
					$mobile_index++;
				} elseif ( ! $is_mobile && $telephone_index <= 2 ) {
					$data[ "telephone_{$telephone_index}" ] = (string) $tel;
					$telephone_index++;
				}
			}
		}

		// URL and social profiles are dropped per requirements (no fixed fields for these)

		// Address
		if ( isset( $vcard->ADR ) ) {
			foreach ( $vcard->ADR as $adr ) {
				$parts      = $adr->getParts();
				$label      = '';
				$type_param = $adr['TYPE'];
				if ( $type_param ) {
					$label = strtolower( is_array( $type_param ) ? $type_param[0] : $type_param );
				}

				$data['addresses'][] = [
					'address_label' => $label,
					'street'        => $parts[2] ?? '',
					'city'          => $parts[3] ?? '',
					'state'         => $parts[4] ?? '',
					'postal_code'   => $parts[5] ?? '',
					'country'       => $parts[6] ?? '',
				];
			}
		}

		// Organization
		if ( isset( $vcard->ORG ) ) {
			$data['org'] = (string) $vcard->ORG;
		}

		// Title
		if ( isset( $vcard->TITLE ) ) {
			$data['title'] = (string) $vcard->TITLE;
		}

		// Birthday
		if ( isset( $vcard->BDAY ) ) {
			$bday = (string) $vcard->BDAY;
			// Convert YYYYMMDD to Y-m-d
			if ( strlen( $bday ) === 8 && is_numeric( $bday ) ) {
				$data['birthday'] = substr( $bday, 0, 4 ) . '-' . substr( $bday, 4, 2 ) . '-' . substr( $bday, 6, 2 );
			} else {
				$data['birthday'] = $bday;
			}
		}

		// Photo
		if ( isset( $vcard->PHOTO ) ) {
			$photo       = $vcard->PHOTO;
			$photo_value = (string) $photo;

			// Check if it's a URI
			if ( isset( $photo['VALUE'] ) && strtoupper( $photo['VALUE'] ) === 'URI' ) {
				$data['photo_url'] = $photo_value;
			} else {
				// Base64 encoded photo
				$encoding = $photo['ENCODING'] ?? '';
				if ( strtoupper( $encoding ) === 'B' || strtoupper( $encoding ) === 'BASE64' ||
					preg_match( '/^[a-zA-Z0-9+\/=\s]+$/', $photo_value ) ) {
					// Remove any whitespace from base64 data
					$data['photo_base64'] = preg_replace( '/\s+/', '', $photo_value );
					// Get photo type
					$type = $photo['TYPE'] ?? 'jpeg';
					if ( is_object( $type ) ) {
						$type = (string) $type;
					}
					$data['photo_type'] = strtolower( str_replace( [ 'image/', 'IMAGE/' ], '', $type ) );
				}
			}
		}

		// Notes
		if ( isset( $vcard->NOTE ) ) {
			foreach ( $vcard->NOTE as $note ) {
				$note_content = trim( (string) $note );
				if ( ! empty( $note_content ) ) {
					$data['notes'][] = $note_content;
				}
			}
		}

		// Gender
		if ( isset( $vcard->GENDER ) ) {
			$gender_value = (string) $vcard->GENDER;
			// Handle full gender value (e.g., "M;Male" - only use first component)
			if ( strpos( $gender_value, ';' ) !== false ) {
				$gender_value = explode( ';', $gender_value )[0];
			}
			$gender_code    = strtoupper( trim( $gender_value ) );
			$gender_map     = [
				'M' => 'male',
				'F' => 'female',
				'O' => 'other',
				'N' => 'prefer_not_to_say',
			];
			$data['gender'] = $gender_map[ $gender_code ] ?? '';
		}

		// Pronouns (check both RFC 9554 standard and Apple X- extension)
		$pronouns_key = 'X-PRONOUNS';
		if ( isset( $vcard->{$pronouns_key} ) ) {
			$data['pronouns'] = trim( (string) $vcard->{$pronouns_key} );
		}
		if ( empty( $data['pronouns'] ) && isset( $vcard->PRONOUNS ) ) {
			$data['pronouns'] = trim( (string) $vcard->PRONOUNS );
		}

		// X-SOCIALPROFILE — dropped per requirements (no fixed fields for social links)

		return $data;
	}

	/**
	 * Detect social network type from URL
	 */
	private static function detect_social_type( $url ) {
		$url_lower = strtolower( $url );

		if ( strpos( $url_lower, 'linkedin.com' ) !== false ) {
			return 'linkedin';
		}
		if ( strpos( $url_lower, 'twitter.com' ) !== false || strpos( $url_lower, 'x.com' ) !== false ) {
			return 'twitter';
		}
		if ( strpos( $url_lower, 'instagram.com' ) !== false ) {
			return 'instagram';
		}
		if ( strpos( $url_lower, 'facebook.com' ) !== false ) {
			return 'facebook';
		}

		return '';
	}

	/**
	 * Manual vCard parsing fallback
	 *
	 * @param string $vcard_data
	 * @return array
	 */
	private static function manual_parse( $vcard_data ) {
		$data = [
			'first_name'   => '',
			'infix'        => '',
			'last_name'    => '',
			'full_name'    => '',
			'nickname'     => '',
			'gender'       => '',
			'pronouns'     => '',
			'email_1'      => '',
			'email_2'      => '',
			'mobile_1'     => '',
			'mobile_2'     => '',
			'telephone_1'  => '',
			'telephone_2'  => '',
			'addresses'    => [],
			'org'          => '',
			'title'        => '',
			'birthday'     => '',
			'photo_url'    => '',
			'uid'          => '',
			'notes'        => [],
		];

		$email_index     = 1;
		$mobile_index    = 1;
		$telephone_index = 1;

		$lines = preg_split( '/\r\n|\r|\n/', $vcard_data );

		foreach ( $lines as $line ) {
			// Skip empty lines
			if ( empty( trim( $line ) ) ) {
				continue;
			}

			// Parse line
			if ( strpos( $line, ':' ) === false ) {
				continue;
			}

			list($property, $value) = explode( ':', $line, 2 );
			$property_parts         = explode( ';', $property );
			$property_name          = strtoupper( $property_parts[0] );

			switch ( $property_name ) {
				case 'FN':
					$data['full_name'] = self::unescape_value( $value );
					break;

				case 'N':
					$parts              = explode( ';', $value );
					$data['last_name']  = self::unescape_value( $parts[0] ?? '' );
					$data['first_name'] = self::unescape_value( $parts[1] ?? '' );
					$data['infix']      = self::unescape_value( $parts[2] ?? '' );
					break;

				case 'NICKNAME':
					$data['nickname'] = self::unescape_value( $value );
					break;

				case 'EMAIL':
					if ( $email_index <= 2 ) {
						$data[ "email_{$email_index}" ] = self::unescape_value( $value );
						$email_index++;
					}
					break;

				case 'TEL':
					$is_mobile = stripos( $property, 'CELL' ) !== false || stripos( $property, 'MOBILE' ) !== false;
					if ( $is_mobile && $mobile_index <= 2 ) {
						$data[ "mobile_{$mobile_index}" ] = self::unescape_value( $value );
						$mobile_index++;
					} elseif ( ! $is_mobile && $telephone_index <= 2 ) {
						$data[ "telephone_{$telephone_index}" ] = self::unescape_value( $value );
						$telephone_index++;
					}
					break;

				case 'URL':
				case 'X-SOCIALPROFILE':
					// URLs and social profiles dropped per requirements
					break;

				case 'ORG':
					$data['org'] = self::unescape_value( $value );
					break;

				case 'TITLE':
					$data['title'] = self::unescape_value( $value );
					break;

				case 'BDAY':
					$bday = self::unescape_value( $value );
					if ( strlen( $bday ) === 8 && is_numeric( $bday ) ) {
						$data['birthday'] = substr( $bday, 0, 4 ) . '-' . substr( $bday, 4, 2 ) . '-' . substr( $bday, 6, 2 );
					} else {
						$data['birthday'] = $bday;
					}
					break;

				case 'UID':
					$data['uid'] = self::unescape_value( $value );
					break;

				case 'NOTE':
					$note_content = trim( self::unescape_value( $value ) );
					if ( ! empty( $note_content ) ) {
						$data['notes'][] = $note_content;
					}
					break;

				case 'GENDER':
					$gender_value = self::unescape_value( $value );
					if ( strpos( $gender_value, ';' ) !== false ) {
						$gender_value = explode( ';', $gender_value )[0];
					}
					$gender_code    = strtoupper( trim( $gender_value ) );
					$gender_map     = [
						'M' => 'male',
						'F' => 'female',
						'O' => 'other',
						'N' => 'prefer_not_to_say',
					];
					$data['gender'] = $gender_map[ $gender_code ] ?? '';
					break;

				case 'PRONOUNS':
				case 'X-PRONOUNS':
					if ( empty( $data['pronouns'] ) ) {
						$data['pronouns'] = trim( self::unescape_value( $value ) );
					}
					break;

				case 'PHOTO':
					if ( stripos( $property, 'VALUE=URI' ) !== false ) {
						$data['photo_url'] = self::unescape_value( $value );
					} else {
						$photo_type = 'jpeg';
						if ( stripos( $property, 'TYPE=' ) !== false ) {
							preg_match( '/TYPE=([^;:]+)/i', $property, $matches );
							$photo_type = strtolower( $matches[1] ?? 'jpeg' );
						}
						$photo_type = str_replace( [ 'image/', 'IMAGE/' ], '', $photo_type );

						$data['photo_base64'] = preg_replace( '/\s+/', '', $value );
						$data['photo_type']   = $photo_type;
					}
					break;
			}
		}

		return $data;
	}

	/**
	 * Unescape vCard values
	 *
	 * @param string $value
	 * @return string
	 */
	private static function unescape_value( $value ) {
		$value = str_replace( '\\n', "\n", $value );
		$value = str_replace( '\\,', ',', $value );
		$value = str_replace( '\\;', ';', $value );
		$value = str_replace( '\\\\', '\\', $value );
		return $value;
	}
}
