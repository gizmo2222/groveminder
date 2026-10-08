<?php
// ─────────────────────────────────────────────────────────────────────────────
// hp-lib.php — shared helpers for the PHP endpoints. Not an endpoint itself.
// ─────────────────────────────────────────────────────────────────────────────

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

// ── Site config ───────────────────────────────────────────────────────────────
// Reads the few values the server needs straight out of site-config.js, so that
// file stays the only one to edit for a new site.
function hp_site_config() {
    static $cfg = null;
    if ($cfg !== null) return $cfg;
    $js  = @file_get_contents(__DIR__ . '/site-config.js') ?: '';
    $get = function ($key) use ($js) {
        return preg_match('/\b' . $key . ':\s*"([^"]*)"/', $js, $m) ? $m[1] : '';
    };
    return $cfg = [
        'projectId' => $get('projectId'),
        'siteName'  => $get('siteName'),
    ];
}

// ── Responses / requests ──────────────────────────────────────────────────────
function hp_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

function hp_require_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') hp_json(['ok' => false, 'error' => 'Method not allowed'], 405);
}

function hp_input() {
    $raw = file_get_contents('php://input', false, null, 0, 65536);
    $in  = json_decode($raw, true);
    if (!is_array($in)) hp_json(['ok' => false, 'error' => 'Invalid JSON'], 400);
    return $in;
}

// Strip CR/LF so a value can't add extra headers to an email
function hp_one_line($s, $max = 200) {
    $s = preg_replace('/[\r\n\t\x00-\x1F\x7F]+/', ' ', (string)$s);
    return mb_substr(trim($s), 0, $max);
}

// Keep newlines, drop other control characters
function hp_multi_line($s, $max = 2000) {
    $s = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/', '', str_replace("\r\n", "\n", (string)$s));
    return mb_substr(trim($s), 0, $max);
}

// The mail domain comes from the server's own name, never the client's Host header
function hp_mail_from() {
    $host = preg_replace('/[^a-z0-9.-]/i', '', $_SERVER['SERVER_NAME'] ?? '');
    $host = preg_replace('/^www\./i', '', $host) ?: 'localhost';
    return 'noreply@' . $host;
}

// ── HTTP ──────────────────────────────────────────────────────────────────────
function hp_http($method, $url, $headers = [], $body = null, $timeout = 10) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp === false ? '' : $resp];
}

// ── Firestore (REST) ──────────────────────────────────────────────────────────
// Turns Firestore's typed JSON ({"stringValue": "x"}, ...) into plain PHP values
function hp_fs_value($v) {
    if (!is_array($v)) return null;
    if (array_key_exists('stringValue',  $v)) return $v['stringValue'];
    if (array_key_exists('integerValue', $v)) return (int)$v['integerValue'];
    if (array_key_exists('doubleValue',  $v)) return (float)$v['doubleValue'];
    if (array_key_exists('booleanValue', $v)) return (bool)$v['booleanValue'];
    if (array_key_exists('nullValue',    $v)) return null;
    if (array_key_exists('timestampValue', $v)) return $v['timestampValue'];
    if (array_key_exists('arrayValue',   $v)) return array_map('hp_fs_value', $v['arrayValue']['values'] ?? []);
    if (array_key_exists('mapValue',     $v)) return hp_fs_fields($v['mapValue']['fields'] ?? []);
    return null;
}

function hp_fs_fields($fields) {
    $out = [];
    foreach ($fields as $k => $v) $out[$k] = hp_fs_value($v);
    return $out;
}

// Reads one document. $idToken is the signed-in user's Firebase ID token, if any.
// Returns [httpCode, fieldsArray|null].
function hp_fs_get($path, $idToken = null) {
    $pid = hp_site_config()['projectId'];
    $url = "https://firestore.googleapis.com/v1/projects/{$pid}/databases/(default)/documents/{$path}";
    $headers = $idToken ? ["Authorization: Bearer {$idToken}"] : [];
    [$code, $body] = hp_http('GET', $url, $headers);
    if ($code !== 200) return [$code, null];
    $doc = json_decode($body, true);
    return [$code, hp_fs_fields($doc['fields'] ?? [])];
}

// Public site settings (contact email, email templates)
function hp_public_settings() {
    [, $fields] = hp_fs_get('config/settings');
    return $fields ?: [];
}

// ── Admin check ───────────────────────────────────────────────────────────────
// The admin page sends the signed-in user's Firebase ID token in X-Firebase-Token
// (a custom header, because some hosts strip Authorization before PHP sees it).
// Firestore itself verifies the token's signature when we read admins/{uid} with
// it, and the security rules only let a user read their own admins entry — so a
// 200 means "valid token, and this user is on the admin list".
function hp_require_admin() {
    $token = trim($_SERVER['HTTP_X_FIREBASE_TOKEN'] ?? '');
    if (!$token || substr_count($token, '.') !== 2) hp_json(['ok' => false, 'error' => 'Please sign in again.'], 401);

    $payload = json_decode(base64_decode(strtr(explode('.', $token)[1], '-_', '+/')), true);
    $uid = $payload['user_id'] ?? $payload['sub'] ?? '';
    if (!preg_match('/^[A-Za-z0-9]{1,128}$/', $uid)) hp_json(['ok' => false, 'error' => 'Please sign in again.'], 401);

    [$code] = hp_fs_get('admins/' . $uid, $token);
    if ($code !== 200) hp_json(['ok' => false, 'error' => 'Not authorized.'], 403);
    return $token;
}

// ── Rate limiting ─────────────────────────────────────────────────────────────
// Sliding window kept in a small file per bucket. $perIp = false makes one
// shared bucket for everyone (a ceiling on total volume).
function hp_rate_limit($bucket, $max, $windowSec, $perIp = true) {
    $who  = $perIp ? ($_SERVER['REMOTE_ADDR'] ?? 'unknown') : '*';
    $file = sys_get_temp_dir() . '/hp-rl-' . md5(__DIR__ . '|' . $bucket . '|' . $who);
    $fp   = @fopen($file, 'c+');
    if (!$fp) return true;   // fail open rather than break the site
    flock($fp, LOCK_EX);
    $now  = time();
    $hits = array_filter(json_decode(stream_get_contents($fp), true) ?: [], function ($t) use ($now, $windowSec) {
        return $t > $now - $windowSec;
    });
    $allowed = count($hits) < $max;
    if ($allowed) $hits[] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode(array_values($hits)));
    flock($fp, LOCK_UN);
    fclose($fp);
    return $allowed;
}
