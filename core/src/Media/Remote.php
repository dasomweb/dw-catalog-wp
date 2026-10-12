<?php
namespace DWCatalog\Media;

/**
 * Download a remote image for CSV imports — SSRF-safe:
 *  - http/https only, ports 80/443 only
 *  - every hop (redirects followed manually, max 3) must resolve to a public IP
 *  - size cap, timeout
 */
final class Remote {

	const MAX_BYTES = 15728640;

	/** @return string|array temp file path, or [ 'error' => message ] */
	public static function fetch( string $url ) {
		for ( $hop = 0; $hop < 4; $hop++ ) {
			$check = self::check( $url );
			if ( true !== $check ) {
				return array( 'error' => $check );
			}
			$result = self::get( $url );
			if ( isset( $result['redirect'] ) ) {
				$url = self::absolute( $url, $result['redirect'] );
				continue;
			}
			return $result;
		}
		return array( 'error' => 'Too many redirects.' );
	}

	/** @return true|string */
	private static function check( string $url ) {
		$p = parse_url( $url );
		if ( ! $p || empty( $p['host'] ) || ! in_array( strtolower( isset( $p['scheme'] ) ? $p['scheme'] : '' ), array( 'http', 'https' ), true ) ) {
			return 'Only http(s) URLs are allowed.';
		}
		if ( isset( $p['port'] ) && ! in_array( (int) $p['port'], array( 80, 443 ), true ) ) {
			return 'Only standard ports are allowed.';
		}
		if ( isset( $p['user'] ) || isset( $p['pass'] ) ) {
			return 'Credentials in URLs are not allowed.';
		}
		$ips = @gethostbynamel( $p['host'] );
		if ( ! $ips ) {
			return 'Host not found.';
		}
		foreach ( $ips as $ip ) {
			if ( false === filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return 'Private or reserved addresses are not allowed.';
			}
		}
		return true;
	}

	private static function get( string $url ): array {
		$tmp = tempnam( sys_get_temp_dir(), 'dwc' );
		$fh  = fopen( $tmp, 'wb' );
		if ( ! function_exists( 'curl_init' ) ) {
			return array( 'error' => 'cURL is required for image import.' );
		}
		$ch    = curl_init( $url );
		$bytes = 0;
		curl_setopt_array( $ch, array(
			CURLOPT_FOLLOWLOCATION   => false,
			CURLOPT_PROTOCOLS        => CURLPROTO_HTTP | CURLPROTO_HTTPS,
			CURLOPT_CONNECTTIMEOUT   => 10,
			CURLOPT_TIMEOUT          => 30,
			CURLOPT_USERAGENT        => 'DW-Catalog-Importer/1.0',
			CURLOPT_HEADER           => false,
			CURLOPT_FILE             => $fh,
			CURLOPT_NOPROGRESS       => false,
			CURLOPT_PROGRESSFUNCTION => function ( $ch, $dlTotal, $dl ) {
				return $dl > self::MAX_BYTES ? 1 : 0; // abort oversized downloads
			},
		) );
		$ok       = curl_exec( $ch );
		$status   = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$location = (string) curl_getinfo( $ch, CURLINFO_REDIRECT_URL );
		curl_close( $ch );
		fclose( $fh );

		if ( $status >= 300 && $status < 400 && '' !== $location ) {
			@unlink( $tmp );
			return array( 'redirect' => $location );
		}
		if ( ! $ok || 200 !== $status ) {
			@unlink( $tmp );
			return array( 'error' => 'Download failed (HTTP ' . $status . ').' );
		}
		return array( 'file' => $tmp );
	}

	private static function absolute( string $base, string $location ): string {
		if ( preg_match( '#^https?://#i', $location ) ) {
			return $location;
		}
		$p = parse_url( $base );
		return $p['scheme'] . '://' . $p['host'] . ( '/' === substr( $location, 0, 1 ) ? '' : '/' ) . ltrim( $location, '/' );
	}
}
