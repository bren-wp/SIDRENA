/**
 * Sidrena source file.
 * Author: Brendigo LTD Developer
 * Author URI: https://brendigo.com/
 * Plugin URI: https://sidrene-cijene.com.hr/
 * Support: sidrena@brendigo.com
 */


(function () {
	"use strict";

	var cfg = window.SidrenaCompat || {};
	var productId = parseInt(cfg.productId || 0, 10);
	var endpoint = cfg.endpoint || "";
	var selectors = Array.isArray(cfg.selectors) ? cfg.selectors : [];
	var cache = {};
	var pending = {};
	var observerTimer = 0;
	var activeId = productId;

	if (!productId || !endpoint || !selectors.length) {
		return;
	}

	function fetchMarkup(id) {
		id = parseInt(id || 0, 10);
		if (!id) {
			return Promise.resolve("");
		}
		if (Object.prototype.hasOwnProperty.call(cache, id)) {
			return Promise.resolve(cache[id]);
		}
		if (pending[id]) {
			return pending[id];
		}

		pending[id] = fetch(endpoint + id, {
			credentials: "same-origin",
			headers: { "Accept": "application/json" }
		})
			.then(function (response) {
				if (!response.ok) {
					return "";
				}
				return response.json();
			})
			.then(function (data) {
				var html = data && typeof data.html === "string" ? data.html : "";
				cache[id] = html;
				delete pending[id];
				return html;
			})
			.catch(function () {
				delete pending[id];
				return "";
			});

		return pending[id];
	}

	function candidateTargets(root, variationMode) {
		root = root || document;
		var found = [];

		if (variationMode) {
			var variationPrice = root.querySelector(".woocommerce-variation-price .price");
			if (variationPrice) {
				return [variationPrice];
			}
		}

		selectors.forEach(function (selector) {
			root.querySelectorAll(selector).forEach(function (node) {
				if (found.indexOf(node) === -1) {
					found.push(node);
				}
			});
		});
		return found;
	}

	function applyMarkup(html, root, variationMode) {
		if (!html) {
			return;
		}

		candidateTargets(root, variationMode).forEach(function (target) {
			if (target.closest(".sidrena-reference-prices")) {
				return;
			}
			var existing = target.querySelector(".sidrena-reference-prices");
			if (existing) {
				existing.outerHTML = html;
				return;
			}
			if (target.parentElement && target.parentElement.querySelector(":scope > .sidrena-reference-prices")) {
				target.parentElement.querySelector(":scope > .sidrena-reference-prices").outerHTML = html;
				return;
			}
			target.insertAdjacentHTML("beforeend", html);
		});
	}

	function hydrate(id, root, variationMode) {
		fetchMarkup(id).then(function (html) {
			applyMarkup(html, root, variationMode);
		});
	}

	function boot() {
		hydrate(productId, document, false);

		if (window.jQuery) {
			var $ = window.jQuery;
			$(document).on("found_variation", ".variations_form", function (event, variation) {
				var id = variation && parseInt(variation.variation_id || 0, 10);
				var root = event.currentTarget.closest(".product") || document;
				if (id) {
					activeId = id;
					hydrate(id, root, true);
				}
			});
			$(document).on("reset_data hide_variation", ".variations_form", function (event) {
				var root = event.currentTarget.closest(".product") || document;
				activeId = productId;
				hydrate(productId, root, false);
			});
		}

		if (typeof MutationObserver !== "undefined") {
			var root = document.querySelector(".single-product, .product, main") || document.body;
			if (root) {
				var observer = new MutationObserver(function () {
					window.clearTimeout(observerTimer);
					observerTimer = window.setTimeout(function () {
						hydrate(activeId, root, activeId !== productId);
					}, 120);
				});
				observer.observe(root, { childList: true, subtree: true });
			}
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", boot);
	} else {
		boot();
	}
}());
