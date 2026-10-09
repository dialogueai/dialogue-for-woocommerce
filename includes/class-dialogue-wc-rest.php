<?php
/**
 * REST routes Dialogue calls on the store (namespace dialogue/v1). Every route except /ping
 * requires a valid HMAC signature made with the shared secret.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Inbound REST API.
 */
class Dialogue_WC_Rest {

	const NS = 'dialogue/v1';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'product_field' ) );
	}

	/**
	 * Adds "dialogue": {taxonomies} to WooCommerce REST product responses, only when Dialogue's
	 * product import asks for it (header X-Dialogue-Feed) and the caller may read products. Lets the
	 * field mapping use brand / custom taxonomies that WooCommerce's API does not include.
	 *
	 * @return void
	 */
	public static function product_field() {
		register_rest_field(
			'product',
			'dialogue',
			array(
				'get_callback' => array( __CLASS__, 'product_field_value' ),
				'schema'       => null,
			)
		);
	}

	/**
	 * Value of the "dialogue" product field.
	 *
	 * @param array           $data    Product response data.
	 * @param string          $field   Field name.
	 * @param WP_REST_Request $request Request.
	 * @return array|null
	 */
	public static function product_field_value( $data, $field, $request ) {
		if ( ! $request instanceof WP_REST_Request || '1' !== (string) $request->get_header( 'x_dialogue_feed' ) || empty( $data['id'] ) ) {
			return null;
		}
		$skip = array_merge( Dialogue_WC_Catalog_Schema::INTERNAL_TAXONOMIES, array( 'product_cat', 'product_tag' ) );
		$out  = array();
		foreach ( get_object_taxonomies( 'product' ) as $taxonomy ) {
			if ( in_array( $taxonomy, $skip, true ) || 0 === strpos( $taxonomy, 'pa_' ) ) {
				continue;
			}
			$terms = wp_get_post_terms( (int) $data['id'], $taxonomy, array( 'fields' => 'names' ) );
			if ( is_array( $terms ) && $terms ) {
				$out[ $taxonomy ] = array_map( array( 'Dialogue_WC_Catalog_Schema', 'decode' ), array_values( $terms ) );
			}
		}
		return array( 'taxonomies' => $out );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			self::NS,
			'/ping',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'ping' ),
				'permission_callback' => '__return_true',
			)
		);
		$signed = array(
			'/handshake'      => array( 'POST', 'handshake' ),
			'/health'         => array( 'GET', 'health' ),
			'/catalog-schema' => array( 'GET', 'catalog_schema' ),
			'/swatches'       => array( 'GET', 'swatches' ),
			'/connection'     => array( 'POST', 'connection' ),
		);
		foreach ( $signed as $route => $def ) {
			register_rest_route(
				self::NS,
				$route,
				array(
					'methods'             => $def[0],
					'callback'            => array( __CLASS__, $def[1] ),
					'permission_callback' => array( __CLASS__, 'verify' ),
				)
			);
		}
	}

	/**
	 * Signature check for signed routes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	public static function verify( $request ) {
		$conn = Dialogue_WC_Settings::connection();
		$ok   = Dialogue_WC_Signer::verify(
			Dialogue_WC_Settings::secret(),
			$conn['site_id'],
			(string) $request->get_header( 'x_dialogue_site' ),
			(string) $request->get_header( 'x_dialogue_timestamp' ),
			(string) $request->get_header( 'x_dialogue_signature' ),
			(string) $request->get_body()
		);
		return $ok ? true : new WP_Error( 'dialogue_bad_signature', __( 'Invalid signature.', 'dialogue-for-woocommerce' ), array( 'status' => 401 ) );
	}

	/**
	 * Public liveness check (no store data).
	 *
	 * @return array
	 */
	public static function ping() {
		return array(
			'plugin'    => 'dialogue-for-woocommerce',
			'version'   => DIALOGUE_WC_VERSION,
			'connected' => Dialogue_WC_Settings::is_connected(),
		);
	}

	/**
	 * Dialogue proves it has the secret and learns the versions.
	 *
	 * @return array
	 */
	public static function handshake() {
		$conn = Dialogue_WC_Settings::connection();
		return array(
			'ok'             => true,
			'site_id'        => $conn['site_id'],
			'plugin_version' => DIALOGUE_WC_VERSION,
			'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : '',
			'wp_version'     => get_bloginfo( 'version' ),
			'home_url'       => home_url( '/' ),
		);
	}

	/**
	 * Diagnostics for the Dialogue admin status page.
	 *
	 * @return array
	 */
	public static function health() {
		$pending = get_option( Dialogue_WC_Settings::OPTION_PENDING, array() );
		return array_merge(
			self::handshake(),
			array(
				'currency'         => get_woocommerce_currency(),
				'locale'           => determine_locale(),
				'action_scheduler' => function_exists( 'as_schedule_single_action' ),
				'pending_products' => is_array( $pending ) && isset( $pending['updated'] ) ? count( $pending['updated'] ) : 0,
				'consent_api'      => function_exists( 'wp_has_consent' ),
				'hpos'             => class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),
				'log'              => array_slice( Dialogue_WC_Log::entries(), 0, 20 ),
			)
		);
	}

	/**
	 * Field mapping sources.
	 *
	 * @return array
	 */
	public static function catalog_schema() {
		return Dialogue_WC_Catalog_Schema::get();
	}

	/**
	 * Swatches for one attribute.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function swatches( $request ) {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		return array(
			'taxonomy' => $taxonomy,
			'terms'    => Dialogue_WC_Catalog_Schema::swatches(
				$taxonomy,
				sanitize_key( (string) $request->get_param( 'color_key' ) ),
				sanitize_key( (string) $request->get_param( 'image_key' ) )
			),
		);
	}

	/**
	 * Dialogue tells the plugin about a connection change (e.g. disconnected from the dashboard).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function connection( $request ) {
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		if ( Dialogue_WC_Settings::STATUS_DISCONNECTED === $status ) {
			Dialogue_WC_Settings::update_connection( array( 'status' => Dialogue_WC_Settings::STATUS_DISCONNECTED ) );
			Dialogue_WC_Log::add( 'info', __( 'Dialogue disconnected this store.', 'dialogue-for-woocommerce' ) );
		} elseif ( Dialogue_WC_Settings::STATUS_CONNECTED === $status ) {
			$values = array( 'status' => Dialogue_WC_Settings::STATUS_CONNECTED );
			foreach ( array( 'store_id', 'user_id' ) as $k ) {
				if ( null !== $request->get_param( $k ) ) {
					$values[ $k ] = absint( $request->get_param( $k ) );
				}
			}
			Dialogue_WC_Settings::update_connection( $values );
		}
		return array( 'ok' => true );
	}
}
