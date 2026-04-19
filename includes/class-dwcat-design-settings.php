<?php
/**
 * Design Settings — Admin UI for shortcode typography/color/layout customization.
 *
 * Pattern: admin saves design → wp_options → shortcode renderer injects CSS variables.
 * Based on: Smart Branch Locator Pro — Shortcode Design Settings Pattern (DW-MCP).
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Design_Settings {

	const OPTION_KEY = 'dwcat_design_settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_dwcat_save_design', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'dw-catalog-settings',
			__( 'Shortcode Design', 'dw-catalog-wp' ),
			__( 'Shortcode Design', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-design',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_scripts( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( $page !== 'dw-catalog-design' ) {
			return;
		}
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );
	}

	/**
	 * Default design settings.
	 */
	public static function get_defaults() {
		return array(
			// Common typography
			'font_family'      => '',
			'title_size'       => 16,
			'title_weight'     => 600,
			'title_color'      => '#1f2937',
			'field_label_size' => 12,
			'field_label_color' => '#9ca3af',
			'field_value_size' => 13,
			'field_value_color' => '#1f2937',

			// Card
			'card_bg'          => '#ffffff',
			'card_border_color' => '#e5e7eb',
			'card_border_width' => 1,
			'card_radius'      => 8,
			'card_shadow'      => 'sm', // none, sm, md, lg
			'card_padding'     => 0,

			// Grid
			'grid_gap'         => 20,

			// Carousel
			'carousel_btn_bg'  => '#ffffff',
			'carousel_btn_color' => '#374151',
			'carousel_btn_hover_bg' => '#1f2937',
			'carousel_btn_hover_color' => '#ffffff',

			// Magazine
			'mag_title_size'   => 28,
			'mag_title_weight' => 700,
			'mag_title_color'  => '#ffffff',
			'mag_field_label_size' => 14,
			'mag_field_label_color' => 'rgba(255,255,255,0.7)',
			'mag_field_value_size' => 14,
			'mag_field_value_color' => '#ffffff',
			'mag_overlay_bg'   => 'rgba(0,0,0,0.7)',
			'mag_overlay_light_bg' => 'rgba(255,255,255,0.9)',
			'mag_overlay_light_color' => '#1f2937',
			'mag_content_radius' => 6,
			'mag_content_padding' => 24,
		);
	}

	public static function get_settings() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_defaults() );
	}

	/**
	 * Convert settings array to CSS custom properties.
	 *
	 * @return array Map of --dwcat-* => value
	 */
	public static function get_css_vars() {
		$s = self::get_settings();

		$shadow_map = array(
			'none' => 'none',
			'sm'   => '0 1px 3px rgba(0,0,0,0.05)',
			'md'   => '0 4px 12px rgba(0,0,0,0.08)',
			'lg'   => '0 8px 24px rgba(0,0,0,0.12)',
		);
		$shadow = isset( $shadow_map[ $s['card_shadow'] ] ) ? $shadow_map[ $s['card_shadow'] ] : $shadow_map['sm'];

		$vars = array(
			// Common
			'--dwcat-font-family'       => $s['font_family'] ? "'" . $s['font_family'] . "', inherit" : 'inherit',
			'--dwcat-title-size'        => $s['title_size'] . 'px',
			'--dwcat-title-weight'      => $s['title_weight'],
			'--dwcat-title-color'       => $s['title_color'],
			'--dwcat-field-label-size'  => $s['field_label_size'] . 'px',
			'--dwcat-field-label-color' => $s['field_label_color'],
			'--dwcat-field-value-size'  => $s['field_value_size'] . 'px',
			'--dwcat-field-value-color' => $s['field_value_color'],

			// Card
			'--dwcat-card-bg'           => $s['card_bg'],
			'--dwcat-card-border-color' => $s['card_border_color'],
			'--dwcat-card-border-width' => $s['card_border_width'] . 'px',
			'--dwcat-card-radius'       => $s['card_radius'] . 'px',
			'--dwcat-card-shadow'       => $shadow,
			'--dwcat-card-padding'      => $s['card_padding'] . 'px',

			// Grid
			'--dwcat-grid-gap'          => $s['grid_gap'] . 'px',

			// Carousel
			'--dwcat-carousel-btn-bg'       => $s['carousel_btn_bg'],
			'--dwcat-carousel-btn-color'    => $s['carousel_btn_color'],
			'--dwcat-carousel-btn-hover-bg' => $s['carousel_btn_hover_bg'],
			'--dwcat-carousel-btn-hover-color' => $s['carousel_btn_hover_color'],

			// Magazine
			'--dwcat-mag-title-size'        => $s['mag_title_size'] . 'px',
			'--dwcat-mag-title-weight'      => $s['mag_title_weight'],
			'--dwcat-mag-title-color'       => $s['mag_title_color'],
			'--dwcat-mag-field-label-size'  => $s['mag_field_label_size'] . 'px',
			'--dwcat-mag-field-label-color' => $s['mag_field_label_color'],
			'--dwcat-mag-field-value-size'  => $s['mag_field_value_size'] . 'px',
			'--dwcat-mag-field-value-color' => $s['mag_field_value_color'],
			'--dwcat-mag-overlay-bg'        => $s['mag_overlay_bg'],
			'--dwcat-mag-overlay-light-bg'  => $s['mag_overlay_light_bg'],
			'--dwcat-mag-overlay-light-color' => $s['mag_overlay_light_color'],
			'--dwcat-mag-content-radius'    => $s['mag_content_radius'] . 'px',
			'--dwcat-mag-content-padding'   => $s['mag_content_padding'] . 'px',
		);

		return $vars;
	}

	/**
	 * Get inline style="--var: value; ..." string for root element injection.
	 */
	public static function get_inline_style() {
		$vars = self::get_css_vars();
		$css = '';
		foreach ( $vars as $k => $v ) {
			$css .= $k . ':' . $v . ';';
		}
		return $css;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$s = self::get_settings();
		$saved = isset( $_GET['saved'] ) ? intval( $_GET['saved'] ) : 0;
		?>
		<div class="wrap">
			<h1><?php _e( 'Shortcode Design', 'dw-catalog-wp' ); ?></h1>
			<p class="description"><?php _e( 'Customize the typography, colors, and layout of the frontend shortcodes. Changes apply instantly to all shortcode instances.', 'dw-catalog-wp' ); ?></p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php _e( 'Design settings saved.', 'dw-catalog-wp' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'dwcat_save_design', 'dwcat_design_nonce' ); ?>
				<input type="hidden" name="action" value="dwcat_save_design">

				<h2 class="title"><?php _e( 'Common', 'dw-catalog-wp' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label><?php _e( 'Font Family', 'dw-catalog-wp' ); ?></label></th>
						<td>
							<input type="text" name="font_family" value="<?php echo esc_attr( $s['font_family'] ); ?>" class="regular-text" placeholder="e.g. Poppins, Inter, Pretendard">
							<p class="description"><?php _e( 'Leave empty to inherit from theme. Make sure the font is loaded in your theme/head.', 'dw-catalog-wp' ); ?></p>
						</td>
					</tr>
				</table>

				<h2 class="title"><?php _e( 'Grid & Carousel — Card', 'dw-catalog-wp' ); ?></h2>
				<table class="form-table">
					<?php $this->render_number_row( 'title_size', $s, __( 'Title Font Size (px)', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_weight_row( 'title_weight', $s, __( 'Title Weight', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'title_color', $s, __( 'Title Color', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_number_row( 'field_label_size', $s, __( 'Field Label Size (px)', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'field_label_color', $s, __( 'Field Label Color', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_number_row( 'field_value_size', $s, __( 'Field Value Size (px)', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'field_value_color', $s, __( 'Field Value Color', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'card_bg', $s, __( 'Card Background', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'card_border_color', $s, __( 'Card Border Color', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_number_row( 'card_border_width', $s, __( 'Card Border Width (px)', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_number_row( 'card_radius', $s, __( 'Card Radius (px)', 'dw-catalog-wp' ) ); ?>
					<tr>
						<th><label><?php _e( 'Card Shadow', 'dw-catalog-wp' ); ?></label></th>
						<td>
							<select name="card_shadow">
								<?php foreach ( array( 'none', 'sm', 'md', 'lg' ) as $opt ) : ?>
									<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $s['card_shadow'], $opt ); ?>><?php echo esc_html( strtoupper( $opt ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<?php $this->render_number_row( 'grid_gap', $s, __( 'Grid Gap (px)', 'dw-catalog-wp' ) ); ?>
				</table>

				<h2 class="title"><?php _e( 'Carousel — Navigation Buttons', 'dw-catalog-wp' ); ?></h2>
				<table class="form-table">
					<?php $this->render_color_row( 'carousel_btn_bg', $s, __( 'Button Background', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'carousel_btn_color', $s, __( 'Button Icon Color', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'carousel_btn_hover_bg', $s, __( 'Button Hover Background', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'carousel_btn_hover_color', $s, __( 'Button Hover Icon Color', 'dw-catalog-wp' ) ); ?>
				</table>

				<h2 class="title"><?php _e( 'Magazine (Detail View)', 'dw-catalog-wp' ); ?></h2>
				<table class="form-table">
					<?php $this->render_number_row( 'mag_title_size', $s, __( 'Title Size (px)', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_weight_row( 'mag_title_weight', $s, __( 'Title Weight', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'mag_title_color', $s, __( 'Title Color', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_number_row( 'mag_field_label_size', $s, __( 'Field Label Size (px)', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_text_row( 'mag_field_label_color', $s, __( 'Field Label Color', 'dw-catalog-wp' ), 'e.g. rgba(255,255,255,0.7) or #fff' ); ?>
					<?php $this->render_number_row( 'mag_field_value_size', $s, __( 'Field Value Size (px)', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_color_row( 'mag_field_value_color', $s, __( 'Field Value Color', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_text_row( 'mag_overlay_bg', $s, __( 'Dark Overlay BG', 'dw-catalog-wp' ), 'e.g. rgba(0,0,0,0.7)' ); ?>
					<?php $this->render_text_row( 'mag_overlay_light_bg', $s, __( 'Light Overlay BG', 'dw-catalog-wp' ), 'e.g. rgba(255,255,255,0.9)' ); ?>
					<?php $this->render_color_row( 'mag_overlay_light_color', $s, __( 'Light Overlay Text Color', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_number_row( 'mag_content_radius', $s, __( 'Overlay Box Radius (px)', 'dw-catalog-wp' ) ); ?>
					<?php $this->render_number_row( 'mag_content_padding', $s, __( 'Overlay Box Padding (px)', 'dw-catalog-wp' ) ); ?>
				</table>

				<?php submit_button( __( 'Save Design Settings', 'dw-catalog-wp' ) ); ?>
			</form>

			<script>
			jQuery(function($){
				$('.dwcat-color-field').wpColorPicker();
			});
			</script>
		</div>
		<?php
	}

	private function render_number_row( $key, $s, $label ) {
		?>
		<tr>
			<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td><input type="number" id="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="small-text" step="1" min="0"></td>
		</tr>
		<?php
	}

	private function render_weight_row( $key, $s, $label ) {
		?>
		<tr>
			<th><label><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select name="<?php echo esc_attr( $key ); ?>">
					<?php foreach ( array( 300, 400, 500, 600, 700, 800 ) as $w ) : ?>
						<option value="<?php echo (int) $w; ?>" <?php selected( (int) $s[ $key ], $w ); ?>><?php echo (int) $w; ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<?php
	}

	private function render_color_row( $key, $s, $label ) {
		?>
		<tr>
			<th><label><?php echo esc_html( $label ); ?></label></th>
			<td><input type="text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="dwcat-color-field" data-default-color="<?php echo esc_attr( $s[ $key ] ); ?>"></td>
		</tr>
		<?php
	}

	private function render_text_row( $key, $s, $label, $placeholder = '' ) {
		?>
		<tr>
			<th><label><?php echo esc_html( $label ); ?></label></th>
			<td><input type="text" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $placeholder ); ?>"></td>
		</tr>
		<?php
	}

	public function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		check_admin_referer( 'dwcat_save_design', 'dwcat_design_nonce' );

		$defaults = self::get_defaults();
		$out = array();
		foreach ( $defaults as $key => $default ) {
			if ( ! isset( $_POST[ $key ] ) ) {
				$out[ $key ] = $default;
				continue;
			}
			$val = wp_unslash( $_POST[ $key ] );
			if ( is_int( $default ) ) {
				$out[ $key ] = (int) $val;
			} else {
				$out[ $key ] = sanitize_text_field( $val );
			}
		}

		update_option( self::OPTION_KEY, $out );

		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-design&saved=1' ) );
		exit;
	}
}
