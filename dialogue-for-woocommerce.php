<?php
/**
 * Plugin Name:       Dialogue for WooCommerce
 * Plugin URI:        https://nowdialogue.com/woocommerce
 * Description:       Connects your WooCommerce store to Dialogue: AI shopping assistant, product recommendations, Reels and A/B tests. One click to connect, products and orders sync automatically.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Dialogue
 * Author URI:        https://nowdialogue.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dialogue-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * WC requires at least: 7.0
 * WC tested up to:   11.2
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

define( 'DIALOGUE_WC_VERSION', '1.0.0' );
define( 'DIALOGUE_WC_FILE', __FILE__ );
define( 'DIALOGUE_WC_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIALOGUE_WC_URL', plugin_dir_url( __FILE__ ) );

require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-signer.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-settings.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-log.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-api-client.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-queue.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-page-context.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-frontend.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-order-payload.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-orders.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-products.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-catalog-schema.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-rest.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-admin.php';
require_once DIALOGUE_WC_DIR . 'includes/class-dialogue-wc-plugin.php';

// Declare compatibility with WooCommerce High-Performance Order Storage (HPOS) and cart/checkout blocks.
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, array( 'Dialogue_WC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Dialogue_WC_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Dialogue_WC_Plugin', 'instance' ) );
