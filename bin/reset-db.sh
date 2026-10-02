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
echo "Restored $(basename "$SNAP") — active theme: $(wp option get stylesheet --skip-plugins --skip-themes)"
