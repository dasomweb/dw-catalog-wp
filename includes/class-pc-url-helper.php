<?php
/**
 * URL Helper Class
 * 
 * Provides domain-agnostic URL generation utilities.
 * All URL generation uses WordPress functions that adapt to domain changes.
 * 
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DWCAT_URL_Helper Class
 * 
 * Centralized URL generation using WordPress functions.
 */
class DWCAT_URL_Helper {

	/**
	 * Get plugin admin URL
	 * Uses admin_url() - domain agnostic
	 * 
	 * @param string $path Optional path to append
	 * @return string Admin URL
	 */
	public static function get_admin_url( $path = '' ) {
		return admin_url( $path );
	}

	/**
	 * Get plugin settings URL
	 * Uses admin_url() - domain agnostic
	 * 
	 * @return string Settings URL
	 */
	public static function get_settings_url() {
		$config = dwcat_get_config();
		return admin_url( 'admin.php?page=' . $config['plugin_slug'] );
	}

	/**
	 * Get plugin assets URL
	 * Uses plugin_dir_url() - domain agnostic
	 * 
	 * @param string $file Optional file path relative to assets directory
	 * @return string Assets URL
	 */
	public static function get_assets_url( $file = '' ) {
		$base_url = plugin_dir_url( dirname( __FILE__ ) );
		return $base_url . 'assets/' . ltrim( $file, '/' );
	}

	/**
	 * Get plugin CSS URL
	 * Uses plugin_dir_url() - domain agnostic
	 * 
	 * @param string $file CSS file name
	 * @return string CSS URL
	 */
	public static function get_css_url( $file ) {
		return self::get_assets_url( 'css/' . $file );
	}

	/**
	 * Get plugin JS URL
	 * Uses plugin_dir_url() - domain agnostic
	 * 
	 * @param string $file JS file name
	 * @return string JS URL
	 */
	public static function get_js_url( $file ) {
		return self::get_assets_url( 'js/' . $file );
	}

	/**
	 * Get plugin image URL
	 * Uses plugin_dir_url() - domain agnostic
	 * 
	 * @param string $file Image file name
	 * @return string Image URL
	 */
	public static function get_image_url( $file ) {
		return self::get_assets_url( 'images/' . $file );
	}

	/**
	 * Get site home URL
	 * Uses home_url() - domain agnostic
	 * 
	 * @param string $path Optional path to append
	 * @return string Home URL
	 */
	public static function get_home_url( $path = '' ) {
		return home_url( $path );
	}

	/**
	 * Get site URL
	 * Uses site_url() - domain agnostic
	 * 
	 * @param string $path Optional path to append
	 * @return string Site URL
	 */
	public static function get_site_url( $path = '' ) {
		return site_url( $path );
	}

	/**
	 * Get AJAX URL
	 * Uses admin_url() with admin-ajax.php - domain agnostic
	 * 
	 * @return string AJAX URL
	 */
	public static function get_ajax_url() {
		return admin_url( 'admin-ajax.php' );
	}

	/**
	 * Get REST API URL
	 * Uses rest_url() - domain agnostic
	 * 
	 * @param string $path Optional REST route
	 * @return string REST API URL
	 */
	public static function get_rest_url( $path = '' ) {
		return rest_url( $path );
	}
}


