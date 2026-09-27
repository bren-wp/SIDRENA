/**
 * Sidrena source file.
 * Author: Brendigo
 * Author URI: https://brendigo.com/
 * Plugin URI: https://brendigo.com/sidrene-cijene/
 * Support: sidrena@brendigo.com
 */

import { chromium } from 'playwright';
import fs from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.SIDRENA_BASE_URL || 'http://localhost:8888';
const outputDir = process.env.SIDRENA_SCREENSHOT_DIR;
const edition = process.env.SIDRENA_SCREENSHOT_EDITION || 'wordpress';

if (!outputDir) {
	throw new Error('SIDRENA_SCREENSHOT_DIR is required.');
}

const screens = [
	['screenshot-1.png', '/wp-admin/admin.php?page=sidrena', '.sidrena-app'],
	['screenshot-2.png', '/wp-admin/admin.php?page=sidrena-catalog', '.sidrena-app'],
	['screenshot-3.png', '/wp-admin/admin.php?page=sidrena-files', '.sidrena-app'],
	['screenshot-4.png', '/wp-admin/admin.php?page=sidrena-locations', '.sidrena-app'],
	['screenshot-5.png', '/wp-admin/admin.php?page=sidrena-settings', '.sidrena-app'],
	['screenshot-6.png', '/wp-admin/admin.php?page=sidrena-support', '.sidrena-app'],
];

await fs.mkdir(outputDir, { recursive: true });

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({
	viewport: { width: 1440, height: 1080 },
	deviceScaleFactor: 1,
});
const page = await context.newPage();
let pageErrors = [];

page.on('pageerror', (error) => {
	pageErrors.push(error && error.message ? error.message : String(error));
});

function resetPageErrors() {
	pageErrors = [];
}

function assertNoClientErrors(route) {
	if (pageErrors.length) {
		throw new Error(`JavaScript runtime error on ${route}: ${pageErrors.join(' | ')}`);
	}
}

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

async function assertBrandRuntime(targetPage, route) {
	const result = await targetPage.evaluate(() => {
		const styleLink = Array.from(document.querySelectorAll('link[rel="stylesheet"]')).find((link) =>
			(link.href || '').includes('/admin/css/brand.css')
		);
		const adminScript = Array.from(document.scripts).find((script) =>
			(script.src || '').includes('/admin/js/admin.js')
		);
		const brandbar = document.querySelector('.sidrena-brandbar');
		const logo = document.querySelector('.sidrena-brandbar__logo');
		const card = document.querySelector('.sid-reference-panel, .sid-dashboard-metric, .sid-settings-section, .sid-card');
		const menuIcon = document.querySelector('#adminmenu .toplevel_page_sidrena .wp-menu-image img');

		const read = (node) => node ? window.getComputedStyle(node) : null;
		const brandStyle = read(brandbar);
		const cardStyle = read(card);
		const logoRect = logo ? logo.getBoundingClientRect() : null;
		const menuRect = menuIcon ? menuIcon.getBoundingClientRect() : null;

		return {
			styleLoaded: !!styleLink,
			scriptLoaded: !!adminScript,
			brandDisplay: brandStyle ? brandStyle.display : '',
			brandBottomLeftRadius: brandStyle ? parseFloat(brandStyle.borderBottomLeftRadius) || 0 : 0,
			brandBottomRightRadius: brandStyle ? parseFloat(brandStyle.borderBottomRightRadius) || 0 : 0,
			brandBackground: brandStyle ? brandStyle.backgroundImage : '',
			cardRadius: cardStyle ? parseFloat(cardStyle.borderRadius) || 0 : 0,
			cardBackground: cardStyle ? cardStyle.backgroundColor : '',
			logoWidth: logoRect ? logoRect.width : 0,
			logoHeight: logoRect ? logoRect.height : 0,
			menuWidth: menuRect ? menuRect.width : 0,
			menuHeight: menuRect ? menuRect.height : 0,
		};
	});

	if (!result.styleLoaded) {
		throw new Error(`SIDRENA brand.css is missing on ${route}`);
	}
	if (!result.scriptLoaded) {
		throw new Error(`SIDRENA admin.js is missing on ${route}`);
	}
	if (result.brandDisplay !== 'grid' || result.brandBottomLeftRadius < 10 || result.brandBottomRightRadius < 10 || !result.brandBackground.includes('brand-hero.svg')) {
		throw new Error(`SIDRENA hero styles are not applied on ${route}: ${JSON.stringify(result)}`);
	}
	if (result.cardRadius < 8 || result.cardBackground !== 'rgb(255, 255, 255)') {
		throw new Error(`SIDRENA card design is not applied on ${route}: ${JSON.stringify(result)}`);
	}
	if (result.logoWidth < 180 || result.logoWidth > 520 || result.logoHeight < 48 || result.logoHeight > 100) {
		throw new Error(`SIDRENA hero logo is outside the production bounds on ${route}: ${JSON.stringify(result)}`);
	}
	if (result.menuWidth > 20.5 || result.menuHeight > 20.5) {
		throw new Error(`SIDRENA WordPress menu icon is oversized on ${route}: ${JSON.stringify(result)}`);
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

		const gridSelectors = [
			'.sid-reference-metrics',
			'.sid-reference-action-grid',
			'.sid-reference-grid',
			'.sid-reference-files-grid',
			'.sid-fields-location',
			'.sid-row-details__grid',
		];
		for (const selector of gridSelectors) {
			for (const grid of document.querySelectorAll(selector)) {
				const children = Array.from(grid.children).filter((node) => {
					const style = window.getComputedStyle(node);
					const rect = node.getBoundingClientRect();
					return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 2 && rect.height > 2;
				});
				for (let i = 0; i < children.length; i += 1) {
					const aRect = children[i].getBoundingClientRect();
					const a = { left: aRect.left, top: aRect.top, right: aRect.right, bottom: aRect.bottom };
					for (let j = i + 1; j < children.length; j += 1) {
						const bRect = children[j].getBoundingClientRect();
						const b = { left: bRect.left, top: bRect.top, right: bRect.right, bottom: bRect.bottom };
						if (intersects(a, b)) {
							overlaps.push([selector + ' child ' + i, selector + ' child ' + j]);
						}
					}
				}
			}
		}
		return overlaps;
	});
	if (result.length) {
		throw new Error(`Key UI overlap detected on ${route}: ${JSON.stringify(result)}`);
	}
}

async function assertFormRuntime(targetPage, route) {
	const issues = await targetPage.evaluate(() => {
		const out = [];
		const viewportWidth = document.documentElement.clientWidth;
		const forms = document.querySelectorAll('.sid-form, .sid-bulk-card, .sid-standalone-form, .sid-standalone-import');

		for (const form of forms) {
			const submit = form.querySelector('button[type="submit"], input[type="submit"]');
			if (submit) {
				const rect = submit.getBoundingClientRect();
				const style = window.getComputedStyle(submit);
				if (submit.disabled || style.display === 'none' || style.visibility === 'hidden' || rect.width < 2 || rect.height < 34) {
					out.push('submit control is disabled, hidden or too small');
				}
			}

			for (const file of form.querySelectorAll('input[type="file"]')) {
				const label = file.closest('label');
				const describedBy = file.getAttribute('aria-describedby');
				if (!label) {
					out.push('file input has no wrapping label');
				}
				if (!describedBy || !document.getElementById(describedBy)) {
					out.push('file input has no valid aria-describedby help text');
				}
			}

			for (const control of form.querySelectorAll('input:not([type="hidden"]), select, textarea, button')) {
				if (control.closest('.sid-table-wrap')) continue;
				const style = window.getComputedStyle(control);
				if (style.display === 'none' || style.visibility === 'hidden') continue;
				const rect = control.getBoundingClientRect();
				if (rect.width < 2 || rect.height < 2) {
					out.push('visible form control has zero layout size');
					continue;
				}
				if (rect.left < -4 || rect.right > viewportWidth + 4) {
					out.push(`form control escapes viewport: ${Math.round(rect.left)}..${Math.round(rect.right)} of ${viewportWidth}`);
				}
			}
		}
		return out;
	});

	if (issues.length) {
		throw new Error(`Form UI regression on ${route}: ${JSON.stringify(issues)}`);
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
		resetPageErrors();
		await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
		await page.locator(selector).first().waitFor({ state: 'visible', timeout: 30000 });
		await assertNoRuntimeError(page, route);
		assertNoClientErrors(route);
		await assertBrandRuntime(page, route);
		await assertNoKeyOverlaps(page, route);
		await assertFormRuntime(page, route);
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
			resetPageErrors();
			await page.goto(`${baseUrl}${route}`, { waitUntil: 'networkidle' });
			await page.locator(selector).first().waitFor({ state: 'visible', timeout: 30000 });
			await assertNoRuntimeError(page, responsiveRoute);
			assertNoClientErrors(responsiveRoute);
			await assertBrandRuntime(page, responsiveRoute);
			await assertNoKeyOverlaps(page, responsiveRoute);
			await assertFormRuntime(page, responsiveRoute);
			const overflow = await page.evaluate(() => Math.max(0, document.documentElement.scrollWidth - document.documentElement.clientWidth));
			if (overflow > 4) {
				throw new Error(`Horizontal layout overflow on ${responsiveRoute}: ${overflow}px`);
			}
		}
	}
} finally {
	await browser.close();
}
