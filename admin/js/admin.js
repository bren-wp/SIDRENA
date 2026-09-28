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

	function validateFileField(field) {
		if (!field || !field.matches || !field.matches('.sid-file-input')) {
			return;
		}
		field.setCustomValidity('');
		var file = field.files && field.files.length ? field.files[0] : null;
		if (!file) {
			return;
		}
		var maxBytes = Number(field.getAttribute('data-max-bytes') || 5242880);
		if (file.size > maxBytes) {
			field.setCustomValidity(message('fileTooLarge', 'Datoteka je prevelika. Najveća dopuštena veličina je 5 MB.'));
			return;
		}
		var accept = String(field.getAttribute('accept') || '').toLowerCase();
		if (accept) {
			var name = String(file.name || '').toLowerCase();
			var allowed = accept.split(',').some(function (token) {
				token = token.trim();
				return token.charAt(0) === '.' ? name.endsWith(token) : token === String(file.type || '').toLowerCase();
			});
			if (!allowed) {
				field.setCustomValidity(message('invalidFileType', 'Odaberite podržanu CSV ili XML datoteku.'));
			}
		}
	}

	function formatMessage(template, value) {
		return String(template || '').replace('%s', value);
	}

	function safeFillScope(scope) {
		if (!scope || !scope.querySelectorAll) {
			return 0;
		}
		var changed = 0;
		scope.querySelectorAll('[data-sidrena-safe-fill]').forEach(function (field) {
			var current = String(field.value || '').trim();
			var suggestion = String(field.getAttribute('data-sidrena-suggest') || '').trim();
			if (current || !suggestion) {
				return;
			}
			field.value = suggestion;
			field.classList.add('is-suggested');
			field.dispatchEvent(new Event('input', { bubbles: true }));
			changed += 1;
		});
		return changed;
	}

	function updateLocationSummary(row) {
		if (!row) {
			return;
		}
		var codeField = row.querySelector('input[name$="[code]"]');
		var addressField = row.querySelector('input[name$="[address]"]');
		var title = row.querySelector('.sid-location-title');
		var address = row.querySelector('.sid-location-address');
		var remove = row.querySelector('.sid-remove-location');
		var code = codeField && String(codeField.value || '').trim()
			? String(codeField.value).trim()
			: message('newLocation', 'Nova lokacija');
		var addressText = addressField && String(addressField.value || '').trim()
			? String(addressField.value).trim()
			: message('emptyLocationAddress', 'Adresa nije upisana');

		if (title) {
			title.textContent = code;
		}
		if (address) {
			address.textContent = addressText;
		}
		if (remove) {
			remove.setAttribute('aria-label', formatMessage(message('removeLocationLabel', 'Ukloni lokaciju %s'), code));
		}
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
		updateLocationSummary(row);
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

		var fillRowButton = closest(target, '.sid-safe-fill-row');
		if (fillRowButton) {
			event.preventDefault();
			var details = closest(fillRowButton, '.sid-row-details');
			var rowForm = fillRowButton.form || closest(fillRowButton, 'form');
			var rowChanged = safeFillScope(details || rowForm);
			setFormStatus(
				rowForm,
				rowChanged
					? formatMessage(message('safeFillChanged', 'Popunjeno je %s praznih polja iz pouzdanih WooCommerce izvora. Pregledajte podatke i spremite promjene.'), rowChanged)
					: message('safeFillEmpty', 'Nema praznih polja s pouzdanim WooCommerce izvorom. Ostala polja ostaju nepromijenjena.')
			);
			return;
		}

		var fillPageButton = closest(target, '#sid-safe-fill-page');
		if (fillPageButton) {
			event.preventDefault();
			var pageForm = fillPageButton.form || closest(fillPageButton, 'form');
			var pageChanged = safeFillScope(pageForm);
			setFormStatus(
				pageForm,
				pageChanged
					? formatMessage(message('safeFillChanged', 'Popunjeno je %s praznih polja iz pouzdanih WooCommerce izvora. Pregledajte podatke i spremite promjene.'), pageChanged)
					: message('safeFillEmpty', 'Nema praznih polja s pouzdanim WooCommerce izvorom. Ostala polja ostaju nepromijenjena.')
			);
			return;
		}

		var fillWooLocation = closest(target, '#sid-fill-woo-location');
		if (fillWooLocation) {
			event.preventDefault();
			var wooWrap = document.getElementById('sid-locations');
			var wooAddress = wooWrap ? String(wooWrap.getAttribute('data-sidrena-woo-address') || '').trim() : '';
			var wooTarget = null;
			if (wooWrap && wooAddress) {
				Array.prototype.some.call(wooWrap.querySelectorAll('.sid-location'), function (locationRow) {
					var kindField = locationRow.querySelector('.sid-location-kind');
					var addressField = locationRow.querySelector('.sid-location-address-input');
					var kind = kindField ? String(kindField.value || '').trim().toLowerCase() : '';
					var currentAddress = addressField ? String(addressField.value || '').trim() : '';
					if ('webshop' === kind && addressField && !currentAddress) {
						wooTarget = locationRow;
						addressField.value = wooAddress;
						addressField.classList.add('is-suggested');
						addressField.dispatchEvent(new Event('input', { bubbles: true }));
						updateLocationSummary(locationRow);
						addressField.focus();
						return true;
					}
					return false;
				});
			}
			var wooForm = fillWooLocation.form || closest(fillWooLocation, 'form');
			setFormStatus(
				wooForm,
				wooTarget
					? message('wooLocationFilled', 'WooCommerce adresa trgovine unesena je u praznu webshop lokaciju. Pregledajte podatak i spremite lokacije.')
					: message('wooLocationNoTarget', 'Nema prazne webshop adrese za popunjavanje. Postojeći podaci nisu promijenjeni.')
			);
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
			var locationForm = last ? last.closest('form') : null;
			setFormStatus(locationForm, message('locationAdded', 'Nova lokacija je dodana. Unesite podatke i spremite promjene.'));
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
			var detailsRow = closest(removeStandalone, '.sid-standalone-details-row');
			var standaloneRow = detailsRow && detailsRow.previousElementSibling && detailsRow.previousElementSibling.matches('.sidrena-standalone-row')
				? detailsRow.previousElementSibling
				: closest(removeStandalone, '.sidrena-standalone-row');
			if (standaloneRow && window.confirm(message('removeUnsavedProduct', 'Ukloniti ovaj nespremljeni proizvod?'))) {
				if (detailsRow) {
					detailsRow.remove();
				}
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
				var nextRow = row.nextElementSibling && row.nextElementSibling.matches('.sid-location') ? row.nextElementSibling : null;
				var previousRow = row.previousElementSibling && row.previousElementSibling.matches('.sid-location') ? row.previousElementSibling : null;
				var locationForm = row.closest('form');
				row.remove();
				if (nextRow || previousRow) {
					focusFirstField(nextRow || previousRow);
				} else {
					var addLocationButton = document.getElementById('sid-add-location');
					if (addLocationButton) {
						addLocationButton.focus();
					}
				}
				setFormStatus(locationForm, message('locationRemoved', 'Lokacija je uklonjena iz obrasca. Spremite promjene za potvrdu.'));
			}
		}
	});

	document.addEventListener('change', function (event) {
		var target = event.target;
		if (!target) {
			return;
		}
		validateFileField(target);
		clearInvalidState(target);
		var changedForm = target.form;
		if (changedForm && changedForm.matches && changedForm.matches(managedFormSelector)) {
			setFormStatus(changedForm, '');
		}
		if (target.matches && target.matches('.sid-location-enabled')) {
			setLocationState(closest(target, '.sid-location'));
			return;
		}
		if (target.matches && target.matches('.sid-location input[name$="[code]"], .sid-location input[name$="[address]"]')) {
			updateLocationSummary(closest(target, '.sid-location'));
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
		if (event.target && event.target.classList && event.isTrusted) {
			event.target.classList.remove('is-suggested');
		}
		validateFileField(event.target);
		clearInvalidState(event.target);
		var form = event.target && event.target.form;
		if (form && form.matches && form.matches(managedFormSelector)) {
			setFormStatus(form, '');
		}
	});

	window.addEventListener('pageshow', function () {
		document.querySelectorAll(managedFormSelector).forEach(resetSubmittingState);
	});

	document.querySelectorAll('.sid-file-input').forEach(validateFileField);
	initLocations();
	syncStandaloneEmptyState(document.getElementById('sidrena-standalone-rows'));
}());
