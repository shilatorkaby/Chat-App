#!/usr/bin/env bash
set -euo pipefail

: "${MYSQL_HOST:=db}"
: "${MYSQL_PORT:=3306}"
: "${MYSQL_USER:=root}"
: "${MYSQL_PASSWORD:=root}"
: "${MYSQL_DATABASE:=home_test}"

echo "➡ Waiting for MySQL at ${MYSQL_HOST}:${MYSQL_PORT}..."
until mysqladmin ping -h"${MYSQL_HOST}" -P"${MYSQL_PORT}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" --silent; do
  sleep 1
done
echo "MySQL is up"

mysql -h"${MYSQL_HOST}" -P"${MYSQL_PORT}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" \
  -e "CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "➡ Applying SQL migrations (idempotent)"
shopt -s nullglob
for f in /app/sql/*.sql; do
  echo "   - $f"
  mysql -h"${MYSQL_HOST}" -P"${MYSQL_PORT}" -u"${MYSQL_USER}" -p"${MYSQL_PASSWORD}" "${MYSQL_DATABASE}" < "$f"
done

echo "➡ Seeding/Updating config from ENV via tools/setup.ps1"
php /var/www/html/tools/setup.php \
  --base-url="${BASE_URL:-http://localhost:8080/}" \
  --brevo-key="${BREVO_API_KEY:-}" \
  --email-from="${BREVO_FROM_EMAIL:-}" \
  --email-name="${BREVO_FROM_NAME:-}" \
  --otp-len="${OTP_LEN:-6}" \
  --otp-valid="${OTP_VALID:-10}" \
  --otp-cooldown="${OTP_COOLDOWN:-30}" \
  --otp-hour="${OTP_HOUR:-4}" \
  --otp-day="${OTP_DAY:-10}" \
  --token-ttl="${TOKEN_TTL:-24}" \
  --block-fails="${BLOCK_FAILS_PER_HOUR:-10}" \
  --block-hours="${BLOCK_HOURS:-24}" || true

echo "➡ Starting Apache"
exec apache2-foreground
