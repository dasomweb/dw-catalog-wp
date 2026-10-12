<?php
namespace DWCatalog\Front;

use DWCatalog\Repo\Settings;

/**
 * Visual settings → CSS custom properties on the catalog root element.
 * Values are validated on save (colors, numbers, enums) so they are safe inside style="".
 */
final class Design {

	public static function defaults(): array {
		return array(
			'accent'            => '#1e5bb8',
			'font_family'       => '',
			'title_size'        => 16,
			'title_weight'      => 600,
			'title_color'       => '#1f2937',
			'field_label_size'  => 12,
			'field_label_color' => '#9ca3af',
			'field_value_size'  => 13,
			'field_value_color' => '#1f2937',
			'card_bg'           => '#ffffff',
			'card_border_color' => '#e5e7eb',
			'card_border_width' => 1,
			'card_radius'       => 8,
			'card_shadow'       => 'sm',
			'card_padding'      => 0,
			'grid_gap'          => 20,
			'columns'           => 4,
		);
	}

	public static function get( Settings $s ): array {
		$saved = $s->get( 'design' );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function save( Settings $s, array $in ): void {
		$d   = self::defaults();
		$out = array();
		foreach ( $d as $key => $default ) {
			$v = isset( $in[ $key ] ) ? trim( (string) $in[ $key ] ) : (string) $default;
			if ( is_int( $default ) ) {
				$out[ $key ] = max( 0, min( 'title_weight' === $key ? 900 : 200, (int) $v ) );
			} elseif ( 'card_shadow' === $key ) {
				$out[ $key ] = in_array( $v, array( 'none', 'sm', 'md', 'lg' ), true ) ? $v : $default;
			} elseif ( 'font_family' === $key ) {
				$out[ $key ] = substr( preg_replace( '/[^A-Za-z0-9 \-]/', '', $v ), 0, 60 );
			} else {
				$out[ $key ] = self::color( $v ) ? $v : $default;
			}
		}
		$out['columns'] = max( 1, min( 6, $out['columns'] ) );
		$s->set( 'design', $out );
	}

	public static function color( string $v ): bool {
		return (bool) preg_match( '/^#[0-9a-fA-F]{3,8}$/', $v )
			|| (bool) preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d+)\s*)?\)$/', $v );
	}

	/** style="" value for the catalog root. */
	public static function style( Settings $s ): string {
		$d      = self::get( $s );
		$shadow = array(
			'none' => 'none',
			'sm'   => '0 1px 3px rgba(0,0,0,0.05)',
			'md'   => '0 4px 12px rgba(0,0,0,0.08)',
			'lg'   => '0 8px 24px rgba(0,0,0,0.12)',
		);
		$vars   = array(
			'--dwcat-accent'            => $d['accent'],
			'--dwcat-font-family'       => '' !== $d['font_family'] ? "'" . $d['font_family'] . "', inherit" : 'inherit',
			'--dwcat-title-size'        => $d['title_size'] . 'px',
			'--dwcat-title-weight'      => $d['title_weight'],
			'--dwcat-title-color'       => $d['title_color'],
			'--dwcat-field-label-size'  => $d['field_label_size'] . 'px',
			'--dwcat-field-label-color' => $d['field_label_color'],
			'--dwcat-field-value-size'  => $d['field_value_size'] . 'px',
			'--dwcat-field-value-color' => $d['field_value_color'],
			'--dwcat-card-bg'           => $d['card_bg'],
			'--dwcat-card-border-color' => $d['card_border_color'],
			'--dwcat-card-border-width' => $d['card_border_width'] . 'px',
			'--dwcat-card-radius'       => $d['card_radius'] . 'px',
			'--dwcat-card-shadow'       => isset( $shadow[ $d['card_shadow'] ] ) ? $shadow[ $d['card_shadow'] ] : $shadow['sm'],
			'--dwcat-card-padding'      => $d['card_padding'] . 'px',
			'--dwcat-grid-gap'          => $d['grid_gap'] . 'px',
		);
		$css = '';
		foreach ( $vars as $k => $v ) {
			$css .= $k . ':' . $v . ';';
		}
		return $css;
	}
}
