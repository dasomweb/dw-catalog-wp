<?php
/**
 * Field Reference Class
 * 
 * Displays custom field information and reference guide.
 * Domain-agnostic implementation.
 * 
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_Field_Reference Class
 * 
 * Manages field reference page.
 */
class PC_Field_Reference {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'pc-products',
			__( 'Field Reference', 'dw-product-catalog' ),
			__( 'Field Reference', 'dw-product-catalog' ),
			'edit_posts',
			'pc-field-reference',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Get all custom fields
	 * 
	 * @return array Custom fields array
	 */
	public function get_custom_fields() {
		return array(
			array(
				'label'       => __( 'Product Name', 'dw-product-catalog' ),
				'meta_key'    => '_pc_product_name',
				'type'        => 'text',
				'description' => __( 'Display name for the product', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'Premium Coffee Beans',
			),
			array(
				'label'       => __( 'Brand', 'dw-product-catalog' ),
				'meta_key'    => '_pc_brand',
				'type'        => 'text',
				'description' => __( 'Brand name of the product', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'Brand Name',
			),
			array(
				'label'       => __( 'Item Code', 'dw-product-catalog' ),
				'meta_key'    => '_pc_item_code',
				'type'        => 'text',
				'description' => __( 'Internal item code or SKU', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'ITEM-001',
			),
			array(
				'label'       => __( 'UPC', 'dw-product-catalog' ),
				'meta_key'    => '_pc_upc',
				'type'        => 'text',
				'description' => __( 'Universal Product Code', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => '123456789012',
			),
			array(
				'label'       => __( 'Allergen', 'dw-product-catalog' ),
				'meta_key'    => '_pc_allergen',
				'type'        => 'textarea',
				'description' => __( 'Allergen information (comma-separated)', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'Milk, Eggs, Nuts',
			),
			array(
				'label'       => __( 'Featured Image', 'dw-product-catalog' ),
				'meta_key'    => 'featured_image_url',
				'type'        => 'url',
				'description' => __( 'Featured image URL (for bulk import - will be downloaded and set as featured image)', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'https://example.com/product-image.jpg',
			),
		);
	}

	/**
	 * Render field reference page
	 */
	public function render_page() {
		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'You do not have permission to access this page.', 'dw-product-catalog' ) );
		}

		$fields = $this->get_custom_fields();
		?>
		<div class="wrap">
			<h1><?php _e( 'Field Reference', 'dw-product-catalog' ); ?></h1>
			<p class="description">
				<?php _e( 'This page lists all custom fields used in the Product Catalog plugin. Use these field names when importing data or accessing product information programmatically.', 'dw-product-catalog' ); ?>
			</p>

			<div class="pc-field-reference">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="manage-column"><?php _e( 'Label', 'dw-product-catalog' ); ?></th>
							<th class="manage-column"><?php _e( 'Meta Key', 'dw-product-catalog' ); ?></th>
							<th class="manage-column"><?php _e( 'Type', 'dw-product-catalog' ); ?></th>
							<th class="manage-column"><?php _e( 'Description', 'dw-product-catalog' ); ?></th>
							<th class="manage-column"><?php _e( 'Example', 'dw-product-catalog' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fields as $field ) : ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $field['label'] ); ?></strong>
									<?php if ( isset( $field['required'] ) && $field['required'] ) : ?>
										<span class="required">*</span>
									<?php endif; ?>
								</td>
								<td>
									<code><?php echo esc_html( $field['meta_key'] ); ?></code>
								</td>
								<td>
									<?php echo esc_html( $field['type'] ); ?>
									<?php if ( isset( $field['options'] ) && is_array( $field['options'] ) ) : ?>
										<br><small>
											<?php _e( 'Options:', 'dw-product-catalog' ); ?>
											<?php echo esc_html( implode( ', ', array_keys( $field['options'] ) ) ); ?>
										</small>
									<?php endif; ?>
								</td>
								<td><?php echo esc_html( $field['description'] ); ?></td>
								<td>
									<?php if ( isset( $field['example'] ) ) : ?>
										<code><?php echo esc_html( $field['example'] ); ?></code>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div class="pc-field-reference-info" style="margin-top: 30px;">
					<h2><?php _e( 'Usage Examples', 'dw-product-catalog' ); ?></h2>
					
					<h3><?php _e( 'Get Field Value', 'dw-product-catalog' ); ?></h3>
					<pre><code>$product_name = get_post_meta( $post_id, '_pc_product_name', true );
$brand = get_post_meta( $post_id, '_pc_brand', true );</code></pre>

					<h3><?php _e( 'Using Helper Functions', 'dw-product-catalog' ); ?></h3>
					<pre><code>$product_name = PC_Product_Display::get_product_name( $post_id );
$brand = PC_Product_Display::get_brand( $post_id );
$item_code = PC_Product_Display::get_item_code( $post_id );</code></pre>

					<h3><?php _e( 'Kadence Blocks Pro Dynamic Field', 'dw-product-catalog' ); ?></h3>
					<p><?php _e( 'Use the meta key directly in Kadence Blocks Pro Dynamic Field:', 'dw-product-catalog' ); ?></p>
					<ul>
						<li><code>_pc_product_name</code></li>
						<li><code>_pc_brand</code></li>
						<li><code>_pc_item_code</code></li>
						<li><code>_pc_upc</code></li>
						<li><code>_pc_allergen</code></li>
					</ul>

					<h3><?php _e( 'Excel Import Format', 'dw-product-catalog' ); ?></h3>
					<p><?php _e( 'When importing from Excel, use these column headers:', 'dw-product-catalog' ); ?></p>
					<ul>
						<li><code>post_title</code> - <?php _e( 'Product Title (required)', 'dw-product-catalog' ); ?></li>
						<li><code>post_content</code> - <?php _e( 'Product Description', 'dw-product-catalog' ); ?></li>
						<li><code>post_status</code> - <?php _e( 'Status: publish, draft, or private', 'dw-product-catalog' ); ?></li>
						<li><code>featured_image_url</code> - <?php _e( 'Featured Image URL (will be downloaded and set as featured image)', 'dw-product-catalog' ); ?></li>
						<?php foreach ( $fields as $field ) : ?>
							<?php if ( $field['meta_key'] !== 'featured_image_url' ) : ?>
								<li><code><?php echo esc_html( $field['meta_key'] ); ?></code> - <?php echo esc_html( $field['label'] ); ?></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		</div>
		<?php
	}
}

