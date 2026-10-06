/**
 * Listing form uploads through Classified Listing's endpoints:
 * - photos: rtcl_fb_gallery_image_upload / _delete / _update_as_feature (S-14: cover, remove, progress, errors)
 * - file fields (logo, floor plan…): rtcl_fb_file_upload / rtcl_fb_file_delete
 * Uploads need a listing id: the workspace creates the draft first (ensureListing).
 */

import { config } from '../lib/config.js';

const cfg = config.listingForm || {};
const i18n = cfg.i18n || {};

const sprintf = (s, ...args) => {
	let i = 0;
	return String(s || '')
		.replace(/%(\d)\$[sd]/g, (_, n) => String(args[Number(n) - 1] ?? ''))
		.replace(/%%/g, '%')
		.replace(/%[sd]/g, () => String(args[i++] ?? ''));
};

function post(data, onProgress) {
	return new Promise((resolve) => {
		const body = new FormData();
		Object.entries({ [cfg.nonceId]: cfg.nonce, ...data }).forEach(([k, v]) => body.append(k, v));
		const xhr = new XMLHttpRequest();
		xhr.open('POST', config.ajaxUrl);
		xhr.withCredentials = true;
		xhr.responseType = 'json';
		if (onProgress) {
			xhr.upload.addEventListener('progress', (e) => e.lengthComputable && onProgress(Math.round((e.loaded / e.total) * 100)));
		}
		xhr.onload = () => resolve(xhr.response || { success: false });
		xhr.onerror = () => resolve({ success: false });
		xhr.send(body);
	});
}

const errorText = (json) => (typeof json?.data === 'string' ? json.data.replace(/<[^>]+>/g, '') : json?.data?.message) || i18n.uploadFailed;

/** Smallest generated size of an uploaded image that is still sharp in a 200 px tile. */
function thumbUrl(data) {
	const sizes = Object.values(data?.sizes || {}).filter((s) => s && s.url && s.width);
	sizes.sort((a, b) => a.width - b.width);
	return (sizes.find((s) => s.width >= 280) || sizes[sizes.length - 1])?.url || data?.guid || '';
}

const extOf = (name) => (name.split('.').pop() || '').toLowerCase();

/* ---------------------------------------------------------------- photos */

export function initGallery(el, ws) {
	const list = el.querySelector('[data-th-uploader-list]');
	const addTile = el.querySelector('[data-th-uploader-add]');
	const input = el.querySelector('[data-th-uploader-input]');
	const errorBox = el.querySelector('[data-th-uploader-error]');
	const countEl = el.querySelector('[data-th-uploader-count]');
	const max = Number(el.dataset.max) || 5;
	const bytes = Number(el.dataset.bytes) || 10 * 1024 * 1024;
	const types = (el.dataset.types || 'jpg,jpeg,png,webp').split(',');
	const live = document.createElement('p');
	live.className = 'th-sr-only';
	live.setAttribute('aria-live', 'polite');
	el.append(live);

	const tiles = () => [...list.querySelectorAll('.th-lf-photo[data-id], .th-lf-photo--uploading')];
	const update = () => {
		const done = list.querySelectorAll('.th-lf-photo[data-id]').length;
		countEl.textContent = String(done);
		el.classList.toggle('is-full', tiles().length >= max);
		if (!list.querySelector('.th-lf-photo[data-cover]')) {
			list.querySelector('.th-lf-photo[data-id]')?.setAttribute('data-cover', '');
		}
		ws.refresh();
	};
	const showError = (lines) => {
		errorBox.hidden = !lines.length;
		errorBox.querySelector('p').textContent = lines.join(' ');
	};

	const tile = (data) => {
		const li = document.createElement('li');
		li.className = 'th-lf-photo';
		li.dataset.id = String(data.attach_id);
		if (Number(data.featured) === 1) {
			li.setAttribute('data-cover', '');
		}
		const img = document.createElement('img');
		img.src = thumbUrl(data);
		img.alt = '';
		const cover = document.createElement('span');
		cover.className = 'th-lf-photo__cover';
		cover.textContent = i18n.cover;
		const make = document.createElement('button');
		make.type = 'button';
		make.className = 'th-lf-photo__make';
		make.dataset.thPhotoCover = '';
		make.textContent = i18n.makeCover;
		const remove = document.createElement('button');
		remove.type = 'button';
		remove.className = 'th-lf-photo__remove';
		remove.dataset.thPhotoRemove = '';
		remove.setAttribute('aria-label', i18n.remove);
		remove.innerHTML = `<svg class="th-icon" width="14" height="14" aria-hidden="true" focusable="false"><use href="${config.sprite}#i-close"></use></svg>`;
		li.append(img, cover, make, remove);
		return li;
	};

	async function upload(file) {
		const pending = document.createElement('li');
		pending.className = 'th-lf-photo th-lf-photo--uploading';
		pending.innerHTML = '<span data-pct></span><span class="th-progress th-progress--blue"><span class="th-progress__fill"></span></span>';
		const pct = pending.querySelector('[data-pct]');
		const bar = pending.querySelector('.th-progress__fill');
		const progress = (n) => {
			pct.textContent = sprintf(i18n.uploading, n);
			bar.style.setProperty('--pct', `${n}%`);
		};
		progress(0);
		list.insertBefore(pending, addTile);
		update();

		const listingId = await ws.ensureListing();
		if (!listingId) {
			pending.remove();
			update();
			return i18n.uploadFailed;
		}
		const json = await post({ action: 'rtcl_fb_gallery_image_upload', listingId: String(listingId), image: file }, progress);
		if (json?.success && json.data?.attach_id) {
			pending.replaceWith(tile(json.data));
			update();
			live.textContent = file.name;
			return '';
		}
		pending.remove();
		update();
		return `${file.name}: ${errorText(json)}`;
	}

	async function addFiles(fileList) {
		const errors = [];
		const queue = [];
		for (const file of fileList) {
			const ext = extOf(file.name);
			if (!types.includes(ext) || !/^image\//.test(file.type)) {
				errors.push(sprintf(i18n.badType, file.name));
			} else if (file.size > bytes) {
				errors.push(sprintf(i18n.tooBig, file.name, (file.size / 1048576).toFixed(1), Math.round(bytes / 1048576)));
			} else if (tiles().length + queue.length >= max) {
				errors.push(sprintf(i18n.tooMany, max));
				break;
			} else {
				queue.push(file);
			}
		}
		showError(errors);
		for (const file of queue) {
			const err = await upload(file); // One at a time: the first upload may create the draft.
			if (err) {
				errors.push(err);
				showError(errors);
			}
		}
	}

	input.addEventListener('change', () => {
		addFiles([...input.files]);
		input.value = '';
	});
	['dragenter', 'dragover'].forEach((type) => el.addEventListener(type, (e) => {
		if ([...(e.dataTransfer?.types || [])].includes('Files')) {
			e.preventDefault();
			el.classList.add('is-drag');
		}
	}));
	['dragleave', 'drop'].forEach((type) => el.addEventListener(type, (e) => {
		if (type === 'drop' || !el.contains(e.relatedTarget)) {
			el.classList.remove('is-drag');
		}
	}));
	el.addEventListener('drop', (e) => {
		if (e.dataTransfer?.files?.length) {
			e.preventDefault();
			addFiles([...e.dataTransfer.files]);
		}
	});

	list.addEventListener('click', async (e) => {
		const li = e.target.closest('.th-lf-photo[data-id]');
		if (!li) {
			return;
		}
		if (e.target.closest('[data-th-photo-remove]')) {
			li.style.opacity = '0.5';
			const json = await post({ action: 'rtcl_fb_gallery_image_delete', attach_id: li.dataset.id, listingId: String(ws.listingId) });
			if (json?.success) {
				const next = li.nextElementSibling?.matches('[data-id]') ? li.nextElementSibling : li.previousElementSibling;
				li.remove();
				update();
				(next?.querySelector('[data-th-photo-remove]') || input).focus();
			} else {
				li.style.opacity = '';
				showError([errorText(json)]);
			}
		} else if (e.target.closest('[data-th-photo-cover]')) {
			const json = await post({ action: 'rtcl_fb_gallery_image_update_as_feature', attach_id: li.dataset.id, listingId: String(ws.listingId) });
			if (json?.success) {
				list.querySelectorAll('[data-cover]').forEach((t) => t.removeAttribute('data-cover'));
				li.setAttribute('data-cover', '');
				li.querySelector('[data-th-photo-remove]')?.focus();
				live.textContent = i18n.cover;
				update();
			} else {
				showError([errorText(json)]);
			}
		}
	});
	update();
}

/* ---------------------------------------------------------------- file fields */

export function initFile(el, ws) {
	const list = el.querySelector('[data-th-file-list]');
	const add = el.querySelector('[data-th-file-add]');
	const input = el.querySelector('[data-th-file-input]');
	const field = el.closest('[data-th-lf-field]');
	const uuid = el.dataset.thFileField;
	const max = Number(el.dataset.max) || 1;
	const bytes = Number(el.dataset.bytes) || 2 * 1024 * 1024;
	const types = (el.dataset.types || '').split(',').filter(Boolean);

	const update = () => {
		add.hidden = list.querySelectorAll('.th-lf-file').length >= max;
		ws.refresh();
	};
	const fail = (text) => ws.showError(field, text);

	input.addEventListener('change', async () => {
		const file = input.files[0];
		input.value = '';
		if (!file) {
			return;
		}
		if (types.length && !types.includes(extOf(file.name))) {
			fail(sprintf(i18n.badType, file.name));
			return;
		}
		if (file.size > bytes) {
			fail(sprintf(i18n.tooBig, file.name, (file.size / 1048576).toFixed(1), Math.round(bytes / 1048576)));
			return;
		}
		fail('');
		const li = document.createElement('li');
		li.className = 'th-lf-file is-uploading';
		li.textContent = sprintf(i18n.uploading, 0);
		list.append(li);
		add.hidden = true;
		const listingId = await ws.ensureListing();
		const json = listingId
			? await post({ action: 'rtcl_fb_file_upload', form_id: String(cfg.formId), field_uuid: uuid, listingId: String(listingId), file }, (n) => { li.textContent = sprintf(i18n.uploading, n); })
			: null;
		if (json?.success && json.data?.uid) {
			li.className = 'th-lf-file';
			li.dataset.id = String(json.data.uid);
			li.textContent = '';
			const a = document.createElement('a');
			a.href = json.data.url;
			a.target = '_blank';
			a.rel = 'noopener';
			a.textContent = json.data.name || file.name;
			const btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'th-btn th-btn--text th-btn--sm';
			btn.dataset.thFileRemove = '';
			btn.textContent = i18n.remove;
			li.append(a, btn);
		} else {
			li.remove();
			fail(json ? errorText(json) : i18n.uploadFailed);
		}
		update();
	});

	list.addEventListener('click', async (e) => {
		const btn = e.target.closest('[data-th-file-remove]');
		const li = btn?.closest('.th-lf-file');
		if (!li) {
			return;
		}
		const json = await post({ action: 'rtcl_fb_file_delete', form_id: String(cfg.formId), field_uuid: uuid, listingId: String(ws.listingId), attach_id: li.dataset.id });
		if (json?.success) {
			li.remove();
			update();
			input.focus();
		} else {
			fail(errorText(json));
		}
	});
}
