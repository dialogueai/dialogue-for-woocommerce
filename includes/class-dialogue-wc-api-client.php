<?php
/**
 * HTTP client for the Dialogue API.
 *
 * @package Dialogue_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

/**
 * Calls Dialogue. Signed calls carry an HMAC of the body (see Dialogue_WC_Signer).
 */
class Dialogue_WC_Api_Client {

	/**
	 * Unsigned JSON POST (only used to start a connection).
	 *
	 * @param string $path    Path under the API base, e.g. /api/woocommerce/connect/start.
	 * @param array  $payload Body.
	 * @param int    $timeout Seconds.
	 * @return array|WP_Error Decoded JSON.
	 */
	public static function post_public( $path, array $payload, $timeout = 15 ) {
		$args = array(
			'method'  => 'POST',
			'timeout' => $timeout,
			'headers' => array(
				'Content-Type' => 'application/json',
				'User-Agent'   => self::user_agent(),
			),
			'body'    => wp_json_encode( $payload ),
		);
		return self::decode( self::send( $path, $args ) );
	}

	/**
	 * Send, switching to the fallback host when the primary one can't be reached (DNS / connection).
	 *
	 * @param string $path Path.
	 * @param array  $args wp_remote_request args.
	 * @return array|WP_Error
	 */
	private static function send( $path, array $args ) {
		$response = wp_remote_request( Dialogue_WC_Settings::api_base() . $path, $args );
		if ( is_wp_error( $response ) && 'http_request_failed' === $response->get_error_code() && Dialogue_WC_Settings::can_fall_back() ) {
			Dialogue_WC_Settings::use_fallback();
			$response = wp_remote_request( Dialogue_WC_Settings::api_base() . $path, $args );
		}
		return $response;
	}

	/**
	 * Signed request.
	 *
	 * @param string     $method  GET|POST.
	 * @param string     $path    Path under the API base.
	 * @param array|null $payload Body for POST.
	 * @param int        $timeout Seconds.
	 * @param bool       $blocking False = fire and forget.
	 * @return array|WP_Error
	 */
	public static function signed( $method, $path, $payload = null, $timeout = 10, $blocking = true ) {
		$conn   = Dialogue_WC_Settings::connection();
		$secret = Dialogue_WC_Settings::secret();
		if ( '' === $conn['site_id'] || '' === $secret ) {
			return new WP_Error( 'dialogue_not_connected', __( 'This store is not connected to Dialogue yet.', 'dialogue-for-woocommerce' ) );
		}
		$body    = null === $payload ? '' : (string) wp_json_encode( $payload );
		$headers = array_merge(
			Dialogue_WC_Signer::headers( $secret, $conn['site_id'], $body ),
			array(
				'Content-Type' => 'application/json',
				'User-Agent'   => self::user_agent(),
			)
		);
		$args    = array(
			'method'   => $method,
			'timeout'  => $timeout,
			'headers'  => $headers,
			'blocking' => $blocking,
		);
		if ( 'GET' !== $method ) {
			$args['body'] = $body;
		}
		$response = self::send( $path, $args );
		if ( ! $blocking ) {
			return is_wp_error( $response ) ? $response : array();
		}
		return self::decode( $response );
	}

	/**
	 * Decode a response.
	 *
	 * @param array|WP_Error $response Response.
	 * @return array|WP_Error
	 */
	private static function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $data ) && isset( $data['message'] ) && is_string( $data['message'] ) ? $data['message'] : '';
			/* translators: %d: HTTP status code */
			return new WP_Error( 'dialogue_http_' . $code, $msg ? $msg : sprintf( __( 'Dialogue answered with an error (%d).', 'dialogue-for-woocommerce' ), $code ), array( 'status' => $code ) );
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * User agent.
	 *
	 * @return string
	 */
	private static function user_agent() {
		return 'DialogueWooCommerce/' . DIALOGUE_WC_VERSION . '; ' . home_url( '/' );
	}
}
