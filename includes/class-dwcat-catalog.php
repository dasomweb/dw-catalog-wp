<?php
/**
 * [dw_catalog] — full catalog page from a single shortcode.
 *
 *   [dw_catalog category="refrigerated" filter_fields="dw_pc_brand_raw"]
 *
 * List view : left sidebar (search, categories, field filters, reset) + sort + grid + pagination.
 * Detail    : same page with ?dw_item={slug} — image, fields, variations, description, related items.
 *
 * Filters are plain GET params, so every state has a shareable URL and works without JS;
 * assets/js/catalog.js upgrades it to in-place updates (fetch + history.pushState).
 *
 * Single product permalinks (/product/{slug}/) render the same detail view via the_content.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Catalog {

	/** URL params */
	const P_SEARCH = 'dwq';
	const P_CATS   = 'dwc';
	const P_FIELDS = 'dwf';
	const P_SORT   = 'dws';
	const P_PAGE   = 'dwp';
	const P_ITEM   = 'dw_item';

	/** @var DWCAT_Shortcodes */
	private $cards;

	/** Re-entrancy guard for the_content (detail view renders post content). */
	private $rendering = false;

	public function __construct( DWCAT_Shortcodes $cards ) {
		$this->cards = $cards;
		add_shortcode( 'dw_catalog', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_filter( 'the_content', array( $this, 'single_content' ), 20 );
	}

	public function register_assets() {
		$config = dwcat_get_config();
		wp_register_script(
			'dwcat-catalog',
			DWCAT_URL_Helper::get_js_url( 'catalog.js' ),
			array(),
			$config['plugin_version'],
			true
		);
	}

	// ─────────────────────────────────────────────────────────────
	// Shortcode
	// ─────────────────────────────────────────────────────────────

	public function shortcode( $atts ) {
		// 게이트 1 + 8 — 라이선스 없이는 프런트에 카탈로그를 렌더하지 않습니다.
		if ( ! dwcat_can_render() || ! dwcat_can_use_shortcode() ) {
			return dwcat_gate_placeholder();
		}

		$atts = shortcode_atts( array(
			'post_type'      => 'product',
			'category'       => '',      // fixed category (slug or ID, comma separated)
			'columns'        => 4,
			'per_page'       => 20,
			'show_fields'    => '',      // card fields (default: "List" fields)
			'detail_fields'  => '',      // detail fields (default: all except title / internal)
			'filter_fields'  => '',      // sidebar field filters, comma separated meta keys
			'search'         => 'yes',
			'sort'           => 'yes',
			'default_sort'   => 'date_desc',
			'show_category'  => 'yes',   // category label on cards
			'image_size'     => 'medium',
			'related'        => 4,       // related items on the detail view (0 = off)
			'inquiry_url'    => '',      // optional "Inquire" button on the detail view
			'inquiry_label'  => '',
		), $atts, 'dw_catalog' );

		$pt = $atts['post_type'];
		if ( ! DWCAT_Config::is_our_post_type( $pt ) ) {
			return '<p>' . esc_html__( 'Invalid post type.', 'dw-catalog-wp' ) . '</p>';
		}

		if ( dwcat_can_load_assets() ) {
			wp_enqueue_style( 'dwcat-frontend' );
			wp_enqueue_script( 'dwcat-catalog' );
		}

		$state = $this->read_state( $pt, $atts );

		$item = $this->find_item( $pt, $atts );
		$html = $item
			? $this->render_detail( $item, $atts, $state )
			: $this->render_list( $pt, $atts, $state );

		// alignwide: block themes otherwise squeeze the catalog into the narrow text column
		return '<div class="dwcat-catalog alignwide" data-dwcat-catalog style="' . esc_attr( DWCAT_Design_Settings::get_inline_style() ) . '">' . $html . '</div>';
	}

	/**
	 * Requested list state from the URL — whitelisted against the real fields / terms.
	 */
	private function read_state( $pt, $atts ) {
		$state = array(
			'search' => '',
			'cats'   => array(),
			'meta'   => array(),
			'sort'   => $atts['default_sort'],
			'page'   => 1,
		);

		if ( isset( $_GET[ self::P_SEARCH ] ) ) {
			$state['search'] = sanitize_text_field( wp_unslash( $_GET[ self::P_SEARCH ] ) );
		}
		if ( isset( $_GET[ self::P_CATS ] ) ) {
			$state['cats'] = array_values( array_filter( array_map( 'absint', (array) $_GET[ self::P_CATS ] ) ) );
		}
		if ( isset( $_GET[ self::P_FIELDS ] ) && is_array( $_GET[ self::P_FIELDS ] ) ) {
			$allowed = $this->filter_field_keys( $atts );
			foreach ( wp_unslash( $_GET[ self::P_FIELDS ] ) as $key => $values ) {
				if ( in_array( $key, $allowed, true ) ) {
					$state['meta'][ $key ] = array_map( 'sanitize_text_field', (array) $values );
				}
			}
		}
		if ( isset( $_GET[ self::P_SORT ] ) ) {
			$sort = sanitize_text_field( wp_unslash( $_GET[ self::P_SORT ] ) );
			if ( isset( DWCAT_Catalog_Query::get_sort_options( $pt, true )[ $sort ] ) ) {
				$state['sort'] = $sort;
			}
		}
		if ( isset( $_GET[ self::P_PAGE ] ) ) {
			$state['page'] = max( 1, absint( $_GET[ self::P_PAGE ] ) );
		}
		return $state;
	}

	/** State → URL params (empty values dropped). */
	private function state_params( $state, $atts ) {
		$params = array();
		if ( '' !== $state['search'] ) {
			$params[ self::P_SEARCH ] = $state['search'];
		}
		if ( $state['cats'] ) {
			$params[ self::P_CATS ] = $state['cats'];
		}
		if ( $state['meta'] ) {
			$params[ self::P_FIELDS ] = $state['meta'];
		}
		if ( $state['sort'] !== $atts['default_sort'] ) {
			$params[ self::P_SORT ] = $state['sort'];
		}
		if ( $state['page'] > 1 ) {
			$params[ self::P_PAGE ] = $state['page'];
		}
		return $params;
	}

	private function page_url( $params = array() ) {
		$base = is_singular() ? get_permalink( get_queried_object_id() ) : home_url( add_query_arg( array() ) );
		$base = strtok( $base, '?' );
		return $params ? $base . '?' . http_build_query( $params ) : $base;
	}

	/** Sidebar filter fields — public fields only (an internal field's values must not be listed). */
	private function filter_field_keys( $atts ) {
		$public = DWCAT_Catalog_Query::allowed_fields( $atts['post_type'], true );
		return array_values( array_filter( array_map( 'trim', explode( ',', $atts['filter_fields'] ) ), function ( $k ) use ( $public ) {
			return isset( $public[ $k ] );
		} ) );
	}

	// ─────────────────────────────────────────────────────────────
	// List view
	// ─────────────────────────────────────────────────────────────

	private function render_list( $pt, $atts, $state ) {
		$args = DWCAT_Catalog_Query::build_args(
			$pt,
			array(
				'search'   => $state['search'],
				'cats'     => $state['cats'],
				'base_cat' => $atts['category'],
				'meta'     => $state['meta'],
				'sort'     => $state['sort'],
				'public'   => true,
			),
			array(
				'posts_per_page' => max( 1, min( 100, (int) $atts['per_page'] ) ),
				'paged'          => $state['page'],
				'post_status'    => 'publish',
			)
		);
		$query   = new WP_Query( $args );
		DWCAT_Store::prime( wp_list_pluck( $query->posts, 'ID' ) );
		$fields  = $this->card_fields( $pt, $atts['show_fields'] );
		$columns = max( 1, min( 6, (int) $atts['columns'] ) );
		$params  = $this->state_params( $state, $atts );
		$form_id = 'dwcat-filter-form';
		$is_filtered = '' !== $state['search'] || $state['cats'] || $state['meta'];

		ob_start();
		?>
		<div class="dwcat-layout">
			<aside class="dwcat-sidebar">
				<button type="button" class="dwcat-sidebar-toggle" data-dwcat-toggle aria-expanded="false"><?php esc_html_e( 'Filters', 'dw-catalog-wp' ); ?></button>
				<form id="<?php echo esc_attr( $form_id ); ?>" class="dwcat-filter-form" method="get" action="<?php echo esc_url( $this->page_url() ); ?>" data-dwcat-form>
					<?php if ( 'yes' === $atts['search'] ) : ?>
						<div class="dwcat-filter-block">
							<label class="dwcat-filter-title" for="dwcat-search"><?php esc_html_e( 'Search', 'dw-catalog-wp' ); ?></label>
							<div class="dwcat-search">
								<input type="search" id="dwcat-search" name="<?php echo esc_attr( self::P_SEARCH ); ?>" value="<?php echo esc_attr( $state['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search...', 'dw-catalog-wp' ); ?>">
								<button type="submit" aria-label="<?php esc_attr_e( 'Search', 'dw-catalog-wp' ); ?>">
									<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
								</button>
							</div>
						</div>
					<?php endif; ?>

					<?php $this->render_category_filter( $pt, $atts, $state ); ?>
					<?php $this->render_field_filters( $pt, $atts, $state ); ?>

					<?php if ( 'yes' !== $atts['sort'] && $state['sort'] !== $atts['default_sort'] ) : ?>
						<input type="hidden" name="<?php echo esc_attr( self::P_SORT ); ?>" value="<?php echo esc_attr( $state['sort'] ); ?>">
					<?php endif; ?>

					<noscript><button type="submit" class="dwcat-btn"><?php esc_html_e( 'Apply', 'dw-catalog-wp' ); ?></button></noscript>
					<?php if ( $is_filtered ) : ?>
						<a class="dwcat-btn dwcat-reset" href="<?php echo esc_url( $this->page_url() ); ?>" data-dwcat-link><?php esc_html_e( 'Reset filters', 'dw-catalog-wp' ); ?></a>
					<?php endif; ?>
				</form>
			</aside>

			<div class="dwcat-main">
				<div class="dwcat-toolbar">
					<span class="dwcat-count">
						<?php /* translators: %d: number of items */ printf( esc_html( _n( '%d item', '%d items', $query->found_posts, 'dw-catalog-wp' ) ), (int) $query->found_posts ); ?>
					</span>
					<?php if ( 'yes' === $atts['sort'] ) : ?>
						<label class="dwcat-sort">
							<span><?php esc_html_e( 'Sort by', 'dw-catalog-wp' ); ?></span>
							<select name="<?php echo esc_attr( self::P_SORT ); ?>" form="<?php echo esc_attr( $form_id ); ?>" data-dwcat-sort data-default="<?php echo esc_attr( $atts['default_sort'] ); ?>">
								<?php foreach ( DWCAT_Catalog_Query::get_sort_options( $pt, true ) as $key => $label ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $state['sort'], $key ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
					<?php endif; ?>
				</div>

				<?php if ( $query->have_posts() ) : ?>
					<div class="dwcat-grid" style="--dwcat-cols: <?php echo (int) $columns; ?>;">
						<?php
						while ( $query->have_posts() ) :
							$query->the_post();
							$post_id = get_the_ID();
							$link    = $this->page_url( array_merge( $params, array( self::P_ITEM => get_post_field( 'post_name', $post_id ) ) ) );
							?>
							<div class="dwcat-card">
								<?php $this->cards->render_card_content( $post_id, $fields, true, $atts['image_size'], $link, 'yes' === $atts['show_category'] ); ?>
							</div>
						<?php endwhile; ?>
					</div>
					<?php
					$pagination = paginate_links( array(
						// Placeholder number survives http_build_query; '%#%' itself would be encoded.
						'base'      => str_replace( '999999999', '%#%', $this->page_url( array_merge( $params, array( self::P_PAGE => 999999999 ) ) ) ),
						'format'    => '',
						'current'   => $state['page'],
						'total'     => $query->max_num_pages,
						'prev_text' => '&lsaquo;',
						'next_text' => '&rsaquo;',
					) );
					if ( $pagination ) {
						echo '<nav class="dwcat-pagination">' . $pagination . '</nav>'; // phpcs:ignore
					}
					?>
				<?php else : ?>
					<p class="dwcat-empty"><?php esc_html_e( 'No items match your filters.', 'dw-catalog-wp' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Category checkboxes — children of the fixed category, or the whole tree.
	 */
	private function render_category_filter( $pt, $atts, $state ) {
		$pt_config = DWCAT_Config::get_post_type( $pt );
		if ( empty( $pt_config['has_category'] ) ) {
			return;
		}
		$tax    = DWCAT_Config::get_category_taxonomy( $pt );
		$parent = 0;
		if ( '' !== $atts['category'] ) {
			$first = trim( strtok( $atts['category'], ',' ) );
			$term  = is_numeric( $first ) ? get_term( (int) $first, $tax ) : get_term_by( 'slug', $first, $tax );
			if ( ! $term || is_wp_error( $term ) ) {
				return;
			}
			$parent = $term->term_id;
		}

		$terms = get_terms( array(
			'taxonomy'   => $tax,
			'hide_empty' => true,
			'child_of'   => $parent,
			'orderby'    => 'name',
		) );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return;
		}

		// Group by parent for an indented tree
		$by_parent = array();
		foreach ( $terms as $t ) {
			$by_parent[ $t->parent ][] = $t;
		}
		?>
		<div class="dwcat-filter-block">
			<div class="dwcat-filter-title"><?php esc_html_e( 'Categories', 'dw-catalog-wp' ); ?></div>
			<?php $this->render_term_list( $by_parent, $parent, $state['cats'] ); ?>
		</div>
		<?php
	}

	private function render_term_list( $by_parent, $parent, $selected ) {
		if ( empty( $by_parent[ $parent ] ) ) {
			return;
		}
		echo '<ul class="dwcat-checklist">';
		foreach ( $by_parent[ $parent ] as $t ) {
			printf(
				'<li><label><input type="checkbox" name="%s[]" value="%d"%s> %s</label>',
				esc_attr( self::P_CATS ),
				(int) $t->term_id,
				checked( in_array( (int) $t->term_id, $selected, true ), true, false ),
				esc_html( $t->name )
			);
			$this->render_term_list( $by_parent, $t->term_id, $selected );
			echo '</li>';
		}
		echo '</ul>';
	}

	private function render_field_filters( $pt, $atts, $state ) {
		$fields = DWCAT_Catalog_Query::allowed_fields( $pt, true );
		foreach ( $this->filter_field_keys( $atts ) as $key ) {
			if ( ! isset( $fields[ $key ] ) ) {
				continue;
			}
			$choices = DWCAT_Catalog_Query::get_filter_choices( $pt, $fields[ $key ], $atts['category'] );
			if ( ! $choices ) {
				continue;
			}
			$selected = isset( $state['meta'][ $key ] ) ? $state['meta'][ $key ] : array();
			?>
			<div class="dwcat-filter-block">
				<div class="dwcat-filter-title"><?php echo esc_html( $fields[ $key ]['label'] ); ?></div>
				<ul class="dwcat-checklist">
					<?php foreach ( $choices as $value => $label ) : ?>
						<li><label>
							<input type="checkbox" name="<?php echo esc_attr( self::P_FIELDS . '[' . $key . '][]' ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( in_array( (string) $value, $selected, true ) ); ?>>
							<?php echo esc_html( $label ); ?>
						</label></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		}
	}

	/** Card fields — explicit list, or the post type's "List" fields; public fields only. */
	private function card_fields( $pt, $show_fields ) {
		$all = array_filter( DWCAT_Config::get_fields( $pt ), array( 'DWCAT_Config', 'is_public_field' ) );
		if ( '' === trim( $show_fields ) ) {
			return array_filter( $all, function ( $f ) {
				return ! empty( $f['show_in_list'] ) && empty( $f['is_title_field'] );
			} );
		}
		$keys = array_map( 'trim', explode( ',', $show_fields ) );
		return array_filter( $all, function ( $f ) use ( $keys ) {
			return in_array( $f['meta_key'], $keys, true );
		} );
	}

	// ─────────────────────────────────────────────────────────────
	// Detail view
	// ─────────────────────────────────────────────────────────────

	/** The item requested with ?dw_item= — must be a published item of this catalog. */
	private function find_item( $pt, $atts ) {
		if ( empty( $_GET[ self::P_ITEM ] ) ) {
			return null;
		}
		$slug  = sanitize_title( wp_unslash( $_GET[ self::P_ITEM ] ) );
		$posts = get_posts( array(
			'name'           => $slug,
			'post_type'      => $pt,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
		) );
		if ( ! $posts ) {
			return null;
		}
		if ( '' !== $atts['category'] ) {
			$tax   = DWCAT_Config::get_category_taxonomy( $pt );
			$terms = array_map( 'trim', explode( ',', $atts['category'] ) );
			$in    = false;
			foreach ( $terms as $t ) {
				$term = is_numeric( $t ) ? get_term( (int) $t, $tax ) : get_term_by( 'slug', $t, $tax );
				if ( $term && ! is_wp_error( $term ) && ( has_term( $term->term_id, $tax, $posts[0] ) || $this->in_child_term( $posts[0]->ID, $term->term_id, $tax ) ) ) {
					$in = true;
					break;
				}
			}
			if ( ! $in ) {
				return null;
			}
		}
		return $posts[0];
	}

	private function in_child_term( $post_id, $term_id, $tax ) {
		$children = get_term_children( $term_id, $tax );
		return $children && ! is_wp_error( $children ) && has_term( $children, $tax, $post_id );
	}

	/**
	 * @param array|null $state List state to return to (null on single permalinks).
	 */
	public function render_detail( $post, $atts, $state = null ) {
		$pt          = $post->post_type;
		$post_id     = $post->ID;
		$tax         = DWCAT_Config::get_category_taxonomy( $pt );
		$terms       = get_the_terms( $post_id, $tax );
		$title_field = DWCAT_Config::get_title_field( $pt );
		$title       = $post->post_title;
		if ( $title_field ) {
			$meta_title = DWCAT_Store::get_value( $post_id, $title_field['meta_key'], '' );
			if ( '' !== $meta_title ) {
				$title = $meta_title;
			}
		}

		ob_start();
		?>
		<article class="dwcat-detail">
			<?php if ( null !== $state ) : ?>
				<a class="dwcat-back" href="<?php echo esc_url( $this->page_url( $this->state_params( $state, $atts ) ) ); ?>" data-dwcat-link>&larr; <?php esc_html_e( 'Back to list', 'dw-catalog-wp' ); ?></a>
			<?php endif; ?>

			<div class="dwcat-detail-top">
				<div class="dwcat-detail-media">
					<?php echo $this->render_gallery( $post_id ); // phpcs:ignore ?>
				</div>

				<div class="dwcat-detail-info">
					<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
						<div class="dwcat-card-category"><?php echo esc_html( implode( ' · ', wp_list_pluck( $terms, 'name' ) ) ); ?></div>
					<?php endif; ?>
					<h1 class="dwcat-detail-title"><?php echo esc_html( $title ); ?></h1>

					<?php $detail_fields = $this->detail_fields( $pt, isset( $atts['detail_fields'] ) ? $atts['detail_fields'] : '' ); ?>
					<?php if ( $detail_fields ) : ?>
						<dl class="dwcat-detail-fields">
							<?php foreach ( $detail_fields as $field ) : ?>
								<?php $val = $this->cards->get_field_value( $post_id, $field ); ?>
								<?php if ( '' === $val ) { continue; } ?>
								<dt><?php echo esc_html( $field['label'] ); ?></dt>
								<dd><?php echo 'textarea' === $field['type'] ? nl2br( esc_html( $val ) ) : esc_html( $val ); ?></dd>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>

					<?php echo DWCAT_Variations::render_table( $post_id ); // phpcs:ignore ?>

					<?php if ( ! empty( $atts['inquiry_url'] ) ) : ?>
						<a class="dwcat-btn dwcat-btn-primary" href="<?php echo esc_url( add_query_arg( 'item', rawurlencode( $title ), $atts['inquiry_url'] ) ); ?>">
							<?php echo esc_html( '' !== $atts['inquiry_label'] ? $atts['inquiry_label'] : __( 'Inquire about this item', 'dw-catalog-wp' ) ); ?>
						</a>
					<?php endif; ?>
				</div>
			</div>

			<?php
			$content = trim( $post->post_content );
			if ( '' !== $content ) :
				$this->rendering = true;
				$content         = apply_filters( 'the_content', $content );
				$this->rendering = false;
				?>
				<div class="dwcat-detail-description"><?php echo $content; // phpcs:ignore ?></div>
			<?php endif; ?>

			<?php echo $this->render_related( $post, $atts, $state ); // phpcs:ignore ?>
		</article>
		<?php
		return ob_get_clean();
	}

	/** Featured image + images attached to the item (thumbnails switch the main image). */
	private function render_gallery( $post_id ) {
		$ids = array();
		if ( has_post_thumbnail( $post_id ) ) {
			$ids[] = (int) get_post_thumbnail_id( $post_id );
		}
		$attached = get_attached_media( 'image', $post_id );
		foreach ( $attached as $img ) {
			if ( ! in_array( (int) $img->ID, $ids, true ) ) {
				$ids[] = (int) $img->ID;
			}
		}
		if ( ! $ids ) {
			return '<div class="dwcat-detail-main dwcat-no-image">—</div>';
		}

		$html = '<div class="dwcat-detail-main" data-dwcat-main>' . wp_get_attachment_image( $ids[0], 'large' ) . '</div>';
		if ( count( $ids ) > 1 ) {
			$html .= '<div class="dwcat-detail-thumbs">';
			foreach ( $ids as $i => $id ) {
				$large = wp_get_attachment_image_src( $id, 'large' );
				$html .= sprintf(
					'<button type="button" class="dwcat-thumb%s" data-dwcat-thumb="%s">%s</button>',
					0 === $i ? ' is-active' : '',
					esc_url( $large ? $large[0] : '' ),
					wp_get_attachment_image( $id, 'thumbnail' )
				);
			}
			$html .= '</div>';
		}
		return $html;
	}

	private function render_related( $post, $atts, $state ) {
		$count = isset( $atts['related'] ) ? (int) $atts['related'] : 4;
		$tax   = DWCAT_Config::get_category_taxonomy( $post->post_type );
		$terms = wp_get_post_terms( $post->ID, $tax, array( 'fields' => 'ids' ) );
		if ( $count < 1 || ! $terms || is_wp_error( $terms ) ) {
			return '';
		}
		$query = new WP_Query( array(
			'post_type'      => $post->post_type,
			'ignore_sticky_posts' => true,
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'post__not_in'   => array( $post->ID ),
			'orderby'        => 'date', // not 'rand': ORDER BY RAND() scans the whole table
			'no_found_rows'  => true,
			'tax_query'      => array( array( 'taxonomy' => $tax, 'terms' => $terms ) ),
		) );
		if ( ! $query->have_posts() ) {
			return '';
		}
		DWCAT_Store::prime( wp_list_pluck( $query->posts, 'ID' ) );

		$fields = $this->card_fields( $post->post_type, isset( $atts['show_fields'] ) ? $atts['show_fields'] : '' );
		$params = null !== $state ? $this->state_params( $state, $atts ) : array();

		ob_start();
		?>
		<section class="dwcat-related">
			<h2 class="dwcat-related-title"><?php esc_html_e( 'Related Items', 'dw-catalog-wp' ); ?></h2>
			<div class="dwcat-grid" style="--dwcat-cols: <?php echo (int) min( 4, $count ); ?>;">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					$rid  = get_the_ID();
					$link = null !== $state
						? $this->page_url( array_merge( $params, array( self::P_ITEM => get_post_field( 'post_name', $rid ) ) ) )
						: null;
					?>
					<div class="dwcat-card">
						<?php $this->cards->render_card_content( $rid, $fields, true, 'medium', $link, true ); ?>
					</div>
				<?php endwhile; ?>
			</div>
		</section>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	/** Detail fields — explicit list, or the fields with "Detail" checked. */
	private function detail_fields( $pt, $detail_fields ) {
		$all = array_filter( DWCAT_Config::get_fields( $pt ), array( 'DWCAT_Config', 'is_public_field' ) );
		if ( '' !== trim( $detail_fields ) ) {
			$keys = array_map( 'trim', explode( ',', $detail_fields ) );
			return array_filter( $all, function ( $f ) use ( $keys ) {
				return in_array( $f['meta_key'], $keys, true );
			} );
		}
		return array_filter( $all, function ( $f ) {
			return empty( $f['is_title_field'] ) && DWCAT_Config::shows_in_detail( $f );
		} );
	}

	// ─────────────────────────────────────────────────────────────
	// Single permalink (/product/{slug}/)
	// ─────────────────────────────────────────────────────────────

	public function single_content( $content ) {
		if ( $this->rendering || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || ! DWCAT_Config::is_our_post_type( $post->post_type ) ) {
			return $content;
		}
		if ( ! dwcat_can_render() ) {
			return dwcat_gate_placeholder();
		}
		if ( dwcat_can_load_assets() ) {
			wp_enqueue_style( 'dwcat-frontend' );
			wp_enqueue_script( 'dwcat-catalog' );
		}
		return '<div class="dwcat-catalog dwcat-catalog-single" style="' . esc_attr( DWCAT_Design_Settings::get_inline_style() ) . '">'
			. $this->render_detail( $post, array() )
			. '</div>';
	}
}
