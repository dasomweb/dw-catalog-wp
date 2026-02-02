<?php
/**
 * Product Display Helper Class
 *
 * Helper functions for displaying product information.
 * All output is properly escaped for security.
 *
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_Product_Display Class
 *
 * Provides helper functions for displaying product data.
 */
class PC_Product_Display {

	public static function get_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return ! empty( $value ) ? $value : $default;
	}

	public static function get_product_name( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_product_name' );
	}

	public static function display_product_name( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, 'dw_pc_product_name' );
	}

	public static function get_brand( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_brand_raw' );
	}

	public static function display_brand( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, 'dw_pc_brand_raw' );
	}

	public static function get_item_code( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_item_code' );
	}

	public static function display_item_code( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, 'dw_pc_item_code' );
	}

	public static function get_pack_size_raw( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_pack_size_raw' );
	}

	public static function display_pack_size_raw( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, 'dw_pc_pack_size_raw' );
	}

	public static function get_origin( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_origin_raw' );
	}

	public static function display_origin( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, 'dw_pc_origin_raw' );
	}

	public static function get_status( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_status' );
	}

	public static function get_category_slug( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_category_slug' );
	}

	public static function get_internal_note( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_internal_note' );
	}

	public static function display_internal_note( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, 'dw_pc_internal_note' );
	}
}
