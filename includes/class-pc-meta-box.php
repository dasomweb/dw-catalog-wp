<?php
/**
 * Meta Box Class
 *
 * Dynamically renders custom meta boxes based on PC_Config fields.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PC_Meta_Box {

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Add meta boxes for all registered post types.
	 */
	public function add_meta_boxes() {
		$post_types = PC_Config::get_post_types();
		foreach ( $post_types as $slug => $config ) {
			$fields = PC_Config::get_fields( $slug );
			if ( empty( $fields ) ) {
				continue;
			}
			add_meta_box(
				'dw_catalog_' . $slug . '_details',
				sprintf( __( '%s Details', 'dw-catalog-wp' ), $config['singular_name'] ),
				array( $this, 'render_meta_box' ),
				$slug,
				'normal',
				'high',
				array( 'post_type_slug' => $slug )
			);
		}
	}

	/**
	 * Render the meta box for a post type.
	 */
	public function render_meta_box( $post, $metabox ) {
		$pt_slug = $metabox['args']['post_type_slug'];
		$fields = PC_Config::get_fields( $pt_slug );

		wp_nonce_field( 'dw_catalog_save_meta_' . $pt_slug, 'dw_catalog_meta_nonce' );
		?>
		<div class="pc-product-fields">
			<table class="form-table">
				<tbody>
					<?php foreach ( $fields as $field ) : ?>
						<?php $this->render_field( $post, $field ); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render a single field row.
	 */
	private function render_field( $post, $field ) {
		$meta_key = $field['meta_key'];
		$value = get_post_meta( $post->ID, $meta_key, true );
		$type = $field['type'];
		$label = $field['label'];
		$input_id = 'dw_field_' . sanitize_key( $meta_key );

		// For title field, default to post_title
		if ( ! empty( $field['is_title_field'] ) && $value === '' ) {
			$value = $post->post_title;
		}
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $input_id ); ?>">
					<?php echo esc_html( $label ); ?>
					<?php if ( ! empty( $field['required'] ) ) : ?>
						<span style="color:#d63638;">*</span>
					<?php endif; ?>
				</label>
			</th>
			<td>
				<?php
				switch ( $type ) {
					case 'textarea':
						printf(
							'<textarea id="%s" name="%s" rows="4" class="large-text">%s</textarea>',
							esc_attr( $input_id ),
							esc_attr( $meta_key ),
							esc_textarea( $value )
						);
						break;

					case 'select':
						$options = PC_Config::parse_select_options( $field['options'] );
						printf( '<select id="%s" name="%s">', esc_attr( $input_id ), esc_attr( $meta_key ) );
						echo '<option value="">' . esc_html__( '— Select —', 'dw-catalog-wp' ) . '</option>';
						foreach ( $options as $opt_val => $opt_label ) {
							printf(
								'<option value="%s"%s>%s</option>',
								esc_attr( $opt_val ),
								selected( $value, $opt_val, false ),
								esc_html( $opt_label )
							);
						}
						echo '</select>';
						break;

					case 'number':
						printf(
							'<input type="number" id="%s" name="%s" value="%s" class="regular-text" step="any" />',
							esc_attr( $input_id ),
							esc_attr( $meta_key ),
							esc_attr( $value )
						);
						break;

					case 'email':
						printf(
							'<input type="email" id="%s" name="%s" value="%s" class="regular-text" />',
							esc_attr( $input_id ),
							esc_attr( $meta_key ),
							esc_attr( $value )
						);
						break;

					case 'url':
						printf(
							'<input type="url" id="%s" name="%s" value="%s" class="regular-text" />',
							esc_attr( $input_id ),
							esc_attr( $meta_key ),
							esc_attr( $value )
						);
						break;

					case 'date':
						printf(
							'<input type="date" id="%s" name="%s" value="%s" class="regular-text" />',
							esc_attr( $input_id ),
							esc_attr( $meta_key ),
							esc_attr( $value )
						);
						break;

					case 'text':
					default:
						printf(
							'<input type="text" id="%s" name="%s" value="%s" class="regular-text" />',
							esc_attr( $input_id ),
							esc_attr( $meta_key ),
							esc_attr( $value )
						);
						break;
				}

				if ( ! empty( $field['description'] ) ) {
					echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Save meta for any of our post types.
	 */
	public function save_meta( $post_id, $post ) {
		$pt_slug = $post->post_type;
		if ( ! PC_Config::is_our_post_type( $pt_slug ) ) {
			return;
		}

		if ( ! isset( $_POST['dw_catalog_meta_nonce'] ) || ! wp_verify_nonce( $_POST['dw_catalog_meta_nonce'], 'dw_catalog_save_meta_' . $pt_slug ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = PC_Config::get_fields( $pt_slug );
		$title_value = '';

		foreach ( $fields as $field ) {
			$meta_key = $field['meta_key'];

			if ( isset( $_POST[ $meta_key ] ) ) {
				$raw_value = $_POST[ $meta_key ];
				if ( $field['type'] === 'textarea' ) {
					$value = sanitize_textarea_field( $raw_value );
				} elseif ( $field['type'] === 'email' ) {
					$value = sanitize_email( $raw_value );
				} elseif ( $field['type'] === 'url' ) {
					$value = esc_url_raw( $raw_value );
				} elseif ( $field['type'] === 'number' ) {
					$value = is_numeric( $raw_value ) ? $raw_value : '';
				} else {
					$value = sanitize_text_field( $raw_value );
				}
				update_post_meta( $post_id, $meta_key, $value );

				// Track title field
				if ( ! empty( $field['is_title_field'] ) ) {
					$title_value = trim( $value );
				}
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
		}

		// Sync title field to post_title
		if ( $title_value !== '' ) {
			remove_action( 'save_post', array( $this, 'save_meta' ), 10 );
			wp_update_post( array(
				'ID'         => $post_id,
				'post_title' => $title_value,
			) );
			add_action( 'save_post', array( $this, 'save_meta' ), 10, 2 );
		}

		// Handle category taxonomy fields (category_name / category_slug pattern)
		$pt_config = PC_Config::get_post_type( $pt_slug );
		if ( ! empty( $pt_config['has_category'] ) ) {
			$cat_taxonomy = PC_Config::get_category_taxonomy( $pt_slug );
			$cat_name = isset( $_POST['dw_catalog_category_name'] ) ? trim( sanitize_text_field( $_POST['dw_catalog_category_name'] ) ) : '';
			$cat_slug = isset( $_POST['dw_catalog_category_slug'] ) ? trim( sanitize_text_field( $_POST['dw_catalog_category_slug'] ) ) : '';
			if ( $cat_name !== '' || $cat_slug !== '' ) {
				$term_id = pc_get_or_create_term( $cat_name, $cat_slug, $cat_taxonomy );
				if ( $term_id ) {
					wp_set_object_terms( $post_id, array( $term_id ), $cat_taxonomy );
				}
			}
		}
	}

	/**
	 * Enqueue admin scripts for our post types.
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		global $post_type;
		if ( ! PC_Config::is_our_post_type( $post_type ) ) {
			return;
		}

		$config = pc_get_plugin_config();
		$css_path = pc_get_plugin_path() . 'assets/css/admin.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'pc-admin-style', PC_URL_Helper::get_css_url( 'admin.css' ), array(), $config['plugin_version'] );
		}

		// Title field sync: if a title field is defined, sync it to post_title
		$title_field = PC_Config::get_title_field( $post_type );
		if ( $title_field ) {
			wp_enqueue_script( 'jquery' );
			$field_id = 'dw_field_' . sanitize_key( $title_field['meta_key'] );
			$inline = 'jQuery(function($){var $f=$("#' . esc_js( $field_id ) . '"),$t=$("#post_title");if($f.length&&$t.length){$f.on("input",function(){var v=$(this).val();if(v)$t.val(v);});if($f.val()&&!$t.val())$t.val($f.val());}});';
			wp_add_inline_script( 'jquery', $inline, 'after' );
		}
	}

	/**
	 * Static helper: get a meta value with default.
	 */
	public static function get_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return ! empty( $value ) ? $value : $default;
	}

	public static function display_product_meta( $post_id, $meta_key, $default = '' ) {
		$value = self::get_product_meta( $post_id, $meta_key, $default );
		echo esc_html( $value );
	}
}
