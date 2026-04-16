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

// Remove field options for all known post types
global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", 'dw\_catalog\_fields\_%' ) );

// Remove license options
delete_option( 'dwcat_license_key' );
delete_option( 'dwcat_license_status' );
delete_option( 'dwcat_license_expires' );
delete_transient( 'dwcat_license_verified' );
