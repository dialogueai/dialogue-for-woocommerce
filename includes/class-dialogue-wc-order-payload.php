<?php
/**
 * Purchase event payload. No customer personal data: no names, emails, phones, addresses or IPs.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the order payload sent to Dialogue.
 */
class Dialogue_WC_Order_Payload {

	/**
	 * Visitor id cookie value: Dialogue writes "abc123_def456_..." style ids; anything else is dropped.
	 *
	 * @param mixed $value Raw cookie value.
	 * @return string
	 */
	public static function clean_visitor_id( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$value = trim( $value );
		return preg_match( '/^[A-Za-z0-9_\-]{1,80}$/', $value ) ? $value : '';
	}

	/**
	 * Read data from a WC_Order (or anything with the same getters).
	 *
	 * @param WC_Order $order Order.
	 * @return array Facts for build().
	 */
	public static function facts_from_order( $order ) {
		$items = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product = $item->get_product();
			$items[] = array(
				'product_id'   => (int) $item->get_product_id(),
				'variation_id' => (int) $item->get_variation_id(),
				'merchant_sku' => $product ? (string) $product->get_sku() : '',
				'name'         => (string) $item->get_name(),
				'qty'          => (float) $item->get_quantity(),
				'subtotal'     => (float) $item->get_subtotal(),
				'total'        => (float) $item->get_total(),
			);
		}
		$created = $order->get_date_created();
		$paid    = $order->get_date_paid();
		return array(
			'order_id'       => (string) $order->get_id(),
			'order_number'   => (string) $order->get_order_number(),
			'status'         => (string) $order->get_status(),
			'currency'       => (string) $order->get_currency(),
			'total'          => (float) $order->get_total(),
			'subtotal'       => (float) $order->get_subtotal(),
			'discount_total' => (float) $order->get_discount_total(),
			'shipping_total' => (float) $order->get_shipping_total(),
			'tax_total'      => (float) $order->get_total_tax(),
			'coupons'        => $order->get_coupon_codes(),
			'created_via'    => (string) $order->get_created_via(),
			'created_at'     => $created ? $created->getTimestamp() : 0,
			'paid_at'        => $paid ? $paid->getTimestamp() : 0,
			'visitor'        => (string) $order->get_meta( '_dialogue_visitor' ),
			'session'        => (string) $order->get_meta( '_dialogue_session' ),
			'device'         => (string) $order->get_meta( '_dialogue_device' ),
			'items'          => $items,
		);
	}

	/**
	 * Build the payload (pure; unit tested).
	 *
	 * @param array  $f    Facts.
	 * @param string $type order.paid | order.updated.
	 * @return array
	 */
	public static function build( array $f, $type = 'order.paid' ) {
		$items = array();
		foreach ( isset( $f['items'] ) && is_array( $f['items'] ) ? $f['items'] : array() as $i ) {
			$qty = isset( $i['qty'] ) ? (float) $i['qty'] : 0;
			if ( $qty <= 0 ) {
				continue;
			}
			$product_id   = isset( $i['product_id'] ) ? (int) $i['product_id'] : 0;
			$variation_id = isset( $i['variation_id'] ) ? (int) $i['variation_id'] : 0;
			$total        = isset( $i['total'] ) ? (float) $i['total'] : 0;
			$subtotal     = isset( $i['subtotal'] ) ? (float) $i['subtotal'] : $total;
			$items[]      = array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				// Same id Dialogue stores as the feed sku: the variation, else the product.
				'sku'          => (string) ( $variation_id > 0 ? $variation_id : $product_id ),
				'merchant_sku' => isset( $i['merchant_sku'] ) ? (string) $i['merchant_sku'] : '',
				'name'         => isset( $i['name'] ) ? (string) $i['name'] : '',
				'qty'          => $qty,
				// Unit price the shopper paid, after discounts, before tax.
				'price'        => round( $total / $qty, 4 ),
				'subtotal'     => round( $subtotal, 4 ),
				'total'        => round( $total, 4 ),
			);
		}
		$coupons = array();
		foreach ( isset( $f['coupons'] ) && is_array( $f['coupons'] ) ? $f['coupons'] : array() as $c ) {
			$coupons[] = substr( (string) $c, 0, 64 );
		}
		return array(
			'type'           => (string) $type,
			'order_id'       => isset( $f['order_id'] ) ? (string) $f['order_id'] : '',
			'order_number'   => isset( $f['order_number'] ) ? (string) $f['order_number'] : '',
			'status'         => isset( $f['status'] ) ? (string) $f['status'] : '',
			'currency'       => isset( $f['currency'] ) ? strtoupper( (string) $f['currency'] ) : '',
			'total'          => isset( $f['total'] ) ? round( (float) $f['total'], 4 ) : 0,
			'subtotal'       => isset( $f['subtotal'] ) ? round( (float) $f['subtotal'], 4 ) : 0,
			'discount_total' => isset( $f['discount_total'] ) ? round( (float) $f['discount_total'], 4 ) : 0,
			'shipping_total' => isset( $f['shipping_total'] ) ? round( (float) $f['shipping_total'], 4 ) : 0,
			'tax_total'      => isset( $f['tax_total'] ) ? round( (float) $f['tax_total'], 4 ) : 0,
			'coupons'        => $coupons,
			'created_via'    => isset( $f['created_via'] ) ? (string) $f['created_via'] : '',
			'created_at'     => ! empty( $f['created_at'] ) ? gmdate( 'c', (int) $f['created_at'] ) : null,
			'paid_at'        => ! empty( $f['paid_at'] ) ? gmdate( 'c', (int) $f['paid_at'] ) : null,
			'visitor'        => array(
				'hash'    => self::clean_visitor_id( isset( $f['visitor'] ) ? $f['visitor'] : '' ),
				'session' => self::clean_visitor_id( isset( $f['session'] ) ? $f['session'] : '' ),
				'device'  => isset( $f['device'] ) && in_array( $f['device'], array( 'mobile', 'desktop' ), true ) ? $f['device'] : '',
			),
			'items'          => $items,
		);
	}
}
