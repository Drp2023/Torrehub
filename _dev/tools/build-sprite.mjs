#!/usr/bin/env node
// Build assets/icons/sprite.svg (+ icons.json) from the Direction C icons and the theme-added ones.
//   node _dev/tools/build-sprite.mjs
// Sources:
//   _dev/design/extracted/icons/*.svg   (from unbundle.mjs — design originals)
//   _dev/tools/icons-extra/*.svg        (theme-added, same 24px stroke style)
// Each symbol is `#i-<name>`; presentation attributes sit on <symbol> so `currentColor` and CSS overrides work.

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const sources = [
	path.join(root, '_dev/design/extracted/icons'),
	path.join(root, '_dev/tools/icons-extra'),
];
const outDir = path.join(root, 'assets/icons');

// Not part of the UI kit:
// - status-*: phone status-bar mockup chrome
// - drawer-*: the M-NAV drawer redraws 4 category icons differently; the cat-* set is canonical (INVENTORY §4.1)
// - *-m: mobile duplicates of the account-type drawings; one drawing per type is enough
const skip = (name) => /^status-|^drawer-|-m$/.test(name);

// Variants generated from a base icon.
const variants = {
	'heart-filled': { from: 'heart', set: { fill: 'currentColor' } },
};

const ATTRS = ['fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin'];

function parse(file) {
	const src = fs.readFileSync(file, 'utf8');
	const svg = src.match(/<svg\b([^>]*)>([\s\S]*?)<\/svg>/);
	if (!svg) throw new Error(`No <svg> in ${file}`);
	const attrs = Object.fromEntries([...svg[1].matchAll(/([\w:-]+)="([^"]*)"/g)].map((m) => [m[1], m[2]]));
	const body = svg[2].replace(/<!--[\s\S]*?-->/g, '').replace(/\s*\n\s*/g, '').trim();
	return { viewBox: attrs.viewBox || '0 0 24 24', attrs, body };
}

const icons = new Map();
for (const dir of sources) {
	for (const f of fs.readdirSync(dir).filter((f) => f.endsWith('.svg')).sort()) {
		const name = f.slice(0, -4);
		if (skip(name)) continue;
		icons.set(name, parse(path.join(dir, f))); // later sources override earlier ones
	}
}
for (const [name, v] of Object.entries(variants)) {
	const base = icons.get(v.from);
	if (base) icons.set(name, { ...base, attrs: { ...base.attrs, ...v.set } });
}

const symbols = [...icons.entries()]
	.sort(([a], [b]) => a.localeCompare(b))
	.map(([name, ic]) => {
		const a = ATTRS.filter((k) => ic.attrs[k] !== undefined).map((k) => `${k}="${ic.attrs[k]}"`).join(' ');
		return `<symbol id="i-${name}" viewBox="${ic.viewBox}" ${a}>${ic.body}</symbol>`;
	});

fs.mkdirSync(outDir, { recursive: true });
fs.writeFileSync(
	path.join(outDir, 'sprite.svg'),
	`<svg xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="display:none">\n${symbols.join('\n')}\n</svg>\n`
);
fs.writeFileSync(path.join(outDir, 'icons.json'), JSON.stringify([...icons.keys()].sort(), null, '\t') + '\n');
console.log(`sprite.svg: ${symbols.length} symbols, ${fs.statSync(path.join(outDir, 'sprite.svg')).size} bytes`);
