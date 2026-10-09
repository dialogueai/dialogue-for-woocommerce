<?php
/**
 * Unit test bootstrap: minimal WordPress stubs so the pure parts of the plugin can be tested
 * without a WordPress install (the Docker e2e run covers the WordPress integration).
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DIALOGUE_WC_VERSION', '1.0.0-test' );

$GLOBALS['dialogue_test_options'] = array();

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['dialogue_test_options'] ) ? $GLOBALS['dialogue_test_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['dialogue_test_options'][ $name ] = $value;
	return true;
}
function delete_option( $name ) {
	unset( $GLOBALS['dialogue_test_options'][ $name ] );
	return true;
}
function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, (array) $args );
}
function apply_filters( $tag, $value ) {
	return $value;
}
function untrailingslashit( $s ) {
	return rtrim( $s, '/\\' );
}
function __( $s, $d = null ) {
	return $s;
}

require_once dirname( __DIR__ ) . '/includes/class-dialogue-wc-signer.php';
require_once dirname( __DIR__ ) . '/includes/class-dialogue-wc-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-dialogue-wc-queue.php';
require_once dirname( __DIR__ ) . '/includes/class-dialogue-wc-page-context.php';
require_once dirname( __DIR__ ) . '/includes/class-dialogue-wc-order-payload.php';
require_once dirname( __DIR__ ) . '/includes/class-dialogue-wc-products.php';
require_once dirname( __DIR__ ) . '/includes/class-dialogue-wc-catalog-schema.php';
