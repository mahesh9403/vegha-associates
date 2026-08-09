<?php
/* Form handler for the contact, query and careers forms.
 *
 * Submissions go straight from this server to the Firm's inbox -- enquiries and
 * candidate CVs never pass through a third party, which matters for a practice
 * handling confidential financial matters.
 *
 * Answers assets/js/site.js with the same JSON shape the forms already expect:
 * {"success":true|false,"message":"..."}
 *
 * Requires PHP 8.0+. No database, no session, no credentials.
 */
declare(strict_types=1);

const TO_ADDRESS  = 'admin@veghaandassociates.com';
/* The From address must be on the site's own domain or SPF and DKIM will not
   match and the mail is likely to be filtered as spam. The visitor's address
   goes in Reply-To instead, so replying still reaches them. */
const FROM_ADDRESS = 'noreply@veghaandassociates.com';
const FROM_NAME    = 'VEGHA & ASSOCIATES website';

const DATA_DIR      = __DIR__ . '/admin/data';   /* already denied over HTTP */
const LOG_FILE      = DATA_DIR . '/enquiries.log';
const THROTTLE_FILE = DATA_DIR . '/form-throttle.json';
const THROTTLE_MAX    = 5;    /* submissions per IP ... */
const THROTTLE_WINDOW = 900;  /* ... per 15 minutes */

const MAX_FILE_BYTES = 1048576; /* 1 MB, matching the client-side check */
const ALLOWED_CV = ['pdf' => 'application/pdf', 'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'rtf' => 'application/rtf'];

/* Field names that are plumbing rather than content. */
const SKIP_FIELDS = ['_gotcha', 'csrf', 'access_key', 'subject', 'from_name'];

header('Content-Type: application/json; charset=utf-8');

function respond(bool $ok, string $message, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}

/* A header value must never carry a line break, or the sender can inject extra
   headers (a second Bcc, say) and turn this into an open relay. */
function header_safe(string $v): string {
    return trim(str_replace(["\r", "\n", "\0"], ' ', $v));
}

function client_ip(): string {
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/* Returns false when this IP has already sent THROTTLE_MAX in the window. */
function throttle_ok(): bool {
    $ip  = client_ip();
    $now = time();
    $all = [];
    if (is_readable(THROTTLE_FILE)) {
        $all = json_decode((string)file_get_contents(THROTTLE_FILE), true) ?: [];
    }
    foreach ($all as $k => $stamps) {
        $all[$k] = array_values(array_filter($stamps, fn($t) => $t > $now - THROTTLE_WINDOW));
        if (!$all[$k]) unset($all[$k]);
    }
    $mine = $all[$ip] ?? [];
    if (count($mine) >= THROTTLE_MAX) {
        @file_put_contents(THROTTLE_FILE, json_encode($all), LOCK_EX);
        return false;
    }
    $mine[] = $now;
    $all[$ip] = $mine;
    if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);
    @file_put_contents(THROTTLE_FILE, json_encode($all), LOCK_EX);
    return true;
}

function pretty_label(string $key): string {
    return ucfirst(trim(str_replace('_', ' ', $key)));
}

/* Builds the readable body of the email from whatever fields the form posted,
   so adding a field to a form needs no change here. */
function build_body(array $fields, string $formName, ?string $cvName): string {
    $lines = [$formName, str_repeat('=', strlen($formName)), ''];
    foreach ($fields as $k => $v) {
        $v = trim((string)$v);
        if ($v === '') continue;
        $lines[] = pretty_label($k) . ': ' . $v;
    }
    if ($cvName !== null) {
        $lines[] = '';
        $lines[] = 'Attachment: ' . $cvName;
    }
    $lines[] = '';
    $lines[] = '--';
    $lines[] = 'Sent from the website contact forms on ' . date('j F Y \a\t H:i');
    $lines[] = 'Reply to this email to answer the sender directly.';
    return implode("\r\n", $lines);
}

/* Returns [headers, body]. Kept pure so it can be exercised without sending. */
function build_message(string $body, string $replyTo, ?array $cv): array {
    $headers = [
        'From: ' . header_safe(FROM_NAME) . ' <' . FROM_ADDRESS . '>',
        'Reply-To: ' . header_safe($replyTo),
        'MIME-Version: 1.0',
        'X-Mailer: veghaandassociates.com',
    ];
    if ($cv === null) {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        return [implode("\r\n", $headers), $body];
    }
    $boundary = '=_' . bin2hex(random_bytes(16));
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    $parts = [
        '--' . $boundary,
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        '',
        $body,
        '',
        '--' . $boundary,
        'Content-Type: ' . $cv['mime'] . '; name="' . header_safe($cv['name']) . '"',
        'Content-Transfer-Encoding: base64',
        'Content-Disposition: attachment; filename="' . header_safe($cv['name']) . '"',
        '',
        chunk_split(base64_encode($cv['data'])),
        '--' . $boundary . '--',
        '',
    ];
    return [implode("\r\n", $headers), implode("\r\n", $parts)];
}

/* ------------------------------------------------------------------ run ---- */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(false, 'Method not allowed.', 405);
}

/* Honeypot: a real person never fills a field they cannot see. Report success so
   the bot has nothing to learn and does not come back to try something else. */
if (trim((string)($_POST['_gotcha'] ?? '')) !== '') {
    respond(true, 'Thank you. Your message has been sent.');
}

$email = trim((string)($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $email !== header_safe($email)) {
    respond(false, 'Please provide a valid email address.', 422);
}
$name = trim((string)($_POST['name'] ?? ''))
     ?: trim(((string)($_POST['first_name'] ?? '')) . ' ' . ((string)($_POST['last_name'] ?? '')));
if (trim($name) === '') {
    respond(false, 'Please provide your name.', 422);
}

/* Throttle only once the submission is known to be valid, so the limit measures
   mail actually sent. Counting rejects would let a mistyped address, or anyone
   probing with junk, use up a real visitor's allowance. */
if (!throttle_ok()) {
    respond(false, 'Too many submissions from this connection. Please try again shortly, or email ' . TO_ADDRESS . '.', 429);
}

$formName = header_safe((string)($_POST['form_name'] ?? 'Website message'));

$fields = [];
foreach ($_POST as $k => $v) {
    if (in_array($k, SKIP_FIELDS, true) || $k === 'form_name') continue;
    if (is_array($v)) $v = implode(', ', $v);
    $fields[$k] = $v;
}

/* Optional CV on the careers form. Unlike images this cannot be re-encoded to
   neutralise it, so restrict it by extension and size and pass it through. */
$cv = null;
if (!empty($_FILES['resume']['tmp_name']) && is_uploaded_file($_FILES['resume']['tmp_name'])) {
    if (($_FILES['resume']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        respond(false, 'The attachment did not upload correctly. Please try again.', 422);
    }
    if ($_FILES['resume']['size'] > MAX_FILE_BYTES) {
        respond(false, 'The attachment is larger than 1 MB.', 422);
    }
    $ext = strtolower(pathinfo((string)$_FILES['resume']['name'], PATHINFO_EXTENSION));
    if (!isset(ALLOWED_CV[$ext])) {
        respond(false, 'Attach a PDF, DOC, DOCX or RTF file.', 422);
    }
    /* Rebuild the filename rather than trusting the one supplied. */
    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$_FILES['resume']['name']) ?: "cv.$ext";
    $cv = [
        'name' => $safeName,
        'mime' => ALLOWED_CV[$ext],
        'data' => (string)file_get_contents($_FILES['resume']['tmp_name']),
    ];
}

$subject = header_safe($formName . ' | veghaandassociates.com');
$body    = build_body($fields, $formName, $cv['name'] ?? null);
[$headers, $message] = build_message($body, $email, $cv);

/* Log before sending, so a delivery failure never loses the enquiry. */
if (!is_dir(DATA_DIR)) @mkdir(DATA_DIR, 0755, true);
@file_put_contents(
    LOG_FILE,
    '[' . date('c') . '] ' . client_ip() . ' ' . $subject . "\r\n" . $body . "\r\n\r\n",
    FILE_APPEND | LOCK_EX
);

/* The -f envelope sender keeps the return path on our own domain. */
$sent = @mail(TO_ADDRESS, $subject, $message, $headers, '-f' . FROM_ADDRESS);

if ($sent) {
    respond(true, 'Thank you. Your message has been sent. A partner will respond shortly.');
}
respond(false, 'We could not send that just now. Please email ' . TO_ADDRESS . ' directly.', 500);
