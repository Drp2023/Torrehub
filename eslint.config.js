import js from '@eslint/js';
import globals from 'globals';

export default [
	js.configs.recommended,
	{
		files: ['assets/js/**/*.js'],
		languageOptions: { ecmaVersion: 2022, sourceType: 'module', globals: { ...globals.browser } },
		rules: { 'no-unused-vars': ['error', { argsIgnorePattern: '^_' }] },
	},
	{
		// Dev tooling + Playwright tests: Node, plus browser globals inside page.evaluate() callbacks.
		files: ['_dev/**/*.mjs'],
		languageOptions: { ecmaVersion: 2022, sourceType: 'module', globals: { ...globals.node, ...globals.browser } },
	},
];
