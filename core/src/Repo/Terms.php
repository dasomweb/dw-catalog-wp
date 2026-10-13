<?php
namespace DWCatalog\Repo;

use DWCatalog\Db\Connection;
use DWCatalog\Support\Text;

/**
 * Categories (tree) and tags per catalog type.
 * Term row: id, type, taxonomy (category|tag), parent, name, slug, position.
 */
class Terms {

	const TAXONOMIES = array( 'category', 'tag' );

	/** @var Connection */
	private $db;
	/** @var Settings */
	private $settings;

	/** "type|taxonomy" => [ id => row ] */
	private $all = array();
	/** item_id => [ taxonomy => [ term rows ] ] */
	private $itemTerms = array();

	public function __construct( Connection $db, Settings $settings ) {
		$this->db       = $db;
		$this->settings = $settings;
	}

	/** @return array id => term row, ordered by position, name */
	public function all( string $type, string $taxonomy = 'category' ): array {
		$k = $type . '|' . $taxonomy;
		if ( ! isset( $this->all[ $k ] ) ) {
			$this->all[ $k ] = array();
			foreach ( $this->db->select( 'SELECT * FROM ' . $this->db->table( 'terms' ) . ' WHERE type = ? AND taxonomy = ? ORDER BY position ASC, name ASC', array( $type, $taxonomy ) ) as $row ) {
				$this->all[ $k ][ (int) $row['id'] ] = $row;
			}
		}
		return $this->all[ $k ];
	}

	public function get( string $type, int $id, string $taxonomy = 'category' ): ?array {
		$all = $this->all( $type, $taxonomy );
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	public function bySlug( string $type, string $slug, string $taxonomy = 'category' ): ?array {
		foreach ( $this->all( $type, $taxonomy ) as $row ) {
			if ( $row['slug'] === $slug ) {
				return $row;
			}
		}
		return null;
	}

	/** Resolve "slug" / "id" references (comma list) to term ids. */
	public function resolve( string $type, string $refs, string $taxonomy = 'category' ): array {
		$ids = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $refs ) ), 'strlen' ) as $ref ) {
			$row = ctype_digit( $ref ) ? $this->get( $type, (int) $ref, $taxonomy ) : $this->bySlug( $type, $ref, $taxonomy );
			if ( $row ) {
				$ids[] = (int) $row['id'];
			}
		}
		return $ids;
	}

	/** @return int[] the term and every descendant */
	public function withDescendants( string $type, array $ids, string $taxonomy = 'category' ): array {
		$children = array();
		foreach ( $this->all( $type, $taxonomy ) as $row ) {
			$children[ (int) $row['parent'] ][] = (int) $row['id'];
		}
		$out   = array();
		$stack = array_map( 'intval', $ids );
		while ( $stack ) {
			$id = array_pop( $stack );
			if ( isset( $out[ $id ] ) ) {
				continue; // cycle guard
			}
			$out[ $id ] = true;
			if ( isset( $children[ $id ] ) ) {
				$stack = array_merge( $stack, $children[ $id ] );
			}
		}
		return array_keys( $out );
	}

	/** parent_id => [ rows ] for tree rendering */
	public function tree( string $type, string $taxonomy = 'category' ): array {
		$tree = array();
		foreach ( $this->all( $type, $taxonomy ) as $row ) {
			$tree[ (int) $row['parent'] ][] = $row;
		}
		return $tree;
	}

	/** Published item counts per term (direct assignments), cached per request. */
	public function counts( string $type, string $taxonomy = 'category' ): array {
		$out = array();
		$sql = 'SELECT it.term_id, COUNT(*) AS n FROM ' . $this->db->table( 'item_terms' ) . ' it'
			. ' INNER JOIN ' . $this->db->table( 'items' ) . ' i ON i.id = it.item_id AND i.type = ? AND i.status = ?'
			. ' INNER JOIN ' . $this->db->table( 'terms' ) . ' t ON t.id = it.term_id AND t.taxonomy = ?'
			. ' GROUP BY it.term_id';
		foreach ( $this->db->select( $sql, array( $type, 'publish', $taxonomy ) ) as $r ) {
			$out[ (int) $r['term_id'] ] = (int) $r['n'];
		}
		return $out;
	}

	/** Create or update. @return int id */
	public function save( string $type, string $taxonomy, string $name, string $slug = '', int $parent = 0, int $id = 0, int $position = 0 ): int {
		if ( ! in_array( $taxonomy, self::TAXONOMIES, true ) ) {
			throw new \InvalidArgumentException( 'Unknown taxonomy.' );
		}
		$name = Text::line( $name, 200 );
		if ( '' === $name ) {
			throw new \InvalidArgumentException( 'Name is required.' );
		}
		$slug = Text::slug( '' !== trim( $slug ) ? $slug : $name );
		$slug = $this->uniqueSlug( $type, $taxonomy, '' !== $slug ? $slug : 'term', $id );
		if ( $parent && ( $parent === $id || ! $this->get( $type, $parent, $taxonomy ) || ( $id && in_array( $parent, $this->withDescendants( $type, array( $id ), $taxonomy ), true ) ) ) ) {
			$parent = 0; // invalid or would create a cycle
		}
		$row = array( 'type' => $type, 'taxonomy' => $taxonomy, 'parent' => $parent, 'name' => $name, 'slug' => $slug, 'position' => $position );
		if ( $id && $this->get( $type, $id, $taxonomy ) ) {
			$this->db->execute(
				'UPDATE ' . $this->db->table( 'terms' ) . ' SET parent = ?, name = ?, slug = ?, position = ? WHERE id = ?',
				array( $parent, $name, $slug, $position, $id )
			);
		} else {
			$id = $this->db->insert( 'terms', $row );
		}
		$this->flush( $type );
		return $id;
	}

	/**
	 * Re-arrange the whole tree at once (drag & drop): $nodes = [ [ id, parent ], ... ] in display
	 * order. Unknown ids are ignored, a parent that is not a known term (or would create a cycle)
	 * becomes top level, positions are renumbered per parent in the given order. Terms not listed
	 * keep their place. @return int number of terms changed
	 */
	public function arrange( string $type, array $nodes, string $taxonomy = 'category' ): int {
		$all    = $this->all( $type, $taxonomy );
		$parent = array();
		$order  = array();
		foreach ( array_slice( $nodes, 0, 5000 ) as $n ) {
			$id = is_array( $n ) && isset( $n['id'] ) ? (int) $n['id'] : 0;
			if ( isset( $all[ $id ] ) && ! isset( $parent[ $id ] ) ) {
				$p             = isset( $n['parent'] ) ? (int) $n['parent'] : 0;
				$parent[ $id ] = ( $p !== $id && isset( $all[ $p ] ) ) ? $p : 0;
				$order[]       = $id;
			}
		}
		// Effective parent: the new one if listed, else the stored one
		$parentOf = function ( $id ) use ( $parent, $all ) {
			return isset( $parent[ $id ] ) ? $parent[ $id ] : (int) $all[ $id ]['parent'];
		};
		foreach ( $order as $id ) {
			$seen = array( $id => true );
			for ( $p = $parentOf( $id ); $p; $p = $parentOf( $p ) ) {
				if ( isset( $seen[ $p ] ) ) {
					$parent[ $id ] = 0; // cycle → top level
					break;
				}
				$seen[ $p ] = true;
			}
		}
		$pos     = array();
		$changes = array();
		foreach ( $order as $id ) {
			$p   = $parent[ $id ];
			$at  = isset( $pos[ $p ] ) ? $pos[ $p ] : 0;
			$pos[ $p ] = $at + 1;
			if ( (int) $all[ $id ]['parent'] !== $p || (int) $all[ $id ]['position'] !== $at ) {
				$changes[ $id ] = array( $p, $at );
			}
		}
		if ( $changes ) {
			$this->db->transaction( function () use ( $changes ) {
				foreach ( $changes as $id => $c ) {
					$this->db->execute( 'UPDATE ' . $this->db->table( 'terms' ) . ' SET parent = ?, position = ? WHERE id = ?', array( $c[0], $c[1], $id ) );
				}
			} );
			$this->flush( $type );
		}
		return count( $changes );
	}

	/** Find by name/slug or create (CSV import). Supports "Parent > Child". */
	public function ensure( string $type, string $path, string $taxonomy = 'category', string $slug = '' ): int {
		$parent = 0;
		$id     = 0;
		$parts  = array_values( array_filter( array_map( 'trim', explode( '>', $path ) ), 'strlen' ) );
		foreach ( $parts as $i => $name ) {
			$want = ( $i === count( $parts ) - 1 && '' !== $slug ) ? Text::slug( $slug ) : Text::slug( $name );
			$id   = 0;
			foreach ( $this->all( $type, $taxonomy ) as $row ) {
				if ( (int) $row['parent'] === $parent && ( $row['slug'] === $want || 0 === strcasecmp( $row['name'], $name ) ) ) {
					$id = (int) $row['id'];
					break;
				}
			}
			if ( ! $id ) {
				$id = $this->save( $type, $taxonomy, $name, $want, $parent );
			}
			$parent = $id;
		}
		return $id;
	}

	/** Delete; children move up to the deleted term's parent. */
	public function delete( string $type, int $id, string $taxonomy = 'category' ): void {
		$row = $this->get( $type, $id, $taxonomy );
		if ( ! $row ) {
			return;
		}
		$this->db->execute( 'UPDATE ' . $this->db->table( 'terms' ) . ' SET parent = ? WHERE parent = ? AND type = ? AND taxonomy = ?', array( (int) $row['parent'], $id, $type, $taxonomy ) );
		$this->db->execute( 'DELETE FROM ' . $this->db->table( 'item_terms' ) . ' WHERE term_id = ?', array( $id ) );
		$this->db->execute( 'DELETE FROM ' . $this->db->table( 'terms' ) . ' WHERE id = ?', array( $id ) );
		$this->flush( $type );
	}

	// ── Item ↔ term ─────────────────────────────────────────────

	public function primeItems( string $type, array $itemIds ): void {
		$ids = array_values( array_diff( array_unique( array_map( 'intval', $itemIds ) ), array_keys( $this->itemTerms ), array( 0 ) ) );
		if ( ! $ids ) {
			return;
		}
		foreach ( $ids as $id ) {
			$this->itemTerms[ $id ] = array();
		}
		$byId = array();
		foreach ( self::TAXONOMIES as $tax ) {
			$byId += $this->all( $type, $tax );
		}
		foreach ( array_chunk( $ids, 1000 ) as $chunk ) {
			foreach ( $this->db->select( 'SELECT item_id, term_id FROM ' . $this->db->table( 'item_terms' ) . ' WHERE item_id IN (?)', array( $chunk ) ) as $r ) {
				$term = isset( $byId[ (int) $r['term_id'] ] ) ? $byId[ (int) $r['term_id'] ] : null;
				if ( $term ) {
					$this->itemTerms[ (int) $r['item_id'] ][ $term['taxonomy'] ][] = $term;
				}
			}
		}
	}

	/** @return array term rows */
	public function ofItem( string $type, int $itemId, string $taxonomy = 'category' ): array {
		$this->primeItems( $type, array( $itemId ) );
		return isset( $this->itemTerms[ $itemId ][ $taxonomy ] ) ? $this->itemTerms[ $itemId ][ $taxonomy ] : array();
	}

	public function setItem( string $type, int $itemId, string $taxonomy, array $termIds ): void {
		$valid = array_values( array_intersect( array_unique( array_map( 'intval', $termIds ) ), array_keys( $this->all( $type, $taxonomy ) ) ) );
		$own   = array_keys( $this->all( $type, $taxonomy ) );
		if ( $own ) {
			$this->db->execute( 'DELETE FROM ' . $this->db->table( 'item_terms' ) . ' WHERE item_id = ? AND term_id IN (?)', array( $itemId, $own ) );
		}
		$this->db->insertMany( 'item_terms', array( 'item_id', 'term_id' ), array_map( function ( $tid ) use ( $itemId ) {
			return array( 'item_id' => $itemId, 'term_id' => $tid );
		}, $valid ) );
		unset( $this->itemTerms[ $itemId ] );
		$this->settings->bumpCacheVersion();
	}

	private function uniqueSlug( string $type, string $taxonomy, string $slug, int $selfId ): string {
		$base  = $slug;
		$taken = array();
		foreach ( $this->all( $type, $taxonomy ) as $row ) {
			if ( (int) $row['id'] !== $selfId ) {
				$taken[ $row['slug'] ] = true;
			}
		}
		for ( $n = 2; isset( $taken[ $slug ] ); $n++ ) {
			$slug = Text::cut( $base, 180 ) . '-' . $n;
		}
		return $slug;
	}

	private function flush( string $type ): void {
		foreach ( self::TAXONOMIES as $tax ) {
			unset( $this->all[ $type . '|' . $tax ] );
		}
		$this->itemTerms = array();
		$this->settings->bumpCacheVersion();
	}
}
