<?php
use PHPUnit\Framework\TestCase;

final class PageContextTest extends TestCase {
	private $base = array(
		'currency'       => 'USD',
		'locale'         => 'he_IL',
		'store_api_cart' => 'https://shop.test/wp-json/wc/store/v1/cart',
		'wc_ajax'        => '/?wc-ajax=%%endpoint%%',
		'cart_url'       => 'https://shop.test/cart/',
		'checkout_url'   => 'https://shop.test/checkout/',
	);

	public function test_variable_product_page() {
		$ctx = Dialogue_WC_Page_Context::build(
			array_merge(
				$this->base,
				array(
					'is_product' => true,
					'product'    => array( 'id' => 5, 'type' => 'variable', 'sku' => 'H', 'name' => 'Hoodie', 'price' => '40', 'variant_id' => 0, 'category' => array( 'id' => 9, 'slug' => 'tops', 'name' => 'Tops' ) ),
				)
			),
			array( 'store_id' => 77 )
		);
		$this->assertSame( 'woocommerce', $ctx['platform'] );
		$this->assertSame( 'product', $ctx['page_type'] );
		$this->assertSame( 5, $ctx['product_id'] );
		$this->assertNull( $ctx['variant_id'], 'no default variation chosen' );
		$this->assertSame( array( 'id' => 9, 'slug' => 'tops', 'name' => 'Tops' ), $ctx['collection'] );
		$this->assertSame( 'he-IL', $ctx['locale'] );
		$this->assertSame( 77, $ctx['store_id'] );
		$this->assertSame( 'https://shop.test/wp-json/wc/store/v1/cart', $ctx['cart']['store_api'] );
	}

	public function test_simple_product_is_its_own_variant() {
		$ctx = Dialogue_WC_Page_Context::build( array_merge( $this->base, array( 'is_product' => true, 'product' => array( 'id' => 8, 'type' => 'simple' ) ) ) );
		$this->assertSame( 8, $ctx['variant_id'] );
	}

	public function test_category_cart_thankyou_search_home() {
		$cat = Dialogue_WC_Page_Context::build( array_merge( $this->base, array( 'is_product_cat' => true, 'term' => array( 'id' => 3, 'slug' => 'shoes', 'name' => 'Shoes', 'taxonomy' => 'product_cat' ) ) ) );
		$this->assertSame( 'collection', $cat['page_type'] );
		$this->assertSame( 'shoes', $cat['collection']['slug'] );
		$this->assertSame( 'cart', Dialogue_WC_Page_Context::build( array( 'is_cart' => true ) )['page_type'] );
		// The thank-you page is also a checkout page: thank_you wins.
		$this->assertSame( 'thank_you', Dialogue_WC_Page_Context::build( array( 'is_checkout' => true, 'is_order_received' => true ) )['page_type'] );
		$s = Dialogue_WC_Page_Context::build( array( 'is_search' => true, 'search' => 'red dress' ) );
		$this->assertSame( array( 'search', 'red dress' ), array( $s['page_type'], $s['search'] ) );
		$this->assertSame( 'home', Dialogue_WC_Page_Context::build( array( 'is_front_page' => true ) )['page_type'] );
		$this->assertSame( 'collection', Dialogue_WC_Page_Context::build( array( 'is_shop' => true ) )['page_type'] );
		$this->assertSame( 'other', Dialogue_WC_Page_Context::build( array() )['page_type'] );
	}
}
