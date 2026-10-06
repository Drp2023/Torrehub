// E2E: saved searches + alert e-mails (phase 7). node _dev/tests/e2e/search-alerts.mjs [baseUrl]
// Needs (local only): member tester/tester, seller user54, Mailpit on :10000, WP-CLI via bin/env.sh (run from Git Bash).
// Side effects: removed at the end (search-alerts-helper.php cleanup).
import { execSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const mailpit = 'http://localhost:10000/api/v1';
const out = '_dev/screens/theme';
const wp = (args) => execSync(`bash -c "source bin/env.sh >/dev/null 2>&1; wp ${args} 2>/dev/null"`, { encoding: 'utf8' }).trim();
const helper = (args) => wp(`eval-file _dev/tests/e2e/search-alerts-helper.php ${args}`);
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const mails = async (subject) => ((await (await fetch(`${mailpit}/search?query=${encodeURIComponent(`subject:"${subject}"`)}`)).json()).messages || []);
const mailText = async (msg) => (await (await fetch(`${mailpit}/message/${msg.ID}`)).json());
const errors = [];

helper('cleanup');
const archive = `${base}/listing-category/auto-moto-boats/?min_price=1000`;

/* ---------------------------------------------------------------- guest */
const gctx = await browser.newContext();
const guest = await gctx.newPage();
await guest.goto(archive);
const gbtn = guest.locator('.th-save-search a');
check('guest: “Save this search” leads to login and back', /\/login\/.*redirect_to=.*min_price/.test(decodeURIComponent(await gbtn.getAttribute('href'))));
await gctx.close();

/* ---------------------------------------------------------------- member saves */
const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();
page.on('pageerror', (e) => errors.push(String(e)));
await page.goto(base + '/login/');
await page.fill('#th-log', 'tester');
await page.fill('#th-pwd', 'tester');
await Promise.all([page.waitForURL(/my-account/), page.click('.th-auth-form [type="submit"]')]);
await page.goto(archive);
await page.click('.th-save-search a');
await page.waitForSelector('#th-save-search[open]');
check('dialog: name prefilled, summary shows the filters', (await page.inputValue('#th-save-search-label')) !== '' && /Auto/.test(await page.locator('.th-save-search__summary').innerText()));
await page.screenshot({ path: `${out}/alerts-dialog-1280.png` });
await page.fill('#th-save-search-label', 'Cars over 1000');
await Promise.all([page.waitForURL(/th_alert=saved/), page.click('#th-save-search [type="submit"]')]);
check('saved → “Search saved” + notice', /Search saved/.test(await page.locator('.th-save-search').innerText()) && /We’ll e-mail you/.test(await page.locator('.th-save-search').innerText()));

await page.goto(base + '/my-account/alerts/');
check('account: Saved searches lists it', /Cars over 1000/.test(await page.locator('.th-alerts').innerText()));
await page.screenshot({ path: `${out}/alerts-account-1280.png` });

/* ---------------------------------------------------------------- daily digest */
const before = (await mails('New listing for')).length + (await mails('new listings for')).length;
helper("publish 'E2E alert Golf' 6500");
helper("publish 'E2E alert Cheap' 300"); // Below min_price: must not be announced.
helper('backdate');
wp('cron event run th_search_alerts_digest');
await new Promise((r) => setTimeout(r, 1500));
const digest = (await mails('New listing for')).concat(await mails('new listings for'));
check('daily digest sent once', digest.length === before + 1, `${before} → ${digest.length}`);
const msg = digest.length ? await mailText(digest[0]) : { Text: '', HTML: '' };
check('digest has the matching listing, not the cheap one', /E2E alert Golf/.test(msg.Text) && !/E2E alert Cheap/.test(msg.Text));
const headers = digest.length ? await (await fetch(`${mailpit}/message/${digest[0].ID}/headers`)).json() : {};
check('List-Unsubscribe (+ one-click) headers', !!headers['List-Unsubscribe'] && !!headers['List-Unsubscribe-Post']);
wp('cron event run th_search_alerts_digest');
await new Promise((r) => setTimeout(r, 1000));
check('no second digest within the day', (await mails('New listing for')).concat(await mails('new listings for')).length === before + 1);

/* ---------------------------------------------------------------- instant */
await page.goto(base + '/my-account/alerts/');
await page.selectOption('.th-alert-row select[name="frequency"]', 'instant');
await Promise.all([page.waitForURL(/th_alert=updated/), page.click('.th-alert-row__form [type="submit"]')]);
check('frequency changed to Instantly', (await page.inputValue('.th-alert-row select[name="frequency"]')) === 'instant');
const before2 = (await mails('New listing for')).length;
helper("publish 'E2E alert Polo' 4200");
wp('cron event run th_search_alerts_instant');
await new Promise((r) => setTimeout(r, 1500));
const instant = await mails('New listing for');
check('instant e-mail for the new listing', instant.length === before2 + 1 && /E2E alert Polo/.test((await mailText(instant[0])).Text));

/* ---------------------------------------------------------------- unsubscribe */
const text = (await mailText(instant[0])).Text;
const off = (text.match(/Stop these e-mails: (\S+)/) || [])[1];
check('e-mail has the unsubscribe link', !!off, off);
const unsub = await browser.newPage();
await unsub.goto(off);
check('unsubscribe page: “E-mails stopped”', /E-mails stopped/.test(await unsub.locator('main').innerText()));
await unsub.screenshot({ path: `${out}/alerts-unsubscribed-1280.png` });
await page.goto(base + '/my-account/alerts/');
check('account shows it Off', (await page.inputValue('.th-alert-row select[name="frequency"]')) === 'off');
await Promise.all([unsub.waitForNavigation(), unsub.click('main form [type="submit"]')]);
check('undo turns it back on (daily)', /E-mails back on/.test(await unsub.locator('main').innerText()));
const oneClick = await unsub.request.post(off);
check('one-click POST unsubscribe answers 200', oneClick.status() === 200);
const bad = await unsub.request.get(off.replace(/t=[a-f0-9]+/, 't=' + '0'.repeat(32)));
check('wrong token → 404, nothing changed', bad.status() === 404);
await unsub.close();

/* ---------------------------------------------------------------- delete + mobile */
await page.goto(base + '/my-account/alerts/');
page.once('dialog', (d) => d.accept());
await Promise.all([page.waitForURL(/th_alert=deleted/), page.click('.th-alert-row form[data-th-confirm] [type="submit"]')]);
check('delete removes it', (await page.locator('.th-alert-row').count()) === 0);
const mob = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
const mpage = await mob.newPage();
await mpage.goto(archive);
check('mobile archive head: no overflow', (await mpage.evaluate(() => document.documentElement.scrollWidth)) <= 390);
await mob.close();

check('no JS errors', errors.length === 0, errors.join(' | '));
await browser.close();
helper('cleanup');
console.log(failures ? `\n${failures} FAILED` : '\nAll passed');
process.exit(failures ? 1 : 0);
