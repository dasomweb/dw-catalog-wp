<?php
namespace DWCatalog\Db;

/**
 * SQL differences between databases. Core queries are written in the common subset
 * (LIMIT/OFFSET, `col IS NULL` ordering, IN subqueries); only identifiers and DDL vary.
 */
interface Dialect {

	/** 'mysql' | 'pgsql' */
	public function name(): string;

	public function quoteIdent( string $name ): string;

	/**
	 * DDL statements that create a table (and its indexes) if missing.
	 *
	 * @param string $table quoted full table name
	 * @param array  $def   see Schema::tables()
	 * @return string[]
	 */
	public function createTable( string $table, string $rawName, array $def ): array;
}
