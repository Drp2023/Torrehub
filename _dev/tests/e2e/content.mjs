// E2E: guides, static pages, contact form, 404 (phase 7). node _dev/tests/e2e/content.mjs [baseUrl]
// Needs (local only): `wp torrehub migrate-pages --apply` done, Mailpit on :10000, member tester/tester, WP-CLI via bin/env.sh
// (run from Git Bash). Side effects: removed at the end (content-helper.php cleanup).
import { execSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const mailpit = 'http://localhost:10000/api/v1';
const out = '_dev/screens/theme';
const helper = (args) => execSync(`bash -c "source bin/env.sh >/dev/null 2>&1; wp eval-file _dev/tests/e2e/content-helper.php ${args} 2>/dev/null"`, { encoding: 'utf8' }).trim();
const browser = await chromium.launch({ channel: 'chrome', headless: true });
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const errors = [];
const page = await browser.newPage({ viewport: { width: 1440, height: 1000 } });
page.on('pageerror', (e) => errors.push(String(e)));
const jsonld = async (type) => page.locator('script[type="application/ld+json"]').evaluateAll((els, t) => els.map((e) => { try { return JSON.parse(e.textContent); } catch { return {}; } }).some((d) => d['@type'] === t), type);

/* ---------------------------------------------------------------- guides */
const guideUrl = helper('setup');
await page.goto(base + '/guides/');
check('Guides index: featured guide + cards + topic chips', (await page.locator('.th-guide-featured').count()) === 1 && (await page.locator('.th-guide-card').count()) >= 1 && (await page.locator('.th-guides__chips a').count()) >= 2);
await page.screenshot({ path: `${out}/guides-1440.png` });
await page.click('.th-guides__chips a:has-text("Tips in Spain")');
check('topic chip → category, chip current', /\/category\//.test(page.url()) && (await page.locator('.th-guides__chips [aria-current="page"]').innerText()).includes('Tips'));

await page.goto(guideUrl);
check('guide: byline with reading time', /\d+ min read/.test(await page.locator('.th-guide__byline').innerText()));
check('guide: “In this guide” lists the 4 headings', (await page.locator('.th-guide__toc a').count()) === 4);
await page.locator('#what-to-bring').scrollIntoViewIfNeeded();
await page.mouse.wheel(0, 200);
await page.waitForTimeout(500);
check('scrollspy marks the section in view', /What to bring|On the day/.test(await page.locator('.th-guide__toc a[aria-current="true"]').innerText().catch(() => '')));
check('“Need help with this?” card links to the first related category', /\/listing-category\/services\//.test(await page.locator('.th-guide-cta a').getAttribute('href')));
check('“Mentioned in this guide” shows both categories with counts', (await page.locator('.th-guide-mention').count()) === 2 && /listing/.test(await page.locator('.th-guide__mentioned').innerText()));
check('Article JSON-LD', await jsonld('Article'));
await page.screenshot({ path: `${out}/guide-1440.png`, fullPage: true });
const mob = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true });
await mob.goto(guideUrl);
await mob.mouse.wheel(0, 1500);
await mob.waitForTimeout(400);
const pct = await mob.locator('[data-th-reading-progress] span').evaluate((e) => e.style.getPropertyValue('--pct'));
check('mobile: reading progress moves', pct !== '' && pct !== '0%', pct);
check('mobile guide: no overflow', (await mob.evaluate(() => document.documentElement.scrollWidth)) <= 390);
await mob.screenshot({ path: `${out}/guide-390.png` });

/* ---------------------------------------------------------------- static pages */
await page.goto(base + '/about-us/');
check('About: hero with live numbers + content card', (await page.locator('.th-about__stats li').count()) >= 3 && /Costa Blanca/.test(await page.locator('.th-about__card').innerText()));
await page.goto(base + '/privacy-policy/');
const tocLinks = page.locator('.th-legal__toc a');
check('Privacy: contents from the headings', (await tocLinks.count()) >= 10);
const firstHash = await tocLinks.nth(1).getAttribute('href');
check('contents anchors exist', (await page.locator(firstHash).count()) === 1, firstHash);
check('“Last updated” line', /Last updated/.test(await page.locator('.th-legal__updated').innerText()));
await page.goto(base + '/faq/');
check('FAQ: questions as accordion + FAQPage JSON-LD', (await page.locator('[data-th-faq] details').count()) >= 6 && (await jsonld('FAQPage')));
await page.fill('#th-faq-q', 'photo');
const visibleQ = await page.locator('[data-th-faq] details:not([hidden])').count();
check('FAQ search filters (and opens) the matching question', visibleQ === 1 && (await page.locator('[data-th-faq] details:not([hidden])').getAttribute('open')) !== null, String(visibleQ));
await page.fill('#th-faq-q', 'zzzz');
check('FAQ search: “no question matches”', await page.locator('[data-th-faq-none]').isVisible());
await page.screenshot({ path: `${out}/faq-1440.png` });

/* ---------------------------------------------------------------- contact form */
const mails = async () => ((await (await fetch(`${mailpit}/search?query=${encodeURIComponent('subject:"Contact:"')}`)).json()).messages || []).length;
const before = await mails();
await page.goto(base + '/contact/');
await page.waitForTimeout(3200); // The form refuses submissions faster than 3 s (bots).
await page.fill('#th-contact-name', 'Test Visitor');
await page.fill('#th-contact-email', 'marco@');
await page.fill('#th-contact-message', 'Hello, I want to report a listing that looks fake.');
await Promise.all([page.waitForNavigation(), page.click('.th-contact__form [type="submit"]')]);
check('contact: invalid e-mail → error, values kept', /complete e-mail/.test(await page.locator('.th-contact__form').innerText()) && (await page.inputValue('#th-contact-message')).includes('report a listing'));
await page.waitForTimeout(3200);
await page.fill('#th-contact-email', 'visitor@example.com');
await page.selectOption('#th-contact-subject', 'listing');
await Promise.all([page.waitForNavigation(), page.click('.th-contact__form [type="submit"]')]);
await page.waitForTimeout(1200);
check('contact: sent → “Message sent”', /Message sent/.test(await page.locator('.th-contact__card').innerText()));
check('contact: e-mail delivered (one)', (await mails()) === before + 1);
const latest = ((await (await fetch(`${mailpit}/search?query=${encodeURIComponent('subject:"Contact:"')}`)).json()).messages || [])[0];
const headers = latest ? await (await fetch(`${mailpit}/message/${latest.ID}/headers`)).json() : {};
check('contact e-mail: Reply-To is the visitor', /visitor@example\.com/.test(JSON.stringify(headers['Reply-To'] || '')));
await page.goto(base + '/contact/');
await page.fill('#th-contact-name', 'Bot');
await page.fill('#th-contact-email', 'bot@example.com');
await page.fill('#th-contact-message', 'Buy cheap things now, best prices ever.');
await Promise.all([page.waitForNavigation(), page.click('.th-contact__form [type="submit"]')]);
await page.waitForTimeout(1000);
check('contact: instant (bot-speed) submit looks sent but sends nothing', /Message sent/.test(await page.locator('.th-contact__card').innerText()) && (await mails()) === before + 1);
await page.goto(base + '/contact/?subject=upgrade');
check('contact: ?subject= preselects', (await page.inputValue('#th-contact-subject')) === 'upgrade');

/* ---------------------------------------------------------------- 404 / 403 */
const res = await page.goto(base + '/this-page-does-not-exist-e2e/');
check('404: status + panel with search and links', res.status() === 404 && (await page.locator('.th-error-panel__code').innerText()) === '404' && (await page.locator('.th-error-panel input[type="search"]').count()) === 1);
await page.screenshot({ path: `${out}/404-1440.png` });
const mctx = await browser.newContext();
const member = await mctx.newPage();
await member.goto(base + '/login/');
await member.fill('#th-log', 'tester');
await member.fill('#th-pwd', 'tester');
await Promise.all([member.waitForURL(/my-account/), member.click('.th-auth-form [type="submit"]')]);
const r403 = await member.goto(base + '/listing-form/');
check('403 (member posting): panel + “Contact us to upgrade” → contact?subject=upgrade', r403.status() === 403 && /subject=upgrade/.test(await member.locator('a:has-text("Contact us to upgrade")').getAttribute('href')));
await member.screenshot({ path: `${out}/403-1440.png` });
await mctx.close();

check('no JS errors', errors.length === 0, errors.join(' | '));
await browser.close();
helper('cleanup');
console.log(failures ? `\n${failures} FAILED` : '\nAll passed');
process.exit(failures ? 1 : 0);
