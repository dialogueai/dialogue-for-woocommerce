<?php
/**
 * Stored plugin settings (WordPress options).
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the connection state. The shared secret is kept in its own
 * non-autoloaded option and is never printed anywhere.
 */
class Dialogue_WC_Settings {

	const OPTION_CONNECTION = 'dialogue_wc_connection';
	const OPTION_SECRET     = 'dialogue_wc_secret';
	const OPTION_LOG        = 'dialogue_wc_log';
	const OPTION_PENDING    = 'dialogue_wc_pending_products';
	const OPTION_DB_VERSION = 'dialogue_wc_version';

	const STATUS_DISCONNECTED = 'disconnected';
	const STATUS_PENDING      = 'pending';
	const STATUS_CONNECTED    = 'connected';

	const DEFAULT_API_BASE  = 'https://dashboard.nowdialogue.com';
	const DEFAULT_BUNDLE    = 'https://cdn.nowdialogue.com/prod/build/bundle.js';
	const DEFAULT_DASHBOARD = 'https://dashboard.nowdialogue.com';

	/**
	 * Every option the plugin owns (for uninstall).
	 *
	 * @return string[]
	 */
	public static function all_options() {
		return array( self::OPTION_CONNECTION, self::OPTION_SECRET, self::OPTION_LOG, self::OPTION_PENDING, self::OPTION_DB_VERSION );
	}

	/**
	 * Dialogue API base URL. Override with the DIALOGUE_WC_API_BASE constant (local testing) or filter.
	 *
	 * @return string
	 */
	public static function api_base() {
		$base = defined( 'DIALOGUE_WC_API_BASE' ) ? (string) DIALOGUE_WC_API_BASE : self::DEFAULT_API_BASE;
		return untrailingslashit( (string) apply_filters( 'dialogue_wc_api_base', $base ) );
	}

	/**
	 * Connection state.
	 *
	 * @return array
	 */
	public static function connection() {
		$conn = get_option( self::OPTION_CONNECTION, array() );
		if ( ! is_array( $conn ) ) {
			$conn = array();
		}
		return wp_parse_args(
			$conn,
			array(
				'status'        => self::STATUS_DISCONNECTED,
				'site_id'       => '',
				'store_id'      => 0,
				'user_id'       => 0,
				'key_id'        => 0,
				'connected_at'  => '',
				'dashboard_url' => '',
				'bundle_url'    => '',
				'last_error'    => '',
			)
		);
	}

	/**
	 * Merge values into the connection state.
	 *
	 * @param array $values Values.
	 * @return void
	 */
	public static function update_connection( array $values ) {
		update_option( self::OPTION_CONNECTION, array_merge( self::connection(), $values ), false );
	}

	/**
	 * True when the store is fully connected.
	 *
	 * @return bool
	 */
	public static function is_connected() {
		$c = self::connection();
		return self::STATUS_CONNECTED === $c['status'] && '' !== $c['site_id'] && '' !== self::secret();
	}

	/**
	 * Shared secret (never output it).
	 *
	 * @return string
	 */
	public static function secret() {
		$s = get_option( self::OPTION_SECRET, '' );
		return is_string( $s ) ? $s : '';
	}

	/**
	 * Store the shared secret.
	 *
	 * @param string $secret Secret.
	 * @return void
	 */
	public static function set_secret( $secret ) {
		update_option( self::OPTION_SECRET, (string) $secret, false );
	}

	/**
	 * Forget the connection (keeps the activity log).
	 *
	 * @return void
	 */
	public static function reset() {
		delete_option( self::OPTION_CONNECTION );
		delete_option( self::OPTION_SECRET );
		delete_option( self::OPTION_PENDING );
	}
}
