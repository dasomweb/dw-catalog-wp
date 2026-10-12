<?php
namespace DWCatalog\Repo;

use DWCatalog\Db\Connection;
use DWCatalog\Host\Host;
use DWCatalog\Media\ImageProcessor;
use DWCatalog\Support\Text;

/**
 * Item images: files under the host's storage dir, records in the images table.
 * The first image (lowest position) is the main image.
 *
 * Stored per upload: "full" (≤ 2400px, re-encoded), "large" 1200, "medium" 600, "thumb" 300² crop.
 */
class Images {

	const MAX_BYTES = 15728640; // 15 MB
	const SIZES     = array(
		'large'  => array( 1200, 1200, false ),
		'medium' => array( 600, 600, false ),
		'thumb'  => array( 300, 300, true ),
	);

	/** @var Connection */
	private $db;
	/** @var Host */
	private $host;
	/** @var Settings */
	private $settings;

	/** item_id => rows */
	private $cache = array();

	public function __construct( Connection $db, Host $host, Settings $settings ) {
		$this->db       = $db;
		$this->host     = $host;
		$this->settings = $settings;
	}

	public function forItem( int $itemId ): array {
		if ( ! isset( $this->cache[ $itemId ] ) ) {
			$this->primeItems( array( $itemId ) );
		}
		return $this->cache[ $itemId ];
	}

	public function main( int $itemId ): ?array {
		$rows = $this->forItem( $itemId );
		return $rows ? $rows[0] : null;
	}

	/** All images for a page of items in one query. */
	public function primeItems( array $itemIds ): void {
		$ids = array_values( array_diff( array_unique( array_map( 'intval', $itemIds ) ), array_keys( $this->cache ), array( 0 ) ) );
		if ( ! $ids ) {
			return;
		}
		foreach ( $ids as $id ) {
			$this->cache[ $id ] = array();
		}
		foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
			foreach ( $this->db->select( 'SELECT * FROM ' . $this->db->table( 'images' ) . ' WHERE item_id IN (?) ORDER BY item_id ASC, position ASC, id ASC', array( $chunk ) ) as $row ) {
				$row['sizes']                           = json_decode( (string) $row['sizes'], true ) ?: array();
				$this->cache[ (int) $row['item_id'] ][] = $row;
			}
		}
	}

	/**
	 * Store an uploaded file ($_FILES entry). @return int|string new image id, or an error message.
	 */
	public function upload( int $itemId, array $file ) {
		if ( ! isset( $file['error'], $file['tmp_name'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return 'Upload failed.';
		}
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return 'Upload failed.';
		}
		$name = isset( $file['name'] ) ? (string) $file['name'] : 'image';
		return $this->store( $itemId, $file['tmp_name'], $name );
	}

	/**
	 * Store a local temp file (already downloaded by Remote::fetch for imports).
	 * @return int|string
	 */
	public function store( int $itemId, string $tmp, string $originalName ) {
		if ( ! ImageProcessor::available() ) {
			return 'Image processing is not available on this server (GD or Imagick required).';
		}
		if ( ! is_file( $tmp ) || filesize( $tmp ) > self::MAX_BYTES ) {
			return 'Image is too large (max 15 MB).';
		}
		$info = ImageProcessor::inspect( $tmp );
		if ( ! $info ) {
			return 'Not a supported image (JPEG, PNG, GIF, WebP).';
		}
		list( $mime, $ext ) = $info;

		$sub  = gmdate( 'Y' ) . '/' . gmdate( 'm' );
		$dir  = $this->host->storageDir() . '/' . $sub;
		if ( ! $this->ensureDir( $dir ) ) {
			return 'Storage directory is not writable.';
		}
		$base = Text::slug( pathinfo( $originalName, PATHINFO_FILENAME ), 60 );
		$base = ( '' !== $base ? $base : 'image' ) . '-' . bin2hex( random_bytes( 4 ) );

		$full = ImageProcessor::resize( $tmp, "$dir/$base.$ext", $mime, 2400, 2400 );
		if ( ! $full ) {
			return 'Could not process the image.';
		}
		$sizes = array();
		foreach ( self::SIZES as $size => list( $w, $h, $crop ) ) {
			$dims = ImageProcessor::resize( "$dir/$base.$ext", "$dir/$base-$size.$ext", $mime, $w, $h, $crop );
			if ( $dims ) {
				$sizes[ $size ] = array( 'file' => "$sub/$base-$size.$ext", 'width' => $dims[0], 'height' => $dims[1] );
			}
		}

		$position = (int) $this->db->selectValue( 'SELECT COUNT(*) FROM ' . $this->db->table( 'images' ) . ' WHERE item_id = ?', array( $itemId ) );
		$id       = $this->db->insert( 'images', array(
			'item_id'    => $itemId,
			'position'   => $position,
			'file'       => "$sub/$base.$ext",
			'mime'       => $mime,
			'width'      => $full[0],
			'height'     => $full[1],
			'sizes'      => json_encode( $sizes ),
			'alt'        => '',
			'created_at' => Text::now(),
		) );
		unset( $this->cache[ $itemId ] );
		$this->settings->bumpCacheVersion();
		return $id;
	}

	/** Reorder + alt text from the editor. $order = image ids in display order. */
	public function update( int $itemId, array $order, array $alts ): void {
		$own = array();
		foreach ( $this->forItem( $itemId ) as $row ) {
			$own[ (int) $row['id'] ] = true;
		}
		$pos = 0;
		foreach ( $order as $id ) {
			$id = (int) $id;
			if ( ! isset( $own[ $id ] ) ) {
				continue;
			}
			$alt = isset( $alts[ $id ] ) ? Text::line( $alts[ $id ], 255 ) : '';
			$this->db->execute( 'UPDATE ' . $this->db->table( 'images' ) . ' SET position = ?, alt = ? WHERE id = ? AND item_id = ?', array( $pos++, $alt, $id, $itemId ) );
		}
		unset( $this->cache[ $itemId ] );
	}

	public function delete( int $itemId, int $imageId ): void {
		foreach ( $this->forItem( $itemId ) as $row ) {
			if ( (int) $row['id'] === $imageId ) {
				$this->removeFiles( $row );
				$this->db->execute( 'DELETE FROM ' . $this->db->table( 'images' ) . ' WHERE id = ?', array( $imageId ) );
			}
		}
		unset( $this->cache[ $itemId ] );
		$this->settings->bumpCacheVersion();
	}

	public function deleteForItem( int $itemId ): void {
		foreach ( $this->forItem( $itemId ) as $row ) {
			$this->removeFiles( $row );
		}
		$this->db->execute( 'DELETE FROM ' . $this->db->table( 'images' ) . ' WHERE item_id = ?', array( $itemId ) );
		unset( $this->cache[ $itemId ] );
	}

	// ── Output ──────────────────────────────────────────────────

	public function url( array $row, string $size = 'full' ): string {
		$file = ( 'full' !== $size && isset( $row['sizes'][ $size ]['file'] ) ) ? $row['sizes'][ $size ]['file'] : $row['file'];
		return $this->host->storageUrl() . '/' . implode( '/', array_map( 'rawurlencode', explode( '/', $file ) ) );
	}

	public function path( array $row, string $size = 'full' ): string {
		$file = ( 'full' !== $size && isset( $row['sizes'][ $size ]['file'] ) ) ? $row['sizes'][ $size ]['file'] : $row['file'];
		return $this->host->storageDir() . '/' . $file;
	}

	/** <img> with srcset over the stored sizes. */
	public function tag( array $row, string $size, string $sizesAttr = '(max-width: 600px) 100vw, 600px', bool $lazy = true ): string {
		$srcset = array();
		foreach ( $row['sizes'] as $name => $s ) {
			if ( 'thumb' !== $name ) {
				$srcset[] = $this->url( $row, $name ) . ' ' . (int) $s['width'] . 'w';
			}
		}
		$srcset[] = $this->url( $row ) . ' ' . (int) $row['width'] . 'w';
		$w        = isset( $row['sizes'][ $size ] ) ? $row['sizes'][ $size ]['width'] : $row['width'];
		$h        = isset( $row['sizes'][ $size ] ) ? $row['sizes'][ $size ]['height'] : $row['height'];
		return sprintf(
			'<img src="%s" %s width="%d" height="%d" alt="%s"%s decoding="async">',
			htmlspecialchars( $this->url( $row, $size ), ENT_QUOTES, 'UTF-8' ),
			'thumb' === $size ? '' : 'srcset="' . htmlspecialchars( implode( ', ', $srcset ), ENT_QUOTES, 'UTF-8' ) . '" sizes="' . htmlspecialchars( $sizesAttr, ENT_QUOTES, 'UTF-8' ) . '"',
			(int) $w,
			(int) $h,
			htmlspecialchars( (string) $row['alt'], ENT_QUOTES, 'UTF-8' ),
			$lazy ? ' loading="lazy"' : ''
		);
	}

	// ── Files ───────────────────────────────────────────────────

	private function removeFiles( array $row ): void {
		$paths = array( $this->path( $row ) );
		foreach ( array_keys( $row['sizes'] ) as $size ) {
			$paths[] = $this->path( $row, $size );
		}
		$root = realpath( $this->host->storageDir() );
		foreach ( array_unique( $paths ) as $p ) {
			$real = realpath( $p );
			if ( $real && $root && 0 === strpos( $real, $root ) && is_file( $real ) ) {
				@unlink( $real ); // only inside storage dir
			}
		}
	}

	/** Create a directory; the storage root also gets listing / script-execution guards. */
	private function ensureDir( string $dir ): bool {
		$root = $this->host->storageDir();
		if ( ! is_dir( $root ) && ! @mkdir( $root, 0755, true ) ) {
			return false;
		}
		if ( ! is_file( $root . '/index.html' ) ) {
			@file_put_contents( $root . '/index.html', '' );
		}
		if ( ! is_file( $root . '/.htaccess' ) ) {
			@file_put_contents( $root . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n  Require all denied\n</FilesMatch>\n" );
		}
		return ( is_dir( $dir ) || @mkdir( $dir, 0755, true ) ) && is_writable( $dir );
	}
}
