<?php
/**
 * People REST API Endpoints
 *
 * Handles REST API endpoints related to people domain.
 */

namespace Rondo\REST;

use Rondo\CustomFields\Manager;
use Rondo\Core\SponsorStatus;
use Rondo\Passes\PublicMembershipPassPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class People extends Base {

	/**
	 * Post type for sharing permission checks.
	 */
	protected $sharing_post_type = 'person';

	/**
	 * Constructor
	 *
	 * Register routes and filters for people endpoints.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Expand relationship data in person REST responses
		add_filter( 'rest_prepare_person', [ $this, 'expand_person_relationships' ], 10, 3 );

		// Add computed fields (is_deceased) to person REST responses
		add_filter( 'rest_prepare_person', [ $this, 'add_person_computed_fields' ], 20, 3 );

		// Reject ACF edits on persons marked former_member=true. Sportlink
		// rejects writes for these members' lidsoort ("Oud bondslid" /
		// "Oud verenigingslid"), so anything we accept here just generates
		// reverse-sync work that can never land. Admins (incl. the sync
		// service user) are exempt so the sync itself can still touch
		// former-member records. The only allowed non-admin write is the
		// former_member toggle itself — flip it off first, then edit.
		add_filter( 'rest_pre_insert_person', [ $this, 'block_former_member_edits' ], 10, 2 );
		add_filter( 'rest_pre_insert_person', [ $this, 'enforce_sponsor_manager_scope' ], 15, 2 );
		add_filter( 'rest_pre_insert_person', [ $this, 'validate_sponsor_pass_variant' ], 18, 2 );
		add_filter( 'rest_pre_insert_person', [ $this, 'validate_person_identity' ], 20, 2 );

		// Allow callers to filter person list endpoints by former_member via
		// a ?former_member=0 (or =1) query param. Used by rondo-sync's
		// change detector to skip former members at the database level
		// instead of pulling them all into JS and filtering there.
		add_filter( 'rest_person_query', [ $this, 'filter_by_former_member' ], 20, 2 );
	}

	/**
	 * Register custom REST routes for people domain
	 */
	public function register_routes() {
		// Personal household scope, independent of broader management privileges.
		register_rest_route(
			'rondo/v1',
			'/people/household',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_household' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Dates by person
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/dates',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dates_by_person' ],
				'permission_callback' => [ $this, 'check_person_access' ],
				'args'                => [
					'person_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Upload person photo with proper filename
		register_rest_route(
			'rondo/v1',
			'/people/(?P<person_id>\d+)/photo',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_person_photo' ],
				'permission_callback' => [ $this, 'check_person_edit_permission' ],
				'args'                => [
					'person_id' => [
						'validate_callback' => function ( $param ) {
							return is_numeric( $param );
						},
					],
				],
			]
		);

		// Sharing endpoints
		register_rest_route(
			'rondo/v1',
			'/people/(?P<id>\d+)/shares',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_shares' ],
					'permission_callback' => [ $this, 'check_post_owner' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_share' ],
					'permission_callback' => [ $this, 'check_post_owner' ],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/people/(?P<id>\d+)/shares/(?P<user_id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_share' ],
				'permission_callback' => [ $this, 'check_post_owner' ],
			]
		);

		// Onboarding email send endpoint
		register_rest_route(
			'rondo/v1',
			'/people/onboarding-email',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_onboarding_emails' ],
				'permission_callback' => [ $this, 'check_ledenadministratie_permission' ],
				'args'                => [
					'person_ids' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							if ( ! is_array( $param ) || empty( $param ) ) {
								return false;
							}
							foreach ( $param as $id ) {
								if ( ! is_numeric( $id ) ) {
									return false;
								}
							}
							return true;
						},
						'sanitize_callback' => function ( $param ) {
							return array_map( 'intval', $param );
						},
					],
					'type'       => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ 'lid', 'vrijwilliger' ], true );
						},
					],
				],
			]
		);

		// Bulk update endpoint
		register_rest_route(
			'rondo/v1',
			'/people/bulk-update',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk_update_people' ],
				'permission_callback' => [ $this, 'check_bulk_update_permission' ],
				'args'                => [
					'ids'     => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							if ( ! is_array( $param ) || empty( $param ) ) {
								return false;
							}
							foreach ( $param as $id ) {
								if ( ! is_numeric( $id ) ) {
									return false;
								}
							}
							return true;
						},
						'sanitize_callback' => function ( $param ) {
							return array_map( 'intval', $param );
						},
					],
					'updates' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							if ( ! is_array( $param ) || empty( $param ) ) {
								return false;
							}
							// Must have at least one supported update type
							$has_update = array_key_exists( 'organization_id', $param );
							if ( ! $has_update ) {
								return false;
							}
							// Validate organization_id if provided (can be int or null)
							if ( array_key_exists( 'organization_id', $param ) ) {
								$org_id = $param['organization_id'];
								if ( $org_id !== null ) {
									if ( ! is_numeric( $org_id ) ) {
										return false;
									}
									// Validate organization exists as published team post
									$org = get_post( (int) $org_id );
									if ( ! $org || $org->post_type !== 'team' || $org->post_status !== 'publish' ) {
										return false;
									}
								}
							}
							return true;
						},
					],
				],
			]
		);

		// Filtered people with server-side pagination, filtering, and sorting
		register_rest_route(
			'rondo/v1',
			'/people/filtered',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_filtered_people' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'page'                      => [
						'default'           => 1,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0;
						},
						'sanitize_callback' => 'absint',
					],
					'per_page'                  => [
						'default'           => 100,
						'validate_callback' => function ( $param ) {
							return is_numeric( $param ) && (int) $param > 0 && (int) $param <= 100;
						},
						'sanitize_callback' => 'absint',
					],
					'ownership'                 => [
						'default'           => 'all',
						'validate_callback' => function ( $param ) {
							return in_array( $param, [ 'mine', 'shared', 'all' ], true );
						},
					],
					'modified_days'             => [
						'default'           => null,
						'validate_callback' => function ( $param ) {
							return $param === null || $param === '' || ( is_numeric( $param ) && (int) $param > 0 );
						},
						'sanitize_callback' => function ( $param ) {
							return $param === null || $param === '' ? null : absint( $param );
						},
					],
					'orderby'                   => [
						'default'           => 'first_name',
						'validate_callback' => [ $this, 'validate_orderby_param' ],
					],
					'order'                     => [
						'default'           => 'asc',
						'validate_callback' => function ( $param ) {
							return in_array( strtolower( $param ), [ 'asc', 'desc' ], true );
						},
						'sanitize_callback' => function ( $param ) {
							return strtolower( $param );
						},
					],
					'birth_year_from'           => [
						'description'       => 'Filter by birth year (minimum year, inclusive)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1900 && $value <= 2100;
						},
					],
					'birth_year_to'             => [
						'description'       => 'Filter by birth year (maximum year, inclusive)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1900 && $value <= 2100;
						},
					],
					'birth_month'               => [
						'description'       => 'Filter by birth month (1-12)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1 && $value <= 12;
						},
					],
					// Custom field filters
					'huidig_vrijwilliger'       => [
						'description'       => 'Filter by current volunteer status (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'financiele_blokkade'       => [
						'description'       => 'Filter by financial block status (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'type_lid'                  => [
						'description'       => 'Filter by member type',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'person_type'               => [
						'description'       => 'Filter by Rondo person type (member or contact)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'member', 'contact' ], true );
						},
					],
					'is_sponsor'                => [
						'description'       => 'Filter by active sponsor role (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'is_businessclub_member'    => [
						'description'       => 'Filter by active Businessclub membership (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'foto_missing'              => [
						'description'       => 'Filter for people without photo date (1=missing, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'vog_missing'               => [
						'description'       => 'Filter for people without VOG date (1=missing, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'vog_older_than_years'      => [
						'description'       => 'Filter for VOG older than N years',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1 && $value <= 10;
						},
					],
					'vog_email_status'          => [
						'description'       => 'Filter by VOG email status (sent, not_sent, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'sent', 'not_sent' ], true );
						},
					],
					'vog_type'                  => [
						'description'       => 'Filter by VOG type (nieuw=no VOG, vernieuwing=expired VOG)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'nieuw', 'vernieuwing' ], true );
						},
					],
					'leeftijdsgroep'            => [
						'description'       => 'Filter by age group',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'vog_expiring_within_days'  => [
						'description'       => 'Filter for VOG expiring within N days (valid but expiring soon)',
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'validate_callback' => function ( $value ) {
							return $value >= 1 && $value <= 365;
						},
					],
					'vog_justis_status'         => [
						'description'       => 'Filter by VOG Justis status (submitted, not_submitted, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'submitted', 'not_submitted' ], true );
						},
					],
					'vog_reminder_status'       => [
						'description'       => 'Filter by VOG reminder status (sent, not_sent, empty=all)',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', 'sent', 'not_sent' ], true );
						},
					],
					'include_former'            => [
						'description'       => 'Include former members in results (1=include, empty=exclude)',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'lid_tot_future'            => [
						'description'       => 'Filter for people with lid-tot date in the future (1=future only, empty=all)',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'lid_tot_season'            => [
						'description'       => 'Filter for people with lid-tot date in the current sports season (1 Jul – 30 Jun). 1=only this season, empty=all. Auto-includes former members.',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'lid_sinds_season'          => [
						'description'       => 'Filter for people with lid-sinds date in the current sports season (1 Jul – 30 Jun) — i.e. members who joined this season. 1=only this season, empty=all.',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'wacht_op_overschrijving'   => [
						'description'       => 'Filter for people waiting on KNVB transfer (1=only waiting, empty=all)',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'spelactiviteit_no_team'    => [
						'description'       => 'Filter for people with spelactiviteit but no team (1=filter, empty=all)',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'spelend_lid'               => [
						'description'       => 'Filter by playing-member status: spelactiviteit set and not "-" (1=yes, 0=no, empty=all)',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1', '0' ], true );
						},
					],
					'onboarding_new_members'    => [
						'description'       => 'New members (lid-sinds <= 30 days ago) who have not yet received an onboarding email. 1=filter, empty=all.',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
					'onboarding_new_volunteers' => [
						'description'       => 'New volunteers (vrijwilliger-sinds <= 60 days ago, huidig-vrijwilliger=1) who have not yet received an onboarding email. 1=filter, empty=all.',
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return in_array( $value, [ '', '1' ], true );
						},
					],
				],
			]
		);

		// Filter options endpoint
		register_rest_route(
			'rondo/v1',
			'/people/filter-options',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_filter_options' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);
	}

	/** Return only the linked person and their minor children. */
	public function get_household() {
		$ids = \Rondo\Core\AccessControl::get_visible_person_ids();
		if ( empty( $ids ) ) {
			return rest_ensure_response( [] );
		}

		$posts = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'post__in'         => $ids,
				'posts_per_page'   => count( $ids ),
				'orderby'          => 'post__in',
				'suppress_filters' => true,
			]
		);

		$fields = [
			'first_name',
			'infix',
			'last_name',
			'email_1',
			'mobile_1',
			'telephone_1',
			'addresses',
			'birthdate',
			'leeftijdsgroep',
			'knvb-id',
			'lid-sinds',
			'datum-vog',
		];
		$people = [];
		foreach ( $posts as $post ) {
			$acf = [];
			foreach ( $fields as $field ) {
				$acf[ $field ] = get_field( $field, $post->ID );
			}
			$people[] = [
				'id'  => $post->ID,
				'acf' => $acf,
			];
		}

		return rest_ensure_response( $people );
	}

	/**
	 * Upload person photo with proper filename based on person's name
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response|WP_Error Response with attachment info or error.
	 */
	public function upload_person_photo( $request ) {
		$person_id = (int) $request->get_param( 'person_id' );

		// Verify person exists
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' ) {
			return new \WP_Error( 'person_not_found', __( 'Person not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		// Check for uploaded file
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'rondo' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		// Validate file type
		$allowed_types = [ 'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp' ];
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new \WP_Error( 'invalid_type', __( 'Invalid file type. Please upload an image.', 'rondo' ), [ 'status' => 400 ] );
		}

		// Get person's name for filename
		$first_name = get_field( 'first_name', $person_id ) ?: '';
		$last_name  = get_field( 'last_name', $person_id ) ?: '';
		$name_slug  = sanitize_title( strtolower( trim( $first_name . ' ' . $last_name ) ) );

		// Get file extension
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( $extension === 'jpeg' ) {
			$extension = 'jpg';
		}

		// Generate filename
		$filename = ! empty( $name_slug ) ? $name_slug . '.' . $extension : 'person-' . $person_id . '.' . $extension;

		// Load required files
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Prepare file array with new filename
		$file_array = [
			'name'     => $filename,
			'type'     => $file['type'],
			'tmp_name' => $file['tmp_name'],
			'error'    => $file['error'],
			'size'     => $file['size'],
		];

		// Handle the upload
		$attachment_id = media_handle_sideload( $file_array, $person_id, sprintf( '%s %s', $first_name, $last_name ) );

		if ( is_wp_error( $attachment_id ) ) {
			return new \WP_Error( 'upload_failed', $attachment_id->get_error_message(), [ 'status' => 500 ] );
		}

		// Set as featured image
		set_post_thumbnail( $person_id, $attachment_id );

		return rest_ensure_response(
			[
				'success'       => true,
				'attachment_id' => $attachment_id,
				'filename'      => $filename,
				'thumbnail_url' => get_the_post_thumbnail_url( $person_id, 'thumbnail' ),
				'full_url'      => get_the_post_thumbnail_url( $person_id, 'full' ),
			]
		);
	}

	/**
	 * Expand relationship data with person names and relationship type names
	 *
	 * @param WP_REST_Response $response The REST response object.
	 * @param WP_Post $post The post object.
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Modified response with expanded relationships.
	 */
	public function expand_person_relationships( $response, $post, $request ) {
		// Return early if response is an error (e.g., unauthorized access)
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! isset( $data['acf']['relationships'] ) || ! is_array( $data['acf']['relationships'] ) ) {
			return $response;
		}

		$expanded_relationships = [];

		foreach ( $data['acf']['relationships'] as $rel ) {
			// Get person ID - could be an object, array, or just an ID
			$person_id = null;
			if ( is_object( $rel['related_person'] ) ) {
				$person_id = $rel['related_person']->ID;
			} elseif ( is_array( $rel['related_person'] ) ) {
				$person_id = $rel['related_person']['ID'] ?? null;
			} else {
				$person_id = $rel['related_person'];
			}

			// Get relationship type - could be term object, array, or ID
			$type_id   = null;
			$type_name = '';
			$type_slug = '';

			if ( is_object( $rel['relationship_type'] ) ) {
				$type_id   = $rel['relationship_type']->term_id;
				$type_name = $rel['relationship_type']->name;
				$type_slug = $rel['relationship_type']->slug;
			} elseif ( is_array( $rel['relationship_type'] ) ) {
				$type_id   = $rel['relationship_type']['term_id'] ?? null;
				$type_name = $rel['relationship_type']['name'] ?? '';
				$type_slug = $rel['relationship_type']['slug'] ?? '';
			} else {
				$type_id = $rel['relationship_type'];
				if ( $type_id ) {
					$term = get_term( $type_id, 'relationship_type' );
					if ( $term && ! is_wp_error( $term ) ) {
						$type_name = $term->name;
						$type_slug = $term->slug;
					}
				}
			}

			// Get person name
			$person_name      = '';
			$person_thumbnail = '';
			if ( $person_id ) {
				$person_name      = get_the_title( $person_id );
				$person_thumbnail = get_the_post_thumbnail_url( $person_id, 'thumbnail' );
			}

			$expanded_relationships[] = [
				'related_person'     => $person_id,
				'person_name'        => $person_name,
				'person_thumbnail'   => $person_thumbnail ?: '',
				'relationship_type'  => $type_id,
				'relationship_name'  => $type_name,
				'relationship_slug'  => $type_slug,
				'relationship_label' => $rel['relationship_label'] ?? '',
			];
		}

		$data['acf']['relationships'] = $expanded_relationships;
		$response->set_data( $data );

		return $response;
	}

	/**
	 * Add computed fields to person REST response
	 * This includes is_deceased and birth_year
	 *
	 * @param WP_REST_Response $response The REST response object.
	 * @param WP_Post $post The post object.
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Modified response with computed fields.
	 */
	public function add_person_computed_fields( $response, $post, $request ) {
		// Return early if response is an error (e.g., unauthorized access)
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = $response->get_data();

		// Deceased status field (reserved for future use)
		$data['is_deceased']       = false;
		$is_former_member          = ! empty( $data['acf']['former_member'] );
		$data['is_current_parent'] = $is_former_member && $this->has_current_child_relationship( $post->ID );

		// Get birth year from birthdate field on person
		$data['birth_year'] = null;
		$birthdate          = get_field( 'birthdate', $post->ID );
		if ( $birthdate ) {
			$year = (int) gmdate( 'Y', strtotime( $birthdate ) );
			if ( $year > 0 ) {
				$data['birth_year'] = $year;
			}
		}

		// Expose contributie exclusion flag only to users who may view finance data.
		// Writing it still requires 'financieel' — see the auth_callback in PostTypes.
		if ( \Rondo\Core\UserRoles::can_view_finances() ) {
			$data['exclude_from_contributie'] = (bool) get_post_meta( $post->ID, '_exclude_from_contributie', true );
		}

		// Expose stable public membership pass URL for eligible members.
		$data['membership_pass_url'] = PublicMembershipPassPage::ensure_person_pass_url( $post->ID ) ?: null;

		// Expose provisioning status for admin AccountCard (Plan 205-02).
		// Primary lookup: _rondo_wp_user_id post meta (set by UserProvisioning::provision()).
		$linked_user_id = (int) get_post_meta( $post->ID, '_rondo_wp_user_id', true ) ?: 0;

		// Fallback lookup: find a WP user with rondo_linked_person_id pointing to this person.
		// This covers admins who linked themselves via Settings without going through provisioning,
		// and any case where update_linked_person was called before the bidirectional fix.
		if ( ! $linked_user_id ) {
			$linked_users = get_users(
				[
					'meta_key'   => 'rondo_linked_person_id',
					'meta_value' => $post->ID,
					'number'     => 1,
					'fields'     => 'ids',
				]
			);
			if ( ! empty( $linked_users ) ) {
				$linked_user_id = (int) $linked_users[0];
				// Backfill the post meta so subsequent requests skip this lookup.
				update_post_meta( $post->ID, \Rondo\Users\UserProvisioning::META_USER_ID, $linked_user_id );
			}
		}

		$data['linked_user_id']        = $linked_user_id ?: null;
		$data['welcome_email_sent_at'] = get_post_meta( $post->ID, '_welcome_email_sent_at', true ) ?: null;

		// Expose linked user roles for admin AccountCard.
		if ( $data['linked_user_id'] && current_user_can( 'manage_options' ) ) {
			$user = get_user_by( 'ID', $data['linked_user_id'] );
			if ( $user ) {
				$data['linked_user_roles'] = array_values(
					array_intersect(
						$user->roles,
						[ 'rondo_user', 'rondo_fairplay', 'rondo_vog', 'rondo_financieel', 'rondo_financieel_lezen', 'rondo_toegangscontrole', 'rondo_ledenadministratie', 'rondo_sponsorbeheerder', 'rondo_bestuur', 'administrator' ]
					)
				);
			}
		}

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Whether this person currently has a parent role for a published,
	 * non-former person. This role is independent from their own membership
	 * status: a former member can still be an active parent or guardian.
	 *
	 * @param int $person_id Person post ID.
	 * @return bool
	 */
	private function has_current_child_relationship( int $person_id ): bool {
		$child_term = get_term_by( 'slug', 'child', 'relationship_type' );
		if ( ! $child_term || is_wp_error( $child_term ) ) {
			return false;
		}

		$relationships = get_field( 'relationships', $person_id ) ?: [];
		foreach ( $relationships as $relationship ) {
			$type_values = $relationship['relationship_type'] ?? [];
			$type_values = is_array( $type_values ) ? $type_values : [ $type_values ];
			$is_child    = false;

			foreach ( $type_values as $type_value ) {
				$type_id = 0;
				if ( $type_value instanceof \WP_Term ) {
					$type_id = (int) $type_value->term_id;
				} elseif ( is_array( $type_value ) ) {
					$type_id = (int) ( $type_value['term_id'] ?? 0 );
				} elseif ( is_numeric( $type_value ) ) {
					$type_id = (int) $type_value;
				}

				if ( $type_id === (int) $child_term->term_id ) {
					$is_child = true;
					break;
				}
			}

			if ( ! $is_child ) {
				continue;
			}

			$related    = $relationship['related_person'] ?? 0;
			$related_id = 0;
			if ( $related instanceof \WP_Post ) {
				$related_id = (int) $related->ID;
			} elseif ( is_array( $related ) ) {
				$related_id = (int) ( $related['ID'] ?? 0 );
			} elseif ( is_numeric( $related ) ) {
				$related_id = (int) $related;
			}

			if (
				$related_id > 0 &&
				get_post_status( $related_id ) === 'publish' &&
				! (bool) get_field( 'former_member', $related_id )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Block ACF edits on persons marked former_member=true, except for the
	 * former_member toggle itself. Admins (including the rondo-sync service
	 * user, which authenticates with manage_options) bypass the check so
	 * the sync can still write to former-member records.
	 *
	 * Filter signature: rest_pre_insert_{$post_type}. Runs before WordPress
	 * persists a REST insert/update; returning WP_Error aborts the write.
	 *
	 * @param stdClass $prepared_post Sanitized post data ready for insert.
	 * @param WP_REST_Request $request The originating request.
	 * @return stdClass|WP_Error Original $prepared_post to allow, WP_Error to block.
	 */
	public function block_former_member_edits( $prepared_post, $request ) {
		// Skip on create (no existing ID) — this hook is about edits.
		if ( empty( $prepared_post->ID ) ) {
			return $prepared_post;
		}

		// Admins (incl. the sync service user) are exempt.
		if ( current_user_can( 'manage_options' ) ) {
			return $prepared_post;
		}

		// Existing post's former_member state.
		$is_former = (bool) get_field( 'former_member', $prepared_post->ID );
		if ( ! $is_former ) {
			return $prepared_post;
		}

		$acf = $request->get_param( 'acf' );
		if ( ! is_array( $acf ) || empty( $acf ) ) {
			// Non-ACF write (e.g., title-only edit) on a former member is
			// also blocked — there's nothing legitimate to change here.
			return $this->former_member_readonly_error();
		}

		// Identify which ACF fields the request actually changes vs. current.
		$current_acf  = get_fields( $prepared_post->ID ) ?: [];
		$changed_keys = [];
		foreach ( $acf as $key => $new_value ) {
			$current = $current_acf[ $key ] ?? null;
			// Loose serialized comparison handles arrays, post relations,
			// ACF date-picker formats, etc., without false positives on
			// type coercion.
			if ( maybe_serialize( $new_value ) !== maybe_serialize( $current ) ) {
				$changed_keys[] = $key;
			}
		}

		// Only allowed change: flipping former_member itself.
		$other_changes = array_diff( $changed_keys, [ 'former_member' ] );
		if ( empty( $other_changes ) ) {
			return $prepared_post;
		}

		return $this->former_member_readonly_error( $other_changes );
	}

	/**
	 * Require either a personal first name or a company name.
	 *
	 * ACF's first_name field is optional so company-only contacts can be saved,
	 * but a person record must never become entirely nameless.
	 *
	 * @param stdClass        $prepared_post Sanitized post data ready for insert.
	 * @param WP_REST_Request $request       Originating REST request.
	 * @return stdClass|WP_Error
	 */
	public function validate_person_identity( $prepared_post, $request ) {
		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}

		$acf = $request->get_param( 'acf' );
		if ( ! is_array( $acf ) ) {
			return $prepared_post;
		}

		$post_id      = ! empty( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;
		$first_name   = array_key_exists( 'first_name', $acf ) ? $acf['first_name'] : ( $post_id ? get_field( 'first_name', $post_id ) : '' );
		$company_name = array_key_exists( 'company_name', $acf ) ? $acf['company_name'] : ( $post_id ? get_field( 'company_name', $post_id ) : '' );
		$person_type  = array_key_exists( 'person_type', $acf ) ? $acf['person_type'] : ( $post_id ? get_field( 'person_type', $post_id ) : 'member' );

		if ( trim( (string) $first_name ) === '' && trim( (string) $company_name ) === '' ) {
			return new \WP_Error(
				'rondo_person_name_required',
				__( 'Vul een voornaam of bedrijfsnaam in.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		if ( trim( (string) $first_name ) === '' && $person_type !== 'contact' ) {
			return new \WP_Error(
				'rondo_company_only_contact_required',
				__( 'Alleen contacten en sponsors mogen uitsluitend een bedrijfsnaam hebben.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		return $prepared_post;
	}

	/**
	 * Require an explicit valid pass variant whenever the sponsor role is active.
	 *
	 * @param stdClass        $prepared_post Sanitized post data ready for insert.
	 * @param WP_REST_Request $request       Originating REST request.
	 * @return stdClass|WP_Error
	 */
	public function validate_sponsor_pass_variant( $prepared_post, $request ) {
		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}

		$acf = $request->get_param( 'acf' );
		if ( ! is_array( $acf ) ) {
			return $prepared_post;
		}

		$post_id              = ! empty( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;
		$current_is_sponsor   = $post_id ? SponsorStatus::is_sponsor( $post_id ) : false;
		$requested_is_sponsor = array_key_exists( 'is_sponsor', $acf )
			? SponsorStatus::value_is_true( $acf['is_sponsor'] )
			: $current_is_sponsor;
		$has_variant          = array_key_exists( 'sponsor_pass_variant', $acf );
		$variant              = $has_variant ? sanitize_key( (string) $acf['sponsor_pass_variant'] ) : '';
		$allowed              = [
			PublicMembershipPassPage::SPONSOR_PASS_VARIANT_BUSINESSCLUB,
			PublicMembershipPassPage::SPONSOR_PASS_VARIANT_AWC_SPONSOR,
		];

		if ( $has_variant && $variant !== '' && ! in_array( $variant, $allowed, true ) ) {
			return $this->sponsor_pass_variant_error();
		}

		$current_variant   = $post_id ? PublicMembershipPassPage::get_sponsor_pass_variant( $post_id ) : '';
		$effective_variant = $has_variant ? $variant : $current_variant;
		if ( $requested_is_sponsor && ! in_array( $effective_variant, $allowed, true ) ) {
			return $this->sponsor_pass_variant_error();
		}

		return $prepared_post;
	}

	/**
	 * Build the validation error for a missing or invalid Sponsor pass variant.
	 *
	 * @return WP_Error
	 */
	private function sponsor_pass_variant_error() {
		return new \WP_Error(
			'rondo_sponsor_pass_variant_required',
			__( 'Kies Businessclub AWC of AWC Sponsor als pasvariant.', 'rondo' ),
			[ 'status' => 400 ]
		);
	}

	/**
	 * Keep sponsor-only managers inside their sponsor-role boundary.
	 *
	 * Core REST create permissions are capability-based and do not yet have a
	 * post ID. This pre-insert guard therefore requires new records to be explicit
	 * contact+sponsor records. On a dual-role member, only sponsor-owned fields
	 * may be changed. Full people managers are unaffected.
	 *
	 * @param stdClass        $prepared_post Sanitized post data ready for insert.
	 * @param WP_REST_Request $request       Originating REST request.
	 * @return stdClass|WP_Error
	 */
	public function enforce_sponsor_manager_scope( $prepared_post, $request ) {
		if ( is_wp_error( $prepared_post )
			|| ! \Rondo\Core\AccessControl::can_manage_sponsors()
			|| \Rondo\Core\AccessControl::can_edit_people() ) {
			return $prepared_post;
		}

		$acf     = $request->get_param( 'acf' );
		$acf     = is_array( $acf ) ? $acf : [];
		$post_id = ! empty( $prepared_post->ID ) ? (int) $prepared_post->ID : 0;

		if ( $post_id === 0 ) {
			$requested_type       = sanitize_key( (string) ( $acf['person_type'] ?? '' ) );
			$requested_is_sponsor = SponsorStatus::value_is_true( $acf['is_sponsor'] ?? false );
			if ( $requested_type === 'contact' && $requested_is_sponsor ) {
				return $prepared_post;
			}
		} elseif ( SponsorStatus::is_sponsor( $post_id ) ) {
			$current_type = sanitize_key( (string) get_post_meta( $post_id, 'person_type', true ) );
			if ( array_key_exists( 'is_sponsor', $acf ) && ! SponsorStatus::value_is_true( $acf['is_sponsor'] ) ) {
				return $this->sponsor_manager_scope_error();
			}
			if ( array_key_exists( 'person_type', $acf )
				&& sanitize_key( (string) $acf['person_type'] ) !== $current_type ) {
				return $this->sponsor_manager_scope_error();
			}

			if ( SponsorStatus::is_dual_role( $post_id ) ) {
				$allowed_fields = [ 'company_name', 'is_sponsor', 'sponsor_pass_variant' ];
				$blocked_fields = array_diff( array_keys( $acf ), $allowed_fields );
				if ( ! empty( $blocked_fields ) ) {
					return $this->sponsor_manager_scope_error( $blocked_fields );
				}
			}

			return $prepared_post;
		}

		return $this->sponsor_manager_scope_error();
	}

	/**
	 * Build the sponsor-manager scope error.
	 *
	 * @param array $blocked_fields Fields outside the sponsor-owned allowlist.
	 * @return WP_Error
	 */
	private function sponsor_manager_scope_error( array $blocked_fields = [] ) {
		$data = [ 'status' => 403 ];
		if ( ! empty( $blocked_fields ) ) {
			$data['blocked_fields'] = array_values( $blocked_fields );
		}

		return new \WP_Error(
			'rondo_sponsor_manager_scope',
			__( 'Sponsorbeheerders mogen uitsluitend sponsorvelden beheren.', 'rondo' ),
			$data
		);
	}

	/**
	 * Build the standard WP_Error returned when a non-admin tries to edit
	 * a former member's data.
	 *
	 * @param array $blocked_fields Names of the fields the caller tried to change.
	 * @return WP_Error
	 */
	protected function former_member_readonly_error( array $blocked_fields = [] ) {
		$message = __(
			'Deze persoon is gemarkeerd als oud-lid en kan niet worden bewerkt. Zet eerst "Oud-lid" uit als je de gegevens wilt aanpassen.',
			'rondo'
		);
		$data    = [ 'status' => 403 ];
		if ( ! empty( $blocked_fields ) ) {
			$data['blocked_fields'] = array_values( $blocked_fields );
		}
		return new \WP_Error( 'rondo_former_member_readonly', $message, $data );
	}

	/**
	 * Server-side filter on the ?former_member=0|1 query param for GET
	 * /wp/v2/people. Pushes the predicate into the meta query instead of
	 * making clients fetch everything and filter client-side.
	 *
	 * - `former_member=0` (or `false`, `no`) → only members whose
	 *   former_member meta is NOT '1'. Matches "no value at all" too, so
	 *   newly-created persons that haven't been touched yet show up.
	 * - `former_member=1` (or `true`, `yes`) → only members where
	 *   former_member meta is '1'.
	 * - Any other value (incl. missing param) → no filter applied.
	 *
	 * @param array $args WP_Query args being built for this REST call.
	 * @param WP_REST_Request $request
	 * @return array
	 */
	public function filter_by_former_member( $args, $request ) {
		$param = $request->get_param( 'former_member' );
		if ( $param === null || $param === '' ) {
			return $args;
		}

		$truthy = [ '1', 1, 'true', true, 'yes' ];
		$falsy  = [ '0', 0, 'false', false, 'no' ];

		if ( in_array( $param, $truthy, true ) ) {
			$args['meta_query'] = array_merge(
				$args['meta_query'] ?? [],
				[
					[
						'key'   => 'former_member',
						'value' => '1',
					],
				]
				);
			return $args;
		}

		if ( in_array( $param, $falsy, true ) ) {
			$args['meta_query'] = array_merge(
				$args['meta_query'] ?? [],
				[
					[
						'relation' => 'OR',
						[
							'key'     => 'former_member',
							'compare' => 'NOT EXISTS',
						],
						[
							'key'     => 'former_member',
							'value'   => '1',
							'compare' => '!=',
						],
					],
				]
				);
			return $args;
		}

		return $args;
	}

	/**
	 * Check if current user can bulk update the specified people
	 *
	 * Verifies that the current user owns all posts in the request.
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return bool|WP_Error True if user owns all posts, WP_Error otherwise.
	 */
	public function check_bulk_update_permission( $request ) {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to perform this action.', 'rondo' ),
				[ 'status' => 401 ]
			);
		}

		$ids             = $request->get_param( 'ids' );
		$current_user_id = get_current_user_id();
		$is_admin        = current_user_can( 'manage_options' );

		foreach ( $ids as $post_id ) {
			$post = get_post( $post_id );

			if ( ! $post || $post->post_type !== 'person' ) {
				return new \WP_Error(
					'rest_invalid_id',
					// translators: %d is the person post ID.
				sprintf( __( 'Person with ID %d not found.', 'rondo' ), $post_id ),
					[ 'status' => 404 ]
				);
			}

			// Must be post author or admin
			if ( (int) $post->post_author !== $current_user_id && ! $is_admin ) {
				return new \WP_Error(
					'rest_forbidden',
					// translators: %d is the person post ID.
				sprintf( __( 'You do not have permission to update person with ID %d.', 'rondo' ), $post_id ),
					[ 'status' => 403 ]
				);
			}
		}

		return true;
	}

	/**
	 * Bulk update multiple people
	 *
	 * Updates organization for multiple people at once.
	 *
	 * Supported updates:
	 * - organization_id: Team post ID to set as current employer (null to clear)
	 *
	 * @param WP_REST_Request $request The REST request object.
	 * @return WP_REST_Response Response with success/failure details.
	 */
	public function bulk_update_people( $request ) {
		$ids     = $request->get_param( 'ids' );
		$updates = $request->get_param( 'updates' );

		$updated = [];
		$failed  = [];

		foreach ( $ids as $post_id ) {
			try {
				// Update organization assignment if provided
				if ( array_key_exists( 'organization_id', $updates ) ) {
					$org_id = $updates['organization_id'];

					// Get current work_history
					$work_history = get_field( 'work_history', $post_id ) ?: [];

					if ( $org_id === null ) {
						// Clear current organization: set is_current=false on all entries
						foreach ( $work_history as &$job ) {
							$job['is_current'] = false;
						}
						unset( $job ); // Unset reference to avoid issues
					} else {
						// Check if team already exists in work history
						$found = false;
						foreach ( $work_history as &$job ) {
							$job['is_current'] = ( (int) $job['team'] === (int) $org_id );
							if ( (int) $job['team'] === (int) $org_id ) {
								$found = true;
							}
						}
						unset( $job ); // Unset reference to avoid issues

						// If team not in history, add new entry
						if ( ! $found ) {
							$work_history[] = [
								'team'       => $org_id,
								'job_title'  => '',
								'start_date' => '',
								'end_date'   => '',
								'is_current' => true,
							];
						}
					}

					update_field( 'work_history', $work_history, $post_id );
				}

				$updated[] = $post_id;
			} catch ( Exception $e ) {
				$failed[] = [
					'id'    => $post_id,
					'error' => $e->getMessage(),
				];
			}
		}

		return rest_ensure_response(
			[
				'success' => empty( $failed ),
				'updated' => $updated,
				'failed'  => $failed,
			]
		);
	}

	/**
	 * Send onboarding emails to a batch of people.
	 *
	 * Skips people who already received the email; records a timestamp on
	 * successful sends so they drop out of the onboarding list. People with no
	 * email are reported back so the caller can flag them — they do not fail
	 * the whole batch.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response
	 */
	public function send_onboarding_emails( $request ) {
		$person_ids = $request->get_param( 'person_ids' );
		$type       = $request->get_param( 'type' );

		$sender  = new \Rondo\Notifications\OnboardingEmailSender();
		$results = [];
		$counts  = [
			'sent'         => 0,
			'already_sent' => 0,
			'no_email'     => 0,
			'send_failed'  => 0,
			'not_found'    => 0,
			'invalid_type' => 0,
		];

		foreach ( $person_ids as $person_id ) {
			$result    = $sender->send( (int) $person_id, $type );
			$results[] = $result;
			if ( isset( $counts[ $result['status'] ] ) ) {
				++$counts[ $result['status'] ];
			}
		}

		return rest_ensure_response(
			[
				'success' => $counts['send_failed'] === 0 && $counts['invalid_type'] === 0,
				'type'    => $type,
				'counts'  => $counts,
				'results' => $results,
			]
		);
	}

	/**
	 * Validate orderby parameter - accepts built-in fields or custom_ prefixed fields.
	 *
	 * @param string $param The orderby value to validate.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_orderby_param( $param ) {
		// Check built-in fields first.
		$built_in_fields = [
			'first_name',
			'last_name',
			'modified',
			'birthdate',
			'organization',
			// Sportlink fields (ACF fields, not from Manager)
			'custom_knvb-id',
			'custom_type-lid',
			'custom_leeftijdsgroep',
			'custom_lid-sinds',
			'custom_vrijwilliger-sinds',
			'custom_datum-foto',
			'custom_datum-vog',
			'custom_isparent',
			'custom_huidig-vrijwilliger',
			'custom_financiele-blokkade',
			'custom_freescout-id',
		];
		if ( in_array( $param, $built_in_fields, true ) ) {
			return true;
		}

		// Check for custom field (must start with 'custom_').
		if ( strpos( $param, 'custom_' ) !== 0 ) {
			return false;
		}

		// Extract field name (remove 'custom_' prefix).
		$field_name = substr( $param, 7 );

		// Get all active custom fields for person entity.
		$manager = new Manager();
		$fields  = $manager->get_fields( 'person', false );

		// Find field by name and validate it's sortable.
		foreach ( $fields as $field ) {
			if ( $field['name'] === $field_name ) {
				// Only allow sortable field types.
				$sortable_types = [ 'text', 'textarea', 'number', 'date', 'select', 'email', 'url', 'true_false' ];
				return in_array( $field['type'], $sortable_types, true );
			}
		}

		// Field not found or inactive.
		return false;
	}

	/**
	 * Get filtered and paginated people
	 *
	 * Returns people with server-side filtering, sorting, and pagination.
	 * Uses optimized $wpdb queries with JOINs to fetch data in minimal queries.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response with people array and pagination info.
	 */
	public function get_filtered_people( $request ) {
		global $wpdb;

		// Extract validated parameters
		$page            = (int) $request->get_param( 'page' );
		$per_page        = (int) $request->get_param( 'per_page' );
		$ownership       = $request->get_param( 'ownership' );
		$modified_days   = $request->get_param( 'modified_days' );
		$birth_year_from = $request->get_param( 'birth_year_from' );
		$birth_year_to   = $request->get_param( 'birth_year_to' );
		$birth_month     = $request->get_param( 'birth_month' );
		$orderby         = $request->get_param( 'orderby' );
		$order           = strtoupper( $request->get_param( 'order' ) );

		// Custom field filter parameters
		$huidig_vrijwilliger       = $request->get_param( 'huidig_vrijwilliger' );
		$financiele_blokkade       = $request->get_param( 'financiele_blokkade' );
		$type_lid                  = $request->get_param( 'type_lid' );
		$person_type               = $request->get_param( 'person_type' );
		$is_sponsor                = $request->get_param( 'is_sponsor' );
		$is_businessclub_member    = $request->get_param( 'is_businessclub_member' );
		$foto_missing              = $request->get_param( 'foto_missing' );
		$vog_missing               = $request->get_param( 'vog_missing' );
		$vog_older_than_years      = $request->get_param( 'vog_older_than_years' );
		$vog_expiring_within_days  = $request->get_param( 'vog_expiring_within_days' );
		$vog_email_status          = $request->get_param( 'vog_email_status' );
		$vog_type                  = $request->get_param( 'vog_type' );
		$leeftijdsgroep            = $request->get_param( 'leeftijdsgroep' );
		$vog_justis_status         = $request->get_param( 'vog_justis_status' );
		$vog_reminder_status       = $request->get_param( 'vog_reminder_status' );
		$include_former            = $request->get_param( 'include_former' );
		$lid_tot_future            = $request->get_param( 'lid_tot_future' );
		$lid_tot_season            = $request->get_param( 'lid_tot_season' );
		$lid_sinds_season          = $request->get_param( 'lid_sinds_season' );
		$spelactiviteit_no_team    = $request->get_param( 'spelactiviteit_no_team' );
		$spelend_lid               = $request->get_param( 'spelend_lid' );
		$wacht_op_overschrijving   = $request->get_param( 'wacht_op_overschrijving' );
		$onboarding_new_members    = $request->get_param( 'onboarding_new_members' );
		$onboarding_new_volunteers = $request->get_param( 'onboarding_new_volunteers' );

		// Cancellation-this-season filter implies former members must be visible:
		// once lid-tot has passed, Sportlink flips `former_member` to '1', and the
		// default people query hides those. Auto-flip include_former so the user
		// sees every cancellation in the season window, not just the not-yet-expired ones.
		if ( $lid_tot_season === '1' ) {
			$include_former = '1';
		}

		// Double-check access control (permission_callback should have caught this,
		// but custom $wpdb queries bypass pre_get_posts hooks, so we verify explicitly)
		if ( ! is_user_logged_in() ) {
			return rest_ensure_response(
				[
					'people'      => [],
					'total'       => 0,
					'page'        => $page,
					'total_pages' => 0,
				]
			);
		}

		$offset = ( $page - 1 ) * $per_page;

		// Check if VOG-only user (should only see volunteers)
		$current_user_id = get_current_user_id();
		$volunteers_only = user_can( $current_user_id, 'vog' ) && ! user_can( $current_user_id, 'fairplay' );

		// Build query components
		$select_fields  = 'p.ID, p.post_title, p.post_modified, p.post_author';
		$join_clauses   = [];
		$where_clauses  = [
			"p.post_type = 'person'",
			"p.post_status = 'publish'",
		];
		$prepare_values = [];

		// Former member handling
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} fm ON p.ID = fm.post_id AND fm.meta_key = 'former_member'";
		if ( $include_former !== '1' ) {
			// Default: exclude former members
			$where_clauses[] = "(fm.meta_value IS NULL OR fm.meta_value = '' OR fm.meta_value = '0')";
		}
		$select_fields .= ', fm.meta_value AS is_former_member';

		// Always JOIN meta for first_name, infix, and last_name (needed for display and sorting)
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} fn ON p.ID = fn.post_id AND fn.meta_key = 'first_name'";
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} ix ON p.ID = ix.post_id AND ix.meta_key = 'infix'";
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} ln ON p.ID = ln.post_id AND ln.meta_key = 'last_name'";
		$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} tm ON p.ID = tm.post_id AND tm.meta_key = 'team'";
		$select_fields .= ', fn.meta_value AS first_name, ix.meta_value AS infix, ln.meta_value AS last_name';
		$select_fields .= ', tm.meta_value AS team_id';

		$has_birthdate_join    = false;
		$birthdate_value_sql   = "CASE
			WHEN br.meta_value REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' OR br.meta_value REGEXP '^[0-9]{8}$' THEN br.meta_value
			WHEN bd.meta_value REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' OR bd.meta_value REGEXP '^[0-9]{8}$' THEN bd.meta_value
			ELSE NULL
		END";
		$ensure_birthdate_join = static function () use ( &$has_birthdate_join, &$join_clauses, $wpdb ) {
			if ( ! $has_birthdate_join ) {
				$join_clauses[]     = "LEFT JOIN {$wpdb->postmeta} bd ON p.ID = bd.post_id AND bd.meta_key = '_birthdate'";
				$join_clauses[]     = "LEFT JOIN {$wpdb->postmeta} br ON p.ID = br.post_id AND br.meta_key = 'birthdate'";
				$has_birthdate_join = true;
			}
		};

		// VOG-only users can only see volunteers
		if ( $volunteers_only ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} vog_hv ON p.ID = vog_hv.post_id AND vog_hv.meta_key = 'huidig-vrijwilliger'";
			$where_clauses[] = "(vog_hv.meta_value = '1')";
		}

		// Age-group access filtering
		$permitted_age_groups = \Rondo\Core\AccessControl::get_permitted_age_groups( $current_user_id );

		if ( $permitted_age_groups !== null ) {
			if ( empty( $permitted_age_groups ) ) {
				// Scoped member: their own household, nothing else.
				$visible = \Rondo\Core\AccessControl::get_visible_person_ids( $current_user_id );

				if ( empty( $visible ) ) {
					$where_clauses[] = '1 = 0';
				} else {
					$id_placeholders = implode( ', ', array_fill( 0, count( $visible ), '%d' ) );
					$where_clauses[] = "p.ID IN ($id_placeholders)";
					$prepare_values  = array_merge( $prepare_values, $visible );
				}
			} else {
				$ag_placeholders = implode( ', ', array_fill( 0, count( $permitted_age_groups ), '%s' ) );
				$join_clauses[]  = "INNER JOIN {$wpdb->postmeta} ag ON p.ID = ag.post_id AND ag.meta_key = 'leeftijdsgroep'";
				$where_clauses[] = "ag.meta_value IN ($ag_placeholders)";
				$prepare_values  = array_merge( $prepare_values, $permitted_age_groups );
			}
		}

		// Ownership filter
		if ( $ownership === 'mine' ) {
			$where_clauses[]  = 'p.post_author = %d';
			$prepare_values[] = get_current_user_id();
		} elseif ( $ownership === 'shared' ) {
			$where_clauses[]  = 'p.post_author != %d';
			$prepare_values[] = get_current_user_id();
		}

		// Modified date filter
		if ( $modified_days !== null ) {
			$date_threshold   = gmdate( 'Y-m-d H:i:s', strtotime( "-{$modified_days} days" ) );
			$where_clauses[]  = 'p.post_modified >= %s';
			$prepare_values[] = $date_threshold;
		}

		// Birth year filter (supports denormalized _birthdate and direct birthdate meta)
		if ( $birth_year_from !== null || $birth_year_to !== null ) {
			$ensure_birthdate_join();
			$birth_year_expr = "CAST(SUBSTRING(REPLACE($birthdate_value_sql, '-', ''), 1, 4) AS UNSIGNED)";

			if ( $birth_year_from !== null && $birth_year_to !== null ) {
				// Range filter
				$where_clauses[]  = "$birth_year_expr BETWEEN %d AND %d";
				$prepare_values[] = $birth_year_from;
				$prepare_values[] = $birth_year_to;
			} elseif ( $birth_year_from !== null ) {
				// Minimum year only (treat as exact match for single year)
				$where_clauses[]  = "$birth_year_expr = %d";
				$prepare_values[] = $birth_year_from;
			} else {
				// Maximum year only (treat as exact match for single year)
				$where_clauses[]  = "$birth_year_expr = %d";
				$prepare_values[] = $birth_year_to;
			}
		}

		// Birth month filter (1-12) on normalized birthdate value.
		if ( $birth_month !== null ) {
			$ensure_birthdate_join();
			$where_clauses[]  = "CAST(SUBSTRING(REPLACE($birthdate_value_sql, '-', ''), 5, 2) AS UNSIGNED) = %d";
			$prepare_values[] = $birth_month;
		}

		// Custom field filters
		// Note: These use hardcoded field names for the specific Sportlink integration fields

		// Huidig vrijwilliger (current volunteer) - boolean filter
		if ( $huidig_vrijwilliger !== null && $huidig_vrijwilliger !== '' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} hv ON p.ID = hv.post_id AND hv.meta_key = 'huidig-vrijwilliger'";
			$where_clauses[] = $huidig_vrijwilliger === '1'
				? "(hv.meta_value = '1')"
				: "(hv.meta_value IS NULL OR hv.meta_value = '' OR hv.meta_value = '0')";
		}

		// Financiele blokkade (financial block) - boolean filter
		if ( $financiele_blokkade !== null && $financiele_blokkade !== '' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} fb ON p.ID = fb.post_id AND fb.meta_key = 'financiele-blokkade'";
			$where_clauses[] = $financiele_blokkade === '1'
				? "(fb.meta_value = '1' OR fb.meta_value = 'Ja')"
				: "(fb.meta_value IS NULL OR fb.meta_value = '' OR fb.meta_value = '0' OR fb.meta_value = 'Nee')";
		}

		// Type lid (member type) - select filter
		if ( ! empty( $type_lid ) ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} tl ON p.ID = tl.post_id AND tl.meta_key = 'type-lid'";
			$where_clauses[]  = 'tl.meta_value = %s';
			$prepare_values[] = $type_lid;
		}

		// Rondo person type. Legacy records without this field are members/parents.
		if ( ! empty( $person_type ) ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} pt ON p.ID = pt.post_id AND pt.meta_key = 'person_type'";
			if ( $person_type === 'contact' ) {
				$where_clauses[]  = 'pt.meta_value = %s';
				$prepare_values[] = $person_type;
			} else {
				$where_clauses[] = "(pt.meta_value IS NULL OR pt.meta_value = '' OR pt.meta_value = 'member')";
			}
		}

		// Sponsorship is an independent role and can overlap either person type.
		if ( $is_sponsor !== null && $is_sponsor !== '' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} sp ON p.ID = sp.post_id AND sp.meta_key = 'is_sponsor'";
			$where_clauses[] = $is_sponsor === '1'
				? "(sp.meta_value = '1')"
				: "(sp.meta_value IS NULL OR sp.meta_value = '' OR sp.meta_value = '0')";
		}

		// Businessclub membership is the active sponsor role with the Businessclub pass variant.
		if ( $is_businessclub_member !== null && $is_businessclub_member !== '' ) {
			$join_clauses[]         = "LEFT JOIN {$wpdb->postmeta} bcsp ON p.ID = bcsp.post_id AND bcsp.meta_key = 'is_sponsor'";
			$join_clauses[]         = "LEFT JOIN {$wpdb->postmeta} bcpv ON p.ID = bcpv.post_id AND bcpv.meta_key = 'sponsor_pass_variant'";
			$businessclub_condition = "(COALESCE(bcsp.meta_value, '') = '1' AND COALESCE(bcpv.meta_value, '') = 'businessclub')";
			$where_clauses[]        = $is_businessclub_member === '1'
				? $businessclub_condition
				: "NOT {$businessclub_condition}";
		}

		// Leeftijdsgroep (age group) - select filter
		if ( ! empty( $leeftijdsgroep ) ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} lg ON p.ID = lg.post_id AND lg.meta_key = 'leeftijdsgroep'";
			$where_clauses[]  = 'lg.meta_value = %s';
			$prepare_values[] = $leeftijdsgroep;
		}

		// Datum foto (photo date) - missing filter
		if ( $foto_missing === '1' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} df ON p.ID = df.post_id AND df.meta_key = 'datum-foto'";
			$where_clauses[] = "(df.meta_value IS NULL OR df.meta_value = '')";
		}

		// Datum VOG filtering based on vog_type
		if ( $vog_type === 'nieuw' ) {
			// Only show people WITHOUT a VOG date
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$where_clauses[] = "(dv.meta_value IS NULL OR dv.meta_value = '')";
		} elseif ( $vog_type === 'vernieuwing' && $vog_older_than_years !== null ) {
			// Only show people WITH an expired VOG date
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
			$where_clauses[]  = "(dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value <= %s)";
			$prepare_values[] = $cutoff_date;
		} elseif ( $vog_missing === '1' && $vog_older_than_years !== null ) {
			// Default: OR both conditions (show all needing VOG)
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
			$where_clauses[]  = "((dv.meta_value IS NULL OR dv.meta_value = '') OR (dv.meta_value <= %s))";
			$prepare_values[] = $cutoff_date;
		} elseif ( $vog_missing === '1' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$where_clauses[] = "(dv.meta_value IS NULL OR dv.meta_value = '')";
		} elseif ( $vog_older_than_years !== null ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$cutoff_date      = gmdate( 'Y-m-d', strtotime( "-{$vog_older_than_years} years" ) );
			$where_clauses[]  = "(dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value <= %s)";
			$prepare_values[] = $cutoff_date;
		} elseif ( $vog_expiring_within_days !== null ) {
			// Find people whose VOG is still valid but will expire within N days.
			// VOG validity = 3 years. Expiry = datum-vog + 3 years.
			// We want: today < expiry <= today + N days
			// Which means: today - 3 years < datum-vog <= today + N days - 3 years
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
			$expired_date     = gmdate( 'Y-m-d', strtotime( '-3 years' ) );
			$expiring_date    = gmdate( 'Y-m-d', strtotime( "+{$vog_expiring_within_days} days -3 years" ) );
			$where_clauses[]  = "(dv.meta_value IS NOT NULL AND dv.meta_value != '' AND dv.meta_value > %s AND dv.meta_value <= %s)";
			$prepare_values[] = $expired_date;
			$prepare_values[] = $expiring_date;
		}

		// VOG email status filter (sent/not_sent based on vog_email_sent_date meta field)
		if ( $vog_email_status !== null && $vog_email_status !== '' ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} ves ON p.ID = ves.post_id AND ves.meta_key = 'vog_email_sent_date'";

			if ( $vog_email_status === 'sent' ) {
				$where_clauses[] = "(ves.meta_value IS NOT NULL AND ves.meta_value != '')";
			} elseif ( $vog_email_status === 'not_sent' ) {
				$where_clauses[] = "(ves.meta_value IS NULL OR ves.meta_value = '')";
			}
		}

		// VOG Justis status filter (submitted/not_submitted based on vog_justis_submitted_date meta field)
		if ( $vog_justis_status !== null && $vog_justis_status !== '' ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} vjs ON p.ID = vjs.post_id AND vjs.meta_key = 'vog_justis_submitted_date'";

			if ( $vog_justis_status === 'submitted' ) {
				$where_clauses[] = "(vjs.meta_value IS NOT NULL AND vjs.meta_value != '')";
			} elseif ( $vog_justis_status === 'not_submitted' ) {
				$where_clauses[] = "(vjs.meta_value IS NULL OR vjs.meta_value = '')";
			}
		}

		// VOG Reminder status filter (sent/not_sent based on vog_reminder_sent_date meta field)
		if ( $vog_reminder_status !== null && $vog_reminder_status !== '' ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} vrs ON p.ID = vrs.post_id AND vrs.meta_key = 'vog_reminder_sent_date'";

			if ( $vog_reminder_status === 'sent' ) {
				$where_clauses[] = "(vrs.meta_value IS NOT NULL AND vrs.meta_value != '')";
			} elseif ( $vog_reminder_status === 'not_sent' ) {
				$where_clauses[] = "(vrs.meta_value IS NULL OR vrs.meta_value = '')";
			}
		}

		// Lid-tot (membership end date) future filter
		if ( $lid_tot_future === '1' ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} lt ON p.ID = lt.post_id AND lt.meta_key = 'lid-tot'";
			$today            = gmdate( 'Y-m-d' );
			$where_clauses[]  = "(lt.meta_value IS NOT NULL AND lt.meta_value != '' AND lt.meta_value >= %s)";
			$prepare_values[] = $today;
		}

		// Lid-tot cancelled-this-season filter (Dutch sports season: 1 Jul – 30 Jun).
		// Window: members whose lid-tot falls inside the season that contains today.
		if ( $lid_tot_season === '1' && $lid_tot_future !== '1' ) {
			[ $season_start, $season_end ] = $this->get_current_season_window();
			$join_clauses[]                = "LEFT JOIN {$wpdb->postmeta} lt ON p.ID = lt.post_id AND lt.meta_key = 'lid-tot'";
			$where_clauses[]               = "(lt.meta_value IS NOT NULL AND lt.meta_value != '' AND lt.meta_value >= %s AND lt.meta_value <= %s)";
			$prepare_values[]              = $season_start;
			$prepare_values[]              = $season_end;
		}

		// Lid-sinds new-this-season filter (Dutch sports season: 1 Jul – 30 Jun).
		// Window: members whose lid-sinds falls inside the season that contains today.
		// Does NOT auto-include former members — by default we want current members who joined this season,
		// not people who joined and already cancelled.
		if ( $lid_sinds_season === '1' ) {
			[ $season_start, $season_end ] = $this->get_current_season_window();
			$join_clauses[]                = "LEFT JOIN {$wpdb->postmeta} ls ON p.ID = ls.post_id AND ls.meta_key = 'lid-sinds'";
			$where_clauses[]               = "(ls.meta_value IS NOT NULL AND ls.meta_value != '' AND ls.meta_value >= %s AND ls.meta_value <= %s)";
			$prepare_values[]              = $season_start;
			$prepare_values[]              = $season_end;
		}

		// Spelactiviteit without team filter
		// Shows people who have a spelactiviteit value but no current PLAYER role in a team.
		// Volunteer/staff roles in teams should NOT exclude a person from this filter.
		if ( $spelactiviteit_no_team === '1' ) {
			$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} sa ON p.ID = sa.post_id AND sa.meta_key = 'spelactiviteit'";

			// Build the list of player roles for the SQL IN clause.
			$player_roles      = \Rondo\Core\VolunteerStatus::get_player_roles();
			$role_placeholders = implode( ', ', array_fill( 0, count( $player_roles ), '%s' ) );

			// Subquery: person has at least one current work_history entry that is
			// (a) linked to a team (post_type = 'team'), and (b) has a player job_title.
			// ACF repeater rows are stored as work_history_{N}_job_title, work_history_{N}_team, etc.
			$player_team_subquery = $wpdb->prepare(
				"SELECT 1
				 FROM {$wpdb->postmeta} wh_jt
				 JOIN {$wpdb->postmeta} wh_tm
				   ON wh_jt.post_id = wh_tm.post_id
				   AND REPLACE(wh_jt.meta_key, '_job_title', '_team') = wh_tm.meta_key
				 JOIN {$wpdb->postmeta} wh_ic
				   ON wh_jt.post_id = wh_ic.post_id
				   AND REPLACE(wh_jt.meta_key, '_job_title', '_is_current') = wh_ic.meta_key
				 JOIN {$wpdb->posts} linked_team
				   ON wh_tm.meta_value = linked_team.ID
				 WHERE wh_jt.post_id = p.ID
				   AND wh_jt.meta_key REGEXP '^work_history_[0-9]+_job_title$'
				   AND wh_ic.meta_value = '1'
				   AND linked_team.post_type = 'team'
				   AND wh_jt.meta_value IN ($role_placeholders)
				 LIMIT 1",
				...$player_roles
			);

			$where_clauses[] = "(sa.meta_value IS NOT NULL AND sa.meta_value != '' AND NOT EXISTS ($player_team_subquery))";
		}

		// Spelend lid — playing members have a spelactiviteit value other than empty or '-'.
		// 1 = playing (value set and not '-'), 0 = not playing (no value or '-').
		if ( $spelend_lid !== null && $spelend_lid !== '' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} sl ON p.ID = sl.post_id AND sl.meta_key = 'spelactiviteit'";
			$where_clauses[] = $spelend_lid === '1'
				? "(sl.meta_value IS NOT NULL AND sl.meta_value != '' AND sl.meta_value != '-')"
				: "(sl.meta_value IS NULL OR sl.meta_value = '' OR sl.meta_value = '-')";
		}

		// Wacht op overschrijving — members transferred in from another club whose
		// KNVB transfer hasn't been processed yet (Sportlink Tooltip "Actie van
		// een ander (overschrijving)").
		if ( $wacht_op_overschrijving === '1' ) {
			$join_clauses[]  = "LEFT JOIN {$wpdb->postmeta} wo ON p.ID = wo.post_id AND wo.meta_key = 'wacht_op_overschrijving'";
			$where_clauses[] = "(wo.meta_value = '1')";
		}

		// Onboarding: new members.
		// lid-sinds within the last 30 days AND no onboarding-email-lid-sent timestamp.
		if ( $onboarding_new_members === '1' ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} ols ON p.ID = ols.post_id AND ols.meta_key = 'lid-sinds'";
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} oles ON p.ID = oles.post_id AND oles.meta_key = 'onboarding-email-lid-sent'";
			$cutoff           = gmdate( 'Y-m-d', strtotime( '-30 days' ) );
			$where_clauses[]  = '(ols.meta_value IS NOT NULL AND ols.meta_value != \'\' AND ols.meta_value >= %s)';
			$where_clauses[]  = '(oles.meta_value IS NULL OR oles.meta_value = \'\')';
			$prepare_values[] = $cutoff;
		}

		// Onboarding: new volunteers.
		// vrijwilliger-sinds within last 60 days AND huidig-vrijwilliger=1 AND no onboarding-email-vrijwilliger-sent timestamp.
		if ( $onboarding_new_volunteers === '1' ) {
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} ovs ON p.ID = ovs.post_id AND ovs.meta_key = 'vrijwilliger-sinds'";
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} ohv ON p.ID = ohv.post_id AND ohv.meta_key = 'huidig-vrijwilliger'";
			$join_clauses[]   = "LEFT JOIN {$wpdb->postmeta} oves ON p.ID = oves.post_id AND oves.meta_key = 'onboarding-email-vrijwilliger-sent'";
			$cutoff           = gmdate( 'Y-m-d', strtotime( '-60 days' ) );
			$where_clauses[]  = '(ovs.meta_value IS NOT NULL AND ovs.meta_value != \'\' AND ovs.meta_value >= %s)';
			$where_clauses[]  = "(ohv.meta_value = '1')";
			$where_clauses[]  = '(oves.meta_value IS NULL OR oves.meta_value = \'\')';
			$prepare_values[] = $cutoff;
		}

		// Build ORDER BY clause (columns are whitelisted in args validation)
		// ORDER and orderby are safe - validated against whitelist
		switch ( $orderby ) {
			case 'first_name':
				$order_clause = "ORDER BY COALESCE(NULLIF(fn.meta_value, ''), p.post_title) $order, ln.meta_value $order";
				break;
			case 'last_name':
				$order_clause = "ORDER BY COALESCE(NULLIF(ln.meta_value, ''), p.post_title) $order, fn.meta_value $order";
				break;
			case 'modified':
				$order_clause = "ORDER BY p.post_modified $order";
				break;
			case 'organization':
				$order_clause = "ORDER BY
					(tm.meta_value IS NULL OR tm.meta_value = '') ASC,
					tm.meta_value $order,
					fn.meta_value ASC";
				break;
			case 'birthdate':
				$ensure_birthdate_join();
				$order_clause = "ORDER BY
					($birthdate_value_sql IS NULL) ASC,
					CAST(REPLACE($birthdate_value_sql, '-', '') AS UNSIGNED) $order,
					fn.meta_value ASC";
				break;
			case 'custom_datum-vog':
				// ACF date field - not a custom field from Manager, so handle explicitly
				// Check if 'dv' alias already exists from VOG filtering (lines 1114-1149)
				// to avoid duplicate JOINs on the same table
				$has_dv_join = false;
				foreach ( $join_clauses as $join ) {
					if ( strpos( $join, ' dv ON ' ) !== false && strpos( $join, "meta_key = 'datum-vog'" ) !== false ) {
						$has_dv_join = true;
						break;
					}
				}
				if ( ! $has_dv_join ) {
					$join_clauses[] = "LEFT JOIN {$wpdb->postmeta} dv ON p.ID = dv.post_id AND dv.meta_key = 'datum-vog'";
				}
				$order_clause = "ORDER BY COALESCE(dv.meta_value, '') $order, fn.meta_value ASC";
				break;
			case 'custom_lid-sinds':
			case 'custom_lid-tot':
			case 'custom_vrijwilliger-sinds':
			case 'custom_datum-foto':
				// ACF date fields (not from Manager)
				$field_name     = substr( $orderby, 7 ); // Remove 'custom_' prefix
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				$order_clause   = "ORDER BY STR_TO_DATE(cf.meta_value, '%%Y-%%m-%%d') $order, fn.meta_value ASC";
				break;
			case 'custom_isparent':
			case 'custom_huidig-vrijwilliger':
			case 'custom_financiele-blokkade':
				// Boolean ACF fields
				$field_name     = substr( $orderby, 7 );
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				$order_clause   = "ORDER BY CAST(COALESCE(cf.meta_value, '0') AS UNSIGNED) $order, fn.meta_value ASC";
				break;
			case 'custom_freescout-id':
				// Numeric ACF field
				$field_name     = substr( $orderby, 7 );
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				$order_clause   = "ORDER BY CAST(cf.meta_value AS DECIMAL(10,2)) $order, fn.meta_value ASC";
				break;
			case 'custom_knvb-id':
			case 'custom_type-lid':
				// Text ACF fields
				$field_name     = substr( $orderby, 7 );
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				$order_clause   = "ORDER BY COALESCE(cf.meta_value, '') $order, fn.meta_value ASC";
				break;
			case 'custom_leeftijdsgroep':
				// ACF field with custom age group sorting logic
				$field_name     = substr( $orderby, 7 );
				$join_clauses[] = $wpdb->prepare(
					"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
					$field_name
				);
				// Custom sort for leeftijdsgroep: Onder 6 < Onder 7 < ... < Onder 19 < Senioren
				$order_clause = "ORDER BY
					CASE
						WHEN cf.meta_value LIKE 'Onder %' THEN CAST(SUBSTRING(cf.meta_value, 7) AS UNSIGNED)
						WHEN cf.meta_value LIKE 'Senioren%' THEN 99
						ELSE 100
					END $order,
					CASE
						WHEN cf.meta_value LIKE '%Meiden%' OR cf.meta_value LIKE '%Vrouwen%' THEN 1
						ELSE 0
					END $order,
					fn.meta_value ASC";
				break;
			default:
				// Check if this is a custom field (starts with 'custom_')
				if ( strpos( $orderby, 'custom_' ) === 0 ) {
					$field_name = substr( $orderby, 7 );

					// Get the field definition to determine type-appropriate sorting
					$manager    = new Manager();
					$fields     = $manager->get_fields( 'person', false );
					$field_type = null;

					foreach ( $fields as $field ) {
						if ( $field['name'] === $field_name ) {
							$field_type = $field['type'];
							break;
						}
					}

					// Add LEFT JOIN for the custom field meta
					$join_clauses[] = $wpdb->prepare(
						"LEFT JOIN {$wpdb->postmeta} cf ON p.ID = cf.post_id AND cf.meta_key = %s",
						$field_name
					);

					// Build type-appropriate ORDER BY clause
					// Always include first_name as secondary sort for consistent ordering
					if ( $field_name === 'leeftijdsgroep' ) {
						// Custom sort for leeftijdsgroep: Onder 6 < Onder 7 < ... < Onder 19 < Senioren
						// Extract numeric part from "Onder X" values, treat "Senioren" as 99
						$order_clause = "ORDER BY
							CASE
								WHEN cf.meta_value LIKE 'Onder %' THEN CAST(SUBSTRING(cf.meta_value, 7) AS UNSIGNED)
								WHEN cf.meta_value LIKE 'Senioren%' THEN 99
								ELSE 100
							END $order,
							CASE
								WHEN cf.meta_value LIKE '%Meiden%' OR cf.meta_value LIKE '%Vrouwen%' THEN 1
								ELSE 0
							END $order,
							fn.meta_value ASC";
					} elseif ( $field_type === 'number' ) {
						// Numeric sort with NULLS LAST
						$order_clause = "ORDER BY CAST(cf.meta_value AS DECIMAL(10,2)) $order, fn.meta_value ASC";
					} elseif ( $field_type === 'date' ) {
						// Date sort (ACF stores dates as Y-m-d format) with NULLS LAST
						// Double %% to escape for wpdb->prepare()
						$order_clause = "ORDER BY STR_TO_DATE(cf.meta_value, '%%Y-%%m-%%d') $order, fn.meta_value ASC";
					} elseif ( $field_type === 'true_false' ) {
						// Boolean sort (ACF stores as 1 or 0/empty) - cast to integer
						$order_clause = "ORDER BY CAST(COALESCE(cf.meta_value, '0') AS UNSIGNED) $order, fn.meta_value ASC";
					} else {
						// Text-based sort (text, textarea, select, email, url) with NULLS LAST
						$order_clause = "ORDER BY COALESCE(cf.meta_value, '') $order, fn.meta_value ASC";
					}
				} else {
					// Fallback to first_name
					$order_clause = "ORDER BY fn.meta_value $order";
				}
		}

		// Combine clauses
		$join_sql  = implode( ' ', $join_clauses );
		$where_sql = implode( ' AND ', $where_clauses );

		// Main query with DISTINCT (needed when filtering by taxonomy to avoid duplicates)
		$main_sql = "SELECT DISTINCT $select_fields
					 FROM {$wpdb->posts} p
					 $join_sql
					 WHERE $where_sql
					 $order_clause";

		// Add pagination
		$prepare_values[] = $per_page;
		$prepare_values[] = $offset;
		$paginated_sql    = $main_sql . ' LIMIT %d OFFSET %d';

		// Prepare and execute main query
		$prepared_sql = $wpdb->prepare( $paginated_sql, $prepare_values );
		$results      = $wpdb->get_results( $prepared_sql );

		// Count query (same joins/where, no order/limit)
		// Need to rebuild prepare_values without the pagination values
		$count_prepare_values = array_slice( $prepare_values, 0, -2 );
		$count_sql            = "SELECT COUNT(DISTINCT p.ID)
								 FROM {$wpdb->posts} p
								 $join_sql
								 WHERE $where_sql";

		if ( ! empty( $count_prepare_values ) ) {
			$prepared_count_sql = $wpdb->prepare( $count_sql, $count_prepare_values );
		} else {
			$prepared_count_sql = $count_sql;
		}
		$total = (int) $wpdb->get_var( $prepared_count_sql );

		// Format results
		$people = [];
		foreach ( $results as $row ) {
			$person = [
				'id'            => (int) $row->ID,
				'name'          => $this->sanitize_text( $row->post_title ?: '' ),
				'first_name'    => $this->sanitize_text( $row->first_name ?: '' ),
				'infix'         => $this->sanitize_text( $row->infix ?: '' ),
				'last_name'     => $this->sanitize_text( $row->last_name ?: '' ),
				'team_id'       => is_numeric( $row->team_id ) ? (int) $row->team_id : null,
				'modified'      => $row->post_modified,
				'former_member' => ( $row->is_former_member === '1' ),
				// These are fetched post-query to avoid complex JOINs
				'thumbnail'     => $this->sanitize_url( get_the_post_thumbnail_url( $row->ID, 'thumbnail' ) ),
			];

			// Add ACF fields for custom field columns
			if ( function_exists( 'get_fields' ) ) {
				$acf_fields = get_fields( $row->ID );
				if ( $acf_fields ) {
					$person['acf'] = $acf_fields;
				}
			}

			// Add VOG-related post meta fields to acf array for frontend consistency
			$vog_email_sent = get_post_meta( $row->ID, 'vog_email_sent_date', true );
			$vog_justis     = get_post_meta( $row->ID, 'vog_justis_submitted_date', true );
			$vog_reminder   = get_post_meta( $row->ID, 'vog_reminder_sent_date', true );
			if ( $vog_email_sent ) {
				$person['acf']['vog_email_sent_date'] = $vog_email_sent;
			}
			if ( $vog_justis ) {
				$person['acf']['vog_justis_submitted_date'] = $vog_justis;
			}
			if ( $vog_reminder ) {
				$person['acf']['vog_reminder_sent_date'] = $vog_reminder;
			}

			$people[] = $person;
		}

		return rest_ensure_response(
			[
				'people'      => $people,
				'total'       => $total,
				'page'        => $page,
				'total_pages' => (int) ceil( $total / $per_page ),
			]
		);
	}

	/**
	 * Get dynamic filter configuration
	 *
	 * Maps filter keys to their meta_key and sort method.
	 * This makes adding future dynamic filters trivial.
	 *
	 * @return array Filter configuration.
	 */
	/**
	 * Return [start, end] dates (Y-m-d strings) for the Dutch sports season
	 * that contains "today" — 1 July through 30 June.
	 *
	 * Before 1 July: season ran (year-1)-07-01 .. year-06-30
	 * On/after 1 July: season runs year-07-01 .. (year+1)-06-30
	 *
	 * @return array{0:string,1:string} Tuple of [season_start, season_end].
	 */
	private function get_current_season_window(): array {
		$today_year  = (int) gmdate( 'Y' );
		$today_month = (int) gmdate( 'n' );

		if ( $today_month < 7 ) {
			return [ ( $today_year - 1 ) . '-07-01', $today_year . '-06-30' ];
		}

		return [ $today_year . '-07-01', ( $today_year + 1 ) . '-06-30' ];
	}

	private function get_dynamic_filter_config() {
		return [
			'age_groups'   => [
				'meta_key'    => 'leeftijdsgroep',
				'sort_method' => 'sort_age_groups',
			],
			'member_types' => [
				'meta_key'    => 'type-lid',
				'sort_method' => 'sort_member_types',
			],
		];
	}

	/**
	 * Get filter options with counts
	 *
	 * Returns dynamic filter options for the People list.
	 * Each option includes the value and count of matching people.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response Response with filter options and counts.
	 */
	public function get_filter_options( $request ) {
		global $wpdb;

		// Double-check access control (permission_callback should have caught this)
		if ( ! is_user_logged_in() ) {
			return rest_ensure_response(
				[
					'total'        => 0,
					'age_groups'   => [],
					'member_types' => [],
				]
			);
		}

		// Get total count of published person posts
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*)
			 FROM {$wpdb->posts}
			 WHERE post_type = 'person'
			   AND post_status = 'publish'"
		);

		$result = [ 'total' => $total ];

		// Get filter configuration
		$filters = $this->get_dynamic_filter_config();

		// Query each filter
		foreach ( $filters as $filter_key => $config ) {
			$meta_key    = $config['meta_key'];
			$sort_method = $config['sort_method'];

			// Query DISTINCT meta_values with COUNT using GROUP BY
			$sql = $wpdb->prepare(
				"SELECT pm.meta_value AS value, COUNT(DISTINCT p.ID) AS count
				 FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				 WHERE p.post_type = 'person'
				   AND p.post_status = 'publish'
				   AND pm.meta_key = %s
				   AND pm.meta_value != ''
				 GROUP BY pm.meta_value
				 HAVING count > 0",
				$meta_key
			);

			$rows = $wpdb->get_results( $sql );

			// Convert to array with value and count
			$options = [];
			foreach ( $rows as $row ) {
				$options[] = [
					'value' => $row->value,
					'count' => (int) $row->count,
				];
			}

			// Apply sort method
			if ( method_exists( $this, $sort_method ) ) {
				$options = $this->$sort_method( $options );
			}

			$result[ $filter_key ] = $options;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Sort age groups intelligently
	 *
	 * Sorts age groups youngest to oldest with smart numeric extraction.
	 * - "Onder 6" before "Onder 7", etc.
	 * - Within same number, base value before gender variant (e.g., "Onder 9" before "Onder 9 Meiden")
	 * - Non-numeric values (e.g., "Senioren", "Senioren Vrouwen") sort to end
	 * - Among non-numeric, base before gender variant
	 *
	 * @param array $options Array of options with value and count.
	 * @return array Sorted array.
	 */
	private function sort_age_groups( $options ) {
		usort(
			$options,
			function ( $a, $b ) {
				$a_value = $a['value'];
				$b_value = $b['value'];

				// Extract numeric part from "Onder X" pattern
				$a_has_number = preg_match( '/(\d+)/', $a_value, $a_matches );
				$b_has_number = preg_match( '/(\d+)/', $b_value, $b_matches );

				$a_number = $a_has_number ? (int) $a_matches[1] : 9999;
				$b_number = $b_has_number ? (int) $b_matches[1] : 9999;

				// Primary sort: by number (youngest to oldest)
				if ( $a_number !== $b_number ) {
					return $a_number - $b_number;
				}

				// Secondary sort: gender variants after base groups
				$a_has_gender = strpos( $a_value, 'Meiden' ) !== false || strpos( $a_value, 'Vrouwen' ) !== false;
				$b_has_gender = strpos( $b_value, 'Meiden' ) !== false || strpos( $b_value, 'Vrouwen' ) !== false;

				if ( $a_has_gender !== $b_has_gender ) {
					return $a_has_gender ? 1 : -1;
				}

				// Tertiary sort: alphabetical
				return strcmp( $a_value, $b_value );
			}
		);

		return $options;
	}

	/**
	 * Sort member types in priority order
	 *
	 * Sorts member types in meaningful priority order.
	 * Values not in priority array sort to end (allows new types from sync to appear automatically).
	 *
	 * @param array $options Array of options with value and count.
	 * @return array Sorted array.
	 */
	private function sort_member_types( $options ) {
		$priority = [
			'Junior'             => 1,
			'Senior'             => 2,
			'Donateur'           => 3,
			'Lid van Verdienste' => 4,
		];

		usort(
			$options,
			function ( $a, $b ) use ( $priority ) {
				$a_priority = $priority[ $a['value'] ] ?? 99;
				$b_priority = $priority[ $b['value'] ] ?? 99;

				if ( $a_priority !== $b_priority ) {
					return $a_priority - $b_priority;
				}

				// Same priority: alphabetical
				return strcmp( $a['value'], $b['value'] );
			}
		);

		return $options;
	}
}
