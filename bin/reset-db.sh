#!/usr/bin/env bash
# Restore the anonymised clean snapshot (backup/local-clean.sql) — run before every phase check.
set -euo pipefail
source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

SNAP="$BACKUP_DIR/local-clean.sql"
LIKE_PREFIX='wp\_d5c58b26b6\_'   # LIKE-escaped live table prefix
[[ -f "$SNAP" ]] || { echo "No snapshot. Run bin/setup-local.sh first."; exit 1; }

mysqlc() { mysql $LOCAL_MYSQL_ARGS --default-character-set=utf8mb4 "$@" | tr -d '\r'; return "${PIPESTATUS[0]}"; }

# Keep WordPress from recreating plugin tables mid-restore (see setup-local.sh step 3).
printf '<?php $upgrading = %s;\n' "$(date +%s)" > "$WP_PATH/.maintenance"
trap 'rm -f "$WP_PATH/.maintenance"' EXIT

drop=$(mysqlc -N -e "SET SESSION group_concat_max_len=1000000; SELECT GROUP_CONCAT(CONCAT('\`',table_name,'\`')) FROM information_schema.tables WHERE table_schema='local' AND table_name LIKE '${LIKE_PREFIX}%'")
[[ -n "$drop" && "$drop" != "NULL" ]] && mysqlc local -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE $drop;"
mysql $LOCAL_MYSQL_ARGS --default-character-set=utf8mb4 --init-command="SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'; SET SESSION FOREIGN_KEY_CHECKS=0;" local < "$SNAP"
rm -f "$WP_PATH/.maintenance"
wp cache flush --quiet 2>/dev/null || true
wp rewrite flush --skip-themes --quiet 2>/dev/null || true
# The snapshot carries the old theme; the theme's WP-CLI commands below only exist while it is active (runbook B4 → C).
wp theme activate torrehub --quiet
# Decisions applied after the snapshot was taken (snapshots made before 2026-10-06 still carry them).
wp torrehub purge-nie --apply --quiet 2>/dev/null || true          # GDPR: no NIE numbers are kept.
wp torrehub fix-option-values --apply --quiet 2>/dev/null || true  # Empty € option values.
wp option update tlrs_threshold 3 --quiet 2>/dev/null || true      # Report threshold (client decision).
wp torrehub migrate-pages --apply --quiet 2>/dev/null || true      # Elementor pages → blocks + theme templates.
wp torrehub trash-demo --apply --quiet 2>/dev/null || true         # Demo posts/pages to the trash (client decision).
# Plugin end state (runbook D, after the migrations above): only classified-listing + gtranslate stay active.
wp plugin deactivate classified-listing-pro classified-listing-store rtcl-seller-verification rtcl-search-alert rtcl-verification   rtcl-elementor-builder review-schema-pro review-schema elementor-pro elementor cldirectory-core rt-framework classified-listing-toolkits   gdpr-cookie-compliance insert-headers-and-footers filester duplicate-page fluentform advanced-custom-fields --quiet 2>/dev/null || true
# Local test accounts (e2e, a11y; the anonymiser gave everyone a random password): member tester/tester, business seller user54/seller54.
wp user get tester --field=ID >/dev/null 2>&1 || wp user create tester tester@example.test --role=customer --user_pass=tester --display_name="Test Member" --quiet
wp user meta update tester _rtcl_user_type buyer --quiet
wp user update user54 --user_pass=seller54 --skip-email --quiet
echo "Restored $(basename "$SNAP") — active theme: $(wp option get stylesheet --skip-plugins --skip-themes)"
