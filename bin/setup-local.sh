#!/usr/bin/env bash
# Build the local working copy of torrehub.com inside the LocalWP site from backup/.
# Idempotent. Run from Git Bash or Local's "Open site shell":  bash bin/setup-local.sh
#
# PRIVACY: this script never prints SQL content. backup/db_tables/*.sql are only piped into mysql.
set -uo pipefail

source "$(dirname "${BASH_SOURCE[0]}")/env.sh"

PREFIX="wp_d5c58b26b6_"
LIKE_PREFIX='wp\_d5c58b26b6\_'   # LIKE-escaped live table prefix
LIVE_DOMAIN="torrehub.com"
[[ -d "$BACKUP_DIR" ]] || { echo "BACKUP_DIR not found: $BACKUP_DIR (set BACKUP_DIR=...)"; exit 1; }
SQL_DIR="$BACKUP_DIR/db_tables"
WPC="$WP_PATH/wp-content"
LOG="$BACKUP_DIR/setup-local.log"     # outside the repo
MAILPIT_SMTP_PORT="${MAILPIT_SMTP_PORT:-10001}"
DB_NAME="local"

mysqlc() { mysql $LOCAL_MYSQL_ARGS --default-character-set=utf8mb4 "$@" | tr -d '\r'; return "${PIPESTATUS[0]}"; }
# Drop every table carrying the live prefix (or the BMI temp prefix) so the import can be re-run.
drop_live_tables() {
  local list
  list=$(mysqlc -N -e "SET SESSION group_concat_max_len=1000000; SELECT GROUP_CONCAT(CONCAT('\`',table_name,'\`')) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND (table_name LIKE '${LIKE_PREFIX}%' OR table_name REGEXP '^[0-9]+_${PREFIX}')")
  [[ -n "$list" && "$list" != "NULL" ]] && mysqlc "$DB_NAME" -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE $list;"
  return 0
}
step()   { printf '\n\033[1;34m==> %s\033[0m\n' "$*" | tee -a "$LOG"; }
note()   { printf '    %s\n' "$*" | tee -a "$LOG"; }

: > "$LOG"
[[ -d "$SQL_DIR" ]] || { echo "backup/db_tables missing"; exit 1; }
mysqlc -e "SELECT 1" >/dev/null || { echo "Cannot reach Local MySQL. Start the site in Local, or run from Local's 'Open site shell'."; exit 1; }

# ---------------------------------------------------------------- 1. GoDaddy drop-ins off, safety mu-plugin on
step "1. Disable GoDaddy-specific drop-ins (rename, never delete) + install local safety mu-plugin"
if [[ -f "$WPC/object-cache.php" ]]; then mv "$WPC/object-cache.php" "$WPC/object-cache.php.disabled"; note "object-cache.php -> object-cache.php.disabled"; else note "object-cache.php already disabled"; fi
mkdir -p "$WPC/mu-plugins/_disabled"
for f in gd-system-plugin.php gd-system-plugin object-cache-pro.php object-cache-pro vendor; do
  if [[ -e "$WPC/mu-plugins/$f" ]]; then mv "$WPC/mu-plugins/$f" "$WPC/mu-plugins/_disabled/$f"; note "mu-plugins/$f -> mu-plugins/_disabled/"; fi
done
cp "$REPO_DIR/bin/mu-plugins/th-local-safety.php" "$WPC/mu-plugins/th-local-safety.php"
note "mu-plugins/th-local-safety.php installed (forces wp_mail -> Mailpit :$MAILPIT_SMTP_PORT)"

# ---------------------------------------------------------------- 2. Local URL (before the prefix switch)
step "2. Resolve Local site URL"
LOCAL_URL="${LOCAL_URL:-}"
if [[ -z "$LOCAL_URL" ]]; then
  LOCAL_URL=$(mysqlc -N -e "SELECT option_value FROM ${DB_NAME}.wp_options WHERE option_name='home'" 2>/dev/null || true)
fi
LOCAL_URL="${LOCAL_URL:-http://torrehub-website.local}"
LOCAL_URL="${LOCAL_URL%/}"
LOCAL_HOST="${LOCAL_URL#*://}"
note "Local URL: $LOCAL_URL"

# ---------------------------------------------------------------- 3. Import
step "3. Import $(ls "$SQL_DIR"/*.sql | wc -l) table files into '$DB_NAME'"
# Any page hit while tables are missing lets plugins (Action Scheduler, CookieYes…) recreate tables with
# seed rows => duplicate-key errors on import. Hold WordPress in maintenance mode for the duration.
printf '<?php $upgrading = %s;\n' "$(date +%s)" > "$WP_PATH/.maintenance"
trap 'rm -f "$WP_PATH/.maintenance"' EXIT
drop_live_tables; note "previous live-prefix tables dropped (re-runnable import); site in maintenance mode"
ok=0; failed=()
for f in "$SQL_DIR"/*.sql; do
  mysqlc "$DB_NAME" -e "SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS \`$(basename "$f" .sql)\`;"
  # stderr can quote row data ("near '...'") — keep only the error code/position, never the payload.
  # - sql_mode relaxed: live dump has 0000-00-00 datetime defaults (MySQL 8 strict => ERROR 1067)
  # - FK checks off: some plugin tables reference tables imported later (ERROR 1824)
  # - BMI writes `<timestamp>_<prefix>table`; strip the timestamp in quoted identifiers while streaming,
  #   otherwise long names exceed MySQL's 64-char limit (ERROR 1059). Content is streamed, never displayed.
  if err=$(sed -E "s/\`[0-9]{9,11}_${PREFIX}/\`${PREFIX}/g" "$f" \
      | mysql $LOCAL_MYSQL_ARGS --default-character-set=utf8mb4 \
          --init-command="SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'; SET SESSION FOREIGN_KEY_CHECKS=0;" \
          "$DB_NAME" 2>&1 >/dev/null); then ok=$((ok+1)); else
    failed+=("$(basename "$f")")
    printf '%s: %s\n' "$(basename "$f")" "$(printf '%s' "$err" | grep -o '^ERROR [0-9]* ([^)]*) at line [0-9]*' | head -1)" >> "$LOG"
  fi
done
note "imported OK: $ok   failed: ${#failed[@]}"
for f in "${failed[@]:-}"; do [[ -n "$f" ]] && note "  FAILED: $f"; done

# Backup Migration (BMI v4) may create tables under a temporary numeric prefix and rename on restore. Do that rename here.
tmp_tables=$(mysqlc -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name REGEXP '^[0-9]+_${PREFIX}'")
if [[ -n "$tmp_tables" ]]; then
  step "3b. Rename BMI temp-prefixed tables"
  while read -r t; do
    real="${t#*_}"
    mysqlc "$DB_NAME" -e "DROP TABLE IF EXISTS \`$real\`; RENAME TABLE \`$t\` TO \`$real\`;" && note "$t -> $real"
  done <<< "$tmp_tables"
fi
note "tables with live prefix: $(mysqlc -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name LIKE '${LIKE_PREFIX}%'")"
rm -f "$WP_PATH/.maintenance"; note "maintenance mode off"

# ---------------------------------------------------------------- 4. wp-config
step "4. wp-config: prefix + local constants"
wp config set table_prefix "$PREFIX" --type=variable --quiet
wp config set WP_ENVIRONMENT_TYPE local --type=constant --quiet
wp config set DISABLE_WP_CRON true --raw --type=constant --quiet
wp config set WP_CACHE false --raw --type=constant --quiet
wp config set TH_MAILPIT_PORT "$MAILPIT_SMTP_PORT" --raw --type=constant --quiet
wp config set WP_DEBUG true --raw --type=constant --quiet
wp config set WP_DEBUG_LOG true --raw --type=constant --quiet
wp config set WP_DEBUG_DISPLAY false --raw --type=constant --quiet
note "table_prefix=$PREFIX, WP_ENVIRONMENT_TYPE=local, DISABLE_WP_CRON, WP_CACHE=false, debug log on"

# ---------------------------------------------------------------- 5. search-replace
step "5. search-replace $LIVE_DOMAIN -> $LOCAL_HOST"
SR="wp search-replace --all-tables-with-prefix --precise --skip-plugins --skip-themes --report-changed-only --format=count"
for pair in \
  "https://www.$LIVE_DOMAIN|$LOCAL_URL" "http://www.$LIVE_DOMAIN|$LOCAL_URL" \
  "https://$LIVE_DOMAIN|$LOCAL_URL"     "http://$LIVE_DOMAIN|$LOCAL_URL" \
  "https:\\/\\/www.$LIVE_DOMAIN|${LOCAL_URL//\//\\/}" "https:\\/\\/$LIVE_DOMAIN|${LOCAL_URL//\//\\/}" \
  "//$LIVE_DOMAIN|//$LOCAL_HOST"; do
  from="${pair%%|*}"; to="${pair#*|}"
  n=$($SR "$from" "$to" 2>>"$LOG" || echo "ERR")
  note "$from -> $to : $n"
done

# ---------------------------------------------------------------- 6. Plugins that must not run locally
step "6. Record live active_plugins, then deactivate fluent-smtp"
# The safety mu-plugin filters fluent-smtp out of active_plugins at runtime, so edit the stored option directly.
wp eval --skip-plugins --skip-themes '
  remove_all_filters( "option_active_plugins" );
  $p = (array) get_option( "active_plugins", array() );
  if ( ! get_option( "th_live_active_plugins" ) ) { add_option( "th_live_active_plugins", $p, "", false ); }
  $n = array_values( array_diff( $p, array( "fluent-smtp/fluent-smtp.php" ) ) );
  update_option( "active_plugins", $n );
  echo count( $p ) . " live active plugins recorded in th_live_active_plugins; fluent-smtp " . ( count( $p ) !== count( $n ) ? "deactivated" : "was not active" ) . PHP_EOL;
' 2>&1 | tee -a "$LOG"

# ---------------------------------------------------------------- 7. Anonymise + detach
step "7. Anonymise personal data"
wp eval-file "$REPO_DIR/bin/anonymize.php" --skip-plugins --skip-themes 2>&1 | tee -a "$LOG"
step "8. Detach external services"
wp eval-file "$REPO_DIR/bin/detach-services.php" --skip-plugins --skip-themes 2>&1 | tee -a "$LOG"

# ---------------------------------------------------------------- 9. Local admin
step "9. Local admin 'dev'"
if wp user get dev --field=ID --skip-plugins --skip-themes >/dev/null 2>&1; then
  wp user update dev --user_pass=dev --role=administrator --skip-plugins --skip-themes --quiet && note "dev exists — password reset to 'dev'"
else
  wp user create dev dev@example.test --role=administrator --user_pass=dev --skip-plugins --skip-themes --quiet && note "dev / dev created"
fi

# ---------------------------------------------------------------- 10. Verify
step "10. Verify"
wp cache flush --quiet 2>/dev/null
wp rewrite flush --skip-themes --quiet 2>>"$LOG"
note "siteurl: $(wp option get siteurl --skip-plugins --skip-themes)"
note "home:    $(wp option get home --skip-plugins --skip-themes)"
note "theme:   $(wp option get stylesheet --skip-plugins --skip-themes) (template $(wp option get template --skip-plugins --skip-themes))"
code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 "$LOCAL_URL/" || echo "curl-failed")
note "GET $LOCAL_URL/ -> HTTP $code"

# ---------------------------------------------------------------- 11. Clean snapshot
step "11. Snapshot -> backup/local-clean.sql"
tables=$(mysqlc -N -e "SET SESSION group_concat_max_len=1000000; SELECT GROUP_CONCAT(table_name SEPARATOR ',') FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name LIKE '${LIKE_PREFIX}%'")
mysqldump $LOCAL_MYSQL_ARGS --default-character-set=utf8mb4 --single-transaction --skip-comments "$DB_NAME" ${tables//,/ } > "$BACKUP_DIR/local-clean.sql" && note "snapshot: $(du -h "$BACKUP_DIR/local-clean.sql" | cut -f1)"

step "Done. Log: backup/setup-local.log"
