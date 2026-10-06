/**
 * Torrehub front-end entry (ES module, no jQuery).
 * Components are initialised from data attributes; each module is imported only if the page uses it.
 */

import { config } from './lib/config.js';

const lazy = [
	['[data-th-header]', () => import('./components/header.js')],
	['[data-th-dialog]', () => import('./components/dialog.js')],
	['[data-th-password]', () => import('./components/password.js')],
	['[data-th-range]', () => import('./components/range.js')],
	['[data-th-counter]', () => import('./components/counter.js')],
	['[data-th-toast-demo]', () => import('./components/toast.js')],
	['[data-th-archive]', () => import('./components/archive.js')],
	['[data-th-map], [data-th-map-single]', () => import('./components/map.js')],
	['[data-th-listing]', () => import('./components/listing.js')],
	['[data-th-gallery]', () => import('./components/gallery.js')],
	['form[data-th-confirm], input[data-th-nif]', () => import('./components/forms.js')],
	['[data-th-fav]', () => import('./components/favourites.js')],
	['[data-th-listing-form]', () => import('./components/listing-form.js')],
	['[data-th-chat]', () => import('./components/chat.js')],
	['[data-th-toc], [data-th-guide], [data-th-faq]', () => import('./components/content.js')],
	['[data-th-consent-bar][data-show="1"], [data-th-embed-gate]', () => import('./components/consent.js')],
];

function boot(root = document) {
	for (const [selector, load] of lazy) {
		if (root.querySelector(selector)) {
			load().then((m) => m.init?.(root));
		}
	}
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', () => boot());
} else {
	boot();
}

// "Cookie settings" (footer, every page): when the page had nothing to ask, the consent module loads on first use.
document.addEventListener('click', (e) => {
	const opener = e.target.closest('[data-th-consent-open]');
	if (opener && !window.torrehub.consent) {
		e.preventDefault();
		import('./components/consent.js').then((m) => {
			m.init();
			window.torrehub.consent.open(opener);
		});
	}
});

// Toasts are used by many modules: expose a tiny global for non-module callers (e.g. inline handlers in RTCL markup).
window.torrehub = Object.assign(window.torrehub || {}, {
	config,
	toast: (...args) => import('./components/toast.js').then((m) => m.toast(...args)),
	boot,
});
