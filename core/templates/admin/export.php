<?php
/**
 * Export: CSV (round-trips with Import) and PDF catalog.
 *
 * @var DWCatalog\Admin\AdminApp $app
 * @var DWCatalog\Catalog $c
 * @var string $type
 */
use DWCatalog\Support\Html;

$current = 'export';
$tree    = $c->terms()->tree( $type );
$options = array( '_title' => $app->t( 'Name' ) );
foreach ( $c->fields()->fields( $type ) as $f ) {
	if ( ! empty( $f['show_in_export'] ) && empty( $f['is_title_field'] ) ) {
		$options[ $f['meta_key'] ] = $f['label'];
	}
}
$options['_category'] = $app->t( 'Category' );

$catList = function ( $parent ) use ( &$catList, $tree ) {
	if ( empty( $tree[ $parent ] ) ) {
		return;
	}
	echo '<ul>';
	foreach ( $tree[ $parent ] as $t ) {
		echo '<li><label><input type="checkbox" name="cats[]" value="' . (int) $t['id'] . '"> ' . Html::e( $t['name'] ) . '</label>';
		$catList( (int) $t['id'] );
		echo '</li>';
	}
	echo '</ul>';
};
?>
<?php echo $app->head( $app->t( 'Import / Export' ) ); ?>
<?php include __DIR__ . '/_nav.php'; ?>

<h2><?php echo Html::e( $app->t( 'CSV (spreadsheet)' ) ); ?></h2>
<p class="dwc-muted"><?php echo Html::e( $app->t( 'All fields, categories, tags, image URLs and variations (one row per variation). Edit in a spreadsheet and bring it back with Import → Update.' ) ); ?></p>
<?php echo $app->form( 'export_csv', $type ); ?>
	<div class="dwc-cols">
		<div class="dwc-main">
			<table class="dwc-form">
				<tr><th><?php echo Html::e( $app->t( 'Status' ) ); ?></th><td>
					<select name="status">
						<option value="any"><?php echo Html::e( $app->t( 'All' ) ); ?></option>
						<option value="publish"><?php echo Html::e( $app->t( 'Published' ) ); ?></option>
						<option value="draft"><?php echo Html::e( $app->t( 'Draft' ) ); ?></option>
					</select>
				</td></tr>
				<tr><th><?php echo Html::e( $app->t( 'Delimiter' ) ); ?></th><td>
					<select name="delimiter">
						<option value="comma"><?php echo Html::e( $app->t( 'Comma (,)' ) ); ?></option>
						<option value="semicolon"><?php echo Html::e( $app->t( 'Semicolon (;)' ) ); ?></option>
						<option value="tab"><?php echo Html::e( $app->t( 'Tab' ) ); ?></option>
					</select>
				</td></tr>
			</table>
			<p><button type="submit" class="dwc-btn dwc-btn-primary"><?php echo Html::e( $app->t( 'Download CSV' ) ); ?></button></p>
		</div>
		<aside class="dwc-side">
			<div class="dwc-box">
				<div class="dwc-label"><?php echo Html::e( $app->t( 'Categories' ) ); ?> <span class="dwc-muted"><?php echo Html::e( $app->t( '(none = all)' ) ); ?></span></div>
				<div class="dwc-checklist"><?php $catList( 0 ); ?></div>
			</div>
		</aside>
	</div>
</form>

<h2><?php echo Html::e( $app->t( 'PDF catalog' ) ); ?></h2>
<?php echo $app->form( 'export', $type ); ?>
	<div class="dwc-cols">
		<div class="dwc-main">
			<table class="dwc-form">
				<?php foreach ( $options as $key => $label ) : ?>
					<tr>
						<td class="dwc-c"><input type="checkbox" name="cols[]" value="<?php echo Html::attr( $key ); ?>" checked></td>
						<td><?php echo Html::e( $label ); ?></td>
						<td><input type="text" name="labels[<?php echo Html::attr( $key ); ?>]" value="<?php echo Html::attr( $label ); ?>"></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<p><label><input type="checkbox" name="image" value="1" checked> <?php echo Html::e( $app->t( 'Include main image' ) ); ?></label></p>
			<p><?php echo Html::e( $app->t( 'Cards per row' ) ); ?>
				<select name="per_row"><option>1</option><option selected>2</option><option>3</option><option>4</option></select>
			</p>
			<p><button type="submit" class="dwc-btn dwc-btn-primary"><?php echo Html::e( $app->t( 'Generate PDF' ) ); ?></button></p>
		</div>
		<aside class="dwc-side">
			<div class="dwc-box">
				<div class="dwc-label"><?php echo Html::e( $app->t( 'Categories' ) ); ?></div>
				<div class="dwc-checklist"><?php $catList( 0 ); ?></div>
			</div>
		</aside>
	</div>
</form>
