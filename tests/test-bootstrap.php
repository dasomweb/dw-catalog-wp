<?php
/**
 * DW Catalog WP — 부트스트랩 · 라이선스 게이트 런타임 테스트
 *
 * WordPress 없이 최소 스텁만 두고 플러그인 진입 파일을 실제로 require 해서
 * 로드 순서 · 게이트 fail-closed · 레거시 유예 · dev bypass 를 **실행**으로 검증한다.
 * (test-plugin-integrity.php 는 정적 문자열 검사, 이쪽은 동작 검사)
 *
 * 실행: php tests/test-bootstrap.php
 *
 * @package DW_Catalog_WP
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'WP_DEBUG', false );

$GLOBALS['wp_version']   = '6.7';
$GLOBALS['_options']     = array();
$GLOBALS['_transients']  = array();
$GLOBALS['_actions']     = array();
$GLOBALS['_filters']     = array();
$GLOBALS['_http_calls']  = array();

function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['_actions'][ $h ][] = $cb; return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['_filters'][ $h ][] = $cb; return true; }
function do_action( $h ) { foreach ( $GLOBALS['_actions'][ $h ] ?? array() as $cb ) { call_user_func( $cb ); } }
function add_shortcode( $t, $cb ) { return true; }
function register_activation_hook( $f, $cb ) { return true; }
function register_deactivation_hook( $f, $cb ) { return true; }
function add_submenu_page() { return ''; }

function get_option( $k, $d = false ) { return $GLOBALS['_options'][ $k ] ?? $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['_options'][ $k ] = $v; return true; }
function add_option( $k, $v, $x = '', $a = null ) {
	if ( array_key_exists( $k, $GLOBALS['_options'] ) ) return false;
	$GLOBALS['_options'][ $k ] = $v; return true;
}
function delete_option( $k ) { unset( $GLOBALS['_options'][ $k ] ); return true; }
function get_transient( $k ) { return $GLOBALS['_transients'][ $k ] ?? false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['_transients'][ $k ] = $v; return true; }
function delete_transient( $k ) { unset( $GLOBALS['_transients'][ $k ] ); return true; }

function home_url( $p = '' ) { return 'https://canary.example.com' . $p; }
function admin_url( $p = '' ) { return 'https://canary.example.com/wp-admin/' . $p; }
function rest_url( $p = '' ) { return home_url( '/wp-json/' . $p ); }
function plugin_dir_path( $f ) { return rtrim( str_replace( '\\', '/', dirname( $f ) ), '/' ) . '/'; }
function plugin_dir_url( $f ) { return 'https://canary.example.com/wp-content/plugins/dw-catalog-wp/'; }
function plugin_basename( $f ) { return 'dw-catalog-wp/' . basename( $f ); }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_html__( $s, $d = '' ) { return $s; }
function esc_html_e( $s, $d = '' ) { echo $s; }
function __( $s, $d = '' ) { return $s; }
function _e( $s, $d = '' ) { echo $s; }
function current_user_can( $c ) { return true; }
function is_admin() { return false; }
function current_time( $t ) { return gmdate( 'Y-m-d H:i:s' ); }
function wp_next_scheduled( $h ) { return false; }
function wp_schedule_event( $t, $r, $h ) { return true; }
function wp_schedule_single_event( $t, $h, $a = array() ) { return true; }
function wp_clear_scheduled_hook( $h ) { return true; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9]+/i', '-', $s ) ); }
function wp_unslash( $s ) { return $s; }
function wp_die( $m = '' ) { throw new RuntimeException( 'wp_die: ' . $m ); }
function is_wp_error( $t ) { return false; }
function wp_remote_request( $url, $args = array() ) {
	$GLOBALS['_http_calls'][] = array( 'url' => $url, 'args' => $args );
	return array( 'response' => array( 'code' => 503 ), 'body' => '{"ok":false,"error":{"code":"SERVICE_UNAVAILABLE"}}' );
}
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function wp_remote_retrieve_header( $r, $h ) { return ''; }
function add_query_arg( $a, $u = '' ) { return $u . '?' . http_build_query( $a ); }
function get_current_screen() { return null; }
function wp_nonce_field() {}
function check_admin_referer() { return true; }
function wp_safe_redirect( $u ) {}
function wp_create_nonce( $a ) { return 'nonce'; }
function nl2br_esc( $s ) { return $s; }

$root = isset( $argv[1] ) ? $argv[1] : dirname( __DIR__ );
$pass = 0; $fail = 0;
function t( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "  ✓ $msg\n"; }
	else         { $fail++; echo "  ✗ $msg\n"; }
}

echo "=== bootstrap smoke ===\n\n--- load ---\n";
try {
	require_once $root . '/dw-catalog-wp.php';
	t( true, 'plugin file loaded without fatal' );
} catch ( Throwable $e ) {
	t( false, 'plugin file loaded: ' . $e->getMessage() );
	echo "\nFATAL — cannot continue\n"; exit( 1 );
}

t( class_exists( 'DW_DWCAT_License_Manager' ), 'DW_DWCAT_License_Manager loaded' );
t( class_exists( 'DW_DWCAT_Forge_Client' ), 'DW_DWCAT_Forge_Client loaded' );
t( ! class_exists( 'DW_License_Manager', false ), 'unprefixed DW_License_Manager NOT declared' );
t( function_exists( 'dwcat_can_render' ), 'gates loaded' );

echo "\n--- unlicensed: every gate must be closed ---\n";
$gates = array( 'dwcat_can_render', 'dwcat_can_load_assets', 'dwcat_can_save_meta',
	'dwcat_can_manage_fields', 'dwcat_can_bulk_import', 'dwcat_can_export_pdf',
	'dwcat_can_render_admin', 'dwcat_can_use_shortcode', 'dwcat_can_call_rest' );
foreach ( $gates as $g ) {
	t( $g() === false, "$g() === false when unlicensed" );
}
t( dwcat_is_licensed() === false, 'dwcat_is_licensed() false (loose helper)' );

echo "\n--- legacy grace: v1.x upgrade must keep working ---\n";
$GLOBALS['_options']['dwcat_version'] = '1.2.1';
dwcat_maybe_grant_legacy_grace( '1.2.1' );
t( isset( $GLOBALS['_options']['dwcat_legacy_grace_until'] ), 'grace granted on v1.x upgrade' );
t( dwcat_in_legacy_grace() === true, 'grace window is open' );
t( dwcat_legacy_grace_days_left() === 30, 'grace = 30 days (got ' . dwcat_legacy_grace_days_left() . ')' );
$open = 0;
foreach ( $gates as $g ) { if ( $g() === true ) $open++; }
t( $open === count( $gates ), "all $open/" . count( $gates ) . ' gates open during grace' );

echo "\n--- grace is granted once only, and not to fresh installs ---\n";
$before = $GLOBALS['_options']['dwcat_legacy_grace_until'];
$GLOBALS['_options']['dwcat_legacy_grace_until'] = time() - 1;  // 만료 시뮬레이션
dwcat_maybe_grant_legacy_grace( '1.2.1' );
t( $GLOBALS['_options']['dwcat_legacy_grace_until'] < time(), 'expired grace is NOT re-granted' );
foreach ( $gates as $g ) { if ( $g() === true ) { t( false, "$g() still open after grace expiry" ); break; } }
t( dwcat_can_render() === false, 'gates close again after grace expiry' );

unset( $GLOBALS['_options']['dwcat_legacy_grace_until'] );
dwcat_maybe_grant_legacy_grace( '' );   // 신규 설치
t( ! isset( $GLOBALS['_options']['dwcat_legacy_grace_until'] ), 'fresh install gets NO grace' );

echo "\n--- dev bypass ---\n";
define( 'DWCAT_DEV_BYPASS', true );
$open = 0;
foreach ( $gates as $g ) { if ( $g() === true ) $open++; }
t( $open === count( $gates ), "dev bypass opens all $open/" . count( $gates ) . ' gates' );

echo "\n--- SDK behaviour with no license ---\n";
t( DW_DWCAT_License_Manager::get_status() === 'unlicensed', 'get_status() = unlicensed' );
t( DW_DWCAT_License_Manager::has_valid_token() === false, 'has_valid_token() = false' );
$has_manifest = file_exists( $root . '/integrity.json' );
$hashes       = DW_DWCAT_License_Manager::compute_file_hashes();
if ( $has_manifest ) {
	// integrity.json 은 빌드 산출물이라 보통 소스트리에 없다. 있으면 키 규약을 검증한다.
	t( ! empty( $hashes ), 'compute_file_hashes() returns hashes when integrity.json present' );
	$bad_key  = array_filter( array_keys( $hashes ), function ( $k ) { return strpos( $k, 'wp-plugin/' ) === 0 || strpos( $k, 'dw-catalog-wp/' ) === 0; } );
	$bad_hash = array_filter( $hashes, function ( $h ) { return ! preg_match( '/^[0-9a-f]{64}$/', $h ); } );
	t( empty( $bad_key ), '§7.2.2 규약 1 — 키가 루트상대 (접두사 없음)' );
	t( empty( $bad_hash ), '§7.2.2 — 값이 sha256 hex lowercase 64자' );
} else {
	t( $hashes === array(), 'compute_file_hashes() empty without integrity.json' );
}
t( count( $GLOBALS['_http_calls'] ) === 0, 'no HTTP call made without a license key (got ' . count( $GLOBALS['_http_calls'] ) . ')' );

echo "\n--- token request shape (offline server) ---\n";
$GLOBALS['_options']['dw_license_dw_catalog_wp'] = array(
	'key' => 'DW-TEST-TEST-TEST', 'status' => 'active', 'domain' => 'canary.example.com',
);
DW_DWCAT_License_Manager::get_token( true );
$call = end( $GLOBALS['_http_calls'] );
if ( $call ) {
	t( strpos( $call['url'], 'api.dasomforge.com' ) !== false, 'calls api.dasomforge.com (got ' . $call['url'] . ')' );
	t( strpos( $call['url'], '/auth/token' ) !== false, 'endpoint = /auth/token' );
	t( preg_match( '#^DW-dw-catalog-wp/2\.0\.0 \(WordPress/6\.7; PHP/#', $call['args']['headers']['User-Agent'] ) === 1,
	   'User-Agent matches API-CONTRACT §1.1 (' . $call['args']['headers']['User-Agent'] . ')' );
} else {
	// integrity.json 이 없어 토큰 요청 전에 중단되는 것이 정상 동작
	t( DW_DWCAT_License_Manager::get_last_error()['code'] === 'LOCAL_MANIFEST_MISSING',
	   'aborts before HTTP when integrity.json missing (fails closed)' );
}

echo "\n=== RESULTS ===\nPASS: $pass\nFAIL: $fail\n";
echo $fail === 0 ? "\nSMOKE PASSED\n" : "\n$fail FAILED\n";
exit( $fail > 0 ? 1 : 0 );
