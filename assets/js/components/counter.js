/**
 * Character counter for textareas: <textarea maxlength="2000" data-th-counter="msg-count"> + <span id="msg-count">.
 * The visible counter is aria-hidden; screen readers get the native maxlength behaviour.
 */

export function init(root = document) {
	root.querySelectorAll('[data-th-counter]').forEach((field) => {
		const out = document.getElementById(field.dataset.thCounter);
		if (!out) {
			return;
		}
		const max = Number(field.getAttribute('maxlength')) || 0;
		const template = out.dataset.template || '%1$s / %2$s';
		const update = () => {
			out.textContent = template.replace('%1$s', field.value.length.toLocaleString()).replace('%2$s', max.toLocaleString());
		};
		field.addEventListener('input', update);
		update();
	});
}
