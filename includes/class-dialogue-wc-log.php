<?php
/**
 * Small activity log shown on the settings page (last 50 entries) and mirrored to the
 * WooCommerce logger (WooCommerce → Status → Logs, source "dialogue").
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activity log.
 */
class Dialogue_WC_Log {

	const MAX = 50;

	/**
	 * Add an entry. Never pass secrets or customer data.
	 *
	 * @param string $level   info|warning|error.
	 * @param string $message Message.
	 * @return void
	 */
	public static function add( $level, $message ) {
		$entries = get_option( Dialogue_WC_Settings::OPTION_LOG, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}
		array_unshift(
			$entries,
			array(
				'time'    => time(),
				'level'   => (string) $level,
				'message' => substr( (string) $message, 0, 300 ),
			)
		);
		update_option( Dialogue_WC_Settings::OPTION_LOG, array_slice( $entries, 0, self::MAX ), false );

		if ( function_exists( 'wc_get_logger' ) ) {
			$logger = wc_get_logger();
			$method = in_array( $level, array( 'info', 'warning', 'error' ), true ) ? $level : 'info';
			$logger->{$method}( (string) $message, array( 'source' => 'dialogue' ) );
		}
	}

	/**
	 * Entries, newest first.
	 *
	 * @return array
	 */
	public static function entries() {
		$entries = get_option( Dialogue_WC_Settings::OPTION_LOG, array() );
		return is_array( $entries ) ? $entries : array();
	}
}
