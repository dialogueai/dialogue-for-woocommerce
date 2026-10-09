<?php
/**
 * TEST ENVIRONMENT ONLY (never shipped): lets this local WordPress talk to the mock Dialogue API on
 * the Docker host (private IP, self-signed certificate).
 */
add_filter( 'http_request_host_is_external', '__return_true' );
add_filter( 'https_ssl_verify', '__return_false' );
add_filter( 'https_local_ssl_verify', '__return_false' );
add_filter( 'http_allowed_safe_ports', function ( $ports ) { $ports[] = 4556; return $ports; } );
