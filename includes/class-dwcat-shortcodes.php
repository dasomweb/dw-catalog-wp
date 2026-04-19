<?php
/**
 * Shortcodes Class
 *
 * Frontend display shortcodes:
 *   [dw_catalog_grid]     — Grid layout
 *   [dw_catalog_carousel] — Carousel slider
 *   [dw_catalog_magazine] — Magazine-style detail (image + overlay)
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Shortcodes {

	public function __construct() {
		add_shortcode( 'dw_catalog_grid', array( $this, 'shortcode_grid' ) );
		add_shortcode( 'dw_catalog_carousel', array( $this, 'shortcode_carousel' ) );
		add_shortcode( 'dw_catalog_magazine', array( $this, 'shortcode_magazine' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		$config = dwcat_get_config();

		wp_register_style(
			'dwcat-frontend',
			DWCAT_URL_Helper::get_css_url( 'frontend.css' ),
			array(),
			$config['plugin_version']
		);

		wp_register_script(
			'dwcat-carousel',
			DWCAT_URL_Helper::get_js_url( 'carousel.js' ),
			array(),
			$config['plugin_version'],
			true
		);
	}

	/**
	 * Common: parse shared attributes and run WP_Query.
	 */
	private function query_items( $atts ) {
		$args = array(
			'post_type'      => $atts['post_type'],
			'posts_per_page' => max( 1, (int) $atts['per_page'] ),
			'post_status'    => 'publish',
			'orderby'        => sanitize_key( $atts['orderby'] ),
			'order'          => in_array( strtoupper( $atts['order'] ), array( 'ASC', 'DESC' ), true ) ? strtoupper( $atts['order'] ) : 'DESC',
		);

		if ( ! empty( $atts['category'] ) ) {
			$cat_tax = DWCAT_Config::get_category_taxonomy( $atts['post_type'] );
			$terms = array_map( 'trim', explode( ',', $atts['category'] ) );
			$args['tax_query'] = array(
				array(
					'taxonomy' => $cat_tax,
					'field'    => is_numeric( $terms[0] ) ? 'term_id' : 'slug',
					'terms'    => $terms,
				),
			);
		}

		if ( ! empty( $atts['ids'] ) ) {
			$args['post__in'] = array_map( 'intval', explode( ',', $atts['ids'] ) );
			$args['orderby']  = 'post__in';
		}

		return new WP_Query( $args );
	}

	/**
	 * Resolve which fields to display.
	 * $show_fields: comma-separated meta keys, or empty to use all "show_in_list" fields.
	 */
	private function resolve_display_fields( $post_type, $show_fields ) {
		$all_fields = DWCAT_Config::get_fields( $post_type );
		if ( empty( $show_fields ) ) {
			return array_filter( $all_fields, function ( $f ) {
				return ! empty( $f['show_in_list'] ) && empty( $f['is_title_field'] );
			} );
		}

		$requested = array_map( 'trim', explode( ',', $show_fields ) );
		return array_filter( $all_fields, function ( $f ) use ( $requested ) {
			return in_array( $f['meta_key'], $requested, true );
		} );
	}

	/**
	 * Render a single field value (resolves select labels).
	 */
	private function get_field_value( $post_id, $field ) {
		$value = get_post_meta( $post_id, $field['meta_key'], true );
		if ( $value === '' || $value === false ) {
			return '';
		}
		if ( $field['type'] === 'select' ) {
			$opts = DWCAT_Config::parse_select_options( $field['options'] );
			return isset( $opts[ $value ] ) ? $opts[ $value ] : $value;
		}
		return $value;
	}

	/**
	 * [dw_catalog_grid]
	 */
	public function shortcode_grid( $atts ) {
		$atts = shortcode_atts( array(
			'post_type'   => 'product',
			'columns'     => 3,
			'per_page'    => 12,
			'category'    => '',
			'ids'         => '',
			'show_link'   => 'yes',
			'show_fields' => '',
			'image_size'  => 'medium',
			'order'       => 'DESC',
			'orderby'     => 'date',
		), $atts, 'dw_catalog_grid' );

		if ( ! DWCAT_Config::is_our_post_type( $atts['post_type'] ) ) {
			return '<p>' . esc_html__( 'Invalid post type.', 'dw-catalog-wp' ) . '</p>';
		}

		wp_enqueue_style( 'dwcat-frontend' );

		$query = $this->query_items( $atts );
		$fields = $this->resolve_display_fields( $atts['post_type'], $atts['show_fields'] );
		$columns = max( 1, min( 6, (int) $atts['columns'] ) );
		$show_link = in_array( strtolower( $atts['show_link'] ), array( 'yes', '1', 'true' ), true );

		if ( ! $query->have_posts() ) {
			return '<p class="dwcat-empty">' . esc_html__( 'No items found.', 'dw-catalog-wp' ) . '</p>';
		}

		ob_start();
		?>
		<div class="dwcat-grid" style="--dwcat-cols: <?php echo (int) $columns; ?>;">
			<?php while ( $query->have_posts() ) : $query->the_post(); $post_id = get_the_ID(); ?>
				<div class="dwcat-card">
					<?php $this->render_card_content( $post_id, $fields, $show_link, $atts['image_size'] ); ?>
				</div>
			<?php endwhile; ?>
		</div>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * [dw_catalog_carousel]
	 */
	public function shortcode_carousel( $atts ) {
		$atts = shortcode_atts( array(
			'post_type'   => 'product',
			'per_slide'   => 3,
			'per_page'    => 12,
			'autoplay'    => 'yes',
			'interval'    => 5000,
			'category'    => '',
			'ids'         => '',
			'show_link'   => 'yes',
			'show_fields' => '',
			'image_size'  => 'medium',
			'order'       => 'DESC',
			'orderby'     => 'date',
		), $atts, 'dw_catalog_carousel' );

		if ( ! DWCAT_Config::is_our_post_type( $atts['post_type'] ) ) {
			return '<p>' . esc_html__( 'Invalid post type.', 'dw-catalog-wp' ) . '</p>';
		}

		wp_enqueue_style( 'dwcat-frontend' );
		wp_enqueue_script( 'dwcat-carousel' );

		$query = $this->query_items( $atts );
		$fields = $this->resolve_display_fields( $atts['post_type'], $atts['show_fields'] );
		$per_slide = max( 1, min( 6, (int) $atts['per_slide'] ) );
		$show_link = in_array( strtolower( $atts['show_link'] ), array( 'yes', '1', 'true' ), true );
		$autoplay = in_array( strtolower( $atts['autoplay'] ), array( 'yes', '1', 'true' ), true ) ? '1' : '0';
		$interval = max( 1000, (int) $atts['interval'] );

		if ( ! $query->have_posts() ) {
			return '<p class="dwcat-empty">' . esc_html__( 'No items found.', 'dw-catalog-wp' ) . '</p>';
		}

		$instance_id = 'dwcat-carousel-' . wp_unique_id();

		ob_start();
		?>
		<div class="dwcat-carousel" id="<?php echo esc_attr( $instance_id ); ?>"
			 data-autoplay="<?php echo esc_attr( $autoplay ); ?>"
			 data-interval="<?php echo esc_attr( $interval ); ?>"
			 data-per-slide="<?php echo esc_attr( $per_slide ); ?>"
			 style="--dwcat-cols: <?php echo (int) $per_slide; ?>;">
			<button type="button" class="dwcat-carousel-btn dwcat-prev" aria-label="Previous">&#10094;</button>
			<div class="dwcat-carousel-viewport">
				<div class="dwcat-carousel-track">
					<?php while ( $query->have_posts() ) : $query->the_post(); $post_id = get_the_ID(); ?>
						<div class="dwcat-carousel-slide">
							<div class="dwcat-card">
								<?php $this->render_card_content( $post_id, $fields, $show_link, $atts['image_size'] ); ?>
							</div>
						</div>
					<?php endwhile; ?>
				</div>
			</div>
			<button type="button" class="dwcat-carousel-btn dwcat-next" aria-label="Next">&#10095;</button>
		</div>
		<?php
		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * Shared card rendering (used by grid + carousel).
	 */
	private function render_card_content( $post_id, $fields, $show_link, $image_size ) {
		$permalink = get_permalink( $post_id );
		$wrap_open  = $show_link ? '<a href="' . esc_url( $permalink ) . '" class="dwcat-card-link">' : '<div class="dwcat-card-link">';
		$wrap_close = $show_link ? '</a>' : '</div>';

		echo $wrap_open; // phpcs:ignore

		// Image
		echo '<div class="dwcat-card-image">';
		if ( has_post_thumbnail( $post_id ) ) {
			echo get_the_post_thumbnail( $post_id, $image_size, array( 'loading' => 'lazy' ) );
		} else {
			echo '<div class="dwcat-no-image">—</div>';
		}
		echo '</div>';

		// Title
		$title_field = DWCAT_Config::get_title_field( get_post_type( $post_id ) );
		$display_title = get_the_title( $post_id );
		if ( $title_field ) {
			$meta_title = get_post_meta( $post_id, $title_field['meta_key'], true );
			if ( ! empty( $meta_title ) ) {
				$display_title = $meta_title;
			}
		}
		echo '<h3 class="dwcat-card-title">' . esc_html( $display_title ) . '</h3>';

		// Custom fields
		if ( ! empty( $fields ) ) {
			echo '<ul class="dwcat-card-fields">';
			foreach ( $fields as $field ) {
				$val = $this->get_field_value( $post_id, $field );
				if ( $val === '' ) continue;
				echo '<li><span class="dwcat-field-label">' . esc_html( $field['label'] ) . ':</span> <span class="dwcat-field-value">' . esc_html( $val ) . '</span></li>';
			}
			echo '</ul>';
		}

		echo $wrap_close; // phpcs:ignore
	}

	/**
	 * [dw_catalog_magazine]
	 * Magazine-style detail view with overlay.
	 */
	public function shortcode_magazine( $atts ) {
		$atts = shortcode_atts( array(
			'post_id'     => 0,
			'position'    => 'bottom-right', // top-left, top-right, bottom-left, bottom-right, middle, center
			'show_fields' => '',
			'show_title'  => 'yes',
			'height'      => '600',
			'overlay'     => 'dark', // dark, light, none
		), $atts, 'dw_catalog_magazine' );

		$post_id = (int) $atts['post_id'];
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}
		if ( ! $post_id ) {
			return '<p class="dwcat-empty">' . esc_html__( 'No post specified.', 'dw-catalog-wp' ) . '</p>';
		}

		$post = get_post( $post_id );
		if ( ! $post || ! DWCAT_Config::is_our_post_type( $post->post_type ) ) {
			return '<p class="dwcat-empty">' . esc_html__( 'Invalid post.', 'dw-catalog-wp' ) . '</p>';
		}

		wp_enqueue_style( 'dwcat-frontend' );

		$fields = $this->resolve_display_fields( $post->post_type, $atts['show_fields'] );
		$show_title = in_array( strtolower( $atts['show_title'] ), array( 'yes', '1', 'true' ), true );
		$position = in_array( $atts['position'], array( 'top-left', 'top-right', 'bottom-left', 'bottom-right', 'middle', 'center' ), true )
			? $atts['position'] : 'bottom-right';
		$overlay_class = in_array( $atts['overlay'], array( 'dark', 'light', 'none' ), true ) ? $atts['overlay'] : 'dark';
		$height = max( 200, (int) $atts['height'] );

		$title_field = DWCAT_Config::get_title_field( $post->post_type );
		$display_title = $post->post_title;
		if ( $title_field ) {
			$meta_title = get_post_meta( $post_id, $title_field['meta_key'], true );
			if ( ! empty( $meta_title ) ) {
				$display_title = $meta_title;
			}
		}

		$image_url = get_the_post_thumbnail_url( $post_id, 'large' );
		if ( ! $image_url ) {
			$image_url = get_the_post_thumbnail_url( $post_id, 'full' );
		}

		ob_start();
		?>
		<div class="dwcat-magazine dwcat-pos-<?php echo esc_attr( $position ); ?> dwcat-overlay-<?php echo esc_attr( $overlay_class ); ?>"
			 style="height: <?php echo (int) $height; ?>px; <?php echo $image_url ? 'background-image: url(' . esc_url( $image_url ) . ');' : ''; ?>">
			<?php if ( ! $image_url ) : ?>
				<div class="dwcat-magazine-no-image">—</div>
			<?php endif; ?>

			<div class="dwcat-magazine-content">
				<?php if ( $show_title ) : ?>
					<h2 class="dwcat-magazine-title"><?php echo esc_html( $display_title ); ?></h2>
				<?php endif; ?>

				<?php if ( ! empty( $fields ) ) : ?>
					<dl class="dwcat-magazine-fields">
						<?php foreach ( $fields as $field ) : ?>
							<?php $val = $this->get_field_value( $post_id, $field ); if ( $val === '' ) continue; ?>
							<dt><?php echo esc_html( $field['label'] ); ?></dt>
							<dd><?php echo esc_html( $val ); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
