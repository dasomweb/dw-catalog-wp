<?php
/**
 * Bulk Import Class
 *
 * Handles CSV bulk import for any registered post type.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PC_Bulk_Import {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
		add_action( 'admin_post_dw_catalog_import', array( $this, 'handle_import' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Add Bulk Import submenu to each post type menu.
	 */
	public function add_admin_menus() {
		$post_types = PC_Config::get_post_types();
		foreach ( $post_types as $slug => $config ) {
			$parent = 'dw-catalog-' . $slug;
			add_submenu_page(
				$parent,
				__( 'Bulk Import', 'dw-catalog-wp' ),
				__( 'Bulk Import', 'dw-catalog-wp' ),
				'edit_posts',
				$parent . '-import',
				array( $this, 'render_page' )
			);
		}
	}

	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, '-import' ) === false ) {
			return;
		}
		$config = pc_get_plugin_config();
		$css_path = pc_get_plugin_path() . 'assets/css/admin.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'pc-admin-style', PC_URL_Helper::get_css_url( 'admin.css' ), array(), $config['plugin_version'] );
		}
	}

	/**
	 * Determine the post type from the page parameter.
	 */
	private function get_post_type_from_page() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		// Format: dw-catalog-{slug}-import
		if ( preg_match( '/^dw-catalog-(.+)-import$/', $page, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Render bulk import page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$pt_slug = $this->get_post_type_from_page();
		$pt_config = PC_Config::get_post_type( $pt_slug );
		if ( ! $pt_config ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Post type not found.', 'dw-catalog-wp' ) . '</p></div>';
			return;
		}

		$fields = PC_Config::get_fields( $pt_slug );

		// Show results
		if ( isset( $_GET['imported'] ) || isset( $_GET['failed'] ) || isset( $_GET['skipped'] ) ) {
			$imported = intval( $_GET['imported'] ?? 0 );
			$failed = intval( $_GET['failed'] ?? 0 );
			$skipped = intval( $_GET['skipped'] ?? 0 );
			$errors = isset( $_GET['errors'] ) ? explode( '|', urldecode( $_GET['errors'] ) ) : array();

			echo '<div class="notice notice-info"><p>';
			if ( $imported > 0 ) {
				echo '<strong>' . sprintf( __( '%d items imported successfully.', 'dw-catalog-wp' ), $imported ) . '</strong><br>';
			}
			if ( $skipped > 0 ) {
				echo sprintf( __( '%d items skipped (duplicates).', 'dw-catalog-wp' ), $skipped ) . '<br>';
			}
			if ( $failed > 0 ) {
				echo '<strong style="color:#d63638;">' . sprintf( __( '%d items failed.', 'dw-catalog-wp' ), $failed ) . '</strong><br>';
			}
			echo '</p></div>';

			if ( ! empty( $errors ) ) {
				echo '<div class="notice notice-error"><p><strong>' . __( 'Errors:', 'dw-catalog-wp' ) . '</strong></p><ul>';
				foreach ( $errors as $err ) {
					if ( ! empty( $err ) ) {
						echo '<li>' . esc_html( $err ) . '</li>';
					}
				}
				echo '</ul></div>';
			}
		}

		$title_field = PC_Config::get_title_field( $pt_slug );
		$title_key = $title_field ? $title_field['meta_key'] : 'post_title';
		?>
		<div class="wrap">
			<h1><?php printf( __( 'Bulk Import — %s', 'dw-catalog-wp' ), esc_html( $pt_config['plural_name'] ) ); ?></h1>

			<div style="background:#fff; padding:20px; margin:20px 0; border:1px solid #ccd0d4;">
				<h2><?php _e( 'CSV Format', 'dw-catalog-wp' ); ?></h2>
				<p><?php _e( 'First row must be column headers. Required column:', 'dw-catalog-wp' ); ?> <code><?php echo esc_html( $title_key ); ?></code></p>
				<p><?php _e( 'Available columns:', 'dw-catalog-wp' ); ?></p>
				<ul style="list-style:disc; padding-left:20px;">
					<?php foreach ( $fields as $field ) : ?>
						<li><code><?php echo esc_html( $field['meta_key'] ); ?></code> — <?php echo esc_html( $field['label'] ); ?><?php echo ! empty( $field['required'] ) ? ' <strong>(' . __( 'required', 'dw-catalog-wp' ) . ')</strong>' : ''; ?></li>
					<?php endforeach; ?>
					<li><code>post_content</code> — <?php _e( 'Description', 'dw-catalog-wp' ); ?></li>
					<li><code>post_status</code> — <?php _e( 'publish, draft, or private', 'dw-catalog-wp' ); ?></li>
					<li><code>featured_image_url</code> — <?php _e( 'Image URL (downloaded to Media library)', 'dw-catalog-wp' ); ?></li>
					<?php if ( ! empty( $pt_config['has_category'] ) ) : ?>
						<li><code>category_name</code> — <?php _e( 'Category name (created if not exists)', 'dw-catalog-wp' ); ?></li>
						<li><code>category_slug</code> — <?php _e( 'Category slug', 'dw-catalog-wp' ); ?></li>
					<?php endif; ?>
				</ul>

				<h3><?php _e( 'Sample CSV', 'dw-catalog-wp' ); ?></h3>
				<pre style="background:#f5f5f5; padding:10px; overflow-x:auto;"><code><?php
					$headers = array();
					foreach ( $fields as $f ) {
						$headers[] = $f['meta_key'];
					}
					echo esc_html( implode( ';', $headers ) );
					echo "\n";
					$sample = array();
					foreach ( $fields as $f ) {
						$sample[] = '"sample"';
					}
					echo esc_html( implode( ';', $sample ) );
				?></code></pre>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'dw_catalog_import', 'dw_catalog_import_nonce' ); ?>
				<input type="hidden" name="action" value="dw_catalog_import">
				<input type="hidden" name="post_type_slug" value="<?php echo esc_attr( $pt_slug ); ?>">

				<table class="form-table">
					<tr>
						<th><label for="import_file"><?php _e( 'CSV File', 'dw-catalog-wp' ); ?></label></th>
						<td>
							<input type="file" id="import_file" name="import_file" accept=".csv" required>
							<p class="description"><?php _e( 'Upload a CSV file (.csv)', 'dw-catalog-wp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="csv_delimiter"><?php _e( 'CSV Delimiter', 'dw-catalog-wp' ); ?></label></th>
						<td>
							<select name="csv_delimiter" id="csv_delimiter">
								<option value="semicolon" selected><?php _e( 'Semicolon (;)', 'dw-catalog-wp' ); ?></option>
								<option value="comma"><?php _e( 'Comma (,)', 'dw-catalog-wp' ); ?></option>
								<option value="tab"><?php _e( 'Tab', 'dw-catalog-wp' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="default_status"><?php _e( 'Default Status', 'dw-catalog-wp' ); ?></label></th>
						<td>
							<select name="default_status" id="default_status">
								<option value="publish"><?php _e( 'Published', 'dw-catalog-wp' ); ?></option>
								<option value="draft"><?php _e( 'Draft', 'dw-catalog-wp' ); ?></option>
								<option value="private"><?php _e( 'Private', 'dw-catalog-wp' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Skip Duplicates', 'dw-catalog-wp' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="skip_duplicates" value="1" checked>
								<?php _e( 'Skip items with duplicate titles', 'dw-catalog-wp' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Import', 'dw-catalog-wp' ), 'primary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle import.
	 */
	public function handle_import() {
		if ( ! isset( $_POST['dw_catalog_import_nonce'] ) || ! wp_verify_nonce( $_POST['dw_catalog_import_nonce'], 'dw_catalog_import' ) ) {
			wp_die( __( 'Security check failed.', 'dw-catalog-wp' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			wp_die( __( 'File upload failed.', 'dw-catalog-wp' ) );
		}

		$pt_slug = sanitize_key( $_POST['post_type_slug'] );
		$pt_config = PC_Config::get_post_type( $pt_slug );
		if ( ! $pt_config ) {
			wp_die( __( 'Invalid post type.', 'dw-catalog-wp' ) );
		}

		$default_status = sanitize_text_field( $_POST['default_status'] ?? 'publish' );
		$skip_duplicates = ! empty( $_POST['skip_duplicates'] );
		$delimiter_key = sanitize_text_field( $_POST['csv_delimiter'] ?? 'semicolon' );
		$delimiters = array( 'semicolon' => ';', 'comma' => ',', 'tab' => "\t" );
		$delimiter = isset( $delimiters[ $delimiter_key ] ) ? $delimiters[ $delimiter_key ] : ';';

		$result = $this->import_csv( $_FILES['import_file'], $pt_slug, $pt_config, $default_status, $skip_duplicates, $delimiter );

		$redirect = add_query_arg( array(
			'page'     => 'dw-catalog-' . $pt_slug . '-import',
			'imported' => $result['imported'],
			'failed'   => $result['failed'],
			'skipped'  => $result['skipped'],
			'errors'   => urlencode( implode( '|', $result['errors'] ) ),
		), admin_url( 'admin.php' ) );
		wp_safe_redirect( $redirect );
		exit;
	}

	private function import_csv( $file, $pt_slug, $pt_config, $default_status, $skip_duplicates, $delimiter ) {
		$imported = 0;
		$failed = 0;
		$skipped = 0;
		$errors = array();

		$handle = fopen( $file['tmp_name'], 'r' );
		if ( ! $handle ) {
			return array( 'imported' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => array( __( 'Could not open file.', 'dw-catalog-wp' ) ) );
		}

		$headers_raw = fgetcsv( $handle, 0, $delimiter );
		if ( ! $headers_raw ) {
			fclose( $handle );
			return array( 'imported' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => array( __( 'Could not read CSV headers.', 'dw-catalog-wp' ) ) );
		}

		$headers = $this->normalize_headers( $headers_raw );

		// Find title field
		$title_field = PC_Config::get_title_field( $pt_slug );
		$title_key = $title_field ? $title_field['meta_key'] : 'post_title';

		if ( ! in_array( $title_key, $headers, true ) && ! in_array( 'post_title', $headers, true ) ) {
			fclose( $handle );
			return array( 'imported' => 0, 'failed' => 0, 'skipped' => 0, 'errors' => array(
				sprintf( __( 'CSV must contain a "%s" or "post_title" column.', 'dw-catalog-wp' ), $title_key )
			) );
		}

		$fields = PC_Config::get_fields( $pt_slug );
		$row_num = 1;

		while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			$row_num++;
			if ( count( $row ) !== count( $headers ) ) {
				$errors[] = sprintf( __( 'Row %d: Column count mismatch', 'dw-catalog-wp' ), $row_num );
				$failed++;
				continue;
			}

			$data = array_combine( $headers, $row );
			$result = $this->import_single( $data, $pt_slug, $pt_config, $fields, $title_key, $default_status, $skip_duplicates );

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
		return compact( 'imported', 'failed', 'skipped', 'errors' );
	}

	private function normalize_headers( $headers ) {
		$out = array();
		foreach ( $headers as $i => $h ) {
			$h = trim( (string) $h );
			if ( $i === 0 && substr( $h, 0, 3 ) === "\xEF\xBB\xBF" ) {
				$h = substr( $h, 3 );
			}
			$out[] = $h;
		}
		return $out;
	}

	private function import_single( $data, $pt_slug, $pt_config, $fields, $title_key, $default_status, $skip_duplicates ) {
		// Resolve title
		$title = '';
		if ( isset( $data[ $title_key ] ) && trim( $data[ $title_key ] ) !== '' ) {
			$title = trim( $data[ $title_key ] );
		} elseif ( isset( $data['post_title'] ) && trim( $data['post_title'] ) !== '' ) {
			$title = trim( $data['post_title'] );
		}
		if ( empty( $title ) ) {
			return array( 'success' => false, 'skipped' => false, 'error' => __( 'Title is required', 'dw-catalog-wp' ) );
		}

		if ( $skip_duplicates ) {
			$dup_query = new WP_Query( array(
				'post_type'      => $pt_slug,
				'title'          => $title,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			) );
			if ( $dup_query->found_posts > 0 ) {
				return array( 'success' => false, 'skipped' => true, 'error' => '' );
			}
		}

		$post_slug = sanitize_title( $title );
		$post_data = array(
			'post_type'    => $pt_slug,
			'post_title'   => sanitize_text_field( $title ),
			'post_name'    => $post_slug,
			'post_content' => isset( $data['post_content'] ) ? wp_kses_post( $data['post_content'] ) : '',
			'post_status'  => isset( $data['post_status'] ) ? sanitize_text_field( $data['post_status'] ) : $default_status,
		);

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			return array( 'success' => false, 'skipped' => false, 'error' => $post_id->get_error_message() );
		}

		// Save meta fields
		foreach ( $fields as $field ) {
			$mk = $field['meta_key'];
			if ( isset( $data[ $mk ] ) && (string) $data[ $mk ] !== '' ) {
				$val = $field['type'] === 'textarea'
					? sanitize_textarea_field( $data[ $mk ] )
					: sanitize_text_field( $data[ $mk ] );
				update_post_meta( $post_id, $mk, $val );
			}
		}

		// Category
		if ( ! empty( $pt_config['has_category'] ) ) {
			$cat_name = isset( $data['category_name'] ) ? trim( $data['category_name'] ) : '';
			$cat_slug_val = isset( $data['category_slug'] ) ? trim( $data['category_slug'] ) : '';
			// Also support legacy column names
			if ( $cat_name === '' && isset( $data['dw_pc_category_name'] ) ) {
				$cat_name = trim( $data['dw_pc_category_name'] );
			}
			if ( $cat_slug_val === '' && isset( $data['dw_pc_category_slug'] ) ) {
				$cat_slug_val = trim( $data['dw_pc_category_slug'] );
			}
			if ( $cat_name !== '' || $cat_slug_val !== '' ) {
				$cat_tax = PC_Config::get_category_taxonomy( $pt_slug );
				$term_id = pc_get_or_create_term( $cat_name, $cat_slug_val, $cat_tax );
				if ( $term_id ) {
					wp_set_object_terms( $post_id, array( $term_id ), $cat_tax );
				}
			}
		}

		// Featured image
		$image_warning = '';
		$image_url = '';
		if ( ! empty( $data['featured_image_url'] ) ) {
			$image_url = trim( $data['featured_image_url'] );
		} elseif ( ! empty( $data['image_url'] ) ) {
			$image_url = trim( $data['image_url'] );
		}
		if ( $image_url !== '' ) {
			$image_url = esc_url_raw( $image_url );
			if ( $image_url !== '' ) {
				$att_id = $this->import_image( $image_url, $post_id, $title );
				if ( $att_id && ! is_wp_error( $att_id ) ) {
					set_post_thumbnail( $post_id, $att_id );
				} elseif ( is_wp_error( $att_id ) ) {
					$image_warning = sprintf( __( 'Image failed: %s', 'dw-catalog-wp' ), $att_id->get_error_message() );
				}
			}
		}

		return array( 'success' => true, 'skipped' => false, 'error' => '', 'warning' => $image_warning );
	}

	private function import_image( $url, $post_id, $title = '' ) {
		$att_id = attachment_url_to_postid( $url );
		if ( $att_id ) {
			return $att_id;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		add_filter( 'http_request_timeout', function () { return 30; }, 10, 0 );
		$tmp = download_url( $url );
		remove_all_filters( 'http_request_timeout' );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$path = parse_url( $url, PHP_URL_PATH );
		$name = $path ? basename( $path ) : 'image';
		$name = preg_replace( '/[^a-zA-Z0-9._-]/', '', $name );
		if ( ! preg_match( '/\.(jpe?g|gif|png|webp|bmp)$/i', $name ) ) {
			$name .= '.jpg';
		}

		$file_array = array( 'name' => $name, 'tmp_name' => $tmp );
		$att_id = media_handle_sideload( $file_array, $post_id, $title );

		if ( is_wp_error( $att_id ) && ! empty( $file_array['tmp_name'] ) && file_exists( $file_array['tmp_name'] ) ) {
			@unlink( $file_array['tmp_name'] );
			return $att_id;
		}

		$attached = get_attached_file( $att_id );
		if ( $attached && file_exists( $attached ) ) {
			$meta = wp_generate_attachment_metadata( $att_id, $attached );
			if ( ! is_wp_error( $meta ) ) {
				wp_update_attachment_metadata( $att_id, $meta );
			}
		}

		return $att_id;
	}
}
