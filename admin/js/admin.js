(function () {
	'use strict';

	var cfg = window.SidrenaAdmin || {};
	var locationCounter = 0;

	function closest(element, selector) {
		return element && element.closest ? element.closest(selector) : null;
	}

	function message(key, fallback) {
		return typeof cfg[key] === 'string' && cfg[key] ? cfg[key] : fallback;
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
		if (!form || !form.matches || !form.matches('.sid-form, .sid-bulk-card, .sid-standalone-form, .sid-standalone-import')) {
			return;
		}
		form.classList.add('is-submitting');
		window.setTimeout(function () {
			form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (button) {
				button.disabled = true;
				button.setAttribute('aria-disabled', 'true');
			});
		}, 0);
	});

	initLocations();
}());
