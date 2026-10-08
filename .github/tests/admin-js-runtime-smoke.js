/**
 * Sidrena source file.
 * Author: brendigo
 * Author URI: https://brendigo.com/
 * Plugin URI: https://brendigo.com/sidrene-cijene/
 * Support: sidrena@brendigo.com
 */
'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const listeners = {};
const windowListeners = {};
function classes() {
  const names = new Set();
  return {
    add(name) { names.add(name); },
    remove(name) { names.delete(name); },
    toggle(name, force) {
      if (force) names.add(name);
      else names.delete(name);
    },
    contains(name) { return names.has(name); }
  };
}
function attributes() {
  const values = {};
  return {
    setAttribute(name, value) { values[name] = String(value); },
    getAttribute(name) { return Object.hasOwn(values, name) ? values[name] : null; },
    removeAttribute(name) { delete values[name]; }
  };
}
const submitButton = { disabled: false, ...attributes() };
const form = {
  dataset: {},
  classList: classes(),
  status: null,
  ...attributes(),
  matches(selector) { return selector.includes('.sid-form'); },
  querySelector(selector) {
    return selector === '.sid-form-status' ? this.status : null;
  },
  querySelectorAll(selector) {
    return selector.includes('button[type="submit"]') ? [submitButton] : [];
  },
  appendChild(child) { this.status = child; }
};
const file = {
  classList: classes(),
  files: [],
  name: 'catalog',
  form,
  customError: '',
  ...attributes(),
  matches(selector) { return selector === '.sid-file-input'; },
  setCustomValidity(message) { this.customError = message; },
  get validity() { return {valid: !this.customError}; },
  get validationMessage() { return this.customError; }
};
file.setAttribute('accept', '.csv,text/csv,text/plain');

const document = {
  addEventListener(name, fn) { listeners[name] = fn; },
  createElement(tag) {
    assert.equal(tag, 'p');
    return {classList: classes(), textContent: '', ...attributes()};
  },
  querySelectorAll(selector) {
    if (selector === '.sid-file-input') return [file];
    if (selector.includes('.sid-form')) return [form];
    return [];
  },
  getElementById() { return null; }
};
const window = {
  SidrenaAdmin: {
    fileTooLarge: 'Datoteka je prevelika.',
    invalidFileType: 'Datoteka nije CSV.',
    unsavedChanges: 'Promjene nisu spremljene.',
    savingForm: 'Spremanje…'
  },
  addEventListener(name, fn) { windowListeners[name] = fn; },
  setTimeout(fn) { fn(); }
};
const source = fs.readFileSync(path.join(__dirname, '../../admin/js/admin.js'), 'utf8');
vm.runInNewContext(source, {document, window, Event: class {}, console}, {filename: 'admin.js'});

function emit(name, target) {
  let prevented = false;
  listeners[name]({target, isTrusted: true, preventDefault() { prevented = true; }});
  return prevented;
}

file.files = [{name: 'large.csv', size: 6000000, type: 'text/csv'}];
emit('change', file);
assert.equal(file.getAttribute('aria-invalid'), 'true', 'Oversized CSV remains visually invalid.');
assert.equal(form.status.getAttribute('role'), 'alert', 'Validation error is announced immediately.');
assert.equal(form.status.textContent, 'Datoteka je prevelika.');
assert.equal(form.status.classList.contains('is-error'), true);

file.files = [{name: 'fake.exe', size: 24, type: 'application/octet-stream'}];
emit('input', file);
assert.equal(file.getAttribute('aria-invalid'), 'true', 'Wrong file extension must stay invalid.');
assert.equal(form.status.textContent, 'Datoteka nije CSV.');

file.files = [{name: 'valid.csv', size: 32, type: 'text/csv'}];
emit('change', file);
assert.equal(file.getAttribute('aria-invalid'), null, 'Corrected file clears invalid attributes.');
assert.equal(form.status.getAttribute('role'), 'status');
assert.equal(form.status.classList.contains('is-error'), false);
assert.equal(form.status.textContent, 'Promjene nisu spremljene.');

emit('invalid', file);
assert.equal(file.getAttribute('aria-invalid'), 'true');
assert.equal(form.status.getAttribute('role'), 'alert', 'Native browser invalid event announces error.');
emit('change', file);

assert.equal(emit('submit', form), false, 'Initial submit is accepted.');
assert.equal(form.dataset.sidrenaSubmitting, '1');
assert.equal(form.getAttribute('aria-busy'), 'true');
assert.equal(submitButton.disabled, true);
assert.equal(emit('submit', form), true, 'Second submit is blocked to avoid double writes.');
windowListeners.pageshow();
assert.equal(form.dataset.sidrenaSubmitting, undefined, 'Back-forward restoration unlocks form.');
assert.equal(submitButton.disabled, false);
assert.equal(form.getAttribute('aria-busy'), null);
assert.equal(emit('submit', form), false, 'Submission works again after pageshow.');

console.log('SIDRENA admin JS file validation, alerts, double-submit and pageshow smoke test passed.');
