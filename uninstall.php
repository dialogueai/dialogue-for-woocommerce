<?php
/**
 * Uninstall: remove everything the plugin stored, including the read-only API key WooCommerce
 * created for Dialogue (so Dialogue can no longer read the store).
 *
 * @package Dialogue_WooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$dialogue_wc_conn = get_option( 'dialogue_wc_connection', array() );

if ( is_array( $dialogue_wc_conn ) && ! empty( $dialogue_wc_conn['key_id'] ) ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- WooCommerce has no API to delete a REST key.
	$wpdb->delete( $wpdb->prefix . 'woocommerce_api_keys', array( 'key_id' => absint( $dialogue_wc_conn['key_id'] ) ), array( '%d' ) );
}

foreach ( array( 'dialogue_wc_connection', 'dialogue_wc_secret', 'dialogue_wc_log', 'dialogue_wc_pending_products', 'dialogue_wc_version' ) as $dialogue_wc_option ) {
	delete_option( $dialogue_wc_option );
}

foreach ( array( 'dialogue_wc_flush_products', 'dialogue_wc_send_order' ) as $dialogue_wc_hook ) {
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( $dialogue_wc_hook );
	}
	wp_unschedule_hook( $dialogue_wc_hook );
}
