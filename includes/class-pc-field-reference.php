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
				'meta_key'    => 'dw_pc_product_name',
				'type'        => 'text',
				'description' => __( 'Product name. Saved as post title.', 'dw-product-catalog' ),
				'required'    => true,
				'example'     => 'Premium Coffee Beans',
			),
			array(
				'label'       => __( 'Category Name', 'dw-product-catalog' ),
				'meta_key'    => 'dw_pc_category_name',
				'type'        => 'text',
				'description' => __( 'Category display name. Created if missing (import/save).', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'Seafood',
			),
			array(
				'label'       => __( 'Category Slug', 'dw-product-catalog' ),
				'meta_key'    => 'dw_pc_category_slug',
				'type'        => 'slug',
				'description' => __( 'Category code / slug. Created if missing (import/save).', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'category-code',
			),
			array(
				'label'       => __( 'Item Code', 'dw-product-catalog' ),
				'meta_key'    => 'dw_pc_item_code',
				'type'        => 'text',
				'description' => __( 'Item code. Used as product URL slug (post slug).', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'ITEM-001',
			),
			array(
				'label'       => __( 'Pack Size / Case Pack', 'dw-product-catalog' ),
				'meta_key'    => 'dw_pc_pack_size_raw',
				'type'        => 'text',
				'description' => __( 'Pack size or case pack', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => '10pc/cs',
			),
			array(
				'label'       => __( 'Brand', 'dw-product-catalog' ),
				'meta_key'    => 'dw_pc_brand_raw',
				'type'        => 'text',
				'description' => __( 'Brand', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'Brand Name',
			),
			array(
				'label'       => __( 'Origin', 'dw-product-catalog' ),
				'meta_key'    => 'dw_pc_origin_raw',
				'type'        => 'text',
				'description' => __( 'Country or region', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'USA, Japan',
			),
			array(
				'label'       => __( 'Status', 'dw-product-catalog' ),
				'meta_key'    => 'dw_pc_status',
				'type'        => 'select',
				'description' => __( 'Active, Inactive, Discontinued', 'dw-product-catalog' ),
				'required'    => false,
				'options'     => array( 'active' => 'Active', 'inactive' => 'Inactive', 'discontinued' => 'Discontinued' ),
				'example'     => 'active',
			),
			array(
				'label'       => __( 'ETC', 'dw-product-catalog' ),
				'meta_key'    => 'dw_pc_internal_note',
				'type'        => 'textarea',
				'description' => __( 'Internal notes (ETC)', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'Note',
			),
			array(
				'label'       => __( 'Featured Image', 'dw-product-catalog' ),
				'meta_key'    => 'featured_image_url',
				'type'        => 'url',
				'description' => __( 'Featured image URL (bulk import)', 'dw-product-catalog' ),
				'required'    => false,
				'example'     => 'https://example.com/image.jpg',
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
					<pre><code>$product_name = get_post_meta( $post_id, 'dw_pc_product_name', true );
$item_code = get_post_meta( $post_id, 'dw_pc_item_code', true );
$brand = get_post_meta( $post_id, 'dw_pc_brand_raw', true );
$status = get_post_meta( $post_id, 'dw_pc_status', true );
$internal_note = get_post_meta( $post_id, 'dw_pc_internal_note', true );</code></pre>

					<h3><?php _e( 'Using Helper Functions', 'dw-product-catalog' ); ?></h3>
					<pre><code>$product_name = PC_Product_Display::get_product_name( $post_id );
$item_code = PC_Product_Display::get_item_code( $post_id );
$pack_size = PC_Product_Display::get_pack_size_raw( $post_id );
$brand = PC_Product_Display::get_brand( $post_id );
$origin = PC_Product_Display::get_origin( $post_id );
$status = PC_Product_Display::get_status( $post_id );
$category_slug = PC_Product_Display::get_category_slug( $post_id );
$internal_note = PC_Product_Display::get_internal_note( $post_id );</code></pre>

					<h3><?php _e( 'Kadence Blocks Pro Dynamic Field', 'dw-product-catalog' ); ?></h3>
					<p><?php _e( 'Use the meta key directly in Kadence Blocks Pro Dynamic Field:', 'dw-product-catalog' ); ?></p>
					<ul>
						<li><code>dw_pc_product_name</code></li>
						<li><code>dw_pc_item_code</code></li>
						<li><code>dw_pc_pack_size_raw</code></li>
						<li><code>dw_pc_brand_raw</code></li>
						<li><code>dw_pc_origin_raw</code></li>
						<li><code>dw_pc_status</code></li>
						<li><code>dw_pc_category_slug</code></li>
						<li><code>dw_pc_internal_note</code></li>
					</ul>

					<h3><?php _e( 'Excel Import Format', 'dw-product-catalog' ); ?></h3>
					<p><?php _e( 'When importing from Excel, use these column headers:', 'dw-product-catalog' ); ?></p>
					<ul>
						<li><code>dw_pc_product_name</code> - <?php _e( 'Product Name (required - will be used as post title)', 'dw-product-catalog' ); ?></li>
						<li><code>post_content</code> - <?php _e( 'Product Description', 'dw-product-catalog' ); ?></li>
						<li><code>post_status</code> - <?php _e( 'Status: publish, draft, or private', 'dw-product-catalog' ); ?></li>
						<li><code>featured_image_url</code> - <?php _e( 'Featured Image URL (will be downloaded and set as featured image)', 'dw-product-catalog' ); ?></li>
						<?php foreach ( $fields as $field ) : ?>
							<?php if ( $field['meta_key'] !== 'featured_image_url' && $field['meta_key'] !== 'dw_pc_product_name' ) : ?>
								<li><code><?php echo esc_html( $field['meta_key'] ); ?></code> - <?php echo esc_html( $field['label'] ); ?></li>
							<?php endif; ?>
						<?php endforeach; ?>
					</ul>
					<p><strong><?php _e( 'Note:', 'dw-product-catalog' ); ?></strong> <?php _e( 'Product Name (dw_pc_product_name) is used as the post title. Item Code (dw_pc_item_code) is used as the product URL slug (post slug). Category Name and Category Slug are created if they do not exist.', 'dw-product-catalog' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}

