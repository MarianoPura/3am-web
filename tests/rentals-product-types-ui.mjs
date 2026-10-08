import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../js/rentals-admin.js', import.meta.url), 'utf8');
const start = source.indexOf("document.querySelectorAll('[data-rental-product-form]')");
const end = source.indexOf("document.querySelectorAll('[data-service-proof-image]')", start);
assert.ok(start >= 0 && end > start);
const handler = source.slice(start, end);
const fixture = (initialType = '0', initialCategory = '', serviceCategories = true, locked = false) => {
  const listeners = {};
  const type = { value: initialType, disabled: locked, addEventListener: (name, fn) => { listeners.type = fn; } };
  const options = [{ value: '', dataset: {} }, { value: 'equipment', dataset: { isService: '0' } }];
  if (serviceCategories) options.push({ value: 'service', dataset: { isService: '1' } });
  const category = {
    value: initialCategory, options,
    get selectedOptions() { return options.filter(option => option.value === this.value); },
    addEventListener: (name, fn) => { listeners.category = fn; },
  };
  const quantity = { disabled: false };
  const equipment = { hidden: false, querySelectorAll: () => [quantity] };
  const service = { hidden: true };
  const label = { textContent: '', dataset: { equipmentText: 'Equipment details', serviceText: 'Service scope' } };
  const input = { placeholder: '', dataset: { equipmentPlaceholder: 'Camera', servicePlaceholder: 'Crew' } };
  const heading = { textContent: '' };
  const form = {
    querySelector: selector => selector === '[data-rental-type]' ? type : category,
    querySelectorAll: selector => ({ '[data-product-label]': [label], '[data-product-placeholder]': [input],
      '[data-service-field]': [service], '[data-equipment-field]': [equipment] })[selector] ?? [],
    closest: () => ({ querySelector: () => heading }),
  };
  vm.runInNewContext(handler, { document: { querySelectorAll: () => [form] } });
  return { type, category, options, listeners, quantity, equipment, service, label, input, heading };
};

const f = fixture('0', 'equipment');
assert.equal(f.options[2].disabled, true);
assert.equal(f.options[2].hidden, true);
assert.equal(f.quantity.disabled, false);
f.type.value = '1'; f.listeners.type();
assert.equal(f.category.value, '', 'Changing type must discard an incompatible category.');
assert.equal(f.options[1].disabled, true);
assert.equal(f.options[2].disabled, false);
assert.equal(f.equipment.hidden, true);
assert.equal(f.quantity.disabled, true);
assert.equal(f.service.hidden, false);
assert.equal(f.heading.textContent, 'Add service');
assert.equal(f.label.textContent, 'Service scope');
assert.equal(f.input.placeholder, 'Crew');
f.category.value = 'service'; f.listeners.category();
assert.equal(f.type.value, '1');
f.type.value = '0'; f.listeners.type();
assert.equal(f.category.value, '');
assert.equal(f.quantity.disabled, false);
assert.equal(f.service.hidden, true);
assert.equal(f.heading.textContent, 'Add equipment');
const draft = fixture('1', 'service');
assert.equal(draft.category.value, 'service', 'A valid service draft must survive initialization.');
const edit = fixture('1', 'service', true, true);
assert.equal(edit.type.disabled, true);
assert.equal(edit.options[1].disabled, true);
const empty = fixture('1', '', false);
assert.equal(empty.category.value, '');
assert.equal(empty.options.filter(option => !option.disabled).length, 1, 'Only the required placeholder is available without matching categories.');
console.log('PASS: Equipment/Service selection, matching categories, stale selection clearing, field visibility/disabled state, labels, drafts, locked edits and absent service categories.');
