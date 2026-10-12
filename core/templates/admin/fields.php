<?php
/**
 * Field schema + presets + variation columns.
 *
 * @var DWCatalog\Admin\AdminApp $app
 * @var DWCatalog\Catalog $c
 * @var string $type
 */
use DWCatalog\Fields\FieldConfig;
use DWCatalog\Fields\Presets;
use DWCatalog\Fields\VariationColumns;
use DWCatalog\Support\Html;

$current = 'fields';
$fields  = $c->fields()->fields( $type );
$cols    = $c->fields()->variationColumns( $type );
$presets = Presets::get_presets();
$types   = array(
	'text'     => 'Text',
	'textarea' => 'Textarea',
	'number'   => 'Number',
	'email'    => 'Email',
	'url'      => 'URL',
	'date'     => 'Date',
	'select'   => 'Select (Dropdown)',
);

$fieldRow = function ( $i, array $f ) use ( $types, $app ) {
	$p   = 'fields[' . $i . ']';
	$chk = function ( $name, $on ) use ( $p ) {
		return '<td class="dwc-c"><input type="checkbox" name="' . $p . '[' . $name . ']" value="1"' . ( $on ? ' checked' : '' ) . '></td>';
	};
	$h  = '<tr' . ( ! empty( $f['_preset'] ) ? ' class="dwc-added"' : '' ) . '><td class="dwc-move"><button type="button" class="dwc-icon" data-dwc-up>↑</button><button type="button" class="dwc-icon" data-dwc-down>↓</button></td>';
	$h .= '<td><input type="text" name="' . $p . '[label]" value="' . Html::attr( isset( $f['label'] ) ? $f['label'] : '' ) . '" required></td>';
	$h .= '<td><input type="text" name="' . $p . '[meta_key]" value="' . Html::attr( isset( $f['meta_key'] ) ? $f['meta_key'] : '' ) . '" required pattern="[a-z0-9_]+"></td>';
	$h .= '<td><select name="' . $p . '[type]">';
	foreach ( $types as $k => $l ) {
		$h .= '<option value="' . $k . '"' . ( ( isset( $f['type'] ) ? $f['type'] : 'text' ) === $k ? ' selected' : '' ) . '>' . Html::e( $app->t( $l ) ) . '</option>';
	}
	$h .= '</select></td>';
	$h .= $chk( 'required', ! empty( $f['required'] ) );
	$h .= '<td><input type="text" name="' . $p . '[options]" value="' . Html::attr( isset( $f['options'] ) ? $f['options'] : '' ) . '" placeholder="val:Label,val2:Label2"></td>';
	$h .= $chk( 'show_in_list', ! isset( $f['show_in_list'] ) || ! empty( $f['show_in_list'] ) );
	$h .= $chk( 'show_in_export', ! isset( $f['show_in_export'] ) || ! empty( $f['show_in_export'] ) );
	$h .= $chk( 'show_in_detail', isset( $f['meta_key'] ) ? FieldConfig::isPublic( $f ) : true );
	$h .= '<td class="dwc-c"><input type="radio" name="title_field" value="' . Html::attr( $i ) . '"' . ( ! empty( $f['is_title_field'] ) ? ' checked' : '' ) . '></td>';
	return $h . '<td class="dwc-c"><button type="button" class="dwc-icon dwc-danger" data-dwc-remove>×</button></td></tr>';
};
$colRow = function ( $i, array $col ) use ( $app ) {
	$p  = 'variation_columns[' . $i . ']';
	$t  = isset( $col['type'] ) ? $col['type'] : 'text';
	$h  = '<tr' . ( ! empty( $col['_preset'] ) ? ' class="dwc-added"' : '' ) . '><td class="dwc-move"><button type="button" class="dwc-icon" data-dwc-up>↑</button><button type="button" class="dwc-icon" data-dwc-down>↓</button></td>';
	$h .= '<td><input type="text" name="' . $p . '[label]" value="' . Html::attr( isset( $col['label'] ) ? $col['label'] : '' ) . '" required></td>';
	$h .= '<td><input type="text" name="' . $p . '[key]" value="' . Html::attr( isset( $col['key'] ) ? $col['key'] : '' ) . '" required pattern="[a-z0-9_]+"></td>';
	$h .= '<td><select name="' . $p . '[type]">';
	foreach ( array( 'text' => 'Text', 'number' => 'Number', 'select' => 'Select (Dropdown)' ) as $k => $l ) {
		$h .= '<option value="' . $k . '"' . ( $t === $k ? ' selected' : '' ) . '>' . Html::e( $app->t( $l ) ) . '</option>';
	}
	$h .= '</select></td>';
	$h .= '<td><input type="text" name="' . $p . '[options]" value="' . Html::attr( isset( $col['options'] ) ? $col['options'] : '' ) . '" placeholder="val:Label,val2:Label2"></td>';
	$h .= '<td class="dwc-c"><input type="hidden" name="' . $p . '[public]" value="0"><input type="checkbox" name="' . $p . '[public]" value="1"' . ( VariationColumns::isPublic( $col ) ? ' checked' : '' ) . '></td>';
	return $h . '<td class="dwc-c"><button type="button" class="dwc-icon dwc-danger" data-dwc-remove>×</button></td></tr>';
};
?>
<div class="dwc-head"><h1><?php echo Html::e( $app->t( 'Fields' ) ); ?></h1></div>
<?php include __DIR__ . '/_nav.php'; ?>

<?php echo $app->form( 'save_fields', $type, false, 'class="dwc-fields"' ); ?>
	<div class="dwc-box" data-dwc-presets='<?php echo Html::attr( json_encode( $presets ) ); ?>' data-dwc-default-cols='<?php echo Html::attr( json_encode( array_column( VariationColumns::defaults(), 'key' ) ) ); ?>'>
		<h2><?php echo Html::e( $app->t( 'Business type presets' ) ); ?></h2>
		<p class="dwc-muted"><?php echo Html::e( $app->t( 'Pick your business type to add its field set. Picking another type switches the set; fields that already exist are kept. Nothing is saved until you click "Save fields".' ) ); ?></p>
		<?php foreach ( $presets as $g => $group ) : ?>
			<div class="dwc-presets">
				<strong><?php echo Html::e( $app->t( $group['label'] ) ); ?></strong>
				<?php foreach ( $group['presets'] as $k => $preset ) : ?>
					<button type="button" class="dwc-btn" data-dwc-preset="<?php echo Html::attr( $g . '.' . $k ); ?>" title="<?php echo Html::attr( implode( ', ', array_column( $preset['fields'], 'label' ) ) ); ?>">
						<?php echo Html::e( $app->t( $preset['label'] ) ); ?> <span class="dwc-muted">(<?php echo count( $preset['fields'] ); ?>)</span>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
		<p class="dwc-notice dwc-notice-info" data-dwc-preset-result hidden></p>
	</div>

	<h2><?php echo Html::e( $app->t( 'Fields' ) ); ?></h2>
	<table class="dwc-table dwc-rows" data-dwc-rows="fields">
		<thead><tr>
			<th></th>
			<th><?php echo Html::e( $app->t( 'Label' ) ); ?></th>
			<th><?php echo Html::e( $app->t( 'Key' ) ); ?></th>
			<th><?php echo Html::e( $app->t( 'Type' ) ); ?></th>
			<th class="dwc-c"><?php echo Html::e( $app->t( 'Required' ) ); ?></th>
			<th><?php echo Html::e( $app->t( 'Options (for Select)' ) ); ?></th>
			<th class="dwc-c" title="<?php echo Html::attr( $app->t( 'Column in the admin list and field on catalog cards' ) ); ?>"><?php echo Html::e( $app->t( 'List' ) ); ?></th>
			<th class="dwc-c" title="<?php echo Html::attr( $app->t( 'Available in PDF export' ) ); ?>"><?php echo Html::e( $app->t( 'PDF' ) ); ?></th>
			<th class="dwc-c" title="<?php echo Html::attr( $app->t( 'Public: shown on the website and usable for site search, filters and sorting. Unchecked fields never leave the admin.' ) ); ?>"><?php echo Html::e( $app->t( 'Public' ) ); ?></th>
			<th class="dwc-c"><?php echo Html::e( $app->t( 'Name field' ) ); ?></th>
			<th></th>
		</tr></thead>
		<tbody>
			<?php foreach ( $fields as $i => $f ) { echo $fieldRow( $i, $f ); } ?>
		</tbody>
	</table>
	<template data-dwc-template="fields"><?php echo $fieldRow( '__i__', array() ); ?></template>
	<p><button type="button" class="dwc-btn" data-dwc-add="fields"><?php echo Html::e( $app->t( 'Add field' ) ); ?></button></p>
	<p class="dwc-muted"><?php echo Html::e( $app->t( 'Public: costs, supplier info and internal notes should stay unchecked — they are then never printed on the site, never searchable and never filterable by visitors.' ) ); ?></p>

	<h2 id="variation-columns"><?php echo Html::e( $app->t( 'Variation columns' ) ); ?></h2>
	<p class="dwc-muted"><?php echo Html::e( $app->t( 'Columns of each item\'s Variations table. Remove all columns to turn variations off. Changing a key or removing a column hides values already entered under it.' ) ); ?></p>
	<table class="dwc-table dwc-rows" data-dwc-rows="cols">
		<thead><tr>
			<th></th>
			<th><?php echo Html::e( $app->t( 'Label' ) ); ?></th>
			<th><?php echo Html::e( $app->t( 'Key' ) ); ?></th>
			<th><?php echo Html::e( $app->t( 'Type' ) ); ?></th>
			<th><?php echo Html::e( $app->t( 'Options (for Select)' ) ); ?></th>
			<th class="dwc-c"><?php echo Html::e( $app->t( 'Public' ) ); ?></th>
			<th></th>
		</tr></thead>
		<tbody>
			<?php foreach ( $cols as $i => $col ) { echo $colRow( $i, $col ); } ?>
		</tbody>
	</table>
	<template data-dwc-template="cols"><?php echo $colRow( '__i__', array() ); ?></template>
	<p><button type="button" class="dwc-btn" data-dwc-add="cols"><?php echo Html::e( $app->t( 'Add column' ) ); ?></button></p>

	<p><button type="submit" class="dwc-btn dwc-btn-primary"><?php echo Html::e( $app->t( 'Save fields' ) ); ?></button></p>
</form>
