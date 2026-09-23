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
