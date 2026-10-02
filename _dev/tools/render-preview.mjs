#!/usr/bin/env node
// Optional companion to unbundle.mjs: renders _dev/design/extracted/direction-c.html in a
// headless browser, then writes
//   _dev/design/extracted/preview.png               (full-page screenshot, 1440px viewport)
//   _dev/design/extracted/direction-c.rendered.html (DOM after the dc-runtime/React render)
//
// Needs playwright-core (not a repo dependency) and a locally installed Chrome or Edge:
//   npm i --no-save playwright-core     (or run from a folder where it is installed)
//   node _dev/tools/render-preview.mjs [--channel=chrome|msedge]
//   (or PLAYWRIGHT_CORE=<path to an installed playwright-core folder> node ...)
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

let chromium;
try {
  // PLAYWRIGHT_CORE may point at an installed package folder elsewhere (ESM ignores NODE_PATH).
  const spec = process.env.PLAYWRIGHT_CORE
    ? pathToFileURL(path.join(process.env.PLAYWRIGHT_CORE, 'index.mjs')).href
    : 'playwright-core';
  ({ chromium } = await import(spec));
} catch {
  console.error('playwright-core not found. Install it with "npm i --no-save playwright-core" and re-run.');
  process.exit(1);
}
const here = path.dirname(fileURLToPath(import.meta.url));
const dir = path.resolve(here, '..', 'design', 'extracted');
const channel = (process.argv.find((a) => a.startsWith('--channel=')) || '--channel=chrome').split('=')[1];

const browser = await chromium.launch({ channel, headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
page.on('pageerror', (e) => console.warn('pageerror:', e.message));
await page.goto(pathToFileURL(path.join(dir, 'direction-c.html')).href);
await page.waitForSelector('#dc-root *', { timeout: 30000 });
await page.evaluate(() => document.fonts.ready);
await page.waitForTimeout(1500);
const size = await page.evaluate(() => [document.documentElement.scrollWidth, document.documentElement.scrollHeight]);
fs.writeFileSync(path.join(dir, 'direction-c.rendered.html'),
  '<!DOCTYPE html>\n' + await page.evaluate(() => document.documentElement.outerHTML));
await page.screenshot({ path: path.join(dir, 'preview.png'), fullPage: true });
await browser.close();
console.log(`rendered ${size[0]}x${size[1]} -> preview.png, direction-c.rendered.html`);
// Note: a fetch of ".image-slots.state.json" fails under file:// (CORS). It is the optional
// <image-slot> sidecar and is harmless; the slots render as placeholders.
