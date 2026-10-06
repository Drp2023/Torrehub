// SEO probe: title, description, canonical, robots, Open Graph, JSON-LD types, one h1, html lang — per page type.
//   node _dev/tests/seo.mjs [baseUrl]
// Logged-in pages are checked for noindex with the seller account (user54/seller54, local only).
// The th_seo_audit cookie lifts the local mu-plugin's forced "discourage search engines" for these requests only.
import { chromium } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const browser = await chromium.launch({ channel: 'chrome', headless: true });

// [url, expect indexable]
const guest = [
	['/', true], ['/listings/', true], ['/listing-category/auto-moto-boats/', true], ['/listing-category/auto-moto-boats/?view=map', false],
	['/listings/?q=car&sort=price-asc', false], ['/listings/amrit-restaurant/', true], ['/guides/', true], ['/renting-a-car-in-spain/', true],
	['/category/uncategorized/', true], ['/about-us/', true], ['/contact/', true], ['/faq/', true], ['/privacy-policy/', true],
	['/login/', false], ['/register/', false], ['/lost-password/', false], ['/listing-form/', false], ['/no-such-page/', false],
];
const seller = [['/my-account/', false], ['/my-account/listings/', false], ['/my-account/chat/', false], ['/my-account/alerts/', false], ['/listing-form/?th_cat=39', false]];

let problems = 0;
const titles = new Map();
async function probe(ctx, label, urls) {
	for (const [url, indexable] of urls) {
		const page = await ctx.newPage();
		const res = await page.goto(base + url, { waitUntil: 'domcontentloaded' });
		const d = await page.evaluate(() => {
			const meta = (sel) => document.querySelector(sel)?.getAttribute('content') || '';
			const types = [];
			document.querySelectorAll('script[type="application/ld+json"]').forEach((s) => {
				try {
					const j = JSON.parse(s.textContent);
					(j['@graph'] || [j]).forEach((n) => types.push([].concat(n['@type']).join('+')));
				} catch {
					types.push('INVALID');
				}
			});
			return {
				title: document.title,
				desc: meta('meta[name="description"]'),
				canonical: document.querySelector('link[rel="canonical"]')?.href || '',
				robots: meta('meta[name="robots"]'),
				og: meta('meta[property="og:title"]') ? 'og' : '',
				ogImage: meta('meta[property="og:image"]') ? 'img' : '',
				types,
				h1: document.querySelectorAll('h1').length,
				lang: document.documentElement.lang,
			};
		});
		const noindex = res.status() === 404 || /noindex/.test(d.robots) || /noindex/.test(res.headers()['x-robots-tag'] || '');
		const issues = [];
		if (noindex === indexable) {
			issues.push(indexable ? 'NOINDEX' : 'indexable');
		}
		if (d.h1 !== 1) {
			issues.push(`h1×${d.h1}`);
		}
		if (!d.lang) {
			issues.push('no lang');
		}
		if (!indexable && d.og) {
			issues.push('og on a noindex page');
		}
		if (indexable) {
			if (!d.desc) {
				issues.push('no description');
			}
			if (!d.canonical) {
				issues.push('no canonical');
			}
			if (!d.og || !d.ogImage) {
				issues.push('no og');
			}
			if (url === '/' && !(d.types.includes('Organization') && d.types.includes('WebSite'))) {
				issues.push('no Organization/WebSite');
			}
			if (titles.has(d.title)) {
				issues.push(`dup title with ${titles.get(d.title)}`);
			}
			titles.set(d.title, url);
		}
		problems += issues.length;
		console.log(`${issues.length ? '!!' : 'ok'} ${label} ${url} [${res.status()}]\n     title: ${d.title}\n     desc: ${d.desc.slice(0, 90)}\n     canonical: ${d.canonical.replace(base, '')} robots: ${d.robots || '-'} ${d.og} ${d.ogImage} ld: ${d.types.join(', ') || '-'}${issues.length ? '\n     ISSUES: ' + issues.join('; ') : ''}`);
		await page.close();
	}
}

const audit = async () => {
	const ctx = await browser.newContext();
	await ctx.addCookies([{ name: 'th_seo_audit', value: '1', url: base }]);
	return ctx;
};
const g = await audit();
await probe(g, 'guest', guest);
const s = await audit();
const lp = await s.newPage();
await lp.goto(base + '/login/');
await lp.fill('#th-log', 'user54');
await lp.fill('#th-pwd', 'seller54');
await Promise.all([lp.waitForURL(/my-account/), lp.click('.th-auth-form [type="submit"]')]);
await probe(s, 'seller', seller);

// Core sitemap: listings in, users out.
const idx = await (await g.request.get(base + '/wp-sitemap.xml')).text();
const maps = [...idx.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1].replace(base, ''));
console.log('\nsitemaps:', maps.join('  '));
if (maps.some((m) => /users/.test(m))) {
	problems++;
	console.log('!! users sitemap exposed');
}
if (!maps.some((m) => /rtcl_listing/.test(m))) {
	problems++;
	console.log('!! no listing sitemap');
}
const pages = await (await g.request.get(base + '/wp-sitemap-posts-page-1.xml')).text();
const leaked = ['/login/', '/register/', '/lost-password/', '/my-account/', '/listing-form/'].filter((u) => pages.includes(base + u + '<'));
if (leaked.length) {
	problems++;
	console.log('!! noindex pages in the page sitemap:', leaked.join(' '));
}
const robotsTxt = await (await g.request.get(base + '/robots.txt')).text();
console.log('robots.txt:', robotsTxt.trim().split(/\s*\n\s*/).join(' | '));
await browser.close();
console.log(problems ? `\n${problems} issues` : '\nNo issues');
process.exit(problems ? 1 : 0);
