<?php
// ─────────────────────────────────────────────────────────────────────────────
// ical-proxy.php — fetches an iCal feed for the admin's "Sync from Calendar"
// (browsers can't fetch most calendar feeds directly because of CORS).
// Admin only, HTTPS only, and only returns content that is an iCal feed.
// ─────────────────────────────────────────────────────────────────────────────

require __DIR__ . '/hp-lib.php';

hp_require_admin();

function fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

$url = trim($_GET['url'] ?? '');
if (!$url) fail(400, 'Missing url parameter');
if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $url)) fail(400, 'Please use an https:// calendar link.');

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_MAXREDIRS       => 3,
    CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_TIMEOUT         => 10,
    CURLOPT_USERAGENT       => 'GroveMinder/1.0 iCal-Proxy',
    CURLOPT_MAXFILESIZE     => 5 * 1024 * 1024,
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false || $code < 200 || $code >= 300) fail(502, 'Could not fetch the calendar URL');
if (strpos($body, 'BEGIN:VCALENDAR') === false) fail(422, 'URL does not appear to be a valid iCal feed');

header('Content-Type: text/calendar; charset=utf-8');
header('Cache-Control: no-store');
echo $body;
