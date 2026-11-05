// Vectorise a flat-colour logo region: node trace.js <sheet.png> <x> <y> <w> <h> <out.svg> [scale]
// Each pixel goes to the nearest palette colour (white = background); every colour layer is traced with potrace;
// each layer's fill is the mean colour of its clean (non-edge) pixels.
const Jimp = require('jimp');
const { Potrace } = require('potrace');
const fs = require('fs');

const [src, x, y, w, h, out, scaleArg] = process.argv.slice(2);
const scale = Number(scaleArg || 4);
const palette = { navy: [8, 40, 72], cyan: [40, 180, 212], orange: [240, 160, 48] };
const white = [255, 255, 255];
const dist = (a, b) => (a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2 + (a[2] - b[2]) ** 2;

(async () => {
  const img = (await Jimp.read(src)).crop(+x, +y, +w, +h).resize(+w * scale, +h * scale, Jimp.RESIZE_BICUBIC);
  const { width, height, data } = img.bitmap;
  const layers = {}; const sums = {};
  for (const k of Object.keys(palette)) {
    layers[k] = new Jimp(width, height, 0xffffffff);
    sums[k] = [0, 0, 0, 0];
  }
  for (let i = 0; i < width * height; i++) {
    const p = [data[i * 4], data[i * 4 + 1], data[i * 4 + 2]];
    // Anti-aliased edges are a colour mixed with white: pick the colour whose colour→white line is nearest, and
    // count the pixel in that layer when it is at least half covered (no halo of a third colour around shapes).
    let best = 'white'; let bd = Infinity; let cover = 0;
    for (const [k, c] of Object.entries(palette)) {
      const v = [c[0] - 255, c[1] - 255, c[2] - 255];
      const q = [p[0] - 255, p[1] - 255, p[2] - 255];
      const t = Math.max(0, Math.min(1, (q[0] * v[0] + q[1] * v[1] + q[2] * v[2]) / (v[0] ** 2 + v[1] ** 2 + v[2] ** 2)));
      const d = dist(p, [255 + t * v[0], 255 + t * v[1], 255 + t * v[2]]);
      if (d < bd) { bd = d; best = k; cover = t; }
    }
    if (cover < 0.5) continue;
    bd = dist(p, palette[best]);
    layers[best].bitmap.data.writeUInt32BE(0x000000ff, i * 4);
    if (bd < 1600) { sums[best][0] += p[0]; sums[best][1] += p[1]; sums[best][2] += p[2]; sums[best][3]++; }
  }
  const hex = (s) => '#' + s.slice(0, 3).map((v) => Math.round(v / s[3]).toString(16).padStart(2, '0')).join('');
  const paths = [];
  for (const k of ['navy', 'cyan', 'orange']) {
    if (!sums[k][3]) continue;
    const buf = await layers[k].getBufferAsync(Jimp.MIME_PNG);
    const d = await new Promise((resolve, reject) => {
      const t = new Potrace({ turdSize: 12 * scale, optTolerance: 0.35, alphaMax: 1, threshold: 128, blackOnWhite: true });
      t.loadImage(buf, (err) => (err ? reject(err) : resolve(t.getPathTag(hex(sums[k])))));
    });
    paths.push(d.replace(/fill-rule="evenodd"/, 'fill-rule="evenodd" data-layer="' + k + '"'));
    console.log(k, hex(sums[k]), sums[k][3], 'px');
  }
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${width} ${height}" width="${+w}" height="${+h}">\n${paths.join('\n')}\n</svg>\n`;
  fs.writeFileSync(out, svg);
  console.log('svg', out, Math.round(svg.length / 1024) + ' KB');
})();
