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
	'includes/class-dw-forge-client.php',
	'includes/license/gates.php',
	'includes/keys/dasomforge.pub',
	'includes/class-dwcat-shortcodes.php',
	'includes/class-dwcat-design-settings.php',
	'assets/css/frontend.css',
	'assets/js/carousel.js',
	'assets/css/admin.css',
	// dasomforge 빌드 파이프라인 (PLUGIN-DEV-GUIDE §7.2 / §8)
	'.gitattributes',
	'composer.lock',
	'build/generate-integrity.js',
	'build/generate-release-manifest.js',
	'build/verify-package.js',
	'.github/scripts/package-release.sh',
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

$php_files = array_merge(
	glob( "$plugin_root/includes/*.php" ),
	glob( "$plugin_root/includes/*/*.php" )   // includes/license/gates.php
);
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
	'DW_DWCAT_License_Manager' => 'class-dw-license-manager.php',
	'DW_DWCAT_Forge_Client'    => 'class-dw-forge-client.php',
	'DWCAT_Shortcodes'       => 'class-dwcat-shortcodes.php',
	'DWCAT_Design_Settings'  => 'class-dwcat-design-settings.php',
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
	// SDK 는 nonce action 을 cache_prefix + 'license_action' 으로 조합하므로
	// 리터럴 전체가 아니라 조합 조각을 확인한다 (check_admin_referer 자체는 §10 에서 검증).
	'class-dw-license-manager.php' => "'license_action'",
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

echo "--- 10. License SDK (DW_DWCAT_License_Manager) ---\n";

$license_file = file_get_contents( "$plugin_root/includes/class-dw-license-manager.php" );
$client_file  = file_get_contents( "$plugin_root/includes/class-dw-forge-client.php" );
$gates_file   = file_get_contents( "$plugin_root/includes/license/gates.php" );

// legacy flat endpoints (API-CONTRACT §3 / §7.1)
assert_true( strpos( $license_file, '/license/activate' ) !== false, 'License SDK: /license/activate endpoint' );
assert_true( strpos( $license_file, '/license/verify' ) !== false, 'License SDK: /license/verify endpoint' );
assert_true( strpos( $license_file, '/license/deactivate' ) !== false, 'License SDK: /license/deactivate endpoint' );
assert_true( strpos( $license_file, '/releases/update-check' ) !== false, 'License SDK: /releases/update-check endpoint' );

// v3.0 envelope endpoints (API-CONTRACT §4 / §5 / §6)
assert_true( strpos( $license_file, '/auth/token' ) !== false, 'License SDK: /auth/token endpoint' );
assert_true( strpos( $license_file, '/configs/sign' ) !== false, 'License SDK: /configs/sign endpoint' );
assert_true( strpos( $license_file, '/integrity/' ) !== false, 'License SDK: /integrity manifest endpoint' );

// Prefix 배포 전략 (PLUGIN-DEV-GUIDE §3.5 B)
assert_true( strpos( $license_file, 'class DW_DWCAT_License_Manager' ) !== false, 'License SDK: prefixed class name' );
assert_true( strpos( $client_file, 'class DW_DWCAT_Forge_Client' ) !== false, 'Forge Client: prefixed class name' );
assert_false( preg_match( '/\bclass DW_License_Manager\b/', $license_file ), 'License SDK: unprefixed class NOT declared (winner race avoidance)' );

// 모든 외부 호출은 Forge Client 를 경유 (PLUGIN-DEV-GUIDE §1.3 SHOULD 4)
assert_true( strpos( $client_file, 'wp_remote_request' ) !== false, 'Forge Client: uses wp_remote_request' );
assert_false( strpos( $license_file, 'wp_remote_request' ) !== false, 'License SDK: does NOT bypass Forge Client' );

// 관리자 액션 보안
assert_true( strpos( $license_file, 'check_admin_referer' ) !== false, 'License SDK: admin-post nonce verification' );
assert_true( strpos( $license_file, 'current_user_can' ) !== false, 'License SDK: capability check' );
assert_true( strpos( $license_file, 'pre_set_site_transient_update_plugins' ) !== false, 'License SDK: auto-update hook' );
assert_true( strpos( $license_file, 'plugins_api' ) !== false, 'License SDK: plugin_info hook' );

// §11.1 운영자 통합 검증 호환성
assert_true( strpos( $license_file, 'resolve_token( true )' ) !== false, 'S11.1.a: activate 직후 강제 토큰 발급' );
assert_true( strpos( $license_file, 'maybe_lazy_refresh' ) !== false, 'S11.1.b: 어드민 페이지 lazy refresh' );
assert_true( strpos( $license_file, "'refresh'" ) !== false, 'S11.1.c: 명시적 새로고침 액션' );

// §11.2.c TAMPER_DETECTED 를 삼키지 않는다
assert_true( strpos( $license_file, 'TAMPER_DETECTED' ) !== false, 'S11.2.c: TAMPER_DETECTED 처리' );
assert_true( strpos( $license_file, 'mismatched_files' ) !== false, 'S11.2.c: mismatched_files 보존' );
assert_true( strpos( $license_file, 'render_admin_notices' ) !== false, 'S11.2.c: 어드민 notice 렌더' );

// §7.2.2 무결성 키 규약 — integrity.json 항목을 그대로 사용
assert_true( strpos( $license_file, 'integrity.json' ) !== false, 'S7.2.2: integrity.json 을 읽는다' );
assert_true( strpos( $license_file, "hash_file( 'sha256'" ) !== false, 'S7.2.2: SHA-256 hex' );

// PLUGIN-CALL-FLOWS §3 — synthetic_test 플래그는 절대 전송 금지
assert_false( preg_match( "/'synthetic_test'\s*=>/", $license_file ), 'synthetic_test 플래그를 전송하지 않는다' );

// API-CONTRACT §1.1 — 실 사이트 User-Agent 형식
assert_true( strpos( $client_file, 'DW-%s/%s (WordPress/%s; PHP/%s)' ) !== false, 'API-CONTRACT S1.1: User-Agent 형식' );

// Main file loads and initializes SDK
assert_true( strpos( $main_file, 'class-dw-license-manager.php' ) !== false, 'Main file loads license SDK' );
assert_true( strpos( $main_file, 'class-dw-forge-client.php' ) !== false, 'Main file loads Forge Client' );
assert_true( strpos( $main_file, 'license/gates.php' ) !== false, 'Main file loads license gates' );
assert_true( strpos( $main_file, 'DW_DWCAT_License_Manager::init' ) !== false, 'Main file calls DW_DWCAT_License_Manager::init()' );

// SPA Integration
assert_true( strpos( $main_file, 'dw_spa_modules' ) !== false, 'Main file registers dw_spa_modules filter' );
assert_true( strpos( $main_file, 'function_exists' ) !== false, 'SPA integration checks function_exists (no hard dependency)' );

// ─── 10a. 라이선스 게이트 분산 (PLUGIN-DEV-GUIDE §6) ─────────

echo "--- 10a. License Gates (anti-piracy) ---\n";

$gates = array(
	'dwcat_can_render',
	'dwcat_can_load_assets',
	'dwcat_can_save_meta',
	'dwcat_can_manage_fields',
	'dwcat_can_bulk_import',
	'dwcat_can_export_pdf',
	'dwcat_can_render_admin',
	'dwcat_can_use_shortcode',
	'dwcat_can_call_rest',
);
foreach ( $gates as $g ) {
	assert_true( strpos( $gates_file, "function $g(" ) !== false, "Gate defined: $g()" );
}
assert_true( count( $gates ) >= 5, 'S6 SHOULD: 5개 이상 게이트 분산 (' . count( $gates ) . '개)' );

// 게이트가 서로 다른 검증 경로를 쓰는가 — 전부 같은 함수면 한 줄 패치로 뚫린다
$verify_paths = array( 'has_valid_token', 'verify_token_signature', 'get_token_claims', 'get_status' );
foreach ( $verify_paths as $path_fn ) {
	assert_true( strpos( $gates_file, $path_fn ) !== false, "S6: 검증 경로 사용 — $path_fn" );
}

// is_callable 가드 (§3.3 / SDK-README) — method_exists 보다 정확
assert_true( strpos( $gates_file, 'is_callable' ) !== false, 'S3.3: is_callable() 가드' );

// dev bypass — 플러그인 코드가 아니라 wp-config.php 에서만 true 로 정의되어야 함
assert_true( strpos( $gates_file, 'DWCAT_DEV_BYPASS' ) !== false, 'dev bypass 상수 지원' );
assert_false(
	preg_match( "/define\(\s*'DWCAT_DEV_BYPASS'\s*,\s*true/", $gates_file ),
	'dev bypass 를 플러그인 코드에서 true 로 정의하지 않는다'
);

// Strict vs Loose 분리 (§3.4)
assert_true( strpos( $gates_file, 'function dwcat_is_licensed(' ) !== false, 'S3.4: Loose helper 존재' );

// v1.x 하위호환 유예 (KICKOFF FAQ Q8)
assert_true( strpos( $gates_file, 'dwcat_maybe_grant_legacy_grace' ) !== false, 'FAQ Q8: v1.x 마이그레이션 유예' );
assert_true( strpos( $main_file, 'dwcat_maybe_grant_legacy_grace' ) !== false, 'FAQ Q8: 마이그레이션에서 유예 부여' );

// 게이트가 실제 호출 지점에 적용되었는가
$gate_sites = array(
	'class-dwcat-shortcodes.php' => 'dwcat_can_render',
	'class-pc-meta-box.php'      => 'dwcat_can_save_meta',
	'class-pc-bulk-import.php'   => 'dwcat_can_bulk_import',
	'class-pc-pdf-export.php'    => 'dwcat_can_export_pdf',
	'class-pc-settings.php'      => 'dwcat_can_manage_fields',
	'class-pc-admin-pages.php'   => 'dwcat_can_render_admin',
);
foreach ( $gate_sites as $gs_file => $gs_gate ) {
	$gs_content = file_get_contents( "$plugin_root/includes/$gs_file" );
	assert_true( strpos( $gs_content, $gs_gate . '()' ) !== false, "Gate applied: $gs_gate() in $gs_file" );
}

// ─── 10c. 릴리스 패키징 계약 (§7.2 / §7.3 / §11.2.b) ─────────

echo "--- 10c. Release Packaging Contract ---\n";

$pkg = file_get_contents( "$plugin_root/.github/scripts/package-release.sh" );
assert_true( strpos( $pkg, 'wp-plugin' ) !== false, 'S7.2: wp-plugin/ 스테이징' );
assert_true( strpos( $pkg, 'manifest.json' ) !== false, 'S7.3: manifest.json 생성' );
assert_true( strpos( $pkg, 'SOURCE_DATE_EPOCH' ) !== false, 'S11.2.b: 결정론적 타임스탬프' );
assert_true( strpos( $pkg, 'zip -X' ) !== false, 'S11.2.b: zip -X (extra field 제거)' );
assert_true( strpos( $pkg, 'chmod 644' ) !== false, 'S11.2.b: 파일 권한 표준화' );
assert_true( strpos( $pkg, 'wp-config.php' ) !== false, 'FAQ Q7: wp-config.php 혼입 차단' );

$wf = file_get_contents( "$plugin_root/.github/workflows/release.yml" );
assert_true( strpos( $wf, 'verify-package.js' ) !== false, 'CI: 패키지 계약 검증 실행' );
assert_true( strpos( $wf, 'composer.lock' ) !== false, 'CI: composer.lock 존재 강제' );
assert_true( strpos( $wf, 'build #2' ) !== false, 'S11.3.4: 두 번 빌드해 SHA256 비교' );

$ga = file_get_contents( "$plugin_root/.gitattributes" );
assert_true( strpos( $ga, 'text=auto eol=lf' ) !== false, 'S11.2.b: 라인엔딩 통일' );
assert_true( strpos( $ga, 'binary' ) !== false, 'S11.2.b: 바이너리 명시' );

// integrity.json 은 커밋 대상이 아니다 (빌드 산출물)
$gi = file_get_contents( "$plugin_root/.gitignore" );
assert_true( strpos( $gi, '/integrity.json' ) !== false, 'integrity.json 은 gitignore (stale 매니페스트 방지)' );
assert_false( file_exists( "$plugin_root/integrity.json" ), 'integrity.json 이 소스트리에 커밋되어 있지 않다' );

// 공개키 위생
$pub = file_get_contents( "$plugin_root/includes/keys/dasomforge.pub" );
assert_true( strpos( $pub, 'BEGIN PUBLIC KEY' ) !== false, 'dasomforge.pub 은 PEM 공개키' );
assert_false( strpos( $pub, 'PRIVATE KEY' ) !== false, 'dasomforge.pub 에 비밀키가 없다' );

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

// Design Settings
$design_file = file_get_contents( "$plugin_root/includes/class-dwcat-design-settings.php" );
assert_true( strpos( $design_file, 'admin_post_dwcat_save_design' ) !== false, 'Design: save handler registered' );
assert_true( strpos( $design_file, 'dwcat_save_design' ) !== false, 'Design: nonce action present' );
assert_true( strpos( $design_file, 'get_css_vars' ) !== false, 'Design: get_css_vars() method' );
assert_true( strpos( $design_file, 'get_inline_style' ) !== false, 'Design: get_inline_style() method' );
assert_true( strpos( $design_file, 'wp-color-picker' ) !== false, 'Design: uses wp-color-picker' );

// Shortcodes use design settings
$sc2 = file_get_contents( "$plugin_root/includes/class-dwcat-shortcodes.php" );
assert_true( strpos( $sc2, 'DWCAT_Design_Settings::get_inline_style' ) !== false, 'Shortcodes inject design CSS vars' );

// CSS uses variables
$css = file_get_contents( "$plugin_root/assets/css/frontend.css" );
assert_true( strpos( $css, 'var(--dwcat-title-size' ) !== false, 'CSS uses --dwcat-title-size variable' );
assert_true( strpos( $css, 'var(--dwcat-card-bg' ) !== false, 'CSS uses --dwcat-card-bg variable' );
assert_true( strpos( $css, 'var(--dwcat-mag-title-size' ) !== false, 'CSS uses --dwcat-mag-title-size variable' );

// uninstall cleans design option
$uninstall2 = file_get_contents( "$plugin_root/uninstall.php" );
assert_true( strpos( $uninstall2, 'dwcat_design_settings' ) !== false, 'uninstall.php removes design settings option' );

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
