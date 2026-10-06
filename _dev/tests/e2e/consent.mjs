// E2E: cookie consent (phase 8). node _dev/tests/e2e/consent.mjs [baseUrl]
// Needs (local only): WP-CLI via bin/env.sh (run from Git Bash). Side effects: removed at the end (consent-helper.php cleanup).
import { execSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const out = '_dev/screens/theme';
const helper = (args) => execSync(`bash -c "source bin/env.sh >/dev/null 2>&1; wp eval-file _dev/tests/e2e/consent-helper.php ${args} 2>/dev/null"`, { encoding: 'utf8' }).trim();
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const errors = [];
const fresh = async (opts = {}) => {
	const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, ...opts });
	const page = await ctx.newPage();
	page.on('pageerror', (e) => errors.push(String(e)));
	return { ctx, page };
};
const stat = (p) => p.evaluate(() => window.thStat || 0);
const mkt = (p) => p.evaluate(() => window.thMkt || 0);

helper('cleanup');

/* ---------------------------------------------------------------- nothing optional configured */
{
	const { ctx, page } = await fresh();
	const loaded = [];
	page.on('request', (r) => loaded.push(r.url()));
	await page.goto(base + '/');
	await page.waitForTimeout(800);
	check('no optional code → no bar, consent module not even downloaded', (await page.locator('[data-th-consent-bar]').isHidden()) && !loaded.some((u) => u.includes('components/consent.js')));
	await page.click('.th-footer [data-th-consent-open]');
	await page.click('.th-consent__details summary');
	check('footer “Cookie settings” opens the dialog with the cookies in use', await page.locator('#th-consent-dialog').isVisible() && /th_location/.test(await page.locator('#th-consent-dialog').innerText()));
	check('categories marked “Not in use”', (await page.locator('#th-consent-dialog .th-consent__cat', { hasText: 'Not in use' }).count()) === 3);
	await page.keyboard.press('Escape');
	await ctx.close();
}

const guideUrl = helper('setup');

/* ---------------------------------------------------------------- reject */
{
	const { ctx, page } = await fresh();
	await page.goto(base + '/');
	await page.waitForSelector('[data-th-consent-bar]:not([hidden])');
	check('bar shown on first visit, nothing optional ran', (await stat(page)) === 0 && (await mkt(page)) === 0);
	check('optional code is printed inert (text/plain), the noscript pixel dropped', (await page.locator('script[type="text/plain"][data-th-consent="statistics"]').count()) === 1 && (await page.locator('img[src*="pixel.gif"]').count()) === 0);
	const btns = await page.locator('.th-consent__actions .th-btn').evaluateAll((els) => els.map((e) => getComputedStyle(e).backgroundColor));
	check('“Reject optional” as prominent as “Accept all”', btns[1] === btns[2], btns.join(' / '));
	await page.screenshot({ path: `${out}/consent-bar-1280.png` });
	await page.click('.th-consent__actions [data-th-consent-reject]');
	check('reject → bar gone, nothing ran', (await page.locator('[data-th-consent-bar]').isHidden()) && (await stat(page)) === 0);
	await page.reload();
	await page.waitForTimeout(600);
	check('reload: choice remembered, still nothing', (await page.locator('[data-th-consent-bar]').isHidden()) && (await stat(page)) === 0);

	/* settings: statistics only */
	await page.click('.th-footer [data-th-consent-open]');
	await page.check('#th-consent-dialog [data-th-consent-cat="statistics"]', { force: true });
	await page.screenshot({ path: `${out}/consent-dialog-1280.png` });
	await page.click('#th-consent-dialog [data-th-consent-save]');
	await page.waitForTimeout(300);
	check('statistics on → its code runs at once (once), marketing still not', (await stat(page)) === 1 && (await mkt(page)) === 0);
	await page.reload();
	await page.waitForTimeout(600);
	check('reload: statistics runs from the stored choice', (await stat(page)) === 1 && (await mkt(page)) === 0);

	/* withdraw */
	await page.click('.th-footer [data-th-consent-open]');
	await page.uncheck('#th-consent-dialog [data-th-consent-cat="statistics"]', { force: true });
	await Promise.all([page.waitForNavigation(), page.click('#th-consent-dialog [data-th-consent-save]')]);
	await page.waitForTimeout(500);
	check('withdrawing consent reloads the page without the code', (await stat(page)) === 0);
	await ctx.close();
}

/* ---------------------------------------------------------------- accept all + embed */
{
	const { ctx, page } = await fresh();
	await page.goto(guideUrl);
	await page.waitForSelector('[data-th-consent-bar]:not([hidden])');
	check('embed: placeholder instead of the YouTube player', (await page.locator('.th-embed-gate').count()) === 1 && (await page.locator('iframe[src*="youtube"]').count()) === 0);
	await page.click('[data-th-embed-load]');
	await page.waitForTimeout(500);
	check('click loads that embed only (marketing code still blocked)', (await page.locator('.th-embed-gate').count()) === 0 && (await page.locator('figure.wp-block-embed').count()) === 1 && (await mkt(page)) === 0);
	await page.click('.th-consent__actions [data-th-consent-accept]');
	await page.waitForTimeout(300);
	check('accept all → statistics + marketing run', (await stat(page)) === 1 && (await mkt(page)) === 1);
	const cookie = (await ctx.cookies()).find((c) => c.name === 'th_consent');
	check('choice cookie: first-party, ~1 year, SameSite=Lax', !!cookie && cookie.sameSite === 'Lax' && cookie.expires * 1000 - Date.now() > 360 * 86400000);
	await ctx.close();
}

/* ---------------------------------------------------------------- mobile + no JS */
{
	const { ctx, page } = await fresh({ viewport: { width: 390, height: 844 }, isMobile: true });
	await page.goto(base + '/');
	await page.waitForSelector('[data-th-consent-bar]:not([hidden])');
	const bar = await page.locator('[data-th-consent-bar]').boundingBox();
	const nav = await page.locator('.th-bottom-nav').boundingBox();
	check('mobile: bar sits above the bottom nav, no overflow', bar.y + bar.height <= nav.y + 1 && (await page.evaluate(() => document.documentElement.scrollWidth)) <= 390);
	await page.screenshot({ path: `${out}/consent-bar-390.png` });
	await ctx.close();
	const nojs = await fresh({ javaScriptEnabled: false });
	await nojs.page.goto(base + '/');
	check('no JS: no bar, optional code inert', (await nojs.page.locator('[data-th-consent-bar]').isHidden()) && (await nojs.page.locator('script[type="text/plain"][data-th-consent]').count()) === 2);
	await nojs.ctx.close();
}

/* ---------------------------------------------------------------- focused footers */
{
	const { ctx, page } = await fresh();
	await page.goto(base + '/login/');
	check('login footer has “Cookie settings”', (await page.locator('.th-auth-footer [data-th-consent-open]').count()) === 1);
	await ctx.close();
}

check('no JS errors', errors.length === 0, errors.join(' | '));
await browser.close();
helper('cleanup');
console.log(failures ? `\n${failures} FAILED` : '\nAll passed');
process.exit(failures ? 1 : 0);
