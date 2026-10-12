<?php
namespace DWCatalog\Db;

/**
 * Database access used by the whole core.
 *
 * SQL uses `?` placeholders only. An array parameter expands to a list for IN (...):
 *   $db->select( 'SELECT * FROM t WHERE id IN (?) AND type = ?', [ [1, 2, 3], 'product' ] );
 * Values are always bound/escaped by the implementation — never concatenated by callers.
 * Table names come from table() (host prefix + 'dwcat_' + name), never from input.
 */
interface Connection {

	/** @return array[] rows as associative arrays */
	public function select( string $sql, array $params = array() ): array;

	/** @return array|null first row */
	public function selectRow( string $sql, array $params = array() ): ?array;

	/** @return mixed first column of first row (null when no row) */
	public function selectValue( string $sql, array $params = array() );

	/** @return array first column of every row */
	public function selectColumn( string $sql, array $params = array() ): array;

	/** INSERT/UPDATE/DELETE/DDL. @return int affected rows */
	public function execute( string $sql, array $params = array() ): int;

	/** Insert one row. @return int new auto-increment id (0 when the table has none) */
	public function insert( string $table, array $row ): int;

	/** Insert many rows into the same columns in one statement (chunked by the caller). */
	public function insertMany( string $table, array $columns, array $rows ): void;

	/** @return mixed whatever $fn returns; rolls back and rethrows on exception */
	public function transaction( callable $fn );

	/** Full, quoted table name for a core table, e.g. table('items') → `wp_dwcat_items`. */
	public function table( string $name ): string;

	public function dialect(): Dialect;

	/** Whether a core table exists (no error logged when it does not). */
	public function tableExists( string $name ): bool;

	public function columnExists( string $table, string $column ): bool;
}
