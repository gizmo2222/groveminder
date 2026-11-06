<?php
// Unit checks for the calendar security fixes. Nothing here touches the network:
// it loads the helper functions out of ical-proxy.php and cal-push.php (neither can
// be included, since both run a request when loaded) and calls them directly.
// Usage: php calendar_unit.php <repo root>
$root = $argv[1] ?? dirname(__DIR__, 2);
$pass = 0; $fail = 0;

function t($name, $ok) {
    global $pass, $fail;
    if ($ok) { $pass++; echo "ok   $name\n"; } else { $fail++; echo "FAIL $name\n"; }
}

// Evaluates the named top-level functions and constants from a source file.
function load($file, $names) {
    $src = str_replace("\r\n", "\n", file_get_contents($file));
    foreach ($names as $n) {
        if (!preg_match('/^(function ' . preg_quote($n, '/') . '\(.*?^\})/ms', $src, $m)
            && !preg_match('/^(const ' . preg_quote($n, '/') . '\s*=[^;]*;)/m', $src, $m)) { echo "FAIL could not find $n in $file\n"; exit(1); }
        eval($m[1]);
    }
}

load("$root/ical-proxy.php", ['GM_ICAL_MAX_BYTES', 'GM_ICAL_MAX_REDIRECTS', 'gm_ip_is_public', 'gm_resolve_host', 'gm_check_url',
    'gm_redirect_target', 'gm_make_writer', 'gm_fetch_following']);
load("$root/cal-push.php", ['caldavRegistrableDomain', 'caldavHostAllowed', 'absoluteUrl', 'loadCalendarSecrets', 'applyCalendarSecrets']);

// ── L2: address checks ─────────────────────────────────────────────────────────
foreach (['127.0.0.1', '127.8.8.8', '10.0.0.5', '172.16.4.1', '192.168.1.1', '169.254.169.254', '0.0.0.0', '0.1.2.3', '100.64.0.1',
          '::1', '::', 'fe80::1', 'fc00::1', 'fd12:3456::1', '::ffff:127.0.0.1', '::ffff:10.1.1.1', '64:ff9b::7f00:1', '2002:7f00:1::1'] as $ip) {
    t("refuses $ip", !gm_ip_is_public($ip));
}
foreach (['93.184.216.34', '8.8.8.8', '2606:4700:4700::1111', '::ffff:8.8.8.8'] as $ip) {
    t("allows $ip", gm_ip_is_public($ip));
}
t('refuses a name that resolves to loopback (localhost)', !gm_check_url('https://localhost/a.ics')['ok']);
t('refuses an https loopback URL', !gm_check_url('https://127.0.0.1:9/')['ok']);
t('refuses a bracketed IPv6 loopback URL', !gm_check_url('https://[::1]/')['ok']);
t('refuses the cloud metadata address', !gm_check_url('https://169.254.169.254/latest/')['ok']);
t('refuses http://', !gm_check_url('http://93.184.216.34/')['ok']);
t('refuses a login in the URL', !gm_check_url('https://user:pw@93.184.216.34/')['ok']);
t('allows a public literal address', gm_check_url('https://93.184.216.34/a.ics')['ok']);

// ── L2: redirect hops ──────────────────────────────────────────────────────────
t('redirect: relative path',        gm_redirect_target('https://93.184.216.34/a/b.ics', 'c.ics') === 'https://93.184.216.34/a/c.ics');
t('redirect: absolute path',        gm_redirect_target('https://93.184.216.34:8443/a/b.ics', '/z.ics') === 'https://93.184.216.34:8443/z.ics');
t('redirect: protocol-relative',    gm_redirect_target('https://93.184.216.34/', '//8.8.8.8/x') === 'https://8.8.8.8/x');
t('redirect: http target dropped',  gm_redirect_target('https://93.184.216.34/', 'http://8.8.8.8/') === null);
t('redirect: file target dropped',  gm_redirect_target('https://93.184.216.34/', 'file:///etc/passwd') === null);

$log = [];
$fake = function ($map) use (&$log) {
    return function ($url, $check) use ($map, &$log) {
        $log[] = $url;
        return $map[$url] ?? ['code' => 404, 'body' => '', 'location' => '', 'toobig' => false];
    };
};
$ok   = ['code' => 200, 'body' => "BEGIN:VCALENDAR\nEND:VCALENDAR", 'location' => '', 'toobig' => false];
$redir = function ($to, $code = 302) { return ['code' => $code, 'body' => '', 'location' => $to, 'toobig' => false]; };

$log = [];
$r = gm_fetch_following('https://93.184.216.34/a', $fake(['https://93.184.216.34/a' => $redir('https://127.0.0.1/secret')]));
t('redirect to loopback is refused', isset($r['error']) && $r['status'] === 400);
t('redirect to loopback is never requested', $log === ['https://93.184.216.34/a']);

$log = [];
$r = gm_fetch_following('https://93.184.216.34/a', $fake(['https://93.184.216.34/a' => $redir('https://[::1]/'), ]));
t('redirect to IPv6 loopback is refused', isset($r['error']) && $log === ['https://93.184.216.34/a']);

$log = [];
$r = gm_fetch_following('https://93.184.216.34/a', $fake(['https://93.184.216.34/a' => $redir('http://8.8.8.8/')]));
t('redirect to http is refused', isset($r['error']) && count($log) === 1);

$r = gm_fetch_following('https://93.184.216.34/a', $fake(['https://93.184.216.34/a' => $redir('/b'), 'https://93.184.216.34/b' => $ok]));
t('a public redirect is followed', ($r['body'] ?? '') === $ok['body']);

$chain = ['https://93.184.216.34/1' => $redir('/2'), 'https://93.184.216.34/2' => $redir('/3'), 'https://93.184.216.34/3' => $redir('/4'),
          'https://93.184.216.34/4' => $ok, 'https://93.184.216.34/5' => $redir('/6')];
$log = [];
$r = gm_fetch_following('https://93.184.216.34/1', $fake($chain));
t('three redirects are allowed', ($r['body'] ?? '') === $ok['body'] && count($log) === 4);
$chain['https://93.184.216.34/4'] = $redir('/5');
$log = [];
$r = gm_fetch_following('https://93.184.216.34/1', $fake($chain));
t('a fourth redirect is refused', isset($r['error']) && count($log) === 4);

// ── L3: size cap ───────────────────────────────────────────────────────────────
$w = gm_make_writer(10, $st);
t('writer accepts a small chunk', $w(null, 'abcde') === 5 && $st['body'] === 'abcde');
t('writer accepts up to the limit', $w(null, 'fghij') === 5 && !$st['toobig']);
t('writer aborts past the limit', $w(null, 'k') === 0 && $st['toobig'] && $st['body'] === '');
$r = gm_fetch_following('https://93.184.216.34/a', function ($u, $c) { return ['code' => 200, 'body' => '', 'location' => '', 'toobig' => true]; });
t('an oversize body answers 502 with a message', ($r['status'] ?? 0) === 502 && strpos($r['error'], '5 MB') !== false);
t('the cap is 5 MB', GM_ICAL_MAX_BYTES === 5 * 1024 * 1024);

// ── L4: CalDAV hosts ───────────────────────────────────────────────────────────
$base = 'https://caldav.icloud.com/';
$allowed = ['/123/principal/', 'https://caldav.icloud.com/123/', 'https://p42-caldav.icloud.com/123/calendars/', 'https://P42-CalDAV.iCloud.com./x', 'rel/path/', '//p07-caldav.icloud.com/x'];
foreach ($allowed as $h) {
    try { $u = absoluteUrl($h, $base); t("allows $h", strpos($u, 'https://') === 0); }
    catch (RuntimeException $e) { t("allows $h", false); }
}
$refused = ['https://evil.example/x', 'https://icloud.com.evil.example/x', 'https://evilicloud.com/x', 'https://icloud.com/x', 'http://p42-caldav.icloud.com/x',
            'https://caldav.icloud.com@evil.example/x', 'https://user@caldav.icloud.com/x', '//evil.example/x', 'ftp://caldav.icloud.com/x', "https://caldav.icloud.com/x\r\nHost: evil"];
foreach ($refused as $h) {
    try { absoluteUrl($h, $base); t('refuses ' . json_encode($h), false); }
    catch (RuntimeException $e) { t('refuses ' . json_encode($h), true); }
}
t('registrable domain is the last two labels', caldavRegistrableDomain('p42-caldav.icloud.com') === 'icloud.com');
t('an IP host only matches itself', caldavHostAllowed('203.0.113.5', '203.0.113.5') && !caldavHostAllowed('203.0.113.6', '203.0.113.5'));
t('a same-domain sibling is allowed', caldavHostAllowed('dav.example.org', 'cal.example.org'));
t('a different domain is refused', !caldavHostAllowed('cal.example.org.evil.test', 'cal.example.org'));

// ── L12: server secrets file ───────────────────────────────────────────────────
$dir = sys_get_temp_dir() . '/gm-unit-' . bin2hex(random_bytes(4)); mkdir($dir);
t('no file means null', loadCalendarSecrets("$dir/calendar.php") === null);
file_put_contents("$dir/calendar.php", "<?php return ['caldavUser' => 'srv', 'caldavPass' => 'srvpw', 'googleJson' => ['private_key' => 'k']];");
$s = loadCalendarSecrets("$dir/calendar.php");
t('the file is read', is_array($s) && $s['caldavUser'] === 'srv');
$cal = applyCalendarSecrets(['caldavUser' => 'attacker', 'caldavPass' => 'x', 'googleJson' => '{}', 'caldavUrl' => 'https://p1-caldav.icloud.com/', 'email' => 'a@b.test'], $s);
t('the file wins over request credentials', $cal['caldavUser'] === 'srv' && $cal['caldavPass'] === 'srvpw');
t('an array googleJson becomes JSON', json_decode($cal['googleJson'], true) === ['private_key' => 'k']);
t('other request fields are kept', $cal['caldavUrl'] === 'https://p1-caldav.icloud.com/' && $cal['email'] === 'a@b.test');
$cal = applyCalendarSecrets(['caldavUser' => 'attacker', 'caldavPass' => 'x'], ['caldavUser' => 'only-user']);
t('a credential missing from the file is not taken from the request', $cal['caldavUser'] === 'only-user' && !isset($cal['caldavPass']));
file_put_contents("$dir/calendar.php", '<?php return "nope";');
t('a file that does not return an array is an error', isset(loadCalendarSecrets("$dir/calendar.php")['error']));
file_put_contents("$dir/calendar.php", '<?php throw new Exception("boom");');
t('a file that throws is an error', isset(loadCalendarSecrets("$dir/calendar.php")['error']));
unlink("$dir/calendar.php"); rmdir($dir);

echo "unit pass $pass fail $fail\n";
exit($fail ? 1 : 0);
