<?php
/**
 * POST /api/booking.php
 * Airbnb booking request. Computes nights + total, stores as 'pending',
 * emails admin and (if provided) the guest.
 *
 * Fields: property_id, guest_name, guest_email, guest_phone, guests,
 *         check_in, check_out, notes, _csrf, redirect
 */
require __DIR__ . '/../includes/bootstrap.php';

$isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
    || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function booking_fail(bool $ajax, string $msg, string $back = '', int $code = 422): void
{
    if ($ajax) json_out(['ok' => false, 'message' => $msg], $code);
    flash('error', $msg);
    redirect($back ?: 'index.php');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') booking_fail($isAjax, 'Invalid request.', '', 405);
if (!csrf_verify())                        booking_fail($isAjax, 'Session expired. Please refresh and try again.', '', 419);
if (!rate_limit('booking', 10, 3600))      booking_fail($isAjax, 'Too many booking attempts. Please try again later.', '', 429);
if (!recaptcha_ok())                       booking_fail($isAjax, 'Spam check failed. Please try again.', '');

$propertyId = input_int('property_id');
$name  = trim((string) input('guest_name', ''));
$email = trim((string) input('guest_email', ''));
$phone = trim((string) input('guest_phone', ''));
$guests = max(1, (int) input_int('guests', 1));
$checkIn  = input('check_in');
$checkOut = input('check_out');
$notes = trim((string) input('notes', ''));
$back  = (string) input('redirect', '');

$property = $propertyId ? Property::findById($propertyId) : null;
if (!$property || $property['purpose'] !== 'airbnb') {
    booking_fail($isAjax, 'This property is not available for booking.', $back);
}
if ($name === '' || (!is_email($email) && $phone === '')) {
    booking_fail($isAjax, 'Please provide your name and a valid email or phone number.', $back);
}

$ciTs = $checkIn ? strtotime((string) $checkIn) : false;
$coTs = $checkOut ? strtotime((string) $checkOut) : false;
if (!$ciTs || !$coTs || $coTs <= $ciTs) {
    booking_fail($isAjax, 'Please choose a valid check-in and check-out date.', $back);
}
if ($ciTs < strtotime('today')) {
    booking_fail($isAjax, 'Check-in date cannot be in the past.', $back);
}
if ($guests > 0 && !empty($property['airbnb_max_guests']) && $guests > (int) $property['airbnb_max_guests']) {
    booking_fail($isAjax, 'This property allows a maximum of ' . (int) $property['airbnb_max_guests'] . ' guests.', $back);
}

$nights = (int) round(($coTs - $ciTs) / 86400);
$nightly = (float) ($property['rent_price'] ?? $property['price'] ?? 0);
$cleaning = (float) ($property['airbnb_cleaning_fee'] ?? 0);
$total = ($nightly * $nights) + $cleaning;

try {
    Database::run(
        "INSERT INTO bookings (property_id, guest_name, guest_email, guest_phone, guests, check_in, check_out, nights, total_amount, status, notes)
         VALUES (?,?,?,?,?,?,?,?,?,'pending',?)",
        [
            $property['id'], $name, is_email($email) ? $email : null, $phone ?: null,
            $guests, date('Y-m-d', $ciTs), date('Y-m-d', $coTs), $nights, $total, $notes ?: null,
        ]
    );
    $bookingId = (int) Database::conn()->lastInsertId();
} catch (Throwable $e) {
    booking_fail($isAjax, 'We could not submit your booking right now. Please try again.', $back, 500);
}

$cur = $property['currency'] ?? 'KES';
$summary = '<table cellpadding="6" style="font-family:Arial,sans-serif;font-size:14px;border-collapse:collapse">'
    . '<tr><td><strong>Property</strong></td><td>' . e($property['title']) . ' (' . e($property['ref_code']) . ')</td></tr>'
    . '<tr><td><strong>Guest</strong></td><td>' . e($name) . '</td></tr>'
    . '<tr><td><strong>Email</strong></td><td>' . e($email ?: '—') . '</td></tr>'
    . '<tr><td><strong>Phone</strong></td><td>' . e($phone ?: '—') . '</td></tr>'
    . '<tr><td><strong>Guests</strong></td><td>' . $guests . '</td></tr>'
    . '<tr><td><strong>Check-in</strong></td><td>' . e(date('D, d M Y', $ciTs)) . '</td></tr>'
    . '<tr><td><strong>Check-out</strong></td><td>' . e(date('D, d M Y', $coTs)) . '</td></tr>'
    . '<tr><td><strong>Nights</strong></td><td>' . $nights . '</td></tr>'
    . '<tr><td><strong>Estimated total</strong></td><td>' . e(money($total, $cur)) . '</td></tr>'
    . ($notes ? '<tr><td><strong>Notes</strong></td><td>' . nl2br(e($notes)) . '</td></tr>' : '')
    . '</table>';

// Notify admin
notify_admins(
    '🏡 Booking request — ' . $name,
    $property['title'] . "\n" . date('d M', $ciTs) . ' – ' . date('d M', $coTs) . ' · ' . $nights . ' night' . ($nights == 1 ? '' : 's') . ' · ' . $guests . ' guest' . ($guests == 1 ? '' : 's') . ' · ' . ($phone ?: $email),
    'admin/bookings.php', 'booking-' . $bookingId
);
send_mail(setting('contact_email', ADMIN_EMAIL), 'New booking request — ' . SITE_NAME,
    '<h2 style="font-family:Georgia,serif;color:#16150f">New Airbnb booking request</h2>' . $summary);

// Confirm to guest (request received; not yet confirmed)
if (is_email($email)) {
    send_mail($email, 'We received your booking request — ' . SITE_NAME,
        '<h2 style="font-family:Georgia,serif;color:#16150f">Thank you, ' . e($name) . '</h2>'
        . '<p style="font-family:Arial,sans-serif;font-size:14px">We have received your booking request. Our team will review availability and confirm shortly.</p>'
        . $summary
        . '<p style="font-family:Arial,sans-serif;font-size:13px;color:#8a8578">This is a request, not a confirmation. You will receive a separate confirmation email once approved.</p>');
}

$okMsg = 'Your booking request has been sent. We will confirm availability shortly.';
if ($isAjax) json_out(['ok' => true, 'message' => $okMsg, 'id' => $bookingId, 'nights' => $nights, 'total' => $total]);
flash('success', $okMsg);
redirect($back ?: 'index.php');
