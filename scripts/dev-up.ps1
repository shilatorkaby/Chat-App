param(
  [string]$BaseUrl = "http://localhost:8080/",
  [string]$BrevoKey = "",
  [string]$EmailFrom = "no-reply@example.com",
  [string]$EmailName = "Your App",
  [int]$OtpLen = 6,
  [int]$OtpValidMin = 10,
  [int]$OtpCooldown = 30,
  [int]$OtpPerHour = 4,  # שנה ל-6 לפי הצורך
  [int]$OtpPerDay = 10,  # שנה ל-12 לפי הצורך
  [int]$TokenTtlHours = 24,
  [int]$BlockFailsHour = 10,
  [int]$BlockHours = 24,
  [switch]$ResetDb
)

$ErrorActionPreference = "Stop"

Write-Host "➡ build & up"
docker compose down
docker compose build --no-cache web
docker compose up -d

if ($ResetDb) {
  Write-Host "➡ reset DB"
  docker compose exec -T db mysql -uroot -proot -e "DROP DATABASE IF EXISTS home_test; CREATE DATABASE home_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
}

Write-Host "➡ import mysql.sql"
docker compose cp mysql.sql db:/mysql.sql
docker compose exec -T db sh -lc "mysql -uroot -proot home_test < /mysql.sql"

Write-Host "➡ tools/setup.php"
$cmd = @(
  "php","tools/setup.php",
  "--base-url=$BaseUrl",
  "--brevo-key=$BrevoKey",
  "--email-from=$EmailFrom",
  "--email-name=$EmailName",
  "--otp-len=$OtpLen",
  "--otp-valid=$OtpValidMin",
  "--otp-cooldown=$OtpCooldown",
  "--otp-hour=$OtpPerHour",
  "--otp-day=$OtpPerDay",
  "--token-ttl=$TokenTtlHours",
  "--block-fails=$BlockFailsHour",
  "--block-hours=$BlockHours"
) -join " "
docker compose exec web bash -lc "$cmd"

Write-Host "✅ Done. Open $BaseUrl"
