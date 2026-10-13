<?php
/**
 * Categories: a tree you re-arrange by drag & drop (order + parent), edit in place, add on the right.
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

$parentSelect = function ( $selected ) use ( $tree, $app ) {
	$h    = '<select name="parent" id="dwc-new-parent"><option value="0">' . Html::e( $app->t( '(top level)' ) ) . '</option>';
	$walk = function ( $parent, $depth ) use ( &$walk, &$h, $tree, $selected ) {
		foreach ( isset( $tree[ $parent ] ) ? $tree[ $parent ] : array() as $t ) {
			$h .= '<option value="' . (int) $t['id'] . '"' . ( (int) $selected === (int) $t['id'] ? ' selected' : '' ) . '>' . str_repeat( '— ', $depth ) . Html::e( $t['name'] ) . '</option>';
			$walk( (int) $t['id'], $depth + 1 );
		}
	};
	$walk( 0, 0 );
	return $h . '</select>';
};
$t_ = function ( $s ) use ( $app ) {
	return Html::attr( $app->t( $s ) );
};
?>
<?php echo $app->head( $app->t( 'Categories' ) ); ?>
<?php include __DIR__ . '/_nav.php'; ?>

<div class="dwc-cols">
	<div class="dwc-main">
		<?php if ( ! $all ) : ?>
			<p class="dwc-empty"><?php echo Html::e( $app->t( 'No categories yet. Add the first one on the right.' ) ); ?></p>
		<?php else : ?>
			<section class="dwc-card">
				<div class="dwc-tree-head">
					<p class="dwc-muted"><?php echo Html::e( $app->t( 'Drag ⠿ to reorder. Drop on the middle of a category to put it inside; drop above or below to place it next to it. Changes are saved when you click "Save order".' ) ); ?></p>
					<span class="dwc-tree-tools">
						<button type="button" class="dwc-link" data-dwc-tree-all="open"><?php echo Html::e( $app->t( 'Expand all' ) ); ?></button>
						<button type="button" class="dwc-link" data-dwc-tree-all="close"><?php echo Html::e( $app->t( 'Collapse all' ) ); ?></button>
					</span>
				</div>
				<?php
				$walk = function ( $parent ) use ( &$walk, $tree, $app, $type, $counts, $t_ ) {
					echo '<ol class="dwc-tree"' . ( 0 === $parent ? ' data-dwc-tree' : '' ) . '>';
					foreach ( isset( $tree[ $parent ] ) ? $tree[ $parent ] : array() as $t ) {
						$id  = (int) $t['id'];
						$fid = 'dwc-term-' . $id;
						$kids = ! empty( $tree[ $id ] );
						?>
						<li data-id="<?php echo $id; ?>">
							<div class="dwc-node">
								<button type="button" class="dwc-handle" data-dwc-handle title="<?php echo $t_( 'Drag to move' ); ?>" aria-label="<?php echo Html::attr( sprintf( $app->t( 'Move %s' ), $t['name'] ) ); ?>">⠿</button>
								<button type="button" class="dwc-caret" data-dwc-caret aria-expanded="true" title="<?php echo $t_( 'Show / hide sub-categories' ); ?>"<?php echo $kids ? '' : ' hidden'; ?>><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg></button>
								<span class="dwc-node-name"><?php echo Html::e( $t['name'] ); ?></span>
								<span class="dwc-node-slug"><?php echo Html::e( $t['slug'] ); ?></span>
								<span class="dwc-node-count" title="<?php echo $t_( 'Active items in this category' ); ?>"><?php echo isset( $counts[ $id ] ) ? (int) $counts[ $id ] : 0; ?></span>
								<span class="dwc-node-keys">
									<button type="button" class="dwc-icon" data-dwc-key="up" title="<?php echo $t_( 'Move up' ); ?>">↑</button>
									<button type="button" class="dwc-icon" data-dwc-key="down" title="<?php echo $t_( 'Move down' ); ?>">↓</button>
									<button type="button" class="dwc-icon" data-dwc-key="out" title="<?php echo $t_( 'Move out one level' ); ?>">←</button>
									<button type="button" class="dwc-icon" data-dwc-key="in" title="<?php echo $t_( 'Put inside the category above' ); ?>">→</button>
								</span>
								<details class="dwc-node-edit">
									<summary><?php echo Html::e( $app->t( 'Edit' ) ); ?></summary>
									<div class="dwc-node-form">
										<?php echo $app->form( 'save_term', $type, false, 'id="' . $fid . '"' ); ?>
											<input type="hidden" name="id" value="<?php echo $id; ?>">
											<input type="hidden" name="parent" value="<?php echo (int) $t['parent']; ?>">
											<input type="hidden" name="position" value="<?php echo (int) $t['position']; ?>">
											<label><?php echo Html::e( $app->t( 'Name' ) ); ?> <input type="text" name="name" value="<?php echo Html::attr( $t['name'] ); ?>" required></label>
											<label><?php echo Html::e( $app->t( 'Slug' ) ); ?> <input type="text" name="slug" class="dwc-mono" value="<?php echo Html::attr( $t['slug'] ); ?>"></label>
											<button type="submit" class="dwc-btn dwc-btn-primary"><?php echo Html::e( $app->t( 'Save' ) ); ?></button>
										</form>
										<?php echo $app->form( 'delete_term', $type ); ?>
											<input type="hidden" name="id" value="<?php echo $id; ?>">
											<button type="submit" class="dwc-link dwc-danger" data-dwc-confirm="<?php echo $t_( 'Delete this category? Items stay; sub-categories move up one level.' ); ?>"><?php echo Html::e( $app->t( 'Delete' ) ); ?></button>
										</form>
									</div>
								</details>
							</div>
							<?php $walk( $id ); ?>
						</li>
						<?php
					}
					echo '</ol>';
				};
				$walk( 0 );
				?>
				<?php echo $app->form( 'save_tree', $type, false, 'class="dwc-card-foot dwc-tree-save" data-dwc-tree-form' ); ?>
					<input type="hidden" name="tree" value="">
					<span class="dwc-muted" data-dwc-tree-status data-dirty="<?php echo $t_( 'Unsaved changes' ); ?>"></span>
					<span>
						<a class="dwc-btn" href="<?php echo Html::url( $app->url( array( 'view' => 'categories', 'type' => $type ) ) ); ?>" data-dwc-tree-undo hidden><?php echo Html::e( $app->t( 'Undo' ) ); ?></a>
						<button type="submit" class="dwc-btn dwc-btn-primary" data-dwc-tree-submit disabled><?php echo Html::e( $app->t( 'Save order' ) ); ?></button>
					</span>
				</form>
			</section>
		<?php endif; ?>
	</div>
	<aside class="dwc-side">
		<section class="dwc-card">
			<h2><?php echo Html::e( $app->t( 'Add category' ) ); ?></h2>
			<div class="dwc-card-body">
				<?php echo $app->form( 'save_term', $type, false, 'class="dwc-stack"' ); ?>
					<div class="dwc-field">
						<label for="dwc-new-name"><?php echo Html::e( $app->t( 'Name' ) ); ?></label>
						<input type="text" id="dwc-new-name" name="name" required>
					</div>
					<div class="dwc-field">
						<label for="dwc-new-slug"><?php echo Html::e( $app->t( 'Slug' ) ); ?> <span class="dwc-muted"><?php echo Html::e( $app->t( '(optional)' ) ); ?></span></label>
						<input type="text" id="dwc-new-slug" name="slug" class="dwc-mono">
					</div>
					<div class="dwc-field">
						<label for="dwc-new-parent"><?php echo Html::e( $app->t( 'Parent' ) ); ?></label>
						<?php echo $parentSelect( 0 ); ?>
					</div>
					<input type="hidden" name="position" value="999">
					<div><button type="submit" class="dwc-btn dwc-btn-primary"><?php echo Html::e( $app->t( 'Add category' ) ); ?></button></div>
				</form>
			</div>
		</section>
	</aside>
</div>
