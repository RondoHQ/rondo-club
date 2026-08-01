<?php
/**
 * QR Code Generator
 *
 * Generates branded QR codes for payment URLs using the club's accent color and logo.
 * Uses the chillerlan/php-qrcode library with GD image output.
 */

namespace Rondo\Finance;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provider-agnostic branded QR code generator.
 *
 * Generates QR codes with the club's accent color and optional logo overlay,
 * saving the PNG to wp-content/uploads/invoices/ and updating the canonical field.
 */
class QrCodeGenerator {

	/**
	 * Default accent color used when none is configured.
	 */
	private const DEFAULT_ACCENT_COLOR = '#0891b2';

	/**
	 * Generate a branded QR code PNG for a payment URL.
	 *
	 * Applies the club accent color to all dark modules and optionally overlays the club logo
	 * in the center. Saves the PNG to uploads/invoices/ and updates the qr_code_path canonical field.
	 *
	 * @param string $url        The URL to encode in the QR code.
	 * @param int    $invoice_id The invoice post ID.
	 * @return string|\WP_Error Relative path (e.g. 'invoices/qr-F2025-001.png') on success, or WP_Error on failure.
	 */
	public static function generate( string $url, int $invoice_id ): string|\WP_Error {
		$upload_dir   = wp_upload_dir();
		$invoices_dir = $upload_dir['basedir'] . '/invoices';
		wp_mkdir_p( $invoices_dir );

		$invoice_number = \Rondo\Fields\Fields::get_for_post( $invoice_id, 'invoice_number' );
		if ( empty( $invoice_number ) ) {
			$invoice_number = $invoice_id;
		}

		$full_path = $invoices_dir . '/qr-' . $invoice_number . '.png';
		$result    = self::generate_to_path( $url, $full_path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$relative_path = 'invoices/qr-' . $invoice_number . '.png';
		\Rondo\Fields\Fields::update_for_post( $invoice_id, 'qr_code_path', $relative_path );

		return $relative_path;
	}

	/**
	 * Generate a branded QR code PNG and save it to a specific file path.
	 *
	 * @param string $url       The URL to encode in the QR code.
	 * @param string $full_path Absolute file path to save the PNG to.
	 * @return true|\WP_Error True on success, or WP_Error on failure.
	 */
	public static function generate_to_path( string $url, string $full_path ): true|\WP_Error {
		try {
			$finance_config = new FinanceConfig();
			$accent_hex     = $finance_config->get_accent_color();

			if ( empty( $accent_hex ) ) {
				$accent_hex = self::DEFAULT_ACCENT_COLOR;
			}

			// Convert hex color to RGB components.
			$accent_rgb = self::hex_to_rgb( $accent_hex );

			// Build QR options.
			$options                   = new QROptions();
			$options->outputType       = QROutputInterface::GDIMAGE_PNG;
			$options->outputBase64     = false;
			$options->returnResource   = true; // Return GdImage for logo overlay.
			$options->scale            = 10;   // 10px per module → ~330×330px output.
			$options->addQuietzone     = true;
			$options->quietzoneSize    = 4;
			$options->imageTransparent = false;
			$options->bgColor          = [ 255, 255, 255 ];

			// Use H (30%) error correction level to allow logo overlay up to ~30% of surface.
			$options->eccLevel = EccLevel::H;

			// Map dark module types to accent color, light types to white.
			$options->moduleValues = [
				// Light modules → white.
				QRMatrix::M_NULL             => [ 255, 255, 255 ],
				QRMatrix::M_DARKMODULE_LIGHT => [ 255, 255, 255 ],
				QRMatrix::M_DATA             => [ 255, 255, 255 ],
				QRMatrix::M_FINDER           => [ 255, 255, 255 ],
				QRMatrix::M_SEPARATOR        => [ 255, 255, 255 ],
				QRMatrix::M_ALIGNMENT        => [ 255, 255, 255 ],
				QRMatrix::M_TIMING           => [ 255, 255, 255 ],
				QRMatrix::M_FORMAT           => [ 255, 255, 255 ],
				QRMatrix::M_VERSION          => [ 255, 255, 255 ],
				QRMatrix::M_QUIETZONE        => [ 255, 255, 255 ],
				QRMatrix::M_LOGO             => [ 255, 255, 255 ],
				QRMatrix::M_FINDER_DOT_LIGHT => [ 255, 255, 255 ],
				// Dark modules → accent color.
				QRMatrix::M_DARKMODULE       => $accent_rgb,
				QRMatrix::M_DATA_DARK        => $accent_rgb,
				QRMatrix::M_FINDER_DARK      => $accent_rgb,
				QRMatrix::M_SEPARATOR_DARK   => $accent_rgb,
				QRMatrix::M_ALIGNMENT_DARK   => $accent_rgb,
				QRMatrix::M_TIMING_DARK      => $accent_rgb,
				QRMatrix::M_FORMAT_DARK      => $accent_rgb,
				QRMatrix::M_VERSION_DARK     => $accent_rgb,
				QRMatrix::M_QUIETZONE_DARK   => $accent_rgb,
				QRMatrix::M_LOGO_DARK        => $accent_rgb,
				QRMatrix::M_FINDER_DOT       => $accent_rgb,
			];

			// Generate QR code — returns a GdImage resource.
			$qr_image = ( new QRCode( $options ) )->render( $url );

			// Apply logo overlay if configured.
			$logo_id = $finance_config->get_club_logo_id();
			if ( $logo_id > 0 ) {
				$qr_image = self::apply_logo_overlay( $qr_image, $logo_id );
			}

			// Capture PNG bytes.
			ob_start();
			imagepng( $qr_image );
			$png_data = ob_get_clean();
			imagedestroy( $qr_image );

			if ( empty( $png_data ) ) {
				return new \WP_Error( 'qr_generation_failed', 'Failed to capture PNG output.' );
			}

			$bytes_written = file_put_contents( $full_path, $png_data );
			if ( $bytes_written === false ) {
				return new \WP_Error( 'qr_save_failed', 'Failed to save QR code PNG to disk.' );
			}

			return true;

		} catch ( \Throwable $e ) {
			return new \WP_Error(
				'qr_generation_failed',
				sprintf( 'QR code generation failed: %s', $e->getMessage() )
			);
		}
	}

	/**
	 * Overlay the club logo onto the center of a QR code GdImage.
	 *
	 * Adds a white rounded-rect background behind the logo so it stands out
	 * against colored QR modules. The logo area is sized to 20% of the QR image.
	 *
	 * @param \GdImage $qr_image The QR code GdImage resource.
	 * @param int      $logo_id  WordPress attachment ID for the logo.
	 * @return \GdImage The QR code image with logo overlay applied (may be original if logo fails).
	 */
	private static function apply_logo_overlay( $qr_image, int $logo_id ) {
		$logo_path = get_attached_file( $logo_id );
		if ( ! $logo_path || ! file_exists( $logo_path ) ) {
			return $qr_image;
		}

		$image_info = @getimagesize( $logo_path );
		if ( ! $image_info ) {
			return $qr_image;
		}

		$mime_type = $image_info['mime'];

		// Load logo as GdImage based on MIME type.
		$logo_image = null;
		switch ( $mime_type ) {
			case 'image/png':
				$logo_image = @imagecreatefrompng( $logo_path );
				break;
			case 'image/jpeg':
			case 'image/jpg':
				$logo_image = @imagecreatefromjpeg( $logo_path );
				break;
			case 'image/gif':
				$logo_image = @imagecreatefromgif( $logo_path );
				break;
			case 'image/webp':
				$logo_image = @imagecreatefromwebp( $logo_path );
				break;
		}

		if ( ! $logo_image ) {
			return $qr_image;
		}

		$qr_size    = imagesx( $qr_image );
		$logo_area  = (int) round( $qr_size * 0.20 ); // Logo area = 20% of QR size.
		$logo_src_w = imagesx( $logo_image );
		$logo_src_h = imagesy( $logo_image );

		// Scale logo to fit within logo_area while preserving aspect ratio.
		$scale_factor = min( $logo_area / $logo_src_w, $logo_area / $logo_src_h );
		$logo_dst_w   = (int) round( $logo_src_w * $scale_factor );
		$logo_dst_h   = (int) round( $logo_src_h * $scale_factor );

		// Position: centered on QR image.
		$logo_dst_x = (int) round( ( $qr_size - $logo_dst_w ) / 2 );
		$logo_dst_y = (int) round( ( $qr_size - $logo_dst_h ) / 2 );

		// Draw white background rounded-rect behind the logo (with 8px padding).
		$padding = (int) round( $logo_area * 0.12 );
		$bg_x    = $logo_dst_x - $padding;
		$bg_y    = $logo_dst_y - $padding;
		$bg_w    = $logo_dst_w + ( $padding * 2 );
		$bg_h    = $logo_dst_h + ( $padding * 2 );

		$white = imagecolorallocate( $qr_image, 255, 255, 255 );
		imagefilledrectangle( $qr_image, $bg_x, $bg_y, $bg_x + $bg_w, $bg_y + $bg_h, $white );

		// Copy logo onto QR image.
		$resized_logo = imagecreatetruecolor( $logo_dst_w, $logo_dst_h );
		imagesavealpha( $resized_logo, true );
		$transparent = imagecolorallocatealpha( $resized_logo, 0, 0, 0, 127 );
		imagefill( $resized_logo, 0, 0, $transparent );

		imagecopyresampled(
			$resized_logo,
			$logo_image,
			0,
			0,
			0,
			0,
			$logo_dst_w,
			$logo_dst_h,
			$logo_src_w,
			$logo_src_h
		);

		imagecopy( $qr_image, $resized_logo, $logo_dst_x, $logo_dst_y, 0, 0, $logo_dst_w, $logo_dst_h );

		imagedestroy( $logo_image );
		imagedestroy( $resized_logo );

		return $qr_image;
	}

	/**
	 * Convert a hex color string to an [R, G, B] array.
	 *
	 * @param string $hex Hex color string (e.g. '#0891b2' or '0891b2').
	 * @return int[] Array of [R, G, B] values 0–255. Returns [8, 145, 178] (electric-cyan) on parse failure.
	 */
	private static function hex_to_rgb( string $hex ): array {
		$hex = ltrim( $hex, '#' );

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if ( strlen( $hex ) !== 6 ) {
			return [ 8, 145, 178 ]; // Default: electric-cyan.
		}

		return [
			(int) hexdec( substr( $hex, 0, 2 ) ),
			(int) hexdec( substr( $hex, 2, 2 ) ),
			(int) hexdec( substr( $hex, 4, 2 ) ),
		];
	}
}
