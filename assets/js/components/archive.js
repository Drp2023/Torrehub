/**
 * Listing archive enhancements (the page works without them):
 *  - filter sheet: live "Show N results" (`?th_count=1` on the same archive), category change reloads with that
 *    category's fields, empty values and untouched sliders stay out of the URL;
 *  - sort select submits on change;
 *  - "Load N more" fetches the next page and appends its cards (skeletons while loading, focus moves to the first
 *    new result, a polite status message announces it).
 */

import { config } from '../lib/config.js';

const i18n = config.archive?.i18n || {};

export function init(root = document) {
	root.querySelectorAll('[data-th-filter-form]').forEach(setupFilters);
	root.querySelectorAll('form[data-th-autosubmit]').forEach((form) => {
		form.addEventListener('change', () => form.requestSubmit());
	});
	root.addEventListener('click', onLoadMore);
}

/* ------------------------------------------------------------------ filter sheet */

/** Query string of a filter form without empty values and without sliders resting on their bounds. */
function cleanParams(form) {
	const params = new URLSearchParams();
	for (const el of form.elements) {
		if (!el.name || el.disabled || !isActiveValue(el)) {
			continue;
		}
		if (el.type === 'range' && el.dataset.thBound !== undefined && Number(el.value) === Number(el.dataset.thBound)) {
			continue;
		}
		if (el.name === 'radius' && !form.elements.rtcl_location?.value) {
			continue;
		}
		if (el.multiple) {
			[...el.selectedOptions].forEach((o) => o.value && params.append(el.name, o.value));
		} else {
			params.append(el.name, el.value);
		}
	}
	return params;
}

function isActiveValue(el) {
	if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) {
		return false;
	}
	if (el.type === 'submit' || el.type === 'button') {
		return false;
	}
	return el.value !== '' && !(el.name === 'radius' && el.value === '0');
}

function setupFilters(form) {
	const button = form.querySelector('[data-th-count]');
	const radius = form.querySelector('[data-th-radius]');
	const town = form.querySelector('[data-th-town]');
	let timer = 0;
	let controller = null;

	const target = (params) => `${form.action}${form.action.includes('?') ? '&' : '?'}${params}`;

	const count = () => {
		controller?.abort();
		controller = new AbortController();
		const params = cleanParams(form);
		params.set('th_count', '1');
		if (button) {
			button.setAttribute('aria-busy', 'true');
		}
		fetch(target(params.toString()), { signal: controller.signal, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
			.then((r) => (r.ok ? r.json() : Promise.reject(r.status)))
			.then((data) => {
				if (button && data?.label) {
					(button.querySelector('span') || button).textContent = data.label;
				}
			})
			.catch(() => {})
			.finally(() => button?.removeAttribute('aria-busy'));
	};

	form.addEventListener('input', (event) => {
		if (event.target.matches('[data-th-category]')) {
			return;
		}
		clearTimeout(timer);
		timer = setTimeout(count, event.target.type === 'range' ? 350 : 150);
	});

	form.addEventListener('change', (event) => {
		const el = event.target;
		if (el.matches('[data-th-category]')) {
			// Field filters belong to the old category's form: drop them and show the new category.
			form.querySelectorAll('[name^="f["]').forEach((f) => (f.disabled = true));
			form.requestSubmit();
			return;
		}
		if (el === town && radius) {
			radius.hidden = !town.value;
		}
		if (el.type !== 'range') {
			clearTimeout(timer);
			timer = setTimeout(count, 100);
		}
	});

	form.addEventListener('submit', (event) => {
		event.preventDefault();
		window.location.assign(target(cleanParams(form).toString()).replace(/\?$/, ''));
	});
}

/* ------------------------------------------------------------------ load more */

function onLoadMore(event) {
	const link = event.target.closest('[data-th-load-more]');
	if (!link) {
		return;
	}
	const list = document.querySelector('[data-th-results]');
	if (!list) {
		return;
	}
	event.preventDefault();
	if (link.getAttribute('aria-busy') === 'true') {
		return;
	}
	link.setAttribute('aria-busy', 'true');
	link.classList.add('is-loading');

	const status = document.querySelector('[data-th-results-status]');
	const skeleton = document.querySelector('template[data-th-skeleton]');
	const placeholders = [];
	for (let n = 0; n < 3 && skeleton; n++) {
		const node = skeleton.content.firstElementChild.cloneNode(true);
		list.append(node);
		placeholders.push(node);
	}
	if (status) {
		status.textContent = i18n.loading || '';
	}

	fetch(link.href, { credentials: 'same-origin' })
		.then((r) => (r.ok ? r.text() : Promise.reject(r.status)))
		.then((html) => {
			const doc = new DOMParser().parseFromString(html, 'text/html');
			const incoming = doc.querySelector('[data-th-results]');
			placeholders.forEach((p) => p.remove());
			if (!incoming) {
				throw new Error('no results in response');
			}
			const items = [...incoming.children].filter((li) => !li.classList.contains('th-results__item--discovery'));
			const first = items.find((li) => !li.classList.contains('th-results__item--end'));
			list.append(...items.map((li) => document.importNode(li, true)));

			// Replace the button with the next page's (or remove it on the last page).
			const wrap = link.closest('[data-th-more-wrap]');
			const nextWrap = doc.querySelector('[data-th-more-wrap]');
			if (wrap && nextWrap) {
				wrap.replaceWith(document.importNode(nextWrap, true));
			} else if (wrap) {
				wrap.remove();
			}
			if (first) {
				const id = first.querySelector('.th-card__title a');
				const added = id ? [...list.querySelectorAll('.th-card__title a')].find((a) => a.href === id.href) : null;
				added?.focus({ preventScroll: false });
			}
			if (status) {
				status.textContent = i18n.loaded || '';
			}
			window.torrehub?.boot?.(list);
		})
		.catch(() => {
			placeholders.forEach((p) => p.remove());
			link.removeAttribute('aria-busy');
			link.classList.remove('is-loading');
			if (status) {
				status.textContent = i18n.error || '';
			}
			window.torrehub?.toast?.(i18n.error || 'Error', { type: 'error' });
		});
}
