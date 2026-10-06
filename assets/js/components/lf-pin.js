/**
 * Map pin picker for the listing form (Form Builder "map" field → `map[latitude]`, `map[longitude]`).
 * Click to place, drag to fine-tune, "Pin at map centre" for keyboard users (the map pans with the arrow keys),
 * "Use my location", "Remove pin". Starts at the saved pin, else the chosen town, else Torrevieja.
 */

import { config } from '../lib/config.js';
import { tiles, whenVisible } from '../lib/leaflet.js';

const cfg = config.listingForm || {};
const i18n = cfg.i18n || {};
const FALLBACK = [37.978, -0.683];

const sprintf = (s, ...args) => {
	let i = 0;
	return String(s || '').replace(/%(\d)\$s/g, (_, n) => String(args[Number(n) - 1] ?? '')).replace(/%s/g, () => String(args[i++] ?? ''));
};

export function initPin(el, ws) {
	const mapEl = el.querySelector('[data-th-pin-map]');
	const latIn = el.querySelector('[data-th-pin-lat]');
	const lngIn = el.querySelector('[data-th-pin-lng]');
	const readout = el.querySelector('[data-th-pin-readout]');
	const town = ws.form.querySelector('[data-th-lf-town]');

	const townPoint = () => (town && cfg.towns?.[town.value]) || null;
	const describe = () => {
		readout.textContent = latIn.value
			? sprintf(i18n.pinAt, Number(latIn.value).toFixed(5), Number(lngIn.value).toFixed(5))
			: i18n.noPin;
	};
	describe();

	whenVisible(mapEl, (L) => {
		let marker = null;
		const saved = latIn.value && lngIn.value ? [Number(latIn.value), Number(lngIn.value)] : null;
		const start = saved || townPoint() || FALLBACK;
		const map = L.map(mapEl, { scrollWheelZoom: false, keyboard: true }).setView(start, saved ? 16 : (townPoint() ? 13 : 10));
		L.tileLayer(tiles.url, { attribution: tiles.attribution, maxZoom: tiles.maxZoom || 18 }).addTo(map);
		mapEl.setAttribute('aria-label', i18n.mapLabel || '');
		const icon = L.divIcon({ className: '', html: '<span class="th-lf-pin-marker"></span>', iconSize: [30, 30], iconAnchor: [15, 30] });

		const write = (latlng, announce = true) => {
			latIn.value = latlng ? latlng.lat.toFixed(6) : '';
			lngIn.value = latlng ? latlng.lng.toFixed(6) : '';
			describe();
			if (announce) {
				readout.textContent = latlng ? `${i18n.pinSet} ${readout.textContent}` : i18n.pinRemoved;
			}
			latIn.dispatchEvent(new Event('change', { bubbles: true }));
		};
		const place = (latlng, announce = true) => {
			if (!marker) {
				marker = L.marker(latlng, { icon, draggable: true, keyboard: false }).addTo(map);
				marker.on('dragend', () => write(marker.getLatLng()));
			} else {
				marker.setLatLng(latlng);
			}
			write(marker.getLatLng(), announce);
		};
		if (saved) {
			place(L.latLng(saved), false);
		}

		map.on('click', (e) => place(e.latlng));
		el.querySelector('[data-th-pin-centre]')?.addEventListener('click', () => place(map.getCenter()));
		el.querySelector('[data-th-pin-clear]')?.addEventListener('click', () => {
			if (marker) {
				marker.remove();
				marker = null;
			}
			write(null);
		});
		el.querySelector('[data-th-pin-locate]')?.addEventListener('click', () => {
			if (!navigator.geolocation) {
				readout.textContent = i18n.locateFailed;
				return;
			}
			readout.textContent = i18n.locating;
			navigator.geolocation.getCurrentPosition(
				(pos) => {
					const at = L.latLng(pos.coords.latitude, pos.coords.longitude);
					map.setView(at, 16);
					place(at);
				},
				() => { readout.textContent = i18n.locateFailed; },
				{ enableHighAccuracy: true, timeout: 10000 },
			);
		});
		town?.addEventListener('change', () => {
			const point = townPoint();
			if (point && !marker) {
				map.setView(point, 13);
			}
		});
		// The section may have been hidden while Leaflet measured it.
		new ResizeObserver(() => map.invalidateSize()).observe(mapEl);
	});
}
