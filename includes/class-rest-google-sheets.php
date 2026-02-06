<?php
/**
 * Google Sheets REST API Endpoints
 *
 * Handles REST API endpoints for Google Sheets OAuth connection management and export:
 * - Status check
 * - OAuth flow initiation
 * - OAuth callback handling
 * - Disconnect functionality
 * - People list export to Google Sheets
 */

namespace Rondo\REST;

use Rondo\Calendar\GoogleOAuth;
use Rondo\Sheets\GoogleSheetsConnection;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GoogleSheets extends Base {

	/**
	 * Constructor
	 *
	 * Register routes for Google Sheets endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register custom REST routes for Google Sheets domain
	 */
	public function register_routes() {
		// GET /stadion/v1/google-sheets/status - Check connection status
		register_rest_route(
			'stadion/v1',
			'/google-sheets/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_status' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// GET /stadion/v1/google-sheets/auth - Initiate OAuth flow
		register_rest_route(
			'stadion/v1',
			'/google-sheets/auth',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'auth_init' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// GET /stadion/v1/google-sheets/callback - OAuth callback (public for redirect)
		register_rest_route(
			'stadion/v1',
			'/google-sheets/callback',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'auth_callback' ],
				'permission_callback' => '__return_true', // Public for OAuth redirect
			]
		);

		// DELETE /stadion/v1/google-sheets/disconnect - Disconnect
		register_rest_route(
			'stadion/v1',
			'/google-sheets/disconnect',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'disconnect' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		// POST /stadion/v1/google-sheets/export-people - Export people to Sheets
		register_rest_route(
			'stadion/v1',
			'/google-sheets/export-people',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'export_people' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'columns' => [
						'required' => true,
						'type'     => 'array',
					],
					'filters' => [
						'required' => false,
						'type'     => 'object',
						'default'  => [],
					],
					'title'   => [
						'required' => false,
						'type'     => 'string',
						'default'  => '',
					],
				],
			]
		);

		// POST /stadion/v1/google-sheets/export-fees - Export fee list to Sheets
		register_rest_route(
			'stadion/v1',
			'/google-sheets/export-fees',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'export_fees' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'sort_field' => [
						'required' => false,
						'type'     => 'string',
						'default'  => 'category',
					],
					'sort_order' => [
						'required' => false,
						'type'     => 'string',
						'default'  => 'asc',
						'enum'     => [ 'asc', 'desc' ],
					],
					'forecast'   => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					],
				],
			]
		);
	}

	/**
	 * Get Google Sheets connection status
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response with connection status.
	 */
	public function get_status( $request ) {
		$user_id    = get_current_user_id();
		$connection = GoogleSheetsConnection::get_connection( $user_id );
		$connected  = GoogleSheetsConnection::is_connected( $user_id );

		$response = [
			'connected'         => $connected,
			'google_configured' => GoogleOAuth::is_configured(),
			'email'             => $connection['email'] ?? '',
			'connected_at'      => $connection['connected_at'] ?? null,
			'last_error'        => $connection['last_error'] ?? null,
		];

		return rest_ensure_response( $response );
	}

	/**
	 * Initiate Google Sheets OAuth flow
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with auth URL or error.
	 */
	public function auth_init( $request ) {
		$user_id = get_current_user_id();

		// Check if Google OAuth is configured
		if ( ! GoogleOAuth::is_configured() ) {
			return new \WP_Error(
				'not_configured',
				__( 'Google integration is not configured. Please add GOOGLE_OAUTH_CLIENT_ID and GOOGLE_OAUTH_CLIENT_SECRET to wp-config.php.', 'stadion' ),
				[ 'status' => 400 ]
			);
		}

		// Check if already connected
		if ( GoogleSheetsConnection::is_connected( $user_id ) ) {
			return new \WP_Error(
				'already_connected',
				__( 'Google Sheets is already connected. Please disconnect first to reconnect.', 'stadion' ),
				[ 'status' => 400 ]
			);
		}

		// Generate auth URL
		$auth_url = GoogleOAuth::get_sheets_auth_url( $user_id );

		if ( empty( $auth_url ) ) {
			return new \WP_Error(
				'auth_url_failed',
				__( 'Failed to generate authorization URL.', 'stadion' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response( [ 'auth_url' => $auth_url ] );
	}

	/**
	 * Handle Google OAuth callback
	 *
	 * Exchanges authorization code for tokens and creates the connection.
	 * Redirects to settings page with success or error status.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 */
	public function auth_callback( $request ) {
		// Check for error from Google (user denied access)
		$error = $request->get_param( 'error' );
		if ( $error ) {
			$error_desc = $request->get_param( 'error_description' ) ?? 'Authorization denied';
			$this->html_redirect( home_url( '/settings/connections?error=' . urlencode( $error_desc ) ) );
		}

		// Get and validate state parameter (token stored in transient)
		$token = $request->get_param( 'state' );
		if ( empty( $token ) ) {
			$this->html_redirect( home_url( '/settings/connections?error=' . urlencode( 'Invalid state parameter' ) ) );
		}

		// Retrieve state data from transient
		$state_data = get_transient( 'google_sheets_oauth_' . $token );
		if ( ! $state_data || ! is_array( $state_data ) ) {
			$this->html_redirect( home_url( '/settings/connections?error=' . urlencode( 'Security verification failed or link expired. Please try again.' ) ) );
		}

		// Delete the transient to prevent reuse
		delete_transient( 'google_sheets_oauth_' . $token );

		$user_id = absint( $state_data['user_id'] ?? 0 );

		if ( ! $user_id ) {
			$this->html_redirect( home_url( '/settings/connections?error=' . urlencode( 'Invalid user session' ) ) );
		}

		// Get authorization code
		$code = $request->get_param( 'code' );
		if ( empty( $code ) ) {
			$this->html_redirect( home_url( '/settings/connections?error=' . urlencode( 'No authorization code received' ) ) );
		}

		try {
			// Exchange code for tokens
			$tokens = GoogleOAuth::handle_sheets_callback( $code, $user_id );

			// Check if Sheets scope was granted
			if ( ! GoogleOAuth::has_sheets_scope( $tokens ) ) {
				$this->html_redirect( home_url( '/settings/connections?error=' . urlencode( 'Sheets permission was not granted. Please try again and allow access to Google Sheets.' ) ) );
			}

			// Get user email from token
			$email = $this->get_user_email_from_token( $tokens );

			// Create connection
			$connection = [
				'credentials'  => $tokens,
				'email'        => $email,
				'connected_at' => current_time( 'c' ),
				'last_error'   => null,
			];

			GoogleSheetsConnection::save_connection( $user_id, $connection );

			// Redirect to people page with success (where export button will now appear)
			$this->html_redirect( home_url( '/people?sheets_connected=1' ) );

		} catch ( \Exception $e ) {
			// Log error for debugging
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Google Sheets OAuth error: ' . $e->getMessage() );
			}
			$this->html_redirect( home_url( '/settings/connections?error=' . urlencode( $e->getMessage() ) ) );
		}
	}

	/**
	 * Disconnect Google Sheets
	 *
	 * Removes the connection from user meta but does NOT revoke the token
	 * at Google (user might still have Calendar/Contacts connected with same account).
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response with success message.
	 */
	public function disconnect( $request ) {
		$user_id = get_current_user_id();

		// Check if connected
		if ( ! GoogleSheetsConnection::is_connected( $user_id ) ) {
			return new \WP_Error(
				'not_connected',
				__( 'Google Sheets is not connected.', 'stadion' ),
				[ 'status' => 400 ]
			);
		}

		// Delete connection
		GoogleSheetsConnection::delete_connection( $user_id );

		return rest_ensure_response(
			[
				'success' => true,
				'message' => __( 'Google Sheets disconnected.', 'stadion' ),
			]
		);
	}

	/**
	 * Export people list to Google Sheets
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with spreadsheet URL or error.
	 */
	public function export_people( $request ) {
		$user_id = get_current_user_id();

		// Check if connected
		if ( ! GoogleSheetsConnection::is_connected( $user_id ) ) {
			return new \WP_Error(
				'not_connected',
				__( 'Google Sheets is not connected. Please connect first.', 'stadion' ),
				[ 'status' => 400 ]
			);
		}

		$columns = $request->get_param( 'columns' );
		$filters = $request->get_param( 'filters' );
		$title   = $request->get_param( 'title' );

		// Default title if not provided
		if ( empty( $title ) ) {
			$title = 'Leden Export - ' . gmdate( 'Y-m-d H:i' );
		}

		try {
			// Get connection and credentials
			$connection  = GoogleSheetsConnection::get_connection( $user_id );
			$credentials = GoogleSheetsConnection::get_decrypted_credentials( $user_id );

			if ( ! $credentials ) {
				throw new \Exception( 'Failed to decrypt credentials' );
			}

			// Refresh token if needed
			$access_token = GoogleOAuth::get_access_token( array_merge( $connection, [ 'user_id' => $user_id ] ) );
			if ( ! $access_token ) {
				throw new \Exception( 'Failed to get valid access token' );
			}

			// Create Google Sheets client
			$client = new \Google\Client();
			$client->setClientId( GOOGLE_OAUTH_CLIENT_ID );
			$client->setClientSecret( GOOGLE_OAUTH_CLIENT_SECRET );
			$client->setAccessToken( [ 'access_token' => $access_token ] );

			$sheets_service = new \Google\Service\Sheets( $client );

			// Fetch people data using same query logic as frontend
			$people_data = $this->fetch_people_data( $filters );

			// Build spreadsheet data
			$spreadsheet_data = $this->build_spreadsheet_data( $columns, $people_data );

			// Create spreadsheet
			$spreadsheet = new \Google\Service\Sheets\Spreadsheet(
				[
					'properties' => [
						'title' => $title,
					],
					'sheets'     => [
						[
							'properties' => [
								'title' => 'Leden',
							],
						],
					],
				]
			);

			$created_spreadsheet = $sheets_service->spreadsheets->create( $spreadsheet );
			$spreadsheet_id      = $created_spreadsheet->getSpreadsheetId();

			// Get the actual sheet ID from the created spreadsheet
			$sheets   = $created_spreadsheet->getSheets();
			$sheet_id = $sheets[0]->getProperties()->getSheetId();

			// Write data to sheet
			$range = 'Leden!A1';
			$body  = new \Google\Service\Sheets\ValueRange(
				[
					'values' => $spreadsheet_data,
				]
			);

			$params = [ 'valueInputOption' => 'RAW' ];
			$sheets_service->spreadsheets_values->update( $spreadsheet_id, $range, $body, $params );

			// Build formatting requests
			$requests = [];

			// 1. Format header row: bold text, light gray background
			$requests[] = [
				'repeatCell' => [
					'range'  => [
						'sheetId'          => $sheet_id,
						'startRowIndex'    => 0,
						'endRowIndex'      => 1,
						'startColumnIndex' => 0,
						'endColumnIndex'   => count( $columns ),
					],
					'cell'   => [
						'userEnteredFormat' => [
							'backgroundColor' => [
								'red'   => 0.9,
								'green' => 0.9,
								'blue'  => 0.9,
							],
							'textFormat'      => [
								'bold' => true,
							],
						],
					],
					'fields' => 'userEnteredFormat(backgroundColor,textFormat)',
				],
			];

			// 2. Freeze header row
			$requests[] = [
				'updateSheetProperties' => [
					'properties' => [
						'sheetId'          => $sheet_id,
						'gridProperties'   => [
							'frozenRowCount' => 1,
						],
					],
					'fields'     => 'gridProperties.frozenRowCount',
				],
			];

			// 3. Auto-resize columns
			for ( $i = 0; $i < count( $columns ); $i++ ) {
				$requests[] = [
					'autoResizeDimensions' => [
						'dimensions' => [
							'sheetId'    => $sheet_id,
							'dimension'  => 'COLUMNS',
							'startIndex' => $i,
							'endIndex'   => $i + 1,
						],
					],
				];
			}

			$batch_update_request = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(
				[
					'requests' => $requests,
				]
			);
			$sheets_service->spreadsheets->batchUpdate( $spreadsheet_id, $batch_update_request );

			$spreadsheet_url = 'https://docs.google.com/spreadsheets/d/' . $spreadsheet_id;

			return rest_ensure_response(
				[
					'success'         => true,
					'spreadsheet_url' => $spreadsheet_url,
					'spreadsheet_id'  => $spreadsheet_id,
					'rows_exported'   => count( $people_data ),
				]
			);

		} catch ( \Exception $e ) {
			// Log error
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Google Sheets export error: ' . $e->getMessage() );
			}

			// Store error in connection
			GoogleSheetsConnection::update_connection(
				$user_id,
				[
					'last_error' => $e->getMessage(),
				]
			);

			return new \WP_Error(
				'export_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Export fee list to Google Sheets
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error Response with spreadsheet URL or error.
	 */
	public function export_fees( $request ) {
		$user_id = get_current_user_id();

		// Check if connected
		if ( ! GoogleSheetsConnection::is_connected( $user_id ) ) {
			return new \WP_Error(
				'not_connected',
				__( 'Google Sheets is not connected. Please connect first.', 'stadion' ),
				[ 'status' => 400 ]
			);
		}

		$sort_field = $request->get_param( 'sort_field' );
		$sort_order = $request->get_param( 'sort_order' );
		$forecast   = $request->get_param( 'forecast' );

		try {
			// Get connection and credentials
			$connection  = GoogleSheetsConnection::get_connection( $user_id );
			$credentials = GoogleSheetsConnection::get_decrypted_credentials( $user_id );

			if ( ! $credentials ) {
				throw new \Exception( 'Failed to decrypt credentials' );
			}

			// Refresh token if needed
			$access_token = GoogleOAuth::get_access_token( array_merge( $connection, [ 'user_id' => $user_id ] ) );
			if ( ! $access_token ) {
				throw new \Exception( 'Failed to get valid access token' );
			}

			// Create Google Sheets client
			$client = new \Google\Client();
			$client->setClientId( GOOGLE_OAUTH_CLIENT_ID );
			$client->setClientSecret( GOOGLE_OAUTH_CLIENT_SECRET );
			$client->setAccessToken( [ 'access_token' => $access_token ] );

			$sheets_service = new \Google\Service\Sheets( $client );

			// Fetch fee data
			$fee_data = $this->fetch_fee_data( $sort_field, $sort_order, $forecast );

			// Build spreadsheet data
			$spreadsheet_data = $this->build_fee_spreadsheet_data( $fee_data, $forecast );

			// Create title: "Contributie {season} - {date}" or "Contributie {season} (Prognose) - {date}"
			$season = $fee_data['season'] ?? gmdate( 'Y' ) . '-' . ( gmdate( 'Y' ) + 1 );
			$title  = 'Contributie ' . $season;
			if ( $forecast ) {
				$title .= ' (Prognose)';
			}
			$title .= ' - ' . gmdate( 'Y-m-d' );

			// Create spreadsheet with nl_NL locale for Euro formatting
			$spreadsheet = new \Google\Service\Sheets\Spreadsheet(
				[
					'properties' => [
						'title'  => $title,
						'locale' => 'nl_NL',
					],
					'sheets'     => [
						[
							'properties' => [
								'title' => 'Contributie',
							],
						],
					],
				]
			);

			$created_spreadsheet = $sheets_service->spreadsheets->create( $spreadsheet );
			$spreadsheet_id      = $created_spreadsheet->getSpreadsheetId();

			// Get the actual sheet ID from the created spreadsheet
			$sheets   = $created_spreadsheet->getSheets();
			$sheet_id = $sheets[0]->getProperties()->getSheetId();

			// Write data to sheet
			$range = 'Contributie!A1';
			$body  = new \Google\Service\Sheets\ValueRange(
				[
					'values' => $spreadsheet_data,
				]
			);

			$params = [ 'valueInputOption' => 'RAW' ];
			$sheets_service->spreadsheets_values->update( $spreadsheet_id, $range, $body, $params );

			// Build formatting requests
			$num_columns = $forecast ? 8 : 10; // 8 columns in forecast mode, 10 in current mode
			$num_rows    = count( $spreadsheet_data );
			$requests    = [];

			// 1. Format header row: bold text, light gray background
			$requests[] = [
				'repeatCell' => [
					'range'  => [
						'sheetId'          => $sheet_id,
						'startRowIndex'    => 0,
						'endRowIndex'      => 1,
						'startColumnIndex' => 0,
						'endColumnIndex'   => $num_columns,
					],
					'cell'   => [
						'userEnteredFormat' => [
							'backgroundColor' => [
								'red'   => 0.9,
								'green' => 0.9,
								'blue'  => 0.9,
							],
							'textFormat'      => [
								'bold' => true,
							],
						],
					],
					'fields' => 'userEnteredFormat(backgroundColor,textFormat)',
				],
			];

			// 2. Freeze header row
			$requests[] = [
				'updateSheetProperties' => [
					'properties' => [
						'sheetId'        => $sheet_id,
						'gridProperties' => [
							'frozenRowCount' => 1,
						],
					],
					'fields'     => 'gridProperties.frozenRowCount',
				],
			];

			// 3. Currency format for Basis (column E, index 4) - whole numbers
			$requests[] = [
				'repeatCell' => [
					'range'  => [
						'sheetId'          => $sheet_id,
						'startRowIndex'    => 1,
						'endRowIndex'      => $num_rows,
						'startColumnIndex' => 4,
						'endColumnIndex'   => 5,
					],
					'cell'   => [
						'userEnteredFormat' => [
							'numberFormat' => [
								'type'    => 'CURRENCY',
								'pattern' => '[$EUR] #,##0',
							],
						],
					],
					'fields' => 'userEnteredFormat.numberFormat',
				],
			];

			// 4. Percentage format for Gezinskorting (column F, index 5)
			$requests[] = [
				'repeatCell' => [
					'range'  => [
						'sheetId'          => $sheet_id,
						'startRowIndex'    => 1,
						'endRowIndex'      => $num_rows,
						'startColumnIndex' => 5,
						'endColumnIndex'   => 6,
					],
					'cell'   => [
						'userEnteredFormat' => [
							'numberFormat' => [
								'type'    => 'PERCENT',
								'pattern' => '0%',
							],
						],
					],
					'fields' => 'userEnteredFormat.numberFormat',
				],
			];

			// 5. Percentage format for Pro-rata % (column G, index 6)
			$requests[] = [
				'repeatCell' => [
					'range'  => [
						'sheetId'          => $sheet_id,
						'startRowIndex'    => 1,
						'endRowIndex'      => $num_rows,
						'startColumnIndex' => 6,
						'endColumnIndex'   => 7,
					],
					'cell'   => [
						'userEnteredFormat' => [
							'numberFormat' => [
								'type'    => 'PERCENT',
								'pattern' => '0%',
							],
						],
					],
					'fields' => 'userEnteredFormat.numberFormat',
				],
			];

			// 6. Currency format for Bedrag (column H, index 7) - whole numbers
			$requests[] = [
				'repeatCell' => [
					'range'  => [
						'sheetId'          => $sheet_id,
						'startRowIndex'    => 1,
						'endRowIndex'      => $num_rows,
						'startColumnIndex' => 7,
						'endColumnIndex'   => 8,
					],
					'cell'   => [
						'userEnteredFormat' => [
							'numberFormat' => [
								'type'    => 'CURRENCY',
								'pattern' => '[$EUR] #,##0',
							],
						],
					],
					'fields' => 'userEnteredFormat.numberFormat',
				],
			];

			// 7. Currency format for Nikki Total (column I, index 8) - 2 decimals (only for current season)
			if ( ! $forecast ) {
				$requests[] = [
					'repeatCell' => [
						'range'  => [
							'sheetId'          => $sheet_id,
							'startRowIndex'    => 1,
							'endRowIndex'      => $num_rows,
							'startColumnIndex' => 8,
							'endColumnIndex'   => 9,
						],
						'cell'   => [
							'userEnteredFormat' => [
								'numberFormat' => [
									'type'    => 'CURRENCY',
									'pattern' => '[$EUR] #,##0.00',
								],
							],
						],
						'fields' => 'userEnteredFormat.numberFormat',
					],
				];

				// 8. Currency format for Saldo (column J, index 9) - 2 decimals (only for current season)
				$requests[] = [
					'repeatCell' => [
						'range'  => [
							'sheetId'          => $sheet_id,
							'startRowIndex'    => 1,
							'endRowIndex'      => $num_rows,
							'startColumnIndex' => 9,
							'endColumnIndex'   => 10,
						],
						'cell'   => [
							'userEnteredFormat' => [
								'numberFormat' => [
									'type'    => 'CURRENCY',
									'pattern' => '[$EUR] #,##0.00',
								],
							],
						],
						'fields' => 'userEnteredFormat.numberFormat',
					],
				];
			}

			// 9. Bold formatting for totals row (last row)
			$requests[] = [
				'repeatCell' => [
					'range'  => [
						'sheetId'          => $sheet_id,
						'startRowIndex'    => $num_rows - 1,
						'endRowIndex'      => $num_rows,
						'startColumnIndex' => 0,
						'endColumnIndex'   => $num_columns,
					],
					'cell'   => [
						'userEnteredFormat' => [
							'textFormat' => [
								'bold' => true,
							],
						],
					],
					'fields' => 'userEnteredFormat.textFormat',
				],
			];

			// 10. Auto-resize columns
			for ( $i = 0; $i < $num_columns; $i++ ) {
				$requests[] = [
					'autoResizeDimensions' => [
						'dimensions' => [
							'sheetId'    => $sheet_id,
							'dimension'  => 'COLUMNS',
							'startIndex' => $i,
							'endIndex'   => $i + 1,
						],
					],
				];
			}

			$batch_update_request = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest(
				[
					'requests' => $requests,
				]
			);
			$sheets_service->spreadsheets->batchUpdate( $spreadsheet_id, $batch_update_request );

			$spreadsheet_url = 'https://docs.google.com/spreadsheets/d/' . $spreadsheet_id;

			return rest_ensure_response(
				[
					'success'         => true,
					'spreadsheet_url' => $spreadsheet_url,
					'spreadsheet_id'  => $spreadsheet_id,
					'rows_exported'   => count( $fee_data['members'] ),
				]
			);

		} catch ( \Exception $e ) {
			// Log error
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Google Sheets fee export error: ' . $e->getMessage() );
			}

			// Store error in connection
			GoogleSheetsConnection::update_connection(
				$user_id,
				[
					'last_error' => $e->getMessage(),
				]
			);

			return new \WP_Error(
				'export_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Fetch fee data with sorting
	 *
	 * @param string $sort_field Sort field (name, category, base_fee, final_fee).
	 * @param string $sort_order Sort order (asc, desc).
	 * @param bool   $forecast   Whether to fetch forecast data for next season.
	 * @return array Fee data with season and members.
	 */
	private function fetch_fee_data( string $sort_field, string $sort_order, bool $forecast = false ): array {
		$fees = new \Stadion\Fees\MembershipFees();

		// Use next season for forecast
		$season = $forecast
			? $fees->get_next_season_key()
			: $fees->get_season_key();

		// Nikki year = first 4 chars of season (2025-2026 => 2025)
		$nikki_year = substr( $season, 0, 4 );

		// Query all person posts
		$query = new \WP_Query(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'orderby'        => 'meta_value',
				'meta_key'       => 'first_name',
				'order'          => 'ASC',
			]
		);

		$results = [];

		foreach ( $query->posts as $person ) {
			// Get person name
			$first_name = get_field( 'first_name', $person->ID ) ?: '';
			$last_name  = get_field( 'last_name', $person->ID ) ?: '';
			$name       = trim( $first_name . ' ' . $last_name );

			// Get relatiecode (KNVB ID)
			$relatiecode = get_field( 'relatiecode', $person->ID ) ?: '';

			if ( $forecast ) {
				// Calculate with 100% pro-rata for forecast
				$fee_data = $fees->calculate_fee_with_family_discount( $person->ID, $season );
				if ( $fee_data === null ) {
					continue;
				}
				$fee_data['prorata_percentage'] = 1.0;
				$fee_data['final_fee']          = $fee_data['fee_after_discount'] ?? $fee_data['final_fee'];
			} else {
				// Use cached calculation for current season
				$fee_data = $fees->get_fee_for_person_cached( $person->ID, $season );
				if ( $fee_data === null ) {
					continue;
				}
			}

			$result = [
				'id'                   => $person->ID,
				'name'                 => $name ?: $person->post_title,
				'relatiecode'          => $relatiecode,
				'category'             => $fee_data['category'],
				'leeftijdsgroep'       => $fee_data['leeftijdsgroep'],
				'base_fee'             => $fee_data['base_fee'],
				'family_discount_rate' => $fee_data['family_discount_rate'],
				'prorata_percentage'   => $fee_data['prorata_percentage'],
				'final_fee'            => $fee_data['final_fee'],
			];

			// Only include Nikki data for current season
			if ( ! $forecast ) {
				// Get Nikki data for this year
				$nikki_total = get_post_meta( $person->ID, '_nikki_' . $nikki_year . '_total', true );
				$nikki_saldo = get_post_meta( $person->ID, '_nikki_' . $nikki_year . '_saldo', true );

				$result['nikki_total'] = $nikki_total !== '' ? (float) $nikki_total : null;
				$result['nikki_saldo'] = $nikki_saldo !== '' ? (float) $nikki_saldo : null;
			}

			$results[] = $result;
		}

		// Sort results based on sort_field and sort_order
		$category_order = [ 'mini' => 1, 'pupil' => 2, 'junior' => 3, 'senior' => 4, 'recreant' => 5, 'donateur' => 6 ];

		usort(
			$results,
			function ( $a, $b ) use ( $sort_field, $sort_order, $category_order ) {
				$cmp = 0;

				switch ( $sort_field ) {
					case 'name':
						$cmp = strcasecmp( $a['name'], $b['name'] );
						break;
					case 'category':
						$cmp = ( $category_order[ $a['category'] ] ?? 99 ) - ( $category_order[ $b['category'] ] ?? 99 );
						break;
					case 'base_fee':
						$cmp = $a['base_fee'] - $b['base_fee'];
						break;
					case 'final_fee':
						$cmp = $a['final_fee'] - $b['final_fee'];
						break;
					default:
						$cmp = ( $category_order[ $a['category'] ] ?? 99 ) - ( $category_order[ $b['category'] ] ?? 99 );
				}

				return $sort_order === 'asc' ? $cmp : -$cmp;
			}
		);

		return [
			'season'   => $season,
			'forecast' => $forecast,
			'members'  => $results,
		];
	}

	/**
	 * Build spreadsheet data from fee data
	 *
	 * Builds 2D array with header row, data rows, and totals row.
	 * Columns: Naam, Relatiecode, Categorie, Leeftijdsgroep, Basis, Gezinskorting, Pro-rata %, Bedrag, Nikki Total, Saldo
	 * For forecast mode: Only first 8 columns (no Nikki Total, no Saldo)
	 *
	 * @param array $fee_data Fee data with season and members.
	 * @param bool  $forecast Whether to build forecast data (excludes Nikki columns).
	 * @return array 2D array for spreadsheet.
	 */
	private function build_fee_spreadsheet_data( array $fee_data, bool $forecast = false ): array {
		$data = [];

		// Header row with Dutch labels (conditional Nikki columns)
		$headers = [
			'Naam',
			'Relatiecode',
			'Categorie',
			'Leeftijdsgroep',
			'Basis',
			'Gezinskorting',
			'Pro-rata %',
			'Bedrag',
		];
		if ( ! $forecast ) {
			$headers[] = 'Nikki Total';
			$headers[] = 'Saldo';
		}
		$data[] = $headers;

		// Category labels
		$category_labels = [
			'mini'     => 'Mini',
			'pupil'    => 'Pupil',
			'junior'   => 'Junior',
			'senior'   => 'Senior',
			'recreant' => 'Recreant',
			'donateur' => 'Donateur',
		];

		// Totals
		$total_base_fee  = 0;
		$total_final_fee = 0;

		// Data rows (conditional Nikki columns)
		foreach ( $fee_data['members'] as $member ) {
			$row = [
				$member['name'],
				$member['relatiecode'] ?: '',
				$category_labels[ $member['category'] ] ?? $member['category'],
				$member['leeftijdsgroep'] ?: '',
				$member['base_fee'],
				$member['family_discount_rate'],
				$member['prorata_percentage'],
				$member['final_fee'],
			];
			if ( ! $forecast ) {
				$row[] = $member['nikki_total'];
				$row[] = $member['nikki_saldo'];
			}
			$data[] = $row;

			$total_base_fee  += $member['base_fee'];
			$total_final_fee += $member['final_fee'];
		}

		// Totals row (per CONTEXT.md: only Base Fee and Bedrag, not Nikki columns)
		$totals_row = [ 'Totaal', '', '', '', $total_base_fee, '', '', $total_final_fee ];
		if ( ! $forecast ) {
			$totals_row[] = '';
			$totals_row[] = '';
		}
		$data[] = $totals_row;

		return $data;
	}

	/**
	 * Fetch people data matching current filters
	 *
	 * Uses internal REST request to reuse all filtering logic from People endpoint.
	 *
	 * @param array $filters Filter parameters from frontend.
	 * @return array People data.
	 */
	private function fetch_people_data( array $filters ): array {
		// Build query params for internal REST request
		$params = array_merge(
			[
				'per_page' => 10000, // Get all matching records
				'page'     => 1,
			],
			$filters
		);

		// Use the People REST class directly to get filtered results
		$people_api = new People();

		// Create a mock REST request with our parameters
		$request = new \WP_REST_Request( 'GET', '/stadion/v1/people' );
		foreach ( $params as $key => $value ) {
			if ( $value !== null && $value !== '' ) {
				$request->set_param( $key, $value );
			}
		}

		// Call the get_filtered_people method directly
		$response = $people_api->get_filtered_people( $request );

		if ( is_wp_error( $response ) ) {
			error_log( 'Google Sheets Export - Error: ' . $response->get_error_message() );
			return [];
		}

		$data   = $response->get_data();
		$people = [];

		// Transform to format expected by build_spreadsheet_data
		foreach ( $data['people'] as $person ) {
			$people[] = [
				'id'         => $person['id'],
				'first_name' => $person['first_name'] ?? '',
				'last_name'  => $person['last_name'] ?? '',
				'acf'        => get_fields( $person['id'] ),
			];
		}

		return $people;
	}

	/**
	 * Build spreadsheet data from columns and people
	 *
	 * @param array $columns Column IDs to include.
	 * @param array $people  People data.
	 * @return array 2D array for spreadsheet (header row + data rows).
	 */
	private function build_spreadsheet_data( array $columns, array $people ): array {
		$data = [];

		// Build header row
		$headers = [];
		foreach ( $columns as $col_id ) {
			$headers[] = $this->get_column_header( $col_id );
		}
		$data[] = $headers;

		// Build data rows
		foreach ( $people as $person ) {
			$row = [];
			foreach ( $columns as $col_id ) {
				$row[] = $this->get_column_value( $col_id, $person );
			}
			$data[] = $row;
		}

		return $data;
	}

	/**
	 * Get column header label
	 *
	 * Uses column metadata from API class for consistent labeling.
	 *
	 * @param string $col_id Column ID.
	 * @return string Header label.
	 */
	private function get_column_header( string $col_id ): string {
		// Core columns with Dutch labels
		$core_headers = [
			'name'       => 'Naam',
			'first_name' => 'Voornaam',
			'last_name'  => 'Achternaam',
			'email'      => 'E-mail',
			'phone'      => 'Telefoon',
			'team'       => 'Team',
			'labels'     => 'Labels',
			'modified'   => 'Laatst gewijzigd',
		];

		// Check core headers first
		if ( isset( $core_headers[ $col_id ] ) ) {
			return $core_headers[ $col_id ];
		}

		// Check custom fields for label
		$manager       = new \Stadion\CustomFields\Manager();
		$custom_fields = $manager->get_fields( 'person', false );

		foreach ( $custom_fields as $field ) {
			if ( $field['name'] === $col_id ) {
				return $field['label'];
			}
		}

		// Fallback: capitalize the column ID
		return ucfirst( str_replace( '_', ' ', $col_id ) );
	}

	/**
	 * Get column value for a person
	 *
	 * @param string $col_id Column ID.
	 * @param array  $person Person data.
	 * @return string Column value.
	 */
	private function get_column_value( string $col_id, array $person ): string {
		switch ( $col_id ) {
			case 'name':
				return trim( ( $person['first_name'] ?? '' ) . ' ' . ( $person['last_name'] ?? '' ) );
			case 'first_name':
				return $person['first_name'] ?? '';
			case 'last_name':
				return $person['last_name'] ?? '';
			case 'email':
				return $this->get_first_contact_by_type( $person, 'email' );
			case 'phone':
				return $this->get_first_phone( $person );
			case 'team':
				return $this->get_current_team_name( $person );
			case 'labels':
				return $this->get_labels( $person );
			case 'modified':
				return $person['acf']['modified'] ?? '';
			default:
				// Custom field
				return $person['acf'][ $col_id ] ?? '';
		}
	}

	/**
	 * Get first contact value by type
	 *
	 * @param array  $person Person data.
	 * @param string $type   Contact type.
	 * @return string Contact value.
	 */
	private function get_first_contact_by_type( array $person, string $type ): string {
		$contact_info = $person['acf']['contact_info'] ?? [];
		foreach ( $contact_info as $contact ) {
			if ( $contact['contact_type'] === $type ) {
				return $contact['contact_value'] ?? '';
			}
		}
		return '';
	}

	/**
	 * Get first phone (includes mobile)
	 *
	 * @param array $person Person data.
	 * @return string Phone number.
	 */
	private function get_first_phone( array $person ): string {
		$contact_info = $person['acf']['contact_info'] ?? [];
		foreach ( $contact_info as $contact ) {
			if ( in_array( $contact['contact_type'], [ 'phone', 'mobile' ], true ) ) {
				return $contact['contact_value'] ?? '';
			}
		}
		return '';
	}

	/**
	 * Get current team name
	 *
	 * @param array $person Person data.
	 * @return string Team name.
	 */
	private function get_current_team_name( array $person ): string {
		$work_history = $person['acf']['work_history'] ?? [];
		foreach ( $work_history as $job ) {
			if ( ! empty( $job['is_current'] ) && ! empty( $job['team'] ) ) {
				$team = get_post( $job['team'] );
				return $team ? $team->post_title : '';
			}
		}
		return '';
	}

	/**
	 * Get labels as comma-separated string
	 *
	 * @param array $person Person data.
	 * @return string Labels.
	 */
	private function get_labels( array $person ): string {
		$labels     = wp_get_post_terms( $person['id'], 'person_label' );
		$label_names = array_map(
			function ( $label ) {
				return $label->name;
			},
			$labels
		);
		return implode( ', ', $label_names );
	}

	/**
	 * Get user email from OAuth tokens
	 *
	 * Tries to extract email from id_token JWT or makes userinfo API call.
	 *
	 * @param array $tokens OAuth tokens including access_token and optionally id_token.
	 * @return string User email or empty string on failure.
	 */
	private function get_user_email_from_token( array $tokens ): string {
		// Try to decode email from id_token first (no API call needed)
		if ( ! empty( $tokens['id_token'] ) ) {
			$parts = explode( '.', $tokens['id_token'] );
			if ( count( $parts ) === 3 ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding JWT payload
				$payload = json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true );
				if ( ! empty( $payload['email'] ) ) {
					return $payload['email'];
				}
			}
		}

		// Fallback: Make userinfo API call
		if ( ! empty( $tokens['access_token'] ) ) {
			$client = new \Google\Client();
			$client->setClientId( GOOGLE_OAUTH_CLIENT_ID );
			$client->setClientSecret( GOOGLE_OAUTH_CLIENT_SECRET );
			$client->setAccessToken( $tokens );

			try {
				$oauth2   = new \Google\Service\Oauth2( $client );
				$userinfo = $oauth2->userinfo->get();
				return $userinfo->getEmail() ?: '';
			} catch ( \Exception $e ) {
				// Log but don't fail - email is nice to have, not critical
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( 'Google Sheets: Failed to get user email: ' . $e->getMessage() );
				}
				return '';
			}
		}

		return '';
	}

	/**
	 * Output an HTML redirect and exit
	 *
	 * Used in OAuth callbacks where wp_redirect() doesn't work because
	 * REST API endpoints process the response object rather than executing
	 * the HTTP redirect headers.
	 *
	 * @param string $url The URL to redirect to.
	 */
	private function html_redirect( $url ) {
		$safe_url = esc_url( $url );

		// Clear any output buffers that REST API may have started
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Send HTTP redirect header - most reliable method
		header( 'Location: ' . $safe_url, true, 302 );

		// Fallback HTML redirect if headers were already sent
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		echo '<!DOCTYPE html><html><head>';
		echo '<script>window.location.replace(' . wp_json_encode( $safe_url ) . ');</script>';
		echo '</head><body>Redirecting...</body></html>';
		exit;
	}
}
