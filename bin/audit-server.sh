#!/usr/bin/env bash
#
# 3AM — Phase 0 Server Audit
# ==========================
# STRICTLY READ-ONLY. This script does not create, modify, move, or delete
# anything on the server except its own output file under /tmp.
#
# Purpose: capture everything the deployment design depends on —
#   - web server, vhosts, document roots
#   - the COMPLETE legacy application inventory (/armonyx, /trebl, ...)
#   - PHP version, SAPI, pools, extensions, session config
#   - MariaDB version, schemas, users, sizes
#   - existing backups (or the absence of them)
#
# Usage:
#     sudo bash audit-server.sh
#
# Then send back the generated report file.

set -uo pipefail

OUT="/tmp/3am-server-audit-$(date +%Y%m%d-%H%M%S).txt"

# Everything below is echoed to both stdout and the report.
exec > >(tee "$OUT") 2>&1

hr()  { printf '\n%s\n' "────────────────────────────────────────────────────────────"; }
sec() { hr; printf '## %s\n\n' "$1"; }
try() { "$@" 2>&1 || echo "  (unavailable: $*)"; }

echo "3AM SERVER AUDIT — $(date -Is)"
echo "host: $(hostname)  user: $(whoami)"
echo "READ-ONLY. No changes are made by this script."

# ─────────────────────────────────────────────────────────────
sec "1. SYSTEM"
try cat /etc/os-release
echo
try uname -a
echo
echo "uptime: $(uptime -p 2>/dev/null || uptime)"
echo
echo "-- memory --"
try free -h
echo
echo "-- disk --"
try df -hT
echo
echo "-- CPU --"
grep -c ^processor /proc/cpuinfo 2>/dev/null | sed 's/^/cores: /'
echo
echo "-- SELinux --"
try getenforce

# ─────────────────────────────────────────────────────────────
sec "2. WEB SERVER"
for svc in nginx httpd apache2 caddy; do
  if command -v "$svc" >/dev/null 2>&1; then
    echo "FOUND: $svc -> $(command -v "$svc")"
    case "$svc" in
      nginx)          try nginx -v; echo; echo "-- config test --"; try nginx -t ;;
      httpd|apache2)  try "$svc" -v; echo; echo "-- loaded modules --"; try "$svc" -M ;;
    esac
    echo
  fi
done

echo "-- running web/php services --"
try systemctl list-units --type=service --state=running --no-pager --no-legend \
  | grep -Ei 'nginx|httpd|apache|php|caddy|mariadb|mysql'

echo
echo "-- listening ports --"
try ss -tlnp

# ─────────────────────────────────────────────────────────────
sec "3. VHOST CONFIGURATION  (the deployment design depends on this)"
for d in /etc/nginx/conf.d /etc/nginx/sites-enabled /etc/nginx/sites-available \
         /etc/httpd/conf.d /etc/httpd/conf /etc/apache2/sites-enabled; do
  if [ -d "$d" ]; then
    echo "=== $d ==="
    ls -la "$d"
    echo
    # Print the actual config — this is what we need to write the fallthrough rules.
    find "$d" -maxdepth 1 -type f \( -name '*.conf' -o -name '*.vhost' \) 2>/dev/null | while read -r f; do
      echo "----- FILE: $f -----"
      grep -vE '^\s*(#|$)' "$f"
      echo
    done
  fi
done

echo "-- main nginx.conf (non-comment lines) --"
[ -f /etc/nginx/nginx.conf ] && grep -vE '^\s*(#|$)' /etc/nginx/nginx.conf

echo
echo "-- declared document roots --"
try grep -rhoE '^\s*(root|DocumentRoot)\s+[^;]+' \
  /etc/nginx /etc/httpd /etc/apache2 2>/dev/null | sort -u

# ─────────────────────────────────────────────────────────────
sec "4. LEGACY APPLICATION INVENTORY  ★ CRITICAL ★"
echo "Every directory listed here becomes a RESERVED PATH that the new site"
echo "must never route, and that must appear in the smoke-test baseline."
echo
for root in /var/www/html /var/www /usr/share/nginx/html /srv/www /home/*/public_html; do
  if [ -d "$root" ]; then
    echo "=== DOCROOT CANDIDATE: $root ==="
    ls -la "$root"
    echo
    echo "-- top-level directories (candidate app paths) --"
    find "$root" -maxdepth 1 -mindepth 1 -type d -printf '  /%f\n' 2>/dev/null | sort
    echo
    echo "-- per-app detail --"
    find "$root" -maxdepth 1 -mindepth 1 -type d 2>/dev/null | while read -r app; do
      name=$(basename "$app")
      size=$(du -sh "$app" 2>/dev/null | cut -f1)
      echo "  /$name  (size: ${size:-?})"
      # framework fingerprint
      [ -f "$app/composer.json" ] && echo "      composer: $(grep -oE '"(laravel/framework|codeigniter4?/framework|symfony/symfony)"[^,]*' "$app/composer.json" 2>/dev/null | head -3 | tr '\n' ' ')"
      [ -d "$app/system/core" ]   && echo "      framework: CodeIgniter 3 (system/core present)"
      [ -f "$app/artisan" ]       && echo "      framework: Laravel (artisan present)"
      [ -f "$app/wp-config.php" ] && echo "      framework: WordPress"
      [ -f "$app/.htaccess" ]     && echo "      has .htaccess"
      [ -f "$app/index.php" ]     && echo "      has index.php"
      # which DB does it talk to? (names only — NO credential values printed)
      for cfg in "$app/.env" "$app/application/config/database.php" "$app/config/database.php"; do
        if [ -f "$cfg" ]; then
          dbn=$(grep -ioE "(DB_DATABASE|'database')\s*[=>]+\s*['\"]?([A-Za-z0-9_]+)" "$cfg" 2>/dev/null | head -1 | grep -oE '[A-Za-z0-9_]+$')
          [ -n "${dbn:-}" ] && echo "      db schema: $dbn   (from $(basename "$cfg"))"
        fi
      done
    done
    echo
  fi
done

echo "-- existing robots.txt / sitemap.xml (must not be clobbered) --"
for root in /var/www/html /usr/share/nginx/html; do
  for f in robots.txt sitemap.xml; do
    if [ -f "$root/$f" ]; then
      echo "=== $root/$f ==="
      cat "$root/$f"
      echo
    fi
  done
done

# ─────────────────────────────────────────────────────────────
sec "5. PHP"
try php -v
echo
echo "-- SAPI in use by the web server --"
try systemctl status php-fpm --no-pager -l 2>/dev/null | head -5
try ls -la /etc/php-fpm.d/ /etc/php/*/fpm/pool.d/ 2>/dev/null
echo
echo "-- FPM pools (user, listen, php_admin_value overrides) --"
for p in /etc/php-fpm.d/*.conf /etc/php/*/fpm/pool.d/*.conf; do
  [ -f "$p" ] || continue
  echo "----- $p -----"
  grep -vE '^\s*(;|$)' "$p"
  echo
done

echo "-- loaded extensions --"
try php -m
echo
echo "-- REQUIRED EXTENSION CHECK --"
for ext in pdo pdo_mysql mbstring json curl openssl fileinfo exif intl zip gd imagick opcache; do
  if php -m 2>/dev/null | grep -qix "$ext"; then
    echo "  [ok]      $ext"
  else
    echo "  [MISSING] $ext"
  fi
done
echo
echo "  NOTE: imagick OR gd is required for the media derivative pipeline."
echo "        imagick is strongly preferred (AVIF support, EXIF stripping)."

echo
echo "-- key ini settings --"
try php -i 2>/dev/null | grep -E '^(memory_limit|upload_max_filesize|post_max_size|max_execution_time|max_file_uploads|disable_functions|display_errors|expose_php|date.timezone|open_basedir) '

echo
echo "-- ★ SESSION CONFIG (collision hazard with legacy apps) ★ --"
try php -i 2>/dev/null | grep -E '^(session\.name|session\.save_path|session\.save_handler|session\.cookie_path|session\.cookie_secure|session\.cookie_httponly|session\.cookie_samesite|session\.gc_maxlifetime) '
echo
echo "  If session.name is PHPSESSID at cookie_path '/', the new site MUST use"
echo "  a distinct session name and its own save_path, or logins to the legacy"
echo "  apps will be clobbered unpredictably."

echo
echo "-- opcache --"
try php -i 2>/dev/null | grep -E '^opcache\.(enable|memory_consumption|max_accelerated_files|validate_timestamps|preload) '

echo
echo "-- other PHP versions installed (for a separate pool, if needed) --"
try ls -1 /usr/bin/php* /opt/remi/*/root/usr/bin/php 2>/dev/null

# ─────────────────────────────────────────────────────────────
sec "6. COMPOSER / TOOLING"
for t in composer git node npm rsync certbot aws; do
  if command -v "$t" >/dev/null 2>&1; then
    printf '  [ok]      %-9s %s\n' "$t" "$($t --version 2>&1 | head -1)"
  else
    printf '  [absent]  %s\n' "$t"
  fi
done

# ─────────────────────────────────────────────────────────────
sec "7. MARIADB"
try mariadb -V
echo
echo "-- server status --"
try systemctl status mariadb --no-pager -l 2>/dev/null | head -8
echo
echo "NOTE: the queries below need DB access. If this runs as root with a unix"
echo "      socket auth plugin they will just work; otherwise re-run with:"
echo "        mariadb -u root -p < <(...)  or supply credentials interactively."
echo

if mariadb -e "SELECT 1" >/dev/null 2>&1; then
  echo "-- schemas and sizes --"
  mariadb -e "
    SELECT table_schema AS 'schema',
           COUNT(*) AS tables,
           ROUND(SUM(data_length+index_length)/1024/1024,1) AS mb
    FROM information_schema.tables
    WHERE table_schema NOT IN ('information_schema','performance_schema','mysql','sys')
    GROUP BY table_schema ORDER BY mb DESC;"
  echo
  echo "-- users and hosts (no password hashes printed) --"
  mariadb -e "SELECT user, host, plugin FROM mysql.user ORDER BY user;"
  echo
  echo "-- storage engines and collations in use --"
  mariadb -e "
    SELECT engine, table_collation, COUNT(*) AS tables
    FROM information_schema.tables
    WHERE table_schema NOT IN ('information_schema','performance_schema','mysql','sys')
    GROUP BY engine, table_collation;"
  echo
  echo "-- server defaults --"
  mariadb -e "SHOW VARIABLES WHERE Variable_name IN
    ('version','character_set_server','collation_server','max_connections',
     'innodb_buffer_pool_size','datadir','sql_mode','slow_query_log');"
  echo
  echo "-- is 'threeam' already taken? --"
  mariadb -e "SELECT schema_name FROM information_schema.schemata WHERE schema_name='threeam';"
else
  echo "  (could not connect to MariaDB as $(whoami) — run the queries manually)"
fi

# ─────────────────────────────────────────────────────────────
sec "8. BACKUPS  ★ CRITICAL — expected to find NOTHING ★"
echo "-- cron jobs --"
try crontab -l
echo
try ls -la /etc/cron.d/ /etc/cron.daily/ 2>/dev/null
echo
try grep -rl --include='*' -iE 'mysqldump|mariadb-dump|backup' /etc/cron* 2>/dev/null
echo
echo "-- systemd timers --"
try systemctl list-timers --all --no-pager
echo
echo "-- any dump files lying around --"
try find /var /home /opt /backup -maxdepth 3 \( -name '*.sql' -o -name '*.sql.gz' -o -name '*dump*' \) \
  -type f -printf '%TY-%Tm-%Td  %10s  %p\n' 2>/dev/null | head -20
echo
echo "  If this section is empty, the database has NO automated backup."
echo "  That is a launch blocker and is addressed first in Phase 0."

# ─────────────────────────────────────────────────────────────
sec "9. TLS / CERTIFICATES"
try certbot certificates 2>/dev/null
try ls -la /etc/letsencrypt/live/ 2>/dev/null
try find /etc/nginx /etc/httpd /etc/apache2 -name '*.conf' -exec grep -l 'ssl_certificate\|SSLCertificateFile' {} \; 2>/dev/null

# ─────────────────────────────────────────────────────────────
sec "10. AWS INSTANCE METADATA"
TOK=$(curl -s -X PUT "http://169.254.169.254/latest/api/token" \
      -H "X-aws-ec2-metadata-token-ttl-seconds: 60" --max-time 2 2>/dev/null)
if [ -n "${TOK:-}" ]; then
  for k in instance-id instance-type placement/availability-zone ami-id; do
    printf '  %-32s %s\n' "$k" \
      "$(curl -s -H "X-aws-ec2-metadata-token: $TOK" --max-time 2 \
         "http://169.254.169.254/latest/meta-data/$k" 2>/dev/null)"
  done
  echo
  echo "  IAM role attached:"
  curl -s -H "X-aws-ec2-metadata-token: $TOK" --max-time 2 \
    "http://169.254.169.254/latest/meta-data/iam/security-credentials/" 2>/dev/null | sed 's/^/    /'
  echo
else
  echo "  (IMDS unavailable — not EC2, or IMDSv2 blocked)"
fi

hr
echo
echo "AUDIT COMPLETE"
echo "Report written to: $OUT"
echo
echo "Please send back that file. The items that gate the build are:"
echo "  §4  complete legacy app inventory  -> reserved paths + smoke-test baseline"
echo "  §3  vhost config                   -> path-fallthrough rules"
echo "  §5  imagick/gd + session config     -> media pipeline + session isolation"
echo "  §8  backups                         -> Phase 0 launch blocker"
