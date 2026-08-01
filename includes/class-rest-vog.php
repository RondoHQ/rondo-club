<?php
/**
 * VOG (Verklaring Omtrent het Gedrag) REST API Endpoints
 *
 * Handles VOG settings, bulk email operations, Justis submission tracking,
 * and REST response filters for VOG-related fields on person and discipline case responses.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vog extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'rest_api_init', [ $this, 'register_response_filters' ] );
	}

	/**
	 * Register VOG REST routes
	 */
	public function register_routes() {
		// VOG settings (admin only)
		register_rest_route(
			'rondo/v1',
			'/vog/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_vog_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_vog_settings' ],
					'permission_callback' => [ $this, 'check_admin_permission' ],
					'args'                => [
						'from_email'                => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return empty( $param ) || is_email( $param );
							},
						],
						'from_name'                 => [
							'required' => false,
						],
						'template_new'              => [
							'required' => false,
						],
						'template_renewal'          => [
							'required' => false,
						],
						'reminder_template_new'     => [
							'required' => false,
						],
						'reminder_template_renewal' => [
							'required' => false,
						],
						'exempt_commissies'         => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
						'exempt_discipline_teams'   => [
							'required'          => false,
							'validate_callback' => function ( $param ) {
								return is_array( $param );
							},
						],
					],
				],
			]
		);

		// Bulk send VOG emails
		register_rest_route(
			'rondo/v1',
			'/vog/bulk-send',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_send_vog_emails' ],
				'permission_callback' => [ $this, 'check_vog_permission' ],
				'args'                => [
					'ids' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					],
				],
			]
		);

		// Bulk mark VOG as submitted to Justis
		register_rest_route(
			'rondo/v1',
			'/vog/bulk-mark-justis',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_mark_vog_justis' ],
				'permission_callback' => [ $this, 'check_vog_permission' ],
				'args'                => [
					'ids' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					],
				],
			]
		);

		// Bulk send VOG reminder emails
		register_rest_route(
			'rondo/v1',
			'/vog/bulk-send-reminder',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_send_vog_reminders' ],
				'permission_callback' => [ $this, 'check_vog_permission' ],
				'args'                => [
					'ids' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param ) && ! empty( $param );
						},
					],
				],
			]
		);
	}

	/**
	 * Register REST response filters for VOG-related fields
	 */
	public function register_response_filters() {
		// Add VOG post meta fields to person REST API response
		add_filter( 'rest_prepare_person', [ $this, 'add_vog_fields_to_person' ], 10, 3 );

		// Add computed discipline case charging exception status based on settings.
		add_filter( 'rest_prepare_discipline_case', [ $this, 'add_discipline_case_exception_status' ], 10, 3 );
	}

	/**
	 * Get VOG settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with VOG settings.
	 */
	public function get_vog_settings( $request ) {
		$vog_email = new \Rondo\VOG\VOGEmail();
		return rest_ensure_response( $vog_email->get_all_settings() );
	}

	/**
	 * Update VOG settings
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with updated VOG settings.
	 */
	public function update_vog_settings( $request ) {
		$vog_email = new \Rondo\VOG\VOGEmail();

		$from_email                = $request->get_param( 'from_email' );
		$from_name                 = $request->get_param( 'from_name' );
		$template_new              = $request->get_param( 'template_new' );
		$template_renewal          = $request->get_param( 'template_renewal' );
		$reminder_template_new     = $request->get_param( 'reminder_template_new' );
		$reminder_template_renewal = $request->get_param( 'reminder_template_renewal' );
		$exempt_commissies         = $request->get_param( 'exempt_commissies' );
		$exempt_discipline_teams   = $request->get_param( 'exempt_discipline_teams' );

		// Update provided settings
		if ( $from_email !== null ) {
			$vog_email->update_from_email( $from_email );
		}

		if ( $from_name !== null ) {
			$vog_email->update_from_name( $from_name );
		}

		if ( $template_new !== null ) {
			$vog_email->update_template_new( $template_new );
		}

		if ( $template_renewal !== null ) {
			$vog_email->update_template_renewal( $template_renewal );
		}

		if ( $reminder_template_new !== null ) {
			$vog_email->update_reminder_template_new( $reminder_template_new );
		}

		if ( $reminder_template_renewal !== null ) {
			$vog_email->update_reminder_template_renewal( $reminder_template_renewal );
		}

		// Track if exempt commissies changed for recalculation
		$people_recalculated = null;
		if ( $exempt_commissies !== null ) {
			$old_exempt = $vog_email->get_exempt_commissies();
			$vog_email->update_exempt_commissies( $exempt_commissies );

			// If exempt commissies changed, trigger volunteer status recalculation
			$new_exempt = $vog_email->get_exempt_commissies();
			if ( $old_exempt !== $new_exempt ) {
				$people_recalculated = $this->trigger_vog_recalculation();
			}
		}

		if ( $exempt_discipline_teams !== null ) {
			$vog_email->update_exempt_discipline_teams( $exempt_discipline_teams );
		}

		// Return updated settings
		$response = $vog_email->get_all_settings();
		if ( $people_recalculated !== null ) {
			$response['people_recalculated'] = $people_recalculated;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Bulk send VOG emails
	 *
	 * Sends VOG emails to selected people. Determines the correct template
	 * (new or renewal) based on the presence of an existing VOG date.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with results.
	 */
	public function bulk_send_vog_emails( $request ) {
		$ids       = $request->get_param( 'ids' );
		$vog_email = new \Rondo\VOG\VOGEmail();

		$results = [];
		$sent    = 0;
		$failed  = 0;

		foreach ( $ids as $person_id ) {
			// Determine template type based on datum-vog
			$datum_vog     = \Rondo\Fields\Fields::get_for_post( $person_id, 'datum_vog' );
			$template_type = empty( $datum_vog ) ? 'new' : 'renewal';

			$result = $vog_email->send( (int) $person_id, $template_type );

			if ( $result === true ) {
				++$sent;
				$results[] = [
					'id'      => $person_id,
					'success' => true,
					'type'    => $template_type,
				];
			} else {
				++$failed;
				$results[] = [
					'id'      => $person_id,
					'success' => false,
					'error'   => is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error',
				];
			}
		}

		return rest_ensure_response(
			[
				'results' => $results,
				'sent'    => $sent,
				'failed'  => $failed,
				'total'   => count( $ids ),
			]
		);
	}

	/**
	 * Bulk mark VOG as submitted to Justis
	 *
	 * Records the current date in the vog_justis_submitted_date post meta.
	 * Used to track when the VOG request was submitted to the Justis system.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with results.
	 */
	public function bulk_mark_vog_justis( $request ) {
		$ids          = $request->get_param( 'ids' );
		$current_date = current_time( 'Y-m-d' );

		$marked  = 0;
		$failed  = 0;
		$results = [];

		foreach ( $ids as $person_id ) {
			$person = get_post( (int) $person_id );

			if ( ! $person || $person->post_type !== 'person' ) {
				++$failed;
				$results[] = [
					'id'      => $person_id,
					'success' => false,
					'error'   => 'Invalid person ID',
				];
				continue;
			}

			// Update post meta for Justis submission date
			update_post_meta( $person_id, 'vog_justis_submitted_date', $current_date );
			++$marked;
			$results[] = [
				'id'      => $person_id,
				'success' => true,
			];
		}

		return rest_ensure_response(
			[
				'results' => $results,
				'marked'  => $marked,
				'failed'  => $failed,
				'total'   => count( $ids ),
			]
		);
	}

	/**
	 * Bulk send VOG reminder emails
	 *
	 * Sends VOG reminder emails to selected people. Determines the correct template
	 * (reminder_new or reminder_renewal) based on the presence of an existing VOG date.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response with results.
	 */
	public function bulk_send_vog_reminders( $request ) {
		$ids       = $request->get_param( 'ids' );
		$vog_email = new \Rondo\VOG\VOGEmail();

		$results = [];
		$sent    = 0;
		$failed  = 0;

		foreach ( $ids as $person_id ) {
			// Determine template type based on datum-vog
			$datum_vog     = \Rondo\Fields\Fields::get_for_post( $person_id, 'datum_vog' );
			$template_type = empty( $datum_vog ) ? 'reminder_new' : 'reminder_renewal';

			$result = $vog_email->send_reminder( (int) $person_id, $template_type );

			if ( $result === true ) {
				++$sent;
				$results[] = [
					'id'      => $person_id,
					'success' => true,
					'type'    => $template_type,
				];
			} else {
				++$failed;
				$results[] = [
					'id'      => $person_id,
					'success' => false,
					'error'   => is_wp_error( $result ) ? $result->get_error_message() : 'Unknown error',
				];
			}
		}

		return rest_ensure_response(
			[
				'results' => $results,
				'sent'    => $sent,
				'failed'  => $failed,
				'total'   => count( $ids ),
			]
		);
	}

	/**
	 * Add VOG-related post meta fields to person REST API response
	 *
	 * These fields are stored as post meta (not canonical fields) and need to be
	 * exposed in the REST API for the VOG status card on the person detail page.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response Modified response with VOG fields.
	 */
	public function add_vog_fields_to_person( $response, $post, $request ) {
		// Bail early if response is an error (e.g., post is trashed)
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! isset( $data['fields'] ) ) {
			$data['fields'] = [];
		}

		// Add VOG email sent date from post meta
		$vog_email_sent = get_post_meta( $post->ID, 'vog_email_sent_date', true );
		if ( $vog_email_sent ) {
			$data['fields']['vog_email_sent_date'] = $vog_email_sent;
		}

		// Add VOG Justis submitted date from post meta
		$vog_justis = get_post_meta( $post->ID, 'vog_justis_submitted_date', true );
		if ( $vog_justis ) {
			$data['fields']['vog_justis_submitted_date'] = $vog_justis;
		}

		// Add VOG reminder sent date from post meta
		$vog_reminder = get_post_meta( $post->ID, 'vog_reminder_sent_date', true );
		if ( $vog_reminder ) {
			$data['fields']['vog_reminder_sent_date'] = $vog_reminder;
		}

		$response->set_data( $data );
		return $response;
	}

	/**
	 * Add computed discipline case charging exception status to REST response.
	 *
	 * Cases belonging to configured exempt teams are exposed with is_charged = 'exception'
	 * so frontend can display "Uitzondering" without mutating stored native field values.
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     The post object.
	 * @param \WP_REST_Request  $request  The request object.
	 * @return \WP_REST_Response Modified response.
	 */
	public function add_discipline_case_exception_status( $response, $post, $request ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$vog_email    = new \Rondo\VOG\VOGEmail();
		$exempt_teams = $vog_email->get_exempt_discipline_teams();
		if ( empty( $exempt_teams ) ) {
			return $response;
		}

		if ( ! $this->is_discipline_case_charging_exception( $post->ID, $exempt_teams ) ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! isset( $data['fields'] ) || ! is_array( $data['fields'] ) ) {
			$data['fields'] = [];
		}
		$data['fields']['is_charged'] = 'exception';
		$response->set_data( $data );
		return $response;
	}

	/**
	 * Trigger VOG recalculation for all people.
	 *
	 * Recalculates volunteer status for all published people.
	 * Called when exempt commissies setting changes.
	 *
	 * @return int Number of people recalculated.
	 */
	private function trigger_vog_recalculation(): int {
		$volunteer_status = new \Rondo\Core\VolunteerStatus();

		$people = get_posts(
			[
				'post_type'      => 'person',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			]
		);

		foreach ( $people as $person_id ) {
			$volunteer_status->calculate_and_update_status( $person_id );
		}

		return count( $people );
	}

	/**
	 * Check whether a discipline case matches exempt charging teams.
	 *
	 * @param int   $case_id       Discipline case post ID.
	 * @param array $exempt_teams  Exempt team IDs.
	 * @return bool True when case should be treated as exception.
	 */
	private function is_discipline_case_charging_exception( int $case_id, array $exempt_teams ): bool {
		$team_id = $this->get_discipline_case_team_id( $case_id );
		if ( $team_id && in_array( $team_id, $exempt_teams, true ) ) {
			return true;
		}

		// Fallback: match by team_name text when home/away team IDs are missing.
		$team_name = \Rondo\Fields\Fields::get_for_post( $case_id, 'team_name' );
		if ( ! is_string( $team_name ) || trim( $team_name ) === '' ) {
			return false;
		}
		$team_name = trim( wp_strip_all_tags( $team_name ) );

		foreach ( $exempt_teams as $exempt_team_id ) {
			$title = get_the_title( (int) $exempt_team_id );
			if ( ! is_string( $title ) || $title === '' ) {
				continue;
			}
			if ( strcasecmp( trim( $title ), $team_name ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Resolve team ID for a discipline case.
	 *
	 * @param int $case_id Discipline case post ID.
	 * @return int|null Team post ID or null.
	 */
	private function get_discipline_case_team_id( int $case_id ): ?int {
		$home_team = \Rondo\Fields\Fields::get_for_post( $case_id, 'home_team' );
		$away_team = \Rondo\Fields\Fields::get_for_post( $case_id, 'away_team' );

		$home_id = is_numeric( $home_team ) ? (int) $home_team : 0;
		$away_id = is_numeric( $away_team ) ? (int) $away_team : 0;

		if ( $home_id > 0 ) {
			return $home_id;
		}
		if ( $away_id > 0 ) {
			return $away_id;
		}

		return null;
	}
}
