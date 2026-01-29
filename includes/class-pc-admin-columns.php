<?php
/**
 * Admin Columns Class
 * 
 * Adds custom columns to product list in admin.
 * Domain-agnostic implementation.
 * 
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_Admin_Columns Class
 * 
 * Manages admin columns for product list.
 */
class PC_Admin_Columns {

	/**
	 * Post type
	 * 
	 * @var string
	 */
	private $post_type = 'product';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . $this->post_type . '_sortable_columns', array( $this, 'sortable_columns' ) );
	}

	/**
	 * Add custom columns
	 * 
	 * @param array $columns Existing columns
	 * @return array Modified columns
	 */
	public function add_columns( $columns ) {
		// Insert columns after title
		$new_columns = array();
		
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			
			if ( 'title' === $key ) {
				$new_columns['pc_brand']        = __( 'Brand', 'dw-product-catalog' );
				$new_columns['pc_cut_type']     = __( 'Cut / Form', 'dw-product-catalog' );
				$new_columns['pc_size_weight'] = __( 'Size / Weight', 'dw-product-catalog' );
				$new_columns['pc_origin']       = __( 'Origin', 'dw-product-catalog' );
				$new_columns['pc_item_code']    = __( 'Item Code', 'dw-product-catalog' );
			}
		}
		
		return $new_columns;
	}

	/**
	 * Render column content
	 * 
	 * @param string $column_name Column name
	 * @param int    $post_id     Post ID
	 */
	public function render_column( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'pc_brand':
				$value = PC_Product_Display::get_brand( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
			
			case 'pc_cut_type':
				$value = PC_Product_Display::get_cut_type( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
			
			case 'pc_size_weight':
				$value = PC_Product_Display::get_size_weight( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
			
			case 'pc_origin':
				$value = PC_Product_Display::get_origin( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
			
			case 'pc_item_code':
				$value = PC_Product_Display::get_item_code( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
		}
	}

	/**
	 * Make columns sortable
	 * 
	 * @param array $columns Sortable columns
	 * @return array Modified sortable columns
	 */
	public function sortable_columns( $columns ) {
		$columns['pc_brand']        = 'pc_brand';
		$columns['pc_cut_type']     = 'pc_cut_type';
		$columns['pc_size_weight']  = 'pc_size_weight';
		$columns['pc_origin']        = 'pc_origin';
		$columns['pc_item_code']    = 'pc_item_code';
		
		return $columns;
	}
}


