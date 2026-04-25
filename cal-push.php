<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$method  = $input['method']  ?? 'none';
$booking = $input['booking'] ?? [];
$cal     = $input['cal']     ?? [];

// ── Helpers ────────────────────────────────────────────────────────────────────

function b64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function makeIcal($booking) {
    $uid  = uniqid('hp-', true) . '@' . ($_SERVER['HTTP_HOST'] ?? 'hollypoppins.com');
    $now  = gmdate('Ymd\THis\Z');
    $date = preg_replace('/[^0-9]/', '', $booking['date'] ?? date('Y-m-d'));
    $time = trim($booking['time'] ?? '');
    $name = htmlspecialchars_decode($booking['name']    ?? 'Client');
    $svc  = htmlspecialchars_decode($booking['service'] ?? 'Booking');

    if ($time && preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
        $h = (int)$m[1]; $mi = (int)$m[2];
        $dtstart = "DTSTART:{$date}T" . sprintf('%02d%02d00', $h, $mi);
        $dtend   = "DTEND:{$date}T"   . sprintf('%02d%02d00', min($h + 2, 23), $mi);
    } else {
        $nextDay = date('Ymd', strtotime(($booking['date'] ?? 'today') . ' +1 day'));
        $dtstart = "DTSTART;VALUE=DATE:{$date}";
        $dtend   = "DTEND;VALUE=DATE:{$nextDay}";
    }

    $lines = ["Client: $name"];
    if (!empty($booking['email'])) $lines[] = "Email: {$booking['email']}";
    if (!empty($booking['phone'])) $lines[] = "Phone: {$booking['phone']}";
    if (!empty($booking['notes'])) $lines[] = "Notes: {$booking['notes']}";
    $desc = implode('\\n', $lines);

    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//HollyPoppins//EN\r\nCALSCALE:GREGORIAN\r\nMETHOD:PUBLISH\r\nBEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:{$now}\r\n{$dtstart}\r\n{$dtend}\r\nSUMMARY:{$svc} — {$name}\r\nDESCRIPTION:{$desc}\r\nSTATUS:CONFIRMED\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}

// ── Email ──────────────────────────────────────────────────────────────────────

function pushEmail($to, $booking, $ical) {
    if (!$to) return ['ok'=>false,'error'=>'No email address configured'];
    $when    = $booking['date'] . ($booking['time'] ? ' at ' . $booking['time'] : '');
    $subject = "Booking confirmed: {$booking['service']} — {$booking['name']} on {$when}";
    $boundary = md5(uniqid());
    $from    = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'hollypoppins.com');

    $plain  = "Booking confirmed.\n\nClient: {$booking['name']}\nService: {$booking['service']}\nDate: {$when}\n";
    if ($booking['email']) $plain .= "Email: {$booking['email']}\n";
    if ($booking['phone']) $plain .= "Phone: {$booking['phone']}\n";
    if ($booking['notes']) $plain .= "Notes: {$booking['notes']}\n";
    $plain .= "\nOpen the attached .ics file to add this to your calendar.";

    $headers = "From: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"";
    $body    = "--{$boundary}\r\nContent-Type: text/plain; charset=utf-8\r\n\r\n{$plain}\r\n";
    $body   .= "--{$boundary}\r\nContent-Type: text/calendar; charset=utf-8; method=PUBLISH\r\nContent-Disposition: attachment; filename=\"booking.ics\"\r\n\r\n{$ical}\r\n";
    $body   .= "--{$boundary}--";

    $ok = @mail($to, $subject, $body, $headers);
    return $ok ? ['ok'=>true] : ['ok'=>false,'error'=>'mail() failed — check PHP mail configuration on your server'];
}

// ── CalDAV ─────────────────────────────────────────────────────────────────────

function caldavRequest($method, $url, $user, $pass, $body = null, $extraHeaders = []) {
    $headers = array_merge(['Content-Type: application/xml; charset=utf-8'], $extraHeaders);
    if ($body !== null) $headers[] = 'Content-Length: ' . strlen($body);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_USERPWD        => "{$user}:{$pass}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body'=>$resp, 'code'=>$code, 'error'=>$err];
}

function xpathQuery($xml, $ns, $query) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadXML($xml);
    $xpath = new DOMXPath($dom);
    foreach ($ns as $prefix => $uri) $xpath->registerNamespace($prefix, $uri);
    return $xpath->query($query);
}

function absoluteUrl($href, $baseUrl) {
    if (strpos($href, 'http') === 0) return $href;
    $p = parse_url($baseUrl);
    return $p['scheme'] . '://' . $p['host'] . $href;
}

function discoverCaldav($url, $user, $pass) {
    $url = rtrim($url, '/') . '/';
    $ns  = ['D'=>'DAV:', 'C'=>'urn:ietf:params:xml:ns:caldav'];

    // Step 1 — find principal
    $xml  = '<?xml version="1.0"?><D:propfind xmlns:D="DAV:"><D:prop><D:current-user-principal/></D:prop></D:propfind>';
    $resp = caldavRequest('PROPFIND', $url, $user, $pass, $xml, ['Depth: 0']);
    if ($resp['error'])    return ['ok'=>false,'error'=>$resp['error']];
    if ($resp['code']==401) return ['ok'=>false,'error'=>'Unauthorized — check your username and password'];
    if ($resp['code'] < 200 || $resp['code'] >= 400) return ['ok'=>false,'error'=>"Server returned HTTP {$resp['code']}"];

    $nodes = xpathQuery($resp['body'], $ns, '//D:current-user-principal/D:href');
    if (!$nodes->length) return ['ok'=>false,'error'=>'Could not find principal in server response'];
    $principalUrl = absoluteUrl(trim($nodes->item(0)->textContent), $url);

    // Step 2 — find calendar home
    $xml  = '<?xml version="1.0"?><D:propfind xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop><C:calendar-home-set/></D:prop></D:propfind>';
    $resp = caldavRequest('PROPFIND', $principalUrl, $user, $pass, $xml, ['Depth: 0']);
    $nodes = xpathQuery($resp['body'], $ns, '//C:calendar-home-set/D:href');
    if (!$nodes->length) return ['ok'=>false,'error'=>'Could not find calendar home'];
    $homeUrl = absoluteUrl(trim($nodes->item(0)->textContent), $url);

    // Step 3 — list calendars
    $xml  = '<?xml version="1.0"?><D:propfind xmlns:D="DAV:" xmlns:C="urn:ietf:params:xml:ns:caldav"><D:prop><D:displayname/><D:resourcetype/></D:prop></D:propfind>';
    $resp = caldavRequest('PROPFIND', $homeUrl, $user, $pass, $xml, ['Depth: 1']);

    libxml_use_internal_errors(true);
    $dom = new DOMDocument(); $dom->loadXML($resp['body']);
    $xpath = new DOMXPath($dom);
    foreach ($ns as $p => $u) $xpath->registerNamespace($p, $u);

    $calendars = [];
    foreach ($xpath->query('//D:response') as $r) {
        if (!$xpath->query('D:propstat/D:prop/D:resourcetype/C:calendar', $r)->length) continue;
        $nameNodes = $xpath->query('D:propstat/D:prop/D:displayname', $r);
        $hrefNodes = $xpath->query('D:href', $r);
        $name = $nameNodes->length ? trim($nameNodes->item(0)->textContent) : 'Unnamed';
        $href = $hrefNodes->length ? trim($hrefNodes->item(0)->textContent) : '';
        if ($href) $calendars[] = ['name' => $name, 'url' => absoluteUrl($href, $url)];
    }

    return ['ok'=>true, 'calendars'=>$calendars];
}

function pushCaldav($url, $user, $pass, $ical) {
    $uid = 'hp-' . bin2hex(random_bytes(8));
    $url = rtrim($url, '/') . "/{$uid}.ics";
    $resp = caldavRequest('PUT', $url, $user, $pass, $ical, ['Content-Type: text/calendar; charset=utf-8']);
    if ($resp['error']) return ['ok'=>false,'error'=>$resp['error']];
    if ($resp['code'] < 200 || $resp['code'] >= 300) return ['ok'=>false,'error'=>"CalDAV returned HTTP {$resp['code']}"];
    return ['ok'=>true];
}

// ── Google Calendar ────────────────────────────────────────────────────────────

function getGoogleToken($sa) {
    $now     = time();
    $header  = b64url(json_encode(['alg'=>'RS256','typ'=>'JWT']));
    $payload = b64url(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'iat'   => $now,
        'exp'   => $now + 3600,
    ]));
    $data = "{$header}.{$payload}";
    $key  = openssl_pkey_get_private($sa['private_key'] ?? '');
    if (!$key) return null;
    openssl_sign($data, $sig, $key, 'SHA256');
    $jwt = "{$data}." . b64url($sig);

    $ctx  = stream_context_create(['http'=>[
        'method'  => 'POST',
        'header'  => 'Content-Type: application/x-www-form-urlencoded',
        'content' => http_build_query(['grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt]),
        'timeout' => 15,
    ]]);
    $resp = @file_get_contents('https://oauth2.googleapis.com/token', false, $ctx);
    if (!$resp) return null;
    return json_decode($resp, true)['access_token'] ?? null;
}

function pushGoogle($saJson, $calId, $booking) {
    $sa = json_decode($saJson, true);
    if (!$sa || empty($sa['private_key'])) return ['ok'=>false,'error'=>'Invalid service account JSON'];

    $token = getGoogleToken($sa);
    if (!$token) return ['ok'=>false,'error'=>'Could not obtain Google access token — check service account JSON and Calendar API is enabled'];

    $date = $booking['date'] ?? date('Y-m-d');
    $time = trim($booking['time'] ?? '');

    if ($time && preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
        $h = (int)$m[1]; $mi = (int)$m[2];
        $tz    = $booking['timezone'] ?? 'America/New_York';
        $start = ['dateTime' => "{$date}T" . sprintf('%02d:%02d:00', $h, $mi),          'timeZone' => $tz];
        $end   = ['dateTime' => "{$date}T" . sprintf('%02d:%02d:00', min($h+2,23), $mi), 'timeZone' => $tz];
    } else {
        $start = ['date' => $date];
        $end   = ['date' => date('Y-m-d', strtotime("$date +1 day"))];
    }

    $desc  = implode("\n", array_filter([
        "Client: {$booking['name']}",
        !empty($booking['email']) ? "Email: {$booking['email']}" : null,
        !empty($booking['phone']) ? "Phone: {$booking['phone']}" : null,
        !empty($booking['notes']) ? "Notes: {$booking['notes']}" : null,
    ]));
    $event = ['summary' => "{$booking['service']} — {$booking['name']}", 'description' => $desc, 'start' => $start, 'end' => $end];

    $url = 'https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calId ?: 'primary') . '/events';
    $ch  = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($event),
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    if ($err)  return ['ok'=>false,'error'=>$err];
    if ($code !== 200 && $code !== 201) {
        $msg = json_decode($resp, true)['error']['message'] ?? "HTTP $code";
        return ['ok'=>false,'error'=>$msg];
    }
    return ['ok'=>true];
}

// ── Router ─────────────────────────────────────────────────────────────────────

$ical = makeIcal($booking);

switch ($method) {
    case 'email':
        echo json_encode(pushEmail($cal['email'] ?? '', $booking, $ical));
        break;

    case 'caldav':
        echo json_encode(pushCaldav($cal['caldavUrl'] ?? '', $cal['caldavUser'] ?? '', $cal['caldavPass'] ?? '', $ical));
        break;

    case 'caldav_discover':
        echo json_encode(discoverCaldav($cal['caldavUrl'] ?? 'https://caldav.icloud.com/', $cal['caldavUser'] ?? '', $cal['caldavPass'] ?? ''));
        break;

    case 'google':
        echo json_encode(pushGoogle($cal['googleJson'] ?? '', $cal['googleCalId'] ?? 'primary', $booking));
        break;

    default:
        echo json_encode(['ok'=>true,'skipped'=>true]);
}
