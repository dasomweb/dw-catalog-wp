<?php
/**
 * Post Type Registration Class
 * 
 * Registers the Catalog WP custom post type.
 * Domain-agnostic implementation.
 * 
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_Post_Type Class
 * 
 * Handles custom post type registration for products.
 */
class PC_Post_Type {

	/**
	 * Post type slug
	 * 
	 * @var string
	 */
	private $post_type = 'product';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
	}

	/**
	 * Register Product post type
	 * 
	 * Uses WordPress functions - domain agnostic
	 */
	public function register_post_type() {
		$config = pc_get_plugin_config();
		
		$labels = array(
			'name'                  => _x( 'Products', 'Post type general name', 'dw-catalog-wp' ),
			'singular_name'         => _x( 'Product', 'Post type singular name', 'dw-catalog-wp' ),
			'menu_name'             => _x( 'Catalog WP', 'Admin Menu text', 'dw-catalog-wp' ),
			'name_admin_bar'        => _x( 'Product', 'Add New on Toolbar', 'dw-catalog-wp' ),
			'add_new'               => __( 'Add New', 'dw-catalog-wp' ),
			'add_new_item'          => __( 'Add New Product', 'dw-catalog-wp' ),
			'new_item'              => __( 'New Product', 'dw-catalog-wp' ),
			'edit_item'             => __( 'Edit Product', 'dw-catalog-wp' ),
			'view_item'             => __( 'View Product', 'dw-catalog-wp' ),
			'all_items'             => __( 'All Products', 'dw-catalog-wp' ),
			'search_items'          => __( 'Search Products', 'dw-catalog-wp' ),
			'parent_item_colon'     => __( 'Parent Product:', 'dw-catalog-wp' ),
			'not_found'             => __( 'No products found.', 'dw-catalog-wp' ),
			'not_found_in_trash'    => __( 'No products found in Trash.', 'dw-catalog-wp' ),
			'featured_image'        => _x( 'Product Image', 'Overrides the "Featured Image" phrase', 'dw-catalog-wp' ),
			'set_featured_image'    => _x( 'Set product image', 'Overrides the "Set featured image" phrase', 'dw-catalog-wp' ),
			'remove_featured_image' => _x( 'Remove product image', 'Overrides the "Remove featured image" phrase', 'dw-catalog-wp' ),
			'use_featured_image'    => _x( 'Use as product image', 'Overrides the "Use as featured image" phrase', 'dw-catalog-wp' ),
			'archives'              => _x( 'Product Archives', 'The post type archive label used in nav menus', 'dw-catalog-wp' ),
			'insert_into_item'      => _x( 'Insert into product', 'Overrides the "Insert into post"/"Insert into page" phrase', 'dw-catalog-wp' ),
			'uploaded_to_this_item' => _x( 'Uploaded to this product', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'dw-catalog-wp' ),
			'filter_items_list'     => _x( 'Filter products list', 'Screen reader text for the filter links', 'dw-catalog-wp' ),
			'items_list_navigation' => _x( 'Products list navigation', 'Screen reader text for the pagination', 'dw-catalog-wp' ),
			'items_list'            => _x( 'Products list', 'Screen reader text for the items list', 'dw-catalog-wp' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'              => true,
			'publicly_queryable' => true,
			'show_ui'             => true,
			'show_in_menu'       => true,
			'show_in_admin_bar'  => true,
			'show_in_nav_menus'  => true,
			'query_var'           => true,
			'rewrite'            => array( 'slug' => 'product' ), // Domain agnostic - uses WordPress rewrite
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-products',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
			'show_in_rest'       => true, // Enable Gutenberg editor
		);

		register_post_type( $this->post_type, $args );
	}

	/**
	 * Register taxonomies for products
	 * 
	 * Uses WordPress functions - domain agnostic
	 */
	public function register_taxonomies() {
		// Product Category
		$category_labels = array(
			'name'              => _x( 'Product Categories', 'taxonomy general name', 'dw-catalog-wp' ),
			'singular_name'     => _x( 'Product Category', 'taxonomy singular name', 'dw-catalog-wp' ),
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

		$category_args = array(
			'hierarchical'      => true,
			'labels'            => $category_labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'product-category' ), // Domain agnostic
			'show_in_rest'      => true,
		);

		register_taxonomy( 'product_category', array( $this->post_type ), $category_args );

		// Product Tag
		$tag_labels = array(
			'name'                       => _x( 'Product Tags', 'taxonomy general name', 'dw-catalog-wp' ),
			'singular_name'              => _x( 'Product Tag', 'taxonomy singular name', 'dw-catalog-wp' ),
			'search_items'               => __( 'Search Tags', 'dw-catalog-wp' ),
			'popular_items'              => __( 'Popular Tags', 'dw-catalog-wp' ),
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

		$tag_args = array(
			'hierarchical'          => false,
			'labels'                => $tag_labels,
			'show_ui'               => true,
			'show_admin_column'     => true,
			'update_count_callback' => '_update_post_term_count',
			'query_var'             => true,
			'rewrite'               => array( 'slug' => 'product-tag' ), // Domain agnostic
			'show_in_rest'          => true,
		);

		register_taxonomy( 'product_tag', array( $this->post_type ), $tag_args );
	}

	/**
	 * Get post type slug
	 * 
	 * @return string Post type slug
	 */
	public function get_post_type() {
		return $this->post_type;
	}
}

