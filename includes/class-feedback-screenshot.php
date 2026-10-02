<?php
/** Private screenshots attached to feedback, outside the public WordPress directory. */
namespace Rondo\Feedback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FeedbackScreenshot {
	public const META = '_feedback_screenshot';

	public function __construct() {
		add_action( 'before_delete_post', [ self::class, 'delete' ] );
	}

	public static function path( array $file ): string {
		$name = $file['file'] ?? '';
		if ( ! preg_match( '/^[a-f0-9]{32}\.(png|jpg|webp)$/', $name ) ) {
			return '';
		}
		return dirname( untrailingslashit( ABSPATH ) ) . '/rondo-private/feedback/' . $name;
	}

	/** Validate actual image bytes, independently of the browser MIME type. */
	public static function validate( array $file ) {
		$path = $file['tmp_name'] ?? '';
		if ( ! is_string( $path ) || ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK || ! is_file( $path ) ) {
			return new \WP_Error( 'feedback_screenshot_upload', 'De screenshot kon niet worden geüpload. Kies het bestand opnieuw.', [ 'status' => 400 ] );
		}
		if ( filesize( $path ) > 5 * MB_IN_BYTES ) {
			return new \WP_Error( 'feedback_screenshot_size', 'De screenshot mag maximaal 5 MB groot zijn.', [ 'status' => 400 ] );
		}
		$dimensions = wp_getimagesize( $path );
		$extensions = [
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/webp' => 'webp',
		];
		$type       = $dimensions['mime'] ?? '';
		if ( ! isset( $extensions[ $type ] ) || $dimensions[0] * $dimensions[1] > 25000000 ) {
			return new \WP_Error( 'feedback_screenshot_type', 'Kies een PNG, JPG of WebP van maximaal 25 megapixels.', [ 'status' => 400 ] );
		}
		return [
			'file' => bin2hex( random_bytes( 16 ) ) . '.' . $extensions[ $type ],
			'type' => $type,
			'name' => sanitize_file_name( $file['name'] ?? 'screenshot.' . $extensions[ $type ] ),
		];
	}

	/** Store an optional real HTTP upload before creating the feedback record. */
	public static function upload( array $files ) {
		if ( ! isset( $files['screenshot'] ) ) {
			return [];
		}
		$input = $files['screenshot'];
		if ( ! is_array( $input ) ) {
			return new \WP_Error( 'feedback_screenshot_upload', 'Kies één screenshot.', [ 'status' => 400 ] );
		}
		$file = self::validate( $input );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		if ( ! is_uploaded_file( $input['tmp_name'] ) ) {
			return new \WP_Error( 'feedback_screenshot_upload', 'De screenshot kon niet worden geüpload.', [ 'status' => 400 ] );
		}
		$path      = self::path( $file );
		$directory = dirname( $path );
		if ( ! wp_mkdir_p( $directory ) || ! chmod( $directory, 0700 ) || ! move_uploaded_file( $input['tmp_name'], $path ) ) {
			return new \WP_Error( 'feedback_screenshot_storage', 'De screenshot kon niet veilig worden opgeslagen. Probeer het opnieuw.', [ 'status' => 500 ] );
		}
		if ( ! chmod( $path, 0600 ) ) {
			wp_delete_file( $path );
			return new \WP_Error( 'feedback_screenshot_storage', 'De screenshot kon niet veilig worden opgeslagen.', [ 'status' => 500 ] );
		}
		return $file;
	}

	public static function delete( int $post_id ): void {
		if ( get_post_type( $post_id ) !== 'rondo_feedback' ) {
			return;
		}
		$file = get_post_meta( $post_id, self::META, true );
		$path = is_array( $file ) ? self::path( $file ) : '';
		if ( $path && is_file( $path ) ) {
			wp_delete_file( $path );
		}
	}
}
