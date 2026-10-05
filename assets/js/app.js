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

// Toasts are used by many modules: expose a tiny global for non-module callers (e.g. inline handlers in RTCL markup).
window.torrehub = Object.assign(window.torrehub || {}, {
	config,
	toast: (...args) => import('./components/toast.js').then((m) => m.toast(...args)),
	boot,
});
