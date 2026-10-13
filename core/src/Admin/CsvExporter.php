<?php
namespace DWCatalog\Admin;

use DWCatalog\Catalog;

/**
 * Catalog → CSV (layout: CsvFormat). Includes every field, internal ones too — this is an admin
 * export guarded by the catalog.export capability, not a visitor feature.
 */
class CsvExporter {

	/** @var Catalog */
	private $c;

	public function __construct( Catalog $c ) {
		$this->c = $c;
	}

	/** @param array $post cats[] (optional), status (any|publish|draft), delimiter, template (1 = header + example only) */
	public function run( string $type, array $post ): array {
		$delim  = CsvFormat::delimiter( isset( $post['delimiter'] ) ? $post['delimiter'] : 'comma' );
		$header = CsvFormat::header( $this->c, $type );
		$out    = fopen( 'php://temp', 'w+' );
		fwrite( $out, "\xEF\xBB\xBF" ); // UTF-8 BOM so Excel reads Korean etc. correctly
		fputcsv( $out, $header, $delim, '"', '' );

		if ( ! empty( $post['template'] ) ) {
			$this->example( $out, $type, $header, $delim );
		} else {
			$this->rows( $out, $type, $header, $delim, $post );
		}

		rewind( $out );
		$body = stream_get_contents( $out );
		fclose( $out );
		$name = $type . ( ! empty( $post['template'] ) ? '-import-template' : '-catalog-' . gmdate( 'Y-m-d-His' ) ) . '.csv';
		return array( 'download' => array( $name, 'text/csv; charset=utf-8', $body ) );
	}

	private function rows( $out, string $type, array $header, string $delim, array $post ): void {
		$cats   = array_values( array_filter( array_map( 'intval', isset( $post['cats'] ) ? (array) $post['cats'] : array() ) ) );
		$status = isset( $post['status'] ) && in_array( $post['status'], array( 'publish', 'draft' ), true ) ? $post['status'] : 'any';
		$fields = $this->c->fields()->fieldsByKey( $type );
		$cols   = $this->c->fields()->variationColumns( $type );
		$terms  = $this->c->terms();

		for ( $page = 1; ; $page++ ) {
			$r = $this->c->query()->run( $type, array( 'cats' => $cats, 'status' => $status, 'sort' => 'title_asc', 'page' => $page, 'per_page' => 100 ) );
			if ( ! $r['rows'] ) {
				break;
			}
			$ids = array_map( 'intval', array_column( $r['rows'], 'id' ) );
			$this->c->images()->primeItems( $ids );
			$terms->primeItems( $type, $ids );

			foreach ( $r['rows'] as $item ) {
				$id   = (int) $item['id'];
				$base = array(
					'slug'      => $item['slug'],
					'title'     => $item['title'],
					'status'    => $item['status'],
					'category'  => implode( CsvFormat::SEP, array_map( function ( $t ) use ( $terms, $type ) {
						return $this->path( $type, $t );
					}, $terms->ofItem( $type, $id ) ) ),
					'tags'      => implode( ', ', array_column( $terms->ofItem( $type, $id, 'tag' ), 'name' ) ),
					'content'   => $item['content'],
					'excerpt'   => $item['excerpt'],
					'image_url' => implode( CsvFormat::SEP, array_map( function ( $img ) {
						return $this->c->images()->url( $img, 'large' );
					}, $this->c->images()->forItem( $id ) ) ),
				);
				foreach ( $fields as $key => $f ) {
					if ( empty( $f['is_title_field'] ) ) {
						$base[ $key ] = $this->c->items()->value( $id, $key );
					}
				}
				$vars = $this->c->items()->variations( $id );
				foreach ( $vars ? $vars : array( array() ) as $i => $var ) {
					$row = 0 === $i ? $base : array( 'slug' => $item['slug'] );
					foreach ( $cols as $col ) {
						$row[ CsvFormat::VAR . $col['key'] ] = isset( $var[ $col['key'] ] ) ? $var[ $col['key'] ] : '';
					}
					$this->put( $out, $header, $row, $delim );
				}
			}
			if ( $page >= $r['pages'] ) {
				break;
			}
		}
	}

	/** One example item with two variations — shows the layout. */
	private function example( $out, string $type, array $header, string $delim ): void {
		$row = array( 'slug' => 'sample-item', 'title' => 'Sample Item', 'status' => 'publish', 'category' => 'Parent Category > Child Category', 'tags' => 'tag one, tag two', 'content' => 'Description', 'excerpt' => 'Short summary', 'image_url' => 'https://example.com/photo.jpg' );
		foreach ( $this->c->fields()->fields( $type ) as $f ) {
			if ( empty( $f['is_title_field'] ) ) {
				$row[ $f['meta_key'] ] = 'number' === $f['type'] ? '10' : ( 'select' === $f['type'] ? (string) key( \DWCatalog\Support\Text::parseOptions( $f['options'] ) ) : '' );
			}
		}
		$second = array( 'slug' => 'sample-item' );
		foreach ( $this->c->fields()->variationColumns( $type ) as $i => $col ) {
			$val = 'number' === $col['type'] ? '10' : ( 'select' === $col['type'] ? (string) key( \DWCatalog\Support\Text::parseOptions( $col['options'] ) ) : 'A' );
			$row[ CsvFormat::VAR . $col['key'] ]    = $val;
			$second[ CsvFormat::VAR . $col['key'] ] = 'number' === $col['type'] ? '20' : ( 'select' === $col['type'] ? $val : 'B' );
		}
		$this->put( $out, $header, $row, $delim );
		$this->put( $out, $header, $second, $delim );
	}

	private function put( $out, array $header, array $row, string $delim ): void {
		$line = array();
		foreach ( $header as $col ) {
			$line[] = CsvFormat::safeCell( isset( $row[ $col ] ) ? $row[ $col ] : '' );
		}
		fputcsv( $out, $line, $delim, '"', '' );
	}

	/** "Parent > Child" for a term. */
	private function path( string $type, array $term ): string {
		$names = array( $term['name'] );
		$seen  = array( (int) $term['id'] => true );
		$p     = (int) $term['parent'];
		while ( $p && ! isset( $seen[ $p ] ) ) {
			$parent = $this->c->terms()->get( $type, $p );
			if ( ! $parent ) {
				break;
			}
			array_unshift( $names, $parent['name'] );
			$seen[ $p ] = true;
			$p          = (int) $parent['parent'];
		}
		return implode( ' > ', $names );
	}
}
