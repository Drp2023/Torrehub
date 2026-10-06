/**
 * Listing gallery: "1 / 5" counter for the swipe strip (mobile) and a lightbox <dialog> with
 * previous/next (buttons, arrow keys, swipe). Each photo stays a plain link to the full image without JS.
 */

import { config } from '../lib/config.js';
import { open as openDialog } from './dialog.js';

const i18n = config.listing?.i18n || {};

export function init(root = document) {
	root.querySelectorAll('[data-th-gallery]').forEach(setup);
}

function setup(gallery) {
	if (gallery.dataset.thReady) {
		return;
	}
	gallery.dataset.thReady = '1';
	const links = [...gallery.querySelectorAll('[data-th-gallery-index]')];
	const strip = gallery.querySelector('[data-th-gallery-strip]');
	const current = gallery.querySelector('[data-th-gallery-current]');

	// Counter: the item most in view.
	if (strip && current && 'IntersectionObserver' in window) {
		const io = new IntersectionObserver(
			(entries) => {
				entries.forEach((e) => {
					if (e.intersectionRatio > 0.6) {
						current.textContent = String(links.indexOf(e.target.querySelector('a')) + 1);
					}
				});
			},
			{ root: strip, threshold: [0.6] }
		);
		strip.querySelectorAll('li').forEach((li) => io.observe(li));
	}

	const box = document.querySelector('[data-th-lightbox]');
	if (!box || !links.length) {
		return;
	}
	const img = box.querySelector('[data-th-lightbox-img]');
	const counter = box.querySelector('[data-th-lightbox-counter]');
	let index = 0;

	const show = (i) => {
		index = (i + links.length) % links.length;
		img.src = links[index].href;
		img.alt = links[index].getAttribute('aria-label') || '';
		counter.textContent = (i18n.photo || 'Photo %1$s of %2$s').replace('%1$s', index + 1).replace('%2$s', links.length);
		// Warm the next image.
		const next = new Image();
		next.src = links[(index + 1) % links.length].href;
	};

	gallery.addEventListener('click', (event) => {
		const link = event.target.closest('[data-th-gallery-index]');
		if (!link) {
			return;
		}
		event.preventDefault();
		show(Number(link.dataset.thGalleryIndex));
		openDialog(box, link);
	});

	box.addEventListener('click', (event) => {
		const step = event.target.closest('[data-th-lightbox-step]');
		if (step) {
			show(index + Number(step.dataset.thLightboxStep));
		}
	});
	box.addEventListener('keydown', (event) => {
		if (event.key === 'ArrowRight') {
			show(index + 1);
		} else if (event.key === 'ArrowLeft') {
			show(index - 1);
		}
	});

	let startX = null;
	box.addEventListener('pointerdown', (e) => (startX = e.clientX));
	box.addEventListener('pointerup', (e) => {
		if (startX !== null && Math.abs(e.clientX - startX) > 50) {
			show(index + (e.clientX < startX ? 1 : -1));
		}
		startX = null;
	});
}
