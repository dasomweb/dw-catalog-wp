<?php
/**
 * CSV import.
 *
 * @var DWCatalog\Admin\AdminApp $app
 * @var DWCatalog\Catalog $c
 * @var string $type
 */
use DWCatalog\Support\Html;

$current = 'import';
$fields  = $c->fields()->fields( $type );
$cols    = array_merge( array( 'title', 'content', 'excerpt', 'status', 'category', 'tags', 'image_url' ), array_column( array_filter( $fields, function ( $f ) {
	return empty( $f['is_title_field'] );
} ), 'meta_key' ) );
?>
<div class="dwc-head"><h1><?php echo Html::e( $app->t( 'Import CSV' ) ); ?></h1></div>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="dwc-cols">
	<div class="dwc-main">
		<?php echo $app->form( 'import', $type, true ); ?>
			<table class="dwc-form">
				<tr><th><?php echo Html::e( $app->t( 'CSV file' ) ); ?></th><td><input type="file" name="csv" accept=".csv,text/csv" required></td></tr>
				<tr><th><?php echo Html::e( $app->t( 'Delimiter' ) ); ?></th><td>
					<select name="delimiter">
						<option value="semicolon"><?php echo Html::e( $app->t( 'Semicolon (;)' ) ); ?></option>
						<option value="comma"><?php echo Html::e( $app->t( 'Comma (,)' ) ); ?></option>
						<option value="tab"><?php echo Html::e( $app->t( 'Tab' ) ); ?></option>
					</select>
				</td></tr>
				<tr><th><?php echo Html::e( $app->t( 'Default status' ) ); ?></th><td>
					<select name="status">
						<option value="publish"><?php echo Html::e( $app->t( 'Published' ) ); ?></option>
						<option value="draft"><?php echo Html::e( $app->t( 'Draft' ) ); ?></option>
					</select>
				</td></tr>
				<tr><th><?php echo Html::e( $app->t( 'Duplicates' ) ); ?></th><td><label><input type="checkbox" name="skip_duplicates" value="1" checked> <?php echo Html::e( $app->t( 'Skip rows whose name already exists' ) ); ?></label></td></tr>
			</table>
			<p><button type="submit" class="dwc-btn dwc-btn-primary"><?php echo Html::e( $app->t( 'Import' ) ); ?></button></p>
		</form>
	</div>
	<aside class="dwc-side">
		<div class="dwc-box">
			<h2><?php echo Html::e( $app->t( 'Columns' ) ); ?></h2>
			<p class="dwc-muted"><?php echo Html::e( $app->t( 'First row = headers. "title" is required. Category accepts "Parent > Child"; several image URLs are separated by "|".' ) ); ?></p>
			<pre class="dwc-code"><?php echo Html::e( implode( ';', $cols ) ); ?></pre>
		</div>
	</aside>
</div>
