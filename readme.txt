=== Dialogue for WooCommerce ===
Contributors: nowdialogue
Tags: ai shopping assistant, product recommendations, a/b testing, shoppable video, chatbot
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

* **AI shopping assistant** that answers questions about your products, sizes, shipping and policies, and shows matching products with real colors and images.
* **Product recommendation widgets** (similar items, complete the look, bundles) on product, category and cart pages.
* **Shoppable Reels** from your Instagram and TikTok.
* **A/B tests** so you can see what actually sells more.

This plugin connects your WooCommerce store to your Dialogue account:

1. Go to **WooCommerce → Dialogue** and click **Connect to Dialogue**.
2. Log in to Dialogue (or create an account) and choose your plan.
3. Approve read-only access on WooCommerce's own screen.
4. Back in WordPress you see **Connected**. Dialogue appears on your store, your products are imported and kept in sync, and paid orders are counted in your Dialogue results.

= What the plugin does =

* Loads the Dialogue script on your storefront (without slowing the page down) and tells it what page the shopper is on.
* Lets Dialogue read your products, variations, categories, prices, stock and images, using a read-only WooCommerce API key that you approve.
* Tells Dialogue about product changes within about a minute, so prices and stock stay correct.
* Sends paid orders to Dialogue in the background so you can see the sales Dialogue helped with. Checkout never waits for Dialogue and never fails because of it.
* In your Dialogue dashboard you can choose which store fields, attributes and custom fields fill each product detail, which attribute is the color, where swatches come from (swatch plugins, variation images or color names) and which images to show.

== External services ==

This plugin connects to the Dialogue service by Dialogue (https://nowdialogue.com). It only works with a Dialogue account. [Terms of Service](https://nowdialogue.com/terms) · [Privacy Policy](https://nowdialogue.com/privacy-policy).

* `https://plugin.nowdialogue.com` (or `https://dashboard.nowdialogue.com` while the first can't be reached): connecting the store, product change notices (product ids only) and paid-order events, described under Privacy.
* `https://dashboard.nowdialogue.com`: the page where you log in, choose a plan and manage Dialogue.
* `https://cdn.nowdialogue.com`: the storefront script, loaded in your shoppers' browsers.
* Card payments for your Dialogue plan are handled by Stripe on Dialogue's side; the plugin never sees card details.

== Privacy ==

**What the plugin sends to Dialogue**

* When you connect: your site address, store name, WordPress REST API address, currency, language, time zone and the WordPress, WooCommerce and plugin versions. WooCommerce then sends Dialogue a read-only API key that you approve.
* When a product changes: only the ids of the changed or deleted products. Dialogue then reads those products (names, descriptions, prices, stock, attributes, categories, tags, brands, images) through the read-only key.
* When an order is paid: order id and number, status, totals (total, subtotal, discount, shipping, tax), currency, coupon codes, creation and payment dates, how it was placed (checkout or block checkout), and for each product bought: product and variation id, SKU, name, quantity and price. It also sends the random Dialogue visitor id and session id from the shopper's cookies, and whether the order was placed on a phone or a computer.
* **Never sent by the plugin:** customer names, email addresses, phone numbers, billing or shipping addresses, IP addresses, browser details or card details.
* The storefront script runs in the shopper's browser and talks to Dialogue directly (to show the assistant and recommendations and to count views and clicks). As with any web service, Dialogue receives the browser's IP address and user agent with those requests; see Dialogue's privacy policy.

**Cookies**

Dialogue's storefront script sets first-party cookies `dai_v_h` (random visitor id, 1 year) and `dai_v_s` (session, 4 days) to recognise returning visitors and connect orders to Dialogue visits. If your store uses a consent banner that supports the WP Consent API, these cookies are only set after the visitor allows statistics cookies. The plugin adds suggested text to **Settings → Privacy → Policy guide**.

**Stored in WordPress**

The connection details and a shared secret (used to sign every request between your store and Dialogue) are kept in your WordPress options. The secret is never shown. Disconnecting or deleting the plugin removes them and the WooCommerce API key.

== Installation ==

1. Install and activate WooCommerce.
2. Install this plugin from **Plugins → Add New** (search for "Dialogue for WooCommerce") or upload the zip, then activate it.
3. Go to **WooCommerce → Dialogue** and click **Connect to Dialogue**.
4. Log in to Dialogue (or create an account), choose a plan, and approve access on the WooCommerce screen.

== Frequently Asked Questions ==

= Do I need a Dialogue account? =

Yes. When you click Connect you can log in, or create an account in a minute.

= Can Dialogue change my products or orders? =

No. Dialogue asks only for WooCommerce's standard "Read" permission and uses it to read your products and categories. Orders are sent by the plugin itself, without customer details.

= Does it work with the block checkout and HPOS? =

Yes. The plugin supports the cart and checkout blocks and WooCommerce High-Performance Order Storage.

= Does it work with variation swatch plugins? =

Yes. Colors and images set by popular swatch plugins (for example Variation Swatches for WooCommerce and CartFlows Variation Swatches) are used as swatches in Dialogue. You can also use variation images or your own color names.

= How do I disconnect? =

Go to **WooCommerce → Dialogue** and click **Disconnect**. Deleting the plugin also removes the access key.

== Screenshots ==

1. Connect your store from WooCommerce → Dialogue.
2. Log in to Dialogue and confirm the store (plan and card are chosen here too).
3. Connected: status, quick links and recent activity.
4. The AI shopping assistant on a WooCommerce product page, with the store's own products, prices and color swatches.
5. In the Dialogue dashboard: choose the color attribute, where swatches come from and which images to show.

== Changelog ==

= 1.0.0 =
* First release: connect with your Dialogue account, storefront script with page context, product change sync, purchase events without customer details, WP Consent API support, English and Hebrew.

== Upgrade Notice ==

= 1.0.0 =
First release.
