// Sidrena WordPress.org real screenshot capture.
// Author: Brendigo
// Plugin URI: https://brendigo.com/sidrene-cijene/

import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.SIDRENA_BASE_URL || 'http://localhost:8888';
const outputDir = process.env.SIDRENA_SCREENSHOT_DIR;
const edition = process.env.SIDRENA_SCREENSHOT_EDITION || 'wordpress';

if (!outputDir) {
	throw new Error('SIDRENA_SCREENSHOT_DIR is required.');
}

const screens = edition === 'woocommerce'
	? [
		['screenshot-1.png', '/wp-admin/admin.php?page=sidrena', '.sidrena-app'],
		['screenshot-2.png', '/wp-admin/admin.php?page=sidrena-catalog', '.sidrena-app'],
		['screenshot-3.png', '/wp-admin/admin.php?page=sidrena-files', '.sidrena-app'],
		['screenshot-4.png', '/wp-admin/admin.php?page=sidrena-locations', '.sidrena-app'],
	]
	: [
		['screenshot-1.png', '/wp-admin/admin.php?page=sidrena', '.sidrena-app'],
		['screenshot-2.png', '/wp-admin/admin.php?page=sidrena-catalog', '.sidrena-app'],
		['screenshot-3.png', '/wp-admin/admin.php?page=sidrena-files', '.sidrena-app'],
		['screenshot-4.png', '/wp-admin/admin.php?page=sidrena-settings', '.sidrena-app'],
	];

await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
	viewport: { width: 1440, height: 1080 },
	deviceScaleFactor: 1,
});
const page = await context.newPage();

async function assertNoRuntimeError(targetPage, route) {
	const bodyText = await targetPage.locator('body').innerText();
	const fatalPatterns = [
		/there has been a critical error/i,
		/došlo je do kritične greške/i,
		/fatal error/i,
		/uncaught (?:error|exception)/i,
	];
	if (fatalPatterns.some((pattern) => pattern.test(bodyText))) {
		throw new Error(`Runtime error detected while capturing ${route}`);
	}
}

async function assertNoKeyOverlaps(targetPage, route) {
	const result = await targetPage.evaluate(() => {
		const overlaps = [];
		const visibleRect = (selector, root = document) => {
			const node = root.querySelector(selector);
			if (!node) return null;
			const style = window.getComputedStyle(node);
			const rect = node.getBoundingClientRect();
			if (style.display === 'none' || style.visibility === 'hidden' || rect.width < 2 || rect.height < 2) return null;
			return { selector, left: rect.left, top: rect.top, right: rect.right, bottom: rect.bottom };
		};
		const intersects = (a, b) => a && b && Math.min(a.right, b.right) - Math.max(a.left, b.left) > 2 && Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top) > 2;
		const pairs = [
			['.sidrena-brandbar__identity', '.sidrena-brandbar__copy'],
			['.sidrena-brandbar__copy', '.sidrena-brandbar__actions'],
		];
		for (const [aSelector, bSelector] of pairs) {
			const a = visibleRect(aSelector);
			const b = visibleRect(bSelector);
			if (intersects(a, b)) overlaps.push([aSelector, bSelector]);
		}
		for (const head of document.querySelectorAll('.sid-location-head')) {
			const a = visibleRect(':scope > div:first-child', head);
			const b = visibleRect('.sid-location-actions', head);
			if (intersects(a, b)) overlaps.push(['.sid-location-head identity', '.sid-location-actions']);
		}
		return overlaps;
	});
	if (result.length) {
		throw new Error(`Key UI overlap detected on ${route}: ${JSON.stringify(result)}`);
	}
}

try {
	await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
	await page.locator('#user_login').fill('admin');
	await page.locator('#user_pass').fill('password');
	await Promise.all([
		page.waitForURL(/wp-admin/),
		page.locator('#wp-submit').click(),
	]);

	for (const [filename, route, selector] of screens) {
		await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
		await page.locator(selector).first().waitFor({ state: 'visible', timeout: 30000 });
		await assertNoRuntimeError(page, route);
		await assertNoKeyOverlaps(page, route);
		const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
		if (overflow > 4) {
			throw new Error(`Horizontal layout overflow on ${route}: ${overflow}px`);
		}
		await page.evaluate(() => window.scrollTo(0, 0));
		await page.screenshot({
			path: path.join(outputDir, filename),
			fullPage: false,
		});
	}

	for (const width of [1180, 782, 390]) {
		await page.setViewportSize({ width, height: 900 });
		for (const [, route, selector] of screens) {
			const responsiveRoute = `${route} @${width}px`;
			await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
			await page.locator(selector).first().waitFor({ state: 'visible', timeout: 30000 });
			await assertNoRuntimeError(page, responsiveRoute);
			await assertNoKeyOverlaps(page, responsiveRoute);
			const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
			if (overflow > 4) {
				throw new Error(`Horizontal layout overflow on ${responsiveRoute}: ${overflow}px`);
			}
		}
	}
} finally {
	await browser.close();
}
