// Accessibility scan (axe-core, WCAG 2.1 A/AA) of every page type, guest + logged in, 1440 and 390 wide.
//   node _dev/tests/a11y.mjs [baseUrl]
// Needs (local only): member tester/tester, business seller user54/seller54.
import fs from 'node:fs';
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const axe = fs.readFileSync('node_modules/axe-core/axe.min.js', 'utf8');
const browser = await chromium.launch({ channel: 'chrome', headless: true });

const guest = [
	'/', '/listings/', '/listing-category/auto-moto-boats/?view=list', '/listing-category/auto-moto-boats/?view=map',
	'/listing-category/services/?th_sheet=1', '/listings/amrit-restaurant/', '/guides/', '/renting-a-car-in-spain/',
	'/about-us/', '/contact/', '/faq/', '/privacy-policy/', '/login/', '/register/', '/register/?type=business',
	'/lost-password/', '/no-such-page/',
];
const seller = ['/my-account/', '/my-account/listings/', '/my-account/chat/', '/my-account/alerts/', '/my-account/verification/', '/my-account/edit-account/', '/listing-form/', '/listing-form/?th_cat=39', '/listing-form/?th_cat=41'];
const member = ['/my-account/', '/listing-form/'];

async function login(ctx, user, pass) {
	const p = await ctx.newPage();
	await p.goto(base + '/login/');
	await p.fill('#th-log', user);
	await p.fill('#th-pwd', pass);
	await Promise.all([p.waitForURL(/my-account/), p.click('.th-auth-form [type="submit"]')]);
	await p.close();
}

let total = 0;
async function scan(ctx, label, urls) {
	for (const url of urls) {
		const page = await ctx.newPage();
		await page.goto(base + url, { waitUntil: 'networkidle' }).catch(() => {});
		await page.waitForTimeout(500);
		await page.addScriptTag({ content: axe });
		const res = await page.evaluate(async () => window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'] } }));
		for (const v of res.violations) {
			// Third-party markup we don't render (GTranslate, Leaflet controls from the library, RTCL's own forms on edit-account).
			const nodes = v.nodes.filter((n) => !/gtranslate|gt_|leaflet-control-attribution/i.test(n.html));
			if (!nodes.length) {
				continue;
			}
			total += nodes.length;
			console.log(`${label} ${ctx._w} ${url}  [${v.impact}] ${v.id}: ${v.help}`);
			nodes.slice(0, 3).forEach((n) => console.log(`    ${n.target.join(' ')}  ${n.html.slice(0, 140)}`));
		}
		await page.close();
	}
}

for (const width of [1440, 390]) {
	const opts = { viewport: { width, height: width > 600 ? 1000 : 844 }, isMobile: width < 600 };
	const g = await browser.newContext(opts);
	g._w = width;
	await scan(g, 'guest', guest);
	await g.close();
	const s = await browser.newContext(opts);
	s._w = width;
	await login(s, 'user54', 'seller54');
	await scan(s, 'seller', seller);
	await s.close();
	const m = await browser.newContext(opts);
	m._w = width;
	await login(m, 'tester', 'tester');
	await scan(m, 'member', member);
	await m.close();
}
await browser.close();
console.log(total ? `\n${total} violations` : '\nNo violations');
process.exit(total ? 1 : 0);
