<?php
/**
 * Uninstall DW Catalog WP
 *
 * Cleans up plugin options when the plugin is deleted via WordPress admin.
 *
 * @package DW_Catalog_WP
 */

// Abort if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options
delete_option( 'dw_catalog_post_types' );
delete_option( 'dwcat_version' );
delete_option( 'dwcat_activated' );
delete_option( 'dwcat_design_settings' );

// Remove field options for all known post types
global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'dw\_catalog\_fields\_%' ) );

// Remove license options (DW License Manager SDK)
delete_option( 'dw_license_dw_catalog_wp' );
delete_transient( 'dw_license_check_dw-catalog-wp' );
wp_clear_scheduled_hook( 'dw_verify_license_dw-catalog-wp' );
