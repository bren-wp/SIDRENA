/**
 * Sidrena source file.
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * Plugin URI: https://brendigo.com/sidrene-cijene/
 * Support: sidrena@brendigo.com
 */

(function () {
	'use strict';

	var cfg = window.SidrenaAdmin || {};
	var locationCounter = 0;
	var managedFormSelector = '.sid-form, .sid-bulk-card, .sid-standalone-form, .sid-standalone-import';

	function closest(element, selector) {
		return element && element.closest ? element.closest(selector) : null;
	}

	function message(key, fallback) {
		return typeof cfg[key] === 'string' && cfg[key] ? cfg[key] : fallback;
	}

	function ensureFormStatus(form) {
		if (!form) {
			return null;
		}
		var status = form.querySelector('.sid-form-status');
		if (!status) {
			status = document.createElement('p');
			status.className = 'sid-form-status';
			status.setAttribute('role', 'status');
			status.setAttribute('aria-live', 'polite');
			form.appendChild(status);
		}
		return status;
	}

	function setFormStatus(form, text) {
		var status = ensureFormStatus(form);
		if (status) {
			status.textContent = text || '';
		}
	}

	function resetSubmittingState(form) {
		if (!form) {
			return;
		}
		delete form.dataset.sidrenaSubmitting;
		form.classList.remove('is-submitting');
		form.removeAttribute('aria-busy');
		form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
			button.disabled = false;
			button.removeAttribute('aria-disabled');
		});
		setFormStatus(form, '');
	}

	function clearInvalidState(field) {
		if (!field || !field.classList) {
			return;
		}
		field.classList.remove('is-invalid');
		field.removeAttribute('aria-invalid');
	}

	function isValidOib(value) {
		var oib = String(value || '').replace(/\D+/g, '');
		if (oib.length !== 11) {
			return false;
		}
		var a = 10;
		for (var i = 0; i < 10; i += 1) {
			a = (a + Number(oib.charAt(i))) % 10;
			if (a === 0) {
				a = 10;
			}
			a = (2 * a) % 11;
		}
		var control = 11 - a;
		if (control === 10) {
			control = 0;
		}
		return control === Number(oib.charAt(10));
	}

	function validateOibField(field) {
		if (!field || !field.matches || !field.matches('[data-sidrena-oib]')) {
			return;
		}
		var value = String(field.value || '').trim();
		if (!value) {
			field.setCustomValidity('');
			return;
		}
		field.setCustomValidity(isValidOib(value) ? '' : message('invalidOib', 'Unesite valjani OIB s 11 znamenki i ispravnom kontrolnom znamenkom.'));
	}

	function setLocationState(row) {
		if (!row) {
			return;
		}
		var enabled = row.querySelector('.sid-location-enabled');
		var active = !enabled || enabled.checked;
		row.classList.toggle('is-inactive', !active);
		row.querySelectorAll('[data-required-when-active]').forEach(function (field) {
			field.required = active;
			field.setAttribute('aria-required', active ? 'true' : 'false');
		});
	}

	function initLocations() {
		document.querySelectorAll('.sid-location').forEach(setLocationState);
	}

	function nextLocationKey() {
		locationCounter += 1;
		return 'new-' + Date.now().toString(36) + '-' + locationCounter.toString(36);
	}

	function focusFirstField(root) {
		var field = root ? root.querySelector('input[type="text"], input[type="number"], select, textarea') : null;
		if (field) {
			field.focus();
		}
	}

	function syncStandaloneEmptyState(wrap) {
		if (!wrap) {
			return;
		}
		var empty = wrap.querySelector('.sid-catalog-empty-row');
		if (empty) {
			empty.hidden = !!wrap.querySelector('.sidrena-standalone-row');
		}
	}

	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!target) {
			return;
		}

		var addButton = closest(target, '#sid-add-location');
		if (addButton) {
			event.preventDefault();
			var wrap = document.getElementById('sid-locations');
			var template = document.getElementById('sid-location-template');
			if (!wrap || !template) {
				return;
			}
			var html = template.innerHTML.split('__INDEX__').join(nextLocationKey());
			wrap.insertAdjacentHTML('beforeend', html);
			var rows = wrap.querySelectorAll('.sid-location');
			var last = rows.length ? rows[rows.length - 1] : null;
			setLocationState(last);
			focusFirstField(last);
			return;
		}

		var addStandalone = closest(target, '#sidrena-add-standalone');
		if (addStandalone) {
			event.preventDefault();
			var standaloneWrap = document.getElementById('sidrena-standalone-rows');
			var standaloneTemplate = document.getElementById('sidrena-standalone-template');
			if (!standaloneWrap || !standaloneTemplate) {
				return;
			}
			var key = 'new-' + Date.now().toString(36) + '-' + standaloneWrap.querySelectorAll('.sidrena-standalone-row').length;
			var standaloneHtml = standaloneTemplate.innerHTML.split('__KEY__').join(key);
			standaloneWrap.insertAdjacentHTML('beforeend', standaloneHtml);
			syncStandaloneEmptyState(standaloneWrap);
			var standaloneRows = standaloneWrap.querySelectorAll('.sidrena-standalone-row');
			focusFirstField(standaloneRows.length ? standaloneRows[standaloneRows.length - 1] : null);
			return;
		}

		var removeStandalone = closest(target, '.sidrena-remove-standalone');
		if (removeStandalone) {
			event.preventDefault();
			var standaloneRow = closest(removeStandalone, '.sidrena-standalone-row');
			if (standaloneRow && window.confirm(message('removeUnsavedProduct', 'Ukloniti ovaj nespremljeni proizvod?'))) {
				standaloneRow.remove();
				syncStandaloneEmptyState(document.getElementById('sidrena-standalone-rows'));
				var addStandaloneButton = document.getElementById('sidrena-add-standalone');
				if (addStandaloneButton) {
					addStandaloneButton.focus();
				}
			}
			return;
		}

		var removeButton = closest(target, '.sid-remove-location');
		if (removeButton) {
			event.preventDefault();
			var wrapLocations = document.getElementById('sid-locations');
			var row = closest(removeButton, '.sid-location');
			var locationRows = wrapLocations ? wrapLocations.querySelectorAll('.sid-location') : [];
			if (locationRows.length <= 1) {
				window.alert(message('keepOneLocation', 'Mora ostati barem jedna lokacija. Možete je isključiti ako je trenutačno ne želite objavljivati.'));
				return;
			}
			if (row && window.confirm(message('removeLocation', 'Ukloniti ovu lokaciju iz konfiguracije?'))) {
				row.remove();
				var addLocationButton = document.getElementById('sid-add-location');
				if (addLocationButton) {
					addLocationButton.focus();
				}
			}
		}
	});

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (!target) {
			return;
		}
		validateOibField(target);
		clearInvalidState(target);
		var changedForm = target.form;
		if (changedForm && changedForm.matches && changedForm.matches(managedFormSelector)) {
			setFormStatus(changedForm, '');
		}
		if (target.matches && target.matches('.sid-location-enabled')) {
			setLocationState(closest(target, '.sid-location'));
			return;
		}
		if (target.matches && target.matches('.sid-inline-delete input[type="checkbox"]') && target.checked) {
			if (!window.confirm(message('deleteProduct', 'Označiti ovaj proizvod za brisanje nakon spremanja?'))) {
				target.checked = false;
			}
		}
	});

	document.addEventListener('submit', function (event) {
		var form = event.target;
		if (!form || !form.matches || !form.matches(managedFormSelector)) {
			return;
		}
		if (form.dataset.sidrenaSubmitting === '1') {
			event.preventDefault();
			return;
		}
		form.dataset.sidrenaSubmitting = '1';
		form.classList.add('is-submitting');
		form.setAttribute('aria-busy', 'true');
		setFormStatus(form, message('savingForm', 'Spremanje…'));
		window.setTimeout(function () {
			form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
				button.disabled = true;
				button.setAttribute('aria-disabled', 'true');
			});
		}, 0);
	});

	document.addEventListener('invalid', function (event) {
		var field = event.target;
		var form = field && field.form;
		if (!form || !form.matches || !form.matches(managedFormSelector)) {
			return;
		}
		field.classList.add('is-invalid');
		field.setAttribute('aria-invalid', 'true');
		setFormStatus(form, field.validationMessage || message('invalidField', 'Provjerite označeno polje i pokušajte ponovno.'));
	}, true);

	document.addEventListener('input', function (event) {
		validateOibField(event.target);
		clearInvalidState(event.target);
		var form = event.target && event.target.form;
		if (form && form.matches && form.matches(managedFormSelector)) {
			setFormStatus(form, '');
		}
	});

	window.addEventListener('pageshow', function () {
		document.querySelectorAll(managedFormSelector).forEach(resetSubmittingState);
	});

	document.querySelectorAll('[data-sidrena-oib]').forEach(validateOibField);
	initLocations();
	syncStandaloneEmptyState(document.getElementById('sidrena-standalone-rows'));
}());
