<?php
/**
 * Bulk Import Class
 * 
 * Handles Excel/CSV bulk import functionality.
 * Domain-agnostic implementation.
 * 
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_Bulk_Import Class
 * 
 * Manages bulk import functionality.
 */
class PC_Bulk_Import {

	/**
	 * Post type
	 * 
	 * @var string
	 */
	private $post_type = 'product';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_post_pc_import_products', array( $this, 'handle_import' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'pc-products',
			__( 'Bulk Import', 'dw-product-catalog' ),
			__( 'Bulk Import', 'dw-product-catalog' ),
			'edit_posts',
			'pc-bulk-import',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue scripts
	 * 
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_scripts( $hook ) {
		if ( 'product_page_pc-bulk-import' !== $hook ) {
			return;
		}

		$config = pc_get_plugin_config();
		$css_url = PC_URL_Helper::get_css_url( 'admin.css' );
		$css_path = pc_get_plugin_path() . 'assets/css/admin.css';
		
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'pc-admin-style',
				$css_url,
				array(),
				$config['plugin_version']
			);
		}
	}

	/**
	 * Render bulk import page
	 */
	public function render_page() {
		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'dw-product-catalog' ) );
		}

		// Show import results
		if ( isset( $_GET['imported'] ) || isset( $_GET['failed'] ) || isset( $_GET['skipped'] ) ) {
			$imported = isset( $_GET['imported'] ) ? intval( $_GET['imported'] ) : 0;
			$failed = isset( $_GET['failed'] ) ? intval( $_GET['failed'] ) : 0;
			$skipped = isset( $_GET['skipped'] ) ? intval( $_GET['skipped'] ) : 0;
			$errors = isset( $_GET['errors'] ) ? explode( '|', urldecode( $_GET['errors'] ) ) : array();

			echo '<div class="notice notice-info"><p>';
			if ( $imported > 0 ) {
				echo '<strong>' . sprintf( __( '%d products imported successfully.', 'dw-product-catalog' ), $imported ) . '</strong><br>';
			}
			if ( $skipped > 0 ) {
				echo sprintf( __( '%d products skipped (duplicates).', 'dw-product-catalog' ), $skipped ) . '<br>';
			}
			if ( $failed > 0 ) {
				echo '<strong style="color: #d63638;">' . sprintf( __( '%d products failed to import.', 'dw-product-catalog' ), $failed ) . '</strong><br>';
			}
			echo '</p></div>';

			if ( ! empty( $errors ) ) {
				echo '<div class="notice notice-error"><p><strong>' . __( 'Errors:', 'dw-product-catalog' ) . '</strong></p><ul>';
				foreach ( $errors as $error ) {
					if ( ! empty( $error ) ) {
						echo '<li>' . esc_html( $error ) . '</li>';
					}
				}
				echo '</ul></div>';
			}
		}
		?>
		<div class="wrap">
			<h1><?php _e( 'Bulk Import Products', 'dw-product-catalog' ); ?></h1>

			<div class="pc-import-instructions" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<h2><?php _e( 'Import Instructions', 'dw-product-catalog' ); ?></h2>
				<ol>
					<li><?php _e( 'Prepare your Excel file (.xlsx) or CSV file (.csv)', 'dw-product-catalog' ); ?></li>
					<li><?php _e( 'Ensure the first row contains column headers', 'dw-product-catalog' ); ?></li>
					<li><?php _e( 'Required column: <code>post_title</code>', 'dw-product-catalog' ); ?></li>
					<li><?php _e( 'Optional columns:', 'dw-product-catalog' ); ?>
						<ul>
							<li><code>post_content</code> - <?php _e( 'Product description', 'dw-product-catalog' ); ?></li>
							<li><code>post_status</code> - <?php _e( 'publish, draft, or private', 'dw-product-catalog' ); ?></li>
							<li><code>featured_image_url</code> - <?php _e( 'Featured image URL (will be downloaded and set as featured image)', 'dw-product-catalog' ); ?></li>
							<li><code>_pc_product_name</code> - <?php _e( 'Product Name', 'dw-product-catalog' ); ?></li>
							<li><code>_pc_brand</code> - <?php _e( 'Brand', 'dw-product-catalog' ); ?></li>
							<li><code>_pc_item_code</code> - <?php _e( 'Item Code', 'dw-product-catalog' ); ?></li>
							<li><code>_pc_upc</code> - <?php _e( 'UPC', 'dw-product-catalog' ); ?></li>
							<li><code>_pc_allergen</code> - <?php _e( 'Allergen (comma-separated)', 'dw-product-catalog' ); ?></li>
						</ul>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pc-field-reference' ) ); ?>">
							<?php _e( 'View Field Reference', 'dw-product-catalog' ); ?>
						</a>
						<?php _e( 'for detailed field information', 'dw-product-catalog' ); ?>
					</li>
				</ol>

				<h3><?php _e( 'Sample CSV Format', 'dw-product-catalog' ); ?></h3>
				<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;"><code>post_title,post_content,featured_image_url,_pc_product_name,_pc_brand,_pc_item_code,_pc_upc,_pc_allergen
"Product 1","Description 1","https://example.com/image1.jpg","Product Name 1","Brand A","ITEM-001","123456789012","Milk, Eggs"
"Product 2","Description 2","https://example.com/image2.jpg","Product Name 2","Brand B","ITEM-002","123456789013","Nuts"</code></pre>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="pc-import-form">
				<?php wp_nonce_field( 'pc_import_products', 'pc_import_nonce' ); ?>
				<input type="hidden" name="action" value="pc_import_products">

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="import_file"><?php _e( 'Import File', 'dw-product-catalog' ); ?></label>
							</th>
							<td>
								<input 
									type="file" 
									id="import_file" 
									name="import_file" 
									accept=".csv,.xlsx,.xls"
									required
								/>
								<p class="description">
									<?php _e( 'Upload a CSV or Excel file (.csv, .xlsx, .xls)', 'dw-product-catalog' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="default_status"><?php _e( 'Default Status', 'dw-product-catalog' ); ?></label>
							</th>
							<td>
								<select name="default_status" id="default_status">
									<option value="publish"><?php _e( 'Published', 'dw-product-catalog' ); ?></option>
									<option value="draft"><?php _e( 'Draft', 'dw-product-catalog' ); ?></option>
									<option value="private"><?php _e( 'Private', 'dw-product-catalog' ); ?></option>
								</select>
								<p class="description">
									<?php _e( 'Status to use if not specified in the file', 'dw-product-catalog' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="skip_duplicates"><?php _e( 'Skip Duplicates', 'dw-product-catalog' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="skip_duplicates" value="1" checked>
									<?php _e( 'Skip products with duplicate titles', 'dw-product-catalog' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Import Products', 'dw-product-catalog' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle import
	 */
	public function handle_import() {
		// Check nonce
		if ( ! isset( $_POST['pc_import_nonce'] ) || ! wp_verify_nonce( $_POST['pc_import_nonce'], 'pc_import_products' ) ) {
			wp_die( __( 'Security verification failed.', 'dw-product-catalog' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'dw-product-catalog' ) );
		}

		// Check file upload
		if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_die( __( 'File upload failed. Please try again.', 'dw-product-catalog' ) );
		}

		$file = $_FILES['import_file'];
		$default_status = isset( $_POST['default_status'] ) ? sanitize_text_field( $_POST['default_status'] ) : 'publish';
		$skip_duplicates = isset( $_POST['skip_duplicates'] ) && $_POST['skip_duplicates'] === '1';

		// Process file
		$file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		
		if ( $file_ext === 'csv' ) {
			$result = $this->import_csv( $file, $default_status, $skip_duplicates );
		} elseif ( in_array( $file_ext, array( 'xlsx', 'xls' ), true ) ) {
			$result = $this->import_excel( $file, $default_status, $skip_duplicates );
		} else {
			wp_die( __( 'Unsupported file format. Please use CSV or Excel files.', 'dw-product-catalog' ) );
		}

		// Redirect with results
		$redirect_url = add_query_arg(
			array(
				'page'           => 'pc-bulk-import',
				'imported'       => $result['imported'],
				'failed'         => $result['failed'],
				'skipped'        => $result['skipped'],
				'errors'         => urlencode( implode( '|', $result['errors'] ) ),
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Import CSV file
	 * 
	 * @param array  $file File array from $_FILES
	 * @param string $default_status Default post status
	 * @param bool   $skip_duplicates Skip duplicate titles
	 * @return array Result array
	 */
	private function import_csv( $file, $default_status, $skip_duplicates ) {
		$imported = 0;
		$failed = 0;
		$skipped = 0;
		$errors = array();

		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			return array(
				'imported' => 0,
				'failed'   => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'Could not open file.', 'dw-product-catalog' ) ),
			);
		}

		// Read header row
		$headers = fgetcsv( $handle );
		if ( ! $headers || ! in_array( 'post_title', $headers, true ) ) {
			fclose( $handle );
			return array(
				'imported' => 0,
				'failed'   => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'CSV file must contain a "post_title" column.', 'dw-product-catalog' ) ),
			);
		}

		// Process rows
		$row_num = 1;
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$row_num++;
			
			if ( count( $row ) !== count( $headers ) ) {
				$errors[] = sprintf( __( 'Row %d: Column count mismatch', 'dw-product-catalog' ), $row_num );
				$failed++;
				continue;
			}

			$data = array_combine( $headers, $row );
			
			$result = $this->import_product( $data, $default_status, $skip_duplicates );
			
			if ( $result['success'] ) {
				$imported++;
			} elseif ( $result['skipped'] ) {
				$skipped++;
			} else {
				$failed++;
				if ( ! empty( $result['error'] ) ) {
					$errors[] = sprintf( __( 'Row %d: %s', 'dw-product-catalog' ), $row_num, $result['error'] );
				}
			}
		}

		fclose( $handle );

		return array(
			'imported' => $imported,
			'failed'   => $failed,
			'skipped'  => $skipped,
			'errors'   => $errors,
		);
	}

	/**
	 * Import Excel file
	 * 
	 * @param array  $file File array from $_FILES
	 * @param string $default_status Default post status
	 * @param bool   $skip_duplicates Skip duplicate titles
	 * @return array Result array
	 */
	private function import_excel( $file, $default_status, $skip_duplicates ) {
		// For Excel files, we'll convert to CSV first
		// Note: This requires PHPExcel or PhpSpreadsheet library
		// For now, we'll show an error message
		return array(
			'imported' => 0,
			'failed'   => 0,
			'skipped'  => 0,
			'errors'   => array( __( 'Excel import requires additional library. Please convert to CSV format.', 'dw-product-catalog' ) ),
		);
	}

	/**
	 * Import single product
	 * 
	 * @param array  $data Product data
	 * @param string $default_status Default post status
	 * @param bool   $skip_duplicates Skip duplicate titles
	 * @return array Result array
	 */
	private function import_product( $data, $default_status, $skip_duplicates ) {
		// Validate required fields
		if ( empty( $data['post_title'] ) ) {
			return array(
				'success' => false,
				'skipped' => false,
				'error'   => __( 'Title is required', 'dw-product-catalog' ),
			);
		}

		// Check for duplicates
		if ( $skip_duplicates ) {
			$existing = get_page_by_title( $data['post_title'], OBJECT, $this->post_type );
			if ( $existing ) {
				return array(
					'success' => false,
					'skipped' => true,
					'error'   => __( 'Duplicate title skipped', 'dw-product-catalog' ),
				);
			}
		}

		// Prepare post data
		$post_data = array(
			'post_type'    => $this->post_type,
			'post_title'   => sanitize_text_field( $data['post_title'] ),
			'post_content' => isset( $data['post_content'] ) ? wp_kses_post( $data['post_content'] ) : '',
			'post_status'  => isset( $data['post_status'] ) ? sanitize_text_field( $data['post_status'] ) : $default_status,
		);

		// Insert post
		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return array(
				'success' => false,
				'skipped' => false,
				'error'   => $post_id->get_error_message(),
			);
		}

		// Handle featured image
		if ( isset( $data['featured_image_url'] ) && ! empty( $data['featured_image_url'] ) ) {
			$image_url = esc_url_raw( $data['featured_image_url'] );
			$attachment_id = $this->import_image_from_url( $image_url, $post_id, $data['post_title'] );
			
			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		// Save meta fields
		$meta_fields = array(
			'_pc_product_name',
			'_pc_brand',
			'_pc_item_code',
			'_pc_upc',
			'_pc_allergen',
		);

		foreach ( $meta_fields as $meta_key ) {
			if ( isset( $data[ $meta_key ] ) && ! empty( $data[ $meta_key ] ) ) {
				$value = $data[ $meta_key ];
				
				// Sanitize based on field type
				if ( $meta_key === '_pc_allergen' ) {
					$value = sanitize_textarea_field( $value );
				} else {
					$value = sanitize_text_field( $value );
				}
				
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		return array(
			'success' => true,
			'skipped' => false,
			'error'   => '',
		);
	}

	/**
	 * Import image from URL
	 * 
	 * @param string $image_url Image URL
	 * @param int    $post_id   Post ID to attach image to
	 * @param string $title     Image title
	 * @return int|WP_Error Attachment ID or error
	 */
	private function import_image_from_url( $image_url, $post_id, $title = '' ) {
		// Check if image already exists
		$attachment_id = attachment_url_to_postid( $image_url );
		if ( $attachment_id ) {
			return $attachment_id;
		}

		// Download image
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Get file extension
		$file_array = array(
			'name'     => basename( $image_url ),
			'tmp_name' => $tmp,
		);

		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@unlink( $file_array['tmp_name'] );
			return $tmp;
		}

		// Do the validation and storage stuff
		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

		// If error storing permanently, unlink
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $file_array['tmp_name'] );
			return $attachment_id;
		}

		return $attachment_id;
	}
}

