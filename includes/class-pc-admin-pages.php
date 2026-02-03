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
								<th class="manage-column"><?php _e( 'Product Name', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Category', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Item Code', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Pack Size', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Brand', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Origin', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Status', 'dw-product-catalog' ); ?></th>
								<th class="manage-column"><?php _e( 'Actions', 'dw-product-catalog' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php while ( $products->have_posts() ) : $products->the_post(); ?>
								<?php
								$post_id = get_the_ID();
								$product_name = PC_Product_Display::get_product_name( $post_id );
								// If product_name is empty, use post_title as fallback
								if ( empty( $product_name ) ) {
									$product_name = get_the_title( $post_id );
								}
								$categories = wp_get_post_terms( $post_id, 'product_category' );
								$category_names = ! empty( $categories ) ? implode( ', ', wp_list_pluck( $categories, 'name' ) ) : '';
								$item_code = PC_Product_Display::get_item_code( $post_id );
								$pack_size = get_post_meta( $post_id, 'dw_pc_pack_size_raw', true );
								$brand = PC_Product_Display::get_brand( $post_id );
								$origin = PC_Product_Display::get_origin( $post_id );
								$product_status = get_post_meta( $post_id, 'dw_pc_status', true );
								$status_labels = array(
									'active'       => __( 'Active', 'dw-product-catalog' ),
									'inactive'     => __( 'Inactive', 'dw-product-catalog' ),
									'discontinued' => __( 'Discontinued', 'dw-product-catalog' ),
								);
								?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="product_ids[]" value="<?php echo esc_attr( $post_id ); ?>">
									</th>
									<td>
										<strong>
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=pc-products-edit&product_id=' . $post_id ) ); ?>">
												<?php echo $product_name ? esc_html( $product_name ) : esc_html__( '(No Product Name)', 'dw-product-catalog' ); ?>
											</a>
										</strong>
									</td>
									<td><?php echo $category_names ? esc_html( $category_names ) : '—'; ?></td>
									<td><?php echo $item_code ? esc_html( $item_code ) : '—'; ?></td>
									<td><?php echo $pack_size ? esc_html( $pack_size ) : '—'; ?></td>
									<td><?php echo $brand ? esc_html( $brand ) : '—'; ?></td>
									<td><?php echo $origin ? esc_html( $origin ) : '—'; ?></td>
									<td><?php echo isset( $status_labels[ $product_status ] ) ? esc_html( $status_labels[ $product_status ] ) : ( $product_status ? esc_html( $product_status ) : '—' ); ?></td>
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
		// For edit mode, use product_name from meta, otherwise use post_title
		if ( $is_edit ) {
			$product_name = PC_Product_Display::get_product_name( $product_id );
			// If product_name is empty, use post_title as fallback
			if ( empty( $product_name ) ) {
				$product_name = $title;
			}
		} else {
			$product_name = '';
		}
		$item_code     = $is_edit ? PC_Product_Display::get_item_code( $product_id ) : '';
		$pack_size_raw = $is_edit ? get_post_meta( $product_id, 'dw_pc_pack_size_raw', true ) : '';
		$brand_raw     = $is_edit ? PC_Product_Display::get_brand( $product_id ) : '';
		$origin_raw    = $is_edit ? PC_Product_Display::get_origin( $product_id ) : '';
		$status        = $is_edit ? get_post_meta( $product_id, 'dw_pc_status', true ) : '';
		$category_slug = $is_edit ? get_post_meta( $product_id, 'dw_pc_category_slug', true ) : '';
		$category_name = '';
		if ( $is_edit ) {
			$terms = wp_get_post_terms( $product_id, 'product_category' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$category_name = $terms[0]->name;
				if ( $category_slug === '' ) {
					$category_slug = $terms[0]->slug;
				}
			}
		}
		$internal_note = $is_edit ? get_post_meta( $product_id, 'dw_pc_internal_note', true ) : '';
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
													<label for="pc_product_name"><?php _e( 'Product Name', 'dw-product-catalog' ); ?> <span class="required">*</span></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_product_name" 
														name="pc_product_name" 
														value="<?php echo esc_attr( $product_name ); ?>" 
														class="large-text" 
														required
														placeholder="<?php esc_attr_e( 'Enter product name (actual product name used in transactions)', 'dw-product-catalog' ); ?>"
													/>
													<p class="description">
														<?php _e( 'This will be saved as the product title.', 'dw-product-catalog' ); ?>
													</p>
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
													<label for="pc_item_code"><?php _e( 'Item Code', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_item_code" 
														name="pc_item_code" 
														value="<?php echo esc_attr( $item_code ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( 'Enter item code', 'dw-product-catalog' ); ?>"
													/>
													<p class="description">
														<?php _e( 'Used as the product URL slug (post slug).', 'dw-product-catalog' ); ?>
													</p>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_pack_size_raw"><?php _e( 'Pack Size / Case Pack', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_pack_size_raw" 
														name="pc_pack_size_raw" 
														value="<?php echo esc_attr( $pack_size_raw ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( 'e.g., 10pc/cs', 'dw-product-catalog' ); ?>"
													/>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_brand_raw"><?php _e( 'Brand', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_brand_raw" 
														name="pc_brand_raw" 
														value="<?php echo esc_attr( $brand_raw ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( 'Enter brand', 'dw-product-catalog' ); ?>"
													/>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_origin_raw"><?php _e( 'Origin', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_origin_raw" 
														name="pc_origin_raw" 
														value="<?php echo esc_attr( $origin_raw ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( 'Enter country or region', 'dw-product-catalog' ); ?>"
													/>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_status"><?php _e( 'Status', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<select name="pc_status" id="pc_status">
														<option value="" <?php selected( $status, '' ); ?>><?php _e( '— Select —', 'dw-product-catalog' ); ?></option>
														<option value="active" <?php selected( $status, 'active' ); ?>><?php _e( 'Active', 'dw-product-catalog' ); ?></option>
														<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php _e( 'Inactive', 'dw-product-catalog' ); ?></option>
														<option value="out_of_stock" <?php selected( $status, 'out_of_stock' ); ?>><?php _e( 'Out of Stock', 'dw-product-catalog' ); ?></option>
														<option value="discontinued" <?php selected( $status, 'discontinued' ); ?>><?php _e( 'Discontinued', 'dw-product-catalog' ); ?></option>
													</select>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_category_name"><?php _e( 'Category Name', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_category_name" 
														name="pc_category_name" 
														value="<?php echo esc_attr( $category_name ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( 'e.g., Seafood', 'dw-product-catalog' ); ?>"
													/>
													<p class="description">
														<?php _e( 'Category display name. Created if it does not exist.', 'dw-product-catalog' ); ?>
													</p>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_category_slug"><?php _e( 'Category Slug', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<input 
														type="text" 
														id="pc_category_slug" 
														name="pc_category_slug" 
														value="<?php echo esc_attr( $category_slug ); ?>" 
														class="regular-text"
														placeholder="<?php esc_attr_e( 'e.g., category-code', 'dw-product-catalog' ); ?>"
													/>
													<p class="description">
														<?php _e( 'Category code / slug. Created if it does not exist. Used as post URL slug when combined with Item Code.', 'dw-product-catalog' ); ?>
													</p>
												</td>
											</tr>
											<tr>
												<th scope="row">
													<label for="pc_internal_note"><?php _e( 'ETC', 'dw-product-catalog' ); ?></label>
												</th>
												<td>
													<textarea 
														id="pc_internal_note" 
														name="pc_internal_note" 
														rows="4" 
														class="large-text"
														placeholder="<?php esc_attr_e( 'Internal notes', 'dw-product-catalog' ); ?>"
													><?php echo esc_textarea( $internal_note ); ?></textarea>
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

							<?php
							// Categories meta box
							if ( $is_edit ) {
								$categories = get_terms( array(
									'taxonomy'   => 'product_category',
									'hide_empty' => false,
								) );
								$selected_categories = wp_get_post_terms( $product_id, 'product_category', array( 'fields' => 'ids' ) );
								?>
								<div class="postbox">
									<div class="postbox-header">
										<h2 class="hndle"><?php _e( 'Categories', 'dw-product-catalog' ); ?></h2>
									</div>
									<div class="inside">
										<div id="taxonomy-product_category" class="categorydiv">
											<div id="product_category-all" class="tabs-panel">
												<?php if ( ! empty( $categories ) ) : ?>
													<ul id="product_categorychecklist" class="categorychecklist form-no-clear">
														<?php
														wp_terms_checklist(
															$product_id,
															array(
																'taxonomy'      => 'product_category',
																'selected_cats' => $selected_categories,
															)
														);
														?>
													</ul>
												<?php else : ?>
													<p><?php _e( 'No categories found.', 'dw-product-catalog' ); ?></p>
													<p>
														<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=product_category&post_type=' . $this->post_type ) ); ?>">
															<?php _e( 'Add Category', 'dw-product-catalog' ); ?>
														</a>
													</p>
												<?php endif; ?>
											</div>
										</div>
									</div>
								</div>
								<?php
							} else {
								// For new products, show simple category selection
								$categories = get_terms( array(
									'taxonomy'   => 'product_category',
									'hide_empty' => false,
								) );
								?>
								<div class="postbox">
									<div class="postbox-header">
										<h2 class="hndle"><?php _e( 'Categories', 'dw-product-catalog' ); ?></h2>
									</div>
									<div class="inside">
										<?php if ( ! empty( $categories ) ) : ?>
											<ul id="product_categorychecklist" class="categorychecklist form-no-clear">
												<?php foreach ( $categories as $category ) : ?>
													<li id="product_category-<?php echo esc_attr( $category->term_id ); ?>">
														<label class="selectit">
															<input 
																type="checkbox" 
																name="tax_input[product_category][]" 
																value="<?php echo esc_attr( $category->term_id ); ?>"
															/>
															<?php echo esc_html( $category->name ); ?>
														</label>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php else : ?>
											<p><?php _e( 'No categories found.', 'dw-product-catalog' ); ?></p>
											<p>
												<a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=product_category&post_type=' . $this->post_type ) ); ?>">
													<?php _e( 'Add Category', 'dw-product-catalog' ); ?>
												</a>
											</p>
										<?php endif; ?>
									</div>
								</div>
								<?php
							}

							// Tags meta box
							$product_tags = $is_edit ? wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) ) : array();
							$tags_string = ! empty( $product_tags ) ? implode( ', ', $product_tags ) : '';
							?>
							<div class="postbox">
								<div class="postbox-header">
									<h2 class="hndle"><?php _e( 'Tags', 'dw-product-catalog' ); ?></h2>
								</div>
								<div class="inside">
									<div id="taxonomy-product_tag" class="tagsdiv">
										<div class="jaxtag">
											<label for="product_tag_input" class="screen-reader-text">
												<?php _e( 'Tags', 'dw-product-catalog' ); ?>
											</label>
											<input 
												type="text" 
												id="product_tag_input" 
												name="product_tags" 
												class="newtag form-input-tip" 
												size="40" 
												autocomplete="off" 
												value="<?php echo esc_attr( $tags_string ); ?>"
												placeholder="<?php esc_attr_e( 'Separate tags with commas', 'dw-product-catalog' ); ?>"
											/>
										</div>
										<p class="howto">
											<?php _e( 'Separate tags with commas', 'dw-product-catalog' ); ?>
										</p>
									</div>
								</div>
							</div>
							<?php
							?>

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

		// Get Product Name first (required field)
		$product_name = isset( $_POST['pc_product_name'] ) ? sanitize_text_field( $_POST['pc_product_name'] ) : '';
		
		if ( empty( $product_name ) ) {
			wp_die( __( 'Product Name is required.', 'dw-product-catalog' ) );
		}
		
		// Product Name becomes the Title
		// Post slug (post_name): use Item Code if provided, else Product Name
		$item_code = isset( $_POST['pc_item_code'] ) ? trim( sanitize_text_field( $_POST['pc_item_code'] ) ) : '';
		$post_slug = $item_code !== '' ? sanitize_title( $item_code ) : sanitize_title( $product_name );

		$post_data = array(
			'post_type'    => $this->post_type,
			'post_title'   => $product_name,
			'post_name'    => $post_slug,
			'post_content' => isset( $_POST['post_content'] ) ? wp_kses_post( $_POST['post_content'] ) : '',
			'post_status'  => isset( $_POST['post_status'] ) ? sanitize_text_field( $_POST['post_status'] ) : 'publish',
		);

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

		// Category: get or create from Category Name / Category Slug
		$category_name = isset( $_POST['pc_category_name'] ) ? trim( sanitize_text_field( $_POST['pc_category_name'] ) ) : '';
		$category_slug = isset( $_POST['pc_category_slug'] ) ? trim( sanitize_text_field( $_POST['pc_category_slug'] ) ) : '';
		if ( $category_name !== '' || $category_slug !== '' ) {
			$term_id = pc_get_or_create_product_category( $category_name, $category_slug );
			if ( $term_id ) {
				wp_set_object_terms( $product_id, array( $term_id ), 'product_category' );
				$term = get_term( $term_id, 'product_category' );
				if ( $term && ! is_wp_error( $term ) ) {
					update_post_meta( $product_id, 'dw_pc_category_slug', $term->slug );
				}
			}
		} elseif ( isset( $_POST['tax_input']['product_category'] ) ) {
			// Fallback: checkbox selection
			$categories = array_map( 'intval', (array) $_POST['tax_input']['product_category'] );
			wp_set_object_terms( $product_id, $categories, 'product_category' );
			$terms = wp_get_post_terms( $product_id, 'product_category' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				update_post_meta( $product_id, 'dw_pc_category_slug', $terms[0]->slug );
			} else {
				delete_post_meta( $product_id, 'dw_pc_category_slug' );
			}
		} else {
			wp_set_object_terms( $product_id, array(), 'product_category' );
			delete_post_meta( $product_id, 'dw_pc_category_slug' );
		}

		// Handle tags (comma-separated string)
		if ( isset( $_POST['product_tags'] ) ) {
			$tags_string = sanitize_text_field( $_POST['product_tags'] );
			if ( ! empty( $tags_string ) ) {
				$tags = array_map( 'trim', explode( ',', $tags_string ) );
				$tags = array_filter( $tags );
				wp_set_object_terms( $product_id, $tags, 'product_tag' );
			} else {
				// If empty, remove all tags
				wp_set_object_terms( $product_id, array(), 'product_tag' );
			}
		}

		// Save meta fields (text)
		$text_fields = array(
			'pc_product_name'  => 'dw_pc_product_name',
			'pc_item_code'     => 'dw_pc_item_code',
			'pc_pack_size_raw' => 'dw_pc_pack_size_raw',
			'pc_brand_raw'     => 'dw_pc_brand_raw',
			'pc_origin_raw'    => 'dw_pc_origin_raw',
			'pc_status'        => 'dw_pc_status',
			'pc_category_slug' => 'dw_pc_category_slug',
		);
		foreach ( $text_fields as $field_name => $meta_key ) {
			if ( isset( $_POST[ $field_name ] ) ) {
				update_post_meta( $product_id, $meta_key, sanitize_text_field( $_POST[ $field_name ] ) );
			} else {
				delete_post_meta( $product_id, $meta_key );
			}
		}
		// Textarea: ETC
		if ( isset( $_POST['pc_internal_note'] ) ) {
			update_post_meta( $product_id, 'dw_pc_internal_note', sanitize_textarea_field( $_POST['pc_internal_note'] ) );
		} else {
			delete_post_meta( $product_id, 'dw_pc_internal_note' );
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
		
		// Add JavaScript to sync Product Name to Title
		if ( strpos( $hook, 'pc-products-edit' ) !== false ) {
			?>
			<script type="text/javascript">
			jQuery(document).ready(function($) {
				var $productName = $('#pc_product_name');
				var $title = $('#post_title');
				var titleInitialValue = $title.val();
				
				// Sync Product Name to Title when Product Name changes
				$productName.on('input', function() {
					var productNameValue = $(this).val();
					if (productNameValue) {
						$title.val(productNameValue);
					}
				});
				
				// If Product Name is filled but Title is empty, sync on page load
				if ($productName.val() && !$title.val()) {
					$title.val($productName.val());
				}
			});
			</script>
			<?php
		}
	}
}

