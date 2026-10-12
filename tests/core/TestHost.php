<?php
/**
 * In-memory Host for core integration tests.
 */

use DWCatalog\Host\Host;

class TestHost implements Host {

	public $user     = 1;
	public $caps     = null; // null = all
	public $denied   = array();
	public $cache    = array();
	public $dir;

	public function __construct( string $dir ) {
		$this->dir = $dir;
	}

	public function userId(): int {
		return $this->user;
	}

	public function can( string $capability ): bool {
		return $this->user > 0 && ( null === $this->caps || in_array( $capability, $this->caps, true ) );
	}

	public function allows( string $feature ): bool {
		return ! in_array( $feature, $this->denied, true );
	}

	public function secret(): string {
		return 'test-secret-0123456789';
	}

	public function translate( string $text ): string {
		return $text;
	}

	public function sanitizeHtml( string $html ): string {
		return strip_tags( $html, '<p><br><strong><em><ul><ol><li><a><h2><h3>' );
	}

	public function richText( string $name, string $html ): string {
		return '<textarea name="' . htmlspecialchars( $name ) . '">' . htmlspecialchars( $html ) . '</textarea>';
	}

	public function storageDir(): string {
		return $this->dir;
	}

	public function storageUrl(): string {
		return 'https://test.example/files';
	}

	public function adminUrl( array $params = array() ): string {
		return 'https://test.example/admin?' . http_build_query( array_merge( array( 'page' => 'catalog' ), $params ) );
	}

	public function adminPostUrl(): string {
		return 'https://test.example/admin-post';
	}

	public function assetUrl( string $file ): string {
		return 'https://test.example/assets/' . $file;
	}

	public function version(): string {
		return 'test';
	}

	public function remember( string $key, int $ttl, callable $fn ) {
		if ( ! array_key_exists( $key, $this->cache ) ) {
			$this->cache[ $key ] = $fn();
		}
		return $this->cache[ $key ];
	}
}
