/**
 * Archive map view: Leaflet (vendored, loaded only when the map comes into view) + OpenStreetMap tiles.
 * Price pins are keyboard-focusable markers; selecting one highlights its list card and shows it on the map.
 */

import { config } from '../lib/config.js';

const settings = config.archive || {};
let leafletPromise = null;

function loadLeaflet() {
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

export function init(root = document) {
	root.querySelectorAll('[data-th-map]').forEach((wrap) => {
		if (wrap.dataset.thReady) {
			return;
		}
		wrap.dataset.thReady = '1';
		const start = () => loadLeaflet().then((L) => build(L, wrap)).catch(() => {});
		if ('IntersectionObserver' in window) {
			const io = new IntersectionObserver((entries) => {
				if (entries.some((e) => e.isIntersecting)) {
					io.disconnect();
					start();
				}
			}, { rootMargin: '200px' });
			io.observe(wrap);
		} else {
			start();
		}
	});
}

function escapeHtml(s) {
	return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

function build(L, wrap) {
	const canvas = wrap.querySelector('[data-th-map-canvas]');
	const dataEl = wrap.querySelector('[data-th-map-data]');
	const cardSlot = wrap.querySelector('[data-th-map-card]');
	const controls = wrap.querySelector('[data-th-map-controls]');
	const list = wrap.querySelector('[data-th-map-list]');
	if (!canvas || !dataEl) {
		return;
	}
	const data = JSON.parse(dataEl.textContent || '{}');
	const pins = data.pins || [];
	const tiles = settings.tiles || {};

	const map = L.map(canvas, { zoomControl: false, scrollWheelZoom: false, attributionControl: true });
	L.tileLayer(tiles.url, { attribution: tiles.attribution, maxZoom: tiles.maxZoom || 18 }).addTo(map);
	canvas.setAttribute('aria-label', settings.i18n?.mapLabel || canvas.getAttribute('aria-label') || '');

	const markers = new Map();
	let active = null;

	const select = (id, { fromList = false } = {}) => {
		if (active) {
			markers.get(active)?.getElement()?.classList.remove('is-active');
			list?.querySelector(`[data-th-pin-id="${active}"]`)?.classList.remove('is-active');
		}
		active = id;
		if (!id) {
			cardSlot.replaceChildren();
			return;
		}
		const marker = markers.get(id);
		marker?.getElement()?.classList.add('is-active');
		const item = list?.querySelector(`[data-th-pin-id="${id}"]`);
		item?.classList.add('is-active');
		if (fromList) {
			if (marker) {
				map.panTo(marker.getLatLng());
			}
			return;
		}
		// Show the listing's card on the map; on desktop also bring it into view in the list.
		const card = item?.querySelector('.th-card');
		if (card) {
			const clone = card.cloneNode(true);
			clone.classList.add('th-card--map');
			clone.classList.remove('th-card--row');
			cardSlot.replaceChildren(clone);
		}
		if (item && window.matchMedia('(min-width: 900px)').matches) {
			item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
		}
	};

	pins.forEach((pin) => {
		const variant = pin.featured ? ' th-pin--accent' : '';
		const icon = L.divIcon({
			className: 'th-marker',
			html: `<span class="th-pin${variant}">${escapeHtml(pin.label)}</span>`,
			iconSize: null,
		});
		const marker = L.marker([pin.lat, pin.lng], { icon, title: `${pin.title} · ${pin.label}`, keyboard: true, riseOnHover: true }).addTo(map);
		marker.on('click', () => select(pin.id));
		markers.set(pin.id, marker);
	});

	if (pins.length > 1) {
		map.fitBounds(L.latLngBounds(pins.map((p) => [p.lat, p.lng])), { padding: [48, 48], maxZoom: 14 });
	} else if (pins.length === 1) {
		map.setView([pins[0].lat, pins[0].lng], 13);
	} else {
		map.setView(data.center || [37.978, -0.683], 11);
	}

	map.on('click', () => select(null));
	wrap.addEventListener('keydown', (e) => {
		if (e.key === 'Escape' && active) {
			select(null);
		}
	});

	list?.addEventListener('mouseover', (e) => {
		const item = e.target.closest('[data-th-pin-id]');
		if (item) {
			markers.get(Number(item.dataset.thPinId))?.getElement()?.classList.add('is-active');
		}
	});
	list?.addEventListener('mouseout', (e) => {
		const item = e.target.closest('[data-th-pin-id]');
		const id = item ? Number(item.dataset.thPinId) : 0;
		if (id && id !== active) {
			markers.get(id)?.getElement()?.classList.remove('is-active');
		}
	});
	list?.addEventListener('focusin', (e) => {
		const item = e.target.closest('[data-th-pin-id]');
		if (item) {
			select(Number(item.dataset.thPinId), { fromList: true });
		}
	});

	if (controls) {
		controls.hidden = false;
		controls.addEventListener('click', (e) => {
			const action = e.target.closest('[data-th-map-action]')?.dataset.thMapAction;
			if (action === 'zoom-in') {
				map.zoomIn();
			} else if (action === 'zoom-out') {
				map.zoomOut();
			} else if (action === 'locate' && navigator.geolocation) {
				navigator.geolocation.getCurrentPosition(
					(pos) => {
						const here = [pos.coords.latitude, pos.coords.longitude];
						L.circleMarker(here, { radius: 8, color: '#fff', weight: 3, fillColor: '#0056b3', fillOpacity: 1 }).addTo(map);
						map.setView(here, 13);
					},
					() => window.torrehub?.toast?.(config.location?.i18n?.unavailable || '', { type: 'error' }),
					{ timeout: 8000, maximumAge: 600000 }
				);
			}
		});
	}

	// Leaflet measures the container on init; re-measure once layout settles (sticky/fonts).
	requestAnimationFrame(() => map.invalidateSize());
}
