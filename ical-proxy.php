<?php
header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-store');

$url = trim($_GET['url'] ?? '');

if (!$url) { http_response_code(400); echo 'Missing url parameter'; exit; }
if (!filter_var($url, FILTER_VALIDATE_URL)) { http_response_code(400); echo 'Invalid URL'; exit; }
if (!preg_match('#^https://#i', $url)) { http_response_code(400); echo 'Only HTTPS URLs are allowed'; exit; }

$ctx = stream_context_create(['http' => [
    'timeout'    => 10,
    'user_agent' => 'GroveMinder/1.0 iCal-Proxy',
    'follow_location' => true,
]]);

$body = @file_get_contents($url, false, $ctx);

if ($body === false) { http_response_code(502); echo 'Could not fetch the calendar URL'; exit; }
if (strpos($body, 'BEGIN:VCALENDAR') === false) { http_response_code(422); echo 'URL does not appear to be a valid iCal feed'; exit; }

echo $body;
