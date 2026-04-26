<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method not allowed']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$to      = trim($input['to']      ?? '');
$subject = trim($input['subject'] ?? '');
$body    = trim($input['body']    ?? '');
$replyTo = trim($input['replyTo'] ?? '');

if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) { echo json_encode(['ok'=>false,'error'=>'Invalid recipient']); exit; }
if (!$subject || !$body) { echo json_encode(['ok'=>false,'error'=>'Missing subject or body']); exit; }

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$from = 'noreply@' . $host;
$headers = "From: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8";
if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers .= "\r\nReply-To: {$replyTo}";

$ok = @mail($to, $subject, wordwrap($body, 76, "\r\n", false), $headers);
echo json_encode($ok ? ['ok'=>true] : ['ok'=>false,'error'=>'mail() failed — check server mail configuration']);
