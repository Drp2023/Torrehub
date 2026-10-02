/**
 * Password field: reveal toggle + optional strength meter.
 *
 * <div class="th-control th-control--toggle" data-th-password>
 *   <input type="password" class="th-input" aria-describedby="pw-strength">
 *   <button type="button" class="th-control__toggle" data-th-password-toggle aria-pressed="false" aria-label="Show password">…</button>
 * </div>
 * <div class="th-strength" id="pw-strength" data-th-strength-for="…input id…" data-score="0">…</div>
 *
 * The meter is a hint only — the server enforces the real policy.
 */

const LABELS = ['', 'Weak', 'Fair', 'Good', 'Strong'];

export function score(value) {
	if (!value) {
		return 0;
	}
	let s = 0;
	if (value.length >= 8) s++;
	if (value.length >= 12) s++;
	if (/[a-z]/.test(value) && /[A-Z]/.test(value)) s++;
	if (/\d/.test(value) && /[^A-Za-z0-9]/.test(value)) s++;
	if (value.length < 8) s = Math.min(s, 1);
	return Math.max(1, Math.min(4, s));
}

export function init(root = document) {
	root.querySelectorAll('[data-th-password]').forEach((wrap) => {
		const input = wrap.querySelector('input');
		const toggle = wrap.querySelector('[data-th-password-toggle]');
		if (!input) {
			return;
		}

		toggle?.addEventListener('click', () => {
			const show = input.type === 'password';
			input.type = show ? 'text' : 'password';
			toggle.setAttribute('aria-pressed', String(show));
			const label = show ? toggle.dataset.labelHide : toggle.dataset.labelShow;
			if (label) {
				toggle.setAttribute('aria-label', label);
			}
		});

		const meter = input.id ? document.querySelector(`[data-th-strength-for="${input.id}"]`) : null;
		if (meter) {
			const label = meter.querySelector('.th-strength__label');
			const labels = meter.dataset.labels ? meter.dataset.labels.split('|') : LABELS;
			const update = () => {
				const s = score(input.value);
				meter.dataset.score = String(s);
				if (label) {
					label.textContent = labels[s] || '';
				}
			};
			input.addEventListener('input', update);
			update();
		}
	});
}
