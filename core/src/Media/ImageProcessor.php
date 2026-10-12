<?php
namespace DWCatalog\Media;

/**
 * Image decode / resize / re-encode with GD (or Imagick when GD is missing).
 *
 * Every stored image is re-encoded from decoded pixels: metadata (EXIF GPS etc.) is dropped
 * and a file that only pretends to be an image cannot survive.
 */
final class ImageProcessor {

	const TYPES = array(
		IMAGETYPE_JPEG => array( 'image/jpeg', 'jpg' ),
		IMAGETYPE_PNG  => array( 'image/png', 'png' ),
		IMAGETYPE_GIF  => array( 'image/gif', 'gif' ),
		IMAGETYPE_WEBP => array( 'image/webp', 'webp' ),
	);

	public static function available(): bool {
		return function_exists( 'imagecreatefromstring' ) || class_exists( 'Imagick' );
	}

	/**
	 * Inspect a file. @return array|null [ mime, ext, width, height ] when it is a supported image.
	 */
	public static function inspect( string $path ): ?array {
		$info = @getimagesize( $path );
		if ( ! $info || ! isset( self::TYPES[ $info[2] ] ) || $info[0] < 1 || $info[1] < 1 ) {
			return null;
		}
		if ( $info[0] * $info[1] > 50000000 ) {
			return null; // > 50 MP: decompression bomb guard
		}
		return array( self::TYPES[ $info[2] ][0], self::TYPES[ $info[2] ][1], (int) $info[0], (int) $info[1] );
	}

	/**
	 * Write a resized copy. Fits inside $maxW × $maxH (or covers + center-crops when $crop).
	 * Never upscales. @return array|null [ width, height ]
	 */
	public static function resize( string $src, string $dest, string $mime, int $maxW, int $maxH, bool $crop = false ): ?array {
		if ( function_exists( 'imagecreatefromstring' ) ) {
			return self::resizeGd( $src, $dest, $mime, $maxW, $maxH, $crop );
		}
		if ( class_exists( 'Imagick' ) ) {
			return self::resizeImagick( $src, $dest, $maxW, $maxH, $crop );
		}
		return null;
	}

	private static function box( int $w, int $h, int $maxW, int $maxH, bool $crop ): array {
		if ( $crop ) {
			$scale = max( $maxW / $w, $maxH / $h );
			$scale = min( 1, $scale );
			$cw    = (int) round( min( $w, $maxW / $scale ) );
			$ch    = (int) round( min( $h, $maxH / $scale ) );
			return array( (int) ( ( $w - $cw ) / 2 ), (int) ( ( $h - $ch ) / 2 ), $cw, $ch, max( 1, (int) round( $cw * $scale ) ), max( 1, (int) round( $ch * $scale ) ) );
		}
		$scale = min( 1, $maxW / $w, $maxH / $h );
		return array( 0, 0, $w, $h, max( 1, (int) round( $w * $scale ) ), max( 1, (int) round( $h * $scale ) ) );
	}

	private static function resizeGd( string $src, string $dest, string $mime, int $maxW, int $maxH, bool $crop ): ?array {
		$data = file_get_contents( $src );
		$img  = false !== $data ? @imagecreatefromstring( $data ) : false;
		if ( ! $img ) {
			return null;
		}
		// Phone photos store rotation in EXIF; apply it before the metadata is dropped.
		if ( 'image/jpeg' === $mime && function_exists( 'exif_read_data' ) ) {
			$exif = @exif_read_data( $src );
			$rot  = array( 3 => 180, 6 => -90, 8 => 90 );
			if ( ! empty( $exif['Orientation'] ) && isset( $rot[ $exif['Orientation'] ] ) ) {
				$rotated = imagerotate( $img, $rot[ $exif['Orientation'] ], 0 );
				if ( $rotated ) {
					imagedestroy( $img );
					$img = $rotated;
				}
			}
		}
		list( $sx, $sy, $sw, $sh, $dw, $dh ) = self::box( imagesx( $img ), imagesy( $img ), $maxW, $maxH, $crop );
		$out = imagecreatetruecolor( $dw, $dh );
		if ( 'image/jpeg' !== $mime ) {
			imagealphablending( $out, false );
			imagesavealpha( $out, true );
			imagefill( $out, 0, 0, imagecolorallocatealpha( $out, 0, 0, 0, 127 ) );
		}
		imagecopyresampled( $out, $img, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh );
		switch ( $mime ) {
			case 'image/png':
				$ok = imagepng( $out, $dest, 6 );
				break;
			case 'image/gif':
				$ok = imagegif( $out, $dest );
				break;
			case 'image/webp':
				$ok = function_exists( 'imagewebp' ) && imagewebp( $out, $dest, 82 );
				break;
			default:
				$ok = imagejpeg( $out, $dest, 82 );
		}
		imagedestroy( $img );
		imagedestroy( $out );
		return $ok ? array( $dw, $dh ) : null;
	}

	private static function resizeImagick( string $src, string $dest, int $maxW, int $maxH, bool $crop ): ?array {
		try {
			$im = new \Imagick( $src );
			$im->setIteratorIndex( 0 );
			$im->autoOrient();
			$im->stripImage();
			list( $sx, $sy, $sw, $sh, $dw, $dh ) = self::box( $im->getImageWidth(), $im->getImageHeight(), $maxW, $maxH, $crop );
			if ( $crop ) {
				$im->cropImage( $sw, $sh, $sx, $sy );
			}
			$im->resizeImage( $dw, $dh, \Imagick::FILTER_LANCZOS, 1 );
			$im->setImageCompressionQuality( 82 );
			$im->writeImage( $dest );
			$im->clear();
			return array( $dw, $dh );
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
