/**
 * Compare: the toggles on cards / the listing page and the floating bar's remove / Clear. Every control is a link
 * to /compare/?… that works without JS; here the same link is fetched with &format=json — the server keeps the
 * selection (cookie, same category, max 3) and answers with the ids and the new bar. On /compare/ itself the
 * links stay plain (the page reloads).
 */

const CONTROLS = '[data-th-compare], [data-th-compare-remove], [data-th-compare-clear]';

function update(res) {
	const ids = (res.ids || []).map(String);
	document.querySelectorAll('[data-th-compare]').forEach((button) => {
		button.setAttribute('aria-pressed', String(ids.includes(button.dataset.thCompare)));
	});
	const old = document.querySelector('[data-th-compare-bar]');
	const hadFocus = old?.contains(document.activeElement);
	const tpl = document.createElement('template');
	tpl.innerHTML = res.bar || '';
	const bar = tpl.content.querySelector('[data-th-compare-bar]');
	if (old && bar) {
		old.replaceWith(bar);
	} else if (old) {
		old.remove();
	} else if (bar) {
		document.body.append(bar);
	}
	// Removing from the bar takes the focused control away: keep the keyboard in the bar (or on the page).
	if (hadFocus) {
		(bar?.querySelector('[data-th-compare-remove], a') || document.getElementById('main'))?.focus();
	}
}

export function init() {
	if (document.documentElement.dataset.thCompareReady) {
		return;
	}
	document.documentElement.dataset.thCompareReady = '1';

	document.addEventListener('click', (event) => {
		const link = event.target.closest(CONTROLS);
		if (!link || link.closest('.th-compare') || event.metaKey || event.ctrlKey || event.shiftKey) {
			return;
		}
		event.preventDefault();
		if (link.getAttribute('aria-busy') === 'true') {
			return;
		}
		const url = new URL(link.href);
		url.searchParams.set('format', 'json');
		link.setAttribute('aria-busy', 'true');
		fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
			.then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
			.then((res) => {
				update(res);
				window.torrehub?.toast?.(res.message, { type: res.ok ? 'success' : 'info' });
			})
			.catch(() => window.location.assign(link.href))
			.finally(() => link.removeAttribute('aria-busy'));
	});

	// The toggles are links with role="button": Space presses them too.
	document.addEventListener('keydown', (event) => {
		if (event.key === ' ' && event.target.matches?.('a[role="button"][data-th-compare]')) {
			event.preventDefault();
			event.target.click();
		}
	});
}
