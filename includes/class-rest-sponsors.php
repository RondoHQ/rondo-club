<?php
/**
 * Sponsor-company REST API.
 */

namespace Rondo\REST;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\Sponsors\Relations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Manage sponsors without exposing their private CPT through wp/v2. */
final class Sponsors extends Base {
	private const ROLES                = [ 'businessclub', 'awc_sponsor' ];
	private const TYPES                = [ 'organization', 'person' ];
	private const CLUB_TV_PRIORITIES   = [ 0, 1, 2, 3 ];
	private const CLUB_TV_ALWAYS_LIMIT = 6;

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_prepare_person', [ $this, 'add_sponsor_relationships_to_person' ], 20, 3 );
	}

	public function register_routes(): void {
		$manage = [ $this, 'can_manage' ];

		register_rest_route(
			'rondo/v1',
			'/sponsors',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'list_sponsors' ],
					'permission_callback' => $manage,
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_sponsor' ],
					'permission_callback' => $manage,
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/sponsors/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_sponsor' ],
					'permission_callback' => $manage,
				],
				[
					'methods'             => [ 'POST', 'PUT', 'PATCH' ],
					'callback'            => [ $this, 'update_sponsor' ],
					'permission_callback' => $manage,
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'archive_sponsor' ],
					'permission_callback' => $manage,
				],
			]
		);

		register_rest_route(
			'rondo/v1',
			'/sponsors/(?P<id>\d+)/contacts',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_contact' ],
				'permission_callback' => $manage,
			]
		);

		register_rest_route(
			'rondo/v1',
			'/sponsors/(?P<id>\d+)/logo/upload',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_logo' ],
				'permission_callback' => $manage,
			]
		);

		register_rest_route(
			'rondo/v1',
			'/sponsor-person-options',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'person_options' ],
				'permission_callback' => $manage,
			]
		);
	}

	public function can_manage(): bool {
		return AccessControl::can_manage_sponsors();
	}

	public function list_sponsors( \WP_REST_Request $request ) {
		$page       = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per_page   = min( 100, max( 1, absint( $request->get_param( 'per_page' ) ?: 50 ) ) );
		$status     = sanitize_key( (string) ( $request->get_param( 'status' ) ?: 'active' ) );
		$role       = sanitize_key( (string) $request->get_param( 'sponsor_role' ) );
		$args       = [
			'post_type'        => 'rondo_sponsor',
			'post_status'      => $status === 'all' ? [ 'publish', 'draft' ] : ( $status === 'archived' ? 'draft' : 'publish' ),
			'posts_per_page'   => -1,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
		];
		$meta_query = [];
		if ( in_array( $role, self::ROLES, true ) ) {
			$meta_query[] = [
				'key'   => 'sponsor_role',
				'value' => $role,
			];
		}
		if ( $request->get_param( 'sponsit_contact_id' ) ) {
			$meta_query[] = [
				'key'   => 'sponsit_contact_id',
				'value' => sanitize_text_field( (string) $request->get_param( 'sponsit_contact_id' ) ),
			];
		}
		$sponsor_type = sanitize_key( (string) $request->get_param( 'sponsor_type' ) );
		if ( in_array( $sponsor_type, self::TYPES, true ) ) {
			$meta_query[] = $sponsor_type === 'organization'
				? [
					'relation' => 'OR',
					[
						'key'   => 'sponsor_type',
						'value' => 'organization',
					],
					[
						'key'     => 'sponsor_type',
						'compare' => 'NOT EXISTS',
					],
				]
				: [
					'key'   => 'sponsor_type',
					'value' => 'person',
				];
		}
		$logo = sanitize_key( (string) $request->get_param( 'logo' ) );
		if ( in_array( $logo, [ 'present', 'missing' ], true ) ) {
			$meta_query[] = [
				'key'     => '_thumbnail_id',
				'compare' => $logo === 'present' ? 'EXISTS' : 'NOT EXISTS',
			];
		}
		if ( $meta_query ) {
			$args['meta_query'] = $meta_query;
		}

		$query  = new \WP_Query( $args );
		$items  = array_map( fn( \WP_Post $post ): array => $this->format_sponsor( $post ), $query->posts );
		$search = strtolower( trim( sanitize_text_field( (string) $request->get_param( 'search' ) ) ) );
		if ( $search !== '' ) {
			$items = array_values(
				array_filter(
					$items,
					static function ( array $item ) use ( $search ): bool {
						$haystack = [ $item['title'] ];
						foreach ( $item['fields']['contacts'] ?? [] as $contact ) {
							$haystack[] = (string) ( $contact['person_name'] ?? '' );
						}
						return str_contains( strtolower( implode( ' ', $haystack ) ), $search );
					}
				)
			);
		}
		$total       = count( $items );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$items       = array_slice( $items, ( $page - 1 ) * $per_page, $per_page );

		return rest_ensure_response(
			[
				'items'       => $items,
				'total'       => $total,
				'page'        => $page,
				'total_pages' => $total_pages,
			]
		);
	}

	public function get_sponsor( \WP_REST_Request $request ) {
		$post = $this->sponsor_post( absint( $request['id'] ) );
		return is_wp_error( $post ) ? $post : rest_ensure_response( $this->format_sponsor( $post ) );
	}

	public function create_sponsor( \WP_REST_Request $request ) {
		$payload = $request->get_json_params() ?: $request->get_params();
		$title   = sanitize_text_field( (string) ( $payload['title'] ?? '' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'rondo_sponsor_name_required', 'Vul een sponsornaam in.', [ 'status' => 400 ] );
		}

		$fields = $this->sanitize_fields( (array) ( $payload['fields'] ?? [] ), 0, true );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$logo_validation = $this->validate_logo_attachment( $payload['logo_attachment_id'] ?? 0 );
		if ( is_wp_error( $logo_validation ) ) {
			return $logo_validation;
		}
		$status            = $this->sanitize_status( $payload['status'] ?? 'publish' );
		$always_validation = $this->validate_club_tv_always_limit( 0, $fields, $status, absint( $payload['logo_attachment_id'] ?? 0 ) );
		if ( is_wp_error( $always_validation ) ) {
			return $always_validation;
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => 'rondo_sponsor',
				'post_title'  => $title,
				'post_status' => $status,
				'post_author' => get_current_user_id(),
			],
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$result = $this->write_fields( (int) $post_id, $fields );
		if ( is_wp_error( $result ) ) {
			wp_delete_post( (int) $post_id, true );
			return $result;
		}
		$logo_result = $this->set_logo( (int) $post_id, $payload['logo_attachment_id'] ?? 0 );
		if ( is_wp_error( $logo_result ) ) {
			wp_delete_post( (int) $post_id, true );
			return $logo_result;
		}

		return new \WP_REST_Response( $this->format_sponsor( get_post( $post_id ) ), 201 );
	}

	public function update_sponsor( \WP_REST_Request $request ) {
		$post = $this->sponsor_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$payload = $request->get_json_params() ?: $request->get_params();
		$fields  = null;
		if ( array_key_exists( 'fields', $payload ) ) {
			$fields = $this->sanitize_fields( (array) $payload['fields'], $post->ID, false );
			if ( is_wp_error( $fields ) ) {
				return $fields;
			}
		}
		if ( array_key_exists( 'logo_attachment_id', $payload ) ) {
			$logo_validation = $this->validate_logo_attachment( $payload['logo_attachment_id'] );
			if ( is_wp_error( $logo_validation ) ) {
				return $logo_validation;
			}
		}
		$desired_fields    = array_merge( Fields::all_for_post( $post->ID ), $fields ?? [] );
		$desired_status    = array_key_exists( 'status', $payload ) ? $this->sanitize_status( $payload['status'] ) : $post->post_status;
		$desired_logo      = array_key_exists( 'logo_attachment_id', $payload ) ? absint( $payload['logo_attachment_id'] ) : (int) get_post_thumbnail_id( $post->ID );
		$always_validation = $this->validate_club_tv_always_limit( $post->ID, $desired_fields, $desired_status, $desired_logo );
		if ( is_wp_error( $always_validation ) ) {
			return $always_validation;
		}
		$updates = [ 'ID' => $post->ID ];
		if ( array_key_exists( 'title', $payload ) ) {
			$title = sanitize_text_field( (string) $payload['title'] );
			if ( $title === '' ) {
				return new \WP_Error( 'rondo_sponsor_name_required', 'Vul een sponsornaam in.', [ 'status' => 400 ] );
			}
			$updates['post_title'] = $title;
		}
		if ( array_key_exists( 'status', $payload ) ) {
			$updates['post_status'] = $this->sanitize_status( $payload['status'] );
		}
		if ( count( $updates ) > 1 ) {
			$result = wp_update_post( $updates, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( $fields !== null ) {
			$result = $this->write_fields( $post->ID, $fields );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		if ( array_key_exists( 'logo_attachment_id', $payload ) ) {
			$result = $this->set_logo( $post->ID, $payload['logo_attachment_id'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		Relations::flush_cache();
		return rest_ensure_response( $this->format_sponsor( get_post( $post->ID ) ) );
	}

	public function archive_sponsor( \WP_REST_Request $request ) {
		$post = $this->sponsor_post( absint( $request['id'] ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$result = wp_update_post(
			[
				'ID'          => $post->ID,
				'post_status' => 'draft',
			],
			true
			);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		Relations::flush_cache();
		return rest_ensure_response( $this->format_sponsor( get_post( $post->ID ) ) );
	}

	/** Upload and attach a sponsor logo, optionally recording its Sponsit source ID. */
	public function upload_logo( \WP_REST_Request $request ) {
		$sponsor = $this->sponsor_post( absint( $request['id'] ) );
		if ( is_wp_error( $sponsor ) ) {
			return $sponsor;
		}

		$source_logo_id = sanitize_text_field( (string) $request->get_param( 'sponsit_logo_id' ) );
		if ( $source_logo_id !== '' && ! ctype_digit( $source_logo_id ) ) {
			return new \WP_Error( 'rondo_sponsor_logo_source_invalid', 'De Sponsit logo-ID is ongeldig.', [ 'status' => 400 ] );
		}

		$old_attachment_id = (int) get_post_thumbnail_id( $sponsor->ID );
		$response          = $this->upload_entity_logo( $request, 'sponsor' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data              = $response->get_data();
		$attachment_id     = absint( $data['attachment_id'] ?? 0 );
		$always_validation = $this->validate_club_tv_always_limit(
			$sponsor->ID,
			Fields::all_for_post( $sponsor->ID ),
			$sponsor->post_status,
			$attachment_id
		);
		if ( is_wp_error( $always_validation ) ) {
			if ( $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}
			return $always_validation;
		}
		if ( ! $attachment_id || ! set_post_thumbnail( $sponsor->ID, $attachment_id ) ) {
			if ( $attachment_id ) {
				wp_delete_attachment( $attachment_id, true );
			}
			return new \WP_Error( 'rondo_sponsor_logo_save_failed', 'Het sponsorlogo kon niet worden opgeslagen.', [ 'status' => 500 ] );
		}

		if ( $source_logo_id !== '' ) {
			$result = Fields::update_many_for_post( $sponsor->ID, [ 'sponsit_logo_id' => $source_logo_id ] );
			if ( is_wp_error( $result ) ) {
				$old_attachment_id ? set_post_thumbnail( $sponsor->ID, $old_attachment_id ) : delete_post_thumbnail( $sponsor->ID );
				wp_delete_attachment( $attachment_id, true );
				return $result;
			}
		}

		return rest_ensure_response( $this->format_sponsor( get_post( $sponsor->ID ) ) );
	}

	/** Create an external person and append it as a sponsor contact atomically enough to recover. */
	public function create_contact( \WP_REST_Request $request ) {
		$sponsor = $this->sponsor_post( absint( $request['id'] ) );
		if ( is_wp_error( $sponsor ) ) {
			return $sponsor;
		}
		$payload    = $request->get_json_params() ?: $request->get_params();
		$first_name = sanitize_text_field( (string) ( $payload['first_name'] ?? '' ) );
		$infix      = sanitize_text_field( (string) ( $payload['infix'] ?? '' ) );
		$last_name  = sanitize_text_field( (string) ( $payload['last_name'] ?? '' ) );
		$title      = trim( implode( ' ', array_filter( [ $first_name, $infix, $last_name ] ) ) );
		if ( $title === '' ) {
			return new \WP_Error( 'rondo_sponsor_contact_name_required', 'Vul de naam van de contactpersoon in.', [ 'status' => 400 ] );
		}
		$contacts     = Fields::get_for_post( $sponsor->ID, 'contacts' );
		$contacts     = is_array( $contacts ) ? $contacts : [];
		$sponsor_type = (string) ( Fields::get_for_post( $sponsor->ID, 'sponsor_type' ) ?: 'organization' );
		if ( $sponsor_type === 'person' && $contacts !== [] ) {
			return new \WP_Error( 'rondo_person_sponsor_contact_exists', 'Een persoonlijke sponsor kan aan één persoon zijn gekoppeld.', [ 'status' => 409 ] );
		}

		$person_id = wp_insert_post(
			[
				'post_type'   => 'person',
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
			],
			true
		);
		if ( is_wp_error( $person_id ) ) {
			return $person_id;
		}

		$person_fields = [
			'person_type' => 'contact',
			'first_name'  => $first_name,
			'infix'       => $infix,
			'last_name'   => $last_name,
			'email_1'     => sanitize_email( (string) ( $payload['email'] ?? '' ) ),
			'mobile_1'    => sanitize_text_field( (string) ( $payload['mobile'] ?? '' ) ),
			'telephone_1' => sanitize_text_field( (string) ( $payload['telephone'] ?? '' ) ),
		];
		foreach ( [ 'telephone_2', 'gender', 'birthdate' ] as $field_name ) {
			if ( array_key_exists( $field_name, $payload ) ) {
				$person_fields[ $field_name ] = sanitize_text_field( (string) $payload[ $field_name ] );
			}
		}
		if ( array_key_exists( 'email_2', $payload ) ) {
			$person_fields['email_2'] = sanitize_email( (string) $payload['email_2'] );
		}
		$result = Fields::update_many_for_post( (int) $person_id, $person_fields );
		if ( is_wp_error( $result ) ) {
			wp_delete_post( (int) $person_id, true );
			return $result;
		}

		$is_primary = array_key_exists( 'is_primary', $payload ) ? ! empty( $payload['is_primary'] ) : empty( $contacts );
		$gets_pass  = array_key_exists( 'receives_pass', $payload ) ? ! empty( $payload['receives_pass'] ) : true;
		$contacts[] = [
			'person_id'         => (int) $person_id,
			'contact_role'      => sanitize_text_field( (string) ( $payload['contact_role'] ?? 'Contactpersoon' ) ),
			'is_primary'        => $is_primary,
			'receives_pass'     => $gets_pass,
			'is_primary_pass'   => $gets_pass && ! empty( $payload['is_primary_pass'] ),
			'sponsit_person_id' => sanitize_text_field( (string) ( $payload['sponsit_person_id'] ?? '' ) ),
		];
		$result     = Relations::set_contacts( $sponsor->ID, $contacts );
		if ( is_wp_error( $result ) ) {
			wp_delete_post( (int) $person_id, true );
			return $result;
		}

		return new \WP_REST_Response( $this->format_sponsor( $sponsor ), 201 );
	}

	public function person_options( \WP_REST_Request $request ) {
		$search = sanitize_text_field( (string) $request->get_param( 'search' ) );
		if ( mb_strlen( $search ) < 2 ) {
			return rest_ensure_response( [] );
		}
		$posts       = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => 20,
				's'                => $search,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);
		$email_posts = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'posts_per_page'   => 20,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'meta_query'       => [
					'relation' => 'OR',
					[
						'key'     => 'email_1',
						'value'   => $search,
						'compare' => 'LIKE',
					],
					[
						'key'     => 'email_2',
						'value'   => $search,
						'compare' => 'LIKE',
					],
				],
				'suppress_filters' => true,
			]
		);
		$posts       = array_slice(
			array_values(
				array_reduce(
					array_merge( $posts, $email_posts ),
					static function ( array $unique, \WP_Post $post ): array {
						$unique[ $post->ID ] = $post;
						return $unique;
					},
					[]
				)
			),
			0,
			20
		);
		return rest_ensure_response(
			array_map(
				static function ( \WP_Post $post ): array {
					$fields = Fields::all_for_post( $post->ID );
					return [
						'id'          => $post->ID,
						'name'        => get_the_title( $post ),
						'person_type' => (string) ( $fields['person_type'] ?? 'member' ),
						'email'       => (string) ( $fields['email_1'] ?? '' ),
					];
				},
				$posts
			)
		);
	}

	public function add_sponsor_relationships_to_person( $response, $post, $request ) {
		if ( ! AccessControl::can_manage_sponsors() || ! $response instanceof \WP_REST_Response ) {
			return $response;
		}
		$data                          = $response->get_data();
		$data['sponsor_relationships'] = Relations::for_person( (int) $post->ID );
		$data['is_sponsor_contact']    = Relations::is_sponsor_contact( (int) $post->ID );
		$response->set_data( $data );
		return $response;
	}

	/** @return \WP_Post|\WP_Error */
	private function sponsor_post( int $id ) {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'rondo_sponsor' || ! in_array( $post->post_status, [ 'publish', 'draft' ], true ) ) {
			return new \WP_Error( 'rondo_sponsor_not_found', 'Sponsor niet gevonden.', [ 'status' => 404 ] );
		}
		return $post;
	}

	/** @return array<string,mixed>|\WP_Error */
	private function sanitize_fields( array $input, int $sponsor_id, bool $creating ) {
		$allowed = [
			'sponsor_type',
			'sponsor_role',
			'club_tv_priority',
			'website',
			'address_street_name',
			'address_house_number',
			'address_house_number_addition',
			'address_postal_code',
			'address_city',
			'address_country',
			'address_country_code',
			'sponsit_contact_id',
			'contacts',
		];
		$unknown = array_diff( array_keys( $input ), $allowed );
		if ( $unknown ) {
			return new \WP_Error( 'rondo_sponsor_unknown_field', 'Onbekende sponsorvelden: ' . implode( ', ', $unknown ), [ 'status' => 400 ] );
		}

		$current = $sponsor_id ? Fields::all_for_post( $sponsor_id ) : [];
		$type    = array_key_exists( 'sponsor_type', $input ) ? sanitize_key( (string) $input['sponsor_type'] ) : (string) ( $current['sponsor_type'] ?? 'organization' );
		$role    = array_key_exists( 'sponsor_role', $input ) ? sanitize_key( (string) $input['sponsor_role'] ) : (string) ( $current['sponsor_role'] ?? '' );
		if ( ( $creating || array_key_exists( 'sponsor_type', $input ) ) && ! in_array( $type, self::TYPES, true ) ) {
			return new \WP_Error( 'rondo_sponsor_type_required', 'Kies organisatie of persoon als sponsortype.', [ 'status' => 400 ] );
		}
		if ( ( $creating || array_key_exists( 'sponsor_role', $input ) ) && ! in_array( $role, self::ROLES, true ) ) {
			return new \WP_Error( 'rondo_sponsor_role_required', 'Kies Businessclub AWC of AWC Sponsor.', [ 'status' => 400 ] );
		}
		if ( array_key_exists( 'sponsit_contact_id', $input ) ) {
			$source_id = sanitize_text_field( (string) $input['sponsit_contact_id'] );
			if ( $source_id !== '' && $this->sponsit_id_exists( $source_id, $sponsor_id ) ) {
				return new \WP_Error( 'rondo_sponsor_sponsit_id_exists', 'Deze Sponsit contact-ID is al gekoppeld.', [ 'status' => 409 ] );
			}
		}

		$output = [];
		foreach ( $input as $key => $value ) {
			if ( $key === 'contacts' ) {
				$contacts = Relations::normalize_contacts( (array) $value );
				if ( is_wp_error( $contacts ) ) {
					return $contacts;
				}
				if ( $type === 'person' && count( $contacts ) > 1 ) {
					return new \WP_Error( 'rondo_person_sponsor_contact_limit', 'Een persoonlijke sponsor kan aan één persoon zijn gekoppeld.', [ 'status' => 400 ] );
				}
				$output[ $key ] = $contacts;
			} elseif ( $key === 'sponsor_type' ) {
				$output[ $key ] = $type;
			} elseif ( $key === 'sponsor_role' ) {
				$output[ $key ] = $role;
			} elseif ( $key === 'club_tv_priority' ) {
				if ( filter_var( $value, FILTER_VALIDATE_INT ) === false || ! in_array( (int) $value, self::CLUB_TV_PRIORITIES, true ) ) {
					return new \WP_Error( 'rondo_sponsor_club_tv_priority_invalid', 'Kies een geldige Club TV-weergave.', [ 'status' => 400 ] );
				}
				$output[ $key ] = (int) $value;
			} elseif ( $key === 'website' ) {
				$output[ $key ] = esc_url_raw( (string) $value );
			} else {
				$output[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $output;
	}

	/** @return true|\WP_Error */
	private function write_fields( int $sponsor_id, array $fields ) {
		$contacts = null;
		if ( array_key_exists( 'contacts', $fields ) ) {
			$contacts = (array) $fields['contacts'];
			unset( $fields['contacts'] );
		}
		if ( $fields ) {
			$result = Fields::update_many_for_post( $sponsor_id, $fields );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		return $contacts === null ? true : Relations::set_contacts( $sponsor_id, $contacts );
	}

	/** @return true|\WP_Error */
	private function set_logo( int $sponsor_id, $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id === 0 ) {
			delete_post_thumbnail( $sponsor_id );
			return true;
		}
		if ( (int) get_post_thumbnail_id( $sponsor_id ) === $attachment_id ) {
			return true;
		}
		if ( ! set_post_thumbnail( $sponsor_id, $attachment_id ) ) {
			return new \WP_Error( 'rondo_sponsor_logo_save_failed', 'Het sponsorlogo kon niet worden opgeslagen.', [ 'status' => 500 ] );
		}
		return true;
	}

	/** @return true|\WP_Error */
	private function validate_logo_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id === 0 ) {
			return true;
		}
		if ( get_post_type( $attachment_id ) !== 'attachment' || ! wp_attachment_is_image( $attachment_id ) ) {
			return new \WP_Error( 'rondo_sponsor_logo_invalid', 'Kies een geldige afbeelding als logo.', [ 'status' => 400 ] );
		}
		return true;
	}

	/** @return true|\WP_Error */
	private function validate_club_tv_always_limit( int $sponsor_id, array $fields, string $status, int $logo_attachment_id ) {
		$priority = (int) ( $fields['club_tv_priority'] ?? 0 );
		if ( $priority !== 3 || $status !== 'publish' || $logo_attachment_id === 0 ) {
			return true;
		}

		if ( $sponsor_id ) {
			$current_priority  = (int) Fields::get_for_post( $sponsor_id, 'club_tv_priority' );
			$current_is_always = $current_priority === 3
				&& get_post_status( $sponsor_id ) === 'publish'
				&& (int) get_post_thumbnail_id( $sponsor_id ) > 0;
			if ( $current_is_always ) {
				return true;
			}
		}

		$always_ids     = get_posts(
			[
				'post_type'        => 'rondo_sponsor',
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'post__not_in'     => $sponsor_id ? [ $sponsor_id ] : [],
				'meta_key'         => 'club_tv_priority',
				'meta_value'       => '3',
				'suppress_filters' => true,
			]
		);
		$visible_always = array_filter( $always_ids, static fn( int $id ): bool => (int) get_post_thumbnail_id( $id ) > 0 );
		if ( count( $visible_always ) >= self::CLUB_TV_ALWAYS_LIMIT ) {
			return new \WP_Error(
				'rondo_sponsor_club_tv_always_limit',
				'Er kunnen maximaal zes sponsoren op Altijd tonen staan.',
				[ 'status' => 400 ]
			);
		}

		return true;
	}

	private function sanitize_status( $status ): string {
		return sanitize_key( (string) $status ) === 'draft' ? 'draft' : 'publish';
	}

	private function sponsit_id_exists( string $source_id, int $exclude_id ): bool {
		$ids = get_posts(
			[
				'post_type'        => 'rondo_sponsor',
				'post_status'      => [ 'publish', 'draft' ],
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'post__not_in'     => $exclude_id ? [ $exclude_id ] : [],
				'meta_key'         => 'sponsit_contact_id',
				'meta_value'       => $source_id,
				'suppress_filters' => true,
			]
		);
		return $ids !== [];
	}

	private function format_sponsor( \WP_Post $post ): array {
		$fields   = Fields::all_for_post( $post->ID );
		$contacts = [];
		foreach ( (array) ( $fields['contacts'] ?? [] ) as $row ) {
			$person_id = absint( $row['person_id'] ?? 0 );
			if ( ! $person_id || get_post_type( $person_id ) !== 'person' ) {
				continue;
			}
			$person_fields = Fields::all_for_post( $person_id );
			$contacts[]    = array_merge(
				$row,
				[
					'person_id'   => $person_id,
					'person_name' => get_the_title( $person_id ),
					'person_type' => (string) ( $person_fields['person_type'] ?? 'member' ),
					'email'       => (string) ( $person_fields['email_1'] ?? '' ),
					'mobile'      => (string) ( $person_fields['mobile_1'] ?? '' ),
					'telephone'   => (string) ( $person_fields['telephone_1'] ?? '' ),
				]
			);
		}
		$fields['contacts']     = $contacts;
		$fields['sponsor_type'] = (string) ( $fields['sponsor_type'] ?? 'organization' );

		return [
			'id'                 => $post->ID,
			'title'              => get_the_title( $post ),
			'status'             => $post->post_status,
			'fields'             => $fields,
			'logo_attachment_id' => (int) get_post_thumbnail_id( $post->ID ),
			'logo_url'           => get_the_post_thumbnail_url( $post, 'medium_large' ) ?: null,
			'modified'           => get_post_modified_time( DATE_ATOM, false, $post ),
		];
	}
}
