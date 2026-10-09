<?php
/**
 * Plugin bootstrap.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything together.
 */
class Dialogue_WC_Plugin {

	/**
	 * Singleton.
	 *
	 * @var Dialogue_WC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Boot (plugins_loaded).
	 *
	 * @return Dialogue_WC_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( __CLASS__, 'notice_missing_wc' ) );
			return;
		}
		Dialogue_WC_Frontend::init();
		Dialogue_WC_Orders::init();
		Dialogue_WC_Products::init();
		Dialogue_WC_Rest::init();
		if ( is_admin() ) {
			Dialogue_WC_Admin::init();
		}
	}

	/**
	 * Translations.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'dialogue-for-woocommerce', false, dirname( plugin_basename( DIALOGUE_WC_FILE ) ) . '/languages' );
	}

	/**
	 * WooCommerce missing.
	 *
	 * @return void
	 */
	public static function notice_missing_wc() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p>' . esc_html__( 'Dialogue for WooCommerce needs WooCommerce to be installed and active.', 'dialogue-for-woocommerce' ) . '</p></div>';
	}

	/**
	 * Activation.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( Dialogue_WC_Settings::OPTION_DB_VERSION, DIALOGUE_WC_VERSION, false );
	}

	/**
	 * Deactivation: stop background jobs (connection is kept so re-activating just works).
	 *
	 * @return void
	 */
	public static function deactivate() {
		Dialogue_WC_Queue::clear( array( Dialogue_WC_Products::FLUSH_HOOK, Dialogue_WC_Orders::SEND_HOOK ) );
	}
}
