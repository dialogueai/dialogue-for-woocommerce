/*
 * WooCommerce + Dialogue plugin end-to-end run (local Docker WordPress, mock Dialogue API).
 *
 *  1. docker compose up -d   (then: wp core install, wp plugin install woocommerce --activate,
 *     wp eval-file seed.php, wp plugin activate dialogue-for-woocommerce — inside the "cli" service)
 *  2. Build the mock's libs from dialogue-backend-1 (tsc src/modules/woocommerce/util/*.ts --outDir mock/lib)
 *     and the real widgets_front cart listener into mock/public/cart-e2e.js; start: node mock/server.js
 *     (https :4556 = Dialogue API with a self-signed cert, http :4557 = stand-in storefront bundle)
 *  3. node e2e.js  → shots/*.png, logs/e2e-results.json; then run the queued jobs:
 *     wp action-scheduler action run <ids>  → logs/order-events.json, product-events.json, feed-rows*.json
 * mu/dialogue-test-env.php only exists in this test site (private IP + self-signed cert + port 4556).
 */
const { chromium } = require(process.env.PLAYWRIGHT_PATH || 'playwright');
const fs = require('fs');
const path = require('path');

const SITE = 'http://localhost:8089';
const OUT = __dirname;
const shot = (page, name) => page.screenshot({ path: path.join(OUT, 'shots', `${name}.png`), fullPage: true });
const results = {};
const save = () => fs.writeFileSync(path.join(OUT, 'logs', 'e2e-results.json'), JSON.stringify(results, null, 2));

(async () => {
  const browser = await chromium.launch({ args: ['--host-resolver-rules=MAP host.docker.internal 127.0.0.1'] });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true });
  const page = await ctx.newPage();
  page.on('console', (m) => { if (m.type() === 'error') fs.appendFileSync(path.join(OUT, 'logs', 'browser-console.log'), `${m.text()}\n`); });

  // 1. Admin login + settings page (not connected).
  await page.goto(`${SITE}/wp-login.php`);
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'admin');
  await page.click('#wp-submit');
  await page.waitForLoadState('networkidle');
  await page.goto(`${SITE}/wp-admin/admin.php?page=dialogue-for-woocommerce`);
  await shot(page, '01-settings-not-connected');

  // 2. Connect → WooCommerce approve screen.
  await page.click('input[type=submit][value="Connect to Dialogue"]');
  await page.waitForLoadState('networkidle');
  results.connect_page_url = page.url().replace(/token=[^&]+/, 'token=…');
  await shot(page, '02a-dialogue-login');
  await page.click('#login');
  await page.waitForLoadState('networkidle');
  await shot(page, '02b-dialogue-plan');
  await page.click('#pay');
  await page.waitForLoadState('networkidle');
  await shot(page, '02c-stripe-test-checkout');
  await page.click('#subscribe');
  await page.waitForURL(/wc-auth\/v1\/authorize/, { timeout: 15000 });
  await page.waitForLoadState('networkidle');
  results.authorize_url = page.url();
  await shot(page, '02-woocommerce-approve-screen');
  await page.click('a.wc-auth-approve, #wc-auth-approve, .wc-auth-approve');
  await page.waitForLoadState('networkidle');
  results.after_approve_url = page.url();
  await shot(page, '03-settings-connected');
  results.settings_text = (await page.textContent('.dialogue-wc-card')).replace(/\s+/g, ' ').trim();

  // 3. Storefront product page: script + page context.
  const shop = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const sp = await shop.newPage();
  await sp.goto(`${SITE}/product/classic-hoodie/`);
  await sp.waitForFunction(() => window.__cartEvents !== undefined, null, { timeout: 15000 });
  results.product_page = await sp.evaluate(() => ({
    DialogueWoo: window.DialogueWoo,
    DialogueWooConfig: window.DialogueWooConfig,
    started: window.__dialogueStarted,
    platform: window.__platform,
    readPage: window.__page,
    chatPageType: window.__chatPageType,
    loaderScript: [...document.scripts].map((s) => s.src).filter((s) => /dialogue/.test(s)),
    cookies: document.cookie,
  }));
  await shot(sp, '04-storefront-product');

  // 4. Category page context.
  await sp.goto(`${SITE}/product-category/hoodies/`);
  await sp.waitForFunction(() => window.__cartEvents !== undefined, null, { timeout: 15000 });
  results.category_page = await sp.evaluate(() => ({ DialogueWoo: window.DialogueWoo, readPage: window.__page }));
  await shot(sp, '05-storefront-category');

  // 5. Add to cart (classic AJAX button on the archive) → cart adapter sees "add".
  const addBtn = sp.locator('.ajax_add_to_cart').first();
  if (await addBtn.count()) {
    await addBtn.click();
    await sp.waitForTimeout(3000);
  } else {
    await sp.evaluate(async () => {
      const r = await fetch('/wp-json/wc/store/v1/cart', { credentials: 'include' });
      const nonce = r.headers.get('Nonce');
      await fetch('/wp-json/wc/store/v1/cart/add-item', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Nonce: nonce }, body: JSON.stringify({ id: 13, quantity: 1 }) });
    });
    await sp.waitForTimeout(3000);
  }
  results.cart_events_after_add = await sp.evaluate(() => window.__cartEvents);
  await shot(sp, '06-after-add-to-cart');

  // 6. Remove via the Store API (what the cart block does) → "remove".
  await sp.evaluate(async () => {
    const r = await fetch('/wp-json/wc/store/v1/cart', { credentials: 'include' });
    const nonce = r.headers.get('Nonce');
    const cart = await r.json();
    if (cart.items[0]) {
      await fetch('/wp-json/wc/store/v1/cart/remove-item', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Nonce: nonce }, body: JSON.stringify({ key: cart.items[0].key }) });
    }
  });
  await sp.waitForTimeout(3000);
  results.cart_events_after_remove = await sp.evaluate(() => window.__cartEvents);

  // 7. Checkout through the Store API (block checkout) with Cash on Delivery.
  const order = await sp.evaluate(async () => {
    let r = await fetch('/wp-json/wc/store/v1/cart', { credentials: 'include' });
    let nonce = r.headers.get('Nonce');
    await fetch('/wp-json/wc/store/v1/cart/add-item', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Nonce: nonce }, body: JSON.stringify({ id: 11, quantity: 2 }) });
    await fetch('/wp-json/wc/store/v1/cart/add-item', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Nonce: nonce }, body: JSON.stringify({ id: 13, quantity: 1 }) });
    const addr = { first_name: 'Jane', last_name: 'Shopper', address_1: '1 Main St', city: 'Tel Aviv', postcode: '6100000', country: 'IL', email: 'jane.shopper@example.com', phone: '0500000000', state: '' };
    r = await fetch('/wp-json/wc/store/v1/checkout', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Nonce: nonce }, body: JSON.stringify({ billing_address: addr, shipping_address: addr, payment_method: 'cod' }) });
    return { status: r.status, body: await r.json() };
  });
  results.checkout = { status: order.status, order_id: order.body.order_id, order_status: order.body.status, redirect: order.body.payment_result && order.body.payment_result.redirect_url };
  if (results.checkout.redirect) {
    await sp.goto(results.checkout.redirect);
    await sp.waitForTimeout(2000);
    results.thank_you_context = await sp.evaluate(() => window.DialogueWoo && window.DialogueWoo.page_type);
    await shot(sp, '07-thank-you');
  }

  // 8. Consent: with the WP Consent API denying statistics, no visitor cookies are set.
  const priv = await browser.newContext();
  const pp = await priv.newPage();
  await pp.addInitScript(() => { window.wp_has_consent = () => false; });
  await pp.goto(`${SITE}/product/classic-hoodie/`);
  await pp.waitForFunction(() => window.__dialogueStarted !== undefined, null, { timeout: 15000 });
  results.consent_denied = await pp.evaluate(() => ({ started: window.__dialogueStarted, cookie_has_dai: /dai_v_h/.test(document.cookie) }));

  save();
  await browser.close();
  console.log('E2E done');
})().catch((e) => {
  results.error = e.stack;
  save();
  console.error(e);
  process.exit(1);
});
