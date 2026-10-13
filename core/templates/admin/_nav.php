<?php
/**
 * Admin tab bar.
 *
 * @var DWCatalog\Admin\AdminApp $app
 * @var string $type
 * @var string $current
 */
use DWCatalog\Support\Html;

$tabs = array(
	'items'      => array( 'Items', 'catalog.edit' ),
	'edit'       => array( 'Add New', 'catalog.edit' ),
	'categories' => array( 'Categories', 'catalog.terms' ),
	'fields'     => array( 'Fields', 'catalog.manage' ),
	'import'     => array( 'Import', 'catalog.import' ),
	'export'     => array( 'Export', 'catalog.export' ),
	'design'     => array( 'Design', 'catalog.manage' ),
	'reference'  => array( 'Shortcodes', 'catalog.edit' ),
);
?>
<nav class="dwc-tabs">
	<?php foreach ( $tabs as $view => $tab ) : ?>
		<?php if ( $app->catalog()->host()->can( $tab[1] ) ) : ?>
			<a href="<?php echo Html::url( $app->url( array( 'view' => $view, 'type' => $type ) ) ); ?>" class="<?php echo $view === $current ? 'is-active' : ''; ?>"><?php echo Html::e( $app->t( $tab[0] ) ); ?></a>
		<?php endif; ?>
	<?php endforeach; ?>
</nav>
