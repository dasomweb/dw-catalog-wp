<?php
namespace DWCatalog\Admin;

use DWCatalog\Catalog;
use DWCatalog\Fields\FieldConfig;
use DWCatalog\Media\Remote;
use DWCatalog\Support\Text;

/**
 * CSV import.
 *
 * Columns: title (or the title field key / post_title), content, excerpt, status, category
 * ("Parent > Child"; category_name / category_slug also accepted), tags ("a, b"),
 * image_url (several separated by "|"; featured_image_url also accepted), and any field key.
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
		$delims    = array( 'semicolon' => ';', 'comma' => ',', 'tab' => "\t" );
		$delimiter = isset( $post['delimiter'], $delims[ $post['delimiter'] ] ) ? $delims[ $post['delimiter'] ] : ';';
		$status    = isset( $post['status'] ) && 'draft' === $post['status'] ? 'draft' : 'publish';
		$skipDup   = ! empty( $post['skip_duplicates'] );

		$fh = fopen( $file['tmp_name'], 'r' );
		if ( ! $fh ) {
			return array( $t( 'Could not open file.' ) );
		}
		$headers = fgetcsv( $fh, 0, $delimiter );
		if ( ! $headers ) {
			fclose( $fh );
			return array( $t( 'Could not read CSV headers.' ) );
		}
		$headers = array_map( function ( $h, $i ) {
			$h = trim( (string) $h );
			return 0 === $i && "\xEF\xBB\xBF" === substr( $h, 0, 3 ) ? substr( $h, 3 ) : $h; // BOM
		}, $headers, array_keys( $headers ) );

		$fields     = $this->c->fields()->fields( $type );
		$titleField = $this->c->fields()->titleField( $type );
		$titleKeys  = array_filter( array( 'title', 'post_title', $titleField ? $titleField['meta_key'] : '' ) );
		if ( ! array_intersect( $titleKeys, $headers ) ) {
			fclose( $fh );
			return array( sprintf( $t( 'CSV must contain a "%s" column.' ), implode( '" / "', $titleKeys ) ) );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 );
		}

		$imported = 0;
		$skipped  = 0;
		$failed   = 0;
		$errors   = array();
		$rowNum   = 1;
		$items    = $this->c->items();

		while ( false !== ( $row = fgetcsv( $fh, 0, $delimiter ) ) && $rowNum <= self::MAX_ROWS ) {
			$rowNum++;
			if ( array( null ) === $row ) {
				continue; // blank line
			}
			if ( count( $row ) !== count( $headers ) ) {
				$failed++;
				$errors[] = sprintf( $t( 'Row %d: column count mismatch.' ), $rowNum );
				continue;
			}
			$data  = array_combine( $headers, $row );
			$title = '';
			foreach ( $titleKeys as $k ) {
				if ( isset( $data[ $k ] ) && '' !== trim( $data[ $k ] ) ) {
					$title = trim( $data[ $k ] );
					break;
				}
			}
			if ( '' === $title ) {
				$failed++;
				$errors[] = sprintf( $t( 'Row %d: title is required.' ), $rowNum );
				continue;
			}
			if ( $skipDup && $this->exists( $type, Text::line( $title, 255 ) ) ) {
				$skipped++;
				continue;
			}

			$items->beginBatch();
			try {
				$rowStatus = isset( $data['status'] ) ? $data['status'] : ( isset( $data['post_status'] ) ? $data['post_status'] : $status );
				$id        = $items->save( array(
					'type'      => $type,
					'title'     => $title,
					'content'   => $this->c->host()->sanitizeHtml( isset( $data['content'] ) ? $data['content'] : ( isset( $data['post_content'] ) ? $data['post_content'] : '' ) ),
					'excerpt'   => isset( $data['excerpt'] ) ? $data['excerpt'] : '',
					'status'    => in_array( $rowStatus, array( 'publish', 'draft' ), true ) ? $rowStatus : $status,
					'author_id' => $this->c->host()->userId(),
				) );

				$values = array();
				foreach ( $fields as $f ) {
					if ( ! empty( $f['is_title_field'] ) ) {
						$values[ $f['meta_key'] ] = Text::line( $title, 255 );
					} elseif ( isset( $data[ $f['meta_key'] ] ) ) {
						$values[ $f['meta_key'] ] = FieldConfig::sanitizeValue( $f, $data[ $f['meta_key'] ] );
					}
				}
				$items->saveValues( $id, $values );

				$cat = isset( $data['category'] ) ? $data['category'] : ( isset( $data['category_name'] ) ? $data['category_name'] : '' );
				$cat = Text::line( $cat, 500 );
				if ( '' !== $cat || ! empty( $data['category_slug'] ) ) {
					$termId = $this->c->terms()->ensure( $type, '' !== $cat ? $cat : $data['category_slug'], 'category', isset( $data['category_slug'] ) ? $data['category_slug'] : '' );
					$this->c->terms()->setItem( $type, $id, 'category', array( $termId ) );
				}
				if ( ! empty( $data['tags'] ) ) {
					$tagIds = array();
					foreach ( array_slice( explode( ',', $data['tags'] ), 0, 50 ) as $tag ) {
						$tag = Text::line( $tag, 100 );
						if ( '' !== $tag ) {
							$tagIds[] = $this->c->terms()->ensure( $type, $tag, 'tag' );
						}
					}
					$this->c->terms()->setItem( $type, $id, 'tag', $tagIds );
				}

				$urls = isset( $data['image_url'] ) ? $data['image_url'] : ( isset( $data['featured_image_url'] ) ? $data['featured_image_url'] : '' );
				foreach ( array_slice( array_filter( array_map( 'trim', explode( '|', (string) $urls ) ) ), 0, 10 ) as $url ) {
					$got = Remote::fetch( $url );
					if ( isset( $got['error'] ) ) {
						$errors[] = sprintf( $t( 'Row %d: image skipped — %s' ), $rowNum, $t( $got['error'] ) );
						continue;
					}
					$stored = $this->c->images()->store( $id, $got['file'], (string) parse_url( $url, PHP_URL_PATH ) );
					@unlink( $got['file'] );
					if ( is_string( $stored ) ) {
						$errors[] = sprintf( $t( 'Row %d: image skipped — %s' ), $rowNum, $t( $stored ) );
					}
				}
				$imported++;
			} catch ( \InvalidArgumentException $e ) {
				$failed++;
				$errors[] = sprintf( $t( 'Row %d: %s' ), $rowNum, $t( $e->getMessage() ) );
			}
			$items->endBatch();
		}
		fclose( $fh );

		$report = array( sprintf( $t( '%1$d imported, %2$d skipped (duplicates), %3$d failed.' ), $imported, $skipped, $failed ) );
		if ( $rowNum > self::MAX_ROWS ) {
			$report[] = sprintf( $t( 'Stopped after %d rows — split larger files.' ), self::MAX_ROWS );
		}
		return array_merge( $report, array_slice( $errors, 0, 40 ) );
	}

	private function exists( string $type, string $title ): bool {
		$db = $this->c->db();
		return (bool) $db->selectValue(
			'SELECT id FROM ' . $db->table( 'items' ) . ' WHERE type = ? AND status IN (?) AND title = ?',
			array( $type, array( 'publish', 'draft' ), $title )
		);
	}
}
