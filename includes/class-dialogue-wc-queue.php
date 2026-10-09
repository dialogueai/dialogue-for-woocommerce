<?php
/**
 * Background jobs: Action Scheduler (bundled with WooCommerce) when available, WP-Cron otherwise.
 * Nothing that talks to Dialogue runs inside the shopper's checkout request.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Job queue with retry policy.
 */
class Dialogue_WC_Queue {

	const GROUP        = 'dialogue';
	const MAX_ATTEMPTS = 8;

	/**
	 * Seconds to wait before retry number $attempt (1-based): 1m, 2m, 4m ... capped at 6h.
	 *
	 * @param int $attempt Attempt that just failed.
	 * @return int
	 */
	public static function retry_delay( $attempt ) {
		$attempt = max( 1, (int) $attempt );
		return (int) min( 6 * HOUR_IN_SECONDS, 60 * pow( 2, $attempt - 1 ) );
	}

	/**
	 * True when another try is allowed after $attempt failures.
	 *
	 * @param int $attempt Attempts so far.
	 * @return bool
	 */
	public static function should_retry( $attempt ) {
		return (int) $attempt < self::MAX_ATTEMPTS;
	}

	/**
	 * Run $hook with $args after $delay seconds.
	 *
	 * @param string $hook  Action hook.
	 * @param array  $args  Arguments.
	 * @param int    $delay Seconds.
	 * @return void
	 */
	public static function schedule( $hook, array $args = array(), $delay = 0 ) {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + max( 0, (int) $delay ), $hook, $args, self::GROUP );
			return;
		}
		wp_schedule_single_event( time() + max( 0, (int) $delay ), $hook, $args );
	}

	/**
	 * Schedule only when the same job is not already waiting.
	 *
	 * @param string $hook  Hook.
	 * @param array  $args  Args.
	 * @param int    $delay Seconds.
	 * @return void
	 */
	public static function schedule_unique( $hook, array $args = array(), $delay = 0 ) {
		if ( self::is_scheduled( $hook, $args ) ) {
			return;
		}
		self::schedule( $hook, $args, $delay );
	}

	/**
	 * Is the job waiting?
	 *
	 * @param string $hook Hook.
	 * @param array  $args Args.
	 * @return bool
	 */
	public static function is_scheduled( $hook, array $args = array() ) {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			return (bool) as_has_scheduled_action( $hook, $args, self::GROUP );
		}
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			return false !== as_next_scheduled_action( $hook, $args, self::GROUP );
		}
		return false !== wp_next_scheduled( $hook, $args );
	}

	/**
	 * Remove every waiting job of ours.
	 *
	 * @param string[] $hooks Hooks.
	 * @return void
	 */
	public static function clear( array $hooks ) {
		foreach ( $hooks as $hook ) {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook );
			}
			wp_unschedule_hook( $hook );
		}
	}
}
