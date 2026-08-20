<?php
/**
 * Plugin Name: DW Catalog WP
 * Plugin URI: https://github.com/dasomweb/dw-catalog-wp
 * Description: Product catalog with dynamic custom fields per post type.
 * Version: 2.0.0
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
		'plugin_version'     => '2.0.0',
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

// DASOM-Forge SDK — Forge Client 를 먼저 (License Manager 가 생성자에서 사용)
require_once dwcat_get_path() . 'includes/class-dw-forge-client.php';
require_once dwcat_get_path() . 'includes/class-dw-license-manager.php';
require_once dwcat_get_path() . 'includes/license/gates.php';

require_once dwcat_get_path() . 'includes/class-pc-settings.php';
require_once dwcat_get_path() . 'includes/class-pc-post-type.php';
require_once dwcat_get_path() . 'includes/class-pc-meta-box.php';
require_once dwcat_get_path() . 'includes/class-pc-product-display.php';
require_once dwcat_get_path() . 'includes/class-pc-admin-columns.php';
require_once dwcat_get_path() . 'includes/class-pc-admin-pages.php';
require_once dwcat_get_path() . 'includes/class-pc-field-reference.php';
require_once dwcat_get_path() . 'includes/class-pc-bulk-import.php';
require_once dwcat_get_path() . 'includes/class-dwcat-design-settings.php';
require_once dwcat_get_path() . 'includes/class-dwcat-shortcodes.php';

/**
 * DASOM-Forge License SDK.
 *
 * Prefix 배포 전략 (PLUGIN-DEV-GUIDE §3.5 B) — 클래스는 DW_DWCAT_* 로 격리되어
 * 다른 DW 플러그인이 ship 한 SDK 버전과 winner race 를 벌이지 않습니다.
 *
 * plugins_loaded 보다 먼저 등록해야 admin_menu / admin_post 훅을 놓치지 않습니다.
 */
DW_DWCAT_License_Manager::init( array(
	'product_slug'    => 'dw-catalog-wp',
	'cache_prefix'    => 'dwcat_',
	'plugin_file'     => __FILE__,
	'plugin_basename' => plugin_basename( __FILE__ ),
	'plugin_name'     => 'DW Catalog WP',
	'plugin_version'  => '2.0.0',
	'settings_page'   => 'dw-catalog-license',
	'public_keys'     => array(
		dwcat_get_path() . 'includes/keys/dasomforge.pub',
		dwcat_get_path() . 'includes/keys/dasomforge.previous.pub', // 키 회전 시에만 존재
	),
) );

/**
 * GitHub Updater — dasomforge 업데이트 경로와 **상호 배타**.
 *
 * 두 업데이터가 동시에 pre_set_site_transient_update_plugins 에 붙으면
 * 서로의 응답을 덮어써 업데이트가 오락가락합니다. PLUGIN-DEV-GUIDE §7.4 는
 * 고객이 dasomforge 가 발급한 R2 signed URL 로만 받도록 규정하므로,
 * 라이선스가 등록된 사이트에서는 GitHub 직접 다운로드 경로를 끕니다.
 */
add_action( 'plugins_loaded', 'dwcat_init_updater', 10 );
function dwcat_init_updater() {
	$license = get_option( 'dw_license_dw_catalog_wp', array() );
	if ( is_array( $license ) && ! empty( $license['key'] ) ) {
		return; // dasomforge 가 업데이트를 담당
	}

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
	new DWCAT_Design_Settings();
	new DWCAT_Shortcodes();

	// DW Admin SPA Integration (optional — works without DW Admin)
	// 게이트 9: 라이선스가 없으면 SPA 모듈을 노출하지 않습니다.
	if ( function_exists( 'dw_admin' ) && dwcat_can_call_rest() ) {
		$spa_post_types = DWCAT_Config::get_post_types();
		foreach ( $spa_post_types as $spa_slug => $spa_config ) {
			$spa_columns = array();
			$spa_fields  = DWCAT_Config::get_list_fields( $spa_slug );
			foreach ( $spa_fields as $f ) {
				if ( empty( $f['is_title_field'] ) ) {
					$spa_columns[] = array( 'key' => $f['meta_key'], 'label' => $f['label'] );
				}
			}
			array_unshift( $spa_columns, array( 'key' => 'title', 'label' => $spa_config['singular_name'] ) );

			add_filter( 'dw_spa_modules', function ( $modules ) use ( $spa_slug, $spa_config, $spa_columns ) {
				$modules[] = array(
					'key'       => 'dwcat-' . $spa_slug,
					'label'     => $spa_config['menu_name'],
					'icon'      => 'grid',
					'post_type' => $spa_slug,
					'rest_base' => rest_url( 'wp/v2/' . $spa_slug ),
					'columns'   => $spa_columns,
					'roles'     => array(),
				);
				return $modules;
			} );
		}
	}

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

	// 라이선스 일일 검증 cron 등록
	DW_DWCAT_License_Manager::on_activate();

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
	// v1.x → v2.0 하위호환 (KICKOFF FAQ Q8) —
	// 자동 업데이트로 올라온 기존 사이트의 카탈로그가 즉시 사라지면 안 됩니다.
	// 업그레이드 설치에만 1회 유예를 부여하고, 신규 설치는 즉시 게이트 적용.
	dwcat_maybe_grant_legacy_grace( $old_version );

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

		// PLUGIN-CALL-FLOWS §4 — 새 버전은 새 file_hashes 를 갖습니다.
		// 캐시 토큰은 구 버전 매니페스트로 발급된 것이라 즉시 폐기하고
		// 다음 요청에서 새 버전으로 재발급받아야 운영자 verify 가 ✅ 로 뜹니다.
		DW_DWCAT_License_Manager::clear_token_cache();

		flush_rewrite_rules();
	}
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'dwcat_deactivate' );
function dwcat_deactivate() {
	// 라이선스 cron 해제 (라이선스 자체는 보존 — 재활성화 시 그대로 복구)
	DW_DWCAT_License_Manager::on_deactivate_plugin();

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
