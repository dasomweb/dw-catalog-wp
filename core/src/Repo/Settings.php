<?php
namespace DWCatalog\Repo;

use DWCatalog\Db\Connection;

/**
 * Core configuration (field schema, variation columns, design, …) as JSON in the core's own
 * settings table — not in the host's option store.
 */
class Settings {

	/** @var Connection */
	private $db;

	/** @var array|null name => decoded value */
	private $cache = null;

	public function __construct( Connection $db ) {
		$this->db = $db;
	}

	public function get( string $name, $default = null ) {
		$this->load();
		return array_key_exists( $name, $this->cache ) ? $this->cache[ $name ] : $default;
	}

	public function set( string $name, $value ): void {
		$json = json_encode( $value, JSON_UNESCAPED_UNICODE );
		$this->db->transaction( function ( Connection $db ) use ( $name, $json ) {
			$db->execute( 'DELETE FROM ' . $db->table( 'settings' ) . ' WHERE name = ?', array( $name ) );
			$db->insert( 'settings', array( 'name' => $name, 'value' => $json ) );
		} );
		$this->load();
		$this->cache[ $name ] = $value;
	}

	public function delete( string $name ): void {
		$this->db->execute( 'DELETE FROM ' . $this->db->table( 'settings' ) . ' WHERE name = ?', array( $name ) );
		unset( $this->cache[ $name ] );
	}

	/** Bumped on every catalog write; part of cache keys so cached lists never go stale. */
	public function cacheVersion(): int {
		return (int) $this->get( 'cache_version', 1 );
	}

	/** Once per request is enough — caches are keyed by version, not by exact write. */
	public function bumpCacheVersion(): void {
		if ( $this->bumped ) {
			return;
		}
		$this->bumped = true;
		$this->set( 'cache_version', $this->cacheVersion() + 1 );
	}

	/** @var bool */
	private $bumped = false;

	private function load(): void {
		if ( null !== $this->cache ) {
			return;
		}
		$this->cache = array();
		// All settings are small; one query per request.
		foreach ( $this->db->select( 'SELECT name, value FROM ' . $this->db->table( 'settings' ) ) as $row ) {
			$decoded                     = json_decode( (string) $row['value'], true );
			$this->cache[ $row['name'] ] = null === $decoded && 'null' !== $row['value'] ? $row['value'] : $decoded;
		}
	}
}
