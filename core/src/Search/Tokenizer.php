<?php
namespace DWCatalog\Search;

use DWCatalog\Support\Text;

/**
 * Words for the search index and for queries — both sides use the same rules.
 * Lowercased, split on anything that is not a letter or digit (any script).
 * With $compact, codes like "WK-009" or "1/4.4lb/6pk" also yield "wk009" / "144lb6pk".
 */
final class Tokenizer {

	const TOKEN_LEN = 64;

	/** @return string[] */
	public static function tokenize( string $text, bool $compact = false ): array {
		$text   = Text::lower( $text );
		$tokens = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( $compact ) {
			foreach ( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $chunk ) {
				$joined = preg_replace( '/[^\p{L}\p{N}]+/u', '', $chunk );
				if ( '' !== $joined && $joined !== $chunk ) {
					$tokens[] = $joined;
				}
			}
		}
		$out = array();
		foreach ( (array) $tokens as $t ) {
			$out[ Text::cut( $t, self::TOKEN_LEN ) ] = true;
		}
		return array_map( 'strval', array_keys( $out ) );
	}

	/** Visitor input → at most $max words; 1-letter words dropped when others exist. */
	public static function queryWords( string $search, int $max = 6 ): array {
		$words = self::tokenize( Text::cut( $search, 200 ) );
		if ( count( $words ) > 1 ) {
			$words = array_values( array_filter( $words, function ( $w ) {
				return Text::length( $w ) > 1;
			} ) );
		}
		return array_slice( $words, 0, $max );
	}
}
