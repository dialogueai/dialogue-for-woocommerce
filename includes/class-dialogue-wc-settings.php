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

	const DEFAULT_API_BASE  = 'https://plugin.nowdialogue.com';
	const FALLBACK_API_BASE = 'https://dashboard.nowdialogue.com';
	const TERMS_URL         = 'https://nowdialogue.com/terms';
	const PRIVACY_URL       = 'https://nowdialogue.com/privacy-policy';

	const TRANSIENT_FALLBACK = 'dialogue_wc_api_fallback';
	const DEFAULT_BUNDLE     = 'https://cdn.nowdialogue.com/prod/build/bundle.js';
	const DEFAULT_DASHBOARD  = 'https://dashboard.nowdialogue.com';

	/**
	 * Every option the plugin owns (for uninstall).
	 *
	 * @return string[]
	 */
	public static function all_options() {
		return array( self::OPTION_CONNECTION, self::OPTION_SECRET, self::OPTION_LOG, self::OPTION_PENDING, self::OPTION_DB_VERSION );
	}

	/**
	 * Dialogue API base URL: the DIALOGUE_WC_API_BASE constant (or filter) when set; otherwise
	 * plugin.nowdialogue.com, falling back to dashboard.nowdialogue.com for 12 hours when it can't be
	 * reached (see Dialogue_WC_Api_Client).
	 *
	 * @return string
	 */
	public static function api_base() {
		if ( defined( 'DIALOGUE_WC_API_BASE' ) ) {
			$base = (string) DIALOGUE_WC_API_BASE;
		} else {
			$base = get_transient( self::TRANSIENT_FALLBACK ) ? self::FALLBACK_API_BASE : self::DEFAULT_API_BASE;
		}
		return untrailingslashit( (string) apply_filters( 'dialogue_wc_api_base', $base ) );
	}

	/**
	 * True when the base is the built-in primary host (so a fallback makes sense).
	 *
	 * @return bool
	 */
	public static function can_fall_back() {
		return ! defined( 'DIALOGUE_WC_API_BASE' ) && ! get_transient( self::TRANSIENT_FALLBACK ) && self::DEFAULT_API_BASE === self::api_base();
	}

	/**
	 * Use the fallback host for a while.
	 *
	 * @return void
	 */
	public static function use_fallback() {
		set_transient( self::TRANSIENT_FALLBACK, 1, 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Hosts the connect flow may redirect the admin to (Dialogue's pages).
	 *
	 * @return string[]
	 */
	public static function dialogue_hosts() {
		$hosts = array();
		foreach ( array( self::api_base(), self::DEFAULT_API_BASE, self::FALLBACK_API_BASE, self::DEFAULT_DASHBOARD ) as $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST );
			if ( $host ) {
				$hosts[] = strtolower( $host );
			}
		}
		return array_values( array_unique( (array) apply_filters( 'dialogue_wc_allowed_hosts', $hosts ) ) );
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
