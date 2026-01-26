<?php
/**
 * Admin Pages Class
 * 
 * Creates custom admin pages for product management.
 * Replaces default WordPress post editor with custom interface.
 * Domain-agnostic implementation.
 * 
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_Admin_Pages Class
 * 
 * Manages custom admin pages for products.
 */
class PC_Admin_Pages {

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
		// Remove default post type menu
		add_action( 'admin_menu', array( $this, 'remove_default_menu' ) );
		
		// Add custom admin menu
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		
		// Disable Gutenberg for products
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_gutenberg' ), 10, 2 );
		
		// Handle form submissions
		add_action( 'admin_post_pc_save_product', array( $this, 'handle_save_product' ) );
		add_action( 'admin_post_pc_delete_product', array( $this, 'handle_delete_product' ) );
		
		// Enqueue admin scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Remove default post type menu
	 */
	public function remove_default_menu() {
		remove_menu_page( 'edit.php?post_type=' . $this->post_type );
	}

	/**
	 * Add custom admin menu
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'Product Catalog', 'dw-product-catalog' ),
			__( 'Product Catalog', 'dw-product-catalog' ),
			'edit_posts',
			'pc-products',
			array( $this, 'render_list_page' ),
			'dashicons-products',
			20
		);

		// List submenu
		add_submenu_page(
			'pc-products',
			__( 'All Products', 'dw-product-catalog' ),
			__( 'All Products', 'dw-product-catalog' ),
			'edit_posts',
			'pc-products',
			array( $this, 'render_list_page' )
		);

		// Add new submenu
		add_submenu_page(
			'pc-products',
			__( 'Add New Product', 'dw-product-catalog' ),
			__( 'Add New', 'dw-product-catalog' ),
			'edit_posts',
			'pc-products-new',
			array( $this, 'render_edit_page' )
		);

		// Edit submenu (hidden, but accessible via URL)
		add_submenu_page(
			null, // Hidden from menu
			__( 'Edit Product', 'dw-product-catalog' ),
			__( 'Edit Product', 'dw-product-catalog' ),
			'edit_posts',
			'pc-products-edit',
			array( $this, 'render_edit_page' )
		);

		// Categories submenu
		add_submenu_page(
			'pc-products',
			__( 'Product Categories', 'dw-product-catalog' ),
			__( 'Categories', 'dw-product-catalog' ),
			'manage_categories',
			'edit-tags.php?taxonomy=product_category&post_type=' . $this->post_type
		);

		// Tags submenu
		add_submenu_page(
			'pc-products',
			__( 'Product Tags', 'dw-product-catalog' ),
			__( 'Tags', 'dw-product-catalog' ),
			'manage_categories',
			'edit-tags.php?taxonomy=product_tag&post_type=' . $this->post_type
		);
	}

	/**
	 * Disable Gutenberg for products
	 * 
	 * @param bool   $use_block_editor Whether to use block editor
	 * @param string $post_type        Post type
	 * @return bool
	 */
	public function disable_gutenberg( $use_block_editor, $post_type ) {
		if ( $this->post_type === $post_type ) {
			return false;
		}
		return $use_block_editor;
	}

	/**
	 * Render product list page
	 */
	public function render_list_page() {
		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'dw-product-catalog' ) );
		}

		// Handle bulk actions
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'delete' && isset( $_POST['product_ids'] ) ) {
			check_admin_referer( 'pc-bulk-action' );
			foreach ( $_POST['product_ids'] as $product_id ) {
				wp_delete_post( intval( $product_id ), true );
			}
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Products deleted successfully.', 'dw-product-catalog' ) . '</p></div>';
		}

		// Get products
		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;
		$args = array(
			'post_type'      => $this->post_type,
			'posts_per_page' => 20,
			'paged'          => $paged,
			'post_status'    => 'any',
		);

		$products = new WP_Query( $args );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'Product Catalog', 'dw-product-catalog' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=pc-products-new' ) ); ?>" class="page-title-action">
				<?php _e( 'Add New', 'dw-product-catalog' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php if ( $products->have_posts() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=pc-products' ) ); ?>">
					<?php wp_nonce_field( 'pc-bulk-action' ); ?>
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<select name="action">
								<option value="-1"><?php _e( 'Bulk Actions', 'dw-product-catalog' ); ?></option>
								<option value="delete"><?php _e( 'Delete', 'dw-product-catalog' ); ?></option>
							</select>
							<input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'dw-product-catalog' ); ?>">
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column">
									<input type="checkbox" id="cb-select-all">
								</td>
								<th class="manage-column"><?php _e( 'Title', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Product Name', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Brand', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Item Code', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'UPC', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Temperature', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Status', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Actions', 'dw-product-catalog' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php while ( $products->have_posts() ) : $products->the_post(); ?>
								<?php
								$post_id = get_the_ID();
								$product_name = PC_Product_Display::get_product_name( $post_id );
								$brand = PC_Product_Display::get_brand( $post_id );
								$item_code = PC_Product_Display::get_item_code( $post_id );
								$upc = PC_Product_Display::get_upc( $post_id );
								$temperature = PC_Product_Display::get_temperature( $post_id );
								?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="product_ids[]" value="<?php echo esc_attr( $post_id ); ?>">
									</th>
									<td>
										<strong>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=pc-products-edit&product_id=' . $post_id ) ); ?>">
												<?php echo esc_html( get_the_title() ? get_the_title() : __( '(No Title)', 'dw-product-catalog' ) ); ?>
											</a>
										</strong>
									</td>
									<td><?php echo $product_name ? esc_html( $product_name ) : '—'; ?></td>
									<td><?php echo $brand ? esc_html( $brand ) : '—'; ?></td>
									<td><?php echo $item_code ? esc_html( $item_code ) : '—'; ?></td>
									<td><?php echo $upc ? esc_html( $upc ) : '—'; ?></td>
									<td><?php echo $temperature ? esc_html( $temperature ) : '—'; ?></td>
									<td>
										<?php
										$status = get_post_status( $post_id );
										$status_labels = array(
											'publish' => __( 'Published', 'dw-product-catalog' ),
											'draft'   => __( 'Draft', 'dw-product-catalog' ),
											'private' => __( 'Private', 'dw-product-catalog' ),
										);
										echo isset( $status_labels[ $status ] ) ? esc_html( $status_labels[ $status ] ) : esc_html( $status );
										?>
									</td>
									<td>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=pc-products-edit&product_id=' . $post_id ) ); ?>">
											<?php _e( 'Edit', 'dw-product-catalog' ); ?>
										</a>
										|
										<a href="<?php echo esc_url( admin_url( 'admin-post.php?action=pc_delete_product&product_id=' . $post_id . '&_wpnonce=' . wp_create_nonce( 'pc_delete_' . $post_id ) ) ); ?>" 
										   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this product?', 'dw-product-catalog' ); ?>');">
											<?php _e( 'Delete', 'dw-product-catalog' ); ?>
										</a>
									</td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>

					<?php
					// Pagination
					$pagination = paginate_links( array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $products->max_num_pages,
					) );
					if ( $pagination ) {
						echo '<div class="tablenav"><div class="tablenav-pages">' . $pagination . '</div></div>';
					}
					?>
				</form>
			<?php else : ?>
				<p><?php _e( 'No products found.', 'dw-product-catalog' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=pc-products-new' ) ); ?>" class="button button-primary">
					<?php _e( 'Add Your First Product', 'dw-product-catalog' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Render edit page (add/edit)
	 */
	public function render_edit_page() {
		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'dw-product-catalog' ) );
		}

		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
		$is_edit = $product_id > 0;

		if ( $is_edit ) {
			$product = get_post( $product_id );
			if ( ! $product || $product->post_type !== $this->post_type ) {
				wp_die( __( 'Product not found.', 'dw-product-catalog' ) );
			}
			$title = $product->post_title;
			$content = $product->post_content;
			$status = $product->post_status;
		} else {
			$title = '';
			$content = '';
			$status = 'publish';
		}

		// Get existing meta values
		$product_name = $is_edit ? PC_Product_Display::get_product_name( $product_id ) : '';
		$brand = $is_edit ? PC_Product_Display::get_brand( $product_id ) : '';
		$item_code = $is_edit ? PC_Product_Display::get_item_code( $product_id ) : '';
		$upc = $is_edit ? PC_Product_Display::get_upc( $product_id ) : '';
		$temperature = $is_edit ? PC_Product_Display::get_product_meta( $product_id, '_pc_temperature' ) : '';
		$allergen = $is_edit ? PC_Product_Display::get_allergen( $product_id ) : '';

		$temperature_options = array(
			''           => __( 'Select', 'dw-product-catalog' ),
			'room'       => __( 'Room Temperature', 'dw-product-catalog' ),
			'cold'       => __( 'Refrigerated', 'dw-product-catalog' ),
			'frozen'     => __( 'Frozen', 'dw-product-catalog' ),
			'freezer'    => __( 'Freezer', 'dw-product-catalog' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo $is_edit ? __( 'Edit Product', 'dw-product-catalog' ) : __( 'Add New Product', 'dw-product-catalog' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="pc-product-form">
				<?php wp_nonce_field( 'pc_save_product_' . ( $is_edit ? $product_id : 'new' ), 'pc_product_nonce' ); ?>
				<input type="hidden" name="action" value="pc_save_product">
				<input type="hidden" name="product_id" value="<?php echo esc_attr( $product_id ); ?>">

				<div id="poststuff">
					<div id="post-body" class="metabox-holder columns-2">
						<div id="post-body-content">
							<div class="postbox">
								<div class="postbox-header">
									<h2 class="hndle"><?php _e( 'Basic Information', 'dw-product-catalog' ); ?></h2>
								</div>
								<div class="inside">
									<table class="form-table">
										<tbody>
											<tr>
												<th scope="row">
													<label for="post_title"><?php _e( 'Title', 'dw-product-catalog' ); ?> <span class="required">*</span></label>
												</th>
												<td>
													<input 
														type="text" 
														id="post_title" 
														name="post_title" 
														value="<?php echo esc_attr( $title ); ?>" 
														class="large-text" 
														required
														placeholder="<?php esc_attr_e( 'Enter product title', 'dw-product-catalog' ); ?>"
													/>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="post_content"><?php _e( 'Description', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<?php
													wp_editor(
														$content,
														'post_content',
														array(
															'textarea_name' => 'post_content',
															'textarea_rows'  => 10,
															'media_buttons'  => true,
															'teeny'          => true,
														)
													);
													?>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="post_status"><?php _e( 'Status', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<select name="post_status" id="post_status">
														<option value="publish" <?php selected( $status, 'publish' ); ?>><?php _e( 'Published', 'dw-product-catalog' ); ?></option>
														<option value="draft" <?php selected( $status, 'draft' ); ?>><?php _e( 'Draft', 'dw-product-catalog' ); ?></option>
														<option value="private" <?php selected( $status, 'private' ); ?>><?php _e( 'Private', 'dw-product-catalog' ); ?></option>
													</select>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>

							<div class="postbox">
								<div class="postbox-header">
									<h2 class="hndle"><?php _e( 'Product Details', 'dw-product-catalog' ); ?></h2>
								</div>
								<div class="inside">
									<table class="form-table">
										<tbody>
											<tr>
												<th scope="row">
													<label for="pc_product_name"><?php _e( 'Product Name', 'dw-product-catalog' ); ?></label>
													<span class="description"><?php _e( '(표시명)', 'dw-product-catalog' ); ?></span>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_product_name" 
														name="pc_product_name" 
														value="<?php echo esc_attr( $product_name ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( '제품명을 입력하세요', 'dw-product-catalog' ); ?>"
													/>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_brand"><?php _e( 'Brand', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_brand" 
														name="pc_brand" 
														value="<?php echo esc_attr( $brand ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( '브랜드명을 입력하세요', 'dw-product-catalog' ); ?>"
													/>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_item_code"><?php _e( 'Item Code', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_item_code" 
														name="pc_item_code" 
														value="<?php echo esc_attr( $item_code ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( '아이템 코드를 입력하세요', 'dw-product-catalog' ); ?>"
													/>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_upc"><?php _e( 'UPC', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_upc" 
														name="pc_upc" 
														value="<?php echo esc_attr( $upc ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( 'UPC 코드를 입력하세요', 'dw-product-catalog' ); ?>"
													/>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_temperature"><?php _e( 'Temperature', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<select 
														id="pc_temperature" 
														name="pc_temperature" 
														class="regular-text"
													>
														<?php foreach ( $temperature_options as $value => $label ) : ?>
															<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $temperature, $value ); ?>>
																<?php echo esc_html( $label ); ?>
															</option>
														<?php endforeach; ?>
													</select>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_allergen"><?php _e( 'Allergen', 'dw-product-catalog' ); ?></label>
													<span class="description"><?php _e( '(쉼표로 구분)', 'dw-product-catalog' ); ?></span>
												</th>
												<td>
													<textarea 
														id="pc_allergen" 
														name="pc_allergen" 
														rows="3" 
														class="large-text"
														placeholder="<?php esc_attr_e( '알레르기 유발 성분을 입력하세요 (쉼표로 구분)', 'dw-product-catalog' ); ?>"
													><?php echo esc_textarea( $allergen ); ?></textarea>
													<p class="description">
														<?php _e( '여러 항목을 입력할 경우 쉼표(,)로 구분하세요.', 'dw-product-catalog' ); ?>
													</p>
												</td>
											</tr>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div id="postbox-container-1" class="postbox-container">
							<div class="postbox">
								<div class="postbox-header">
									<h2 class="hndle"><?php _e( 'Publish', 'dw-product-catalog' ); ?></h2>
								</div>
								<div class="inside">
									<div class="submitbox">
										<div id="major-publishing-actions">
											<div id="publishing-action">
												<?php submit_button( $is_edit ? __( 'Update', 'dw-product-catalog' ) : __( 'Publish', 'dw-product-catalog' ), 'primary', 'submit', false ); ?>
											</div>
											<div class="clear"></div>
										</div>
									</div>
								</div>
							</div>

							<?php if ( $is_edit ) : ?>
								<div class="postbox">
									<div class="postbox-header">
										<h2 class="hndle"><?php _e( 'Product Information', 'dw-product-catalog' ); ?></h2>
									</div>
									<div class="inside">
										<p>
											<strong><?php _e( 'Created:', 'dw-product-catalog' ); ?></strong><br>
											<?php echo esc_html( get_the_date( 'Y-m-d H:i', $product_id ) ); ?>
										</p>
										<?php if ( get_the_modified_date( 'Y-m-d H:i', $product_id ) !== get_the_date( 'Y-m-d H:i', $product_id ) ) : ?>
											<p>
												<strong><?php _e( 'Modified:', 'dw-product-catalog' ); ?></strong><br>
												<?php echo esc_html( get_the_modified_date( 'Y-m-d H:i', $product_id ) ); ?>
											</p>
										<?php endif; ?>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle save product form submission
	 */
	public function handle_save_product() {
		// Check nonce
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$nonce_action = 'pc_save_product_' . ( $product_id > 0 ? $product_id : 'new' );
		
		if ( ! isset( $_POST['pc_product_nonce'] ) || ! wp_verify_nonce( $_POST['pc_product_nonce'], $nonce_action ) ) {
			wp_die( __( 'Security verification failed.', 'dw-product-catalog' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'dw-product-catalog' ) );
		}

		// Prepare post data
		$post_data = array(
			'post_type'    => $this->post_type,
			'post_title'   => isset( $_POST['post_title'] ) ? sanitize_text_field( $_POST['post_title'] ) : '',
			'post_content' => isset( $_POST['post_content'] ) ? wp_kses_post( $_POST['post_content'] ) : '',
			'post_status'  => isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : 'publish',
		);

		if ( empty( $post_data['post_title'] ) ) {
			wp_die( __( 'Title is required.', 'dw-product-catalog' ) );
		}

		// Update or insert
		if ( $product_id > 0 ) {
			$post_data['ID'] = $product_id;
			$result = wp_update_post( $post_data, true );
		} else {
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) {
			wp_die( $result->get_error_message() );
		}

		$product_id = $result;

		// Save meta fields
		$fields = array(
			'pc_product_name' => '_pc_product_name',
			'pc_brand'        => '_pc_brand',
			'pc_item_code'    => '_pc_item_code',
			'pc_upc'          => '_pc_upc',
			'pc_temperature'  => '_pc_temperature',
			'pc_allergen'     => '_pc_allergen',
		);

		foreach ( $fields as $field_name => $meta_key ) {
			if ( isset( $_POST[ $field_name ] ) ) {
				$value = $_POST[ $field_name ];
				
				// Sanitize based on field type
				switch ( $field_name ) {
					case 'pc_temperature':
						$value = sanitize_text_field( $value );
						$allowed_values = array( '', 'room', 'cold', 'frozen', 'freezer' );
						if ( ! in_array( $value, $allowed_values, true ) ) {
							$value = '';
						}
						break;
					
					case 'pc_allergen':
						$value = sanitize_textarea_field( $value );
						break;
					
					default:
						$value = sanitize_text_field( $value );
						break;
				}
				
				update_post_meta( $product_id, $meta_key, $value );
			} else {
				delete_post_meta( $product_id, $meta_key );
			}
		}

		// Redirect
		$redirect_url = add_query_arg(
			array(
				'page'       => 'pc-products',
				'updated'    => '1',
				'product_id' => $product_id,
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Handle delete product
	 */
	public function handle_delete_product() {
		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;

		if ( ! $product_id ) {
			wp_die( __( 'Product ID is required.', 'dw-product-catalog' ) );
		}

		// Verify nonce
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'pc_delete_' . $product_id ) ) {
			wp_die( __( 'Security verification failed.', 'dw-product-catalog' ) );
		}

		// Check permissions
		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_die( __( 'You do not have permission to perform this action.', 'dw-product-catalog' ) );
		}

		// Delete product
		wp_delete_post( $product_id, true );

		// Redirect
		$redirect_url = add_query_arg(
			array(
				'page'    => 'pc-products',
				'deleted' => '1',
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Enqueue admin scripts
	 * 
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_scripts( $hook ) {
		// Only load on our custom pages
		if ( strpos( $hook, 'pc-products' ) === false ) {
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
}

