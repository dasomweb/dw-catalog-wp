<?php
/**
 * PDF export.
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
?>
<div class="dwc-head"><h1><?php echo Html::e( $app->t( 'Export PDF' ) ); ?></h1></div>
<?php include __DIR__ . '/_nav.php'; ?>

<?php echo $app->form( 'export', $type ); ?>
	<div class="dwc-cols">
		<div class="dwc-main">
			<h2><?php echo Html::e( $app->t( 'Fields' ) ); ?></h2>
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
				<div class="dwc-checklist">
					<?php
					$list = function ( $parent ) use ( &$list, $tree ) {
						if ( empty( $tree[ $parent ] ) ) {
							return;
						}
						echo '<ul>';
						foreach ( $tree[ $parent ] as $t ) {
							echo '<li><label><input type="checkbox" name="cats[]" value="' . (int) $t['id'] . '"> ' . Html::e( $t['name'] ) . '</label>';
							$list( (int) $t['id'] );
							echo '</li>';
						}
						echo '</ul>';
					};
					$list( 0 );
					?>
				</div>
			</div>
		</aside>
	</div>
</form>
