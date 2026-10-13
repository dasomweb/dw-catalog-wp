<?php
namespace DWCatalog\Admin;

use DWCatalog\Catalog;

/**
 * Numbers and lookups for the admin screens: status counts, shared item codes,
 * distinct values (brand suggestions). Admin only — every value here may be internal.
 */
class ItemStats {

	/** @var Catalog */
	private $c;

	public function __construct( Catalog $c ) {
		$this->c = $c;
	}

	/** @return array total, publish, draft */
	public function counts( string $type ): array {
		$out = array( 'total' => 0, 'publish' => 0, 'draft' => 0 );
		foreach ( $this->c->db()->select( 'SELECT status, COUNT(*) AS n FROM ' . $this->c->db()->table( 'items' ) . ' WHERE type = ? GROUP BY status', array( $type ) ) as $r ) {
			$out[ $r['status'] ] = (int) $r['n'];
			$out['total']       += (int) $r['n'];
		}
		return $out;
	}

	/** @return array top, sub — category counts */
	public function categoryCounts( string $type ): array {
		$top = 0;
		$sub = 0;
		foreach ( $this->c->terms()->all( $type ) as $t ) {
			(int) $t['parent'] ? $sub++ : $top++;
		}
		return array( 'top' => $top, 'sub' => $sub );
	}

	/** Values of $key used by more than one item. @return array value => item count */
	public function shared( string $type, string $key ): array {
		$db  = $this->c->db();
		$out = array();
		$sql = 'SELECT v.value_idx, COUNT(*) AS n FROM ' . $db->table( 'values' ) . ' v'
			. ' INNER JOIN ' . $db->table( 'items' ) . ' i ON i.id = v.item_id AND i.type = ?'
			. ' WHERE v.field_key = ? GROUP BY v.value_idx HAVING COUNT(*) > 1';
		foreach ( $db->select( $sql, array( $type, $key ) ) as $r ) {
			$out[ (string) $r['value_idx'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Other items using the same value. @return array rows id, title */
	public function usedBy( string $type, string $key, string $value, int $exceptId, int $limit = 5 ): array {
		if ( '' === $value ) {
			return array();
		}
		$db = $this->c->db();
		return $db->select(
			'SELECT i.id, i.title FROM ' . $db->table( 'values' ) . ' v INNER JOIN ' . $db->table( 'items' ) . ' i ON i.id = v.item_id AND i.type = ?'
			. ' WHERE v.field_key = ? AND v.value_idx = ? AND v.item_id <> ? ORDER BY i.id LIMIT ?',
			array( $type, $key, $value, $exceptId, $limit )
		);
	}

	/** Distinct non-empty values of a field, alphabetical. @return string[] */
	public function distinct( string $type, string $key, int $limit = 200 ): array {
		$db = $this->c->db();
		return array_map( 'strval', $db->selectColumn(
			'SELECT DISTINCT v.value_idx FROM ' . $db->table( 'values' ) . ' v INNER JOIN ' . $db->table( 'items' ) . ' i ON i.id = v.item_id AND i.type = ?'
			. ' WHERE v.field_key = ? ORDER BY v.value_idx LIMIT ?',
			array( $type, $key, $limit )
		) );
	}
}
