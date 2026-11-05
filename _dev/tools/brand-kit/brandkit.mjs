// Torrehub brand kit from the traced logo: node brandkit.mjs <logo-trace.svg> <lighthouse-trace.svg> <outDir>
// SVG (colour + white), transparent PNGs, 720×720 squares on white and on the brand blue, favicon / app icon set.
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright-core';

const [logoFile, lightFile, out] = process.argv.slice(2);
const NAVY = '#02204c';
const CYAN = '#24b2cf';
const ORANGE = '#eda32a';
const BRAND = '#0056b3'; // --th-blue, the site's primary colour
const WHITE = '#ffffff';

const layers = (svg) => Object.fromEntries([...svg.matchAll(/<path d="([^"]+)"[^>]*data-layer="(\w+)"/g)].map((m) => [m[2], m[1]]));
const logo = layers(fs.readFileSync(logoFile, 'utf8'));
const light = layers(fs.readFileSync(lightFile, 'utf8'));
const LOGO_VB = '0 0 3912 1372';
const LIGHT_VB = '0 0 1056 1788';

const paint = (d, fill) => `<path fill="${fill}" fill-rule="evenodd" d="${d}"/>`;
const wordmark = (ink) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="${LOGO_VB}" width="978" height="343" role="img" aria-label="Torrehub">${paint(logo.navy, ink)}${paint(logo.cyan, CYAN)}${paint(logo.orange, ORANGE)}</svg>`;
// Icon: the logo's lighthouse (traced on its own) over the logo's wave, narrowed to the tower's width.
const icon = (ink) => `<svg xmlns="http://www.w3.org/2000/svg" viewBox="150 140 700 720" width="700" height="720" role="img" aria-label="Torrehub">`
	+ `<svg x="317" y="150" width="366" height="620" viewBox="${LIGHT_VB}">${paint(light.navy, ink)}${paint(light.orange, ORANGE)}</svg>`
	+ `<svg x="200" y="726" width="600" height="125" viewBox="912 1116 2428 240" preserveAspectRatio="none">${paint(logo.cyan, CYAN)}</svg>`
	+ '</svg>';

const svgs = {
	'logo/torrehub-logo.svg': wordmark(NAVY),
	'logo/torrehub-logo-white.svg': wordmark(WHITE),
	'icon/torrehub-icon.svg': icon(NAVY),
	'icon/torrehub-icon-white.svg': icon(WHITE),
};
for (const [file, svg] of Object.entries(svgs)) {
	fs.mkdirSync(path.join(out, path.dirname(file)), { recursive: true });
	fs.writeFileSync(path.join(out, file), svg + '\n');
}

// [file, svg, width, height, background|null (transparent), artwork width share]
const png = [
	['logo/torrehub-logo-1024.png', svgs['logo/torrehub-logo.svg'], 1024, Math.round(1024 * 343 / 978), null, 1],
	['logo/torrehub-logo-white-1024.png', svgs['logo/torrehub-logo-white.svg'], 1024, Math.round(1024 * 343 / 978), null, 1],
	['logo/torrehub-logo-720-white-bg.png', svgs['logo/torrehub-logo.svg'], 720, 720, WHITE, 0.78],
	['logo/torrehub-logo-720-brand-bg.png', svgs['logo/torrehub-logo-white.svg'], 720, 720, BRAND, 0.78],
	['icon/torrehub-icon-1024.png', svgs['icon/torrehub-icon.svg'], 1024, 1024, null, 0.9],
	['icon/torrehub-icon-720-white-bg.png', svgs['icon/torrehub-icon.svg'], 720, 720, WHITE, 0.62],
	['icon/torrehub-icon-720-brand-bg.png', svgs['icon/torrehub-icon-white.svg'], 720, 720, BRAND, 0.62],
	['icon/favicon/favicon-16.png', svgs['icon/torrehub-icon.svg'], 16, 16, null, 1],
	['icon/favicon/favicon-32.png', svgs['icon/torrehub-icon.svg'], 32, 32, null, 1],
	['icon/favicon/favicon-48.png', svgs['icon/torrehub-icon.svg'], 48, 48, null, 1],
	['icon/favicon/apple-touch-icon-180.png', svgs['icon/torrehub-icon.svg'], 180, 180, WHITE, 0.74],
	['icon/favicon/android-chrome-192.png', svgs['icon/torrehub-icon.svg'], 192, 192, WHITE, 0.74],
	['icon/favicon/android-chrome-512.png', svgs['icon/torrehub-icon.svg'], 512, 512, WHITE, 0.74],
	['icon/favicon/site-icon-512.png', svgs['icon/torrehub-icon.svg'], 512, 512, null, 0.92],
];
const b = await chromium.launch({ channel: 'chrome' });
for (const [file, svg, w, h, bg, share] of png) {
	const p = await b.newPage({ viewport: { width: w, height: h }, deviceScaleFactor: 1 });
	const sized = svg.replace(/ width="\d+" height="\d+" role/, ` style="width:${Math.round(w * share)}px;height:auto;max-height:${Math.round(h * share)}px" role`);
	await p.setContent(`<html><body style="margin:0;width:${w}px;height:${h}px;display:grid;place-items:center;background:${bg || 'transparent'}">${sized}</body></html>`);
	fs.mkdirSync(path.join(out, path.dirname(file)), { recursive: true });
	await p.screenshot({ path: path.join(out, file), omitBackground: !bg, clip: { x: 0, y: 0, width: w, height: h } });
	await p.close();
	console.log(file, `${w}×${h}`, bg || 'transparent');
}
await b.close();

// favicon.ico with the 16 / 32 / 48 PNGs inside (PNG-in-ICO, supported everywhere current).
const sizes = [16, 32, 48];
const imgs = sizes.map((s) => fs.readFileSync(path.join(out, `icon/favicon/favicon-${s}.png`)));
const head = Buffer.alloc(6 + 16 * sizes.length);
head.writeUInt16LE(0, 0); head.writeUInt16LE(1, 2); head.writeUInt16LE(sizes.length, 4);
let offset = head.length;
sizes.forEach((s, i) => {
	const e = 6 + 16 * i;
	head.writeUInt8(s, e); head.writeUInt8(s, e + 1); head.writeUInt8(0, e + 2); head.writeUInt8(0, e + 3);
	head.writeUInt16LE(1, e + 4); head.writeUInt16LE(32, e + 6);
	head.writeUInt32LE(imgs[i].length, e + 8); head.writeUInt32LE(offset, e + 12);
	offset += imgs[i].length;
});
fs.writeFileSync(path.join(out, 'icon/favicon/favicon.ico'), Buffer.concat([head, ...imgs]));
console.log('icon/favicon/favicon.ico 16/32/48');
