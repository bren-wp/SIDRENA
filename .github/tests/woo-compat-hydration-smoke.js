/**
 * SIDRENA Woo compatibility hydration behavior regression.
 *
 * Executes the production compatibility script in a minimal browser-like VM
 * and verifies that server-rendered parent markup avoids an initial REST call,
 * a selected variation hydrates dynamically, and reset restores the parent.
 */

"use strict";

const fs = require("fs");
const path = require("path");
const vm = require("vm");

function assert(condition, message) {
	if (!condition) {
		process.stderr.write(message + "\n");
		process.exit(1);
	}
}

const parentHtml = '<span class="sidrena-reference-prices" data-product="10">parent</span>';
const variationHtml = '<span class="sidrena-reference-prices" data-product="20">variation</span>';
let renderedHtml = parentHtml;
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
		assert("beforeend" === position, "Compatibility markup must append with beforeend.");
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
			assert(".variations_form" === selector, "Compatibility lifecycle must stay scoped to variation forms.");
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
		return Promise.resolve({
			ok: true,
			json() {
				return Promise.resolve({
					html: 20 === id ? variationHtml : parentHtml,
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

	assert(0 === fetches.length, "Server-rendered parent SIDRENA markup must avoid the redundant initial REST request.");
	assert("function" === typeof handlers.found_variation, "found_variation handler must be registered.");
	assert("function" === typeof handlers.reset_data, "reset_data handler must be registered.");
	assert("function" === typeof handlers.hide_variation, "hide_variation handler must be registered.");

	handlers.found_variation(
		{ currentTarget: form },
		{ variation_id: 20 }
	);
	await flushPromises();

	assert(1 === fetches.length && /\/20$/.test(fetches[0]), "Selected variation must fetch its own SIDRENA markup.");
	assert(variationHtml === renderedHtml, "Selected variation must replace the parent reference markup.");

	handlers.reset_data({ currentTarget: form });
	await flushPromises();

	assert(2 === fetches.length && /\/10$/.test(fetches[1]), "Variation reset must force a parent-product REST refresh.");
	assert(parentHtml === renderedHtml, "Variation reset must restore the parent product reference markup.");

	renderedHtml = variationHtml;
	handlers.hide_variation({ currentTarget: form });
	await flushPromises();

	assert(parentHtml === renderedHtml, "hide_variation must also restore the parent product reference markup.");

	process.stdout.write("SIDRENA Woo compatibility hydration behavior smoke test passed.\n");
})().catch((error) => {
	process.stderr.write((error && error.stack ? error.stack : String(error)) + "\n");
	process.exit(1);
});
