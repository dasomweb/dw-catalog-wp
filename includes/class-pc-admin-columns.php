<?php
/**
 * Admin Columns Class
 *
 * Dynamically adds custom columns to admin list for all registered post types.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Admin_Columns {

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_columns' ) );
	}

	/**
	 * Register column hooks for all configured post types.
	 */
	public function register_columns() {
		$post_types = DWCAT_Config::get_post_types();
		foreach ( $post_types as $slug => $config ) {
			add_filter( 'manage_' . $slug . '_posts_columns', array( $this, 'add_columns' ) );
			add_action( 'manage_' . $slug . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
			add_filter( 'manage_edit-' . $slug . '_sortable_columns', array( $this, 'sortable_columns' ) );
		}
	}

	/**
	 * Add columns. Determines which post type by inspecting the current screen.
	 */
	public function add_columns( $columns ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $columns;
		}
		$pt_slug = $screen->post_type;
		$fields = DWCAT_Config::get_list_fields( $pt_slug );

		$new_columns = array();
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'title' === $key ) {
				// Add category column if available
				$pt_config = DWCAT_Config::get_post_type( $pt_slug );
				if ( $pt_config && ! empty( $pt_config['has_category'] ) ) {
					$new_columns['dw_cat_category'] = __( 'Category', 'dw-catalog-wp' );
				}
				// Add custom field columns
				foreach ( $fields as $field ) {
					if ( ! empty( $field['is_title_field'] ) ) {
						continue; // Skip title field — already in the title column
					}
					$new_columns[ 'dw_field_' . $field['meta_key'] ] = $field['label'];
				}
			}
		}
		return $new_columns;
	}

	/**
	 * Render column values.
	 */
	public function render_column( $column_name, $post_id ) {
		$post = get_post( $post_id );
		$pt_slug = $post->post_type;

		// Category column
		if ( $column_name === 'dw_cat_category' ) {
			$pt_config = DWCAT_Config::get_post_type( $pt_slug );
			if ( $pt_config && ! empty( $pt_config['has_category'] ) ) {
				$tax = DWCAT_Config::get_category_taxonomy( $pt_slug );
				$terms = wp_get_post_terms( $post_id, $tax );
				$names = ! empty( $terms ) && ! is_wp_error( $terms ) ? implode( ', ', wp_list_pluck( $terms, 'name' ) ) : '';
				echo $names ? esc_html( $names ) : '—';
			}
			return;
		}

		// Custom field columns
		if ( strpos( $column_name, 'dw_field_' ) === 0 ) {
			$meta_key = substr( $column_name, strlen( 'dw_field_' ) );
			$value = get_post_meta( $post_id, $meta_key, true );

			// Check if this is a select field and resolve label
			$fields = DWCAT_Config::get_fields( $pt_slug );
			foreach ( $fields as $field ) {
				if ( $field['meta_key'] === $meta_key && $field['type'] === 'select' && $value !== '' ) {
					$options = DWCAT_Config::parse_select_options( $field['options'] );
					$value = isset( $options[ $value ] ) ? $options[ $value ] : $value;
					break;
				}
			}

			echo $value !== '' ? esc_html( $value ) : '—';
		}
	}

	/**
	 * Make custom field columns sortable.
	 */
	public function sortable_columns( $columns ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $columns;
		}
		$pt_slug = $screen->post_type;
		$fields = DWCAT_Config::get_list_fields( $pt_slug );
		foreach ( $fields as $field ) {
			if ( ! empty( $field['is_title_field'] ) ) {
				continue;
			}
			$col_key = 'dw_field_' . $field['meta_key'];
			$columns[ $col_key ] = $col_key;
		}
		return $columns;
	}
}
