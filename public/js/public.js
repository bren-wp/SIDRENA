
(function () {
	"use strict";

	function normalize(value) {
		return (value || "")
			.toString()
			.toLocaleLowerCase("hr-HR")
			.normalize("NFD")
			.replace(/[\u0300-\u036f]/g, "")
			.trim();
	}

	function init(root) {
		var input = root.querySelector("[data-sidrena-search]");
		var rows = Array.prototype.slice.call(root.querySelectorAll("[data-sidrena-row]"));
		var empty = root.querySelector("[data-sidrena-empty]");

		if (!input || !rows.length) {
			return;
		}

		function filter() {
			var query = normalize(input.value);
			var visible = 0;

			rows.forEach(function (row) {
				var haystack = normalize(row.getAttribute("data-search") || row.textContent);
				var match = !query || haystack.indexOf(query) !== -1;
				row.hidden = !match;
				if (match) {
					visible += 1;
				}
			});

			if (empty) {
				empty.hidden = visible !== 0;
			}
		}

		input.addEventListener("input", filter);
	}

	function boot() {
		document.querySelectorAll("[data-sidrena-pricelist]").forEach(init);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
}());
