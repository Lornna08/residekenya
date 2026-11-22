<?php
/** admin/push.php — register this device for notifications, send a test, read unread counts. */
require __DIR__ . '/../includes/bootstrap.php';
if (!is_logged_in()) json_out(['ok' => false, 'error' => 'Please sign in again.'], 401);
ensure_schema();

$action = (string) input('action', 'counts');

if ($action === 'counts') {
    $n = Database::row("SELECT
        (SELECT COUNT(*) FROM inquiries WHERE is_read = 0) AS inquiries,
        (SELECT COUNT(*) FROM bookings WHERE status = 'pending') AS bookings,
        (SELECT COUNT(*) FROM submissions WHERE status = 'new') AS submissions");
    $n = array_map('intval', $n);
    $n['total'] = array_sum($n);
    json_out(['ok' => true] + $n);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'error' => 'Invalid request.'], 405);
require_csrf();
$body = json_decode((string) file_get_contents('php://input'), true) ?: [];
$sub = $body['subscription'] ?? null;

if ($action === 'subscribe') {
    if (!push_enabled()) json_out(['ok' => false, 'error' => 'Notifications are not configured on the server.'], 500);
    $endpoint = (string) ($sub['endpoint'] ?? '');
    $p256 = (string) ($sub['keys']['p256dh'] ?? '');
    $auth = (string) ($sub['keys']['auth'] ?? '');
    if (!preg_match('#^https://#', $endpoint) || $p256 === '' || $auth === '') json_out(['ok' => false, 'error' => 'Invalid subscription.'], 422);
    Database::run(
        "INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent) VALUES (?,?,?,?,?)
         ON CONFLICT (endpoint) DO UPDATE SET user_id = EXCLUDED.user_id, p256dh = EXCLUDED.p256dh, auth = EXCLUDED.auth, user_agent = EXCLUDED.user_agent",
        [current_user()['id'], $endpoint, $p256, $auth, substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250)]
    );
    log_activity('enable_notifications', 'user', current_user()['id']);
    json_out(['ok' => true]);
}

if ($action === 'unsubscribe') {
    $endpoint = (string) ($sub['endpoint'] ?? ($body['endpoint'] ?? ''));
    Database::run("DELETE FROM push_subscriptions WHERE endpoint = ?", [$endpoint]);
    json_out(['ok' => true]);
}

if ($action === 'test') {
    $n = notify_admins('Notifications are on ✅', 'You will get alerts here for new inquiries, viewing requests, bookings and property submissions.', 'admin/', 'test');
    json_out(['ok' => $n > 0, 'sent' => $n, 'error' => $n ? null : 'No device received it. Turn notifications off and on again.']);
}

json_out(['ok' => false, 'error' => 'Unknown action.'], 400);
