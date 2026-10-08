<?php
// ─────────────────────────────────────────────────────────────────────────────
// mailer.php — sends the emails for a form submission.
//
// The browser only says which form was sent and what the visitor typed. The
// server picks the recipients and builds each message from the saved templates,
// so this can't be used to send arbitrary mail to arbitrary people:
//   • "…_admin" messages go only to the contact email saved in Settings
//   • "…_visitor" messages go only to the address the visitor typed, using a
//     fixed template, and are rate limited
//   • confirm/decline messages require a signed-in admin
// ─────────────────────────────────────────────────────────────────────────────

require __DIR__ . '/hp-lib.php';

hp_require_post();
$in = hp_input();

// Honeypot: a hidden field real visitors never fill in. Pretend it worked.
if (!empty($in['website'])) hp_json(['ok' => true]);

const FORMS = [
    'booking'           => ['visitor' => 'booking_visitor',   'admin' => 'booking_admin'],
    'question'          => ['visitor' => 'question_visitor',  'admin' => 'question_admin'],
    'rates'             => ['visitor' => 'rates_visitor',     'admin' => 'rates_admin'],
    'reference'         => ['visitor' => 'reference_visitor'],
    'booking_confirmed' => ['visitor' => 'booking_confirmed', 'adminOnly' => true],
    'booking_declined'  => ['visitor' => 'booking_declined',  'adminOnly' => true],
];

$form = $in['form'] ?? '';
if (!isset(FORMS[$form])) hp_json(['ok' => false, 'error' => 'Unknown form'], 400);
$spec = FORMS[$form];

if (!empty($spec['adminOnly'])) {
    hp_require_admin();
} else {
    // Generous for a real person, useless for a spammer
    if (!hp_rate_limit('mail', 5, 3600) || !hp_rate_limit('mail-all', 100, 86400, false)) {
        hp_json(['ok' => false, 'error' => 'Too many messages — please try again later.'], 429);
    }
}

// ── Template variables ────────────────────────────────────────────────────────
$raw  = is_array($in['vars'] ?? null) ? $in['vars'] : [];
$vars = [];
foreach (['name', 'email', 'phone', 'service', 'date'] as $k) $vars[$k] = hp_one_line($raw[$k] ?? '', 100);
foreach (['notes', 'question', 'details'] as $k)          $vars[$k] = hp_multi_line($raw[$k] ?? '', 2000);
$time = hp_one_line($raw['time'] ?? '', 20);
$vars['time']     = $time !== '' ? " at {$time}" : '';
$vars['siteName'] = hp_site_config()['siteName'];

$visitorEmail = filter_var($vars['email'], FILTER_VALIDATE_EMAIL) ?: '';

// ── Templates: saved in Settings, falling back to the defaults ────────────────
$settings  = hp_public_settings();
$saved     = is_array($settings['emailTemplates'] ?? null) ? $settings['emailTemplates'] : [];
$defaults  = json_decode(@file_get_contents(__DIR__ . '/email-templates.json'), true) ?: [];
$adminEmail = filter_var($settings['email'] ?? '', FILTER_VALIDATE_EMAIL) ?: '';

function fill($str, $vars) {
    return preg_replace_callback('/\{(\w+)\}/', function ($m) use ($vars) { return $vars[$m[1]] ?? ''; }, (string)$str);
}

function send_template($type, $to, $vars, $replyTo, $saved, $defaults) {
    $tmpl = (!empty($saved[$type]['subject']) || !empty($saved[$type]['body'])) ? $saved[$type] : ($defaults[$type] ?? null);
    if (!$tmpl || !$to) return true;
    $subject = hp_one_line(fill($tmpl['subject'] ?? '', $vars), 200);
    $body    = fill($tmpl['body'] ?? '', $vars);
    $headers = 'From: ' . hp_mail_from() . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8";
    if ($replyTo) $headers .= "\r\nReply-To: {$replyTo}";
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    // Wrap each line on its own, so existing line breaks reset the count
    $lines = array_map(function ($l) { return wordwrap($l, 76, "\r\n", false); }, explode("\n", str_replace("\r\n", "\n", $body)));
    return @mail($to, $encodedSubject, implode("\r\n", $lines), $headers);
}

$ok = true;
if (!empty($spec['visitor']) && $visitorEmail) {
    $ok = send_template($spec['visitor'], $visitorEmail, $vars, $adminEmail, $saved, $defaults) && $ok;
}
if (!empty($spec['admin']) && $adminEmail) {
    $ok = send_template($spec['admin'], $adminEmail, $vars, $visitorEmail, $saved, $defaults) && $ok;
}

hp_json($ok ? ['ok' => true] : ['ok' => false, 'error' => 'mail() failed — check server mail configuration']);
