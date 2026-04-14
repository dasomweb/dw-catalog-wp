<?php
/**
 * Admin Columns Class
 *
 * Adds custom columns to product list in admin.
 *
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PC_Admin_Columns {

	private $post_type = 'product';

	public function __construct() {
		add_filter( 'manage_' . $this->post_type . '_posts_columns', array( $this, 'add_columns' ) );
		add_action( 'manage_' . $this->post_type . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
		add_filter( 'manage_edit-' . $this->post_type . '_sortable_columns', array( $this, 'sortable_columns' ) );
	}

	public function add_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				$new_columns['pc_category']     = __( 'Category', 'dw-catalog-wp' );
				$new_columns['pc_item_code']    = __( 'Item Code', 'dw-catalog-wp' );
				$new_columns['pc_pack_size']    = __( 'Pack Size', 'dw-catalog-wp' );
				$new_columns['pc_brand']        = __( 'Brand', 'dw-catalog-wp' );
				$new_columns['pc_origin']      = __( 'Origin', 'dw-catalog-wp' );
				$new_columns['pc_status']       = __( 'Status', 'dw-catalog-wp' );
			}
		}
		return $new_columns;
	}

	public function render_column( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'pc_category':
				$terms = wp_get_post_terms( $post_id, 'product_category' );
				$names = ! empty( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
				echo $names ? esc_html( $names ) : '—';
				break;
			case 'pc_item_code':
				$value = PC_Product_Display::get_item_code( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
			case 'pc_pack_size':
				$value = PC_Product_Display::get_pack_size_raw( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
			case 'pc_brand':
				$value = PC_Product_Display::get_brand( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
			case 'pc_origin':
				$value = PC_Product_Display::get_origin( $post_id );
				echo $value ? esc_html( $value ) : '—';
				break;
			case 'pc_status':
				$value = PC_Product_Display::get_status( $post_id );
				$labels = array(
					'active'        => __( 'Active', 'dw-catalog-wp' ),
					'inactive'      => __( 'Inactive', 'dw-catalog-wp' ),
					'out_of_stock'  => __( 'Out of Stock', 'dw-catalog-wp' ),
					'discontinued'  => __( 'Discontinued', 'dw-catalog-wp' ),
				);
				echo $value ? ( isset( $labels[ $value ] ) ? esc_html( $labels[ $value ] ) : esc_html( $value ) ) : '—';
				break;
		}
	}

	public function sortable_columns( $columns ) {
		$columns['pc_item_code'] = 'pc_item_code';
		$columns['pc_pack_size'] = 'pc_pack_size';
		$columns['pc_brand']     = 'pc_brand';
		$columns['pc_origin']    = 'pc_origin';
		$columns['pc_status']    = 'pc_status';
		return $columns;
	}
}
