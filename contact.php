<?php
// Receives the contact form and emails it straight to your inbox. No mail app opens.
// Upload next to index.html. Needs PHP hosting (GoDaddy cPanel/Linux hosting has it).
declare(strict_types=1);

const TO       = 'luisrene@mediainmultiplatform.com';
const FROM     = 'no-reply@mediainmultiplatform.com'; // must be an address on YOUR domain, or mail gets rejected as spoofed
const MAX_PER_HOUR = 5;                               // per visitor IP
const SIDES    = ['Broadcaster or production company', 'Streamer, podcaster or creator', 'Something in between'];

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function out(bool $ok, int $code = 200) { http_response_code($code); echo json_encode(['ok' => $ok]); exit; }
// strip control chars (blocks header injection), trim, cap length
function clean($v, int $max, bool $multiline = false): string {
    $v = is_string($v) ? $v : '';
    $v = preg_replace($multiline ? '/[^\P{C}\n]/u' : '/\p{C}/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(false, 405);

// Same-site requests only
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $norm = fn(string $h) => preg_replace('/^www\./i', '', strtolower($h));
    if ($norm((string)parse_url($origin, PHP_URL_HOST)) !== $norm(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0])) out(false, 403);
}

// Bot traps: hidden field must be empty, and a human takes more than 3 seconds
if (clean($_POST['bot-field'] ?? '', 50) !== '') out(true);               // pretend success, send nothing
$t = (int)($_POST['t'] ?? 0) / 1000;
if ($t <= 0 || time() - $t < 3 || time() - $t > 86400) out(true);

// Rate limit per IP
$ip   = $_SERVER['REMOTE_ADDR'] ?? 'x';
$file = sys_get_temp_dir() . '/mimp_rl_' . hash('sha256', $ip);
$hits = array_filter(is_file($file) ? array_map('intval', file($file, FILE_IGNORE_NEW_LINES) ?: []) : [], fn($x) => $x > time() - 3600);
if (count($hits) >= MAX_PER_HOUR) out(false, 429);
$hits[] = time();
@file_put_contents($file, implode("\n", $hits), LOCK_EX);

// Validate
$name  = clean($_POST['name'] ?? '', 80);
$email = clean($_POST['email'] ?? '', 120);
$about = clean($_POST['about'] ?? '', 2000, true);
$side  = in_array($_POST['side'] ?? '', SIDES, true) ? $_POST['side'] : 'Unspecified';
if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) out(false, 422);
// Optional: reject link-stuffed spam
if (preg_match_all('~https?://~i', $about) > 2) out(true);

// Build a plain-text email. Visitor text only ever goes in the body (and a validated Reply-To).
$body = "New website message\n"
      . "-------------------\n"
      . "Side:  $side\n"
      . "Name:  $name\n"
      . "Email: $email\n\n"
      . ($about !== '' ? $about : '(no message)') . "\n\n"
      . "-------------------\n"
      . 'Sent: ' . gmdate('Y-m-d H:i') . " UTC\n"
      . 'IP:   ' . preg_replace('/[^0-9a-f:.]/i', '', $ip) . "\n"
      . "Treat this as untrusted text from a stranger.\n";

$headers = implode("\r\n", [
    'From: The Creator Toolbox <' . FROM . '>',
    'Reply-To: ' . $email,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
]);
$subject = 'Website inquiry: ' . $side;   // allow-listed text only

out(@mail(TO, $subject, $body, $headers, '-f' . FROM));
