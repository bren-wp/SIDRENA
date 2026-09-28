/**
 * Sidrena source file.
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * Plugin URI: https://brendigo.com/sidrene-cijene/
 * Support: sidrena@brendigo.com
 */

import { chromium } from "playwright";

const baseUrl = (process.env.SIDRENA_RUNTIME_URL || "http://localhost:8888").replace(/\/$/, "");
const edition = process.env.SIDRENA_QA_EDITION || "unknown";

function assert(condition, message) {
	if (!condition) {
		throw new Error(`[${edition}] ${message}`);
	}
}

async function readJson(page, path, expectedStatus = 200) {
	const response = await page.goto(baseUrl + path, { waitUntil: "domcontentloaded" });
	assert(response, `No HTTP response for ${path}`);
	assert(response.status() === expectedStatus, `${path} returned HTTP ${response.status()}, expected ${expectedStatus}`);

	const text = await page.locator("body").innerText();
	try {
		return JSON.parse(text);
	} catch (error) {
		throw new Error(`[${edition}] ${path} did not return JSON: ${error.message}`);
	}
}

const browser = await chromium.launch({ headless: true });
try {
	const page = await browser.newPage();

	const prices = await readJson(page, "/wp-json/sidrena/v1/cijene?type=products&page=1&per_page=20");
	assert(prices && prices.schema === 1, "Realtime price endpoint schema changed unexpectedly.");
	assert(prices.location && prices.location.id === "webshop", "Omitted location must resolve to the default webshop location.");
	assert(Object.keys(prices.location).join(",") === "id,code,kind,address", "Public location payload contains unexpected internal fields.");
	for (const key of ["generator", "catalog_mode", "woocommerce_active"]) {
		assert(!Object.prototype.hasOwnProperty.call(prices, key), `Realtime endpoint leaked internal field: ${key}`);
	}
	assert(prices.products && Array.isArray(prices.products.items), "Realtime products payload is missing its item list.");

	const index = await readJson(page, "/wp-json/sidrena/v1/cjenici");
	assert(index && index.schema === 3, "Public cjenik index schema changed unexpectedly.");
	assert(!Object.prototype.hasOwnProperty.call(index, "plugin_url"), "Public cjenik index must not expose an external plugin credit URL.");
	for (const key of ["generator", "ruleset", "rules_effective", "catalog_mode", "woocommerce_active", "product_count", "retention_days"]) {
		assert(!Object.prototype.hasOwnProperty.call(index, key), `Public cjenik index leaked internal field: ${key}`);
	}

	const missingLocation = await readJson(page, "/wp-json/sidrena/v1/cijene?type=products&location=ne-postoji", 404);
	assert(missingLocation && missingLocation.code === "location_not_found", "Unknown explicit location must return location_not_found.");

	process.stdout.write(`Sidrena ${edition} Chromium REST runtime QA passed.\n`);
} finally {
	await browser.close();
}
