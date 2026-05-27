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
use Rondo\Volunteer\IvaStatus;
use Rondo\Volunteer\RelationshipQualityChecker;
use Rondo\Volunteer\VolunteerCacheInvalidator;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerExemptionResolver;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Rondo\Volunteer\VolunteerSeeder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Volunteer extends Base {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/volunteer-eligibility',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_eligibility' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
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
				'permission_callback' => [ $this, 'check_user_approved' ],
				'args'                => [
					'category' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function ( $param ) {
							return in_array(
								$param,
								[ 'orphan', 'address_fallback', 'missing_leeftijdsgroep', 'non_paying' ],
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
				'permission_callback' => [ $this, 'check_user_approved' ],
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
				'permission_callback' => [ $this, 'check_user_approved' ],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/volunteer-exemption/(?P<person_id>\d+)',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_exemption' ],
				'permission_callback' => [ $this, 'check_user_approved' ],
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
			]
		);

		// IVA upload — door het lid zélf, via /vrijwillig/profiel. Schrijft het
		// uploadbestand naar de attachment-library, koppelt het aan het ACF veld
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
				'permission_callback' => [ $this, 'check_user_approved' ],
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
				'permission_callback' => [ $this, 'check_user_approved' ],
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
			$cert_field = get_field( 'iva-certificaat', $post->ID );
			$cert_url   = '';
			if ( is_array( $cert_field ) ) {
				$cert_url = $cert_field['url'] ?? '';
			} elseif ( is_string( $cert_field ) ) {
				$cert_url = $cert_field;
			}

			$people[] = [
				'id'                => $post->ID,
				'name'              => $this->sanitize_text( $post->post_title ),
				'thumbnail'         => $this->sanitize_url( get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ),
				'datum_iva'         => (string) get_field( 'datum-iva', $post->ID ),
				'iva_certificaat'   => $cert_url ? $this->sanitize_url( $cert_url ) : '',
				'iva_approved'      => (bool) get_post_meta( $post->ID, 'iva-approved', true ),
				'status'            => IvaStatus::status( $post->ID ),
				'expires_at'        => IvaStatus::expires_at( $post->ID ),
			];
		}

		return rest_ensure_response( [ 'people' => $people ] );
	}

	/**
	 * Find the post IDs of every person with any IVA-relevant meta set.
	 *
	 * Direct WPDB query — meta-OR via WP_Query is awkward and we want the
	 * smallest possible read for an admin page that's loaded rarely.
	 */
	private function collect_iva_person_ids(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			"SELECT DISTINCT pm.post_id
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = 'person'
			   AND p.post_status = 'publish'
			   AND pm.meta_key IN ('datum-iva', 'iva-certificaat', 'iva-approved')
			   AND pm.meta_value <> ''
			   AND pm.meta_value <> '0'"
		);

		return array_values( array_unique( array_map( 'intval', (array) $ids ) ) );
	}

	/**
	 * POST /rondo/v1/iva/upload
	 *
	 * Lid-zelf upload. Schrijft het bestand naar de attachment-library,
	 * koppelt het aan het ACF veld iva-certificaat van de gelinkte persoon,
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

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$ext  = pathinfo( $file['name'], PATHINFO_EXTENSION );
		$name = sanitize_file_name( 'iva-certificaat-' . $person_id . '.' . $ext );

		$_FILES['certificaat']         = $file;
		$_FILES['certificaat']['name'] = $name;

		$attachment_id = media_handle_upload( 'certificaat', $person_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Datum: gebruik wat het lid invult, anders vandaag.
		$datum = (string) $request->get_param( 'datum_iva' );
		if ( $datum === '' || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ) {
			$datum = gmdate( 'Y-m-d' );
		}

		// Schrijf via ACF zodat zowel de admin-form als de relationships-pipeline
		// het correct ophalen.
		update_field( 'iva-certificaat', $attachment_id, $person_id );
		update_field( 'datum-iva', $datum, $person_id );
		update_field( 'iva-approved', 0, $person_id );
		update_post_meta( $person_id, 'iva-approved', 0 );

		return rest_ensure_response(
			[
				'person_id'   => $person_id,
				'attachment'  => [
					'id'  => $attachment_id,
					'url' => wp_get_attachment_url( $attachment_id ),
				],
				'datum_iva'   => $datum,
				'status'      => IvaStatus::status( $person_id ),
				'expires_at'  => IvaStatus::expires_at( $person_id ),
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

		$cert     = get_field( 'iva-certificaat', $person_id );
		$cert_url = '';
		if ( is_array( $cert ) ) {
			$cert_url = $cert['url'] ?? '';
		} elseif ( is_numeric( $cert ) ) {
			$cert_url = (string) wp_get_attachment_url( (int) $cert );
		} elseif ( is_string( $cert ) ) {
			$cert_url = $cert;
		}

		return rest_ensure_response(
			[
				'person_id'             => $person_id,
				'status'                => IvaStatus::status( $person_id ),
				'expires_at'            => IvaStatus::expires_at( $person_id ),
				'datum_iva'             => (string) get_field( 'datum-iva', $person_id ),
				'iva_certificaat_url'   => $cert_url ? $this->sanitize_url( $cert_url ) : '',
				'iva_approved'          => (bool) get_post_meta( $person_id, 'iva-approved', true ),
				'needs_renewal_reminder' => IvaStatus::needs_renewal_reminder( $person_id ),
				'validity_years'        => IvaStatus::VALIDITY_YEARS,
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

		update_post_meta( $person_id, 'iva-approved', $approve ? 1 : 0 );
		update_field( 'iva-approved', $approve ? 1 : 0, $person_id );

		return rest_ensure_response(
			[
				'person_id' => $person_id,
				'approved'  => $approve,
				'status'    => IvaStatus::status( $person_id ),
				'expires_at' => IvaStatus::expires_at( $person_id ),
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

		$units      = $eligibility->get_eligible_units( $season );
		$decorated  = $calculator->decorate_units( $units, $season );
		$aggregate  = $calculator->aggregate( $decorated );

		return rest_ensure_response(
			[
				'season'    => $season,
				'units'     => $decorated,
				'aggregate' => $aggregate,
			]
		);
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
				'person_id'             => $person_id,
				'status'                => IvaStatus::status( $person_id ),
				'expires_at'            => IvaStatus::expires_at( $person_id ),
				'needs_renewal_reminder' => IvaStatus::needs_renewal_reminder( $person_id ),
				'validity_years'        => IvaStatus::VALIDITY_YEARS,
			]
		);
	}

	/**
	 * GET /rondo/v1/volunteer-eligibility
	 *
	 * Returns the derived eligible units for the requested season.
	 * If `person_id` is given, returns just that person's unit (or 404 if none).
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
			$unit = $service->get_eligible_unit_for_person( $person_id, $season );
			if ( $unit === null ) {
				return rest_ensure_response(
					[
						'season' => $season,
						'unit'   => null,
					]
				);
			}
			return rest_ensure_response(
				[
					'season' => $season,
					'unit'   => $with_persons ? $this->expand_unit( $unit, $season ) : $unit,
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

		return rest_ensure_response(
			[
				'season'      => $season,
				'units'       => $units,
				'total_units' => count( $units ),
				'diagnostics' => $diagnostics,
			]
		);
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
			return new \WP_Error( 'invalid_person', 'Invalid person ID.', [ 'status' => 404 ] );
		}

		$reason = VolunteerExemptionResolver::resolve( $person_id, $season );

		return rest_ensure_response(
			[
				'person_id'    => $person_id,
				'season'       => $season,
				'is_exempt'    => $reason !== null,
				'reason'       => $reason,
				'reason_label' => $reason ? VolunteerExemptionResolver::reason_label( $reason ) : null,
			]
		);
	}

	/**
	 * GET /rondo/v1/volunteer-data-quality/{category}
	 *
	 * Drill-down for the Datakwaliteit-kaart on the dashboard. Returns the
	 * personen behind each category so admins can fix the underlying records.
	 *
	 *   - orphan                 → JO16- spelers zonder ouder-relatie én zonder volwassen huisgenoot.
	 *   - address_fallback       → spelers + ouders waar het gezin alleen via adres-overeenkomst is samengesteld.
	 *   - missing_leeftijdsgroep → personen zonder leeftijdsgroep meta.
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
			case 'non_paying':
				$ids = $service->get_non_paying_ids();
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

			$age_group = (string) get_field( 'leeftijdsgroep', $pid );
			$addresses = get_field( 'addresses', $pid );
			$primary   = is_array( $addresses ) && ! empty( $addresses ) ? $addresses[0] : null;
			$rels      = get_field( 'relationships', $pid );
			$rel_count = is_array( $rels ) ? count( $rels ) : 0;

			$persons[] = [
				'id'                 => (int) $pid,
				'name'               => $this->sanitize_text( $post->post_title ),
				'thumbnail'          => $this->sanitize_url( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ),
				'leeftijdsgroep'     => $age_group,
				'address'            => $primary ? trim(
					(string) ( $primary['street'] ?? '' )
					. ' ' . (string) ( $primary['house_number'] ?? '' )
					. (string) ( $primary['house_number_addition'] ?? '' )
				) : '',
				'postal_code'        => $primary ? (string) ( $primary['postal_code'] ?? '' ) : '',
				'city'               => $primary ? (string) ( $primary['city'] ?? '' ) : '',
				'relationships_count' => $rel_count,
			];
		}

		// Sort by name for a stable display.
		usort( $persons, fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

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
			$slug = array_search( $id, $pools, true ) ?: '';
			$detail[] = [
				'id'        => (int) $id,
				'slug'      => is_string( $slug ) ? $slug : '',
				'title'     => $this->sanitize_text( $post->post_title ),
			];
		}

		return rest_ensure_response(
			[
				'ids'         => $ids,
				'pools'       => $pools,
				'commissies'  => $detail,
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
			$reason = VolunteerExemptionResolver::resolve( (int) $pid, $season );
			$persons[] = [
				'id'           => (int) $pid,
				'name'         => $this->sanitize_text( $post->post_title ),
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
