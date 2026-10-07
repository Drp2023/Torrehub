#!/usr/bin/env bash
# Every e2e suite + accessibility + SEO probe, one line per suite. Run after bin/reset-db.sh (Git Bash, from the theme root).
cd "$(dirname "${BASH_SOURCE[0]}")/../.."
fail=0
for t in header archive listing auth account listing-form chat search-alerts content admin-forms consent migration; do
  out=$(node "_dev/tests/e2e/$t.mjs" 2>&1); code=$?
  printf '%-14s %s  (%s PASS, %s FAIL)\n' "$t" "$([ $code -eq 0 ] && echo ok || echo FAILED)" "$(grep -c '^PASS' <<<"$out")" "$(grep -c '^FAIL' <<<"$out")"
  [ $code -eq 0 ] || { fail=1; grep '^FAIL' <<<"$out" | sed 's/^/    /'; }
done
for t in a11y seo; do
  out=$(node "_dev/tests/$t.mjs" 2>&1); code=$?
  printf '%-14s %s  %s\n' "$t" "$([ $code -eq 0 ] && echo ok || echo FAILED)" "$(tail -1 <<<"$out")"
  [ $code -eq 0 ] || { fail=1; grep -E '^!!|ISSUES|\[(serious|critical|moderate|minor)\]' <<<"$out" | head -20 | sed 's/^/    /'; }
done
exit $fail
