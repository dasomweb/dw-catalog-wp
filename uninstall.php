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

// ── DASOM-Forge License SDK (DW_DWCAT_License_Manager) ──────────────
// 옵션 키는 SDK 의 cache_prefix('dwcat_') + product_slug 규약을 따릅니다.
delete_option( 'dw_license_dw_catalog_wp' );  // 라이선스 상태 (권위 저장소)
delete_option( 'dwcat_token' );               // JWT 캐시
delete_option( 'dwcat_tamper_notice' );       // TAMPER_DETECTED mismatched_files
delete_option( 'dwcat_last_sdk_error' );      // 마지막 SDK 오류 (§3.6)
delete_option( 'dwcat_platform_notice' );     // INTEGRITY_MANIFEST_NOT_FOUND 등
delete_option( 'dwcat_offline_since' );       // 연속 실패 추적
delete_option( 'dwcat_legacy_grace_until' );  // v1.x 마이그레이션 유예

// 멀티사이트: 라이선스는 네트워크 옵션에 저장됩니다 (FAQ Q10).
// 토큰·노티스 계열은 사이트별이라 위에서 이미 정리됨.
if ( is_multisite() ) {
	delete_network_option( null, 'dw_license_dw_catalog_wp' );
}

delete_transient( 'dwcat_token_backoff' );
delete_transient( 'dwcat_update_info' );
delete_transient( 'dwcat_runtime_manifest' );

wp_clear_scheduled_hook( 'dw_verify_license_dw-catalog-wp' );
wp_clear_scheduled_hook( 'dwcat_refresh_token' );

// v1.x 잔여물 정리
delete_transient( 'dw_license_check_dw-catalog-wp' );
