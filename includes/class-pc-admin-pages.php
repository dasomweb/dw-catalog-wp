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
		
		// Use Classic editor for products (Featured Image / Publish work natively; no custom script)
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_gutenberg' ), 10, 2 );
		
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

		// Add New → WordPress standard editor (no custom edit page)
		add_submenu_page(
			'pc-products',
			__( 'Add New Product', 'dw-product-catalog' ),
			__( 'Add New', 'dw-product-catalog' ),
			'edit_posts',
			'pc-products-new',
			array( $this, 'redirect_to_new_product' )
		);

		// Edit (hidden) → redirect to standard editor
		add_submenu_page(
			null,
			__( 'Edit Product', 'dw-product-catalog' ),
			__( 'Edit Product', 'dw-product-catalog' ),
			'edit_posts',
			'pc-products-edit',
			array( $this, 'redirect_to_edit_product' )
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
											<a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>">
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
										<a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>">
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
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=product' ) ); ?>" class="button button-primary">
					<?php _e( 'Add Your First Product', 'dw-product-catalog' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Redirect "Add New" to WordPress standard editor (Featured Image / Publish work natively).
	 */
	public function redirect_to_new_product() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'dw-product-catalog' ) );
		}
		wp_safe_redirect( admin_url( 'post-new.php?post_type=' . $this->post_type ) );
		exit;
	}

	/**
	 * Redirect "Edit" (old URL) to WordPress standard editor.
	 */
	public function redirect_to_edit_product() {
		$product_id = isset( $_GET['product_id'] ) ? intval( $_GET['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_die( __( 'Product not found.', 'dw-product-catalog' ) );
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'dw-product-catalog' ) );
		}
		$post = get_post( $product_id );
		if ( ! $post || $post->post_type !== $this->post_type ) {
			wp_die( __( 'Product not found.', 'dw-product-catalog' ) );
		}
		wp_safe_redirect( get_edit_post_link( $product_id, 'raw' ) );
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
	 * Only used on the product list page (Add New / Edit redirect to standard editor).
	 *
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( $hook !== 'toplevel_page_pc-products' ) {
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

