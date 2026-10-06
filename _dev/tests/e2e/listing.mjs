// E2E: single listing (phase 4). node _dev/tests/e2e/listing.mjs [baseUrl]
// Needs a local visitor account tester/tester (role customer) that doesn't own the listing.
// Side effects (local only): one pending review + one report on the listing, one enquiry e-mail in Mailpit.
// Clean up with: wp eval-file _dev/tests/e2e/listing-cleanup.php
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const slug = '/listings/amrit-restaurant/';
const mailpit = 'http://localhost:10000/api/v1/messages?limit=1';
const out = '_dev/screens/theme';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const mailCount = async () => (await (await fetch(mailpit)).json()).total;

/* ---------------------------------------------------------------- guest, desktop */
{
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	await page.goto(base + slug, { waitUntil: 'networkidle' });

	check('one H1 = listing title', (await page.locator('h1').count()) === 1);
	const html = await page.content();
	check('phone number is not in the HTML before reveal', !/href="tel:/.test(html));
	const ld = await page.$$eval('script[type="application/ld+json"]', (s) => s.map((x) => JSON.parse(x.textContent)));
	const item = ld[0]?.['@graph']?.[0];
	check('JSON-LD: Restaurant with name and address', item?.['@type'] === 'Restaurant' && !!item.name && !!item.address, item?.['@type']);
	check('JSON-LD: AggregateRating matches the page', !item?.aggregateRating || item.aggregateRating.reviewCount >= 1);
	check('no identity numbers on the page (NIF/VIN/plate fields hidden)', !/>\s*(NIF|VIN|License Plate)/i.test(html));

	const phoneBtn = page.locator('.th-listing-contact [data-th-reveal="phone"]');
	if (await phoneBtn.count()) {
		await phoneBtn.click();
		await page.waitForSelector('.th-listing-contact a[href^="tel:"]');
		check('Show phone number reveals a tel: link', (await page.locator('.th-listing-contact a[href^="tel:"]').count()) === 1);
	}
	check('guest sees the "need an account" e-mail note', (await page.locator('.th-listing-contact__note').count()) === 1);
	check('guest "Write a review" goes to login', /wp-login\.php|\/login\//.test((await page.locator('#reviews .th-btn').first().getAttribute('href')) || ''));

	// Gallery lightbox.
	const photos = await page.locator('[data-th-gallery-index]').count();
	if (photos) {
		await page.locator('[data-th-gallery-index]').first().click();
		check('photo opens the lightbox (modal)', await page.evaluate(() => document.getElementById('th-lightbox').matches(':modal')));
		const c1 = await page.locator('[data-th-lightbox-counter]').textContent();
		if (photos > 1) {
			await page.keyboard.press('ArrowRight');
			const c2 = await page.locator('[data-th-lightbox-counter]').textContent();
			check('ArrowRight shows the next photo', c1 !== c2, `${c1} → ${c2}`);
		}
		await page.keyboard.press('Escape');
		check('Esc closes the lightbox', !(await page.evaluate(() => document.getElementById('th-lightbox').open)));
	}

	// Hours + map.
	if (await page.locator('#hours').count()) {
		check('today is marked in the hours table', (await page.locator('#hours tr[aria-current="date"]').count()) === 1);
	}
	if (await page.locator('[data-th-map-single]').count()) {
		await page.locator('#location').scrollIntoViewIfNeeded();
		await page.waitForSelector('[data-th-map-single].leaflet-container', { timeout: 10000 }).catch(() => {});
		check('map loads when scrolled into view', (await page.locator('[data-th-map-single].leaflet-container').count()) === 1);
	}
	// Tabs follow the scroll.
	await page.evaluate(() => document.getElementById('reviews').scrollIntoView({ block: 'start' }));
	await page.waitForTimeout(500);
	check('tabs mark the section in view', (await page.locator('[data-th-tabs] a[aria-current="true"]').getAttribute('href')) === '#reviews');
	await page.screenshot({ path: `${out}/listing-guest-1440.png`, fullPage: true });
	check('no JS errors (guest)', errors.length === 0, errors.join(' | '));
	await ctx.close();
}

/* ---------------------------------------------------------------- logged-in visitor */
{
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(String(e)));
	await page.goto(base + '/wp-login.php');
	await page.fill('#user_login', 'tester');
	await page.fill('#user_pass', 'tester');
	await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
	await page.goto(base + slug, { waitUntil: 'networkidle' });

	// E-mail enquiry → Mailpit.
	const before = await mailCount();
	await page.fill('#th-enquiry-message', 'Hello, is the terrace open on Sunday evenings? (E2E test)');
	await page.click('[data-th-enquiry] [type="submit"]');
	await page.waitForFunction(() => /sent/i.test(document.querySelector('[data-th-enquiry-status]')?.textContent || ''), null, { timeout: 10000 }).catch(() => {});
	check('enquiry: success message', /sent/i.test((await page.locator('[data-th-enquiry-status]').textContent()) || ''));
	await page.waitForTimeout(1000);
	const after = await mailCount();
	check('enquiry: e-mail delivered (Mailpit)', after > before, `${before} → ${after}`);

	// Review (pending moderation).
	const write = page.locator('#reviews [data-th-dialog-open="th-review-dialog"]');
	if (await write.count()) {
		await write.click();
		check('review dialog opens', await page.evaluate(() => document.getElementById('th-review-dialog').matches(':modal')));
		await page.locator('label[for="th-star-4"]').click();
		await page.fill('#th-review-text', 'Lovely terrace and friendly staff, we will be back. (E2E test)');
		await Promise.all([page.waitForURL(/th_review=/), page.click('#th-review-dialog [type="submit"]')]);
		check('review submitted → waiting for moderation', /th_review=pending/.test(page.url()), page.url());
		check('confirmation notice shown', (await page.locator('.th-alert').count()) >= 1);
	} else {
		check('review: already reviewed (rerun)', (await page.locator('#reviews .th-listing-reviews__none').count()) >= 1);
	}

	// Report.
	const report = page.locator('.th-listing-head__report[data-th-dialog-open]');
	if (await report.count()) {
		await report.click();
		await page.locator('#th-report input[value="wrong"]').check();
		await page.click('#th-report [type="submit"]');
		await page.waitForTimeout(800);
		check('report sent: dialog closes', !(await page.evaluate(() => document.getElementById('th-report')?.open)));
		await page.reload();
		check('after reload: "Reported" state', (await page.locator('.th-listing-head__report.is-done').count()) === 1);
	} else {
		check('report: already reported (rerun)', (await page.locator('.th-listing-head__report.is-done').count()) === 1);
	}
	check('no JS errors (logged in)', errors.length === 0, errors.join(' | '));
	await ctx.close();
}

/* ---------------------------------------------------------------- mobile */
{
	const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 });
	const page = await ctx.newPage();
	await page.goto(base + slug, { waitUntil: 'networkidle' });
	const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
	check('mobile: no horizontal overflow', overflow === 0, `${overflow}px`);
	check('mobile: sticky contact bar visible', await page.locator('[data-th-contact-bar]').isVisible());
	check('mobile: bottom nav hidden behind the contact bar', !(await page.locator('.th-bottom-nav').isVisible().catch(() => false)));
	await page.locator('[data-th-contact-bar] a').first().click();
	await page.waitForSelector('[data-th-sheet-phone][href^="tel:"]', { timeout: 8000 }).catch(() => {});
	check('mobile: Call opens the sheet with the number', (await page.locator('[data-th-sheet-phone][href^="tel:"]').count()) === 1);
	await page.screenshot({ path: `${out}/listing-sheet-390.png` });
	await ctx.close();
}

/* ---------------------------------------------------------------- no JS */
{
	const ctx = await browser.newContext({ javaScriptEnabled: false });
	const page = await ctx.newPage();
	await page.goto(base + slug);
	const href = await page.locator('.th-listing-contact [data-th-reveal="phone"]').getAttribute('href');
	await page.goto(href.startsWith('http') ? href : base + href);
	check('no-JS: ?th_contact=1 renders the tel: link', (await page.locator('.th-listing-contact a[href^="tel:"]').count()) === 1);
	await ctx.close();
}

await browser.close();
console.log(failures ? `\n${failures} FAILED` : '\nALL PASSED');
process.exit(failures ? 1 : 0);
