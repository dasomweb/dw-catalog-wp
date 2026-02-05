<?php
/**
 * PDF Export Class
 *
 * Exports products by category to US Letter PDF with card layout.
 * Domain-agnostic: no hardcoded URLs; uses attachment IDs for images.
 *
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_PDF_Export Class
 */
class PC_PDF_Export {

	const POST_TYPE = 'product';
	const TAXONOMY  = 'product_category';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_pc_pdf_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'pc-products',
			__( 'PDF Export', 'dw-product-catalog' ),
			__( 'PDF Export', 'dw-product-catalog' ),
			'edit_posts',
			'pc-pdf-export',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Get exportable fields (meta_key => default label)
	 *
	 * @return array
	 */
	public static function get_exportable_fields() {
		return array(
			'post_title'           => __( 'Product Name', 'dw-product-catalog' ),
			'dw_pc_item_code'      => __( 'Item Code', 'dw-product-catalog' ),
			'dw_pc_pack_size_raw'  => __( 'Pack Size / Case Pack', 'dw-product-catalog' ),
			'dw_pc_brand_raw'      => __( 'Brand', 'dw-product-catalog' ),
			'dw_pc_origin_raw'     => __( 'Origin', 'dw-product-catalog' ),
			'dw_pc_status'         => __( 'Status', 'dw-product-catalog' ),
			'product_category'    => __( 'Category', 'dw-product-catalog' ),
			'dw_pc_internal_note'  => __( 'ETC', 'dw-product-catalog' ),
		);
	}

	/**
	 * Render PDF Export settings page
	 */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'dw-product-catalog' ) );
		}

		$categories = get_terms( array(
			'taxonomy'   => self::TAXONOMY,
			'hide_empty' => false,
		) );
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}

		$fields = self::get_exportable_fields();
		$form_url = admin_url( 'admin-post.php?action=pc_pdf_export' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PDF Export', 'dw-product-catalog' ); ?></h1>
			<p><?php esc_html_e( 'Export products by category to a US Letter PDF with card layout. Choose categories and which fields to include.', 'dw-product-catalog' ); ?></p>

			<?php
			$autoload = pc_get_plugin_path() . 'vendor/autoload.php';
			if ( ! file_exists( $autoload ) ) {
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'PDF export requires Composer dependencies. Run "composer install" in the plugin directory, or use a release ZIP that includes them.', 'dw-product-catalog' );
				echo '</p></div>';
			}
			?>

			<form method="post" action="<?php echo esc_url( $form_url ); ?>" id="pc-pdf-export-form" style="max-width: 720px;">
				<?php wp_nonce_field( 'pc_pdf_export', 'pc_pdf_export_nonce' ); ?>

				<h2 class="title"><?php esc_html_e( 'Categories', 'dw-product-catalog' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Select one or more categories. Products in these categories will be included.', 'dw-product-catalog' ); ?></p>
				<div style="max-height: 200px; overflow-y: auto; border: 1px solid #c3c4c7; padding: 10px; background: #fff; margin-bottom: 20px;">
					<?php if ( empty( $categories ) ) : ?>
						<p><?php esc_html_e( 'No categories found.', 'dw-product-catalog' ); ?></p>
					<?php else : ?>
						<?php foreach ( $categories as $term ) : ?>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="pc_pdf_categories[]" value="<?php echo esc_attr( $term->term_id ); ?>">
								<?php echo esc_html( $term->name ); ?>
								<?php if ( $term->slug ) : ?>
									<code style="font-size: 11px;">(<?php echo esc_html( $term->slug ); ?>)</code>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>

				<h2 class="title"><?php esc_html_e( 'Include featured image', 'dw-product-catalog' ); ?></h2>
				<label>
					<input type="checkbox" name="pc_pdf_include_image" value="1" checked>
					<?php esc_html_e( 'Show product image in each card', 'dw-product-catalog' ); ?>
				</label>

				<h2 class="title" style="margin-top: 24px;"><?php esc_html_e( 'Grid layout', 'dw-product-catalog' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Number of cards per row in the PDF.', 'dw-product-catalog' ); ?></p>
				<p>
					<label for="pc_pdf_per_row">
						<?php esc_html_e( 'Cards per row:', 'dw-product-catalog' ); ?>
					</label>
					<select name="pc_pdf_per_row" id="pc_pdf_per_row">
						<option value="1">1</option>
						<option value="2" selected>2</option>
						<option value="3">3</option>
						<option value="4">4</option>
					</select>
				</p>

				<h2 class="title" style="margin-top: 24px;"><?php esc_html_e( 'Fields to include', 'dw-product-catalog' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Select fields and optionally set a custom label for the PDF.', 'dw-product-catalog' ); ?></p>
				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $fields as $meta_key => $default_label ) : ?>
							<tr>
								<td style="width: 30px; vertical-align: middle;">
									<?php
									$default_checked = in_array( $meta_key, array( 'post_title', 'dw_pc_item_code', 'dw_pc_brand_raw', 'product_category' ), true );
									?>
									<input type="checkbox" name="pc_pdf_fields[]" value="<?php echo esc_attr( $meta_key ); ?>" id="pc_pdf_field_<?php echo esc_attr( sanitize_title( $meta_key ) ); ?>" <?php echo $default_checked ? ' checked' : ''; ?>>
								</td>
								<td style="width: 200px;">
									<label for="pc_pdf_field_<?php echo esc_attr( sanitize_title( $meta_key ) ); ?>"><?php echo esc_html( $default_label ); ?></label>
								</td>
								<td>
									<input type="text" name="pc_pdf_field_label[<?php echo esc_attr( $meta_key ); ?>]" value="<?php echo esc_attr( $default_label ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $default_label ); ?>">
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p class="submit">
					<button type="submit" name="pc_pdf_generate" class="button button-primary"><?php esc_html_e( 'Generate PDF', 'dw-product-catalog' ); ?></button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle PDF export request
	 */
	public function handle_export() {
		if ( ! isset( $_POST['pc_pdf_export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_pdf_export_nonce'] ) ), 'pc_pdf_export' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'dw-product-catalog' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to export.', 'dw-product-catalog' ) );
		}

		$autoload = pc_get_plugin_path() . 'vendor/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			wp_die( esc_html__( 'PDF library not found. Run composer install in the plugin directory.', 'dw-product-catalog' ) );
		}

		$category_ids = isset( $_POST['pc_pdf_categories'] ) && is_array( $_POST['pc_pdf_categories'] ) ? array_map( 'intval', $_POST['pc_pdf_categories'] ) : array();
		$category_ids = array_filter( $category_ids );
		if ( empty( $category_ids ) ) {
			wp_die( esc_html__( 'Please select at least one category.', 'dw-product-catalog' ) );
		}

		$include_image = ! empty( $_POST['pc_pdf_include_image'] );
		$per_row = isset( $_POST['pc_pdf_per_row'] ) ? max( 1, min( 4, (int) $_POST['pc_pdf_per_row'] ) ) : 2;
		$selected_fields = isset( $_POST['pc_pdf_fields'] ) && is_array( $_POST['pc_pdf_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['pc_pdf_fields'] ) ) : array();
		if ( empty( $selected_fields ) && ! $include_image ) {
			wp_die( esc_html__( 'Please select at least one field or include the product image.', 'dw-product-catalog' ) );
		}
		$field_labels = isset( $_POST['pc_pdf_field_label'] ) && is_array( $_POST['pc_pdf_field_label'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['pc_pdf_field_label'] ) ) : array();
		$all_fields = self::get_exportable_fields();
		$labels = array();
		foreach ( $all_fields as $key => $default ) {
			$labels[ $key ] = isset( $field_labels[ $key ] ) && $field_labels[ $key ] !== '' ? $field_labels[ $key ] : $default;
		}

		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
			'tax_query'      => array(
				array(
					'taxonomy' => self::TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $category_ids,
				),
			),
		);
		$query = new WP_Query( $args );
		$products = $query->posts;
		wp_reset_postdata();

		if ( empty( $products ) ) {
			wp_die( esc_html__( 'No products found in the selected categories.', 'dw-product-catalog' ) );
		}

		require_once $autoload;
		$html = $this->build_pdf_html( $products, $selected_fields, $labels, $include_image, $per_row );
		$this->output_pdf( $html );
		exit;
	}

	/**
	 * Build HTML for PDF (card layout, US Letter)
	 *
	 * @param WP_Post[] $products
	 * @param string[]  $selected_fields
	 * @param string[]  $labels
	 * @param bool      $include_image
	 * @param int       $per_row Number of cards per row (1–4).
	 * @return string
	 */
	protected function build_pdf_html( $products, $selected_fields, $labels, $include_image, $per_row = 2 ) {
		$per_row = max( 1, min( 4, (int) $per_row ) );
		$rows = array();
		$cells = array();
		$count = 0;

		foreach ( $products as $post ) {
			$post_id = $post->ID;
			$cell = '<div class="pc-pdf-card">';

			if ( $include_image ) {
				$thumb_id = (int) get_post_thumbnail_id( $post_id );
				if ( $thumb_id ) {
					$file = get_attached_file( $thumb_id );
					if ( $file && file_exists( $file ) ) {
						$cell .= '<div class="pc-pdf-card-img"><img src="' . esc_url( $this->get_local_image_url_for_dompdf( $file ) ) . '" alt="" /></div>';
					} else {
						$url = wp_get_attachment_image_url( $thumb_id, 'medium' );
						if ( $url ) {
							$cell .= '<div class="pc-pdf-card-img"><img src="' . esc_url( $url ) . '" alt="" /></div>';
						}
					}
				} else {
					$cell .= '<div class="pc-pdf-card-img pc-pdf-no-img">—</div>';
				}
			}

			$cell .= '<div class="pc-pdf-card-fields">';
			foreach ( $selected_fields as $key ) {
				$label = isset( $labels[ $key ] ) ? $labels[ $key ] : $key;
				$value = $this->get_product_field_value( $post_id, $key, $post );
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
		$card_width = ( 100 - ( $per_row - 1 ) * $gap ) / $per_row;
		$nth_clear = $per_row;
		$css = '
			body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; margin: 0; padding: 16px; }
			.pc-pdf-row { clear: both; overflow: hidden; margin-bottom: 16px; }
			.pc-pdf-card { float: left; width: ' . ( (float) $card_width ) . '%; margin-right: ' . ( (float) $gap ) . '%; border: 1px solid #ccc; padding: 10px; box-sizing: border-box; min-height: 180px; }
			.pc-pdf-card:nth-child(' . ( (int) $nth_clear ) . 'n) { margin-right: 0; }
			.pc-pdf-card-img { width: 120px; height: 120px; float: left; margin-right: 12px; text-align: center; line-height: 120px; background: #f5f5f5; font-size: 14pt; color: #999; }
			.pc-pdf-card-img img { max-width: 120px; max-height: 120px; vertical-align: middle; }
			.pc-pdf-card-fields { overflow: hidden; }
			.pc-pdf-field { margin-bottom: 4px; }
		';

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>' . implode( '', $rows ) . '</body></html>';
	}

	/**
	 * Get value for a product field
	 *
	 * @param int      $post_id
	 * @param string   $key
	 * @param WP_Post  $post
	 * @return string
	 */
	protected function get_product_field_value( $post_id, $key, $post ) {
		if ( $key === 'post_title' ) {
			return $post->post_title;
		}
		if ( $key === 'product_category' ) {
			$terms = get_the_terms( $post_id, self::TAXONOMY );
			if ( ! $terms || is_wp_error( $terms ) ) {
				return '';
			}
			return implode( ', ', wp_list_pluck( $terms, 'name' ) );
		}
		$value = get_post_meta( $post_id, $key, true );
		if ( $key === 'dw_pc_status' ) {
			$status_labels = array(
				'active'       => __( 'Active', 'dw-product-catalog' ),
				'inactive'     => __( 'Inactive', 'dw-product-catalog' ),
				'out_of_stock' => __( 'Out of Stock', 'dw-product-catalog' ),
				'discontinued' => __( 'Discontinued', 'dw-product-catalog' ),
			);
			$value = isset( $status_labels[ $value ] ) ? $status_labels[ $value ] : $value;
		}
		return is_string( $value ) ? $value : '';
	}

	/**
	 * Return file URL for Dompdf (file:// so Dompdf can load local image)
	 *
	 * @param string $file_path Absolute path to image file.
	 * @return string
	 */
	protected function get_local_image_url_for_dompdf( $file_path ) {
		$path = str_replace( '\\', '/', $file_path );
		if ( substr( $path, 1, 1 ) === ':' ) {
			$path = '/' . $path;
		}
		return 'file://' . $path;
	}

	/**
	 * Output PDF using Dompdf (US Letter)
	 *
	 * @param string $html
	 */
	protected function output_pdf( $html ) {
		$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => true ) );
		$dompdf->setPaper( 'letter', 'portrait' );
		$dompdf->loadHtml( $html );
		$dompdf->render();
		$filename = 'product-catalog-' . gmdate( 'Y-m-d-His' ) . '.pdf';
		$dompdf->stream( $filename, array( 'Attachment' => true ) );
	}
}
