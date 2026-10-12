<?php
/**
 * Store — dedicated tables for catalog field values, variations and search.
 *
 * Why not wp_postmeta: postmeta is an unindexed key/value pile. Filtering needs one JOIN per
 * field, numeric sort casts every row, and "contains" search scans the whole table. At tens of
 * thousands of items that dominates page time. These tables keep each concern indexed:
 *
 *   {prefix}dwcat_items       post_id → post_type, indexed_at   (one row per item; "migrated" marker)
 *   {prefix}dwcat_values      post_id, field_key → value / value_idx / value_num
 *                             KEY (field_key, value_idx) for filters + text sort,
 *                             KEY (field_key, value_num) for numeric sort
 *   {prefix}dwcat_variations  one row per variation; item_code + price indexed, other columns in JSON
 *   {prefix}dwcat_search      (token, post_id, source) — word-prefix search through the primary key.
 *                             source = field key / '_title' / '_var_{column}', so public searches
 *                             only match words from public fields (no probing of internal data).
 *
 * The item itself (title, content, image, permalink, categories) stays in wp_posts/terms so
 * permalinks, media, SEO plugins and themes keep working.
 *
 * Compatibility: get_post_meta()/update_post_meta() calls for catalog field keys are served
 * from these tables (see shim_*), so page builders' dynamic fields (Kadence etc.) still work
 * while wp_postmeta holds no catalog data.
 *
 * Security: every query goes through $wpdb->prepare(); table names come from $wpdb->prefix;
 * IDs are cast to int; field keys are whitelisted against the field config by callers.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Store {

	const DB_VERSION      = '1';
	const OPT_DB_VERSION  = 'dwcat_db_version';
	const OPT_MIGRATION   = 'dwcat_migration';      // [ 'last_id' => int, 'done' => bool ]
	const OPT_CACHE_VER   = 'dwcat_cache_ver';      // bumped on every write; part of cache keys
	const LEGACY_VAR_KEY  = '_dwcat_variations';    // pre-store variations meta key
	const MIGRATE_BATCH   = 200;
	const MAX_TOKENS      = 600;                    // per item
	const TOKEN_LEN       = 64;

	/** Request caches: post_id => [ key => value ] / [ rows ] */
	private static $values     = array();
	private static $variations = array();

	/** Set while the store itself touches postmeta (migration), so the shim stays out of the way. */
	private static $raw = false;

	/** @var array|null meta_key => true for every configured field (shim fast path) */
	private static $field_keys = null;

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'deleted_post', array( __CLASS__, 'delete_item' ) );
		add_action( 'save_post', array( __CLASS__, 'on_save_post' ), 99, 2 );
		add_action( 'dwcat_migrate_batch', array( __CLASS__, 'migrate_batch' ) );
		add_action( 'admin_init', array( __CLASS__, 'migrate_on_admin' ) );
		add_action( 'admin_notices', array( __CLASS__, 'migration_notice' ) );

		add_filter( 'get_post_metadata', array( __CLASS__, 'shim_get' ), 10, 4 );
		add_filter( 'update_post_metadata', array( __CLASS__, 'shim_update' ), 10, 4 );
		add_filter( 'add_post_metadata', array( __CLASS__, 'shim_update' ), 10, 4 );
		add_filter( 'delete_post_metadata', array( __CLASS__, 'shim_delete' ), 10, 3 );
	}

	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'dwcat_' . $name;
	}

	// ─────────────────────────────────────────────────────────────
	// Schema
	// ─────────────────────────────────────────────────────────────

	public static function maybe_install() {
		if ( get_option( self::OPT_DB_VERSION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();

		// Index prefixes keep composite keys under 767 bytes (utf8mb4, old InnoDB row formats).
		dbDelta( 'CREATE TABLE ' . self::table( 'items' ) . " (
  post_id bigint(20) unsigned NOT NULL,
  post_type varchar(20) NOT NULL DEFAULT '',
  indexed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (post_id),
  KEY post_type (post_type)
) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'values' ) . " (
  post_id bigint(20) unsigned NOT NULL,
  field_key varchar(64) NOT NULL,
  value longtext NOT NULL,
  value_idx varchar(191) NOT NULL DEFAULT '',
  value_num decimal(20,4) DEFAULT NULL,
  PRIMARY KEY  (post_id,field_key),
  KEY field_text (field_key(40),value_idx(140),post_id),
  KEY field_num (field_key(40),value_num,post_id)
) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'variations' ) . " (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  position smallint(5) unsigned NOT NULL DEFAULT 0,
  item_code varchar(100) NOT NULL DEFAULT '',
  price decimal(20,4) DEFAULT NULL,
  data longtext NOT NULL,
  PRIMARY KEY  (id),
  KEY post_position (post_id,position),
  KEY item_code (item_code)
) $charset;" );

		dbDelta( 'CREATE TABLE ' . self::table( 'search' ) . " (
  token varchar(64) NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  source varchar(70) NOT NULL DEFAULT '',
  PRIMARY KEY  (token,post_id,source),
  KEY post_id (post_id)
) $charset;" );

		update_option( self::OPT_DB_VERSION, self::DB_VERSION, true );

		if ( false === get_option( self::OPT_MIGRATION, false ) ) {
			update_option( self::OPT_MIGRATION, array( 'last_id' => 0, 'done' => false ), true );
			if ( ! wp_next_scheduled( 'dwcat_migrate_batch' ) ) {
				wp_schedule_single_event( time(), 'dwcat_migrate_batch' );
			}
		}
	}

	public static function uninstall() {
		global $wpdb;
		foreach ( array( 'items', 'values', 'variations', 'search' ) as $t ) {
			$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table( $t ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared — fixed table name
		}
		delete_option( self::OPT_DB_VERSION );
		delete_option( self::OPT_MIGRATION );
		delete_option( self::OPT_CACHE_VER );
	}

	// ─────────────────────────────────────────────────────────────
	// Field values
	// ─────────────────────────────────────────────────────────────

	/** @return array meta_key => value */
	public static function get_values( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! isset( self::$values[ $post_id ] ) ) {
			self::prime( array( $post_id ) );
		}
		return isset( self::$values[ $post_id ] ) ? self::$values[ $post_id ] : array();
	}

	public static function get_value( $post_id, $key, $default = '' ) {
		$values = self::get_values( $post_id );
		return isset( $values[ $key ] ) && '' !== $values[ $key ] ? $values[ $key ] : $default;
	}

	/**
	 * Load values for many items in one query (list pages call this once per page — no N+1).
	 */
	public static function prime( $post_ids ) {
		global $wpdb;
		$ids = array_values( array_diff( array_unique( array_map( 'intval', (array) $post_ids ) ), array_keys( self::$values ), array( 0 ) ) );
		if ( ! $ids ) {
			return;
		}

		// Items not yet moved out of postmeta (only while the background migration runs)
		if ( ! self::migration_done() ) {
			$in       = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$migrated = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT post_id FROM ' . self::table( 'items' ) . " WHERE post_id IN ($in)", $ids ) ) ); // phpcs:ignore
			foreach ( array_diff( $ids, $migrated ) as $pending ) {
				self::migrate_item( $pending );
			}
		}

		foreach ( $ids as $id ) {
			self::$values[ $id ] = array();
		}
		$in   = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT post_id, field_key, value FROM ' . self::table( 'values' ) . " WHERE post_id IN ($in)", $ids ) ); // phpcs:ignore
		foreach ( $rows as $r ) {
			self::$values[ (int) $r->post_id ][ $r->field_key ] = $r->value;
		}
	}

	/**
	 * Replace all field values of an item.
	 *
	 * @param array $values meta_key => scalar value ('' = no value)
	 */
	public static function save_values( $post_id, $values ) {
		global $wpdb;
		$post_id = (int) $post_id;
		$wpdb->delete( self::table( 'values' ), array( 'post_id' => $post_id ), array( '%d' ) );

		$clean = array();
		foreach ( (array) $values as $key => $value ) {
			$key   = substr( sanitize_key( $key ), 0, 64 );
			$value = is_scalar( $value ) ? (string) $value : '';
			if ( '' === $key || '' === $value ) {
				continue;
			}
			$clean[ $key ] = $value;
		}

		if ( $clean ) {
			$place = array();
			$args  = array();
			foreach ( $clean as $key => $value ) {
				$place[] = '(%d, %s, %s, %s, ' . ( is_numeric( $value ) ? '%f' : 'NULL' ) . ')';
				array_push( $args, $post_id, $key, $value, self::index_text( $value ) );
				if ( is_numeric( $value ) ) {
					$args[] = (float) $value;
				}
			}
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table( 'values' ) . ' (post_id, field_key, value, value_idx, value_num) VALUES ' . implode( ',', $place ), $args ) ); // phpcs:ignore
		}

		self::$values[ $post_id ] = $clean;
		self::reindex( $post_id );
	}

	public static function set_value( $post_id, $key, $value ) {
		$values         = self::get_values( $post_id );
		$values[ $key ] = $value;
		self::save_values( $post_id, $values );
	}

	public static function delete_value( $post_id, $key ) {
		$values = self::get_values( $post_id );
		unset( $values[ $key ] );
		self::save_values( $post_id, $values );
	}

	// ─────────────────────────────────────────────────────────────
	// Variations
	// ─────────────────────────────────────────────────────────────

	/** @return array[] rows keyed by variation column key */
	public static function get_variations( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		if ( ! isset( self::$variations[ $post_id ] ) ) {
			self::get_values( $post_id ); // runs lazy migration if needed
			$data = $wpdb->get_col( $wpdb->prepare( 'SELECT data FROM ' . self::table( 'variations' ) . ' WHERE post_id = %d ORDER BY position ASC', $post_id ) ); // phpcs:ignore
			$rows = array();
			foreach ( $data as $json ) {
				$row = json_decode( $json, true );
				if ( is_array( $row ) ) {
					$rows[] = $row;
				}
			}
			self::$variations[ $post_id ] = $rows;
		}
		return self::$variations[ $post_id ];
	}

	public static function save_variations( $post_id, $rows ) {
		global $wpdb;
		$post_id = (int) $post_id;
		$wpdb->delete( self::table( 'variations' ), array( 'post_id' => $post_id ), array( '%d' ) );

		$rows = array_values( array_filter( (array) $rows, 'is_array' ) );
		foreach ( $rows as $pos => $row ) {
			$code  = '';
			$price = null;
			foreach ( array( 'item_code', 'sku' ) as $k ) {
				if ( '' === $code && ! empty( $row[ $k ] ) ) {
					$code = substr( (string) $row[ $k ], 0, 100 );
				}
			}
			foreach ( array( 'price', 'wholesale', 'case_price' ) as $k ) {
				if ( null === $price && isset( $row[ $k ] ) && is_numeric( $row[ $k ] ) ) {
					$price = (float) $row[ $k ];
				}
			}
			$wpdb->insert(
				self::table( 'variations' ),
				array(
					'post_id'   => $post_id,
					'position'  => $pos,
					'item_code' => $code,
					'price'     => $price,
					'data'      => wp_json_encode( $row ),
				),
				array( '%d', '%d', '%s', null === $price ? null : '%f', '%s' )
			);
		}

		self::$variations[ $post_id ] = $rows;
		self::reindex( $post_id );
	}

	// ─────────────────────────────────────────────────────────────
	// Search index
	// ─────────────────────────────────────────────────────────────

	/**
	 * Rebuild an item's search tokens: title + every field value + every variation value.
	 */
	public static function reindex( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		// source => text
		$sources = array( '_title' => $post->post_title );
		foreach ( self::get_values_raw_cache( $post_id ) as $key => $value ) {
			$sources[ $key ] = $value;
		}
		$rows = isset( self::$variations[ $post_id ] ) ? self::$variations[ $post_id ] : self::get_variations( $post_id );
		foreach ( $rows as $row ) {
			foreach ( $row as $col => $value ) {
				$k             = '_var_' . substr( (string) $col, 0, 64 );
				$sources[ $k ] = ( isset( $sources[ $k ] ) ? $sources[ $k ] . ' ' : '' ) . ( is_scalar( $value ) ? (string) $value : '' );
			}
		}

		$pairs = array();
		foreach ( $sources as $source => $text ) {
			foreach ( self::tokenize( $text, true ) as $t ) {
				$pairs[] = array( $t, $source );
				if ( count( $pairs ) >= self::MAX_TOKENS ) {
					break 2;
				}
			}
		}

		$wpdb->delete( self::table( 'search' ), array( 'post_id' => $post_id ), array( '%d' ) );
		foreach ( array_chunk( $pairs, 200 ) as $chunk ) {
			$place = implode( ',', array_fill( 0, count( $chunk ), '(%s, %d, %s)' ) );
			$args  = array();
			foreach ( $chunk as $pair ) {
				array_push( $args, $pair[0], $post_id, $pair[1] );
			}
			$wpdb->query( $wpdb->prepare( 'INSERT INTO ' . self::table( 'search' ) . " (token, post_id, source) VALUES $place", $args ) ); // phpcs:ignore
		}

		$wpdb->replace(
			self::table( 'items' ),
			array( 'post_id' => $post_id, 'post_type' => $post->post_type, 'indexed_at' => current_time( 'mysql', true ) ),
			array( '%d', '%s', '%s' )
		);
		self::bump();
	}

	/**
	 * Words for the index / a query. Lowercased, split on anything that is not a letter or digit.
	 * With $compact, codes like "WK-009" or "1/4.4lb/6pk" also yield "wk009" / "144lb6pk" so they
	 * match however the visitor types them.
	 *
	 * @return string[]
	 */
	public static function tokenize( $text, $compact = false ) {
		$text   = function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
		$tokens = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( $compact ) {
			foreach ( preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY ) as $chunk ) {
				$joined = preg_replace( '/[^\p{L}\p{N}]+/u', '', $chunk );
				if ( '' !== $joined && $joined !== $chunk ) {
					$tokens[] = $joined;
				}
			}
		}
		$out = array();
		foreach ( (array) $tokens as $t ) {
			$t = function_exists( 'mb_substr' ) ? mb_substr( $t, 0, self::TOKEN_LEN, 'UTF-8' ) : substr( $t, 0, self::TOKEN_LEN );
			$out[ $t ] = true;
		}
		return array_map( 'strval', array_keys( $out ) );
	}

	public static function delete_item( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		foreach ( array( 'items', 'values', 'variations', 'search' ) as $t ) {
			$wpdb->delete( self::table( $t ), array( 'post_id' => $post_id ), array( '%d' ) );
		}
		unset( self::$values[ $post_id ], self::$variations[ $post_id ] );
		self::bump();
	}

	/** Title / status edits change what is searchable — keep the index in step. */
	public static function on_save_post( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status || ! DWCAT_Config::is_our_post_type( $post->post_type ) ) {
			return;
		}
		self::reindex( $post_id );
	}

	/** Cache-key version: changes on every write, so cached filter choices never go stale. */
	public static function cache_version() {
		return (int) get_option( self::OPT_CACHE_VER, 1 );
	}

	private static function bump() {
		update_option( self::OPT_CACHE_VER, self::cache_version() + 1, true );
	}

	private static function index_text( $value ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 191, 'UTF-8' ) : substr( $value, 0, 191 );
	}

	private static function get_values_raw_cache( $post_id ) {
		return isset( self::$values[ $post_id ] ) ? self::$values[ $post_id ] : self::get_values( $post_id );
	}

	// ─────────────────────────────────────────────────────────────
	// Migration from wp_postmeta (v2.0.0-rc.3 and earlier)
	// ─────────────────────────────────────────────────────────────

	public static function migration_done() {
		$m = get_option( self::OPT_MIGRATION, array() );
		return ! empty( $m['done'] );
	}

	/**
	 * Move one item's catalog data out of postmeta: copy into the store, verify, then delete.
	 */
	public static function migrate_item( $post_id ) {
		global $wpdb;
		$post_id = (int) $post_id;
		$type    = get_post_type( $post_id );
		if ( ! $type || ! DWCAT_Config::is_our_post_type( $type ) ) {
			return;
		}

		$keys   = wp_list_pluck( DWCAT_Config::get_fields( $type ), 'meta_key' );
		$keys[] = self::LEGACY_VAR_KEY;
		$in     = implode( ',', array_fill( 0, count( $keys ), '%s' ) );
		$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ($in)", array_merge( array( $post_id ), $keys ) ) ); // phpcs:ignore

		$values     = array();
		$variations = array();
		foreach ( $rows as $r ) {
			if ( self::LEGACY_VAR_KEY === $r->meta_key ) {
				$v          = maybe_unserialize( $r->meta_value );
				$variations = is_array( $v ) ? $v : array();
			} elseif ( ! isset( $values[ $r->meta_key ] ) ) {
				$values[ $r->meta_key ] = $r->meta_value;
			}
		}

		self::$raw = true;
		self::save_values( $post_id, $values );
		if ( $variations ) {
			self::save_variations( $post_id, $variations );
		}

		// Verify before deleting the originals
		unset( self::$values[ $post_id ] );
		$stored = self::get_values_from_db( $post_id );
		$ok     = true;
		foreach ( $values as $k => $v ) {
			if ( '' !== (string) $v && ( ! isset( $stored[ $k ] ) || (string) $stored[ $k ] !== (string) $v ) ) {
				$ok = false;
				break;
			}
		}
		if ( $ok && $rows ) {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key IN ($in)", array_merge( array( $post_id ), $keys ) ) ); // phpcs:ignore
			wp_cache_delete( $post_id, 'post_meta' );
		}
		self::$values[ $post_id ] = $stored;
		self::$raw                = false;
	}

	private static function get_values_from_db( $post_id ) {
		global $wpdb;
		$out = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT field_key, value FROM ' . self::table( 'values' ) . ' WHERE post_id = %d', $post_id ) ) as $r ) { // phpcs:ignore
			$out[ $r->field_key ] = $r->value;
		}
		return $out;
	}

	/** One batch of the background migration. Reschedules itself until every item is moved. */
	public static function migrate_batch() {
		global $wpdb;
		if ( self::migration_done() ) {
			return;
		}
		$state = get_option( self::OPT_MIGRATION, array( 'last_id' => 0, 'done' => false ) );
		$types = DWCAT_Config::get_post_type_slugs();
		if ( ! $types ) {
			update_option( self::OPT_MIGRATION, array( 'last_id' => 0, 'done' => true ), true );
			return;
		}
		$in  = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE ID > %d AND post_type IN ($in) AND post_status <> 'auto-draft' ORDER BY ID ASC LIMIT %d", // phpcs:ignore
			array_merge( array( (int) $state['last_id'] ), $types, array( self::MIGRATE_BATCH ) )
		) );

		$started = microtime( true );
		foreach ( $ids as $id ) {
			if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::table( 'items' ) . ' WHERE post_id = %d', $id ) ) ) { // phpcs:ignore
				self::migrate_item( $id );
			}
			$state['last_id'] = (int) $id;
			if ( microtime( true ) - $started > 15 ) {
				break; // stay well inside PHP time limits; continue next run
			}
		}

		$state['done'] = count( $ids ) < self::MIGRATE_BATCH && ( ! $ids || (int) end( $ids ) === $state['last_id'] );
		update_option( self::OPT_MIGRATION, $state, true );
		if ( ! $state['done'] && ! wp_next_scheduled( 'dwcat_migrate_batch' ) ) {
			wp_schedule_single_event( time() + 5, 'dwcat_migrate_batch' );
		}
	}

	/** Low-traffic sites barely run WP-Cron — admin visits move the migration along too. */
	public static function migrate_on_admin() {
		if ( ! self::migration_done() && current_user_can( 'edit_posts' ) && ! wp_doing_ajax() ) {
			self::migrate_batch();
		}
	}

	public static function migration_notice() {
		if ( self::migration_done() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p><strong>DW Catalog:</strong> '
			. esc_html__( 'Moving catalog data to the fast catalog tables. Search and filters cover all items once this finishes; editing works normally meanwhile.', 'dw-catalog-wp' )
			. '</p></div>';
	}

	// ─────────────────────────────────────────────────────────────
	// get_post_meta / update_post_meta compatibility
	// ─────────────────────────────────────────────────────────────

	private static function is_field_key( $key ) {
		if ( null === self::$field_keys ) {
			self::$field_keys = array( self::LEGACY_VAR_KEY => true );
			foreach ( DWCAT_Config::get_post_type_slugs() as $slug ) {
				foreach ( DWCAT_Config::get_fields( $slug ) as $f ) {
					self::$field_keys[ $f['meta_key'] ] = true;
				}
			}
		}
		return is_string( $key ) && isset( self::$field_keys[ $key ] );
	}

	/** Field config changed (Manage Fields saved) — rebuild the shim's key list. */
	public static function flush_field_keys() {
		self::$field_keys = null;
	}

	private static function shim_applies( $post_id, $key ) {
		return ! self::$raw && self::is_field_key( $key ) && DWCAT_Config::is_our_post_type( get_post_type( $post_id ) );
	}

	public static function shim_get( $check, $post_id, $key, $single ) {
		if ( null !== $check || '' === $key || ! self::shim_applies( $post_id, $key ) ) {
			return $check;
		}
		if ( self::LEGACY_VAR_KEY === $key ) {
			$rows = self::get_variations( $post_id );
			return $single ? array( $rows ) : ( $rows ? array( $rows ) : array() );
		}
		$value = self::get_value( $post_id, $key, '' );
		return $single ? array( $value ) : ( '' === $value ? array() : array( $value ) );
	}

	public static function shim_update( $check, $post_id, $key, $value ) {
		if ( null !== $check || ! self::shim_applies( $post_id, $key ) ) {
			return $check;
		}
		if ( self::LEGACY_VAR_KEY === $key ) {
			self::save_variations( $post_id, is_array( $value ) ? $value : array() );
		} else {
			self::set_value( $post_id, $key, is_scalar( $value ) ? (string) $value : '' );
		}
		return true;
	}

	public static function shim_delete( $check, $post_id, $key ) {
		if ( null !== $check || ! self::shim_applies( $post_id, $key ) ) {
			return $check;
		}
		if ( self::LEGACY_VAR_KEY === $key ) {
			self::save_variations( $post_id, array() );
		} else {
			self::delete_value( $post_id, $key );
		}
		return true;
	}
}

/**
 * Public helper for themes: a catalog field value of an item.
 *
 * @param int    $post_id Item ID.
 * @param string $key     Field meta key (see DW Catalog → Field Reference).
 * @return string
 */
function dwcat_get_field( $post_id, $key ) {
	return DWCAT_Store::get_value( $post_id, $key, '' );
}
