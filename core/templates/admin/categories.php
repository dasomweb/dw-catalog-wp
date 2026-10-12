<?php
/**
 * Categories.
 *
 * @var DWCatalog\Admin\AdminApp $app
 * @var DWCatalog\Catalog $c
 * @var string $type
 */
use DWCatalog\Support\Html;

$current = 'categories';
$tree    = $c->terms()->tree( $type );
$counts  = $c->terms()->counts( $type );
$all     = $c->terms()->all( $type );

$parentSelect = function ( $selected, $exclude ) use ( $tree, $app, $c, $type ) {
	$skip = $exclude ? $c->terms()->withDescendants( $type, array( $exclude ) ) : array();
	$h    = '<select name="parent"><option value="0">' . Html::e( $app->t( '(top level)' ) ) . '</option>';
	$walk = function ( $parent, $depth ) use ( &$walk, &$h, $tree, $selected, $skip ) {
		foreach ( isset( $tree[ $parent ] ) ? $tree[ $parent ] : array() as $t ) {
			if ( in_array( (int) $t['id'], $skip, true ) ) {
				continue;
			}
			$h .= '<option value="' . (int) $t['id'] . '"' . ( (int) $selected === (int) $t['id'] ? ' selected' : '' ) . '>' . str_repeat( '— ', $depth ) . Html::e( $t['name'] ) . '</option>';
			$walk( (int) $t['id'], $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $h . '</select>';
};
?>
<div class="dwc-head"><h1><?php echo Html::e( $app->t( 'Categories' ) ); ?></h1></div>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="dwc-cols">
	<div class="dwc-main">
		<?php if ( ! $all ) : ?>
			<p class="dwc-empty"><?php echo Html::e( $app->t( 'No categories yet. Add the first one on the right.' ) ); ?></p>
		<?php else : ?>
			<table class="dwc-table">
				<thead><tr>
					<th><?php echo Html::e( $app->t( 'Name' ) ); ?></th>
					<th><?php echo Html::e( $app->t( 'Slug' ) ); ?></th>
					<th><?php echo Html::e( $app->t( 'Parent' ) ); ?></th>
					<th><?php echo Html::e( $app->t( 'Order' ) ); ?></th>
					<th><?php echo Html::e( $app->t( 'Items' ) ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
					<?php
					$walk = function ( $parent, $depth ) use ( &$walk, $tree, $app, $type, $counts, $parentSelect ) {
						foreach ( isset( $tree[ $parent ] ) ? $tree[ $parent ] : array() as $t ) {
							$fid = 'dwc-term-' . (int) $t['id'];
							?>
							<tr>
								<td>
									<?php echo $app->form( 'save_term', $type, false, 'id="' . $fid . '"' ); ?>
										<input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
									</form>
									<span class="dwc-indent"><?php echo str_repeat( '— ', $depth ); ?></span>
									<input type="text" name="name" form="<?php echo $fid; ?>" value="<?php echo Html::attr( $t['name'] ); ?>" required>
								</td>
								<td><input type="text" name="slug" form="<?php echo $fid; ?>" value="<?php echo Html::attr( $t['slug'] ); ?>"></td>
								<td><?php echo str_replace( '<select name="parent">', '<select name="parent" form="' . $fid . '">', $parentSelect( (int) $t['parent'], (int) $t['id'] ) ); ?></td>
								<td><input type="number" name="position" form="<?php echo $fid; ?>" value="<?php echo (int) $t['position']; ?>" class="dwc-narrow"></td>
								<td><?php echo isset( $counts[ (int) $t['id'] ] ) ? (int) $counts[ (int) $t['id'] ] : 0; ?></td>
								<td class="dwc-nowrap">
									<button type="submit" form="<?php echo $fid; ?>" class="dwc-btn"><?php echo Html::e( $app->t( 'Save' ) ); ?></button>
									<?php echo $app->form( 'delete_term', $type, false, 'class="dwc-inline"' ); ?>
										<input type="hidden" name="id" value="<?php echo (int) $t['id']; ?>">
										<button type="submit" class="dwc-link dwc-danger" data-dwc-confirm="<?php echo Html::attr( $app->t( 'Delete this category? Items stay; sub-categories move up one level.' ) ); ?>"><?php echo Html::e( $app->t( 'Delete' ) ); ?></button>
									</form>
								</td>
							</tr>
							<?php
							$walk( (int) $t['id'], $depth + 1 );
						}
					};
					$walk( 0, 0 );
					?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<aside class="dwc-side">
		<div class="dwc-box">
			<h2><?php echo Html::e( $app->t( 'Add category' ) ); ?></h2>
			<?php echo $app->form( 'save_term', $type ); ?>
				<label class="dwc-label"><?php echo Html::e( $app->t( 'Name' ) ); ?></label>
				<input type="text" name="name" required>
				<label class="dwc-label"><?php echo Html::e( $app->t( 'Slug' ) ); ?> <span class="dwc-muted"><?php echo Html::e( $app->t( '(optional)' ) ); ?></span></label>
				<input type="text" name="slug">
				<label class="dwc-label"><?php echo Html::e( $app->t( 'Parent' ) ); ?></label>
				<?php echo $parentSelect( 0, 0 ); ?>
				<p><button type="submit" class="dwc-btn dwc-btn-primary"><?php echo Html::e( $app->t( 'Add category' ) ); ?></button></p>
			</form>
		</div>
	</aside>
</div>
