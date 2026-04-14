<?php
/**
 * Field Reference Class
 *
 * Displays custom field information per post type.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Field_Reference {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
	}

	/**
	 * Add Field Reference submenu to each post type menu.
	 */
	public function add_admin_menus() {
		$post_types = DWCAT_Config::get_post_types();
		foreach ( $post_types as $slug => $config ) {
			$parent = 'dw-catalog-' . $slug;
			add_submenu_page(
				$parent,
				__( 'Field Reference', 'dw-catalog-wp' ),
				__( 'Field Reference', 'dw-catalog-wp' ),
				'edit_posts',
				$parent . '-fields',
				array( $this, 'render_page' )
			);
		}
	}

	private function get_post_type_from_page() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		if ( preg_match( '/^dw-catalog-(.+)-fields$/', $page, $m ) ) {
			return $m[1];
		}
		return '';
	}

	public function render_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$pt_slug = $this->get_post_type_from_page();
		$pt_config = DWCAT_Config::get_post_type( $pt_slug );
		if ( ! $pt_config ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Post type not found.', 'dw-catalog-wp' ) . '</p></div>';
			return;
		}

		$fields = DWCAT_Config::get_fields( $pt_slug );
		?>
		<div class="wrap">
			<h1><?php printf( __( 'Field Reference — %s', 'dw-catalog-wp' ), esc_html( $pt_config['singular_name'] ) ); ?></h1>
			<p class="description"><?php _e( 'All custom fields for this post type. Use meta keys when importing data or accessing fields programmatically.', 'dw-catalog-wp' ); ?></p>

			<?php if ( empty( $fields ) ) : ?>
				<p><?php _e( 'No fields configured for this post type.', 'dw-catalog-wp' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=dw-catalog-manage-fields&post_type=' . $pt_slug ) ); ?>" class="button">
					<?php _e( 'Add Fields', 'dw-catalog-wp' ); ?>
				</a>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php _e( 'Label', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Meta Key', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Type', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Required', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Options', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'In List', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'In Export', 'dw-catalog-wp' ); ?></th>
							<th><?php _e( 'Title Sync', 'dw-catalog-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $fields as $field ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $field['label'] ); ?></strong></td>
								<td><code><?php echo esc_html( $field['meta_key'] ); ?></code></td>
								<td><?php echo esc_html( $field['type'] ); ?></td>
								<td><?php echo ! empty( $field['required'] ) ? '&#10003;' : '—'; ?></td>
								<td>
									<?php
									if ( $field['type'] === 'select' && ! empty( $field['options'] ) ) {
										$opts = DWCAT_Config::parse_select_options( $field['options'] );
										echo esc_html( implode( ', ', array_keys( $opts ) ) );
									} else {
										echo '—';
									}
									?>
								</td>
								<td><?php echo ! empty( $field['show_in_list'] ) ? '&#10003;' : '—'; ?></td>
								<td><?php echo ! empty( $field['show_in_export'] ) ? '&#10003;' : '—'; ?></td>
								<td><?php echo ! empty( $field['is_title_field'] ) ? '&#10003;' : '—'; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<div style="margin-top:30px; padding:15px; background:#fff; border:1px solid #ccd0d4;">
					<h2><?php _e( 'Usage Examples', 'dw-catalog-wp' ); ?></h2>

					<h3><?php _e( 'Get Field Value (PHP)', 'dw-catalog-wp' ); ?></h3>
					<pre><code><?php
					foreach ( $fields as $f ) {
						echo '$' . sanitize_key( $f['label'] ) . ' = get_post_meta( $post_id, \'' . esc_html( $f['meta_key'] ) . '\', true );' . "\n";
					}
					?></code></pre>

					<h3><?php _e( 'Kadence Blocks Pro Dynamic Field', 'dw-catalog-wp' ); ?></h3>
					<ul>
						<?php foreach ( $fields as $f ) : ?>
							<li><code><?php echo esc_html( $f['meta_key'] ); ?></code> — <?php echo esc_html( $f['label'] ); ?></li>
						<?php endforeach; ?>
					</ul>

					<h3><?php _e( 'CSV Import Column Headers', 'dw-catalog-wp' ); ?></h3>
					<pre><code><?php echo esc_html( implode( ';', array_map( function ( $f ) { return $f['meta_key']; }, $fields ) ) ); ?></code></pre>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
