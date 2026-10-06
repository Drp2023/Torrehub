// E2E: my account + seller verification (phase 5). node _dev/tests/e2e/account.mjs [baseUrl]
// Needs (local only): business seller user54/seller54 with listings, member tester/tester, admin dev/dev.
// Side effects: user54 gets verified (undo: wp eval-file _dev/tests/e2e/account-cleanup.php).
import fs from 'node:fs';
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
// A tiny valid PNG as the "ID document".
const png = path.join(os.tmpdir(), 'th-id-test.png');
fs.writeFileSync(png, Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64'));
const fake = path.join(os.tmpdir(), 'th-id-test.php.png');
fs.writeFileSync(fake, '<?php echo "x";');

/* ---------------------------------------------------------------- seller */
const sctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const seller = await login(sctx, 'user54', 'seller54');
const errors = [];
seller.on('pageerror', (e) => errors.push(String(e)));
check('dashboard greets the seller', /Good (morning|afternoon|evening)/.test(await seller.locator('h1').innerText()));
check('dashboard shows Active / Views / Pending / Unread', /Active.*Views.*Pending.*Unread/is.test(await seller.locator('.th-account-stats').innerText()));
check('no NIE / NIF input anywhere in the account', (await seller.locator('input[name*="nif" i], input[name*="nie" i], input[name="custom_field_1"]').count()) === 0);
await seller.goto(base + '/my-account/edit-account/');
check('account details: no NIF/NIE field (snippet 7263 retired)', !/NIF\/NIE/.test(await seller.locator('main').innerText()));
const nav = await seller.locator('.th-account__link').allInnerTexts();
check('nav: Verification present, Logout last', nav.some((t) => /Verification/.test(t)) && /Log ?out/i.test(nav[nav.length - 1]), nav.join(' | '));

// Verification: wrong file type refused, real image accepted.
await seller.goto(base + '/my-account/verification/');
await seller.setInputFiles('#th-document', fake);
await seller.check('input[name="consent"]');
await Promise.all([seller.waitForURL(/th_v=/), seller.click('main form [type="submit"]')]);
check('a PHP file renamed .png is refused', /th_v=type/.test(seller.url()), seller.url());
await seller.setInputFiles('#th-document', png);
await seller.check('input[name="consent"]');
await Promise.all([seller.waitForURL(/th_v=/), seller.click('main form [type="submit"]')]);
check('ID image accepted → "In review"', /th_v=sent/.test(seller.url()) && /In review/.test(await seller.locator('main').innerText()));

/* ---------------------------------------------------------------- member */
const mctx = await browser.newContext();
const member = await login(mctx, 'tester', 'tester');
const mnav = await member.locator('.th-account__link').allInnerTexts();
check('member: no Verification / My listings in the nav', !mnav.some((t) => /Verification|My Listings/i.test(t)), mnav.join(' | '));
const forbidden = await member.request.get(base + '/wp-admin/admin-ajax.php?action=th_verification_file&user=54');
check('member can’t fetch a verification document', forbidden.status() >= 400 || !/PNG/.test(await forbidden.text()));
await mctx.close();

/* ---------------------------------------------------------------- admin review */
const actx = await browser.newContext();
const admin = await actx.newPage();
await admin.goto(base + '/wp-login.php');
await admin.fill('#user_login', 'dev');
await admin.fill('#user_pass', 'dev');
await Promise.all([admin.waitForNavigation(), admin.click('#wp-submit')]);
await admin.goto(base + '/wp-admin/users.php?page=th-verification');
const row = admin.locator('tr:has-text("user54@example.test")');
check('admin: request listed', (await row.count()) === 1);
const docHref = await row.locator('a:has-text("View document")').getAttribute('href');
const doc = await admin.request.get(docHref);
check('admin: document streams with nosniff', doc.status() === 200 && doc.headers()['x-content-type-options'] === 'nosniff' && /image\/png/.test(doc.headers()['content-type']));
const files = fs.readdirSync(path.resolve('../../uploads/th-private/verification')).filter((f) => f.endsWith('.png.php'));
check('stored outside the Media Library with a random, guarded name', files.length === 1 && /^[a-f0-9]{40}\.png\.php$/.test(files[0]), files.join(','));
const direct = await admin.request.get(`${base}/wp-content/uploads/th-private/verification/${files[0]}`);
const body = await direct.body();
check('direct URL to the file returns nothing', direct.status() >= 400 || body.length === 0, `${direct.status()} · ${body.length} bytes`);
await Promise.all([admin.waitForNavigation(), row.locator('button[value="approve"]').click()]);
check('admin: approve → notice', /verified/i.test(await admin.locator('.notice-success').innerText()));
const left = fs.readdirSync(path.resolve('../../uploads/th-private/verification')).filter((f) => f.endsWith('.png.php'));
check('document deleted after the decision', left.length === 0, left.join(','));
await actx.close();

/* ---------------------------------------------------------------- seller after approval */
await seller.goto(base + '/my-account/');
check('dashboard: Verified badge, no upload card', (await seller.locator('.th-account-dash__sub .th-badge').count()) === 1 && (await seller.locator('.th-account-verify').count()) === 0);
await seller.goto(base + '/listings/?verified=1&th_count=1');
check('archive "Verified only" now finds the seller’s listings', JSON.parse(await seller.locator('body').innerText()).count > 0);
await seller.goto(base + '/my-account/');
await seller.screenshot({ path: `${out}/account-dash-verified-1280.png`, fullPage: true });

// Delete one of my listings (to trash) — with the confirm dialog accepted.
await seller.goto(base + '/my-account/listings/');
const before = await seller.locator('.th-account-row').count();
seller.once('dialog', (d) => d.accept());
const title = await seller.locator('.th-account-row .th-dash-row__title').last().innerText();
await Promise.all([seller.waitForURL(/th_deleted=1/), seller.locator('.th-account-row__delete button').last().click()]);
check('delete → listing gone from my list', !(await seller.locator('.th-dash-row__title').allInnerTexts()).includes(title) && (await seller.locator('.th-account-row').count()) <= before, title);
fs.writeFileSync(path.join(os.tmpdir(), 'th-deleted-title.txt'), title);
check('no JS errors (seller)', errors.length === 0, errors.join(' | '));
await sctx.close();

/* ---------------------------------------------------------------- mobile */
const mob = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const mp = await login(mob, 'user54', 'seller54');
for (const u of ['/my-account/', '/my-account/listings/', '/my-account/verification/']) {
	await mp.goto(base + u);
	const w = await mp.evaluate(() => document.documentElement.scrollWidth);
	check(`mobile: ${u} fits 390 px`, w <= 390, `${w}px`);
}
await mob.close();

await browser.close();
console.log(failures ? `\n${failures} FAILED` : '\nALL PASSED');
process.exit(failures ? 1 : 0);
