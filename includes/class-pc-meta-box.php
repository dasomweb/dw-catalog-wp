<?php
/**
 * Product Meta Box Class
 * 
 * Handles custom meta boxes for product fields.
 * Domain-agnostic implementation.
 * 
 * @package DW_Product_Catalog
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PC_Meta_Box Class
 * 
 * Manages product meta boxes and custom fields.
 */
class PC_Meta_Box {

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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . $this->post_type, array( $this, 'save_product_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add meta boxes
	 */
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

	/**
	 * Render product details meta box
	 * 
	 * @param WP_Post $post Current post object
	 */
	public function render_product_details_meta_box( $post ) {
		// Add nonce for security
		wp_nonce_field( 'pc_save_product_meta', 'pc_product_meta_nonce' );

		// Get existing values
		$product_name = get_post_meta( $post->ID, '_pc_product_name', true );
		$brand        = get_post_meta( $post->ID, '_pc_brand', true );
		$item_code    = get_post_meta( $post->ID, '_pc_item_code', true );
		$upc          = get_post_meta( $post->ID, '_pc_upc', true );
		$temperature  = get_post_meta( $post->ID, '_pc_temperature', true );
		$allergen     = get_post_meta( $post->ID, '_pc_allergen', true );

		// Temperature options
		$temperature_options = array(
			''           => __( 'Select', 'dw-product-catalog' ),
			'room'       => __( 'Room Temperature', 'dw-product-catalog' ),
			'cold'       => __( 'Refrigerated', 'dw-product-catalog' ),
			'frozen'     => __( 'Frozen', 'dw-product-catalog' ),
			'freezer'    => __( 'Freezer', 'dw-product-catalog' ),
		);
		?>
		<div class="pc-product-fields">
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
		<?php
	}

	/**
	 * Save product meta data
	 * 
	 * @param int     $post_id Post ID
	 * @param WP_Post $post    Post object
	 */
	public function save_product_meta( $post_id, $post ) {
		// Check if nonce is set
		if ( ! isset( $_POST['pc_product_meta_nonce'] ) ) {
			return;
		}

		// Verify nonce
		if ( ! wp_verify_nonce( $_POST['pc_product_meta_nonce'], 'pc_save_product_meta' ) ) {
			return;
		}

		// Check if this is an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Check user permissions
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Check post type
		if ( $this->post_type !== $post->post_type ) {
			return;
		}

		// Sanitize and save fields
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
						// Sanitize select field
						$value = sanitize_text_field( $value );
						$allowed_values = array( '', 'room', 'cold', 'frozen', 'freezer' );
						if ( ! in_array( $value, $allowed_values, true ) ) {
							$value = '';
						}
						break;
					
					case 'pc_allergen':
						// Sanitize textarea (comma-separated)
						$value = sanitize_textarea_field( $value );
						break;
					
					default:
						// Sanitize text fields
						$value = sanitize_text_field( $value );
						break;
				}
				
				// Save meta (domain agnostic - only stores values, not URLs)
				update_post_meta( $post_id, $meta_key, $value );
			} else {
				// Delete meta if field is empty
				delete_post_meta( $post_id, $meta_key );
			}
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 * 
	 * @param string $hook Current admin page hook
	 */
	public function enqueue_admin_scripts( $hook ) {
		global $post_type;

		// Only load on product edit pages
		if ( $this->post_type !== $post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// Enqueue admin CSS (if needed)
		$config = pc_get_plugin_config();
		$css_url = PC_URL_Helper::get_css_url( 'admin.css' );
		
		// Check if file exists before enqueuing
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
	 * Get product meta value (helper function for output)
	 * 
	 * @param int    $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed  $default Default value
	 * @return mixed Meta value
	 */
	public static function get_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return ! empty( $value ) ? $value : $default;
	}

	/**
	 * Display product meta value (escaped for output)
	 * 
	 * @param int    $post_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed  $default Default value
	 * @return void
	 */
	public static function display_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = self::get_product_meta( $post_id, $meta_key, $default );
		echo esc_html( $value );
	}
}

