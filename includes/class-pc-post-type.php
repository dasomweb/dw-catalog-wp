<?php
/**
 * Post Type Registration Class
 *
 * Dynamically registers custom post types and taxonomies based on DWCAT_Config.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Post_Type {

	public function __construct() {
		add_action( 'init', array( $this, 'register_all' ) );
	}

	/**
	 * Register all configured post types and their taxonomies.
	 */
	public function register_all() {
		$post_types = DWCAT_Config::get_post_types();

		foreach ( $post_types as $slug => $config ) {
			$this->register_post_type( $slug, $config );

			if ( ! empty( $config['has_category'] ) ) {
				$this->register_category_taxonomy( $slug, $config );
			}
			if ( ! empty( $config['has_tag'] ) ) {
				$this->register_tag_taxonomy( $slug, $config );
			}
		}
	}

	/**
	 * Register a single post type.
	 */
	private function register_post_type( $slug, $config ) {
		$singular = $config['singular_name'];
		$plural   = $config['plural_name'];
		$menu     = ! empty( $config['menu_name'] ) ? $config['menu_name'] : $plural;

		$labels = array(
			'name'                  => $plural,
			'singular_name'         => $singular,
			'menu_name'             => $menu,
			'name_admin_bar'        => $singular,
			'add_new'               => __( 'Add New', 'dw-catalog-wp' ),
			'add_new_item'          => sprintf( __( 'Add New %s', 'dw-catalog-wp' ), $singular ),
			'new_item'              => sprintf( __( 'New %s', 'dw-catalog-wp' ), $singular ),
			'edit_item'             => sprintf( __( 'Edit %s', 'dw-catalog-wp' ), $singular ),
			'view_item'             => sprintf( __( 'View %s', 'dw-catalog-wp' ), $singular ),
			'all_items'             => sprintf( __( 'All %s', 'dw-catalog-wp' ), $plural ),
			'search_items'          => sprintf( __( 'Search %s', 'dw-catalog-wp' ), $plural ),
			'not_found'             => sprintf( __( 'No %s found.', 'dw-catalog-wp' ), strtolower( $plural ) ),
			'not_found_in_trash'    => sprintf( __( 'No %s found in Trash.', 'dw-catalog-wp' ), strtolower( $plural ) ),
			'featured_image'        => sprintf( __( '%s Image', 'dw-catalog-wp' ), $singular ),
			'set_featured_image'    => sprintf( __( 'Set %s image', 'dw-catalog-wp' ), strtolower( $singular ) ),
			'remove_featured_image' => sprintf( __( 'Remove %s image', 'dw-catalog-wp' ), strtolower( $singular ) ),
			'use_featured_image'    => sprintf( __( 'Use as %s image', 'dw-catalog-wp' ), strtolower( $singular ) ),
			'archives'              => sprintf( __( '%s Archives', 'dw-catalog-wp' ), $singular ),
		);

		$supports = ! empty( $config['supports'] ) ? $config['supports'] : array( 'title', 'editor', 'thumbnail' );

		$args = array(
			'labels'             => $labels,
			'public'             => ! empty( $config['public'] ),
			'publicly_queryable' => ! empty( $config['public'] ),
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_admin_bar'  => true,
			'show_in_nav_menus'  => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => $slug ),
			'capability_type'    => 'post',
			'has_archive'        => ! empty( $config['has_archive'] ),
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => ! empty( $config['menu_icon'] ) ? $config['menu_icon'] : 'dashicons-admin-generic',
			'supports'           => $supports,
			'show_in_rest'       => ! empty( $config['show_in_rest'] ),
		);

		register_post_type( $slug, $args );
	}

	/**
	 * Register category taxonomy for a post type.
	 */
	private function register_category_taxonomy( $slug, $config ) {
		$singular = $config['singular_name'];
		$tax_slug = DWCAT_Config::get_category_taxonomy( $slug );

		$labels = array(
			'name'              => sprintf( __( '%s Categories', 'dw-catalog-wp' ), $singular ),
			'singular_name'     => sprintf( __( '%s Category', 'dw-catalog-wp' ), $singular ),
			'search_items'      => __( 'Search Categories', 'dw-catalog-wp' ),
			'all_items'         => __( 'All Categories', 'dw-catalog-wp' ),
			'parent_item'       => __( 'Parent Category', 'dw-catalog-wp' ),
			'parent_item_colon' => __( 'Parent Category:', 'dw-catalog-wp' ),
			'edit_item'         => __( 'Edit Category', 'dw-catalog-wp' ),
			'update_item'       => __( 'Update Category', 'dw-catalog-wp' ),
			'add_new_item'      => __( 'Add New Category', 'dw-catalog-wp' ),
			'new_item_name'     => __( 'New Category Name', 'dw-catalog-wp' ),
			'menu_name'         => __( 'Categories', 'dw-catalog-wp' ),
		);

		register_taxonomy( $tax_slug, array( $slug ), array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => $slug . '-category' ),
			'show_in_rest'      => ! empty( $config['show_in_rest'] ),
		) );
	}

	/**
	 * Register tag taxonomy for a post type.
	 */
	private function register_tag_taxonomy( $slug, $config ) {
		$singular = $config['singular_name'];
		$tax_slug = DWCAT_Config::get_tag_taxonomy( $slug );

		$labels = array(
			'name'                       => sprintf( __( '%s Tags', 'dw-catalog-wp' ), $singular ),
			'singular_name'              => sprintf( __( '%s Tag', 'dw-catalog-wp' ), $singular ),
			'search_items'               => __( 'Search Tags', 'dw-catalog-wp' ),
			'all_items'                  => __( 'All Tags', 'dw-catalog-wp' ),
			'edit_item'                  => __( 'Edit Tag', 'dw-catalog-wp' ),
			'update_item'                => __( 'Update Tag', 'dw-catalog-wp' ),
			'add_new_item'               => __( 'Add New Tag', 'dw-catalog-wp' ),
			'new_item_name'              => __( 'New Tag Name', 'dw-catalog-wp' ),
			'separate_items_with_commas' => __( 'Separate tags with commas', 'dw-catalog-wp' ),
			'add_or_remove_items'        => __( 'Add or remove tags', 'dw-catalog-wp' ),
			'choose_from_most_used'      => __( 'Choose from the most used tags', 'dw-catalog-wp' ),
			'not_found'                  => __( 'No tags found.', 'dw-catalog-wp' ),
			'menu_name'                  => __( 'Tags', 'dw-catalog-wp' ),
		);

		register_taxonomy( $tax_slug, array( $slug ), array(
			'hierarchical'          => false,
			'labels'                => $labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => $slug . '-tag' ),
			'show_in_rest'          => ! empty( $config['show_in_rest'] ),
		) );
	}
}
