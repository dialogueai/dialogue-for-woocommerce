/**
 * Dialogue storefront loader for WooCommerce.
 * Reads window.DialogueWooConfig (printed by the plugin), loads the Dialogue bundle without
 * blocking the page, then starts it for this store. window.DialogueWoo holds the page context.
 */
( function () {
	'use strict';
	var cfg = window.DialogueWooConfig;
	if ( ! cfg || ! cfg.bundle || ! cfg.store_id || window.__dialogueWooLoaded ) {
		return;
	}
	window.__dialogueWooLoaded = true;

	// First-party visitor cookies (dai_v_h / dai_v_s) let the server match orders to Dialogue
	// visits. With the WP Consent API, only set them after statistics consent.
	function consentGiven() {
		if ( typeof window.wp_has_consent === 'function' ) {
			try {
				return !! window.wp_has_consent( 'statistics' );
			} catch ( e ) {
				return false;
			}
		}
		return true;
	}
	window.DAI_COOKIE = consentGiven();
	document.addEventListener( 'wp_listen_for_consent_change', function () {
		window.DAI_COOKIE = consentGiven();
	} );

	function start() {
		if ( window.DialogueAI && typeof window.DialogueAI.getAllMerchantScripts === 'function' ) {
			window.DialogueAI.getAllMerchantScripts( cfg.user_id, cfg.store_id );
		}
	}

	var s = document.createElement( 'script' );
	s.src = cfg.bundle;
	s.async = true;
	s.onload = start;
	( document.head || document.documentElement ).appendChild( s );
}() );
