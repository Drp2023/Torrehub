// E2E: client feedback 2026-11 — group 1 (bugs): approval e-mail, phone-width account labels in long languages,
// town picker, "all listings" paging, Report colour; module cache-busting.
//   node _dev/tests/e2e/feedback.mjs [baseUrl]
// Needs (local only): WP-CLI via bin/env.sh (Git Bash), Mailpit, admin dev/dev, business seller user54/seller54,
// the th-local-safety mu-plugin (live-like canonical redirects). The language sweep needs the internet (GTranslate);
// without it those checks are skipped. Reverted by feedback-helper.php cleanup.
import { execSync } from 'node:child_process';
import { chromium, devices } from 'playwright-core';

const base = process.argv[2] || 'http://localhost:10004';
const out = '_dev/screens/theme';
const mailpit = 'http://localhost:10000/api/v1';
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
// GTranslate's own script (cdn.gtranslate.net) throws now and then while it translates; only this site's code counts.
const ownError = (e) => (e.stack || String(e)).includes(new URL(base).host);
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

	check('no JS errors', errors.length === 0, errors.slice(0, 3).join(' | '));
} finally {
	helper('cleanup');
	await browser.close();
}
process.exit(failures ? 1 : 0);
