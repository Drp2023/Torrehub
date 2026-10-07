// E2E: Tools › Torrehub migration (go-live steps from wp-admin, no SSH). node _dev/tests/e2e/migration.mjs [baseUrl]
// Needs (local only): WP-CLI via bin/env.sh (Git Bash), admin dev/dev, seller user54/seller54. Side effects reverted (migration-helper.php cleanup).
import { execSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const out = '_dev/screens/theme';
const wp = (cmd) => execSync(`source bin/env.sh >/dev/null 2>&1; wp ${cmd} 2>/dev/null`, { encoding: 'utf8', shell: 'bash' }).trim();
const helper = (arg) => wp(`eval-file _dev/tests/e2e/migration-helper.php ${arg}`);
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const tool = base + '/wp-admin/tools.php?page=torrehub-migration';
const card = (page, key) => page.locator(`#${key}`);
const run = async (page, key, label) => {
	await Promise.all([page.waitForNavigation(), card(page, key).getByRole('button', { name: label, exact: true }).click()]);
};
const pageCount = (status) => Number(wp(`post list --post_type=page --post_status=${status} --format=count`));

helper('setup');
const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));
page.on('dialog', (d) => d.accept());
await page.goto(base + '/wp-login.php');
await page.fill('#user_login', 'dev');
await page.fill('#user_pass', 'dev');
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

/* ---------------------------------------------------------------- page + data check */
await page.goto(tool);
check('Tools › Torrehub migration lists the 7 steps in runbook order', (await page.locator('.th-migration .card h3').allInnerTexts()).map((t) => t.split(' ')[0]).join(',') === 'C1,C2,C3,C4,C5,C6,D4');
check('“Apply” locked until a dry run', await card(page, 'trash-demo').getByRole('button', { name: 'Apply', exact: true }).isDisabled());
await run(page, 'data-check', 'Save as baseline (before)');
check('baseline saved', /Baseline saved/.test(await page.locator('#data-check').innerText()));

/* ---------------------------------------------------------------- dry run → apply */
const trashBefore = pageCount('trash');
await run(page, 'trash-demo', 'Dry run');
const dry = await card(page, 'trash-demo').locator('.th-migration__result').innerText();
check('dry run reports the demo items and changes nothing', /Would move to the trash: 19/.test(dry) && /home-one/.test(dry) && pageCount('trash') === trashBefore, dry.split('\n')[0]);
check('dry run unlocks “Apply”', await card(page, 'trash-demo').getByRole('button', { name: 'Apply', exact: true }).isEnabled());
await page.screenshot({ path: `${out}/migration-tool-1440.png`, fullPage: true });
await run(page, 'trash-demo', 'Apply');
check('apply writes: demo content in the trash', /Moved to the trash: 19/.test(await card(page, 'trash-demo').innerText()) && pageCount('trash') === trashBefore + 11);
check('after apply “Apply” is locked again', await card(page, 'trash-demo').getByRole('button', { name: 'Apply', exact: true }).isDisabled());
check('data check shows what changed', (await page.locator('.th-migration__changed').count()) >= 2);

await run(page, 'migrate-pages', 'Dry run');
check('migrate-pages dry run: 6 pages with word counts', /6 pages would be migrated/.test(await card(page, 'migrate-pages').innerText()));
await run(page, 'migrate-pages', 'Apply');
check('migrate-pages applied (FAQ uses the theme template)', /6 pages migrated/.test(await card(page, 'migrate-pages').innerText()) && wp('post meta get $(wp post list --post_type=page --name=faq --field=ID) _wp_page_template') === 'page-templates/faq.php');

/* ---------------------------------------------------------------- destructive step */
await run(page, 'purge-nie', 'Dry run');
check('purge-nie dry run counts the NIE', /1 NIE entry/.test(await card(page, 'purge-nie').innerText()));
const box = card(page, 'purge-nie').locator('input[name="th_backup"]');
check('GDPR step asks for the backup confirmation', (await box.count()) === 1 && (await box.getAttribute('required')) !== null);
await box.check();
await run(page, 'purge-nie', 'Apply');
check('purge-nie applied', /1 NIE entry deleted/.test(await card(page, 'purge-nie').innerText()) && wp("user meta get tester custom_field_1 || true") === '');

/* ---------------------------------------------------------------- guards */
const nonce = await card(page, 'import-chat').locator('input[name="_wpnonce"]').first().inputValue();
const forged = await page.request.post(base + '/wp-admin/admin-post.php', { form: { action: 'th_migration', th_do: 'apply', th_step: 'import-chat', _wpnonce: nonce } });
check('forged “Apply” without a dry run is refused', forged.ok() && /Run the dry run first/.test(await forged.text()));
wp('torrehub fix-option-values');
await page.goto(tool);
check('a WP-CLI run shows up in the log', /dry run .* \(wp-cli\)/.test(await card(page, 'fix-option-values').innerText()));

/* ---------------------------------------------------------------- maintenance */
await run(page, 'maintenance', 'Switch on');
check('maintenance on: admin notice + admin bar flag', (await page.locator('.notice-warning', { hasText: 'Maintenance mode is on' }).count()) === 1 && (await page.locator('#wp-admin-bar-th-maintenance').count()) === 1);
const guest = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
const g = await guest.newPage();
const res = await g.goto(base + '/listings/');
check('visitors get 503 “Back soon” (noindex, Retry-After)', res.status() === 503 && /Back soon/.test(await g.locator('h1').innerText()) && res.headers()['retry-after'] === '1800' && /noindex/.test(await g.locator('meta[name="robots"]').getAttribute('content')));
await g.screenshot({ path: `${out}/maintenance-390.png` });
check('the login page still works during maintenance', (await g.goto(base + '/login/')).status() === 200);
check('administrators see the site', (await page.goto(base + '/listings/')).status() === 200);
await page.goto(tool);
await run(page, 'maintenance', 'Switch off');
check('maintenance off: public again', (await g.goto(base + '/listings/')).status() === 200);
await guest.close();

/* ---------------------------------------------------------------- not for sellers */
const s = await browser.newContext();
const sp = await s.newPage();
await sp.goto(base + '/login/');
await sp.fill('#th-log', 'user54');
await sp.fill('#th-pwd', 'seller54');
await Promise.all([sp.waitForURL(/my-account/), sp.click('.th-auth-form [type="submit"]')]);
const denied = await sp.request.post(base + '/wp-admin/admin-post.php', { form: { action: 'th_migration', th_do: 'maintenance_on', _wpnonce: nonce } });
check('a seller cannot use the handler', denied.status() === 403 && wp('option get th_maintenance') !== '1');
await s.close();

check('no JS errors', errors.length === 0, errors.join(' | '));
await browser.close();
helper('cleanup');
console.log(failures ? `\n${failures} FAILED` : '\nAll passed');
process.exit(failures ? 1 : 0);
