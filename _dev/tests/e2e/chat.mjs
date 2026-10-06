// E2E: chat (phase 6). node _dev/tests/e2e/chat.mjs [baseUrl]
// Needs (local only): member tester/tester, business seller user54/seller54 who owns listing 6996, Mailpit on :10000.
// Side effects: a conversation tester ↔ user54 (undo: wp eval-file _dev/tests/e2e/chat-cleanup.php).
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const mailpit = 'http://localhost:10000/api/v1';
const LISTING = 6996;
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
const errors = [];
const watch = (page) => {
	page.on('pageerror', (e) => errors.push(String(e)));
	// The security checks provoke 401 / 404 / 429 responses on purpose.
	page.on('console', (m) => m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text()) && errors.push(m.text()));
};
const stamp = Date.now().toString(36);

/* ---------------------------------------------------------------- guest */
const gctx = await browser.newContext();
const guest = await gctx.newPage();
await guest.goto(`${base}/?post_type=rtcl_listing&p=${LISTING}`);
const guestChat = guest.locator('#contact a:has-text("Chat")');
check('guest: Chat button leads to login', (await guestChat.count()) === 1 && /\/login\//.test(await guestChat.getAttribute('href')));
await gctx.close();

/* ---------------------------------------------------------------- buyer starts */
const bctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const buyer = await login(bctx, 'tester', 'tester');
watch(buyer);
await buyer.goto(`${base}/?post_type=rtcl_listing&p=${LISTING}`);
await Promise.all([buyer.waitForURL(/my-account\/chat\/\?listing=/), buyer.click('#contact a:has-text("Chat")')]);
const head = await buyer.locator('.th-chat__head').innerText().catch(() => '');
check('Chat → new conversation screen with the listing', /amrit/i.test(head), head.replace(/\s+/g, ' '));
const first = `Hello, is the table for 4 free on Friday? ${stamp}`;
await buyer.fill('#th-chat-message', first);
await Promise.all([buyer.waitForURL(/thread=\d+/), buyer.click('[data-th-chat-form] [type="submit"]')]);
const threadId = Number(new URL(buyer.url()).searchParams.get('thread'));
check('first message starts a thread', threadId > 0 && (await buyer.locator('.th-msg--mine').last().innerText()).includes(stamp));

/* ---------------------------------------------------------------- e-mail */
await new Promise((r) => setTimeout(r, 1500));
const hits = await (await fetch(`${mailpit}/search?query=${encodeURIComponent('subject:"New message from"')}`)).json();
const mail = (hits.messages || []).find((m) => Date.now() - new Date(m.Created).getTime() < 60000);
const mailText = mail ? (await (await fetch(`${mailpit}/message/${mail.ID}`)).json()).Text : '';
check('seller gets one e-mail with the message and a reply link', mailText.includes(stamp) && /my-account\/chat\/\?thread=/.test(mailText));
const mailCount = async () => (await (await fetch(`${mailpit}/search?query=${encodeURIComponent('subject:"New message from"')}`)).json()).messages_count ?? 0;
const before = await mailCount();
await buyer.fill('#th-chat-message', 'Second message, no new e-mail please.');
await buyer.press('#th-chat-message', 'Enter');
await buyer.waitForFunction(() => document.querySelectorAll('.th-msg--mine').length >= 2);
check('Enter sends without reloading', /thread=/.test(buyer.url()) && (await buyer.locator('.th-msg--mine').count()) >= 2);
await new Promise((r) => setTimeout(r, 1500));
check('no second e-mail while the first is unread', (await mailCount()) === before);

/* ---------------------------------------------------------------- seller */
const sctx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const seller = await login(sctx, 'user54', 'seller54');
watch(seller);
check('seller dashboard: "Unread" tile', /Unread/i.test(await seller.locator('.th-account-stats').innerText()));
await seller.goto(`${base}/`);
check('seller header: chat badge shows 2', (await seller.locator('[data-th-chat-badge]').first().innerText()).trim() === '2');
await seller.goto(`${base}/my-account/chat/`);
check('seller list: conversation marked unread', (await seller.locator(`.th-chat-item.is-unread[data-thread="${threadId}"]`).count()) === 1);
await Promise.all([seller.waitForURL(/thread=/), seller.click(`.th-chat-item[data-thread="${threadId}"]`)]);
check('seller sees the buyer’s messages', (await seller.locator('.th-msg:not(.th-msg--mine)').count()) >= 2);
await seller.screenshot({ path: `${out}/chat-1280.png` });
await seller.goto(`${base}/`);
check('after reading, the badge is gone', await seller.locator('[data-th-chat-badge]').first().isHidden());
await seller.goto(`${base}/my-account/chat/?thread=${threadId}`);
const reply = `Yes, Friday is free. ${stamp}-reply`;
await seller.fill('#th-chat-message', reply);
await seller.click('[data-th-chat-form] [type="submit"]');
await seller.waitForFunction((t) => [...document.querySelectorAll('.th-msg--mine')].some((m) => m.innerText.includes(t)), `${stamp}-reply`);

/* ---------------------------------------------------------------- polling + seen */
await buyer.waitForFunction((t) => [...document.querySelectorAll('.th-msg:not(.th-msg--mine)')].some((m) => m.innerText.includes(t)), `${stamp}-reply`, { timeout: 12000 }).catch(() => {});
check('buyer receives the reply by polling (no reload)', (await buyer.locator('.th-msg:not(.th-msg--mine)', { hasText: `${stamp}-reply` }).count()) === 1);
await seller.reload();
check('seller sees "Seen" under the reply once the buyer read it', await seller.locator('[data-th-chat-seen]').isVisible());

/* ---------------------------------------------------------------- security */
const foreign = await buyer.evaluate(async (base) => {
	const nonce = JSON.parse(document.getElementById('wp-script-module-data-th-app').textContent).chat.nonce;
	const noNonce = await fetch(`${base}/wp-json/torrehub/v1/chat/threads`, { credentials: 'same-origin' });
	const bad = await fetch(`${base}/wp-json/torrehub/v1/chat/threads/999999`, { credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce } });
	const self = await fetch(`${base}/wp-json/torrehub/v1/chat/threads`, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }, body: JSON.stringify({ listing_id: 7177, message: '<script>alert(1)</script>Hi' }) });
	return { noNonce: noNonce.status, bad: bad.status, self: self.status };
}, base);
check('REST without nonce is refused', foreign.noNonce === 401 || foreign.noNonce === 403, String(foreign.noNonce));
check('REST: someone else’s / unknown thread → 404', foreign.bad === 404, String(foreign.bad));
check('REST: markup is stored as text', foreign.self === 200);
await buyer.goto(`${base}/my-account/chat/`);
const listText = await buyer.locator('.th-chat__list').innerText();
check('markup stripped: no <script>, no script text', (await buyer.locator('.th-chat__list script').count()) === 0 && /Hi/.test(listText) && !/alert/.test(listText), listText.replace(/\s+/g, ' ').slice(0, 200));

/* ---------------------------------------------------------------- owner + no-JS */
await seller.goto(`${base}/?post_type=rtcl_listing&p=${LISTING}`);
check('owner: no Chat button on own listing', (await seller.locator('#contact a:has-text("Chat")').count()) === 0);

const njctx = await browser.newContext({ javaScriptEnabled: false });
const nj = await login(njctx, 'tester', 'tester').catch(() => null);
if (nj) {
	await nj.goto(`${base}/my-account/chat/?thread=${threadId}`);
	await nj.fill('#th-chat-message', `No-JS message ${stamp}`);
	await Promise.all([nj.waitForNavigation(), nj.click('[data-th-chat-form] [type="submit"]')]);
	check('no-JS: plain form sends and returns to the thread', (await nj.locator('.th-msg--mine', { hasText: `No-JS message ${stamp}` }).count()) === 1);
	await njctx.close();
}

/* ---------------------------------------------------------------- mobile */
const mctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
const mobile = await login(mctx, 'user54', 'seller54');
await mobile.goto(`${base}/my-account/chat/`);
const mw = await mobile.evaluate(() => document.documentElement.scrollWidth);
check('mobile list: no overflow', mw <= 390, String(mw));
await mobile.screenshot({ path: `${out}/chat-list-390.png` });
await mobile.goto(`${base}/my-account/chat/?thread=${threadId}`);
check('mobile thread: list hidden, back link shown', (await mobile.locator('.th-chat__list').isHidden()) && (await mobile.locator('.th-chat__back').isVisible()));
await mobile.screenshot({ path: `${out}/chat-thread-390.png` });
await mctx.close();

/* ---------------------------------------------------------------- rate limit (last: it blocks the buyer for 10 min) */
const statuses = await buyer.evaluate(async ({ base, id }) => {
	const nonce = JSON.parse(document.getElementById('wp-script-module-data-th-app').textContent).chat.nonce;
	const codes = [];
	for (let i = 0; i < 32; i++) {
		const r = await fetch(`${base}/wp-json/torrehub/v1/chat/threads/${id}`, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' }, body: JSON.stringify({ message: `flood ${i}` }) });
		codes.push(r.status);
	}
	return codes;
}, { base, id: threadId });
check('rate limit: 30 messages / 10 min, then 429', statuses.includes(429) && statuses.filter((s) => s === 200).length <= 30, statuses.join(','));

check('no JS errors', errors.length === 0, errors.join(' | '));
await browser.close();
console.log(failures ? `\n${failures} FAILED` : '\nAll passed');
process.exit(failures ? 1 : 0);
