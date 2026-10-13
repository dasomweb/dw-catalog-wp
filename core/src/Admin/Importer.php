<?php
namespace DWCatalog\Admin;

use DWCatalog\Catalog;
use DWCatalog\Fields\FieldConfig;
use DWCatalog\Fields\VariationColumns;
use DWCatalog\Media\Remote;
use DWCatalog\Support\Text;

/**
 * CSV → catalog (layout: CsvFormat — the same file the CSV export produces).
 *
 * - Rows are grouped into items by slug (or by title when there is no slug column); every row of
 *   an item may add one variation through the var_* columns.
 * - Existing items (same slug) are updated or skipped (`existing` = update | skip).
 * - Update only touches columns present in the file: a file with just slug + price changes prices.
 * - Images: URLs already stored for the item are kept as they are; new URLs are downloaded.
 * - Also accepted: post_title, post_content, post_status, category_name / category_slug,
 *   featured_image_url (older column names).
 */
class Importer {

	const MAX_ROWS = 20000;

	/** @var Catalog */
	private $c;

	public function __construct( Catalog $c ) {
		$this->c = $c;
	}

	/** @return string[] report lines */
	public function run( string $type, array $post, array $file ): array {
		$t = array( $this->c, 't' );
		if ( ! isset( $file['tmp_name'], $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return array( $t( 'File upload failed.' ) );
		}
		return $this->importFile( $type, $file['tmp_name'], $post );
	}

	/** Import from a local file (also used by tests). */
	public function importFile( string $type, string $path, array $post ): array {
		$t        = array( $this->c, 't' );
		$existing = isset( $post['existing'] ) && 'skip' === $post['existing'] ? 'skip' : 'update';
		$status   = isset( $post['status'] ) && 'draft' === $post['status'] ? 'draft' : 'publish';

		$fh = fopen( $path, 'r' );
		if ( ! $fh ) {
			return array( $t( 'Could not open file.' ) );
		}
		$first = (string) fgets( $fh );
		rewind( $fh );
		$delim = isset( $post['delimiter'] ) && '' !== $post['delimiter'] && 'auto' !== $post['delimiter'] ? CsvFormat::delimiter( $post['delimiter'] ) : CsvFormat::sniff( $first );

		$header = fgetcsv( $fh, 0, $delim, '"', '' );
		if ( ! $header ) {
			fclose( $fh );
			return array( $t( 'Could not read CSV headers.' ) );
		}
		$header = array_map( function ( $h, $i ) {
			$h = trim( (string) $h );
			return 0 === $i && "\xEF\xBB\xBF" === substr( $h, 0, 3 ) ? substr( $h, 3 ) : $h;
		}, $header, array_keys( $header ) );

		$titleField = $this->c->fields()->titleField( $type );
		$aliases    = array( 'post_title' => 'title', 'post_content' => 'content', 'post_status' => 'status', 'category_name' => 'category', 'featured_image_url' => 'image_url' );
		if ( $titleField ) {
			$aliases[ $titleField['meta_key'] ] = 'title';
		}
		$header = array_map( function ( $h ) use ( $aliases ) {
			return isset( $aliases[ $h ] ) ? $aliases[ $h ] : $h;
		}, $header );
		if ( ! in_array( 'title', $header, true ) && ! in_array( 'slug', $header, true ) ) {
			fclose( $fh );
			return array( $t( 'CSV must contain a "title" or "slug" column.' ) );
		}

		// ── Read and group rows into items ──
		$groups = array();
		$order  = array();
		$errors = array();
		$rowNum = 1;
		while ( false !== ( $row = fgetcsv( $fh, 0, $delim, '"', '' ) ) ) {
			$rowNum++;
			if ( $rowNum > self::MAX_ROWS + 1 ) {
				$errors[] = sprintf( $t( 'Stopped after %d rows — split larger files.' ), self::MAX_ROWS );
				break;
			}
			if ( array( null ) === $row || '' === trim( implode( '', $row ) ) ) {
				continue;
			}
			if ( count( $row ) < count( $header ) ) {
				$row = array_pad( $row, count( $header ), '' );
			}
			$data  = array_combine( $header, array_map( array( CsvFormat::class, 'readCell' ), array_slice( $row, 0, count( $header ) ) ) );
			$slug  = isset( $data['slug'] ) ? Text::slug( $data['slug'] ) : '';
			$title = isset( $data['title'] ) ? trim( $data['title'] ) : '';
			$key   = '' !== $slug ? 's:' . $slug : ( '' !== $title ? 't:' . Text::lower( $title ) : '' );
			if ( '' === $key ) {
				$errors[] = sprintf( $t( 'Row %d: slug or title is required.' ), $rowNum );
				continue;
			}
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array( 'row' => $rowNum, 'slug' => $slug, 'data' => null, 'vars' => array() );
				$order[]        = $key;
			}
			if ( null === $groups[ $key ]['data'] && ( '' !== $title || ! $this->onlyVariation( $data ) ) ) {
				$groups[ $key ]['data'] = $data; // first row with item columns
			}
			$groups[ $key ]['vars'][] = $data;
		}
		fclose( $fh );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		// ── Write ──
		$hasVar    = (bool) preg_grep( '/^' . CsvFormat::VAR . '/', $header );
		$cols      = $this->c->fields()->variationColumns( $type );
		$created   = 0;
		$updated   = 0;
		$skipped   = 0;
		$failed    = 0;
		$items     = $this->c->items();
		foreach ( $order as $key ) {
			$g    = $groups[ $key ];
			$data = null !== $g['data'] ? $g['data'] : $g['vars'][0];
			$item = '' !== $g['slug'] ? $items->getBySlug( $type, $g['slug'], false ) : $this->findByTitle( $type, isset( $data['title'] ) ? $data['title'] : '' );
			if ( $item && 'skip' === $existing ) {
				$skipped++;
				continue;
			}
			$items->beginBatch();
			try {
				$item = $this->saveItem( $type, $item, $g['slug'], $data, $header, $status );
				$id   = (int) $item['id'];
				if ( isset( $this->justCreated[ $id ] ) ) {
					$created++;
				} else {
					$updated++;
				}

				if ( $hasVar ) {
					$rows = array();
					foreach ( $g['vars'] as $v ) {
						$raw = array();
						foreach ( $cols as $col ) {
							$raw[ $col['key'] ] = isset( $v[ CsvFormat::VAR . $col['key'] ] ) ? $v[ CsvFormat::VAR . $col['key'] ] : '';
						}
						$clean = VariationColumns::sanitizeRow( $cols, $raw );
						if ( $clean ) {
							$rows[] = $clean;
						}
					}
					$items->saveVariations( $id, array_slice( $rows, 0, 500 ) );
				}
				foreach ( $this->images( $id, $data ) as $msg ) {
					$errors[] = sprintf( $t( 'Row %d: image skipped — %s' ), $g['row'], $msg );
				}
			} catch ( \InvalidArgumentException $e ) {
				$failed++;
				$errors[] = sprintf( $t( 'Row %d: %s' ), $g['row'], $t( $e->getMessage() ) );
			}
			$items->endBatch();
		}

		return array_merge(
			array( sprintf( $t( '%1$d created, %2$d updated, %3$d skipped, %4$d failed.' ), $created, $updated, $skipped, $failed ) ),
			array_slice( $errors, 0, 40 )
		);
	}

	/** @var array ids created in this run */
	private $justCreated = array();

	/** Create or update one item from its first row. Only columns present in the file are written. */
	private function saveItem( string $type, ?array $item, string $slug, array $data, array $header, string $defaultStatus ): array {
		$items = $this->c->items();
		$has   = array_flip( $header );
		$title = isset( $data['title'] ) && '' !== trim( $data['title'] ) ? $data['title'] : ( $item ? $item['title'] : '' );
		$row   = array(
			'id'      => $item ? (int) $item['id'] : 0,
			'type'    => $type,
			'title'   => $title,
			'slug'    => '' !== $slug ? $slug : ( $item ? $item['slug'] : '' ),
			'content' => isset( $has['content'] ) ? $this->c->host()->sanitizeHtml( (string) $data['content'] ) : ( $item ? $item['content'] : '' ),
			'excerpt' => isset( $has['excerpt'] ) ? $data['excerpt'] : ( $item ? $item['excerpt'] : '' ),
			'status'  => isset( $has['status'] ) && in_array( $data['status'], array( 'publish', 'draft' ), true ) ? $data['status'] : ( $item ? $item['status'] : $defaultStatus ),
		);
		if ( ! $item ) {
			$row['author_id'] = $this->c->host()->userId();
		}
		$id = $items->save( $row );
		if ( ! $item ) {
			$this->justCreated[ $id ] = true;
		}

		// Field values: merge — keep values of columns the file does not contain
		$values = $item ? $items->values( $id ) : array();
		foreach ( $this->c->fields()->fields( $type ) as $f ) {
			$k = $f['meta_key'];
			if ( ! empty( $f['is_title_field'] ) ) {
				$values[ $k ] = Text::line( $title, 255 );
			} elseif ( isset( $has[ $k ] ) ) {
				$values[ $k ] = FieldConfig::sanitizeValue( $f, isset( $data[ $k ] ) ? $data[ $k ] : '' );
			}
		}
		$items->saveValues( $id, $values );

		$terms = $this->c->terms();
		if ( isset( $has['category'] ) || isset( $has['category_slug'] ) ) {
			$ids = array();
			foreach ( array_filter( array_map( 'trim', explode( '|', (string) ( isset( $data['category'] ) ? $data['category'] : '' ) ) ), 'strlen' ) as $path ) {
				$ids[] = $terms->ensure( $type, Text::line( $path, 500 ) );
			}
			if ( ! $ids && ! empty( $data['category_slug'] ) ) {
				$ids[] = $terms->ensure( $type, (string) $data['category_slug'], 'category', (string) $data['category_slug'] );
			}
			$terms->setItem( $type, $id, 'category', $ids );
		}
		if ( isset( $has['tags'] ) ) {
			$tagIds = array();
			foreach ( array_slice( explode( ',', (string) $data['tags'] ), 0, 50 ) as $tag ) {
				$tag = Text::line( $tag, 100 );
				if ( '' !== $tag ) {
					$tagIds[] = $terms->ensure( $type, $tag, 'tag' );
				}
			}
			$terms->setItem( $type, $id, 'tag', $tagIds );
		}
		return $items->get( $id );
	}

	/** Download new image URLs; URLs that already point at this item's stored files are kept. */
	private function images( int $id, array $data ): array {
		$urls = isset( $data['image_url'] ) ? array_filter( array_map( 'trim', explode( '|', (string) $data['image_url'] ) ) ) : array();
		if ( ! $urls ) {
			return array();
		}
		$own = array();
		foreach ( $this->c->images()->forItem( $id ) as $img ) {
			foreach ( array_merge( array( 'large' ), array_keys( $img['sizes'] ) ) as $size ) {
				$own[ $this->c->images()->url( $img, $size ) ] = true;
			}
		}
		$storage = $this->c->host()->storageUrl() . '/';
		$errors  = array();
		foreach ( array_slice( $urls, 0, 10 ) as $url ) {
			if ( isset( $own[ $url ] ) || 0 === strpos( $url, $storage ) ) {
				continue; // already ours
			}
			$got = Remote::fetch( $url );
			if ( isset( $got['error'] ) ) {
				$errors[] = $this->c->t( $got['error'] );
				continue;
			}
			$stored = $this->c->images()->store( $id, $got['file'], (string) parse_url( $url, PHP_URL_PATH ) );
			@unlink( $got['file'] );
			if ( is_string( $stored ) ) {
				$errors[] = $this->c->t( $stored );
			}
		}
		return $errors;
	}

	/** A row that only carries var_* values (continuation of the previous item). */
	private function onlyVariation( array $data ): bool {
		foreach ( $data as $k => $v ) {
			if ( 'slug' !== $k && 0 !== strpos( $k, CsvFormat::VAR ) && '' !== trim( (string) $v ) ) {
				return false;
			}
		}
		return true;
	}

	private function findByTitle( string $type, string $title ): ?array {
		$title = Text::line( $title, 255 );
		if ( '' === $title ) {
			return null;
		}
		$db  = $this->c->db();
		$row = $db->selectRow( 'SELECT * FROM ' . $db->table( 'items' ) . ' WHERE type = ? AND status IN (?) AND title = ?', array( $type, array( 'publish', 'draft' ), $title ) );
		return $row ?: null;
	}
}
