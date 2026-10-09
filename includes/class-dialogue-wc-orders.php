<?php
/**
 * Purchase events for attribution. Sent in the background (never during checkout), retried on failure.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orders → Dialogue.
 */
class Dialogue_WC_Orders {

	const SEND_HOOK = 'dialogue_wc_send_order';
	const META_SENT = '_dialogue_sent';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Remember which Dialogue visitor placed the order (classic and block checkout).
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'capture_visitor' ) );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'capture_visitor' ) );

		// Paid orders.
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'queue' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'queue' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'queue' ) );

		add_action( self::SEND_HOOK, array( __CLASS__, 'send' ), 10, 2 );
	}

	/**
	 * Save the visitor cookies Dialogue's script set (dai_v_h / dai_v_s) on the order.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public static function capture_visitor( $order ) {
		if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		// phpcs:disable WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___COOKIE -- first-party Dialogue cookie, validated below.
		$visitor = isset( $_COOKIE['dai_v_h'] ) ? Dialogue_WC_Order_Payload::clean_visitor_id( sanitize_text_field( wp_unslash( $_COOKIE['dai_v_h'] ) ) ) : '';
		$session = isset( $_COOKIE['dai_v_s'] ) ? Dialogue_WC_Order_Payload::clean_visitor_id( sanitize_text_field( wp_unslash( $_COOKIE['dai_v_s'] ) ) ) : '';
		// phpcs:enable
		if ( '' === $visitor && '' === $session ) {
			return;
		}
		$order->update_meta_data( '_dialogue_visitor', $visitor );
		$order->update_meta_data( '_dialogue_session', $session );
		// Device type only (for Dialogue's mobile / desktop reports); the user agent itself is not kept.
		$order->update_meta_data( '_dialogue_device', wp_is_mobile() ? 'mobile' : 'desktop' );
		$order->save_meta_data();
	}

	/**
	 * Queue the purchase event for an order.
	 *
	 * @param int $order_id Order id.
	 * @return void
	 */
	public static function queue( $order_id ) {
		if ( ! Dialogue_WC_Settings::is_connected() ) {
			return;
		}
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::META_SENT ) ) {
			return;
		}
		Dialogue_WC_Queue::schedule_unique( self::SEND_HOOK, array( $order_id, 1 ), 5 );
	}

	/**
	 * Background job: send one order.
	 *
	 * @param int $order_id Order id.
	 * @param int $attempt  Attempt number.
	 * @return void
	 */
	public static function send( $order_id, $attempt = 1 ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order || $order->get_meta( self::META_SENT ) || ! Dialogue_WC_Settings::is_connected() ) {
			return;
		}
		$payload = Dialogue_WC_Order_Payload::build( Dialogue_WC_Order_Payload::facts_from_order( $order ) );
		$result  = Dialogue_WC_Api_Client::signed( 'POST', '/api/woocommerce/events/order', $payload, 10 );

		if ( is_wp_error( $result ) ) {
			$attempt = (int) $attempt;
			if ( Dialogue_WC_Queue::should_retry( $attempt ) ) {
				Dialogue_WC_Queue::schedule( self::SEND_HOOK, array( (int) $order_id, $attempt + 1 ), Dialogue_WC_Queue::retry_delay( $attempt ) );
				/* translators: 1: order number, 2: attempt, 3: error */
				Dialogue_WC_Log::add( 'warning', sprintf( __( 'Order %1$s was not sent to Dialogue (try %2$d), will retry: %3$s', 'dialogue-for-woocommerce' ), $order->get_order_number(), $attempt, $result->get_error_message() ) );
			} else {
				/* translators: 1: order number, 2: error */
				Dialogue_WC_Log::add( 'error', sprintf( __( 'Order %1$s could not be sent to Dialogue after several tries: %2$s', 'dialogue-for-woocommerce' ), $order->get_order_number(), $result->get_error_message() ) );
			}
			return;
		}
		$order->update_meta_data( self::META_SENT, time() );
		$order->save_meta_data();
	}
}
