<?php
/**
 * Settings Page Class
 *
 * Admin UI for managing custom fields per post type.
 * Post types are defined in code (DWCAT_Config); fields are managed via this UI.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 5 );
		add_action( 'admin_post_dw_catalog_save_fields', array( $this, 'handle_save_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	public function add_admin_menu() {
		add_menu_page(
			__( 'DW Catalog Settings', 'dw-catalog-wp' ),
			__( 'DW Catalog', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-settings',
			array( $this, 'render_page' ),
			'dashicons-database',
			3
		);

		add_submenu_page(
			'dw-catalog-settings',
			__( 'Custom Fields', 'dw-catalog-wp' ),
			__( 'Custom Fields', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-settings',
			array( $this, 'render_page' )
		);

		// Hidden page for field management
		add_submenu_page(
			null,
			__( 'Manage Fields', 'dw-catalog-wp' ),
			__( 'Manage Fields', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-manage-fields',
			array( $this, 'render_manage_fields_page' )
		);
	}

	public function enqueue_scripts( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		$our_pages = array( 'dw-catalog-settings', 'dw-catalog-manage-fields' );
		if ( ! in_array( $page, $our_pages, true ) ) {
			return;
		}
		$config = dwcat_get_config();
		$css_path = dwcat_get_path() . 'assets/css/admin.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'dwcat-admin-style', DWCAT_URL_Helper::get_css_url( 'admin.css' ), array(), $config['plugin_version'] );
		}
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * Render main settings page — lists post types and their field counts.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$post_types = DWCAT_Config::get_post_types();
		?>
		<div class="wrap">
			<h1><?php _e( 'DW Catalog — Custom Fields', 'dw-catalog-wp' ); ?></h1>
			<p class="description"><?php _e( 'Manage custom fields for each post type. Click "Manage Fields" to add, edit, reorder, or remove fields.', 'dw-catalog-wp' ); ?></p>
			<hr class="wp-header-end">

			<?php if ( empty( $post_types ) ) : ?>
				<p><?php _e( 'No post types registered.', 'dw-catalog-wp' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'Post Type', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Slug', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Fields', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Actions', 'dw-catalog-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $post_types as $slug => $pt ) : ?>
							<?php $fields = DWCAT_Config::get_fields( $slug ); ?>
							<tr>
								<td>
									<strong><?php echo esc_html( $pt['singular_name'] ); ?></strong>
									/ <?php echo esc_html( $pt['plural_name'] ); ?>
								</td>
								<td><code><?php echo esc_html( $slug ); ?></code></td>
								<td><?php echo count( $fields ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=dw-catalog-manage-fields&post_type=' . $slug ) ); ?>" class="button">
										<?php _e( 'Manage Fields', 'dw-catalog-wp' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render manage fields page.
	 */
	public function render_manage_fields_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$pt_slug = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
		$pt = DWCAT_Config::get_post_type( $pt_slug );
		if ( ! $pt ) {
			wp_die( __( 'Post type not found.', 'dw-catalog-wp' ) );
		}

		$fields = DWCAT_Config::get_fields( $pt_slug );
		$field_types = DWCAT_Config::get_field_types();
		$saved = isset( $_GET['saved'] ) ? intval( $_GET['saved'] ) : 0;
		?>
		<div class="wrap">
			<h1>
				<?php printf( __( 'Manage Fields — %s', 'dw-catalog-wp' ), esc_html( $pt['singular_name'] ) ); ?>
				<code style="font-size: 14px; margin-left: 8px;"><?php echo esc_html( $pt_slug ); ?></code>
			</h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dw-catalog-settings' ) ); ?>">&larr; <?php _e( 'Back to Custom Fields', 'dw-catalog-wp' ); ?></a>
			</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php _e( 'Fields saved.', 'dw-catalog-wp' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="dw-fields-form">
				<?php wp_nonce_field( 'dw_catalog_save_fields', 'dw_catalog_fields_nonce' ); ?>
				<input type="hidden" name="action" value="dw_catalog_save_fields">
				<input type="hidden" name="post_type_slug" value="<?php echo esc_attr( $pt_slug ); ?>">

				<table class="wp-list-table widefat fixed" id="dw-fields-table">
					<thead>
						<tr>
							<th style="width:30px;"><?php _e( 'Order', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Label', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Meta Key', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Type', 'dw-catalog-wp' ); ?></th>
							<th style="width:60px;"><?php _e( 'Required', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Options (for Select)', 'dw-catalog-wp' ); ?></th>
							<th style="width:60px;"><?php _e( 'List', 'dw-catalog-wp' ); ?></th>
							<th style="width:60px;"><?php _e( 'Export', 'dw-catalog-wp' ); ?></th>
							<th style="width:60px;"><?php _e( 'Title', 'dw-catalog-wp' ); ?></th>
							<th style="width:60px;"><?php _e( 'Remove', 'dw-catalog-wp' ); ?></th>
						</tr>
					</thead>
					<tbody id="dw-fields-body">
						<?php if ( ! empty( $fields ) ) : ?>
							<?php foreach ( $fields as $i => $field ) : ?>
								<?php $this->render_field_row( $i, $field, $field_types ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<p style="margin-top: 12px;">
					<button type="button" class="button" id="dw-add-field"><?php _e( 'Add Field', 'dw-catalog-wp' ); ?></button>
				</p>

				<?php submit_button( __( 'Save Fields', 'dw-catalog-wp' ) ); ?>
			</form>

			<!-- Template row for JS -->
			<table style="display:none;">
				<tbody id="dw-field-template">
					<?php $this->render_field_row( '__INDEX__', array(), $field_types ); ?>
				</tbody>
			</table>

			<script>
			jQuery(function($){
				var idx = <?php echo count( $fields ); ?>;

				$('#dw-add-field').on('click', function(){
					var tpl = $('#dw-field-template').html().replace(/__INDEX__/g, idx);
					$('#dw-fields-body').append(tpl);
					idx++;
				});

				$(document).on('click', '.dw-remove-field', function(){
					$(this).closest('tr').remove();
				});

				$('#dw-fields-body').sortable({
					handle: '.dw-sort-handle',
					axis: 'y',
					cursor: 'move',
					opacity: 0.7
				});
			});
			</script>

			<div style="margin-top: 30px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
				<h3 style="margin-top:0;"><?php _e( 'Field Settings Guide', 'dw-catalog-wp' ); ?></h3>
				<ul style="list-style: disc; padding-left: 20px;">
					<li><strong><?php _e( 'Meta Key', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'The database key for this field. Use lowercase with underscores (e.g., dw_product_price).', 'dw-catalog-wp' ); ?></li>
					<li><strong><?php _e( 'Type', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'Field type: Text, Textarea, Select, Number, Email, URL, Date', 'dw-catalog-wp' ); ?></li>
					<li><strong><?php _e( 'Options', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'For Select type. Format: value:Label,value2:Label2 (e.g., active:Active,inactive:Inactive)', 'dw-catalog-wp' ); ?></li>
					<li><strong><?php _e( 'List', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'Show this field as a column in the admin list page.', 'dw-catalog-wp' ); ?></li>
					<li><strong><?php _e( 'Export', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'Include this field in PDF export options.', 'dw-catalog-wp' ); ?></li>
					<li><strong><?php _e( 'Title', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'Sync this field as the post title. Only ONE field should have this checked.', 'dw-catalog-wp' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single field row in the fields table.
	 */
	private function render_field_row( $index, $field, $field_types ) {
		$meta_key       = isset( $field['meta_key'] ) ? $field['meta_key'] : '';
		$label          = isset( $field['label'] ) ? $field['label'] : '';
		$type           = isset( $field['type'] ) ? $field['type'] : 'text';
		$required       = ! empty( $field['required'] );
		$options        = isset( $field['options'] ) ? $field['options'] : '';
		$show_in_list   = isset( $field['show_in_list'] ) ? ! empty( $field['show_in_list'] ) : true;
		$show_in_export = isset( $field['show_in_export'] ) ? ! empty( $field['show_in_export'] ) : true;
		$is_title_field = ! empty( $field['is_title_field'] );
		$prefix = "fields[{$index}]";
		?>
		<tr>
			<td><span class="dashicons dashicons-menu dw-sort-handle" style="cursor:move; color:#999;"></span></td>
			<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[label]" value="<?php echo esc_attr( $label ); ?>" class="widefat" required placeholder="<?php esc_attr_e( 'Field Label', 'dw-catalog-wp' ); ?>"></td>
			<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[meta_key]" value="<?php echo esc_attr( $meta_key ); ?>" class="widefat" required placeholder="<?php esc_attr_e( 'meta_key', 'dw-catalog-wp' ); ?>" pattern="[a-zA-Z_][a-zA-Z0-9_]*"></td>
			<td>
				<select name="<?php echo esc_attr( $prefix ); ?>[type]" class="widefat">
					<?php foreach ( $field_types as $t_key => $t_label ) : ?>
						<option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $type, $t_key ); ?>><?php echo esc_html( $t_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td style="text-align:center;"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[required]" value="1" <?php checked( $required ); ?>></td>
			<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[options]" value="<?php echo esc_attr( $options ); ?>" class="widefat" placeholder="val:Label,val2:Label2"></td>
			<td style="text-align:center;"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[show_in_list]" value="1" <?php checked( $show_in_list ); ?>></td>
			<td style="text-align:center;"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[show_in_export]" value="1" <?php checked( $show_in_export ); ?>></td>
			<td style="text-align:center;"><input type="radio" name="title_field" value="<?php echo esc_attr( $index ); ?>" <?php checked( $is_title_field ); ?>></td>
			<td style="text-align:center;"><button type="button" class="button-link dw-remove-field" style="color:#b32d2e;">&times;</button></td>
		</tr>
		<?php
	}

	/**
	 * Handle save fields form.
	 */
	public function handle_save_fields() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		check_admin_referer( 'dw_catalog_save_fields', 'dw_catalog_fields_nonce' );

		$pt_slug = sanitize_key( $_POST['post_type_slug'] );
		if ( ! DWCAT_Config::get_post_type( $pt_slug ) ) {
			wp_die( __( 'Post type not found.', 'dw-catalog-wp' ) );
		}

		$raw_fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? $_POST['fields'] : array();
		$title_field_index = isset( $_POST['title_field'] ) ? sanitize_text_field( $_POST['title_field'] ) : '';

		$fields = array();
		foreach ( $raw_fields as $index => $raw ) {
			$meta_key = isset( $raw['meta_key'] ) ? sanitize_key( $raw['meta_key'] ) : '';
			$label    = isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : '';
			if ( empty( $meta_key ) || empty( $label ) ) {
				continue;
			}
			$fields[] = array(
				'meta_key'       => $meta_key,
				'label'          => $label,
				'type'           => isset( $raw['type'] ) ? sanitize_key( $raw['type'] ) : 'text',
				'required'       => ! empty( $raw['required'] ),
				'options'        => isset( $raw['options'] ) ? sanitize_text_field( $raw['options'] ) : '',
				'description'    => '',
				'show_in_list'   => ! empty( $raw['show_in_list'] ),
				'show_in_export' => ! empty( $raw['show_in_export'] ),
				'is_title_field' => ( (string) $index === (string) $title_field_index ),
			);
		}

		DWCAT_Config::save_fields( $pt_slug, $fields );

		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-manage-fields&post_type=' . $pt_slug . '&saved=1' ) );
		exit;
	}
}
