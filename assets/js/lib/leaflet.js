/**
 * Leaflet (vendored, assets/vendor/leaflet) loaded on demand + "run when the element is near the viewport".
 * Shared by the archive / listing maps and the listing form's pin picker.
 */

import { config } from './config.js';

const settings = config.archive || {};
let leafletPromise = null;

/** Tile layer settings (`th_map_tiles` filter). */
export const tiles = settings.tiles || {};

export function loadLeaflet() {
	if (window.L) {
		return Promise.resolve(window.L);
	}
	if (!leafletPromise) {
		leafletPromise = new Promise((resolve, reject) => {
			const css = document.createElement('link');
			css.rel = 'stylesheet';
			css.href = settings.leaflet.css;
			document.head.append(css);
			const script = document.createElement('script');
			script.src = settings.leaflet.js;
			script.async = true;
			script.onload = () => resolve(window.L);
			script.onerror = reject;
			document.head.append(script);
		});
	}
	return leafletPromise;
}

/** Load Leaflet and run `fn` once the element is (nearly) on screen. */
export function whenVisible(el, fn) {
	if (el.dataset.thReady) {
		return;
	}
	el.dataset.thReady = '1';
	const start = () => loadLeaflet().then(fn).catch(() => {});
	if ('IntersectionObserver' in window) {
		const io = new IntersectionObserver((entries) => {
			if (entries.some((e) => e.isIntersecting)) {
				io.disconnect();
				start();
			}
		}, { rootMargin: '200px' });
		io.observe(el);
	} else {
		start();
	}
}
