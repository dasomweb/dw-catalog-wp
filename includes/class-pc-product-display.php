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
	 * Get product name
	 * 
	 * @param int $post_id Post ID
	 * @return string Product name
	 */
	public static function get_product_name( $post_id ) {
		return PC_Meta_Box::get_product_meta( $post_id, '_pc_product_name' );
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
	 * Get UPC
	 * 
	 * @param int $post_id Post ID
	 * @return string UPC
	 */
	public static function get_upc( $post_id ) {
		return PC_Meta_Box::get_product_meta( $post_id, '_pc_upc' );
	}

	/**
	 * Display UPC (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_upc( $post_id ) {
		PC_Meta_Box::display_product_meta( $post_id, '_pc_upc' );
	}

	/**
	 * Get temperature
	 * 
	 * @param int $post_id Post ID
	 * @return string Temperature
	 */
	public static function get_temperature( $post_id ) {
		$value = PC_Meta_Box::get_product_meta( $post_id, '_pc_temperature' );
		
		// Convert to readable label
		$labels = array(
			'room'    => __( '상온', 'dw-product-catalog' ),
			'cold'    => __( '냉장', 'dw-product-catalog' ),
			'frozen'  => __( '냉동', 'dw-product-catalog' ),
			'freezer' => __( '프리저', 'dw-product-catalog' ),
		);
		
		return isset( $labels[ $value ] ) ? $labels[ $value ] : $value;
	}

	/**
	 * Display temperature (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_temperature( $post_id ) {
		echo esc_html( self::get_temperature( $post_id ) );
	}

	/**
	 * Get allergen (as array)
	 * 
	 * @param int $post_id Post ID
	 * @return array Array of allergens
	 */
	public static function get_allergen_array( $post_id ) {
		$allergen = PC_Meta_Box::get_product_meta( $post_id, '_pc_allergen' );
		
		if ( empty( $allergen ) ) {
			return array();
		}
		
		// Split by comma and trim
		$allergens = array_map( 'trim', explode( ',', $allergen ) );
		return array_filter( $allergens );
	}

	/**
	 * Get allergen (as string)
	 * 
	 * @param int $post_id Post ID
	 * @return string Allergen string
	 */
	public static function get_allergen( $post_id ) {
		return PC_Meta_Box::get_product_meta( $post_id, '_pc_allergen' );
	}

	/**
	 * Display allergen (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_allergen( $post_id ) {
		$allergen = self::get_allergen( $post_id );
		echo esc_html( $allergen );
	}

	/**
	 * Display allergen as list (escaped)
	 * 
	 * @param int $post_id Post ID
	 * @return void
	 */
	public static function display_allergen_list( $post_id ) {
		$allergens = self::get_allergen_array( $post_id );
		
		if ( empty( $allergens ) ) {
			return;
		}
		
		echo '<ul class="pc-allergen-list">';
		foreach ( $allergens as $allergen ) {
			echo '<li>' . esc_html( $allergen ) . '</li>';
		}
		echo '</ul>';
	}
}

