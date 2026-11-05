# Brand kit generator (dev only — not in the theme zip)

The logo has no vector original; these scripts trace it from the brand sheet (`_dev/brand/torrehub-logo.png`) and
render the kit. Output goes **outside the repo**: `C:\Users\Miklos\Local Sites\Torrehub\brand-kit` (client request
2026-11).

```bash
cd _dev/tools/brand-kit
npm i --no-save potrace@2.1.8 jimp@0.22.12          # tracer + image IO, only for this
node trace.js ../../brand/torrehub-logo.png 227 116 978 343 logo-trace.svg 4      # primary logo, 4× upscaled
node lighthouse-crop.js ../../brand/torrehub-logo.png lighthouse.png              # the lighthouse alone
node trace.js lighthouse.png 0 0 176 298 lighthouse-trace.svg 6
node brandkit.mjs logo-trace.svg lighthouse-trace.svg "/c/Users/Miklos/Local Sites/Torrehub/brand-kit"
```

`brandkit.mjs` needs `playwright-core` (the theme's dev dependency) and Chrome; run it from the theme root
(`node _dev/tools/brand-kit/brandkit.mjs …`) so the module resolves. Colours: navy `#02204c`, turquoise `#24b2cf`,
orange `#eda32a` (means of the traced layers), brand blue `#0056b3` (`--th-blue`). The kit's README explains the files.
