import fs from 'node:fs';
import path from 'node:path';
import net from 'node:net';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

// Headless local browser QA. No record forms, cart actions or uploads are submitted.
// Existing fictional credentials stay in memory. External page requests are denied.
const workspace = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const base = new URL(process.env.RENTALS_BROWSER_BASE_URL || 'http://localhost/3am-web/');
if (!['localhost', '127.0.0.1'].includes(base.hostname) || base.protocol !== 'http:') throw new Error('Loopback HTTP URL required.');
const chromePath = process.env.RENTALS_BROWSER_CHROME || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const focused = process.argv.includes('--focused');
const widths = focused ? [390, 1440] : [320, 375, 390, 430, 768, 1024, 1440];
const tmp = path.join(workspace, 'storage', 'tmp');
const useFixtures = process.argv.includes('--fixtures');
const capture = process.argv.includes('--capture');
let fixtureState;
if (useFixtures) {
  try { fixtureState = JSON.parse(fs.readFileSync(path.join(tmp, 'browser-fixture-state.json'), 'utf8')); }
  catch { throw new Error('Prepared browser fixture state is unavailable or invalid.'); }
}
const safeRoute = relative => new URL(relative, base).pathname
  .replace(/\/(order-status|confirmation|account\/reset)\/[^/]+/g, '/$1/[redacted]');
const localUrl = relative => {
  const value = new URL(relative, base);
  if (value.origin !== base.origin || !value.pathname.startsWith(base.pathname)) throw new Error('A browser route is outside the approved local application.');
  return value;
};
const profile = fs.mkdtempSync(path.join(tmp, 'browser-audit-'));
const results = { engine: 'Chrome headless CDP', widths, fixtures: useFixtures, externalRequestsBlocked: 0, pages: [], interactions: [], assets: [], captures: [], failures: [] };
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
const reserve = net.createServer();
await new Promise(resolve => reserve.listen(0, '127.0.0.1', resolve));
const port = reserve.address().port;
await new Promise(resolve => reserve.close(resolve));
let spawnFailure;
const chrome = spawn(chromePath, [
  '--headless=new', '--disable-background-networking', '--disable-sync', '--disable-extensions',
  '--disable-component-update', '--disable-domain-reliability', '--no-first-run', '--no-default-browser-check',
  '--safebrowsing-disable-auto-update', '--remote-debugging-address=127.0.0.1', `--remote-debugging-port=${port}`,
  '--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE localhost, EXCLUDE 127.0.0.1',
  `--user-data-dir=${profile}`, 'about:blank',
], { windowsHide: true, stdio: 'ignore' });
chrome.on('error', error => { spawnFailure = error.code || 'launch error'; });

class CDP {
  constructor(socket) {
    this.socket = socket; this.nextId = 1; this.pending = new Map(); this.handlers = new Map();
    socket.addEventListener('message', ({ data }) => {
      const message = JSON.parse(data);
      if (message.id) {
        const item = this.pending.get(message.id); if (!item) return;
        this.pending.delete(message.id); clearTimeout(item.timeout);
        if (message.error) item.reject(new Error(`CDP ${item.method} failed.`)); else item.resolve(message.result);
      } else (this.handlers.get(message.method) || []).forEach(handler => handler(message.params));
    });
  }
  on(method, handler) { this.handlers.set(method, [...(this.handlers.get(method) || []), handler]); }
  send(method, params = {}) {
    const id = this.nextId++;
    return new Promise((resolve, reject) => {
      const timeout = setTimeout(() => { this.pending.delete(id); reject(new Error(`CDP ${method} timed out.`)); }, 20000);
      this.pending.set(id, { resolve, reject, timeout, method });
      this.socket.send(JSON.stringify({ id, method, params }));
    });
  }
  async evaluate(expression) {
    const data = await this.send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
    if (data.exceptionDetails) throw new Error('Browser evaluation failed; private details withheld.');
    return data.result.value;
  }
  async waitFor(expression, label) {
    for (let n = 0; n < 80; n++) { if (await this.evaluate(expression)) return; await delay(100); }
    throw new Error(`Browser ${label} did not become ready.`);
  }
}

let cdp;
try {
  let targets;
  for (let n = 0; n < 80; n++) {
    if (spawnFailure) throw new Error(`Chrome launch blocked: ${spawnFailure}.`);
    if (chrome.exitCode !== null) throw new Error(`Chrome exited before CDP was ready (code ${chrome.exitCode}).`);
    try { targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); break; } catch { await delay(100); }
  }
  const target = targets?.find(item => item.type === 'page');
  if (!target) throw new Error('Chrome local CDP endpoint unavailable.');
  const socket = new WebSocket(target.webSocketDebuggerUrl);
  await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', () => reject(new Error('CDP socket unavailable.')), { once: true }); });
  cdp = new CDP(socket);
  await cdp.send('Page.enable'); await cdp.send('Runtime.enable'); await cdp.send('Network.enable');
  cdp.on('Fetch.requestPaused', async event => {
    const request = new URL(event.request.url);
    const local = request.origin === base.origin && request.pathname.startsWith(base.pathname);
    const allowedPost = request.pathname === new URL('rentals/account', base).pathname;
    if (local && (event.request.method === 'GET' || event.request.method === 'HEAD' || (event.request.method === 'POST' && allowedPost))) {
      await cdp.send('Fetch.continueRequest', { requestId: event.requestId }).catch(() => {});
    } else {
      results.externalRequestsBlocked++;
      await cdp.send('Fetch.failRequest', { requestId: event.requestId, errorReason: 'BlockedByClient' }).catch(() => {});
    }
  });
  await cdp.send('Fetch.enable', { patterns: [{ urlPattern: 'http://*' }, { urlPattern: 'https://*' }] });
  let responseStatus = 0;
  let mainLoader = '';
  cdp.on('Network.responseReceived', event => { if (event.type === 'Document') responseStatus = event.response.status; });
  cdp.on('Page.frameNavigated', event => { if (!event.frame.parentId) mainLoader = event.frame.loaderId; });
  const navigate = async relative => {
    responseStatus = 0;
    const navigation = await cdp.send('Page.navigate', { url: localUrl(relative).href });
    let ready = false;
    for (let n = 0; n < 80; n++) {
      // A new loader and Document response prevent accepting the previous page's
      // readyState. Approved loopback redirects (empty cart, Analytics) are valid.
      if (responseStatus > 0 && mainLoader === navigation.loaderId && await cdp.evaluate(`location.origin === ${JSON.stringify(base.origin)} && location.pathname.startsWith(${JSON.stringify(base.pathname)}) && document.readyState === 'complete'`)) { ready = true; break; }
      await delay(100);
    }
    if (!ready) throw new Error('Browser navigation did not become ready.');
    await cdp.evaluate('new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))');
  };
  const viewport = width => cdp.send('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: false });
  const measure = async (relative, width, audience) => {
    await viewport(width); await navigate(relative);
    await cdp.evaluate("document.querySelectorAll('.rentals-order__details,.rentals-admin__proof-detail').forEach(detail => { detail.open = true; });");
    await cdp.waitFor("[...document.querySelectorAll('[data-admin-proof-image],[data-service-proof-image]')].every(img => img.complete)", 'proof images');
    const layout = await cdp.evaluate(`(() => {
      const w = document.documentElement.clientWidth;
      const clipped = el => { for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) { if (['auto','scroll','hidden','clip'].includes(getComputedStyle(p).overflowX)) return true; } return false; };
      const overflowing = [...document.body.querySelectorAll('*')].filter(el => {
        // The anti-spam honeypot is deliberately positioned off-screen.
        if (el.closest('.form-hp[aria-hidden="true"]')) return false;
        const r = el.getBoundingClientRect(); const style = getComputedStyle(el);
        return r.width > 0 && r.height > 0 && style.visibility !== 'hidden' && !clipped(el) && (r.right > w + 1 || r.left < -1);
      }).slice(0, 6).map(el => ({ tag: el.tagName, class: String(el.className).slice(0,150), left: Math.round(el.getBoundingClientRect().left), right: Math.round(el.getBoundingClientRect().right) }));
      const wrappers = [...document.querySelectorAll('.rentals-order__table-wrap,.rentals-admin__table-wrap')];
      const proofs = [...document.querySelectorAll('[data-admin-proof-image],[data-service-proof-image]')];
      return { finalRoute: location.pathname, redirected: location.pathname !== ${JSON.stringify(new URL(relative, base).pathname)}, clientWidth: w, scrollWidth: document.documentElement.scrollWidth, overflowing,
        tableRegions: wrappers.length, accessibleTableRegions: wrappers.every(el => el.tabIndex === 0 && el.getAttribute('role') === 'region' && Boolean(el.getAttribute('aria-label'))),
        proofImageCount: proofs.length, proofImagesLoaded: proofs.every(img => img.naturalWidth > 0),
        hasMain: Boolean(document.querySelector('main')), adminLayout: document.body.classList.contains('rentals-admin-module') };
    })()`);
    const expectedStates = {
      checkout: '[data-rental-checkout]',
      customer_service_payment: '[data-service-payment]',
      admin_equipment_order: '.rentals-admin__proof-actions',
      admin_service_review: '[name=payment_message]',
    };
    layout.expectedFormRendered = true;
    if (useFixtures) {
      for (const [key, selector] of Object.entries(expectedStates)) {
        if (fixtureState.routes?.[key] && localUrl(relative).pathname === localUrl(fixtureState.routes[key]).pathname) {
          layout.expectedFormRendered = await cdp.evaluate(`Boolean(document.querySelector(${JSON.stringify(selector)}))`);
        }
      }
    }
    const passed = responseStatus === 200 && layout.hasMain && layout.scrollWidth <= layout.clientWidth + 1 && layout.overflowing.length === 0 && layout.accessibleTableRegions && layout.proofImagesLoaded && layout.expectedFormRendered && (audience !== 'admin' || layout.adminLayout);
    layout.finalRoute = safeRoute(layout.finalRoute);
    const entry = { route: safeRoute(relative), width, audience, status: responseStatus, ...layout, passed };
    results.pages.push(entry);
    if (!passed) results.failures.push({ kind: 'layout', ...entry });
  };
  const captureElement = async (selector, name) => {
    if (!capture) return;
    await cdp.evaluate(`(() => {
      const el = document.querySelector(${JSON.stringify(selector)});
      if (el instanceof HTMLDialogElement) el.scrollTop = 0; else el.scrollIntoView({ block: 'start' });
    })()`);
    // Viewport captures preserve responsive layout; beyond-viewport clipping
    // can temporarily resize it and make a dialog look cropped.
    const shot = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    const nameInTmp = `rentals-browser-${name}.png`;
    fs.writeFileSync(path.join(tmp, nameInTmp), Buffer.from(shot.data, 'base64'));
    results.captures.push(nameInTmp);
  };
  const verifyProof = async relative => {
    if (!relative) return;
    const proof = await cdp.evaluate(`(async () => { const response = await fetch(${JSON.stringify(localUrl(relative).href)}, { credentials: 'same-origin' }); return { status: response.status, contentType: response.headers.get('content-type') || '', bytes: (await response.arrayBuffer()).byteLength }; })()`);
    const entry = { kind: 'protected-proof-read', route: safeRoute(relative), ...proof, passed: proof.status === 200 && proof.bytes > 0 && /^(image\/|application\/pdf)/.test(proof.contentType) };
    results.assets.push(entry); if (!entry.passed) results.failures.push(entry);
  };
  const publicRoutes = ['', 'start', 'start?type=media', 'start?type=technology', 'start?type=rentals', 'start?type=other', 'start/received', 'rentals', 'rentals/items', 'rentals/items?type=services', 'rentals/services', 'rentals/categories', 'rentals/account', 'rentals/account?mode=register', 'rentals/account/forgot', 'rentals/how-to-rent', 'rentals/support', 'rentals/cart'];
  for (const relative of publicRoutes) for (const width of widths) await measure(relative, width, 'guest');
  for (const width of widths) {
    await viewport(width); await navigate('start?type=rentals');
    const enquiry = await cdp.evaluate(`(() => {
      const radios = [...document.querySelectorAll('.project-choice input[type=radio]')];
      const labels = radios.map(input => input.value), preset = radios.find(input => input.checked)?.value;
      const selectable = radios.every(input => { input.click(); return input.checked; });
      const fields = ['name', 'email', 'phone', 'details'].every(name => document.querySelector('[name=' + name + ']')?.required);
      return { options: labels, correctPreset: preset === 'Equipment Rentals', allSelectable: selectable, requiredFields: fields };
    })()`);
    const expected = ['Media / Production', 'Technology / Event Systems', 'Equipment Rentals', 'Other / General Inquiry'];
    const passed = JSON.stringify(enquiry.options) === JSON.stringify(expected) && enquiry.correctPreset && enquiry.allSelectable && enquiry.requiredFields;
    const entry = { kind: 'project-enquiry-options', viewportWidth: width, ...enquiry, passed };
    results.interactions.push(entry); if (!passed) results.failures.push(entry);
    if (width === 390) await captureElement('.form-page', 'start-project-390');
    await navigate('rentals');
    const support = await cdp.evaluate(`(() => { const link = [...document.querySelectorAll('a')].find(a => a.textContent.trim() === 'Contact Rental Support'); return link ? new URL(link.href).pathname + new URL(link.href).search : null; })()`);
    const expectedSupport = localUrl('start?type=rentals');
    const supportEntry = { kind: 'rental-support-enquiry-link', viewportWidth: width, passed: support === expectedSupport.pathname + expectedSupport.search };
    results.interactions.push(supportEntry); if (!supportEntry.passed) results.failures.push(supportEntry);
  }
  console.log(`Guest layouts checked: ${results.pages.length}; failures: ${results.failures.length}.`);

  const privateText = fs.readFileSync(path.join(tmp, useFixtures ? 'browser-fixture-accounts.txt' : 'home-pc-accounts.txt'), 'utf8');
  const account = role => {
    const match = privateText.match(new RegExp(`(?:^|\\n)${role}:\\s*([^\\r\\n]+)\\r?\\nPassword:\\s*([^\\r\\n]+)`));
    if (!match) throw new Error('Fictional local login details unavailable.');
    return { email: match[1].trim(), password: match[2].trim() };
  };
  const login = async role => {
    await cdp.send('Network.clearBrowserCookies'); await viewport(1024); await navigate('rentals/account');
    const credentials = account(role);
    await cdp.evaluate(`(() => { const form = document.querySelector('.rentals-account__form'); form.elements.email.value = ${JSON.stringify(credentials.email)}; form.elements.password.value = ${JSON.stringify(credentials.password)}; form.requestSubmit(); })()`);
    await cdp.waitFor("!document.querySelector('.rentals-account--guest') && document.readyState === 'complete'", 'local login');
  };
  await login('customer');
  const customerRoutes = ['rentals/account', 'rentals/account/settings', 'rentals/orders', 'rentals/service-requests', 'rentals/checkout'];
  await navigate('rentals/items?type=services');
  const serviceRequestPath = await cdp.evaluate("(() => { const a = document.querySelector('a[href*=\"/request\"]'); return a ? new URL(a.href).pathname : null; })()");
  if (useFixtures) {
    for (const key of ['customer_orders', 'customer_order_status', 'customer_receipt', 'customer_service_payment', 'customer_service_payment_review', 'customer_service_request_form', 'cart', 'checkout']) {
      if (fixtureState.routes?.[key]) customerRoutes.push(fixtureState.routes[key]);
    }
  } else if (serviceRequestPath) customerRoutes.push(serviceRequestPath.slice(base.pathname.length));
  for (const relative of [...new Set(customerRoutes.map(value => localUrl(value).href))]) for (const width of widths) await measure(relative, width, 'customer');
  if (useFixtures) {
    await verifyProof(fixtureState.routes?.customer_equipment_proof);
    await verifyProof(fixtureState.routes?.customer_service_proof);
  }
  for (const width of widths) {
    await viewport(width); await navigate(useFixtures ? fixtureState.routes.equipment_catalogue : 'rentals/items');
    const available = await cdp.evaluate("Boolean(document.querySelector('[data-rentals-detail]'))");
    if (!available) { results.interactions.push({ width, kind: 'equipment-modal', passed: false, reason: 'No equipment available for modal inspection.' }); continue; }
    await cdp.evaluate("document.querySelector('[data-rentals-detail]').click(); document.querySelector('[data-date-trigger]').click();");
    await cdp.waitFor("document.querySelector('[data-calendar-days]').getAttribute('aria-busy') !== 'true' && document.querySelectorAll('[data-calendar-days] button').length > 0", 'calendar');
    if (width === 390 || width === 1440) await captureElement('[data-rentals-dialog]', `equipment-modal-${width}`);
    const modal = await cdp.evaluate(`(() => {
      const d = document.querySelector('[data-rentals-dialog]'), close = d.querySelector('[data-rentals-close]'), visual = d.querySelector('.rentals-detail__visual'), image = visual.querySelector('img'), calendar = d.querySelector('[data-availability-calendar]');
      const r = d.getBoundingClientRect(); d.scrollTop = d.scrollHeight;
      const c = close.getBoundingClientRect(); const v = visual.getBoundingClientRect();
      const days = [...calendar.querySelectorAll('button:not(:disabled)')];
      const body = d.querySelector('.rentals-detail__body').getBoundingClientRect();
      return { modalWidth: Math.round(r.width), modalHeight: Math.round(r.height), scrollWidth: d.scrollWidth, clientWidth: d.clientWidth, visualWidth: Math.round(v.width), visualHeight: Math.round(v.height), imageObjectFit: getComputedStyle(image).objectFit, closeVisibleWhenScrolled: c.top >= r.top && c.bottom <= r.bottom, calendarVisible: !calendar.hidden, selectableDays: days.length, dateColumns: getComputedStyle(d.querySelector('.rentals-detail__dates')).gridTemplateColumns.split(' ').length, contentWithinDialog: body.left >= r.left && body.right <= r.right + 1 };
    })()`);
    if (modal.selectableDays) {
      await cdp.evaluate("document.querySelector('[data-calendar-days] button:not(:disabled)').click()");
      await cdp.waitFor("document.querySelector('[data-calendar-days]').getAttribute('aria-busy') !== 'true'", 'first date');
      await cdp.evaluate("document.querySelector('[data-calendar-days] button:not(:disabled)').click()");
      await cdp.waitFor("Boolean(document.querySelector('[name=rental_end_date]').value) && document.querySelector('[data-calendar-days]').getAttribute('aria-busy') !== 'true'", 'second date');
      modal.selectedDatesKeptVisible = await cdp.evaluate("!document.querySelector('[data-availability-calendar]').hidden && document.querySelectorAll('[data-calendar-days] .is-selected').length > 0");
    }
    modal.quantityRespectsAvailability = await cdp.evaluate(`(() => {
      const input = document.querySelector('[data-rentals-dialog] [name=quantity]');
      if (input.disabled) return true;
      const limit = Number(input.max); input.value = String(limit + 99); input.dispatchEvent(new Event('input', { bubbles: true }));
      return Number(input.value) <= limit && Number(input.value) >= 1;
    })()`);
    if (useFixtures) {
      const galleryReady = await cdp.evaluate("!document.querySelector('[data-gallery-controls]').hidden");
      if (galleryReady) {
        await cdp.evaluate("document.querySelector('[data-rentals-dialog]').scrollTop = 0; document.querySelector('[data-gallery-next]').click()");
        const moved = await cdp.evaluate("document.querySelector('[data-gallery-count]').textContent.trim().startsWith('2 /')");
        await cdp.evaluate("document.querySelector('[data-gallery-prev]').click()");
        modal.galleryArrowsWork = moved && await cdp.evaluate("document.querySelector('[data-gallery-count]').textContent.trim().startsWith('1 /')");
      } else modal.galleryArrowsWork = false;
    }
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
    try {
      // Native dialog close/focus restoration dispatches a queued close event.
      await cdp.waitFor("!document.querySelector('[data-rentals-dialog]').open && document.activeElement.matches('[data-rentals-detail]')", 'modal close/focus restoration');
      modal.escapeClosedAndFocusRestored = true;
    } catch { modal.escapeClosedAndFocusRestored = false; }
    const passed = modal.contentWithinDialog && modal.scrollWidth <= modal.clientWidth + 1 && modal.modalWidth <= width && modal.imageObjectFit === 'contain' && modal.calendarVisible && modal.quantityRespectsAvailability && modal.escapeClosedAndFocusRestored && (width > 780 || modal.closeVisibleWhenScrolled) && (width > 560 || modal.dateColumns === 1) && (!modal.selectableDays || modal.selectedDatesKeptVisible) && (!useFixtures || modal.galleryArrowsWork);
    const entry = { kind: 'equipment-modal', viewportWidth: width, ...modal, passed }; results.interactions.push(entry); if (!passed) results.failures.push(entry);
    if (width <= 768) {
      await cdp.evaluate("document.querySelector('[data-rentals-nav-toggle]').click()");
      const open = await cdp.evaluate("document.querySelector('[data-rentals-nav-toggle]').getAttribute('aria-expanded') === 'true' && getComputedStyle(document.querySelector('#rentals-primary-nav')).display !== 'none'");
      await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
      await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
      const closed = await cdp.evaluate("document.querySelector('[data-rentals-nav-toggle]').getAttribute('aria-expanded') === 'false' && document.activeElement.matches('[data-rentals-nav-toggle]')");
      const entry = { kind: 'mobile-navigation', viewportWidth: width, passed: open && closed }; results.interactions.push(entry); if (!entry.passed) results.failures.push(entry);
    }
  }
  console.log(`Customer layouts and modal/navigation checked; total layout checks: ${results.pages.length}.`);

  await login('admin');
  const adminRoutes = ['rentals/admin', 'rentals/admin/orders', 'rentals/admin/service-requests', 'rentals/admin/items', 'rentals/admin/items/new', 'rentals/admin/categories', 'rentals/admin/categories/new', 'rentals/admin/sales-report', 'rentals/admin/payment-report', 'rentals/admin/analytics', 'rentals/admin/payments', 'rentals/admin/payments/new', 'rentals/admin/customers'];
  for (const [list, marker] of [['items', '/edit'], ['categories', '/edit'], ['payments', '/edit']]) {
    await navigate(`rentals/admin/${list}`);
    const edit = await cdp.evaluate(`(() => { const a = document.querySelector('.rentals-admin__table a[href$=${JSON.stringify(marker)}]'); return a ? new URL(a.href).pathname : null; })()`);
    if (edit) adminRoutes.push(edit.slice(base.pathname.length));
  }
  await navigate('rentals/admin/items');
  const equipmentEdit = await cdp.evaluate("(() => { const a = document.querySelector('.rentals-admin__table a[href*=\"#equipment-availability\"]'); return a ? new URL(a.href).pathname : null; })()");
  if (equipmentEdit) adminRoutes.push(equipmentEdit.slice(base.pathname.length));
  if (useFixtures) {
    for (const key of ['admin_equipment_order', 'admin_service_review', 'admin_item_edit', 'admin_category_edit', 'admin_payment_edit']) {
      if (fixtureState.routes?.[key]) adminRoutes.push(fixtureState.routes[key]);
    }
  }
  for (const relative of [...new Set(adminRoutes.map(value => localUrl(value).href))]) for (const width of widths) await measure(relative, width, 'admin');
  if (useFixtures) {
    await verifyProof(fixtureState.routes?.admin_equipment_proof);
    await verifyProof(fixtureState.routes?.admin_service_proof);
  }
  if (equipmentEdit || fixtureState?.routes?.admin_item_edit) {
    for (const width of widths) {
      await viewport(width); await navigate(fixtureState?.routes?.admin_item_edit || equipmentEdit);
      await cdp.evaluate("document.querySelector('[data-admin-availability]').open = true");
      await cdp.waitFor("document.querySelectorAll('[data-admin-days] button').length > 0", 'Admin availability calendar');
      const calendar = await cdp.evaluate(`(() => {
        const el = document.querySelector('.rentals-admin-calendar'), r = el.getBoundingClientRect();
        return { width: Math.round(r.width), scrollWidth: el.scrollWidth, clientWidth: el.clientWidth, columns: getComputedStyle(el.querySelector('.rentals-admin-calendar__days')).gridTemplateColumns.split(' ').length, dayCount: el.querySelectorAll('[data-admin-days] button').length };
      })()`);
      const entry = { kind: 'admin-availability-calendar', viewportWidth: width, ...calendar, passed: calendar.scrollWidth <= calendar.clientWidth + 1 && calendar.columns === 7 && calendar.dayCount >= 28 };
      results.interactions.push(entry); if (!entry.passed) results.failures.push(entry);
    }
  }
  for (const width of widths) {
    await viewport(width); await navigate('rentals/admin/items');
    if (width === 390) await captureElement('.rentals-admin__list', 'admin-products-390');
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
    const table = await cdp.evaluate(`(() => {
      const wrapper = document.querySelector('.rentals-admin__table-wrap'); wrapper.scrollLeft = 0; wrapper.focus(); wrapper.scrollIntoView({ block: 'center' });
      return { needsScroll: wrapper.scrollWidth > wrapper.clientWidth, focused: document.activeElement === wrapper, focusOutlineWidth: parseFloat(getComputedStyle(wrapper).outlineWidth) };
    })()`);
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: 'ArrowRight', code: 'ArrowRight', windowsVirtualKeyCode: 39 });
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: 'ArrowRight', code: 'ArrowRight', windowsVirtualKeyCode: 39 });
    if (table.needsScroll) {
      try { await cdp.waitFor("document.querySelector('.rentals-admin__table-wrap').scrollLeft > 0", 'table keyboard scrolling'); table.scrolledByKeyboard = true; }
      catch { table.scrolledByKeyboard = false; }
    }
    const entry = { kind: 'keyboard-table', viewportWidth: width, ...table, passed: table.focused && table.focusOutlineWidth >= 2 && (!table.needsScroll || table.scrolledByKeyboard) };
    results.interactions.push(entry); if (!entry.passed) results.failures.push(entry);
  }
  console.log(`PASS/FAIL summary: ${results.pages.filter(row => row.passed).length}/${results.pages.length} layouts; ${results.interactions.filter(row => row.passed).length}/${results.interactions.length} interactions; ${results.assets.filter(row => row.passed).length}/${results.assets.length} protected assets; ${results.failures.length} failures.`);
  fs.writeFileSync(path.join(tmp, focused ? 'rentals-browser-focused.json' : 'rentals-browser-audit.json'), JSON.stringify(results, null, 2));
  for (const failure of results.failures) console.log(JSON.stringify(failure));
  if (results.failures.length) process.exitCode = 1;
} catch (error) {
  // Known diagnostics only; raw browser/source/account content must never be printed.
  console.error(`Browser audit stopped: ${error.message}`);
  fs.writeFileSync(path.join(tmp, focused ? 'rentals-browser-focused.json' : 'rentals-browser-audit.json'), JSON.stringify(results, null, 2));
  process.exitCode = 1;
} finally {
  if (cdp) { try { await cdp.send('Browser.close'); } catch {} cdp.socket.close(); }
  chrome.kill();
  await delay(500);
  const checkedProfile = path.resolve(profile);
  if (checkedProfile.startsWith(path.resolve(tmp) + path.sep) && path.basename(checkedProfile).startsWith('browser-audit-')) {
    try { fs.rmSync(checkedProfile, { recursive: true, force: true }); } catch { /* Chrome may still release temporary files. */ }
  }
}
