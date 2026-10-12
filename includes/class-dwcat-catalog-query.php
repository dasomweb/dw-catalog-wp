<?php
/**
 * Catalog Query — shared search / filter / sort for the admin list and frontend grid.
 *
 * Both sides describe what they want as a normalized filter array and get WP_Query args
 * back, so search and sort behave the same in wp-admin and on the site.
 *
 *   search   string   Matches post title and every text-like custom field (item code, brand ...).
 *   cats     int[]    Category term IDs (OR).
 *   base_cat string   Shortcode-fixed category slugs/IDs, comma separated (always applied).
 *   meta     array    meta_key => string[] values (OR within a field, AND across fields).
 *   sort     string   One of get_sort_options() keys.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Catalog_Query {

	/** Query var that carries the search term into the posts_where filter. */
	const SEARCH_VAR = 'dwcat_search';

	/** Field types whose values are searched as text. */
	const SEARCHABLE_TYPES = array( 'text', 'textarea', 'select', 'email', 'url' );

	public static function init() {
		add_filter( 'posts_where', array( __CLASS__, 'filter_search_where' ), 10, 2 );
	}

	/**
	 * Build WP_Query args.
	 *
	 * @param string $post_type Our post type slug.
	 * @param array  $filter    See class doc.
	 * @param array  $base      Base args merged first (posts_per_page, paged, post_status ...).
	 * @return array
	 */
	public static function build_args( $post_type, $filter, $base = array() ) {
		$filter = wp_parse_args( $filter, array(
			'search'   => '',
			'cats'     => array(),
			'base_cat' => '',
			'meta'     => array(),
			'sort'     => 'date_desc',
		) );

		$args = array_merge( array( 'post_type' => $post_type ), $base );

		// Search — title + text-like custom fields (handled in filter_search_where)
		if ( '' !== $filter['search'] ) {
			$args[ self::SEARCH_VAR ] = $filter['search'];
		}

		// Categories
		$tax_query = array();
		$cat_tax   = DWCAT_Config::get_category_taxonomy( $post_type );
		if ( '' !== (string) $filter['base_cat'] ) {
			$terms = array_filter( array_map( 'trim', explode( ',', $filter['base_cat'] ) ) );
			if ( $terms ) {
				$tax_query[] = array(
					'taxonomy' => $cat_tax,
					'field'    => is_numeric( reset( $terms ) ) ? 'term_id' : 'slug',
					'terms'    => $terms,
				);
			}
		}
		$cats = array_filter( array_map( 'intval', (array) $filter['cats'] ) );
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

		// Field filters — only keys that are real fields of this post type
		$meta_query = array();
		$fields     = self::fields_by_key( $post_type );
		foreach ( (array) $filter['meta'] as $key => $values ) {
			$values = array_values( array_filter( array_map( 'strval', (array) $values ), 'strlen' ) );
			if ( ! isset( $fields[ $key ] ) || ! $values ) {
				continue;
			}
			$meta_query[] = array(
				'key'     => $key,
				'value'   => $values,
				'compare' => 'IN',
			);
		}

		// Sort
		$sort = self::resolve_sort( $post_type, $filter['sort'] );
		if ( $sort['meta_key'] ) {
			// Named EXISTS / NOT EXISTS pair so items without a value are still listed, not dropped.
			$meta_query['dwcat_sort'] = array(
				'relation'        => 'OR',
				'dwcat_sort_has'  => array( 'key' => $sort['meta_key'], 'compare' => 'EXISTS', 'type' => $sort['meta_type'] ),
				'dwcat_sort_none' => array( 'key' => $sort['meta_key'], 'compare' => 'NOT EXISTS' ),
			);
			$args['orderby'] = array( 'dwcat_sort_has' => $sort['order'], 'title' => 'ASC' );
		} else {
			$args['orderby'] = array( $sort['orderby'] => $sort['order'] );
		}

		if ( $meta_query ) {
			$meta_query['relation'] = 'AND';
			$args['meta_query']     = $meta_query;
		}

		return $args;
	}

	/**
	 * posts_where — title OR any searchable custom field contains the term.
	 */
	public static function filter_search_where( $where, $query ) {
		$term = $query->get( self::SEARCH_VAR );
		if ( '' === (string) $term ) {
			return $where;
		}
		global $wpdb;

		$like = '%' . $wpdb->esc_like( $term ) . '%';
		$keys = self::searchable_keys( (string) $query->get( 'post_type' ) );

		$meta_clause = '';
		if ( $keys ) {
			$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
			$meta_clause  = $wpdb->prepare(
				" OR {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( {$placeholders} ) AND meta_value LIKE %s )", // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders
				array_merge( $keys, array( $like ) )
			);
		}

		return $where . $wpdb->prepare( " AND ( {$wpdb->posts}.post_title LIKE %s{$meta_clause} )", $like ); // phpcs:ignore
	}

	/**
	 * Sort choices. Number fields get Low→High / High→Low entries.
	 *
	 * @return array key => label
	 */
	public static function get_sort_options( $post_type ) {
		$options = array(
			'date_desc'  => __( 'Newest', 'dw-catalog-wp' ),
			'date_asc'   => __( 'Oldest', 'dw-catalog-wp' ),
			'title_asc'  => __( 'Name: A → Z', 'dw-catalog-wp' ),
			'title_desc' => __( 'Name: Z → A', 'dw-catalog-wp' ),
		);
		foreach ( DWCAT_Config::get_fields( $post_type ) as $f ) {
			if ( 'number' === $f['type'] ) {
				/* translators: %s: field label */
				$options[ 'meta:' . $f['meta_key'] . ':asc' ]  = sprintf( __( '%s: Low → High', 'dw-catalog-wp' ), $f['label'] );
				/* translators: %s: field label */
				$options[ 'meta:' . $f['meta_key'] . ':desc' ] = sprintf( __( '%s: High → Low', 'dw-catalog-wp' ), $f['label'] );
			}
		}
		return $options;
	}

	/**
	 * Sort key → orderby parts. Accepts the get_sort_options() keys plus
	 * "meta:{key}:{dir}" for any field (admin column headers sort text fields too).
	 */
	public static function resolve_sort( $post_type, $sort ) {
		$out = array( 'orderby' => 'date', 'order' => 'DESC', 'meta_key' => '', 'meta_type' => 'CHAR' );

		if ( preg_match( '/^meta:([a-z0-9_]+):(asc|desc)$/', (string) $sort, $m ) ) {
			$fields = self::fields_by_key( $post_type );
			if ( isset( $fields[ $m[1] ] ) ) {
				$out['meta_key']  = $m[1];
				$out['meta_type'] = 'number' === $fields[ $m[1] ]['type'] ? 'NUMERIC' : ( 'date' === $fields[ $m[1] ]['type'] ? 'DATE' : 'CHAR' );
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
	 * Choices for a field filter: select options, or distinct stored values for free-text fields.
	 *
	 * @param string $base_cat Limit free-text values to items in this category (shortcode "category").
	 * @return array value => label
	 */
	public static function get_filter_choices( $post_type, $field, $base_cat = '', $limit = 100 ) {
		if ( 'select' === $field['type'] ) {
			return DWCAT_Config::parse_select_options( $field['options'] );
		}

		global $wpdb;
		$scope = '';
		if ( '' !== (string) $base_cat ) {
			$ids = get_posts( array_merge(
				self::build_args( $post_type, array( 'base_cat' => $base_cat ) ),
				array( 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true )
			) );
			if ( ! $ids ) {
				return array();
			}
			$scope = ' AND p.ID IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')';
		}

		$values = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s AND p.post_type = %s AND p.post_status = 'publish' AND pm.meta_value <> ''{$scope}
			 ORDER BY pm.meta_value ASC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared — $scope is intval'd IDs
			$field['meta_key'],
			$post_type,
			(int) $limit
		) );
		return $values ? array_combine( $values, $values ) : array();
	}

	/**
	 * @return array meta_key => field
	 */
	public static function fields_by_key( $post_type ) {
		$out = array();
		foreach ( DWCAT_Config::get_fields( $post_type ) as $f ) {
			$out[ $f['meta_key'] ] = $f;
		}
		return $out;
	}

	private static function searchable_keys( $post_type ) {
		$keys = array();
		foreach ( DWCAT_Config::get_fields( $post_type ) as $f ) {
			if ( in_array( $f['type'], self::SEARCHABLE_TYPES, true ) ) {
				$keys[] = $f['meta_key'];
			}
		}
		// Variation item codes / option names (serialized array — substring match is enough)
		$keys[] = DWCAT_Variations::META_KEY;
		return $keys;
	}
}
