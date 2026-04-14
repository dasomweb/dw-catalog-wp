<?php
/**
 * Plugin Name: DW Catalog WP
 * Plugin URI: https://github.com/dasomweb/dw-catalog-wp
 * Description: Product catalog with dynamic custom fields per post type.
 * Version: 1.0.4
 * Author: Dasom Web
 * Author URI: https://github.com/dasomweb
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI: https://github.com/dasomweb/dw-catalog-wp
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
function dwcat_get_config() {
	return array(
		'github_repo_owner'  => 'dasomweb',
		'github_repo_name'   => 'dw-catalog-wp',
		'plugin_slug'        => 'dw-catalog-wp',
		'plugin_version'     => '1.0.4',
		'plugin_name'        => 'DW Catalog WP',
		'plugin_text_domain' => 'dw-catalog-wp',
		'github_branch'      => 'main',
		'requires_wp'        => '5.0',
		'requires_php'       => '7.4',
	);
}

function dwcat_get_url() {
	return plugin_dir_url( __FILE__ );
}

function dwcat_get_path() {
	return plugin_dir_path( __FILE__ );
}

function dwcat_get_file() {
	return __FILE__;
}

/**
 * Get or create a taxonomy term by name and/or slug.
 * Generic replacement for the old dwcat_get_or_create_product_category.
 *
 * @param string $name     Term display name.
 * @param string $slug     Term slug.
 * @param string $taxonomy Taxonomy name.
 * @return int Term ID, or 0 on failure.
 */
function dwcat_get_or_create_term( $name = '', $slug = '', $taxonomy = '' ) {
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
function dwcat_get_or_create_product_category( $category_name = '', $category_slug = '' ) {
	return dwcat_get_or_create_term( $category_name, $category_slug, 'product_category' );
}

// Load includes
require_once dwcat_get_path() . 'includes/class-pc-url-helper.php';
require_once dwcat_get_path() . 'includes/class-pc-config.php';
require_once dwcat_get_path() . 'includes/class-pc-github-updater.php';
require_once dwcat_get_path() . 'includes/class-pc-settings.php';
require_once dwcat_get_path() . 'includes/class-pc-post-type.php';
require_once dwcat_get_path() . 'includes/class-pc-meta-box.php';
require_once dwcat_get_path() . 'includes/class-pc-product-display.php';
require_once dwcat_get_path() . 'includes/class-pc-admin-columns.php';
require_once dwcat_get_path() . 'includes/class-pc-admin-pages.php';
require_once dwcat_get_path() . 'includes/class-pc-field-reference.php';
require_once dwcat_get_path() . 'includes/class-pc-bulk-import.php';

// Initialize GitHub Updater
add_action( 'plugins_loaded', 'dwcat_init_updater', 10 );
function dwcat_init_updater() {
	$config = dwcat_get_config();
	new DWCAT_GitHub_Updater(
		dwcat_get_file(),
		$config['github_repo_owner'],
		$config['github_repo_name'],
		$config['plugin_slug'],
		$config['plugin_version']
	);
}

// Initialize all components
add_action( 'plugins_loaded', 'dwcat_init', 10 );
function dwcat_init() {
	new DWCAT_Settings();
	new DWCAT_Post_Type();
	new DWCAT_Meta_Box();
	new DWCAT_Admin_Columns();
	new DWCAT_Admin_Pages();
	new DWCAT_Field_Reference();
	new DWCAT_Bulk_Import();

	// PDF Export (requires composer autoload)
	require_once dwcat_get_path() . 'includes/class-pc-pdf-export.php';
	new DWCAT_PDF_Export();
}

// Activation hook
register_activation_hook( __FILE__, 'dwcat_activate' );
function dwcat_activate() {
	$config = dwcat_get_config();
	$old_version = get_option( 'dwcat_version', '' );

	update_option( 'dwcat_version', $config['plugin_version'] );
	update_option( 'dwcat_activated', time() );

	// Ensure default config is seeded
	DWCAT_Config::get_post_types();

	// Migration: seed default fields for existing post types that have no field config yet
	dwcat_migrate( $old_version, $config['plugin_version'] );

	// Register post types for rewrite flush
	$pt = new DWCAT_Post_Type();
	$pt->register_all();
	flush_rewrite_rules();
}

/**
 * Migration: runs on activation when upgrading from an older version.
 * Ensures hardcoded fields from pre-1.0 are migrated to the dynamic field config.
 */
function dwcat_migrate( $old_version, $new_version ) {
	// If upgrading from pre-1.0 (old pc_ era) or fresh install with no field config
	$post_types = DWCAT_Config::get_post_types();
	foreach ( $post_types as $slug => $config ) {
		$fields = get_option( DWCAT_Config::OPTION_FIELDS_PREFIX . $slug, null );
		if ( $fields === null || ( is_array( $fields ) && empty( $fields ) ) ) {
			// No fields configured yet — seed defaults
			$defaults = DWCAT_Config::get_default_fields( $slug );
			if ( ! empty( $defaults ) ) {
				update_option( DWCAT_Config::OPTION_FIELDS_PREFIX . $slug, $defaults, true );
			}
		}
	}
}

// Version check on every admin load — handles upgrades without re-activation
add_action( 'admin_init', 'dwcat_check_version' );
function dwcat_check_version() {
	$config = dwcat_get_config();
	$stored = get_option( 'dwcat_version', '' );
	if ( $stored !== $config['plugin_version'] ) {
		dwcat_migrate( $stored, $config['plugin_version'] );
		update_option( 'dwcat_version', $config['plugin_version'] );
		flush_rewrite_rules();
	}
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'dwcat_deactivate' );
function dwcat_deactivate() {
	// Unregister post types and taxonomies before flushing
	$post_types = DWCAT_Config::get_post_types();
	foreach ( $post_types as $slug => $config ) {
		unregister_post_type( $slug );
		if ( ! empty( $config['has_category'] ) ) {
			unregister_taxonomy( DWCAT_Config::get_category_taxonomy( $slug ) );
		}
		if ( ! empty( $config['has_tag'] ) ) {
			unregister_taxonomy( DWCAT_Config::get_tag_taxonomy( $slug ) );
		}
	}
	flush_rewrite_rules();
}
