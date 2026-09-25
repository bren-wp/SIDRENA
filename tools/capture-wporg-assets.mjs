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

	await page.setViewportSize({ width: 390, height: 844 });
	await page.goto(`${baseUrl}/wp-admin/admin.php?page=sidrena`, { waitUntil: 'networkidle' });
	await page.locator('.sidrena-app').first().waitFor({ state: 'visible', timeout: 30000 });
	const mobileOverflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
	if (mobileOverflow > 4) {
		throw new Error(`Horizontal layout overflow at 390px: ${mobileOverflow}px`);
	}
} finally {
	await browser.close();
}
