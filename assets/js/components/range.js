/**
 * Dual range slider: two <input type="range"> inside .th-range[data-th-range].
 * Keeps lo ≤ hi, mirrors values into CSS vars (--lo/--hi) and the [data-th-range-out] labels.
 */

export function init(root = document) {
	root.querySelectorAll('[data-th-range]').forEach((wrap) => {
		const [lo, hi] = wrap.querySelectorAll('input[type="range"]');
		if (!lo || !hi) {
			return;
		}
		const group = wrap.closest('[data-th-range-group]') || wrap.parentElement;
		const outLo = group.querySelector('[data-th-range-out="lo"]');
		const outHi = group.querySelector('[data-th-range-out="hi"]');
		const fmt = (v) => (wrap.dataset.prefix || '') + Number(v).toLocaleString(document.documentElement.lang || undefined) + (wrap.dataset.suffix || '');

		wrap.style.setProperty('--min', lo.min || 0);
		wrap.style.setProperty('--max', lo.max || 100);

		const sync = (event) => {
			if (Number(lo.value) > Number(hi.value)) {
				if (event?.target === lo) {
					lo.value = hi.value;
				} else {
					hi.value = lo.value;
				}
			}
			wrap.style.setProperty('--lo', lo.value);
			wrap.style.setProperty('--hi', hi.value);
			if (outLo) outLo.textContent = fmt(lo.value);
			if (outHi) outHi.textContent = fmt(hi.value) + (Number(hi.value) >= Number(hi.max) && wrap.dataset.plus ? '+' : '');
		};

		lo.addEventListener('input', sync);
		hi.addEventListener('input', sync);
		sync();
	});
}
