<?php
/**
 * Settings Page Class
 *
 * Admin UI for managing dynamic post types and custom fields.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PC_Settings {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ), 5 );
		add_action( 'admin_post_dw_catalog_save_post_type', array( $this, 'handle_save_post_type' ) );
		add_action( 'admin_post_dw_catalog_delete_post_type', array( $this, 'handle_delete_post_type' ) );
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
			__( 'Post Types', 'dw-catalog-wp' ),
			__( 'Post Types', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-settings',
			array( $this, 'render_page' )
		);

		add_submenu_page(
			'dw-catalog-settings',
			__( 'Add Post Type', 'dw-catalog-wp' ),
			__( 'Add Post Type', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-add-post-type',
			array( $this, 'render_add_post_type_page' )
		);

		// Hidden pages for edit
		add_submenu_page(
			null,
			__( 'Edit Post Type', 'dw-catalog-wp' ),
			__( 'Edit Post Type', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-edit-post-type',
			array( $this, 'render_edit_post_type_page' )
		);

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
		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$our_pages = array( 'dw-catalog-settings', 'dw-catalog-add-post-type', 'dw-catalog-edit-post-type', 'dw-catalog-manage-fields' );
		if ( ! in_array( $page, $our_pages, true ) ) {
			return;
		}
		$config = pc_get_plugin_config();
		$css_path = pc_get_plugin_path() . 'assets/css/admin.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'pc-admin-style', PC_URL_Helper::get_css_url( 'admin.css' ), array(), $config['plugin_version'] );
		}
		// Inline JS for field management
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'jquery-ui-sortable' );
	}

	/**
	 * Render post types list page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$post_types = PC_Config::get_post_types();
		$deleted = isset( $_GET['deleted'] ) ? intval( $_GET['deleted'] ) : 0;
		$saved = isset( $_GET['saved'] ) ? intval( $_GET['saved'] ) : 0;
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php _e( 'DW Catalog — Post Types', 'dw-catalog-wp' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=dw-catalog-add-post-type' ) ); ?>" class="page-title-action">
				<?php _e( 'Add New Post Type', 'dw-catalog-wp' ); ?>
			</a>
			<hr class="wp-header-end">

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php _e( 'Post type saved.', 'dw-catalog-wp' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $deleted ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php _e( 'Post type deleted.', 'dw-catalog-wp' ); ?></p></div>
			<?php endif; ?>

			<?php if ( empty( $post_types ) ) : ?>
				<p><?php _e( 'No post types registered yet.', 'dw-catalog-wp' ); ?></p>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'Slug', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Singular Name', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Plural Name', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Menu Name', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Icon', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Fields', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Category', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Tag', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Actions', 'dw-catalog-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $post_types as $slug => $pt ) : ?>
							<?php $fields = PC_Config::get_fields( $slug ); ?>
							<tr>
								<td><code><?php echo esc_html( $slug ); ?></code></td>
								<td><?php echo esc_html( $pt['singular_name'] ); ?></td>
								<td><?php echo esc_html( $pt['plural_name'] ); ?></td>
								<td><?php echo esc_html( $pt['menu_name'] ); ?></td>
								<td><span class="dashicons <?php echo esc_attr( $pt['menu_icon'] ); ?>"></span></td>
								<td><?php echo count( $fields ); ?></td>
								<td><?php echo ! empty( $pt['has_category'] ) ? '&#10003;' : '—'; ?></td>
								<td><?php echo ! empty( $pt['has_tag'] ) ? '&#10003;' : '—'; ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=dw-catalog-edit-post-type&slug=' . $slug ) ); ?>">
										<?php _e( 'Edit', 'dw-catalog-wp' ); ?>
									</a> |
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=dw-catalog-manage-fields&post_type=' . $slug ) ); ?>">
										<?php _e( 'Fields', 'dw-catalog-wp' ); ?>
									</a> |
									<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dw_catalog_delete_post_type&slug=' . $slug ), 'dw_catalog_delete_pt_' . $slug ) ); ?>"
									   onclick="return confirm('<?php esc_attr_e( 'Delete this post type? Existing posts will NOT be deleted, but they will no longer be accessible through this plugin.', 'dw-catalog-wp' ); ?>');"
									   style="color: #b32d2e;">
										<?php _e( 'Delete', 'dw-catalog-wp' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<div style="margin-top: 30px; padding: 15px; background: #fff; border: 1px solid #ccd0d4;">
				<h3 style="margin-top:0;"><?php _e( 'How It Works', 'dw-catalog-wp' ); ?></h3>
				<ol>
					<li><?php _e( 'Create a Post Type (e.g., "Equipment", "Menu Item", "Service")', 'dw-catalog-wp' ); ?></li>
					<li><?php _e( 'Add Custom Fields to it (e.g., "Price", "Color", "SKU")', 'dw-catalog-wp' ); ?></li>
					<li><?php _e( 'Each post type gets its own admin menu, list page, bulk import, PDF export, and field reference.', 'dw-catalog-wp' ); ?></li>
				</ol>
				<p class="description"><?php _e( 'After adding or removing a post type, visit Settings > Permalinks to flush rewrite rules.', 'dw-catalog-wp' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render add post type page.
	 */
	public function render_add_post_type_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		$this->render_post_type_form( null );
	}

	/**
	 * Render edit post type page.
	 */
	public function render_edit_post_type_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
		$pt = PC_Config::get_post_type( $slug );
		if ( ! $pt ) {
			wp_die( __( 'Post type not found.', 'dw-catalog-wp' ) );
		}
		$this->render_post_type_form( $pt );
	}

	/**
	 * Render the post type add/edit form.
	 *
	 * @param array|null $pt Existing post type data, or null for new.
	 */
	private function render_post_type_form( $pt ) {
		$is_edit = ! empty( $pt );
		$slug           = $is_edit ? $pt['slug'] : '';
		$singular_name  = $is_edit ? $pt['singular_name'] : '';
		$plural_name    = $is_edit ? $pt['plural_name'] : '';
		$menu_name      = $is_edit ? $pt['menu_name'] : '';
		$menu_icon      = $is_edit ? $pt['menu_icon'] : 'dashicons-admin-generic';
		$has_archive    = $is_edit ? ! empty( $pt['has_archive'] ) : true;
		$is_public      = $is_edit ? ! empty( $pt['public'] ) : true;
		$show_in_rest   = $is_edit ? ! empty( $pt['show_in_rest'] ) : true;
		$has_category   = $is_edit ? ! empty( $pt['has_category'] ) : true;
		$has_tag        = $is_edit ? ! empty( $pt['has_tag'] ) : true;
		$supports_arr   = $is_edit && ! empty( $pt['supports'] ) ? $pt['supports'] : array( 'title', 'editor', 'thumbnail' );
		$icons = PC_Config::get_menu_icons();
		$all_supports = array(
			'title'         => __( 'Title', 'dw-catalog-wp' ),
			'editor'        => __( 'Editor', 'dw-catalog-wp' ),
			'thumbnail'     => __( 'Featured Image', 'dw-catalog-wp' ),
			'excerpt'       => __( 'Excerpt', 'dw-catalog-wp' ),
			'custom-fields' => __( 'Custom Fields', 'dw-catalog-wp' ),
			'revisions'     => __( 'Revisions', 'dw-catalog-wp' ),
			'page-attributes' => __( 'Page Attributes', 'dw-catalog-wp' ),
		);
		?>
		<div class="wrap">
			<h1><?php echo $is_edit ? __( 'Edit Post Type', 'dw-catalog-wp' ) : __( 'Add New Post Type', 'dw-catalog-wp' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'dw_catalog_save_pt', 'dw_catalog_pt_nonce' ); ?>
				<input type="hidden" name="action" value="dw_catalog_save_post_type">
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="original_slug" value="<?php echo esc_attr( $slug ); ?>">
				<?php endif; ?>

				<table class="form-table">
					<tr>
						<th><label for="pt_slug"><?php _e( 'Slug (ID)', 'dw-catalog-wp' ); ?></label></th>
						<td>
							<input type="text" id="pt_slug" name="pt_slug" value="<?php echo esc_attr( $slug ); ?>"
								   class="regular-text" pattern="[a-z][a-z0-9_]{0,19}" maxlength="20" required
								   <?php echo $is_edit ? 'readonly' : ''; ?>>
							<p class="description"><?php _e( 'Lowercase letters, numbers, underscores. Max 20 chars. Cannot be changed after creation.', 'dw-catalog-wp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="pt_singular"><?php _e( 'Singular Name', 'dw-catalog-wp' ); ?></label></th>
						<td><input type="text" id="pt_singular" name="pt_singular" value="<?php echo esc_attr( $singular_name ); ?>" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="pt_plural"><?php _e( 'Plural Name', 'dw-catalog-wp' ); ?></label></th>
						<td><input type="text" id="pt_plural" name="pt_plural" value="<?php echo esc_attr( $plural_name ); ?>" class="regular-text" required></td>
					</tr>
					<tr>
						<th><label for="pt_menu_name"><?php _e( 'Menu Name', 'dw-catalog-wp' ); ?></label></th>
						<td>
							<input type="text" id="pt_menu_name" name="pt_menu_name" value="<?php echo esc_attr( $menu_name ); ?>" class="regular-text">
							<p class="description"><?php _e( 'Displayed in admin sidebar. Defaults to Plural Name.', 'dw-catalog-wp' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label><?php _e( 'Menu Icon', 'dw-catalog-wp' ); ?></label></th>
						<td>
							<?php foreach ( $icons as $icon_class => $icon_label ) : ?>
								<label style="display:inline-block; margin: 4px 8px 4px 0; cursor:pointer;">
									<input type="radio" name="pt_menu_icon" value="<?php echo esc_attr( $icon_class ); ?>"
										   <?php checked( $menu_icon, $icon_class ); ?> style="display:none;">
									<span class="dashicons <?php echo esc_attr( $icon_class ); ?>"
										  style="font-size: 24px; width: 24px; height: 24px; padding: 4px; border: 2px solid transparent; border-radius: 4px; cursor: pointer;"
										  title="<?php echo esc_attr( $icon_label ); ?>"></span>
								</label>
							<?php endforeach; ?>
							<style>
								input[name="pt_menu_icon"]:checked + .dashicons { border-color: #2271b1; background: #f0f6fc; }
							</style>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Supports', 'dw-catalog-wp' ); ?></th>
						<td>
							<?php foreach ( $all_supports as $sup_key => $sup_label ) : ?>
								<label style="display: block; margin-bottom: 6px;">
									<input type="checkbox" name="pt_supports[]" value="<?php echo esc_attr( $sup_key ); ?>"
										   <?php checked( in_array( $sup_key, $supports_arr, true ) ); ?>>
									<?php echo esc_html( $sup_label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Options', 'dw-catalog-wp' ); ?></th>
						<td>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="pt_public" value="1" <?php checked( $is_public ); ?>>
								<?php _e( 'Public (visible on front-end)', 'dw-catalog-wp' ); ?>
							</label>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="pt_has_archive" value="1" <?php checked( $has_archive ); ?>>
								<?php _e( 'Has Archive', 'dw-catalog-wp' ); ?>
							</label>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="pt_show_in_rest" value="1" <?php checked( $show_in_rest ); ?>>
								<?php _e( 'Show in REST API (Gutenberg support)', 'dw-catalog-wp' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php _e( 'Taxonomies', 'dw-catalog-wp' ); ?></th>
						<td>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="pt_has_category" value="1" <?php checked( $has_category ); ?>>
								<?php _e( 'Category (hierarchical)', 'dw-catalog-wp' ); ?>
							</label>
							<label style="display: block; margin-bottom: 6px;">
								<input type="checkbox" name="pt_has_tag" value="1" <?php checked( $has_tag ); ?>>
								<?php _e( 'Tag (flat)', 'dw-catalog-wp' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( $is_edit ? __( 'Update Post Type', 'dw-catalog-wp' ) : __( 'Create Post Type', 'dw-catalog-wp' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Handle save post type form.
	 */
	public function handle_save_post_type() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		check_admin_referer( 'dw_catalog_save_pt', 'dw_catalog_pt_nonce' );

		$original_slug = isset( $_POST['original_slug'] ) ? sanitize_key( $_POST['original_slug'] ) : '';
		$slug = sanitize_key( $_POST['pt_slug'] );

		if ( empty( $slug ) || ! preg_match( '/^[a-z][a-z0-9_]{0,19}$/', $slug ) ) {
			wp_die( __( 'Invalid slug. Use lowercase letters, numbers, and underscores (max 20 chars).', 'dw-catalog-wp' ) );
		}

		// Prevent conflict with WordPress built-in post types
		$reserved = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request', 'wp_block', 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' );
		if ( in_array( $slug, $reserved, true ) ) {
			wp_die( __( 'This slug is reserved by WordPress.', 'dw-catalog-wp' ) );
		}

		// Check for duplicate slug on new post types
		if ( empty( $original_slug ) ) {
			$existing = PC_Config::get_post_type( $slug );
			if ( $existing ) {
				wp_die( __( 'A post type with this slug already exists.', 'dw-catalog-wp' ) );
			}
		}

		$singular = sanitize_text_field( $_POST['pt_singular'] );
		$plural   = sanitize_text_field( $_POST['pt_plural'] );
		$menu     = sanitize_text_field( $_POST['pt_menu_name'] );
		if ( empty( $menu ) ) {
			$menu = $plural;
		}

		$config = array(
			'slug'          => $slug,
			'singular_name' => $singular,
			'plural_name'   => $plural,
			'menu_name'     => $menu,
			'menu_icon'     => sanitize_text_field( $_POST['pt_menu_icon'] ),
			'has_archive'   => ! empty( $_POST['pt_has_archive'] ),
			'public'        => ! empty( $_POST['pt_public'] ),
			'show_in_rest'  => ! empty( $_POST['pt_show_in_rest'] ),
			'supports'      => isset( $_POST['pt_supports'] ) ? array_map( 'sanitize_key', $_POST['pt_supports'] ) : array( 'title' ),
			'has_category'  => ! empty( $_POST['pt_has_category'] ),
			'has_tag'       => ! empty( $_POST['pt_has_tag'] ),
		);

		PC_Config::save_post_type( $slug, $config );

		// Flush rewrite rules
		flush_rewrite_rules();

		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-settings&saved=1' ) );
		exit;
	}

	/**
	 * Handle delete post type.
	 */
	public function handle_delete_post_type() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}
		$slug = isset( $_GET['slug'] ) ? sanitize_key( $_GET['slug'] ) : '';
		check_admin_referer( 'dw_catalog_delete_pt_' . $slug );

		if ( empty( $slug ) ) {
			wp_die( __( 'Invalid post type.', 'dw-catalog-wp' ) );
		}

		PC_Config::delete_post_type( $slug );
		flush_rewrite_rules();

		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-settings&deleted=1' ) );
		exit;
	}

	/**
	 * Render manage fields page.
	 */
	public function render_manage_fields_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$pt_slug = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
		$pt = PC_Config::get_post_type( $pt_slug );
		if ( ! $pt ) {
			wp_die( __( 'Post type not found.', 'dw-catalog-wp' ) );
		}

		$fields = PC_Config::get_fields( $pt_slug );
		$field_types = PC_Config::get_field_types();
		$saved = isset( $_GET['saved'] ) ? intval( $_GET['saved'] ) : 0;
		?>
		<div class="wrap">
			<h1>
				<?php printf( __( 'Manage Fields — %s', 'dw-catalog-wp' ), esc_html( $pt['singular_name'] ) ); ?>
				<code style="font-size: 14px; margin-left: 8px;"><?php echo esc_html( $pt_slug ); ?></code>
			</h1>
			<p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dw-catalog-settings' ) ); ?>">&larr; <?php _e( 'Back to Post Types', 'dw-catalog-wp' ); ?></a>
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
					<li><strong><?php _e( 'Meta Key', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'The database key for this field. Use lowercase with underscores (e.g., dw_product_price). Cannot conflict with other field keys.', 'dw-catalog-wp' ); ?></li>
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
		if ( ! PC_Config::get_post_type( $pt_slug ) ) {
			wp_die( __( 'Post type not found.', 'dw-catalog-wp' ) );
		}

		$raw_fields = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? $_POST['fields'] : array();
		$title_field_index = isset( $_POST['title_field'] ) ? sanitize_text_field( $_POST['title_field'] ) : '';

		$fields = array();
		$counter = 0;
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
			$counter++;
		}

		PC_Config::save_fields( $pt_slug, $fields );

		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-manage-fields&post_type=' . $pt_slug . '&saved=1' ) );
		exit;
	}
}
