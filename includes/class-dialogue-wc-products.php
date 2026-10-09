<?php
/**
 * Product change listening: created / updated / deleted products and variations, stock and price
 * changes, category and attribute term edits. Changes are collected for a minute (debounce) and
 * sent to Dialogue in one signed call, retried with backoff on failure. Dialogue then re-reads only
 * those products. A nightly and weekly re-check on Dialogue's side catches anything missed.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Products → Dialogue.
 */
class Dialogue_WC_Products {

	const FLUSH_HOOK = 'dialogue_wc_flush_products';
	const DEBOUNCE   = 60;
	const MAX_IDS    = 500;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_product' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_product' ) );
		add_action( 'woocommerce_new_product_variation', array( __CLASS__, 'on_variation' ) );
		add_action( 'woocommerce_update_product_variation', array( __CLASS__, 'on_variation' ) );
		add_action( 'woocommerce_delete_product_variation', array( __CLASS__, 'on_variation_deleted' ), 10, 1 );
		add_action( 'woocommerce_product_set_stock', array( __CLASS__, 'on_stock' ) );
		add_action( 'woocommerce_variation_set_stock', array( __CLASS__, 'on_stock' ) );
		add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'on_product' ) );
		add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'on_variation' ) );
		add_action( 'woocommerce_delete_product', array( __CLASS__, 'on_deleted' ) );
		add_action( 'woocommerce_trash_product', array( __CLASS__, 'on_deleted' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_post_deleted' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'on_post_deleted' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_post_untrashed' ) );
		add_action( 'edited_term', array( __CLASS__, 'on_term' ), 10, 3 );
		add_action( 'delete_term', array( __CLASS__, 'on_term' ), 10, 3 );
		add_action( self::FLUSH_HOOK, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Product created / updated.
	 *
	 * @param int $product_id Product id.
	 * @return void
	 */
	public static function on_product( $product_id ) {
		if ( is_object( $product_id ) && method_exists( $product_id, 'get_id' ) ) {
			$product_id = $product_id->get_id();
		}
		self::record( array( 'updated' => array( (int) $product_id ) ) );
	}

	/**
	 * Variation created / updated → its parent product.
	 *
	 * @param int $variation_id Variation id.
	 * @return void
	 */
	public static function on_variation( $variation_id ) {
		if ( is_object( $variation_id ) && method_exists( $variation_id, 'get_id' ) ) {
			$variation_id = $variation_id->get_id();
		}
		$parent = (int) wp_get_post_parent_id( (int) $variation_id );
		if ( $parent ) {
			self::record( array( 'updated' => array( $parent ) ) );
		}
	}

	/**
	 * Variation deleted → re-read its parent.
	 *
	 * @param int $variation_id Variation id.
	 * @return void
	 */
	public static function on_variation_deleted( $variation_id ) {
		self::on_variation( $variation_id );
	}

	/**
	 * Stock changed.
	 *
	 * @param WC_Product $product Product or variation.
	 * @return void
	 */
	public static function on_stock( $product ) {
		if ( ! $product || ! is_object( $product ) ) {
			return;
		}
		$parent = method_exists( $product, 'get_parent_id' ) ? (int) $product->get_parent_id() : 0;
		self::record( array( 'updated' => array( $parent ? $parent : (int) $product->get_id() ) ) );
	}

	/**
	 * Product deleted or trashed.
	 *
	 * @param int $product_id Product id.
	 * @return void
	 */
	public static function on_deleted( $product_id ) {
		self::record( array( 'deleted' => array( (int) $product_id ) ) );
	}

	/**
	 * WordPress post deleted / trashed (covers bulk actions).
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function on_post_deleted( $post_id ) {
		$type = get_post_type( $post_id );
		if ( 'product' === $type ) {
			self::on_deleted( $post_id );
		} elseif ( 'product_variation' === $type ) {
			self::on_variation( $post_id );
		}
	}

	/**
	 * Product restored from trash.
	 *
	 * @param int $post_id Post id.
	 * @return void
	 */
	public static function on_post_untrashed( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			self::on_product( $post_id );
		}
	}

	/**
	 * Category or attribute term edited / deleted: names, swatch colors or images may have changed
	 * on many products, so Dialogue re-reads the catalog.
	 *
	 * @param int    $term_id  Term id.
	 * @param int    $tt_id    Term taxonomy id.
	 * @param string $taxonomy Taxonomy.
	 * @return void
	 */
	public static function on_term( $term_id, $tt_id, $taxonomy ) {
		if ( in_array( $taxonomy, array( 'product_cat', 'product_tag', 'product_brand' ), true ) || 0 === strpos( (string) $taxonomy, 'pa_' ) ) {
			self::record( array( 'catalog_changed' => true ) );
		}
	}

	/**
	 * Merge a change into the pending batch (pure; unit tested).
	 *
	 * @param array $pending Current batch.
	 * @param array $change  {updated?: int[], deleted?: int[], catalog_changed?: bool}.
	 * @return array
	 */
	public static function merge( array $pending, array $change ) {
		$p = array_merge(
			array(
				'updated'         => array(),
				'deleted'         => array(),
				'catalog_changed' => false,
				'attempt'         => 0,
			),
			$pending
		);
		foreach ( isset( $change['updated'] ) ? (array) $change['updated'] : array() as $id ) {
			$id = (int) $id;
			if ( $id > 0 && ! in_array( $id, $p['deleted'], true ) && ! in_array( $id, $p['updated'], true ) ) {
				$p['updated'][] = $id;
			}
		}
		foreach ( isset( $change['deleted'] ) ? (array) $change['deleted'] : array() as $id ) {
			$id = (int) $id;
			if ( $id <= 0 ) {
				continue;
			}
			$p['updated'] = array_values( array_diff( $p['updated'], array( $id ) ) );
			if ( ! in_array( $id, $p['deleted'], true ) ) {
				$p['deleted'][] = $id;
			}
		}
		if ( ! empty( $change['catalog_changed'] ) ) {
			$p['catalog_changed'] = true;
		}
		// Too many single changes (bulk edit / import): ask for a catalog re-read instead.
		if ( count( $p['updated'] ) > self::MAX_IDS ) {
			$p['updated']         = array();
			$p['catalog_changed'] = true;
		}
		return $p;
	}

	/**
	 * Add a change to the batch and make sure a flush is scheduled.
	 *
	 * @param array $change Change.
	 * @return void
	 */
	public static function record( array $change ) {
		if ( ! Dialogue_WC_Settings::is_connected() ) {
			return;
		}
		$pending = get_option( Dialogue_WC_Settings::OPTION_PENDING, array() );
		update_option( Dialogue_WC_Settings::OPTION_PENDING, self::merge( is_array( $pending ) ? $pending : array(), $change ), false );
		Dialogue_WC_Queue::schedule_unique( self::FLUSH_HOOK, array(), self::DEBOUNCE );
	}

	/**
	 * Background job: send the batch.
	 *
	 * @return void
	 */
	public static function flush() {
		$pending = get_option( Dialogue_WC_Settings::OPTION_PENDING, array() );
		if ( ! is_array( $pending ) || ( empty( $pending['updated'] ) && empty( $pending['deleted'] ) && empty( $pending['catalog_changed'] ) ) ) {
			return;
		}
		if ( ! Dialogue_WC_Settings::is_connected() ) {
			return;
		}
		// Take the batch; changes arriving while we send go into a new one.
		delete_option( Dialogue_WC_Settings::OPTION_PENDING );
		$payload = array(
			'updated'         => array_values( array_map( 'intval', isset( $pending['updated'] ) ? $pending['updated'] : array() ) ),
			'deleted'         => array_values( array_map( 'intval', isset( $pending['deleted'] ) ? $pending['deleted'] : array() ) ),
			'catalog_changed' => ! empty( $pending['catalog_changed'] ),
		);
		$result  = Dialogue_WC_Api_Client::signed( 'POST', '/api/woocommerce/events/products', $payload, 10 );
		if ( ! is_wp_error( $result ) ) {
			return;
		}
		$attempt = isset( $pending['attempt'] ) ? (int) $pending['attempt'] + 1 : 1;
		if ( ! Dialogue_WC_Queue::should_retry( $attempt ) ) {
			/* translators: %s: error */
			Dialogue_WC_Log::add( 'error', sprintf( __( 'Product changes could not be sent to Dialogue after several tries; the nightly check will pick them up. Last error: %s', 'dialogue-for-woocommerce' ), $result->get_error_message() ) );
			return;
		}
		$current           = get_option( Dialogue_WC_Settings::OPTION_PENDING, array() );
		$merged            = self::merge( is_array( $current ) ? $current : array(), $payload );
		$merged['attempt'] = $attempt;
		update_option( Dialogue_WC_Settings::OPTION_PENDING, $merged, false );
		Dialogue_WC_Queue::schedule( self::FLUSH_HOOK, array(), Dialogue_WC_Queue::retry_delay( $attempt ) );
		/* translators: 1: attempt, 2: error */
		Dialogue_WC_Log::add( 'warning', sprintf( __( 'Product changes were not sent to Dialogue (try %1$d), will retry: %2$s', 'dialogue-for-woocommerce' ), $attempt, $result->get_error_message() ) );
	}
}
