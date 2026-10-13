<?php
namespace DWCatalog\Admin;

use DWCatalog\Catalog;

/**
 * The catalog CSV layout — shared by export and import so a file round-trips:
 * export → edit in a spreadsheet → import (update) gives back the same catalog.
 *
 *   slug · title · status · category · tags · content · excerpt · image_url · {field keys} · var_{column}…
 *
 * One row per variation (Shopify style): rows with the same slug are one item. The first row of
 * an item carries the item columns; following rows may leave them empty and only fill var_*.
 * Several categories / image URLs are separated by " | "; a category path is "Parent > Child".
 */
final class CsvFormat {

	const BASE   = array( 'slug', 'title', 'status', 'category', 'tags', 'content', 'excerpt', 'image_url' );
	const VAR    = 'var_';
	const SEP    = ' | ';

	/** Header for a catalog type: base columns, every field (except the name field), variation columns. */
	public static function header( Catalog $c, string $type ): array {
		$h = self::BASE;
		foreach ( $c->fields()->fields( $type ) as $f ) {
			if ( empty( $f['is_title_field'] ) ) {
				$h[] = $f['meta_key'];
			}
		}
		foreach ( $c->fields()->variationColumns( $type ) as $col ) {
			$h[] = self::VAR . $col['key'];
		}
		return $h;
	}

	public static function delimiter( $key ): string {
		$map = array( 'comma' => ',', 'semicolon' => ';', 'tab' => "\t" );
		return isset( $map[ $key ] ) ? $map[ $key ] : ',';
	}

	/** Spreadsheet formula injection guard: =, +, -, @, tab, CR at the start are made literal. */
	public static function safeCell( $value ): string {
		$value = (string) $value;
		return ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) && ! is_numeric( $value ) ) ? "'" . $value : $value;
	}

	/** Undo safeCell() on import. */
	public static function readCell( $value ): string {
		$value = (string) $value;
		return ( strlen( $value ) > 1 && "'" === $value[0] && false !== strpos( "=+-@\t\r", $value[1] ) ) ? substr( $value, 1 ) : $value;
	}

	/** Detect the delimiter from the header line when the user did not pick one. */
	public static function sniff( string $line ): string {
		$best = ',';
		$max  = 0;
		foreach ( array( ',', ';', "\t" ) as $d ) {
			$n = substr_count( $line, $d );
			if ( $n > $max ) {
				$max  = $n;
				$best = $d;
			}
		}
		return $best;
	}
}
