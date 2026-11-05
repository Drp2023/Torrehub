/**
 * Favourite (heart) buttons on cards and the listing page: Classified Listing's own AJAX action and
 * `rtcl_favourites` user meta. Only rendered while favourites are switched on in Classified Listing. Guests get a
 * link to the login page instead (the listing is saved on the way back — `?th_faved` shows the toast here).
 * On Account › Favourites a removed listing leaves the list.
 */

import { config } from '../lib/config.js';

export function init(root = document) {
	if (root.dataset?.thFavReady || document.documentElement.dataset.thFavReady) {
		return;
	}
	document.documentElement.dataset.thFavReady = '1';

	// Back from logging in with the listing saved (Account\Favourites).
	const url = new URL(window.location.href);
	if (url.searchParams.has('th_faved')) {
		window.torrehub?.toast?.(config.favourites?.added || '', { type: 'success' });
		url.searchParams.delete('th_faved');
		window.history.replaceState(null, '', url.pathname + url.search + url.hash);
	}

	document.addEventListener('click', (event) => {
		const button = event.target.closest('[data-th-fav]');
		if (!button) {
			return;
		}
		event.preventDefault();
		const fav = config.favourites;
		if (!fav) {
			// Not logged in: log in, then come back here.
			const login = config.loginUrl || '/wp-login.php';
			window.location.assign(`${login}${login.includes('?') ? '&' : '?'}redirect_to=${encodeURIComponent(window.location.href)}`);
			return;
		}
		const pressed = button.getAttribute('aria-pressed') === 'true';
		button.setAttribute('aria-pressed', String(!pressed)); // Optimistic.
		const body = new URLSearchParams({ action: fav.action, post_id: button.dataset.thFav, [fav.nonceId]: fav.nonce });
		fetch(config.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })
			.then((r) => r.json())
			.then((res) => {
				if (!res?.success) {
					throw new Error(res?.message || 'failed');
				}
				const added = res.action === 'added';
				button.setAttribute('aria-pressed', String(added));
				window.torrehub?.toast?.(added ? fav.added : fav.removed, { type: 'success' });
				const list = button.closest('[data-th-fav-list]');
				if (!added && list) {
					button.closest('li')?.remove();
					if (!list.children.length) {
						list.hidden = true;
						document.querySelector('[data-th-fav-empty]')?.removeAttribute('hidden');
					}
				}
			})
			.catch((err) => {
				button.setAttribute('aria-pressed', String(pressed));
				window.torrehub?.toast?.(err.message, { type: 'error' });
			});
	});
}
