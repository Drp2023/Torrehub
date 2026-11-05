// E2E: client feedback 2026-11.
//   Group 1 (bugs): approval e-mail, phone-width account labels in long languages, town picker, "all listings"
//   paging, Report colour; module cache-busting.
//   Group 2 (Service form): Languages Spoken → multiple choice, Mobile Service options; migration step U1.
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

	check('no JS errors', errors.length === 0, errors.slice(0, 3).join(' | '));
} finally {
	helper('cleanup');
	await browser.close();
}
process.exit(failures ? 1 : 0);
