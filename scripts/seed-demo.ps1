param(
  [string]$DbName = "home_test",
  [string]$Username = "demo",
  [string]$Email = "you@example.com",
  [string]$DisplayName = "Demo User"
)
$ErrorActionPreference = "Stop"
$cols = (docker compose exec -T db mysql -uroot -proot -N -e "SELECT GROUP_CONCAT(COLUMN_NAME) FROM information_schema.columns WHERE table_schema='$DbName' AND table_name='users'").Trim()
if (-not $cols) { Write-Error "Can't read users columns"; exit 1 }
$hasUsername = $cols -match '(?i)\busername\b'
$hasEmail    = $cols -match '(?i)\bemail\b'
$hasDisplay  = $cols -match '(?i)\bdisplay_name\b'
$hasName     = $cols -match '(?i)\bname\b'
if (-not ($hasUsername -and $hasEmail)) { Write-Error "users table must have username & email. Found: $cols"; exit 1 }
$u=$Username.Replace("'","''"); $e=$Email.Replace("'","''"); $d=$DisplayName.Replace("'","''")
if ($hasDisplay) {
  $insert="INSERT INTO users(username,email,display_name) VALUES('$u','$e','$d') ON DUPLICATE KEY UPDATE email=VALUES(email), display_name=VALUES(display_name);"
} elseif ($hasName) {
  $insert="INSERT INTO users(username,email,name) VALUES('$u','$e','$d') ON DUPLICATE KEY UPDATE email=VALUES(email), name=VALUES(name);"
} else {
  $insert="INSERT INTO users(username,email) VALUES('$u','$e') ON DUPLICATE KEY UPDATE email=VALUES(email);"
}
$cmd="USE $DbName; $insert SELECT id,username,email FROM users WHERE username='$u' LIMIT 1;"
docker compose exec -T db mysql -uroot -proot -e "$cmd"
Write-Host "✅ Seeded/updated user: $Username <$Email>"
