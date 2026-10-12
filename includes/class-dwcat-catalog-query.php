<?php
/**
 * Catalog Query — shared search / filter / sort for the admin list and the frontend catalog.
 *
 * Runs on the indexed DWCAT_Store tables (never wp_postmeta). Callers describe what they want
 * as a filter array; build_args() returns WP_Query args carrying it in the VAR_NAME var, and
 * posts_clauses() turns that into indexed SQL:
 *
 *   search   string   Word-prefix match per word (all words must match), via {prefix}dwcat_search.
 *   cats     int[]    Category term IDs (OR, children included).
 *   base_cat string   Shortcode-fixed category slugs/IDs (always applied).
 *   meta     array    field_key => string[] (OR within a field, AND across fields).
 *   sort     string   One of get_sort_options() keys, or "meta:{key}:{asc|desc}".
 *   public   bool     Frontend: only public fields may be searched / filtered / sorted, so
 *                     internal values cannot be probed from the site.
 *
 * Input limits (abuse / DoS guards): MAX_WORDS search words, MAX_VALUES values per filter.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Catalog_Query {

	const VAR_NAME   = 'dwcat_filter';
	const MAX_WORDS  = 6;
	const MAX_VALUES = 50;

	public static function init() {
		add_filter( 'posts_clauses', array( __CLASS__, 'posts_clauses' ), 10, 2 );
	}

	/**
	 * @param string $post_type Our post type slug.
	 * @param array  $filter    See class doc.
	 * @param array  $base      Base WP_Query args (posts_per_page, paged, post_status ...).
	 * @return array
	 */
	public static function build_args( $post_type, $filter, $base = array() ) {
		$filter = wp_parse_args( $filter, array(
			'search'   => '',
			'cats'     => array(),
			'base_cat' => '',
			'meta'     => array(),
			'sort'     => 'date_desc',
			'public'   => false,
		) );

		$args = array_merge( array( 'post_type' => $post_type ), $base );

		// Categories — native taxonomy query (term tables are indexed)
		$tax_query  = array();
		$cat_tax    = DWCAT_Config::get_category_taxonomy( $post_type );
		$base_terms = self::base_terms( $filter['base_cat'] );
		if ( $base_terms ) {
			$tax_query[] = array(
				'taxonomy' => $cat_tax,
				'field'    => is_numeric( reset( $base_terms ) ) ? 'term_id' : 'slug',
				'terms'    => $base_terms,
			);
		}
		$cats = array_slice( array_filter( array_map( 'intval', (array) $filter['cats'] ) ), 0, self::MAX_VALUES );
		if ( $cats ) {
			$tax_query[] = array(
				'taxonomy'         => $cat_tax,
				'field'            => 'term_id',
				'terms'            => $cats,
				'include_children' => true,
			);
		}
		if ( $tax_query ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query;
		}

		// Everything on custom fields goes through posts_clauses()
		$allowed = self::allowed_fields( $post_type, $filter['public'] );
		$spec    = array(
			'words'  => self::search_words( $filter['search'] ),
			'meta'   => array(),
			'sort'   => null,
			'public' => (bool) $filter['public'],
		);
		foreach ( (array) $filter['meta'] as $key => $values ) {
			$values = array_slice( array_values( array_filter( array_map( 'strval', (array) $values ), 'strlen' ) ), 0, self::MAX_VALUES );
			if ( isset( $allowed[ $key ] ) && $values ) {
				$spec['meta'][ $key ] = $values;
			}
		}

		$sort = self::resolve_sort( $post_type, $filter['sort'], $filter['public'] );
		if ( $sort['meta_key'] ) {
			$spec['sort'] = $sort;
		} else {
			$args['orderby'] = array( $sort['orderby'] => $sort['order'] );
		}

		if ( $spec['words'] || $spec['meta'] || $spec['sort'] ) {
			$args[ self::VAR_NAME ] = $spec;
		}
		return $args;
	}

	/**
	 * WP_Query SQL for search / field filters / field sort — all indexed lookups.
	 */
	public static function posts_clauses( $clauses, $query ) {
		$spec = $query->get( self::VAR_NAME );
		if ( ! is_array( $spec ) ) {
			return $clauses;
		}
		global $wpdb;
		$post_type = (string) $query->get( 'post_type' );

		// Search: every word must prefix-match a token (from public sources only, on the site)
		if ( ! empty( $spec['words'] ) ) {
			$sources    = ! empty( $spec['public'] ) ? self::public_sources( $post_type ) : array();
			$source_sql = $sources ? ' AND source IN (' . implode( ',', array_fill( 0, count( $sources ), '%s' ) ) . ')' : '';
			foreach ( $spec['words'] as $word ) {
				$clauses['where'] .= $wpdb->prepare(
					" AND {$wpdb->posts}.ID IN ( SELECT post_id FROM " . DWCAT_Store::table( 'search' ) . " WHERE token LIKE %s{$source_sql} )", // phpcs:ignore
					array_merge( array( $wpdb->esc_like( $word ) . '%' ), $sources )
				);
			}
		}

		// Field filters — (field_key, value_idx) index
		foreach ( (array) $spec['meta'] as $key => $values ) {
			$in                = implode( ',', array_fill( 0, count( $values ), '%s' ) );
			$clauses['where'] .= $wpdb->prepare(
				" AND {$wpdb->posts}.ID IN ( SELECT post_id FROM " . DWCAT_Store::table( 'values' ) . " WHERE field_key = %s AND value_idx IN ($in) )", // phpcs:ignore
				array_merge( array( $key ), $values )
			);
		}

		// Field sort — items without a value go last in both directions
		if ( ! empty( $spec['sort'] ) ) {
			$col                = 'NUMERIC' === $spec['sort']['meta_type'] ? 'dwcat_sv.value_num' : 'dwcat_sv.value_idx';
			$dir                = 'ASC' === $spec['sort']['order'] ? 'ASC' : 'DESC';
			$clauses['join']   .= $wpdb->prepare(
				' LEFT JOIN ' . DWCAT_Store::table( 'values' ) . " dwcat_sv ON dwcat_sv.post_id = {$wpdb->posts}.ID AND dwcat_sv.field_key = %s", // phpcs:ignore
				$spec['sort']['meta_key']
			);
			$clauses['orderby'] = "{$col} IS NULL ASC, {$col} {$dir}, {$wpdb->posts}.post_title ASC";
		}
		return $clauses;
	}

	/**
	 * Sort choices. Number fields get Low→High / High→Low entries (public ones only on the site).
	 *
	 * @return array key => label
	 */
	public static function get_sort_options( $post_type, $public = true ) {
		$options = array(
			'date_desc'  => __( 'Newest', 'dw-catalog-wp' ),
			'date_asc'   => __( 'Oldest', 'dw-catalog-wp' ),
			'title_asc'  => __( 'Name: A → Z', 'dw-catalog-wp' ),
			'title_desc' => __( 'Name: Z → A', 'dw-catalog-wp' ),
		);
		foreach ( self::allowed_fields( $post_type, $public ) as $f ) {
			if ( 'number' === $f['type'] ) {
				/* translators: %s: field label */
				$options[ 'meta:' . $f['meta_key'] . ':asc' ] = sprintf( __( '%s: Low → High', 'dw-catalog-wp' ), $f['label'] );
				/* translators: %s: field label */
				$options[ 'meta:' . $f['meta_key'] . ':desc' ] = sprintf( __( '%s: High → Low', 'dw-catalog-wp' ), $f['label'] );
			}
		}
		return $options;
	}

	public static function resolve_sort( $post_type, $sort, $public = false ) {
		$out = array( 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => '', 'meta_type' => 'CHAR' );

		if ( preg_match( '/^meta:([a-z0-9_]+):(asc|desc)$/', (string) $sort, $m ) ) {
			$fields = self::allowed_fields( $post_type, $public );
			if ( isset( $fields[ $m[1] ] ) ) {
				$out['meta_key']  = $m[1];
				$out['meta_type'] = 'number' === $fields[ $m[1] ]['type'] ? 'NUMERIC' : 'CHAR';
				$out['order']     = strtoupper( $m[2] );
			}
			return $out;
		}

		$map = array(
			'date_desc'     => array( 'date', 'DESC' ),
			'date_asc'      => array( 'date', 'ASC' ),
			'title_asc'     => array( 'title', 'ASC' ),
			'title_desc'    => array( 'title', 'DESC' ),
			'modified_desc' => array( 'modified', 'DESC' ),
			'modified_asc'  => array( 'modified', 'ASC' ),
		);
		if ( isset( $map[ $sort ] ) ) {
			list( $out['orderby'], $out['order'] ) = $map[ $sort ];
		}
		return $out;
	}

	/**
	 * Choices for a field filter: select options, or distinct stored values (cached until the
	 * next catalog write). Callers pass only fields they are allowed to expose.
	 *
	 * @return array value => label
	 */
	public static function get_filter_choices( $post_type, $field, $base_cat = '', $limit = 200 ) {
		if ( 'select' === $field['type'] ) {
			return DWCAT_Config::parse_select_options( $field['options'] );
		}

		$cache_key = 'dwcat_fc_' . md5( $post_type . '|' . $field['meta_key'] . '|' . $base_cat . '|' . DWCAT_Store::cache_version() );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$join = '';
		$tt   = self::base_term_taxonomy_ids( $post_type, $base_cat );
		if ( null !== $tt ) {
			if ( ! $tt ) {
				return array();
			}
			$join = " INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = v.post_id AND tr.term_taxonomy_id IN (" . implode( ',', array_map( 'intval', $tt ) ) . ')';
		}

		$values  = $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT v.value_idx FROM ' . DWCAT_Store::table( 'values' ) . " v
			 INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id AND p.post_type = %s AND p.post_status = 'publish'{$join}
			 WHERE v.field_key = %s AND v.value_idx <> ''
			 ORDER BY v.value_idx ASC LIMIT %d", // phpcs:ignore — $join holds intval'd IDs only
			$post_type,
			$field['meta_key'],
			(int) $limit
		) );
		$choices = $values ? array_combine( $values, $values ) : array();
		set_transient( $cache_key, $choices, 12 * HOUR_IN_SECONDS );
		return $choices;
	}

	/**
	 * Fields a query may touch. On the site only public fields ("Detail" checked) — fields that
	 * stay in the admin must not be filterable, sortable or searchable from outside.
	 *
	 * @return array meta_key => field
	 */
	public static function allowed_fields( $post_type, $public ) {
		$out = array();
		foreach ( DWCAT_Config::get_fields( $post_type ) as $f ) {
			if ( ! $public || DWCAT_Config::is_public_field( $f ) ) {
				$out[ $f['meta_key'] ] = $f;
			}
		}
		return $out;
	}

	/** Search-index sources visitors may match: title, public fields, public variation columns. */
	public static function public_sources( $post_type ) {
		$sources = array( '_title' );
		foreach ( DWCAT_Config::get_fields( $post_type ) as $f ) {
			if ( DWCAT_Config::is_public_field( $f ) ) {
				$sources[] = $f['meta_key'];
			}
		}
		foreach ( DWCAT_Config::get_variation_columns( $post_type ) as $c ) {
			if ( DWCAT_Variations::is_public_column( $c ) ) {
				$sources[] = '_var_' . $c['key'];
			}
		}
		return $sources;
	}

	/** Visitor search text → at most MAX_WORDS words (1-letter words dropped when others exist). */
	public static function search_words( $search ) {
		$words = DWCAT_Store::tokenize( substr( (string) $search, 0, 200 ) );
		if ( count( $words ) > 1 ) {
			$words = array_values( array_filter( $words, function ( $w ) {
				return ( function_exists( 'mb_strlen' ) ? mb_strlen( $w, 'UTF-8' ) : strlen( $w ) ) > 1;
			} ) );
		}
		return array_slice( $words, 0, self::MAX_WORDS );
	}

	private static function base_terms( $base_cat ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $base_cat ) ) ) );
	}

	/** term_taxonomy_ids of the fixed category and its children; null when none is fixed. */
	private static function base_term_taxonomy_ids( $post_type, $base_cat ) {
		$terms = self::base_terms( $base_cat );
		if ( ! $terms ) {
			return null;
		}
		$tax = DWCAT_Config::get_category_taxonomy( $post_type );
		$ids = array();
		foreach ( $terms as $t ) {
			$term = is_numeric( $t ) ? get_term( (int) $t, $tax ) : get_term_by( 'slug', $t, $tax );
			if ( ! $term || is_wp_error( $term ) ) {
				continue;
			}
			$ids[] = (int) $term->term_taxonomy_id;
			foreach ( (array) get_term_children( $term->term_id, $tax ) as $child_id ) {
				$child = get_term( $child_id, $tax );
				if ( $child && ! is_wp_error( $child ) ) {
					$ids[] = (int) $child->term_taxonomy_id;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/** @return array meta_key => field (all fields — admin use). */
	public static function fields_by_key( $post_type ) {
		return self::allowed_fields( $post_type, false );
	}
}
