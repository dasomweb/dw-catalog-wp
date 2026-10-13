<?php
namespace DWCatalog\Admin;

use DWCatalog\Catalog;
use DWCatalog\Fields\FieldConfig;
use DWCatalog\Fields\VariationColumns;
use DWCatalog\Front\Design;
use DWCatalog\Security\Csrf;
use DWCatalog\Query\CatalogQuery;
use DWCatalog\Support\Html;
use DWCatalog\Support\Text;

/**
 * The one admin UI of the catalog — server-rendered pages, embedded by the host.
 *
 *   GET  render( $query )            view = items | edit | categories | fields | import | export | design | reference
 *   POST handle( $post, $files )     do   = save_item | delete_item | bulk_items | save_term | delete_term |
 *                                           save_fields | import | export | export_csv | save_design
 *
 * Every POST: capability check → host policy (license) → CSRF token bound to user + action.
 * Input arrives unslashed from the host (no magic quotes).
 */
class AdminApp {

	/** @var Catalog */
	private $c;

	const VIEWS = array(
		'items'      => 'catalog.edit',
		'edit'       => 'catalog.edit',
		'categories' => 'catalog.terms',
		'fields'     => 'catalog.manage',
		'import'     => 'catalog.import',
		'export'     => 'catalog.export',
		'design'     => 'catalog.manage',
		'reference'  => 'catalog.edit',
	);

	const ACTIONS = array(
		'save_item'   => array( 'catalog.edit', 'save' ),
		'delete_item' => array( 'catalog.delete', 'save' ),
		'bulk_items'  => array( 'catalog.edit', 'save' ),
		'save_term'   => array( 'catalog.terms', 'save' ),
		'delete_term' => array( 'catalog.terms', 'save' ),
		'save_fields' => array( 'catalog.manage', 'manage_fields' ),
		'import'      => array( 'catalog.import', 'import' ),
		'export'      => array( 'catalog.export', 'export' ),
		'export_csv'  => array( 'catalog.export', 'export' ),
		'save_design' => array( 'catalog.manage', 'save' ),
	);

	const NOTICES = array(
		'saved'     => 'Saved.',
		'deleted'   => 'Deleted.',
		'imported'  => 'Import finished.',
		'not_found' => 'Item not found.',
		'invalid'   => 'Please check the form — some values were not accepted.',
	);

	public function __construct( Catalog $c ) {
		$this->c = $c;
	}

	// ─────────────────────────────────────────────────────────────
	// GET
	// ─────────────────────────────────────────────────────────────

	public function render( array $q ): string {
		$view = isset( $q['view'] ) && isset( self::VIEWS[ $q['view'] ] ) ? $q['view'] : 'items';
		if ( ! $this->c->host()->allows( 'admin' ) ) {
			return $this->wrap( '<div class="dwc-notice dwc-notice-error">' . Html::e( $this->t( 'The catalog is not licensed on this site. Activate the license to manage the catalog.' ) ) . '</div>' );
		}
		if ( ! $this->c->host()->can( self::VIEWS[ $view ] ) ) {
			return $this->wrap( '<div class="dwc-notice dwc-notice-error">' . Html::e( $this->t( 'You do not have permission to view this page.' ) ) . '</div>' );
		}
		$type = $this->type( $q );
		$vars = array( 'type' => $type, 'q' => $q );

		switch ( $view ) {
			case 'items':
				$vars += $this->itemsData( $type, $q );
				break;
			case 'edit':
				$id   = isset( $q['id'] ) ? (int) $q['id'] : 0;
				$item = $id ? $this->c->items()->get( $id ) : null;
				if ( $id && ( ! $item || $item['type'] !== $type ) ) {
					return $this->wrap( $this->notice( 'not_found', 'error' ) );
				}
				$vars['item'] = $item;
				$vars        += $this->editData( $type, $item );
				break;
		}
		$notice = isset( $q['msg'] ) && isset( self::NOTICES[ $q['msg'] ] ) ? $this->notice( $q['msg'], 'invalid' === $q['msg'] ? 'error' : 'success' ) : '';
		$flash  = $this->takeFlash();
		return $this->wrap( $notice . $flash . $this->template( $view, $vars ) );
	}

	const PER_PAGE = array( 10, 20, 50, 100 );

	private function itemsData( string $type, array $q ): array {
		$fields = $this->c->fields()->fieldsByKey( $type );
		$meta   = array();
		foreach ( isset( $q['f'] ) && is_array( $q['f'] ) ? $q['f'] : array() as $k => $v ) {
			if ( isset( $fields[ $k ] ) && '' !== (string) $v && is_scalar( $v ) ) {
				$meta[ $k ] = array( (string) $v );
			}
		}
		$orderby = isset( $q['orderby'] ) ? Text::key( $q['orderby'] ) : '';
		$order   = isset( $q['order'] ) && 'asc' === $q['order'] ? 'asc' : 'desc';
		if ( in_array( $orderby, array( 'title', 'date', 'modified' ), true ) ) {
			$sort = $orderby . '_' . $order;
		} elseif ( isset( $fields[ $orderby ] ) ) {
			$sort = 'field:' . $orderby . ':' . $order;
		} else {
			$sort = 'date_desc';
		}
		$status  = isset( $q['status'] ) && in_array( $q['status'], array( 'publish', 'draft' ), true ) ? $q['status'] : 'any';
		$perPage = isset( $q['n'] ) && in_array( (int) $q['n'], self::PER_PAGE, true ) ? (int) $q['n'] : 20;
		$code    = $this->c->fields()->roleField( $type, 'code', false );
		$brand   = $this->c->fields()->roleField( $type, 'brand', false );
		$stats   = new ItemStats( $this->c );
		$shared  = $code ? $stats->shared( $type, $code['meta_key'] ) : array();
		$dup     = $code && ! empty( $q['dup'] );
		$result  = $this->c->query()->run( $type, array(
			'search'   => isset( $q['s'] ) ? (string) $q['s'] : '',
			'cats'     => ! empty( $q['cat'] ) ? array( (int) $q['cat'] ) : array(),
			'meta'     => $meta,
			'sort'     => $sort,
			'status'   => $status,
			'shared'   => $dup ? $code['meta_key'] : '',
			'page'     => isset( $q['paged'] ) ? (int) $q['paged'] : 1,
			'per_page' => $perPage,
		) );
		$ids = array_map( 'intval', array_column( $result['rows'], 'id' ) );
		$this->c->images()->primeItems( $ids );
		$this->c->terms()->primeItems( $type, $ids );
		$this->c->items()->primeValues( $ids );
		return array(
			'result'  => $result,
			'orderby' => $orderby,
			'order'   => $order,
			'status'  => $status,
			'meta'    => $meta,
			'perPage' => $perPage,
			'dup'     => $dup,
			'code'    => $code,
			'brand'   => $brand,
			'shared'  => $shared,
			'counts'  => $stats->counts( $type ),
			'catStat' => $stats->categoryCounts( $type ),
			'brands'  => $brand && 'select' !== $brand['type'] ? $stats->distinct( $type, $brand['meta_key'] ) : array(),
		);
	}

	private function editData( string $type, ?array $item ): array {
		$code   = $this->c->fields()->roleField( $type, 'code', false );
		$brand  = $this->c->fields()->roleField( $type, 'brand', false );
		$stats  = new ItemStats( $this->c );
		$id     = $item ? (int) $item['id'] : 0;
		$value  = $code && $id ? $this->c->items()->value( $id, $code['meta_key'] ) : '';
		return array(
			'code'     => $code,
			'brand'    => $brand,
			'codeUsed' => $code ? $stats->usedBy( $type, $code['meta_key'], $value, $id ) : array(),
			'brands'   => $brand && 'select' !== $brand['type'] ? $stats->distinct( $type, $brand['meta_key'] ) : array(),
		);
	}

	// ─────────────────────────────────────────────────────────────
	// POST
	// ─────────────────────────────────────────────────────────────

	/**
	 * @return array [ 'redirect' => url ] or [ 'download' => [ filename, mime, body ] ]
	 */
	public function handle( array $post, array $files = array() ): array {
		$do = isset( $post['do'] ) && isset( self::ACTIONS[ $post['do'] ] ) ? $post['do'] : '';
		if ( '' === $do ) {
			throw new Forbidden( 'Unknown action.' );
		}
		list( $cap, $feature ) = self::ACTIONS[ $do ];
		if ( ! $this->c->host()->can( $cap ) ) {
			throw new Forbidden( $this->t( 'You do not have permission to do that.' ) );
		}
		if ( ! $this->c->host()->allows( $feature ) ) {
			throw new Forbidden( $this->t( 'This feature requires an active license.' ) );
		}
		if ( ! $this->c->csrf()->verify( $do, isset( $post[ Csrf::FIELD ] ) ? $post[ Csrf::FIELD ] : '' ) ) {
			throw new Forbidden( $this->t( 'The form has expired. Please reload the page and try again.' ) );
		}
		$type = $this->type( $post );

		switch ( $do ) {
			case 'save_item':
				return ( new ItemEditor( $this->c, $this ) )->save( $type, $post, $files );
			case 'delete_item':
				$id   = isset( $post['id'] ) ? (int) $post['id'] : 0;
				$item = $this->c->items()->get( $id );
				if ( $item && $item['type'] === $type ) {
					$this->c->deleteItem( $id );
				}
				return $this->redirect( array( 'view' => 'items', 'type' => $type, 'msg' => 'deleted' ) );
			case 'bulk_items':
				return $this->bulk( $type, $post );
			case 'save_term':
				try {
					$this->c->terms()->save(
						$type,
						'category',
						isset( $post['name'] ) ? (string) $post['name'] : '',
						isset( $post['slug'] ) ? (string) $post['slug'] : '',
						isset( $post['parent'] ) ? (int) $post['parent'] : 0,
						isset( $post['id'] ) ? (int) $post['id'] : 0,
						isset( $post['position'] ) ? (int) $post['position'] : 0
					);
					$msg = 'saved';
				} catch ( \InvalidArgumentException $e ) {
					$msg = 'invalid';
				}
				return $this->redirect( array( 'view' => 'categories', 'type' => $type, 'msg' => $msg ) );
			case 'delete_term':
				$this->c->terms()->delete( $type, isset( $post['id'] ) ? (int) $post['id'] : 0 );
				return $this->redirect( array( 'view' => 'categories', 'type' => $type, 'msg' => 'deleted' ) );
			case 'save_fields':
				$raw = isset( $post['fields'] ) && is_array( $post['fields'] ) ? $post['fields'] : array();
				$this->c->fields()->saveFields( $type, FieldConfig::sanitizeFields( $raw, isset( $post['title_field'] ) ? $post['title_field'] : '' ) );
				$this->c->fields()->saveVariationColumns( $type, isset( $post['variation_columns'] ) && is_array( $post['variation_columns'] ) ? $post['variation_columns'] : array() );
				return $this->redirect( array( 'view' => 'fields', 'type' => $type, 'msg' => 'saved' ) );
			case 'import':
				$report = ( new Importer( $this->c ) )->run( $type, $post, isset( $files['csv'] ) ? $files['csv'] : array() );
				$this->flash( $report );
				return $this->redirect( array( 'view' => 'import', 'type' => $type, 'msg' => 'imported' ) );
			case 'export':
				return ( new PdfExporter( $this->c ) )->run( $type, $post );
			case 'export_csv':
				return ( new CsvExporter( $this->c ) )->run( $type, $post );
			case 'save_design':
				Design::save( $this->c->settings(), isset( $post['design'] ) && is_array( $post['design'] ) ? $post['design'] : array() );
				return $this->redirect( array( 'view' => 'design', 'type' => $type, 'msg' => 'saved' ) );
		}
		throw new Forbidden( 'Unknown action.' );
	}

	/** Bulk / row actions on the item list: publish, draft, move, export, delete. */
	private function bulk( string $type, array $post ): array {
		$action = isset( $post['bulk'] ) ? (string) $post['bulk'] : '';
		$ids    = isset( $post['ids'] ) ? (array) $post['ids'] : array();
		if ( ! empty( $post['one'] ) && preg_match( '/^(\w+):(\d+)$/', (string) $post['one'], $m ) ) {
			$action = $m[1];
			$ids    = array( $m[2] );
		}
		$valid = array();
		foreach ( array_slice( $ids, 0, CatalogQuery::MAX_PER_PAGE ) as $id ) {
			$item = $this->c->items()->get( (int) $id );
			if ( $item && $item['type'] === $type ) {
				$valid[] = (int) $id;
			}
		}
		$back = $this->listState( isset( $post['back'] ) ? (string) $post['back'] : '', $type );
		$msg  = 'saved';
		switch ( $action ) {
			case 'publish':
			case 'draft':
				$this->c->items()->setStatus( $valid, $action );
				break;
			case 'move':
				$term = $this->c->terms()->get( $type, isset( $post['move_cat'] ) ? (int) $post['move_cat'] : 0 );
				if ( ! $term ) {
					$msg = 'invalid';
					break;
				}
				foreach ( $valid as $id ) {
					$this->c->terms()->setItem( $type, $id, 'category', array( (int) $term['id'] ) );
				}
				break;
			case 'export':
				if ( ! $this->c->host()->can( 'catalog.export' ) || ! $this->c->host()->allows( 'export' ) ) {
					throw new Forbidden( $this->t( 'You do not have permission to do that.' ) );
				}
				if ( $valid ) {
					return ( new CsvExporter( $this->c ) )->run( $type, array( 'ids' => $valid ) );
				}
				break;
			case 'delete':
				if ( ! $this->c->host()->can( 'catalog.delete' ) ) {
					throw new Forbidden( $this->t( 'You do not have permission to do that.' ) );
				}
				foreach ( $valid as $id ) {
					$this->c->deleteItem( $id );
				}
				$msg = 'deleted';
				break;
			default:
				$msg = 'invalid';
		}
		return $this->redirect( array_merge( $back, array( 'msg' => $msg ) ) );
	}

	/** The list URL the user came from (query string), reduced to known list parameters. */
	private function listState( string $qs, string $type ): array {
		parse_str( $qs, $in );
		$out = array( 'view' => 'items', 'type' => $type );
		foreach ( array( 's', 'cat', 'status', 'orderby', 'order', 'n', 'paged', 'dup' ) as $k ) {
			if ( isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) && '' !== (string) $in[ $k ] ) {
				$out[ $k ] = Text::line( (string) $in[ $k ], 100 );
			}
		}
		if ( isset( $in['f'] ) && is_array( $in['f'] ) ) {
			foreach ( $in['f'] as $k => $v ) {
				if ( is_scalar( $v ) && '' !== (string) $v ) {
					$out['f'][ Text::key( $k ) ] = Text::line( (string) $v, 100 );
				}
			}
		}
		return $out;
	}

	// ─────────────────────────────────────────────────────────────
	// Helpers used by templates
	// ─────────────────────────────────────────────────────────────

	public function t( string $text ): string {
		return $this->c->t( $text );
	}

	public function url( array $params ): string {
		return $this->c->host()->adminUrl( $params );
	}

	public function redirect( array $params ): array {
		return array( 'redirect' => $this->url( $params ) );
	}

	/** Page header: kicker, title, action buttons (already-escaped HTML). */
	public function head( string $title, string $actions = '', string $before = '' ): string {
		return '<div class="dwc-head"><div class="dwc-head-title">' . ( '' !== $before ? $before : '<span class="dwc-kicker">DW CATALOG</span>' )
			. '<h1>' . Html::e( $title ) . '</h1></div>' . ( '' !== $actions ? '<div class="dwc-head-actions">' . $actions . '</div>' : '' ) . '</div>';
	}

	/** <form> opening + hidden action/type/CSRF fields. */
	public function form( string $do, string $type, bool $multipart = false, string $attrs = '' ): string {
		return '<form method="post" action="' . Html::url( $this->c->host()->adminPostUrl() ) . '"' . ( $multipart ? ' enctype="multipart/form-data"' : '' ) . ( $attrs ? ' ' . $attrs : '' ) . '>'
			. '<input type="hidden" name="do" value="' . Html::attr( $do ) . '">'
			. '<input type="hidden" name="type" value="' . Html::attr( $type ) . '">'
			. $this->c->csrf()->field( $do );
	}

	/**
	 * GET form opening. Browsers drop the query string of a GET form's action, so the host's own
	 * base params (e.g. WordPress "page=") are carried as hidden fields.
	 */
	public function getForm( array $hidden, string $attrs = '' ): string {
		$base = $this->url( array() );
		parse_str( (string) parse_url( $base, PHP_URL_QUERY ), $baseParams );
		$html = '<form method="get" action="' . Html::url( strtok( $base, '?' ) ) . '"' . ( $attrs ? ' ' . $attrs : '' ) . '>';
		foreach ( array_merge( $baseParams, $hidden ) as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$html .= '<input type="hidden" name="' . Html::attr( $k ) . '" value="' . Html::attr( $v ) . '">';
			}
		}
		return $html;
	}

	public function catalog(): Catalog {
		return $this->c;
	}

	/** Flash messages (per user, one-shot) for import reports / upload errors. */
	public function flash( array $lines ): void {
		$uid = $this->c->host()->userId();
		if ( $uid && $lines ) {
			$this->c->settings()->set( 'flash.' . $uid, array_slice( array_values( $lines ), 0, 50 ) );
		}
	}

	private function takeFlash(): string {
		$uid   = $this->c->host()->userId();
		$lines = $uid ? $this->c->settings()->get( 'flash.' . $uid ) : null;
		if ( ! is_array( $lines ) || ! $lines ) {
			return '';
		}
		$this->c->settings()->delete( 'flash.' . $uid );
		$html = '<div class="dwc-notice dwc-notice-info"><ul>';
		foreach ( $lines as $line ) {
			$html .= '<li>' . Html::e( $line ) . '</li>';
		}
		return $html . '</ul></div>';
	}

	private function notice( string $code, string $kind ): string {
		return '<div class="dwc-notice dwc-notice-' . Html::attr( $kind ) . '">' . Html::e( $this->t( self::NOTICES[ $code ] ) ) . '</div>';
	}

	private function type( array $in ): string {
		$type = isset( $in['type'] ) ? Text::key( $in['type'], 20 ) : '';
		return $this->c->fields()->type( $type ) ? $type : $this->c->fields()->primaryType();
	}

	private function wrap( string $body ): string {
		return '<div class="dwc-admin">' . $body . '</div>';
	}

	private function template( string $name, array $vars ): string {
		$app = $this;
		$c   = $this->c;
		extract( $vars, EXTR_SKIP );
		ob_start();
		include DWCATALOG_CORE_DIR . '/templates/admin/' . $name . '.php';
		return (string) ob_get_clean();
	}

	/** Shared bits for templates. */
	public function variationColumnsFor( string $type ): array {
		return $this->c->fields()->variationColumns( $type );
	}

	public function stockOptions(): array {
		return Text::parseOptions( VariationColumns::STOCK_OPTIONS );
	}
}
