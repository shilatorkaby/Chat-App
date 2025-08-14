param(
  [string]$BaseUrl        = $env:BASE_URL,
  [string]$BrevoKey       = $env:BREVO_API_KEY,
  [string]$EmailFrom      = $env:BREVO_FROM_EMAIL,
  [string]$EmailName      = $env:BREVO_FROM_NAME,
  [int]$OtpLen            = $(if ($env:OTP_LEN) { [int]$env:OTP_LEN } else { 6 }),
  [int]$OtpValidMin       = $(if ($env:OTP_VALID) { [int]$env:OTP_VALID } else { 10 }),
  [int]$OtpCooldown       = $(if ($env:OTP_COOLDOWN) { [int]$env:OTP_COOLDOWN } else { 30 }),
  [int]$OtpPerHour        = $(if ($env:OTP_HOUR) { [int]$env:OTP_HOUR } else { 4 }),
  [int]$OtpPerDay         = $(if ($env:OTP_DAY) { [int]$env:OTP_DAY } else { 10 }),
  [int]$TokenTtlHours     = $(if ($env:TOKEN_TTL) { [int]$env:TOKEN_TTL } else { 24 }),
  [int]$BlockFailsHour    = $(if ($env:BLOCK_FAILS_HOUR) { [int]$env:BLOCK_FAILS_HOUR } else { 10 }),
  [int]$BlockHours        = $(if ($env:BLOCK_HOURS) { [int]$env:BLOCK_HOURS } else { 24 }),
  [switch]$ResetDb
)

$ErrorActionPreference = "Stop"

function Invoke-MySql([string]$Sql) {
  docker compose exec -T db mysql -uroot -proot -D home_test -e $Sql
}

Write-Host "➡ Bringing stack up"
docker compose down
docker compose build web
docker compose up -d

# Wait for DB to be healthy
Write-Host "➡ Waiting for MySQL health..."
$maxWait = 60
$ok = $false
for ($i=0; $i -lt $maxWait; $i++) {
  try {
    docker compose exec -T db sh -lc "mysqladmin ping -proot >/dev/null 2>&1" | Out-Null
    $ok = $true
    break
  } catch {
    Start-Sleep -Seconds 1
  }
}
if (-not $ok) { throw "MySQL did not become healthy in time." }

if ($ResetDb) {
  Write-Host "➡ Resetting DB home_test"
  docker compose exec -T db mysql -uroot -proot -e "DROP DATABASE IF EXISTS home_test; CREATE DATABASE home_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
}

# Import schema: prefer ./sql/schema.sql
$schemaLocal = Join-Path $PSScriptRoot "sql\schema.sql"
if (Test-Path $schemaLocal) {
  Write-Host "➡ Importing schema from sql/schema.sql"
  docker compose cp "$schemaLocal" db:/schema.sql
  docker compose exec -T db sh -lc "mysql -uroot -proot home_test < /schema.sql"
} else {
  Write-Warning "No schema file found. Skipping import."
}
# ---- SAFE CONFIG SEEDING (no -e quoting) ----
function Esc([string]$s) { if ($null -eq $s) { return "" } else { return $s.Replace("'", "''") } }

$BaseUrlEsc     = Esc($BaseUrl)
$BrevoKeyEsc    = Esc($BrevoKey)
$EmailFromEsc   = Esc($EmailFrom)
$EmailNameEsc   = Esc($EmailName)

# Ensure table exists (in case schema didn't create it)
$schemaConfig = @"
CREATE TABLE IF NOT EXISTS config(
  id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, 
  setting  VARCHAR(191) NOT NULL UNIQUE,
  value    TEXT NOT NULL,
  comments TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
"@
$tmpSchema = Join-Path $env:TEMP "config_schema.sql"
$schemaConfig | Out-File -FilePath $tmpSchema -Encoding UTF8

$seedSql = @"
INSERT INTO config (setting,value) VALUES
  ('base_url',             '$BaseUrlEsc'),
  ('brevo_api_key',        '$BrevoKeyEsc'),
  ('email_from',           '$EmailFromEsc'),
  ('email_name',           '$EmailNameEsc'),
  ('otp_len',              '$OtpLen'),
  ('otp_valid_minutes',    '$OtpValidMin'),
  ('otp_cooldown_seconds', '$OtpCooldown'),
  ('otp_limit_hour',       '$OtpPerHour'),
  ('otp_limit_day',        '$OtpPerDay'),
  ('token_ttl_hours',      '$TokenTtlHours'),
  ('block_fails_per_hour', '$BlockFailsHour'),
  ('block_hours',          '$BlockHours')
ON DUPLICATE KEY UPDATE value=VALUES(value);

SELECT setting, value FROM config ORDER BY setting;
"@

Write-Host "➡ Creating/ensuring config table"
docker compose cp "$tmpSchema" db:/config_schema.sql
docker compose exec -T db sh -lc "mysql -uroot -proot home_test < /config_schema.sql"

Write-Host "➡ Seeding config values"
docker compose exec -T db sh -lc "mysql -uroot -proot home_test < /config_seed.sql"

Write-Host "➡ Config now:"
docker compose exec -T db mysql -uroot -proot -D home_test -e "SELECT setting,value FROM config ORDER BY setting;"

