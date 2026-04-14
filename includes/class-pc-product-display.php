<?php
/**
 * Display Helper Class
 *
 * Generic helper functions for displaying post meta.
 * Works with any registered post type and its fields.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Product_Display {

	/**
	 * Get a meta value with default.
	 */
	public static function get_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return ( $value !== '' && $value !== false ) ? $value : $default;
	}

	/**
	 * Get a field value by meta key, resolving select labels.
	 */
	public static function get_field_value( $post_id, $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( $value === '' || $value === false ) {
			return '';
		}

		// Try to find field definition to resolve select labels
		$post = get_post( $post_id );
		if ( $post && DWCAT_Config::is_our_post_type( $post->post_type ) ) {
			$fields = DWCAT_Config::get_fields( $post->post_type );
			foreach ( $fields as $field ) {
				if ( $field['meta_key'] === $meta_key && $field['type'] === 'select' ) {
					$options = DWCAT_Config::parse_select_options( $field['options'] );
					return isset( $options[ $value ] ) ? $options[ $value ] : $value;
				}
			}
		}

		return $value;
	}

	/**
	 * Display a meta value (escaped).
	 */
	public static function display_field( $post_id, $meta_key, $default = '' ) {
		$value = self::get_field_value( $post_id, $meta_key );
		echo esc_html( $value !== '' ? $value : $default );
	}

	// Legacy compatibility methods for existing themes
	public static function get_product_name( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_product_name' );
	}

	public static function get_brand( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_brand_raw' );
	}

	public static function get_item_code( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_item_code' );
	}

	public static function get_pack_size_raw( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_pack_size_raw' );
	}

	public static function get_origin( $post_id ) {
		return self::get_product_meta( $post_id, 'dw_pc_origin_raw' );
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

	public static function display_product_name( $post_id ) {
		DWCAT_Meta_Box::display_product_meta( $post_id, 'dw_pc_product_name' );
	}

	public static function display_brand( $post_id ) {
		DWCAT_Meta_Box::display_product_meta( $post_id, 'dw_pc_brand_raw' );
	}

	public static function display_item_code( $post_id ) {
		DWCAT_Meta_Box::display_product_meta( $post_id, 'dw_pc_item_code' );
	}

	public static function display_pack_size_raw( $post_id ) {
		DWCAT_Meta_Box::display_product_meta( $post_id, 'dw_pc_pack_size_raw' );
	}

	public static function display_origin( $post_id ) {
		DWCAT_Meta_Box::display_product_meta( $post_id, 'dw_pc_origin_raw' );
	}

	public static function display_internal_note( $post_id ) {
		DWCAT_Meta_Box::display_product_meta( $post_id, 'dw_pc_internal_note' );
	}
}
