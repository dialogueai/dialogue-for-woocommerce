<?php
/**
 * HMAC request signing shared with the Dialogue backend.
 *
 * Message: "{site_id}.{timestamp}.{raw body}", HMAC-SHA256 with the shared secret, hex encoded.
 * Headers: X-Dialogue-Site, X-Dialogue-Timestamp, X-Dialogue-Signature.
 *
 * Pure PHP (no WordPress calls) so it can be unit tested in isolation.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Signs and verifies requests between the plugin and Dialogue.
 */
class Dialogue_WC_Signer {

	/** Maximum clock skew accepted, in seconds. */
	const MAX_SKEW = 300;

	/**
	 * Signature for a request.
	 *
	 * @param string $secret    Shared secret.
	 * @param string $site_id   Dialogue site (connection) id.
	 * @param int    $timestamp Unix seconds.
	 * @param string $body      Raw request body ('' for GET).
	 * @return string Hex HMAC-SHA256.
	 */
	public static function sign( $secret, $site_id, $timestamp, $body ) {
		return hash_hmac( 'sha256', $site_id . '.' . (int) $timestamp . '.' . (string) $body, (string) $secret );
	}

	/**
	 * Headers for an outgoing signed request.
	 *
	 * @param string   $secret  Shared secret.
	 * @param string   $site_id Site id.
	 * @param string   $body    Raw body.
	 * @param int|null $now     Override for tests.
	 * @return array<string,string>
	 */
	public static function headers( $secret, $site_id, $body, $now = null ) {
		$ts = null === $now ? time() : (int) $now;
		return array(
			'X-Dialogue-Site'      => (string) $site_id,
			'X-Dialogue-Timestamp' => (string) $ts,
			'X-Dialogue-Signature' => self::sign( $secret, $site_id, $ts, $body ),
		);
	}

	/**
	 * Verifies an incoming signed request.
	 *
	 * @param string   $secret    Shared secret.
	 * @param string   $site_id   Expected site id (ours).
	 * @param string   $given_site Site id from the request header.
	 * @param string   $timestamp Timestamp header.
	 * @param string   $signature Signature header.
	 * @param string   $body      Raw body.
	 * @param int|null $now       Override for tests.
	 * @return bool
	 */
	public static function verify( $secret, $site_id, $given_site, $timestamp, $signature, $body, $now = null ) {
		if ( '' === (string) $secret || '' === (string) $site_id || ! is_string( $signature ) || '' === $signature ) {
			return false;
		}
		if ( ! hash_equals( (string) $site_id, (string) $given_site ) ) {
			return false;
		}
		if ( ! preg_match( '/^\d{9,11}$/', (string) $timestamp ) ) {
			return false;
		}
		$now = null === $now ? time() : (int) $now;
		if ( abs( $now - (int) $timestamp ) > self::MAX_SKEW ) {
			return false;
		}
		$expected = self::sign( $secret, $site_id, (int) $timestamp, $body );
		return hash_equals( $expected, strtolower( $signature ) );
	}
}
