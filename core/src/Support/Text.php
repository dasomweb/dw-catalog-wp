<?php
namespace DWCatalog\Support;

/**
 * Input cleaning and small string helpers (multibyte-safe).
 */
final class Text {

	/** Single-line text: no tags, no control chars, trimmed, length-capped. */
	public static function line( $value, int $max = 500 ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = strip_tags( $value );
		$value = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );
		$value = trim( preg_replace( '/\s+/u', ' ', (string) $value ) );
		return self::cut( $value, $max );
	}

	/** Multi-line plain text: no tags, newlines kept. */
	public static function block( $value, int $max = 20000 ): string {
		$value = is_scalar( $value ) ? (string) $value : '';
		$value = strip_tags( $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value );
		$value = str_replace( array( "\r\n", "\r" ), "\n", (string) $value );
		return self::cut( trim( $value ), $max );
	}

	/** Lowercase key: a-z 0-9 _ (field keys, column keys). */
	public static function key( $value, int $max = 64 ): string {
		$value = strtolower( is_scalar( $value ) ? (string) $value : '' );
		return substr( preg_replace( '/[^a-z0-9_]/', '', $value ), 0, $max );
	}

	/** URL slug: letters/digits of any script, joined by "-". */
	public static function slug( $value, int $max = 190 ): string {
		$value = self::lower( self::line( $value, 1000 ) );
		$value = preg_replace( '/[^\p{L}\p{N}]+/u', '-', $value );
		return trim( self::cut( trim( (string) $value, '-' ), $max ), '-' );
	}

	public static function lower( string $value ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	public static function cut( string $value, int $max ): string {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $value, 0, $max, 'UTF-8' );
		}
		return substr( $value, 0, $max );
	}

	public static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value, 'UTF-8' ) : strlen( $value );
	}

	/** Plain-text summary of HTML (meta descriptions, excerpts). */
	public static function summary( string $html, int $max = 160 ): string {
		$text = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
		if ( self::length( $text ) <= $max ) {
			return $text;
		}
		return rtrim( self::cut( $text, $max - 1 ) ) . '…';
	}

	public static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}

	/** "value:Label,value2:Label2" or "a,b" → [ value => label ]. */
	public static function parseOptions( string $options ): array {
		$out = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $options ) ), 'strlen' ) as $pair ) {
			$parts = explode( ':', $pair, 2 );
			$val   = trim( $parts[0] );
			$out[ $val ] = isset( $parts[1] ) ? trim( $parts[1] ) : $val;
		}
		return $out;
	}
}
