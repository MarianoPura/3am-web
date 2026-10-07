import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../js/rentals.js', import.meta.url), 'utf8');
const start = source.indexOf('const initRentalServiceRequest =');
const end = source.indexOf('const initRentals =', start);
assert.ok(start >= 0 && end > start, 'Service submit handler missing');
const attributes = new Map();
const button = { disabled: false, dataset: {}, setAttribute: (k, v) => attributes.set(`button:${k}`, v), removeAttribute: k => attributes.delete(`button:${k}`) };
const label = { textContent: 'Submit service request' };
const spinner = { hidden: true };
let valid = false;
let submit;
let pageshow;
const form = {
  querySelector: selector => selector === '[type="submit"]' ? button : selector === '[data-service-submit-label]' ? label : spinner,
  checkValidity: () => valid,
  setAttribute: (k, v) => attributes.set(k, v), removeAttribute: k => attributes.delete(k),
  addEventListener: (name, handler) => { if (name === 'submit') submit = handler; },
};
vm.runInNewContext(source.slice(start, end) + '\ninitRentalServiceRequest();', {
  document: { querySelector: () => form },
  window: { addEventListener: (name, handler) => { if (name === 'pageshow') pageshow = handler; } },
});
const event = () => ({ defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } });
submit(event());
assert.equal(button.disabled, false, 'Invalid forms should not disable submission');
valid = true;
submit(event());
assert.equal(button.disabled, true);
assert.equal(label.textContent, 'SUBMITTING...');
assert.equal(spinner.hidden, false);
assert.equal(attributes.get('aria-busy'), 'true');
assert.equal(attributes.get('button:aria-busy'), 'true');
const repeated = event();
submit(repeated);
assert.equal(repeated.defaultPrevented, true, 'Repeated submit must be blocked');
pageshow({ persisted: true });
assert.equal(button.disabled, false);
assert.equal(label.textContent, 'Submit service request');
assert.equal(spinner.hidden, true);
assert.equal(attributes.has('aria-busy'), false);
console.log('PASS: service submit validity; immediate disable/loading text/decorative spinner/aria-busy; repeated-click prevention; browser-history recovery.');
