/* Mock Dialogue API for the WooCommerce Docker e2e. Uses the REAL backend modules (compiled to ./lib):
   signature check, order allow-list, WooCommerce client (OAuth1 over http) and the feed mapping. */
const https = require('https');
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const { verifyWoo } = require('./lib/woocommerce.signature');
const { normalizeOrderEvent, normalizeProductsEvent } = require('./lib/woocommerce.order');
const { WooClient } = require('./lib/woocommerce.client');
const { mapProduct } = require('./lib/woocommerce.mapping');
const { defaultsFromSchema, detectedSwatchKey } = require('./lib/woocommerce.schema');
const { approveUrl, sha256, sameToken } = require('./lib/woocommerce.connect');
const CALLBACK = 'https://host.docker.internal:4556/api/woocommerce/auth/callback';
const page = (title, body) => `<!doctype html><html><head><meta charset="utf-8"><title>${title}</title><style>body{font-family:system-ui;background:#f6f6f8;display:flex;justify-content:center;padding:60px}form,div.c{background:#fff;border:1px solid #ddd;border-radius:12px;padding:32px;width:440px}button{background:#7733DE;color:#fff;border:0;border-radius:8px;padding:12px 18px;font-size:16px;width:100%;margin-top:16px}label{display:block;margin:10px 0}input{padding:8px;width:95%}</style></head><body>${body}</body></html>`;

const LOG = path.join(__dirname, '..', 'logs');
const state = { conns: {} };
const log = (name, data) => fs.writeFileSync(path.join(LOG, name), JSON.stringify(data, null, 2));
const line = (msg) => fs.appendFileSync(path.join(LOG, 'mock-requests.log'), `${new Date().toISOString()} ${msg}\n`);
const redact = (o) => JSON.parse(JSON.stringify(o, (k, v) => (/secret|^ck$|^cs$|consumer_key/i.test(k) ? '[redacted]' : v)));
const readJson = (name, def) => (fs.existsSync(path.join(LOG, name)) ? JSON.parse(fs.readFileSync(path.join(LOG, name))) : def);

async function feedPull(conn, tag) {
  const client = new WooClient({ siteUrl: conn.site_url, restUrl: conn.rest_url, consumerKey: conn.ck, consumerSecret: conn.cs, minIntervalMs: 50 });
  const attrs = (await client.get('products/attributes')).data;
  const attributeTaxonomies = {};
  for (const a of attrs) attributeTaxonomies[a.id] = a.slug;
  const schema = await client.plugin('catalog-schema', {}, conn.site_id, conn.secret);
  const swatchResp = await client.plugin('swatches', { taxonomy: 'pa_color' }, conn.site_id, conn.secret);
  const swatches = { pa_color: {} };
  for (const t of swatchResp.terms) swatches.pa_color[t.name.toLowerCase()] = swatches.pa_color[t.slug] = { color: t.color, image: t.image };
  const products = await client.getAll('products', { status: 'publish' }, 50, { 'X-Dialogue-Feed': '1' });
  const rows = [];
  for (const p of products) {
    const variations = p.type === 'variable' ? await client.getAll(`products/${p.id}/variations`, { status: 'publish' }, 100) : [];
    rows.push(...mapProduct(p, variations, { store_id: 4242, lang: 'en', merchant: 'localhost', mapping: conn.mapping || {}, attributeTaxonomies, swatches, hashFeed: (s) => `h_${s}` }).rows);
  }
  log(`feed-rows${tag}.json`, rows);
  log('catalog-schema.json', { schema, defaults: defaultsFromSchema(schema), swatch_hint: detectedSwatchKey(schema, 'attr:pa_color'), swatches: swatchResp });
  line(`FEED${tag} pulled ${products.length} products -> ${rows.length} rows (auth: ${client.isHttps ? 'basic/https' : 'OAuth1/http'})`);
  return rows.length;
}

function body(req) {
  return new Promise((r) => {
    const b = [];
    req.on('data', (c) => b.push(c));
    req.on('end', () => r(Buffer.concat(b).toString('utf8')));
  });
}
const send = (res, code, obj) => {
  res.writeHead(code, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify(obj));
};

async function api(req, res) {
  const raw = await body(req);
  const url = req.url.split('?')[0];
  line(`${req.method} ${url} site=${req.headers['x-dialogue-site'] || '-'}`);
  let json = {};
  try {
    json = raw ? JSON.parse(raw) : {};
  } catch (e) {}
  const signed = () => {
    const conn = state.conns[req.headers['x-dialogue-site']];
    if (!conn || !verifyWoo(conn.secret, conn.site_id, req.headers['x-dialogue-timestamp'], req.headers['x-dialogue-signature'], raw)) {
      line('  -> 401 bad signature');
      send(res, 401, { message: 'Invalid signature.' });
      return null;
    }
    line('  signature OK');
    return conn;
  };
  try {
    if (url === '/api/woocommerce/connect/start') {
      const site_id = 'wc_' + crypto.randomBytes(6).toString('hex');
      const secret = crypto.randomBytes(32).toString('hex');
      const token = crypto.randomBytes(24).toString('hex');
      state.conns[site_id] = { site_id, secret, status: 'pending', claim_token_hash: sha256(token), ...json };
      log('connect-start.json', redact(json));
      return send(res, 200, { site_id, secret, callback_url: CALLBACK, connect_url: `https://host.docker.internal:4556/woocommerce/connect?site=${site_id}&token=${token}` });
    }
    // Stand-in for dashboard.nowdialogue.com/woocommerce/connect (login -> plan -> Stripe Checkout test mode).
    if (url === '/woocommerce/connect' || url === '/woocommerce/login' || url === '/woocommerce/checkout' || url === '/woocommerce/paid') {
      const q = new URL(req.url, 'https://x').searchParams;
      const conn = state.conns[q.get('site')];
      if (!conn || !sameToken(q.get('token'), conn.claim_token_hash)) { res.writeHead(404); return res.end('bad link'); }
      const qs = `site=${conn.site_id}&token=${q.get('token')}`;
      res.writeHead(200, { 'Content-Type': 'text/html' });
      if (url === '/woocommerce/connect') return res.end(page('Log in', `<form method="get" action="/woocommerce/login"><input type="hidden" name="site" value="${conn.site_id}"><input type="hidden" name="token" value="${q.get('token')}"><h2>Log in to Dialogue</h2><label>Email <input name="email" value="merchant@shop.test"></label><label>Password <input type="password" value="x"></label><button id="login">Log in</button><p>New to Dialogue? Create an account</p></form>`));
      if (url === '/woocommerce/login') { conn.user_id = 5; line('  CLAIM by user 5'); return res.end(page('Choose plan', `<form method="get" action="/woocommerce/checkout"><input type="hidden" name="site" value="${conn.site_id}"><input type="hidden" name="token" value="${q.get('token')}"><h2>Choose your plan</h2><label><input type="radio" name="plan" value="301" checked> Growth — $49.00 / month · 14-day free trial</label><button id="pay">Continue to payment</button></form>`)); }
      if (url === '/woocommerce/checkout') return res.end(page('Stripe Checkout (test mode)', `<form method="get" action="/woocommerce/paid"><input type="hidden" name="site" value="${conn.site_id}"><input type="hidden" name="token" value="${q.get('token')}"><h2>Stripe Checkout — TEST MODE</h2><label>Card <input value="4242 4242 4242 4242"></label><button id="subscribe">Start trial</button></form>`));
      conn.billing = { customer: 'cus_test', subscription: 'sub_test', package_type_id: 301 };
      line('  BILLING ok (test mode stand-in)');
      const to = approveUrl(conn.auth_url, conn.site_id, conn.return_url, CALLBACK);
      return res.end(page('Redirecting', `<script>location.href=${JSON.stringify(to)}</script>`));
    }
    if (url === '/api/woocommerce/auth/callback') {
      const conn = state.conns[json.user_id];
      if (!conn || !/^ck_[0-9a-f]{40}$/.test(json.consumer_key)) return send(res, 404, { message: 'Unknown connection.' });
      Object.assign(conn, { ck: json.consumer_key, cs: json.consumer_secret, key_id: json.key_id, key_permissions: json.key_permissions, status: 'authorized' });
      log('auth-callback.json', redact(json));
      return send(res, 200, { success: true });
    }
    if (url === '/api/woocommerce/connect/finalize') {
      const conn = signed();
      if (!conn) return;
      if (!conn.user_id) return send(res, 409, { message: 'Please log in to Dialogue to finish connecting. Click Connect again.' });
      if (!conn.billing) return send(res, 402, { message: 'Please choose a plan in Dialogue to finish connecting. Click Connect again.' });
      const client = new WooClient({ siteUrl: conn.site_url, restUrl: conn.rest_url, consumerKey: conn.ck, consumerSecret: conn.cs, minIntervalMs: 0 });
      await client.get('products', { per_page: 1 });
      line('  keys verified against WooCommerce REST');
      const hs = await client.plugin('handshake', {}, conn.site_id, conn.secret, 'POST', { site_id: conn.site_id });
      log('handshake.json', hs);
      conn.status = 'connected';
      setTimeout(() => feedPull(conn, '').catch((e) => line('FEED ERROR ' + e.message)), 500);
      return send(res, 200, { store_id: 4242, user_id: 7, key_id: conn.key_id, dashboard_url: 'https://dashboard.nowdialogue.com', bundle_url: 'http://localhost:4557/bundle.js' });
    }
    if (url === '/api/woocommerce/connect/status') {
      const c = signed();
      if (!c) return;
      return send(res, 200, { status: c.status, store_id: 4242 });
    }
    if (url === '/api/woocommerce/events/order') {
      const c = signed();
      if (!c) return;
      const all = readJson('order-events.json', []);
      all.push({ received_raw: json, forwarded_to_sqs: { event: 'WOOCOMMERCE_ORDER', store_id: 4242, order: normalizeOrderEvent(json) } });
      log('order-events.json', all);
      return send(res, 200, { success: true });
    }
    if (url === '/api/woocommerce/events/products') {
      const c = signed();
      if (!c) return;
      const all = readJson('product-events.json', []);
      all.push({ at: new Date().toISOString(), received: json, forwarded: normalizeProductsEvent(json) });
      log('product-events.json', all);
      setTimeout(() => feedPull(c, '-after-change').catch((e) => line('FEED ERROR ' + e.message)), 200);
      return send(res, 200, { success: true });
    }
    if (url === '/api/woocommerce/sync' || url === '/api/woocommerce/disconnect') {
      const c = signed();
      if (!c) return;
      if (url.endsWith('disconnect')) c.status = 'disconnected';
      return send(res, 200, { success: true });
    }
    if (url === '/__state') return send(res, 200, redact(state));
    if (url === '/__mapping') {
      for (const c of Object.values(state.conns)) c.mapping = json;
      return send(res, 200, { ok: true });
    }
    if (url === '/__pull') {
      const c = Object.values(state.conns).find((x) => x.status === 'connected');
      return send(res, 200, { rows: await feedPull(c, json.tag || '') });
    }
    send(res, 404, { message: 'not found' });
  } catch (e) {
    line(`  ERROR ${e.message}`);
    send(res, 500, { message: e.message });
  }
}

https.createServer({ key: fs.readFileSync(path.join(__dirname, 'key.pem')), cert: fs.readFileSync(path.join(__dirname, 'cert.pem')) }, api).listen(4556, () => line('mock https :4556'));
http.createServer((req, res) => {
  const f = path.join(__dirname, 'public', path.basename(req.url.split('?')[0]));
  if (fs.existsSync(f)) {
    res.writeHead(200, { 'Content-Type': 'application/javascript', 'Access-Control-Allow-Origin': '*' });
    return res.end(fs.readFileSync(f));
  }
  res.writeHead(404);
  res.end();
}).listen(4557, () => line('mock static :4557'));
