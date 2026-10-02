import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const source = readFileSync(new URL('../js/rentals.js', import.meta.url), 'utf8');
const init = source.slice(source.indexOf('const initRentalCheckout ='), source.indexOf('const initRentalHeader ='));
const setup = ({ valid = true, disabled = false } = {}) => {
  const attrs = new Map();
  const label = { textContent: 'Submit rental request' };
  const spinner = { hidden: true };
  const button = {
    disabled, dataset: {}, attributes: new Map(),
    querySelector: selector => selector.includes('label') ? label : spinner,
    setAttribute(name, value) { this.attributes.set(name, value); },
    removeAttribute(name) { this.attributes.delete(name); }
  };
  const events = new Map(), windowEvents = new Map();
  const form = {
    elements: {},
    querySelectorAll: () => [],
    querySelector: () => button,
    checkValidity: () => valid,
    addEventListener: (name, handler) => events.set(name, handler),
    setAttribute: (name, value) => attrs.set(name, value),
    removeAttribute: name => attrs.delete(name)
  };
  const context = vm.createContext({
    document: { querySelector: selector => selector.includes('checkout') ? form : null, querySelectorAll: () => [] },
    window: { addEventListener: (name, handler) => windowEvents.set(name, handler) }
  });
  vm.runInContext(init + '\ninitRentalCheckout();', context);
  const submit = () => {
    const event = { defaultPrevented: false, preventDefault() { this.defaultPrevented = true; } };
    events.get('submit')(event); return event;
  };
  return { submit, button, label, spinner, attrs, windowEvents };
};

const ui = setup();
assert.equal(ui.submit().defaultPrevented, false, 'First submit must preserve native POST.');
assert.equal(ui.button.disabled, true, 'Button must disable immediately.');
assert.equal(ui.label.textContent, 'SUBMITTING...');
assert.equal(ui.spinner.hidden, false);
assert.equal(ui.attrs.get('aria-busy'), 'true');
assert.equal(ui.button.attributes.get('aria-busy'), 'true');
assert.equal(ui.submit().defaultPrevented, true, 'Second submit must be stopped.');
ui.windowEvents.get('pageshow')({ persisted: false });
assert.equal(ui.button.disabled, true, 'Keep disabled through navigation.');
ui.windowEvents.get('pageshow')({ persisted: true });
assert.equal(ui.button.disabled, false, 'History return must restore usable form.');
assert.equal(ui.spinner.hidden, true);
assert.equal(ui.label.textContent, 'Submit rental request');
assert.equal(ui.attrs.has('aria-busy'), false);
assert.equal(setup({ valid: false }).submit().defaultPrevented, false);
const invalid = setup({ valid: false }); invalid.submit();
assert.equal(invalid.button.disabled, false);
assert.equal(invalid.spinner.hidden, true, 'Browser validation must not leave spinner stuck.');
const unavailable = setup({ disabled: true }); unavailable.submit();
unavailable.windowEvents.get('pageshow')({ persisted: true });
assert.equal(unavailable.button.disabled, true, 'Do not enable an unavailable checkout.');
console.log('PASS: immediate disable/spinner/loading text/aria-busy, repeated submit prevention, native POST retained, navigation/history recovery, browser validation and unavailable-state protection.');
