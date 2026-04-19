<?php
/**
 * DW Catalog WP — Plugin Integrity Tests
 *
 * Standalone PHP tests (no WordPress required).
 * Validates: file structure, class definitions, prefix compliance,
 * security patterns, version consistency, and WordPress API patterns.
 *
 * Run: php tests/test-plugin-integrity.php
 *
 * @package DW_Catalog_WP
 */

$plugin_root = dirname( __DIR__ );
$pass = 0;
$fail = 0;
$errors = array();

function assert_true( $condition, $message ) {
	global $pass, $fail, $errors;
	if ( $condition ) {
		$pass++;
	} else {
		$fail++;
		$errors[] = "FAIL: $message";
	}
}

function assert_false( $condition, $message ) {
	assert_true( ! $condition, $message );
}

echo "=== DW Catalog WP — Plugin Integrity Tests ===\n\n";

// ─── 1. File Structure ─────────────────────────────────────

echo "--- 1. File Structure ---\n";

$required_files = array(
	'dw-catalog-wp.php',
	'uninstall.php',
	'includes/class-pc-config.php',
	'includes/class-pc-settings.php',
	'includes/class-pc-post-type.php',
	'includes/class-pc-meta-box.php',
	'includes/class-pc-admin-pages.php',
	'includes/class-pc-admin-columns.php',
	'includes/class-pc-bulk-import.php',
	'includes/class-pc-pdf-export.php',
	'includes/class-pc-field-reference.php',
	'includes/class-pc-product-display.php',
	'includes/class-pc-url-helper.php',
	'includes/class-pc-github-updater.php',
	'includes/class-dw-license-manager.php',
	'includes/class-dwcat-shortcodes.php',
	'assets/css/frontend.css',
	'assets/js/carousel.js',
	'assets/css/admin.css',
);

foreach ( $required_files as $file ) {
	assert_true( file_exists( "$plugin_root/$file" ), "Required file exists: $file" );
}

// ─── 2. Plugin Header ──────────────────────────────────────

echo "--- 2. Plugin Header ---\n";

$main_file = file_get_contents( "$plugin_root/dw-catalog-wp.php" );

assert_true( preg_match( '/Plugin Name:\s*DW Catalog WP/', $main_file ), 'Plugin Name header present' );
assert_true( preg_match( '/Version:\s*[\d.]+/', $main_file ), 'Version header present' );
assert_true( preg_match( '/Text Domain:\s*dw-catalog-wp/', $main_file ), 'Text Domain header present' );
assert_true( preg_match( '/Update URI:/', $main_file ), 'Update URI header present (WP guideline)' );
assert_true( preg_match( '/License:/', $main_file ), 'License header present' );
assert_true( preg_match( '/Requires at least:/', $main_file ), 'Requires at least header present' );
assert_true( preg_match( '/Requires PHP:/', $main_file ), 'Requires PHP header present' );

// Description under 140 chars
preg_match( '/Description:\s*(.+)/', $main_file, $desc_match );
if ( $desc_match ) {
	$desc_len = strlen( trim( $desc_match[1] ) );
	assert_true( $desc_len <= 140, "Description is under 140 chars (got $desc_len)" );
}

// ─── 3. Version Consistency ─────────────────────────────────

echo "--- 3. Version Consistency ---\n";

preg_match( '/\*\s*Version:\s*([\d.]+)/', $main_file, $header_ver );
preg_match( "/'plugin_version'\s*=>\s*'([\d.]+)'/", $main_file, $config_ver );

assert_true( ! empty( $header_ver[1] ), 'Version found in header' );
assert_true( ! empty( $config_ver[1] ), 'Version found in config function' );
if ( ! empty( $header_ver[1] ) && ! empty( $config_ver[1] ) ) {
	assert_true( $header_ver[1] === $config_ver[1], "Header version ({$header_ver[1]}) matches config version ({$config_ver[1]})" );
}

// ─── 4. Prefix Compliance (min 5 chars) ─────────────────────

echo "--- 4. Prefix Compliance ---\n";

$php_files = glob( "$plugin_root/includes/*.php" );
$php_files[] = "$plugin_root/dw-catalog-wp.php";

$old_prefix_found = false;
foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );
	// Check for old 2-char prefix PC_ or pc_ (excluding meta key references dw_pc_)
	$stripped = preg_replace( "/dw_pc_\w+/", '', $content ); // Remove meta key refs
	if ( preg_match( '/\bPC_[A-Z]/', $stripped ) || preg_match( '/\bpc_[a-z]/', $stripped ) ) {
		$old_prefix_found = true;
		$errors[] = "OLD PREFIX in: " . basename( $file );
	}
}
assert_false( $old_prefix_found, 'No old PC_/pc_ prefixes remain (meta keys excluded)' );

// Check new prefix is at least 5 chars
assert_true( preg_match( '/\bDWCAT_/', $main_file ), 'DWCAT_ class prefix used (5+ chars)' );
assert_true( preg_match( '/\bdwcat_/', $main_file ), 'dwcat_ function prefix used (5+ chars)' );

// ─── 5. ABSPATH Security Check ──────────────────────────────

echo "--- 5. ABSPATH Security Check ---\n";

foreach ( $php_files as $file ) {
	$content = file_get_contents( $file );
	$has_abspath = strpos( $content, "defined( 'ABSPATH' )" ) !== false
		|| strpos( $content, "defined('ABSPATH')" ) !== false;
	assert_true( $has_abspath, 'ABSPATH check in: ' . basename( $file ) );
}

// uninstall.php should check WP_UNINSTALL_PLUGIN
$uninstall = file_get_contents( "$plugin_root/uninstall.php" );
assert_true( strpos( $uninstall, 'WP_UNINSTALL_PLUGIN' ) !== false, 'uninstall.php checks WP_UNINSTALL_PLUGIN' );

// ─── 6. Class Definitions ───────────────────────────────────

echo "--- 6. Class Definitions ---\n";

$expected_classes = array(
	'DWCAT_Config'           => 'class-pc-config.php',
	'DWCAT_Settings'         => 'class-pc-settings.php',
	'DWCAT_Post_Type'        => 'class-pc-post-type.php',
	'DWCAT_Meta_Box'         => 'class-pc-meta-box.php',
	'DWCAT_Admin_Pages'      => 'class-pc-admin-pages.php',
	'DWCAT_Admin_Columns'    => 'class-pc-admin-columns.php',
	'DWCAT_Bulk_Import'      => 'class-pc-bulk-import.php',
	'DWCAT_PDF_Export'       => 'class-pc-pdf-export.php',
	'DWCAT_Field_Reference'  => 'class-pc-field-reference.php',
	'DWCAT_Product_Display'  => 'class-pc-product-display.php',
	'DWCAT_URL_Helper'       => 'class-pc-url-helper.php',
	'DWCAT_GitHub_Updater'   => 'class-pc-github-updater.php',
	'DW_License_Manager'     => 'class-dw-license-manager.php',
	'DWCAT_Shortcodes'       => 'class-dwcat-shortcodes.php',
);

foreach ( $expected_classes as $class_name => $file_name ) {
	$path = "$plugin_root/includes/$file_name";
	if ( file_exists( $path ) ) {
		$content = file_get_contents( $path );
		assert_true(
			strpos( $content, "class $class_name" ) !== false,
			"Class $class_name defined in $file_name"
		);
	}
}

// ─── 7. Nonce Patterns ─────────────────────────────────────

echo "--- 7. Security Patterns (Nonce + Sanitize) ---\n";

// All admin_post handlers should have nonce verification
$handlers_with_nonces = array(
	'class-pc-settings.php'    => 'dw_catalog_fields_nonce',
	'class-pc-admin-pages.php' => 'dw_catalog_delete_',
	'class-pc-bulk-import.php' => 'dw_catalog_import_nonce',
	'class-pc-pdf-export.php'  => 'dw_catalog_pdf_nonce',
	'class-dw-license-manager.php' => 'dw_license_nonce',
);

foreach ( $handlers_with_nonces as $file => $nonce_name ) {
	$content = file_get_contents( "$plugin_root/includes/$file" );
	assert_true(
		strpos( $content, $nonce_name ) !== false,
		"Nonce '$nonce_name' used in $file"
	);
}

// Meta box save must verify nonce
$meta_box = file_get_contents( "$plugin_root/includes/class-pc-meta-box.php" );
assert_true(
	strpos( $meta_box, 'wp_verify_nonce' ) !== false,
	'Meta box save_meta verifies nonce'
);
assert_true(
	strpos( $meta_box, 'DOING_AUTOSAVE' ) !== false,
	'Meta box save_meta checks DOING_AUTOSAVE'
);
assert_true(
	strpos( $meta_box, 'current_user_can' ) !== false,
	'Meta box save_meta checks capabilities'
);

// ─── 8. SQL Safety ──────────────────────────────────────────

echo "--- 8. SQL Safety ---\n";

assert_true(
	strpos( $uninstall, 'wpdb->prepare' ) !== false,
	'uninstall.php uses $wpdb->prepare()'
);

// ─── 9. Sanitize on $_GET['page'] ───────────────────────────

echo "--- 9. Input Sanitization ---\n";

$files_with_get_page = array(
	'class-pc-settings.php',
	'class-pc-admin-pages.php',
	'class-pc-bulk-import.php',
	'class-pc-field-reference.php',
	'class-pc-pdf-export.php',
);

foreach ( $files_with_get_page as $file ) {
	$content = file_get_contents( "$plugin_root/includes/$file" );
	// $_GET['page'] should only appear inside isset() or sanitize_text_field()
	// Find raw uses: $_GET['page'] NOT preceded by isset( or sanitize_text_field(
	$lines = explode( "\n", $content );
	$raw_uses = 0;
	foreach ( $lines as $line ) {
		if ( strpos( $line, "\$_GET['page']" ) === false ) continue;
		// OK patterns: isset( $_GET['page'] ) or sanitize_text_field( $_GET['page'] )
		$safe = preg_match( '/isset\(\s*\$_GET/', $line ) || preg_match( '/sanitize_text_field\(\s*\$_GET/', $line );
		if ( ! $safe ) {
			$raw_uses++;
		}
	}
	assert_true(
		$raw_uses === 0,
		"$file: no raw \$_GET['page'] access (found $raw_uses)"
	);
}

// ─── 10. License Integration ────────────────────────────────

echo "--- 10. License SDK (DW_License_Manager) ---\n";

$license_file = file_get_contents( "$plugin_root/includes/class-dw-license-manager.php" );
assert_true( strpos( $license_file, '/license/activate' ) !== false, 'License SDK: /license/activate endpoint' );
assert_true( strpos( $license_file, '/license/verify' ) !== false, 'License SDK: /license/verify endpoint' );
assert_true( strpos( $license_file, '/license/deactivate' ) !== false, 'License SDK: /license/deactivate endpoint' );
assert_true( strpos( $license_file, '/releases/update-check' ) !== false, 'License SDK: /releases/update-check endpoint' );
assert_true( strpos( $license_file, 'wp_remote_request' ) !== false, 'License SDK: uses wp_remote_request' );
assert_true( strpos( $license_file, 'check_ajax_referer' ) !== false, 'License SDK: AJAX nonce verification' );
assert_true( strpos( $license_file, 'pre_set_site_transient_update_plugins' ) !== false, 'License SDK: auto-update hook' );
assert_true( strpos( $license_file, 'plugins_api' ) !== false, 'License SDK: plugin_info hook' );
assert_true( strpos( $license_file, 'DW_License_Manager' ) !== false, 'License SDK: DW_License_Manager class' );

// Main file loads and initializes SDK
assert_true( strpos( $main_file, 'class-dw-license-manager.php' ) !== false, 'Main file loads license SDK' );
assert_true( strpos( $main_file, 'DW_License_Manager::init' ) !== false, 'Main file calls DW_License_Manager::init()' );

// SPA Integration
assert_true( strpos( $main_file, 'dw_spa_modules' ) !== false, 'Main file registers dw_spa_modules filter' );
assert_true( strpos( $main_file, 'function_exists' ) !== false, 'SPA integration checks function_exists (no hard dependency)' );

// ─── 11. Deactivation Hook ─────────────────────────────────

echo "--- 10b. Frontend Shortcodes ---\n";

$sc_file = file_get_contents( "$plugin_root/includes/class-dwcat-shortcodes.php" );
assert_true( strpos( $sc_file, "add_shortcode( 'dw_catalog_grid'" ) !== false, 'Shortcode: dw_catalog_grid registered' );
assert_true( strpos( $sc_file, "add_shortcode( 'dw_catalog_carousel'" ) !== false, 'Shortcode: dw_catalog_carousel registered' );
assert_true( strpos( $sc_file, "add_shortcode( 'dw_catalog_magazine'" ) !== false, 'Shortcode: dw_catalog_magazine registered' );
assert_true( strpos( $sc_file, 'shortcode_atts' ) !== false, 'Shortcodes use shortcode_atts() for defaults' );
assert_true( strpos( $sc_file, 'wp_enqueue_style' ) !== false, 'Shortcodes enqueue frontend.css' );
assert_true( strpos( $sc_file, 'wp_enqueue_script' ) !== false, 'Shortcodes enqueue carousel.js' );
assert_true( strpos( $sc_file, 'esc_url' ) !== false, 'Shortcodes escape URLs' );
assert_true( strpos( $sc_file, 'esc_html' ) !== false, 'Shortcodes escape HTML output' );
assert_true( strpos( $main_file, 'new DWCAT_Shortcodes()' ) !== false, 'Main file initializes DWCAT_Shortcodes' );

echo "--- 11. Activation/Deactivation Hooks ---\n";

assert_true( strpos( $main_file, 'register_activation_hook' ) !== false, 'Activation hook registered' );
assert_true( strpos( $main_file, 'register_deactivation_hook' ) !== false, 'Deactivation hook registered' );
assert_true( strpos( $main_file, 'unregister_post_type' ) !== false, 'Deactivation unregisters post types' );
assert_true( strpos( $main_file, 'unregister_taxonomy' ) !== false, 'Deactivation unregisters taxonomies' );
assert_true( strpos( $main_file, 'flush_rewrite_rules' ) !== false, 'Activation/deactivation flushes rewrite rules' );

// ─── 12. Migration Logic ────────────────────────────────────

echo "--- 12. Migration Logic ---\n";

assert_true( strpos( $main_file, 'dwcat_migrate' ) !== false, 'Migration function exists' );
assert_true( strpos( $main_file, 'dwcat_check_version' ) !== false, 'Version check on admin_init exists' );

// ─── Results ────────────────────────────────────────────────

echo "\n=== RESULTS ===\n";
echo "PASS: $pass\n";
echo "FAIL: $fail\n";

if ( ! empty( $errors ) ) {
	echo "\nFailed tests:\n";
	foreach ( $errors as $e ) {
		echo "  - $e\n";
	}
}

echo "\n" . ( $fail === 0 ? "ALL TESTS PASSED" : "$fail TEST(S) FAILED" ) . "\n";
exit( $fail > 0 ? 1 : 0 );
