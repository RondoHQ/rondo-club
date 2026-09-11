<?php
/** Authenticated upload and review of private VOG documents. */
namespace Rondo\REST;

use Rondo\VOG\VogSubmissions as Store;
use Rondo\VOG\VogDocument;
use Rondo\Core\AccessControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VogSubmissions extends Base {
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $this, 'serve_file' ], 10, 4 );
	}

	public function register_routes(): void {
		foreach ( [
			[ '/vog/upload', 'POST', 'upload', 'member_permission' ],
			[ '/vog/submissions', 'GET', 'listing', 'check_vog_permission' ],
			[ '/vog/submissions/(?P<id>\d+)/files/(?P<file_id>\d+)', 'GET', 'document', 'file_permission' ],
			[ '/vog/submissions/(?P<id>\d+)/review', 'POST', 'review', 'review_permission' ],
			[ '/vog/submissions/(?P<id>\d+)/retry', 'POST', 'retry', 'review_permission' ],
			[ '/vog/approval-rules', 'GET', 'rules', 'check_admin_permission' ],
			[ '/vog/approval-rules', 'POST', 'save_rules', 'check_admin_permission' ],
		] as [ $route, $method, $callback, $permission ] ) {
			register_rest_route(
				'rondo/v1',
				$route,
				[
					'methods'             => $method,
					'callback'            => [ $this, $callback ],
					'permission_callback' => [ $this, $permission ],
				]
				);
		}
	}

	public function member_permission(): bool {
		$user = get_current_user_id();
		return Store::eligible( (int) get_user_meta( $user, 'rondo_linked_person_id', true ), $user );
	}

	public function review_permission( \WP_REST_Request $request ): bool {
		$data = Store::get( (int) $request['id'] );
		return $data && ! get_option( 'rondo_is_demo_site', false ) && $this->check_vog_permission() && AccessControl::can_view_person( $data['person_id'] );
	}

	public function file_permission( \WP_REST_Request $request ): bool {
		$data = Store::get( (int) $request['id'] );
		return $data && ( $this->review_permission( $request ) || ( $data['user_id'] === get_current_user_id() && $this->member_permission() && (int) get_user_meta( get_current_user_id(), 'rondo_linked_person_id', true ) === $data['person_id'] ) );
	}

	public function upload( \WP_REST_Request $request ) {
		$user   = get_current_user_id();
		$person = (int) get_user_meta( $user, 'rondo_linked_person_id', true );
		$source = (string) $request->get_param( 'source' );
		$input  = $request->get_file_params()['files'] ?? [];
		if ( ! in_array( $source, [ 'digital', 'paper', 'digital_scan', 'unknown' ], true ) || ! is_array( $input['name'] ?? null ) || count( $input['name'] ) < 1 || count( $input['name'] ) > 5 ) {
			return new \WP_Error( 'vog_files', 'Kies één PDF of maximaal vijf JPG/PNG-foto’s en geef de herkomst aan.', [ 'status' => 400 ] );
		}
		try {
			$result = Store::locked(
				$person,
				function () use ( $user, $person, $source, $input ) {
					if ( ! $this->member_permission() ) {
						return new \WP_Error( 'vog_forbidden', 'Je kunt geen VOG voor dit profiel inleveren.', [ 'status' => 403 ] );
					}
					$rate_key = 'rondo_vog_upload_' . $user;
					$rate     = get_transient( $rate_key ) ?: [
						'count' => 0,
						'until' => time() + HOUR_IN_SECONDS,
					];
					if ( $rate['count'] >= 8 ) {
						return new \WP_Error( 'vog_limit', 'Je hebt meerdere bestanden ingeleverd. Probeer het over een uur opnieuw.', [ 'status' => 429 ] );
					}
					$validated = [];
					$total     = 0;
					$parsed    = [];
					foreach ( array_keys( $input['name'] ) as $i ) {
						$path = $input['tmp_name'][ $i ] ?? '';
						if ( ( $input['error'][ $i ] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK || ! is_uploaded_file( $path ) ) {
							return new \WP_Error( 'vog_upload', 'Het uploaden is niet gelukt. Kies de bestanden opnieuw.', [ 'status' => 400 ] );
						}
						$total += filesize( $path );
						if ( $total > 10 * MB_IN_BYTES ) {
							return new \WP_Error( 'vog_size', 'De bestanden mogen samen maximaal 10 MB groot zijn.', [ 'status' => 400 ] );
						}
						$type = wp_check_filetype_and_ext(
						$path,
						$input['name'][ $i ],
						[
							'pdf'      => 'application/pdf',
							'jpg|jpeg' => 'image/jpeg',
							'png'      => 'image/png',
						]
						);
						if ( ! $type['type'] || ! $type['ext'] ) {
							return new \WP_Error( 'vog_type', 'Kies een PDF, JPG of PNG. Dit bestandstype wordt niet ondersteund.', [ 'status' => 400 ] );
						}
						if ( $type['type'] === 'application/pdf' ) {
							if ( count( $input['name'] ) !== 1 || file_get_contents( $path, false, null, 0, 5 ) !== '%PDF-' ) {
								return new \WP_Error( 'vog_pdf', 'Lever één originele PDF of één scan-PDF in.', [ 'status' => 400 ] );
							}
							$read = VogDocument::read( $path );
							if ( empty( $read['pages'] ) || $read['pages'] > 5 ) {
								return new \WP_Error( 'vog_pdf_read', 'De PDF kan niet worden gelezen. Kies een PDF van maximaal vijf pagina’s zonder openingswachtwoord. Neem bij aanhoudende problemen contact op met de VOG-coördinator.', [ 'status' => 400 ] );
							}
							$parsed = $source === 'digital' ? VogDocument::parse( $read['text'] ?? '' ) : [];
						} else {
							$dimensions = wp_getimagesize( $path );
							if ( $source === 'digital' || ! $dimensions || $dimensions[0] * $dimensions[1] > 25000000 || ( $dimensions['mime'] ?? '' ) !== $type['type'] ) {
								return new \WP_Error( 'vog_image', 'Gebruik de foto/scan-route voor JPG/PNG-bestanden van maximaal 25 megapixels.', [ 'status' => 400 ] );
							}
						}
						$validated[] = [
							'tmp'    => $path,
							'name'   => bin2hex( random_bytes( 16 ) ) . '.' . ( $type['type'] === 'image/jpeg' ? 'jpg' : $type['ext'] ),
							'type'   => $type['type'],
							'sha256' => hash_file( 'sha256', $path ),
						];
					}
					$old_id = Store::latest_id( $person );
					$old    = Store::get( $old_id );
					if ( $old && $old['user_id'] === $user && $old['source'] === $source && $old['expires'] > time() && in_array( $old['status'], Store::ACTIVE, true ) && array_column( $old['files'], 'sha256' ) === array_column( $validated, 'sha256' ) ) {
						return $old_id;
					}
					$stored = [];
					try {
						foreach ( $validated as $file ) {
							$target = Store::path( $file );
							if ( ! move_uploaded_file( $file['tmp'], $target ) ) {
								throw new \RuntimeException( 'Upload mislukt.' );
							}
							chmod( $target, 0600 );
							unset( $file['tmp'] );
							$stored[] = $file;
						}
						$id = Store::create( $person, $user, $source, $stored, $parsed );
					} catch ( \Throwable $error ) {
						foreach ( $stored as $file ) {
							$path = Store::path( $file );
							if ( is_file( $path ) ) {
								unlink( $path );
							}
						}
						throw $error;
					}
					set_transient(
					$rate_key,
					[
						'count' => $rate['count'] + 1,
						'until' => $rate['until'],
					],
					max( 1, $rate['until'] - time() )
					);
					if ( $source === 'digital' ) {
						wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'rondo_vog_retry', [ $id ] );
					}
					return $id;
				}
				);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			Store::process( $result );
			return rest_ensure_response( Store::payload( $result ) );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'vog_storage', 'De VOG kon niet veilig worden verwerkt. Probeer het later opnieuw.', [ 'status' => 500 ] );
		}
	}

	public function listing( \WP_REST_Request $request ) {
		$page  = max( 1, (int) $request->get_param( 'page' ) );
		$query = new \WP_Query(
			[
				'post_type'      => Store::TYPE,
				'post_status'    => 'private',
				'posts_per_page' => 25,
				'paged'          => $page,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_key'       => '_rondo_vog_active',
				'meta_value'     => '1',
			]
			);
		$items = [];
		foreach ( $query->posts as $post ) {
			if ( AccessControl::can_view_person( $post->post_parent ) ) {
				$items[] = Store::payload( $post->ID, true );
			}
		}
		return rest_ensure_response(
			[
				'items' => $items,
				'pages' => (int) $query->max_num_pages,
			]
			);
	}

	public function document( \WP_REST_Request $request ) {
		$data = Store::get( (int) $request['id'] );
		$file = $data['files'][ (int) $request['file_id'] ] ?? null;
		if ( ! $file || $data['expires'] <= time() || ! in_array( $data['status'], Store::ACTIVE, true ) || ! is_readable( Store::path( $file ) ) ) {
			return new \WP_Error( 'vog_file_missing', 'Dit document is niet meer beschikbaar.', [ 'status' => 404 ] );
		}
		// Never serialize the local path, even if the streaming hook is not installed.
		return new \WP_REST_Response( null, 200, [ 'X-Rondo-VOG-File' => (string) $request['file_id'] ] );
	}

	public function serve_file( $served, $result, $request, $server ) {
		if ( ! preg_match( '#^/rondo/v1/vog/submissions/\d+/files/\d+$#', $request->get_route() ) || $result->get_status() !== 200 || ! $this->file_permission( $request ) ) {
			return $served;
		}
		$data = Store::get( (int) $request['id'] );
		$file = $data['files'][ (int) $request['file_id'] ] ?? null;
		if ( ! $file || $data['expires'] <= time() ) {
			return $served;
		}
		$path   = Store::path( $file );
		$handle = fopen( $path, 'rb' );
		if ( ! $handle ) {
			return $served;
		}
		header_remove( 'X-Rondo-VOG-File' );
		header( 'Content-Type: ' . $file['type'] );
		header( 'Content-Disposition: inline; filename="vog.' . pathinfo( $path, PATHINFO_EXTENSION ) . '"' );
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Security-Policy: sandbox' );
		fpassthru( $handle );
		fclose( $handle );
		return true;
	}

	public function review( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$data = Store::get( $id );
		return Store::locked(
			$data['person_id'],
			function () use ( $id, $request ) {
				$data = Store::get( $id );
				if ( $data['version'] !== (int) $request['version'] || $data['expires'] <= time() || ! in_array( $data['status'], Store::ACTIVE, true ) || Store::latest_id( $data['person_id'] ) !== $id || ! Store::eligible( $data['person_id'], $data['user_id'] ) ) {
					return new \WP_Error( 'vog_changed', 'Deze inzending is gewijzigd, verlopen of niet meer gekoppeld. Ververs het overzicht.', [ 'status' => 409 ] );
				}
				$note = sanitize_textarea_field( (string) $request['note'] );
				if ( mb_strlen( $note ) < 3 || mb_strlen( $note ) > 500 ) {
					return new \WP_Error( 'vog_note', 'Geef een korte toelichting van 3 tot 500 tekens, zonder overbodige persoonsgegevens.', [ 'status' => 400 ] );
				}
				$data['note'] = $note;
				if ( $request['action'] === 'reject' ) {
					$data['reviewer'] = get_current_user_id();
					Store::finish( $id, $data, 'rejected' );
				} elseif ( $request['action'] === 'approve' ) {
					$method = (string) $request['method'];
					if ( $request['confirmed'] !== true || ( $method !== 'paper_original' && $method !== 'gaav_manual' ) || ( $method === 'gaav_manual' && $data['code'] !== 0 ) || ( $method === 'paper_original' && $data['source'] !== 'paper' ) ) {
						return new \WP_Error( 'vog_original', 'Controleer het originele papier of een digitaal document met bevestigde echtheid.', [ 'status' => 400 ] );
					}
					$date   = $data['parsed']['date'] ?? (string) $request['date'];
					$result = Store::approve( $id, $data, $date, $method, get_current_user_id() );
					if ( is_wp_error( $result ) ) {
						return $result;
					}
				} else {
					return new \WP_Error( 'vog_action', 'Kies goedkeuren of afwijzen.', [ 'status' => 400 ] );
				}
				return rest_ensure_response( Store::payload( $id ) );
			}
			);
	}

	public function retry( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$data = Store::get( $id );
		if ( $data['source'] !== 'digital' || $data['status'] !== 'technical' || $data['attempts'] >= 5 ) {
			return new \WP_Error( 'vog_retry', 'Deze inzending kan niet opnieuw automatisch worden gecontroleerd.', [ 'status' => 400 ] );
		}
		Store::process( $id );
		return rest_ensure_response( Store::payload( $id, true ) );
	}

	public function rules() {
		return rest_ensure_response(
			[
				'rules'            => get_option( Store::RULES, [] ),
				'reader_available' => ! empty( VogDocument::read( '--health' )['available'] ),
			]
			);
	}

	public function save_rules( \WP_REST_Request $request ) {
		$rules = $request['rules'];
		if ( ! is_array( $rules ) || count( $rules ) > 10 ) {
			return new \WP_Error( 'vog_rules', 'Gebruik maximaal tien goedkeuringsregels.', [ 'status' => 400 ] );
		}
		$clean = [];
		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) || ! is_string( $rule['organization'] ?? null ) || ! is_string( $rule['function'] ?? null ) || ! is_array( $rule['codes'] ?? null ) || ! $rule['codes'] || count( $rule['codes'] ) > 25 || $rule['organization'] === '' || $rule['function'] === '' || mb_strlen( $rule['organization'] ) > 200 || mb_strlen( $rule['function'] ) > 200 ) {
				return new \WP_Error( 'vog_rule', 'Vul organisatie, functie en screeningscodes volledig in.', [ 'status' => 400 ] );
			}
			foreach ( $rule['codes'] as $code ) {
				if ( ! is_string( $code ) || ! preg_match( '/^\d{2}$/', $code ) ) {
					return new \WP_Error( 'vog_codes', 'Gebruik tweecijferige screeningscodes.', [ 'status' => 400 ] );
				}
			}
			$clean[] = [
				'organization' => sanitize_text_field( $rule['organization'] ),
				'function'     => sanitize_text_field( $rule['function'] ),
				'codes'        => array_values( array_unique( $rule['codes'] ) ),
			];
		}
		update_option( Store::RULES, $clean, false );
		return $this->rules();
	}
}
