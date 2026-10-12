<?php
namespace DWCatalog\Db;

class MysqlDialect implements Dialect {

	/** @var string */
	private $tableOptions;

	public function __construct( string $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' ) {
		$this->tableOptions = $tableOptions;
	}

	public function name(): string {
		return 'mysql';
	}

	public function quoteIdent( string $name ): string {
		return '`' . str_replace( '`', '', $name ) . '`';
	}

	public function createTable( string $table, string $rawName, array $def ): array {
		$lines = array();
		foreach ( $def['columns'] as $col => $spec ) {
			$lines[] = $this->quoteIdent( $col ) . ' ' . $this->columnType( $spec );
		}
		$lines[] = 'PRIMARY KEY (' . $this->indexCols( $def['primary'] ) . ')';
		foreach ( isset( $def['unique'] ) ? $def['unique'] : array() as $name => $cols ) {
			$lines[] = 'UNIQUE KEY ' . $this->quoteIdent( $name ) . ' (' . $this->indexCols( $cols ) . ')';
		}
		foreach ( isset( $def['index'] ) ? $def['index'] : array() as $name => $cols ) {
			$lines[] = 'KEY ' . $this->quoteIdent( $name ) . ' (' . $this->indexCols( $cols ) . ')';
		}
		return array( "CREATE TABLE IF NOT EXISTS $table (\n  " . implode( ",\n  ", $lines ) . "\n) " . $this->tableOptions );
	}

	/** Columns with optional MySQL prefix length: "slug(100)". Keeps keys under 767 bytes. */
	private function indexCols( array $cols ): string {
		return implode( ', ', array_map( function ( $c ) {
			if ( preg_match( '/^([a-z_]+)\((\d+)\)$/', $c, $m ) ) {
				return $this->quoteIdent( $m[1] ) . '(' . $m[2] . ')';
			}
			return $this->quoteIdent( $c );
		}, $cols ) );
	}

	private function columnType( array $spec ): string {
		list( $type ) = $spec;
		$null          = ! empty( $spec['null'] ) ? 'NULL' : 'NOT NULL';
		switch ( true ) {
			case 'id' === $type:
				return 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT';
			case 'bigint' === $type:
				return "BIGINT UNSIGNED $null" . $this->def( $spec, '0' );
			case 'int' === $type:
				return "INT $null" . $this->def( $spec, '0' );
			case 'smallint' === $type:
				return "SMALLINT $null" . $this->def( $spec, '0' );
			case 0 === strpos( $type, 'string:' ):
				return 'VARCHAR(' . (int) substr( $type, 7 ) . ") $null" . $this->def( $spec, "''" );
			case 'text' === $type:
				return "LONGTEXT $null";
			case 'decimal' === $type:
				return 'DECIMAL(20,4) ' . $null;
			case 'datetime' === $type:
				return "DATETIME $null";
		}
		throw new \InvalidArgumentException( 'Unknown column type ' . $type );
	}

	private function def( array $spec, string $fallback ): string {
		if ( ! empty( $spec['null'] ) ) {
			return '';
		}
		return ' DEFAULT ' . ( array_key_exists( 'default', $spec ) ? $spec['default'] : $fallback );
	}
}
