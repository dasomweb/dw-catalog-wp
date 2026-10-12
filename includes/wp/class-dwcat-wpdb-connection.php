<?php
/**
 * Core DB connection over WordPress's $wpdb — the zero-configuration default: catalog tables
 * live in the WordPress database under "{$wpdb->prefix}dwcat_".
 *
 * Placeholders "?" become %d / %f / %s for $wpdb->prepare(); NULL is inlined as SQL NULL.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Wpdb_Connection extends \DWCatalog\Db\AbstractConnection {

	/** @var wpdb */
	private $wpdb;

	public function __construct( wpdb $wpdb, string $prefix ) {
		$this->wpdb = $wpdb;
		parent::__construct( $prefix, new \DWCatalog\Db\MysqlDialect( 'ENGINE=InnoDB ' . $wpdb->get_charset_collate() ) );
	}

	public function select( string $sql, array $params = array() ): array {
		$rows = $this->wpdb->get_results( $this->sql( $sql, $params ), ARRAY_A );
		$this->check();
		return is_array( $rows ) ? $rows : array();
	}

	public function execute( string $sql, array $params = array() ): int {
		$result = $this->wpdb->query( $this->sql( $sql, $params ) );
		$this->check();
		return (int) $result;
	}

	public function insert( string $table, array $row ): int {
		$cols = array_keys( $row );
		$this->execute(
			'INSERT INTO ' . $this->table( $table ) . ' (' . implode( ', ', array_map( array( $this->dialect, 'quoteIdent' ), $cols ) ) . ') VALUES (' . implode( ', ', array_fill( 0, count( $cols ), '?' ) ) . ')',
			array_values( $row )
		);
		return \DWCatalog\Db\Schema::hasAutoId( $table ) ? (int) $this->wpdb->insert_id : 0;
	}

	public function transaction( callable $fn ) {
		$this->wpdb->query( 'START TRANSACTION' );
		try {
			$result = $fn( $this );
			$this->wpdb->query( 'COMMIT' );
			return $result;
		} catch ( \Throwable $e ) {
			$this->wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	private function sql( string $sql, array $params ): string {
		list( $sql, $flat ) = $this->expand( $sql, $params );
		if ( ! $flat ) {
			return $sql;
		}
		// SQL text segments get "%" escaped as "%%" so only our placeholders reach prepare().
		$parts = explode( '?', $sql );
		$out   = str_replace( '%', '%%', $parts[0] );
		$plain = $parts[0];
		$args  = array();
		foreach ( $flat as $i => $v ) {
			if ( null === $v ) {
				$out   .= 'NULL';
				$plain .= 'NULL';
			} elseif ( is_int( $v ) || is_bool( $v ) ) {
				$out   .= '%d';
				$args[] = (int) $v;
			} else {
				$out   .= '%s';
				$args[] = (string) $v;
			}
			$out   .= str_replace( '%', '%%', $parts[ $i + 1 ] );
			$plain .= $parts[ $i + 1 ];
		}
		return $args ? $this->wpdb->prepare( $out, $args ) : $plain; // only NULLs → no prepare needed
	}

	private function check(): void {
		if ( '' !== $this->wpdb->last_error ) {
			$err                    = $this->wpdb->last_error;
			$this->wpdb->last_error = '';
			throw new \RuntimeException( 'Catalog database error: ' . $err );
		}
	}
}
