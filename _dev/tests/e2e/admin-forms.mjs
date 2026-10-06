// E2E: listing forms stay editable in wp-admin without Classified Listing Pro (phase 7).
//   wp eval-file _dev/tests/e2e/admin-forms-snapshot.php
//   node _dev/tests/e2e/admin-forms.mjs [baseUrl]
//   wp eval-file _dev/tests/e2e/admin-forms-cleanup.php
// Needs (local only): admin dev/dev; Pro plugins inactive.
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));
await page.goto(base + '/wp-login.php');
await page.fill('#user_login', 'dev');
await page.fill('#user_pass', 'dev');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

/** Hover a field in the builder canvas and open its settings (the toolbar's edit button). */
async function openField(label) {
	const el = page.getByText(label, { exact: true }).first();
	await el.scrollIntoViewIfNeeded();
	await el.hover();
	const box = await el.boundingBox();
	await page.mouse.move(box.x + 300, box.y + 35);
	await page.waitForTimeout(300);
	const tools = await page.evaluate((y) => [...document.querySelectorAll('button, span, div')].filter((n) => {
		const r = n.getBoundingClientRect();
		return r.width > 20 && r.width < 40 && r.height > 20 && r.height < 40 && Math.abs(r.top - y) < 60 && n.querySelector('svg');
	}).map((n) => { const r = n.getBoundingClientRect(); return [r.x + r.width / 2, r.y + r.height / 2]; }), box.y + 35);
	const edit = tools.length >= 4 ? tools[tools.length - 3] : tools[1];
	await page.mouse.click(edit[0], edit[1]);
	await page.waitForTimeout(800);
}
async function save() {
	const res = page.waitForResponse((r) => r.url().includes('admin-ajax.php') && (r.request().postData() || '').includes('rtcl_fb_admin_form_update'));
	await page.getByText('Save Form', { exact: true }).click();
	return (await res).json();
}

/* ---------------------------------------------------------------- builder */
await page.goto(base + '/wp-admin/admin.php?page=rtcl-fb');
await page.waitForSelector('text=Auto/Moto/Boats');
check('Form Builder lists the 10 forms (no Pro)', (await page.locator('tr', { hasText: 'Published' }).count()) === 10);

await page.goto(base + '/wp-admin/admin.php?page=rtcl-fb#/editor/3', { waitUntil: 'networkidle' });
await page.waitForSelector('text=Service Branding');
check('Service form editor shows the repeater (Amenities)', await page.getByText('Amenities', { exact: true }).first().isVisible());

await page.goto(base + '/wp-admin/admin.php?page=rtcl-fb#/editor/5', { waitUntil: 'networkidle' });
await page.reload({ waitUntil: 'networkidle' });
await page.waitForSelector('text=Item Type');
await openField('Item Type');
const option = page.locator('input[type="text"][value="Motorbike"]');
check('option editor available', (await option.count()) === 1);
await option.fill('Motorbike / scooter');
const section = page.getByText('Car Specs', { exact: true }).first();
await section.scrollIntoViewIfNeeded();
const sb = await section.boundingBox();
const pencil = await page.evaluate((y) => [...document.querySelectorAll('button, span, div')].filter((n) => {
	const r = n.getBoundingClientRect();
	return r.width > 15 && r.width < 40 && Math.abs(r.top + r.height / 2 - y) < 14 && r.x > 700 && n.querySelector('svg');
}).map((n) => { const r = n.getBoundingClientRect(); return [r.x + r.width / 2, r.y + r.height / 2]; }), sb.y + sb.height / 2);
await page.mouse.click(pencil[0][0], pencil[0][1]);
await page.waitForTimeout(800);
check('section settings include Conditional Logic', await page.getByText('Conditional Logic', { exact: true }).first().isVisible());
await page.locator('input[value="Car Specs"]').first().fill('Car specifications');
const saved = await save();
const fields = saved?.data?.data?.fields || {};
const itemType = Object.values(fields).find((f) => f.name === 'select_mo48xoy6');
const moto = itemType?.options?.find((o) => o.label === 'Motorbike / scooter');
check('save succeeds', saved?.success === true);
check('renamed option keeps its stored value "Motorbike" (theme guard)', moto?.value === 'Motorbike', JSON.stringify(moto));
check('section title saved', (saved?.data?.data?.sections || []).some((s) => s.title === 'Car specifications'));
check('repeater and conditions untouched', Object.values(fields).length === 49 && (saved?.data?.data?.sections || []).filter((s) => s.logics && s.logics.status).length === 3);
const log = await page.request.get(base + '/wp-content/rtcl-fb-debug.log');
check('Classified Listing’s debug file is removed after the save', log.status() === 404, String(log.status()));

/* ---------------------------------------------------------------- front end after the rename */
const front = await browser.newPage();
await front.goto(base + '/login/');
await front.fill('#th-log', 'user54');
await front.fill('#th-pwd', 'seller54');
await Promise.all([front.waitForURL(/my-account/), front.click('.th-auth-form [type="submit"]')]);
await front.goto(base + '/listing-form/?th_cat=42'); // Motorcycles.
await front.waitForSelector('[data-th-listing-form][data-th-ready]');
check('listing form: renamed label, Motorcycle section still switches on', (await front.locator('input[value="Motorbike"]').isChecked()) && /Motorbike \/ scooter/.test(await front.locator('.th-lf-choices').innerText()) && (await front.locator('.th-step-pill:not(.is-hidden)', { hasText: 'Motorcycle' }).count()) === 1);
await front.close();

/* ---------------------------------------------------------------- card fields */
await page.goto(base + '/wp-admin/themes.php?page=torrehub-card-fields');
for (const uuid of ['mnwqsqip', 'mo47kwc1', 'mo47nhca']) {
	await page.check(`input[name="th_card[5][${uuid}]"]`);
}
await Promise.all([page.waitForURL(/th_msg=saved/), page.click('#submit')]);
check('Listing cards screen saves', await page.isChecked('input[name="th_card[5][mnwqsqip]"]'));
const archive = await browser.newPage();
await archive.goto(base + '/listing-category/auto-moto-boats/');
const attrs = await archive.locator('.th-card__attrs').allInnerTexts();
check('car card shows the ticked fields', attrs.some((t) => /Diesel/.test(t) && /Manual/.test(t)), attrs.join(' | ').replace(/\s+/g, ' '));
await archive.close();

check('no JS errors in the builder', errors.length === 0, errors.join(' | '));
await browser.close();
console.log(failures ? `\n${failures} FAILED` : '\nAll passed');
process.exit(failures ? 1 : 0);
