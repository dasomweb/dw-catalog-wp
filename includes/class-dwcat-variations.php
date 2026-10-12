<?php
/**
 * Variations — per-item option rows (size, pack, flavor ...).
 *
 * The columns are configurable per post type (DWCAT_Config::get_variation_columns), so a
 * seafood wholesaler can use Size / Grade · Pack Size · Case Price while an apparel shop
 * uses SKU · Size · Color · Price. Field presets bring a matching column set.
 *
 * Rows are stored as one array in post meta META_KEY, keyed by column key. Edited in a
 * repeatable table on the item edit screen; shown as a table on the detail view.
 * Variation values are searchable (DWCAT_Catalog_Query matches the serialized meta value).
 *
 * @package DW_Catalog_WP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DWCAT_Variations {

	const META_KEY = '_dwcat_variations';

	const COLUMN_TYPES = array( 'text', 'number', 'select' );

	const STOCK_OPTIONS = 'in_stock:In Stock,out_of_stock:Out of Stock,pre_order:Pre-Order';

	/** Columns used until a post type saves its own. */
	public static function get_default_columns() {
		return array(
			array( 'key' => 'name', 'label' => 'Option', 'type' => 'text', 'options' => '' ),
			array( 'key' => 'item_code', 'label' => 'Item Code / SKU', 'type' => 'text', 'options' => '' ),
			array( 'key' => 'pack_size', 'label' => 'Pack Size', 'type' => 'text', 'options' => '' ),
			array( 'key' => 'price', 'label' => 'Price', 'type' => 'number', 'options' => '' ),
			array( 'key' => 'stock', 'label' => 'Stock', 'type' => 'select', 'options' => self::STOCK_OPTIONS ),
		);
	}

	/**
	 * Sanitize a column definition list (from the Manage Fields form or a preset).
	 *
	 * @return array[]
	 */
	public static function sanitize_columns( $raw ) {
		$cols = array();
		$seen = array();
		foreach ( (array) $raw as $c ) {
			$key   = isset( $c['key'] ) ? sanitize_key( $c['key'] ) : '';
			$label = isset( $c['label'] ) ? sanitize_text_field( $c['label'] ) : '';
			if ( '' === $key || '' === $label || isset( $seen[ $key ] ) ) {
				continue;
			}
			$type   = isset( $c['type'] ) && in_array( $c['type'], self::COLUMN_TYPES, true ) ? $c['type'] : 'text';
			$cols[] = array(
				'key'     => $key,
				'label'   => $label,
				'type'    => $type,
				'options' => 'select' === $type && isset( $c['options'] ) ? sanitize_text_field( $c['options'] ) : '',
			);
			$seen[ $key ] = true;
		}
		return $cols;
	}

	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
	}

	/** @return array[] Variation rows. */
	public static function get( $post_id ) {
		$rows = get_post_meta( $post_id, self::META_KEY, true );
		return is_array( $rows ) ? $rows : array();
	}

	// ─────────────────────────────────────────────────────────────
	// Admin
	// ─────────────────────────────────────────────────────────────

	public function add_meta_boxes() {
		foreach ( DWCAT_Config::get_post_type_slugs() as $slug ) {
			if ( ! DWCAT_Config::get_variation_columns( $slug ) ) {
				continue; // variations turned off for this post type
			}
			add_meta_box(
				'dwcat_variations',
				__( 'Variations', 'dw-catalog-wp' ),
				array( $this, 'render_meta_box' ),
				$slug,
				'normal',
				'default'
			);
		}
	}

	public function render_meta_box( $post ) {
		$rows    = self::get( $post->ID );
		$columns = DWCAT_Config::get_variation_columns( $post->post_type );
		wp_nonce_field( 'dwcat_save_variations', 'dwcat_variations_nonce' );
		?>
		<p class="description"><?php esc_html_e( 'Add options when this item comes in several sizes, packs or flavors. Leave empty for a single item.', 'dw-catalog-wp' ); ?></p>
		<table class="widefat striped dwcat-variations-table">
			<thead>
				<tr>
					<th style="width: 24px;"></th>
					<?php foreach ( $columns as $col ) : ?>
						<th><?php echo esc_html( $col['label'] ); ?></th>
					<?php endforeach; ?>
					<th style="width: 40px;"></th>
				</tr>
			</thead>
			<tbody data-dwcat-var-body>
				<?php foreach ( $rows as $i => $row ) : ?>
					<?php $this->render_row( $i, $row, $columns ); ?>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button" data-dwcat-var-add><?php esc_html_e( 'Add Variation', 'dw-catalog-wp' ); ?></button>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a href="<?php echo esc_url( admin_url( DWCAT_Settings::manage_fields_slug( $post->post_type ) ) . '#dw-variation-columns' ); ?>" style="margin-left: 8px;"><?php esc_html_e( 'Edit variation columns', 'dw-catalog-wp' ); ?></a>
			<?php endif; ?>
		</p>
		<script type="text/html" id="dwcat-var-template"><?php $this->render_row( '__I__', array(), $columns ); ?></script>
		<script>
		jQuery(function($){
			var $body = $('[data-dwcat-var-body]'), idx = <?php echo (int) count( $rows ); ?>;
			$('[data-dwcat-var-add]').on('click', function(){
				$body.append($('#dwcat-var-template').html().replace(/__I__/g, idx++));
			});
			$body.on('click', '[data-dwcat-var-remove]', function(){ $(this).closest('tr').remove(); });
			if ($.fn.sortable) { $body.sortable({ handle: '.dwcat-var-handle', axis: 'y' }); }
		});
		</script>
		<?php
	}

	private function render_row( $i, $row, $columns ) {
		$name = 'dwcat_variations[' . $i . ']';
		echo '<tr><td><span class="dashicons dashicons-menu dwcat-var-handle" style="cursor: move; color: #999;"></span></td>';
		foreach ( $columns as $col ) {
			$key   = $col['key'];
			$value = isset( $row[ $key ] ) ? $row[ $key ] : '';
			$attr  = esc_attr( $name . '[' . $key . ']' );
			echo '<td>';
			if ( 'select' === $col['type'] ) {
				echo '<select name="' . $attr . '" class="widefat"><option value="">—</option>';
				foreach ( DWCAT_Config::parse_select_options( $col['options'] ) as $o_val => $o_label ) {
					echo '<option value="' . esc_attr( $o_val ) . '"' . selected( (string) $value, (string) $o_val, false ) . '>' . esc_html( $o_label ) . '</option>';
				}
				echo '</select>';
			} elseif ( 'number' === $col['type'] ) {
				echo '<input type="number" name="' . $attr . '" value="' . esc_attr( $value ) . '" class="widefat" step="any" min="0">';
			} else {
				echo '<input type="text" name="' . $attr . '" value="' . esc_attr( $value ) . '" class="widefat">';
			}
			echo '</td>';
		}
		echo '<td><button type="button" class="button-link" style="color: #b32d2e;" data-dwcat-var-remove aria-label="' . esc_attr__( 'Remove', 'dw-catalog-wp' ) . '">&times;</button></td></tr>';
	}

	public function save( $post_id, $post ) {
		if ( ! DWCAT_Config::is_our_post_type( $post->post_type ) ) {
			return;
		}
		if ( ! isset( $_POST['dwcat_variations_nonce'] ) || ! wp_verify_nonce( $_POST['dwcat_variations_nonce'], 'dwcat_save_variations' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// 게이트 3 — 라이선스 없이는 카탈로그 값을 저장하지 않습니다.
		if ( ! dwcat_can_save_meta() ) {
			return;
		}

		$raw     = isset( $_POST['dwcat_variations'] ) && is_array( $_POST['dwcat_variations'] ) ? wp_unslash( $_POST['dwcat_variations'] ) : array();
		$columns = DWCAT_Config::get_variation_columns( $post->post_type );
		$rows    = array();
		foreach ( $raw as $r ) {
			$row   = array();
			$blank = true;
			foreach ( $columns as $col ) {
				$key = $col['key'];
				$val = isset( $r[ $key ] ) && is_scalar( $r[ $key ] ) ? trim( (string) $r[ $key ] ) : '';
				if ( 'number' === $col['type'] ) {
					$val = is_numeric( $val ) ? $val : '';
				} elseif ( 'select' === $col['type'] ) {
					$val = array_key_exists( $val, DWCAT_Config::parse_select_options( $col['options'] ) ) ? $val : '';
				} else {
					$val = sanitize_text_field( $val );
				}
				$row[ $key ] = $val;
				if ( '' !== $val && 'select' !== $col['type'] ) {
					$blank = false; // a row with only a select chosen is still an empty row
				}
			}
			if ( ! $blank ) {
				$rows[] = $row;
			}
		}

		if ( $rows ) {
			update_post_meta( $post_id, self::META_KEY, $rows );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	// ─────────────────────────────────────────────────────────────
	// Frontend
	// ─────────────────────────────────────────────────────────────

	/** Variations table for the detail view. Columns with no values are hidden. */
	public static function render_table( $post_id ) {
		$rows = self::get( $post_id );
		if ( ! $rows ) {
			return '';
		}
		$columns = array();
		foreach ( DWCAT_Config::get_variation_columns( get_post_type( $post_id ) ) as $col ) {
			foreach ( $rows as $row ) {
				if ( isset( $row[ $col['key'] ] ) && '' !== $row[ $col['key'] ] ) {
					$columns[] = $col;
					break;
				}
			}
		}
		if ( ! $columns ) {
			return '';
		}

		$html = '<div class="dwcat-variations"><h3 class="dwcat-variations-title">' . esc_html__( 'Options', 'dw-catalog-wp' ) . '</h3><table class="dwcat-variations-table"><thead><tr>';
		foreach ( $columns as $col ) {
			$html .= '<th>' . esc_html( $col['label'] ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$html .= '<tr>';
			foreach ( $columns as $col ) {
				$val = isset( $row[ $col['key'] ] ) ? (string) $row[ $col['key'] ] : '';
				if ( 'select' === $col['type'] && '' !== $val ) {
					$opts = DWCAT_Config::parse_select_options( $col['options'] );
					$val  = '<span class="dwcat-var-opt dwcat-var-' . esc_attr( $col['key'] . '-' . $val ) . '">' . esc_html( isset( $opts[ $val ] ) ? $opts[ $val ] : $val ) . '</span>';
				} else {
					$val = esc_html( $val );
				}
				$html .= '<td data-label="' . esc_attr( $col['label'] ) . '">' . $val . '</td>';
			}
			$html .= '</tr>';
		}
		return $html . '</tbody></table></div>';
	}
}
