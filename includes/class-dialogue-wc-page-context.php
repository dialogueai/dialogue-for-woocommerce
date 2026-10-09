<?php
/**
 * Page context for the storefront script (window.DialogueWoo), same role as the Shopify theme snippet.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Page context for the storefront script.
 */
class Dialogue_WC_Page_Context {

	/**
	 * Gather facts about the current request (WordPress / WooCommerce conditionals).
	 *
	 * @return array
	 */
	public static function collect() {
		$facts = array(
			'is_product'        => function_exists( 'is_product' ) && is_product(),
			'is_product_cat'    => function_exists( 'is_product_category' ) && is_product_category(),
			'is_product_tag'    => function_exists( 'is_product_tag' ) && is_product_tag(),
			'is_shop'           => function_exists( 'is_shop' ) && is_shop(),
			'is_cart'           => function_exists( 'is_cart' ) && is_cart(),
			'is_checkout'       => function_exists( 'is_checkout' ) && is_checkout(),
			'is_order_received' => function_exists( 'is_order_received_page' ) && is_order_received_page(),
			'is_account'        => function_exists( 'is_account_page' ) && is_account_page(),
			'is_search'         => is_search(),
			'is_front_page'     => is_front_page(),
			'search'            => get_search_query(),
			'product'           => null,
			'term'              => null,
			'currency'          => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'locale'            => determine_locale(),
			'store_api_cart'    => rest_url( 'wc/store/v1/cart' ),
			'wc_ajax'           => class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( '%%endpoint%%' ) : '',
			'cart_url'          => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
			'checkout_url'      => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
		);

		if ( $facts['is_product'] ) {
			$product = wc_get_product( get_queried_object_id() );
			if ( $product ) {
				$cats    = array();
				$cat_ids = $product->get_category_ids();
				foreach ( array_slice( $cat_ids, 0, 1 ) as $cat_id ) {
					$term = get_term( $cat_id, 'product_cat' );
					if ( $term && ! is_wp_error( $term ) ) {
						$cats[] = array(
							'id'   => (int) $term->term_id,
							'slug' => $term->slug,
							'name' => $term->name,
						);
					}
				}
				$default_variation = 0;
				if ( $product->is_type( 'variable' ) && class_exists( 'WC_Data_Store' ) ) {
					$defaults = $product->get_default_attributes();
					if ( ! empty( $defaults ) ) {
						$prefixed = array();
						foreach ( $defaults as $k => $v ) {
							$prefixed[ 'attribute_' . $k ] = $v;
						}
						$default_variation = (int) WC_Data_Store::load( 'product' )->find_matching_product_variation( $product, $prefixed );
					}
				}
				$facts['product'] = array(
					'id'         => (int) $product->get_id(),
					'type'       => $product->get_type(),
					'sku'        => (string) $product->get_sku(),
					'name'       => $product->get_name(),
					'price'      => (string) $product->get_price(),
					'variant_id' => $default_variation,
					'category'   => isset( $cats[0] ) ? $cats[0] : null,
				);
			}
		} elseif ( $facts['is_product_cat'] || $facts['is_product_tag'] ) {
			$term = get_queried_object();
			if ( $term && isset( $term->term_id ) ) {
				$facts['term'] = array(
					'id'       => (int) $term->term_id,
					'slug'     => (string) $term->slug,
					'name'     => (string) $term->name,
					'taxonomy' => (string) $term->taxonomy,
				);
			}
		}
		return $facts;
	}

	/**
	 * Build the window.DialogueWoo object from facts (pure; unit tested).
	 *
	 * @param array $facts    From collect().
	 * @param array $identity {store_id, user_id} of the Dialogue store.
	 * @return array
	 */
	public static function build( array $facts, array $identity = array() ) {
		$f = array_merge(
			array(
				'is_product'        => false,
				'is_product_cat'    => false,
				'is_product_tag'    => false,
				'is_shop'           => false,
				'is_cart'           => false,
				'is_checkout'       => false,
				'is_order_received' => false,
				'is_account'        => false,
				'is_search'         => false,
				'is_front_page'     => false,
				'search'            => '',
				'product'           => null,
				'term'              => null,
				'currency'          => '',
				'locale'            => '',
				'store_api_cart'    => '',
				'wc_ajax'           => '',
				'cart_url'          => '',
				'checkout_url'      => '',
			),
			$facts
		);

		if ( $f['is_order_received'] ) {
			$type = 'thank_you';
		} elseif ( $f['is_product'] ) {
			$type = 'product';
		} elseif ( $f['is_product_cat'] || $f['is_product_tag'] || $f['is_shop'] ) {
			$type = 'collection';
		} elseif ( $f['is_cart'] ) {
			$type = 'cart';
		} elseif ( $f['is_checkout'] ) {
			$type = 'checkout';
		} elseif ( $f['is_search'] ) {
			$type = 'search';
		} elseif ( $f['is_account'] ) {
			$type = 'account';
		} elseif ( $f['is_front_page'] ) {
			$type = 'home';
		} else {
			$type = 'other';
		}

		$out = array(
			'platform'   => 'woocommerce',
			'version'    => defined( 'DIALOGUE_WC_VERSION' ) ? DIALOGUE_WC_VERSION : '',
			'page_type'  => $type,
			'product_id' => null,
			'variant_id' => null,
			'product'    => null,
			'collection' => null,
			'search'     => 'search' === $type ? (string) $f['search'] : '',
			'currency'   => (string) $f['currency'],
			'locale'     => str_replace( '_', '-', (string) $f['locale'] ),
			'store_id'   => isset( $identity['store_id'] ) ? (int) $identity['store_id'] : 0,
			'cart'       => array(
				'store_api' => (string) $f['store_api_cart'],
				'wc_ajax'   => (string) $f['wc_ajax'],
				'cart_url'  => (string) $f['cart_url'],
				'checkout'  => (string) $f['checkout_url'],
			),
		);

		if ( 'product' === $type && is_array( $f['product'] ) ) {
			$p                 = $f['product'];
			$out['product_id'] = isset( $p['id'] ) ? (int) $p['id'] : null;
			$variant           = isset( $p['variant_id'] ) ? (int) $p['variant_id'] : 0;
			// Simple products: the product is its own variant (matches the feed sku).
			$out['variant_id'] = $variant > 0 ? $variant : ( isset( $p['type'] ) && 'variable' === $p['type'] ? null : $out['product_id'] );
			$out['product']    = array(
				'id'    => $out['product_id'],
				'type'  => isset( $p['type'] ) ? (string) $p['type'] : '',
				'sku'   => isset( $p['sku'] ) ? (string) $p['sku'] : '',
				'name'  => isset( $p['name'] ) ? (string) $p['name'] : '',
				'price' => isset( $p['price'] ) ? (string) $p['price'] : '',
			);
			if ( ! empty( $p['category'] ) && is_array( $p['category'] ) ) {
				$out['collection'] = array(
					'id'   => (int) $p['category']['id'],
					'slug' => (string) $p['category']['slug'],
					'name' => (string) $p['category']['name'],
				);
			}
		} elseif ( 'collection' === $type && is_array( $f['term'] ) ) {
			$out['collection'] = array(
				'id'       => (int) $f['term']['id'],
				'slug'     => (string) $f['term']['slug'],
				'name'     => (string) $f['term']['name'],
				'taxonomy' => isset( $f['term']['taxonomy'] ) ? (string) $f['term']['taxonomy'] : 'product_cat',
			);
		}
		return $out;
	}
}
