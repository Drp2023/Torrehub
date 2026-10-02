import js from '@eslint/js';
import globals from 'globals';

export default [
	js.configs.recommended,
	{
		files: ['assets/js/**/*.js'],
		languageOptions: { ecmaVersion: 2022, sourceType: 'module', globals: { ...globals.browser } },
		rules: { 'no-unused-vars': ['error', { argsIgnorePattern: '^_' }] },
	},
];
