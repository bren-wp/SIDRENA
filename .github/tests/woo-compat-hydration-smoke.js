/**
 * Sidrena source file.
 *
 * SIDRENA Woo compatibility hydration behavior regression.
 *
 * Executes the production compatibility script in a minimal browser-like VM
 * and verifies that server-rendered parent markup avoids an initial REST call,
 * a selected variation hydrates dynamically, and reset restores the parent.
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
const fetches = [];
const handlers = {};

const markupNode = {};
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
		return null;
	},
	querySelectorAll(selector) {
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
		return Promise.resolve({
			ok: true,
			status: 200,
			json() {
				return Promise.resolve({
					html: 20 === id ? variationHtml : (30 === id ? retryVariationHtml : parentHtml),
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
vm.runInNewContext(source, sandbox, { filename: "public/js/compat.js" });

async function flushPromises() {
	await Promise.resolve();
	await new Promise((resolve) => setImmediate(resolve));
	await Promise.resolve();
}

(async () => {
	await flushPromises();

	check(0 === fetches.length, "Server-rendered parent SIDRENA markup must avoid the redundant initial REST request.");
	check("function" === typeof handlers.found_variation, "found_variation handler must be registered.");
	check("function" === typeof handlers.reset_data, "reset_data handler must be registered.");
	check("function" === typeof handlers.hide_variation, "hide_variation handler must be registered.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 20 }
	);
	await flushPromises();

	check(1 === fetches.length && /\/20$/.test(fetches[0]), "Selected variation must fetch its own SIDRENA markup.");
	check(variationHtml === renderedHtml, "Selected variation must replace the parent reference markup.");

	handlers.reset_data({ currentTarget: form });
	await flushPromises();

	check(2 === fetches.length && /\/10$/.test(fetches[1]), "Variation reset must force a parent-product REST refresh.");
	check(parentHtml === renderedHtml, "Variation reset must restore the parent product reference markup.");

	renderedHtml = variationHtml;
	handlers.hide_variation({ currentTarget: form });
	await flushPromises();

	check(parentHtml === renderedHtml, "hide_variation must also restore the parent product reference markup.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 30 }
	);
	await flushPromises();

	check(3 === fetches.length && /\/30$/.test(fetches[2]), "First retryable variation request must reach the REST endpoint.");
	check(parentHtml === renderedHtml, "Transient HTTP failure must leave the previously valid parent markup unchanged.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 30 }
	);
	await flushPromises();

	check(4 === fetches.length && /\/30$/.test(fetches[3]), "Transient HTTP failure must not be cached as a permanent empty result.");
	check(retryVariationHtml === renderedHtml, "A later successful retry must hydrate the variation markup.");

	const beforePermanentFailure = fetches.length;
	const markupBeforePermanentFailure = renderedHtml;
	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 40 }
	);
	await flushPromises();

	check(beforePermanentFailure + 1 === fetches.length && /\/40$/.test(fetches[fetches.length - 1]), "Permanent 404 variation request must reach the REST endpoint once.");
	check(markupBeforePermanentFailure === renderedHtml, "Permanent 404 must leave the previously valid markup unchanged.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 40 }
	);
	await flushPromises();

	check(beforePermanentFailure + 1 === fetches.length, "Permanent 4xx REST result must be negative-cached instead of refetched on repeated hydration.");

	process.stdout.write("SIDRENA Woo compatibility hydration behavior smoke test passed.\n");
})().catch((error) => {
	process.stderr.write((error && error.stack ? error.stack : String(error)) + "\n");
	process.exit(1);
});
