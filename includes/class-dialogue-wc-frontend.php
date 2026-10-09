<?php
/**
 * Storefront: loads the Dialogue script on every front-end page with the page context.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Storefront script.
 */
class Dialogue_WC_Frontend {

	const HANDLE = 'dialogue-woo-loader';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		// Register with the WP Consent API (if installed) so consent banners know about us.
		$plugin = plugin_basename( DIALOGUE_WC_FILE );
		add_filter( "wp_consent_api_registered_{$plugin}", '__return_true' );
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			self::register_store_api_data();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register_store_api_data' ) );
		}
	}

	/**
	 * Adds extensions.dialogue = {product_id, variation_id} to Store API cart items, so the storefront
	 * script knows the parent product of a variation in the cart (Dialogue's product group).
	 *
	 * @return void
	 */
	public static function register_store_api_data() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartItemSchema::IDENTIFIER,
				'namespace'       => 'dialogue',
				'data_callback'   => array( __CLASS__, 'cart_item_data' ),
				'schema_callback' => array( __CLASS__, 'cart_item_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	/**
	 * Store API data for one cart item.
	 *
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public static function cart_item_data( $cart_item ) {
		return array(
			'product_id'   => isset( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0,
			'variation_id' => isset( $cart_item['variation_id'] ) ? (int) $cart_item['variation_id'] : 0,
		);
	}

	/**
	 * Schema for cart_item_data().
	 *
	 * @return array
	 */
	public static function cart_item_schema() {
		return array(
			'product_id'   => array(
				'description' => 'Parent product id (Dialogue product group).',
				'type'        => 'integer',
				'readonly'    => true,
			),
			'variation_id' => array(
				'description' => 'Variation id, 0 for simple products.',
				'type'        => 'integer',
				'readonly'    => true,
			),
		);
	}

	/**
	 * Enqueue the loader.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( is_admin() || ! Dialogue_WC_Settings::is_connected() ) {
			return;
		}
		$conn = Dialogue_WC_Settings::connection();
		if ( empty( $conn['store_id'] ) ) {
			return;
		}
		/**
		 * Filter: return false to stop loading Dialogue on this page.
		 *
		 * @param bool $load Load the script.
		 */
		if ( ! apply_filters( 'dialogue_wc_load_script', true ) ) {
			return;
		}

		$args = array( 'in_footer' => false );
		if ( version_compare( get_bloginfo( 'version' ), '6.3', '>=' ) ) {
			$args['strategy'] = 'defer';
		}
		wp_enqueue_script( self::HANDLE, DIALOGUE_WC_URL . 'assets/js/dialogue-loader.js', array(), DIALOGUE_WC_VERSION, $args );

		$context = Dialogue_WC_Page_Context::build(
			Dialogue_WC_Page_Context::collect(),
			array( 'store_id' => (int) $conn['store_id'] )
		);
		$config  = array(
			'store_id' => (int) $conn['store_id'],
			'user_id'  => (int) $conn['user_id'],
			'bundle'   => esc_url_raw( $conn['bundle_url'] ? $conn['bundle_url'] : Dialogue_WC_Settings::DEFAULT_BUNDLE ),
		);
		$inline  = 'window.DialogueWoo=' . wp_json_encode( $context ) . ';window.DialogueWooConfig=' . wp_json_encode( $config ) . ';';
		wp_add_inline_script( self::HANDLE, $inline, 'before' );
	}
}
