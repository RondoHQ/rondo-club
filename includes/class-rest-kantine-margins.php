<?php
/** Authenticated margin reads, costing edits and private invoice uploads. */
namespace Rondo\REST;

use Rondo\Twelve\MarginRepository;
use Rondo\Twelve\PurchaseParser;
use Rondo\Core\UserRoles;

class KantineMargins extends Base {
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function can_read(): bool {
		return $this->check_user_approved() && ( current_user_can( 'kassaomzet' ) || current_user_can( 'manage_options' ) );
	}

	public function can_write(): bool {
		return $this->can_read() && UserRoles::can_manage_finances();
	}

	public function register_routes(): void {
		$routes = [
			'/twelve/margins'                         => [ 'GET', 'catalog', 'can_read' ],
			'/twelve/margins/product'                 => [ 'POST', 'product', 'can_write' ],
			'/twelve/margins/purchase'                => [ 'POST', 'purchase', 'can_write' ],
			'/twelve/margins/preview'                 => [ 'POST', 'preview', 'can_write' ],
			'/twelve/margins/invoice/(?P<id>\d+)/pdf' => [ 'GET', 'pdf', 'can_read' ],
		];
		foreach ( $routes as $route => [ $method, $callback, $permission ] ) {
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

	public function catalog( $request ) {
		$date = $request->get_param( 'date' ) ?? current_time( 'Y-m-d' );
		if ( ! MarginRepository::valid_date( $date ) ) {
			return new \WP_Error( 'kantine_date_invalid', 'Kies een geldige peildatum.', [ 'status' => 400 ] );
		}
		return rest_ensure_response( array_merge( ( new MarginRepository() )->catalog( $date ), [ 'can_write' => $this->can_write() ] ) );
	}

	public function product( $request ) {
		$data = $request->get_json_params();
		return is_array( $data ) ? ( new MarginRepository() )->save_product( $data ) : new \WP_Error( 'kantine_invalid_json', 'Productgegevens ontbreken.', [ 'status' => 400 ] );
	}

	public function preview( $request ) {
		$files = $request->get_file_params();
		$file  = $files['file'] ?? null;
		if ( ! is_array( $file ) || ( $file['error'] ?? 1 ) !== UPLOAD_ERR_OK || ( $file['size'] ?? 0 ) > 5 * 1024 * 1024 || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'kantine_upload_invalid', 'Upload een PDF-factuur van maximaal 5 MB.', [ 'status' => 400 ] );
		}
		$bytes = file_get_contents( $file['tmp_name'] );
		$data  = ( new PurchaseParser() )->parse( $bytes, sanitize_file_name( $file['name'] ), ( new MarginRepository() )->articles( current_time( 'Y-m-d' ) ) );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$token = wp_generate_uuid4();
		if ( ! set_transient(
			'kantine_preview_' . $token,
			[
				'user_id' => get_current_user_id(),
				'data'    => $data,
				'pdf'     => base64_encode( $bytes ),
			],
			30 * MINUTE_IN_SECONDS
			) ) {
			return new \WP_Error( 'kantine_preview_storage', 'Voorbeeld kon niet worden bewaard. Probeer opnieuw.', [ 'status' => 500 ] );
		}
		return [
			'token'   => $token,
			'invoice' => $data,
		];
	}

	public function purchase( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'kantine_invalid_json', 'Factuurgegevens ontbreken.', [ 'status' => 400 ] );
		}
		$pdf = '';
		if ( isset( $data['token'] ) ) {
			if ( ! is_string( $data['token'] ) || ! preg_match( '/^[a-f0-9-]{36}$/', $data['token'] ) ) {
				return new \WP_Error( 'kantine_preview_expired', 'Upload de factuur opnieuw.', [ 'status' => 400 ] );
			}
			$preview = get_transient( 'kantine_preview_' . $data['token'] );
			if ( ! is_array( $preview ) || $preview['user_id'] !== get_current_user_id() ) {
				return new \WP_Error( 'kantine_preview_expired', 'Het factuurvoorbeeld is verlopen. Upload opnieuw.', [ 'status' => 400 ] );
			}
			$edited = $data['lines'] ?? [];
			$data   = $preview['data'];
			if ( ! is_array( $edited ) || count( $edited ) !== count( $data['lines'] ) ) {
				return new \WP_Error( 'kantine_preview_invalid', 'Het aantal factuurregels is gewijzigd. Upload opnieuw.', [ 'status' => 400 ] );
			}
			foreach ( array_values( $edited ) as $index => $line ) {
				if ( ! is_array( $line ) ) {
					return new \WP_Error( 'kantine_preview_invalid', 'Ongeldige factuurregel.', [ 'status' => 400 ] );
				}
				$data['lines'][ $index ] = array_merge( $data['lines'][ $index ], array_intersect_key( $line, array_flip( [ 'quantity', 'unit', 'units_per_pack', 'note' ] ) ) );
			}
			$pdf = base64_decode( $preview['pdf'], true );
		}
		return ( new MarginRepository() )->save_purchase( $data, $pdf );
	}

	public function pdf( $request ) {
		$id    = (int) $request['id'];
		$bytes = get_post_type( $id ) === MarginRepository::PURCHASE ? base64_decode( (string) get_post_meta( $id, '_kantine_pdf_base64', true ), true ) : false;
		if ( ! $bytes ) {
			return new \WP_Error( 'kantine_pdf_missing', 'Bronfactuur niet gevonden.', [ 'status' => 404 ] );
		}
		$response = new \WP_REST_Response(
			null,
			200,
			[
				'Content-Type'        => 'application/pdf',
				'Cache-Control'       => 'private, no-store',
				'Content-Disposition' => 'inline; filename="inkoopfactuur-' . $id . '.pdf"',
			]
			);
		add_filter(
			'rest_pre_serve_request',
			static function ( $served, $result, $rest_request ) use ( $request, $bytes ) {
				if ( $rest_request === $request ) {
					// Authenticated financial documents are never placed in public uploads.
					echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary PDF.
					return true;
				}
				return $served;
			},
			10,
			3
			);
		return $response;
	}
}
