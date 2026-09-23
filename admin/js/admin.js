(function () {
	'use strict';

	function closest(element, selector) {
		return element && element.closest ? element.closest(selector) : null;
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
			var index = wrap.querySelectorAll('.sid-location').length;
			var html = template.innerHTML.split('__INDEX__').join(String(index));
			wrap.insertAdjacentHTML('beforeend', html);
			var rows = wrap.querySelectorAll('.sid-location');
			var last = rows.length ? rows[rows.length - 1] : null;
			var firstInput = last ? last.querySelector('input[type="text"]') : null;
			if (firstInput) {
				firstInput.focus();
			}
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
			var standaloneLast = standaloneRows.length ? standaloneRows[standaloneRows.length - 1] : null;
			var standaloneInput = standaloneLast ? standaloneLast.querySelector('input[type="text"]') : null;
			if (standaloneInput) {
				standaloneInput.focus();
			}
			return;
		}

		var removeStandalone = closest(target, '.sidrena-remove-standalone');
		if (removeStandalone) {
			event.preventDefault();
			var standaloneRow = closest(removeStandalone, '.sidrena-standalone-row');
			if (standaloneRow) {
				standaloneRow.remove();
			}
			return;
		}

		var removeButton = closest(target, '.sid-remove-location');
		if (removeButton) {
			event.preventDefault();
			var row = closest(removeButton, '.sid-location');
			if (row && window.confirm('Ukloniti ovu lokaciju iz konfiguracije?')) {
				row.remove();
			}
		}
	});
}());
