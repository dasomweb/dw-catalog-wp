<?php
/**
 * Product Meta Box Class
 *
 * Handles custom meta boxes for product fields (default post editor).
 *
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PC_Meta_Box {

	private $post_type = 'product';

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . $this->post_type, array( $this, 'save_product_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'pc_product_details',
			__( 'Product Details', 'dw-product-catalog' ),
			array( $this, 'render_product_details_meta_box' ),
			$this->post_type,
			'normal',
			'high'
		);
	}

	public function render_product_details_meta_box( $post ) {
		wp_nonce_field( 'pc_save_product_meta', 'pc_product_meta_nonce' );

		$product_name  = get_post_meta( $post->ID, 'dw_pc_product_name', true );
		$item_code     = get_post_meta( $post->ID, 'dw_pc_item_code', true );
		$pack_size_raw = get_post_meta( $post->ID, 'dw_pc_pack_size_raw', true );
		$brand_raw     = get_post_meta( $post->ID, 'dw_pc_brand_raw', true );
		$origin_raw    = get_post_meta( $post->ID, 'dw_pc_origin_raw', true );
		$status        = get_post_meta( $post->ID, 'dw_pc_status', true );
		$category_slug = get_post_meta( $post->ID, 'dw_pc_category_slug', true );
		$internal_note = get_post_meta( $post->ID, 'dw_pc_internal_note', true );
		?>
		<div class="pc-product-fields">
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="pc_product_name"><?php _e( 'Product Name', 'dw-product-catalog' ); ?></label></th>
						<td><input type="text" id="pc_product_name" name="pc_product_name" value="<?php echo esc_attr( $product_name ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pc_item_code"><?php _e( 'Item Code', 'dw-product-catalog' ); ?></label></th>
						<td><input type="text" id="pc_item_code" name="pc_item_code" value="<?php echo esc_attr( $item_code ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pc_pack_size_raw"><?php _e( 'Pack Size / Case Pack', 'dw-product-catalog' ); ?></label></th>
						<td><input type="text" id="pc_pack_size_raw" name="pc_pack_size_raw" value="<?php echo esc_attr( $pack_size_raw ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pc_brand_raw"><?php _e( 'Brand', 'dw-product-catalog' ); ?></label></th>
						<td><input type="text" id="pc_brand_raw" name="pc_brand_raw" value="<?php echo esc_attr( $brand_raw ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pc_origin_raw"><?php _e( 'Origin', 'dw-product-catalog' ); ?></label></th>
						<td><input type="text" id="pc_origin_raw" name="pc_origin_raw" value="<?php echo esc_attr( $origin_raw ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pc_status"><?php _e( 'Status', 'dw-product-catalog' ); ?></label></th>
						<td>
							<select name="pc_status" id="pc_status">
								<option value="" <?php selected( $status, '' ); ?>><?php _e( '— Select —', 'dw-product-catalog' ); ?></option>
								<option value="active" <?php selected( $status, 'active' ); ?>><?php _e( 'Active', 'dw-product-catalog' ); ?></option>
								<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php _e( 'Inactive', 'dw-product-catalog' ); ?></option>
								<option value="discontinued" <?php selected( $status, 'discontinued' ); ?>><?php _e( 'Discontinued', 'dw-product-catalog' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="pc_category_slug"><?php _e( 'Category Slug', 'dw-product-catalog' ); ?></label></th>
						<td><input type="text" id="pc_category_slug" name="pc_category_slug" value="<?php echo esc_attr( $category_slug ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="pc_internal_note"><?php _e( 'ETC', 'dw-product-catalog' ); ?></label></th>
						<td><textarea id="pc_internal_note" name="pc_internal_note" rows="4" class="large-text"><?php echo esc_textarea( $internal_note ); ?></textarea></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function save_product_meta( $post_id, $post ) {
		if ( ! isset( $_POST['pc_product_meta_nonce'] ) || ! wp_verify_nonce( $_POST['pc_product_meta_nonce'], 'pc_save_product_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) || $this->post_type !== $post->post_type ) {
			return;
		}

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
				update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $field_name ] ) );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}
		if ( isset( $_POST['pc_internal_note'] ) ) {
			update_post_meta( $post_id, 'dw_pc_internal_note', sanitize_textarea_field( $_POST['pc_internal_note'] ) );
		} else {
			delete_post_meta( $post_id, 'dw_pc_internal_note' );
		}
	}

	public function enqueue_admin_scripts( $hook ) {
		global $post_type;
		if ( $this->post_type !== $post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$config = pc_get_plugin_config();
		$css_url = PC_URL_Helper::get_css_url( 'admin.css' );
		$css_path = pc_get_plugin_path() . 'assets/css/admin.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'pc-admin-style', $css_url, array(), $config['plugin_version'] );
		}
	}

	public static function get_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return ! empty( $value ) ? $value : $default;
	}

	public static function display_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = self::get_product_meta( $post_id, $meta_key, $default );
		echo esc_html( $value );
	}
}
