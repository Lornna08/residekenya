<?php
/**
 * POST /api/inquiry.php
 * Handles contact-agent, schedule-viewing, and general contact inquiries.
 * Accepts both AJAX (returns JSON) and standard form POST (redirects with flash).
 *
 * Fields: type (contact|viewing|agent|general), name, email, phone, message,
 *         preferred_date (viewing), property_id (optional), _csrf, redirect (optional)
 */
require __DIR__ . '/../includes/bootstrap.php';

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function inquiry_fail(bool $ajax, string $msg, string $back = '', int $code = 422): void
{
    if ($ajax) json_out(['ok' => false, 'message' => $msg], $code);
    flash('error', $msg);
    redirect($back ?: 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inquiry_fail($isAjax, 'Invalid request.', '', 405);
}
if (!csrf_verify()) {
    inquiry_fail($isAjax, 'Session expired. Please refresh and try again.', '', 419);
}
if (!rate_limit('inquiry', 12, 3600)) {
    inquiry_fail($isAjax, 'Too many messages sent. Please try again later.', '', 429);
}
if (!recaptcha_ok()) {
    inquiry_fail($isAjax, 'Spam check failed. Please try again.', '');
}

$type = (string) input('type', 'general');
if (!in_array($type, ['contact', 'viewing', 'agent', 'general'], true)) $type = 'general';

$name    = trim((string) input('name', ''));
$email   = trim((string) input('email', ''));
$phone   = trim((string) input('phone', ''));
$message = trim((string) input('message', ''));
$prefDate = input('preferred_date');
$prefDate = $prefDate ? date('Y-m-d', strtotime((string) $prefDate)) : null;
$propertyId = input_int('property_id');
$back = (string) input('redirect', '');

if ($name === '' || (!is_email($email) && $phone === '')) {
    inquiry_fail($isAjax, 'Please provide your name and a valid email or phone number.', $back);
}

// Resolve property (for context in the notification email)
$property = $propertyId ? Property::findById($propertyId) : null;

try {
    Database::run(
        "INSERT INTO inquiries (property_id, type, name, email, phone, message, preferred_date, ip_address)
         VALUES (?,?,?,?,?,?,?,?)",
        [
            $property['id'] ?? null,
            $type,
            $name,
            is_email($email) ? $email : null,
            $phone ?: null,
            $message ?: null,
            $prefDate,
            client_ip(),
        ]
    );
    $inquiryId = (int) Database::conn()->lastInsertId();
} catch (Throwable $e) {
    inquiry_fail($isAjax, 'We could not send your message right now. Please try again.', $back, 500);
}

// Notify admin
$labelMap = ['contact' => 'Contact', 'viewing' => 'Viewing request', 'agent' => 'Agent enquiry', 'general' => 'General enquiry'];
$subject  = 'New ' . ($labelMap[$type] ?? 'enquiry') . ' — ' . SITE_NAME;
$rows = [
    'Type'     => $labelMap[$type] ?? $type,
    'Name'     => $name,
    'Email'    => $email ?: '—',
    'Phone'    => $phone ?: '—',
    'Property' => $property ? ($property['title'] . ' (' . $property['ref_code'] . ')') : '—',
    'Preferred date' => $prefDate ?: '—',
    'Message'  => $message ?: '—',
];
$body = '<h2 style="font-family:Georgia,serif;color:#16150f">New ' . e($labelMap[$type] ?? 'enquiry') . '</h2><table cellpadding="6" style="font-family:Arial,sans-serif;font-size:14px;border-collapse:collapse">';
foreach ($rows as $k => $v) {
    $body .= '<tr><td style="color:#8a8578;vertical-align:top"><strong>' . e($k) . '</strong></td><td>' . nl2br(e((string) $v)) . '</td></tr>';
}
$body .= '</table>';
send_mail(setting('contact_email', ADMIN_EMAIL), $subject, $body);

$okMsg = $type === 'viewing'
    ? 'Your viewing request has been sent. Our team will confirm shortly.'
    : 'Thank you — your message has been sent. We will be in touch soon.';

if ($isAjax) json_out(['ok' => true, 'message' => $okMsg, 'id' => $inquiryId]);
flash('success', $okMsg);
redirect($back ?: 'index.php');
