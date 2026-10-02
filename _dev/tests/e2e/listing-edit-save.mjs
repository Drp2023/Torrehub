// E2E: log in, open the RTCL (React) edit form of a listing, save it unchanged, report the AJAX response.
//   node _dev/tests/e2e/listing-edit-save.mjs <user> <password> <listingId> [baseUrl]
// Used to prove the repeater go-live gate (RtclCompat): saving must not wipe repeater meta when Pro is gone.
import { chromium } from 'playwright-core';

const [user, pass, id, base = 'http://localhost:10004'] = process.argv.slice(2);
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const page = await browser.newPage();

await page.goto(`${base}/wp-login.php`);
await page.fill('#user_login', user);
await page.fill('#user_pass', pass);
await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);

await page.goto(`${base}/listing-form/edit/${id}/`, { waitUntil: 'networkidle' });
const submit = page.locator('.rtcl-form-submit-btn button, .rtcl-form-submit-btn .rtcl-fb-btn').first();
await submit.waitFor({ timeout: 30000 });
const repeaterRendered = await page.locator('[data-element="repeater"]').count();

const [resp] = await Promise.all([
	page.waitForResponse((r) => r.url().includes('admin-ajax.php') && r.request().postData()?.includes('rtcl_update_listing'), { timeout: 30000 }),
	submit.click(),
]);
let body = await resp.text();
try { body = JSON.stringify(JSON.parse(body)).slice(0, 200); } catch { body = body.slice(0, 200); }
console.log(JSON.stringify({ listing: id, repeaterFieldsRendered: repeaterRendered, status: resp.status(), response: body }));
await browser.close();
