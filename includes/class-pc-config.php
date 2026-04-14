<?php
/**
 * Configuration Manager Class
 *
 * Central manager for post types and custom fields.
 * Post types are defined in code; custom fields are stored in wp_options.
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PC_Config {

	const OPTION_POST_TYPES = 'dw_catalog_post_types';
	const OPTION_FIELDS_PREFIX = 'dw_catalog_fields_';

	/**
	 * Get all registered post type configurations.
	 *
	 * @return array Associative array keyed by post type slug.
	 */
	public static function get_post_types() {
		$types = get_option( self::OPTION_POST_TYPES, null );
		if ( ! is_array( $types ) || empty( $types ) ) {
			$types = self::get_default_post_types();
			update_option( self::OPTION_POST_TYPES, $types, true );
			// Also seed default fields for each default post type
			foreach ( $types as $slug => $config ) {
				$existing = get_option( self::OPTION_FIELDS_PREFIX . $slug, null );
				if ( $existing === null ) {
					$defaults = self::get_default_fields( $slug );
					if ( ! empty( $defaults ) ) {
						update_option( self::OPTION_FIELDS_PREFIX . $slug, $defaults, true );
					}
				}
			}
		}
		return $types;
	}

	/**
	 * Get a single post type configuration.
	 *
	 * @param string $slug Post type slug.
	 * @return array|null Post type config or null if not found.
	 */
	public static function get_post_type( $slug ) {
		$types = self::get_post_types();
		return isset( $types[ $slug ] ) ? $types[ $slug ] : null;
	}

	/**
	 * Save a post type configuration.
	 *
	 * @param string $slug   Post type slug.
	 * @param array  $config Post type config array.
	 */
	public static function save_post_type( $slug, $config ) {
		$types = self::get_post_types();
		$config['slug'] = $slug;
		$types[ $slug ] = $config;
		update_option( self::OPTION_POST_TYPES, $types, true );
	}

	/**
	 * Delete a post type and its fields.
	 *
	 * @param string $slug Post type slug.
	 */
	public static function delete_post_type( $slug ) {
		$types = self::get_post_types();
		unset( $types[ $slug ] );
		update_option( self::OPTION_POST_TYPES, $types, true );
		delete_option( self::OPTION_FIELDS_PREFIX . $slug );
	}

	/**
	 * Get custom fields for a post type.
	 *
	 * @param string $slug Post type slug.
	 * @return array Array of field definitions.
	 */
	public static function get_fields( $slug ) {
		$fields = get_option( self::OPTION_FIELDS_PREFIX . $slug, array() );
		return is_array( $fields ) ? $fields : array();
	}

	/**
	 * Save custom fields for a post type.
	 *
	 * @param string $slug   Post type slug.
	 * @param array  $fields Array of field definitions.
	 */
	public static function save_fields( $slug, $fields ) {
		update_option( self::OPTION_FIELDS_PREFIX . $slug, $fields, true );
	}

	/**
	 * Get all post type slugs.
	 *
	 * @return string[]
	 */
	public static function get_post_type_slugs() {
		return array_keys( self::get_post_types() );
	}

	/**
	 * Check if a post type slug belongs to this plugin.
	 *
	 * @param string $slug Post type slug.
	 * @return bool
	 */
	public static function is_our_post_type( $slug ) {
		$types = self::get_post_types();
		return isset( $types[ $slug ] );
	}

	/**
	 * Get taxonomy slug for a post type's category.
	 *
	 * @param string $slug Post type slug.
	 * @return string
	 */
	public static function get_category_taxonomy( $slug ) {
		return $slug . '_category';
	}

	/**
	 * Get taxonomy slug for a post type's tag.
	 *
	 * @param string $slug Post type slug.
	 * @return string
	 */
	public static function get_tag_taxonomy( $slug ) {
		return $slug . '_tag';
	}

	/**
	 * Available field types.
	 *
	 * @return array
	 */
	public static function get_field_types() {
		return array(
			'text'     => __( 'Text', 'dw-catalog-wp' ),
			'textarea' => __( 'Textarea', 'dw-catalog-wp' ),
			'number'   => __( 'Number', 'dw-catalog-wp' ),
			'email'    => __( 'Email', 'dw-catalog-wp' ),
			'url'      => __( 'URL', 'dw-catalog-wp' ),
			'date'     => __( 'Date', 'dw-catalog-wp' ),
			'select'   => __( 'Select (Dropdown)', 'dw-catalog-wp' ),
		);
	}

	/**
	 * Available dashicons for menu icon selection.
	 *
	 * @return array
	 */
	public static function get_menu_icons() {
		return array(
			'dashicons-products'        => 'Products',
			'dashicons-cart'            => 'Cart',
			'dashicons-store'           => 'Store',
			'dashicons-archive'         => 'Archive',
			'dashicons-portfolio'       => 'Portfolio',
			'dashicons-book'            => 'Book',
			'dashicons-media-document'  => 'Document',
			'dashicons-clipboard'       => 'Clipboard',
			'dashicons-database'        => 'Database',
			'dashicons-list-view'       => 'List',
			'dashicons-grid-view'       => 'Grid',
			'dashicons-tag'             => 'Tag',
			'dashicons-category'        => 'Category',
			'dashicons-location'        => 'Location',
			'dashicons-building'        => 'Building',
			'dashicons-businessman'     => 'Person',
			'dashicons-groups'          => 'Groups',
			'dashicons-hammer'          => 'Tools',
			'dashicons-food'            => 'Food',
			'dashicons-heart'           => 'Heart',
			'dashicons-star-filled'     => 'Star',
			'dashicons-flag'            => 'Flag',
			'dashicons-admin-generic'   => 'Generic',
		);
	}

	/**
	 * Default post types for fresh installation (backward-compatible with existing product data).
	 *
	 * @return array
	 */
	public static function get_default_post_types() {
		return array(
			'product' => array(
				'slug'          => 'product',
				'singular_name' => 'Product',
				'plural_name'   => 'Products',
				'menu_name'     => 'Catalog WP',
				'menu_icon'     => 'dashicons-products',
				'has_archive'   => true,
				'public'        => true,
				'show_in_rest'  => true,
				'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
				'has_category'  => true,
				'has_tag'       => true,
			),
		);
	}

	/**
	 * Default fields for a post type. Returns backward-compatible fields for "product".
	 *
	 * @param string $slug Post type slug.
	 * @return array
	 */
	public static function get_default_fields( $slug ) {
		if ( $slug === 'product' ) {
			return array(
				array(
					'meta_key'       => 'dw_pc_product_name',
					'label'          => 'Product Name',
					'type'           => 'text',
					'required'       => true,
					'options'        => '',
					'description'    => 'Product name. Synced as post title.',
					'show_in_list'   => true,
					'show_in_export' => true,
					'is_title_field' => true,
				),
				array(
					'meta_key'       => 'dw_pc_item_code',
					'label'          => 'Item Code',
					'type'           => 'text',
					'required'       => false,
					'options'        => '',
					'description'    => 'Used as product URL slug.',
					'show_in_list'   => true,
					'show_in_export' => true,
					'is_title_field' => false,
				),
				array(
					'meta_key'       => 'dw_pc_pack_size_raw',
					'label'          => 'Pack Size / Case Pack',
					'type'           => 'text',
					'required'       => false,
					'options'        => '',
					'description'    => '',
					'show_in_list'   => true,
					'show_in_export' => true,
					'is_title_field' => false,
				),
				array(
					'meta_key'       => 'dw_pc_brand_raw',
					'label'          => 'Brand',
					'type'           => 'text',
					'required'       => false,
					'options'        => '',
					'description'    => '',
					'show_in_list'   => true,
					'show_in_export' => true,
					'is_title_field' => false,
				),
				array(
					'meta_key'       => 'dw_pc_origin_raw',
					'label'          => 'Origin',
					'type'           => 'text',
					'required'       => false,
					'options'        => '',
					'description'    => '',
					'show_in_list'   => true,
					'show_in_export' => true,
					'is_title_field' => false,
				),
				array(
					'meta_key'       => 'dw_pc_status',
					'label'          => 'Status',
					'type'           => 'select',
					'required'       => false,
					'options'        => 'active:Active,inactive:Inactive,out_of_stock:Out of Stock,discontinued:Discontinued',
					'description'    => '',
					'show_in_list'   => true,
					'show_in_export' => true,
					'is_title_field' => false,
				),
				array(
					'meta_key'       => 'dw_pc_internal_note',
					'label'          => 'ETC',
					'type'           => 'textarea',
					'required'       => false,
					'options'        => '',
					'description'    => 'Internal notes',
					'show_in_list'   => false,
					'show_in_export' => true,
					'is_title_field' => false,
				),
			);
		}
		return array();
	}

	/**
	 * Parse select options string into associative array.
	 * Format: "value:Label,value2:Label2" or "value1,value2" (value=label)
	 *
	 * @param string $options_string
	 * @return array
	 */
	public static function parse_select_options( $options_string ) {
		$result = array();
		if ( empty( $options_string ) ) {
			return $result;
		}
		$items = array_map( 'trim', explode( ',', $options_string ) );
		foreach ( $items as $item ) {
			if ( strpos( $item, ':' ) !== false ) {
				list( $value, $label ) = explode( ':', $item, 2 );
				$result[ trim( $value ) ] = trim( $label );
			} else {
				$result[ trim( $item ) ] = trim( $item );
			}
		}
		return $result;
	}

	/**
	 * Get the title field for a post type (the field marked as is_title_field).
	 *
	 * @param string $slug Post type slug.
	 * @return array|null Field definition or null.
	 */
	public static function get_title_field( $slug ) {
		$fields = self::get_fields( $slug );
		foreach ( $fields as $field ) {
			if ( ! empty( $field['is_title_field'] ) ) {
				return $field;
			}
		}
		return null;
	}

	/**
	 * Get fields that should show in admin list columns.
	 *
	 * @param string $slug Post type slug.
	 * @return array
	 */
	public static function get_list_fields( $slug ) {
		$fields = self::get_fields( $slug );
		return array_filter( $fields, function ( $f ) {
			return ! empty( $f['show_in_list'] );
		} );
	}

	/**
	 * Get fields that should show in PDF/export.
	 *
	 * @param string $slug Post type slug.
	 * @return array
	 */
	public static function get_export_fields( $slug ) {
		$fields = self::get_fields( $slug );
		return array_filter( $fields, function ( $f ) {
			return ! empty( $f['show_in_export'] );
		} );
	}
}
