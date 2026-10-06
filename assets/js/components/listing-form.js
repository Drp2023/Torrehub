/**
 * Listing form workspace (S-04 / S-14).
 *
 * - One section at a time; section pills with filled/total, "Hidden" for sections switched off by a condition.
 * - Form Builder conditions (sections + fields) evaluated like FBHelper::isValidateCondition; switched-off
 *   controls are disabled, so they are neither validated nor sent.
 * - Client-side checks mirror the Form Builder rules; Classified Listing validates again on save.
 * - Drafts: autosave to a temp listing (th_listing_draft), "Save & exit".
 * - Publish: Classified Listing's own `rtcl_update_listing` with the serialised form (parse_str format).
 */

import { config } from '../lib/config.js';

const cfg = config.listingForm || {};
const i18n = cfg.i18n || {};
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

export function init(root = document) {
	const form = root.querySelector('[data-th-listing-form]');
	if (form && !form.dataset.thReady) {
		form.dataset.thReady = '1';
		new Workspace(form);
	}
}

const sprintf = (s, ...args) => {
	let i = 0;
	return String(s || '')
		.replace(/%(\d)\$[sd]/g, (_, n) => String(args[Number(n) - 1] ?? ''))
		.replace(/%[sd]/g, () => String(args[i++] ?? ''));
};

const plural = (pair, n) => sprintf(Array.isArray(pair) ? (n === 1 ? pair[0] : pair[1]) : pair?.[n === 1 ? 'singular' : 'plural'] || '', n);

/** Native date input value → the field's PHP date format (DateTime::createFromFormat counterpart). */
export function formatDate(value, format) {
	const m = /^(\d{4})-(\d{2})-(\d{2})(?:T(\d{2}):(\d{2}))?/.exec(value || '');
	if (!m) {
		return value || '';
	}
	const [, Y, mo, d, H = '00', i = '00'] = m;
	const date = new Date(Number(Y), Number(mo) - 1, Number(d));
	const h12 = Number(H) % 12 || 12;
	const map = {
		d, j: String(Number(d)), m: mo, n: String(Number(mo)), Y, y: Y.slice(2),
		M: MONTHS[Number(mo) - 1].slice(0, 3), F: MONTHS[Number(mo) - 1],
		D: DAYS[date.getDay()].slice(0, 3), l: DAYS[date.getDay()],
		H, G: String(Number(H)), h: String(h12).padStart(2, '0'), g: String(h12), i, s: '00',
		A: Number(H) < 12 ? 'AM' : 'PM', a: Number(H) < 12 ? 'am' : 'pm',
	};
	let out = '';
	for (let k = 0; k < format.length; k++) {
		const c = format[k];
		if (c === '\\') {
			out += format[++k] || '';
		} else {
			out += c in map ? map[c] : c;
		}
	}
	return out;
}

class Workspace {
	constructor(form) {
		this.form = form;
		this.listingId = Number(cfg.listingId) || 0;
		this.mode = cfg.mode || 'new';
		this.dirty = false;
		this.busy = false;
		this.saving = null;
		this.sections = [...form.querySelectorAll('[data-th-lf-section]')];
		this.pills = new Map([...document.querySelectorAll('[data-th-lf-goto]')].map((a) => [a.dataset.thLfGoto, a]));
		this.fields = [...form.querySelectorAll('[data-th-lf-field]')];
		this.prevBtn = form.querySelector('[data-th-lf-prev]');
		this.nextBtn = form.querySelector('[data-th-lf-next]');
		this.submitBtn = form.querySelector('[data-th-lf-submit]');
		this.headerPublish = document.querySelector('[data-th-lf-publish]');
		this.leftEl = form.querySelector('[data-th-lf-left]');
		this.totalEl = document.querySelector('[data-th-lf-total]');
		this.alertEl = form.querySelector('[data-th-lf-alert]');
		this.status = document.querySelector('[data-th-lf-status]');
		this.current = this.sections[0]?.dataset.thLfSection;
		this.touched = new Set();

		this.apply();
		this.show(this.current, false);
		this.refresh();
		this.bind();

		const uploader = form.querySelector('[data-th-uploader]');
		const files = form.querySelectorAll('[data-th-file-field]');
		if (uploader || files.length) {
			import('./lf-uploader.js').then((m) => {
				if (uploader) {
					m.initGallery(uploader, this);
				}
				files.forEach((el) => m.initFile(el, this));
			});
		}
		const pin = form.querySelector('[data-th-pin]');
		if (pin) {
			import('./lf-pin.js').then((m) => m.initPin(pin, this));
		}
	}

	/* ------------------------------------------------------------ events */

	bind() {
		const onChange = (event) => {
			const field = event.target.closest('[data-th-lf-field]');
			if (field && field.classList.contains('is-invalid')) {
				this.check(field);
			}
			this.apply();
			this.refresh();
			this.markDirty();
			if (this.alertEl && !this.alertEl.hidden && !this.form.querySelector('.th-lf-field.is-invalid')) {
				this.alert('');
			}
		};
		this.form.addEventListener('input', onChange);
		this.form.addEventListener('change', onChange);
		this.form.addEventListener('focusout', (event) => {
			const field = event.target.closest('[data-th-lf-field]');
			if (field && !field.contains(event.relatedTarget)) {
				this.touched.add(field);
				this.check(field);
			}
		});

		this.pills.forEach((pill, uuid) => pill.addEventListener('click', (event) => {
			event.preventDefault();
			if (!pill.classList.contains('is-hidden')) {
				this.show(uuid);
			}
		}));
		this.prevBtn?.addEventListener('click', () => this.step(-1));
		this.nextBtn?.addEventListener('click', () => {
			const bad = this.checkSection(this.current);
			if (bad.length) {
				this.focusError(bad[0]);
				return;
			}
			this.step(1);
		});
		this.form.addEventListener('submit', (event) => {
			event.preventDefault();
			this.publish();
		});
		document.querySelector('[data-th-lf-exit]')?.addEventListener('click', async () => {
			await this.saveDraft(true);
			if (this.listingId) {
				this.dirty = false;
				window.location.href = cfg.exitUrl;
			}
		});
		document.querySelector('[data-th-lf-change]')?.addEventListener('click', (event) => {
			if (this.dirty && this.mode !== 'edit') {
				const href = event.currentTarget.href;
				event.preventDefault();
				this.saveDraft(true).then(() => {
					this.dirty = false;
					window.location.href = href;
				});
			}
		});
		document.addEventListener('click', (event) => {
			if (event.target.closest('[data-th-dialog-open="th-lf-preview-dialog"]')) {
				this.fillPreviewDialog();
			}
		}, true);
		window.addEventListener('beforeunload', (event) => {
			if (this.dirty && !this.busy) {
				event.preventDefault();
				event.returnValue = i18n.leave || '';
			}
		});
		this.bindRepeaters();
	}

	markDirty() {
		this.dirty = true;
		if (this.mode === 'edit') {
			return;
		}
		clearTimeout(this.saveTimer);
		this.saveTimer = setTimeout(() => this.saveDraft(), 2500);
	}

	/* ------------------------------------------------------------ values + conditions */

	/** Current values keyed by field name (checkbox groups as arrays), enabled controls only. */
	values() {
		const out = {};
		for (const [key, value] of new FormData(this.form)) {
			if (value instanceof File) {
				continue;
			}
			if (key.endsWith('[]')) {
				const k = key.slice(0, -2);
				(out[k] ||= []).push(value);
			} else {
				out[key] = value;
			}
		}
		return out;
	}

	matches(logics, vals) {
		if (!logics) {
			return true;
		}
		const test = (c) => {
			const field = cfg.fields?.[c.fieldId];
			if (!field) {
				return false;
			}
			const v = vals[field.name];
			const s = v === undefined || v === null ? '' : v;
			switch (c.operator) {
				case '=': return !Array.isArray(s) && String(s) === String(c.value);
				case '!=': return Array.isArray(s) || String(s) !== String(c.value);
				case 'contains': return Array.isArray(s) && s.includes(c.value);
				case 'doNotContains': return !Array.isArray(s) || !s.includes(c.value);
				case 'startsWith': return !!s && String(s).startsWith(c.value);
				case 'endsWith': return !!s && String(s).endsWith(c.value);
				case 'empty': return s === '';
				case 'notEmpty': return s !== '';
				default: return false;
			}
		};
		return logics.relation === 'or' ? logics.conditions.some(test) : logics.conditions.every(test);
	}

	/** Raw value of one input name (for `data-th-lf-when="name=value"`). */
	raw(name) {
		const els = [...this.form.elements].filter((el) => el.name === name && !el.disabled);
		const el = els.find((e) => (e.type === 'radio' || e.type === 'checkbox') ? e.checked : true);
		return el ? el.value : '';
	}

	/** Evaluate conditions until stable; switched-off parts get `data-th-off` and disabled controls. */
	apply() {
		for (let pass = 0; pass < 5; pass++) {
			const vals = this.values();
			let changed = false;
			const toggle = (el, off) => {
				if (el.hasAttribute('data-th-off') !== off) {
					el.toggleAttribute('data-th-off', off);
					changed = true;
				}
			};
			this.sections.forEach((s) => toggle(s, !this.matches(cfg.sections?.[s.dataset.thLfSection], vals)));
			this.fields.forEach((f) => toggle(f, !this.matches(cfg.fields?.[f.dataset.thLfField]?.logics, vals)));
			this.form.querySelectorAll('[data-th-lf-when]').forEach((el) => {
				const rule = el.dataset.thLfWhen;
				const m = /^(.+?)(!?=)(.*)$/.exec(rule || '');
				if (!m) {
					return;
				}
				const v = this.raw(m[1]);
				toggle(el, m[2] === '=' ? v !== m[3] : v === m[3]);
			});
			this.syncDisabled();
			if (!changed) {
				break;
			}
		}
		this.fields.forEach((f) => { f.hidden = f.hasAttribute('data-th-off'); });
		this.form.querySelectorAll('[data-th-lf-when]').forEach((el) => { el.hidden = el.hasAttribute('data-th-off'); });
		if (this.current && this.sectionEl(this.current)?.hasAttribute('data-th-off')) {
			this.show(this.visibleSections()[0]?.dataset.thLfSection, false);
		}
	}

	syncDisabled() {
		this.form.querySelectorAll('input, select, textarea').forEach((el) => {
			if (el.closest('template')) {
				return;
			}
			el.disabled = !!el.closest('[data-th-off]');
		});
	}

	/* ------------------------------------------------------------ sections */

	sectionEl(uuid) {
		return this.sections.find((s) => s.dataset.thLfSection === uuid);
	}

	visibleSections() {
		return this.sections.filter((s) => !s.hasAttribute('data-th-off'));
	}

	show(uuid, focus = true) {
		if (!uuid) {
			return;
		}
		this.current = uuid;
		this.sections.forEach((s) => { s.hidden = s.dataset.thLfSection !== uuid; });
		this.pills.forEach((pill, id) => {
			if (id === uuid) {
				pill.setAttribute('aria-current', 'step');
			} else {
				pill.removeAttribute('aria-current');
			}
		});
		const list = this.visibleSections();
		const index = list.findIndex((s) => s.dataset.thLfSection === uuid);
		const next = list[index + 1];
		if (this.prevBtn) {
			this.prevBtn.hidden = index <= 0;
		}
		if (this.nextBtn) {
			this.nextBtn.hidden = !next;
			if (next) {
				this.nextBtn.textContent = sprintf(i18n.continueTo, next.querySelector('.th-lf-section__title')?.textContent || '');
			}
		}
		if (this.submitBtn) {
			this.submitBtn.hidden = !!next;
		}
		if (focus) {
			const section = this.sectionEl(uuid);
			section?.focus({ preventScroll: true });
			this.form.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
		}
	}

	step(dir) {
		const list = this.visibleSections();
		const index = list.findIndex((s) => s.dataset.thLfSection === this.current);
		const target = list[index + dir];
		if (target) {
			this.show(target.dataset.thLfSection);
		}
	}

	/* ------------------------------------------------------------ progress + preview */

	filled(field) {
		if (field.querySelector('[data-th-uploader]')) {
			return !!field.querySelector('.th-lf-photo[data-id]');
		}
		if (field.querySelector('[data-th-file-field]')) {
			return !!field.querySelector('.th-lf-file[data-id]');
		}
		if (field.querySelector('[data-th-pin]')) {
			return !!field.querySelector('[data-th-pin-lat]')?.value;
		}
		const price = field.querySelector('[data-th-lf-price]');
		if (price) {
			return price.disabled || price.value.trim() !== '';
		}
		const controls = field.querySelectorAll('input:not([type="hidden"]):not([type="file"]):not(:disabled), select:not(:disabled), textarea:not(:disabled)');
		return [...controls].some((c) => (c.type === 'checkbox' || c.type === 'radio') ? c.checked : c.value.trim() !== '');
	}

	required(field) {
		return !!cfg.fields?.[field.dataset.thLfField]?.rules?.required;
	}

	refresh() {
		let left = 0;
		let filledAll = 0;
		let totalAll = 0;
		this.sections.forEach((section) => {
			const pill = this.pills.get(section.dataset.thLfSection);
			const count = pill?.querySelector('[data-th-lf-count]');
			const off = section.hasAttribute('data-th-off');
			const fields = [...section.querySelectorAll('[data-th-lf-field]')].filter((f) => !f.hasAttribute('data-th-off'));
			const filled = fields.filter((f) => this.filled(f)).length;
			if (!off) {
				left += fields.filter((f) => this.required(f) && !this.filled(f)).length;
				filledAll += filled;
				totalAll += fields.length;
			}
			if (pill) {
				pill.classList.toggle('is-hidden', off);
				pill.classList.toggle('is-done', !off && fields.length > 0 && filled === fields.length);
				pill.toggleAttribute('aria-disabled', off);
				pill.tabIndex = off ? -1 : 0;
				if (count) {
					count.textContent = off ? (i18n.hidden || 'Hidden') : `${filled}/${fields.length}`;
				}
			}
		});
		if (this.totalEl) {
			this.totalEl.textContent = `${filledAll} / ${totalAll}`;
		}
		if (this.leftEl) {
			this.leftEl.textContent = left ? plural(i18n.left, left) : (i18n.allDone || '');
		}
		this.preview();
	}

	preview() {
		const rail = document.querySelector('[data-th-lf-preview]');
		if (!rail) {
			return;
		}
		const title = this.form.querySelector('[name="title"]')?.value.trim();
		const titleEl = rail.querySelector('[data-th-lf-preview-title]');
		titleEl.textContent = title || i18n.untitled || '';
		titleEl.classList.toggle('is-placeholder', !title);

		const priceInput = this.form.querySelector('[data-th-lf-price]:not(:disabled)');
		const currency = priceInput?.closest('.th-control')?.querySelector('.th-control__affix')?.textContent || '';
		const amount = priceInput?.value.trim() || '';
		const number = Number(amount.replace(/\s/g, '').replace(',', '.'));
		const shown = amount && Number.isFinite(number) ? new Intl.NumberFormat(document.documentElement.lang || undefined, { maximumFractionDigits: 2 }).format(number) : amount;
		rail.querySelector('[data-th-lf-preview-price]').textContent = shown ? `${currency}${shown}` : '';

		const town = this.form.querySelector('[data-th-lf-town]');
		const townName = town && town.value ? town.selectedOptions[0]?.textContent.trim() : '';
		const meta = rail.querySelector('[data-th-lf-preview-meta]');
		meta.dataset.leaf ??= meta.textContent.trim();
		meta.textContent = [townName, meta.dataset.leaf].filter(Boolean).join(' · ');

		const cover = this.form.querySelector('.th-lf-photo[data-cover] img') || this.form.querySelector('.th-lf-photo[data-id] img');
		const box = rail.querySelector('[data-th-lf-preview-cover]');
		box.dataset.empty ??= box.innerHTML;
		const src = cover?.src || '';
		if ((box.dataset.src || '') !== src) {
			box.dataset.src = src;
			box.classList.toggle('is-filled', !!src);
			if (src) {
				const img = document.createElement('img');
				img.src = src;
				img.alt = '';
				box.replaceChildren(img);
			} else {
				box.innerHTML = box.dataset.empty;
			}
		}
	}

	fillPreviewDialog() {
		const target = document.querySelector('[data-th-lf-preview-full]');
		const rail = document.querySelector('[data-th-lf-preview]');
		if (!target || !rail) {
			return;
		}
		this.preview();
		target.innerHTML = '';
		const copy = rail.cloneNode(true);
		copy.removeAttribute('data-th-lf-preview');
		copy.classList.remove('th-lf-card');
		copy.querySelector('h2')?.remove();
		const text = this.form.querySelector('[name="description"]')?.value.trim();
		if (text) {
			const p = document.createElement('p');
			p.className = 'th-lf-preview__text';
			p.textContent = text.length > 600 ? `${text.slice(0, 600)}…` : text;
			copy.append(p);
		}
		target.append(copy);
	}

	/* ------------------------------------------------------------ validation */

	message(field) {
		if (field.closest('[data-th-off]')) {
			return '';
		}
		const def = cfg.fields?.[field.dataset.thLfField];
		const rules = def?.rules || {};
		if (rules.required && !this.filled(field)) {
			return rules.required.message || i18n.required;
		}
		const urls = [...field.querySelectorAll('[data-th-lf-url]:not(:disabled)')];
		if (urls.some((u) => u.value.trim() && !/^https?:\/\/\S+\.\S+/.test(u.value.trim()))) {
			return i18n.url;
		}
		const main = field.querySelector('input.th-input:not(:disabled), textarea:not(:disabled)');
		const value = main ? main.value.trim() : '';
		if (!value || !def) {
			return '';
		}
		const isNumber = def.element === 'number';
		if ((isNumber || rules.numeric) && Number.isNaN(Number(value.replace(',', '.')))) {
			return rules.numeric?.message || i18n.number;
		}
		if (rules.min && Number(rules.min.value)) {
			const bad = isNumber ? Number(value) < Number(rules.min.value) : value.length < Number(rules.min.value);
			if (bad) {
				return rules.min.message || sprintf(i18n.min, rules.min.value);
			}
		}
		if (rules.max && Number(rules.max.value)) {
			const bad = isNumber ? Number(value) > Number(rules.max.value) : value.length > Number(rules.max.value);
			if (bad) {
				return rules.max.message || sprintf(i18n.max, rules.max.value);
			}
		}
		if ((rules.email || def.element === 'email') && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
			return rules.email?.message || i18n.email;
		}
		if ((rules.url || def.element === 'url' || def.element === 'website') && !/^https?:\/\/\S+\.\S+/.test(value)) {
			return rules.url?.message || i18n.url;
		}
		if (rules.regex) {
			try {
				if (!new RegExp(String(rules.regex.value), 'u').test(value)) {
					return rules.regex.message || i18n.pattern;
				}
			} catch {
				// Invalid pattern in the form definition: the server ignores it too.
			}
		}
		return '';
	}

	showError(field, text) {
		const err = field.querySelector('[data-th-lf-error]');
		field.classList.toggle('is-invalid', !!text);
		if (err) {
			err.hidden = !text;
			err.querySelector('span').textContent = text || '';
		}
		field.querySelectorAll('input:not([type="hidden"]), select, textarea, fieldset').forEach((c) => {
			if (c.closest('[data-th-lf-field]') !== field) {
				return;
			}
			const ids = (c.getAttribute('aria-describedby') || '').split(' ').filter((id) => id && id !== err?.id);
			if (text && err) {
				ids.unshift(err.id);
			}
			if (ids.length) {
				c.setAttribute('aria-describedby', ids.join(' '));
			} else {
				c.removeAttribute('aria-describedby');
			}
			if (!['fieldset', 'FIELDSET'].includes(c.tagName) && c.type !== 'radio' && c.type !== 'checkbox') {
				c.toggleAttribute('aria-invalid', !!text);
				if (text) {
					c.setAttribute('aria-invalid', 'true');
				}
			}
		});
	}

	check(field) {
		const text = this.message(field);
		this.showError(field, text);
		return !text;
	}

	checkSection(uuid) {
		const section = this.sectionEl(uuid);
		if (!section || section.hasAttribute('data-th-off')) {
			return [];
		}
		return [...section.querySelectorAll('[data-th-lf-field]')].filter((f) => !this.check(f));
	}

	focusError(field) {
		const section = field.closest('[data-th-lf-section]');
		if (section && section.dataset.thLfSection !== this.current) {
			this.show(section.dataset.thLfSection, false);
		}
		const control = field.querySelector('input:not([type="hidden"]):not(:disabled), select:not(:disabled), textarea:not(:disabled)');
		(control || field).focus?.();
		field.scrollIntoView({ block: 'center' });
	}

	alert(text, variant = 'error') {
		if (!this.alertEl) {
			return;
		}
		this.alertEl.hidden = !text;
		this.alertEl.innerHTML = '';
		if (!text) {
			return;
		}
		const box = document.createElement('div');
		box.className = `th-alert th-alert--${variant}`;
		box.setAttribute('role', 'alert');
		const p = document.createElement('p');
		p.textContent = text;
		box.append(p);
		this.alertEl.append(box);
		this.alertEl.focus({ preventScroll: true });
		this.alertEl.scrollIntoView({ block: 'center' });
	}

	/* ------------------------------------------------------------ saving */

	serialize() {
		const formats = new Map([...this.form.querySelectorAll('[data-th-date]:not(:disabled)')].map((el) => [el.name, el.dataset.thDate]));
		const params = new URLSearchParams();
		for (const [key, value] of new FormData(this.form)) {
			// Empty list entries (a blank second video link) would fail Classified Listing's per-item checks.
			if (value instanceof File || (key.endsWith('[]') && value.trim() === '')) {
				continue;
			}
			params.append(key, formats.has(key) && value ? formatDate(value, formats.get(key)) : value);
		}
		return params.toString();
	}

	setStatus(state, text = '') {
		if (!this.status) {
			return;
		}
		this.status.classList.remove('is-saved', 'is-saving', 'is-error');
		this.status.classList.add(`is-${state}`);
		const label = this.status.querySelector('[data-th-lf-status-text]');
		if (label) {
			label.textContent = text;
		}
	}

	/** Save the draft (creating the temp listing on first save). Resolves with the listing id (0 on failure). */
	async saveDraft(force = false) {
		if (this.mode === 'edit') {
			return this.listingId;
		}
		clearTimeout(this.saveTimer);
		if (this.saving) {
			await this.saving;
			if (!this.dirty && !force) {
				return this.listingId;
			}
		}
		if (!this.dirty && !force && this.listingId) {
			return this.listingId;
		}
		const body = new URLSearchParams({
			action: 'th_listing_draft',
			_ajax_nonce: cfg.draftNonce,
			listing_id: String(this.listingId || ''),
			form_id: String(cfg.formId),
			category: String(cfg.category || ''),
			form_data: this.serialize(),
		});
		this.setStatus('saving', i18n.saving);
		this.dirty = false;
		this.saving = fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body })
			.then((r) => r.json())
			.then((json) => {
				if (!json?.success) {
					throw new Error(json?.data?.message || '');
				}
				if (!this.listingId) {
					this.listingId = Number(json.data.listing_id);
					const url = new URL(window.location.href);
					url.searchParams.delete('th_cat');
					url.searchParams.set('th_draft', String(this.listingId));
					window.history.replaceState(null, '', url);
				}
				this.setStatus('saved', json.data.label);
				return this.listingId;
			})
			.catch((e) => {
				this.dirty = true;
				this.setStatus('error', e.message || i18n.saveFailed);
				return this.listingId;
			})
			.finally(() => { this.saving = null; });
		return this.saving;
	}

	/** Listing id for uploads (creates the draft when needed). */
	async ensureListing() {
		return this.listingId || this.saveDraft(true);
	}

	async publish() {
		if (this.busy) {
			return;
		}
		this.apply();
		this.alert('');
		const bad = this.fields.filter((f) => !this.check(f));
		if (bad.length) {
			this.alert(i18n.fixErrors);
			this.focusError(bad[0]);
			return;
		}
		if (this.form.querySelector('.th-lf-photo--uploading, .th-lf-file.is-uploading')) {
			this.alert(i18n.waitUploads, 'info');
			return;
		}
		this.busy = true;
		clearTimeout(this.saveTimer);
		if (this.saving) {
			await this.saving;
		}
		const buttons = [this.submitBtn, this.headerPublish].filter(Boolean);
		const labels = buttons.map((b) => b.textContent);
		buttons.forEach((b) => {
			b.disabled = true;
			b.setAttribute('aria-busy', 'true');
			b.textContent = i18n.publishing;
		});
		const body = new URLSearchParams({
			action: 'rtcl_update_listing',
			[cfg.nonceId]: cfg.nonce,
			listingId: String(this.listingId || ''),
			formId: String(cfg.formId),
			formData: this.serialize(),
		});
		let json = null;
		try {
			json = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body }).then((r) => r.json());
		} catch {
			json = null;
		}
		if (json?.success) {
			this.dirty = false;
			const id = json.data?.listing_id || this.listingId;
			window.location.href = cfg.doneUrl.replace('%d', String(id)) + (this.mode === 'edit' ? '&th_updated=1' : '');
			return;
		}
		this.busy = false;
		buttons.forEach((b, k) => {
			b.disabled = false;
			b.removeAttribute('aria-busy');
			b.textContent = labels[k];
		});
		this.serverErrors(json?.data);
	}

	serverErrors(data) {
		const first = (v) => (typeof v === 'string' ? v : v && typeof v === 'object' ? first(Object.values(v)[0]) : '');
		if (data && typeof data === 'object' && data.errors) {
			let target = null;
			Object.entries(data.errors).forEach(([uuid, errs]) => {
				const field = this.fields.find((f) => f.dataset.thLfField === uuid);
				if (field) {
					this.showError(field, first(errs));
					target ||= field;
				}
			});
			this.alert(i18n.fixErrors);
			if (target) {
				this.focusError(target);
			}
			return;
		}
		if (data && typeof data === 'object') {
			const text = first(data.extraErrors || data);
			this.alert(text ? text.replace(/<[^>]+>/g, '') : i18n.serverError);
			return;
		}
		this.alert(typeof data === 'string' && data ? data : i18n.serverError);
	}

	/* ------------------------------------------------------------ repeaters */

	bindRepeaters() {
		this.form.querySelectorAll('[data-th-repeat]').forEach((wrap) => {
			const base = wrap.dataset.thRepeat;
			const list = wrap.querySelector('[data-th-repeat-list]');
			const tpl = wrap.querySelector('template[data-th-repeat-template]');
			const add = wrap.querySelector('[data-th-repeat-add]');
			const max = Number(wrap.dataset.max) || 0;
			const esc = base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
			const reindex = () => {
				[...list.children].forEach((row, r) => {
					row.querySelectorAll('[name]').forEach((el) => {
						el.name = el.name.replace(new RegExp(`^${esc}\\[[^\\]]*\\]`), `${base}[${r}]`);
					});
				});
				if (add) {
					add.hidden = max > 0 && list.children.length >= max;
				}
			};
			add?.addEventListener('click', () => {
				const html = tpl.innerHTML.replaceAll('__i__', String(list.children.length));
				list.insertAdjacentHTML('beforeend', html);
				reindex();
				list.lastElementChild?.querySelector('input, select, textarea')?.focus();
				this.markDirty();
			});
			wrap.addEventListener('click', (event) => {
				const btn = event.target.closest('[data-th-repeat-remove]');
				if (!btn) {
					return;
				}
				const row = btn.closest('li');
				if (list.children.length > 1) {
					row.remove();
				} else {
					row.querySelectorAll('input, textarea').forEach((el) => { el.value = ''; });
					row.querySelectorAll('select').forEach((el) => { el.selectedIndex = 0; });
				}
				reindex();
				add?.focus();
				this.refresh();
				this.markDirty();
			});
			reindex();
		});
	}
}
