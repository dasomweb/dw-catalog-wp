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
			__( 'Bulk Import', 'dw-catalog-wp' ),
			__( 'Bulk Import', 'dw-catalog-wp' ),
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
			wp_die( __( 'You do not have permission to access this page.', 'dw-catalog-wp' ) );
		}

		// Show import results
		if ( isset( $_GET['imported'] ) || isset( $_GET['failed'] ) || isset( $_GET['skipped'] ) ) {
			$imported = isset( $_GET['imported'] ) ? intval( $_GET['imported'] ) : 0;
			$failed = isset( $_GET['failed'] ) ? intval( $_GET['failed'] ) : 0;
			$skipped = isset( $_GET['skipped'] ) ? intval( $_GET['skipped'] ) : 0;
			$errors = isset( $_GET['errors'] ) ? explode( '|', urldecode( $_GET['errors'] ) ) : array();

			echo '<div class="notice notice-info"><p>';
			if ( $imported > 0 ) {
				echo '<strong>' . sprintf( __( '%d products imported successfully.', 'dw-catalog-wp' ), $imported ) . '</strong><br>';
			}
			if ( $skipped > 0 ) {
				echo sprintf( __( '%d products skipped (duplicates).', 'dw-catalog-wp' ), $skipped ) . '<br>';
			}
			if ( $failed > 0 ) {
				echo '<strong style="color: #d63638;">' . sprintf( __( '%d products failed to import.', 'dw-catalog-wp' ), $failed ) . '</strong><br>';
			}
			echo '</p></div>';

			if ( ! empty( $errors ) ) {
				echo '<div class="notice notice-error"><p><strong>' . __( 'Errors:', 'dw-catalog-wp' ) . '</strong></p><ul>';
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
			<h1><?php _e( 'Bulk Import Products', 'dw-catalog-wp' ); ?></h1>

			<div class="pc-import-instructions" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<h2><?php _e( 'Import Instructions', 'dw-catalog-wp' ); ?></h2>
				<ol>
					<li><?php _e( 'Prepare your Excel file (.xlsx) or CSV file (.csv)', 'dw-catalog-wp' ); ?></li>
					<li><?php _e( 'Ensure the first row contains column headers', 'dw-catalog-wp' ); ?></li>
					<li><?php _e( 'Required column: <code>dw_pc_product_name</code> (will be used as post title)', 'dw-catalog-wp' ); ?></li>
					<li><?php _e( 'Optional columns:', 'dw-catalog-wp' ); ?>
						<ul>
							<li><code>post_content</code> - <?php _e( 'Product description', 'dw-catalog-wp' ); ?></li>
							<li><code>post_status</code> - <?php _e( 'publish, draft, or private', 'dw-catalog-wp' ); ?></li>
							<li><code>featured_image_url</code> <?php _e( 'or <code>image_url</code>', 'dw-catalog-wp' ); ?> - <?php _e( 'Public image URL (downloaded and added to Media library as featured image)', 'dw-catalog-wp' ); ?></li>
							<li><code>dw_pc_item_code</code> - <?php _e( 'Item Code', 'dw-catalog-wp' ); ?></li>
							<li><code>dw_pc_pack_size_raw</code> - <?php _e( 'Pack Size / Case Pack', 'dw-catalog-wp' ); ?></li>
							<li><code>dw_pc_brand_raw</code> - <?php _e( 'Brand', 'dw-catalog-wp' ); ?></li>
							<li><code>dw_pc_origin_raw</code> - <?php _e( 'Origin', 'dw-catalog-wp' ); ?></li>
							<li><code>dw_pc_status</code> - <?php _e( 'Status (active, inactive, out_of_stock, discontinued)', 'dw-catalog-wp' ); ?></li>
							<li><code>dw_pc_category_name</code> - <?php _e( 'Category Name (created if missing)', 'dw-catalog-wp' ); ?></li>
							<li><code>dw_pc_category_slug</code> - <?php _e( 'Category Slug (created if missing)', 'dw-catalog-wp' ); ?></li>
							<li><code>dw_pc_internal_note</code> - <?php _e( 'ETC', 'dw-catalog-wp' ); ?></li>
						</ul>
					</li>
					<li>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=pc-field-reference' ) ); ?>">
							<?php _e( 'View Field Reference', 'dw-catalog-wp' ); ?>
						</a>
						<?php _e( 'for detailed field information', 'dw-catalog-wp' ); ?>
					</li>
				</ol>

				<p class="description" style="margin-top:8px;"><?php _e( 'Image URLs must be publicly accessible (no login required) so the server can download them into the Media library.', 'dw-catalog-wp' ); ?></p>

				<h3><?php _e( 'Sample CSV Format', 'dw-catalog-wp' ); ?></h3>
				<p class="description"><?php _e( 'Default delimiter is semicolon (;). Select the delimiter that matches your file. Column headers are trimmed; BOM is stripped so headers like featured_image_url are recognized.', 'dw-catalog-wp' ); ?></p>
				<pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;"><code>dw_pc_product_name;post_content;featured_image_url;dw_pc_item_code;dw_pc_pack_size_raw;dw_pc_brand_raw;dw_pc_origin_raw;dw_pc_status;dw_pc_category_name;dw_pc_category_slug;dw_pc_internal_note
"Premium Coffee Beans";"High quality coffee";"https://example.com/image1.jpg";"ITEM-001";"10pc/cs";"Brand A";"Colombia";"active";"Beverages";"category-code";""
"Salmon Fillet";"Fresh salmon";"https://example.com/image2.jpg";"ITEM-002";"1/15lb/cs";"Brand B";"Norway";"active";"Seafood";"";"Note"</code></pre>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="pc-import-form">
				<?php wp_nonce_field( 'pc_import_products', 'pc_import_nonce' ); ?>
				<input type="hidden" name="action" value="pc_import_products">

				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row">
								<label for="import_file"><?php _e( 'Import File', 'dw-catalog-wp' ); ?></label>
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
									<?php _e( 'Upload a CSV or Excel file (.csv, .xlsx, .xls)', 'dw-catalog-wp' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="csv_delimiter"><?php _e( 'CSV Delimiter', 'dw-catalog-wp' ); ?></label>
							</th>
							<td>
								<select name="csv_delimiter" id="csv_delimiter">
									<option value="semicolon" selected><?php _e( 'Semicolon (;)', 'dw-catalog-wp' ); ?></option>
									<option value="comma"><?php _e( 'Comma (,)', 'dw-catalog-wp' ); ?></option>
									<option value="tab"><?php _e( 'Tab', 'dw-catalog-wp' ); ?></option>
								</select>
								<p class="description">
									<?php _e( 'Column separator for CSV files. Default: semicolon (;)', 'dw-catalog-wp' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="default_status"><?php _e( 'Default Status', 'dw-catalog-wp' ); ?></label>
							</th>
							<td>
								<select name="default_status" id="default_status">
									<option value="publish"><?php _e( 'Published', 'dw-catalog-wp' ); ?></option>
									<option value="draft"><?php _e( 'Draft', 'dw-catalog-wp' ); ?></option>
									<option value="private"><?php _e( 'Private', 'dw-catalog-wp' ); ?></option>
								</select>
								<p class="description">
									<?php _e( 'Status to use if not specified in the file', 'dw-catalog-wp' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="skip_duplicates"><?php _e( 'Skip Duplicates', 'dw-catalog-wp' ); ?></label>
							</th>
							<td>
								<label>
									<input type="checkbox" name="skip_duplicates" value="1" checked>
									<?php _e( 'Skip products with duplicate titles', 'dw-catalog-wp' ); ?>
								</label>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( __( 'Import Products', 'dw-catalog-wp' ), 'primary', 'submit', false ); ?>
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
			wp_die( __( 'Security verification failed.', 'dw-catalog-wp' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'dw-catalog-wp' ) );
		}

		// Check file upload
		if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_die( __( 'File upload failed. Please try again.', 'dw-catalog-wp' ) );
		}

		$file = $_FILES['import_file'];
		$default_status = isset( $_POST['default_status'] ) ? sanitize_text_field( $_POST['default_status'] ) : 'publish';
		$skip_duplicates = isset( $_POST['skip_duplicates'] ) && $_POST['skip_duplicates'] === '1';

		// CSV delimiter: semicolon (default), comma, or tab
		$delimiter_key = isset( $_POST['csv_delimiter'] ) ? sanitize_text_field( $_POST['csv_delimiter'] ) : 'semicolon';
		$delimiters = array(
			'semicolon' => ';',
			'comma'     => ',',
			'tab'       => "\t",
		);
		$csv_delimiter = isset( $delimiters[ $delimiter_key ] ) ? $delimiters[ $delimiter_key ] : ';';

		// Process file
		$file_ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		
		if ( $file_ext === 'csv' ) {
			$result = $this->import_csv( $file, $default_status, $skip_duplicates, $csv_delimiter );
		} elseif ( in_array( $file_ext, array( 'xlsx', 'xls' ), true ) ) {
			$result = $this->import_excel( $file, $default_status, $skip_duplicates );
		} else {
			wp_die( __( 'Unsupported file format. Please use CSV or Excel files.', 'dw-catalog-wp' ) );
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
	 * @param string $delimiter CSV column delimiter (default ;)
	 * @return array Result array
	 */
	private function import_csv( $file, $default_status, $skip_duplicates, $delimiter = ';' ) {
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
				'errors'   => array( __( 'Could not open file.', 'dw-catalog-wp' ) ),
			);
		}

		// Read header row (required: dw_pc_product_name)
		$headers_raw = fgetcsv( $handle, 0, $delimiter );
		if ( ! $headers_raw ) {
			fclose( $handle );
			return array(
				'imported' => 0,
				'failed'   => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'Could not read CSV headers.', 'dw-catalog-wp' ) ),
			);
		}
		// Normalize headers: trim, strip BOM (Excel UTF-8 often adds BOM so column names match)
		$headers = $this->normalize_csv_headers( $headers_raw );
		if ( ! in_array( 'dw_pc_product_name', $headers, true ) ) {
			fclose( $handle );
			return array(
				'imported' => 0,
				'failed'   => 0,
				'skipped'  => 0,
				'errors'   => array( __( 'CSV file must contain an "dw_pc_product_name" column.', 'dw-catalog-wp' ) ),
			);
		}

		// Process rows
		$row_num = 1;
		while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			$row_num++;
			
			if ( count( $row ) !== count( $headers ) ) {
				$errors[] = sprintf( __( 'Row %d: Column count mismatch', 'dw-catalog-wp' ), $row_num );
				$failed++;
				continue;
			}

			$data = array_combine( $headers, $row );
			
			$result = $this->import_product( $data, $default_status, $skip_duplicates );
			
			if ( $result['success'] ) {
				$imported++;
				if ( ! empty( $result['warning'] ) ) {
					$errors[] = sprintf( __( 'Row %d: %s', 'dw-catalog-wp' ), $row_num, $result['warning'] );
				}
			} elseif ( $result['skipped'] ) {
				$skipped++;
			} else {
				$failed++;
				if ( ! empty( $result['error'] ) ) {
					$errors[] = sprintf( __( 'Row %d: %s', 'dw-catalog-wp' ), $row_num, $result['error'] );
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
			'errors'   => array( __( 'Excel import requires additional library. Please convert to CSV format.', 'dw-catalog-wp' ) ),
		);
	}

	/**
	 * Normalize CSV headers: trim and strip BOM so column names match (e.g. featured_image_url).
	 * 
	 * @param array $headers Raw header row from fgetcsv
	 * @return array Normalized headers
	 */
	private function normalize_csv_headers( $headers ) {
		$out = array();
		foreach ( $headers as $i => $h ) {
			$h = trim( (string) $h );
			// Strip UTF-8 BOM from first column (Excel / some editors add it)
			if ( $i === 0 && substr( $h, 0, 3 ) === "\xEF\xBB\xBF" ) {
				$h = substr( $h, 3 );
			}
			$out[] = $h;
		}
		return $out;
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
		// Get Product Name
		$product_name = isset( $data['dw_pc_product_name'] ) ? trim( $data['dw_pc_product_name'] ) : '';
		
		// Get Title
		$post_title = isset( $data['post_title'] ) ? trim( $data['post_title'] ) : '';
		
		// If Product Name is provided, use it as Title (Product Name takes priority)
		if ( ! empty( $product_name ) ) {
			$post_title = $product_name;
		}
		
		// Validate required fields
		if ( empty( $post_title ) ) {
			return array(
				'success' => false,
				'skipped' => false,
				'error'   => __( 'Product Name is required', 'dw-catalog-wp' ),
			);
		}
		
		// Update data with the title
		$data['post_title'] = $post_title;

		// Check for duplicates
		if ( $skip_duplicates ) {
			$existing = get_page_by_title( $post_title, OBJECT, $this->post_type );
			if ( $existing ) {
				return array(
					'success' => false,
					'skipped' => true,
					'error'   => __( 'Duplicate title skipped', 'dw-catalog-wp' ),
				);
			}
		}

		// Post slug (post_name): use Item Code if provided, else Product Name
		$item_code = isset( $data['dw_pc_item_code'] ) ? trim( (string) $data['dw_pc_item_code'] ) : '';
		$post_slug = $item_code !== '' ? sanitize_title( $item_code ) : sanitize_title( $data['post_title'] );

		// Prepare post data
		$post_data = array(
			'post_type'    => $this->post_type,
			'post_title'   => sanitize_text_field( $data['post_title'] ),
			'post_name'    => $post_slug,
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

		// Category: get or create from Category Name / Category Slug
		$category_name = isset( $data['dw_pc_category_name'] ) ? trim( (string) $data['dw_pc_category_name'] ) : '';
		$category_slug = isset( $data['dw_pc_category_slug'] ) ? trim( (string) $data['dw_pc_category_slug'] ) : '';
		if ( $category_name !== '' || $category_slug !== '' ) {
			$term_id = pc_get_or_create_product_category( $category_name, $category_slug );
			if ( $term_id ) {
				wp_set_object_terms( $post_id, array( $term_id ), 'product_category' );
				$term = get_term( $term_id, 'product_category' );
				if ( $term && ! is_wp_error( $term ) ) {
					update_post_meta( $post_id, 'dw_pc_category_slug', $term->slug );
				}
			}
		}

		// Handle featured image: support column "featured_image_url" or "image_url" (trimmed)
		$image_warning = '';
		$image_url = '';
		if ( ! empty( $data['featured_image_url'] ) ) {
			$image_url = trim( (string) $data['featured_image_url'] );
		} elseif ( ! empty( $data['image_url'] ) ) {
			$image_url = trim( (string) $data['image_url'] );
		}
		if ( $image_url !== '' ) {
			$image_url = esc_url_raw( $image_url );
			if ( $image_url !== '' ) {
				$attachment_id = $this->import_image_from_url( $image_url, $post_id, $data['post_title'] );
				if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
					set_post_thumbnail( $post_id, $attachment_id );
				} elseif ( is_wp_error( $attachment_id ) ) {
					$image_warning = sprintf( __( 'Image import failed: %s', 'dw-catalog-wp' ), $attachment_id->get_error_message() );
				}
			}
		}

		// Save meta fields (text)
		$text_meta = array(
			'dw_pc_product_name',
			'dw_pc_item_code',
			'dw_pc_pack_size_raw',
			'dw_pc_brand_raw',
			'dw_pc_origin_raw',
			'dw_pc_status',
			'dw_pc_category_slug',
		);
		foreach ( $text_meta as $meta_key ) {
			if ( isset( $data[ $meta_key ] ) && (string) $data[ $meta_key ] !== '' ) {
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $data[ $meta_key ] ) );
			}
		}
		// Textarea: ETC
		if ( isset( $data['dw_pc_internal_note'] ) ) {
			update_post_meta( $post_id, 'dw_pc_internal_note', sanitize_textarea_field( $data['dw_pc_internal_note'] ) );
		}

		return array(
			'success' => true,
			'skipped' => false,
			'error'   => '',
			'warning' => $image_warning,
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
		// Check if image already exists in Media (by URL)
		$attachment_id = attachment_url_to_postid( $image_url );
		if ( $attachment_id ) {
			return $attachment_id;
		}

		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );

		// Slightly longer timeout for large or slow image servers
		add_filter( 'http_request_timeout', array( $this, 'filter_import_http_timeout' ), 10, 0 );
		$tmp = download_url( $image_url );
		remove_filter( 'http_request_timeout', array( $this, 'filter_import_http_timeout' ), 10 );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Filename: use path without query string, ensure we have an extension for Media library
		$path = parse_url( $image_url, PHP_URL_PATH );
		$name = $path ? basename( $path ) : 'image';
		$name = preg_replace( '/[^a-zA-Z0-9._-]/', '', $name );
		if ( ! preg_match( '/\.(jpe?g|gif|png|webp|bmp)$/i', $name ) ) {
			$name .= '.jpg';
		}

		$file_array = array(
			'name'     => $name,
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

		if ( is_wp_error( $attachment_id ) ) {
			if ( ! empty( $file_array['tmp_name'] ) && file_exists( $file_array['tmp_name'] ) ) {
				@unlink( $file_array['tmp_name'] );
			}
			return $attachment_id;
		}

		// Ensure attachment metadata is generated so it appears correctly in Media
		$attached_file = get_attached_file( $attachment_id );
		if ( $attached_file && file_exists( $attached_file ) ) {
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $attached_file );
			if ( ! is_wp_error( $attach_data ) ) {
				wp_update_attachment_metadata( $attachment_id, $attach_data );
			}
		}

		return $attachment_id;
	}

	/**
	 * Increase HTTP timeout during image import (large or slow servers).
	 * 
	 * @return int Timeout in seconds
	 */
	public function filter_import_http_timeout() {
		return 30;
	}
}

