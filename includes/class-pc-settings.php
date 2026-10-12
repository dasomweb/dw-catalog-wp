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
		add_filter( 'parent_file', array( $this, 'highlight_parent_menu' ) );
	}

	public function add_admin_menu() {
		// Hidden page for field management — reached via each post type menu's "Custom Fields"
		add_submenu_page(
			'',
			__( 'Manage Fields', 'dw-catalog-wp' ),
			__( 'Manage Fields', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-manage-fields',
			array( $this, 'render_manage_fields_page' )
		);

		// Pre-2.0 top-level settings page — kept hidden so old bookmarks redirect instead of erroring
		$legacy_hook = add_submenu_page(
			'',
			__( 'Custom Fields', 'dw-catalog-wp' ),
			__( 'Custom Fields', 'dw-catalog-wp' ),
			'manage_options',
			'dw-catalog-settings',
			'__return_null'
		);
		if ( $legacy_hook ) {
			add_action( 'load-' . $legacy_hook, array( $this, 'redirect_legacy_page' ) );
		}
	}

	/**
	 * Submenu slug (a direct admin URL) of the manage-fields page for a post type.
	 */
	public static function manage_fields_slug( $pt_slug ) {
		return 'admin.php?page=dw-catalog-manage-fields&post_type=' . $pt_slug;
	}

	public function redirect_legacy_page() {
		$types = DWCAT_Config::get_post_types();
		reset( $types );
		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-manage-fields&post_type=' . key( $types ) ) );
		exit;
	}

	/**
	 * Keep the post type menu highlighted while on the hidden manage-fields page.
	 */
	public function highlight_parent_menu( $parent_file ) {
		global $plugin_page, $submenu_file;
		if ( 'dw-catalog-manage-fields' !== $plugin_page ) {
			return $parent_file;
		}
		$pt_slug = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
		if ( ! DWCAT_Config::get_post_type( $pt_slug ) ) {
			return $parent_file;
		}
		$submenu_file = self::manage_fields_slug( $pt_slug );
		return 'dw-catalog-' . $pt_slug;
	}

	public function enqueue_scripts( $hook ) {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		$our_pages = array( 'dw-catalog-manage-fields' );
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
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dw-catalog-' . $pt_slug ) ); ?>">&larr; <?php printf( __( 'Back to %s', 'dw-catalog-wp' ), esc_html( $pt['plural_name'] ) ); ?></a>
			</p>

			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php _e( 'Fields saved.', 'dw-catalog-wp' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="dw-fields-form">
				<?php wp_nonce_field( 'dw_catalog_save_fields', 'dw_catalog_fields_nonce' ); ?>
				<input type="hidden" name="action" value="dw_catalog_save_fields">
				<input type="hidden" name="post_type_slug" value="<?php echo esc_attr( $pt_slug ); ?>">

				<?php $this->render_presets_panel(); ?>

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
							<th style="width:60px;"><?php _e( 'Detail', 'dw-catalog-wp' ); ?></th>
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

				<?php $this->render_variation_columns( $pt_slug ); ?>

				<?php submit_button( __( 'Save Fields', 'dw-catalog-wp' ) ); ?>
			</form>

			<!-- Template row for JS -->
			<table style="display:none;">
				<tbody id="dw-field-template">
					<?php $this->render_field_row( '__INDEX__', array(), $field_types ); ?>
				</tbody>
				<tbody id="dw-varcol-template">
					<?php $this->render_variation_column_row( '__VINDEX__', array() ); ?>
				</tbody>
			</table>

			<script>
			jQuery(function($){
				var idx = <?php echo count( $fields ); ?>;
				var vidx = <?php echo count( DWCAT_Config::get_variation_columns( $pt_slug ) ); ?>;
				var defaultVarKeys = <?php echo wp_json_encode( wp_list_pluck( DWCAT_Variations::get_default_columns(), 'key' ) ); ?>;

				function addVarColumn(col) {
					var $row = $($('#dw-varcol-template').html().replace(/__VINDEX__/g, vidx++));
					if (col) {
						$row.find('[name$="[label]"]').val(col.label);
						$row.find('[name$="[key]"]').val(col.key);
						$row.find('[name$="[type]"]').val(col.type);
						$row.find('[name$="[options]"]').val(col.options);
						$row.addClass('dw-preset-added');
					}
					$('#dw-varcols-body').append($row);
				}

				function varColumnKeys() {
					return $('#dw-varcols-body input[name$="[key]"]').map(function(){ return $.trim($(this).val()); }).get().filter(Boolean);
				}

				$('#dw-add-varcol').on('click', function(){ addVarColumn(null); });

				$('#dw-add-field').on('click', function(){
					var tpl = $('#dw-field-template').html().replace(/__INDEX__/g, idx);
					$('#dw-fields-body').append(tpl);
					idx++;
				});

				var presets = <?php echo wp_json_encode( DWCAT_Field_Presets::get_presets() ); ?>;

				function existingKeys() {
					var keys = {};
					$('#dw-fields-body input[name$="[meta_key]"]').each(function(){
						var k = $.trim($(this).val());
						if (k) { keys[k] = true; }
					});
					return keys;
				}

				// Presets act as an industry choice: picking another one replaces the unsaved rows the
				// previous pick added (saved fields are never touched); clicking the active one undoes it.
				var savedVarCols = $('#dw-varcols-body').html();

				function clearPresetRows() {
					$('#dw-fields-body tr.dw-preset-added').remove();
					$('#dw-varcols-body').html(savedVarCols);
					$('.dw-preset-btn').removeClass('button-primary').attr('aria-pressed', 'false');
				}

				$('.dw-preset-btn').on('click', function(){
					var $btn = $(this);
					var wasActive = $btn.hasClass('button-primary');
					clearPresetRows();
					if (wasActive) {
						$('#dw-preset-result').text(<?php echo wp_json_encode( __( 'Preset removed. Saved fields are unchanged.', 'dw-catalog-wp' ) ); ?>).show();
						return;
					}
					$btn.addClass('button-primary').attr('aria-pressed', 'true');

					var preset = presets[$btn.data('group')].presets[$btn.data('preset')];
					var keys = existingKeys(), added = 0, skipped = 0;

					$.each(preset.fields, function(_, f){
						if (keys[f.meta_key]) { skipped++; return; }
						var $row = $($('#dw-field-template').html().replace(/__INDEX__/g, idx));
						$row.find('[name$="[label]"]').val(f.label);
						$row.find('[name$="[meta_key]"]').val(f.meta_key);
						$row.find('[name$="[type]"]').val(f.type);
						$row.find('[name$="[options]"]').val(f.options);
						$row.find('[name$="[show_in_list]"]').prop('checked', !!f.show_in_list);
						$row.find('[name$="[show_in_export]"]').prop('checked', !!f.show_in_export);
						$row.find('[name$="[show_in_detail]"]').prop('checked', !!f.show_in_detail);
						$row.addClass('dw-preset-added');
						$('#dw-fields-body').append($row);
						keys[f.meta_key] = true;
						idx++; added++;
					});

					// Variation columns: an untouched default set (or none) is replaced by the industry
					// set; a customized set only gets the missing columns appended.
					var current = varColumnKeys();
					var untouched = current.length === 0 || current.join(',') === defaultVarKeys.join(',');
					var varAdded = 0;
					if (preset.variation_columns.length) {
						if (untouched) { $('#dw-varcols-body').empty(); current = []; }
						$.each(preset.variation_columns, function(_, col){
							if (current.indexOf(col.key) !== -1) { return; }
							addVarColumn(col); current.push(col.key); varAdded++;
						});
					}

					var msg = <?php echo wp_json_encode( __( '%1$s: %2$d fields added, %3$d skipped (already exist), %4$d variation columns added. Pick another preset to switch, or click it again to undo. Click "Save Fields" to apply.', 'dw-catalog-wp' ) ); ?>;
					$('#dw-preset-result')
						.text(msg.replace('%1$s', preset.label).replace('%2$d', added).replace('%3$d', skipped).replace('%4$d', varAdded))
						.show();
				});

				$(document).on('click', '.dw-remove-field', function(){
					$(this).closest('tr').remove();
				});

				$(document).on('click', '.dw-remove-varcol', function(){
					$(this).closest('tr').remove();
				});

				$('#dw-varcols-body').sortable({ handle: '.dw-sort-handle', axis: 'y', cursor: 'move', opacity: 0.7 });

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
					<li><strong><?php _e( 'Detail', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'Public field: shown on the website (detail page, cards) and usable for site search, filters and sorting. Unchecked fields never leave the admin — use this for costs, supplier info and internal notes.', 'dw-catalog-wp' ); ?></li>
					<li><strong><?php _e( 'Title', 'dw-catalog-wp' ); ?></strong>: <?php _e( 'Sync this field as the post title. Only ONE field should have this checked.', 'dw-catalog-wp' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	/**
	 * Preset buttons — appends industry field sets as unsaved rows (see DWCAT_Field_Presets).
	 */
	private function render_presets_panel() {
		$groups = DWCAT_Field_Presets::get_presets();
		?>
		<div class="dw-presets-panel" style="margin: 16px 0; padding: 12px 15px; background: #fff; border: 1px solid #ccd0d4;">
			<h3 style="margin: 0 0 4px;"><?php _e( 'Field Presets', 'dw-catalog-wp' ); ?></h3>
			<p class="description" style="margin-top: 0;"><?php _e( 'Pick your business type to add its field set. Picking another type switches the set; fields that already exist are kept. Nothing is saved until you click "Save Fields".', 'dw-catalog-wp' ); ?></p>
			<?php foreach ( $groups as $g_key => $group ) : ?>
				<div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px;">
					<strong style="min-width: 90px;"><?php echo esc_html( $group['label'] ); ?></strong>
					<?php foreach ( $group['presets'] as $p_key => $preset ) : ?>
						<button type="button" class="button dw-preset-btn" data-group="<?php echo esc_attr( $g_key ); ?>" data-preset="<?php echo esc_attr( $p_key ); ?>"
							title="<?php echo esc_attr( implode( ', ', wp_list_pluck( $preset['fields'], 'label' ) ) ); ?>">
							<span class="dashicons <?php echo esc_attr( $preset['icon'] ); ?>" style="vertical-align: text-bottom;"></span>
							<?php echo esc_html( $preset['label'] ); ?>
							<span style="color: #888;">(<?php echo count( $preset['fields'] ); ?>)</span>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
			<div id="dw-preset-result" class="notice notice-info inline" style="display: none; margin: 10px 0 0; padding: 8px 12px;"></div>
		</div>
		<style>#dw-fields-body tr.dw-preset-added { background: #f0f6fc; }</style>
		<?php
	}

	/**
	 * Variation column editor — which columns each variation row has for this post type.
	 */
	private function render_variation_columns( $pt_slug ) {
		$columns = DWCAT_Config::get_variation_columns( $pt_slug );
		?>
		<h2 id="dw-variation-columns" style="margin-top: 32px;"><?php _e( 'Variation Columns', 'dw-catalog-wp' ); ?></h2>
		<p class="description"><?php _e( 'Columns of the Variations table on the item edit screen and detail page (e.g. Size, Pack Size, Price). Remove all columns to turn variations off.', 'dw-catalog-wp' ); ?>
			<strong><?php _e( 'Changing a column key or removing a column hides the values already entered under it.', 'dw-catalog-wp' ); ?></strong></p>
		<table class="wp-list-table widefat fixed" id="dw-varcols-table" style="max-width: 900px;">
			<thead>
				<tr>
					<th style="width:30px;"><?php _e( 'Order', 'dw-catalog-wp' ); ?></th>
					<th><?php _e( 'Label', 'dw-catalog-wp' ); ?></th>
					<th><?php _e( 'Key', 'dw-catalog-wp' ); ?></th>
					<th style="width:140px;"><?php _e( 'Type', 'dw-catalog-wp' ); ?></th>
					<th><?php _e( 'Options (for Select)', 'dw-catalog-wp' ); ?></th>
					<th style="width:60px;" title="<?php esc_attr_e( 'Shown on the website. Uncheck for internal columns such as cost.', 'dw-catalog-wp' ); ?>"><?php _e( 'Public', 'dw-catalog-wp' ); ?></th>
					<th style="width:60px;"><?php _e( 'Remove', 'dw-catalog-wp' ); ?></th>
				</tr>
			</thead>
			<tbody id="dw-varcols-body">
				<?php foreach ( $columns as $i => $col ) : ?>
					<?php $this->render_variation_column_row( $i, $col ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<input type="hidden" name="variation_columns_submitted" value="1">
		<p style="margin-top: 12px;">
			<button type="button" class="button" id="dw-add-varcol"><?php _e( 'Add Column', 'dw-catalog-wp' ); ?></button>
		</p>
		<?php
	}

	private function render_variation_column_row( $index, $col ) {
		$prefix = "variation_columns[{$index}]";
		$type   = isset( $col['type'] ) ? $col['type'] : 'text';
		$types  = array(
			'text'   => __( 'Text', 'dw-catalog-wp' ),
			'number' => __( 'Number', 'dw-catalog-wp' ),
			'select' => __( 'Select (Dropdown)', 'dw-catalog-wp' ),
		);
		?>
		<tr>
			<td><span class="dashicons dashicons-menu dw-sort-handle" style="cursor:move; color:#999;"></span></td>
			<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[label]" value="<?php echo esc_attr( isset( $col['label'] ) ? $col['label'] : '' ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Size', 'dw-catalog-wp' ); ?>"></td>
			<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[key]" value="<?php echo esc_attr( isset( $col['key'] ) ? $col['key'] : '' ); ?>" class="widefat" placeholder="size" pattern="[a-z0-9_]+"></td>
			<td>
				<select name="<?php echo esc_attr( $prefix ); ?>[type]" class="widefat">
					<?php foreach ( $types as $t_key => $t_label ) : ?>
						<option value="<?php echo esc_attr( $t_key ); ?>" <?php selected( $type, $t_key ); ?>><?php echo esc_html( $t_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><input type="text" name="<?php echo esc_attr( $prefix ); ?>[options]" value="<?php echo esc_attr( isset( $col['options'] ) ? $col['options'] : '' ); ?>" class="widefat" placeholder="val:Label,val2:Label2"></td>
			<td style="text-align:center;"><input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[public]" value="0"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[public]" value="1" <?php checked( DWCAT_Variations::is_public_column( $col ) ); ?>></td>
			<td style="text-align:center;"><button type="button" class="button-link dw-remove-varcol" style="color:#b32d2e;">&times;</button></td>
		</tr>
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
		$show_in_detail = DWCAT_Config::shows_in_detail( wp_parse_args( $field, array( 'meta_key' => '' ) ) );
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
			<td style="text-align:center;"><input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[show_in_detail]" value="1" <?php checked( $show_in_detail ); ?>></td>
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

		// 게이트 4 — 동적 필드 스키마는 이 플러그인의 핵심 가치 로직입니다
		// (MIGRATION-PLAN §2). 라이선스 없이 편집을 허용하지 않습니다.
		if ( ! dwcat_can_manage_fields() ) {
			wp_die( esc_html__( 'DW Catalog WP: 라이선스가 활성화되지 않아 필드 설정을 저장할 수 없습니다.', 'dw-catalog-wp' ) );
		}

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
				'show_in_detail' => ! empty( $raw['show_in_detail'] ),
				'is_title_field' => ( (string) $index === (string) $title_field_index ),
			);
		}

		DWCAT_Config::save_fields( $pt_slug, $fields );
		DWCAT_Store::flush_field_keys();

		if ( ! empty( $_POST['variation_columns_submitted'] ) ) {
			$raw_cols = isset( $_POST['variation_columns'] ) && is_array( $_POST['variation_columns'] ) ? wp_unslash( $_POST['variation_columns'] ) : array();
			DWCAT_Config::save_variation_columns( $pt_slug, DWCAT_Variations::sanitize_columns( $raw_cols ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-manage-fields&post_type=' . $pt_slug . '&saved=1' ) );
		exit;
	}
}
