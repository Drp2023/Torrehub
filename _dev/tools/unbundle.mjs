#!/usr/bin/env node
// Unbundle a "__bundler" HTML package (Claude design export) into a plain,
// self-contained HTML file + an assets/ folder.
//
// Usage:
//   node _dev/tools/unbundle.mjs [input.html] [outDir]
// Defaults:
//   input  = _dev/design/Torrehub - Direction C, Modern Local Hub.html
//   outDir = _dev/design/extracted
//
// Bundle format (as decoded by the loader script embedded in the input):
//   <script type="__bundler/manifest">      { "<uuid>": { mime, compressed, data(base64) } }
//   <script type="__bundler/template">      JSON string: the real HTML document; assets are
//                                           referenced by bare uuid (src="<uuid>", url("<uuid>"))
//   <script type="__bundler/ext_resources"> [{ id: "<cdn url>", uuid }] -> exposed to the page as
//                                           window.__resources[id] = <asset url> (React UMD builds)
//   <script type="__bundler/page_order">    uuids of nested iframe pages (about:blank#<uuid>)
// No npm dependencies: node:fs, node:zlib, node:path only.

import fs from 'node:fs';
import zlib from 'node:zlib';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const repo = path.resolve(here, '..', '..');
const input = path.resolve(process.argv[2] || path.join(repo, '_dev/design/Torrehub - Direction C, Modern Local Hub.html'));
const outDir = path.resolve(process.argv[3] || path.join(repo, '_dev/design/extracted'));
const assetsDir = path.join(outDir, 'assets');
const outHtml = path.join(outDir, 'direction-c.html');

const src = fs.readFileSync(input, 'utf8');

function block(type, required = true) {
  const open = `<script type="__bundler/${type}">`;
  const i = src.indexOf(open);
  if (i < 0) {
    if (required) throw new Error(`missing <script type="__bundler/${type}">`);
    return null;
  }
  const start = i + open.length;
  const end = src.indexOf('</script>', start);
  return JSON.parse(src.slice(start, end));
}

const manifest = block('manifest');
let template = block('template');
const extResources = block('ext_resources', false) || [];
const pageOrder = new Set(block('page_order', false) || []);

const EXT = {
  'font/woff2': 'woff2', 'font/woff': 'woff', 'font/ttf': 'ttf', 'font/otf': 'otf',
  'application/font-woff': 'woff', 'application/vnd.ms-fontobject': 'eot',
  'image/png': 'png', 'image/jpeg': 'jpg', 'image/gif': 'gif', 'image/webp': 'webp',
  'image/svg+xml': 'svg', 'image/avif': 'avif', 'image/x-icon': 'ico',
  'text/javascript': 'js', 'application/javascript': 'js', 'text/css': 'css',
  'text/html': 'html', 'application/json': 'json', 'text/plain': 'txt',
  'video/mp4': 'mp4', 'audio/mpeg': 'mp3',
};
const extFor = (mime) => EXT[(mime || '').toLowerCase().split(';')[0].trim()] || 'bin';
const slug = (s) => String(s).toLowerCase().replace(/[^a-z0-9.]+/g, '-').replace(/^-+|-+$/g, '');
const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

// ---- 1. decode every manifest entry --------------------------------------
const entries = Object.entries(manifest).map(([uuid, e]) => {
  let bytes = Buffer.from(e.data, 'base64');
  if (e.compressed) bytes = zlib.gunzipSync(bytes);
  return { uuid, mime: e.mime, compressed: !!e.compressed, bytes, name: null, hint: '' };
});
const byUuid = new Map(entries.map((e) => [e.uuid, e]));

// ---- 2. derive descriptive names from template / manifest hints ----------
// 2a. @font-face blocks: "/* subset */ @font-face { font-family; font-weight; src: url(uuid) }"
const fontInfo = new Map(); // uuid -> { family, subset, weights:Set, ranges:Set }
const ffRe = /(?:\/\*\s*([\w-]+)\s*\*\/\s*)?@font-face\s*\{([^}]*)\}/g;
for (const m of template.matchAll(ffRe)) {
  const body = m[2];
  const fam = /font-family:\s*['"]?([^;'"]+)['"]?/.exec(body)?.[1]?.trim();
  const wt = /font-weight:\s*([^;]+);/.exec(body)?.[1]?.trim();
  const style = /font-style:\s*([^;]+);/.exec(body)?.[1]?.trim();
  const range = /unicode-range:\s*([^;]+);/.exec(body)?.[1]?.trim();
  const u = /url\(["']?([0-9a-f-]{36})["']?\)/.exec(body)?.[1];
  if (!u) continue;
  const fi = fontInfo.get(u) || { family: fam, subset: m[1] || '', weights: new Set(), styles: new Set(), ranges: new Set() };
  if (wt) fi.weights.add(wt);
  if (style) fi.styles.add(style);
  if (range) fi.ranges.add(range);
  fontInfo.set(u, fi);
}
for (const [u, fi] of fontInfo) {
  const e = byUuid.get(u);
  if (!e) continue;
  const ws = [...fi.weights].map(Number).filter((n) => !Number.isNaN(n)).sort((a, b) => a - b);
  const wpart = ws.length > 1 ? `${ws[0]}-${ws[ws.length - 1]}` : ws[0] ?? '';
  const italic = fi.styles.has('italic') ? '-italic' : '';
  e.name = [slug(fi.family), slug(fi.subset), wpart].filter(Boolean).join('-') + italic;
  e.hint = `@font-face ${fi.family} [${fi.subset || '?'}] weights ${[...fi.weights].join(',')}` +
    (fi.weights.size > 1 ? ' (same file for every weight => variable font)' : '') +
    ` — unicode-range: ${[...fi.ranges].join(' | ')}`;
}

// 2b. ext_resources (CDN URL -> uuid): use the URL basename
for (const r of extResources) {
  const e = byUuid.get(r.uuid);
  if (!e || e.name) continue;
  e.name = path.basename(new URL(r.id).pathname).replace(/\.(js|css)$/, '');
  e.hint = `ext_resource ${r.id}`;
}

// 2c. <img src="uuid" alt="..."> and other tags referencing the uuid
for (const e of entries) {
  if (e.name) continue;
  const img = new RegExp(`<img[^>]*src="${esc(e.uuid)}"[^>]*>`).exec(template);
  const alt = img && /alt="([^"]+)"/.exec(img[0])?.[1];
  if (alt) { e.name = slug(alt) + (e.mime.startsWith('image/') ? '-logo' : ''); e.hint = `<img alt="${alt}">`; continue; }
  if (/javascript/.test(e.mime)) {
    const head = e.bytes.subarray(0, 4000).toString('utf8');
    const ce = /customElements\.define\(\s*['"]([\w-]+)['"]/.exec(e.bytes.toString('utf8'));
    if (/dc-runtime/.test(head)) { e.name = 'dc-runtime'; e.hint = 'header: "GENERATED from dc-runtime/src/*.ts"'; }
    else if (ce) { e.name = `${ce[1]}-element`; e.hint = `defines custom element <${ce[1]}>`; }
  }
}

// 2d. fallback + de-duplication
const used = new Set();
for (const e of entries) {
  let base = e.name || e.uuid.slice(0, 8);
  let file = `${base}.${extFor(e.mime)}`;
  for (let n = 2; used.has(file); n++) file = `${base}-${n}.${extFor(e.mime)}`;
  used.add(file);
  e.file = file;
}

// ---- 3. write assets ------------------------------------------------------
fs.mkdirSync(assetsDir, { recursive: true });
for (const e of entries) {
  if (pageOrder.has(e.uuid)) continue; // nested pages are written next to the html below
  fs.writeFileSync(path.join(assetsDir, e.file), e.bytes);
}

// ---- 4. rewrite template ----------------------------------------------------
for (const e of entries) {
  if (pageOrder.has(e.uuid)) {
    const pageFile = `page-${e.uuid.slice(0, 8)}.html`;
    fs.writeFileSync(path.join(outDir, pageFile), e.bytes);
    template = template.split(`about:blank#${e.uuid}`).join(pageFile);
    e.file = `../${pageFile}`;
    continue;
  }
  e.refs = template.split(e.uuid).length - 1;
  template = template.split(e.uuid).join(`assets/${e.file}`);
}
// SRI/crossorigin would break file:// loading of local copies (same as the loader does).
template = template.replace(/\s+integrity="[^"]*"/gi, '').replace(/\s+crossorigin="[^"]*"/gi, '');

// The dc-runtime looks up React/ReactDOM in window.__resources (keyed by CDN URL) and otherwise
// fetches from unpkg. Point it at the local copies so the page works offline.
const resourceMap = {};
for (const r of extResources) {
  const e = byUuid.get(r.uuid);
  if (e) resourceMap[r.id] = `assets/${e.file}`;
}
const resourceScript = '<script>window.__resources = ' +
  JSON.stringify(resourceMap).replace(/<\//g, '<\\/') + ';</' + 'script>';
const headOpen = template.match(/<head[^>]*>/i);
if (headOpen) {
  const i = headOpen.index + headOpen[0].length;
  template = template.slice(0, i) + resourceScript + template.slice(i);
}

const leftover = [...new Set(template.match(/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/g) || [])];
fs.writeFileSync(outHtml, template);

// ---- 5. MANIFEST.md ---------------------------------------------------------
const extIds = new Map(extResources.map((r) => [r.uuid, r.id]));
const rows = entries.map((e) =>
  `| \`${e.file}\` | ${e.mime} | ${e.bytes.length.toLocaleString('en-US')} | ${e.compressed ? 'gzip' : '—'} | \`${e.uuid}\` | ${e.refs ?? 0}${extIds.has(e.uuid) ? ' (via window.__resources)' : ''} | ${e.hint.replace(/\|/g, '\\|') || '—'} |`);
const md = `# Extracted assets — Direction C bundle

Generated by \`_dev/tools/unbundle.mjs\` from \`${path.relative(repo, input).replace(/\\/g, '/')}\`.
Do not edit by hand; re-run \`node _dev/tools/unbundle.mjs\`.

| File | MIME | Bytes (decoded) | Stored | Original UUID | Template refs | Naming hint |
|---|---|---:|---|---|---:|---|
${rows.join('\n')}

Notes
- "Template refs" = occurrences of the UUID in the template that were rewritten to \`assets/<file>\`.
- React / ReactDOM are not referenced by UUID in the template; the dc-runtime resolves them through
  \`window.__resources[<unpkg URL>]\`, which the unbundler injects pointing at the local copies.
- Fonts: one file serves every declared weight of a family+subset (variable woff2).
`;
fs.writeFileSync(path.join(assetsDir, 'MANIFEST.md'), md);

console.log(`template: ${template.length} chars -> ${path.relative(repo, outHtml)}`);
for (const e of entries) console.log(`  ${e.file.padEnd(44)} ${e.mime.padEnd(16)} ${String(e.bytes.length).padStart(7)} B  refs=${e.refs ?? 0}  ${e.uuid}`);
console.log(leftover.length ? `WARNING leftover uuids: ${leftover.join(', ')}` : 'no leftover UUID references');
