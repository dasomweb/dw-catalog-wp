<?php
namespace DWCatalog\Support;

/**
 * Output escaping for core templates. Every dynamic value printed by the core goes through
 * one of these — templates never echo raw input.
 */
final class Html {

	public static function e( $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}

	public static function attr( $text ): string {
		return self::e( $text );
	}

	/** Only http(s), protocol-relative-free absolute paths, query strings and fragments pass. */
	public static function url( $url ): string {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( preg_match( '#^(https?:)?//#i', $url ) && ! preg_match( '#^https?://#i', $url ) ) {
			return ''; // protocol-relative
		}
		if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $url ) && ! preg_match( '#^https?:#i', $url ) ) {
			return ''; // javascript:, data:, ...
		}
		return self::e( $url );
	}

	/** JSON for a <script type="application/ld+json"> block (no </script> breakout). */
	public static function json( $data ): string {
		return (string) json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP );
	}

	/** Query string from params (arrays allowed). */
	public static function query( array $params ): string {
		return http_build_query( $params, '', '&', PHP_QUERY_RFC3986 );
	}
}
