<?php
/**
 * PDF Export Class
 *
 * Exports items by category to US Letter PDF with card layout.
 * Works dynamically with any registered post type.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_PDF_Export {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
		add_action( 'admin_post_dw_catalog_pdf_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Add PDF Export submenu to each post type menu.
	 */
	public function add_admin_menus() {
		$post_types = DWCAT_Config::get_post_types();
		foreach ( $post_types as $slug => $config ) {
			if ( empty( $config['has_category'] ) ) {
				continue; // PDF export requires categories
			}
			$parent = 'dw-catalog-' . $slug;
			add_submenu_page(
				$parent,
				__( 'PDF Export', 'dw-catalog-wp' ),
				__( 'PDF Export', 'dw-catalog-wp' ),
				'edit_posts',
				$parent . '-pdf',
				array( $this, 'render_page' )
			);
		}
	}

	private function get_post_type_from_page() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( preg_match( '/^dw-catalog-(.+)-pdf$/', $page, $m ) ) {
			return $m[1];
		}
		return '';
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$pt_slug = $this->get_post_type_from_page();
		$pt_config = DWCAT_Config::get_post_type( $pt_slug );
		if ( ! $pt_config ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Post type not found.', 'dw-catalog-wp' ) . '</p></div>';
			return;
		}

		$cat_tax = DWCAT_Config::get_category_taxonomy( $pt_slug );
		$categories = get_terms( array( 'taxonomy' => $cat_tax, 'hide_empty' => false ) );
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		$export_fields = DWCAT_Config::get_export_fields( $pt_slug );
		// Add post_title and category as export options
		$field_options = array(
			'post_title' => __( 'Title', 'dw-catalog-wp' ),
		);
		foreach ( $export_fields as $f ) {
			$field_options[ $f['meta_key'] ] = $f['label'];
		}
		$field_options[ $cat_tax ] = __( 'Category', 'dw-catalog-wp' );

		$form_url = admin_url( 'admin-post.php?action=dw_catalog_pdf_export' );
		?>
		<div class="wrap">
			<h1><?php printf( __( 'PDF Export — %s', 'dw-catalog-wp' ), esc_html( $pt_config['plural_name'] ) ); ?></h1>

			<?php
			$autoload = dwcat_get_path() . 'vendor/autoload.php';
			if ( ! file_exists( $autoload ) ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'PDF export requires Composer dependencies. Run "composer install" in the plugin directory.', 'dw-catalog-wp' ) . '</p></div>';
			}
			?>

			<form method="post" action="<?php echo esc_url( $form_url ); ?>" style="max-width:720px;">
				<?php wp_nonce_field( 'dw_catalog_pdf', 'dw_catalog_pdf_nonce' ); ?>
				<input type="hidden" name="post_type_slug" value="<?php echo esc_attr( $pt_slug ); ?>">

				<h2 class="title"><?php _e( 'Categories', 'dw-catalog-wp' ); ?></h2>
				<div style="max-height:200px; overflow-y:auto; border:1px solid #c3c4c7; padding:10px; background:#fff; margin-bottom:20px;">
					<?php if ( empty( $categories ) ) : ?>
						<p><?php _e( 'No categories found.', 'dw-catalog-wp' ); ?></p>
					<?php else : ?>
						<?php foreach ( $categories as $term ) : ?>
							<label style="display:block; margin-bottom:6px;">
								<input type="checkbox" name="pdf_categories[]" value="<?php echo esc_attr( $term->term_id ); ?>">
								<?php echo esc_html( $term->name ); ?>
								<?php if ( $term->slug ) : ?>
									<code style="font-size:11px;">(<?php echo esc_html( $term->slug ); ?>)</code>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<h2 class="title"><?php _e( 'Include featured image', 'dw-catalog-wp' ); ?></h2>
				<label>
					<input type="checkbox" name="pdf_include_image" value="1" checked>
					<?php _e( 'Show image in each card', 'dw-catalog-wp' ); ?>
				</label>

				<h2 class="title" style="margin-top:24px;"><?php _e( 'Cards per row', 'dw-catalog-wp' ); ?></h2>
				<select name="pdf_per_row">
					<option value="1">1</option>
					<option value="2" selected>2</option>
					<option value="3">3</option>
					<option value="4">4</option>
				</select>

				<h2 class="title" style="margin-top:24px;"><?php _e( 'Fields to include', 'dw-catalog-wp' ); ?></h2>
				<table class="form-table">
					<?php foreach ( $field_options as $key => $default_label ) : ?>
						<tr>
							<td style="width:30px;"><input type="checkbox" name="pdf_fields[]" value="<?php echo esc_attr( $key ); ?>" checked></td>
							<td style="width:200px;"><?php echo esc_html( $default_label ); ?></td>
							<td><input type="text" name="pdf_label[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $default_label ); ?>" class="regular-text"></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary"><?php _e( 'Generate PDF', 'dw-catalog-wp' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	public function handle_export() {
		if ( ! isset( $_POST['dw_catalog_pdf_nonce'] ) || ! wp_verify_nonce( $_POST['dw_catalog_pdf_nonce'], 'dw_catalog_pdf' ) ) {
			wp_die( __( 'Security check failed.', 'dw-catalog-wp' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$autoload = dwcat_get_path() . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			wp_die( __( 'PDF library not found. Run composer install.', 'dw-catalog-wp' ) );
		}

		$pt_slug = sanitize_key( $_POST['post_type_slug'] );
		$pt_config = DWCAT_Config::get_post_type( $pt_slug );
		if ( ! $pt_config ) {
			wp_die( __( 'Invalid post type.', 'dw-catalog-wp' ) );
		}

		$cat_tax = DWCAT_Config::get_category_taxonomy( $pt_slug );
		$cat_ids = isset( $_POST['pdf_categories'] ) ? array_map( 'intval', $_POST['pdf_categories'] ) : array();
		$cat_ids = array_filter( $cat_ids );
		if ( empty( $cat_ids ) ) {
			wp_die( __( 'Select at least one category.', 'dw-catalog-wp' ) );
		}

		$include_image = ! empty( $_POST['pdf_include_image'] );
		$per_row = isset( $_POST['pdf_per_row'] ) ? max( 1, min( 4, (int) $_POST['pdf_per_row'] ) ) : 2;
		$selected_fields = isset( $_POST['pdf_fields'] ) ? array_map( 'sanitize_text_field', $_POST['pdf_fields'] ) : array();
		$field_labels = isset( $_POST['pdf_label'] ) ? array_map( 'sanitize_text_field', $_POST['pdf_label'] ) : array();

		$query = new WP_Query( array(
			'post_type'      => $pt_slug,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array( array(
				'taxonomy' => $cat_tax,
				'field'    => 'term_id',
				'terms'    => $cat_ids,
			) ),
		) );
		$products = $query->posts;
		wp_reset_postdata();

		if ( empty( $products ) ) {
			wp_die( __( 'No items found.', 'dw-catalog-wp' ) );
		}

		require_once $autoload;
		$html = $this->build_html( $products, $pt_slug, $cat_tax, $selected_fields, $field_labels, $include_image, $per_row );
		$this->output_pdf( $html, $pt_slug );
		exit;
	}

	protected function build_html( $products, $pt_slug, $cat_tax, $fields, $labels, $include_image, $per_row ) {
		$per_row = max( 1, min( 4, (int) $per_row ) );
		$rows = array();
		$cells = array();
		$count = 0;
		$all_fields = DWCAT_Config::get_fields( $pt_slug );

		foreach ( $products as $post ) {
			$pid = $post->ID;
			$cell = '<div class="pc-pdf-card">';

			if ( $include_image ) {
				$thumb_id = (int) get_post_thumbnail_id( $pid );
				if ( $thumb_id ) {
					$file = get_attached_file( $thumb_id );
					if ( $file && file_exists( $file ) ) {
						$cell .= '<div class="pc-pdf-card-img"><img src="' . esc_url( $this->local_url( $file ) ) . '" alt="" /></div>';
					}
				} else {
					$cell .= '<div class="pc-pdf-card-img pc-pdf-no-img">—</div>';
				}
			}

			$cell .= '<div class="pc-pdf-card-fields">';
			foreach ( $fields as $key ) {
				$label = isset( $labels[ $key ] ) && $labels[ $key ] !== '' ? $labels[ $key ] : $key;
				$value = $this->get_field_value( $pid, $key, $post, $pt_slug, $cat_tax, $all_fields );
				if ( $value === '' ) {
					$value = '—';
				}
				$cell .= '<div class="pc-pdf-field"><strong>' . esc_html( $label ) . ':</strong> ' . esc_html( $value ) . '</div>';
			}
			$cell .= '</div></div>';

			$cells[] = $cell;
			$count++;
			if ( $count >= $per_row ) {
				$rows[] = '<div class="pc-pdf-row">' . implode( '', $cells ) . '</div>';
				$cells = array();
				$count = 0;
			}
		}
		if ( ! empty( $cells ) ) {
			$rows[] = '<div class="pc-pdf-row">' . implode( '', $cells ) . '</div>';
		}

		$gap = 2;
		$cw = ( 100 - ( $per_row - 1 ) * $gap ) / $per_row;
		$css = "body{font-family:DejaVu Sans,sans-serif;font-size:10pt;margin:0;padding:16px;}
.pc-pdf-row{clear:both;overflow:hidden;margin-bottom:16px;}
.pc-pdf-card{float:left;width:{$cw}%;margin-right:{$gap}%;border:1px solid #ccc;padding:10px;box-sizing:border-box;min-height:180px;}
.pc-pdf-card:nth-child({$per_row}n){margin-right:0;}
.pc-pdf-card-img{width:120px;height:120px;float:left;margin-right:12px;text-align:center;line-height:120px;background:#f5f5f5;font-size:14pt;color:#999;}
.pc-pdf-card-img img{max-width:120px;max-height:120px;vertical-align:middle;}
.pc-pdf-card-fields{overflow:hidden;}
.pc-pdf-field{margin-bottom:4px;}";

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>' . implode( '', $rows ) . '</body></html>';
	}

	protected function get_field_value( $post_id, $key, $post, $pt_slug, $cat_tax, $all_fields ) {
		if ( $key === 'post_title' ) {
			return $post->post_title;
		}
		if ( $key === $cat_tax ) {
			$terms = get_the_terms( $post_id, $cat_tax );
			return ( $terms && ! is_wp_error( $terms ) ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
		}
		$value = get_post_meta( $post_id, $key, true );
		// Resolve select labels
		foreach ( $all_fields as $f ) {
			if ( $f['meta_key'] === $key && $f['type'] === 'select' && $value !== '' ) {
				$opts = DWCAT_Config::parse_select_options( $f['options'] );
				$value = isset( $opts[ $value ] ) ? $opts[ $value ] : $value;
				break;
			}
		}
		return is_string( $value ) ? $value : '';
	}

	protected function local_url( $path ) {
		$path = str_replace( '\\', '/', $path );
		if ( substr( $path, 1, 1 ) === ':' ) {
			$path = '/' . $path;
		}
		return 'file://' . $path;
	}

	protected function output_pdf( $html, $pt_slug ) {
		$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => true ) );
		$dompdf->setPaper( 'letter', 'portrait' );
		$dompdf->loadHtml( $html );
		$dompdf->render();
		$filename = $pt_slug . '-catalog-' . gmdate( 'Y-m-d-His' ) . '.pdf';
		$dompdf->stream( $filename, array( 'Attachment' => true ) );
	}
}
