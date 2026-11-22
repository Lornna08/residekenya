<?php
/**
 * POST /api/submit-property.php
 * "List Your Property" client submission. Stores submission, saves up to 8 photos,
 * emails admin. Standard form POST (redirects with flash).
 */
require __DIR__ . '/../includes/bootstrap.php';

$back = 'list-property.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { flash('error', 'Invalid request.'); redirect($back); }
if (!csrf_verify()) { flash('error', 'Session expired. Please refresh and try again.'); redirect($back); }
if (!rate_limit('submission', 6, 3600)) { flash('error', 'Too many submissions. Please try again later.'); redirect($back); }
if (!recaptcha_ok()) { flash('error', 'Spam check failed. Please try again.'); redirect($back); }

$fullName = trim((string) input('full_name', ''));
$phone    = trim((string) input('phone', ''));
$email    = trim((string) input('email', ''));
$countyId = input_int('county_id');
$countyText = trim((string) input('county_text', ''));
$propertyType = trim((string) input('property_type', ''));
$propertyStatus = (string) input('property_status', 'selling');
if (!in_array($propertyStatus, ['selling', 'renting', 'airbnb'], true)) $propertyStatus = 'selling';
$description = trim((string) input('description', ''));
$bedrooms  = input_int('bedrooms');
$bathrooms = input_int('bathrooms');
$location  = trim((string) input('location', ''));
$expectedPrice = input_float('expected_price');
$mapsUrl = trim((string) input('maps_url', ''));
$preferredContact = (string) input('preferred_contact', 'phone');
if (!in_array($preferredContact, ['phone', 'email', 'whatsapp'], true)) $preferredContact = 'phone';

if ($fullName === '' || $phone === '') {
    flash('error', 'Please provide at least your name and phone number.');
    redirect($back);
}
if ($mapsUrl !== '' && !filter_var($mapsUrl, FILTER_VALIDATE_URL)) $mapsUrl = '';

// Handle photo uploads (multiple: photos[])
$photoPaths = [];
if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
    $count = min(8, count($_FILES['photos']['name']));
    for ($i = 0; $i < $count; $i++) {
        if (($_FILES['photos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $one = [
            'name'     => $_FILES['photos']['name'][$i],
            'type'     => $_FILES['photos']['type'][$i],
            'tmp_name' => $_FILES['photos']['tmp_name'][$i],
            'error'    => $_FILES['photos']['error'][$i],
            'size'     => $_FILES['photos']['size'][$i],
        ];
        $stored = save_upload($one, 'submissions', ALLOWED_IMAGE_EXT);
        if ($stored) $photoPaths[] = $stored;
    }
}

try {
    Database::run(
        "INSERT INTO submissions
         (full_name, phone, email, county_id, county_text, property_type, property_status,
          description, bedrooms, bathrooms, location, expected_price, maps_url,
          preferred_contact, photos_json, ip_address)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        [
            $fullName, $phone, is_email($email) ? $email : null,
            $countyId, $countyText ?: null, $propertyType ?: null, $propertyStatus,
            $description ?: null, $bedrooms, $bathrooms, $location ?: null,
            $expectedPrice, $mapsUrl ?: null, $preferredContact,
            $photoPaths ? json_encode($photoPaths, JSON_UNESCAPED_SLASHES) : null,
            client_ip(),
        ]
    );
} catch (Throwable $e) {
    flash('error', 'We could not submit your property right now. Please try again.');
    redirect($back);
}

// Notify admin
$statusLabel = ['selling' => 'For sale', 'renting' => 'For rent', 'airbnb' => 'Airbnb'][$propertyStatus];
$body = '<h2 style="font-family:Georgia,serif;color:#16150f">New property submission</h2>'
    . '<table cellpadding="6" style="font-family:Arial,sans-serif;font-size:14px;border-collapse:collapse">'
    . '<tr><td><strong>Name</strong></td><td>' . e($fullName) . '</td></tr>'
    . '<tr><td><strong>Phone</strong></td><td>' . e($phone) . '</td></tr>'
    . '<tr><td><strong>Email</strong></td><td>' . e($email ?: '—') . '</td></tr>'
    . '<tr><td><strong>Intent</strong></td><td>' . e($statusLabel) . '</td></tr>'
    . '<tr><td><strong>Type</strong></td><td>' . e($propertyType ?: '—') . '</td></tr>'
    . '<tr><td><strong>Location</strong></td><td>' . e($location ?: '—') . ' / ' . e($countyText ?: '—') . '</td></tr>'
    . '<tr><td><strong>Beds / Baths</strong></td><td>' . ($bedrooms ?? '—') . ' / ' . ($bathrooms ?? '—') . '</td></tr>'
    . '<tr><td><strong>Expected price</strong></td><td>' . e($expectedPrice !== null ? money($expectedPrice) : '—') . '</td></tr>'
    . '<tr><td><strong>Preferred contact</strong></td><td>' . e($preferredContact) . '</td></tr>'
    . '<tr><td><strong>Photos</strong></td><td>' . count($photoPaths) . ' uploaded</td></tr>'
    . '<tr><td><strong>Description</strong></td><td>' . nl2br(e($description ?: '—')) . '</td></tr>'
    . '</table>';
send_mail(setting('contact_email', ADMIN_EMAIL), 'New property submission — ' . SITE_NAME, $body);
notify_admins(
    '🏠 New property submitted — ' . $fullName,
    trim(($propertyType ?: 'Property') . ' · ' . $propertyStatus . ($location ? ' · ' . $location : '') . ' · ' . $phone),
    'admin/submissions.php', 'submission-' . time()
);

flash('success', 'Thank you! Your property has been submitted. Our team will review it and contact you shortly.');
redirect($back);
