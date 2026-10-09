<?php
use PHPUnit\Framework\TestCase;

final class OrderPayloadTest extends TestCase {
	private function facts() {
		return array(
			'order_id'       => '1001',
			'order_number'   => '1001',
			'status'         => 'processing',
			'currency'       => 'ils',
			'total'          => 230.5,
			'subtotal'       => 250,
			'discount_total' => 25,
			'shipping_total' => 5.5,
			'tax_total'      => 0,
			'coupons'        => array( 'SUMMER10' ),
			'created_via'    => 'checkout',
			'created_at'     => 1700000000,
			'paid_at'        => 1700000100,
			'visitor'        => 'aB3dE9_xYz123_Qw7Er5_Tt0Uu1',
			'session'        => '2026100912',
			'device'         => 'mobile',
			'items'          => array(
				array( 'product_id' => 10, 'variation_id' => 11, 'merchant_sku' => 'TS-RED-M', 'name' => 'T-shirt - Red, M', 'qty' => 2, 'subtotal' => 200, 'total' => 180 ),
				array( 'product_id' => 20, 'variation_id' => 0, 'merchant_sku' => '', 'name' => 'Socks', 'qty' => 1, 'subtotal' => 50, 'total' => 45 ),
				array( 'product_id' => 30, 'variation_id' => 0, 'name' => 'Removed', 'qty' => 0, 'total' => 0 ),
			),
		);
	}

	public function test_builds_attribution_payload() {
		$p = Dialogue_WC_Order_Payload::build( $this->facts() );
		$this->assertSame( 'order.paid', $p['type'] );
		$this->assertSame( '1001', $p['order_id'] );
		$this->assertSame( 'ILS', $p['currency'] );
		$this->assertSame( 230.5, $p['total'] );
		$this->assertSame( array( 'SUMMER10' ), $p['coupons'] );
		$this->assertSame( '2023-11-14T22:13:20+00:00', $p['created_at'] );
		$this->assertSame( 'aB3dE9_xYz123_Qw7Er5_Tt0Uu1', $p['visitor']['hash'] );
		$this->assertSame( 'mobile', $p['visitor']['device'] );
		$this->assertCount( 2, $p['items'], 'zero-quantity lines are dropped' );
		$this->assertSame( '11', $p['items'][0]['sku'], 'variation id is the feed sku' );
		$this->assertSame( 90.0, $p['items'][0]['price'], 'unit price after discount' );
		$this->assertSame( '20', $p['items'][1]['sku'], 'simple product id is the feed sku' );
	}

	public function test_has_no_personal_data_keys() {
		$json = json_encode( Dialogue_WC_Order_Payload::build( $this->facts() ) );
		foreach ( array( 'email', 'phone', 'first_name', 'last_name', 'address', 'billing', 'shipping_address', 'ip' ) as $k ) {
			$this->assertStringNotContainsString( '"' . $k . '"', $json );
		}
	}

	public function test_rejects_bad_visitor_ids() {
		$this->assertSame( '', Dialogue_WC_Order_Payload::clean_visitor_id( '<script>' ) );
		$this->assertSame( '', Dialogue_WC_Order_Payload::clean_visitor_id( str_repeat( 'a', 81 ) ) );
		$this->assertSame( '', Dialogue_WC_Order_Payload::clean_visitor_id( array( 'x' ) ) );
		$this->assertSame( 'abc_123', Dialogue_WC_Order_Payload::clean_visitor_id( ' abc_123 ' ) );
	}
}
