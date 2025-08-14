<?php

// === JSON + DEV shim (put at the VERY top, no BOM) ===
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Read action from either 'action' or 'data'
$action = $_POST['action'] ?? $_GET['action'] ?? $_POST['data'] ?? $_GET['data'] ?? null;

// Simple ping for path checks (curl ?ping=1)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($_GET['ping'])) {
    echo json_encode(['ok' => true, 'pong' => true]);
    exit;
}

// --- DEV MODE: bypass OTP for demo ---
$DEV_MODE = true; // set false for production
if ($DEV_MODE) {
    if ($action === 'otp_request') {
        echo json_encode(['ok' => true, 'message' => 'otp_sent', 'dev' => true]);
        exit;
    }
    if ($action === 'otp_verify') {
        $username = preg_replace('/[^a-zA-Z0-9._-]/', '', (string)($_POST['username'] ?? $_GET['username'] ?? 'guest'));
        if ($username === '') $username = 'guest';
        $token = 'demo_' . $username . '_' . time();
        echo json_encode(['ok' => true, 'token' => $token, 'dev' => true]);
        exit;
    }
}


function json_out($a)
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($a, JSON_UNESCAPED_UNICODE);
    exit;
}
function db()
{
    $d = new mysqli(MYSQL_SERVERNAME, MYSQL_USERNAME, MYSQL_PASSWORD, MYSQL_DATABASE);
    if ($d->connect_errno) {
        http_response_code(500);
        json_out(["ok" => false, "error" => "db_connect"]);
    }
    $d->set_charset('utf8mb4');
    return $d;
}

// Safe prepare: logs SQL error and returns a JSON error instead of fatal
// Dev-friendly prepare wrapper: logs and returns JSON instead of fatals
function prep(mysqli $db, string $sql): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        $err = $db->error;
        error_log("[SQL PREPARE FAIL] $sql | $err");
        http_response_code(500);
        echo json_encode(["ok" => false, "error" => "server_sql_prepare_failed", "mysql" => $err]); // remove "mysql" in prod
        exit;
    }

    return $stmt;
}

function cfg($k, $def = null)
{
    $d = db();
    $s = prep($d, "SELECT value FROM config WHERE setting=? LIMIT 1");
    $s->bind_param("s", $k);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? $r['value'] : $def;
}
function client_ip()
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) if (!empty($_SERVER[$k])) return explode(',', $_SERVER[$k])[0];
    return '0.0.0.0';
}
function now()
{
    return (new DateTime('now', new DateTimeZone(APP_TIMEZONE)))->format('Y-m-d H:i:s');
}
function add_minutes($ts, $m)
{
    $d = new DateTime($ts, new DateTimeZone(APP_TIMEZONE));
    $d->modify("+$m minutes");
    return $d->format('Y-m-d H:i:s');
}
function add_hours($ts, $h)
{
    $d = new DateTime($ts, new DateTimeZone(APP_TIMEZONE));
    $d->modify("+$h hours");
    return $d->format('Y-m-d H:i:s');
}
function base64url($s)
{
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}

function send_otp_via_brevo($toEmail, $toName, $otp)
{
    $apiKey = cfg('brevo_api_key', '');
    if (!$apiKey) return [false, "missing_brevo_api_key"];
    $from = cfg('otp_email_from', 'no-reply@example.com');
    $fromNm = cfg('otp_email_from_name', 'Your App');
    $payload = [
        "sender" => ["email" => $from, "name" => $fromNm],
        "to" => [["email" => $toEmail, "name" => $toName ?: $toEmail]],
        "subject" => "Your One-Time Password",
        "textContent" => "Your OTP is: $otp\nValid for 10 minutes.",
        "htmlContent" => "<p>Your OTP is: <strong>$otp</strong></p><p>Valid for 10 minutes.</p>"
    ];
    $ch = curl_init("https://api.brevo.com/v3/smtp/email");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => ["Content-Type: application/json", "accept: application/json", "api-key: $apiKey"], CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($http >= 200 && $http < 300) return [true, null];
    return [false, "brevo_http_$http:$resp $err"];
}

function count_requests($db, $user_id, $ip, $minutes)
{
    $s = prep($db, "SELECT COUNT(*) c FROM otp_requests WHERE (user_id=? OR ip=?) AND requested_at >= (NOW() - INTERVAL ? MINUTE)");
    $s->bind_param("isi", $user_id, $ip, $minutes);
    $s->execute();
    return (int)($s->get_result()->fetch_assoc()['c'] ?? 0);
}
function secs_since_last_request($db, $user_id, $ip)
{
    $s = prep($db, "SELECT TIMESTAMPDIFF(SECOND, requested_at, NOW()) s FROM otp_requests WHERE (user_id=? OR ip=?) ORDER BY requested_at DESC LIMIT 1");
    $s->bind_param("is", $user_id, $ip);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    return $row ? (int)$row['s'] : 999999;
}
function require_auth_token($db, $token)
{
    if (!$token) return [false, "missing"];
    $s = prep($db, "SELECT user_id FROM auth_tokens WHERE token=? AND revoked_at IS NULL AND expires_at>=NOW() LIMIT 1");
    $s->bind_param("s", $token);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? [true, (int)$r['user_id']] : [false, "invalid"];
}

function is_blocked(mysqli $db, int $user_id, string $ip)
{
    $s = prep($db, "SELECT TIMESTAMPDIFF(HOUR, NOW(), blocked_until) h
                   FROM login_blocks
                   WHERE (user_id=? OR ip=?) AND blocked_until >= NOW()
                   ORDER BY blocked_until DESC LIMIT 1");
    $s->bind_param("is", $user_id, $ip);
    $s->execute();
    $r = $s->get_result()->fetch_assoc();
    return $r ? max(1, (int)$r['h']) : 0; // hours remaining (rounded >=1)
}
function set_block(mysqli $db, int $user_id, string $ip, int $hours)
{
    // upsert; extend block if a longer one already exists
    $s = prep($db, "INSERT INTO login_blocks(user_id, ip, blocked_until)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR))
                    ON DUPLICATE KEY UPDATE blocked_until = GREATEST(blocked_until, VALUES(blocked_until))");
    $s->bind_param("isi", $user_id, $ip, $hours);
    $s->execute();
}


$action = $_GET['action'] ?? $_POST['action'] ?? null;
// === DEV MODE shortcut for otp_request / otp_verify ===
if (!headers_sent() && isset($DEV_MODE) && $DEV_MODE) {
    if ($action === 'otp_request') {
        // Pretend the OTP was sent successfully
        echo json_encode(['ok' => true, 'message' => 'otp_sent', 'dev' => true]);
        exit;
    }
    if ($action === 'otp_verify') {
        // Issue a demo token without checking any email/OTP
        $username = trim((string)($_POST['username'] ?? 'guest'));
        if ($username === '') $username = 'guest';
        $username = preg_replace('/[^a-zA-Z0-9._-]/', '', $username);

        $db = db();

        // Ensure the user exists (idempotent)
        $s = prep($db, "SELECT id FROM users WHERE username=? LIMIT 1");
        $s->bind_param("s", $username);
        $s->execute();
        $uid = ($s->get_result()->fetch_assoc()['id'] ?? null);
        if (!$uid) {
            $email = $username . "@example.test";
            $disp  = $username;
            $s = prep($db, "INSERT INTO users(username,email,display_name,created_at) VALUES (?,?,?,NOW())");
            $s->bind_param("sss", $username, $email, $disp);
            $s->execute();
            $uid = $db->insert_id;
        }

        // Create a token
        $token   = base64url(random_bytes(32));
        $created = now();
        $expires = add_hours($created, (int)cfg('token_ttl_hours', '24'));
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
        $ip = client_ip();

        $s = prep($db, "INSERT INTO auth_tokens(user_id,token,ip,user_agent,created_at,expires_at) VALUES (?,?,?,?,?,?)");
        $s->bind_param("isssss", $uid, $token, $ip, $ua, $created, $expires);
        $s->execute();

        // Also set cookie like the real flow
        setcookie('auth_token', $token, [
            'expires'  => strtotime($expires),
            'path'     => '/',
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']),
        ]);

        echo json_encode(['ok' => true, 'token' => $token, 'expires_at' => $expires, 'dev' => true, 'username' => $username]);
        exit;
    }
}
// === end of DEV MODE ===

if ($action === 'login') {
}
if ($action === 'otp_request') {
    if (!empty($_POST['hp'])) json_out(["ok" => false, "error" => "bot"]);
    $username = trim($_POST['username'] ?? '');
    if ($username === '') json_out(["ok" => false, "error" => "missing_username"]);
    $db = db();
    $s = prep($db, "SELECT id, username, email FROM users WHERE username=? LIMIT 1");
    $s->bind_param("s", $username);
    $s->execute();
    $u = $s->get_result()->fetch_assoc();
    if (!$u || empty($u['email'])) json_out(["ok" => false, "error" => "user_not_found"]);
    $user_id = (int)$u['id'];
    $ip = client_ip();

    $limitFails = (int)cfg('login_block_after_failed_per_hour', '10');
    $blockHours = (int)cfg('login_block_hours', '24');
    $s = prep($db, "SELECT COUNT(*) c FROM login_attempts WHERE (user_id=? OR ip=?) AND success=0 AND created_at >= (NOW() - INTERVAL 1 HOUR)");
    $s->bind_param("is", $user_id, $ip);
    $s->execute();
    if (((int)$s->get_result()->fetch_assoc()['c']) >= $limitFails) json_out(["ok" => false, "error" => "blocked", "retry_after_hours" => $blockHours]);

    $cooldown = (int)cfg('otp_cooldown_seconds', '30');
    if (secs_since_last_request($db, $user_id, $ip) < $cooldown) json_out(["ok" => false, "error" => "cooldown", "cooldown_seconds" => $cooldown]);
    $maxHour = (int)cfg('otp_max_per_hour', '4');
    $maxDay = (int)cfg('otp_max_per_day', '10');
    if (count_requests($db, $user_id, $ip, 60)   >= $maxHour) json_out(["ok" => false, "error" => "rate_hour"]);
    if (count_requests($db, $user_id, $ip, 60 * 24) >= $maxDay)  json_out(["ok" => false, "error" => "rate_day"]);

    $len = max(4, (int)cfg('otp_length', '6'));
    $otp = str_pad((string)random_int(0, 10 ** $len - 1), $len, '0', STR_PAD_LEFT);
    $secret = cfg('otp_secret', 'change-me');
    $created = now();
    $expires = add_minutes($created, (int)cfg('otp_valid_minutes', '10'));
    $hash = hash('sha256', $otp . ':' . $secret . ':' . $user_id . ':' . $created);

    $s = prep($db, "INSERT INTO otp_requests(user_id,ip,requested_at) VALUES (?,?,NOW())");
    $s->bind_param("is", $user_id, $ip);
    $s->execute();
    $s = prep($db, "INSERT INTO otp_codes(user_id,otp_hash,created_at,expires_at,ip) VALUES (?,?,?,?,?)");
    $s->bind_param("issss", $user_id, $hash, $created, $expires, $ip);
    $s->execute();

    [$ok, $err] = send_otp_via_brevo($u['email'], $u['username'] ?? $u['username'], $otp);
    if (!$ok) json_out(["ok" => false, "error" => "email_failed", "details" => $err]);

    json_out(["ok" => true, "message" => "otp_sent", "cooldown_seconds" => $cooldown]);
    exit;
}

if ($action === 'otp_verify') {
    $username = trim($_POST['username'] ?? '');
    $otp = trim($_POST['otp_code'] ?? ($_POST['otp'] ?? ''));
    if ($username === '' || $otp === '') json_out(["ok" => false, "error" => "missing_fields"]);
    $db = db();
    $s = prep($db, "SELECT id FROM users WHERE username=? LIMIT 1");
    $s->bind_param("s", $username);
    $s->execute();
    $u = $s->get_result()->fetch_assoc();
    if (!$u) json_out(["ok" => false, "error" => "user_not_found"]);
    $user_id = (int)$u['id'];

    $s = prep($db, "SELECT id,otp_hash,created_at FROM otp_codes WHERE user_id=? AND used_at IS NULL AND expires_at>=NOW() ORDER BY id DESC LIMIT 1");
    $s->bind_param("i", $user_id);
    $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row) {
        $db->query("INSERT INTO login_attempts(user_id,ip,success,created_at) VALUES ($user_id,'" . $db->real_escape_string(client_ip()) . "',0,NOW())");
        json_out(["ok" => false, "error" => "otp_expired_or_missing"]);
    }

    $calc = hash('sha256', $otp . ':' . cfg('otp_secret', 'change-me') . ':' . $user_id . ':' . $row['created_at']);
    if (!hash_equals($row['otp_hash'], $calc)) {
        $db->query("INSERT INTO login_attempts(user_id,ip,success,created_at) VALUES ($user_id,'" . $db->real_escape_string(client_ip()) . "',0,NOW())");
        json_out(["ok" => false, "error" => "otp_invalid"]);
    }

    $s = prep($db, "UPDATE otp_codes SET used_at=NOW() WHERE id=?");
    $s->bind_param("i", $row['id']);
    $s->execute();

    $token = base64url(random_bytes(32));
    $created = now();
    $expires = add_hours($created, (int)cfg('token_ttl_hours', '24'));
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
    $ip = client_ip();
    $s = prep($db, "INSERT INTO auth_tokens(user_id,token,ip,user_agent,created_at,expires_at) VALUES (?,?,?,?,?,?)");
    $s->bind_param("isssss", $user_id, $token, $ip, $ua, $created, $expires);
    $s->execute();

    setcookie('auth_token', $token, [
        'expires'  => strtotime($expires),
        'path'     => '/',
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);

    $db->query("INSERT INTO login_attempts(user_id,ip,success,created_at) VALUES ($user_id,'" . $db->real_escape_string($ip) . "',1,NOW())");
    json_out(["ok" => true, "token" => $token, "expires_at" => $expires]);
    exit;
}

if ($action === 'logout') {
    $token = $_POST['token'] ?? '';
    if ($token === '') json_out(["ok" => false, "error" => "missing_token"]);
    $db = db();
    $s = prep($db, "UPDATE auth_tokens SET revoked_at=NOW() WHERE token=?");
    $s->bind_param("s", $token);
    $s->execute();
    json_out(["ok" => true]);
    exit;
}

echo json_encode(["ok" => false, "error" => "unknown_action"]);
exit;
