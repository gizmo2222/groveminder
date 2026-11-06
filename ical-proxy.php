<?php
// ─────────────────────────────────────────────────────────────────────────────
// ical-proxy.php — fetches an iCal feed for the admin's "Sync from Calendar"
// (browsers can't fetch most calendar feeds directly because of CORS).
// Admin only, HTTPS only, never to a private or internal address (each redirect
// hop is checked too), at most 5 MB, and only returns content that is an iCal feed.
// ─────────────────────────────────────────────────────────────────────────────

require __DIR__ . '/hp-lib.php';

hp_require_admin();

function fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

const GM_ICAL_MAX_BYTES     = 5 * 1024 * 1024;
const GM_ICAL_MAX_REDIRECTS = 3;

// True when $ip is a public address. Refuses private, loopback, link-local,
// reserved and "this network" ranges, for IPv4 and IPv6, including IPv4 hidden
// inside an IPv6 address (::ffff:a.b.c.d, 64:ff9b::/96 and 2002::/16).
function gm_ip_is_public($ip) {
    $bin = @inet_pton((string)$ip);
    if ($bin === false) return false;
    if (strlen($bin) === 4) {
        $o = array_values(unpack('C4', $bin));
        if ($o[0] === 0 || $o[0] === 127) return false;                          // 0/8, 127/8
        if ($o[0] === 169 && $o[1] === 254) return false;                         // 169.254/16
        if ($o[0] === 100 && $o[1] >= 64 && $o[1] <= 127) return false;           // 100.64/10 shared
        if ($o[0] >= 224) return false;                                           // multicast, reserved, broadcast
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
    $b = array_values(unpack('C16', $bin));
    if ($bin === str_repeat("\0", 16) || $bin === str_repeat("\0", 15) . "\1") return false;   // ::, ::1
    if ($b[0] === 0xfe && ($b[1] & 0xc0) >= 0x80) return false;                 // fe80::/10 link-local, fec0::/10 site-local
    if (($b[0] & 0xfe) === 0xfc) return false;                                  // fc00::/7 unique local
    if ($b[0] === 0xff) return false;                                           // multicast
    $v4 = null;
    if (substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") $v4 = substr($bin, 12);           // ::ffff:a.b.c.d
    elseif (substr($bin, 0, 12) === "\0\x64\xff\x9b" . str_repeat("\0", 8)) $v4 = substr($bin, 12);   // 64:ff9b::/96
    elseif ($b[0] === 0x20 && $b[1] === 0x02) $v4 = substr($bin, 2, 4);                                // 2002::/16
    elseif (substr($bin, 0, 12) === str_repeat("\0", 12)) return false;                                 // ::a.b.c.d (deprecated)
    if ($v4 !== null) return gm_ip_is_public(inet_ntop($v4));
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
}

// Every A and AAAA address of $host (IPv4 first). A literal address returns itself.
function gm_resolve_host($host) {
    $host = trim($host, '[]');
    if (filter_var($host, FILTER_VALIDATE_IP)) return [$host];
    $v4 = []; $v6 = [];
    foreach ((array)@dns_get_record($host, DNS_A | DNS_AAAA) as $r) {
        if (!empty($r['ip']))   $v4[] = $r['ip'];
        if (!empty($r['ipv6'])) $v6[] = $r['ipv6'];
    }
    foreach ((array)@gethostbynamel($host) as $ip) $v4[] = $ip;
    return array_values(array_unique(array_merge($v4, $v6)));
}

// Checks one URL (the first request, or a redirect hop). Returns the address to
// pin the request to, or an error. Every resolved address must be public.
function gm_check_url($url) {
    $p = parse_url($url);
    if (!filter_var($url, FILTER_VALIDATE_URL) || !$p || strtolower($p['scheme'] ?? '') !== 'https' || empty($p['host'])
        || isset($p['user']) || isset($p['pass'])) {
        return ['ok' => false, 'status' => 400, 'error' => 'Please use an https:// calendar link.'];
    }
    $ips = gm_resolve_host($p['host']);
    if (!$ips) return ['ok' => false, 'status' => 502, 'error' => 'Could not find that calendar address.'];
    foreach ($ips as $ip) {
        if (!gm_ip_is_public($ip)) {
            return ['ok' => false, 'status' => 400, 'error' => 'That calendar address points to a private or internal network, so it can\'t be used.'];
        }
    }
    $host = trim($p['host'], '[]');
    return ['ok' => true, 'host' => $host, 'port' => $p['port'] ?? 443, 'ip' => $ips[0], 'literal' => (bool)filter_var($host, FILTER_VALIDATE_IP)];
}

// Turns a Location header into an absolute https URL, or null.
function gm_redirect_target($base, $location) {
    $location = trim($location);
    if ($location === '' || preg_match('/[\x00-\x20\x7f]/', $location)) return null;
    $p = parse_url($base);
    if (!$p || empty($p['host'])) return null;
    $origin = 'https://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) return preg_match('#^https://#i', $location) ? $location : null;
    if (strpos($location, '//') === 0) return 'https:' . $location;
    if ($location[0] === '/') return $origin . $location;
    $dir = preg_replace('#[^/]*$#', '', $p['path'] ?? '/');
    return $origin . ($dir === '' ? '/' : $dir) . $location;
}

// Collects the body, and stops the transfer as soon as it passes $limit bytes.
function gm_make_writer($limit, &$state) {
    $state = ['bytes' => 0, 'body' => '', 'toobig' => false];
    return function ($ch, $chunk) use ($limit, &$state) {
        $state['bytes'] += strlen($chunk);
        if ($state['bytes'] > $limit) { $state['toobig'] = true; $state['body'] = ''; return 0; }
        $state['body'] .= $chunk;
        return strlen($chunk);
    };
}

// One request with no redirects, pinned to the address that gm_check_url approved.
function gm_curl_fetch($url, $check) {
    $location = '';
    $writer = gm_make_writer(GM_ICAL_MAX_BYTES, $state);
    $ch = curl_init($url);
    $opts = [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'GroveMinder/1.0 iCal-Proxy',
        CURLOPT_WRITEFUNCTION  => $writer,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$location) {
            if (stripos($line, 'location:') === 0) $location = trim(substr($line, 9));
            return strlen($line);
        },
    ];
    if (empty($check['literal'])) {
        $ip = strpos($check['ip'], ':') !== false ? '[' . $check['ip'] . ']' : $check['ip'];
        $opts[CURLOPT_RESOLVE] = [$check['host'] . ':' . $check['port'] . ':' . $ip];
    }
    curl_setopt_array($ch, $opts);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $state['body'], 'location' => $location, 'toobig' => $state['toobig'], 'error' => $err];
}

// Fetches $url, following at most GM_ICAL_MAX_REDIRECTS redirects by hand and
// checking every hop. $fetch(url, check) does one request. Returns ['body' => ...]
// or ['status' => the HTTP code to answer with, 'error' => message].
function gm_fetch_following($url, $fetch = 'gm_curl_fetch') {
    for ($hop = 0; $hop <= GM_ICAL_MAX_REDIRECTS; $hop++) {
        $check = gm_check_url($url);
        if (!$check['ok']) return ['status' => $check['status'], 'error' => $check['error']];
        $r = $fetch($url, $check);
        if (!empty($r['toobig'])) return ['status' => 502, 'error' => 'The calendar feed is larger than 5 MB, so it was not read.'];
        if ($r['code'] >= 300 && $r['code'] < 400 && $r['location'] !== '') {
            $url = gm_redirect_target($url, $r['location']);
            if ($url === null) return ['status' => 502, 'error' => 'The calendar address redirected somewhere that can\'t be used.'];
            continue;
        }
        if ($r['code'] < 200 || $r['code'] >= 300) return ['status' => 502, 'error' => 'Could not fetch the calendar URL'];
        return ['body' => $r['body']];
    }
    return ['status' => 502, 'error' => 'The calendar address redirected too many times.'];
}

$url = trim($_GET['url'] ?? '');
if (!$url) fail(400, 'Missing url parameter');
if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https://#i', $url)) fail(400, 'Please use an https:// calendar link.');

$result = gm_fetch_following($url);
if (!isset($result['body'])) fail($result['status'], $result['error']);
$body = $result['body'];
if (strpos($body, 'BEGIN:VCALENDAR') === false) fail(422, 'URL does not appear to be a valid iCal feed');

header('Content-Type: text/calendar; charset=utf-8');
header('Cache-Control: no-store');
echo $body;
