#!/usr/bin/env bash
# Package the installable theme: dist/torrehub-<version>.zip
# Only COMMITTED files go in (git archive), dev folders are dropped via `export-ignore` in .gitattributes.
# This zip is the only thing that may be deployed to production.
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

[[ -f style.css ]] || { echo "style.css missing — not a theme yet"; exit 1; }
version=$(sed -n 's/^[[:space:]]*Version:[[:space:]]*//p' style.css | head -1 | tr -d '\r')
ref="${1:-HEAD}"

if [[ -n "$(git status --porcelain)" ]]; then
  echo "WARNING: uncommitted changes are NOT included (packaging $ref)." >&2
fi

mkdir -p dist
out="dist/torrehub-${version:-dev}.zip"
git archive --format=zip --prefix=torrehub/ -o "$out" "$ref"

# Guard rails: nothing dev-only or sensitive may ship.
listing=$(unzip -Z1 "$out" 2>/dev/null || python -c "import zipfile,sys;print('\n'.join(zipfile.ZipFile(sys.argv[1]).namelist()))" "$out")
if grep -qE '^torrehub/(_dev|bin|hotfix|backup|node_modules|tests|assets/src)/|\.sql$|local-clean|\.env' <<< "$listing"; then
  echo "ABORT: forbidden path in zip:"; grep -E '^torrehub/(_dev|bin|hotfix|backup|node_modules|tests|assets/src)/|\.sql$|local-clean|\.env' <<< "$listing"; rm -f "$out"; exit 1
fi
grep -q '^torrehub/style.css$' <<< "$listing" || { echo "ABORT: style.css not at zip root"; rm -f "$out"; exit 1; }

echo "Built $out ($(du -h "$out" | cut -f1), $(grep -vc '/$' <<< "$listing") files) from $ref"
