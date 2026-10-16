(function () {
	"use strict";

	function initCjenik(root) {
		if (!root || root.dataset.brendigoCjenikReady === "1") {
			return;
		}

		var input = root.querySelector(".cjenik-filter");
		if (!input) {
			root.dataset.brendigoCjenikReady = "1";
			return;
		}

		var productRows = Array.prototype.slice.call(root.querySelectorAll(".cjenik-table tbody tr"));
		var upitRows = Array.prototype.slice.call(root.querySelectorAll(".cjenik-upit tbody tr"));
		var empty = root.querySelector(".cjenik-empty-filter");
		var pager = root.querySelector(".cjenik-pager");
		var table = root.querySelector(".cjenik-table");
		var wrap = table && table.closest ? table.closest(".cjenik-table-wrap") : null;
		var pageSize = 200;
		var page = 1;

		if (pager) {
			var parsedSize = parseInt(pager.getAttribute("data-page-size"), 10);
			if (parsedSize > 0) {
				pageSize = parsedSize;
			}
		}

		function matches(row, query) {
			return !query || (row.getAttribute("data-search") || "").indexOf(query) !== -1;
		}

		function pageList(total, current) {
			var list = [];
			var n;
			if (total <= 9) {
				for (n = 1; n <= total; n += 1) {
					list.push(n);
				}
				return list;
			}

			var keep = {};
			keep[1] = true;
			keep[total] = true;
			for (n = current - 2; n <= current + 2; n += 1) {
				if (n >= 1 && n <= total) {
					keep[n] = true;
				}
			}

			var nums = [];
			for (n = 1; n <= total; n += 1) {
				if (keep[n]) {
					nums.push(n);
				}
			}

			var previous = 0;
			for (n = 0; n < nums.length; n += 1) {
				if (previous && nums[n] - previous > 1) {
					list.push(0);
				}
				list.push(nums[n]);
				previous = nums[n];
			}
			return list;
		}

		function addButton(num) {
			var button = document.createElement("button");
			button.type = "button";
			button.className = "cjenik-page" + (num === page ? " is-current" : "");
			button.textContent = String(num);
			button.setAttribute("aria-label", String(num));
			if (num === page) {
				button.setAttribute("aria-current", "page");
			}
			button.addEventListener("click", function () {
				page = num;
				render(false);
				if (wrap && wrap.scrollIntoView) {
					var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
					wrap.scrollIntoView({ behavior: reduceMotion ? "auto" : "smooth", block: "start" });
				}
			});
			pager.appendChild(button);
		}

		function render(resetPage) {
			var query = (input.value || "").toLocaleLowerCase().trim();
			if (resetPage) {
				page = 1;
			}

			var matched = [];
			var upitShown = 0;
			productRows.forEach(function (row) {
				if (matches(row, query)) {
					matched.push(row);
				} else {
					row.hidden = true;
				}
			});

			upitRows.forEach(function (row) {
				var show = matches(row, query);
				row.hidden = !show;
				if (show) {
					upitShown += 1;
				}
			});

			var pages = Math.max(1, Math.ceil(matched.length / pageSize));
			if (page > pages) {
				page = pages;
			}
			var start = (page - 1) * pageSize;
			var end = start + pageSize;
			matched.forEach(function (row, index) {
				row.hidden = index < start || index >= end;
			});

			if (empty) {
				empty.hidden = matched.length + upitShown !== 0;
			}
			if (!pager) {
				return;
			}

			pager.innerHTML = "";
			if (pages <= 1) {
				pager.hidden = true;
				return;
			}

			pager.hidden = false;
			pageList(pages, page).forEach(function (item) {
				if (item === 0) {
					var gap = document.createElement("span");
					gap.className = "cjenik-page-gap";
					gap.textContent = "…";
					gap.setAttribute("aria-hidden", "true");
					pager.appendChild(gap);
				} else {
					addButton(item);
				}
			});
		}

		input.addEventListener("input", function () {
			render(true);
		});
		root.dataset.brendigoCjenikReady = "1";
		render(false);
	}

	function initAll() {
		document.querySelectorAll(".sidrena-cjenik").forEach(initCjenik);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initAll, { once: true });
	} else {
		initAll();
	}

	if (typeof MutationObserver !== "undefined") {
		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (!node || node.nodeType !== 1) {
						return;
					}
					if (node.matches && node.matches(".sidrena-cjenik")) {
						initCjenik(node);
					}
					if (node.querySelectorAll) {
						node.querySelectorAll(".sidrena-cjenik").forEach(initCjenik);
					}
				});
			});
		});
		observer.observe(document.documentElement, { childList: true, subtree: true });
	}
})();
