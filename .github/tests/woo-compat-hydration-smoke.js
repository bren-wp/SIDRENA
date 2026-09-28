/**
 * Sidrena source file.
 *
 * SIDRENA Woo compatibility hydration behavior regression.
 *
 * Verifies that embedded Woo variation payloads update reference markup without
 * REST, known-empty payloads clear stale markup, reset restores the server
 * parent markup locally, and REST remains a retryable compatibility fallback.
 *
 * @author Brendigo
 * @link https://brendigo.com/sidrene-cijene/
 * @see https://brendigo.com/
 */

"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");

function check(condition, message) {
	if (!condition) {
		process.stderr.write(message + "\n");
		process.exit(1);
	}
}

const parentHtml = '<span class="sidrena-reference-prices" data-product="10">parent</span>';
const variationHtml = '<span class="sidrena-reference-prices" data-product="20">variation</span>';
const retryVariationHtml = '<span class="sidrena-reference-prices" data-product="30">retry variation</span>';
let renderedHtml = parentHtml;
let retryFailedOnce = false;
let timeoutFailedOnce = false;
const fetches = [];
const handlers = {};

const markupNode = {
	nodeType: 1,
	matches(selector) {
		return ".sidrena-reference-prices" === selector;
	},
	closest(selector) {
		return ".sidrena-reference-prices" === selector ? this : null;
	},
	remove() {
		renderedHtml = "";
	},
};
Object.defineProperty(markupNode, "outerHTML", {
	get() {
		return renderedHtml;
	},
	set(value) {
		renderedHtml = value;
	},
});

const target = {
	closest() {
		return null;
	},
	querySelector(selector) {
		return ".sidrena-reference-prices" === selector && renderedHtml ? markupNode : null;
	},
	insertAdjacentHTML(position, html) {
		check("beforeend" === position, "Compatibility markup must append with beforeend.");
		renderedHtml = html;
	},
	parentElement: {
		querySelector() {
			return null;
		},
	},
};

const root = {
	querySelector(selector) {
		if (".woocommerce-variation-price .price" === selector) {
			return null;
		}
		if (".sidrena-reference-prices" === selector) {
			return renderedHtml ? markupNode : null;
		}
		return null;
	},
	querySelectorAll(selector) {
		if (".sidrena-reference-prices" === selector) {
			return renderedHtml ? [markupNode] : [];
		}
		return ".price" === selector ? [target] : [];
	},
};

const form = {
	closest(selector) {
		return ".product" === selector ? root : null;
	},
};

const documentMock = {
	readyState: "complete",
	body: root,
	querySelector(selector) {
		if (".single-product, .product, main" === selector) {
			return root;
		}
		return root.querySelector(selector);
	},
	querySelectorAll(selector) {
		return root.querySelectorAll(selector);
	},
	addEventListener() {},
};

function jQueryMock() {
	return {
		on(events, selector, callback) {
			check(".variations_form" === selector, "Compatibility lifecycle must stay scoped to variation forms.");
			events.split(/\s+/).forEach((eventName) => {
				handlers[eventName] = callback;
			});
		},
	};
}

const windowMock = {
	SidrenaCompat: {
		productId: 10,
		endpoint: "https://example.test/wp-json/sidrena/v1/display/",
		selectors: [".price"],
	},
	jQuery: jQueryMock,
	setTimeout,
	clearTimeout,
};

const sandbox = {
	window: windowMock,
	document: documentMock,
	fetch(url) {
		fetches.push(url);
		const id = Number(String(url).split("/").filter(Boolean).pop());
		if (30 === id && !retryFailedOnce) {
			retryFailedOnce = true;
			return Promise.resolve({
				ok: false,
				status: 503,
				json() {
					return Promise.resolve({});
				},
			});
		}
		if (40 === id) {
			return Promise.resolve({
				ok: false,
				status: 404,
				json() {
					return Promise.resolve({});
				},
			});
		}
		if (50 === id && !timeoutFailedOnce) {
			timeoutFailedOnce = true;
			return Promise.resolve({
				ok: false,
				status: 408,
				json() {
					return Promise.resolve({});
				},
			});
		}
		return Promise.resolve({
			ok: true,
			status: 200,
			json() {
				return Promise.resolve({
					html: 30 === id || 50 === id ? retryVariationHtml : parentHtml,
				});
			},
		});
	},
	MutationObserver: undefined,
	Promise,
	Array,
	Object,
	parseInt,
	setTimeout,
	clearTimeout,
	console,
};

const source = fs.readFileSync(path.join(__dirname, "../../public/js/compat.js"), "utf8");
check(source.includes("if (!productId || !selectors.length)"), "Variation synchronization must boot even when REST fallback endpoint is empty.");
check(!source.includes("!productId || !endpoint || !selectors.length"), "REST availability must not gate embedded Woo variation payload synchronization.");
vm.runInNewContext(source, sandbox, { filename: "public/js/compat.js" });

async function flushPromises() {
	await Promise.resolve();
	await new Promise((resolve) => setImmediate(resolve));
	await Promise.resolve();
}

(async () => {
	await flushPromises();

	check(0 === fetches.length, "Server-rendered parent SIDRENA markup must avoid an initial REST request.");
	check("function" === typeof handlers.found_variation, "found_variation handler must be registered.");
	check("function" === typeof handlers.reset_data, "reset_data handler must be registered.");
	check("function" === typeof handlers.hide_variation, "hide_variation handler must be registered.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 20, sidrena_reference_html: variationHtml }
	);
	await flushPromises();

	check(0 === fetches.length, "Embedded variation markup must not make a REST request.");
	check(variationHtml === renderedHtml, "Embedded variation markup must replace the parent reference markup.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 21, sidrena_reference_html: "" }
	);
	await flushPromises();

	check(0 === fetches.length, "Known-empty variation payload must not make a REST request.");
	check("" === renderedHtml, "Known-empty variation payload must clear stale parent reference markup.");

	handlers.reset_data({ currentTarget: form });
	await flushPromises();

	check(0 === fetches.length, "Variation reset must restore the server parent markup without REST.");
	check(parentHtml === renderedHtml, "Variation reset must restore the original parent reference markup.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 30 }
	);
	await flushPromises();

	check(1 === fetches.length && /\/30$/.test(fetches[0]), "Missing embedded payload must fall back to the variation REST endpoint.");
	check(parentHtml === renderedHtml, "Transient REST failure must leave the last valid parent markup unchanged.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 30 }
	);
	await flushPromises();

	check(2 === fetches.length && /\/30$/.test(fetches[1]), "Transient REST failure must remain retryable.");
	check(retryVariationHtml === renderedHtml, "Successful fallback retry must hydrate the variation markup.");

	const beforePermanentFailure = fetches.length;
	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 40 }
	);
	await flushPromises();

	check(beforePermanentFailure + 1 === fetches.length && /\/40$/.test(fetches[fetches.length - 1]), "Permanent 404 variation request must reach REST once.");
	check("" === renderedHtml, "Permanent known-empty REST result must clear stale variation markup.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 40 }
	);
	await flushPromises();

	check(beforePermanentFailure + 1 === fetches.length, "Permanent 4xx REST result must be negative-cached.");

	handlers.hide_variation({ currentTarget: form });
	await flushPromises();

	check(parentHtml === renderedHtml, "hide_variation must restore the parent markup without REST.");

	const beforeTimeout = fetches.length;
	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 50 }
	);
	await flushPromises();

	check(beforeTimeout + 1 === fetches.length && /\/50$/.test(fetches[fetches.length - 1]), "HTTP 408 variation request must reach REST.");
	check(parentHtml === renderedHtml, "HTTP 408 must preserve the last valid parent markup.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 50 }
	);
	await flushPromises();

	check(beforeTimeout + 2 === fetches.length, "HTTP 408 must remain retryable instead of being negative-cached.");
	check(retryVariationHtml === renderedHtml, "Successful retry after HTTP 408 must hydrate the variation markup.");

	process.stdout.write("SIDRENA Woo embedded variation hydration smoke test passed.\n");
})().catch((error) => {
	process.stderr.write((error && error.stack ? error.stack : String(error)) + "\n");
	process.exit(1);
});
