<?php
/**
 * Item list.
 *
 * @var DWCatalog\Admin\AdminApp $app
 * @var DWCatalog\Catalog $c
 * @var string $type
 * @var array $q
 * @var array $result
 * @var string $orderby
 * @var string $order
 * @var string $status
 * @var array $meta
 */
use DWCatalog\Fields\FieldConfig;
use DWCatalog\Support\Html;

$current    = 'items';
$config     = $c->fields()->type( $type );
$listFields = array_filter( $c->fields()->fields( $type ), function ( $f ) {
	return ! empty( $f['show_in_list'] ) && empty( $f['is_title_field'] );
} );
$state = array_filter( array(
	'view'    => 'items',
	'type'    => $type,
	's'       => isset( $q['s'] ) ? (string) $q['s'] : '',
	'cat'     => ! empty( $q['cat'] ) ? (int) $q['cat'] : '',
	'status'  => 'any' !== $status ? $status : '',
	'f'       => array_map( 'reset', $meta ),
	'orderby' => $orderby,
	'order'   => $orderby ? $order : '',
), function ( $v ) {
	return '' !== $v && array() !== $v;
} );
$sortLink = function ( $label, $key ) use ( $app, $state, $orderby, $order ) {
	$next  = ( $orderby === $key && 'asc' === $order ) ? 'desc' : 'asc';
	$arrow = $orderby === $key ? ( 'asc' === $order ? ' ▲' : ' ▼' ) : '';
	return '<a href="' . Html::url( $app->url( array_merge( $state, array( 'orderby' => $key, 'order' => $next, 'paged' => null ) ) ) ) . '">' . Html::e( $label ) . $arrow . '</a>';
};
$filtered = ! empty( $q['s'] ) || ! empty( $q['cat'] ) || $meta || 'any' !== $status;
?>
<div class="dwc-head">
	<h1><?php echo Html::e( $app->t( $config['plural_name'] ) ); ?></h1>
	<a class="dwc-btn dwc-btn-primary" href="<?php echo Html::url( $app->url( array( 'view' => 'edit', 'type' => $type ) ) ); ?>"><?php echo Html::e( $app->t( 'Add New' ) ); ?></a>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<?php echo $app->getForm( array( 'view' => 'items', 'type' => $type ), 'class="dwc-filters"' ); ?>
	<?php if ( $orderby ) : ?>
		<input type="hidden" name="orderby" value="<?php echo Html::attr( $orderby ); ?>">
		<input type="hidden" name="order" value="<?php echo Html::attr( $order ); ?>">
	<?php endif; ?>
	<input type="search" name="s" value="<?php echo Html::attr( isset( $q['s'] ) ? $q['s'] : '' ); ?>" placeholder="<?php echo Html::attr( $app->t( 'Search name, item code, brand…' ) ); ?>">
	<select name="status">
		<option value=""><?php echo Html::e( $app->t( 'All statuses' ) ); ?></option>
		<option value="publish" <?php echo 'publish' === $status ? 'selected' : ''; ?>><?php echo Html::e( $app->t( 'Published' ) ); ?></option>
		<option value="draft" <?php echo 'draft' === $status ? 'selected' : ''; ?>><?php echo Html::e( $app->t( 'Draft' ) ); ?></option>
	</select>
	<?php if ( ! empty( $config['has_category'] ) ) : ?>
		<select name="cat">
			<option value=""><?php echo Html::e( $app->t( 'All categories' ) ); ?></option>
			<?php
			$tree = $c->terms()->tree( $type );
			$opts = function ( $parent, $depth ) use ( &$opts, $tree, $q ) {
				foreach ( isset( $tree[ $parent ] ) ? $tree[ $parent ] : array() as $term ) {
					echo '<option value="' . (int) $term['id'] . '"' . ( ! empty( $q['cat'] ) && (int) $q['cat'] === (int) $term['id'] ? ' selected' : '' ) . '>' . str_repeat( '— ', $depth ) . Html::e( $term['name'] ) . '</option>';
					$opts( (int) $term['id'], $depth + 1 );
				}
			};
			$opts( 0, 0 );
			?>
		</select>
	<?php endif; ?>
	<?php foreach ( $listFields as $field ) : ?>
		<?php if ( 'select' !== $field['type'] ) { continue; } ?>
		<select name="f[<?php echo Html::attr( $field['meta_key'] ); ?>]">
			<option value=""><?php echo Html::e( sprintf( $app->t( 'All %s' ), $field['label'] ) ); ?></option>
			<?php foreach ( \DWCatalog\Support\Text::parseOptions( $field['options'] ) as $val => $label ) : ?>
				<option value="<?php echo Html::attr( $val ); ?>" <?php echo isset( $meta[ $field['meta_key'] ] ) && $meta[ $field['meta_key'] ][0] === (string) $val ? 'selected' : ''; ?>><?php echo Html::e( $label ); ?></option>
			<?php endforeach; ?>
		</select>
	<?php endforeach; ?>
	<button type="submit" class="dwc-btn"><?php echo Html::e( $app->t( 'Filter' ) ); ?></button>
	<?php if ( $filtered ) : ?>
		<a href="<?php echo Html::url( $app->url( array( 'view' => 'items', 'type' => $type ) ) ); ?>"><?php echo Html::e( $app->t( 'Clear' ) ); ?></a>
	<?php endif; ?>
	<span class="dwc-muted"><?php echo Html::e( sprintf( $app->t( '%d items' ), $result['total'] ) ); ?></span>
</form>

<?php if ( ! $result['rows'] ) : ?>
	<p class="dwc-empty"><?php echo Html::e( $filtered ? $app->t( 'No items match these filters.' ) : $app->t( 'No items yet.' ) ); ?></p>
<?php else : ?>
	<?php echo $app->form( 'bulk_items', $type, false, 'data-dwc-bulk' ); ?>
		<div class="dwc-bulkbar">
			<select name="bulk">
				<option value=""><?php echo Html::e( $app->t( 'Bulk actions' ) ); ?></option>
				<?php if ( $c->host()->can( 'catalog.delete' ) ) : ?>
					<option value="delete"><?php echo Html::e( $app->t( 'Delete' ) ); ?></option>
				<?php endif; ?>
			</select>
			<button type="submit" class="dwc-btn" data-dwc-confirm="<?php echo Html::attr( $app->t( 'Delete the selected items? This cannot be undone.' ) ); ?>"><?php echo Html::e( $app->t( 'Apply' ) ); ?></button>
		</div>
		<table class="dwc-table">
			<thead>
				<tr>
					<th class="dwc-check"><input type="checkbox" data-dwc-checkall></th>
					<th class="dwc-thumb"></th>
					<th><?php echo $sortLink( $app->t( 'Name' ), 'title' ); ?></th>
					<?php if ( ! empty( $config['has_category'] ) ) : ?>
						<th><?php echo Html::e( $app->t( 'Category' ) ); ?></th>
					<?php endif; ?>
					<?php foreach ( $listFields as $field ) : ?>
						<th><?php echo $sortLink( $field['label'], $field['meta_key'] ); ?></th>
					<?php endforeach; ?>
					<th><?php echo $sortLink( $app->t( 'Updated' ), 'modified' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['rows'] as $item ) : ?>
					<?php
					$id   = (int) $item['id'];
					$edit = $app->url( array( 'view' => 'edit', 'type' => $type, 'id' => $id ) );
					$img  = $c->images()->main( $id );
					?>
					<tr>
						<td class="dwc-check"><input type="checkbox" name="ids[]" value="<?php echo $id; ?>"></td>
						<td class="dwc-thumb"><?php echo $img ? $c->images()->tag( $img, 'thumb' ) : '<span class="dwc-noimg">—</span>'; ?></td>
						<td>
							<a class="dwc-strong" href="<?php echo Html::url( $edit ); ?>"><?php echo Html::e( $item['title'] ); ?></a>
							<?php if ( 'draft' === $item['status'] ) : ?>
								<span class="dwc-badge"><?php echo Html::e( $app->t( 'Draft' ) ); ?></span>
							<?php endif; ?>
						</td>
						<?php if ( ! empty( $config['has_category'] ) ) : ?>
							<td><?php echo Html::e( implode( ', ', array_column( $c->terms()->ofItem( $type, $id ), 'name' ) ) ?: '—' ); ?></td>
						<?php endif; ?>
						<?php foreach ( $listFields as $field ) : ?>
							<td><?php $v = FieldConfig::display( $field, $c->items()->value( $id, $field['meta_key'] ) ); echo Html::e( '' !== $v ? $v : '—' ); ?></td>
						<?php endforeach; ?>
						<td class="dwc-muted"><?php echo Html::e( substr( $item['updated_at'], 0, 16 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</form>

	<?php if ( $result['pages'] > 1 ) : ?>
		<nav class="dwc-pages">
			<?php for ( $p = 1; $p <= $result['pages']; $p++ ) : ?>
				<?php if ( $p > 2 && $p < $result['pages'] - 1 && abs( $p - $result['page'] ) > 2 ) { if ( 3 === $p || $result['pages'] - 2 === $p ) { echo '<span>…</span>'; } continue; } ?>
				<?php if ( $p === $result['page'] ) : ?>
					<span class="is-current"><?php echo (int) $p; ?></span>
				<?php else : ?>
					<a href="<?php echo Html::url( $app->url( array_merge( $state, array( 'paged' => $p ) ) ) ); ?>"><?php echo (int) $p; ?></a>
				<?php endif; ?>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>
<?php endif; ?>
