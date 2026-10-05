// Slice one design device frame into N screenshots: node _dev/tools/slice-frame.mjs <frameIndex> [slices]
import { chromium } from 'playwright-core';
import path from 'node:path';
import { pathToFileURL } from 'node:url';
const root = process.cwd();
const b = await chromium.launch({ channel: 'chrome', headless: true });
const p = await b.newPage({ viewport: { width: 2000, height: 1200 } });
await p.goto(pathToFileURL(path.join(root, '_dev/design/extracted/direction-c.html')).href, { waitUntil: 'networkidle' });
await p.waitForTimeout(1500);
const frames = p.locator('[style*="width: 1440px"], [style*="width:1440px"], [style*="width: 390px"], [style*="width:390px"]');
const [idx, slices] = [Number(process.argv[2]), Number(process.argv[3] || 3)];
const el = frames.nth(idx);
await el.scrollIntoViewIfNeeded();
const box = await el.boundingBox();
const h = box.height / slices;
const sy = await p.evaluate(() => window.scrollY);
for (let s = 0; s < slices; s++) {
  await p.screenshot({ path: `_dev/screens/design/frame-${String(idx).padStart(2,'0')}-part${s+1}.png`, clip: { x: box.x, y: box.y + sy + s * h, width: box.width, height: h }, fullPage: true });
}
console.log('ok', box);
await b.close();
