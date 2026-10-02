/**
 * Dialogs, sheets, drawers on native <dialog class="th-dialog">.
 *
 * Open:  <button data-th-dialog-open="filters" aria-controls="filters">…</button>
 * Close: any [data-th-dialog-close] inside, Esc (native), or a click on the backdrop.
 * Focus: showModal() contains focus; on close it returns to the opener.
 */

export function init(root = document) {
	root.querySelectorAll('[data-th-dialog]').forEach(setup);

	root.addEventListener('click', (event) => {
		const opener = event.target.closest('[data-th-dialog-open]');
		if (!opener) {
			return;
		}
		const dialog = document.getElementById(opener.dataset.thDialogOpen);
		if (dialog instanceof HTMLDialogElement) {
			event.preventDefault();
			open(dialog, opener);
		}
	});
}

function setup(dialog) {
	if (dialog.dataset.thReady) {
		return;
	}
	dialog.dataset.thReady = '1';

	dialog.addEventListener('click', (event) => {
		// A click whose target is the dialog element itself landed on the ::backdrop.
		if (event.target === dialog) {
			dialog.close('backdrop');
		}
		if (event.target.closest('[data-th-dialog-close]')) {
			event.preventDefault();
			dialog.close('dismiss');
		}
	});

	dialog.addEventListener('close', () => {
		const opener = dialog._thOpener;
		document.querySelectorAll(`[aria-controls="${dialog.id}"]`).forEach((el) => el.setAttribute('aria-expanded', 'false'));
		if (opener && document.contains(opener)) {
			opener.focus({ preventScroll: true });
		}
	});
}

export function open(dialog, opener = document.activeElement) {
	setup(dialog);
	dialog._thOpener = opener;
	if (!dialog.open) {
		dialog.showModal();
	}
	document.querySelectorAll(`[aria-controls="${dialog.id}"]`).forEach((el) => el.setAttribute('aria-expanded', 'true'));
	const autofocus = dialog.querySelector('[autofocus]') || dialog.querySelector('.th-dialog__body :is(input, select, textarea, button, a[href])');
	autofocus?.focus({ preventScroll: true });
}
