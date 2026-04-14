<?php
/**
 * Admin Pages Class
 *
 * Creates custom admin pages for each registered post type.
 * Replaces default WordPress post list with custom interface.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Admin_Pages {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'remove_default_menus' ) );
		add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
		add_filter( 'use_block_editor_for_post_type', array( $this, 'disable_gutenberg' ), 10, 2 );
		add_action( 'admin_post_dw_catalog_delete_item', array( $this, 'handle_delete_item' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function remove_default_menus() {
		$post_types = DWCAT_Config::get_post_types();
		foreach ( $post_types as $slug => $config ) {
			remove_menu_page( 'edit.php?post_type=' . $slug );
		}
	}

	public function add_admin_menus() {
		$post_types = DWCAT_Config::get_post_types();
		$position = 21;

		foreach ( $post_types as $slug => $config ) {
			$menu_slug = 'dw-catalog-' . $slug;
			$menu_name = ! empty( $config['menu_name'] ) ? $config['menu_name'] : $config['plural_name'];
			$icon = ! empty( $config['menu_icon'] ) ? $config['menu_icon'] : 'dashicons-admin-generic';

			// Main menu
			add_menu_page(
				$menu_name,
				$menu_name,
				'edit_posts',
				$menu_slug,
				array( $this, 'render_list_page' ),
				$icon,
				$position++
			);

			// All items submenu (same as main)
			add_submenu_page(
				$menu_slug,
				sprintf( __( 'All %s', 'dw-catalog-wp' ), $config['plural_name'] ),
				sprintf( __( 'All %s', 'dw-catalog-wp' ), $config['plural_name'] ),
				'edit_posts',
				$menu_slug,
				array( $this, 'render_list_page' )
			);

			// Add New
			add_submenu_page(
				$menu_slug,
				sprintf( __( 'Add New %s', 'dw-catalog-wp' ), $config['singular_name'] ),
				__( 'Add New', 'dw-catalog-wp' ),
				'edit_posts',
				$menu_slug . '-new',
				function () use ( $slug ) {
					if ( ! current_user_can( 'edit_posts' ) ) {
						wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
					}
					wp_safe_redirect( admin_url( 'post-new.php?post_type=' . $slug ) );
					exit;
				}
			);

			// Categories
			if ( ! empty( $config['has_category'] ) ) {
				$cat_tax = DWCAT_Config::get_category_taxonomy( $slug );
				add_submenu_page(
					$menu_slug,
					__( 'Categories', 'dw-catalog-wp' ),
					__( 'Categories', 'dw-catalog-wp' ),
					'manage_categories',
					'edit-tags.php?taxonomy=' . $cat_tax . '&post_type=' . $slug
				);
			}

			// Tags
			if ( ! empty( $config['has_tag'] ) ) {
				$tag_tax = DWCAT_Config::get_tag_taxonomy( $slug );
				add_submenu_page(
					$menu_slug,
					__( 'Tags', 'dw-catalog-wp' ),
					__( 'Tags', 'dw-catalog-wp' ),
					'manage_categories',
					'edit-tags.php?taxonomy=' . $tag_tax . '&post_type=' . $slug
				);
			}
		}
	}

	/**
	 * Disable Gutenberg for our post types (use Classic editor).
	 */
	public function disable_gutenberg( $use_block_editor, $post_type ) {
		if ( DWCAT_Config::is_our_post_type( $post_type ) ) {
			return false;
		}
		return $use_block_editor;
	}

	/**
	 * Determine the post type slug from the current page parameter.
	 */
	private function get_current_post_type_slug() {
		$page = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : '';
		// Page format: dw-catalog-{slug}
		if ( strpos( $page, 'dw-catalog-' ) === 0 ) {
			return substr( $page, strlen( 'dw-catalog-' ) );
		}
		return '';
	}

	/**
	 * Render product list page (works for any post type).
	 */
	public function render_list_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		$pt_slug = $this->get_current_post_type_slug();
		$pt_config = DWCAT_Config::get_post_type( $pt_slug );
		if ( ! $pt_config ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Post type not found.', 'dw-catalog-wp' ) . '</p></div>';
			return;
		}

		$fields = DWCAT_Config::get_fields( $pt_slug );
		$list_fields = DWCAT_Config::get_list_fields( $pt_slug );
		$title_field = DWCAT_Config::get_title_field( $pt_slug );
		$menu_slug = 'dw-catalog-' . $pt_slug;

		// Handle bulk delete
		if ( isset( $_POST['action'] ) && $_POST['action'] === 'delete' && isset( $_POST['item_ids'] ) ) {
			check_admin_referer( 'dw-catalog-bulk-action-' . $pt_slug );
			foreach ( $_POST['item_ids'] as $item_id ) {
				wp_delete_post( intval( $item_id ), true );
			}
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Items deleted successfully.', 'dw-catalog-wp' ) . '</p></div>';
		}

		// Search
		$search_query = isset( $_REQUEST['dw_search'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['dw_search'] ) ) : '';
		$paged = isset( $_GET['paged'] ) ? intval( $_GET['paged'] ) : 1;

		$args = array(
			'post_type'      => $pt_slug,
			'posts_per_page' => 20,
			'paged'          => $paged,
			'post_status'    => 'any',
		);

		if ( $search_query !== '' ) {
			// Search in post title and title meta field
			$title_meta_key = $title_field ? $title_field['meta_key'] : '';
			$filter_cb = function ( $where, $query ) use ( $search_query, $pt_slug, $title_meta_key ) {
				if ( $query->get( 'post_type' ) !== $pt_slug ) {
					return $where;
				}
				global $wpdb;
				$like = '%' . $wpdb->esc_like( $search_query ) . '%';
				$meta_clause = '';
				if ( $title_meta_key ) {
					$meta_clause = $wpdb->prepare(
						" OR {$wpdb->posts}.ID IN ( SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s )",
						$title_meta_key,
						$like
					);
				}
				$where .= $wpdb->prepare(
					" AND ( {$wpdb->posts}.post_title LIKE %s{$meta_clause} )",
					$like
				);
				return $where;
			};
			add_filter( 'posts_where', $filter_cb, 10, 2 );
		}

		$query = new WP_Query( $args );

		if ( isset( $filter_cb ) ) {
			remove_filter( 'posts_where', $filter_cb, 10 );
		}
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php echo esc_html( $pt_config['menu_name'] ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $menu_slug . '-new' ) ); ?>" class="page-title-action">
				<?php _e( 'Add New', 'dw-catalog-wp' ); ?>
			</a>
			<hr class="wp-header-end">

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom: 12px;">
				<input type="hidden" name="page" value="<?php echo esc_attr( $menu_slug ); ?>">
				<label for="dw_search"><?php _e( 'Search', 'dw-catalog-wp' ); ?></label>
				<input type="search" id="dw_search" name="dw_search" value="<?php echo esc_attr( $search_query ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Search...', 'dw-catalog-wp' ); ?>" style="width: 250px; margin-right: 8px;">
				<input type="submit" class="button" value="<?php esc_attr_e( 'Search', 'dw-catalog-wp' ); ?>">
				<?php if ( $search_query !== '' ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $menu_slug ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'dw-catalog-wp' ); ?></a>
				<?php endif; ?>
			</form>

			<?php if ( $query->have_posts() ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=' . $menu_slug ) ); ?>">
					<?php wp_nonce_field( 'dw-catalog-bulk-action-' . $pt_slug ); ?>
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<select name="action">
								<option value="-1"><?php _e( 'Bulk Actions', 'dw-catalog-wp' ); ?></option>
								<option value="delete"><?php _e( 'Delete', 'dw-catalog-wp' ); ?></option>
							</select>
							<input type="submit" class="button action" value="<?php esc_attr_e( 'Apply', 'dw-catalog-wp' ); ?>">
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all"></td>
								<th class="manage-column"><?php echo esc_html( $pt_config['singular_name'] ); ?></th>
								<?php
								$pt_has_cat = ! empty( $pt_config['has_category'] );
								if ( $pt_has_cat ) :
								?>
									<th class="manage-column"><?php _e( 'Category', 'dw-catalog-wp' ); ?></th>
								<?php endif; ?>
								<?php foreach ( $list_fields as $field ) : ?>
									<?php if ( ! empty( $field['is_title_field'] ) ) continue; ?>
									<th class="manage-column"><?php echo esc_html( $field['label'] ); ?></th>
								<?php endforeach; ?>
								<th class="manage-column"><?php _e( 'Actions', 'dw-catalog-wp' ); ?></th>
								<th class="manage-column column-thumb"><?php _e( 'Image', 'dw-catalog-wp' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php while ( $query->have_posts() ) : $query->the_post(); ?>
								<?php
								$post_id = get_the_ID();
								$display_title = get_the_title( $post_id );
								if ( $title_field ) {
									$meta_title = get_post_meta( $post_id, $title_field['meta_key'], true );
									if ( ! empty( $meta_title ) ) {
										$display_title = $meta_title;
									}
								}
								?>
								<tr>
									<th scope="row" class="check-column">
										<input type="checkbox" name="item_ids[]" value="<?php echo esc_attr( $post_id ); ?>">
									</th>
									<td>
										<strong>
											<a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>">
												<?php echo $display_title ? esc_html( $display_title ) : esc_html__( '(No Title)', 'dw-catalog-wp' ); ?>
											</a>
										</strong>
									</td>
									<?php if ( $pt_has_cat ) : ?>
										<td>
											<?php
											$cat_tax = DWCAT_Config::get_category_taxonomy( $pt_slug );
											$terms = wp_get_post_terms( $post_id, $cat_tax );
											$names = ! empty( $terms ) && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
											echo $names ? esc_html( $names ) : '—';
											?>
										</td>
									<?php endif; ?>
									<?php foreach ( $list_fields as $field ) : ?>
										<?php if ( ! empty( $field['is_title_field'] ) ) continue; ?>
										<td>
											<?php
											$val = get_post_meta( $post_id, $field['meta_key'], true );
											if ( $field['type'] === 'select' && $val !== '' ) {
												$opts = DWCAT_Config::parse_select_options( $field['options'] );
												$val = isset( $opts[ $val ] ) ? $opts[ $val ] : $val;
											}
											echo $val !== '' ? esc_html( $val ) : '—';
											?>
										</td>
									<?php endforeach; ?>
									<td>
										<a href="<?php echo esc_url( get_edit_post_link( $post_id, 'raw' ) ); ?>"><?php _e( 'Edit', 'dw-catalog-wp' ); ?></a>
										|
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=dw_catalog_delete_item&item_id=' . $post_id . '&post_type=' . $pt_slug ), 'dw_catalog_delete_' . $post_id ) ); ?>"
										   onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this item?', 'dw-catalog-wp' ); ?>');">
											<?php _e( 'Delete', 'dw-catalog-wp' ); ?>
										</a>
									</td>
									<td class="column-thumb">
										<?php
										if ( has_post_thumbnail( $post_id ) ) {
											echo get_the_post_thumbnail( $post_id, array( 60, 60 ), array( 'class' => 'pc-list-thumb' ) );
										} else {
											echo '<span class="pc-list-thumb-placeholder">—</span>';
										}
										?>
									</td>
								</tr>
							<?php endwhile; ?>
						</tbody>
					</table>

					<?php
					$pagination_base = add_query_arg( array( 'page' => $menu_slug ), admin_url( 'admin.php' ) );
					if ( $search_query !== '' ) {
						$pagination_base = add_query_arg( 'dw_search', $search_query, $pagination_base );
					}
					$pagination_base = add_query_arg( 'paged', '%#%', $pagination_base );
					$pagination = paginate_links( array(
						'base'    => $pagination_base,
						'format'  => '',
						'current' => $paged,
						'total'   => $query->max_num_pages,
					) );
					if ( $pagination ) {
						echo '<div class="tablenav"><div class="tablenav-pages">' . $pagination . '</div></div>';
					}
					?>
				</form>
			<?php else : ?>
				<p><?php printf( __( 'No %s found.', 'dw-catalog-wp' ), strtolower( esc_html( $pt_config['plural_name'] ) ) ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $pt_slug ) ); ?>" class="button button-primary">
					<?php printf( __( 'Add Your First %s', 'dw-catalog-wp' ), esc_html( $pt_config['singular_name'] ) ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		wp_reset_postdata();
	}

	/**
	 * Handle delete item.
	 */
	public function handle_delete_item() {
		$item_id = isset( $_GET['item_id'] ) ? intval( $_GET['item_id'] ) : 0;
		$pt_slug = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';

		if ( ! $item_id || ! $pt_slug ) {
			wp_die( __( 'Invalid request.', 'dw-catalog-wp' ) );
		}
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'dw_catalog_delete_' . $item_id ) ) {
			wp_die( __( 'Security check failed.', 'dw-catalog-wp' ) );
		}
		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_die( __( 'Unauthorized', 'dw-catalog-wp' ) );
		}

		wp_delete_post( $item_id, true );

		wp_safe_redirect( admin_url( 'admin.php?page=dw-catalog-' . $pt_slug . '&deleted=1' ) );
		exit;
	}

	/**
	 * Enqueue admin scripts for the list pages.
	 */
	public function enqueue_admin_scripts( $hook ) {
		$page = isset( $_GET['page'] ) ? $_GET['page'] : '';
		$pt_slug = $this->get_current_post_type_slug();
		if ( empty( $pt_slug ) || ! DWCAT_Config::is_our_post_type( $pt_slug ) ) {
			return;
		}
		// Only on our list pages (page = dw-catalog-{slug})
		if ( $page !== 'dw-catalog-' . $pt_slug ) {
			return;
		}

		$config = dwcat_get_config();
		$css_path = dwcat_get_path() . 'assets/css/admin.css';
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'dwcat-admin-style', DWCAT_URL_Helper::get_css_url( 'admin.css' ), array(), $config['plugin_version'] );
		}
	}
}
