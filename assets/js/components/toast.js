/**
 * Toasts: polite live region, auto-dismiss (errors stay until closed or replaced).
 *
 * import { toast } from './toast.js';
 * toast('Saved to favourites', { type: 'success' });
 */

import { config } from '../lib/config.js';

let region;

function ensureRegion() {
	if (region && document.body.contains(region)) {
		return region;
	}
	region = document.createElement('div');
	region.className = 'th-toasts';
	region.setAttribute('role', 'status');
	region.setAttribute('aria-live', 'polite');
	region.setAttribute('aria-atomic', 'false');
	document.body.append(region);
	return region;
}

function icon(name) {
	const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
	svg.setAttribute('class', `th-icon th-icon--${name}`);
	svg.setAttribute('width', '18');
	svg.setAttribute('height', '18');
	svg.setAttribute('aria-hidden', 'true');
	svg.setAttribute('focusable', 'false');
	const use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
	use.setAttribute('href', `${config.sprite || ''}#i-${name}`);
	svg.append(use);
	return svg;
}

export function toast(message, { type = 'info', timeout } = {}) {
	const el = document.createElement('div');
	el.className = `th-toast th-toast--${type}`;
	el.append(icon(type === 'error' ? 'alert-circle' : type === 'success' ? 'check' : 'info'));
	const text = document.createElement('span');
	text.textContent = message;
	el.append(text);

	if (type === 'error') {
		el.setAttribute('role', 'alert');
	}

	ensureRegion().append(el);

	const ms = timeout ?? (type === 'error' ? 8000 : 4000);
	window.setTimeout(() => {
		el.classList.add('is-leaving');
		window.setTimeout(() => el.remove(), 250);
	}, ms);

	return el;
}

export function init(root = document) {
	root.querySelectorAll('[data-th-toast-demo]').forEach((btn) => {
		btn.addEventListener('click', () => toast(btn.dataset.thToastDemo, { type: btn.dataset.thToastType || 'info' }));
	});
}
