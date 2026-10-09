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
      state.conns[site_id] = { site_id, secret, status: 'pending', ...json };
      log('connect-start.json', redact(json));
      return send(res, 200, { site_id, secret, callback_url: 'https://host.docker.internal:4556/api/woocommerce/auth/callback' });
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
