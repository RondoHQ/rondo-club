<?php
namespace Rondo\Alv;

class Publications {
	private const PAGE  = 'rondo_alv_page';
	private const PDF   = 'rondo_alv_pdf';
	private const LIMIT = 5 * 1024 * 1024;

	public function __construct( private Alv $alv ) {
		add_action( 'init', [ $this, 'register' ] );
		add_filter( 'page_attributes_dropdown_pages_args', [ $this, 'parent_options' ], 10, 2 );
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_rondo_alv_publications', [ $this, 'admin_action' ] );
		add_action( 'admin_post_rondo_alv_pdf', [ $this, 'download' ] );
		add_action( 'admin_post_nopriv_rondo_alv_pdf', [ $this, 'download' ] );
	}

	public function register(): void {
		$caps = array_fill_keys(
			[ 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ],
			'manage_options'
		);
		register_post_type(
			self::PAGE,
			[
				'labels'       => [
					'name'          => 'ALV-pagina’s',
					'singular_name' => 'ALV-pagina',
					'add_new_item'  => 'ALV-pagina toevoegen',
				],
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'hierarchical' => true,
				'supports'     => [ 'title', 'editor', 'page-attributes', 'revisions' ],
				'capabilities' => $caps,
				'map_meta_cap' => false,
				'query_var'    => false,
				'rewrite'      => false,
			]
		);
		register_post_type(
			self::PDF,
			[
				'public'       => false,
				'query_var'    => false,
				'rewrite'      => false,
				'capabilities' => $caps,
				'map_meta_cap' => false,
			]
			);
	}

	public function parent_options( array $args, $post ): array {
		if ( $post->post_type === self::PAGE ) {
			$args['post_status'] = [ 'draft', 'pending', 'private', 'publish' ];
		}
		return $args;
	}

	public function menu(): void {
		add_menu_page( 'ALV-pagina’s', 'ALV-pagina’s', 'manage_options', 'rondo-alv-pages', [ $this, 'screen' ], 'dashicons-media-document' );
	}

	private function url( int $meeting ): string {
		return admin_url( 'admin.php?page=rondo-alv-pages&meeting=' . $meeting );
	}

	private function pdf_url( int $meeting, int $pdf ): string {
		return admin_url( 'admin-post.php?action=rondo_alv_pdf&meeting=' . $meeting . '&pdf=' . $pdf );
	}

	private function form( int $id, string $action, string $label, array $pages = [], string $hash = '' ): void {
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'rondo_alv_publications_' . $id );
		echo '<input type="hidden" name="action" value="rondo_alv_publications">';
		echo '<input type="hidden" name="meeting" value="' . esc_attr( (string) $id ) . '">';
		echo '<input type="hidden" name="operation" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="snapshot" value="' . esc_attr( $hash ) . '">';
		if ( $action === 'upload' ) {
			echo '<p><label>Pagina <select name="page">';
			foreach ( $pages as $page ) {
				echo '<option value="' . esc_attr( (string) $page['id'] ) . '">' . esc_html( $page['title'] ) . '</option>';
			}
			echo '</select></label> <label>PDF (max. 5 MB) <input type="file" name="pdf" accept="application/pdf,.pdf" required></label></p>';
		}
		submit_button( $label, $action === 'publish' ? 'primary' : 'secondary', 'submit', true );
		echo '</form>';
	}

	public function screen(): void {
		$id = isset( $_GET['meeting'] ) ? absint( $_GET['meeting'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view.
		echo '<div class="wrap"><h1>ALV-pagina’s</h1>';
		foreach ( $this->alv->meeting_documents( $id ) as $meeting_id => $meeting ) {
			echo '<h2><a href="' . esc_url( $this->url( $meeting_id ) ) . '">' . esc_html( $meeting['title'] ) . '</a></h2>';
			if ( ! $id ) {
				continue;
			}
			$publication = $meeting['publication'] ?? [];
			if ( empty( $publication['root'] ) ) {
				$this->form( $id, 'initialize', 'WordPress-paginastructuur maken en bestaande concepten overnemen' );
				continue;
			}
			$snapshot = $this->snapshot( $id, $publication );
			if ( is_wp_error( $snapshot ) ) {
				echo '<p>' . esc_html( $snapshot->get_error_message() ) . '</p>';
				continue;
			}
			echo '<p>Wijzig de pagina’s en hun bovenliggende pagina in WordPress. De leden zien de laatst gepubliceerde versie van de hele structuur.</p>';
			echo '<p><a href="' . esc_url( admin_url( 'post-new.php?post_type=' . self::PAGE ) ) . '">Pagina toevoegen</a> · <a href="' . esc_url( admin_url( 'edit.php?post_type=' . self::PAGE ) ) . '">Alle WordPress-ALV-pagina’s beheren</a></p><ul>';
			foreach ( $snapshot['pages'] as $page ) {
				echo '<li style="margin-left:' . esc_attr( (string) ( 20 * $page['depth'] ) ) . 'px"><a href="' . esc_url( get_edit_post_link( $page['id'], 'raw' ) ) . '">' . esc_html( $page['title'] ) . '</a>';
				foreach ( $page['files'] as $file ) {
					echo ' · <a href="' . esc_url( $file['url'] ) . '">' . esc_html( $file['title'] ) . '</a>';
				}
				echo '<details><summary>Voorvertoning</summary>' . wp_kses_post( $page['html'] ) . '</details></li>';
			}
			echo '</ul>';
			$this->form( $id, 'upload', 'Afgeschermde PDF toevoegen', $snapshot['pages'] );
			$hash = $this->fingerprint( $snapshot );
			if ( ( $publication['live']['hash'] ?? '' ) === $hash ) {
				echo '<p>Deze versie is gepubliceerd voor leden.</p>';
			} elseif ( ( $publication['approved']['hash'] ?? '' ) === $hash ) {
				echo '<p>Deze versie is goedgekeurd.</p>';
				$this->form( $id, 'publish', 'Hele ALV publiceren voor leden', [], $hash );
			} else {
				echo '<p>Er zijn wijzigingen die nog moeten worden goedgekeurd.</p>';
				$this->form( $id, 'approve', 'Hele ALV goedkeuren', [], $hash );
			}
		}
		echo '</div>';
	}

	private function add_page( string $title, string $html = '', int $parent = 0, int $order = 0 ) {
		return wp_insert_post(
			wp_slash(
				[
					'post_type'    => self::PAGE,
					'post_status'  => 'draft',
					'post_title'   => $title,
					'post_content' => $html,
					'post_parent'  => $parent,
					'menu_order'   => $order,
				]
				),
			true
		);
	}

	private function initialize( array &$meeting ) {
		if ( isset( $meeting['publication']['root'] ) ) {
			return true;
		}
		$root = $this->add_page( $meeting['title'] );
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		add_post_meta( $root, '_rondo_alv_root', true, true );
		$year = 0;
		foreach ( $meeting['pages'] as $page ) {
			$version = $page['current'];
			$parent  = $root;
			if ( str_starts_with( strtolower( $version['title'] ), 'jaarcijfers' ) ) {
				if ( ! $year ) {
					$year_title = preg_match( '/\b\d{4}\b/', $meeting['title'], $match ) ? $match[0] : 'Jaarstukken';
					$year       = $this->add_page( $year_title, '', $root, 20 );
					if ( is_wp_error( $year ) ) {
						return $year;
					}
				}
				$parent = $year;
			}
			$html = $version['html'];
			if ( $parent === $year && $year ) {
				$html = preg_replace( '/<th colspan="4">(Lasten|Resultaat)<\/th>/', '<th colspan="4" style="padding-top:1.25rem">$1</th>', $html );
			}
			if ( isset( $version['result'] ) ) {
				$html .= '<h3>' . esc_html( $version['result']['title'] ) . '</h3><ul>';
				foreach ( $version['result']['options'] as $index => $option ) {
					$html .= '<li>' . esc_html( $option . ' — ' . $version['result']['totals'][ $index ] ) . '</li>';
				}
				$html .= '</ul>';
			}
			$child = $this->add_page( $version['title'], $html, $parent, $parent === $root ? 10 : 0 );
			if ( is_wp_error( $child ) ) {
				return $child;
			}
		}
		$meeting['publication'] = [
			'root'        => $root,
			'attachments' => [],
		];
		return true;
	}

	private function snapshot( int $meeting, array $publication ) {
		$root      = (int) $publication['root'];
		$posts     = get_posts(
			[
				'post_type'   => self::PAGE,
				'post_status' => [ 'draft', 'pending', 'private', 'publish' ],
				'numberposts' => -1,
				'orderby'     => [
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
				],
			]
			);
		$by_parent = [];
		$root_post = null;
		foreach ( $posts as $post ) {
			if ( (int) $post->ID === $root ) {
				$root_post = $post;
			}
			$by_parent[ (int) $post->post_parent ][] = $post;
		}
		if ( ! $root_post || $root_post->post_parent ) {
			return new \WP_Error( 'rondo_alv_root', 'De ALV-hoofdpagina moet bestaan en mag geen bovenliggende pagina hebben.' );
		}
		$pages    = [];
		$file_ids = [];
		$stack    = [ [ $root_post, 0 ] ];
		while ( $stack ) {
			[ $post, $depth ] = array_pop( $stack );
			$id               = (int) $post->ID;
			if ( isset( $pages[ $id ] ) || ( $id !== $root && get_post_meta( $id, '_rondo_alv_root', true ) ) ) {
				continue;
			}
			$html  = wp_kses_post( $post->post_content );
			$files = [];
			foreach ( $publication['attachments'][ $id ] ?? [] as $file ) {
				$file_ids[] = $file['id'];
				$files[]    = $file + [ 'url' => $this->pdf_url( $meeting, $file['id'] ) ];
			}
			foreach ( array_reverse( $files ) as $file ) {
				if ( stripos( $file['title'], 'verslag-kascommissie' ) === 0 ) {
					$html = preg_replace( '#<a\b[^>]*href="https://[^"]+\.claudeusercontent\.com/[^"]*/verslag-kascommissie-2025-2026\.pdf"[^>]*>(.*?)</a>#i', '<a href="' . esc_url( $file['url'] ) . '">$1</a>', $html );
					break;
				}
			}
			$pages[ $id ] = [
				'id'     => $id,
				'parent' => (int) $post->post_parent,
				'depth'  => $depth,
				'title'  => $post->post_title,
				'html'   => $html,
				'files'  => $files,
			];
			foreach ( array_reverse( $by_parent[ $id ] ?? [] ) as $child ) {
				$stack[] = [ $child, $depth + 1 ];
			}
		}
		return [
			'pages' => $pages,
			'files' => $file_ids,
		];
	}

	private function fingerprint( array $snapshot ): string {
		return hash( 'sha256', wp_json_encode( $snapshot ) );
	}

	public function admin_action(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Je mag de ALV-pagina’s niet beheren.', '', [ 'response' => 403 ] );
		}
		$id = isset( $_POST['meeting'] ) ? absint( $_POST['meeting'] ) : 0;
		check_admin_referer( 'rondo_alv_publications_' . $id );
		$action   = isset( $_POST['operation'] ) ? sanitize_key( wp_unslash( $_POST['operation'] ) ) : '';
		$page     = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 0;
		$expected = isset( $_POST['snapshot'] ) ? sanitize_text_field( wp_unslash( $_POST['snapshot'] ) ) : '';
		$result   = $this->alv->locked(
			$id,
			function ( &$meeting ) use ( $id, $action, $page, $expected ) {
				if ( $action === 'initialize' ) {
					return $this->initialize( $meeting );
				}
				if ( ! isset( $meeting['publication'] ) ) {
					return new \WP_Error( 'rondo_alv_root', 'Maak eerst de paginastructuur.' );
				}
				$publication = &$meeting['publication'];
				$snapshot    = $this->snapshot( $id, $publication );
				if ( is_wp_error( $snapshot ) ) {
					return $snapshot;
				}
				if ( $action === 'upload' && isset( $snapshot['pages'][ $page ] ) ) {
					$file = $this->upload( $id );
					if ( is_wp_error( $file ) ) {
						return $file;
					}
					$publication['attachments'][ $page ][] = $file;
					return true;
				}
				$hash = $this->fingerprint( $snapshot );
				if ( ! hash_equals( $hash, $expected ) ) {
					return new \WP_Error( 'rondo_alv_changed', 'De paginastructuur is gewijzigd. Vernieuw het overzicht en controleer de nieuwe versie.' );
				}
				if ( $action === 'approve' ) {
					$publication['approved'] = [
						'hash' => $hash,
						'by'   => get_current_user_id(),
						'at'   => time(),
					];
					return true;
				}
				if ( $action === 'publish' && ( $publication['approved']['hash'] ?? '' ) === $hash ) {
					$publication['live'] = $snapshot + [
						'hash' => $hash,
						'by'   => get_current_user_id(),
						'at'   => time(),
					];
					return true;
				}
				return new \WP_Error( 'rondo_alv_publication', 'Controleer en keur deze versie goed voordat je publiceert.' );
			}
			);
		if ( is_wp_error( $result ) ) {
			wp_die(
				esc_html( $result->get_error_message() ),
				'',
				[
					'response'  => 400,
					'back_link' => true,
				]
				);
		}
		wp_safe_redirect( $this->url( $id ) );
		exit;
	}

	private function upload( int $meeting ) {
		$file = $_FILES['pdf'] ?? []; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Called only after the admin action checks its nonce.
		$tmp  = $file['tmp_name'] ?? '';
		if ( ! is_string( $tmp ) || ! is_uploaded_file( $tmp ) || $file['error'] !== UPLOAD_ERR_OK || filesize( $tmp ) > self::LIMIT ) {
			return new \WP_Error( 'rondo_alv_pdf', 'Selecteer een PDF van maximaal 5 MB.' );
		}
		$name = sanitize_file_name( wp_unslash( $file['name'] ) );
		$data = file_get_contents( $tmp );
		if ( ! is_string( $data ) || ! str_starts_with( $data, '%PDF-' ) || strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) !== 'pdf' ) {
			return new \WP_Error( 'rondo_alv_pdf', 'Dit bestand is geen geldige PDF.' );
		}
		$id = wp_insert_post(
			[
				'post_type'   => self::PDF,
				'post_status' => 'private',
				'post_title'  => $name,
			],
			true
			);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		if ( ! add_post_meta( $id, '_rondo_alv_pdf', base64_encode( $data ), true ) || ! add_post_meta( $id, '_rondo_alv_meeting', $meeting, true ) ) {
			return new \WP_Error( 'rondo_alv_pdf', 'De PDF kon niet worden opgeslagen.' );
		}
		return [
			'id'    => $id,
			'title' => $name,
		];
	}

	public function download(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only download, authorized against the live snapshot.
		$id  = isset( $_GET['meeting'] ) ? absint( $_GET['meeting'] ) : 0;
		$pdf = isset( $_GET['pdf'] ) ? absint( $_GET['pdf'] ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		nocache_headers();
		header( 'Cache-Control: private, no-store' );
		$allowed = current_user_can( 'manage_options' );
		if ( ! $allowed && is_user_logged_in() && $this->alv->current_member() ) {
			$meeting = $this->alv->meeting_documents( $id )[ $id ] ?? [];
			$allowed = in_array( $pdf, $meeting['publication']['live']['files'] ?? [], true );
		}
		if ( ! $allowed || ! $id || get_post_type( $pdf ) !== self::PDF || (int) get_post_meta( $pdf, '_rondo_alv_meeting', true ) !== $id ) {
			wp_die( 'Bijlage niet gevonden.', '', [ 'response' => 404 ] );
		}
		$data = base64_decode( get_post_meta( $pdf, '_rondo_alv_pdf', true ), true );
		if ( ! $data ) {
			wp_die( 'Bijlage niet gevonden.', '', [ 'response' => 404 ] );
		}
		header( 'Content-Type: application/pdf' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="ALV-bijlage.pdf"; filename*=UTF-8\'\'' . rawurlencode( get_the_title( $pdf ) ) );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Validated binary PDF response.
		echo $data;
		exit;
	}
}
