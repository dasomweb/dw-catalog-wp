<?php
/**
 * Plugin Name: DW Catalog WP
 * Plugin URI: https://github.com/dasomweb/dw-catalog-wp
 * Description: Dynamic custom post type & custom field catalog plugin. Register multiple post types with custom fields per website.
 * Version: 1.0
 * Author: Dasom Web
 * Author URI: https://github.com/dasomweb
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dw-catalog-wp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central Plugin Configuration
 */
function pc_get_plugin_config() {
	return array(
		'github_repo_owner'  => 'dasomweb',
		'github_repo_name'   => 'dw-catalog-wp',
		'plugin_slug'        => 'dw-catalog-wp',
		'plugin_version'     => '1.0',
		'plugin_name'        => 'DW Catalog WP',
		'plugin_text_domain' => 'dw-catalog-wp',
		'github_branch'      => 'main',
		'requires_wp'        => '5.0',
		'requires_php'       => '7.4',
	);
}

function pc_get_plugin_url() {
	return plugin_dir_url( __FILE__ );
}

function pc_get_plugin_path() {
	return plugin_dir_path( __FILE__ );
}

function pc_get_plugin_file() {
	return __FILE__;
}

/**
 * Get or create a taxonomy term by name and/or slug.
 * Generic replacement for the old pc_get_or_create_product_category.
 *
 * @param string $name     Term display name.
 * @param string $slug     Term slug.
 * @param string $taxonomy Taxonomy name.
 * @return int Term ID, or 0 on failure.
 */
function pc_get_or_create_term( $name = '', $slug = '', $taxonomy = '' ) {
	$name = trim( (string) $name );
	$slug = trim( (string) $slug );
	if ( ( $name === '' && $slug === '' ) || $taxonomy === '' ) {
		return 0;
	}

	if ( $slug !== '' ) {
		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
	}
	if ( $name !== '' ) {
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
	}

	$term_name = $name !== '' ? $name : $slug;
	$term_slug = $slug !== '' ? $slug : sanitize_title( $term_name );
	if ( $term_name === '' ) {
		return 0;
	}

	$result = wp_insert_term( $term_name, $taxonomy, array( 'slug' => $term_slug ) );
	if ( is_wp_error( $result ) ) {
		return 0;
	}
	return (int) $result['term_id'];
}

/**
 * Backward-compatible wrapper for the old function name.
 */
function pc_get_or_create_product_category( $category_name = '', $category_slug = '' ) {
	return pc_get_or_create_term( $category_name, $category_slug, 'product_category' );
}

// Load includes
require_once pc_get_plugin_path() . 'includes/class-pc-url-helper.php';
require_once pc_get_plugin_path() . 'includes/class-pc-config.php';
require_once pc_get_plugin_path() . 'includes/class-pc-github-updater.php';
require_once pc_get_plugin_path() . 'includes/class-pc-settings.php';
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
	new PC_GitHub_Updater(
		pc_get_plugin_file(),
		$config['github_repo_owner'],
		$config['github_repo_name'],
		$config['plugin_slug'],
		$config['plugin_version']
	);
}

// Initialize all components
add_action( 'plugins_loaded', 'pc_init_components', 10 );
function pc_init_components() {
	new PC_Settings();
	new PC_Post_Type();
	new PC_Meta_Box();
	new PC_Admin_Columns();
	new PC_Admin_Pages();
	new PC_Field_Reference();
	new PC_Bulk_Import();

	// PDF Export (requires composer autoload)
	require_once pc_get_plugin_path() . 'includes/class-pc-pdf-export.php';
	new PC_PDF_Export();
}

// Activation hook
register_activation_hook( __FILE__, 'pc_activate' );
function pc_activate() {
	$config = pc_get_plugin_config();
	update_option( 'pc_plugin_version', $config['plugin_version'] );
	update_option( 'pc_plugin_activated', time() );

	// Ensure default config is seeded
	PC_Config::get_post_types();

	// Register post types for rewrite flush
	$pt = new PC_Post_Type();
	$pt->register_all();
	flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'pc_deactivate' );
function pc_deactivate() {
	flush_rewrite_rules();
}
