// E2E: listing form workspace (phase 6). node _dev/tests/e2e/listing-form.mjs [baseUrl]
// Needs (local only): business seller user54/seller54, member tester/tester.
// Side effects: listings titled "E2E …" by user54 (undo: wp eval-file _dev/tests/e2e/listing-form-cleanup.php).
import os from 'node:os';
import path from 'node:path';
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const out = '_dev/screens/theme';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
async function login(ctx, user, pass) {
	const page = await ctx.newPage();
	await page.goto(base + '/login/');
	await page.fill('#th-log', user);
	await page.fill('#th-pwd', pass);
	await Promise.all([page.waitForURL(/my-account/), page.click('.th-auth-form [type="submit"]')]);
	return page;
}

// A real 640×480 PNG for the uploader (a screenshot is the simplest image generator at hand).
const photo = path.join(os.tmpdir(), 'th-lf-photo.png');
{
	const p = await browser.newPage({ viewport: { width: 640, height: 480 } });
	await p.setContent('<body style="margin:0;background:linear-gradient(135deg,#0056b3,#ff8a00)"></body>');
	await p.screenshot({ path: photo });
	await p.close();
}

/** Fill every visible, empty control with something valid (generic: works for all forms). */
async function fillAll(page) {
	await page.evaluate(() => {
		const form = document.querySelector('[data-th-listing-form]');
		const fire = (el) => el.dispatchEvent(new Event('change', { bubbles: true }));
		for (let pass = 0; pass < 3; pass++) {
			form.querySelectorAll('[data-th-lf-field]:not([hidden])').forEach((field) => {
				if (field.closest('[data-th-off]')) return;
				field.querySelectorAll('input:not(:disabled), select:not(:disabled), textarea:not(:disabled)').forEach((el) => {
					if (el.closest('[data-th-off]') || el.closest('template')) return;
					if (el.type === 'radio') {
						const group = form.querySelectorAll(`input[type="radio"][name="${CSS.escape(el.name)}"]`);
						if (![...group].some((r) => r.checked)) { group[0].checked = true; fire(group[0]); }
						return;
					}
					if (el.type === 'checkbox') {
						if (el.name === 'rtcl_agree' || /\[\]$/.test(el.name)) { if (!el.checked && (el.name === 'rtcl_agree' || el === field.querySelector('input[type=checkbox]'))) { el.checked = true; fire(el); } }
						return;
					}
					if (el.type === 'hidden' || el.type === 'file' || el.value || el.dataset.thLfUrl !== undefined) return;
					if (el.tagName === 'SELECT') { el.selectedIndex = el.options.length > 1 ? 1 : 0; fire(el); return; }
					const v = { email: 'e2e@example.com', url: 'https://example.com', tel: '+34 600 000 000', number: '2020', date: '2026-11-20', 'datetime-local': '2026-11-20T18:30', time: '10:00' }[el.type];
					el.value = v || (el.name === 'title' ? 'E2E listing' : (el.dataset.thLfPrice !== undefined ? '120' : 'E2E value'));
					fire(el);
				});
			});
		}
	});
}

/* ---------------------------------------------------------------- access */
const gctx = await browser.newContext();
const guest = await gctx.newPage();
await guest.goto(base + '/listing-form/');
check('guest → login page (returns to the form)', /\/login\/.*redirect_to=.*listing-form/.test(decodeURIComponent(guest.url())), guest.url());
await gctx.close();

const mctx = await browser.newContext();
const member = await login(mctx, 'tester', 'tester');
const mres = await member.goto(base + '/listing-form/');
check('member → 403 "needs a seller account"', mres.status() === 403 && /seller account/i.test(await member.locator('main').innerText()));
await mctx.close();

/* ---------------------------------------------------------------- seller: drill-in → car */
const sctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await login(sctx, 'user54', 'seller54');
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));
page.on('console', (m) => m.type() === 'error' && !/favicon|tile\.openstreetmap/.test(m.text()) && errors.push(m.text()));

await page.goto(base + '/listing-form/');
check('S-02: 10 root categories', (await page.locator('.th-lf-roots > li').count()) === 10);
await page.click('.th-lf-roots a:has-text("Auto")');
await page.click('.th-lf-pick:has-text("Vehicles for Sale")');
check('S-02 drill-in shows Cars / Motorcycles / Boats', (await page.locator('.th-lf-pick').allInnerTexts()).join('|').match(/Cars.*Motorcycles|Boats/s) !== null);
await page.screenshot({ path: `${out}/lf-drill-1440.png` });
await page.click('.th-lf-pick:has-text("Cars")');
await page.waitForSelector('[data-th-listing-form][data-th-ready]');
check('workspace: Car preselected for "Cars"', await page.locator('input[value="car"]').isChecked());
check('workspace: Motorcycle + Boat sections "Hidden"', (await page.locator('.th-step-pill.is-hidden').count()) === 2);
check('workspace: no React form / editor loaded', (await page.locator('#rtcl-form-builder, script[src*="form-builder"], script[src*="tinymce"]').count()) === 0);

// Validation: Publish straight away.
await page.click('[data-th-lf-publish]');
check('empty publish → errors + alert', (await page.locator('.th-lf-field.is-invalid').count()) > 0 && (await page.locator('[data-th-lf-alert] .th-alert').count()) === 1);
check('error is linked to its control', await page.evaluate(() => {
	const f = document.querySelector('.th-lf-field.is-invalid');
	const err = f?.querySelector('[data-th-lf-error]');
	return !!err && !!f.querySelector(`[aria-describedby~="${err.id}"]`);
}));

// Section 1
await page.click('[data-th-lf-goto]:first-child');
await page.fill('[name="text_moh5gq8e"]', '1234 ABC');
await page.click('[data-th-lf-next]');
// Details
await page.fill('[name="title"]', 'E2E Volkswagen Golf 1.6 TDI');
await page.fill('[name="description"]', 'One owner.\n\nFull service history.');
await page.setInputFiles('[data-th-uploader-input]', photo);
await page.waitForSelector('.th-lf-photo[data-id]', { timeout: 30000 });
check('photo uploaded → tile with Cover badge', (await page.locator('.th-lf-photo[data-cover]').count()) === 1);
check('first upload created a draft (URL ?th_draft=)', /th_draft=\d+/.test(page.url()), page.url());
await page.waitForSelector('[data-th-lf-status].is-saved', { timeout: 10000 });
check('header shows "Draft saved HH:MM"', /Draft saved \d/.test(await page.locator('[data-th-lf-status]').innerText()));
check('live preview: title + cover', (await page.locator('[data-th-lf-preview-title]').innerText()).includes('Golf') && (await page.locator('[data-th-lf-preview-cover] img').count()) === 1);
await page.fill('[data-th-lf-price]', '12450');
// Oversized/wrong file → error banner, no upload.
await page.setInputFiles('[data-th-uploader-input]', { name: 'notes.txt', mimeType: 'text/plain', buffer: Buffer.from('x') });
check('wrong file type → error banner', await page.locator('[data-th-uploader-error]').isVisible());
await page.screenshot({ path: `${out}/lf-photos-1440.png` });
await page.click('[data-th-lf-next]');
// Car Specs
await page.selectOption('[name="select_mp108r7c"]', 'sale');
await page.fill('[name="text_mojy1qy5"]', 'Volkswagen');
await page.fill('[name="text_mnwqkc1e"]', 'Golf');
await page.fill('[name="number_mo47iwil"]', '2018');
await page.check('input[name="radio_mo47nhca"][value="manual"]', { force: true });
await page.click('[data-th-lf-next]');
// Location: town + pin
await page.selectOption('[data-th-lf-town]', { label: 'Torrevieja' });
await page.waitForSelector('.th-lf-pin__map .leaflet-tile-loaded, .th-lf-pin__map.leaflet-container', { timeout: 15000 });
const box = await page.locator('[data-th-pin-map]').boundingBox();
await page.mouse.click(box.x + box.width * 0.6, box.y + box.height * 0.4);
const lat = await page.inputValue('[data-th-pin-lat]');
check('map click places a pin (lat/lng filled)', /^3[78]\.\d+/.test(lat), lat);
await page.screenshot({ path: `${out}/lf-pin-1440.png` });
await page.click('[data-th-lf-next]');
// Contact
await page.check('[name="rtcl_agree"]', { force: true });
await page.screenshot({ path: `${out}/lf-contact-1440.png`, fullPage: true });
const whyNot = () => page.evaluate(() => [...document.querySelectorAll('.th-lf-field.is-invalid')].map((f) => `${f.dataset.thLfField}: ${f.querySelector('[data-th-lf-error]').innerText}`).join('; ') + ' ' + (document.querySelector('[data-th-lf-alert]')?.innerText || ''));
const [published] = await Promise.all([page.waitForURL(/th_submitted=\d+/, { timeout: 30000 }).then(() => true).catch(() => false), page.click('[data-th-lf-submit]')]);
if (!published) {
	console.log('publish failed:', await whyNot());
	await page.screenshot({ path: `${out}/lf-fail-1440.png`, fullPage: true });
	process.exit(1);
}
const id = Number(new URL(page.url()).searchParams.get('th_submitted'));
check('publish → S-18 "Listing submitted"', /Listing submitted/.test(await page.locator('main').innerText()), page.url());
await page.screenshot({ path: `${out}/lf-submitted-1440.png` });

// The saved listing (pending, owner view).
await page.goto(`${base}/?post_type=rtcl_listing&p=${id}`);
const single = page.locator('[data-th-map-single]');
check('listing page: title saved', /E2E Volkswagen Golf/.test(await page.locator('h1').innerText()));
check('listing page: exact pin on the map (not the town approximation)', (await single.count()) === 1 && (await single.getAttribute('data-approx')) !== '1' && Math.abs(Number(await single.getAttribute('data-lat')) - Number(lat)) < 0.0001);
check('listing page: Make shown in details', /Volkswagen/.test(await page.locator('main').innerText()));

// Edit
await page.goto(`${base}/listing-form/edit/${id}/`);
await page.waitForSelector('[data-th-listing-form][data-th-ready]');
check('edit: values loaded (title, make, pin)', (await page.inputValue('[name="title"]')).includes('Golf') && (await page.inputValue('[name="text_mojy1qy5"]')) === 'Volkswagen' && (await page.inputValue('[data-th-pin-lat]')) !== '');
check('edit: header says "Edit listing", no category change', /Edit listing/.test(await page.locator('.th-lf-header__title').innerText()) && (await page.locator('[data-th-lf-change]').count()) === 0);
await page.click('[data-th-lf-goto]:nth-child(1)');
await page.locator('[data-th-lf-goto]').nth(1).click();
await page.fill('[name="title"]', 'E2E Volkswagen Golf 1.6 TDI (edited)');
await Promise.all([page.waitForURL(/th_updated=1/, { timeout: 30000 }), page.click('[data-th-lf-publish]')]);
check('edit → "Changes submitted"', /Changes (submitted|saved)/.test(await page.locator('main').innerText()));

/* ---------------------------------------------------------------- drafts */
await page.goto(base + '/listing-form/?th_cat=41');
await page.waitForSelector('[data-th-listing-form][data-th-ready]');
await page.locator('[data-th-lf-goto]').nth(1).click();
await page.fill('[name="title"]', 'E2E draft only');
await Promise.all([page.waitForURL(/my-account\/listings/), page.click('[data-th-lf-exit]')]);
check('Save & exit → My listings, draft listed there', /my-account\/listings/.test(page.url()) && /E2E draft only/.test(await page.locator('.th-drafts').innerText()));
await page.goto(base + '/listing-form/');
check('choose screen lists the draft', /E2E draft only/.test(await page.locator('.th-drafts').innerText()));
await page.click('.th-draft__link:has-text("E2E draft only")');
await page.waitForSelector('[data-th-listing-form][data-th-ready]');
check('draft reopens with its values', (await page.inputValue('[name="title"]')) === 'E2E draft only');

/* ---------------------------------------------------------------- one listing per root category */
const roots = await (async () => {
	await page.goto(base + '/listing-form/');
	return page.locator('.th-lf-roots a').evaluateAll((as) => as.map((a) => ({ name: a.innerText.split('\n')[0], url: a.href })));
})();
for (const root of roots) {
	let url = root.url;
	// Drill down through the first subcategory until the workspace opens.
	for (let depth = 0; depth < 4; depth++) {
		await page.goto(url);
		const pick = page.locator('.th-lf-pick').first();
		if (!(await pick.count())) break;
		url = await pick.getAttribute('href');
	}
	await page.waitForSelector('[data-th-listing-form][data-th-ready]', { timeout: 10000 }).catch(() => {});
	if (!(await page.locator('[data-th-listing-form]').count())) {
		check(`category ${root.name}: workspace opens`, false, page.url());
		continue;
	}
	await page.evaluate((t) => { document.querySelector('[name="title"]').value = t; }, `E2E ${root.name}`);
	await fillAll(page);
	const [ok] = await Promise.all([
		page.waitForURL(/th_submitted=\d+/, { timeout: 20000 }).then(() => true).catch(() => false),
		page.click('[data-th-lf-publish]'),
	]);
	const why = ok ? '' : await page.evaluate(() => [...document.querySelectorAll('.th-lf-field.is-invalid')].map((f) => `${f.dataset.thLfField}: ${f.querySelector('[data-th-lf-error]').innerText}`).join('; ') + ' ' + (document.querySelector('[data-th-lf-alert]')?.innerText || ''));
	check(`category ${root.name}: published through Classified Listing`, ok, why);
}

/* ---------------------------------------------------------------- mobile */
const mob = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const mpage = await login(mob, 'user54', 'seller54');
await mpage.goto(base + '/listing-form/?th_cat=41');
await mpage.waitForSelector('[data-th-listing-form][data-th-ready]');
check('mobile 390: no horizontal overflow', (await mpage.evaluate(() => document.documentElement.scrollWidth)) <= 390);
await mpage.screenshot({ path: `${out}/lf-ws-390.png` });
await mob.close();

check('no JS errors', errors.length === 0, errors.join(' | '));
await browser.close();
console.log(failures ? `\n${failures} FAILED` : '\nAll passed');
process.exit(failures ? 1 : 0);
