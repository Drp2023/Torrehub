// E2E: accounts (phase 5). node _dev/tests/e2e/auth.mjs [baseUrl]
// Register (business + NIF, private seller without NIE) → confirm via Mailpit → pending → admin approves (dev/dev)
// → login → wp-admin blocked → password reset → failed-login warning.
// Creates users with @e2e.test addresses; remove them with: wp eval-file _dev/tests/e2e/auth-cleanup.php
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const mailpit = 'http://localhost:10000/api/v1';
const out = '_dev/screens/theme';
const stamp = Date.now().toString(36);
const user = `biz${stamp}`;
const email = `${user}@e2e.test`;
const pass = `Sunny-Coast-${stamp}!`;
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};

/** Latest e-mail to an address (waits up to 10 s), as plain text. */
async function mailTo(addr, subjectPart) {
	for (let i = 0; i < 20; i++) {
		const res = await (await fetch(`${mailpit}/search?query=${encodeURIComponent(`to:${addr}`)}`)).json();
		const hit = (res.messages || []).find((m) => !subjectPart || m.Subject.includes(subjectPart));
		if (hit) {
			return (await (await fetch(`${mailpit}/message/${hit.ID}`)).json()).Text;
		}
		await new Promise((r) => setTimeout(r, 500));
	}
	return '';
}
const linkIn = (text, needle) => (text.match(new RegExp(`https?://\\S*${needle}\\S*`)) || [''])[0];
/** Forms refuse submissions faster than 3 s (bot check). */
const human = (page) => page.waitForTimeout(3200);

const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(String(e)));

/* ---------------------------------------------------------------- registration */
await page.goto(base + '/register/');
check('step 1 shows three account types', (await page.locator('.th-auth-type').count()) === 3);
check('no NIE anywhere on step 1', !/\bNIE\b/.test(await page.locator('main').innerText()));
await page.locator('.th-auth-type:has(input[value="seller"])').click();
await page.click('.th-auth-step__next');
check('private seller: no NIE / NIF field', (await page.locator('input[name="nif"], input[name="nie"]').count()) === 0 && !/\bNIE\b/.test(await page.locator('main').innerText()));

await page.goto(base + '/register/?type=business');
await page.fill('#th-reg-first_name', 'Elena');
await page.fill('#th-reg-last_name', 'Marín');
await page.fill('#th-reg-company', 'Fontanería E2E S.L.');
await page.fill('#th-reg-nif', 'B12345675'); // wrong control digit
await page.fill('#th-reg-username', user);
await page.fill('#th-reg-email', email);
await page.fill('#th-reg-password', pass);
await page.fill('#th-reg-password2', pass);
await page.check('input[name="terms"]');
await human(page);
await page.click('[data-th-register] [type="submit"]');
check('invalid NIF control digit is refused', /isn’t valid/.test((await page.locator('#th-reg-nif-error, .th-help--error').allInnerTexts()).join(' ')));
check('values are kept after an error (not the password)', (await page.inputValue('#th-reg-username')) === user && (await page.inputValue('#th-reg-password')) === '');

await page.fill('#th-reg-nif', 'b-1234567-4'); // valid, sloppy format
await page.fill('#th-reg-password', pass);
await page.fill('#th-reg-password2', pass);
await page.check('input[name="terms"]');
await human(page);
await Promise.all([page.waitForURL(/th_registered=/), page.click('[data-th-register] [type="submit"]')]);
check('registration → "Check your inbox"', /Check your inbox/.test(await page.locator('h1').innerText()));
await page.screenshot({ path: `${out}/auth-registered-1280.png` });

/* ---------------------------------------------------------------- confirmation */
const confirmMail = await mailTo(email, 'Confirm');
const confirmLink = linkIn(confirmMail, 'th_confirm');
check('confirmation e-mail with a link', !!confirmLink);
await page.goto(confirmLink);
check('link → "E-mail confirmed — awaiting approval"', /awaiting approval/i.test(await page.locator('h1').innerText()));
await page.goto(confirmLink);
check('second use of the link → "Already confirmed"', /Already confirmed/i.test(await page.locator('main').innerText()));
const adminSearch = await (await fetch(`${mailpit}/search?query=${encodeURIComponent(`subject:"awaiting approval"`)}`)).json();
const adminHit = (adminSearch.messages || [])[0];
const adminText = adminHit ? (await (await fetch(`${mailpit}/message/${adminHit.ID}`)).json()).Text : '';
check('admin is told about the new account', adminText.includes(email));

/* ---------------------------------------------------------------- pending login */
await page.goto(base + '/login/');
await page.fill('#th-log', email);
await page.fill('#th-pwd', pass);
await page.click('.th-auth-form [type="submit"]');
await page.waitForLoadState();
check('pending account can’t log in → "Awaiting approval"', /Awaiting approval/.test(await page.locator('main').innerText()));

/* ---------------------------------------------------------------- admin approval */
const admin = await browser.newContext();
const ap = await admin.newPage();
await ap.goto(base + '/wp-login.php');
await ap.fill('#user_login', 'dev');
await ap.fill('#user_pass', 'dev');
await Promise.all([ap.waitForNavigation(), ap.click('#wp-submit')]);
await ap.goto(base + '/wp-admin/users.php?th_status=pending');
const row = ap.locator(`tr:has-text("${email}")`);
check('admin: account listed under "Awaiting approval"', (await row.count()) === 1);
check('admin: NIF shown as "on file" (not the number)', /NIF on file/.test(await row.innerText()) && !/B12345674/.test(await row.innerText()));
await row.hover();
await Promise.all([ap.waitForNavigation(), row.locator('.th_approve a').click()]);
check('admin: approve → notice', /approved/i.test(await ap.locator('.notice-success').first().innerText()));
await admin.close();
check('approval e-mail sent', /approved/i.test(await mailTo(email, 'approved')));

/* ---------------------------------------------------------------- login, wp-admin, logout */
await page.goto(base + '/login/');
await page.fill('#th-log', user);
await page.fill('#th-pwd', pass);
await Promise.all([page.waitForURL(/my-account/), page.click('.th-auth-form [type="submit"]')]);
check('approved account logs in → account page', /my-account/.test(page.url()));
await page.goto(base + '/wp-admin/');
check('non-staff can’t open wp-admin', !/wp-admin/.test(page.url()), page.url());
check('no admin bar for non-staff', (await page.locator('#wpadminbar').count()) === 0);
await page.goto(base + '/login/');
check('logged-in users skip /login/', /my-account/.test(page.url()));
await ctx.clearCookies();

/* ---------------------------------------------------------------- password reset */
await page.goto(base + '/lost-password/');
await page.fill('#th-user-login', email);
await Promise.all([page.waitForURL(/th_sent=1/), page.click('main [type="submit"]')]);
check('reset request → neutral "check your inbox"', /Check your inbox/.test(await page.locator('h1').innerText()));
const resetLink = linkIn(await mailTo(email, 'Reset'), 'key=');
check('reset e-mail with a link', !!resetLink);
await page.goto(resetLink);
const newPass = `${pass}-new`;
await page.fill('#th-new-password', newPass);
await page.fill('#th-new-password2', newPass);
await Promise.all([page.waitForURL(/th_notice=password/), page.click('main [type="submit"]')]);
check('new password saved → login notice', /password was changed/.test(await page.locator('main').innerText()));
await page.goto(resetLink);
check('reset link works only once', /invalid or has expired/.test(await page.locator('main').innerText()));

/* ---------------------------------------------------------------- failed logins */
for (let i = 0; i < 3; i++) {
	await page.goto(base + '/login/');
	await page.fill('#th-log', user);
	await page.fill('#th-pwd', 'wrong-password');
	await page.click('.th-auth-form [type="submit"]');
	await page.waitForLoadState();
}
check('3rd failure warns about attempts left', /attempts? left/.test(await page.locator('.th-alert').first().innerText()));
await page.goto(base + '/login/');
await page.fill('#th-log', user);
await page.fill('#th-pwd', newPass);
await Promise.all([page.waitForURL(/my-account/), page.click('.th-auth-form [type="submit"]')]);
check('correct password still works before the lockout (and clears the counter)', /my-account/.test(page.url()));

check('no JS errors', errors.length === 0, errors.join(' | '));
await browser.close();
console.log(failures ? `\n${failures} FAILED` : '\nALL PASSED');
process.exit(failures ? 1 : 0);
