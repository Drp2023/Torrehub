// E2E: client feedback 2026-11.
//   Group 1 (bugs): approval e-mail, phone-width account labels in long languages, town picker, "all listings"
//   paging, Report colour; module cache-busting.
//   Group 2 (Service form): Languages Spoken → multiple choice, Mobile Service options; migration step U1.
//   Group 3 (search in any language): category keywords per language, seeding step U2, search expansion.
//   Group 4 (admin tools): Verified column / toggle / bulk / filters, review e-mail + "Reply via chat",
//   messages to all users (batches, pause, retry, no doubles) and the account-sidebar notice.
//   Group 5 (favourites): Classified Listing's favourites on (U3), hearts on cards / listing page, guest → login →
//   saved, Account › Favourites for every role.
//   Group 6 (home, guides): the 12 main towns (6/4/3/2 per row), Guides 9 a page in 3 × 3 with real /page/N/ links
//   and the posts page's excerpt as intro, "Latest guides" on the home page (Customizer switches).
//   node _dev/tests/e2e/feedback.mjs [baseUrl]
// Needs (local only): WP-CLI via bin/env.sh (Git Bash), Mailpit, admin dev/dev, business seller user54/seller54,
// the th-local-safety mu-plugin (live-like canonical redirects). The language sweep needs the internet (GTranslate);
// without it those checks are skipped. Reverted by feedback-helper.php cleanup.
import { execSync } from 'node:child_process';
import { chromium, devices } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const out = '_dev/screens/theme';
const mailpit = 'http://localhost:10000/api/v1';
// Node's fetch keeps sockets alive; after a slow wp-cli step nginx may have closed one — retry once (all requests are idempotent).
const nativeFetch = globalThis.fetch;
globalThis.fetch = (url, opts) => nativeFetch(url, opts).catch((e) => (e.cause?.code === 'ECONNRESET' ? nativeFetch(url, opts) : Promise.reject(e)));
const wp = (cmd) => execSync(`source bin/env.sh >/dev/null 2>&1; wp ${cmd} 2>/dev/null`, { encoding: 'utf8', shell: 'bash' }).trim();
const helper = (arg) => wp(`eval-file _dev/tests/e2e/feedback-helper.php ${arg}`);
const mails = async (q) => ((await (await fetch(`${mailpit}/search?query=${encodeURIComponent(q)}`)).json()).messages || []);
const browser = await chromium.launch({ channel: 'chrome', headless: true });
const phone = { ...devices['iPhone 13'], viewport: { width: 390, height: 844 } };
let failures = 0;
const check = (name, ok, extra = '') => {
	console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${extra ? '  — ' + extra : ''}`);
	if (!ok) failures++;
};
const errors = [];
// Only the theme's code counts: GTranslate's script throws now and then while it translates, and Classified
// Listing's form-builder.js throws "rtclFB is not defined" on the Listings admin screen (the plugin's, unchanged).
const ownError = (e) => (e.stack || String(e)).includes('/wp-content/themes/torrehub/');
const adminLogin = async () => {
	const ctx = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
	const p = await ctx.newPage();
	p.on('pageerror', (e) => ownError(e) && errors.push(String(e)));
	await p.goto(base + '/wp-login.php');
	await p.fill('#user_login', 'dev');
	await p.fill('#user_pass', 'dev');
	await Promise.all([p.waitForNavigation(), p.click('#wp-submit')]);
	return p;
};
const frontLogin = async (ctx, user, pass) => {
	const p = await ctx.newPage();
	p.on('pageerror', (e) => ownError(e) && errors.push(String(e)));
	await p.goto(base + '/login/');
	await p.fill('#th-log', user);
	await p.fill('#th-pwd', pass);
	await Promise.all([p.waitForURL(/my-account/), p.click('.th-auth-form [type="submit"]')]);
	return p;
};

const t = JSON.parse(helper('setup'));
try {
	/* ------------------------------------------------------------ 1.1 approval e-mail */
	const admin = await adminLogin();
	const approve = async () => {
		await admin.goto(`${base}/wp-admin/post.php?post=${t.listing}&action=edit`);
		await admin.selectOption('#rtcl-listing-status', 'publish');
		await Promise.all([admin.waitForURL(/message=/), admin.locator('#submitdiv button:has-text("Save")').first().click()]);
	};
	helper('rtcl-published off');
	const subject = `subject:"is live" to:${t.email}`;
	const before = (await mails(subject)).length;
	await approve();
	await new Promise((r) => setTimeout(r, 800));
	const live = await mails(subject);
	const text = live[0] ? (await (await fetch(`${mailpit}/message/${live[0].ID}`)).json()).Text : '';
	check('approving a pending listing in wp-admin publishes it', helper(`status ${t.listing}`) === 'publish');
	check('owner gets one "Your listing is live" e-mail', live.length === before + 1, `${before} → ${live.length}`);
	check('… with the title, the end date and the listing link', text.includes(t.title) && /runs until/i.test(text) && /View your listing: http/.test(text), text.slice(0, 200));

	helper(`pend ${t.listing}`);
	helper('rtcl-published on');
	const before2 = (await mails(subject)).length;
	await approve();
	await new Promise((r) => setTimeout(r, 800));
	check('with Classified Listing’s own "approved" e-mail on, the theme doesn’t send a second one', (await mails(subject)).length === before2);
	helper('rtcl-published off');
	await admin.context().close();

	/* ------------------------------------------------------------ 1.2 account labels at 390 in long languages */
	const sctx = await browser.newContext({ ...phone, deviceScaleFactor: 2 });
	const seller = await frontLogin(sctx, 'user54', 'seller54');
	const spill = () => {
		const bad = [];
		for (const el of document.querySelectorAll('main .th-btn, main .th-badge, main .th-account-stats__tile, main .th-cat-tile, main a, main button')) {
			const r = el.getBoundingClientRect();
			if (!r.width || el.closest('.th-account__menu')) continue; // the tab strip scrolls by design
			if (el.scrollWidth > el.clientWidth + 1) bad.push(`clipped "${el.innerText.trim().slice(0, 40)}"`);
			const host = el.parentElement?.closest('.th-dash-row, .th-account-card, .th-account-stats__tile, section, article, li');
			const h = host?.getBoundingClientRect();
			if (h && (r.right > h.right + 1 || r.left < h.left - 1)) bad.push(`spills "${el.innerText.trim().slice(0, 40)}"`);
		}
		if (document.documentElement.scrollWidth > innerWidth) bad.push('page scrolls sideways');
		return [...new Set(bad)];
	};
	await seller.goto(base + '/my-account/');
	const cols = await seller.evaluate(() => getComputedStyle(document.querySelector('.th-account-stats')).gridTemplateColumns.split(' ').length);
	check('dashboard: four number tiles sit 2 × 2 on a phone (no gap in the grid)', cols === 2, `${cols} columns`);
	let translated = 0;
	for (const lang of ['hu', 'fi', 'de']) {
		for (const path of ['/my-account/', '/my-account/listings/', '/listing-form/']) {
			await seller.goto(base + path, { waitUntil: 'networkidle' });
			await seller.evaluate((l) => document.querySelector(`footer [data-gt-lang="${l}"]`)?.click(), lang);
			const ok = await seller.waitForFunction(() => document.querySelector('main font'), null, { timeout: 15000 }).then(() => true, () => false);
			if (!ok) continue;
			translated++;
			await seller.waitForTimeout(1500);
			const bad = await seller.evaluate(spill);
			check(`${lang.toUpperCase()} ${path}: no label clipped or spilling at 390 px`, !bad.length, bad.slice(0, 4).join('; '));
			if (lang === 'hu' && path === '/my-account/') await seller.screenshot({ path: `${out}/feedback-dash-hu-390.png`, fullPage: true });
		}
	}
	if (!translated) console.log('SKIP  language sweep (GTranslate not reachable)');
	await seller.evaluate(() => document.querySelector('footer [data-gt-lang="en"]')?.click());
	await sctx.close();

	/* ------------------------------------------------------------ 1.3 town picker */
	const gctx = await browser.newContext(phone);
	const g = await gctx.newPage();
	g.on('pageerror', (e) => ownError(e) && errors.push(String(e)));
	const moduleUrls = [];
	g.on('request', (r) => r.url().includes('/assets/js/') && moduleUrls.push(r.url()));
	await g.goto(base + '/listings/', { waitUntil: 'networkidle' });
	const pill = g.locator('.th-header__mobile .th-loc-pill');
	check('town pill is a link to the picker page (works before / without JS)', /[?&]th_towns=1#th-location$/.test((await pill.getAttribute('href')) || ''));
	await pill.tap();
	check('tapping the town pill opens the town sheet', await g.evaluate(() => document.getElementById('th-location').matches(':modal')));
	check('every JS module is loaded with the theme version (?ver=)', moduleUrls.length > 2 && moduleUrls.every((u) => /\?ver=\d/.test(u)), moduleUrls.filter((u) => !/\?ver=/.test(u)).join(', '));
	await gctx.close();

	const nctx = await browser.newContext({ ...phone, javaScriptEnabled: false });
	const n = await nctx.newPage();
	await n.goto(base + '/listings/?q=car');
	await Promise.all([n.waitForURL(/th_towns=1/), n.locator('.th-header__mobile .th-loc-pill').click()]);
	check('without JS the picker page shows the town sheet open', (await n.locator('#th-location[open]').count()) === 1);
	check('… and is noindex', /noindex/.test((await n.locator('meta[name=robots]').getAttribute('content')) || ''));
	const pick = await n.locator('#th-location a[data-th-town]').nth(1).getAttribute('href');
	await n.goto(new URL(pick, base).href);
	check('picking a town returns to the same search without the picker', /\/listings\/\?q=car$/.test(n.url()) && (await n.locator('#th-location[open]').count()) === 0, n.url());
	await nctx.close();

	const fctx = await browser.newContext(phone);
	const f = await frontLogin(fctx, 'user54', 'seller54');
	await f.goto(base + '/listing-form/', { waitUntil: 'networkidle' });
	// Drill from a root category down to a leaf: the workspace opens there.
	for (let i = 0; i < 4 && !(await f.locator('[data-th-lf-town]').count()); i++) {
		const tile = f.locator('main a.th-cat-tile, main a.th-lf-pick').first();
		if (!(await tile.count())) break;
		await Promise.all([f.waitForLoadState('networkidle'), tile.click()]);
	}
	const town = f.locator('[data-th-lf-town]');
	if (await town.count()) {
		const sec = await town.evaluate((s) => s.closest('[id^="th-lf-sec"]')?.id || '');
		if (sec) await f.evaluate((id) => document.querySelector(`a[href="#${id}"][data-th-lf-goto]`)?.click(), sec);
		await town.scrollIntoViewIfNeeded();
		check('listing form: the town select is on top and enabled (tap opens it)', await town.evaluate((s) => { const r = s.getBoundingClientRect(); return document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2) === s && !s.disabled; }));
		const map = f.locator('[data-th-pin-map]');
		if (await map.count()) {
			await map.scrollIntoViewIfNeeded();
			await f.waitForTimeout(800);
			const covered = await f.evaluate(() => {
				const bar = document.querySelector('.th-lf-footer')?.getBoundingClientRect();
				if (!bar) return null;
				const el = document.elementFromPoint(bar.x + bar.width / 2, bar.y + bar.height / 2);
				return el?.closest('.leaflet-container') ? 'map' : 'bar';
			});
			check('listing form: the pin map stays under the sticky Back / Next bar', covered !== 'map', String(covered));
		}
	} else {
		check('listing form has a town select', false);
	}
	await fctx.close();

	/* ------------------------------------------------------------ 1.4 all listings: paging */
	const head = (url) => fetch(base + url, { redirect: 'manual' }).then((r) => [r.status, r.headers.get('location') || '']);
	const [s1] = await head('/listings/?page=2');
	check('/listings/?page=2 answers 200 (no redirect loop)', s1 === 200, String(s1));
	const [s2, l2] = await head('/listings/page/2/');
	check('/listings/page/2/ → /listings/?page=2', s2 === 302 && /\/listings\/\?page=2$/.test(l2), `${s2} ${l2}`);
	const [s3] = await head('/listing-category/services/?page=2');
	check('category archive ?page=2 answers 200', s3 === 200, String(s3));

	const hctx = await browser.newContext(phone);
	const h = await hctx.newPage();
	h.on('pageerror', (e) => ownError(e) && errors.push(String(e)));
	await h.goto(base + '/', { waitUntil: 'networkidle' });
	await Promise.all([h.waitForURL(/\/listings\/$/), h.locator('a.th-section-head__link[href$="/listings/"]').first().click()]);
	const total = Number(wp('post list --post_type=rtcl_listing --post_status=publish --format=count'));
	for (let i = 0; i < 10 && (await h.locator('[data-th-load-more]').count()); i++) {
		await h.locator('[data-th-load-more]').tap();
		await h.waitForFunction(() => !document.querySelector('[data-th-load-more][aria-busy]'), null, { timeout: 15000 }).catch(() => {});
	}
	const shown = await h.locator('main [data-th-results] .th-card').count();
	check('home "View all" → Load more until the end shows every live listing', shown === total, `${shown} of ${total}`);
	await hctx.close();

	/* ------------------------------------------------------------ 1.5 Report in the error colour */
	const lctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const l = await lctx.newPage();
	const slug = wp('post list --post_type=rtcl_listing --post_status=publish --posts_per_page=1 --field=url');
	await l.goto(slug);
	const colors = await l.evaluate(() => {
		const a = document.querySelector('.th-listing-head__report');
		const probe = document.createElement('span');
		probe.style.color = 'var(--th-error-text)';
		document.body.append(probe);
		const want = getComputedStyle(probe).color;
		return { want, text: getComputedStyle(a).color, icon: getComputedStyle(a.querySelector('svg')).color };
	});
	check('Report link: label and icon use the error colour token', colors.text === colors.want && colors.icon === colors.want, JSON.stringify(colors));
	await lctx.close();

	/* ------------------------------------------------------------ 2 Service form (step U1) */
	helper('unmigrate');
	const dry = wp('torrehub service-fields');
	check('U1 dry run: Languages single → multiple, Mobile relabelled, old "Yes" answer mapped', /single choice → multiple choice/.test(dry) && /Mobile Service Available\?/.test(dry) && /answer “Yes” → “yes”/.test(dry), dry.split('\n').slice(0, 4).join(' / '));
	check('… and writes nothing', JSON.parse(helper(`rows ${t.service} radio_moa3cxr4`))[0] === 'Yes');
	wp('torrehub service-fields --apply');
	check('U1 apply: answer mapped, second run has nothing to change', JSON.parse(helper(`rows ${t.service} radio_moa3cxr4`))[0] === 'yes' && /Nothing to change/.test(wp('torrehub service-fields')));

	const lang = JSON.parse(helper(`rows ${t.service} select_moa3gux0`));
	const sp = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
	await sp.goto(t.svcUrl);
	const detail = async (label) => sp.evaluate((l) => {
		const tile = [...document.querySelectorAll('.th-detail')].find((d) => d.innerText.toUpperCase().includes(l.toUpperCase()));
		return tile ? tile.innerText.replace(/\s+/g, ' ').trim() : '';
	}, label);
	check('listing page: “Mobile Service Available?” with the new answer text', /Yes – We Come to You/.test(await detail('Mobile Service Available?')), await detail('Mobile Service'));
	const langDetail = await detail('Languages Spoken');
	check('listing page: existing language answers kept', lang.length > 0 && lang.every((v) => new RegExp(v, 'i').test(langDetail)), `${JSON.stringify(lang)} ${langDetail}`);

	// The owner ticks a second language in the listing form and saves (Classified Listing's own save).
	const ectx = await browser.newContext({ viewport: { width: 1280, height: 900 } });
	const e = await frontLogin(ectx, 'user54', 'seller54');
	await e.goto(`${base}/listing-form/edit/${t.service}/`);
	await e.waitForSelector('[data-th-listing-form][data-th-ready]');
	const langBoxes = e.locator('input[type="checkbox"][name="select_moa3gux0[]"]');
	check('listing form: Languages Spoken is a set of checkboxes', (await langBoxes.count()) === 5, String(await langBoxes.count()));
	const sec = await langBoxes.first().evaluate((i) => i.closest('[id^="th-lf-sec"]')?.id || '');
	await e.locator(`a[href="#${sec}"][data-th-lf-goto]`).click();
	const addValue = await e.locator('input[name="select_moa3gux0[]"]:not(:checked)').first().getAttribute('value');
	await e.locator(`input[name="select_moa3gux0[]"][value="${addValue}"]`).check();
	const mobileLabels = await e.locator('input[name="radio_moa3cxr4"]').evaluateAll((ins) => ins.map((i) => i.closest('label')?.innerText.trim()));
	check('listing form: Mobile Service options are the new texts', mobileLabels.join(' | ') === 'Yes – We Come to You | No – On-site Service Only', mobileLabels.join(' | '));
	await e.locator('input[name="radio_moa3cxr4"][value="no_comes_to_you"]').check();
	await Promise.all([e.waitForURL(/th_updated=1/, { timeout: 30000 }), e.click('[data-th-lf-publish]')]);
	const saved = JSON.parse(helper(`rows ${t.service} select_moa3gux0`));
	check('saved: the earlier languages plus the new one (one row each)', saved.length === lang.length + 1 && [...lang, addValue].every((v) => saved.includes(v)), JSON.stringify(saved));
	check('saved: Mobile answer', JSON.parse(helper(`rows ${t.service} radio_moa3cxr4`))[0] === 'no_comes_to_you');
	await ectx.close();

	helper(`publish ${t.service}`);
	await sp.goto(t.svcUrl);
	const both = await detail('Languages Spoken');
	check('listing page: every language in one detail', saved.every((v) => new RegExp(v, 'i').test(both)), both);
	check('listing page: “No – On-site Service Only”', /No – On-site Service Only/.test(await detail('Mobile Service Available?')));
	const inResults = async (qs) => (await (await fetch(`${base}/listing-category/services/?${qs}`)).text()).includes(new URL(t.svcUrl).pathname);
	check('archive filter: the second language finds the listing', await inResults(`f%5Bselect_moa3gux0%5D%5B%5D=${encodeURIComponent(addValue)}`));
	check('archive filter: the first language still finds it', await inResults(`f%5Bselect_moa3gux0%5D%5B%5D=${encodeURIComponent(lang[0])}`));
	const unused = ['English', 'Spanish', 'swedish', 'German', 'other'].find((v) => !saved.includes(v));
	check('archive filter: a language it doesn’t have leaves it out', !(await inResults(`f%5Bselect_moa3gux0%5D%5B%5D=${unused}`)));
	check('archive filter: Mobile “No – On-site Service Only” finds it', await inResults('f%5Bradio_moa3cxr4%5D%5B%5D=no_comes_to_you'));
	await sp.goto(`${base}/listing-category/services/?th_sheet=1`);
	const sheet = await sp.locator('#th-filters').innerText();
	check('filter sheet: Languages as multiple choice, Mobile with the new texts', (await sp.locator('#th-filters input[type="checkbox"][name="f[select_moa3gux0][]"]').count()) === 5 && /Mobile Service Available\?/i.test(sheet) && /Yes – We Come to You/.test(sheet) && /No – On-site Service Only/.test(sheet));
	await sp.context().close();

	/* ------------------------------------------------------------ 3 search in any language */
	const seed = wp('torrehub seed-search-keywords');
	check('U2 dry run: every category already seeded (reset-db applied it), nothing to fill', /Nothing to fill/.test(seed), seed.split('\n').slice(-1)[0]);
	const al = JSON.parse(helper('aliases'));
	const found = async (q, path = '/listings/') => {
		const html = await (await fetch(`${base}${path}?q=${encodeURIComponent(q)}`)).text();
		return { a: html.includes(new URL(al.a).pathname), b: html.includes(new URL(al.b).pathname) };
	};
	for (const q of ['fontanero', 'FONTANEROS', 'putkimies', 'Klempner', 'vízvezeték-szerelő', 'vizvezetek szerelo', 'rörmokare', 'instalator', 'сантехник', 'сантехнік']) {
		const r = await found(q);
		check(`“${q}” finds the Plumbing listing and the one mentioning a plumber`, r.a && r.b, JSON.stringify(r));
	}
	const filtered = await found('fontanero', '/listing-category/marketplace/');
	check('category filter still applies (Marketplace + “fontanero”: only the shed)', !filtered.a && filtered.b, JSON.stringify(filtered));
	const own = await found('E2E Pipe Pro');
	check('plain search unchanged (title words still find it, the other one not)', own.a && !own.b, JSON.stringify(own));
	const none = await found('qwertyzx');
	check('search without a keyword match finds nothing extra', !none.a && !none.b);
	const count = JSON.parse(await (await fetch(`${base}/listings/?q=fontanero&th_count=1`)).text());
	check('live count follows the expanded search', count.count >= 2, JSON.stringify(count));
	const alertHits = JSON.parse(helper('matcher fontanero'));
	check('saved search with “fontanero” matches both listings', alertHits.includes(al.aId) && alertHits.includes(al.bId), JSON.stringify(alertHits));

	// Admin: keywords edited on the category screen work at once (the cached map is rebuilt).
	const adm = await adminLogin();
	await adm.goto(`${base}/wp-admin/term.php?taxonomy=rtcl_category&tag_ID=${al.plumbing}&post_type=rtcl_listing`);
	check('category screen: one keyword field per site language (9)', (await adm.locator('tr.th-aliases input[name^="th_alias["]').count()) === 9);
	await adm.fill('#th-alias-es', 'fontanero, fontanería, desatascos, cañeríaprueba');
	await Promise.all([adm.waitForNavigation(), adm.locator('#edittag input[type="submit"].button-primary, #edittag [type="submit"]').first().click()]);
	check('… a new keyword finds the category’s listing right after saving', (await found('cañeríaprueba')).a);
	await adm.context().close();

	/* ------------------------------------------------------------ 4.1 Verified badge in the admin */
	const rv = JSON.parse(helper('review'));
	const sellerId = rv.seller;
	const a4 = await adminLogin();
	const usersUrl = `${base}/wp-admin/users.php`;
	const seller54 = (p) => p.locator(`#the-list tr:has(#user_${sellerId})`);
	if (helper(`verified ${sellerId}`) !== '1') {
		await a4.goto(`${usersUrl}?s=user54`);
		await Promise.all([a4.waitForURL(/th_verified_done/), seller54(a4).locator('td.column-th_verified a').click()]);
	}
	await a4.goto(`${usersUrl}?th_verified=yes`);
	check('Users › Verified filter lists the verified seller', (await seller54(a4).count()) === 1);
	await a4.goto(`${usersUrl}?th_verified=no`);
	check('Users › Not verified filter leaves them out', (await seller54(a4).count()) === 0);
	await a4.goto(`${usersUrl}?s=user54`);
	check('Users list: “Verified” column with the switch', /Verified/.test(await seller54(a4).locator('td.column-th_verified').innerText()) && /Remove badge/.test(await seller54(a4).locator('td.column-th_verified').innerText()));
	await Promise.all([a4.waitForURL(/th_verified_done=off/), seller54(a4).locator('td.column-th_verified a').click()]);
	check('row switch removes the badge', helper(`verified ${sellerId}`) === '0' && /Not verified/.test(await seller54(a4).locator('td.column-th_verified').innerText()));

	const pub = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
	await pub.goto(`${base}/listings/?seller=user54`);
	const cardsOff = await pub.locator('.th-card__verified').count();
	await pub.goto(rv.url);
	const pageOff = await pub.locator('.th-listing-seller__verified').count();
	check('revoked: no Verified badge on the cards and on the listing page', cardsOff === 0 && pageOff === 0, `${cardsOff}/${pageOff}`);
	const vOnly = await (await fetch(`${base}/listings/?verified=1&th_count=1`)).json();
	await a4.goto(`${base}/wp-admin/edit.php?post_type=rtcl_listing&th_verified=yes`);
	const listedYes = await a4.locator('#the-list tr:not(.no-items)').count();
	check('Listings › Verified filter: none of their listings now', listedYes === 0 || !(await a4.locator('#the-list').innerText()).includes(rv.title), `${listedYes}, verified-only archive count ${vOnly.count}`);

	await a4.goto(`${usersUrl}?s=user54`);
	await a4.check(`#user_${sellerId}`);
	await a4.selectOption('#bulk-action-selector-top', 'th_verify');
	await Promise.all([a4.waitForURL(/th_verified_done=on/), a4.click('#doaction')]);
	check('bulk “Mark verified” gives the badge back', helper(`verified ${sellerId}`) === '1' && /1 seller marked verified/.test(await a4.locator('.notice-success').first().innerText()));
	await pub.goto(`${base}/listings/?seller=user54`);
	check('… and it shows on the cards again', (await pub.locator('.th-card__verified').count()) > 0);
	await a4.goto(`${base}/wp-admin/edit.php?post_type=rtcl_listing&th_verified=yes`);
	check('Listings › Verified filter lists their listings', (await a4.locator('#the-list').innerText()).includes(rv.title));
	await a4.goto(`${base}/wp-admin/edit.php?post_type=rtcl_listing&th_verified=no`);
	check('Listings › Not verified filter leaves them out', !(await a4.locator('#the-list').innerText()).includes(rv.title));

	/* ------------------------------------------------------------ 4.2 review e-mail to the seller */
	await fetch(`${mailpit}/messages`, { method: 'DELETE' });
	await a4.goto(`${base}/wp-admin/edit-comments.php?comment_status=moderated`);
	const crow = a4.locator(`#comment-${rv.comment}`);
	await crow.hover();
	await crow.locator('.approve a').click();
	await a4.waitForTimeout(2500);
	const all = (await (await fetch(`${mailpit}/messages`)).json()).messages || [];
	const toSeller = all.filter((m) => m.To.some((t) => /user54@/.test(t.Address)));
	const reviewMail = toSeller.find((m) => /New review/.test(m.Subject));
	const full = reviewMail ? await (await fetch(`${mailpit}/message/${reviewMail.ID}`)).json() : { Text: '', HTML: '' };
	check('approving a review e-mails the seller once', toSeller.length === 1 && !!reviewMail, toSeller.map((m) => m.Subject).join(' | '));
	check('… with the reviewer’s name, the rating, the text and the listing link', /Rita Reviewer/.test(full.Text) && /★★★★☆/.test(full.Text) && /4 out of 5/.test(full.Text) && /Great service, quick and friendly/.test(full.Text) && full.Text.includes(rv.url));
	check('… never the reviewer’s e-mail address', !/e2e-reviewer@example\.test/.test(full.Text + full.HTML));
	const chatLink = (full.Text.match(/Reply via chat: (\S+)/) || [])[1] || '';
	check('… and a “Reply via chat” link for a new conversation with the reviewer', new RegExp(`listing=${rv.listing}`).test(chatLink) && new RegExp(`buyer=${rv.reviewer}`).test(chatLink), chatLink);

	const sctx4 = await browser.newContext({ viewport: { width: 1280, height: 900 } });
	const s4 = await frontLogin(sctx4, 'user54', 'seller54');
	await s4.goto(chatLink);
	check('chat opens a new conversation with the reviewer', /Write to Rita Reviewer about their review/.test(await s4.locator('main').innerText()) && (await s4.locator('form[data-th-chat-form] input[name="buyer"]').count()) === 1);
	await fetch(`${mailpit}/messages`, { method: 'DELETE' });
	await s4.fill('#th-chat-message', 'Thank you for the kind review, Rita!');
	await Promise.all([s4.waitForURL(/thread=\d+/), s4.click('form[data-th-chat-form] [type="submit"]')]);
	check('the seller’s first message starts the thread', /Thank you for the kind review/.test(await s4.locator('.th-chat__messages').innerText()));
	await new Promise((r) => setTimeout(r, 800));
	const toReviewer = await mails('to:e2e-reviewer@example.test');
	check('the reviewer is told about the message', toReviewer.length >= 1, String(toReviewer.length));
	const other = Number(wp('user get tester --field=ID'));
	await s4.goto(`${base}/my-account/chat/?listing=${rv.listing}&buyer=${other}`);
	check('no conversation can be started with someone who didn’t review the listing', (await s4.locator('form[data-th-chat-form] input[name="buyer"]').count()) === 0);
	const forged = await s4.evaluate(async ({ listing, buyer }) => {
		const r = await fetch('/wp-json/torrehub/v1/chat/threads', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.torrehub?.config?.chat?.nonce || '' }, body: JSON.stringify({ listing_id: listing, buyer_id: buyer, message: 'spam' }) });
		return r.status;
	}, { listing: rv.listing, buyer: other });
	check('… not even with a forged request', forged >= 400, String(forged));
	await sctx4.close();

	/* ------------------------------------------------------------ 4.3 messages to all users */
	const page4 = `${base}/wp-admin/users.php?page=th-notify-users`;
	const go = async (sel, re) => { const l = a4.locator(sel); await l.scrollIntoViewIfNeeded(); await a4.waitForTimeout(250); await Promise.all([a4.waitForURL(re), l.click()]); };
	await a4.goto(page4);
	await a4.fill('input[name=batch]', '2');
	await a4.fill('input[name=interval]', '1');
	await go('form:has(input[name=batch]) [type=submit]', /th_msg=saved/);
	await fetch(`${mailpit}/messages`, { method: 'DELETE' });
	await a4.fill('#th-bc-subject', 'E2E news');
	await a4.fill('#th-bc-body', 'Hello {name},\n\nE2E broadcast body.');
	await a4.check('input[name="roles[]"][value=customer]');
	await go('button[value=test]', /th_msg=test/);
	const test = await mails('subject:"[Test] E2E news"');
	check('test e-mail goes to me only', test.length === 1 && test[0].To[0].Address === 'dev@example.test');
	check('“Start sending” is locked until a preview', await a4.locator('button[value=start]').isDisabled());
	await go('button[value=preview]', /th_msg=preview/);
	await a4.fill('#th-bc-body', 'Edited after the preview');
	a4.once('dialog', (d) => d.accept());
	await go('button[value=start]', /th_msg=preview-first/);
	check('… and refused on the server when the text changed after the preview', /Preview this exact message/.test(await a4.locator('.th-broadcast > .notice').first().innerText()));
	await a4.fill('#th-bc-body', 'Hello {name},\n\nE2E broadcast body.');
	await go('button[value=preview]', /th_msg=preview/);
	const startLabel = await a4.locator('button[value=start]').innerText();
	const nUsers = Number((startLabel.match(/(\d+)/) || [])[1] || 0);
	check('preview shows the e-mail and the number of recipients (role filter)', (await a4.locator('iframe').count()) === 1 && nUsers >= 3, startLabel.trim());
	a4.once('dialog', (d) => d.accept());
	await go('button[value=start]', /th_msg=started/);
	const camp = Number(helper('bc-last'));
	let c = JSON.parse(helper(`bc-run ${camp}`));
	check('first batch: batch size respected', c.sent === 2 && c.queued === nUsers - 2, JSON.stringify(c));
	helper(`bc-stale ${camp}`);
	c = JSON.parse(helper(`bc-run ${camp} fail`));
	check('interrupted e-mail becomes “unknown”, refused ones “failed”', c.unknown === 1 && c.failed >= 1 && c.sent === 2, JSON.stringify(c));
	await a4.goto(page4);
	const crow4 = a4.locator(`tr[data-campaign="${camp}"]`);
	await Promise.all([a4.waitForURL(/th_msg=updated/), crow4.locator('button:has-text("Pause")').click()]);
	check('pause stops the scheduled batches', /Paused/.test(await a4.locator(`tr[data-campaign="${camp}"] .th-broadcast__status`).innerText()) && wp(`cron event list --hook=th_broadcast_batch --format=count`) === '0');
	await Promise.all([a4.waitForURL(/th_msg=updated/), a4.locator(`tr[data-campaign="${camp}"] button:has-text("Resume")`).click()]);
	await Promise.all([a4.waitForURL(/th_msg=updated/), a4.locator(`tr[data-campaign="${camp}"] button:has-text("Retry failed")`).click()]);
	for (let i = 0; i < 6 && JSON.parse(helper(`bc-counts ${camp}`)).queued > 0; i++) {
		helper(`bc-run ${camp}`);
	}
	c = JSON.parse(helper(`bc-counts ${camp}`));
	await a4.goto(page4);
	check('resumed and retried: finished, the interrupted one not resent', c.sent === nUsers - 1 && c.unknown === 1 && c.queued === 0 && /Finished/.test(await a4.locator(`tr[data-campaign="${camp}"] .th-broadcast__status`).innerText()), JSON.stringify(c));
	const sentMails = (await mails('subject:"E2E news"')).filter((m) => !/\[Test\]/.test(m.Subject));
	const recipients = sentMails.map((m) => m.To[0].Address);
	check('nobody got it twice', recipients.length === new Set(recipients).size && recipients.length === nUsers - 1, `${recipients.length} mails, ${new Set(recipients).size} people`);
	const body = sentMails[0] ? (await (await fetch(`${mailpit}/message/${sentMails[0].ID}`)).json()).Text : '';
	check('{name} filled in, paragraphs kept', /Hello [^{]+,/.test(body) && /E2E broadcast body\./.test(body), body.slice(0, 120));
	await a4.locator(`tr[data-campaign="${camp}"] + tr details summary`).click();
	check('log and problems listed', /Batch: 2 sent/.test(await a4.locator(`tr[data-campaign="${camp}"] + tr details`).innerText()) && /Interrupted/.test(await a4.locator(`tr[data-campaign="${camp}"] + tr details`).innerText()));

	// Account-sidebar notice.
	await a4.check('input[name=notice_enabled]');
	await a4.fill('input[name=notice_date]', '2026-11-05');
	await go('form:has(input[name=notice_enabled]) [type=submit]', /th_msg=saved/);
	const nctx4 = await browser.newContext({ ...phone });
	const n4 = await frontLogin(nctx4, 'user54', 'seller54');
	const noticeText = await n4.locator('.th-account-notice').innerText().catch(() => '');
	check('logged-in users see the notice in the account sidebar', /Terms updated/.test(noticeText) && /05\.11\.2026|5 Nov/.test(noticeText), noticeText.replace(/\s+/g, ' '));
	await Promise.all([n4.waitForURL(/my-account/), n4.locator('.th-account-notice button[type=submit]').click()]);
	await n4.goto(`${base}/my-account/listings/`);
	check('dismissed: gone on every account page', (await n4.locator('.th-account-notice').count()) === 0);
	await a4.goto(page4);
	await a4.fill('input[name=notice_date]', '2026-11-06');
	await go('form:has(input[name=notice_enabled]) [type=submit]', /th_msg=saved/);
	await n4.goto(`${base}/my-account/`);
	check('changing the date shows it again', (await n4.locator('.th-account-notice').count()) === 1);
	await nctx4.close();
	await pub.context().close();
	await a4.context().close();

	/* ------------------------------------------------------------ 5 favourites */
	check('U3: favourites already on after reset-db (nothing to change)', /Nothing to change/.test(wp('torrehub enable-favourites')));
	helper('fav-clear tester');
	const gctx5 = await browser.newContext({ viewport: { width: 1440, height: 900 } });
	const g5 = await gctx5.newPage();
	g5.on('pageerror', (e) => ownError(e) && errors.push(String(e)));
	await g5.goto(`${base}/listings/`, { waitUntil: 'networkidle' });
	const gHeart = g5.locator('.th-card__fav a').first();
	const gHref = decodeURIComponent((await gHeart.getAttribute('href')) || '');
	check('guest: the heart is a link to the login page that comes back with the listing', /\/login\/\?redirect_to=.*th_fav=\d+&th_fav_t=[0-9a-f]{16}/.test(gHref), gHref.slice(0, 120));
	const favId = Number((gHref.match(/th_fav=(\d+)/) || [])[1]);
	await Promise.all([g5.waitForURL(/\/login\//), gHeart.click()]);
	await g5.fill('#th-log', 'tester');
	await g5.fill('#th-pwd', 'tester');
	await Promise.all([g5.waitForURL(/\/listings\/$/), g5.click('.th-auth-form [type="submit"]')]);
	check('after logging in: back on the same page, clean URL, listing saved', JSON.parse(helper('favs tester')).includes(favId) && !/th_fav/.test(g5.url()), g5.url());
	check('… and the heart shows it', (await g5.locator(`[data-th-fav="${favId}"]`).first().getAttribute('aria-pressed')) === 'true');
	await g5.goto(`${base}/listings/?th_fav=${favId + 1}&th_fav_t=0000000000000000`);
	check('a forged “save” link saves nothing', !JSON.parse(helper('favs tester')).includes(favId + 1) && !/th_fav/.test(g5.url()));

	const other5 = g5.locator('.th-card [data-th-fav][aria-pressed="false"]').first();
	const otherId = Number(await other5.getAttribute('data-th-fav'));
	await other5.click();
	await g5.waitForFunction((id) => document.querySelector(`[data-th-fav="${id}"]`)?.getAttribute('aria-pressed') === 'true', otherId);
	await g5.waitForTimeout(600);
	check('logged in: the card heart toggles (aria-pressed) and saves', JSON.parse(helper('favs tester')).includes(otherId));
	await g5.goto(`${base}/?p=${otherId}`);
	const chip5 = g5.locator('.th-listing-head__action[data-th-fav]');
	check('listing page: the Save chip shows it pressed', (await chip5.getAttribute('aria-pressed')) === 'true');
	await chip5.click();
	await g5.waitForFunction(() => document.querySelector('.th-listing-head__action[data-th-fav]')?.getAttribute('aria-pressed') === 'false');
	await g5.waitForTimeout(600);
	check('… and removes it again', !JSON.parse(helper('favs tester')).includes(otherId));
	await chip5.click();
	await g5.waitForTimeout(900);

	await g5.goto(`${base}/my-account/favourites/`);
	const nav5 = (await g5.locator('.th-account__link').allInnerTexts()).map((t) => t.trim());
	check('member: “Favourites” in the account menu, Saved searches still there', nav5.some((t) => /^Favourites/.test(t)) && nav5.some((t) => /^Saved searches/.test(t)), nav5.join(' | '));
	const cardTitles = await g5.locator('[data-th-fav-list] .th-card__title').allInnerTexts();
	check('Favourites: the saved listings as cards, with their own titles', cardTitles.length === 2 && !cardTitles.some((t) => /Favourites/.test(t)), cardTitles.join(' | '));
	await g5.locator('[data-th-fav-list] [data-th-fav]').first().click();
	await g5.waitForFunction(() => document.querySelectorAll('[data-th-fav-list] .th-card').length === 1);
	await g5.locator('[data-th-fav-list] [data-th-fav]').first().click();
	await g5.waitForFunction(() => !document.querySelector('[data-th-fav-empty]')?.hidden);
	await g5.waitForTimeout(600);
	check('removing in the list takes the card out; the last one shows the empty state', JSON.parse(helper('favs tester')).length === 0);
	await gctx5.close();

	const sctx5 = await browser.newContext({ viewport: { width: 1280, height: 900 } });
	const s5 = await frontLogin(sctx5, 'user54', 'seller54');
	check('seller: “Favourites” in the account menu too', (await s5.locator('.th-account__link').allInnerTexts()).some((t) => /^Favourites/.test(t.trim())));
	await sctx5.close();

	const pctx5 = await browser.newContext({ ...phone });
	const p5 = await pctx5.newPage();
	await p5.goto(`${base}/?p=${favId}`);
	check('phone, guest: heart in the contact bar is the login link', /\/login\/\?redirect_to=/.test((await p5.locator('.th-contact-bar__fav').getAttribute('href')) || ''));
	await pctx5.close();

	/* ------------------------------------------------------------ 6.1 the 12 main towns */
	const live12 = JSON.parse(helper('towns-live'));
	const want12 = ['Alicante', 'Altea', 'Benidorm', 'Benissa Costa', 'Calp', 'Dénia', 'Elche (Elx)', 'Guardamar del Segura', 'Orihuela Costa', 'Santa Pola', 'Torrevieja', 'Xàbia'];
	check('home towns = the 12 top-level locations, alphabetical', JSON.stringify(live12) === JSON.stringify(want12), live12.join(', '));
	for (const [w, cols] of [[1440, 6], [1000, 4], [700, 3], [390, 2]]) {
		const tp = await (await browser.newContext({ viewport: { width: w, height: 900 } })).newPage();
		await tp.goto(`${base}/`);
		const names = await tp.locator('.th-home__town-list .th-town__name').allInnerTexts();
		const got = await tp.evaluate(() => getComputedStyle(document.querySelector('.th-home__town-list')).gridTemplateColumns.split(' ').length);
		check(`${w}px: ${cols} towns per row, ${12 / cols} full rows (no gaps)`, got === cols && names.length === 12 && JSON.stringify(names) === JSON.stringify(want12), `${got} cols, ${names.length} towns`);
		await tp.context().close();
	}

	/* ------------------------------------------------------------ 6.2 Guides */
	const totalGuides = Number(helper('guides 11'));
	helper('guides-intro "Practical know-how for life on the Costa Blanca."');
	const gd = await (await browser.newContext({ viewport: { width: 1440, height: 1000 } })).newPage();
	gd.on('pageerror', (e) => ownError(e) && errors.push(String(e)));
	await gd.goto(`${base}/guides/`, { waitUntil: 'networkidle' });
	const gcols = await gd.evaluate(() => getComputedStyle(document.querySelector('[data-th-guides-list]')).gridTemplateColumns.split(' ').length);
	check('Guides: 9 guides a page as cards, 3 per row', (await gd.locator('[data-th-guides-list] > li .th-guide-card').count()) === 9 && gcols === 3, String(gcols));
	const featTitle = await gd.locator('.th-guide-featured__title').innerText();
	const featAbove = await gd.evaluate(() => document.querySelector('.th-guide-featured').compareDocumentPosition(document.querySelector('[data-th-guides-list]')) & Node.DOCUMENT_POSITION_FOLLOWING);
	check('… page 1: the featured guide above the grid, not repeated in it', featAbove > 0 && !(await gd.locator('[data-th-guides-list] .th-guide-card__title').allInnerTexts()).includes(featTitle), featTitle);
	check('… the posts page’s excerpt as intro under the heading', (await gd.locator('.th-guides__intro').innerText()) === 'Practical know-how for life on the Costa Blanca.');
	const moreHref = await gd.locator('[data-th-guides-more]').getAttribute('href');
	check('… “Load more guides” is a real /page/2/ link', /\/guides\/page\/2\/$/.test(moreHref || ''), moreHref);
	await gd.locator('[data-th-guides-more]').click();
	await gd.waitForFunction(() => document.querySelectorAll('[data-th-guides-list] > li').length > 9);
	check('… with JS the next page is added in place', (await gd.locator('[data-th-guides-list] > li').count()) === Math.min(18, totalGuides - 1) && /\/guides\/$/.test(gd.url()));
	const g1 = gd.locator('[data-th-guides-list] .th-guide-card__title a').first();
	const g1href = await g1.getAttribute('href');
	await Promise.all([gd.waitForURL(g1href), g1.click()]);
	check('… a card opens the whole guide', (await gd.locator('[data-th-guide]').count()) === 1);
	await gd.context().close();
	const gnj = await (await browser.newContext({ javaScriptEnabled: false })).newPage();
	await gnj.goto(`${base}/guides/`);
	await Promise.all([gnj.waitForURL(/\/guides\/page\/2\/$/), gnj.locator('[data-th-guides-more]').click()]);
	check('… without JS the link opens page 2', (await gnj.locator('[data-th-guides-list] > li').count()) === totalGuides - 10);
	await gnj.context().close();

	/* ------------------------------------------------------------ 6.3 Latest guides on the home page */
	const hm = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
	await hm.goto(`${base}/`);
	check('home: “Latest guides” with the 3 newest as cards and the intro', (await hm.locator('.th-home__guides .th-guide-card').count()) === 3 && (await hm.locator('.th-home__guides-intro').count()) === 1);
	wp('theme mod set th_home_guides_intro_on 0');
	await hm.reload();
	check('… the intro can be switched off (Customizer)', (await hm.locator('.th-home__guides-intro').count()) === 0 && (await hm.locator('.th-home__guides .th-guide-card').count()) === 3);
	wp('theme mod set th_home_guides 0');
	await hm.reload();
	check('… and the whole block', (await hm.locator('.th-home__guides').count()) === 0);
	wp('theme mod remove th_home_guides th_home_guides_intro_on');
	await hm.context().close();

	check('no JS errors', errors.length === 0, errors.slice(0, 3).join(' | '));
} finally {
	helper('cleanup');
	await browser.close();
}
process.exit(failures ? 1 : 0);
