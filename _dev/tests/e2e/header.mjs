// E2E: header interactions (phase 2). node _dev/tests/e2e/header.mjs [baseUrl]
// Prints PASS/FAIL lines and writes viewport screenshots to _dev/screens/theme/.
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const out = '_dev/screens/theme';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};

/* ---------------------------------------------------------------- desktop */
{
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	await page.goto(base + '/', { waitUntil: 'networkidle' });
	await page.screenshot({ path: `${out}/home-header-1440.png` });

	const explore = page.locator('[data-th-mega-toggle]');
	await explore.click();
	check('mega opens on click', (await page.locator('#th-mega').isVisible()) && (await explore.getAttribute('aria-expanded')) === 'true');
	check('focus moves to the selected root tab', await page.evaluate(() => document.activeElement?.getAttribute('role') === 'tab'));
	await page.keyboard.press('ArrowDown');
	const second = await page.evaluate(() => document.activeElement?.id);
	const panelShown = await page.evaluate((id) => {
		const tab = document.getElementById(id);
		return !document.getElementById(tab.getAttribute('aria-controls')).hidden;
	}, second);
	check('ArrowDown selects next root + shows its panel', panelShown, second);
	await page.screenshot({ path: `${out}/home-mega-1440.png` });
	await page.keyboard.press('Escape');
	check('Esc closes mega and returns focus', !(await page.locator('#th-mega').isVisible()) && (await page.evaluate(() => document.activeElement?.hasAttribute('data-th-mega-toggle'))));

	const lang = page.locator('.th-header__actions .th-lang__toggle');
	await lang.click();
	check('language menu opens', await page.locator('.th-header__actions .th-lang__menu').isVisible());
	const langCount = await page.locator('.th-header__actions .th-lang__menu a[data-gt-lang]').count();
	check('language menu lists GTranslate languages', langCount >= 2, `${langCount} languages`);
	await page.keyboard.press('Escape');

	// Town picker (JS): choose Alicante → cookie + reload → pill shows Alicante.
	await page.locator('.th-header__bar .th-loc-pill').click();
	check('location dialog opens', await page.locator('#th-location').isVisible());
	await page.fill('[data-th-town-filter]', 'alic');
	const visibleTowns = await page.locator('[data-th-town-list] li:not([hidden])').count();
	check('town filter narrows the list', visibleTowns >= 1 && visibleTowns < 5, `${visibleTowns} shown`);
	await Promise.all([page.waitForNavigation(), page.locator('#th-location [data-th-town="alicante"]').click()]);
	const pill = (await page.locator('.th-header__bar .th-loc-pill').innerText()).trim();
	check('picked town persists after reload', /Alicante/.test(pill), pill);
	const hidden = await page.locator('form.th-search input[name="rtcl_location"]').first().getAttribute('value');
	check('search carries rtcl_location of the town', hidden === 'alicante', hidden);
	check('no JS errors (desktop)', errors.length === 0, errors.join(' | '));
	await ctx.close();
}

/* ---------------------------------------------------------------- no-JS town switch */
{
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false });
	const page = await ctx.newPage();
	await page.goto(base + '/?th_town=altea', { waitUntil: 'load' });
	const url = page.url();
	const pill = (await page.locator('.th-header__bar .th-loc-pill').innerText()).trim();
	check('no-JS ?th_town= sets town and redirects to clean URL', /Altea/.test(pill) && !url.includes('th_town'), `${url} · ${pill}`);
	await ctx.close();
}

/* ---------------------------------------------------------------- mobile */
{
	const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	await page.goto(base + '/', { waitUntil: 'networkidle' });
	await page.screenshot({ path: `${out}/home-header-390.png` });
	check('desktop-only header parts hidden on mobile', !(await page.locator('.th-header__post').isVisible()) && !(await page.locator('[data-th-mega-toggle]').isVisible()));
	check('bottom nav visible on mobile', await page.locator('.th-bottom-nav').isVisible());
	const h1 = await page.locator('h1').count();
	check('exactly one h1 (kept for AT on mobile)', h1 === 1, `${h1}`);

	await page.locator('.th-header__menu').click();
	check('drawer opens', await page.locator('#th-drawer').isVisible());
	await page.screenshot({ path: `${out}/home-drawer-390.png` });
	const rows = await page.locator('#th-drawer .th-cat-row').count();
	check('drawer lists 10 categories', rows === 10, `${rows}`);
	await page.keyboard.press('Escape');
	check('Esc closes drawer', !(await page.locator('#th-drawer').isVisible()));

	const tooSmall = await page.evaluate(() =>
		[...document.querySelectorAll('.th-header a, .th-header button, .th-bottom-nav a')]
			.filter((el) => el.offsetParent !== null)
			.map((el) => ({ el, r: el.getBoundingClientRect() }))
			.filter(({ r }) => r.width < 44 || r.height < 44)
			.map(({ el, r }) => `${el.className || el.tagName} ${Math.round(r.width)}×${Math.round(r.height)}`)
	);
	check('mobile header/bottom-nav targets ≥ 44px', tooSmall.length === 0, tooSmall.join(', '));
	check('no JS errors (mobile)', errors.length === 0, errors.join(' | '));
	await ctx.close();
}

await browser.close();
console.log(failures ? `\n${failures} FAILED` : '\nALL PASSED');
process.exit(failures ? 1 : 0);
