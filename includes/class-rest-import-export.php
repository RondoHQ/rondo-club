<?php
/**
 * Import/Export REST API Endpoints
 *
 * Handles export endpoints (vCard, Google CSV) and CardDAV URL endpoints.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_REST_Import_Export extends PRM_REST_Base {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes for import/export and CardDAV
	 */
	public function register_routes() {
		// Export contacts as vCard
		register_rest_route(
			'prm/v1',
			'/export/vcard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_vcard' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Export contacts as Google CSV
		register_rest_route(
			'prm/v1',
			'/export/google-csv',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_google_csv' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);

		// Get CardDAV URLs for the current user
		register_rest_route(
			'prm/v1',
			'/carddav/urls',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_carddav_urls' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
	}

	/**
	 * Export all contacts as vCard
	 */
	public function export_vcard( $request ) {
		$user_id        = get_current_user_id();
		$access_control = new PRM_Access_Control();

		// Get all accessible people
		$people_ids = $access_control->get_accessible_post_ids( 'person', $user_id );

		if ( empty( $people_ids ) ) {
			return new WP_Error( 'no_contacts', __( 'No contacts to export.', 'personal-crm' ), array( 'status' => 404 ) );
		}

		// Get companies for work history
		$company_ids = $access_control->get_accessible_post_ids( 'company', $user_id );
		$company_map = array();
		foreach ( $company_ids as $company_id ) {
			$company = get_post( $company_id );
			if ( $company ) {
				$company_map[ $company_id ] = $company->post_title;
			}
		}

		// Build vCard content
		$vcards = array();
		foreach ( $people_ids as $person_id ) {
			$person = get_post( $person_id );
			if ( ! $person || $person->post_status !== 'publish' ) {
				continue;
			}

			// Get person data via REST API to ensure proper formatting
			$rest_request = new WP_REST_Request( 'GET', "/wp/v2/people/{$person_id}" );
			$rest_request->set_query_params( array( '_embed' => true ) );
			$rest_response = rest_do_request( $rest_request );

			if ( is_wp_error( $rest_response ) || $rest_response->get_status() !== 200 ) {
				continue;
			}

			$person_data = $rest_response->get_data();

			// Get dates for birthday
			$dates_request  = new WP_REST_Request( 'GET', "/prm/v1/people/{$person_id}/dates" );
			$dates_response = rest_do_request( $dates_request );
			$person_dates   = array();
			if ( ! is_wp_error( $dates_response ) && $dates_response->get_status() === 200 ) {
				$person_dates = $dates_response->get_data();
			}

			// Generate vCard
			$vcard = $this->generate_vcard_from_person( $person_data, $company_map, $person_dates );
			if ( $vcard ) {
				$vcards[] = $vcard;
			}
		}

		if ( empty( $vcards ) ) {
			return new WP_Error( 'export_failed', __( 'Failed to generate vCard export.', 'personal-crm' ), array( 'status' => 500 ) );
		}

		$vcard_content = implode( "\n", $vcards );

		// Return as download
		header( 'Content-Type: text/vcard; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="caelis-contacts.vcf"' );
		header( 'Content-Length: ' . strlen( $vcard_content ) );
		echo $vcard_content;
		exit;
	}

	/**
	 * Export all contacts as Google Contacts CSV
	 */
	public function export_google_csv( $request ) {
		$user_id        = get_current_user_id();
		$access_control = new PRM_Access_Control();

		// Get all accessible people
		$people_ids = $access_control->get_accessible_post_ids( 'person', $user_id );

		if ( empty( $people_ids ) ) {
			return new WP_Error( 'no_contacts', __( 'No contacts to export.', 'personal-crm' ), array( 'status' => 404 ) );
		}

		// Google Contacts CSV headers
		$headers = array(
			'Name',
			'Given Name',
			'Additional Name',
			'Family Name',
			'Yomi Name',
			'Given Name Yomi',
			'Additional Name Yomi',
			'Family Name Yomi',
			'Name Prefix',
			'Name Suffix',
			'Initials',
			'Nickname',
			'Short Name',
			'Maiden Name',
			'Birthday',
			'Gender',
			'Location',
			'Billing Information',
			'Directory Server',
			'Mileage',
			'Occupation',
			'Hobby',
			'Sensitivity',
			'Priority',
			'Subject',
			'Notes',
			'Language',
			'Photo',
			'Group Membership',
			'E-mail 1 - Type',
			'E-mail 1 - Value',
			'E-mail 2 - Type',
			'E-mail 2 - Value',
			'E-mail 3 - Type',
			'E-mail 3 - Value',
			'Phone 1 - Type',
			'Phone 1 - Value',
			'Phone 2 - Type',
			'Phone 2 - Value',
			'Phone 3 - Type',
			'Phone 3 - Value',
			'Address 1 - Type',
			'Address 1 - Formatted',
			'Address 1 - Street',
			'Address 1 - City',
			'Address 1 - PO Box',
			'Address 1 - Region',
			'Address 1 - Postal Code',
			'Address 1 - Country',
			'Organization 1 - Type',
			'Organization 1 - Name',
			'Organization 1 - Yomi Name',
			'Organization 1 - Title',
			'Organization 1 - Department',
			'Organization 1 - Symbol',
			'Organization 1 - Location',
			'Organization 1 - Job Description',
		);

		$rows = array();
		foreach ( $people_ids as $person_id ) {
			$person = get_post( $person_id );
			if ( ! $person || $person->post_status !== 'publish' ) {
				continue;
			}

			// Get person data via REST API
			$rest_request = new WP_REST_Request( 'GET', "/wp/v2/people/{$person_id}" );
			$rest_request->set_query_params( array( '_embed' => true ) );
			$rest_response = rest_do_request( $rest_request );

			if ( is_wp_error( $rest_response ) || $rest_response->get_status() !== 200 ) {
				continue;
			}

			$person_data = $rest_response->get_data();
			$acf         = $person_data['acf'] ?? array();

			$row = array_fill( 0, count( $headers ), '' );

			// Name fields
			$first_name = $acf['first_name'] ?? '';
			$last_name  = $acf['last_name'] ?? '';
			$full_name  = trim( "{$first_name} {$last_name}" );

			$row[0]  = $full_name; // Name
			$row[1]  = $first_name; // Given Name
			$row[3]  = $last_name; // Family Name
			$row[11] = $acf['nickname'] ?? ''; // Nickname

			// Birthday
			$dates_request  = new WP_REST_Request( 'GET', "/prm/v1/people/{$person_id}/dates" );
			$dates_response = rest_do_request( $dates_request );
			if ( ! is_wp_error( $dates_response ) && $dates_response->get_status() === 200 ) {
				$dates = $dates_response->get_data();
				foreach ( $dates as $date ) {
					if ( isset( $date['date_type'] ) && $date['date_type'] === 'birthday' && isset( $date['date_value'] ) ) {
						$row[14] = date( 'Y-m-d', strtotime( $date['date_value'] ) ); // Birthday
						break;
					}
				}
			}

			// Contact info
			$contact_info = $acf['contact_info'] ?? array();
			$email_count  = 0;
			$phone_count  = 0;

			foreach ( $contact_info as $contact ) {
				$type  = $contact['contact_type'] ?? '';
				$value = $contact['contact_value'] ?? '';
				$label = $contact['contact_label'] ?? '';

				if ( $type === 'email' && $email_count < 3 ) {
					++$email_count;
					$row[ 28 + ( $email_count - 1 ) * 2 ] = $label ?: '* My Contacts'; // Type
					$row[ 29 + ( $email_count - 1 ) * 2 ] = $value; // Value
				} elseif ( ( $type === 'phone' || $type === 'mobile' ) && $phone_count < 3 ) {
					++$phone_count;
					$phone_type                           = ( $type === 'mobile' ) ? 'Mobile' : ( $label ?: 'Work' );
					$row[ 34 + ( $phone_count - 1 ) * 2 ] = $phone_type; // Type
					$row[ 35 + ( $phone_count - 1 ) * 2 ] = $value; // Value
				} elseif ( $type === 'address' ) {
					// Address parsing would be complex, just use formatted value
					$row[40] = $label ?: 'Work'; // Type
					$row[41] = $value; // Formatted
				}
			}

			// Work history
			$work_history = $acf['work_history'] ?? array();
			if ( ! empty( $work_history ) ) {
				$current_job = null;
				foreach ( $work_history as $job ) {
					if ( ! empty( $job['is_current'] ) ) {
						$current_job = $job;
						break;
					}
				}
				if ( ! $current_job && ! empty( $work_history ) ) {
					$current_job = $work_history[0];
				}

				if ( $current_job ) {
					$company_id = $current_job['company'] ?? null;
					if ( $company_id ) {
						$company = get_post( $company_id );
						if ( $company ) {
							$row[48] = 'Work'; // Organization Type
							$row[49] = $company->post_title; // Organization Name
						}
					}
					$row[51] = $current_job['job_title'] ?? ''; // Title
					$row[52] = $current_job['department'] ?? ''; // Department
				}
			}

			// Photo
			if ( isset( $person_data['thumbnail'] ) && ! empty( $person_data['thumbnail'] ) ) {
				$row[27] = $person_data['thumbnail']; // Photo URL
			}

			$rows[] = $row;
		}

		// Generate CSV
		$output = fopen( 'php://output', 'w' );

		// Add BOM for Excel compatibility
		echo "\xEF\xBB\xBF";

		// Write headers
		fputcsv( $output, $headers );

		// Write rows
		foreach ( $rows as $row ) {
			fputcsv( $output, $row );
		}

		fclose( $output );

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="caelis-contacts.csv"' );
		exit;
	}

	/**
	 * Generate vCard from person data
	 *
	 * @param array $person_data Person data from REST API.
	 * @param array $company_map Map of company IDs to names.
	 * @param array $person_dates Array of person's important dates.
	 * @return string vCard content.
	 */
	private function generate_vcard_from_person( $person_data, $company_map = array(), $person_dates = array() ) {
		$acf   = $person_data['acf'] ?? array();
		$lines = array();

		$lines[] = 'BEGIN:VCARD';
		$lines[] = 'VERSION:3.0';

		// Name
		$first_name = $acf['first_name'] ?? '';
		$last_name  = $acf['last_name'] ?? '';
		$full_name  = $person_data['name'] ?? trim( "{$first_name} {$last_name}" ) ?: 'Unknown';

		$lines[] = 'FN:' . $this->escape_vcard_value( $full_name );
		$lines[] = 'N:' . $this->escape_vcard_value( $last_name ) . ';' . $this->escape_vcard_value( $first_name ) . ';;;';

		if ( ! empty( $acf['nickname'] ) ) {
			$lines[] = 'NICKNAME:' . $this->escape_vcard_value( $acf['nickname'] );
		}

		// Contact info
		$contact_info = $acf['contact_info'] ?? array();
		foreach ( $contact_info as $contact ) {
			$type  = $contact['contact_type'] ?? '';
			$value = $contact['contact_value'] ?? '';
			$label = $contact['contact_label'] ?? '';

			if ( empty( $value ) ) {
				continue;
			}

			$escaped_value = $this->escape_vcard_value( $value );

			switch ( $type ) {
				case 'email':
					$email_type = $label ? "EMAIL;TYPE=INTERNET,{$label}" : 'EMAIL;TYPE=INTERNET';
					$lines[]    = "{$email_type}:{$escaped_value}";
					break;

				case 'phone':
				case 'mobile':
					$phone_type = ( $type === 'mobile' ) ? 'CELL' : 'VOICE';
					$tel_type   = $label ? "TEL;TYPE={$phone_type},{$label}" : "TEL;TYPE={$phone_type}";
					$lines[]    = "{$tel_type}:{$escaped_value}";
					break;

				case 'address':
					$lines[] = "ADR;TYPE=HOME:;;{$escaped_value};;;;";
					break;

				case 'website':
				case 'linkedin':
				case 'twitter':
				case 'instagram':
				case 'facebook':
					$url = $value;
					if ( ! preg_match( '/^https?:\/\//i', $url ) ) {
						$url = 'https://' . $url;
					}
					$lines[] = 'URL;TYPE=WORK:' . $this->escape_vcard_value( $url );
					break;
			}
		}

		// Organization
		$work_history = $acf['work_history'] ?? array();
		if ( ! empty( $work_history ) ) {
			$current_job = null;
			foreach ( $work_history as $job ) {
				if ( ! empty( $job['is_current'] ) ) {
					$current_job = $job;
					break;
				}
			}
			if ( ! $current_job && ! empty( $work_history ) ) {
				$current_job = $work_history[0];
			}

			if ( $current_job ) {
				$company_id = $current_job['company'] ?? null;
				if ( $company_id && isset( $company_map[ $company_id ] ) ) {
					$lines[] = 'ORG:' . $this->escape_vcard_value( $company_map[ $company_id ] );
				}
				if ( ! empty( $current_job['job_title'] ) ) {
					$lines[] = 'TITLE:' . $this->escape_vcard_value( $current_job['job_title'] );
				}
			}
		}

		// Birthday
		foreach ( $person_dates as $date ) {
			if ( isset( $date['date_type'] ) && $date['date_type'] === 'birthday' && isset( $date['date_value'] ) ) {
				$birthday = date( 'Ymd', strtotime( $date['date_value'] ) );
				$lines[]  = "BDAY:{$birthday}";
				break;
			}
		}

		// Photo
		if ( isset( $person_data['thumbnail'] ) && ! empty( $person_data['thumbnail'] ) ) {
			// vCard photo would need to be base64 encoded, skip for now
			// Could be added later if needed
		}

		$lines[] = 'END:VCARD';

		return implode( "\r\n", $lines );
	}

	/**
	 * Escape vCard value
	 *
	 * @param string $value The value to escape.
	 * @return string Escaped value.
	 */
	private function escape_vcard_value( $value ) {
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ',', '\\,', $value );
		$value = str_replace( ';', '\\;', $value );
		$value = str_replace( "\n", '\\n', $value );
		return $value;
	}

	/**
	 * Get CardDAV URLs for the current user
	 */
	public function get_carddav_urls( $request ) {
		$user = wp_get_current_user();

		if ( ! $user || ! $user->ID ) {
			return new WP_Error(
				'not_logged_in',
				__( 'You must be logged in.', 'personal-crm' ),
				array( 'status' => 401 )
			);
		}

		$base_url = home_url( '/carddav/' );

		return rest_ensure_response(
			array(
				'server'      => $base_url,
				'principal'   => $base_url . 'principals/' . $user->user_login . '/',
				'addressbook' => $base_url . 'addressbooks/' . $user->user_login . '/contacts/',
				'username'    => $user->user_login,
			)
		);
	}
}
