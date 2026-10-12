<?php
/**
 * Access Guard — keep catalog data behind the server.
 *
 * Visitors get rendered HTML pages only. The machine-readable routes WordPress opens by
 * default would let anyone pull the whole catalog with a script or from the browser's
 * dev tools, so for anyone who cannot edit items they are closed:
 *
 *   /wp-json/wp/v2/{items}            item list/detail (REST)        → 401
 *   /wp-json/wp/v2/{category|tag}     taxonomy REST                  → 401
 *   /wp-json/wp/v2/search             our items removed from results
 *   /{items}/feed, category feeds     RSS of the catalog             → 404
 *
 * Editors and admins (the DW Admin SPA, the block editor) keep full REST access.
 * Field values are never in REST at all (they live in DWCAT_Store, not registered meta),
 * and the site templates print public fields only (DWCAT_Config::is_public_field).
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Access_Guard {

	public static function init() {
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'guard_rest' ), 10, 3 );
		add_filter( 'rest_post_search_query', array( __CLASS__, 'guard_rest_search' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'guard_feeds' ), 1 );
	}

	private static function can_read_raw() {
		return current_user_can( 'edit_posts' );
	}

	/** REST route prefixes that expose catalog items or their categories. */
	private static function protected_routes() {
		$routes = array();
		foreach ( DWCAT_Config::get_post_types() as $slug => $config ) {
			$obj      = get_post_type_object( $slug );
			$routes[] = '/wp/v2/' . ( $obj && ! empty( $obj->rest_base ) ? $obj->rest_base : $slug );
			foreach ( array( 'has_category' => DWCAT_Config::get_category_taxonomy( $slug ), 'has_tag' => DWCAT_Config::get_tag_taxonomy( $slug ) ) as $flag => $tax ) {
				if ( ! empty( $config[ $flag ] ) ) {
					$t        = get_taxonomy( $tax );
					$routes[] = '/wp/v2/' . ( $t && ! empty( $t->rest_base ) ? $t->rest_base : $tax );
				}
			}
		}
		return $routes;
	}

	public static function guard_rest( $result, $server, $request ) {
		if ( null !== $result || self::can_read_raw() ) {
			return $result;
		}
		$route = untrailingslashit( (string) $request->get_route() );
		foreach ( self::protected_routes() as $prefix ) {
			if ( $route === $prefix || 0 === strpos( $route, $prefix . '/' ) ) {
				return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'dw-catalog-wp' ), array( 'status' => rest_authorization_required_code() ) );
			}
		}
		return $result;
	}

	public static function guard_rest_search( $query_args, $request ) {
		if ( self::can_read_raw() || empty( $query_args['post_type'] ) ) {
			return $query_args;
		}
		$query_args['post_type'] = array_values( array_diff( (array) $query_args['post_type'], DWCAT_Config::get_post_type_slugs() ) );
		if ( ! $query_args['post_type'] ) {
			$query_args['post__in'] = array( 0 ); // nothing left to search
		}
		return $query_args;
	}

	public static function guard_feeds() {
		if ( ! is_feed() || self::can_read_raw() ) {
			return;
		}
		$ours = DWCAT_Config::get_post_type_slugs();
		$taxes = array();
		foreach ( $ours as $slug ) {
			$taxes[] = DWCAT_Config::get_category_taxonomy( $slug );
			$taxes[] = DWCAT_Config::get_tag_taxonomy( $slug );
		}
		$qt = (array) get_query_var( 'post_type' );
		if ( array_intersect( $qt, $ours ) || is_post_type_archive( $ours ) || ( $taxes && is_tax( $taxes ) ) || is_singular( $ours ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			exit;
		}
	}
}
