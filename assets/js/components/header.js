/**
 * Header behaviour: Explore mega panel (G-04), language menu (G-06), town picker + geolocation (G-05),
 * "show all" reveal buttons. Everything degrades: links work without JS.
 */

import { config } from '../lib/config.js';

/* ----------------------------------------------------------------- disclosure helper */

function disclosure(button, panel, { onOpen, onClose } = {}) {
	const isOpen = () => button.getAttribute('aria-expanded') === 'true';
	const close = ({ focus = false } = {}) => {
		if (!isOpen()) return;
		button.setAttribute('aria-expanded', 'false');
		panel.hidden = true;
		onClose?.();
		if (focus) button.focus();
	};
	const open = () => {
		button.setAttribute('aria-expanded', 'true');
		panel.hidden = false;
		onOpen?.();
	};
	button.addEventListener('click', () => (isOpen() ? close() : open()));
	document.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && isOpen()) {
			e.stopPropagation();
			close({ focus: true });
		}
	});
	document.addEventListener('click', (e) => {
		if (isOpen() && !panel.contains(e.target) && !button.contains(e.target)) close();
	});
	return { open, close, isOpen };
}

/* ----------------------------------------------------------------- mega panel (vertical tabs) */

function initMega(root) {
	const toggle = root.querySelector('[data-th-mega-toggle]');
	const panel = document.getElementById(toggle?.getAttribute('aria-controls') || '');
	if (!toggle || !panel) return;

	const tabs = [...panel.querySelectorAll('[role="tab"]')];
	const select = (tab, { focus = false } = {}) => {
		tabs.forEach((t) => {
			const on = t === tab;
			t.setAttribute('aria-selected', String(on));
			t.tabIndex = on ? 0 : -1;
			const p = document.getElementById(t.getAttribute('aria-controls'));
			if (p) p.hidden = !on;
		});
		if (focus) tab.focus();
	};

	disclosure(toggle, panel, {
		onOpen: () => panel.querySelector('[role="tab"][aria-selected="true"]')?.focus(),
	});

	tabs.forEach((tab, i) => {
		tab.addEventListener('click', () => select(tab));
		tab.addEventListener('mouseenter', () => select(tab));
		tab.addEventListener('keydown', (e) => {
			const map = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
			if (e.key in map) {
				e.preventDefault();
				select(tabs[(i + map[e.key] + tabs.length) % tabs.length], { focus: true });
			} else if (e.key === 'Home') {
				e.preventDefault();
				select(tabs[0], { focus: true });
			} else if (e.key === 'End') {
				e.preventDefault();
				select(tabs[tabs.length - 1], { focus: true });
			}
		});
	});
}

/* ----------------------------------------------------------------- language menu */

function initLanguage(root) {
	root.querySelectorAll('[data-th-lang]').forEach((wrap) => {
		const button = wrap.querySelector('.th-lang__toggle');
		const menu = wrap.querySelector('.th-lang__menu');
		if (button && menu) {
			const d = disclosure(button, menu);
			menu.addEventListener('click', (e) => {
				if (e.target.closest('[data-gt-lang]')) d.close();
			});
		}
	});

	// GTranslate's base.js marks the active link with .gt-current-lang; mirror it in the "EN ▾" label.
	const sync = () => {
		const active = document.querySelector('a[data-gt-lang].gt-current-lang');
		if (!active) return;
		const code = active.dataset.gtLang.slice(0, 2).toUpperCase();
		document.querySelectorAll('[data-th-lang-current]').forEach((el) => {
			if (el.textContent !== code) el.textContent = code;
		});
		document.querySelectorAll('a[data-gt-lang]').forEach((a) => {
			if (a.dataset.gtLang === active.dataset.gtLang) a.setAttribute('aria-current', 'true');
			else a.removeAttribute('aria-current');
		});
	};
	new MutationObserver(sync).observe(document.body, { subtree: true, attributes: true, attributeFilter: ['class'] });
	sync();
}

/* ----------------------------------------------------------------- town picker + geolocation */

function setTown(slug) {
	const loc = config.location || {};
	const secure = location.protocol === 'https:' ? '; secure' : '';
	document.cookie = `${loc.cookie || 'th_location'}=${encodeURIComponent(slug)}; path=${loc.path || '/'}; max-age=31536000; samesite=lax${secure}`;
	location.reload();
}

function nearestTown(lat, lng) {
	const towns = config.location?.towns || [];
	const rad = (d) => (d * Math.PI) / 180;
	let best = null;
	let bestD = Infinity;
	for (const [slug, tLat, tLng] of towns) {
		const dLat = rad(tLat - lat);
		const dLng = rad(tLng - lng);
		const a = Math.sin(dLat / 2) ** 2 + Math.cos(rad(lat)) * Math.cos(rad(tLat)) * Math.sin(dLng / 2) ** 2;
		const d = 2 * 6371 * Math.asin(Math.sqrt(a));
		if (d < bestD) {
			bestD = d;
			best = slug;
		}
	}
	return best;
}

function initLocation(root) {
	root.addEventListener('click', (e) => {
		const link = e.target.closest('[data-th-town]');
		if (link) {
			e.preventDefault();
			setTown(link.dataset.thTown);
			return;
		}

		const locate = e.target.closest('[data-th-locate]');
		if (!locate) return;
		const i18n = config.location?.i18n || {};
		const status = document.querySelector('[data-th-locate-status]');
		const say = (msg) => {
			if (status) status.textContent = msg;
			else if (window.torrehub?.toast) window.torrehub.toast(msg);
		};
		if (!('geolocation' in navigator)) {
			say(i18n.unavailable || '');
			return;
		}
		locate.setAttribute('aria-busy', 'true');
		say(i18n.locating || '');
		navigator.geolocation.getCurrentPosition(
			(pos) => {
				// The position never leaves the browser: only the nearest town's slug is stored.
				const slug = nearestTown(pos.coords.latitude, pos.coords.longitude);
				locate.removeAttribute('aria-busy');
				if (slug) setTown(slug);
			},
			(err) => {
				locate.removeAttribute('aria-busy');
				say(err.code === err.PERMISSION_DENIED ? i18n.denied || '' : i18n.unavailable || '');
			},
			{ enableHighAccuracy: false, timeout: 10000, maximumAge: 600000 }
		);
	});

	const filter = root.querySelector('[data-th-town-filter]');
	const list = root.querySelector('[data-th-town-list]');
	const empty = root.querySelector('[data-th-town-empty]');
	if (filter && list) {
		const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
		filter.addEventListener('input', () => {
			const q = norm(filter.value.trim());
			let shown = 0;
			list.querySelectorAll('li').forEach((li) => {
				const hit = !q || norm(li.dataset.name || '').includes(q);
				li.hidden = !hit;
				if (hit) shown++;
			});
			if (empty) empty.hidden = shown > 0;
		});
	}
}

/* ----------------------------------------------------------------- reveal ("Show all 10") */

function initReveal(root) {
	root.querySelectorAll('[data-th-reveal]').forEach((btn) => {
		btn.addEventListener('click', () => {
			const target = document.getElementById(btn.dataset.thReveal);
			if (!target) return;
			target.classList.add('is-revealed');
			btn.setAttribute('aria-expanded', 'true');
			btn.hidden = true;
			target.querySelector(':scope > li:nth-child(7) a')?.focus();
		});
	});
}

export function init(root = document) {
	initMega(root);
	initLanguage(root);
	initLocation(root);
	initReveal(root);
}
