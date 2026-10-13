<?php
namespace DWCatalog\Media;

/**
 * Image decode / resize / encode for the web — GD first, Imagick as fallback.
 *
 * Output is WebP whenever the server can write it (JPEG otherwise). Every stored file is
 * encoded from decoded pixels: metadata (EXIF GPS etc.) is dropped and a file that only
 * pretends to be an image cannot survive.
 */
final class ImageProcessor {

	const TYPES = array(
		IMAGETYPE_JPEG => array( 'image/jpeg', 'jpg' ),
		IMAGETYPE_PNG  => array( 'image/png', 'png' ),
		IMAGETYPE_GIF  => array( 'image/gif', 'gif' ),
		IMAGETYPE_WEBP => array( 'image/webp', 'webp' ),
	);

	const QUALITY = 80;

	/** Largest input accepted (decompression-bomb guard). */
	const MAX_PIXELS = 40000000;

	public static function available(): bool {
		return function_exists( 'imagecreatefromstring' ) || class_exists( 'Imagick' );
	}

	/** @return array [ mime, ext ] the stored files use. */
	public static function outputFormat(): array {
		if ( function_exists( 'imagewebp' ) || ( class_exists( 'Imagick' ) && ! function_exists( 'imagecreatefromstring' ) && \Imagick::queryFormats( 'WEBP' ) ) ) {
			return array( 'image/webp', 'webp' );
		}
		return array( 'image/jpeg', 'jpg' );
	}

	/**
	 * Inspect a file. @return array|null [ mime, ext, width, height ] when it is a supported image.
	 */
	public static function inspect( string $path ): ?array {
		$info = @getimagesize( $path );
		if ( ! $info || ! isset( self::TYPES[ $info[2] ] ) || $info[0] < 1 || $info[1] < 1 ) {
			return null;
		}
		if ( $info[0] * $info[1] > self::MAX_PIXELS ) {
			return null;
		}
		return array( self::TYPES[ $info[2] ][0], self::TYPES[ $info[2] ][1], (int) $info[0], (int) $info[1] );
	}

	/**
	 * GD holds the whole bitmap in PHP memory (~5 bytes/pixel incl. the resized copy). Raise
	 * the limit for this request when the default (often 128M) is too small.
	 */
	public static function ensureMemory( int $w, int $h ): bool {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return true; // Imagick keeps pixels outside PHP's memory limit
		}
		$need  = memory_get_usage( true ) + $w * $h * 5 + 32 * 1048576;
		$limit = self::bytes( (string) ini_get( 'memory_limit' ) );
		if ( $limit < 0 || $limit >= $need ) {
			return true;
		}
		@ini_set( 'memory_limit', (string) min( 768, (int) ceil( $need / 1048576 ) ) . 'M' );
		$limit = self::bytes( (string) ini_get( 'memory_limit' ) );
		return $limit < 0 || $limit >= $need;
	}

	private static function bytes( string $v ): int {
		$v = trim( $v );
		if ( '-1' === $v ) {
			return -1;
		}
		$n = (int) $v;
		switch ( strtolower( substr( $v, -1 ) ) ) {
			case 'g':
				return $n * 1073741824;
			case 'm':
				return $n * 1048576;
			case 'k':
				return $n * 1024;
		}
		return $n;
	}

	/**
	 * Write a resized copy as $mime. Fits inside $maxW × $maxH (or covers + center-crops when
	 * $crop). Never upscales. @return array|null [ width, height ]
	 */
	public static function resize( string $src, string $dest, string $mime, int $maxW, int $maxH, bool $crop = false ): ?array {
		if ( function_exists( 'imagecreatefromstring' ) ) {
			return self::resizeGd( $src, $dest, $mime, $maxW, $maxH, $crop );
		}
		if ( class_exists( 'Imagick' ) ) {
			return self::resizeImagick( $src, $dest, $mime, $maxW, $maxH, $crop );
		}
		return null;
	}

	/** JPEG copy in the temp dir (for consumers that cannot read WebP, e.g. PDF). */
	public static function toJpeg( string $src ): ?string {
		$tmp = tempnam( sys_get_temp_dir(), 'dwj' );
		return self::resize( $src, $tmp, 'image/jpeg', 10000, 10000 ) ? $tmp : null;
	}

	private static function box( int $w, int $h, int $maxW, int $maxH, bool $crop ): array {
		if ( $crop ) {
			$scale = min( 1, max( $maxW / $w, $maxH / $h ) );
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
		unset( $data );
		if ( ! $img ) {
			return null;
		}
		// Phone photos store rotation in EXIF; apply it before the metadata is dropped.
		if ( function_exists( 'exif_read_data' ) && 'image/jpeg' === ( getimagesize( $src )['mime'] ?? '' ) ) {
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
		} else {
			imagefill( $out, 0, 0, imagecolorallocate( $out, 255, 255, 255 ) ); // transparent PNG → white, not black
		}
		imagecopyresampled( $out, $img, 0, 0, $sx, $sy, $dw, $dh, $sw, $sh );
		imagedestroy( $img );
		switch ( $mime ) {
			case 'image/webp':
				$ok = imagewebp( $out, $dest, self::QUALITY );
				break;
			case 'image/png':
				$ok = imagepng( $out, $dest, 6 );
				break;
			default:
				imageinterlace( $out, true ); // progressive JPEG
				$ok = imagejpeg( $out, $dest, self::QUALITY );
		}
		imagedestroy( $out );
		return $ok ? array( $dw, $dh ) : null;
	}

	private static function resizeImagick( string $src, string $dest, string $mime, int $maxW, int $maxH, bool $crop ): ?array {
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
			$im->setImageFormat( 'image/webp' === $mime ? 'webp' : ( 'image/png' === $mime ? 'png' : 'jpeg' ) );
			$im->setImageCompressionQuality( self::QUALITY );
			$im->writeImage( $dest );
			$im->clear();
			return array( $dw, $dh );
		} catch ( \Throwable $e ) {
			return null;
		}
	}
}
