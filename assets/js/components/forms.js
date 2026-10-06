/**
 * Small form helpers:
 *  - form[data-th-confirm]: ask before submitting (e.g. deleting a listing);
 *  - input[data-th-nif]: Spanish NIF check as you type (DNI, NIE-form NIF, CIF — same rules as the server,
 *    which stays the authority).
 */

const LETTERS = 'TRWAGMYFPDXBNJZSQVHLCKE';

export function normaliseNif(value) {
	let v = String(value).toUpperCase().replace(/[\s.-]/g, '');
	if (v.startsWith('ES') && v.length === 11) {
		v = v.slice(2);
	}
	return v;
}

export function validNif(nif) {
	let m = nif.match(/^(\d{8})([A-Z])$/);
	if (m) {
		return LETTERS[Number(m[1]) % 23] === m[2];
	}
	m = nif.match(/^([XYZ])(\d{7})([A-Z])$/);
	if (m) {
		return LETTERS[Number('XYZ'.indexOf(m[1]) + m[2]) % 23] === m[3];
	}
	m = nif.match(/^([ABCDEFGHJNPQRSUVW])(\d{7})([0-9A-J])$/);
	if (m) {
		let sum = 0;
		[...m[2]].forEach((ch, i) => {
			let d = Number(ch);
			if (i % 2 === 0) {
				d *= 2;
				d = Math.floor(d / 10) + (d % 10);
			}
			sum += d;
		});
		const digit = (10 - (sum % 10)) % 10;
		const letter = 'JABCDEFGHI'[digit];
		if ('PQRSNW'.includes(m[1])) {
			return m[3] === letter;
		}
		if ('ABEH'.includes(m[1])) {
			return m[3] === String(digit);
		}
		return m[3] === String(digit) || m[3] === letter;
	}
	return false;
}

export function init(root = document) {
	root.addEventListener('submit', (event) => {
		const form = event.target.closest('form[data-th-confirm]');
		if (form && !window.confirm(form.dataset.thConfirm)) {
			event.preventDefault();
		}
	});

	root.querySelectorAll('input[data-th-nif]').forEach((input) => {
		const control = input.closest('.th-control');
		const check = () => {
			const value = normaliseNif(input.value);
			if (!value) {
				input.removeAttribute('aria-invalid');
				control?.classList.remove('th-control--status');
				return;
			}
			const ok = validNif(value);
			input.setAttribute('aria-invalid', String(!ok));
			control?.classList.toggle('th-control--status', ok);
		};
		input.addEventListener('blur', check);
		input.addEventListener('input', () => input.getAttribute('aria-invalid') === 'true' && check());
	});
}
