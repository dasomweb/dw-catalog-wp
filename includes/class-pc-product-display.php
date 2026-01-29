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

	/**
	 * Get product meta value (helper function)
	 * 
	 * @param int    $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed  $default Default value
	 * @return mixed Meta value
	 */
	public static function get_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return ! empty( $value ) ? $value : $default;
	}

	/**
	 * Get product name
	 * 
	 * @param int $post_id Post ID
	 * @return string Product name
	 */
	public static function get_product_name( $post_id ) {
		return self::get_product_meta( $post_id, '_pc_product_name' );
	}

	/**
	 * Display product name (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_product_name( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, '_pc_product_name' );
	}

	/**
	 * Get brand
	 * 
	 * @param int $post_id Post ID
	 * @return string Brand
	 */
	public static function get_brand( $post_id ) {
		return PC_Meta_Box::get_product_meta( $post_id, '_pc_brand' );
	}

	/**
	 * Display brand (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_brand( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, '_pc_brand' );
	}

	/**
	 * Get item code
	 * 
	 * @param int $post_id Post ID
	 * @return string Item code
	 */
	public static function get_item_code( $post_id ) {
		return PC_Meta_Box::get_product_meta( $post_id, '_pc_item_code' );
	}

	/**
	 * Display item code (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_item_code( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, '_pc_item_code' );
	}

	/**
	 * Get cut type
	 * 
	 * @param int $post_id Post ID
	 * @return string Cut type
	 */
	public static function get_cut_type( $post_id ) {
		return self::get_product_meta( $post_id, '_pc_cut_type' );
	}

	/**
	 * Display cut type (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_cut_type( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, '_pc_cut_type' );
	}

	/**
	 * Get size/weight
	 * 
	 * @param int $post_id Post ID
	 * @return string Size/weight
	 */
	public static function get_size_weight( $post_id ) {
		return self::get_product_meta( $post_id, '_pc_size_weight' );
	}

	/**
	 * Display size/weight (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_size_weight( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, '_pc_size_weight' );
	}

	/**
	 * Get packing unit
	 * 
	 * @param int $post_id Post ID
	 * @return string Packing unit
	 */
	public static function get_packing_unit( $post_id ) {
		return self::get_product_meta( $post_id, '_pc_packing_unit' );
	}

	/**
	 * Display packing unit (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_packing_unit( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, '_pc_packing_unit' );
	}

	/**
	 * Get origin
	 * 
	 * @param int $post_id Post ID
	 * @return string Origin
	 */
	public static function get_origin( $post_id ) {
		return self::get_product_meta( $post_id, '_pc_origin' );
	}

	/**
	 * Display origin (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_origin( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, '_pc_origin' );
	}
}

