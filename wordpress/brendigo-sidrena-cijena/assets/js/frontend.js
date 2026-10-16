(function ($) {
	"use strict";

	function config() {
		return typeof sidrenaCijenaFront !== "undefined" ? sidrenaCijenaFront : {};
	}

	function sidrenaHtml() {
		return config().html || "";
	}

	function scope($form) {
		return $form.closest(".product, .summary, li.product");
	}

	function normalizeLegacyTooltipMarkup(root) {
		root = root || document;
		root.querySelectorAll(".sidrena-cijena__icon").forEach(function (el) {
			el.remove();
		});

		root.querySelectorAll(".sidrena-cijena__info").forEach(function (info) {
			var tooltip = info.querySelector(".sidrena-cijena__tooltip");
			var parent = info.parentNode;
			if (tooltip && parent) {
				parent.appendChild(tooltip);
				parent.classList.add("sidrena-cijena--has-tooltip");
			}
			info.remove();
		});
	}

	function isNestedPrice(el) {
		if (!el || !el.classList || !el.classList.contains("price")) {
			return false;
		}

		var parent = el.parentElement;
		return !!(parent && parent.closest && parent.closest(".price"));
	}

	function alreadyHasSidrena(el) {
		if (!el) {
			return true;
		}

		if (el.querySelector(".sidrena-cijena")) {
			return true;
		}

		var outer = el.closest && el.closest("p.price, .price");
		if (outer && outer !== el && outer.querySelector(".sidrena-cijena")) {
			return true;
		}

		return false;
	}

	function insertSidrena(el, html) {
		var lowest = el.querySelector(".lowest_price");
		var actual = el.querySelector(".actual_price");

		if (lowest && el.contains(lowest)) {
			var before = el.innerHTML.slice(0, el.innerHTML.toLowerCase().indexOf("lowest_price"));
			if (
				before.indexOf("woocommerce-Price-amount") !== -1 ||
				before.indexOf("<ins") !== -1 ||
				before.indexOf("<del") !== -1 ||
				before.indexOf("actual_price") !== -1
			) {
				lowest.insertAdjacentHTML("beforebegin", html);
				return;
			}
		}

		if (actual) {
			actual.insertAdjacentHTML("afterend", html);
			return;
		}

		el.insertAdjacentHTML("beforeend", html);
	}

	function dedupeIn(root) {
		if (!root) {
			return;
		}

		var nodes = root.querySelectorAll(".sidrena-cijena");
		for (var i = 1; i < nodes.length; i++) {
			nodes[i].remove();
		}
	}

	function injectInto(el) {
		var html = sidrenaHtml();
		if (!html || !el) {
			return;
		}

		if (
			el.classList.contains("elementor-widget-container") &&
			el.querySelector(".price")
		) {
			return;
		}

		if (isNestedPrice(el)) {
			return;
		}

		if (alreadyHasSidrena(el)) {
			dedupeIn(el);
			return;
		}

		insertSidrena(el, html);
		dedupeIn(el);
	}

	function injectBuilderPrices() {
		var html = sidrenaHtml();
		var selectors = config().selectors || [];
		if (!html || !selectors.length) {
			return;
		}

		selectors.forEach(function (selector) {
			try {
				document.querySelectorAll(selector).forEach(injectInto);
			} catch (e) {
				// Invalid selector from a stale cache - skip.
			}
		});

		normalizeLegacyTooltipMarkup(document);
	}

	function watchBuilderPrices() {
		var roots = config().watchRoots || [];
		if (!roots.length || typeof MutationObserver === "undefined") {
			return;
		}

		var watched = [];
		var debounceTimer = null;
		var running = false;

		function connectAll() {
			watched.forEach(function (entry) {
				entry.observer.observe(entry.root, { childList: true, subtree: true });
			});
		}

		function disconnectAll() {
			watched.forEach(function (entry) {
				entry.observer.disconnect();
			});
		}

		function runInject() {
			if (running) {
				return;
			}

			running = true;
			disconnectAll();
			try {
				injectBuilderPrices();
			} finally {
				connectAll();
				running = false;
			}
		}

		function scheduleRun() {
			clearTimeout(debounceTimer);
			debounceTimer = setTimeout(runInject, 150);
		}

		roots.forEach(function (selector) {
			document.querySelectorAll(selector).forEach(function (root) {
				var observer = new MutationObserver(scheduleRun);
				observer.observe(root, { childList: true, subtree: true });
				watched.push({ observer: observer, root: root });
			});
		});
	}

	function applyVariationSidrena($form, variation) {
		if (!variation || !variation.sidrena_cijena_html) {
			return;
		}

		var $variationPrice = $form.find(".woocommerce-variation-price");
		if (!$variationPrice.length) {
			return;
		}

		scope($form).find(".sidrena-cijena--variable").hide();
		$variationPrice.find(".sidrena-cijena").remove();

		var $lowest = $variationPrice.find(".lowest_price").first();
		if ($lowest.length) {
			$lowest.before(variation.sidrena_cijena_html);
		} else {
			$variationPrice.append(variation.sidrena_cijena_html);
		}

		dedupeIn($variationPrice.get(0));
	}

	$(function () {
		normalizeLegacyTooltipMarkup(document);
		injectBuilderPrices();
		watchBuilderPrices();

		$(document.body).on(
			"updated_wc_div wcpa_fields_updated wcpa_price_updated",
			injectBuilderPrices
		);

		$("form.variations_form").each(function () {
			var $form = $(this);
			var pending = null;

			function showForVariation(variation) {
				window.clearTimeout(pending);
				pending = null;
				applyVariationSidrena($form, variation);
				injectBuilderPrices();
			}

			$form.on("show_variation", function (event, variation) {
				showForVariation(variation);
			});

			// found_variation runs before WooCommerce builds the price box.
			$form.on("found_variation", function (event, variation) {
				window.clearTimeout(pending);
				pending = window.setTimeout(function () {
					showForVariation(variation);
				}, 0);
			});

			$form.on("reset_data hide_variation", function () {
				window.clearTimeout(pending);
				pending = null;
				scope($form).find(".sidrena-cijena--variable").show();
				$form.find(".woocommerce-variation-price .sidrena-cijena").remove();
				injectBuilderPrices();
			});
		});
	});
})(jQuery);
