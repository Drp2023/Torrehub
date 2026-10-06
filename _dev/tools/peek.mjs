#!/usr/bin/env node
// Logged-in screenshot of one page at 1440 and 390, plus console errors.
//   node _dev/tools/peek.mjs <user> <pass> <path> <name> [--full] [--click=<selector>]
// Output: _dev/screens/theme/<name>-1440.png, <name>-390.png. Base URL: TH_BASE (default http://localhost:10004).
import { chromium } from 'playwright-core';

const base = process.env.TH_BASE || 'http://localhost:10004';
const [user, pass, target, name, ...flags] = process.argv.slice(2);
const full = flags.includes('--full');
const click = (flags.find((f) => f.startsWith('--click=')) || '').slice(8);

const browser = await chromium.launch({ channel: 'chrome', headless: true });
for (const width of [1440, 390]) {
	const ctx = await browser.newContext({ viewport: { width, height: width > 600 ? 1000 : 844 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
	if (user) {
		await page.goto(base + '/login/');
		await page.fill('#th-log', user);
		await page.fill('#th-pwd', pass);
		await Promise.all([page.waitForURL(/my-account/), page.click('.th-auth-form [type="submit"]')]);
	}
	const res = await page.goto(base + target, { waitUntil: 'networkidle' });
	if (click) {
		await page.click(click);
		await page.waitForTimeout(600);
	}
	await page.waitForTimeout(400);
	const overflow = await page.evaluate(() => document.documentElement.scrollWidth);
	await page.screenshot({ path: `_dev/screens/theme/${name}-${width}.png`, fullPage: full });
	console.log(`${width}: HTTP ${res?.status()} · scrollWidth ${overflow}${errors.length ? ' · errors: ' + errors.join(' | ') : ''}`);
	await ctx.close();
}
await browser.close();
