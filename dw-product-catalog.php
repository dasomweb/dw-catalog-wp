<?php
/**
 * Plugin Name: DW Product Catalog
 * Plugin URI: https://github.com/dasomweb/DW-Product-Catalog
 * Description: Domain-change friendly product catalog plugin
 * Version: 1.5.1
 * Author: Dasom Web
 * Author URI: https://github.com/dasomweb
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dw-product-catalog
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central Plugin Configuration
 * 
 * This function centralizes all configurable values for the plugin.
 * All environment-specific or plugin-specific values should be defined here.
 * 
 * @return array Configuration array
 */
function pc_get_plugin_config() {
	return array(
		// GitHub Repository Information
		'github_repo_owner' => 'dasomweb',
		'github_repo_name'  => 'DW-Product-Catalog',
		
		// Plugin Information
		'plugin_slug'       => 'dw-product-catalog',
		'plugin_version'    => '1.5.1',
		'plugin_name'       => 'DW Product Catalog',
		'plugin_text_domain' => 'dw-product-catalog',
		
		// Update Settings
		'github_branch'     => 'main',
		'requires_wp'       => '5.0',
		'requires_php'      => '7.4',
	);
}

/**
 * Get plugin directory URL
 * Uses WordPress function - domain agnostic
 * 
 * @return string Plugin directory URL
 */
function pc_get_plugin_url() {
	return plugin_dir_url( __FILE__ );
}

/**
 * Get plugin directory path
 * Uses WordPress function - domain agnostic
 * 
 * @return string Plugin directory path
 */
function pc_get_plugin_path() {
	return plugin_dir_path( __FILE__ );
}

/**
 * Get plugin base file
 * 
 * @return string Plugin base file path
 */
function pc_get_plugin_file() {
	return __FILE__;
}

// Load includes
require_once pc_get_plugin_path() . 'includes/class-pc-url-helper.php';
require_once pc_get_plugin_path() . 'includes/class-pc-github-updater.php';
require_once pc_get_plugin_path() . 'includes/class-pc-post-type.php';
require_once pc_get_plugin_path() . 'includes/class-pc-meta-box.php';
require_once pc_get_plugin_path() . 'includes/class-pc-product-display.php';
require_once pc_get_plugin_path() . 'includes/class-pc-admin-columns.php';
require_once pc_get_plugin_path() . 'includes/class-pc-admin-pages.php';
require_once pc_get_plugin_path() . 'includes/class-pc-field-reference.php';
require_once pc_get_plugin_path() . 'includes/class-pc-bulk-import.php';

// Initialize GitHub Updater
add_action( 'plugins_loaded', 'pc_init_github_updater', 10 );
function pc_init_github_updater() {
	$config = pc_get_plugin_config();
	$updater = new PC_GitHub_Updater(
		pc_get_plugin_file(),
		$config['github_repo_owner'],
		$config['github_repo_name'],
		$config['plugin_slug'],
		$config['plugin_version']
	);
}

// Initialize Post Type
add_action( 'plugins_loaded', 'pc_init_post_type', 10 );
function pc_init_post_type() {
	new PC_Post_Type();
}

// Initialize Meta Box
add_action( 'plugins_loaded', 'pc_init_meta_box', 10 );
function pc_init_meta_box() {
	new PC_Meta_Box();
}

// Initialize Admin Columns
add_action( 'plugins_loaded', 'pc_init_admin_columns', 10 );
function pc_init_admin_columns() {
	new PC_Admin_Columns();
}

// Initialize Admin Pages
add_action( 'plugins_loaded', 'pc_init_admin_pages', 10 );
function pc_init_admin_pages() {
	new PC_Admin_Pages();
}

// Initialize Field Reference
add_action( 'plugins_loaded', 'pc_init_field_reference', 10 );
function pc_init_field_reference() {
	new PC_Field_Reference();
}

// Initialize Bulk Import
add_action( 'plugins_loaded', 'pc_init_bulk_import', 10 );
function pc_init_bulk_import() {
	new PC_Bulk_Import();
}

// Plugin activation hook
register_activation_hook( __FILE__, 'pc_activate' );
function pc_activate() {
	// Do not store any absolute URLs during activation
	// Use WordPress options API with relative values only
	$config = pc_get_plugin_config();
	
	// Store only plugin-specific data, no URLs
	update_option( 'pc_plugin_version', $config['plugin_version'] );
	update_option( 'pc_plugin_activated', time() );
	
	// Register post type and taxonomies for rewrite rules flush
	$post_type = new PC_Post_Type();
	
	// Flush rewrite rules after post type registration (domain agnostic)
	flush_rewrite_rules();
}

// Plugin deactivation hook
register_deactivation_hook( __FILE__, 'pc_deactivate' );
function pc_deactivate() {
	// Cleanup - no domain-specific cleanup needed
	flush_rewrite_rules();
}

