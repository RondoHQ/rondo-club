<?php
/**
 * Volunteer REST API Endpoints
 *
 * Owns every /rondo/v1/volunteer-* endpoint. Today it covers eligibility
 * derivation and exemption resolution; later phases will hang shift signup,
 * IVA approval, and the obligation counter off this same class.
 *
 * @package Rondo\REST
 */

namespace Rondo\REST;

use Rondo\Fees\SeasonKey;
use Rondo\Fields\Fields;
use Rondo\Volunteer\IvaApprovalEmailSender;
use Rondo\Volunteer\IvaCertificateParser;
use Rondo\Volunteer\IvaReviewNotificationEmailSender;
use Rondo\Volunteer\IvaStatus;
use Rondo\Volunteer\RelationshipQualityChecker;
use Rondo\Volunteer\ShiftAssignments;
use Rondo\Volunteer\VolunteerCacheInvalidator;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerExemptionResolver;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Rondo\Volunteer\VolunteerSeeder;
use Rondo\Volunteer\VolunteerStatistics;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Volunteer extends Base {
	private const PRIVATE_IVA_VERSION = 1;
	private const PRIVATE_PATH_META   = '_rondo_private_iva_path';
	private const ORIGINAL_NAME_META  = '_rondo_private_iva_name';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $this, 'serve_private_iva_file' ], 10, 4 );
	}

	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/volunteer-eligibility',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_eligibility' ],
				'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
				'args'                => [
					'season'       => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
					'with_persons' => [
						'required' => false,
						'default'  => false,
					],
					'person_id'    => [
						'required'          => false,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/volunteer-statistics',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_statistics' ],
				'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
				'args'                => [
					'season' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => [ $this, 'validate_optional_season' ],
					],
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/managed-commissies',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_managed_commissies' ],
				'permission_callback' => '__return_true',
			]
		);

		// Data-quality drill-down — returns the personen behind each category
		// surfaced on the dashboard's "Datakwaliteit" card.
		register_rest_route(
			'rondo/v1',
			'/volunteer-data-quality/(?P<category>[a-z_]+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_data_quality_persons' ],
				// no_email is a ledenadministratie concern (activation gaps), so it
				// gates tighter than the eligibility categories — see
				// check_data_quality_permission().
				'permission_callback' => [ $this, 'check_data_quality_permission' ],
				'args'                => [
					'category' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $param ) {
							return in_array(
								$param,
								[ 'orphan', 'address_fallback', 'missing_leeftijdsgroep', 'no_email' ],
								true
							);
						},
					],
					'season'   => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Relationship-quality drill-down — flagged suspect ouder/kind/sibling
		// links uit de relationships repeater.
		register_rest_route(
			'rondo/v1',
			'/relationship-quality',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_suspect_relationships' ],
				'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
			]
		);

		// Manual cache-bust voor de dashboard "ververs"-knop. Wipt zowel de
		// eligibility-view als de relationship-quality transients.
		register_rest_route(
			'rondo/v1',
			'/volunteer-cache/refresh',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'refresh_cache' ],
				'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/volunteer-exemption/(?P<person_id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_exemption' ],
					'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
					'args'                => [
						'person_id' => [
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
						'season'    => [
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_manual_exemption' ],
					'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
					'args'                => [
						'person_id' => [
							'required'          => true,
							'sanitize_callback' => 'absint',
						],
						'enabled'   => [
							'required'          => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
						],
						'reason'    => [
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_textarea_field',
						],
						'season'    => [
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => [ $this, 'validate_optional_season' ],
						],
					],
				],
			]
		);

		// IVA upload — door het lid zélf, via /vrijwillig/profiel. Schrijft het
		// uploadbestand naar de attachment-library, koppelt het aan het native field veld
		// op de gelinkte persoon, en reset iva-approved zodat de bestuurslid
		// kantine het opnieuw beoordeelt.
		register_rest_route(
			'rondo/v1',
			'/iva/upload',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_iva' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'datum_iva' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// Lid haalt zijn/haar eigen IVA-status op voor /vrijwillig/profiel
		// (zonder dat we daar het hele admin-endpoint voor open hoeven te zetten).
		register_rest_route(
			'rondo/v1',
			'/iva/me',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_my_iva' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		register_rest_route(
			'rondo/v1',
			'/iva/(?P<person_id>\d+)/certificate',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_iva_certificate' ],
				'permission_callback' => [ $this, 'check_iva_certificate_permission' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);

		// Lid haalt zijn/haar eigen VOG-status op voor /profile/vog.
		register_rest_route(
			'rondo/v1',
			'/vog/me',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_my_vog' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// IVA approval — restricted to bestuurslid kantine (rondo_iva_approve cap).
		register_rest_route(
			'rondo/v1',
			'/iva/(?P<person_id>\d+)/approve',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'approve_iva' ],
				'permission_callback' => [ $this, 'check_iva_approve_permission' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'approve'   => [
						'required' => false,
						'default'  => true,
					],
				],
			]
		);

		// Obligations — counter view per unit, plus aggregate dashboard stats.
		register_rest_route(
			'rondo/v1',
			'/volunteer-obligations',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_obligations' ],
				'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
				'args'                => [
					'season' => [
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);

		// No-show endpoint (admin / coordinator only).
		register_rest_route(
			'rondo/v1',
			'/shifts/(?P<id>\d+)/no-show',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'mark_no_show' ],
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'args'                => [
					'id'        => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
					'revert'    => [
						'required' => false,
						'default'  => false,
					],
				],
			]
		);

		// IVA admin overview — every person who has uploaded a cert, has a datum-iva,
		// or has been approved. Keeps the result set small enough that we don't need
		// pagination (typical clubs have ~30–100 IVA-relevant people, not the full
		// member roster). Available to anyone with the vrijwilligers cap.
		register_rest_route(
			'rondo/v1',
			'/iva/people',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_iva_people' ],
				'permission_callback' => [ $this, 'check_iva_view_permission' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/iva/(?P<person_id>\d+)/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_iva_status' ],
				'permission_callback' => [ $this, 'check_vrijwilligers_permission' ],
				'args'                => [
					'person_id' => [
						'required'          => true,
						'sanitize_callback' => 'absint',
					],
				],
			]
		);
	}

	public function check_iva_approve_permission() {
		return current_user_can( 'rondo_iva_approve' ) || current_user_can( 'manage_options' );
	}

	public function check_iva_view_permission() {
		return current_user_can( 'vrijwilligers' )
			|| current_user_can( 'rondo_iva_approve' )
			|| current_user_can( 'manage_options' );
	}

	public function check_iva_certificate_permission( \WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		if ( $this->check_iva_view_permission() ) {
			return true;
		}

		return (int) get_user_meta( get_current_user_id(), 'rondo_linked_person_id', true ) === (int) $request->get_param( 'person_id' );
	}

	/**
	 * GET /rondo/v1/iva/people
	 *
	 * Returns every person with any IVA-relevant data: a datum-iva, an uploaded
	 * certificaat, or an iva-approved flag. The set is naturally small (kantine
	 * bar-vrijwilligers) so we return it all in one shot, no pagination.
	 */
	public function get_iva_people( \WP_REST_Request $request ) {
		$ids = $this->collect_iva_person_ids();

		if ( empty( $ids ) ) {
			return rest_ensure_response( [ 'people' => [] ] );
		}

		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'post__in'         => $ids,
				'posts_per_page'   => count( $ids ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			]
		);

		$people = [];
		foreach ( $query->posts as $post ) {
			$cert_field = \Rondo\Fields\Fields::get_for_post( $post->ID, 'iva_certificaat' );
			$cert_url   = $cert_field ? $this->iva_certificate_url( $post->ID ) : '';

			$people[] = [
				'id'              => $post->ID,
				'name'            => $this->sanitize_text( $post->post_title ),
				'first_name'      => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $post->ID, 'first_name' ) ),
				'infix'           => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $post->ID, 'infix' ) ),
				'last_name'       => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $post->ID, 'last_name' ) ),
				'thumbnail'       => $this->sanitize_url( get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ),
				'datum_iva'       => \Rondo\Fields\Formatter::for_wire( 'person', [ 'datum_iva' => \Rondo\Fields\Fields::get_for_post( $post->ID, 'datum_iva' ) ] )['datum_iva'],
				'iva_certificaat' => $cert_url ? $this->sanitize_url( $cert_url ) : '',
				'iva_approved'    => (bool) get_post_meta( $post->ID, 'iva-approved', true ),
				'status'          => IvaStatus::status( $post->ID ),
				'expires_at'      => IvaStatus::expires_at( $post->ID ),
			];
		}

		return rest_ensure_response( [ 'people' => $people ] );
	}

	/** Find the post IDs of every person with any IVA-relevant meta set. */
	private function collect_iva_person_ids(): array {
		$ids = [];

		// Separate meta-key lookups use WordPress' indexed postmeta key path. A
		// three-way OR with NOT IN made the first production migration time out.
		foreach ( [ 'datum-iva', 'iva-certificaat', 'iva-approved' ] as $meta_key ) {
			$candidates = get_posts(
				[
					'post_type'        => 'person',
					'post_status'      => 'publish',
					'posts_per_page'   => -1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => true,
					'meta_key'         => $meta_key,
					'meta_compare'     => 'EXISTS',
				]
			);

			update_meta_cache( 'post', $candidates );
			foreach ( $candidates as $person_id ) {
				$value = get_post_meta( $person_id, $meta_key, true );
				if ( $value !== '' && $value !== '0' ) {
					$ids[] = (int) $person_id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * POST /rondo/v1/iva/upload
	 *
	 * Lid-zelf upload. Schrijft het bestand naar de attachment-library,
	 * koppelt het aan het native field veld iva-certificaat van de gelinkte persoon,
	 * en reset iva-approved zodat de bestuurslid kantine het opnieuw beoordeelt.
	 * Accepteert een optionele datum_iva (anders: vandaag).
	 */
	public function upload_iva( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return new \WP_Error( 'not_logged_in', 'Niet ingelogd.', [ 'status' => 401 ] );
		}

		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error(
				'no_linked_person',
				'Geen gekoppeld lid-profiel — vraag de ledenadministratie om je account te koppelen.',
				[ 'status' => 404 ]
			);
		}

		$files = $request->get_file_params();
		if ( empty( $files['certificaat'] ) ) {
			return new \WP_Error(
				'no_file',
				'Geen bestand geüpload. Stuur het PDF-certificaat mee als veld "certificaat".',
				[ 'status' => 400 ]
			);
		}

		$file          = $files['certificaat'];
		$allowed_types = [ 'application/pdf', 'image/jpeg', 'image/png' ];
		if ( ! in_array( $file['type'], $allowed_types, true ) ) {
			return new \WP_Error(
				'invalid_file_type',
				'Alleen PDF, JPG of PNG-bestanden zijn toegestaan.',
				[ 'status' => 400 ]
			);
		}

		if ( $file['size'] > 10 * 1024 * 1024 ) {
			return new \WP_Error(
				'file_too_large',
				'Bestand is te groot — maximaal 10 MB.',
				[ 'status' => 400 ]
			);
		}

		$checked = wp_check_filetype_and_ext(
			$file['tmp_name'],
			$file['name'],
			[
				'pdf'          => 'application/pdf',
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
			]
		);
		if ( empty( $checked['type'] ) || empty( $checked['ext'] ) ) {
			return new \WP_Error( 'invalid_file_contents', 'Het bestandstype kon niet veilig worden vastgesteld.', [ 'status' => 400 ] );
		}

		$directory = $this->private_iva_directory();
		if ( is_wp_error( $directory ) ) {
			return $directory;
		}

		// Officiële IVA-certificaten (PDF, e-learning "Voor elkaar") hebben een
		// leesbare tekstlaag: naam + behaaldatum. Als de naam matcht met dit lid
		// en de datum recent is, keuren we automatisch goed — anders valt de
		// upload terug op de bestaande handmatige beoordeling.
		//
		// Dit gebeurt bewust vóór de eerste schrijfactie: gaat het parsen stuk,
		// dan is er nog niets om op te ruimen en ruimt PHP het tmp-bestand zelf op.
		$parsed = $checked['type'] === 'application/pdf' ? IvaCertificateParser::parse( $file['tmp_name'] ) : null;

		// Datum: gebruik wat het lid invult, anders vandaag. De datum op het
		// certificaat is betrouwbaarder dan handmatige invoer.
		$datum = (string) $request->get_param( 'datum_iva' );
		if ( $datum === '' || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ) {
			$datum = gmdate( 'Y-m-d' );
		}

		$auto_approved = false;
		if ( $parsed !== null ) {
			$datum         = $parsed['datum'];
			$auto_approved = IvaCertificateParser::should_auto_approve( $parsed, $person_id );
		}

		$filename = wp_unique_filename( $directory, 'iva-' . wp_generate_password( 32, false, false ) . '.' . $checked['ext'] );
		$path     = trailingslashit( $directory ) . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) {
			return new \WP_Error( 'private_upload_failed', 'Het certificaat kon niet veilig worden opgeslagen.', [ 'status' => 500 ] );
		}
		chmod( $path, 0640 );

		$attachment_id = wp_insert_attachment(
			[
				'post_mime_type' => $checked['type'],
				'post_title'     => 'IVA-certificaat ' . $person_id,
				'post_status'    => 'private',
				'post_parent'    => $person_id,
				'guid'           => $this->iva_certificate_url( $person_id ),
			]
		);
		if ( is_wp_error( $attachment_id ) ) {
			unlink( $path );
			return $attachment_id;
		}
		update_post_meta( $attachment_id, self::PRIVATE_PATH_META, $path );
		update_post_meta( $attachment_id, self::ORIGINAL_NAME_META, sanitize_file_name( $file['name'] ) );

		// Vanaf hier bestaan zowel het bestand als de attachment. Alles wat
		// hierna misgaat moet die twee opruimen, anders blijft er een wees
		// achter die aan geen enkel lid gekoppeld is — precies wat er in
		// 33.78.0 zestien keer gebeurde.
		//
		// Schrijf via native field zodat zowel de admin-form als de relationships-pipeline
		// het correct ophalen.
		try {
			$old_attachment_id = $this->iva_attachment_id( $person_id );
			\Rondo\Fields\Fields::update_for_post( $person_id, 'iva_certificaat', $attachment_id );
			if ( $old_attachment_id > 0 && $old_attachment_id !== $attachment_id ) {
				$this->delete_private_attachment( $old_attachment_id );
			}
			\Rondo\Fields\Fields::update_for_post( $person_id, 'datum_iva', $datum );
			\Rondo\Fields\Fields::update_for_post( $person_id, 'iva_approved', $auto_approved ? 1 : 0 );
			update_post_meta( $person_id, 'iva-approved', $auto_approved ? 1 : 0 );
		} catch ( \Throwable $e ) {
			$this->delete_private_attachment( $attachment_id );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'IVA upload: koppelen aan persoon ' . $person_id . ' mislukt: ' . $e->getMessage() );
			return new \WP_Error(
				'iva_save_failed',
				'Het certificaat kon niet aan je profiel worden gekoppeld. Probeer het opnieuw.',
				[ 'status' => 500 ]
			);
		}

		// De upload is op dit punt geslaagd en opgeslagen; een mislukte
		// notificatiemail mag dat niet alsnog als fout terugmelden.
		$notification = null;
		if ( ! $auto_approved ) {
			try {
				$notification = ( new IvaReviewNotificationEmailSender() )->send( $person_id );
			} catch ( \Throwable $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'IVA upload: beoordelingsmail voor persoon ' . $person_id . ' mislukt: ' . $e->getMessage() );
				$notification = [
					'status' => 'error',
					'sent'   => 0,
					'failed' => 0,
				];
			}
		}

		return rest_ensure_response(
			[
				'person_id'     => $person_id,
				'attachment'    => [
					'id'  => $attachment_id,
					'url' => $this->iva_certificate_url( $person_id ),
				],
				'datum_iva'     => $datum,
				'auto_verified' => $auto_approved,
				'status'        => IvaStatus::status( $person_id ),
				'expires_at'    => IvaStatus::expires_at( $person_id ),
				'notification'  => $notification,
			]
		);
	}

	/**
	 * GET /rondo/v1/iva/me — zonder admin-cap, geeft het lid zélf de status terug
	 * van zijn/haar eigen IVA-certificaat.
	 */
	public function get_my_iva( \WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$person_id = $user_id ? (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) : 0;
		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'no_linked_person', 'Geen gekoppeld lid-profiel.', [ 'status' => 404 ] );
		}

		$cert     = \Rondo\Fields\Fields::get_for_post( $person_id, 'iva_certificaat' );
		$cert_url = $cert ? $this->iva_certificate_url( $person_id ) : '';

		return rest_ensure_response(
			[
				'person_id'              => $person_id,
				'status'                 => IvaStatus::status( $person_id ),
				'expires_at'             => IvaStatus::expires_at( $person_id ),
				'datum_iva'              => \Rondo\Fields\Formatter::for_wire( 'person', [ 'datum_iva' => \Rondo\Fields\Fields::get_for_post( $person_id, 'datum_iva' ) ] )['datum_iva'],
				'iva_certificaat_url'    => $cert_url ? $this->sanitize_url( $cert_url ) : '',
				'iva_approved'           => (bool) get_post_meta( $person_id, 'iva-approved', true ),
				'needs_renewal_reminder' => IvaStatus::needs_renewal_reminder( $person_id ),
				'validity_years'         => IvaStatus::VALIDITY_YEARS,
			]
		);
	}

	public function get_iva_certificate( \WP_REST_Request $request ) {
		$person_id     = (int) $request->get_param( 'person_id' );
		$attachment_id = $this->iva_attachment_id( $person_id );
		$path          = $attachment_id ? (string) get_post_meta( $attachment_id, self::PRIVATE_PATH_META, true ) : '';

		if ( $path === '' || ! is_readable( $path ) ) {
			return new \WP_Error( 'iva_certificate_not_found', 'IVA-certificaat niet gevonden.', [ 'status' => 404 ] );
		}

		return rest_ensure_response(
			[
				'_rondo_private_file' => $path,
				'mime_type'           => get_post_mime_type( $attachment_id ) ?: 'application/octet-stream',
				'filename'            => (string) ( get_post_meta( $attachment_id, self::ORIGINAL_NAME_META, true ) ?: basename( $path ) ),
			]
		);
	}

	public function serve_private_iva_file( $served, $result, $request, $server ) {
		$data = $result instanceof \WP_REST_Response ? $result->get_data() : null;
		if ( ! is_array( $data ) || empty( $data['_rondo_private_file'] ) ) {
			return $served;
		}

		$path = (string) $data['_rondo_private_file'];
		if ( ! is_readable( $path ) ) {
			return $served;
		}

		header( 'Content-Type: ' . sanitize_mime_type( (string) $data['mime_type'] ) );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: inline; filename="' . sanitize_file_name( (string) $data['filename'] ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		readfile( $path );
		return true;
	}

	public function maybe_migrate_iva_files() {
		if ( (int) get_option( 'rondo_private_iva_version', 0 ) >= self::PRIVATE_IVA_VERSION ) {
			return;
		}

		$directory = $this->private_iva_directory();
		if ( is_wp_error( $directory ) ) {
			return;
		}

		$success = true;
		foreach ( $this->collect_iva_person_ids() as $person_id ) {
			$attachment_id = $this->iva_attachment_id( $person_id );
			if ( $attachment_id <= 0 || get_post_meta( $attachment_id, self::PRIVATE_PATH_META, true ) ) {
				continue;
			}

			$source = get_attached_file( $attachment_id );
			if ( ! $source || ! is_readable( $source ) ) {
				$success = false;
				continue;
			}

			$filename = wp_unique_filename( $directory, 'iva-' . wp_generate_password( 32, false, false ) . '.' . pathinfo( $source, PATHINFO_EXTENSION ) );
			$target   = trailingslashit( $directory ) . $filename;
			if ( ! rename( $source, $target ) && ( ! copy( $source, $target ) || ! unlink( $source ) ) ) {
				$success = false;
				continue;
			}
			chmod( $target, 0640 );

			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $metadata ) && ! empty( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size ) {
					$derived = trailingslashit( dirname( $source ) ) . $size['file'];
					if ( is_file( $derived ) ) {
						unlink( $derived );
					}
				}
			}

			update_post_meta( $attachment_id, self::PRIVATE_PATH_META, $target );
			update_post_meta( $attachment_id, self::ORIGINAL_NAME_META, basename( $source ) );
			delete_post_meta( $attachment_id, '_wp_attached_file' );
			delete_post_meta( $attachment_id, '_wp_attachment_metadata' );
			wp_update_post(
				[
					'ID'          => $attachment_id,
					'post_status' => 'private',
					'guid'        => $this->iva_certificate_url( $person_id ),
				]
				);
		}

		if ( $success ) {
			update_option( 'rondo_private_iva_version', self::PRIVATE_IVA_VERSION, false );
		}
	}

	private function private_iva_directory() {
		$directory = trailingslashit( dirname( ABSPATH ) ) . 'rondo-private/iva';
		if ( ! wp_mkdir_p( $directory ) ) {
			return new \WP_Error( 'private_directory_failed', 'De private IVA-map kon niet worden aangemaakt.', [ 'status' => 500 ] );
		}
		return $directory;
	}

	private function iva_attachment_id( int $person_id ): int {
		$value = \Rondo\Fields\Fields::get_for_post( $person_id, 'iva_certificaat' );
		if ( is_array( $value ) ) {
			$value = $value['ID'] ?? $value['id'] ?? 0;
		}
		return (int) $value;
	}

	private function iva_certificate_url( int $person_id ): string {
		return rest_url( 'rondo/v1/iva/' . $person_id . '/certificate' );
	}

	private function delete_private_attachment( int $attachment_id ): void {
		$path = (string) get_post_meta( $attachment_id, self::PRIVATE_PATH_META, true );
		if ( $path !== '' && is_file( $path ) ) {
			unlink( $path );
		}
		wp_delete_attachment( $attachment_id, true );
	}

	/**
	 * GET /rondo/v1/vog/me — geeft het lid zélf zijn VOG-status terug.
	 *
	 * VOG (Verklaring Omtrent Gedrag) is geldig 3 jaar vanaf `datum-vog`.
	 * Aanvraag en upload regelt de VOG-coördinator, niet het lid zelf —
	 * dit endpoint is read-only voor het lid.
	 */
	public function get_my_vog( \WP_REST_Request $request ) {
		$user_id   = get_current_user_id();
		$person_id = $user_id ? (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) : 0;
		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'no_linked_person', 'Geen gekoppeld lid-profiel.', [ 'status' => 404 ] );
		}

		$datum_vog              = (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'datum_vog' );
		$expires_at             = '';
		$status                 = 'missing';
		$needs_renewal_reminder = false;

		if ( $datum_vog !== '' ) {
			$expiry_ts = strtotime( $datum_vog . ' +3 years' );
			if ( $expiry_ts !== false ) {
				$expires_at = gmdate( 'Y-m-d', $expiry_ts );
				$now_ts     = strtotime( gmdate( 'Y-m-d' ) );
				if ( $now_ts !== false ) {
					$status = $expiry_ts >= $now_ts ? 'valid' : 'expired';
					if ( $status === 'valid' ) {
						$days_remaining         = (int) floor( ( $expiry_ts - $now_ts ) / DAY_IN_SECONDS );
						$needs_renewal_reminder = $days_remaining <= 90;
					}
				}
			}
		}

		return rest_ensure_response(
			[
				'person_id'              => $person_id,
				'status'                 => $status,
				'datum_vog'              => \Rondo\Fields\Formatter::for_wire( 'person', [ 'datum_vog' => $datum_vog ] )['datum_vog'],
				'expires_at'             => $expires_at,
				'needs_renewal_reminder' => $needs_renewal_reminder,
				'validity_years'         => 3,
			]
		);
	}

	public function approve_iva( \WP_REST_Request $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$approve   = filter_var( $request->get_param( 'approve' ), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		if ( $approve === null ) {
			$approve = true;
		}

		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Invalid person ID.', [ 'status' => 404 ] );
		}

		$was_approved = IvaStatus::is_approved( $person_id );
		update_post_meta( $person_id, 'iva-approved', $approve ? 1 : 0 );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'iva_approved', $approve ? 1 : 0 );

		$notification = [ 'status' => 'not_applicable' ];
		if ( $approve && ! $was_approved && IvaStatus::is_valid( $person_id ) ) {
			$notification = ( new IvaApprovalEmailSender() )->send( $person_id );
		}

		return rest_ensure_response(
			[
				'person_id'    => $person_id,
				'approved'     => $approve,
				'status'       => IvaStatus::status( $person_id ),
				'expires_at'   => IvaStatus::expires_at( $person_id ),
				'notification' => $notification,
			]
		);
	}

	/**
	 * GET /rondo/v1/volunteer-obligations
	 *
	 * Returns every eligible unit for the season, augmented with
	 * `completed_count`, `pending_count`, `no_show_count`, and a `status` bucket.
	 * Used by the Vrijwilligers dashboard and (later) the member-facing surface.
	 */
	public function get_obligations( \WP_REST_Request $request ) {
		$season = $request->get_param( 'season' ) ?: SeasonKey::current();

		$eligibility = new VolunteerEligibilityService();
		$calculator  = new VolunteerObligationCalculator();

		$units     = $eligibility->get_eligible_units( $season );
		$decorated = $calculator->decorate_units( $units, $season );
		$aggregate = $calculator->aggregate( $decorated );

		return rest_ensure_response(
			[
				'season'    => $season,
				'units'     => $decorated,
				'aggregate' => $aggregate,
			]
		);
	}

	/**
	 * GET /rondo/v1/volunteer-statistics
	 *
	 * Returns one privacy-safe aggregate payload for the statistics page. The
	 * browser receives counts and shift summaries, never person identifiers.
	 */
	public function get_statistics( \WP_REST_Request $request ) {
		$season = $request->get_param( 'season' ) ?: SeasonKey::current();

		return rest_ensure_response( ( new VolunteerStatistics() )->for_season( $season ) );
	}

	/**
	 * POST /rondo/v1/shifts/{id}/no-show
	 *
	 * Marks a person as a no-show on a given shift (or reverts the mark when
	 * `revert=true`). Triggers the `rondo_volunteer_no_show_marked` action so the
	 * boete pipeline can fire — see ShiftScheduler.
	 */
	public function mark_no_show( \WP_REST_Request $request ) {
		$shift_id  = (int) $request->get_param( 'id' );
		$person_id = (int) $request->get_param( 'person_id' );
		$revert    = filter_var( $request->get_param( 'revert' ), FILTER_VALIDATE_BOOLEAN );

		if ( $revert ) {
			$ok = VolunteerObligationCalculator::unmark_no_show( $shift_id, $person_id );
			return rest_ensure_response(
				[
					'shift_id'  => $shift_id,
					'person_id' => $person_id,
					'reverted'  => (bool) $ok,
				]
			);
		}

		$result = VolunteerObligationCalculator::mark_no_show( $shift_id, $person_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		/**
		 * Fires after a person is marked no-show on a shift.
		 * Consumed by ShiftScheduler → VolunteerFineGenerator.
		 */
		do_action( 'rondo_volunteer_no_show_marked', $shift_id, $person_id, get_current_user_id() );

		return rest_ensure_response(
			[
				'shift_id'  => $shift_id,
				'person_id' => $person_id,
				'marked'    => true,
			]
		);
	}

	public function get_iva_status( \WP_REST_Request $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Invalid person ID.', [ 'status' => 404 ] );
		}

		return rest_ensure_response(
			[
				'person_id'              => $person_id,
				'status'                 => IvaStatus::status( $person_id ),
				'expires_at'             => IvaStatus::expires_at( $person_id ),
				'needs_renewal_reminder' => IvaStatus::needs_renewal_reminder( $person_id ),
				'validity_years'         => IvaStatus::VALIDITY_YEARS,
			]
		);
	}

	/**
	 * GET /rondo/v1/volunteer-eligibility
	 *
	 * Returns the derived eligible units for the requested season.
	 * If `person_id` is given, returns that person's units — a playing parent has two.
	 *
	 * Response shape:
	 *   {
	 *     season: "2026-2027",
	 *     units: [ { unit_id, kind, person_ids, trigger_person_ids, required_count, ... }, ... ],
	 *     total_units: 312,
	 *   }
	 */
	public function get_eligibility( \WP_REST_Request $request ) {
		$season       = $request->get_param( 'season' ) ?: SeasonKey::current();
		$with_persons = filter_var( $request->get_param( 'with_persons' ), FILTER_VALIDATE_BOOLEAN );
		$person_id    = (int) $request->get_param( 'person_id' );

		$service = new VolunteerEligibilityService();

		if ( $person_id > 0 ) {
			$units = $service->get_eligible_units_for_person( $person_id, $season );
			if ( $with_persons ) {
				$units = array_map( fn( $unit ) => $this->expand_unit( $unit, $season ), $units );
			}
			return rest_ensure_response(
				[
					'season'      => $season,
					'units'       => $units,
					'total_units' => count( $units ),
				]
			);
		}

		$view  = $service->get_eligibility_view( $season );
		$units = $view['units'];

		if ( $with_persons ) {
			$units = array_map(
				fn( $unit ) => $this->expand_unit( $unit, $season ),
				$units
			);
		}

		// Cheap-ish to add: a count of suspect relationships so the dashboard
		// can render the Datakwaliteit-kaart in one round-trip. The drill-down
		// page hits /rondo/v1/relationship-quality directly for full detail.
		$diagnostics                          = $view['diagnostics'];
		$diagnostics['suspect_relationships'] = count(
			( new RelationshipQualityChecker() )->find_suspect_pairs()
		);

		// Only ledenadministratie/admins see the no-email chase card (and the
		// drill-down behind it), so only compute the count for them — it keeps a
		// membership metric out of the general Vrijwilligers view and skips the
		// extra scan for everyone else.
		if ( $this->check_ledenadministratie_permission() ) {
			$diagnostics['no_email'] = count( $this->get_no_email_person_ids() );
		}

		return rest_ensure_response(
			[
				'season'              => $season,
				'units'               => $units,
				'total_units'         => count( $units ),
				'rondo_account_count' => $this->get_rondo_account_count(),
				'diagnostics'         => $diagnostics,
				'shift_capacity'      => $this->get_shift_capacity_stats( $season ),
			]
		);
	}

	/**
	 * Count WordPress users linked to a Rondo person record.
	 */
	private function get_rondo_account_count(): int {
		$query = new \WP_User_Query(
			[
				'meta_key'     => 'rondo_linked_person_id',
				'meta_compare' => '!=',
				'meta_value'   => '',
				'fields'       => 'ID',
				'number'       => 1,
				'count_total'  => true,
			]
		);

		return (int) $query->get_total();
	}

	/**
	 * Count scheduled volunteer capacity and assignments for a sports season.
	 *
	 * Each capacity slot counts as one service opportunity. Cancelled shifts do
	 * not contribute; completed shifts remain part of the season total.
	 *
	 * @return array{total_slots: int, assigned_slots: int}
	 */
	private function get_shift_capacity_stats( string $season ): array {
		if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
			$season = SeasonKey::current();
			preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches );
		}

		$season_start = $matches[1] . '-07-01 00:00:00';
		$season_end   = $matches[2] . '-06-30 23:59:59';
		$shift_ids    = get_posts(
			[
				'post_type'        => 'dienst_shift',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
				'meta_query'       => [
					[
						'key'     => 'start_datetime',
						'value'   => [ $season_start, $season_end ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
				],
			]
		);

		$stats = [
			'total_slots'    => 0,
			'assigned_slots' => 0,
		];
		foreach ( $shift_ids as $shift_id ) {
			if ( (string) get_post_meta( $shift_id, 'status', true ) === 'geannuleerd' ) {
				continue;
			}

			$assigned                 = ShiftAssignments::person_ids( $shift_id );
			$stats['total_slots']    += max( 1, (int) get_post_meta( $shift_id, 'capacity', true ) );
			$stats['assigned_slots'] += count( $assigned );
		}

		return $stats;
	}

	/**
	 * GET /rondo/v1/volunteer-exemption/{person_id}
	 *
	 * Returns the exemption reason for a single person, or null.
	 */
	public function get_exemption( \WP_REST_Request $request ) {
		$person_id = (int) $request->get_param( 'person_id' );
		$season    = $request->get_param( 'season' ) ?: SeasonKey::current();

		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Persoon niet gevonden.', [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->prepare_exemption_response( $person_id, $season ) );
	}

	/**
	 * POST/PUT/PATCH /rondo/v1/volunteer-exemption/{person_id}
	 *
	 * Stores the narrowly scoped manual exemption fields. This intentionally
	 * bypasses general person-edit permissions: the `vrijwilligers` capability
	 * owns this policy decision, but does not gain access to unrelated person data.
	 */
	public function update_manual_exemption( \WP_REST_Request $request ) {
		$person_id = (int) $request->get_param( 'person_id' );

		if ( $person_id <= 0 || get_post_type( $person_id ) !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Persoon niet gevonden.', [ 'status' => 404 ] );
		}

		$enabled = (bool) $request->get_param( 'enabled' );
		$reason  = $enabled ? (string) $request->get_param( 'reason' ) : '';
		$season  = $enabled ? trim( (string) $request->get_param( 'season' ) ) : '';

		Fields::update_for_post( $person_id, 'vrijgesteld_handmatig', $enabled ? 1 : 0 );
		Fields::update_for_post( $person_id, 'vrijstelling_reden', $reason );
		Fields::update_for_post( $person_id, 'vrijstelling_seizoen', $season );

		VolunteerEligibilityService::invalidate_cache();
		RelationshipQualityChecker::invalidate_cache();
		VolunteerObligationCalculator::invalidate_cache();

		return rest_ensure_response( $this->prepare_exemption_response( $person_id, SeasonKey::current() ) );
	}

	/**
	 * Accept an empty season for an ongoing exemption, or a consecutive
	 * YYYY-YYYY sports season.
	 */
	public function validate_optional_season( $value ): bool {
		$season = trim( (string) $value );
		if ( $season === '' ) {
			return true;
		}

		if ( ! preg_match( '/^(\d{4})-(\d{4})$/', $season, $matches ) ) {
			return false;
		}

		return (int) $matches[2] === (int) $matches[1] + 1;
	}

	/**
	 * Build the shared read/write response for one person's exemption state.
	 */
	private function prepare_exemption_response( int $person_id, string $season ): array {
		$reason = VolunteerExemptionResolver::resolve( $person_id, $season );

		return [
			'person_id'    => $person_id,
			'person_name'  => get_the_title( $person_id ),
			'season'       => $season,
			'is_exempt'    => $reason !== null,
			'reason'       => $reason,
			'reason_label' => $reason ? VolunteerExemptionResolver::reason_label( $reason ) : null,
			'manual'       => [
				'enabled' => rest_sanitize_boolean( get_post_meta( $person_id, 'vrijgesteld_handmatig', true ) ),
				'reason'  => (string) get_post_meta( $person_id, 'vrijstelling_reden', true ),
				'season'  => (string) get_post_meta( $person_id, 'vrijstelling_seizoen', true ),
			],
		];
	}

	/**
	 * Permission gate for the data-quality drill-down.
	 *
	 * Volunteer diagnostics contain club-wide person records. The `no_email`
	 * category is additionally restricted to membership administration.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return bool
	 */
	public function check_data_quality_permission( $request ) {
		if ( $request->get_param( 'category' ) === 'no_email' ) {
			return $this->check_ledenadministratie_permission();
		}
		return $this->check_vrijwilligers_permission();
	}

	/**
	 * GET /rondo/v1/volunteer-data-quality/{category}
	 *
	 * Drill-down for the Datakwaliteit-kaart on the dashboard. Returns the
	 * personen behind each category so admins can fix the underlying records.
	 *
	 *   - orphan                 → JO16- spelers zonder ouder-relatie én zonder volwassen huisgenoot.
	 *   - address_fallback       → spelers + ouders waar het gezin alleen via adres-overeenkomst is samengesteld.
	 *   - missing_leeftijdsgroep → spelende leden zonder leeftijdsgroep meta.
	 *   - no_email               → actieve (niet-former) leden zonder geldig e-mailadres — kunnen niet activeren.
	 */
	public function get_data_quality_persons( \WP_REST_Request $request ) {
		$category = sanitize_key( (string) $request->get_param( 'category' ) );
		$season   = $request->get_param( 'season' ) ?: SeasonKey::current();

		$service = new VolunteerEligibilityService();

		switch ( $category ) {
			case 'orphan':
				$ids = $service->get_orphan_youth_ids( $season );
				break;
			case 'address_fallback':
				$ids = $service->get_address_fallback_person_ids( $season );
				break;
			case 'missing_leeftijdsgroep':
				$ids = $service->get_skipped_no_leeftijdsgroep_ids();
				break;
			case 'no_email':
				$ids = $this->get_no_email_person_ids();
				break;
			default:
				return new \WP_Error( 'invalid_category', 'Unknown data-quality category.', [ 'status' => 400 ] );
		}

		$persons = [];
		foreach ( $ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post || $post->post_type !== 'person' ) {
				continue;
			}

			$age_group = (string) \Rondo\Fields\Fields::get_for_post( $pid, 'leeftijdsgroep' );
			$addresses = \Rondo\Fields\Fields::get_for_post( $pid, 'addresses' );
			$primary   = is_array( $addresses ) && ! empty( $addresses ) ? $addresses[0] : null;
			$rels      = \Rondo\Fields\Fields::get_for_post( $pid, 'relationships' );
			$rel_count = is_array( $rels ) ? count( $rels ) : 0;

			$persons[] = [
				'id'                  => (int) $pid,
				'name'                => $this->sanitize_text( $post->post_title ),
				'first_name'          => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $pid, 'first_name' ) ),
				'infix'               => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $pid, 'infix' ) ),
				'last_name'           => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $pid, 'last_name' ) ),
				'thumbnail'           => $this->sanitize_url( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ),
				'knvb_id'             => (string) get_post_meta( $pid, 'knvb-id', true ),
				'leeftijdsgroep'      => $age_group,
				'address'             => $primary ? trim(
					(string) ( $primary['street'] ?? '' )
					. ' ' . (string) ( $primary['house_number'] ?? '' )
					. (string) ( $primary['house_number_addition'] ?? '' )
				) : '',
				'postal_code'         => $primary ? (string) ( $primary['postal_code'] ?? '' ) : '',
				'city'                => $primary ? (string) ( $primary['city'] ?? '' ) : '',
				'relationships_count' => $rel_count,
			];
		}

		// Sort by surname, then first name, for a stable Dutch member list.
		usort(
			$persons,
			static function ( array $a, array $b ): int {
				$last_name_compare = strcasecmp( $a['last_name'], $b['last_name'] );
				return $last_name_compare !== 0 ? $last_name_compare : strcasecmp( $a['first_name'], $b['first_name'] );
			}
		);

		return rest_ensure_response(
			[
				'season'   => $season,
				'category' => $category,
				'count'    => count( $persons ),
				'persons'  => $persons,
			]
		);
	}

	/**
	 * Active (non-former) members with no valid e-mail address on file.
	 *
	 * These members can't be reached by the self-service activation flow
	 * (/activeren) until someone collects an address, so ledenadministratie
	 * needs to see and chase them. A person qualifies when neither `email_1`
	 * nor `email_2` is a valid address and they are not marked `former_member`.
	 *
	 * Full scan with direct post_meta reads is cheaper than a meta_query, and
	 * validity has to be checked in PHP anyway.
	 *
	 * @return int[] Person post IDs.
	 */
	private function get_no_email_person_ids(): array {
		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
			]
		);

		$ids = [];
		foreach ( $query->posts as $pid ) {
			$pid = (int) $pid;

			$former = get_post_meta( $pid, 'former_member', true );
			if ( $former === '1' || $former === 1 || $former === true ) {
				continue;
			}

			$email_1 = (string) get_post_meta( $pid, 'email_1', true );
			$email_2 = (string) get_post_meta( $pid, 'email_2', true );
			if ( is_email( $email_1 ) || is_email( $email_2 ) ) {
				continue;
			}

			$ids[] = $pid;
		}

		return $ids;
	}

	/**
	 * POST /rondo/v1/volunteer-cache/refresh — wipt de eligibility + relationship
	 * quality transients zodat de volgende dashboard-call vers herrekent.
	 */
	public function refresh_cache( \WP_REST_Request $request ) {
		VolunteerEligibilityService::invalidate_cache();
		RelationshipQualityChecker::invalidate_cache();
		return rest_ensure_response( [ 'refreshed' => true ] );
	}

	/**
	 * GET /rondo/v1/relationship-quality
	 *
	 * Lijst van verdachte ouder/kind/sibling-paren — afgewogen op
	 * leeftijdsverschil. Gebruikt door de Datakwaliteit-kaart op het
	 * Vrijwilligers-dashboard om data-issues zichtbaar te maken.
	 */
	public function get_suspect_relationships( \WP_REST_Request $request ) {
		$checker = new RelationshipQualityChecker();
		$pairs   = $checker->find_suspect_pairs();

		// Voeg thumbnails toe zodat het frontend de personen herkenbaar kan tonen.
		foreach ( $pairs as &$pair ) {
			$pair['person_a_thumbnail'] = $this->sanitize_url( get_the_post_thumbnail_url( (int) $pair['person_a_id'], 'thumbnail' ) );
			$pair['person_b_thumbnail'] = $this->sanitize_url( get_the_post_thumbnail_url( (int) $pair['person_b_id'], 'thumbnail' ) );
			$pair['person_a_name']      = $this->sanitize_text( $pair['person_a_name'] );
			$pair['person_b_name']      = $this->sanitize_text( $pair['person_b_name'] );
		}
		unset( $pair );

		return rest_ensure_response(
			[
				'count' => count( $pairs ),
				'pairs' => $pairs,
			]
		);
	}

	/**
	 * GET /rondo/v1/managed-commissies
	 *
	 * Public list of commissie post IDs that are managed inside Rondo (pool
	 * commissies seeded by VolunteerSeeder + any others tagged with the seed key).
	 * Consumed by rondo-sync to skip these during the untracked-commissie cleanup,
	 * so a Sportlink sync never deletes a Rondo-managed pool.
	 *
	 * No auth: read-only and the IDs themselves are not sensitive.
	 */
	public function get_managed_commissies( \WP_REST_Request $request ) {
		$ids   = VolunteerSeeder::get_managed_commissie_ids();
		$pools = VolunteerSeeder::get_pool_commissies();

		$detail = [];
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post || $post->post_type !== 'commissie' ) {
				continue;
			}
			$slug     = array_search( $id, $pools, true ) ?: '';
			$detail[] = [
				'id'    => (int) $id,
				'slug'  => is_string( $slug ) ? $slug : '',
				'title' => $this->sanitize_text( $post->post_title ),
			];
		}

		return rest_ensure_response(
			[
				'ids'        => $ids,
				'pools'      => $pools,
				'commissies' => $detail,
			]
		);
	}

	/**
	 * Expand a unit with person summaries (name/thumbnail/exemption reason).
	 * Used when the client requests `with_persons=true`.
	 */
	private function expand_unit( array $unit, string $season ): array {
		$persons = [];
		foreach ( $unit['person_ids'] as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) {
				continue;
			}
			$reason    = VolunteerExemptionResolver::resolve( (int) $pid, $season );
			$persons[] = [
				'id'           => (int) $pid,
				'name'         => $this->sanitize_text( $post->post_title ),
				'first_name'   => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $pid, 'first_name' ) ),
				'infix'        => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $pid, 'infix' ) ),
				'last_name'    => $this->sanitize_text( \Rondo\Fields\Fields::get_for_post( $pid, 'last_name' ) ),
				'thumbnail'    => $this->sanitize_url( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ),
				'is_trigger'   => in_array( (int) $pid, $unit['trigger_person_ids'], true ),
				'is_exempt'    => $reason !== null,
				'reason'       => $reason,
				'reason_label' => $reason ? VolunteerExemptionResolver::reason_label( $reason ) : null,
			];
		}
		$unit['persons'] = $persons;
		return $unit;
	}
}
