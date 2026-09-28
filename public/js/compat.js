/**
 * Sidrena source file.
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * Plugin URI: https://brendigo.com/sidrene-cijene/
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
	var parentMarkup = null;

	if (!productId || !selectors.length) {
		return;
	}

	function fetchMarkup(id) {
		id = parseInt(id || 0, 10);
		if (!id || !endpoint) {
			return Promise.resolve(null);
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
				if (response.ok) {
					return response.json();
				}
				if ([400, 401, 403, 404, 405, 410, 422, 451].indexOf(response.status) !== -1) {
					return { html: "" };
				}
				return Promise.reject();
			})
			.then(function (data) {
				var html = data && typeof data.html === "string" ? data.html : null;
				if (html !== null) {
					cache[id] = html;
				}
				delete pending[id];
				return html;
			})
			.catch(function () {
				delete pending[id];
				return null;
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

	function existingMarkupNodes(root) {
		root = root || document;
		if (!root.querySelectorAll) {
			return [];
		}
		return Array.prototype.slice.call(root.querySelectorAll(".sidrena-reference-prices"));
	}

	function replaceMarkup(existing, html) {
		if (!existing || existing.outerHTML === html) {
			return;
		}
		existing.outerHTML = html;
	}

	function removeMarkup(existing) {
		if (!existing) {
			return;
		}
		if (typeof existing.remove === "function") {
			existing.remove();
			return;
		}
		existing.outerHTML = "";
	}

	function targetMarkup(target) {
		if (!target) {
			return null;
		}
		if (target.closest) {
			var closest = target.closest(".sidrena-reference-prices");
			if (closest) {
				return closest;
			}
		}
		var nested = target.querySelector ? target.querySelector(".sidrena-reference-prices") : null;
		if (nested) {
			return nested;
		}
		return target.parentElement && target.parentElement.querySelector
			? target.parentElement.querySelector(":scope > .sidrena-reference-prices")
			: null;
	}

	function readMarkup(root) {
		var nodes = existingMarkupNodes(root);
		if (nodes.length && typeof nodes[0].outerHTML === "string") {
			return nodes[0].outerHTML;
		}

		var targets = candidateTargets(root, false);
		for (var i = 0; i < targets.length; i++) {
			var existing = targetMarkup(targets[i]);
			if (existing && typeof existing.outerHTML === "string") {
				return existing.outerHTML;
			}
		}
		return "";
	}

	function targetsAlreadyHydrated(root, variationMode) {
		var targets = candidateTargets(root, variationMode);
		return targets.length > 0 && targets.every(function (target) {
			return !!targetMarkup(target);
		});
	}

	function applyMarkup(html, root, variationMode) {
		if (typeof html !== "string") {
			return;
		}

		root = root || document;
		var existingNodes = existingMarkupNodes(root);
		if (existingNodes.length) {
			existingNodes.forEach(function (existing) {
				if (html) {
					replaceMarkup(existing, html);
				} else {
					removeMarkup(existing);
				}
			});
			return;
		}

		if (!html) {
			return;
		}

		candidateTargets(root, variationMode).forEach(function (target) {
			if (target.closest && target.closest(".sidrena-reference-prices")) {
				return;
			}
			var existing = targetMarkup(target);
			if (existing) {
				replaceMarkup(existing, html);
				return;
			}
			target.insertAdjacentHTML("beforeend", html);
		});
	}

	function isOwnMarkupNode(node) {
		return !!(node && node.nodeType === 1 && (
			(node.matches && node.matches(".sidrena-reference-prices")) ||
			(node.closest && node.closest(".sidrena-reference-prices"))
		));
	}

	function mutationNeedsHydration(mutation) {
		var nodes = Array.prototype.slice.call(mutation.addedNodes || [])
			.concat(Array.prototype.slice.call(mutation.removedNodes || []));
		if (!nodes.length) {
			return true;
		}
		return nodes.some(function (node) {
			return !isOwnMarkupNode(node);
		});
	}

	function hydrate(id, root, variationMode, force) {
		id = parseInt(id || 0, 10);
		if (!id) {
			return;
		}
		if (!force && !variationMode && targetsAlreadyHydrated(root, false)) {
			return;
		}
		fetchMarkup(id).then(function (html) {
			if (id !== activeId) {
				return;
			}
			if (typeof html !== "string") {
				return;
			}
			applyMarkup(html, root, variationMode);
		});
	}

	function applyVariationPayload(id, html, root) {
		activeId = id;
		cache[id] = html;
		applyMarkup(html, root, true);
	}

	function restoreParent(root) {
		activeId = productId;
		if (typeof parentMarkup === "string") {
			cache[productId] = parentMarkup;
			applyMarkup(parentMarkup, root, false);
			return;
		}
		hydrate(productId, root, false, true);
	}

	function boot() {
		parentMarkup = readMarkup(document);
		if (parentMarkup) {
			cache[productId] = parentMarkup;
		} else {
			hydrate(productId, document, false);
		}

		if (window.jQuery) {
			var $ = window.jQuery;
			$(document).on("found_variation", ".variations_form", function (event, variation) {
				var id = variation && parseInt(variation.variation_id || 0, 10);
				var root = event.currentTarget.closest(".product") || document;
				if (!id) {
					return;
				}

				if (typeof variation.sidrena_reference_html === "string") {
					applyVariationPayload(id, variation.sidrena_reference_html, root);
					return;
				}

				activeId = id;
				hydrate(id, root, true);
			});
			$(document).on("reset_data hide_variation", ".variations_form", function (event) {
				var root = event.currentTarget.closest(".product") || document;
				restoreParent(root);
			});
		}

		if (typeof MutationObserver !== "undefined") {
			var root = document.querySelector(".single-product, .product, main") || document.body;
			if (root) {
				var observer = new MutationObserver(function (mutations) {
					if (!mutations.some(mutationNeedsHydration)) {
						return;
					}
					window.clearTimeout(observerTimer);
					observerTimer = window.setTimeout(function () {
						if (Object.prototype.hasOwnProperty.call(cache, activeId)) {
							applyMarkup(cache[activeId], root, activeId !== productId);
						} else {
							hydrate(activeId, root, activeId !== productId);
						}
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
