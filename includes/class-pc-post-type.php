<?php
/**
 * Post Type Registration Class
 * 
 * Registers the Product Catalog custom post type.
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
			'name'                  => _x( '제품', 'Post type general name', 'dw-product-catalog' ),
			'singular_name'         => _x( '제품', 'Post type singular name', 'dw-product-catalog' ),
			'menu_name'             => _x( '제품 카탈로그', 'Admin Menu text', 'dw-product-catalog' ),
			'name_admin_bar'        => _x( '제품', 'Add New on Toolbar', 'dw-product-catalog' ),
			'add_new'               => __( '새로 추가', 'dw-product-catalog' ),
			'add_new_item'          => __( '새 제품 추가', 'dw-product-catalog' ),
			'new_item'              => __( '새 제품', 'dw-product-catalog' ),
			'edit_item'             => __( '제품 편집', 'dw-product-catalog' ),
			'view_item'             => __( '제품 보기', 'dw-product-catalog' ),
			'all_items'             => __( '모든 제품', 'dw-product-catalog' ),
			'search_items'          => __( '제품 검색', 'dw-product-catalog' ),
			'parent_item_colon'     => __( '상위 제품:', 'dw-product-catalog' ),
			'not_found'             => __( '제품을 찾을 수 없습니다.', 'dw-product-catalog' ),
			'not_found_in_trash'    => __( '휴지통에서 제품을 찾을 수 없습니다.', 'dw-product-catalog' ),
			'featured_image'        => _x( '제품 이미지', 'Overrides the "Featured Image" phrase', 'dw-product-catalog' ),
			'set_featured_image'    => _x( '제품 이미지 설정', 'Overrides the "Set featured image" phrase', 'dw-product-catalog' ),
			'remove_featured_image' => _x( '제품 이미지 제거', 'Overrides the "Remove featured image" phrase', 'dw-product-catalog' ),
			'use_featured_image'    => _x( '제품 이미지로 사용', 'Overrides the "Use as featured image" phrase', 'dw-product-catalog' ),
			'archives'              => _x( '제품 아카이브', 'The post type archive label used in nav menus', 'dw-product-catalog' ),
			'insert_into_item'      => _x( '제품에 삽입', 'Overrides the "Insert into post"/"Insert into page" phrase', 'dw-product-catalog' ),
			'uploaded_to_this_item' => _x( '이 제품에 업로드됨', 'Overrides the "Uploaded to this post"/"Uploaded to this page" phrase', 'dw-product-catalog' ),
			'filter_items_list'     => _x( '제품 목록 필터', 'Screen reader text for the filter links', 'dw-product-catalog' ),
			'items_list_navigation' => _x( '제품 목록 탐색', 'Screen reader text for the pagination', 'dw-product-catalog' ),
			'items_list'            => _x( '제품 목록', 'Screen reader text for the items list', 'dw-product-catalog' ),
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
			'name'              => _x( '제품 카테고리', 'taxonomy general name', 'dw-product-catalog' ),
			'singular_name'     => _x( '제품 카테고리', 'taxonomy singular name', 'dw-product-catalog' ),
			'search_items'      => __( '카테고리 검색', 'dw-product-catalog' ),
			'all_items'         => __( '모든 카테고리', 'dw-product-catalog' ),
			'parent_item'       => __( '상위 카테고리', 'dw-product-catalog' ),
			'parent_item_colon' => __( '상위 카테고리:', 'dw-product-catalog' ),
			'edit_item'         => __( '카테고리 편집', 'dw-product-catalog' ),
			'update_item'       => __( '카테고리 업데이트', 'dw-product-catalog' ),
			'add_new_item'      => __( '새 카테고리 추가', 'dw-product-catalog' ),
			'new_item_name'     => __( '새 카테고리 이름', 'dw-product-catalog' ),
			'menu_name'         => __( '카테고리', 'dw-product-catalog' ),
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
			'name'                       => _x( '제품 태그', 'taxonomy general name', 'dw-product-catalog' ),
			'singular_name'              => _x( '제품 태그', 'taxonomy singular name', 'dw-product-catalog' ),
			'search_items'               => __( '태그 검색', 'dw-product-catalog' ),
			'popular_items'              => __( '인기 태그', 'dw-product-catalog' ),
			'all_items'                  => __( '모든 태그', 'dw-product-catalog' ),
			'edit_item'                  => __( '태그 편집', 'dw-product-catalog' ),
			'update_item'                => __( '태그 업데이트', 'dw-product-catalog' ),
			'add_new_item'               => __( '새 태그 추가', 'dw-product-catalog' ),
			'new_item_name'              => __( '새 태그 이름', 'dw-product-catalog' ),
			'separate_items_with_commas' => __( '쉼표로 태그 구분', 'dw-product-catalog' ),
			'add_or_remove_items'        => __( '태그 추가 또는 제거', 'dw-product-catalog' ),
			'choose_from_most_used'      => __( '가장 많이 사용된 태그에서 선택', 'dw-product-catalog' ),
			'not_found'                  => __( '태그를 찾을 수 없습니다.', 'dw-product-catalog' ),
			'menu_name'                  => __( '태그', 'dw-product-catalog' ),
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

