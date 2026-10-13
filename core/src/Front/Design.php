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
			'accent'            => '#1466d6',
			'font_family'       => 'IBM Plex Sans',
			'title_size'        => 16,
			'title_weight'      => 600,
			'title_color'       => '#0f1b2d',
			'field_label_size'  => 13,
			'field_label_color' => '#5b6b82',
			'field_value_size'  => 13,
			'field_value_color' => '#0f1b2d',
			'card_bg'           => '#ffffff',
			'card_border_color' => '#e3e8ef',
			'card_border_width' => 1,
			'card_radius'       => 12,
			'card_shadow'       => 'none',
			'card_padding'      => 0,
			'grid_gap'          => 18,
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

	/**
	 * Web fonts the design ships with (loaded from Google Fonts). Any other family name is used
	 * as-is from the theme / system. @return string stylesheet URL or ''
	 */
	public static function fontUrl( Settings $s ): string {
		$known = array(
			'IBM Plex Sans' => 'family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700',
		);
		$family = self::get( $s )['font_family'];
		return isset( $known[ $family ] ) ? 'https://fonts.googleapis.com/css2?' . $known[ $family ] . '&display=swap' : '';
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
			'--dwcat-font-family'       => '' !== $d['font_family'] ? "'" . $d['font_family'] . "', system-ui, sans-serif" : 'inherit',
			'--dwcat-mono'              => 'IBM Plex Sans' === $d['font_family'] ? "'IBM Plex Mono', ui-monospace, monospace" : 'ui-monospace, SFMono-Regular, Menlo, monospace',
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
