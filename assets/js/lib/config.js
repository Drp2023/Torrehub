/**
 * Data passed from PHP via the `script_module_data_th-app` filter.
 */
function read() {
	const el = document.getElementById('wp-script-module-data-th-app');
	if (!el) {
		return {};
	}
	try {
		return JSON.parse(el.textContent || '{}');
	} catch {
		return {};
	}
}

export const config = read();

export function t(key, fallback = '') {
	return config.i18n?.[key] ?? fallback;
}
