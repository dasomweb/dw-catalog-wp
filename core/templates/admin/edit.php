<?php
/**
 * Item editor.
 *
 * @var DWCatalog\Admin\AdminApp $app
 * @var DWCatalog\Catalog $c
 * @var string $type
 * @var array|null $item
 */
use DWCatalog\Fields\VariationColumns;
use DWCatalog\Support\Html;
use DWCatalog\Support\Text;

$current = 'edit';
$config  = $c->fields()->type( $type );
$id      = $item ? (int) $item['id'] : 0;
$values  = $id ? $c->items()->values( $id ) : array();
$cats    = $id ? array_map( 'intval', array_column( $c->terms()->ofItem( $type, $id ), 'id' ) ) : array();
$tags    = $id ? implode( ', ', array_column( $c->terms()->ofItem( $type, $id, 'tag' ), 'name' ) ) : '';
$cols    = $c->fields()->variationColumns( $type );
$rows    = $id ? $c->items()->variations( $id ) : array();
$images  = $id ? $c->images()->forItem( $id ) : array();

$fieldInput = function ( array $f, string $value ) {
	$name = 'fields[' . $f['meta_key'] . ']';
	$idA  = 'dwc-f-' . $f['meta_key'];
	switch ( $f['type'] ) {
		case 'textarea':
			return '<textarea id="' . Html::attr( $idA ) . '" name="' . Html::attr( $name ) . '" rows="4">' . Html::e( $value ) . '</textarea>';
		case 'select':
			$h = '<select id="' . Html::attr( $idA ) . '" name="' . Html::attr( $name ) . '"><option value="">—</option>';
			foreach ( Text::parseOptions( $f['options'] ) as $v => $l ) {
				$h .= '<option value="' . Html::attr( $v ) . '"' . ( (string) $v === $value ? ' selected' : '' ) . '>' . Html::e( $l ) . '</option>';
			}
			return $h . '</select>';
		default:
			$types = array( 'number' => 'number" step="any', 'email' => 'email', 'url' => 'url', 'date' => 'date' );
			$t     = isset( $types[ $f['type'] ] ) ? $types[ $f['type'] ] : 'text';
			return '<input type="' . $t . '" id="' . Html::attr( $idA ) . '" name="' . Html::attr( $name ) . '" value="' . Html::attr( $value ) . '">';
	}
};
$varCell = function ( array $col, string $name, string $value ) {
	if ( 'select' === $col['type'] ) {
		$h = '<select name="' . Html::attr( $name ) . '"><option value="">—</option>';
		foreach ( Text::parseOptions( $col['options'] ) as $v => $l ) {
			$h .= '<option value="' . Html::attr( $v ) . '"' . ( (string) $v === $value ? ' selected' : '' ) . '>' . Html::e( $l ) . '</option>';
		}
		return $h . '</select>';
	}
	return '<input type="' . ( 'number' === $col['type'] ? 'number" step="any' : 'text' ) . '" name="' . Html::attr( $name ) . '" value="' . Html::attr( $value ) . '">';
};
$varRow = function ( $i, array $row ) use ( $cols, $varCell, $app ) {
	$h = '<tr><td class="dwc-move"><button type="button" class="dwc-icon" data-dwc-up title="' . Html::attr( $app->t( 'Move up' ) ) . '">↑</button><button type="button" class="dwc-icon" data-dwc-down title="' . Html::attr( $app->t( 'Move down' ) ) . '">↓</button></td>';
	foreach ( $cols as $col ) {
		$h .= '<td>' . $varCell( $col, 'var[' . $i . '][' . $col['key'] . ']', isset( $row[ $col['key'] ] ) ? (string) $row[ $col['key'] ] : '' ) . '</td>';
	}
	return $h . '<td><button type="button" class="dwc-icon dwc-danger" data-dwc-remove title="' . Html::attr( $app->t( 'Remove' ) ) . '">×</button></td></tr>';
};
?>
<div class="dwc-head">
	<h1><?php echo Html::e( $id ? $app->t( 'Edit item' ) : $app->t( 'Add item' ) ); ?></h1>
	<?php if ( $id ) : ?>
		<span class="dwc-muted">#<?php echo $id; ?> · <?php echo Html::e( $item['slug'] ); ?></span>
	<?php endif; ?>
</div>
<?php include __DIR__ . '/_nav.php'; ?>

<?php echo $app->form( 'save_item', $type, true, 'class="dwc-edit"' ); ?>
	<input type="hidden" name="id" value="<?php echo $id; ?>">
	<div class="dwc-cols">
		<div class="dwc-main">
			<label class="dwc-label" for="dwc-title"><?php echo Html::e( $app->t( 'Name' ) ); ?> <span class="dwc-req">*</span></label>
			<input type="text" id="dwc-title" name="title" class="dwc-title" required maxlength="255" value="<?php echo Html::attr( $item ? $item['title'] : '' ); ?>">

			<label class="dwc-label"><?php echo Html::e( $app->t( 'Description' ) ); ?></label>
			<?php echo $c->host()->richText( 'content', $item ? (string) $item['content'] : '' ); ?>

			<label class="dwc-label" for="dwc-excerpt"><?php echo Html::e( $app->t( 'Short summary' ) ); ?> <span class="dwc-muted"><?php echo Html::e( $app->t( '(used for search engines; optional)' ) ); ?></span></label>
			<textarea id="dwc-excerpt" name="excerpt" rows="2" maxlength="1000"><?php echo Html::e( $item ? $item['excerpt'] : '' ); ?></textarea>

			<h2><?php echo Html::e( $app->t( 'Details' ) ); ?></h2>
			<table class="dwc-form">
				<?php foreach ( $c->fields()->fields( $type ) as $f ) : ?>
					<?php if ( ! empty( $f['is_title_field'] ) ) { continue; } ?>
					<tr>
						<th><label for="dwc-f-<?php echo Html::attr( $f['meta_key'] ); ?>"><?php echo Html::e( $f['label'] ); ?><?php echo ! empty( $f['required'] ) ? ' <span class="dwc-req">*</span>' : ''; ?></label>
							<?php if ( ! \DWCatalog\Fields\FieldConfig::isPublic( $f ) ) : ?><span class="dwc-badge" title="<?php echo Html::attr( $app->t( 'Not shown on the website' ) ); ?>"><?php echo Html::e( $app->t( 'Internal' ) ); ?></span><?php endif; ?>
						</th>
						<td><?php echo $fieldInput( $f, isset( $values[ $f['meta_key'] ] ) ? (string) $values[ $f['meta_key'] ] : '' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>

			<?php if ( $cols ) : ?>
				<h2><?php echo Html::e( $app->t( 'Variations' ) ); ?></h2>
				<p class="dwc-muted"><?php echo Html::e( $app->t( 'Add rows when this item comes in several sizes, packs or flavors.' ) ); ?></p>
				<table class="dwc-table dwc-rows" data-dwc-rows="var">
					<thead><tr><th></th>
						<?php foreach ( $cols as $col ) : ?>
							<th><?php echo Html::e( $col['label'] ); ?><?php echo VariationColumns::isPublic( $col ) ? '' : ' <span class="dwc-badge">' . Html::e( $app->t( 'Internal' ) ) . '</span>'; ?></th>
						<?php endforeach; ?>
					<th></th></tr></thead>
					<tbody>
						<?php foreach ( $rows as $i => $row ) { echo $varRow( $i, $row ); } ?>
					</tbody>
				</table>
				<template data-dwc-template="var"><?php echo $varRow( '__i__', array() ); ?></template>
				<p><button type="button" class="dwc-btn" data-dwc-add="var"><?php echo Html::e( $app->t( 'Add variation' ) ); ?></button></p>
			<?php endif; ?>
		</div>

		<aside class="dwc-side">
			<div class="dwc-box">
				<label class="dwc-label" for="dwc-status"><?php echo Html::e( $app->t( 'Status' ) ); ?></label>
				<select id="dwc-status" name="status">
					<option value="publish" <?php echo ! $item || 'publish' === $item['status'] ? 'selected' : ''; ?>><?php echo Html::e( $app->t( 'Published' ) ); ?></option>
					<option value="draft" <?php echo $item && 'draft' === $item['status'] ? 'selected' : ''; ?>><?php echo Html::e( $app->t( 'Draft' ) ); ?></option>
				</select>
				<label class="dwc-label" for="dwc-slug"><?php echo Html::e( $app->t( 'URL slug' ) ); ?></label>
				<input type="text" id="dwc-slug" name="slug" value="<?php echo Html::attr( $item ? $item['slug'] : '' ); ?>" placeholder="<?php echo Html::attr( $app->t( 'from the name' ) ); ?>">
				<p><button type="submit" class="dwc-btn dwc-btn-primary dwc-block"><?php echo Html::e( $app->t( 'Save' ) ); ?></button></p>
			</div>

			<?php if ( ! empty( $config['has_category'] ) ) : ?>
				<div class="dwc-box">
					<div class="dwc-label"><?php echo Html::e( $app->t( 'Categories' ) ); ?></div>
					<div class="dwc-checklist">
						<?php
						$tree = $c->terms()->tree( $type );
						$list = function ( $parent ) use ( &$list, $tree, $cats ) {
							if ( empty( $tree[ $parent ] ) ) {
								return;
							}
							echo '<ul>';
							foreach ( $tree[ $parent ] as $t ) {
								echo '<li><label><input type="checkbox" name="cats[]" value="' . (int) $t['id'] . '"' . ( in_array( (int) $t['id'], $cats, true ) ? ' checked' : '' ) . '> ' . Html::e( $t['name'] ) . '</label>';
								$list( (int) $t['id'] );
								echo '</li>';
							}
							echo '</ul>';
						};
						$list( 0 );
						if ( empty( $tree ) ) {
							echo '<p class="dwc-muted">' . Html::e( $app->t( 'No categories yet.' ) ) . '</p>';
						}
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $config['has_tag'] ) ) : ?>
				<div class="dwc-box">
					<label class="dwc-label" for="dwc-tags"><?php echo Html::e( $app->t( 'Tags' ) ); ?></label>
					<input type="text" id="dwc-tags" name="tags" value="<?php echo Html::attr( $tags ); ?>" placeholder="<?php echo Html::attr( $app->t( 'comma, separated' ) ); ?>">
				</div>
			<?php endif; ?>

			<div class="dwc-box">
				<div class="dwc-label"><?php echo Html::e( $app->t( 'Images' ) ); ?> <span class="dwc-muted"><?php echo Html::e( $app->t( '(first = main)' ) ); ?></span></div>
				<ul class="dwc-images" data-dwc-images>
					<?php foreach ( $images as $img ) : ?>
						<li>
							<input type="hidden" name="img_order[]" value="<?php echo (int) $img['id']; ?>">
							<?php echo $c->images()->tag( $img, 'thumb' ); ?>
							<input type="text" name="img_alt[<?php echo (int) $img['id']; ?>]" value="<?php echo Html::attr( $img['alt'] ); ?>" placeholder="<?php echo Html::attr( $app->t( 'Alt text' ) ); ?>">
							<span class="dwc-imgtools">
								<button type="button" class="dwc-icon" data-dwc-up>↑</button>
								<button type="button" class="dwc-icon" data-dwc-down>↓</button>
								<label class="dwc-danger"><input type="checkbox" name="img_delete[]" value="<?php echo (int) $img['id']; ?>"> <?php echo Html::e( $app->t( 'Delete' ) ); ?></label>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>
				<input type="file" name="images[]" accept="image/jpeg,image/png,image/gif,image/webp" multiple>
				<p class="dwc-muted"><?php echo Html::e( $app->t( 'JPEG, PNG, GIF or WebP, up to 15 MB each.' ) ); ?></p>
			</div>
		</aside>
	</div>
</form>

<?php if ( $id && $c->host()->can( 'catalog.delete' ) ) : ?>
	<?php echo $app->form( 'delete_item', $type, false, 'class="dwc-delete"' ); ?>
		<input type="hidden" name="id" value="<?php echo $id; ?>">
		<button type="submit" class="dwc-link dwc-danger" data-dwc-confirm="<?php echo Html::attr( $app->t( 'Delete this item and its images? This cannot be undone.' ) ); ?>"><?php echo Html::e( $app->t( 'Delete item' ) ); ?></button>
	</form>
<?php endif; ?>
