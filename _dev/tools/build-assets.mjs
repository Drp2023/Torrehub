#!/usr/bin/env node
// Build CSS bundles: assets/css/bundle.json → assets/css/build/<bundle>.css (concatenated + minified).
//   node _dev/tools/build-assets.mjs
// No dependencies on purpose (the repo must build on a bare Node). url() paths are rebased from
// assets/css/<sub>/file.css to assets/css/build/. Swap for Lightning CSS later if needed (phase 8).

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const cssDir = path.join(root, 'assets/css');
const outDir = path.join(cssDir, 'build');
const bundles = JSON.parse(fs.readFileSync(path.join(cssDir, 'bundle.json'), 'utf8'));

function rebase(css, fromFile) {
	const fromDir = path.dirname(fromFile);
	return css.replace(/url\(\s*(['"]?)(?!data:|https?:|\/)([^'")]+)\1\s*\)/g, (m, q, url) => {
		const abs = path.resolve(fromDir, url);
		const rel = path.relative(outDir, abs).split(path.sep).join('/');
		return `url("${rel}")`;
	});
}

function minify(css) {
	return css
		.replace(/\/\*[\s\S]*?\*\//g, '') // comments
		.replace(/\s+/g, ' ')
		.replace(/\s*([{}:;,>~])\s*/g, '$1')
		.replace(/;}/g, '}')
		.replace(/\s*!important/g, '!important')
		.trim();
}

fs.mkdirSync(outDir, { recursive: true });
for (const [name, files] of Object.entries(bundles)) {
	const parts = files.map((f) => {
		const file = path.join(cssDir, f);
		return rebase(fs.readFileSync(file, 'utf8'), file);
	});
	const out = minify(parts.join('\n'));
	fs.writeFileSync(path.join(outDir, `${name}.css`), out + '\n');
	console.log(`${name}.css  ${files.length} files → ${(out.length / 1024).toFixed(1)} KB`);
}
