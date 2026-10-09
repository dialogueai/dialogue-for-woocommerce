<?php
// Seed sample catalog for the Dialogue e2e (run with wp eval-file).
update_option( 'woocommerce_currency', 'USD' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );
update_option( 'woocommerce_onboarding_profile', array( 'skipped' => true ) );
$cod = get_option( 'woocommerce_cod_settings', array() );
$cod['enabled'] = 'yes';
update_option( 'woocommerce_cod_settings', $cod );

function e2e_attr( $name, $slug, $terms ) {
	$id = wc_attribute_taxonomy_id_by_name( $slug );
	if ( ! $id ) {
		$id = wc_create_attribute( array( 'name' => $name, 'slug' => $slug, 'type' => 'select' ) );
	}
	$tax = 'pa_' . $slug;
	register_taxonomy( $tax, 'product' );
	$ids = array();
	foreach ( $terms as $t => $hex ) {
		$term = term_exists( $t, $tax ) ?: wp_insert_term( $t, $tax );
		$ids[ $t ] = (int) $term['term_id'];
		if ( $hex ) {
			update_term_meta( $ids[ $t ], 'product_attribute_color', $hex ); // as "Variation Swatches for WooCommerce" stores it
		}
	}
	return array( $id, $tax, $ids );
}
list( $color_id, $color_tax ) = e2e_attr( 'Color', 'color', array( 'Red' => '#cc0000', 'Blue' => '#0033cc' ) );
list( $size_id, $size_tax ) = e2e_attr( 'Size', 'size', array( 'M' => null, 'L' => null ) );

$cat = term_exists( 'Hoodies', 'product_cat' ) ?: wp_insert_term( 'Hoodies', 'product_cat' );
$brand = taxonomy_exists( 'product_brand' ) ? ( term_exists( 'Acme', 'product_brand' ) ?: wp_insert_term( 'Acme', 'product_brand' ) ) : null;

// Variable product.
$p = new WC_Product_Variable();
$p->set_name( 'Classic Hoodie' );
$p->set_sku( 'HOOD' );
$p->set_description( 'A warm classic hoodie.' );
$p->set_category_ids( array( (int) $cat['term_id'] ) );
$a1 = new WC_Product_Attribute();
$a1->set_id( $color_id ); $a1->set_name( $color_tax ); $a1->set_options( array( 'red', 'blue' ) ); $a1->set_position( 0 ); $a1->set_visible( true ); $a1->set_variation( true );
$a2 = new WC_Product_Attribute();
$a2->set_id( $size_id ); $a2->set_name( $size_tax ); $a2->set_options( array( 'm', 'l' ) ); $a2->set_position( 1 ); $a2->set_visible( true ); $a2->set_variation( true );
$a3 = new WC_Product_Attribute();
$a3->set_name( 'Material' ); $a3->set_options( array( 'Cotton' ) ); $a3->set_position( 2 ); $a3->set_visible( true ); $a3->set_variation( false );
$p->set_attributes( array( $a1, $a2, $a3 ) );
$p->update_meta_data( 'fit', 'Relaxed' );
$pid = $p->save();
if ( $brand ) { wp_set_object_terms( $pid, array( (int) $brand['term_id'] ), 'product_brand' ); }
foreach ( array( array( 'red', 'm', '40', '50', 'HOOD-RED-M', 'instock' ), array( 'blue', 'l', '50', '50', 'HOOD-BLUE-L', 'outofstock' ) ) as $v ) {
	$var = new WC_Product_Variation();
	$var->set_parent_id( $pid );
	$var->set_attributes( array( $color_tax => $v[0], $size_tax => $v[1] ) );
	$var->set_regular_price( $v[3] );
	if ( $v[2] !== $v[3] ) { $var->set_sale_price( $v[2] ); }
	$var->set_sku( $v[4] );
	$var->set_stock_status( $v[5] );
	$var->save();
}
WC_Product_Variable::sync( $pid );

// Simple product.
$s = new WC_Product_Simple();
$s->set_name( 'Coffee Mug' );
$s->set_regular_price( '12.50' );
$s->set_category_ids( array( (int) $cat['term_id'] ) );
$sid = $s->save();

echo wp_json_encode( array( 'hoodie' => $pid, 'mug' => $sid, 'brand_taxonomy' => taxonomy_exists( 'product_brand' ) ) ), "\n";
