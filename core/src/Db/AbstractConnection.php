<?php
namespace DWCatalog\Db;

/**
 * Shared parts of Connection implementations: placeholder expansion, convenience reads,
 * batched inserts, table naming.
 */
abstract class AbstractConnection implements Connection {

	/** @var string e.g. "wp_dwcat_" */
	protected $prefix;

	/** @var Dialect */
	protected $dialect;

	public function __construct( string $prefix, Dialect $dialect ) {
		if ( ! preg_match( '/^[A-Za-z0-9_]*$/', $prefix ) ) {
			throw new \InvalidArgumentException( 'Invalid table prefix.' );
		}
		$this->prefix  = $prefix;
		$this->dialect = $dialect;
	}

	public function dialect(): Dialect {
		return $this->dialect;
	}

	public function table( string $name ): string {
		if ( ! preg_match( '/^[a-z_]+$/', $name ) ) {
			throw new \InvalidArgumentException( 'Invalid table name.' );
		}
		return $this->dialect->quoteIdent( $this->prefix . $name );
	}

	public function tableExists( string $name ): bool {
		$schema = 'pgsql' === $this->dialect->name() ? 'current_schema()' : 'DATABASE()';
		return (bool) $this->selectValue(
			"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = $schema AND table_name = ?",
			array( $this->prefix . $name )
		);
	}

	public function selectRow( string $sql, array $params = array() ): ?array {
		$rows = $this->select( $sql, $params );
		return $rows ? $rows[0] : null;
	}

	public function selectValue( string $sql, array $params = array() ) {
		$row = $this->selectRow( $sql, $params );
		return $row ? reset( $row ) : null;
	}

	public function selectColumn( string $sql, array $params = array() ): array {
		$out = array();
		foreach ( $this->select( $sql, $params ) as $row ) {
			$out[] = reset( $row );
		}
		return $out;
	}

	public function insertMany( string $table, array $columns, array $rows ): void {
		if ( ! $rows ) {
			return;
		}
		$cols   = implode( ', ', array_map( array( $this->dialect, 'quoteIdent' ), $columns ) );
		$tuple  = '(' . implode( ', ', array_fill( 0, count( $columns ), '?' ) ) . ')';
		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			$params = array();
			foreach ( $chunk as $row ) {
				foreach ( $columns as $i => $c ) {
					$params[] = array_key_exists( $c, $row ) ? $row[ $c ] : $row[ $i ];
				}
			}
			$this->execute(
				'INSERT INTO ' . $this->table( $table ) . " ($cols) VALUES " . implode( ', ', array_fill( 0, count( $chunk ), $tuple ) ),
				$params
			);
		}
	}

	/**
	 * Expand array parameters into "?, ?, ?" lists.
	 *
	 * @return array [ string $sql, array $flatParams ]
	 */
	protected function expand( string $sql, array $params ): array {
		$parts = explode( '?', $sql );
		if ( count( $parts ) - 1 !== count( $params ) ) {
			throw new \InvalidArgumentException( 'Placeholder count does not match parameter count.' );
		}
		$out  = $parts[0];
		$flat = array();
		foreach ( array_values( $params ) as $i => $p ) {
			if ( is_array( $p ) ) {
				if ( ! $p ) {
					$out .= 'NULL'; // IN (NULL) matches nothing — safe empty list
				} else {
					$out .= implode( ', ', array_fill( 0, count( $p ), '?' ) );
					foreach ( $p as $v ) {
						$flat[] = $v;
					}
				}
			} else {
				$out   .= '?';
				$flat[] = $p;
			}
			$out .= $parts[ $i + 1 ];
		}
		return array( $out, $flat );
	}
}
