/**
 * Cookie consent: shows the bar until a choice exists, stores it (first-party cookie, versioned), turns inert
 * `type="text/plain"` scripts / data-src iframes of the agreed categories into live ones, loads gated embeds on
 * click, reopens from "Cookie settings". Without JS nothing optional ever runs.
 */

import { config } from '../lib/config.js';

const cfg = config.consent || {};
const CATS = ['preferences', 'statistics', 'marketing'];

function read() {
	const raw = document.cookie.split('; ').find((c) => c.startsWith(`${cfg.cookie}=`));
	if (!raw) {
		return null;
	}
	try {
		const data = JSON.parse(decodeURIComponent(raw.slice(cfg.cookie.length + 1)));
		return data && Number(data.v) === Number(cfg.version) ? data : null;
	} catch {
		return null;
	}
}

function write(choice) {
	const data = { v: cfg.version, t: Math.round(Date.now() / 1000), c: choice };
	document.cookie = `${cfg.cookie}=${encodeURIComponent(JSON.stringify(data))}; path=/; max-age=${(cfg.days || 365) * 86400}; samesite=lax${cfg.secure ? '; secure' : ''}`;
	return data;
}

/** Turn one inert script into a live one (once). */
function liven(inert) {
	if (inert.dataset.thDone) {
		return;
	}
	inert.dataset.thDone = '1';
	const live = document.createElement('script');
	[...inert.attributes].forEach((a) => {
		if (a.name !== 'type' && !a.name.startsWith('data-th-')) {
			live.setAttribute(a.name, a.value);
		}
	});
	live.text = inert.text;
	inert.after(live);
}

/** Load an iframe kept back until consent. */
function frame(el) {
	el.src = el.dataset.src;
	el.removeAttribute('data-src');
}

/** Activate everything of the granted categories (once). */
function activate(choice) {
	document.querySelectorAll('script[type="text/plain"][data-th-consent]').forEach((inert) => {
		if (choice[inert.dataset.thConsent] && !inert.closest('template')) {
			liven(inert);
		}
	});
	document.querySelectorAll('iframe[data-th-consent][data-src]').forEach((el) => {
		if (choice[el.dataset.thConsent]) {
			frame(el);
		}
	});
	if (choice.marketing) {
		document.querySelectorAll('[data-th-embed-gate]').forEach(loadEmbed);
	}
	if (typeof window.gtag === 'function') {
		window.gtag('consent', 'update', {
			analytics_storage: choice.statistics ? 'granted' : 'denied',
			ad_storage: choice.marketing ? 'granted' : 'denied',
			ad_user_data: choice.marketing ? 'granted' : 'denied',
			ad_personalization: choice.marketing ? 'granted' : 'denied',
		});
	}
	document.dispatchEvent(new CustomEvent('th:consent', { detail: choice }));
}

/** Replace an embed placeholder with the embed; only its own scripts and frames become live. */
function loadEmbed(gate) {
	const tpl = gate?.querySelector('template[data-th-embed]');
	if (!tpl) {
		return;
	}
	const frag = tpl.content.cloneNode(true);
	const scripts = [...frag.querySelectorAll('script[type="text/plain"]')];
	const frames = [...frag.querySelectorAll('iframe[data-src]')];
	gate.replaceWith(frag);
	scripts.forEach(liven);
	frames.forEach(frame);
}

export function init(root = document) {
	if (window.torrehub?.consent) {
		return; // Already running (loaded by the page, then asked again by a "Cookie settings" click).
	}
	const bar = root.querySelector('[data-th-consent-bar]');
	const dialog = document.getElementById('th-consent-dialog');
	let current = read();

	const boxes = () => [...(dialog?.querySelectorAll('[data-th-consent-cat]') || [])];
	const choose = (choice) => {
		const before = current?.c || {};
		current = write(choice);
		if (bar) {
			bar.hidden = true;
		}
		if (dialog?.open) {
			dialog.close();
		}
		// Withdrawn consent: code already running can't be unloaded — reload without it.
		if (CATS.some((c) => before[c] && !choice[c])) {
			window.location.reload();
			return;
		}
		activate(choice);
	};
	const all = (on) => Object.fromEntries(CATS.map((c) => [c, on]));
	const open = (opener) => {
		if (!dialog) {
			return;
		}
		boxes().forEach((b) => { b.checked = !!current?.c?.[b.dataset.thConsentCat]; });
		dialog.showModal();
		dialog.addEventListener('close', () => opener?.focus?.(), { once: true });
	};

	document.addEventListener('click', (e) => {
		const t = e.target.closest('[data-th-consent-accept], [data-th-consent-reject], [data-th-consent-save], [data-th-consent-open], [data-th-consent-close], [data-th-embed-load]');
		if (!t) {
			return;
		}
		if (t.matches('[data-th-consent-accept]')) {
			choose(all(true));
		} else if (t.matches('[data-th-consent-reject]')) {
			choose(all(false));
		} else if (t.matches('[data-th-consent-save]')) {
			choose(Object.fromEntries(boxes().map((b) => [b.dataset.thConsentCat, b.checked])));
		} else if (t.matches('[data-th-consent-open]')) {
			e.preventDefault();
			open(t);
		} else if (t.matches('[data-th-consent-close]')) {
			dialog?.close();
		} else if (t.matches('[data-th-embed-load]')) {
			loadEmbed(t.closest('[data-th-embed-gate]'));
		}
	});
	dialog?.addEventListener('click', (e) => {
		if (e.target === dialog) {
			dialog.close();
		}
	});

	if (current) {
		activate(current.c);
	} else if (bar && bar.dataset.show === '1') {
		bar.hidden = false;
	}

	window.torrehub = Object.assign(window.torrehub || {}, {
		consent: {
			has: (cat) => cat === 'necessary' || !!current?.c?.[cat],
			open: (opener) => open(opener),
		},
	});
}
