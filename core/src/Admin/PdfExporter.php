<?php
namespace DWCatalog\Admin;

use DWCatalog\Catalog;
use DWCatalog\Fields\FieldConfig;
use DWCatalog\Support\Html;

/**
 * PDF catalog (US Letter, card grid) via Dompdf. The host loads the Dompdf autoloader.
 * Remote resources are disabled; images are read from the storage dir only (chroot).
 */
class PdfExporter {

	const MAX_ITEMS = 5000;

	/** @var Catalog */
	private $c;

	public function __construct( Catalog $c ) {
		$this->c = $c;
	}

	public function run( string $type, array $post ): array {
		if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
			throw new Forbidden( $this->c->t( 'PDF library not found.' ) );
		}
		$cats = array_values( array_filter( array_map( 'intval', isset( $post['cats'] ) ? (array) $post['cats'] : array() ) ) );
		if ( ! $cats ) {
			throw new Forbidden( $this->c->t( 'Select at least one category.' ) );
		}
		$perRow    = max( 1, min( 4, isset( $post['per_row'] ) ? (int) $post['per_row'] : 2 ) );
		$withImage = ! empty( $post['image'] );
		$all       = $this->c->fields()->fieldsByKey( $type );
		$chosen    = array();
		foreach ( isset( $post['cols'] ) ? (array) $post['cols'] : array() as $key ) {
			$key = (string) $key;
			if ( '_title' === $key || '_category' === $key || ( isset( $all[ $key ] ) && ! empty( $all[ $key ]['show_in_export'] ) ) ) {
				$label           = isset( $post['labels'][ $key ] ) ? trim( strip_tags( (string) $post['labels'][ $key ] ) ) : '';
				$chosen[ $key ]  = '' !== $label ? $label : ( isset( $all[ $key ] ) ? $all[ $key ]['label'] : $key );
			}
		}

		// Collect items page by page (bounded)
		$rows = array();
		for ( $page = 1; count( $rows ) < self::MAX_ITEMS; $page++ ) {
			$r    = $this->c->query()->run( $type, array( 'cats' => $cats, 'sort' => 'title_asc', 'page' => $page, 'per_page' => 100 ) );
			$rows = array_merge( $rows, $r['rows'] );
			if ( $page >= $r['pages'] ) {
				break;
			}
		}
		if ( ! $rows ) {
			throw new Forbidden( $this->c->t( 'No items found.' ) );
		}
		$ids = array_map( 'intval', array_column( $rows, 'id' ) );
		$this->c->images()->primeItems( $ids );
		$this->c->terms()->primeItems( $type, $ids );

		$cards = array();
		foreach ( $rows as $item ) {
			$id   = (int) $item['id'];
			$html = '<div class="card">';
			if ( $withImage ) {
				$img   = $this->c->images()->main( $id );
				$path  = $img ? $this->c->images()->path( $img, 'thumb' ) : '';
				$html .= $path && is_file( $path )
					? '<div class="img"><img src="' . Html::attr( $path ) . '"></div>'
					: '<div class="img none">—</div>';
			}
			$html .= '<div class="fields">';
			foreach ( $chosen as $key => $label ) {
				if ( '_title' === $key ) {
					$val = $item['title'];
				} elseif ( '_category' === $key ) {
					$val = implode( ', ', array_column( $this->c->terms()->ofItem( $type, $id ), 'name' ) );
				} else {
					$val = FieldConfig::display( $all[ $key ], $this->c->items()->value( $id, $key ) );
				}
				$html .= '<div class="f"><strong>' . Html::e( $label ) . ':</strong> ' . Html::e( '' !== $val ? $val : '—' ) . '</div>';
			}
			$cards[] = $html . '</div></div>';
		}

		$gap  = 2;
		$cw   = ( 100 - ( $perRow - 1 ) * $gap ) / $perRow;
		$body = '';
		foreach ( array_chunk( $cards, $perRow ) as $chunk ) {
			$body .= '<div class="row">' . implode( '', $chunk ) . '</div>';
		}
		$css  = "body{font-family:DejaVu Sans,sans-serif;font-size:10pt;margin:0;padding:16px}.row{clear:both;overflow:hidden;margin-bottom:16px}"
			. ".card{float:left;width:{$cw}%;margin-right:{$gap}%;border:1px solid #ccc;padding:10px;box-sizing:border-box;min-height:150px}"
			. ".card:nth-child({$perRow}n){margin-right:0}.img{width:110px;height:110px;float:left;margin-right:12px;text-align:center;line-height:110px;background:#f5f5f5;color:#999}"
			. '.img img{max-width:110px;max-height:110px;vertical-align:middle}.fields{overflow:hidden}.f{margin-bottom:4px}';

		$dompdf = new \Dompdf\Dompdf( array(
			'isRemoteEnabled' => false,
			'chroot'          => array( $this->c->host()->storageDir() ),
		) );
		$dompdf->setPaper( 'letter', 'portrait' );
		$dompdf->loadHtml( '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>' . $css . '</style></head><body>' . $body . '</body></html>' );
		$dompdf->render();
		return array( 'download' => array( $type . '-catalog-' . gmdate( 'Y-m-d-His' ) . '.pdf', 'application/pdf', $dompdf->output() ) );
	}
}
