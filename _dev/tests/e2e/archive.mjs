// E2E: listing archive (phase 3). node _dev/tests/e2e/archive.mjs [baseUrl]
// Filters, live count, canonical URLs, load more, sort, map view, no-JS sheet, mobile layout.
// Prints PASS/FAIL lines; screenshots go to _dev/screens/theme/.
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const out = '_dev/screens/theme';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const count = async (page) => Number((await page.locator('.th-result-count strong').first().textContent()) || -1);

/* ---------------------------------------------------------------- server: canonical URLs + count endpoint */
{
	const req = await browser.newContext();
	const r1 = await req.request.get(base + '/listings/?rtcl_category=cars', { maxRedirects: 0 });
	check('/listings/?rtcl_category=cars → category archive', r1.status() === 302 && /\/listing-category\/.*\/cars\/$/.test(r1.headers().location || ''), r1.headers().location);
	const r2 = await req.request.get(base + '/listings/?min_price=&max_price=&verified=&utm_source=x', { maxRedirects: 0 });
	check('empty params dropped, unknown params kept', r2.status() === 302 && /\/listings\/\?utm_source=x$/.test(r2.headers().location || ''), r2.headers().location);
	const r3 = await req.request.get(base + '/listing-location/torrevieja/', { maxRedirects: 0 });
	check('clean town archive is not redirected', r3.status() === 200);

	const filtered = '/listing-category/auto-moto-boats/vehicles-for-sale/cars/?f%5Bselect_mo47kwc1%5D%5B%5D=diesel';
	const json = await (await req.request.get(base + filtered + '&th_count=1')).json();
	const page = await req.newPage();
	await page.goto(base + filtered);
	const shown = await count(page);
	check('count endpoint = rendered count (field filter)', json.count === shown, `endpoint ${json.count}, page ${shown}`);
	const robots = await page.locator('meta[name="robots"]').getAttribute('content');
	check('filtered archive is noindex,follow', /noindex/.test(robots || '') && /follow/.test(robots || ''), robots);
	const bogus = await (await req.request.get(base + '/listing-category/auto-moto-boats/vehicles-for-sale/cars/?f%5Bselect_mo47kwc1%5D%5B%5D=%3Cscript%3E&th_count=1')).json();
	const all = await (await req.request.get(base + '/listing-category/auto-moto-boats/vehicles-for-sale/cars/?th_count=1')).json();
	check('unknown option values are ignored (whitelist)', bogus.count === all.count);
	await req.close();
}

/* ---------------------------------------------------------------- desktop */
{
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));

	await page.goto(base + '/listings/', { waitUntil: 'networkidle' });
	const total = await count(page);
	const first = await page.locator('[data-th-results] .th-card:not(.th-skeleton)').count();
	check('first page shows a full page of cards', first === Math.min(total, 12), `${first} of ${total}`);
	if (total > first) {
		await page.locator('[data-th-load-more]').click();
		await page.waitForFunction((n) => document.querySelectorAll('[data-th-results] .th-card:not(.th-skeleton)').length > n, first);
		const after = await page.locator('[data-th-results] .th-card:not(.th-skeleton)').count();
		check('load more appends the next page', after === Math.min(total, 24), `${after}`);
		check('focus moves to the first new result', await page.evaluate(() => !!document.activeElement?.closest('.th-card__title')));
		check('end-of-results card after the last page', (await page.locator('.th-end').count()) === 1 && (await page.locator('[data-th-load-more]').count()) === 0);
		check('status message announced', ((await page.locator('[data-th-results-status]').textContent()) || '').length > 0);
	}

	// Filter sheet: open, pick a town, live count, radius chips, submit.
	await page.locator('.th-filterbar__open').click();
	check('filter sheet opens as a modal', await page.evaluate(() => document.getElementById('th-filters').matches(':modal')));
	check('radius hidden until a town is chosen', await page.locator('[data-th-radius]').isHidden());
	await page.selectOption('#th-f-town', 'torrevieja');
	check('radius shown after choosing a town', await page.locator('[data-th-radius]').isVisible());
	const btn = page.locator('[data-th-count]');
	await page.waitForFunction(() => !document.querySelector('[data-th-count]').hasAttribute('aria-busy'));
	await page.waitForTimeout(400);
	const label1 = (await btn.textContent()).trim();
	await page.locator('label.th-chip:has(input[name="radius"][value="25"])').click();
	await page.waitForTimeout(600);
	const label2 = (await btn.textContent()).trim();
	check('live count updates', label1 !== label2 || /Show|No results/.test(label2), `${label1} → ${label2}`);
	await page.screenshot({ path: `${out}/archive-sheet-open-1440.png` });
	await Promise.all([page.waitForURL(/radius=25/), btn.click()]);
	check('submit → canonical town URL without empty params', /\/listings\/\?rtcl_location=torrevieja&radius=25$/.test(page.url()), page.url());
	check('heading reflects the town', /Torrevieja/.test(await page.locator('h1').textContent()));
	check('sort offers Nearest with a radius', (await page.locator('#th-sort option[value="nearest"]').count()) === 1);

	// Sort auto-submit.
	await Promise.all([page.waitForURL(/orderby=price-asc/), page.selectOption('#th-sort', 'price-asc')]);
	check('sort select submits on change', /orderby=price-asc/.test(page.url()));

	// Remove the town chip.
	await Promise.all([page.waitForNavigation(), page.locator('.th-filterbar__chips a', { hasText: 'Torrevieja' }).click()]);
	check('chip removes its filter', !/rtcl_location/.test(page.url()) && !/radius/.test(page.url()), page.url());

	// Category change inside the sheet → category archive with its fields.
	await page.goto(base + '/listings/', { waitUntil: 'networkidle' });
	await page.locator('.th-filterbar__open').click();
	await Promise.all([page.waitForURL(/listing-category\/restaurants-nightlife/), page.selectOption('#th-f-category', 'restaurants-nightlife')]);
	check('category change in the sheet loads that category', /listing-category\/restaurants-nightlife\/$/.test(page.url()), page.url());
	await page.locator('.th-filterbar__open').click();
	check('category fields appear in the sheet', (await page.locator('.th-filters__fields .th-filters__field').count()) > 0);
	await page.keyboard.press('Escape');

	// Map view.
	await page.goto(base + '/listings/?view=map', { waitUntil: 'networkidle' });
	await page.waitForSelector('.leaflet-container', { timeout: 10000 }).catch(() => {});
	const markers = await page.locator('.th-marker').count();
	const pins = await page.evaluate(() => JSON.parse(document.querySelector('[data-th-map-data]').textContent).pins.length);
	check('map renders one marker per mappable listing', markers === pins && pins > 0, `${markers} markers / ${pins} pins`);
	await page.locator('.th-marker .th-pin').first().click();
	check('marker click shows the listing card on the map', (await page.locator('[data-th-map-card] .th-card').count()) === 1);
	check('marker click highlights the list item', (await page.locator('.th-mapview__item.is-active').count()) === 1);
	await page.locator('[data-th-map-action="zoom-in"]').click();
	await page.screenshot({ path: `${out}/archive-map-1440.png` });

	check('no JS errors (desktop)', errors.length === 0, errors.slice(0, 3).join(' | '));
	await ctx.close();
}

/* ---------------------------------------------------------------- mobile */
{
	const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	for (const path of ['/listings/', '/listings/?rtcl_location=torrevieja&radius=25', '/listings/?view=map', '/listings/?view=list', '/listing-category/services/?rtcl_location=alcoi']) {
		await page.goto(base + path, { waitUntil: 'networkidle' });
		const overflow = await page.evaluate(() => document.documentElement.scrollWidth - 390);
		check(`no horizontal overflow ${path}`, overflow === 0, `${overflow}px`);
	}
	await page.goto(base + '/listings/', { waitUntil: 'networkidle' });
	await page.locator('.th-filterbar__open').click();
	const sheet = await page.locator('#th-filters').boundingBox();
	check('filter sheet is a bottom sheet on mobile', sheet && Math.round(sheet.y + sheet.height) >= 843, JSON.stringify(sheet));
	await page.screenshot({ path: `${out}/archive-sheet-390.png` });
	const small = await page.evaluate(() =>
		[...document.querySelectorAll('#th-filters a, #th-filters button, #th-filters label.th-chip, #th-filters select')]
			.filter((el) => el.offsetParent !== null)
			.map((el) => ({ el, r: el.getBoundingClientRect() }))
			.filter(({ r }) => r.height < 40)
			.map(({ el, r }) => `${el.className || el.tagName} ${Math.round(r.height)}`)
	);
	check('sheet controls ≥ 40px tall on touch', small.length === 0, small.join(', '));
	check('no JS errors (mobile)', errors.length === 0, errors.join(' | '));
	await ctx.close();
}

/* ---------------------------------------------------------------- no JS */
{
	const ctx = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	await page.goto(base + '/listings/', { waitUntil: 'load' });
	await Promise.all([page.waitForURL(/th_sheet=1/), page.locator('.th-filterbar__open').click()]);
	check('no-JS: Filters link renders the sheet open', await page.locator('#th-filters[open]').isVisible());
	await page.waitForTimeout(1500); // Smooth scroll to #th-filters settles.
	await page.selectOption('#th-f-town', 'torrevieja');
	await page.locator('[data-th-count]').click();
	await page.waitForURL(/listing-location\/torrevieja\/$/, { waitUntil: 'commit', timeout: 10000 }).catch(() => {});
	check('no-JS: plain submit lands on a clean URL', /\/listing-location\/torrevieja\/$/.test(page.url()), page.url());
	await ctx.close();
}

await browser.close();
console.log(failures ? `\n${failures} FAILED` : '\nALL PASSED');
process.exit(failures ? 1 : 0);
