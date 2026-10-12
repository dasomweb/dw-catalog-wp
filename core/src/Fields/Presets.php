<?php
/**
 * Field Presets — industry-specific custom field sets.
 *
 * Shown as buttons on the Manage Fields page. Clicking one appends the preset's
 * fields as unsaved rows; fields whose meta key already exists are skipped, so
 * presets can be stacked (e.g. Seafood + Plants) without duplicates.
 *
 * Fields are defined once in a shared library and referenced by id, so the same
 * concept (price, item code, ...) always maps to the same meta key across presets.
 * Ids that match the default product fields (item_code, brand_raw, ...) reuse them.
 *
 * Each preset also carries an industry variation column set (see VariationColumns).
 * Labels are English source strings; the admin UI translates them through the host.
 *
 */

namespace DWCatalog\Fields;

class Presets {

	const META_PREFIX = 'dw_pc_';

	/**
	 * Shared field library: id => [ label, type, options, show_in_list ].
	 * Meta key is META_PREFIX . id.
	 *
	 * @return array
	 */
	private static function get_library() {
		$yes_no = 'yes:Yes,no:No';
		return array(
			// Identification / commerce
			'item_code'        => array( 'Item Code', 'text', '', true ),
			'upc'              => array( 'UPC / Barcode', 'text', '', false ),
			'brand_raw'        => array( 'Brand', 'text', '', true ),
			'origin_raw'       => array( 'Origin', 'text', '', true ),
			'pack_size_raw'    => array( 'Pack Size / Case Pack', 'text', '', true ),
			'status'           => array( 'Status', 'select', 'active:Active,inactive:Inactive,out_of_stock:Out of Stock,discontinued:Discontinued', true ),
			'price'            => array( 'Price', 'number', '', true ),
			'sale_price'       => array( 'Sale Price', 'number', '', false ),
			'wholesale_price'  => array( 'Wholesale Price', 'number', '', true ),
			'case_price'       => array( 'Case Price', 'number', '', true ),
			'msrp'             => array( 'MSRP', 'number', '', false ),
			'moq'              => array( 'Minimum Order Qty', 'number', '', false ),
			'sale_unit'        => array( 'Unit of Sale', 'select', 'lb:Per lb,kg:Per kg,case:Per Case,piece:Per Piece,dozen:Per Dozen', false ),
			'badge'            => array( 'Badge', 'select', 'new:New,best:Best Seller,signature:Signature,limited:Limited', false ),

			// Seafood
			'species'          => array( 'Species / Scientific Name', 'text', '', false ),
			'catch_method'     => array( 'Catch Method', 'select', 'wild:Wild-Caught,farmed:Farm-Raised', true ),
			'storage'          => array( 'Storage', 'select', 'fresh:Fresh,frozen:Frozen,live:Live,dried:Dried,salted:Salted', true ),
			'size_grade'       => array( 'Size / Grade', 'text', '', true ),
			'processing'       => array( 'Processing / Cut', 'select', 'whole:Whole Round,hg:Headed & Gutted,fillet:Fillet,steak:Steak,pd:Peeled & Deveined,shell_on:Shell-On', false ),
			'glaze'            => array( 'Glaze %', 'number', '', false ),
			'shelf_life'       => array( 'Shelf Life', 'text', '', false ),
			'certification'    => array( 'Certification', 'text', '', false ),

			// Jewelry
			'metal'            => array( 'Metal', 'select', '10k:10K Gold,14k:14K Gold,18k:18K Gold,925:925 Sterling Silver,platinum:Platinum,stainless:Stainless Steel,brass:Brass,plated:Gold-Plated', true ),
			'metal_color'      => array( 'Metal Color', 'select', 'yellow:Yellow,white:White,rose:Rose,two_tone:Two-Tone', false ),
			'gemstone'         => array( 'Main Stone', 'text', '', true ),
			'carat_weight'     => array( 'Total Carat Weight', 'number', '', false ),
			'weight_g'         => array( 'Weight (g)', 'number', '', false ),
			'jewelry_size'     => array( 'Size / Length', 'text', '', false ),
			'certificate'      => array( 'Certificate (GIA, IGI)', 'text', '', false ),
			'engraving'        => array( 'Engraving Available', 'select', $yes_no, false ),

			// Beauty
			'size_volume'      => array( 'Size / Volume', 'text', '', true ),
			'shade'            => array( 'Color / Shade', 'text', '', false ),
			'hair_type'        => array( 'Hair Type', 'select', 'human:Human Hair,synthetic:Synthetic,blend:Blend', false ),
			'hair_length'      => array( 'Hair Length', 'text', '', false ),
			'skin_type'        => array( 'Skin Type', 'select', 'all:All Skin Types,dry:Dry,oily:Oily,combination:Combination,sensitive:Sensitive', false ),
			'ingredients'      => array( 'Ingredients', 'textarea', '', false ),

			// Plants
			'botanical_name'   => array( 'Botanical Name', 'text', '', false ),
			'container_size'   => array( 'Container Size', 'text', '', true ),
			'plant_height'     => array( 'Height', 'text', '', false ),
			'light'            => array( 'Light', 'select', 'full_sun:Full Sun,part_sun:Part Sun,shade:Shade,low_light:Low Light', false ),
			'water'            => array( 'Water Needs', 'select', 'low:Low,medium:Medium,high:High', false ),
			'hardiness_zone'   => array( 'USDA Hardiness Zone', 'text', '', false ),
			'plant_location'   => array( 'Indoor / Outdoor', 'select', 'indoor:Indoor,outdoor:Outdoor,both:Both', false ),
			'qty_per_tray'     => array( 'Qty per Tray / Cart', 'number', '', false ),
			'availability'     => array( 'Availability', 'select', 'in_stock:In Stock,seasonal:Seasonal,pre_order:Pre-Order', true ),

			// Food & drink
			'serving_sizes'    => array( 'Sizes', 'text', '', false ),
			'temperature'      => array( 'Hot / Iced', 'select', 'hot:Hot,iced:Iced,both:Hot & Iced', false ),
			'milk_options'     => array( 'Milk Options', 'text', '', false ),
			'calories'         => array( 'Calories', 'number', '', false ),
			'allergens'        => array( 'Allergens', 'text', '', false ),
			'dietary'          => array( 'Dietary (Vegan, GF...)', 'text', '', false ),
			'menu_season'      => array( 'Menu Availability', 'select', 'regular:Regular,seasonal:Seasonal,limited:Limited Time', false ),
			'alt_name'         => array( 'Alternate Name', 'text', '', false ),
			'spice_level'      => array( 'Spice Level', 'select', '0:Not Spicy,1:Mild,2:Medium,3:Hot', false ),
			'portion'          => array( 'Portion / Serves', 'text', '', false ),
			'meal_period'      => array( 'Meal Period', 'select', 'all_day:All Day,breakfast:Breakfast,lunch:Lunch,dinner:Dinner', false ),
			'unit_count'       => array( 'Size / Count', 'text', '', false ),
			'preorder'         => array( 'Pre-order', 'select', 'walk_in:Walk-in,required:Pre-order Required', false ),
			'available_days'   => array( 'Available Days', 'text', '', false ),

			// General goods / apparel
			'color'            => array( 'Color', 'text', '', false ),
			'material'         => array( 'Material', 'text', '', false ),
			'dimensions'       => array( 'Dimensions', 'text', '', false ),
			'apparel_sizes'    => array( 'Sizes Available', 'text', '', true ),
			'fit'              => array( 'Fit', 'select', 'regular:Regular,slim:Slim,relaxed:Relaxed,oversized:Oversized', false ),
			'gender'           => array( 'Gender', 'select', 'women:Women,men:Men,unisex:Unisex,kids:Kids', false ),
			'care'             => array( 'Care Instructions', 'textarea', '', false ),
		);
	}

	/**
	 * Variation column library: id => [ label, type, options ]. Column key = id.
	 *
	 * @return array
	 */
	private static function get_variation_library() {
		$lib = self::get_library();
		return array(
			'item_code'   => array( 'Item Code', 'text', '' ),
			'sku'         => array( 'SKU', 'text', '' ),
			'name'        => array( 'Option', 'text', '' ),
			'size_grade'  => array( 'Size / Grade', 'text', '' ),
			'pack_size'   => array( 'Pack Size', 'text', '' ),
			'size'        => array( 'Size', 'text', '' ),
			'color'       => array( 'Color', 'text', '' ),
			'shade'       => array( 'Color / Shade', 'text', '' ),
			'size_volume' => array( 'Size / Volume', 'text', '' ),
			'length'      => array( 'Size / Length', 'text', '' ),
			'container'   => array( 'Container Size', 'text', '' ),
			'height'      => array( 'Height', 'text', '' ),
			'portion'     => array( 'Portion', 'text', '' ),
			'unit_count'  => array( 'Size / Count', 'text', '' ),
			'metal'       => array( 'Metal', 'select', $lib['metal'][2] ),
			'storage'     => array( 'Storage', 'select', $lib['storage'][2] ),
			'temperature' => array( 'Hot / Iced', 'select', $lib['temperature'][2] ),
			'price'       => array( 'Price', 'number', '' ),
			'case_price'  => array( 'Case Price', 'number', '' ),
			'wholesale'   => array( 'Wholesale Price', 'number', '' ),
			'calories'    => array( 'Calories', 'number', '' ),
			'stock'       => array( 'Stock', 'select', VariationColumns::STOCK_OPTIONS ),
		);
	}

	/**
	 * Preset definitions grouped by business model.
	 * Each field entry is a library id, or [ id, label override ].
	 *
	 * @return array group_key => [ 'label' => ..., 'presets' => [ key => [ 'label', 'icon', 'fields' ] ] ]
	 */
	private static function get_definitions() {
		return array(
			'wholesale' => array(
				'label'   => 'Wholesaler',
				'presets' => array(
					'seafood'       => array(
						'label'  => 'Seafood Market',
						'icon'   => 'dashicons-palmtree',
						'fields' => array( 'item_code', 'species', 'origin_raw', 'catch_method', 'storage', 'size_grade', 'processing', 'pack_size_raw', 'sale_unit', 'case_price', 'moq', 'glaze', 'shelf_life', 'certification' ),
						'variations' => array( 'item_code', 'size_grade', 'storage', 'pack_size', 'case_price', 'stock' ),
					),
					'jewelry'       => array(
						'label'  => 'Jewelry',
						'icon'   => 'dashicons-star-filled',
						'fields' => array( 'item_code', 'metal', 'metal_color', 'gemstone', 'carat_weight', 'weight_g', 'jewelry_size', 'certificate', array( 'pack_size_raw', 'Pack Qty' ), 'wholesale_price', 'msrp', 'moq' ),
						'variations' => array( 'item_code', 'metal', 'length', 'wholesale', 'stock' ),
					),
					'beauty_supply' => array(
						'label'  => 'Beauty Supply',
						'icon'   => 'dashicons-art',
						'fields' => array( 'item_code', 'upc', 'brand_raw', 'size_volume', 'shade', 'hair_type', 'hair_length', 'ingredients', 'origin_raw', 'pack_size_raw', 'wholesale_price', 'msrp', 'moq' ),
						'variations' => array( 'item_code', 'shade', 'size_volume', 'pack_size', 'wholesale', 'stock' ),
					),
					'plants'        => array(
						'label'  => 'Plants',
						'icon'   => 'dashicons-carrot',
						'fields' => array( 'item_code', 'botanical_name', 'container_size', 'plant_height', 'light', 'water', 'hardiness_zone', 'plant_location', 'qty_per_tray', 'availability', 'wholesale_price', 'moq' ),
						'variations' => array( 'item_code', 'container', 'height', 'wholesale', 'stock' ),
					),
				),
			),
			'retail'    => array(
				'label'   => 'Retailer',
				'presets' => array(
					'cafe'          => array(
						'label'  => 'Cafe',
						'icon'   => 'dashicons-coffee',
						'fields' => array( 'price', 'serving_sizes', 'temperature', 'milk_options', 'calories', 'allergens', 'dietary', 'menu_season', 'badge' ),
						'variations' => array( 'size', 'temperature', 'price', 'calories' ),
					),
					'restaurant'    => array(
						'label'  => 'Restaurant',
						'icon'   => 'dashicons-food',
						'fields' => array( 'price', 'alt_name', 'spice_level', 'portion', 'meal_period', 'allergens', 'dietary', 'calories', array( 'badge', "Badge (Chef's Pick)" ) ),
						'variations' => array( 'portion', 'price' ),
					),
					'bakery'        => array(
						'label'  => 'Bakery',
						'icon'   => 'dashicons-carrot',
						'fields' => array( 'price', 'unit_count', 'allergens', 'dietary', 'preorder', 'available_days', 'badge' ),
						'variations' => array( 'unit_count', 'price', 'stock' ),
					),
					'general_goods' => array(
						'label'  => 'General Goods',
						'icon'   => 'dashicons-cart',
						'fields' => array( array( 'item_code', 'SKU' ), 'upc', 'brand_raw', 'price', 'sale_price', 'color', 'material', 'dimensions', 'origin_raw', 'status' ),
						'variations' => array( 'sku', 'color', 'size', 'price', 'stock' ),
					),
					'apparel'       => array(
						'label'  => 'Apparel',
						'icon'   => 'dashicons-businessperson',
						'fields' => array( array( 'item_code', 'SKU' ), 'brand_raw', 'price', 'sale_price', 'apparel_sizes', 'color', array( 'material', 'Material / Fabric' ), 'fit', 'gender', 'care', 'status' ),
						'variations' => array( 'sku', 'size', 'color', 'price', 'stock' ),
					),
					'jewelry'       => array(
						'label'  => 'Jewelry',
						'icon'   => 'dashicons-star-filled',
						'fields' => array( array( 'item_code', 'SKU' ), 'price', 'sale_price', 'metal', 'metal_color', 'gemstone', 'carat_weight', 'jewelry_size', 'certificate', 'engraving', 'status' ),
						'variations' => array( 'sku', 'metal', 'length', 'price', 'stock' ),
					),
					'cosmetics'     => array(
						'label'  => 'Cosmetics',
						'icon'   => 'dashicons-art',
						'fields' => array( array( 'item_code', 'SKU' ), 'brand_raw', 'price', 'sale_price', 'size_volume', 'shade', 'skin_type', 'ingredients', 'status' ),
						'variations' => array( 'sku', 'shade', 'size_volume', 'price', 'stock' ),
					),
				),
			),
		);
	}

	/**
	 * Presets resolved to full field arrays, in the same shape DWCAT_Config::save_fields() stores.
	 *
	 * @return array
	 */
	public static function get_presets() {
		$library     = self::get_library();
		$var_library = self::get_variation_library();
		$groups      = self::get_definitions();

		foreach ( $groups as $g_key => $group ) {
			foreach ( $group['presets'] as $p_key => $preset ) {
				$fields = array();
				foreach ( $preset['fields'] as $entry ) {
					$id    = is_array( $entry ) ? $entry[0] : $entry;
					$label = is_array( $entry ) ? $entry[1] : null;
					if ( ! isset( $library[ $id ] ) ) {
						continue;
					}
					list( $lib_label, $type, $options, $in_list ) = $library[ $id ];
					$fields[] = array(
						'meta_key'       => self::META_PREFIX . $id,
						'label'          => $label ? $label : $lib_label,
						'type'           => $type,
						'required'       => false,
						'options'        => $options,
						'show_in_list'   => $in_list,
						'show_in_export' => true,
						'show_in_detail' => true,
					);
				}
				$groups[ $g_key ]['presets'][ $p_key ]['fields'] = $fields;

				$columns = array();
				$var_ids = isset( $preset['variations'] ) ? $preset['variations'] : array();
				foreach ( $var_ids as $id ) {
					if ( isset( $var_library[ $id ] ) ) {
						$columns[] = array(
							'key'     => $id,
							'label'   => $var_library[ $id ][0],
							'type'    => $var_library[ $id ][1],
							'options' => $var_library[ $id ][2],
						);
					}
				}
				$groups[ $g_key ]['presets'][ $p_key ]['variation_columns'] = $columns;
			}
		}

		return $groups;
	}
}
