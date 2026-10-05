#!/usr/bin/env node
// Visual comparison screenshots (Playwright + system Chrome).
//   node _dev/tools/screens.mjs design            → _dev/screens/design/<board>.png (Direction C boards/frames)
//   node _dev/tools/screens.mjs theme <path> <name> [sections…]
//        → _dev/screens/theme/<name>-1440.png, <name>-390.png (+ per-section crops at 1440)
// Base URL: TH_BASE (default http://localhost:10004).

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from 'playwright-core';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const outRoot = path.join(root, '_dev/screens');
const base = process.env.TH_BASE || 'http://localhost:10004';
const [mode, ...rest] = process.argv.slice(2);

const browser = await chromium.launch({ channel: 'chrome', headless: true });

async function shotDesign() {
	const out = path.join(outRoot, 'design');
	fs.mkdirSync(out, { recursive: true });
	const page = await browser.newPage({ viewport: { width: 2000, height: 1200 } });
	await page.goto(pathToFileURL(path.join(root, '_dev/design/extracted/direction-c.html')).href, { waitUntil: 'networkidle' });
	await page.waitForTimeout(1500);
	// Boards are identified by a header text; take the smallest ancestor at least 1400px wide.
	const boards = {
		'F-01-F-02': 'F-01 / F-02',
		'F-03-F-05': 'F-03 / F-04 / F-05',
	};
	for (const [name, text] of Object.entries(boards)) {
		const handle = await page.evaluateHandle((t) => {
			const node = [...document.querySelectorAll('body *')].find((el) => el.childElementCount === 0 && el.textContent.includes(t));
			let el = node;
			while (el && el.parentElement && (el.getBoundingClientRect().width < 1400 || el.getBoundingClientRect().height < 800)) el = el.parentElement;
			// climb further while the parent has the same width (wrapper divs) but stop at body
			return el;
		}, text);
		const el = handle.asElement();
		if (!el) {
			console.log(`not found: ${name}`);
			continue;
		}
		await el.screenshot({ path: path.join(out, `${name}.png`) });
		console.log(`design ${name}.png`);
	}
	// Device frames by their label text (mobile 390 frames + desktop 1440 frames).
	const frames = process.env.TH_FRAMES ? JSON.parse(process.env.TH_FRAMES) : {};
	for (const [name, text] of Object.entries(frames)) {
		const handle = await page.evaluateHandle((t) => {
			const node = [...document.querySelectorAll('body *')].find((el) => el.childElementCount === 0 && el.textContent.trim().startsWith(t));
			let el = node;
			while (el && el.parentElement && !(/^(390|1440)px$/.test(el.style.width))) el = el.parentElement;
			return el;
		}, text);
		const el = handle.asElement();
		if (el) {
			await el.screenshot({ path: path.join(out, `${name}.png`) });
			console.log(`design ${name}.png`);
		}
	}
}

async function shotTheme(urlPath, name, sections) {
	const out = path.join(outRoot, 'theme');
	fs.mkdirSync(out, { recursive: true });
	for (const width of [1440, 390]) {
		const page = await browser.newPage({ viewport: { width, height: 900 }, deviceScaleFactor: 1 });
		const errors = [];
		page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
		page.on('pageerror', (e) => errors.push(String(e)));
		await page.goto(base + urlPath, { waitUntil: 'networkidle' });
		await page.evaluate(() => document.fonts.ready);
		await page.screenshot({ path: path.join(out, `${name}-${width}.png`), fullPage: true });
		const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
		console.log(`theme ${name}-${width}.png  horizontal-overflow:${overflow}px  console-errors:${errors.length}${errors.length ? ' → ' + errors.slice(0, 3).join(' | ') : ''}`);
		for (const id of sections) {
			const el = page.locator(`#${id}`);
			if (await el.count()) {
				await el.screenshot({ path: path.join(out, `${name}-${id}${width === 1440 ? '' : '-' + width}.png`) });
			}
		}
		await page.close();
	}
}

async function shotFrames() {
	// Every device frame (inline width 1440px / 390px) → _dev/screens/design/frame-<n>-<w>.png + index.json
	const out = path.join(outRoot, 'design');
	fs.mkdirSync(out, { recursive: true });
	const page = await browser.newPage({ viewport: { width: 2000, height: 1200 } });
	await page.goto(pathToFileURL(path.join(root, '_dev/design/extracted/direction-c.html')).href, { waitUntil: 'networkidle' });
	await page.waitForTimeout(1500);
	const frames = page.locator('[style*="width: 1440px"], [style*="width:1440px"], [style*="width: 390px"], [style*="width:390px"]');
	const n = await frames.count();
	const index = [];
	for (let i = 0; i < n; i++) {
		const el = frames.nth(i);
		const info = await el.evaluate((e) => ({ w: Math.round(e.getBoundingClientRect().width), h: Math.round(e.getBoundingClientRect().height), text: e.innerText.replace(/\s+/g, ' ').slice(0, 90) }));
		if (info.h < 300) continue;
		const file = `frame-${String(i).padStart(2, '0')}-${info.w}.png`;
		await el.screenshot({ path: path.join(out, file) });
		index.push({ file, ...info });
		console.log(file, info.h, info.text.slice(0, 60));
	}
	fs.writeFileSync(path.join(out, 'frames.json'), JSON.stringify(index, null, '\t'));
}

if (mode === 'frames') {
	await shotFrames();
} else if (mode === 'design') {
	await shotDesign();
} else if (mode === 'theme') {
	const [urlPath = '/', name = 'page', ...sections] = rest;
	await shotTheme(urlPath, name, sections);
} else {
	console.log('usage: screens.mjs design | theme <path> <name> [sectionIds…]');
}
await browser.close();
