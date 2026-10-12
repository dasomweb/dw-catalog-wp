<?php
/**
 * Core integration tests — run against real databases.
 *
 *   php tests/core/run.php                       (uses env vars below; skips a DB that is not set)
 *
 *   DWC_MYSQL="mysql:host=127.0.0.1;port=3306;dbname=dwc_test"  DWC_MYSQL_USER=root DWC_MYSQL_PASS=
 *   DWC_PGSQL="pgsql:host=127.0.0.1;port=5432;dbname=dwc_test"  DWC_PGSQL_USER=postgres DWC_PGSQL_PASS=
 *
 * Every run uses a fresh random table prefix and drops its tables at the end.
 */

require dirname( __DIR__, 2 ) . '/core/bootstrap.php';
require __DIR__ . '/TestHost.php';

use DWCatalog\Admin\Forbidden;
use DWCatalog\Catalog;
use DWCatalog\Db\PdoConnection;
use DWCatalog\Front\Context;
use DWCatalog\Security\Csrf;

$pass = 0;
$fail = 0;
function ok( $cond, $msg ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  ✓ $msg\n";
	} else {
		$fail++;
		echo "  ✗ $msg\n";
	}
}
function eq( $expected, $actual, $msg ) {
	ok( $expected === $actual, $msg . ( $expected === $actual ? '' : ' — expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) ) );
}

$targets = array();
foreach ( array( 'mysql' => 'DWC_MYSQL', 'pgsql' => 'DWC_PGSQL' ) as $name => $env ) {
	if ( getenv( $env ) ) {
		$targets[ $name ] = array( getenv( $env ), (string) getenv( $env . '_USER' ), (string) getenv( $env . '_PASS' ) );
	}
}
if ( ! $targets ) {
	echo "No databases configured (DWC_MYSQL / DWC_PGSQL) — skipped.\n";
	exit( 0 );
}

foreach ( $targets as $driver => list( $dsn, $user, $pw ) ) {
	echo "\n=== $driver ===\n";
	$prefix = 't' . bin2hex( random_bytes( 3 ) ) . '_dwcat_';
	$dir    = sys_get_temp_dir() . '/dwc-test-' . bin2hex( random_bytes( 4 ) );
	mkdir( $dir );
	$pdo  = new PDO( $dsn, $user, $pw );
	$db   = new PdoConnection( $pdo, $prefix );
	$host = new TestHost( $dir );
	$c    = new Catalog( $db, $host, $prefix );

	try {
		run_suite( $c, $host, $driver );
	} catch ( Throwable $e ) {
		ok( false, 'Uncaught: ' . get_class( $e ) . ': ' . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() );
	}
	$c->uninstall();
	ok( 0 === (int) $db->selectValue( $driver === 'pgsql'
		? "SELECT COUNT(*) FROM information_schema.tables WHERE table_name LIKE ?"
		: "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?", array( $prefix . '%' ) ), 'uninstall drops every table' );
}

echo "\nPASS: $pass\nFAIL: $fail\n";
exit( $fail ? 1 : 0 );

function run_suite( Catalog $c, TestHost $host, string $driver ) {
	echo "--- schema ---\n";
	ok( $c->needsInstall(), 'fresh database needs install' );
	$c->install();
	$c->install(); // idempotent
	ok( ! $c->needsInstall(), 'installed (twice, idempotent)' );
	// v1 → v2 upgrade path: drop the column, mark v1, reinstall
	$db = $c->db();
	if ( 'pgsql' === $driver ) {
		$db->execute( 'DROP INDEX IF EXISTS ' . $db->dialect()->quoteIdent( $c->prefix() . 'items_type_status_sort' ) );
	} else {
		$db->execute( 'ALTER TABLE ' . $db->table( 'items' ) . ' DROP INDEX type_status_sort' );
	}
	$db->execute( 'ALTER TABLE ' . $db->table( 'items' ) . ' DROP COLUMN sort_title' );
	$db->execute( 'UPDATE ' . $db->table( 'settings' ) . ' SET value = ? WHERE name = ?', array( '1', 'schema_version' ) );
	ok( $c->needsInstall() && ! $db->columnExists( 'items', 'sort_title' ), 'simulated v1 schema' );
	$c->install();
	ok( ! $c->needsInstall() && $db->columnExists( 'items', 'sort_title' ), 'upgrade to v2 adds sort_title' );

	echo "--- fields ---\n";
	$type = $c->fields()->primaryType();
	eq( 'product', $type, 'default type' );
	$fields   = $c->fields()->fields( $type );
	$fields[] = array( 'meta_key' => 'dw_pc_price', 'label' => 'Price', 'type' => 'number', 'show_in_list' => true, 'show_in_export' => true, 'show_in_detail' => true );
	$fields[] = array( 'meta_key' => 'dw_pc_cost', 'label' => 'Cost', 'type' => 'number', 'show_in_list' => true, 'show_in_detail' => false );
	$c->fields()->saveFields( $type, $fields );
	eq( 9, count( $c->fields()->fields( $type ) ), 'fields saved' );
	ok( ! isset( $c->fields()->fieldsByKey( $type, true )['dw_pc_cost'] ), 'non-public field excluded from public set' );

	echo "--- terms ---\n";
	$t     = $c->terms();
	$ref   = $t->save( $type, 'category', 'Refrigerated' );
	$ramen = $t->save( $type, 'category', 'Ramen Soup Base', '', $ref );
	$tofu  = $t->save( $type, 'category', 'Tofu', '', $ref );
	$froz  = $t->save( $type, 'category', 'Frozen' );
	$dup   = $t->save( $type, 'category', 'Tofu', '', $froz );
	ok( 'tofu-2' === $t->get( $type, $dup )['slug'], 'duplicate slug gets a suffix' );
	eq( array( $ref, $ramen, $tofu ), array_values( array_intersect( array( $ref, $ramen, $tofu ), $t->withDescendants( $type, array( $ref ) ) ) ), 'descendants include children' );
	$t->save( $type, 'category', 'Refrigerated', 'refrigerated', $ramen, $ref );
	eq( 0, (int) $t->get( $type, $ref )['parent'], 'cannot make a category its own descendant' );
	eq( $tofu, $t->ensure( $type, 'Refrigerated > Tofu' ), 'ensure() finds "Parent > Child"' );

	echo "--- items ---\n";
	$items = $c->items();
	$mk    = function ( $title, $values, $cat, $status = 'publish', $vars = array() ) use ( $c, $items, $type ) {
		$id = $items->save( array( 'type' => $type, 'title' => $title, 'status' => $status, 'content' => '<p>Rich soup base</p>' ) );
		$items->saveValues( $id, $values );
		$c->terms()->setItem( $type, $id, 'category', array( $cat ) );
		if ( $vars ) {
			$items->saveVariations( $id, $vars );
		}
		return $id;
	};
	$a = $mk( '(Wakou#9) Spicy Miso Ramen Soup', array( 'dw_pc_item_code' => 'WK-009', 'dw_pc_brand_raw' => 'Wakou', 'dw_pc_price' => '42.5', 'dw_pc_cost' => '30', 'dw_pc_internal_note' => 'SECRETNOTE' ), $ramen, 'publish',
		array( array( 'name' => '6 pack', 'item_code' => 'WK-009-6', 'price' => '42.5', 'stock' => 'in_stock' ), array( 'name' => '12 pack', 'item_code' => 'WK-009-12', 'price' => '80', 'stock' => 'out_of_stock' ) ) );
	$b = $mk( '(Wakou#1) Tonkotsu Ramen Soup', array( 'dw_pc_item_code' => 'WK-001', 'dw_pc_brand_raw' => 'Wakou', 'dw_pc_price' => '39' ), $ramen );
	$d = $mk( '(Somi) Miso Ramen Soup', array( 'dw_pc_item_code' => 'SM-310', 'dw_pc_brand_raw' => 'Somi' ), $ramen );
	$e = $mk( 'Firm Tofu', array( 'dw_pc_item_code' => 'TF-100', 'dw_pc_brand_raw' => 'House Foods', 'dw_pc_price' => '18' ), $tofu );
	$f = $mk( 'Black Tiger Shrimp', array( 'dw_pc_item_code' => 'SH-1620', 'dw_pc_brand_raw' => 'Sea Jem', 'dw_pc_price' => '120' ), $froz );
	$g = $mk( 'Draft Miso', array( 'dw_pc_item_code' => 'DR-1' ), $ramen, 'draft' );
	$x = $items->save( array( 'type' => $type, 'title' => 'Firm Tofu', 'status' => 'publish' ) );
	eq( 'firm-tofu-2', $items->get( $x )['slug'], 'item slug made unique' );
	$c->deleteItem( $x );
	ok( null === $items->get( $x ), 'item deleted' );
	eq( 'Wakou', $items->value( $a, 'dw_pc_brand_raw' ), 'value read back' );
	eq( 2, count( $items->variations( $a ) ), 'variations read back' );
	eq( 'WK-009-12', $items->variations( $a )[1]['item_code'], 'variation order kept' );

	$q   = $c->query();
	$run = function ( $f ) use ( $q, $type ) {
		return array_column( $q->run( $type, $f )['rows'], 'title' );
	};
	$count = function ( $f ) use ( $q, $type ) {
		return $q->run( $type, $f )['total'];
	};

	echo "--- search ---\n";
	eq( 2, $count( array( 'search' => 'miso', 'public' => true ) ), 'word search (draft excluded)' );
	eq( 1, $count( array( 'search' => 'wk-009', 'public' => true ) ), 'code with punctuation' );
	eq( 1, $count( array( 'search' => 'wk009', 'public' => true ) ), 'compact code' );
	eq( 1, $count( array( 'search' => 'WK-009-12', 'public' => true ) ), 'variation code' );
	eq( 1, $count( array( 'search' => 'spicy mi', 'public' => true ) ), 'all words must match (prefix)' );
	eq( 0, $count( array( 'search' => 'iso', 'public' => true ) ), 'mid-word does not match (index prefix)' );
	eq( 0, $count( array( 'search' => 'secretnote', 'public' => true ) ), 'internal field not searchable publicly' );
	eq( 1, $count( array( 'search' => 'secretnote', 'status' => 'any' ) ), 'admin search finds internal field' );
	eq( $count( array( 'public' => true ) ), $count( array( 'search' => '%%% ___', 'public' => true ) ), 'symbol-only search is ignored (no LIKE wildcards reach SQL)' );

	echo "--- filters ---\n";
	eq( 4, $count( array( 'base_cat' => 'refrigerated', 'public' => true ) ), 'base category includes descendants' );
	eq( 1, $count( array( 'base_cat' => 'refrigerated', 'cats' => array( $tofu ), 'public' => true ) ), 'category filter' );
	eq( 0, $count( array( 'base_cat' => 'refrigerated', 'cats' => array( $froz ), 'public' => true ) ), 'category filter ANDs with base' );
	eq( 0, $count( array( 'base_cat' => 'nope', 'public' => true ) ), 'unknown base category → nothing' );
	eq( 3, $count( array( 'meta' => array( 'dw_pc_brand_raw' => array( 'Wakou', 'Somi' ) ), 'public' => true ) ), 'field filter OR within field' );
	eq( 5, $count( array( 'meta' => array( 'dw_pc_cost' => array( '30' ) ), 'public' => true ) ), 'non-public field filter ignored on site' );
	eq( 1, $count( array( 'meta' => array( 'dw_pc_cost' => array( '30' ) ) ) ), 'admin may filter any field' );

	echo "--- sort ---\n";
	eq( array( 'Firm Tofu', '(Wakou#1) Tonkotsu Ramen Soup', '(Wakou#9) Spicy Miso Ramen Soup', 'Black Tiger Shrimp', '(Somi) Miso Ramen Soup' ), $run( array( 'sort' => 'field:dw_pc_price:asc', 'public' => true ) ), 'numeric asc, empty last' );
	eq( array( 'Black Tiger Shrimp', '(Wakou#9) Spicy Miso Ramen Soup', '(Wakou#1) Tonkotsu Ramen Soup', 'Firm Tofu', '(Somi) Miso Ramen Soup' ), $run( array( 'sort' => 'field:dw_pc_price:desc', 'public' => true ) ), 'numeric desc, empty last' );
	eq( array( 'Black Tiger Shrimp', 'Firm Tofu', '(Somi) Miso Ramen Soup', '(Wakou#1) Tonkotsu Ramen Soup', '(Wakou#9) Spicy Miso Ramen Soup' ), $run( array( 'sort' => 'title_asc', 'public' => true ) ), 'title sort ignores punctuation (same on every database)' );
	ok( ! isset( $q->sortOptions( $type, true )['field:dw_pc_cost:asc'] ), 'non-public number field not offered for sorting' );
	eq( 'date_desc', $q->resolveSort( $type, 'field:dw_pc_cost:asc', true )['column'] === 'created_at' ? 'date_desc' : 'x', 'non-public sort falls back' );
	eq( array( 'Firm Tofu', '(Somi) Miso Ramen Soup' ), $run( array( 'ids' => array( $e, $d ), 'sort' => 'ids' ) ), 'explicit id order' );

	echo "--- paging ---\n";
	$r = $q->run( $type, array( 'public' => true, 'per_page' => 2, 'page' => 3 ) );
	eq( array( 5, 3, 1 ), array( $r['total'], $r['pages'], count( $r['rows'] ) ), 'total / pages / last page' );
	eq( 100, count( $q->run( $type, array( 'per_page' => 100000 ) )['rows'] ) <= 100 ? 100 : 0, 'per_page capped' );

	echo "--- filter choices ---\n";
	$brand = $c->fields()->fieldsByKey( $type )['dw_pc_brand_raw'];
	eq( array( 'House Foods', 'Somi', 'Wakou' ), array_values( $q->filterChoices( $type, $brand, 'refrigerated' ) ), 'choices scoped to category, published only' );

	echo "--- csrf ---\n";
	$token = $c->csrf()->token( 'save_item' );
	ok( $c->csrf()->verify( 'save_item', $token ), 'token verifies' );
	ok( ! $c->csrf()->verify( 'delete_item', $token ), 'token bound to action' );
	$host->user = 2;
	ok( ! $c->csrf()->verify( 'save_item', $token ), 'token bound to user' );
	$host->user = 1;

	echo "--- admin ---\n";
	$admin = $c->admin();
	foreach ( array( 'items', 'edit', 'categories', 'fields', 'import', 'export', 'design', 'reference' ) as $view ) {
		$html = $admin->render( array( 'view' => $view ) );
		ok( false !== strpos( $html, 'dwc-admin' ) && false === stripos( $html, 'warning' ), "view renders: $view" );
	}
	ok( false !== strpos( $admin->render( array( 'view' => 'edit', 'id' => $a ) ), 'WK-009-12' ), 'editor shows variations' );
	$post = array( 'do' => 'save_item', 'type' => $type, 'id' => $b, 'title' => 'Tonkotsu <b>Renamed</b>', 'status' => 'publish',
		'fields' => array( 'dw_pc_price' => 'abc', 'dw_pc_brand_raw' => 'Wakou' ), 'cats' => array( $ramen ),
		'var' => array( array( 'name' => 'Big', 'item_code' => 'WK1-B', 'price' => 'x', 'stock' => 'bogus' ), array( 'name' => '', 'stock' => 'in_stock' ) ) );
	try {
		$admin->handle( $post );
		ok( false, 'POST without CSRF token rejected' );
	} catch ( Forbidden $ex ) {
		ok( true, 'POST without CSRF token rejected' );
	}
	$post[ Csrf::FIELD ] = $c->csrf()->token( 'save_item' );
	$res                 = $admin->handle( $post );
	ok( false !== strpos( $res['redirect'], 'msg=saved' ), 'save_item redirects with saved' );
	$items2 = ( new Catalog( $c->db(), $host, '' ) )->items(); // fresh caches
	eq( 'Tonkotsu Renamed', $items2->get( $b )['title'], 'title stripped of tags' );
	eq( '', $items2->value( $b, 'dw_pc_price' ), 'invalid number rejected' );
	eq( array( array( 'name' => 'Big', 'item_code' => 'WK1-B', 'pack_size' => '', 'price' => '', 'stock' => '' ) ), $items2->variations( $b ), 'variation row cleaned, blank row dropped' );
	$host->caps = array( 'catalog.edit' );
	try {
		$admin->handle( array( 'do' => 'delete_item', 'type' => $type, 'id' => $b, Csrf::FIELD => $c->csrf()->token( 'delete_item' ) ) );
		ok( false, 'delete without catalog.delete rejected' );
	} catch ( Forbidden $ex ) {
		ok( true, 'delete without catalog.delete rejected' );
	}
	$host->caps   = null;
	$host->denied = array( 'manage_fields' );
	try {
		$admin->handle( array( 'do' => 'save_fields', 'type' => $type, Csrf::FIELD => $c->csrf()->token( 'save_fields' ) ) );
		ok( false, 'host policy (license) blocks action' );
	} catch ( Forbidden $ex ) {
		ok( true, 'host policy (license) blocks action' );
	}
	$host->denied = array();

	echo "--- front ---\n";
	$ctx  = new Context( 'https://site.test/refrigerated/', 'https://site.test/refrigerated/item/', array() );
	$html = $c->front()->catalog( array( 'category' => 'refrigerated', 'filter_fields' => 'dw_pc_brand_raw,dw_pc_cost' ), $ctx );
	ok( false !== strpos( $html, '4 items' ), 'list renders with count' );
	ok( false !== strpos( $html, 'dwf[dw_pc_brand_raw][]' ) && false === strpos( $html, 'dwf[dw_pc_cost]' ), 'only public filter offered' );
	ok( false === strpos( $html, 'SECRETNOTE' ) && false === strpos( $html, 'Cost' ), 'no internal data in list HTML' );
	ok( false !== strpos( $html, 'https://site.test/refrigerated/item/firm-tofu/' ), 'pretty item links' );
	$ctx2 = new Context( 'https://site.test/refrigerated/', 'https://site.test/refrigerated/item/', array(), $items->get( $a )['slug'] );
	$html = $c->front()->catalog( array( 'category' => 'refrigerated' ), $ctx2 );
	ok( false !== strpos( $html, 'dwcat-detail' ) && false !== strpos( $html, 'WK-009-12' ), 'detail renders with variations' );
	ok( false === strpos( $html, 'SECRETNOTE' ) && false === strpos( $html, '>30<' ), 'no internal data in detail HTML' );
	$ctx3 = new Context( 'https://site.test/refrigerated/', '', array( 'dw_item' => $items->get( $f )['slug'] ) );
	ok( false !== strpos( $c->front()->catalog( array( 'category' => 'refrigerated' ), $ctx3 ), 'not available' ), 'item outside the page category is not shown' );
	$ctx4 = new Context( 'https://site.test/refrigerated/', '', array( 'dw_item' => $items->get( $g )['slug'] ) );
	ok( false !== strpos( $c->front()->catalog( array(), $ctx4 ), 'not available' ), 'draft item is not shown' );
	$seo = $c->front()->seo( $items->get( $a ), $ctx2 );
	eq( 'https://site.test/refrigerated/item/' . $items->get( $a )['slug'] . '/', $seo['canonical'], 'SEO canonical' );
	eq( 'WK-009', $seo['json_ld']['sku'], 'JSON-LD sku from public field' );
	$xss = $items->save( array( 'type' => $type, 'title' => '<script>alert(1)</script>Evil', 'status' => 'publish' ) );
	$items->saveValues( $xss, array( 'dw_pc_brand_raw' => '"><img src=x onerror=alert(1)>' ) );
	$html = $c->front()->catalog( array(), new Context( 'https://site.test/c/', '', array( 'dwq' => '"><svg onload=alert(1)>' ) ) );
	ok( false === strpos( $html, '<script>alert' ) && false === strpos( $html, '<img src=x' ) && false === strpos( $html, '<svg onload' ), 'output escaped (title, values, query echo)' );
	$grid = $c->front()->grid( array( 'category' => 'refrigerated', 'per_page' => 2 ), $ctx );
	ok( 2 === substr_count( $grid, 'dwcat-card-title' ), 'grid block' );

	echo "--- images ---\n";
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		echo "  (GD not loaded — run with -d extension=gd)\n";
	} else {
		$tmp = tempnam( sys_get_temp_dir(), 'img' );
		$im  = imagecreatetruecolor( 1600, 900 );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 200, 30, 30 ) );
		imagejpeg( $im, $tmp, 90 );
		$id = $c->images()->store( $e, $tmp, 'Firm Tofu Front.JPG' );
		ok( is_int( $id ), 'jpeg stored' );
		$img = $c->images()->main( $e );
		ok( preg_match( '#^\d{4}/\d{2}/firm-tofu-front-[0-9a-f]{8}\.jpg$#', $img['file'] ) === 1, 'safe generated file name' );
		eq( array( 'large', 'medium', 'thumb' ), array_keys( $img['sizes'] ), 'sizes generated' );
		eq( array( 300, 300 ), array( $img['sizes']['thumb']['width'], $img['sizes']['thumb']['height'] ), 'thumb is a 300² crop' );
		eq( 1200, $img['sizes']['large']['width'], 'large fits 1200' );
		ok( is_file( $c->images()->path( $img, 'medium' ) ), 'size file on disk' );
		ok( false !== strpos( $c->images()->tag( $img, 'medium' ), 'srcset=' ), 'img tag with srcset' );

		$evil = tempnam( sys_get_temp_dir(), 'evil' );
		file_put_contents( $evil, "<?php echo 'pwned'; ?>" );
		ok( is_string( $c->images()->store( $e, $evil, 'shell.php.jpg' ) ), 'non-image rejected' );
		$poly = tempnam( sys_get_temp_dir(), 'poly' );
		file_put_contents( $poly, file_get_contents( $tmp ) . "<?php system('id'); ?>" );
		$pid  = $c->images()->store( $e, $poly, 'poly.jpg' );
		$prow = null;
		foreach ( $c->images()->forItem( $e ) as $r ) {
			if ( (int) $r['id'] === $pid ) {
				$prow = $r;
			}
		}
		ok( $prow && false === strpos( file_get_contents( $c->images()->path( $prow ) ), '<?php' ), 'appended payload stripped by re-encode' );
		ok( is_file( $host->dir . '/.htaccess' ) && is_file( $host->dir . '/index.html' ), 'storage guards written' );

		$path = $c->images()->path( $img );
		$c->deleteItem( $e );
		ok( ! is_file( $path ), 'image files removed with the item' );
		@unlink( $tmp );
		@unlink( $evil );
		@unlink( $poly );
	}

	echo "--- delete cleanup ---\n";
	$c->deleteItem( $a );
	$left = 0;
	foreach ( array( 'values', 'variations', 'search', 'item_terms' ) as $tbl ) {
		$left += (int) $c->db()->selectValue( 'SELECT COUNT(*) FROM ' . $c->db()->table( $tbl ) . ' WHERE item_id = ?', array( $a ) );
	}
	eq( 0, $left, 'all rows of a deleted item removed' );
}
