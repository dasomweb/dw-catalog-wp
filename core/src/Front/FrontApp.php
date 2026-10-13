<?php
namespace DWCatalog\Front;

use DWCatalog\Catalog;
use DWCatalog\Fields\FieldConfig;
use DWCatalog\Fields\VariationColumns;
use DWCatalog\Support\Html;
use DWCatalog\Support\Text;

/**
 * Visitor-facing HTML. Only public fields are ever printed; nothing is exposed as JSON.
 *
 *   catalog()   list (left sidebar: search · categories · field filters · reset) + sort + grid
 *               + pagination, or the detail view when an item is requested
 *   grid() / carousel()   compact blocks linking to the catalog page
 *
 * List state lives in the URL (dwq, dwc[], dwf[key][], dws, dwp), so every state is a
 * shareable link and works without JS; catalog.js swaps the block in place.
 */
class FrontApp {

	const P_SEARCH = 'dwq';
	const P_CATS   = 'dwc';
	const P_FIELDS = 'dwf';
	const P_SORT   = 'dws';
	const P_PAGE   = 'dwp';

	/** @var Catalog */
	private $c;

	public function __construct( Catalog $c ) {
		$this->c = $c;
	}

	public static function defaults(): array {
		return array(
			'type'          => '',
			'category'      => '',
			'columns'       => 0,
			'per_page'      => 20,
			'show_fields'   => '',
			'detail_fields' => '',
			'filter_fields' => '',
			'search'        => 'yes',
			'sort'          => 'yes',
			'default_sort'  => 'date_desc',
			'show_category' => 'yes',
			'related'       => 4,
			'inquiry_url'   => '',
			'inquiry_label' => '',
		);
	}

	private function atts( array $atts ): array {
		$a = array_merge( self::defaults(), array_intersect_key( $atts, self::defaults() ) );
		$a['type'] = $this->c->fields()->type( (string) $a['type'] ) ? (string) $a['type'] : $this->c->fields()->primaryType();
		if ( ! (int) $a['columns'] ) {
			$a['columns'] = Design::get( $this->c->settings() )['columns'];
		}
		return $a;
	}

	// ─────────────────────────────────────────────────────────────
	// [dw_catalog]
	// ─────────────────────────────────────────────────────────────

	public function catalog( array $atts, Context $ctx ): string {
		$a     = $this->atts( $atts );
		$state = $this->state( $a, $ctx->query );
		$item  = $this->requestedItem( $a, $ctx );
		if ( '' !== $ctx->requestedItem() && ! $item ) {
			$html = '<p class="dwcat-empty">' . Html::e( $this->c->t( 'This item is not available.' ) ) . '</p>'
				. '<p><a class="dwcat-back" href="' . Html::url( $ctx->listUrl() ) . '" data-dwcat-link>&larr; ' . Html::e( $this->c->t( 'Back to list' ) ) . '</a></p>';
		} else {
			$html = $item ? $this->detail( $item, $a, $ctx, $state ) : $this->listView( $a, $ctx, $state );
		}
		return '<div class="dwcat-catalog alignwide" data-dwcat-catalog style="' . Html::attr( Design::style( $this->c->settings() ) ) . '">' . $html . '</div>';
	}

	/**
	 * The published item this request asks for, within the page's catalog type and category.
	 * Hosts call this before output to set the page title / SEO tags.
	 */
	public function requestedItem( array $atts, Context $ctx ): ?array {
		$slug = Text::cut( (string) $ctx->requestedItem(), 200 );
		if ( '' === $slug ) {
			return null;
		}
		$a    = $this->atts( $atts );
		$item = $this->c->items()->getBySlug( $a['type'], $slug );
		if ( ! $item ) {
			return null;
		}
		if ( '' !== trim( $a['category'] ) ) {
			$allowed = $this->c->terms()->withDescendants( $a['type'], $this->c->terms()->resolve( $a['type'], $a['category'] ) );
			$mine    = array_map( 'intval', array_column( $this->c->terms()->ofItem( $a['type'], (int) $item['id'] ), 'id' ) );
			if ( ! array_intersect( $allowed, $mine ) ) {
				return null;
			}
		}
		return $item;
	}

	private function state( array $a, array $q ): array {
		$state = array( 'search' => '', 'cats' => array(), 'meta' => array(), 'sort' => $a['default_sort'], 'page' => 1 );
		if ( isset( $q[ self::P_SEARCH ] ) && is_string( $q[ self::P_SEARCH ] ) ) {
			$state['search'] = Text::line( $q[ self::P_SEARCH ], 200 );
		}
		if ( isset( $q[ self::P_CATS ] ) ) {
			$state['cats'] = array_slice( array_values( array_filter( array_map( 'intval', (array) $q[ self::P_CATS ] ) ) ), 0, 50 );
		}
		if ( isset( $q[ self::P_FIELDS ] ) && is_array( $q[ self::P_FIELDS ] ) ) {
			$allowed = $this->filterKeys( $a );
			foreach ( $q[ self::P_FIELDS ] as $key => $values ) {
				if ( in_array( $key, $allowed, true ) ) {
					$state['meta'][ $key ] = array_slice( array_map( function ( $v ) {
						return Text::line( $v, 191 );
					}, array_filter( (array) $values, 'is_scalar' ) ), 0, 50 );
				}
			}
		}
		if ( isset( $q[ self::P_SORT ] ) && is_string( $q[ self::P_SORT ] ) && isset( $this->c->query()->sortOptions( $a['type'], true )[ $q[ self::P_SORT ] ] ) ) {
			$state['sort'] = $q[ self::P_SORT ];
		}
		if ( isset( $q[ self::P_PAGE ] ) ) {
			$state['page'] = max( 1, min( 10000, (int) $q[ self::P_PAGE ] ) );
		}
		return $state;
	}

	private function params( array $state, array $a ): array {
		$p = array();
		if ( '' !== $state['search'] ) {
			$p[ self::P_SEARCH ] = $state['search'];
		}
		if ( $state['cats'] ) {
			$p[ self::P_CATS ] = $state['cats'];
		}
		if ( $state['meta'] ) {
			$p[ self::P_FIELDS ] = $state['meta'];
		}
		if ( $state['sort'] !== $a['default_sort'] ) {
			$p[ self::P_SORT ] = $state['sort'];
		}
		if ( $state['page'] > 1 ) {
			$p[ self::P_PAGE ] = $state['page'];
		}
		return $p;
	}

	/** Sidebar filter keys — public fields only. */
	private function filterKeys( array $a ): array {
		$public = $this->c->fields()->fieldsByKey( $a['type'], true );
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $a['filter_fields'] ) ), function ( $k ) use ( $public ) {
			return isset( $public[ $k ] );
		} ) );
	}

	private function listView( array $a, Context $ctx, array $state ): string {
		$type   = $a['type'];
		$result = $this->c->query()->run( $type, array(
			'search'   => $state['search'],
			'cats'     => $state['cats'],
			'base_cat' => $a['category'],
			'meta'     => $state['meta'],
			'sort'     => $state['sort'],
			'public'   => true,
			'page'     => $state['page'],
			'per_page' => (int) $a['per_page'],
		) );
		$ids = array_map( 'intval', array_column( $result['rows'], 'id' ) );
		$this->c->images()->primeItems( $ids );
		$this->c->terms()->primeItems( $type, $ids );

		$params   = $this->params( $state, $a );
		$fields   = $this->cardFields( $type, $a['show_fields'] );
		$filtered = '' !== $state['search'] || $state['cats'] || $state['meta'];
		$t        = array( $this->c, 't' );

		ob_start();
		?>
		<div class="dwcat-layout">
			<aside class="dwcat-sidebar">
				<button type="button" class="dwcat-sidebar-toggle" data-dwcat-toggle aria-expanded="false"><?php echo Html::e( $t( 'Filters' ) ); ?></button>
				<form id="dwcat-filter-form" class="dwcat-filter-form" method="get" action="<?php echo Html::url( $ctx->listUrl() ); ?>" data-dwcat-form>
					<?php if ( 'yes' === $a['search'] ) : ?>
						<div class="dwcat-filter-block">
							<label class="dwcat-filter-title" for="dwcat-search"><?php echo Html::e( $t( 'Search' ) ); ?></label>
							<div class="dwcat-search">
								<input type="search" id="dwcat-search" name="<?php echo self::P_SEARCH; ?>" value="<?php echo Html::attr( $state['search'] ); ?>" placeholder="<?php echo Html::attr( $t( 'Search…' ) ); ?>" maxlength="200">
								<button type="submit" aria-label="<?php echo Html::attr( $t( 'Search' ) ); ?>"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
							</div>
						</div>
					<?php endif; ?>
					<?php echo $this->categoryFilter( $a, $state ); ?>
					<?php echo $this->fieldFilters( $a, $state ); ?>
					<?php if ( 'yes' !== $a['sort'] && $state['sort'] !== $a['default_sort'] ) : ?>
						<input type="hidden" name="<?php echo self::P_SORT; ?>" value="<?php echo Html::attr( $state['sort'] ); ?>">
					<?php endif; ?>
					<noscript><button type="submit" class="dwcat-btn"><?php echo Html::e( $t( 'Apply' ) ); ?></button></noscript>
					<?php if ( $filtered ) : ?>
						<a class="dwcat-btn dwcat-reset" href="<?php echo Html::url( $ctx->listUrl() ); ?>" data-dwcat-link><?php echo Html::e( $t( 'Reset filters' ) ); ?></a>
					<?php endif; ?>
				</form>
			</aside>

			<div class="dwcat-main">
				<div class="dwcat-toolbar">
					<span class="dwcat-count"><?php echo Html::e( sprintf( 1 === $result['total'] ? $t( '%d item' ) : $t( '%d items' ), $result['total'] ) ); ?></span>
					<?php if ( 'yes' === $a['sort'] ) : ?>
						<label class="dwcat-sort">
							<span><?php echo Html::e( $t( 'Sort by' ) ); ?></span>
							<select name="<?php echo self::P_SORT; ?>" form="dwcat-filter-form" data-dwcat-sort data-default="<?php echo Html::attr( $a['default_sort'] ); ?>">
								<?php foreach ( $this->c->query()->sortOptions( $type, true ) as $key => $label ) : ?>
									<option value="<?php echo Html::attr( $key ); ?>" <?php echo $state['sort'] === $key ? 'selected' : ''; ?>><?php echo Html::e( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>
				</div>

				<?php if ( $result['rows'] ) : ?>
					<div class="dwcat-grid" style="--dwcat-cols: <?php echo max( 1, min( 6, (int) $a['columns'] ) ); ?>;">
						<?php foreach ( $result['rows'] as $i => $item ) : ?>
							<div class="dwcat-card"><?php echo $this->card( $item, $fields, $ctx->itemUrl( $item['slug'], $params ), 'yes' === $a['show_category'], $i < (int) $a['columns'] ); ?></div>
						<?php endforeach; ?>
					</div>
					<?php echo $this->pagination( $result, $params, $ctx ); ?>
				<?php else : ?>
					<p class="dwcat-empty"><?php echo Html::e( $t( 'No items match your filters.' ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	private function pagination( array $result, array $params, Context $ctx ): string {
		if ( $result['pages'] < 2 ) {
			return '';
		}
		$html = '<nav class="dwcat-pagination" aria-label="' . Html::attr( $this->c->t( 'Pages' ) ) . '">';
		$link = function ( $p, $label ) use ( $params, $ctx, $result ) {
			if ( $p === $result['page'] ) {
				return '<span class="page-numbers current" aria-current="page">' . $label . '</span>';
			}
			$qp = $params;
			unset( $qp[ self::P_PAGE ] );
			if ( $p > 1 ) {
				$qp[ self::P_PAGE ] = $p;
			}
			return '<a class="page-numbers" href="' . Html::url( $ctx->listUrl( $qp ) ) . '">' . $label . '</a>';
		};
		if ( $result['page'] > 1 ) {
			$html .= $link( $result['page'] - 1, '&lsaquo;' );
		}
		$gap = false;
		for ( $p = 1; $p <= $result['pages']; $p++ ) {
			if ( $p > 1 && $p < $result['pages'] && abs( $p - $result['page'] ) > 2 ) {
				if ( ! $gap ) {
					$html .= '<span class="page-numbers dots">…</span>';
					$gap   = true;
				}
				continue;
			}
			$gap   = false;
			$html .= $link( $p, (string) $p );
		}
		if ( $result['page'] < $result['pages'] ) {
			$html .= $link( $result['page'] + 1, '&rsaquo;' );
		}
		return $html . '</nav>';
	}

	private function categoryFilter( array $a, array $state ): string {
		$config = $this->c->fields()->type( $a['type'] );
		if ( empty( $config['has_category'] ) ) {
			return '';
		}
		$tree   = $this->c->terms()->tree( $a['type'] );
		$counts = $this->c->terms()->counts( $a['type'] );
		$root   = 0;
		if ( '' !== trim( $a['category'] ) ) {
			$ids = $this->c->terms()->resolve( $a['type'], $a['category'] );
			if ( ! $ids ) {
				return '';
			}
			$root = $ids[0];
		}
		// Hide branches without published items
		$has = function ( $id ) use ( &$has, $tree, $counts ) {
			if ( ! empty( $counts[ $id ] ) ) {
				return true;
			}
			foreach ( isset( $tree[ $id ] ) ? $tree[ $id ] : array() as $child ) {
				if ( $has( (int) $child['id'] ) ) {
					return true;
				}
			}
			return false;
		};
		$list = function ( $parent ) use ( &$list, $tree, $state, $has ) {
			$html = '';
			foreach ( isset( $tree[ $parent ] ) ? $tree[ $parent ] : array() as $t ) {
				if ( ! $has( (int) $t['id'] ) ) {
					continue;
				}
				$html .= '<li><label><input type="checkbox" name="' . self::P_CATS . '[]" value="' . (int) $t['id'] . '"' . ( in_array( (int) $t['id'], $state['cats'], true ) ? ' checked' : '' ) . '> ' . Html::e( $t['name'] ) . '</label>' . $list( (int) $t['id'] ) . '</li>';
			}
			return '' !== $html ? '<ul class="dwcat-checklist">' . $html . '</ul>' : '';
		};
		$inner = $list( $root );
		if ( '' === $inner ) {
			return '';
		}
		return '<div class="dwcat-filter-block"><div class="dwcat-filter-title">' . Html::e( $this->c->t( 'Categories' ) ) . '</div>' . $inner . '</div>';
	}

	private function fieldFilters( array $a, array $state ): string {
		$fields = $this->c->fields()->fieldsByKey( $a['type'], true );
		$html   = '';
		foreach ( $this->filterKeys( $a ) as $key ) {
			$choices = $this->c->query()->filterChoices( $a['type'], $fields[ $key ], $a['category'] );
			if ( ! $choices ) {
				continue;
			}
			$sel   = isset( $state['meta'][ $key ] ) ? $state['meta'][ $key ] : array();
			$html .= '<div class="dwcat-filter-block"><div class="dwcat-filter-title">' . Html::e( $fields[ $key ]['label'] ) . '</div><ul class="dwcat-checklist">';
			foreach ( $choices as $value => $label ) {
				$html .= '<li><label><input type="checkbox" name="' . self::P_FIELDS . '[' . Html::attr( $key ) . '][]" value="' . Html::attr( $value ) . '"' . ( in_array( (string) $value, $sel, true ) ? ' checked' : '' ) . '> ' . Html::e( $label ) . '</label></li>';
			}
			$html .= '</ul></div>';
		}
		return $html;
	}

	// ─────────────────────────────────────────────────────────────
	// Card
	// ─────────────────────────────────────────────────────────────

	/** Card fields — explicit keys or the "List" fields; public only, never the name field. */
	private function cardFields( string $type, string $keys ): array {
		$all  = array_filter( $this->c->fields()->fields( $type ), function ( $f ) {
			return FieldConfig::isPublic( $f ) && empty( $f['is_title_field'] );
		} );
		$want = array_filter( array_map( 'trim', explode( ',', $keys ) ) );
		return array_values( array_filter( $all, function ( $f ) use ( $want ) {
			return $want ? in_array( $f['meta_key'], $want, true ) : ! empty( $f['show_in_list'] );
		} ) );
	}

	/** $eager: first row — load immediately (it is what the visitor sees first). */
	private function card( array $item, array $fields, string $url, bool $showCategory, bool $eager = false ): string {
		$id   = (int) $item['id'];
		$img  = $this->c->images()->main( $id );
		// Cards are ~300px on PC, ~half the screen on phones → the 600px file covers both at 2×.
		$html = '<a href="' . Html::url( $url ) . '" class="dwcat-card-link"><div class="dwcat-card-image">'
			. ( $img ? $this->c->images()->tag( $img, 'medium', '(max-width: 600px) 50vw, 300px', ! $eager ) : '<div class="dwcat-no-image">—</div>' )
			. '</div>';
		if ( $showCategory ) {
			$terms = $this->c->terms()->ofItem( $item['type'], $id );
			if ( $terms ) {
				$html .= '<div class="dwcat-card-category">' . Html::e( $terms[0]['name'] ) . '</div>';
			}
		}
		$html .= '<h3 class="dwcat-card-title">' . Html::e( $item['title'] ) . '</h3>';
		$rows  = '';
		foreach ( $fields as $f ) {
			$v = FieldConfig::display( $f, $this->c->items()->value( $id, $f['meta_key'] ) );
			if ( '' !== $v ) {
				$rows .= '<li><span class="dwcat-field-label">' . Html::e( $f['label'] ) . ':</span> <span class="dwcat-field-value">' . Html::e( $v ) . '</span></li>';
			}
		}
		if ( '' !== $rows ) {
			$html .= '<ul class="dwcat-card-fields">' . $rows . '</ul>';
		}
		return $html . '</a>';
	}

	// ─────────────────────────────────────────────────────────────
	// Detail
	// ─────────────────────────────────────────────────────────────

	private function detail( array $item, array $a, Context $ctx, array $state ): string {
		$id     = (int) $item['id'];
		$type   = $item['type'];
		$terms  = $this->c->terms()->ofItem( $type, $id );
		$images = $this->c->images()->forItem( $id );
		$t      = array( $this->c, 't' );

		$want   = array_filter( array_map( 'trim', explode( ',', (string) $a['detail_fields'] ) ) );
		$fields = array_filter( $this->c->fields()->fields( $type ), function ( $f ) use ( $want ) {
			return FieldConfig::isPublic( $f ) && empty( $f['is_title_field'] ) && ( ! $want || in_array( $f['meta_key'], $want, true ) );
		} );

		ob_start();
		?>
		<article class="dwcat-detail">
			<a class="dwcat-back" href="<?php echo Html::url( $ctx->listUrl( $this->params( $state, $a ) ) ); ?>" data-dwcat-link>&larr; <?php echo Html::e( $t( 'Back to list' ) ); ?></a>
			<div class="dwcat-detail-top">
				<div class="dwcat-detail-media">
					<?php if ( $images ) : ?>
						<div class="dwcat-detail-main" data-dwcat-main><?php echo $this->c->images()->tag( $images[0], 'large', '(max-width: 900px) 100vw, 600px', false ); ?></div>
						<?php if ( count( $images ) > 1 ) : ?>
							<div class="dwcat-detail-thumbs">
								<?php foreach ( $images as $i => $img ) : ?>
									<button type="button" class="dwcat-thumb<?php echo 0 === $i ? ' is-active' : ''; ?>" data-dwcat-thumb="<?php echo Html::attr( $this->c->images()->url( $img, 'large' ) ); ?>"><?php echo $this->c->images()->tag( $img, 'thumb' ); ?></button>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					<?php else : ?>
						<div class="dwcat-detail-main dwcat-no-image">—</div>
					<?php endif; ?>
				</div>
				<div class="dwcat-detail-info">
					<?php if ( $terms ) : ?>
						<div class="dwcat-card-category"><?php echo Html::e( implode( ' · ', array_column( $terms, 'name' ) ) ); ?></div>
					<?php endif; ?>
					<h1 class="dwcat-detail-title"><?php echo Html::e( $item['title'] ); ?></h1>
					<?php
					$rows = '';
					foreach ( $fields as $f ) {
						$v = FieldConfig::display( $f, $this->c->items()->value( $id, $f['meta_key'] ) );
						if ( '' !== $v ) {
							$rows .= '<dt>' . Html::e( $f['label'] ) . '</dt><dd>' . ( 'textarea' === $f['type'] ? nl2br( Html::e( $v ) ) : Html::e( $v ) ) . '</dd>';
						}
					}
					if ( '' !== $rows ) {
						echo '<dl class="dwcat-detail-fields">' . $rows . '</dl>';
					}
					echo $this->variationsTable( $item );
					?>
					<?php if ( '' !== $a['inquiry_url'] ) : ?>
						<a class="dwcat-btn dwcat-btn-primary" href="<?php echo Html::url( $a['inquiry_url'] . ( false === strpos( $a['inquiry_url'], '?' ) ? '?' : '&' ) . 'item=' . rawurlencode( $item['title'] ) ); ?>"><?php echo Html::e( '' !== $a['inquiry_label'] ? $a['inquiry_label'] : $t( 'Inquire about this item' ) ); ?></a>
					<?php endif; ?>
				</div>
			</div>
			<?php if ( '' !== trim( (string) $item['content'] ) ) : ?>
				<div class="dwcat-detail-description"><?php echo $this->c->host()->sanitizeHtml( self::paragraphs( (string) $item['content'] ) ); ?></div>
			<?php endif; ?>
			<?php echo $this->related( $item, $a, $ctx, $state ); ?>
		</article>
		<?php
		return (string) ob_get_clean();
	}

	private function variationsTable( array $item ): string {
		$rows = $this->c->items()->variations( (int) $item['id'] );
		if ( ! $rows ) {
			return '';
		}
		$cols = array();
		foreach ( $this->c->fields()->variationColumns( $item['type'] ) as $col ) {
			if ( ! VariationColumns::isPublic( $col ) ) {
				continue; // internal columns never reach the site
			}
			foreach ( $rows as $row ) {
				if ( isset( $row[ $col['key'] ] ) && '' !== (string) $row[ $col['key'] ] ) {
					$cols[] = $col;
					break;
				}
			}
		}
		if ( ! $cols ) {
			return '';
		}
		$html = '<div class="dwcat-variations"><h3 class="dwcat-variations-title">' . Html::e( $this->c->t( 'Options' ) ) . '</h3><table class="dwcat-variations-table"><thead><tr>';
		foreach ( $cols as $col ) {
			$html .= '<th>' . Html::e( $col['label'] ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( $cols as $col ) {
				$v = isset( $row[ $col['key'] ] ) ? (string) $row[ $col['key'] ] : '';
				if ( 'select' === $col['type'] && '' !== $v ) {
					$opts = Text::parseOptions( $col['options'] );
					$v    = '<span class="dwcat-var-opt dwcat-var-' . Html::attr( $col['key'] . '-' . $v ) . '">' . Html::e( isset( $opts[ $v ] ) ? $opts[ $v ] : $v ) . '</span>';
				} else {
					$v = Html::e( $v );
				}
				$html .= '<td data-label="' . Html::attr( $col['label'] ) . '">' . $v . '</td>';
			}
			$html .= '</tr>';
		}
		return $html . '</tbody></table></div>';
	}

	private function related( array $item, array $a, Context $ctx, array $state ): string {
		$count = (int) $a['related'];
		$terms = array_map( 'intval', array_column( $this->c->terms()->ofItem( $item['type'], (int) $item['id'] ), 'id' ) );
		if ( $count < 1 || ! $terms ) {
			return '';
		}
		$result = $this->c->query()->run( $item['type'], array( 'cats' => $terms, 'public' => true, 'per_page' => $count + 1 ) );
		$rows   = array_slice( array_values( array_filter( $result['rows'], function ( $r ) use ( $item ) {
			return (int) $r['id'] !== (int) $item['id'];
		} ) ), 0, $count );
		if ( ! $rows ) {
			return '';
		}
		$ids = array_map( 'intval', array_column( $rows, 'id' ) );
		$this->c->images()->primeItems( $ids );
		$this->c->terms()->primeItems( $item['type'], $ids );
		$fields = $this->cardFields( $item['type'], (string) $a['show_fields'] );
		$params = $this->params( $state, $a );
		$html   = '<section class="dwcat-related"><h2 class="dwcat-related-title">' . Html::e( $this->c->t( 'Related items' ) ) . '</h2><div class="dwcat-grid" style="--dwcat-cols: ' . min( 4, $count ) . ';">';
		foreach ( $rows as $r ) {
			$html .= '<div class="dwcat-card">' . $this->card( $r, $fields, $ctx->itemUrl( $r['slug'], $params ), true ) . '</div>';
		}
		return $html . '</div></section>';
	}

	/** Plain line breaks → paragraphs (descriptions typed without an editor). */
	private static function paragraphs( string $html ): string {
		if ( preg_match( '/<(p|div|ul|ol|h[1-6]|table)\b/i', $html ) ) {
			return $html;
		}
		$parts = preg_split( '/\n\s*\n/', trim( $html ) );
		return '<p>' . implode( '</p><p>', array_map( 'nl2br', $parts ) ) . '</p>';
	}

	// ─────────────────────────────────────────────────────────────
	// SEO (host prints these in <head>)
	// ─────────────────────────────────────────────────────────────

	/** @return array title, description, canonical, image, json_ld */
	public function seo( array $item, Context $ctx ): array {
		$id    = (int) $item['id'];
		$img   = $this->c->images()->main( $id );
		$desc  = '' !== trim( (string) $item['excerpt'] ) ? Text::summary( (string) $item['excerpt'] ) : Text::summary( (string) $item['content'] );
		$url   = $ctx->itemUrl( $item['slug'] );
		$ld    = array(
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => $item['title'],
			'url'      => $url,
		);
		if ( '' !== $desc ) {
			$ld['description'] = $desc;
		}
		if ( $img ) {
			$ld['image'] = $this->c->images()->url( $img, 'large' );
		}
		$public = $this->c->fields()->fieldsByKey( $item['type'], true );
		foreach ( array( 'dw_pc_item_code' => 'sku', 'dw_pc_brand_raw' => 'brand' ) as $key => $prop ) {
			if ( isset( $public[ $key ] ) ) {
				$v = $this->c->items()->value( $id, $key );
				if ( '' !== $v ) {
					$ld[ $prop ] = 'brand' === $prop ? array( '@type' => 'Brand', 'name' => $v ) : $v;
				}
			}
		}
		return array(
			'title'       => $item['title'],
			'description' => $desc,
			'canonical'   => $url,
			'image'       => $img ? $this->c->images()->url( $img, 'large' ) : '',
			'json_ld'     => $ld,
		);
	}

	// ─────────────────────────────────────────────────────────────
	// Grid / carousel blocks
	// ─────────────────────────────────────────────────────────────

	/**
	 * @param array   $atts category, per_page, columns, ids, show_fields, orderby (date|title), order
	 * @param Context $link context of the catalog page items link to
	 */
	public function grid( array $atts, Context $link, bool $carousel = false ): string {
		$a    = array_merge( array( 'type' => '', 'category' => '', 'per_page' => 12, 'columns' => 0, 'per_slide' => 3, 'ids' => '', 'show_fields' => '', 'orderby' => 'date', 'order' => 'DESC', 'autoplay' => 'yes', 'interval' => 5000 ), $atts );
		$type = $this->c->fields()->type( (string) $a['type'] ) ? (string) $a['type'] : $this->c->fields()->primaryType();
		$ids  = array_filter( array_map( 'intval', explode( ',', (string) $a['ids'] ) ) );
		$sort = $ids ? 'ids' : ( 'title' === $a['orderby'] ? 'title_' : 'date_' ) . ( 'ASC' === strtoupper( $a['order'] ) ? 'asc' : 'desc' );
		$res  = $this->c->query()->run( $type, array( 'base_cat' => $a['category'], 'ids' => $ids, 'sort' => $sort, 'public' => true, 'per_page' => (int) $a['per_page'] ) );
		if ( ! $res['rows'] ) {
			return '<p class="dwcat-empty">' . Html::e( $this->c->t( 'No items found.' ) ) . '</p>';
		}
		$rowIds = array_map( 'intval', array_column( $res['rows'], 'id' ) );
		$this->c->images()->primeItems( $rowIds );
		$this->c->terms()->primeItems( $type, $rowIds );
		$fields = $this->cardFields( $type, (string) $a['show_fields'] );
		$cols   = (int) $a['columns'] ?: Design::get( $this->c->settings() )['columns'];
		$style  = Html::attr( Design::style( $this->c->settings() ) );

		$cards = '';
		foreach ( $res['rows'] as $item ) {
			$card   = '<div class="dwcat-card">' . $this->card( $item, $fields, $link->itemUrl( $item['slug'] ), true ) . '</div>';
			$cards .= $carousel ? '<div class="dwcat-carousel-slide">' . $card . '</div>' : $card;
		}
		if ( ! $carousel ) {
			return '<div class="dwcat-grid" style="--dwcat-cols: ' . max( 1, min( 6, $cols ) ) . ';' . $style . '">' . $cards . '</div>';
		}
		$per = max( 1, min( 6, (int) $a['per_slide'] ) );
		return '<div class="dwcat-carousel" data-autoplay="' . ( 'yes' === $a['autoplay'] ? '1' : '0' ) . '" data-interval="' . max( 1000, (int) $a['interval'] ) . '" data-per-slide="' . $per . '" style="--dwcat-cols: ' . $per . ';' . $style . '">'
			. '<button type="button" class="dwcat-carousel-btn dwcat-prev" aria-label="' . Html::attr( $this->c->t( 'Previous' ) ) . '">&#10094;</button>'
			. '<div class="dwcat-carousel-viewport"><div class="dwcat-carousel-track">' . $cards . '</div></div>'
			. '<button type="button" class="dwcat-carousel-btn dwcat-next" aria-label="' . Html::attr( $this->c->t( 'Next' ) ) . '">&#10095;</button></div>';
	}
}
