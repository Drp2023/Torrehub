/**
 * Single listing enhancements (the page works without them):
 *  - phone / WhatsApp reveal on request (contact module + mobile sheet);
 *  - e-mail enquiry and report sent with fetch (no reload);
 *  - Share (Web Share API, else copy link), Print;
 *  - section tabs mark the section in view; "Back to results" when coming from an archive;
 *  - long descriptions clamp on mobile with Read more; videos load from YouTube only on play.
 */

import { config } from '../lib/config.js';

const data = config.listing || {};
const i18n = data.i18n || {};
const toast = (msg, type = 'info') => window.torrehub?.toast?.(msg, { type });

export function init(root = document) {
	const page = root.querySelector('[data-th-listing]') || document.querySelector('[data-th-listing]');
	if (!page || page.dataset.thReady) {
		return;
	}
	page.dataset.thReady = '1';
	setupReveal(page);
	setupForms(page);
	setupShare(page);
	setupTabs(page);
	setupBack(page);
	setupClamp(page);
	setupVideo(page);
}

/* ------------------------------------------------------------------ contact numbers */

let numbersPromise = null;

function fetchNumbers() {
	if (!numbersPromise) {
		const body = new URLSearchParams({ action: 'th_reveal_contact', listing_id: data.id, _ajax_nonce: data.revealNonce });
		numbersPromise = fetch(config.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })
			.then((r) => r.json().then((json) => ({ ok: r.ok, status: r.status, json })))
			.then(({ ok, status, json }) => {
				if (!ok || !json.success) {
					numbersPromise = null;
					throw new Error(status === 429 ? i18n.tooMany : i18n.error);
				}
				return json.data;
			});
	}
	return numbersPromise;
}

function setLabel(el, text) {
	const span = el.querySelector('span:not(.th-sr-only)');
	(span || el).textContent = text;
}

function setupReveal(page) {
	page.addEventListener('click', (event) => {
		const btn = event.target.closest('[data-th-reveal]');
		if (!btn) {
			return;
		}
		event.preventDefault();
		const kind = btn.dataset.thReveal;
		btn.setAttribute('aria-busy', 'true');
		fetchNumbers()
			.then((n) => {
				if (kind === 'phone' && n.phone) {
					btn.href = `tel:${n.tel}`;
					setLabel(btn, n.phone);
					btn.removeAttribute('data-th-reveal');
					btn.removeAttribute('rel');
					btn.focus();
				} else if (kind === 'whatsapp' && n.wa_link) {
					window.open(n.wa_link, '_blank', 'noopener');
					btn.href = n.wa_link;
					btn.target = '_blank';
					btn.removeAttribute('data-th-reveal');
				}
			})
			.catch((err) => toast(err.message, 'error'))
			.finally(() => btn.removeAttribute('aria-busy'));
	});

	// Mobile sheet: load numbers when it opens.
	const sheet = page.querySelector('[data-th-contact-sheet]');
	if (sheet) {
		const fill = () => {
			const phone = sheet.querySelector('[data-th-sheet-phone]');
			const wa = sheet.querySelector('[data-th-sheet-whatsapp]');
			if (!phone && !wa) {
				return;
			}
			fetchNumbers()
				.then((n) => {
					if (phone) {
						phone.href = `tel:${n.tel}`;
						setLabel(phone, n.phone);
						phone.removeAttribute('aria-busy');
					}
					if (wa && n.wa_link) {
						wa.href = n.wa_link;
					}
				})
				.catch((err) => toast(err.message, 'error'));
		};
		new MutationObserver(() => sheet.open && fill()).observe(sheet, { attributes: true, attributeFilter: ['open'] });
		sheet.querySelector('[data-th-goto-contact]')?.addEventListener('click', () => {
			setTimeout(() => page.querySelector('#th-enquiry-message, #contact')?.focus?.(), 50);
		});
	}
}

/* ------------------------------------------------------------------ enquiry + report */

function setupForms(page) {
	const enquiry = page.querySelector('[data-th-enquiry]');
	enquiry?.addEventListener('submit', (event) => {
		event.preventDefault();
		const status = enquiry.querySelector('[data-th-enquiry-status]');
		const button = enquiry.querySelector('[type="submit"]');
		const body = new FormData(enquiry);
		body.set('action', 'th_contact_seller');
		body.set('_ajax_nonce', body.get('_wpnonce'));
		button.disabled = true;
		status.textContent = i18n.sending || '';
		fetch(config.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })
			.then((r) => r.json())
			.then((res) => {
				status.textContent = res?.data?.message || i18n.error;
				if (res.success) {
					enquiry.querySelector('textarea').value = '';
				}
			})
			.catch(() => (status.textContent = i18n.error))
			.finally(() => (button.disabled = false));
	});

	const report = document.querySelector('[data-th-report] form');
	report?.addEventListener('submit', (event) => {
		event.preventDefault();
		const status = report.querySelector('[data-th-report-status]');
		const body = new FormData(report);
		body.set('action', 'th_report_listing');
		body.set('_ajax_nonce', body.get('_wpnonce'));
		fetch(config.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })
			.then((r) => r.json())
			.then((res) => {
				if (res.success) {
					report.closest('dialog')?.close();
					toast(res.data.message, 'success');
					const link = page.querySelector('.th-listing-head__report[data-th-dialog-open]');
					if (link) {
						link.replaceWith(Object.assign(document.createElement('span'), { className: 'th-listing-head__report is-done', textContent: '✓' }));
					}
				} else {
					status.textContent = res?.data?.message || i18n.error;
				}
			})
			.catch(() => (status.textContent = i18n.error));
	});
}

/* ------------------------------------------------------------------ share / print */

function setupShare(page) {
	const share = page.querySelector('[data-th-share]');
	if (share) {
		share.hidden = false;
		share.addEventListener('click', () => {
			const payload = { title: share.dataset.title, url: share.dataset.url };
			if (navigator.share) {
				navigator.share(payload).catch(() => {});
			} else if (navigator.clipboard) {
				navigator.clipboard.writeText(payload.url).then(() => toast(i18n.copied, 'success'));
			}
		});
	}
	const print = page.querySelector('[data-th-print]');
	if (print) {
		print.hidden = false;
		print.addEventListener('click', () => window.print());
	}
}

/* ------------------------------------------------------------------ tabs, back link, clamp, video */

function setupTabs(page) {
	const nav = page.querySelector('[data-th-tabs]');
	if (!nav || !('IntersectionObserver' in window)) {
		return;
	}
	const links = [...nav.querySelectorAll('a[href^="#"]')];
	const sections = links.map((a) => document.getElementById(a.hash.slice(1))).filter(Boolean);
	const mark = (id) => links.forEach((a) => a.setAttribute('aria-current', String(a.hash === `#${id}`)));
	const io = new IntersectionObserver(
		(entries) => {
			const visible = entries.filter((e) => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
			if (visible[0]) {
				mark(visible[0].target.id);
			}
		},
		{ rootMargin: '-90px 0px -55% 0px' }
	);
	sections.forEach((s) => io.observe(s));

	// The last section may never reach the observed band: mark it when the page bottom is reached (or passed).
	const last = sections[sections.length - 1];
	let ticking = false;
	window.addEventListener(
		'scroll',
		() => {
			if (ticking || !last) {
				return;
			}
			ticking = true;
			requestAnimationFrame(() => {
				ticking = false;
				const bottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4;
				if (bottom || last.getBoundingClientRect().top < window.innerHeight * 0.45) {
					mark(last.id);
				}
			});
		},
		{ passive: true }
	);
}

function setupBack(page) {
	const back = page.querySelector('[data-th-back]');
	if (!back || !document.referrer) {
		return;
	}
	try {
		const ref = new URL(document.referrer);
		const archive = /\/(listings|listing-category|listing-location)\//.test(ref.pathname);
		if (ref.origin === window.location.origin && archive) {
			back.href = ref.href;
			back.hidden = false;
		}
	} catch {
		// Ignore malformed referrers.
	}
}

function setupClamp(page) {
	const box = page.querySelector('[data-th-clamp]');
	if (!box || !window.matchMedia('(max-width: 899px)').matches || box.scrollHeight < 260) {
		return;
	}
	box.classList.add('is-clamped');
	const btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'th-listing__more';
	btn.textContent = i18n.readMore || 'Read more';
	btn.setAttribute('aria-expanded', 'false');
	btn.addEventListener('click', () => {
		const open = box.classList.toggle('is-clamped') === false;
		btn.setAttribute('aria-expanded', String(open));
		btn.textContent = open ? i18n.readLess : i18n.readMore;
	});
	box.after(btn);
}

function setupVideo(page) {
	page.addEventListener('click', (event) => {
		const play = event.target.closest('[data-th-video]');
		if (!play) {
			return;
		}
		const frame = document.createElement('iframe');
		frame.src = `https://www.youtube-nocookie.com/embed/${encodeURIComponent(play.dataset.thVideo)}?autoplay=1`;
		frame.title = play.textContent.trim();
		frame.allow = 'autoplay; encrypted-media; picture-in-picture';
		frame.allowFullscreen = true;
		play.replaceWith(frame);
	});
}
