<?php
namespace DWCatalog\Db;

class PostgresDialect implements Dialect {

	public function name(): string {
		return 'pgsql';
	}

	public function quoteIdent( string $name ): string {
		return '"' . str_replace( '"', '', $name ) . '"';
	}

	public function createTable( string $table, string $rawName, array $def ): array {
		$lines = array();
		foreach ( $def['columns'] as $col => $spec ) {
			$lines[] = $this->quoteIdent( $col ) . ' ' . $this->columnType( $spec );
		}
		$lines[] = 'PRIMARY KEY (' . $this->indexCols( $def['primary'] ) . ')';
		$sql     = array( "CREATE TABLE IF NOT EXISTS $table (\n  " . implode( ",\n  ", $lines ) . "\n)" );

		// Index names are schema-wide in PostgreSQL — prefix them with the table name.
		foreach ( isset( $def['unique'] ) ? $def['unique'] : array() as $name => $cols ) {
			$sql[] = 'CREATE UNIQUE INDEX IF NOT EXISTS ' . $this->quoteIdent( $rawName . '_' . $name ) . " ON $table (" . $this->indexCols( $cols ) . ')';
		}
		foreach ( isset( $def['index'] ) ? $def['index'] : array() as $name => $cols ) {
			$sql[] = 'CREATE INDEX IF NOT EXISTS ' . $this->quoteIdent( $rawName . '_' . $name ) . " ON $table (" . $this->indexCols( $cols ) . ')';
		}
		return $sql;
	}

	public function addColumn( string $table, string $column, array $spec ): array {
		return array( "ALTER TABLE $table ADD COLUMN IF NOT EXISTS " . $this->quoteIdent( $column ) . ' ' . $this->columnType( $spec ) );
	}

	public function addIndex( string $table, string $rawName, string $index, array $cols ): array {
		return array( 'CREATE INDEX IF NOT EXISTS ' . $this->quoteIdent( $rawName . '_' . $index ) . " ON $table (" . $this->indexCols( $cols ) . ')' );
	}

	/** PostgreSQL has no index prefix lengths — "slug(100)" indexes the whole column. */
	private function indexCols( array $cols ): string {
		return implode( ', ', array_map( function ( $c ) {
			return $this->quoteIdent( preg_replace( '/\(\d+\)$/', '', $c ) );
		}, $cols ) );
	}

	private function columnType( array $spec ): string {
		list( $type ) = $spec;
		$null          = ! empty( $spec['null'] ) ? 'NULL' : 'NOT NULL';
		switch ( true ) {
			case 'id' === $type:
				return 'BIGSERIAL';
			case 'bigint' === $type:
				return "BIGINT $null" . $this->def( $spec, '0' );
			case 'int' === $type:
				return "INTEGER $null" . $this->def( $spec, '0' );
			case 'smallint' === $type:
				return "SMALLINT $null" . $this->def( $spec, '0' );
			case 0 === strpos( $type, 'string:' ):
				return 'VARCHAR(' . (int) substr( $type, 7 ) . ") $null" . $this->def( $spec, "''" );
			case 'text' === $type:
				return "TEXT $null";
			case 'decimal' === $type:
				return 'NUMERIC(20,4) ' . $null;
			case 'datetime' === $type:
				return "TIMESTAMP $null";
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
