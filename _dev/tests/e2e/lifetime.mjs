// E2E: listing lifetime by account type, renewal (My listings + e-mail link), reminder e-mail, FAQ (client decision 2026-10-08).
//   node _dev/tests/e2e/lifetime.mjs [baseUrl]
// Needs (local only): WP-CLI via bin/env.sh (Git Bash), Mailpit, admin dev/dev, business seller user54/seller54. Reverted by lifetime-helper.php cleanup.
import { execSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const out = '_dev/screens/theme';
const mailpit = 'http://localhost:10000/api/v1';
const wp = (cmd) => execSync(`source bin/env.sh >/dev/null 2>&1; wp ${cmd} 2>/dev/null`, { encoding: 'utf8', shell: 'bash' }).trim();
const helper = (arg) => wp(`eval-file _dev/tests/e2e/lifetime-helper.php ${arg}`);
const info = (id) => JSON.parse(helper(`info ${id}`));
const mails = async (q) => ((await (await fetch(`${mailpit}/search?query=${encodeURIComponent(q)}`)).json()).messages || []);
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const near = (v, target, tol = 0.1) => v !== null && Math.abs(v - target) <= tol;
const errors = [];
const login = async (ctx, user, pass) => {
	const p = await ctx.newPage();
	p.on('pageerror', (e) => errors.push(String(e)));
	await p.goto(base + '/wp-login.php');
	await p.fill('#user_login', user);
	await p.fill('#user_pass', pass);
	await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
	return p;
};

const t = JSON.parse(helper('setup'));

/* ---------------------------------------------------------------- lifetime by account type */
const priv = info(t.new.private);
const biz = info(t.new.business);
const staff = info(t.new.staff);
check('Private Seller listing going live runs 15 days', priv.status === 'publish' && near(priv.days, 15) && !priv.never, JSON.stringify(priv.days));
check('Business Seller listing runs 30 days', near(biz.days, 30) && !biz.never, JSON.stringify(biz.days));
check('staff listing never expires (DECISION)', staff.never && staff.days === null);

/* ---------------------------------------------------------------- settings in wp-admin */
const admin = await login(await browser.newContext({ viewport: { width: 1440, height: 1000 } }), 'dev', 'dev');
await admin.goto(base + '/wp-admin/themes.php?page=torrehub');
check('settings: Appearance › Torrehub › Listing lifetime (15 / 30 / 0 / 3)', (await admin.inputValue('#th-lifetime-private')) === '15' && (await admin.inputValue('#th-lifetime-business')) === '30' && (await admin.inputValue('#th-lifetime-staff')) === '0' && (await admin.inputValue('#th-lifetime-remind')) === '3');
await admin.fill('#th-lifetime-private', '20');
await Promise.all([admin.waitForNavigation(), admin.click('#submit')]);
check('changed lifetime is saved', wp('option pluck th_lifetime private') === '20');
const home = await admin.context().newPage();
await home.goto(base + '/');
check('home page text follows the setting', /Listings run 20 days \(30 for businesses\) and renew for free in one tap/.test(await home.locator('.th-home__sellers').innerText()));
await admin.goto(base + '/wp-admin/themes.php?page=torrehub');
await admin.fill('#th-lifetime-private', '15');
await Promise.all([admin.waitForNavigation(), admin.click('#submit')]);

/* ---------------------------------------------------------------- reminder e-mail */
const subject = `“${t.title}” ends on`;
const before = (await mails(`to:${t.email} subject:"${subject}"`)).length;
const beforeAll = (await mails(`to:${t.email} subject:"ends on"`)).length;
wp('cron event run th_listing_expiry_notices');
await new Promise((r) => setTimeout(r, 1000));
const sent = await mails(`to:${t.email} subject:"${subject}"`);
check('reminder e-mail to the owner of the listing ending in 2 days (only that one)', sent.length === before + 1 && (await mails(`to:${t.email} subject:"ends on"`)).length === beforeAll + 1, `${before} → ${sent.length}`);
const msg = sent.length ? await (await fetch(`${mailpit}/message/${sent[0].ID}`)).json() : { HTML: '' };
const link = (msg.HTML.match(/href="([^"]*th_renew=\d+[^"]*)"/) || [])[1]?.replace(/&(amp|#038);/g, '&') || '';
check('e-mail: renew link (signed), 30 days, “free”', link.includes(`th_renew=${t.a}`) && /Renew for 30 days/.test(msg.HTML) && /free/.test(msg.Text || ''));
wp('cron event run th_listing_expiry_notices');
await new Promise((r) => setTimeout(r, 800));
check('no second reminder for the same period', (await mails(`to:${t.email} subject:"${subject}"`)).length === before + 1);

/* ---------------------------------------------------------------- renew from the e-mail link */
const guest = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
const g = await guest.newPage();
g.on('pageerror', (e) => errors.push(String(e)));
const daysA = info(t.a).days;
await g.goto(link);
check('link opens a page with the Renew button (GET renews nothing)', /Renew your listing/.test(await g.locator('h1').innerText()) && near(info(t.a).days, daysA, 0.01));
await g.screenshot({ path: `${out}/renew-link-390.png` });
await Promise.all([g.waitForNavigation(), g.getByRole('button', { name: 'Renew for 30 days' }).click()]);
const a = info(t.a);
check('Renew: same number of days again, added to the remaining time', /Listing renewed/.test(await g.locator('h1').innerText()) && near(a.days, daysA + 30, 0.05), `${daysA} → ${a.days}`);
await g.goto(link);
check('link again: “Nothing to renew yet”', /Nothing to renew yet/.test(await g.locator('h1').innerText()));
const bad = await g.goto(link.replace(/t=[a-z0-9]+/, 't=0000'));
check('tampered link: 404, nothing renewed', bad.status() === 404 && /doesn’t work/.test(await g.locator('h1').innerText()));
await guest.close();

/* ---------------------------------------------------------------- My listings */
const s = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const sp = await s.newPage();
sp.on('pageerror', (e) => errors.push(String(e)));
await sp.goto(base + '/login/');
await sp.fill('#th-log', 'user54');
await sp.fill('#th-pwd', 'seller54');
await Promise.all([sp.waitForURL(/my-account/), sp.click('.th-auth-form [type="submit"]')]);
const row = (id) => sp.locator('.th-account-row', { has: sp.locator(`input[name="listing_id"][value="${id}"]`) });
const shown = () => sp.locator('.th-account-row input[name="listing_id"]').evaluateAll((els) => [...new Set(els.map((e) => e.value))].join(','));
// Status tabs: the "All" tab's first page depends on what the other suites left behind.
await sp.goto(base + '/my-account/listings/?status=publish');
check('listing with 20 days left: no Renew, “Runs until …”', (await row(t.c).count()) === 1 && (await row(t.c).getByRole('button', { name: /^Renew/ }).count()) === 0 && /Runs until/.test(await row(t.c).innerText()), `rows: ${await shown()}`);
await sp.goto(base + '/my-account/listings/?status=rtcl-expired');
check('ended listing: Renew button + “Renew by …”', (await row(t.b).count()) === 1 && (await row(t.b).getByRole('button', { name: /^Renew/ }).count()) === 1 && /Renew by/.test(await row(t.b).innerText()), `rows: ${await shown()}`);
const publishedB = info(t.b).published;
await sp.setViewportSize({ width: 390, height: 844 });
await row(t.b).screenshot({ path: `${out}/my-listings-renew-row-390.png` });
await sp.setViewportSize({ width: 1440, height: 1000 });
await Promise.all([sp.waitForNavigation(), row(t.b).getByRole('button', { name: /^Renew/ }).click()]);
const b = info(t.b);
check('one click: live again for 30 days, deletion date gone', new RegExp(`Renewed — “${t.titleB}” runs until`).test(await sp.locator('.th-alert').first().innerText()) && b.status === 'publish' && near(b.days, 30) && b.deletion === '', JSON.stringify(b));
check('renewal is not a “new listing” for saved searches (DECISION)', publishedB === '1700000000' && b.published === publishedB);
await sp.setViewportSize({ width: 390, height: 844 });
await sp.screenshot({ path: `${out}/my-listings-lifetime-390.png`, fullPage: true });
check('mobile: no horizontal overflow', (await sp.evaluate(() => document.documentElement.scrollWidth)) <= 390);

/* ---------------------------------------------------------------- the end comes from Classified Listing's cron */
helper(`expire ${t.c}`);
wp('cron event run rtcl_hourly_scheduled_events');
check('Classified Listing’s hourly cron ends it at the date the theme set', info(t.c).status === 'rtcl-expired');

/* ---------------------------------------------------------------- FAQ */
const faq = await s.newPage();
await faq.goto(base + '/faq/');
const faqText = (await faq.locator('main').textContent()).replace(/\s+/g, ' '); // Answers sit in closed <details>.
const ld = await faq.evaluate(() => [...document.querySelectorAll('script[type="application/ld+json"]')].map((x) => x.textContent).join(''));
check('FAQ: lifetime + renewal answers from the settings', /Private Seller runs 15 days, one of a Business Seller 30 days/.test(faqText) && /renew as often as you like/.test(faqText));
check('FAQPage JSON-LD carries the rendered answers', /How do I renew a listing\?/.test(ld) && /30 days/.test(ld) && !/\[torrehub_listing_lifetime/.test(ld));

check('no JS errors', errors.length === 0, errors.join(' | '));
await browser.close();
helper('cleanup');
console.log(failures ? `\n${failures} FAILED` : '\nAll passed');
process.exit(failures ? 1 : 0);
