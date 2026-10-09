=== Dialogue for WooCommerce ===
Contributors: nowdialogue
Tags: woocommerce, ai shopping assistant, product recommendations, a/b testing, shoppable video
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 11.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI shopping assistant, product recommendations, shoppable Reels and A/B tests for WooCommerce. Connect in one click.

== Description ==

Dialogue helps shoppers find the right product and buy more:

* **AI shopping assistant** that answers questions about your products, sizes, shipping and policies.
* **Product recommendation widgets** (similar items, complete the look, bundles) placed on product, category and cart pages.
* **Shoppable Reels** from your Instagram and TikTok.
* **A/B tests** so you can see what actually sells more.

This plugin connects your WooCommerce store to Dialogue:

1. Go to **WooCommerce → Dialogue** and click **Connect to Dialogue**.
2. Approve read-only access on the standard WooCommerce screen.
3. That's it. Dialogue appears on your store, your products are imported and kept in sync, and paid orders are counted in your Dialogue results.

= What the plugin does =

* Loads the Dialogue script on your storefront (without slowing the page down) and tells it what page the shopper is on.
* Lets Dialogue read your products, variations, categories, prices, stock and images (read-only WooCommerce API key that you approve).
* Sends product changes to Dialogue within about a minute, so prices and stock stay correct.
* Sends paid orders to Dialogue in the background so you can see the sales Dialogue helped with. Checkout is never slowed down and never fails because of Dialogue.

= Privacy =

* Orders sent to Dialogue contain the order number, totals, currency, coupon codes and the products bought. **No customer names, emails, phone numbers or addresses are sent.**
* Dialogue's script sets first-party cookies (`dai_v_h`, `dai_v_s`) to recognize returning visitors and to connect orders to Dialogue visits. If you use a consent plugin that supports the WP Consent API, these cookies are only set after the visitor allows statistics cookies.

This plugin uses the Dialogue service (https://nowdialogue.com). By connecting you agree to Dialogue's [Terms of Service](https://nowdialogue.com/terms) and [Privacy Policy](https://nowdialogue.com/privacy). Data is sent to:

* `https://dashboard.nowdialogue.com` — connection, product change notices and order events.
* `https://cdn.nowdialogue.com` — the storefront script.

== Installation ==

1. Install and activate WooCommerce.
2. Install this plugin from **Plugins → Add New** (search for "Dialogue") or upload the zip, then activate it.
3. Go to **WooCommerce → Dialogue** and click **Connect to Dialogue**.

== Frequently Asked Questions ==

= Do I need a Dialogue account? =

No. If you don't have one, it is created when you connect, and we email you a link to set your password.

= Can Dialogue change my products or orders? =

No. Dialogue asks only for read access (WooCommerce's standard "Read" permission) and only reads your products and categories with it. Orders are sent by the plugin itself, without customer details.

= Does it work with the block checkout and HPOS? =

Yes. The plugin supports the cart and checkout blocks and WooCommerce High-Performance Order Storage.

= How do I disconnect? =

Go to **WooCommerce → Dialogue** and click **Disconnect**. Removing the plugin also removes the access key.

== Screenshots ==

1. Connect your store in one click.
2. Connected: status and quick links.

== Changelog ==

= 1.0.0 =
* First release: one-click connect, storefront script with page context, product change sync, purchase events, WP Consent API support, English and Hebrew.

== Upgrade Notice ==

= 1.0.0 =
First release.
