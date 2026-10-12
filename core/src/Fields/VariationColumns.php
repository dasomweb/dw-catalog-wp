<?php
namespace DWCatalog\Fields;

use DWCatalog\Support\Text;

/**
 * Variation column definitions: per catalog type, industry-specific (Size · Pack · Price …).
 * Column: [ key, label, type (text|number|select), options, public ].
 */
final class VariationColumns {

	const TYPES = array( 'text', 'number', 'select' );

	const STOCK_OPTIONS = 'in_stock:In Stock,out_of_stock:Out of Stock,pre_order:Pre-Order';

	public static function defaults(): array {
		return array(
			array( 'key' => 'name', 'label' => 'Option', 'type' => 'text', 'options' => '', 'public' => true ),
			array( 'key' => 'item_code', 'label' => 'Item Code / SKU', 'type' => 'text', 'options' => '', 'public' => true ),
			array( 'key' => 'pack_size', 'label' => 'Pack Size', 'type' => 'text', 'options' => '', 'public' => true ),
			array( 'key' => 'price', 'label' => 'Price', 'type' => 'number', 'options' => '', 'public' => true ),
			array( 'key' => 'stock', 'label' => 'Stock', 'type' => 'select', 'options' => self::STOCK_OPTIONS, 'public' => true ),
		);
	}

	public static function isPublic( array $col ): bool {
		return ! isset( $col['public'] ) || ! empty( $col['public'] );
	}

	/** Clean a column list from a form or preset: unique keys, known types. */
	public static function sanitize( $raw ): array {
		$cols = array();
		$seen = array();
		foreach ( (array) $raw as $c ) {
			if ( ! is_array( $c ) ) {
				continue;
			}
			$key   = Text::key( isset( $c['key'] ) ? $c['key'] : '' );
			$label = Text::line( isset( $c['label'] ) ? $c['label'] : '', 100 );
			if ( '' === $key || '' === $label || isset( $seen[ $key ] ) ) {
				continue;
			}
			$type         = isset( $c['type'] ) && in_array( $c['type'], self::TYPES, true ) ? $c['type'] : 'text';
			$cols[]       = array(
				'key'     => $key,
				'label'   => $label,
				'type'    => $type,
				'options' => 'select' === $type ? Text::line( isset( $c['options'] ) ? $c['options'] : '', 2000 ) : '',
				'public'  => ! isset( $c['public'] ) || ! empty( $c['public'] ),
			);
			$seen[ $key ] = true;
		}
		return $cols;
	}

	/** Clean one variation row against the columns. Returns null for a blank row. */
	public static function sanitizeRow( array $columns, $raw ): ?array {
		$raw   = is_array( $raw ) ? $raw : array();
		$row   = array();
		$blank = true;
		foreach ( $columns as $col ) {
			$val = isset( $raw[ $col['key'] ] ) && is_scalar( $raw[ $col['key'] ] ) ? trim( (string) $raw[ $col['key'] ] ) : '';
			if ( 'number' === $col['type'] ) {
				$val = is_numeric( $val ) ? $val : '';
			} elseif ( 'select' === $col['type'] ) {
				$val = array_key_exists( $val, Text::parseOptions( $col['options'] ) ) ? $val : '';
			} else {
				$val = Text::line( $val, 255 );
			}
			$row[ $col['key'] ] = $val;
			if ( '' !== $val && 'select' !== $col['type'] ) {
				$blank = false; // a row with only a select chosen is still empty
			}
		}
		return $blank ? null : $row;
	}
}
